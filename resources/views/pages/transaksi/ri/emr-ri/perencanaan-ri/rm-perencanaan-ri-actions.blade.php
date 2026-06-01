<?php
// resources/views/pages/transaksi/ri/emr-ri/perencanaan/rm-perencanaan-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public bool $isBPJS = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $tindakLanjutOptions = [['tindakLanjut' => 'Pulang Sehat', 'tindakLanjutKode' => '371827001', 'tindakLanjutKodeBpjs' => 1], ['tindakLanjut' => 'Pulang dengan Permintaan Sendiri', 'tindakLanjutKode' => '266707007', 'tindakLanjutKodeBpjs' => 3], ['tindakLanjut' => 'Pulang Pindah / Rujuk', 'tindakLanjutKode' => '306206005', 'tindakLanjutKodeBpjs' => 5], ['tindakLanjut' => 'Pulang Tanpa Perbaikan', 'tindakLanjutKode' => '371828006', 'tindakLanjutKodeBpjs' => 5], ['tindakLanjut' => 'Meninggal', 'tindakLanjutKode' => '419099009', 'tindakLanjutKodeBpjs' => 4], ['tindakLanjut' => 'Lain-lain', 'tindakLanjutKode' => '74964007', 'tindakLanjutKodeBpjs' => 5]];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-perencanaan-ri']);
    }

    #[On('open-rm-perencanaan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['perencanaan'] ??= [
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'tindakLanjutKode' => '',
                'statusPulang' => '',
                'noSuratMeninggal' => '',
                'tglMeninggal' => '',
                'tglPulang' => '',
                'noLPManual' => '',
                'noSep' => '',
            ],
            'dischargePlanning' => [
                'pelayananBerkelanjutan' => ['pelayananBerkelanjutan' => 'Tidak Ada', 'ketPelayananBerkelanjutan' => '', 'pelayananBerkelanjutanData' => []],
                'penggunaanAlatBantu' => ['penggunaanAlatBantu' => 'Tidak Ada', 'ketPenggunaanAlatBantu' => '', 'penggunaanAlatBantuData' => []],
            ],
        ];

        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $this->dataDaftarRi['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $this->isBPJS = $klaimStatus === 'BPJS';

        if ($this->isBPJS && empty($this->dataDaftarRi['perencanaan']['tindakLanjut']['noSep'])) {
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['noSep'] = $this->dataDaftarRi['sep']['noSep'] ?? '';
        }

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-perencanaan-ri');
    }

    public function updatedDataDaftarRiPerencanaanTindakLanjutTindakLanjut(string $val): void
    {
        $opt = collect($this->tindakLanjutOptions)->firstWhere('tindakLanjutKode', $val);
        if ($opt) {
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjutKode'] = $opt['tindakLanjutKode'];
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] = $opt['tindakLanjutKodeBpjs'];
        }
        $this->store();
    }

    #[On('save-rm-perencanaan-ri')]
    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['perencanaan'] = $this->dataDaftarRi['perencanaan'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('Perencanaan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function setTglPulang(): void
    {
        $this->dataDaftarRi['perencanaan']['tindakLanjut']['tglPulang'] = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setTglMeninggal(): void
    {
        $this->dataDaftarRi['perencanaan']['tindakLanjut']['tglMeninggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function updateTglPulangBPJS(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Simpan JSON dulu sebelum kirim ke BPJS
        $this->store();

        $tindak = $this->dataDaftarRi['perencanaan']['tindakLanjut'] ?? [];

        // Cek klaim BPJS
        $klaimStatus = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $this->dataDaftarRi['klaimId'] ?? '')
            ->value('klaim_status') ?? 'UMUM';

        if ($klaimStatus !== 'BPJS') {
            $this->dispatch('toast', type: 'error', message: 'Gagal: Klaim ini bukan BPJS.');
            return;
        }

        $noSep = $tindak['noSep'] ?? '';
        if (empty($noSep)) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: Nomor SEP belum tersedia.');
            return;
        }

        $tglSepRaw = $this->dataDaftarRi['sep']['reqSep']['request']['t_sep']['tglSep'] ?? null;
        if (empty($tglSepRaw)) {
            $this->dispatch('toast', type: 'error', message: 'Pembuatan SEP bukan melalui siRUS, Tgl SEP belum tersedia.');
            return;
        }

        // Konversi format tanggal dd/mm/yyyy → Y-m-d
        try {
            $tglPulang = !empty($tindak['tglPulang'])
                ? Carbon::createFromFormat('d/m/Y', $tindak['tglPulang'])->format('Y-m-d')
                : null;
            $tglMeninggal = !empty($tindak['tglMeninggal'])
                ? Carbon::createFromFormat('d/m/Y', $tindak['tglMeninggal'])->format('Y-m-d')
                : null;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Format tanggal tidak valid: ' . $e->getMessage());
            return;
        }

        $SEPJsonReq = [
            'request' => [
                't_sep' => [
                    'noSep'             => $noSep,
                    'statusPulang'      => $tindak['statusPulang'] ?? '',
                    'noSuratMeninggal'  => $tindak['noSuratMeninggal'] ?? '',
                    'tglMeninggal'      => $tglMeninggal,
                    'tglPulang'         => $tglPulang,
                    'noLPManual'        => $tindak['noLPManual'] ?? '',
                    'user'              => 'siRUS',
                    'isKLL'             => $tindak['isKLL'] ?? 0,
                    'tglSep'            => $tglSepRaw,
                    'isAlreadyReferred' => false,
                ],
            ],
        ];

        $HttpGetBpjs = VclaimTrait::sep_updtglplg($SEPJsonReq)->getOriginalContent();

        if (($HttpGetBpjs['metadata']['code'] ?? 0) == 200) {
            $this->dispatch('toast', type: 'success', message: 'Update Tgl Pulang BPJS: ' . $HttpGetBpjs['metadata']['message']);
        } else {
            $this->dispatch('toast', type: 'error', message: 'Update Tgl Pulang BPJS: ' . ($HttpGetBpjs['metadata']['message'] ?? 'Gagal'));
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-perencanaan-ri');
        $this->dispatch('refresh-after-ri.saved');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }

};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-perencanaan-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{
        sectionDirty: false,
        openedAt: 0,
        tab: 'perencanaan',
        markDirty() {
            if (!this.sectionDirty && Date.now() - this.openedAt > 300) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.tab });
            }
        },
    }"
    x-init="
        openedAt = Date.now();
        window.addEventListener('refresh-after-ri.saved', () => {
            sectionDirty = false;
            openedAt = Date.now();
            $dispatch('section-clean', { tab: tab });
        });
    "
    x-on:input="markDirty()"
    x-on:change="markDirty()">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── TINDAK LANJUT ── --}}
    <x-border-form title="Tindak Lanjut / Rencana Pulang" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-4">

            {{-- Radio tindak lanjut --}}
            <div>
                <x-input-label value="Tindak Lanjut *" />
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($tindakLanjutOptions as $opt)
                        <x-radio-button :label="$opt['tindakLanjut']" :value="$opt['tindakLanjutKode']" name="tindakLanjut"
                            wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tindakLanjut" :disabled="$isFormLocked" />
                        {{-- x-radio-button sudah include label --}}
                    @endforeach
                </div>
                @if (!empty($dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjut']))
                    <p class="mt-1 text-xs text-gray-500">
                        Kode SNOMED: <span
                            class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjutKode'] ?? '-' }}</span>
                        | Status BPJS: <span
                            class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '-' }}</span>
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- Tanggal Pulang --}}
                <div>
                    <x-input-label value="Tanggal Pulang" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tglPulang" class="flex-1"
                            placeholder="dd/mm/yyyy" :disabled="$isFormLocked" />
                        @if (!$isFormLocked)
                            <x-secondary-button wire:click="setTglPulang" type="button" class="text-xs">Hari
                                Ini</x-secondary-button>
                        @endif
                    </div>
                </div>

                {{-- Keterangan --}}
                <div>
                    <x-input-label value="Keterangan Tindak Lanjut" />
                    <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.keteranganTindakLanjut"
                        class="w-full mt-1" :disabled="$isFormLocked" />
                </div>
            </div>

            {{-- Jika Meninggal (statusPulang: 4) --}}
            @if (($dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '') == 4)
                <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div>
                        <x-input-label value="No. Surat Keterangan Meninggal *" />
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noSuratMeninggal"
                            class="w-full mt-1" :disabled="$isFormLocked" placeholder="Wajib diisi jika meninggal" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Meninggal *" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tglMeninggal"
                                class="flex-1" placeholder="dd/mm/yyyy" :disabled="$isFormLocked" />
                            @if (!$isFormLocked)
                                <x-now-button wire:click="setTglMeninggal" />
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Jika KLL (Kecelakaan Lalu Lintas) --}}
            @if ($dataDaftarRi['kPolisi'] ?? false)
                <div class="p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                    <div>
                        <x-input-label value="No. Laporan Polisi (KLL) *" />
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noLPManual"
                            class="w-full mt-1" :disabled="$isFormLocked" placeholder="Wajib diisi untuk kasus KLL" />
                    </div>
                </div>
            @endif

            {{-- BPJS: No SEP + Tombol Update Pulang — hide untuk Dokter (domain Perawat) --}}
            @if ($isBPJS && !auth()->user()->hasRole('Dokter'))
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div>
                        <x-input-label value="No. SEP" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noSep"
                                class="flex-1 font-mono" :disabled="$isFormLocked" />
                            @if (!$isFormLocked)
                                <x-success-button wire:click="updateTglPulangBPJS" type="button"
                                    wire:confirm="Kirim update tanggal pulang ke BPJS?"
                                    wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="updateTglPulangBPJS" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                        </svg>
                                        Update Pasien Pulang BPJS
                                    </span>
                                    <span wire:loading wire:target="updateTglPulangBPJS" class="inline-flex items-center gap-2">
                                        <x-loading /> Mengirim ke BPJS...
                                    </span>
                                </x-success-button>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Status Pulang: <span class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '-' }}</span>
                            (1: Atas Persetujuan Dokter, 3: Atas Permintaan Sendiri, 4: Meninggal, 5: Lain-lain)
                        </p>
                    </div>
                </div>
            @endif

        </div>
    </x-border-form>

    {{-- ── DISCHARGE PLANNING ── --}}
    <x-border-form title="Discharge Planning" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Pelayanan Berkelanjutan" />
                <div class="mt-2 flex gap-4">
                    @foreach (['Ada', 'Tidak Ada'] as $opt)
                        <x-radio-button :label="$opt" :value="$opt" name="pelayananBerkelanjutan"
                            wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan"
                            :disabled="$isFormLocked" />
                    @endforeach
                </div>
                @if (
                    ($dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ?? '') ===
                        'Ada')
                    <x-textarea
                        wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.pelayananBerkelanjutan.ketPelayananBerkelanjutan"
                        class="w-full mt-2" rows="2" :disabled="$isFormLocked"
                        placeholder="Keterangan pelayanan berkelanjutan..." />
                @endif
            </div>
            <div>
                <x-input-label value="Penggunaan Alat Bantu" />
                <div class="mt-2 flex gap-4">
                    @foreach (['Ada', 'Tidak Ada'] as $opt)
                        <x-radio-button :label="$opt" :value="$opt" name="penggunaanAlatBantu"
                            wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu"
                            :disabled="$isFormLocked" />
                    @endforeach
                </div>
                @if (($dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantu'] ?? '') === 'Ada')
                    <x-textarea
                        wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.penggunaanAlatBantu.ketPenggunaanAlatBantu"
                        class="w-full mt-2" rows="2" :disabled="$isFormLocked" placeholder="Keterangan alat bantu..." />
                @endif
            </div>
        </div>
    </x-border-form>

</div>
