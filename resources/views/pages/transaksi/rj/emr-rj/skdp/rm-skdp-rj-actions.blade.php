<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    // dataDaftarPoliRJ hanya sebagai reference — TIDAK di-bind ke form
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-skdp-rj'];

    // Form entry terpisah — user hanya mengubah properti ini,
    // aman dari re-fetch / overwrite dataDaftarPoliRJ
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

    /* ═══════════════════════════════════════
     | MOUNT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->registerAreas(['modal-skdp-rj']);
        $this->openSkdp($this->rjNo);
    }

    /* ═══════════════════════════════════════
     | OPEN
    ═══════════════════════════════════════ */
    public function openSkdp(int $rjNo): void
    {
        $this->resetFormEntry();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);
        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Isi formKontrol:
        // 1. Dari DB jika sudah ada → pakai
        // 2. Belum ada → pakai default
        $this->formKontrol = !empty($dataDaftarPoliRJ['kontrol']) && is_array($dataDaftarPoliRJ['kontrol']) ? $dataDaftarPoliRJ['kontrol'] : $this->getDefaultKontrol();

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->incrementVersion('modal-skdp-rj');
    }

    protected function resetFormEntry(): void
    {
        $this->reset(['formKontrol']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    /* ═══════════════════════════════════════
     | DEFAULT KONTROL STRUCTURE
    ═══════════════════════════════════════ */
    private function getDefaultKontrol(): array
    {
        $noSEP = DB::table('rsview_rjkasir')->where('rj_no', $this->rjNo)->value('vno_sep') ?? '';

        $dokter = DB::table('rsmst_doctors')
            ->select('rsmst_doctors.dr_id', 'rsmst_doctors.dr_name', 'kd_dr_bpjs', 'rsmst_polis.poli_id', 'rsmst_polis.poli_desc', 'kd_poli_bpjs')
            ->join('rsmst_polis', 'rsmst_polis.poli_id', 'rsmst_doctors.poli_id')
            ->where('rsmst_doctors.dr_id', $this->dataDaftarPoliRJ['drId'] ?? '')
            ->first();

        return [
            'noKontrolRS' => '',
            'noSKDPBPJS' => '',
            'noAntrian' => '',
            'tglKontrol' => Carbon::now(config('app.timezone'))->addDays(8)->format('d/m/Y'),
            'drKontrol' => $dokter->dr_id ?? '',
            'drKontrolDesc' => $dokter->dr_name ?? '',
            'drKontrolBPJS' => $dokter->kd_dr_bpjs ?? '',
            'poliKontrol' => $dokter->poli_id ?? '',
            'poliKontrolDesc' => $dokter->poli_desc ?? '',
            'poliKontrolBPJS' => $dokter->kd_poli_bpjs ?? '',
            'noSEP' => $noSEP,
            'catatan' => '',
        ];
    }

    /* ═══════════════════════════════════════
     | LOV DOKTER LISTENER
    ═══════════════════════════════════════ */
    #[On('lov.selected.skdpRjDokterKontrol')]
    public function onDokterKontrolSelected(string $target, array $payload): void
    {
        // Update formKontrol — bukan dataDaftarPoliRJ
        $this->formKontrol['drKontrol'] = $payload['dr_id'] ?? '';
        $this->formKontrol['drKontrolDesc'] = $payload['dr_name'] ?? '';
        $this->formKontrol['drKontrolBPJS'] = $payload['kd_dr_bpjs'] ?? '';
        $this->formKontrol['poliKontrol'] = $payload['poli_id'] ?? '';
        $this->formKontrol['poliKontrolDesc'] = $payload['poli_desc'] ?? '';
        $this->formKontrol['poliKontrolBPJS'] = $payload['kd_poli_bpjs'] ?? '';

        $this->incrementVersion('modal-skdp-rj');

        // Setelah dokter dipilih, fokus ke No Antrian
        $this->dispatch('focus-skdp-antrian');
    }

    /* ═══════════════════════════════════════
     | VALIDATION RULES
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

    /* ═══════════════════════════════════════
     | SET NO KONTROL RS (auto-generate)
    ═══════════════════════════════════════ */
    private function setNoKontrolRS(): void
    {
        if (empty($this->formKontrol['noKontrolRS'])) {
            $this->formKontrol['noKontrolRS'] = Carbon::now(config('app.timezone'))->addDays(8)->format('dmY') . ($this->formKontrol['drKontrol'] ?? '') . ($this->formKontrol['poliKontrol'] ?? '');
        }
    }

    /* ═══════════════════════════════════════
     | PUSH SKDP BPJS
     | Dipanggil DI LUAR transaksi — API call ke BPJS tidak boleh
     | berada di dalam DB::transaction.
    ═══════════════════════════════════════ */
    private function pushSuratKontrolBPJS(): void
    {
        if (($this->dataDaftarPoliRJ['klaimStatus'] ?? '') !== 'BPJS' && ($this->dataDaftarPoliRJ['klaimId'] ?? '') !== 'JM') {
            return;
        }

        $isNew = empty($this->formKontrol['noSKDPBPJS']);
        $response = $isNew ? VclaimTrait::suratkontrol_insert($this->formKontrol)->getOriginalContent() : VclaimTrait::suratkontrol_update($this->formKontrol)->getOriginalContent();

        $code = $response['metadata']['code'] ?? 0;
        $message = $response['metadata']['message'] ?? '';
        $label = $isNew ? 'KONTROL' : 'UPDATE KONTROL';

        if ($code == 200) {
            if ($isNew) {
                // Simpan noSKDPBPJS ke formKontrol — akan ikut tersimpan ke DB
                $this->formKontrol['noSKDPBPJS'] = $response['response']['noSuratKontrol'] ?? '';
            }
            $this->dispatch('toast', type: 'success', message: "{$label} {$code} {$message}");
        } else {
            $this->dispatch('toast', type: 'error', message: "{$label} {$code} {$message}");
        }
    }

    /* ═══════════════════════════════════════
     | SAVE
     |
     | Bisa dipanggil dari:
     | - Tombol simpan langsung (wire:click)
     | - Parent perencanaan setelah simpan tindak lanjut = 'Kontrol'
     |
     | Alur:
     | 1. Guard isFormLocked + dataDaftarPoliRJ
     | 2. Re-fetch DB → cek tindakLanjut fresh (bisa berubah dari parent)
     | 3. Jika bukan 'Kontrol' → skip tanpa error
     | 4. setNoKontrolRS + validate
     | 5. pushSuratKontrolBPJS — DI LUAR transaksi (API call)
     | 6. lockRJRow + patch hanya key 'kontrol' ke DB
    ═══════════════════════════════════════ */
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // 3. Re-fetch dari DB untuk cek tindakLanjut yang sudah disimpan parent
        $freshData = $this->findDataRJ($this->rjNo);

        if (($freshData['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') !== 'Kontrol') {
            return; // bukan kontrol, skip tanpa error
        }

        // 4. Sync klaimStatus/klaimId dari data fresh untuk pushSuratKontrolBPJS
        //    Hanya update field ini — formKontrol tetap aman
        $this->dataDaftarPoliRJ['klaimStatus'] = $freshData['klaimStatus'] ?? '';
        $this->dataDaftarPoliRJ['klaimId'] = $freshData['klaimId'] ?? '';

        // 5. Init formKontrol hanya jika child belum pernah di-mount
        //    (kasus: child mount tapi rjNo belum di-set)
        if (empty($this->formKontrol['tglKontrol'])) {
            $this->dataDaftarPoliRJ = $freshData;
            $this->formKontrol = !empty($freshData['kontrol']) ? $freshData['kontrol'] : $this->getDefaultKontrol();
        }

        // 6. Auto-generate noKontrolRS + validasi
        $this->setNoKontrolRS();
        $this->validate();

        // 7. Push ke BPJS — DI LUAR transaksi DB
        $this->pushSuratKontrolBPJS();

        // 8. Simpan ke DB
        try {
            DB::transaction(function () {
                // Lock row dulu — cegah race condition
                $this->lockRJRow($this->rjNo);

                // Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // Patch hanya key 'kontrol' — key lain tidak tersentuh
                $data['kontrol'] = $this->formKontrol;

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-skdp-rj');
            $this->dispatch('toast', type: 'success', message: 'Surat Kontrol berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-skdp-rj', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                x-data {{-- Focus trap: saat enter di field manapun, lanjut ke ref berikutnya --}} x-on:focus-skdp-tgl.window="$nextTick(() => $refs.inputTglKontrol?.focus())"
                x-on:focus-skdp-antrian.window="$nextTick(() => $refs.inputNoAntrian?.focus())"
                x-on:focus-skdp-catatan.window="$nextTick(() => $refs.inputCatatan?.focus())">

                {{-- Render saat formKontrol sudah terisi --}}
                @if (!empty($formKontrol['tglKontrol']))
                    <div class="w-full">
                        <div class="grid grid-cols-1 gap-2">

                            {{-- KOLOM KIRI --}}
                            <div class="space-y-4">

                                {{-- No Kontrol RS — disabled, auto-generate --}}
                                <div>
                                    <x-input-label value="No Kontrol RS" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.noKontrolRS"
                                        placeholder="Auto-generate jika kosong" :disabled="true" class="w-full" />
                                    <x-input-error :messages="$errors->get('formKontrol.noKontrolRS')" class="mt-1" />
                                </div>

                                {{-- No SEP — disabled --}}
                                <div>
                                    <x-input-label value="No SEP" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.noSEP" placeholder="No SEP"
                                        :disabled="true" class="w-full" />
                                </div>

                                {{-- No SKDP BPJS — disabled --}}
                                <div>
                                    <x-input-label value="No SKDP BPJS" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.noSKDPBPJS" placeholder="No SKDP BPJS"
                                        :disabled="true" class="w-full" />
                                </div>

                                {{-- No Antrian — Enter → Catatan --}}
                                <div>
                                    <x-input-label value="No Antrian" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.noAntrian" placeholder="No Antrian"
                                        :disabled="$isFormLocked" x-ref="inputNoAntrian"
                                        x-on:keyup.enter="$refs.inputCatatan?.focus()" class="w-full" />
                                </div>

                            </div>

                            {{-- KOLOM KANAN --}}
                            <div class="space-y-4">

                                {{-- Tgl Kontrol — Enter → fokus ke LOV dokter via ref --}}
                                <div>
                                    <x-input-label value="Tanggal Kontrol" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.tglKontrol" placeholder="dd/mm/yyyy"
                                        :disabled="$isFormLocked" x-ref="inputTglKontrol" x-init="$nextTick(() => $refs.inputTglKontrol?.focus())"
                                        x-on:keyup.enter="$nextTick(() => $refs.lovDokterInput?.querySelector('input')?.focus())"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('formKontrol.tglKontrol')" class="mt-1" />
                                </div>

                                {{-- LOV Dokter — setelah pilih dokter, auto fokus ke No Antrian via event --}}
                                <div x-ref="lovDokterInput">
                                    <livewire:lov.dokter.lov-dokter label="Dokter Kontrol" target="skdpRjDokterKontrol"
                                        :initialDrId="$formKontrol['drKontrol'] ?? null" :disabled="$isFormLocked"
                                        wire:key="lov-dokter-skdp-{{ $rjNo }}-{{ $renderVersions['modal-skdp-rj'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('formKontrol.drKontrol')" class="mt-1" />
                                </div>

                                {{-- Poli (read-only, ikut dokter) --}}
                                <div>
                                    <x-input-label value="Poli Kontrol" class="mb-1" />
                                    <x-text-input :value="($formKontrol['poliKontrol'] ?? '') .
                                        ($formKontrol['poliKontrol'] ?? '' ? ' / ' : '') .
                                        ($formKontrol['poliKontrolBPJS'] ?? '') .
                                        ' ' .
                                        ($formKontrol['poliKontrolDesc'] ?? '')" placeholder="Otomatis dari dokter"
                                        :disabled="true" class="w-full" />
                                    <x-input-error :messages="$errors->get('formKontrol.poliKontrol')" class="mt-1" />
                                </div>

                                {{-- Catatan --}}
                                <div>
                                    <x-input-label value="Keterangan" class="mb-1" />
                                    <x-text-input wire:model.live="formKontrol.catatan"
                                        placeholder="Catatan tambahan..." :disabled="$isFormLocked" x-ref="inputCatatan"
                                        class="w-full" />
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- Tombol Save --}}
                    @if (!$isFormLocked)
                        @php
                            $klaimStatus = $dataDaftarPoliRJ['klaimStatus'] ?? '';
                            $klaimId = $dataDaftarPoliRJ['klaimId'] ?? '';
                            $isBPJS = $klaimStatus === 'BPJS' || $klaimId === 'JM';
                        @endphp
                        <div class="flex justify-end pt-2">
                            <x-success-button type="button" wire:click="save" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                    @if ($isBPJS)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                        </svg>
                                        Simpan & Kirim SKDP ke BPJS
                                    @else
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Simpan Surat Kontrol
                                    @endif
                                </span>
                                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                    <x-loading />
                                    {{ $isBPJS ? 'Mengirim ke BPJS...' : 'Menyimpan...' }}
                                </span>
                            </x-success-button>
                        </div>
                    @endif
                @endif

            </div>
        </div>
    </div>
</div>
