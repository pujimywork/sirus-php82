<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/surat-kematian/rm-surat-kematian-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\SuratKematianClause;
use App\Support\NomorSuratKematian;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public ?int $rjNo = null;
    public bool $disabled = false;
    public bool $isFormLocked = false;

    /** Surat kematian = satu per kunjungan (pasien meninggal sekali), jadi single-entry. */
    public array $newForm = [];
    public bool $sudahAda = false;
    public bool $isFinal = false;

    /** Data screening yang jadi dasar surat — read-only, dipakai prefill & gate P0. */
    public array $screening = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-surat-kematian-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-surat-kematian-ugd']);
        $this->newForm = $this->defaultForm();

        if ($rjNo) {
            $this->muatData();
        }
    }

    #[On('refresh-surat-kematian-ugd')]
    public function muatData(): void
    {
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }

        $this->screening = $data['screening'] ?? [];

        $tersimpan = $data['suratKematianUGD'] ?? [];
        $this->sudahAda = !empty($tersimpan);
        $this->isFinal = (bool) ($tersimpan['isFinal'] ?? false);

        // Record lama bisa kurang key — rapikan lewat default, jangan andalkan ?? di blade.
        $this->newForm = array_replace($this->defaultForm(), is_array($tersimpan) ? $tersimpan : []);

        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled || $this->isFinal;

        // Prefill dari Screening — hanya untuk surat yang BELUM tersimpan, supaya tak
        // menimpa koreksi manual dokter pada surat yang sudah dibuat.
        if (!$this->sudahAda) {
            $this->newForm['tanggalMeninggal'] = $this->screening['waktuMeninggal'] ?? '';
            $this->newForm['nomorSurat'] = NomorSuratKematian::generate();
        }
    }

    /* ===============================
     | GATE P0
     =============================== */

    /**
     * Surat kematian hanya relevan bila screening menyimpulkan P0. Gate-nya dibaca dari
     * hasil tersimpan (triaseSaran), BUKAN dihitung ulang di sini — logika triase tetap
     * satu sumber di form Screening (hitungTriase()).
     */
    public function isPasienMeninggal(): bool
    {
        return ($this->screening['triaseSaran'] ?? '') === 'P0';
    }

    /* ===============================
     | MODAL
     =============================== */
    public function openModal(): void
    {
        $this->muatData();
        $this->resetValidation();
        $this->incrementVersion('modal-surat-kematian-ugd');
        $this->dispatch('open-modal', name: "rm-surat-kematian-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: "rm-surat-kematian-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.nomorSurat' => 'required|string|max:60',
            'newForm.tanggalMeninggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.tempatMeninggal' => 'required|string|max:120',
            'newForm.sebabKematian' => 'required|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.nomorSurat' => 'Nomor Surat',
            'newForm.tanggalMeninggal' => 'Tanggal / Jam Meninggal',
            'newForm.tempatMeninggal' => 'Tempat Meninggal',
            'newForm.sebabKematian' => 'Sebab Kematian',
        ];
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function setTanggalMeninggalSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newForm['tanggalMeninggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Surat sudah dikunci, tidak dapat diubah.');
            return;
        }

        if (!$this->isPasienMeninggal()) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian hanya untuk pasien dengan hasil screening P0.');
            return;
        }

        $this->validateWithToast();
        $this->persist(false, 'Simpan draft');
    }

    /**
     * Finalize + kunci. Menerangkan kematian adalah kewenangan dokter — TTD-E menstempel
     * user yang login (bukan nama ketikan) supaya gambar TTD-nya bisa di-resolve saat cetak.
     */
    public function ttdDokter(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (!$this->isPasienMeninggal()) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian hanya untuk pasien dengan hasil screening P0.');
            return;
        }

        // validate() duluan — kalau guard role ditaruh di depan, field wajib tak jadi merah.
        $this->validateWithToast();

        if (!auth()->user()->hasAnyRole(['Dokter', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya dokter yang berwenang menandatangani surat kematian.');
            return;
        }

        $this->newForm['dokterPenerang'] = auth()->user()->myuser_name;
        $this->newForm['dokterPenerangCode'] = auth()->user()->myuser_code;
        $this->newForm['dokterPenerangDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $this->persist(true, 'Kunci (TTD Dokter)');
    }

    public function cetak(): void
    {
        if (!$this->sudahAda) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian belum tersimpan.');
            return;
        }

        $this->dispatch('cetak-surat-kematian-ugd.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | PERSIST
     =============================== */
    private function persist(bool $final, string $aksi): void
    {
        try {
            DB::transaction(function () use ($final, $aksi) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Surat ter-TTD = dokumen legal, tak boleh ditimpa diam-diam oleh request lain.
                if (!empty($data['suratKematianUGD']['isFinal'])) {
                    throw new \RuntimeException('Surat sudah ditandatangani dan dikunci.');
                }

                $entry = $this->newForm;
                $entry['isFinal'] = $final;
                $entry['createdAt'] = $data['suratKematianUGD']['createdAt'] ?? Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                // Stempel versi klausul hanya sekali, saat record dibuat.
                $entry['clauseVersion'] = $data['suratKematianUGD']['clauseVersion'] ?? SuratKematianClause::CURRENT;

                $data['suratKematianUGD'] = $entry;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->appendAdminLogUGD((int) $this->rjNo, 'Surat Keterangan Kematian — ' . $aksi . ' (No. ' . ($entry['nomorSurat'] ?: '-') . ')', 'MR');
            });

            $this->muatData();
            $this->incrementVersion('modal-surat-kematian-ugd');
            $this->dispatch('toast', type: 'success', message: $final ? 'Surat kematian ditandatangani & dikunci.' : 'Draft surat kematian tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | DEFAULT
     =============================== */
    private function defaultForm(): array
    {
        return [
            'nomorSurat' => '',
            'tanggalMeninggal' => '',
            'tempatMeninggal' => 'Instalasi Gawat Darurat',
            'sebabKematian' => '',
            'keterangan' => '',
            'dokterPenerang' => '',
            'dokterPenerangCode' => '',
            'dokterPenerangDate' => '',
            'isFinal' => false,
            'createdAt' => '',
            'clauseVersion' => SuratKematianClause::CURRENT,
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Surat Keterangan Kematian
                    </h3>
                    @if (!$this->isPasienMeninggal())
                        <x-badge variant="warning">Tidak berlaku</x-badge>
                    @elseif (!$sudahAda)
                        <x-badge variant="warning">Belum ada</x-badge>
                    @elseif ($isFinal)
                        <x-badge variant="success">Ditandatangani</x-badge>
                    @else
                        <x-badge variant="warning">Draft</x-badge>
                    @endif
                </div>

                @if ($this->isPasienMeninggal())
                    <div class="flex gap-2 shrink-0">
                        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                            wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                            <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                                Buka Formulir
                            </span>
                            <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                                <x-loading class="w-4 h-4" /> Memuat...
                            </span>
                        </x-primary-button>

                        @if ($sudahAda)
                            <x-secondary-button type="button" wire:click="cetak" wire:loading.attr="disabled"
                                wire:target="cetak" class="gap-2">
                                <span wire:loading.remove wire:target="cetak" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak
                                </span>
                                <span wire:loading wire:target="cetak" class="flex items-center gap-1.5">
                                    <x-loading class="w-4 h-4" /> Memuat...
                                </span>
                            </x-secondary-button>
                        @endif
                    </div>
                @endif
            </div>

            @if (!$this->isPasienMeninggal())
                {{-- Gate P0: jelaskan KENAPA tak bisa, jangan cuma sembunyikan tombolnya --}}
                <p class="text-base text-muted dark:text-gray-400">
                    Surat keterangan kematian hanya dapat dibuat bila hasil <strong>Screening UGD</strong> adalah
                    <strong>P0 (Meninggal)</strong> — yaitu ada tanda henti jantung-nafas dan dokter sudah mengisi
                    pernyataan meninggal di form Screening.
                </p>
            @else
                <p class="text-base text-muted dark:text-gray-400">
                    Surat keterangan kematian yang diterbitkan rumah sakit untuk pasien meninggal di IGD. Nomor surat,
                    sebab kematian, dan tanda tangan dokter diisi di formulir. Sekali ditandatangani, surat terkunci.
                </p>

                @if ($sudahAda && !empty($newForm['nomorSurat']))
                    <div class="overflow-x-auto">
                        <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Surat Tersimpan</h4>
                        <table class="min-w-full text-sm border rounded-lg border-hairline dark:border-gray-700">
                            <thead class="bg-surface-soft dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">No. Surat</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Waktu Meninggal</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Dokter</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-t border-hairline dark:border-gray-700">
                                    <td class="px-3 py-2 text-body dark:text-gray-300">{{ $newForm['nomorSurat'] }}</td>
                                    <td class="px-3 py-2 text-body dark:text-gray-300">{{ $newForm['tanggalMeninggal'] ?: '-' }}</td>
                                    <td class="px-3 py-2 text-body dark:text-gray-300">{{ $newForm['dokterPenerang'] ?: '-' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($isFinal)
                                            <x-badge variant="success">Ditandatangani</x-badge>
                                        @else
                                            <x-badge variant="warning">Draft</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-surat-kematian-ugd-{{ $rjNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-surat-kematian-ugd', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div>
                    <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Surat Keterangan Kematian</h2>
                    <p class="mt-0.5 text-base text-muted dark:text-gray-400">Instalasi Gawat Darurat</p>
                    @if ($isFormLocked)
                        <div class="mt-2"><x-badge variant="danger">Read Only</x-badge></div>
                    @endif
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien — surat legal, identitas harus terlihat saat mengisi --}}
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="sk-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-4 border shadow-sm bg-canvas border-hairline sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Ringkasan dari Screening (read-only) --}}
                        <div
                            class="px-3 py-2 text-base border rounded-lg bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200">
                            Dasar surat ini: hasil Screening UGD <strong>P0 — Meninggal</strong>.
                            @if (!empty($screening['waktuMeninggal']))
                                Waktu meninggal tercatat <strong>{{ $screening['waktuMeninggal'] }}</strong>.
                            @endif
                            @if (!empty($screening['dokterPenyataMeninggal']))
                                Dinyatakan oleh <strong>{{ $screening['dokterPenyataMeninggal'] }}</strong>.
                            @endif
                        </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Nomor Surat" />
                        <x-text-input :value="$newForm['nomorSurat'] ?? ''" disabled class="w-full mt-1" />
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Dibuat otomatis (stempel waktu). Tetap sama setelah surat disimpan.
                        </p>
                        <x-input-error :messages="$errors->get('newForm.nomorSurat')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Tanggal / Jam Meninggal" :required="true" />
                        <div class="flex items-center gap-2 mt-1">
                            <x-text-input wire:model.blur="newForm.tanggalMeninggal" placeholder="dd/mm/yyyy hh:mm:ss"
                                :disabled="$isFormLocked" :error="$errors->has('newForm.tanggalMeninggal')"
                                class="w-full" />
                            <x-now-button wire:click="setTanggalMeninggalSekarang" :disabled="$isFormLocked" />
                        </div>
                        <x-input-error :messages="$errors->get('newForm.tanggalMeninggal')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Tempat Meninggal" :required="true" />
                    <x-text-input wire:model.blur="newForm.tempatMeninggal" :disabled="$isFormLocked"
                        :error="$errors->has('newForm.tempatMeninggal')" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('newForm.tempatMeninggal')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Sebab Kematian" :required="true" />
                    <x-textarea wire:model.blur="newForm.sebabKematian" rows="3"
                        placeholder="Sebab kematian menurut pemeriksaan dokter..." :disabled="$isFormLocked"
                        :error="$errors->has('newForm.sebabKematian')" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('newForm.sebabKematian')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Keterangan Tambahan" />
                    <x-textarea wire:model.blur="newForm.keterangan" rows="2" placeholder="Opsional..."
                        :disabled="$isFormLocked" class="w-full mt-1" />
                </div>

                {{-- TTD Dokter --}}
                <div class="pt-4 border-t border-hairline dark:border-gray-700">
                    <x-input-label value="Dokter yang Menerangkan" />
                    @if (!empty($newForm['dokterPenerang']))
                        <div
                            class="px-3 py-2 mt-1 text-base border rounded-lg bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200">
                            <strong>{{ $newForm['dokterPenerang'] }}</strong>
                            @if (!empty($newForm['dokterPenerangCode']))
                                (Kode: {{ $newForm['dokterPenerangCode'] }})
                            @endif
                            @if (!empty($newForm['dokterPenerangDate']))
                                &mdash; {{ $newForm['dokterPenerangDate'] }}
                            @endif
                        </div>
                    @else
                        <p class="mt-1 text-base text-muted dark:text-gray-400">
                            Belum ditandatangani. Menandatangani akan <strong>mengunci</strong> surat ini.
                        </p>
                    @endif
                </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 flex justify-end gap-3 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                @if (!$isFormLocked)
                    <x-secondary-button wire:click="saveDraft" wire:loading.attr="disabled">Simpan
                        Draft</x-secondary-button>
                    <x-primary-button wire:click="ttdDokter" wire:loading.attr="disabled">TTD-E Dokter &
                        Kunci</x-primary-button>
                @endif
                @if ($sudahAda)
                    <x-primary-button wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak">
                        <span wire:loading.remove wire:target="cetak" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak
                        </span>
                        <span wire:loading wire:target="cetak" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Mencetak...</span>
                    </x-primary-button>
                @endif
            </div>

        </div>
    </x-modal>

    {{-- Opener cetak PDF --}}
    <livewire:pages::components.modul-dokumen.u-g-d.surat-kematian.cetak-surat-kematian
        wire:key="cetak-surat-kematian-ugd-{{ $rjNo ?? 'init' }}" />
</div>
