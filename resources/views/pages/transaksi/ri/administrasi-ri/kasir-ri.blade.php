<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kasir-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    // ── Ringkasan Biaya ──
    public int $totalAll       = 0;
    public int $angsuranAwal   = 0;
    public int $riDiskon       = 0;
    public int $totalSetelahDiskon = 0;
    public int $sisaTagihan    = 0;
    public int $kembalian      = 0;

    // ── Detail Sum (untuk display) ──
    public int $sumRiVisit      = 0;
    public int $sumRiKonsul     = 0;
    public int $sumRiJasaMedis  = 0;
    public int $sumRiJasaDokter = 0;
    public int $sumRiLab        = 0;
    public int $sumRiRad        = 0;
    public int $sumRiTrfUgdRj   = 0;
    public int $sumRiLainLain   = 0;
    public int $sumRiOk         = 0;
    public int $sumRiRoom       = 0;
    public int $sumRiCService   = 0;
    public int $sumRiPerawatan  = 0;
    public int $sumRiBonResep   = 0;
    public int $sumRiObatPinjam = 0;
    public int $sumRiRtnObat    = 0;
    public int $sumAdminAge     = 0;
    public int $sumAdminStatus  = 0;

    // ── Form Pulang ──
    public ?string $exitDate = null;
    public ?string $outNo    = null;   // kode keterangan keluar
    public ?string $outDesc  = null;   // deskripsi keterangan keluar

    // ── Form Kasir ──
    public ?string $accId   = null;
    public ?string $accName = null;
    public ?int    $bayar   = null;

    // ── Status Transaksi ──
    public ?string $riStatus     = null;   // I / P
    public ?string $statusPulang = null;   // L / H

    // ── Step control ──
    public bool $tglPulangSudahDiproses = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->riHdrNo) {
            $this->loadKasirRI($this->riHdrNo);
        }
    }

    /* ===============================
     | LISTENER
     =============================== */
    #[On('administrasi-kasir-ri.updated')]
    public function onAdministrasiKasirUpdated(): void
    {
        if ($this->riHdrNo) {
            $this->loadKasirRI($this->riHdrNo);
        }
    }

    /* ===============================
     | LOV KETERANGAN KELUAR
     =============================== */
    #[On('lov.selected.outs-kasir-ri')]
    public function onOutsSelected(string $target, ?array $payload): void
    {
        $this->outNo   = $payload['out_no']   ?? null;
        $this->outDesc = $payload['out_desc'] ?? null;
        $this->resetErrorBag('outNo');
        $this->dispatch('focus-lov-kas-kasir-ri');
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-kasir-ri')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId   = $payload['acc_id']   ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        $this->dispatch('focus-input-bayar-ri');
    }

    /* ===============================
     | LOAD KASIR RI
     =============================== */
    public function loadKasirRI(int $riHdrNo): void
    {
        $this->resetKasir();
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $this->dataDaftarRI = $this->findDataRI($riHdrNo) ?? [];

        if (empty($this->dataDaftarRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $hdr = DB::table('rstxn_rihdrs')
            ->select('ri_status', 'status_pulang', 'ri_diskon', 'acc_id', 'exit_date', 'out_no')
            ->where('rihdr_no', $riHdrNo)
            ->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        $this->riStatus     = $hdr->ri_status;
        $this->statusPulang = $hdr->status_pulang;
        $this->riDiskon     = (int) ($hdr->ri_diskon ?? 0);
        $this->outNo = $hdr->out_no;
        if ($hdr->out_no) {
            $this->outDesc = DB::table('rsmst_outs')->where('out_no', $hdr->out_no)->value('out_desc');
        }

        if ($hdr->exit_date) {
            $this->exitDate = Carbon::parse($hdr->exit_date)->format('d/m/Y H:i:s');
        } else {
            $this->exitDate = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        if ($hdr->acc_id) {
            $this->accId   = $hdr->acc_id;
            $this->accName = DB::table('acmst_accounts')
                ->where('acc_id', $hdr->acc_id)
                ->value('acc_name') ?? $hdr->acc_id;
        }

        // Cek apakah trfrooms sudah punya end_date (tgl pulang sudah diproses)
        $maxTrfr = $this->getMaxTrfrNo($riHdrNo);
        if ($maxTrfr) {
            $endDate = DB::table('rsmst_trfrooms')
                ->where('trfr_no', $maxTrfr)
                ->value('end_date');
            $this->tglPulangSudahDiproses = !is_null($endDate);
        }

        if ($this->checkRIStatus($riHdrNo)) {
            $this->isFormLocked = true;
        }

        $this->hitungTotal();
        $this->incrementVersion('kasir-ri');
    }

    private function resetKasir(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRI', 'bayar', 'accId', 'accName', 'riStatus', 'statusPulang', 'exitDate', 'outNo', 'outDesc']);
        $this->resetVersion();
        $this->isFormLocked             = false;
        $this->tglPulangSudahDiproses   = false;
        $this->totalAll                 = 0;
        $this->angsuranAwal             = 0;
        $this->riDiskon                 = 0;
        $this->totalSetelahDiskon       = 0;
        $this->sisaTagihan              = 0;
        $this->kembalian                = 0;
    }

    /* ===============================
     | HITUNG TOTAL
     =============================== */
    public function hitungTotal(): void
    {
        if (!$this->riHdrNo) {
            return;
        }

        $n = $this->riHdrNo;

        $hdr = DB::table('rstxn_rihdrs')
            ->select('admin_age', 'admin_status')
            ->where('rihdr_no', $n)
            ->first();

        $this->sumAdminAge    = (int) ($hdr->admin_age    ?? 0);
        $this->sumAdminStatus = (int) ($hdr->admin_status ?? 0);

        $this->sumRiVisit = (int) DB::table('rstxn_rivisits')
            ->where('rihdr_no', $n)->sum('visit_price');

        $this->sumRiKonsul = (int) DB::table('rstxn_rikonsuls')
            ->where('rihdr_no', $n)->sum('konsul_price');

        $this->sumRiJasaMedis = (int) DB::table('rstxn_riactparams')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(actp_price * actp_qty),0) as total')->value('total');

        $this->sumRiJasaDokter = (int) DB::table('rstxn_riactdocs')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(actd_price * actd_qty),0) as total')->value('total');

        $this->sumRiLab = (int) DB::table('rstxn_rilabs')
            ->where('rihdr_no', $n)->sum('lab_price');

        $this->sumRiRad = (int) DB::table('rstxn_riradiologs')
            ->where('rihdr_no', $n)->sum('rirad_price');

        // Transfer dari UGD/RJ — ambil semua kolom
        $this->sumRiTrfUgdRj = (int) DB::table('rstxn_ritempadmins')
            ->where('rihdr_no', $n)
            ->selectRaw('nvl(sum(
                nvl(rj_admin,0) + nvl(poli_price,0) + nvl(acte_price,0) +
                nvl(actp_price,0) + nvl(actd_price,0) + nvl(obat,0) +
                nvl(rad,0) + nvl(lab,0) + nvl(other,0) + nvl(rs_admin,0)
            ),0) as total')
            ->value('total');

        $this->sumRiLainLain = (int) DB::table('rstxn_riothers')
            ->where('rihdr_no', $n)->sum('other_price');

        $this->sumRiOk = (int) DB::table('rstxn_rioks')
            ->where('rihdr_no', $n)->sum('ok_price');

        $room = DB::table('rsmst_trfrooms')
            ->where('rihdr_no', $n)
            ->selectRaw("nvl(sum(room_price      * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as room_total")
            ->selectRaw("nvl(sum(common_service  * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as cs_total")
            ->selectRaw("nvl(sum(perawatan_price * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as perwt_total")
            ->first();

        $this->sumRiRoom      = (int) ($room->room_total  ?? 0);
        $this->sumRiCService  = (int) ($room->cs_total    ?? 0);
        $this->sumRiPerawatan = (int) ($room->perwt_total ?? 0);

        $this->sumRiBonResep = (int) DB::table('rstxn_ribonobats')
            ->where('rihdr_no', $n)->sum('ribon_price');

        $this->sumRiRtnObat = (int) DB::table('rstxn_riobatrtns')
            ->where('rihdr_no', $n)
            ->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total');

        $this->sumRiObatPinjam = (int) DB::table('rstxn_riobats')
            ->where('rihdr_no', $n)
            ->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total');

        $this->totalAll =
            $this->sumRiOk +
            $this->sumRiLainLain +
            $this->sumRiRad +
            $this->sumRiVisit +
            $this->sumRiKonsul +
            $this->sumRiObatPinjam +
            $this->sumRiJasaMedis +
            $this->sumRiJasaDokter +
            $this->sumRiLab +
            $this->sumRiRoom +
            $this->sumRiPerawatan +
            $this->sumRiCService +
            $this->sumAdminAge +
            $this->sumRiBonResep +
            $this->sumAdminStatus +
            $this->sumRiTrfUgdRj -
            $this->sumRiRtnObat;

        // Angsuran awal yang sudah dibayar selama perawatan
        $this->angsuranAwal = (int) DB::table('rstxn_ripaymentdtls')
            ->where('rihdr_no', $n)->sum('ripay_bayar');

        $this->recalcSisa();
    }

    private function recalcSisa(): void
    {
        $this->totalSetelahDiskon = max(0, $this->totalAll - $this->riDiskon);
        $this->sisaTagihan        = max(0, $this->totalSetelahDiskon - $this->angsuranAwal);
        $this->hitungKembalian();
    }

    /* ===============================
     | REAKTIF
     =============================== */
    public function updatedRiDiskon(): void
    {
        $this->riDiskon = max(0, (int) $this->riDiskon);
        $this->recalcSisa();
    }

    public function updatedBayar(): void
    {
        $this->hitungKembalian();
    }

    private function hitungKembalian(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $this->kembalian = $bayar >= $this->sisaTagihan ? $bayar - $this->sisaTagihan : 0;
    }

    /* ===============================
     | REFRESH TGL KE SEKARANG
     =============================== */
    public function refreshExitDate(): void
    {
        if ($this->tglPulangSudahDiproses || $this->isFormLocked) {
            return;
        }
        $this->exitDate = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->resetErrorBag('exitDate');
    }

    /* ===============================
     | HELPER — max trfr_no
     =============================== */
    private function getMaxTrfrNo(int $riHdrNo): ?int
    {
        return DB::table('rsmst_trfrooms')
            ->where('rihdr_no', $riHdrNo)
            ->max('trfr_no');
    }

    /* ===============================
     | STEP 1 — UPDATE TGL PULANG
     =============================== */
    public function updateTglPulang(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validateOnly('exitDate', [
            'exitDate' => ['required', 'date_format:d/m/Y H:i:s'],
        ], [
            'exitDate.required'     => 'Tanggal pulang harus diisi.',
            'exitDate.date_format'  => 'Format tanggal: dd/mm/yyyy hh:mm:ss.',
        ]);

        $maxTrfrNo = $this->getMaxTrfrNo($this->riHdrNo);

        if (!$maxTrfrNo) {
            $this->dispatch('toast', type: 'error', message: 'Data kamar tidak ditemukan. Pastikan kamar sudah ditambahkan.');
            return;
        }

        try {
            DB::transaction(function () use ($maxTrfrNo) {
                $this->lockRIRow($this->riHdrNo);

                // Hitung jumlah hari: ceil((exit_date - start_date), min 1)
                $longDay = DB::table('rsmst_trfrooms')
                    ->where('trfr_no', $maxTrfrNo)
                    ->selectRaw(
                        "CEIL(DECODE(
                            TO_DATE(?, 'dd/mm/yyyy hh24:mi:ss') - start_date,
                            0, 1,
                            TO_DATE(?, 'dd/mm/yyyy hh24:mi:ss') - start_date
                        )) as longday",
                        [$this->exitDate, $this->exitDate]
                    )
                    ->value('longday');

                DB::table('rsmst_trfrooms')
                    ->where('trfr_no', $maxTrfrNo)
                    ->update([
                        'end_date' => DB::raw("TO_DATE('" . $this->exitDate . "','dd/mm/yyyy hh24:mi:ss')"),
                        'day'      => max(1, (int) $longDay),
                    ]);
            });

            $this->tglPulangSudahDiproses = true;
            $this->hitungTotal();
            $this->incrementVersion('kasir-ri');
            $this->dispatch('toast', type: 'success', message: 'Tanggal pulang & perhitungan kamar berhasil diproses.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal proses tanggal pulang: ' . $e->getMessage());
        }
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'exitDate' => ['required', 'date_format:d/m/Y H:i:s'],
            'outNo'    => ['required', 'string'],
            'accId'    => ['required', 'string'],
            'bayar'    => ['required', 'integer', 'min:1'],
            'riDiskon' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'exitDate.required'    => 'Tanggal pulang harus diisi.',
            'exitDate.date_format' => 'Format tanggal: dd/mm/yyyy hh:mm:ss.',
            'outNo.required'       => 'Keterangan keluar belum diisi.',
            'accId.required'       => 'Akun kas belum dipilih.',
            'bayar.required'       => 'Kolom Bayar masih kosong.',
            'bayar.min'            => 'Nominal bayar harus lebih dari 0.',
        ];
    }

    /* ===============================
     | STEP 2 — POST TRANSAKSI
     =============================== */
    public function postTransaksi(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // Cek akun kas user
        $cekAkunKas = DB::table('user_kas')
            ->where('user_id', auth()->id())
            ->count();

        if ($cekAkunKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        if (!DB::table('rstxn_rihdrs')->where('rihdr_no', $this->riHdrNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->dispatch('toast', type: 'info', message: 'Data sudah diproses sebelumnya.');
            return;
        }

        // Cek perhitungan kamar sudah diproses
        if (!$this->tglPulangSudahDiproses) {
            $this->dispatch('toast', type: 'error', message: 'Data perhitungan kamar belum diproses. Klik "Proses Tgl Pulang" terlebih dahulu.');
            return;
        }

        $this->validate();

        $empId = auth()->user()->emp_id ?? null;

        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $bayar              = (int) $this->bayar;
        $totalSetelahDiskon = $this->totalSetelahDiskon;
        $newStatusPulang    = null;

        try {
            DB::transaction(function () use ($bayar, $totalSetelahDiskon, $empId, &$newStatusPulang) {
                $this->lockRIRow($this->riHdrNo);

                if ($this->checkRIStatus($this->riHdrNo)) {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $hdr = DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->first();

                // Buka lock pasien
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $hdr->reg_no)
                    ->update(['lockstatus' => null]);

                $exitDateRaw = DB::raw("TO_DATE('" . $this->exitDate . "','dd/mm/yyyy hh24:mi:ss')");

                if ($bayar >= $totalSetelahDiskon) {
                    // LUNAS
                    $newStatusPulang = 'L';

                    DB::table('rstxn_rihdrs')
                        ->where('rihdr_no', $this->riHdrNo)
                        ->update([
                            'emp_id'        => $empId,
                            'ri_diskon'     => $this->riDiskon,
                            'ri_bayar'      => $bayar,
                            'ri_titip'      => $totalSetelahDiskon,
                            'status_pulang' => 'L',
                            'payment_date'  => $exitDateRaw,
                            'out_no'        => $this->outNo,
                            'exit_date'     => $exitDateRaw,
                            'ri_status'     => 'P',
                            'acc_id'        => $this->accId,
                        ]);

                    DB::table('rstxn_ripaymentpdtls')->insert([
                        'ripay_no'    => DB::raw('ripayp_seq.nextval'),
                        'ripay_date'  => $exitDateRaw,
                        'ripay_bayar' => $totalSetelahDiskon,
                        'rihdr_no'    => $this->riHdrNo,
                        'emp_id'      => $empId,
                        'acc_id'      => $this->accId,
                    ]);
                } else {
                    // BON / HUTANG
                    $newStatusPulang = 'H';

                    DB::table('rstxn_rihdrs')
                        ->where('rihdr_no', $this->riHdrNo)
                        ->update([
                            'emp_id'        => $empId,
                            'ri_diskon'     => $this->riDiskon,
                            'ri_bayar'      => $bayar,
                            'ri_titip'      => $bayar,
                            'status_pulang' => 'H',
                            'out_no'        => $this->outNo,
                            'exit_date'     => $exitDateRaw,
                            'ri_status'     => 'P',
                            'acc_id'        => $this->accId,
                        ]);

                    DB::table('rstxn_ripaymentpdtls')->insert([
                        'ripay_no'    => DB::raw('ripayp_seq.nextval'),
                        'ripay_date'  => $exitDateRaw,
                        'ripay_bayar' => $bayar,
                        'rihdr_no'    => $this->riHdrNo,
                        'emp_id'      => $empId,
                        'acc_id'      => $this->accId,
                    ]);
                }
            });

            $this->statusPulang = $newStatusPulang;
            $this->riStatus     = 'P';
            $this->isFormLocked = true;
            $this->bayar        = null;
            $this->kembalian    = 0;
            $this->hitungTotal();
            $this->incrementVersion('kasir-ri');

            $msg = $newStatusPulang === 'L'
                ? 'Pembayaran lunas berhasil. Pasien dinyatakan pulang.'
                : 'Pembayaran bon/hutang berhasil. Pasien dinyatakan pulang.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('administrasi-ri.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        if (!$this->riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $hdr = DB::table('rstxn_rihdrs')
                    ->select('reg_no')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->first();

                // Hapus payment final
                DB::table('rstxn_ripaymentpdtls')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->delete();

                // Reset header RI ke status Inap
                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update([
                        'emp_id'        => null,
                        'ri_bayar'      => 0,
                        'ri_titip'      => 0,
                        'ri_diskon'     => 0,
                        'status_pulang' => null,
                        'payment_date'  => null,
                        'out_no'        => null,
                        'exit_date'     => null,
                        'ri_status'     => 'I',
                        'acc_id'        => null,
                    ]);

                // Buka end_date kamar terakhir
                $maxTrfrNo = $this->getMaxTrfrNo($this->riHdrNo);
                if ($maxTrfrNo) {
                    DB::table('rsmst_trfrooms')
                        ->where('trfr_no', $maxTrfrNo)
                        ->update(['end_date' => null, 'day' => null]);
                }

                // Kembalikan lock pasien
                if ($hdr && $hdr->reg_no) {
                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $hdr->reg_no)
                        ->update(['lockstatus' => '1']);
                }
            });

            $this->riStatus              = 'I';
            $this->statusPulang          = null;
            $this->isFormLocked          = false;
            $this->tglPulangSudahDiproses = false;
            $this->exitDate              = null;
            $this->outNo                 = null;
            $this->outDesc               = null;
            $this->accId                 = null;
            $this->accName               = null;
            $this->bayar                 = null;
            $this->riDiskon              = 0;
            $this->kembalian             = 0;

            $this->hitungTotal();
            $this->incrementVersion('kasir-ri');
            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan. Status pasien kembali ke Rawat Inap.');
            $this->dispatch('administrasi-ri.updated');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('kasir-ri', [$riHdrNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Pasien sudah pulang ({{ $statusPulang === 'L' ? 'LUNAS' : 'BON/HUTANG' }}) — transaksi terkunci.
            </div>
            @hasanyrole('Admin|Tu')
            <x-confirm-button variant="danger" :action="'batalTransaksi()'" title="Batal Transaksi Pulang"
                message="Yakin ingin membatalkan? Status pasien akan dikembalikan ke Rawat Inap dan data payment dihapus."
                confirmText="Ya, batalkan" cancelText="Batal">
                Batal Transaksi
            </x-confirm-button>
            @endhasanyrole
        </div>
    @endif

    {{-- RINGKASAN BIAYA --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
        <h4 class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Rincian Tagihan</h4>
        <div class="grid grid-cols-4 gap-2 mb-3 md:grid-cols-6 xl:grid-cols-8">
            @foreach ([
                ['label' => 'Visit',        'value' => $sumRiVisit],
                ['label' => 'Konsul',        'value' => $sumRiKonsul],
                ['label' => 'Jasa Medis',    'value' => $sumRiJasaMedis],
                ['label' => 'Jasa Dokter',   'value' => $sumRiJasaDokter],
                ['label' => 'Lab',           'value' => $sumRiLab],
                ['label' => 'Radiologi',     'value' => $sumRiRad],
                ['label' => 'Kamar',         'value' => $sumRiRoom + $sumRiCService + $sumRiPerawatan],
                ['label' => 'Lain-Lain',     'value' => $sumRiLainLain],
                ['label' => 'Operasi (OK)',  'value' => $sumRiOk],
                ['label' => 'Bon Resep',     'value' => $sumRiBonResep],
                ['label' => 'Obat Pinjam',   'value' => $sumRiObatPinjam],
                ['label' => 'Trf UGD/RJ',    'value' => $sumRiTrfUgdRj],
                ['label' => 'Admin',         'value' => $sumAdminAge + $sumAdminStatus],
                ['label' => 'Rtn Obat (-)',  'value' => $sumRiRtnObat],
            ] as $item)
                <div class="px-2.5 py-2 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">{{ $item['label'] }}</p>
                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                        Rp {{ number_format($item['value']) }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Summary row --}}
        <div class="flex items-stretch gap-3">
            <div class="flex-1 px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Tagihan</p>
                <p class="text-base font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format($totalAll) }}</p>
            </div>

            @if ($angsuranAwal > 0)
                <div class="flex items-center text-gray-300 dark:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
                <div class="flex-1 px-4 py-3 border border-violet-200 rounded-xl dark:border-violet-800/40 bg-violet-50 dark:bg-violet-900/10">
                    <p class="text-xs text-violet-600 dark:text-violet-400 mb-0.5">Angsuran Awal</p>
                    <p class="text-base font-bold text-violet-700 dark:text-violet-300">Rp {{ number_format($angsuranAwal) }}</p>
                </div>
            @endif

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div class="flex-1 px-4 py-3 border border-amber-200 rounded-xl dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                <p class="mb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    Diskon @if (!$isFormLocked)<span class="opacity-60">(dapat diubah)</span>@endif
                </p>
                @if (!$isFormLocked)
                    <x-text-input wire:model.live="riDiskon" type="number" min="0"
                        class="w-full px-0 py-0 text-base font-bold text-amber-700 bg-transparent border-0
                            dark:text-amber-300 focus:ring-0 focus:outline-none
                            [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none
                            [&::-webkit-inner-spin-button]:appearance-none"
                        placeholder="0" />
                    <x-input-error :messages="$errors->get('riDiskon')" class="mt-1" />
                @else
                    <p class="text-base font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($riDiskon) }}</p>
                @endif
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div class="flex-1 px-4 py-3 border border-blue-200 rounded-xl dark:border-blue-800/40 bg-blue-50 dark:bg-blue-900/10">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-0.5">Setelah Diskon</p>
                <p class="text-base font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($totalSetelahDiskon) }}</p>
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div class="flex-1 px-4 py-3 border rounded-xl
                {{ $sisaTagihan > 0
                    ? 'border-rose-200 dark:border-rose-800/40 bg-rose-50 dark:bg-rose-900/10'
                    : 'border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10' }}">
                <p class="text-xs mb-0.5 {{ $sisaTagihan > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    Sisa Tagihan
                </p>
                <p class="text-base font-bold {{ $sisaTagihan > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    Rp {{ number_format($sisaTagihan) }}
                </p>
            </div>
        </div>
    </div>

    @if (!$isFormLocked)

        {{-- STEP 1 — TGL PULANG & PERHITUNGAN KAMAR --}}
        <div class="p-4 border rounded-2xl
            {{ $tglPulangSudahDiproses
                ? 'border-emerald-200 dark:border-emerald-800/40 bg-emerald-50/50 dark:bg-emerald-900/10'
                : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40' }}">

            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full
                    {{ $tglPulangSudahDiproses ? 'bg-emerald-500' : 'bg-gray-400' }}">
                    1
                </span>
                <h4 class="text-sm font-semibold {{ $tglPulangSudahDiproses ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-300' }}">
                    Proses Tanggal Pulang & Perhitungan Kamar
                </h4>
                @if ($tglPulangSudahDiproses)
                    <x-badge variant="success" class="text-xs">Selesai</x-badge>
                @endif
            </div>

            <div class="flex items-end gap-2">
                <div class="w-64">
                    <x-input-label value="Tanggal Pulang" class="mb-1" />
                    <x-text-input wire:model="exitDate" placeholder="dd/mm/yyyy hh:mm:ss"
                        class="w-full text-sm font-mono" :disabled="$tglPulangSudahDiproses" />
                    <x-input-error :messages="$errors->get('exitDate')" class="mt-1" />
                </div>

                @if (!$tglPulangSudahDiproses)
                    {{-- Tombol update ke waktu sekarang --}}
                    <button type="button" wire:click="refreshExitDate"
                        title="Set ke tanggal & jam sekarang"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Sekarang
                    </button>

                    <x-primary-button wire:click="updateTglPulang" wire:loading.attr="disabled"
                        wire:target="updateTglPulang">
                        <span wire:loading.remove wire:target="updateTglPulang">Proses Tgl Pulang</span>
                        <span wire:loading wire:target="updateTglPulang"><x-loading /></span>
                    </x-primary-button>
                @endif
            </div>
        </div>

        {{-- STEP 2 — PEMBAYARAN --}}
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-bayar-ri.window="$nextTick(() => $refs.inputBayarRI?.focus())">

            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full
                    {{ $tglPulangSudahDiproses ? 'bg-brand-green dark:bg-brand-lime dark:text-gray-900' : 'bg-gray-300' }}">
                    2
                </span>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Pembayaran Pasien Pulang</h4>
                @if (!$tglPulangSudahDiproses)
                    <span class="text-xs text-gray-400 italic">— Selesaikan Step 1 terlebih dahulu</span>
                @endif
            </div>

            <div class="flex items-end gap-3 flex-wrap">

                {{-- LOV Keterangan Keluar --}}
                <div class="w-64"
                    x-on:focus-lov-outs-kasir-ri.window="$nextTick(() => $el.querySelector('input')?.focus())">
                    <livewire:lov.outs.lov-outs target="outs-kasir-ri" label="Keterangan Keluar"
                        :initialOutNo="$outNo" :disabled="!$tglPulangSudahDiproses"
                        wire:key="lov-outs-kasir-ri-{{ $riHdrNo }}-{{ $renderVersions['kasir-ri'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('outNo')" class="mt-1" />
                </div>

                {{-- LOV Akun Kas --}}
                <div class="w-72"
                    x-on:focus-lov-kas-kasir-ri.window="$nextTick(() => $el.querySelector('input')?.focus())">
                    <livewire:lov.kas.lov-kas target="kas-kasir-ri" tipe="ri" label="Akun Kas" :initialAccId="$accId"
                        wire:key="lov-kas-kasir-ri-{{ $riHdrNo }}-{{ $renderVersions['kasir-ri'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                </div>

                {{-- Input Bayar --}}
                <div class="w-52">
                    <x-input-label value="Nominal Bayar (Rp)" class="mb-1" />
                    <x-text-input type="number" wire:model.live="bayar" placeholder="0"
                        class="w-full font-mono text-right" min="1"
                        :disabled="!$tglPulangSudahDiproses"
                        x-ref="inputBayarRI"
                        x-on:keyup.enter="$wire.postTransaksi()" />
                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                </div>

                {{-- Kembalian / Kurang Bayar --}}
                @if ($tglPulangSudahDiproses)
                    @if ((int) ($bayar ?? 0) >= $sisaTagihan && $sisaTagihan > 0)
                        <div class="flex-1 px-4 py-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10">
                            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kembalian</p>
                            <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($kembalian) }}</p>
                        </div>
                    @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $sisaTagihan)
                        <div class="flex-1 px-4 py-2.5 rounded-xl border border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Kurang Bayar (Bon)</p>
                            <p class="text-lg font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($sisaTagihan - (int) ($bayar ?? 0)) }}</p>
                        </div>
                    @endif
                @endif

                {{-- Tombol Post --}}
                <div class="flex gap-2 pb-0.5">
                    <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled"
                        wire:target="postTransaksi"
                        :disabled="!$tglPulangSudahDiproses">
                        <span wire:loading.remove wire:target="postTransaksi">Proses Pulang</span>
                        <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                    </x-primary-button>
                </div>

            </div>

            {{-- Badge status pembayaran --}}
            @if ($tglPulangSudahDiproses && (int) ($bayar ?? 0) > 0)
                @if ((int) ($bayar ?? 0) >= $sisaTagihan)
                    <div class="flex items-center gap-1.5 mt-3">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            Pembayaran akan diproses sebagai LUNAS
                        </span>
                    </div>
                @else
                    <div class="flex items-center gap-1.5 mt-3">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                            Pembayaran akan diproses sebagai BON/HUTANG — sisa Rp {{ number_format($sisaTagihan - (int) ($bayar ?? 0)) }}
                        </span>
                    </div>
                @endif
            @endif

        </div>

    @endif

    {{-- TABEL RIWAYAT PAYMENT FINAL --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Pembayaran Pulang</h3>
            @php $payments = DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->orderBy('ripay_date')->get(); @endphp
            <x-badge variant="gray">{{ $payments->count() }} transaksi</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Akun Kas</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($payments as $pay)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ Carbon::parse($pay->ripay_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $pay->acc_id ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $pay->emp_id ?? '-' }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($pay->ripay_bayar) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada riwayat pembayaran pulang
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($payments->isNotEmpty())
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">
                                Total Dibayar
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($payments->sum('ripay_bayar')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
