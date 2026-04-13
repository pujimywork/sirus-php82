<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['lab-actions-modal'];

    /* =======================
     | State
     * ======================= */
    public string $checkupNo = '';
    public string $activeTab = 'PemeriksaanLab'; // PemeriksaanLab | PemeriksaanLuar | Obat
    public array $headerData = [];

    // -- Tab Badge Counts --
    public int $countDtl = 0;
    public int $countOutDtl = 0;
    public int $countObat = 0;

    // -- Ringkasan Biaya --
    public int $sumPemeriksaan = 0;
    public int $sumPemeriksaanLuar = 0;
    public int $sumObat = 0;
    public int $sumTotal = 0;

    // -- Sub-Tab Menu --
    public array $EmrMenuLab = [
        ['ermMenuId' => 'PemeriksaanLab', 'ermMenuName' => 'Pemeriksaan Laboratorium'],
        ['ermMenuId' => 'PemeriksaanLuar', 'ermMenuName' => 'Pemeriksaan Luar'],
        ['ermMenuId' => 'Obat', 'ermMenuName' => 'Obat dan Bahan'],
    ];

    /* =======================
     | Mount
     * ======================= */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* =======================
     | Open from parent
     * ======================= */
    #[On('lab-actions.open')]
    public function openActions(string $checkupNo): void
    {
        // Role check
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses ke modul Laboratorium.');
            return;
        }

        $this->checkupNo = $checkupNo;
        $this->activeTab = 'PemeriksaanLab';

        $this->loadHeader();
        $this->loadCounts();
        $this->loadSums();

        $this->incrementVersion('lab-actions-modal');
        $this->dispatch('open-modal', name: 'lab-actions');
    }

    /* =======================
     | Close
     * ======================= */
    public function closeActions(): void
    {
        $this->dispatch('close-modal', name: 'lab-actions');
        $this->reset(['checkupNo', 'headerData', 'countDtl', 'countOutDtl', 'countObat']);
    }

    /* =======================
     | LOAD HEADER
     * ======================= */
    private function loadHeader(): void
    {
        $header = DB::table('lbtxn_checkuphdrs as a')
            ->join('rsmst_pasiens as c', 'a.reg_no', '=', 'c.reg_no')
            ->leftJoin('rsmst_doctors as f', 'a.dr_id', '=', 'f.dr_id')
            ->select(
                'a.checkup_no',
                DB::raw("to_char(a.checkup_date,'dd/mm/yyyy hh24:mi:ss') as checkup_date"),
                'a.reg_no',
                'c.reg_name',
                'c.sex',
                DB::raw("to_char(c.birth_date,'dd/mm/yyyy') as birth_date"),
                'c.address',
                'a.dr_id',
                'f.dr_name',
                'a.emp_id',
                'a.checkup_status',
                'a.status_rjri',
                'a.ref_no',
                'a.checkup_kesimpulan',
            )
            ->where('a.checkup_no', $this->checkupNo)
            ->first();

        $this->headerData = $header ? (array) $header : [];
    }

    /* =======================
     | LOAD COUNTS (tab badges)
     * ======================= */
    private function loadCounts(): void
    {
        if (empty($this->checkupNo)) {
            return;
        }

        $this->countDtl = (int) DB::table('lbtxn_checkupdtls')
            ->where('checkup_no', $this->checkupNo)
            ->count();

        $this->countOutDtl = (int) DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->checkupNo)
            ->count();

        $this->countObat = (int) DB::table('lbtxn_checkupobats')
            ->where('checkup_no', $this->checkupNo)
            ->count();
    }

    /* =======================
     | LOAD SUMS (ringkasan biaya)
     * ======================= */
    private function loadSums(): void
    {
        if (empty($this->checkupNo)) {
            return;
        }

        // Total pemeriksaan lab (price dari LBTXN_CHECKUPDTLS)
        $this->sumPemeriksaan = (int) DB::table('lbtxn_checkupdtls')
            ->where('checkup_no', $this->checkupNo)
            ->sum('price');

        // Total pemeriksaan luar (labout_price dari LBTXN_CHECKUPOUTDTLS)
        $this->sumPemeriksaanLuar = (int) DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->checkupNo)
            ->sum('labout_price');

        // Total obat/bahan (qty * price dari LBTXN_CHECKUPOBATS)
        $this->sumObat = (int) DB::table('lbtxn_checkupobats')
            ->where('checkup_no', $this->checkupNo)
            ->selectRaw('NVL(SUM(NVL(price, 0) * NVL(qty, 0)), 0) as total')
            ->value('total');

        $this->sumTotal = $this->sumPemeriksaan + $this->sumPemeriksaanLuar + $this->sumObat;
    }

    /* =======================
     | Listener: refresh counts on child tab update
     * ======================= */
    #[On('lab-tab.updated')]
    public function onLabTabUpdated(): void
    {
        $this->loadCounts();
        $this->loadSums();
    }

    /* =======================
     | UPDATE STATUS CHECKUP
     * ======================= */
    public function updateCheckupStatus(string $status): void
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        $currentStatus = $this->headerData['checkup_status'] ?? '';

        // -- Proses Administrasi (P -> C) --
        if ($status === 'C') {
            if ($currentStatus === 'F') {
                $this->dispatch('toast', type: 'error', message: 'Data pasien sudah dibatalkan, tidak dapat melanjutkan.');
                return;
            }

            if ($currentStatus !== 'P') {
                $this->dispatch('toast', type: 'error', message: 'Data pasien sudah tersimpan.');
                return;
            }

            try {
                DB::transaction(function () {
                    $hdr = DB::table('lbtxn_checkuphdrs')
                        ->where('checkup_no', $this->checkupNo)
                        ->lockForUpdate()
                        ->first();

                    if (!$hdr || $hdr->checkup_status !== 'P') {
                        throw new \RuntimeException('Status sudah berubah, silakan refresh.');
                    }

                    $statusRjri = strtoupper($hdr->status_rjri ?? '');
                    $refNo = $hdr->ref_no;
                    $checkupDate = $hdr->checkup_date;

                    // Hitung total biaya lab (pemeriksaan + luar + obat)
                    $totalCheckup = (int) DB::table('lbtxn_checkupdtls')
                        ->where('checkup_no', $this->checkupNo)
                        ->sum('price');

                    $totalCheckupOut = (int) DB::table('lbtxn_checkupoutdtls')
                        ->where('checkup_no', $this->checkupNo)
                        ->sum('labout_price');

                    $totalBahanAlat = (int) DB::table('lbtxn_checkupobats')
                        ->where('checkup_no', $this->checkupNo)
                        ->selectRaw('NVL(SUM(NVL(price, 0) * NVL(qty, 0)), 0) as total')
                        ->value('total');

                    $totalLabPrice = $totalCheckup + $totalCheckupOut + $totalBahanAlat;

                    $labDesc = 'CHECKUP PERTANGGAL ' . $checkupDate . ' /NO CHECKUP ' . $this->checkupNo;

                    // Insert biaya lab ke tabel transaksi sesuai layanan (RJ/UGD/RI)
                    if ($statusRjri === 'RJ' && $refNo) {
                        $dtlNo = DB::scalar('SELECT NVL(MAX(lab_dtl) + 1, 1) FROM rstxn_rjlabs');
                        DB::table('rstxn_rjlabs')->insert([
                            'lab_desc' => $labDesc,
                            'lab_dtl' => $dtlNo,
                            'lab_price' => $totalLabPrice,
                            'rj_no' => $refNo,
                            'checkup_no' => $this->checkupNo,
                        ]);
                    } elseif ($statusRjri === 'UGD' && $refNo) {
                        $dtlNo = DB::scalar('SELECT NVL(MAX(lab_dtl) + 1, 1) FROM rstxn_ugdlabs');
                        DB::table('rstxn_ugdlabs')->insert([
                            'lab_desc' => $labDesc,
                            'lab_dtl' => $dtlNo,
                            'lab_price' => $totalLabPrice,
                            'rj_no' => $refNo,
                            'checkup_no' => $this->checkupNo,
                        ]);
                    } elseif ($statusRjri === 'RI' && $refNo) {
                        $dtlNo = DB::scalar('SELECT NVL(MAX(lab_dtl) + 1, 1) FROM rstxn_rilabs');
                        DB::table('rstxn_rilabs')->insert([
                            'lab_desc' => $labDesc,
                            'lab_dtl' => $dtlNo,
                            'lab_price' => $totalLabPrice,
                            'rihdr_no' => $refNo,
                            'checkup_no' => $this->checkupNo,
                            'lab_date' => $checkupDate,
                        ]);
                    }

                    // Update header: status, emp_id, waktu_masuk
                    $updateData = ['checkup_status' => 'C'];

                    if (empty($hdr->emp_id)) {
                        $authEmpId = auth()->user()->emp_id ?? null;
                        if ($authEmpId) {
                            $updateData['emp_id'] = $authEmpId;
                        }
                    }

                    if (empty($hdr->waktu_masuk_pelayanan)) {
                        $updateData['waktu_masuk_pelayanan'] = DB::raw('SYSDATE');
                    }

                    DB::table('lbtxn_checkuphdrs')
                        ->where('checkup_no', $this->checkupNo)
                        ->update($updateData);
                });

                $this->loadHeader();
                $this->loadCounts();
                $this->loadSums();
                $this->dispatch('refresh-after-lab.saved');
                $regName = $this->headerData['reg_name'] ?? '';
                $this->dispatch('toast', type: 'success', message: "Data Pasien {$regName} sudah tersimpan.");
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'Gagal memproses: ' . $e->getMessage());
            }

            return;
        }

        // -- Selesai Hasil Lab (C -> H) --
        if ($status === 'H') {
            if ($currentStatus === 'H') {
                $this->dispatch('toast', type: 'error', message: 'Hasil LAB sudah tersimpan.');
                return;
            }

            if ($currentStatus !== 'C') {
                $this->dispatch('toast', type: 'error', message: 'Status harus Proses sebelum bisa diselesaikan.');
                return;
            }

            // Validasi emp_id harus terisi
            $empId = DB::table('lbtxn_checkuphdrs')
                ->where('checkup_no', $this->checkupNo)
                ->value('emp_id');

            if (empty($empId)) {
                $this->dispatch('toast', type: 'error', message: 'Kolom pemeriksa masih kosong.');
                return;
            }

            try {
                DB::transaction(function () {
                    $hdr = DB::table('lbtxn_checkuphdrs')
                        ->where('checkup_no', $this->checkupNo)
                        ->lockForUpdate()
                        ->first();

                    if (!$hdr || $hdr->checkup_status !== 'C') {
                        throw new \RuntimeException('Status sudah berubah, silakan refresh.');
                    }

                    $updateData = ['checkup_status' => 'H'];

                    // Set waktu_masuk_pelayanan dari checkup_date jika belum ada
                    if (empty($hdr->waktu_masuk_pelayanan)) {
                        $updateData['waktu_masuk_pelayanan'] = DB::raw(
                            "TO_DATE(TO_CHAR(checkup_date,'dd/mm/yyyy hh24:mi:ss'),'dd/mm/yyyy hh24:mi:ss')"
                        );
                    }

                    // Set waktu_selesai_pelayanan
                    if (empty($hdr->waktu_selesai_pelayanan)) {
                        $updateData['waktu_selesai_pelayanan'] = DB::raw('SYSDATE');
                    }

                    DB::table('lbtxn_checkuphdrs')
                        ->where('checkup_no', $this->checkupNo)
                        ->update($updateData);
                });

                $this->loadHeader();
                $this->dispatch('refresh-after-lab.saved');
                $this->dispatch('toast', type: 'success', message: 'Proses hasil LAB sudah tersimpan.');
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
            }

            return;
        }
    }

    /* =======================
     | SAVE KESIMPULAN
     * ======================= */
    public function saveKesimpulan(string $value): void
    {
        DB::table('lbtxn_checkuphdrs')
            ->where('checkup_no', $this->checkupNo)
            ->update(['checkup_kesimpulan' => $value]);

        $this->headerData['checkup_kesimpulan'] = $value;
        $this->dispatch('toast', type: 'success', message: 'Kesimpulan berhasil disimpan.');
    }

    /* =======================
     | BATALKAN TRANSAKSI (H/C -> F)
     * ======================= */
    public function batalkanTransaksi(): void
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        $currentStatus = $this->headerData['checkup_status'] ?? '';

        // Sudah dibatalkan
        if ($currentStatus === 'F') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah dibatalkan.');
            return;
        }

        // Hanya bisa batal dari H atau C
        if (!in_array($currentStatus, ['H', 'C'])) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tidak bisa dibatalkan dari status ini.');
            return;
        }

        try {
            DB::transaction(function () {
                $hdr = DB::table('lbtxn_checkuphdrs')
                    ->where('checkup_no', $this->checkupNo)
                    ->lockForUpdate()
                    ->first();

                if (!$hdr) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }

                if ($hdr->checkup_status === 'F') {
                    throw new \RuntimeException('Transaksi sudah dibatalkan.');
                }

                $statusRjri = strtoupper($hdr->status_rjri ?? '');
                $refNo = $hdr->ref_no;

                // Cek status transaksi induk (RJ/UGD/RI)
                if ($statusRjri === 'RJ' && $refNo) {
                    $rjStatus = DB::table('rstxn_rjhdrs')->where('rj_no', $refNo)->value('rj_status');
                    if ($rjStatus === 'L') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi RJ sudah ditutup.');
                    }
                    if ($rjStatus === 'F') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi RJ sudah dibatalkan.');
                    }
                    if ($rjStatus === 'I') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi RJ ditransfer ke rawat inap.');
                    }
                    // Hapus biaya lab dari RJ
                    DB::table('rstxn_rjlabs')->where('checkup_no', $this->checkupNo)->delete();
                } elseif ($statusRjri === 'UGD' && $refNo) {
                    $ugdStatus = DB::table('rstxn_ugdhdrs')->where('rj_no', $refNo)->value('rj_status');
                    if ($ugdStatus === 'L') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi UGD sudah ditutup.');
                    }
                    if ($ugdStatus === 'F') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi UGD sudah dibatalkan.');
                    }
                    if ($ugdStatus === 'I') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi UGD ditransfer ke rawat inap.');
                    }
                    // Hapus biaya lab dari UGD
                    DB::table('rstxn_ugdlabs')->where('checkup_no', $this->checkupNo)->delete();
                } elseif ($statusRjri === 'RI' && $refNo) {
                    $riStatus = DB::table('rstxn_rihdrs')->where('rihdr_no', $refNo)->value('ri_status');
                    if ($riStatus === 'P') {
                        throw new \RuntimeException('Tidak bisa membatalkan, transaksi RI sudah ditutup.');
                    }
                    // Hapus biaya lab dari RI
                    DB::table('rstxn_rilabs')->where('checkup_no', $this->checkupNo)->delete();
                }

                // Reset status lab ke P, hapus waktu pelayanan
                DB::table('lbtxn_checkuphdrs')
                    ->where('checkup_no', $this->checkupNo)
                    ->update([
                        'checkup_status' => 'P',
                        'waktu_masuk_pelayanan' => null,
                        'waktu_selesai_pelayanan' => null,
                    ]);
            });

            $this->loadHeader();
            $this->loadCounts();
            $this->loadSums();
            $this->dispatch('refresh-after-lab.saved');
            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    /* =======================
     | CETAK HASIL LABORATORIUM
     * ======================= */
    public function cetakHasilLab(): mixed
    {
        if (empty($this->checkupNo)) {
            return null;
        }

        $header = collect(
            DB::select("
                SELECT DISTINCT a.emp_id, a.checkup_no,
                       to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') AS checkup_date,
                       a.reg_no, reg_name, a.dr_id, dr_name,
                       sex, birth_date, c.address, emp_name,
                       waktu_selesai_pelayanan, checkup_kesimpulan
                FROM lbtxn_checkuphdrs a
                JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
                JOIN rsmst_doctors f ON a.dr_id = f.dr_id
                LEFT JOIN immst_employers g ON a.emp_id = g.emp_id
                WHERE a.checkup_no = :cno
            ", ['cno' => $this->checkupNo]),
        )->first();

        if (!$header) {
            $this->dispatch('toast', type: 'error', message: 'Data pemeriksaan tidak ditemukan.');
            return null;
        }

        $txn = DB::select("
            SELECT b.clabitem_id, clabitem_desc, clab_desc, app_seq, item_seq,
                   lab_result, unit_desc, unit_convert, item_code,
                   normal_f, normal_m, high_limit_m, high_limit_f,
                   low_limit_m, low_limit_f, lowhigh_status, lab_result_status,
                   sex, a.dr_id, dr_name, a.emp_id, emp_name
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupdtls b ON a.checkup_no = b.checkup_no
            JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
            JOIN lbmst_clabitems d ON b.clabitem_id = d.clabitem_id
            JOIN lbmst_clabs e ON d.clab_id = e.clab_id
            JOIN rsmst_doctors f ON a.dr_id = f.dr_id
            LEFT JOIN immst_employers g ON a.emp_id = g.emp_id
            WHERE a.checkup_no = :cno
              AND nvl(hidden_status,'N') = 'N'
            ORDER BY app_seq, item_seq, clabitem_desc
        ", ['cno' => $this->checkupNo]);

        $txnLuar = DB::select("
            SELECT ('  ' || labout_desc) AS labout_desc, labout_result, labout_normal
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupoutdtls b ON a.checkup_no = b.checkup_no
            WHERE a.checkup_no = :cno
            ORDER BY labout_dtl, labout_desc
        ", ['cno' => $this->checkupNo]);

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.penunjang.laboratorium-display.laboratorium-display-print',
            compact('header', 'txn', 'txnLuar')
        )->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn() => print $pdf->output(),
            'hasil-laboratorium-' . $this->checkupNo . '.pdf'
        );
    }

    /* =======================
     | ROLE CHECK
     * ======================= */
    private function isAllowedRole(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Laborat']);
    }
};
?>

