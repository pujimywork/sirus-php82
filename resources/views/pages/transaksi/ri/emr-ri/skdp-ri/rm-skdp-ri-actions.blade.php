<?php
// resources/views/pages/transaksi/ri/emr-ri/skdp-ri/rm-skdp-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;

    public array $dataDaftarRi = [];

    public array $formKontrol = [
        'noKontrolRS' => '',
        'noSKDPBPJS' => '',
        'noAntrian' => '',
        'tglKontrol' => '',
        'drKontrol' => '',
        'drKontrolDesc' => '',
        'drKontrolBPJS' => '',
        'poliKontrol' => '',
        'poliKontrolDesc' => '',
        'poliKontrolBPJS' => '',
        'noSEP' => '',
        'catatan' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-skdp-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-skdp-ri']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultKontrol();
        $current = $this->dataDaftarRi['kontrol'] ?? [];
        $this->dataDaftarRi['kontrol'] = array_replace_recursive($default, $current);
    }

    #[On('open-rm-skdp-ri')]
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

        $this->formKontrol = !empty($data['kontrol']) && is_array($data['kontrol'])
            ? $data['kontrol']
            : $this->getDefaultKontrol();

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-skdp-ri');
    }

    protected function resetForm(): void
    {
        $this->reset(['formKontrol']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    private function getDefaultKontrol(): array
    {
        $noSEP = $this->dataDaftarRi['sep']['noSep'] ?? '';

        // Cari dokter utama dari leveling dokter di pengkajian awal
        $levelingDokter = $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
        $dokterUtama = collect($levelingDokter)->firstWhere('levelDokter', 'Utama');

        $drId = $dokterUtama['drId'] ?? '';
        $drDesc = $dokterUtama['drName'] ?? '';
        $poliKontrol = $dokterUtama['poliId'] ?? '';
        $poliKontrolDesc = $dokterUtama['poliDesc'] ?? '';

        // Ambil kd_dr_bpjs & kd_poli_bpjs dari master (jika drId tersedia)
        $drKontrolBPJS = '';
        $poliKontrolBPJS = '';

        if ($drId) {
            $master = DB::table('rsmst_doctors')
                ->select('kd_dr_bpjs', 'kd_poli_bpjs')
                ->join('rsmst_polis', 'rsmst_polis.poli_id', 'rsmst_doctors.poli_id')
                ->where('rsmst_doctors.dr_id', $drId)
                ->first();

            if ($master) {
                $drKontrolBPJS = $master->kd_dr_bpjs ?? '';
                $poliKontrolBPJS = $master->kd_poli_bpjs ?? '';
            }
        }

        return [
            'noKontrolRS' => '',
            'noSKDPBPJS' => '',
            'noAntrian' => '',
            'tglKontrol' => Carbon::now(config('app.timezone'))->addDays(8)->format('d/m/Y'),
            'drKontrol' => $drId,
            'drKontrolDesc' => $drDesc,
            'drKontrolBPJS' => $drKontrolBPJS,
            'poliKontrol' => $poliKontrol,
            'poliKontrolDesc' => $poliKontrolDesc,
            'poliKontrolBPJS' => $poliKontrolBPJS,
            'noSEP' => $noSEP,
            'catatan' => '',
        ];
    }

    /* ═══════════════════════════════════════
     | LOV DOKTER LISTENER
    ═══════════════════════════════════════ */
    #[On('lov.selected.skdpRiDokterKontrol')]
    public function onDokterKontrolSelected(string $target, array $payload): void
    {
        $this->formKontrol['drKontrol'] = $payload['dr_id'] ?? '';
        $this->formKontrol['drKontrolDesc'] = $payload['dr_name'] ?? '';
        $this->formKontrol['drKontrolBPJS'] = $payload['kd_dr_bpjs'] ?? '';
        $this->formKontrol['poliKontrol'] = $payload['poli_id'] ?? '';
        $this->formKontrol['poliKontrolDesc'] = $payload['poli_desc'] ?? '';
        $this->formKontrol['poliKontrolBPJS'] = $payload['kd_poli_bpjs'] ?? '';

        $this->incrementVersion('modal-skdp-ri');
        $this->dispatch('focus-skdp-ri-antrian');
    }

    /* ═══════════════════════════════════════
     | VALIDATION
    ═══════════════════════════════════════ */
    protected function rules(): array
    {
        return [
            'formKontrol.tglKontrol' => 'required|date_format:d/m/Y',
            'formKontrol.drKontrol' => 'required',
            'formKontrol.poliKontrol' => 'required',
            'formKontrol.noKontrolRS' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'formKontrol.tglKontrol.required' => 'Tanggal kontrol wajib diisi.',
            'formKontrol.tglKontrol.date_format' => 'Format tanggal harus dd/mm/yyyy.',
            'formKontrol.drKontrol.required' => 'Dokter kontrol wajib dipilih.',
            'formKontrol.poliKontrol.required' => 'Poli kontrol wajib dipilih.',
            'formKontrol.noKontrolRS.required' => 'No kontrol RS wajib diisi.',
        ];
    }

    private function setNoKontrolRS(): void
    {
        if (empty($this->formKontrol['noKontrolRS'])) {
            $this->formKontrol['noKontrolRS'] = Carbon::now(config('app.timezone'))->addDays(8)->format('dmY')
                . ($this->formKontrol['drKontrol'] ?? '')
                . ($this->formKontrol['poliKontrol'] ?? '');
        }
    }

    /* ═══════════════════════════════════════
     | PUSH SKDP BPJS
    ═══════════════════════════════════════ */
    private function pushSuratKontrolBPJS(): void
    {
        $klaimStatus = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $this->dataDaftarRi['klaimId'] ?? '')
            ->value('klaim_status') ?? 'UMUM';

        if ($klaimStatus !== 'BPJS') {
            return;
        }

        $isNew = empty($this->formKontrol['noSKDPBPJS']);
        $response = $isNew
            ? VclaimTrait::suratkontrol_insert($this->formKontrol)->getOriginalContent()
            : VclaimTrait::suratkontrol_update($this->formKontrol)->getOriginalContent();

        $code = $response['metadata']['code'] ?? 0;
        $message = $response['metadata']['message'] ?? '';
        $label = $isNew ? 'KONTROL Post Inap' : 'UPDATE KONTROL';

        if ($code == 200) {
            if ($isNew) {
                $this->formKontrol['noSKDPBPJS'] = $response['response']['noSuratKontrol'] ?? '';
            }
            $this->dispatch('toast', type: 'success', message: "{$label} {$code} {$message}");
        } else {
            $this->dispatch('toast', type: 'error', message: "{$label} {$code} {$message}");
        }
    }

    /* ═══════════════════════════════════════
     | SAVE
    ═══════════════════════════════════════ */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        if (empty($this->dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan, silakan buka ulang form.');
            return;
        }

        $this->setNoKontrolRS();
        $this->validate();

        // Push ke BPJS — DI LUAR transaksi DB
        $this->pushSuratKontrolBPJS();

        // Simpan ke DB
        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($fresh)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                $fresh['kontrol'] = $this->formKontrol;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->incrementVersion('modal-skdp-ri');
            $this->dispatch('toast', type: 'success', message: 'Surat Kontrol berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetakSKDP(): void
    {
        if (empty($this->riHdrNo) || empty($this->formKontrol['tglKontrol'])) {
            $this->dispatch('toast', type: 'error', message: 'Data surat kontrol belum tersedia untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-skdp-ri.open', riHdrNo: (string) $this->riHdrNo);
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-skdp-ri', [$riHdrNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                x-data
                x-on:focus-skdp-ri-tgl.window="$nextTick(() => $refs.inputTglKontrol?.focus())"
                x-on:focus-skdp-ri-antrian.window="$nextTick(() => $refs.inputNoAntrian?.focus())"
                x-on:focus-skdp-ri-catatan.window="$nextTick(() => $refs.inputCatatan?.focus())">

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

                @if (!empty($formKontrol['tglKontrol']))
                    <div class="grid grid-cols-2 gap-x-6 gap-y-4">

                        {{-- ══ KOLOM KIRI ══ --}}

                        {{-- No SEP --}}
                        <div>
                            <x-input-label value="No SEP *" class="mb-1" />
                            <x-text-input wire:model.live="formKontrol.noSEP" placeholder="No SEP"
                                :disabled="true" class="w-full font-mono" />
                        </div>

                        {{-- ══ KOLOM KANAN ══ --}}

                        {{-- No SKDP BPJS + No Kontrol RS --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label value="No SKDP BPJS" class="mb-1" />
                                <x-text-input wire:model.live="formKontrol.noSKDPBPJS" placeholder="Otomatis dari BPJS"
                                    :disabled="true" class="w-full font-mono" />
                            </div>
                            <div>
                                <x-input-label value="No Kontrol RS" class="mb-1" />
                                <x-text-input wire:model.live="formKontrol.noKontrolRS"
                                    placeholder="Auto-generate" :disabled="true" class="w-full font-mono" />
                                <x-input-error :messages="$errors->get('formKontrol.noKontrolRS')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Tgl, Dokter, Poli, Catatan — 1 baris --}}
                        <div class="col-span-2 grid grid-cols-4 gap-3">
                            {{-- Tanggal Rencana Kontrol --}}
                            <div>
                                <x-input-label value="Tgl Rencana Kontrol *" class="mb-1" />
                                <x-text-input wire:model.live="formKontrol.tglKontrol" placeholder="dd/mm/yyyy"
                                    :disabled="$isFormLocked" x-ref="inputTglKontrol"
                                    x-init="$nextTick(() => $refs.inputTglKontrol?.focus())"
                                    x-on:keyup.enter="$nextTick(() => $refs.lovDokterInput?.querySelector('input')?.focus())"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('formKontrol.tglKontrol')" class="mt-1" />
                            </div>

                            {{-- Dokter Kontrol --}}
                            <div x-ref="lovDokterInput">
                                <livewire:lov.dokter.lov-dokter label="Dokter Kontrol *" target="skdpRiDokterKontrol"
                                    :initialDrId="$formKontrol['drKontrol'] ?? null" :disabled="$isFormLocked"
                                    wire:key="lov-dokter-skdp-ri-{{ $riHdrNo }}-{{ $renderVersions['modal-skdp-ri'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('formKontrol.drKontrol')" class="mt-1" />
                                @if (!empty($formKontrol['drKontrolBPJS']))
                                    <p class="mt-1 text-xs text-gray-500 font-mono">{{ $formKontrol['drKontrolBPJS'] }}</p>
                                @endif
                            </div>

                            {{-- Poli Kontrol --}}
                            <div>
                                <x-input-label value="Poli Kontrol *" class="mb-1" />
                                <x-text-input :value="($formKontrol['poliKontrolDesc'] ?? '')" placeholder="Otomatis dari dokter"
                                    :disabled="true" class="w-full" />
                                @if (!empty($formKontrol['poliKontrolBPJS']))
                                    <p class="mt-1 text-xs text-gray-500 font-mono">{{ $formKontrol['poliKontrolBPJS'] }}</p>
                                @endif
                                <x-input-error :messages="$errors->get('formKontrol.poliKontrol')" class="mt-1" />
                            </div>

                            {{-- Catatan --}}
                            <div>
                                <x-input-label value="Catatan" class="mb-1" />
                                <x-text-input wire:model.live="formKontrol.catatan"
                                    placeholder="Catatan tambahan..." :disabled="$isFormLocked" x-ref="inputCatatan"
                                    class="w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        {{-- Cetak --}}
                        @if (!empty($formKontrol['noSKDPBPJS']) || !empty($formKontrol['noKontrolRS']))
                            <x-secondary-button type="button" wire:click="cetakSKDP" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="cetakSKDP" class="inline-flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak SKDP
                                </span>
                                <span wire:loading wire:target="cetakSKDP" class="inline-flex items-center gap-1">
                                    <x-loading /> Mencetak...
                                </span>
                            </x-secondary-button>
                        @endif

                        {{-- Simpan --}}
                        @if (!$isFormLocked)
                            <x-success-button type="button" wire:click="save" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                    Simpan & Kirim SKDP ke BPJS
                                </span>
                                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                    <x-loading />
                                    Mengirim ke BPJS...
                                </span>
                            </x-success-button>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Cetak SKDP --}}
    <livewire:pages::components.modul-dokumen.b-p-j-s.cetak-skdp.cetak-skdp wire:key="cetak-skdp-ri-{{ $riHdrNo }}" />
</div>
