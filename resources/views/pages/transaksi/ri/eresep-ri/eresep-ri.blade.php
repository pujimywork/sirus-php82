<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];
    public string $activeTab = 'NonRacikan';
    public ?int $activeResepIndex = null;
    public array $apotekStatuses = []; // [slsNo => status] dari imtxn_slshdrs

    public array $formResepHdr = [
        'resepDate' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'hdr-list'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'hdr-list']);
    }

    /* ===============================
     | OPEN ERESEP RI
     =============================== */
    #[On('emr-ri.eresep.open')]
    public function openEresep(int $riHdrNo): void
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data rawat inap tidak ditemukan.');
            return;
        }

        $this->dataDaftarRI = $data;
        $this->dataDaftarRI['eresepHdr'] ??= [];

        if ($this->checkRIStatus($riHdrNo)) {
            $this->isFormLocked = true;
        }
        // Set active resep ke header terakhir
        if (!empty($this->dataDaftarRI['eresepHdr'])) {
            $this->activeResepIndex = count($this->dataDaftarRI['eresepHdr']) - 1;
        }

        $this->loadApotekStatuses();
        $this->formResepHdr['resepDate'] = Carbon::now()->format('d/m/Y H:i:s');

        $this->dispatch('open-modal', name: 'emr-ri.eresep-ri');
        $this->incrementVersion('modal');
        $this->incrementVersion('hdr-list');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-ri.eresep-ri');
    }

    /* ===============================
     | REFRESH DATA (dipanggil dari child setelah save)
     =============================== */
    #[On('eresep-ri.data-updated')]
    public function refreshData(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if ($data) {
            $this->dataDaftarRI = $data;
            $this->dataDaftarRI['eresepHdr'] ??= [];
        }
        $this->loadApotekStatuses();
        $this->incrementVersion('hdr-list');
    }

    /* ===============================
     | LOAD STATUS APOTEK (1 query untuk semua slsNo)
     =============================== */
    private function loadApotekStatuses(): void
    {
        $slsNos = collect($this->dataDaftarRI['eresepHdr'] ?? [])
            ->pluck('slsNo')
            ->filter()
            ->values()
            ->all();

        if (empty($slsNos)) {
            $this->apotekStatuses = [];
            return;
        }

        $this->apotekStatuses = DB::table('imtxn_slshdrs')
            ->select('sls_no', 'status')
            ->whereIn('sls_no', $slsNos)
            ->get()
            ->pluck('status', 'sls_no')
            ->all();
    }

    /* ===============================
     | TAMBAH RESEP HEADER BARU
     =============================== */
    public function addResepHdr(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, pasien sudah pulang.');
            return;
        }

        $this->validate(
            [
                'formResepHdr.resepDate' => 'required|date_format:d/m/Y H:i:s',
            ],
            [
                'formResepHdr.resepDate.required' => 'Tanggal resep wajib diisi.',
                'formResepHdr.resepDate.date_format' => 'Format tanggal harus d/m/Y H:i:s.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                $data['eresepHdr'] ??= [];

                $newResepNo = (collect($data['eresepHdr'])->max('resepNo') ?? 0) + 1;

                $data['eresepHdr'][] = [
                    'resepNo' => $newResepNo,
                    'resepDate' => $this->formResepHdr['resepDate'],
                    'regNo' => $data['regNo'],
                    'riHdrNo' => $this->riHdrNo,
                    'eresep' => [],
                    'eresepRacikan' => [],
                ];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->activeResepIndex = count($data['eresepHdr']) - 1;
            });

            $this->formResepHdr['resepDate'] = Carbon::now()->format('d/m/Y H:i:s');
            $this->incrementVersion('modal');
            $this->incrementVersion('hdr-list');
            $this->dispatch('toast', type: 'success', message: 'Resep baru berhasil dibuat.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuat resep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS RESEP HEADER
     =============================== */
    public function removeResepHdr(int $resepIndex): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, pasien sudah pulang.');
            return;
        }

        $hdr = $this->dataDaftarRI['eresepHdr'][$resepIndex] ?? null;
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Resep tidak ditemukan.');
            return;
        }

        if (!empty($hdr['tandaTanganDokter']['dokterPeresep'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Resep sudah ditandatangani dokter, tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($resepIndex) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                array_splice($data['eresepHdr'], $resepIndex, 1);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;

                $total = count($data['eresepHdr']);
                $this->activeResepIndex = $total > 0 ? min($this->activeResepIndex ?? 0, $total - 1) : null;
            });

            $this->incrementVersion('modal');
            $this->incrementVersion('hdr-list');
            $this->dispatch('toast', type: 'success', message: 'Resep berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus resep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PILIH RESEP AKTIF
     =============================== */
    public function selectResep(int $resepIndex): void
    {
        $this->activeResepIndex = $resepIndex;
        $this->incrementVersion('modal');
    }

    /* ===============================
     | TTD DOKTER → KIRIM KE APOTEK
     =============================== */
    public function setDokterPeresep(int $resepIndex): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $hdr = $this->dataDaftarRI['eresepHdr'][$resepIndex] ?? null;
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Resep tidak ditemukan.');
            return;
        }

        if (!empty($hdr['slsNo'])) {
            $this->dispatch('toast', type: 'info', message: 'Resep sudah terkirim ke apotek (SLS#' . $hdr['slsNo'] . ').');
            return;
        }

        $user = auth()->user();
        if (!$user->hasAnyRole(['Dokter', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: "User {$user->name} bukan Dokter atau Admin.");
            return;
        }

        try {
            DB::transaction(function () use ($resepIndex, $user) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                $hdr = $data['eresepHdr'][$resepIndex] ?? null;
                if (!$hdr) {
                    throw new \RuntimeException('Header resep tidak ditemukan.');
                }

                if (!empty($hdr['slsNo'])) {
                    $this->dispatch('toast', type: 'info', message: 'Resep sudah terkirim ke apotek.');
                    return;
                }

                // Set TTD
                $data['eresepHdr'][$resepIndex]['tandaTanganDokter'] = [
                    'dokterPeresep' => $user->name,
                    'dokterPeresepCode' => $user->myuser_code ?? $user->id,
                ];

                // Kirim ke apotek
                $slsNo = $this->sendToApotek($hdr['resepDate'], $hdr['regNo'], $this->riHdrNo, $user->myuser_code ?? $user->id, $hdr['eresep'] ?? []);

                $data['eresepHdr'][$resepIndex]['slsNo'] = $slsNo;

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
            });

            $this->incrementVersion('modal');
            $this->incrementVersion('hdr-list');
            $this->dispatch('toast', type: 'success', message: 'TTD & pengiriman resep ke apotek berhasil.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATALKAN TTD & RESET KE APOTEK
     | Cek status di imtxn_slshdrs:
     |  - Belum ada slsNo   → hapus TTD saja
     |  - status = 'A'      → hapus data apotek + hapus TTD
     |  - status = 'L'      → TOLAK, sudah diproses
     =============================== */
    public function batalTTD(int $resepIndex): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, pasien sudah pulang.');
            return;
        }

        $hdr = $this->dataDaftarRI['eresepHdr'][$resepIndex] ?? null;
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Resep tidak ditemukan.');
            return;
        }

        $slsNo = $hdr['slsNo'] ?? null;

        // Cek status di apotek jika sudah pernah dikirim
        if ($slsNo) {
            $slsRecord = DB::table('imtxn_slshdrs')
                ->select('status')
                ->where('sls_no', $slsNo)
                ->first();

            if ($slsRecord && $slsRecord->status === 'L') {
                $this->dispatch('toast', type: 'error', message:
                    'Resep #' . ($hdr['resepNo'] ?? '') . ' sudah diproses apotek (status L), tidak dapat diubah.'
                );
                return;
            }

            if ($slsRecord && $slsRecord->status !== 'A') {
                $this->dispatch('toast', type: 'error', message:
                    "Status resep di apotek '{$slsRecord->status}', tidak dapat diubah."
                );
                return;
            }
        }

        try {
            DB::transaction(function () use ($resepIndex, $slsNo) {
                $this->lockRIRow($this->riHdrNo);

                // Hapus data apotek jika ada
                if ($slsNo) {
                    DB::table('imtxn_slsdtls')->where('sls_no', $slsNo)->delete();
                    DB::table('imtxn_slshdrs')->where('sls_no', $slsNo)->delete();
                }

                // Reset TTD dan slsNo di JSON
                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) throw new \RuntimeException('Data RI tidak ditemukan.');

                unset($data['eresepHdr'][$resepIndex]['tandaTanganDokter']);
                unset($data['eresepHdr'][$resepIndex]['slsNo']);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
            });

            $this->incrementVersion('modal');
            $this->incrementVersion('hdr-list');

            $msg = $slsNo
                ? 'TTD dibatalkan & data apotek dihapus. Silakan edit dan TTD ulang.'
                : 'TTD dibatalkan. Resep dapat diedit kembali.';

            $this->dispatch('toast', type: 'success', message: $msg);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan TTD: ' . $e->getMessage());
        }
    }

    /* ===============================
     | COPY RESEP (hanya yg sudah TTD)
     =============================== */
    public function copyResepHdr(int $srcIndex): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $srcHdr = $this->dataDaftarRI['eresepHdr'][$srcIndex] ?? null;
        if (!$srcHdr) {
            $this->dispatch('toast', type: 'error', message: 'Resep sumber tidak ditemukan.');
            return;
        }

        if (empty($srcHdr['tandaTanganDokter']['dokterPeresep'] ?? null)) {
            $this->dispatch('toast', type: 'warning', message: 'Resep belum ditandatangani, tidak dapat dicopy.');
            return;
        }

        try {
            DB::transaction(function () use ($srcHdr) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                $data['eresepHdr'] ??= [];
                $newResepNo = (collect($data['eresepHdr'])->max('resepNo') ?? 0) + 1;
                $now = Carbon::now()->format('d/m/Y H:i:s');

                // Copy items tanpa TTD dan slsNo agar bisa diedit + TTD ulang
                $data['eresepHdr'][] = [
                    'resepNo' => $newResepNo,
                    'resepDate' => $now,
                    'regNo' => $srcHdr['regNo'],
                    'riHdrNo' => $this->riHdrNo,
                    'eresep' => $srcHdr['eresep'] ?? [],
                    'eresepRacikan' => $srcHdr['eresepRacikan'] ?? [],
                ];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->activeResepIndex = count($data['eresepHdr']) - 1;
            });

            $this->incrementVersion('modal');
            $this->incrementVersion('hdr-list');
            $this->dispatch('toast', type: 'success', message: 'Resep berhasil dicopy. Silakan edit & TTD.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal copy resep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SIMPAN PLAN CPPT
     =============================== */
    public function simpanPlanCppt(int $resepIndex): void
    {
        $hdr = $this->dataDaftarRI['eresepHdr'][$resepIndex] ?? null;

        if (!$hdr) {
            $this->dispatch('toast', type: 'warning', message: 'Data resep tidak ditemukan.');
            return;
        }

        if (empty($hdr['tandaTanganDokter']['dokterPeresep'] ?? null)) {
            $this->dispatch('toast', type: 'warning', message: 'Resep belum ditandatangani dokter, tidak dapat disimpan ke CPPT.');
            return;
        }

        $eresepNonRacikan = '';
        $eresepRacikan = '';

        foreach ($hdr['eresep'] ?? [] as $item) {
            $catatan = trim((string) ($item['catatanKhusus'] ?? ''));
            $suffix = $catatan !== '' ? ' (' . $catatan . ')' : '';
            $eresepNonRacikan .= 'R/ ' . ($item['productName'] ?? '-') . ' | No. ' . ($item['qty'] ?? '-') . ' | S ' . ($item['signaX'] ?? '-') . 'dd' . ($item['signaHari'] ?? '-') . $suffix . PHP_EOL;
        }

        foreach ($hdr['eresepRacikan'] ?? [] as $item) {
            $jmlLine = $item['qty'] ? 'Jml Racikan ' . $item['qty'] . ' | ' . ($item['catatan'] ?? '') . ' | S ' . ($item['catatanKhusus'] ?? '') . PHP_EOL : '';
            $eresepRacikan .= ($item['noRacikan'] ?? '') . '/ ' . ($item['productName'] ?? '') . ' - ' . ($item['dosis'] ?? '') . PHP_EOL . $jmlLine;
        }

        $this->dispatch('syncronizeCpptPlan', text: trim($eresepNonRacikan . $eresepRacikan));
        $this->dispatch('toast', type: 'success', message: 'Plan CPPT berhasil disimpan.');
    }

    /* ===============================
     | KIRIM KE APOTEK (internal helper)
     =============================== */
    private function sendToApotek(string $resepDate, string $regNo, int $riHdrNo, mixed $drId, array $dataObat): int
    {
        $pasien = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->first();
        if (!$pasien) {
            throw new \RuntimeException("Pasien dengan reg_no {$regNo} tidak ditemukan.");
        }

        $dokter = DB::table('rsmst_doctors')->where('dr_id', $drId)->first();
        if (!$dokter) {
            throw new \RuntimeException("Dokter dengan dr_id {$drId} tidak ditemukan.");
        }

        // Nomor transaksi apotek baru
        $maxSlsNo = DB::table('imtxn_slshdrs')->select(DB::raw('nvl(max(sls_no)+1,1) as max_sls_no'))->first()->max_sls_no;

        // Cari shift
        $formattedTime = Carbon::createFromFormat('d/m/Y H:i:s', $resepDate, config('app.timezone'))->format('H:i:s');

        $shift =
            DB::table('rstxn_shiftctls')
                ->select('shift')
                ->whereRaw("'{$formattedTime}' between shift_start and shift_end")
                ->value('shift') ?? 1;

        // Insert header transaksi apotek
        DB::table('imtxn_slshdrs')->insert([
            'sls_no' => $maxSlsNo,
            'sls_date' => DB::raw("to_date('{$resepDate}','dd/mm/yyyy hh24:mi:ss')"),
            'status' => 'A',
            'dr_id' => $drId,
            'reg_no' => $regNo,
            'shift' => $shift,
            'rihdr_no' => $riHdrNo,
            'emp_id' => '1',
            'acte_price' => 3000,
            'waktu_masuk_pelayanan' => DB::raw('sysdate'),
        ]);

        // Insert detail tiap obat
        foreach ($dataObat as $item) {
            $maxSlsDtl = DB::table('imtxn_slsdtls')->select(DB::raw('nvl(max(sls_dtl)+1,1) as max_sls_dtl'))->first()->max_sls_dtl;

            $salesPrice = DB::table('immst_products')->where('product_id', $item['productId'])->value('sales_price') ?? 0;

            $takar = DB::table('immst_products')->where('product_id', $item['productId'])->value('takar') ?? 'Tablet';

            DB::table('imtxn_slsdtls')->insert([
                'sls_dtl' => $maxSlsDtl,
                'qty' => $item['qty'],
                'exp_date' => DB::raw("add_months(to_date('{$resepDate}','dd/mm/yyyy hh24:mi:ss'),12)"),
                'sales_price' => $salesPrice,
                'product_id' => $item['productId'],
                'sls_no' => $maxSlsNo,
                'resep_carapakai' => $item['signaX'],
                'resep_takar' => $takar,
                'resep_kapsul' => $item['signaHari'],
                'resep_ket' => $item['catatanKhusus'] ?? '',
                'etiket_status' => 1,
            ]);
        }

        return $maxSlsNo;
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRI', 'activeTab', 'activeResepIndex', 'formResepHdr', 'apotekStatuses']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="emr-ri.eresep-ri" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    E-Resep Rawat Inap
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Penulisan resep obat pasien rawat inap
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 mt-3">
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only — Pasien Sudah Pulang</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid max-w-full grid-cols-1 gap-4 mx-auto lg:grid-cols-4">

                    {{-- KOLOM KIRI: Daftar Resep Header --}}
                    <div class="lg:col-span-1">
                        <div
                            class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Resep</h3>

                            {{-- Form Tambah Resep Baru --}}
                            @if (!$isFormLocked)
                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <x-input-label :value="__('Tgl Resep')" />
                                        <x-text-input wire:model="formResepHdr.resepDate" class="w-full mt-1"
                                            placeholder="dd/mm/yyyy HH:ii:ss" />
                                        <x-input-error :messages="$errors->get('formResepHdr.resepDate')" />
                                    </div>
                                    <div class="flex items-end">
                                        <x-primary-button wire:click="addResepHdr" wire:loading.attr="disabled">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                        </x-primary-button>
                                    </div>
                                </div>
                            @endif

                            {{-- List Resep --}}
                            <div wire:key="{{ $this->renderKey('hdr-list', [$riHdrNo ?? 'new']) }}" class="space-y-2">
                                @forelse ($dataDaftarRI['eresepHdr'] ?? [] as $idx => $hdr)
                                    <div
                                        class="p-3 text-sm border rounded-lg cursor-pointer {{ $activeResepIndex === $idx ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 bg-gray-50 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800/50' }}">

                                        {{-- Resep info --}}
                                        <div wire:click="selectResep({{ $idx }})" class="space-y-0.5">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">
                                                Resep #{{ $hdr['resepNo'] }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $hdr['resepDate'] }}</div>

                                            @php
                                                $hasTTD   = !empty($hdr['tandaTanganDokter']['dokterPeresep'] ?? null);
                                                $hasSlsNo = !empty($hdr['slsNo'] ?? null);
                                                $apotekStatus = $hasSlsNo ? ($apotekStatuses[$hdr['slsNo']] ?? null) : null;
                                                $isApotekLocked = $apotekStatus === 'L';
                                            @endphp

                                            @if ($isApotekLocked)
                                                {{-- Sudah selesai diproses apotek (status L) --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">
                                                    <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                    Selesai Diproses Apotek
                                                </span>
                                            @elseif ($hasSlsNo)
                                                {{-- Terkirim ke apotek, status A (masih bisa diedit) --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    ✓ Apotek SLS#{{ $hdr['slsNo'] }}
                                                </span>
                                            @elseif ($hasTTD)
                                                {{-- TTD ada tapi belum terkirim (seharusnya jarang terjadi) --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    TTD — menunggu kirim
                                                </span>
                                            @else
                                                {{-- Draft --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                    Draft
                                                </span>
                                            @endif

                                            @if ($hasTTD)
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    dr. {{ $hdr['tandaTanganDokter']['dokterPeresep'] }}
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Action buttons (hanya tampil saat card aktif) --}}
                                        @if ($activeResepIndex === $idx)
                                            <div class="mt-2 space-y-1.5">

                                                {{-- ── DRAFT: belum TTD ── --}}
                                                @if (!$hasTTD && !$isFormLocked)
                                                    @role(['Dokter', 'Admin'])
                                                        {{-- TTD & Kirim ke Apotek --}}
                                                        <x-primary-button
                                                            wire:click="setDokterPeresep({{ $idx }})"
                                                            class="!py-1 !px-2 !text-xs w-full justify-center"
                                                            wire:loading.attr="disabled"
                                                            title="Tanda tangani resep ini dan kirimkan ke apotek">
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            TTD & Kirim ke Apotek
                                                        </x-primary-button>

                                                        {{-- Hapus Draft --}}
                                                        <x-secondary-button
                                                            wire:click="removeResepHdr({{ $idx }})"
                                                            class="!py-1 !px-2 !text-xs w-full justify-center text-red-600"
                                                            wire:confirm="Hapus resep draft ini? Tindakan tidak dapat dibatalkan."
                                                            title="Hapus resep yang belum ditandatangani">
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 18 20">
                                                                <path d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2Z" />
                                                            </svg>
                                                            Hapus Draft
                                                        </x-secondary-button>
                                                    @endrole
                                                @endif

                                                {{-- ── SUDAH TTD ── --}}
                                                @if ($hasTTD)
                                                    @role(['Dokter', 'Admin'])
                                                        @if (!$isFormLocked)
                                                            @if ($isApotekLocked)
                                                                {{-- Resep sudah selesai diproses apotek, tidak bisa diedit --}}
                                                                <div class="flex items-start gap-1.5 p-2 rounded-lg bg-gray-100 border border-gray-300 text-xs text-gray-600">
                                                                    <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    <span>Resep sudah selesai diproses oleh apotek, tidak dapat diubah.</span>
                                                                </div>
                                                            @else
                                                                {{-- Edit Resep (batalkan TTD, status masih A) --}}
                                                                <div>
                                                                    <x-secondary-button
                                                                        wire:click="batalTTD({{ $idx }})"
                                                                        class="!py-1 !px-2 !text-xs w-full justify-center text-orange-600 border-orange-300 hover:border-orange-400"
                                                                        wire:loading.attr="disabled"
                                                                        wire:confirm="Batalkan TTD resep ini untuk diedit ulang? Data di apotek akan dihapus dan resep kembali ke draft."
                                                                        title="Batalkan TTD agar resep bisa diedit ulang">
                                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                        </svg>
                                                                        Edit Resep
                                                                    </x-secondary-button>
                                                                    @if ($hasSlsNo)
                                                                        <p class="mt-0.5 text-xs text-gray-400 leading-tight">
                                                                            SLS#{{ $hdr['slsNo'] }} — apotek belum memproses, masih bisa diedit
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                            @endif

                                                            {{-- Salin ke Resep Baru (tetap bisa meski locked) --}}
                                                            <x-secondary-button
                                                                wire:click="copyResepHdr({{ $idx }})"
                                                                class="!py-1 !px-2 !text-xs w-full justify-center"
                                                                title="Buat resep baru dengan isi obat yang sama (tanpa TTD)">
                                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                                </svg>
                                                                Salin ke Resep Baru
                                                            </x-secondary-button>
                                                        @endif
                                                    @endrole

                                                    {{-- Kirim ke Plan CPPT --}}
                                                    <x-secondary-button
                                                        wire:click="simpanPlanCppt({{ $idx }})"
                                                        class="!py-1 !px-2 !text-xs w-full justify-center text-blue-600 border-blue-300 hover:border-blue-400"
                                                        title="Salin ringkasan obat resep ini ke kolom Plan di form CPPT">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                        </svg>
                                                        Kirim ke Plan CPPT
                                                    </x-secondary-button>
                                                @endif

                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-center text-gray-400 py-4">Belum ada resep.</p>
                                @endforelse
                            </div>

                        </div>
                    </div>

                    {{-- KOLOM KANAN: Detail Resep Aktif --}}
                    <div class="lg:col-span-3 space-y-4">

                        {{-- Data Pasien --}}
                        <div
                            class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="eresep-ri-display-pasien-{{ $riHdrNo }}" />
                        </div>

                        {{-- Konten Resep Aktif --}}
                        @if ($activeResepIndex !== null && isset($dataDaftarRI['eresepHdr'][$activeResepIndex]))
                            <div
                                class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                                {{-- Info Resep Aktif --}}
                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">
                                        Resep #{{ $dataDaftarRI['eresepHdr'][$activeResepIndex]['resepNo'] }}
                                    </span>
                                    <span>—</span>
                                    <span>{{ $dataDaftarRI['eresepHdr'][$activeResepIndex]['resepDate'] }}</span>
                                    @if (!empty($dataDaftarRI['eresepHdr'][$activeResepIndex]['slsNo']))
                                        <x-badge variant="success">
                                            Terkirim ke Apotek
                                        </x-badge>
                                    @endif
                                </div>

                                {{-- Tab NonRacikan / Racikan --}}
                                <div x-data="{ activeTab: @entangle('activeTab') }" class="w-full">
                                    <div class="px-2 mb-0 overflow-auto border-b border-gray-200">
                                        <ul
                                            class="flex flex-row flex-wrap justify-center -mb-px text-sm font-medium text-gray-500">
                                            <li class="mx-1 rounded-t-lg"
                                                :class="activeTab === 'NonRacikan' ? 'text-primary border-primary bg-gray-100' :
                                                    'border border-gray-200'">
                                                <label
                                                    class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    x-on:click="activeTab = 'NonRacikan'"
                                                    wire:click="$set('activeTab', 'NonRacikan')">
                                                    Non Racikan
                                                </label>
                                            </li>
                                            <li class="mx-1 rounded-t-lg"
                                                :class="activeTab === 'Racikan' ? 'text-primary border-primary bg-gray-100' :
                                                    'border border-gray-200'">
                                                <label
                                                    class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    x-on:click="activeTab = 'Racikan'"
                                                    wire:click="$set('activeTab', 'Racikan')">
                                                    Racikan
                                                </label>
                                            </li>
                                        </ul>
                                    </div>

                                    {{-- Non Racikan --}}
                                    <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'NonRacikan'"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100">
                                        <livewire:pages::transaksi.ri.eresep-ri.eresep-ri-non-racikan :riHdrNo="$riHdrNo"
                                            :resepIndex="$activeResepIndex"
                                            wire:key="{{ $this->renderKey('modal', ['non-racikan', $riHdrNo ?? 'new', $activeResepIndex ?? 'none']) }}" />
                                    </div>

                                    {{-- Racikan --}}
                                    <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'Racikan'"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100">
                                        <livewire:pages::transaksi.ri.eresep-ri.eresep-ri-racikan :riHdrNo="$riHdrNo"
                                            :resepIndex="$activeResepIndex"
                                            wire:key="{{ $this->renderKey('modal', ['racikan', $riHdrNo ?? 'new', $activeResepIndex ?? 'none']) }}" />
                                    </div>
                                </div>
                            </div>
                        @else
                            <div
                                class="p-8 text-center text-gray-400 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <p class="text-sm">Pilih atau buat resep baru di panel kiri.</p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