<div>
    {{-- Modal Lab Actions --}}
    <x-modal name="lab-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full"
            wire:key="{{ $this->renderKey('lab-actions-modal', [$checkupNo ?: 'empty']) }}">

            {{-- MODAL HEADER --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-green/15">
                            <svg class="w-5 h-5 text-brand-green dark:text-brand-lime" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Transaksi Laboratorium
                            </h2>
                            <p class="text-xs text-gray-500">No. Checkup:
                                <span class="font-mono font-medium">{{ $checkupNo }}</span>
                            </p>
                        </div>
                    </div>

                    {{-- TENGAH: Ringkasan Biaya --}}
                    <div
                        class="flex-1 p-2 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-3">

                            {{-- Grid biaya --}}
                            <div class="grid flex-1 grid-cols-3 gap-1.5">
                                @foreach ([
                                    ['label' => 'Pemeriksaan', 'value' => $sumPemeriksaan],
                                    ['label' => 'Pemeriksaan Luar', 'value' => $sumPemeriksaanLuar],
                                    ['label' => 'Obat dan Bahan', 'value' => $sumObat],
                                ] as $item)
                                    <div
                                        class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">
                                            {{ $item['label'] }}</p>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                                            Rp {{ number_format($item['value']) }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Total Tagihan --}}
                            <div
                                class="flex-shrink-0 px-5 py-3 text-right border rounded-2xl bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20">
                                <p
                                    class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                    Total Tagihan
                                </p>
                                <p
                                    class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums whitespace-nowrap">
                                    Rp {{ number_format($sumTotal) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- STATUS BADGE + CLOSE --}}
                    <div class="flex items-center gap-3 flex-shrink-0">
                        @if (!empty($headerData))
                            @php
                                $st = $headerData['checkup_status'] ?? '';
                                $stText = match($st) {
                                    'H' => 'Selesai',
                                    'C' => 'Proses',
                                    'P' => 'Terdaftar',
                                    default => '-',
                                };
                                $stVariant = match($st) {
                                    'H' => 'success',
                                    'C' => 'warning',
                                    default => 'gray',
                                };
                            @endphp
                            <x-badge :variant="$stVariant">{{ $stText }}</x-badge>
                        @endif

                        <x-secondary-button type="button" wire:click="closeActions" class="!p-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <div class="grid grid-cols-1 gap-3">

                        {{-- Display Pasien --}}
                        <div>
                            <livewire:pages::transaksi.penunjang.laborat.display-pasien-laborat.display-pasien-laborat
                                :checkupNo="$checkupNo"
                                wire:key="display-pasien-laborat-{{ $checkupNo }}" />
                        </div>

                        {{-- SUB-TAB --}}
                        <div x-data="{ tab: @entangle('activeTab') }"
                            class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                            <div class="flex flex-wrap p-2 border-b border-gray-200 dark:border-gray-700">
                                @foreach ($EmrMenuLab as $menu)
                                    <button type="button" x-on:click="tab = '{{ $menu['ermMenuId'] }}'"
                                        x-bind:class="tab === '{{ $menu['ermMenuId'] }}'
                                            ?
                                            'border-b-2 border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime font-semibold bg-brand-green/5 dark:bg-brand-lime/5' :
                                            'border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                                        class="px-4 py-2.5 -mb-px text-sm transition-all whitespace-nowrap rounded-t-lg">
                                        {{ $menu['ermMenuName'] }}
                                        @php
                                            $count = match($menu['ermMenuId']) {
                                                'PemeriksaanLab' => $countDtl,
                                                'PemeriksaanLuar' => $countOutDtl,
                                                'Obat' => $countObat,
                                                default => 0,
                                            };
                                        @endphp
                                        @if ($count > 0)
                                            <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-brand-green/20 text-brand-green">{{ $count }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            <div class="p-4 min-h-[300px]">

                                {{-- TAB 1: PEMERIKSAAN LAB --}}
                                <div x-show="tab === 'PemeriksaanLab'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    @php
                                        $labStatus = $headerData['checkup_status'] ?? 'P';
                                        // P = administrasi (bisa tambah/hapus item, tidak bisa input hasil)
                                        // C = proses (bisa input hasil, tidak bisa tambah/hapus item)
                                        // H = selesai (semua terkunci)
                                    @endphp
                                    <livewire:pages::transaksi.penunjang.laborat.pemeriksaan-laborat
                                        :checkupNo="$checkupNo"
                                        :sex="$headerData['sex'] ?? 'L'"
                                        :labStatus="$labStatus"
                                        wire:key="tab-pemeriksaan-laborat-{{ $checkupNo }}" />
                                </div>

                                {{-- TAB 2: PEMERIKSAAN LUAR --}}
                                <div x-show="tab === 'PemeriksaanLuar'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.penunjang.laborat.pemeriksaan-luar-laborat
                                        :checkupNo="$checkupNo"
                                        :labStatus="$labStatus"
                                        wire:key="tab-pemeriksaan-luar-laborat-{{ $checkupNo }}" />
                                </div>

                                {{-- TAB 3: OBAT --}}
                                <div x-show="tab === 'Obat'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.penunjang.laborat.obat-laborat
                                        :checkupNo="$checkupNo"
                                        :labStatus="$labStatus"
                                        wire:key="tab-obat-laborat-{{ $checkupNo }}" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MODAL FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KESIMPULAN --}}
                    <div class="flex items-center gap-2 flex-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Kesimpulan:</label>
                        <x-text-input type="text"
                            value="{{ $headerData['checkup_kesimpulan'] ?? '' }}"
                            wire:change="saveKesimpulan($event.target.value)"
                            class="flex-1 text-sm"
                            placeholder="Masukkan kesimpulan..." />
                    </div>

                    {{-- KANAN: STATUS BUTTONS --}}
                    <div class="flex items-center gap-2">
                        @php $st = $headerData['checkup_status'] ?? ''; @endphp

                        {{-- Batal (terpisah jauh dari tombol utama) --}}
                        @if ($st === 'H')
                            <x-secondary-button type="button" wire:click="batalkanTransaksi"
                                wire:loading.attr="disabled" wire:target="batalkanTransaksi"
                                wire:confirm="Apakah anda ingin membatalkan transaksi ini?"
                                class="text-xs !bg-red-50 !text-red-700 !border-red-300 hover:!bg-red-100">
                                <span wire:loading.remove wire:target="batalkanTransaksi" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    Batalkan Transaksi
                                </span>
                                <span wire:loading wire:target="batalkanTransaksi" class="flex items-center gap-1.5">
                                    <x-loading /> Membatalkan...
                                </span>
                            </x-secondary-button>

                            <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                        @endif

                        {{-- Tombol utama --}}
                        @if ($st === 'P')
                            <x-secondary-button type="button" wire:click="updateCheckupStatus('C')"
                                wire:loading.attr="disabled"
                                class="text-xs !bg-amber-50 !text-amber-700 !border-amber-300 hover:!bg-amber-100">
                                <span wire:loading.remove wire:target="updateCheckupStatus('C')">Proses Administrasi</span>
                                <span wire:loading wire:target="updateCheckupStatus('C')" class="flex items-center gap-1.5">
                                    <x-loading /> Memproses Administrasi...
                                </span>
                            </x-secondary-button>
                        @elseif ($st === 'C')
                            <x-primary-button type="button" wire:click="updateCheckupStatus('H')"
                                wire:loading.attr="disabled"
                                class="text-xs">
                                <span wire:loading.remove wire:target="updateCheckupStatus('H')">Simpan Hasil Laboratorium</span>
                                <span wire:loading wire:target="updateCheckupStatus('H')" class="flex items-center gap-1.5">
                                    <x-loading /> Menyimpan Hasil...
                                </span>
                            </x-primary-button>
                        @elseif ($st === 'H')
                            <x-badge variant="success">Sudah Selesai</x-badge>
                            <x-primary-button type="button" wire:click="cetakHasilLab"
                                wire:loading.attr="disabled" wire:target="cetakHasilLab"
                                class="text-xs">
                                <span wire:loading.remove wire:target="cetakHasilLab" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak Hasil Laboratorium
                                </span>
                                <span wire:loading wire:target="cetakHasilLab" class="flex items-center gap-1.5">
                                    <x-loading /> Mencetak...
                                </span>
                            </x-primary-button>
                        @endif

                        <x-secondary-button type="button" wire:click="closeActions">
                            Tutup
                        </x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
