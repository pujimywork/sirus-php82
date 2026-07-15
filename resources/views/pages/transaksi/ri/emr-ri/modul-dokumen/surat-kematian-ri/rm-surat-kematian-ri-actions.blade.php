<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/surat-kematian-ri/rm-surat-kematian-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\SuratKematianClause;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public ?string $riHdrNo = null;
    public bool $disabled = false;
    public bool $isFormLocked = false;

    /** Surat kematian = satu per kunjungan. */
    public array $newForm = [];
    public bool $sudahAda = false;
    public bool $isFinal = false;

    /** Slice tindakLanjut Perencanaan — sumber gate, nomor surat, & tanggal meninggal. */
    public array $tindakLanjut = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-surat-kematian-ri'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-surat-kematian-ri']);
        $this->newForm = $this->defaultForm();

        if ($riHdrNo) {
            $this->muatData();
        }
    }

    #[On('refresh-surat-kematian-ri')]
    public function muatData(): void
    {
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }

        $this->tindakLanjut = $data['perencanaan']['tindakLanjut'] ?? [];

        $tersimpan = $data['suratKematianRI'] ?? [];
        $this->sudahAda = !empty($tersimpan);
        $this->isFinal = (bool) ($tersimpan['isFinal'] ?? false);

        $this->newForm = array_replace($this->defaultForm(), is_array($tersimpan) ? $tersimpan : []);

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled || $this->isFinal;
    }

    /* ===============================
     | GATE + SUMBER DATA PERENCANAAN
     =============================== */

    /**
     * Gate RI BEDA dari UGD: tak ada triase P0 di rawat inap. Penanda meninggal di RI
     * adalah status pulang BPJS 4 di Perencanaan (App\Support\DischargeDisposition:
     * 'Meninggal' => tindakLanjutKode 419099009, tindakLanjutKodeBpjs 4).
     */
    public function isPasienMeninggal(): bool
    {
        return (string) ($this->tindakLanjut['statusPulang'] ?? '') === '4';
    }

    /**
     * Nomor surat TIDAK dibuat ulang di sini — Perencanaan sudah memilikinya
     * (`noSuratMeninggal`) dan nomor itulah yang dikirim ke BPJS saat update pulang SEP
     * (VclaimTrait: required_if statusPulang=4). Kalau modul ini bikin nomor sendiri,
     * nomor di kertas bisa beda dengan yang dilaporkan ke BPJS. Satu nomor, satu pemilik.
     */
    public function nomorSurat(): string
    {
        return trim((string) ($this->tindakLanjut['noSuratMeninggal'] ?? ''));
    }

    public function tanggalMeninggal(): string
    {
        return trim((string) ($this->tindakLanjut['tglMeninggal'] ?? ''));
    }

    public function perencanaanLengkap(): bool
    {
        return $this->nomorSurat() !== '' && $this->tanggalMeninggal() !== '';
    }

    /* ===============================
     | MODAL
     =============================== */
    public function openModal(): void
    {
        $this->muatData();
        $this->resetValidation();
        $this->incrementVersion('modal-surat-kematian-ri');
        $this->dispatch('open-modal', name: "rm-surat-kematian-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: "rm-surat-kematian-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tempatMeninggal' => 'required|string|max:120',
            'newForm.sebabKematian' => 'required|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return ['required' => ':attribute wajib diisi.'];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tempatMeninggal' => 'Tempat Meninggal',
            'newForm.sebabKematian' => 'Sebab Kematian',
        ];
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function saveDraft(): void
    {
        if ($this->gateTertutup()) {
            return;
        }

        $this->validateWithToast();

        if (!$this->perencanaanBelumLengkap()) {
            $this->persist(false, 'Simpan draft');
        }
    }

    public function ttdDokter(): void
    {
        if ($this->gateTertutup()) {
            return;
        }

        // validate() duluan — guard di depan bikin field wajib tak pernah memerah.
        // perencanaanBelumLengkap() sengaja SETELAH validate: ia sejenis cek kelengkapan,
        // jadi dokter melihat SEMUA yang kurang sekaligus (field merah + toast), bukan
        // satu per satu tiap klik.
        $this->validateWithToast();

        if ($this->perencanaanBelumLengkap()) {
            return;
        }

        if (!auth()->user()->hasAnyRole(['Dokter', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya dokter yang berwenang menandatangani surat kematian.');
            return;
        }

        $this->newForm['dokterPenerang'] = auth()->user()->myuser_name;
        $this->newForm['dokterPenerangCode'] = auth()->user()->myuser_code;
        $this->newForm['dokterPenerangDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $this->persist(true, 'Kunci (TTD Dokter)');
    }

    /**
     * Gerbang keras: kondisi di mana form ini seharusnya tak boleh disentuh sama sekali.
     * Boleh early-return sebelum validate() — memerahkan field tak ada gunanya kalau
     * masalahnya bukan isi form.
     */
    private function gateTertutup(): bool
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Surat sudah dikunci, tidak dapat diubah.');
            return true;
        }

        if (!$this->isPasienMeninggal()) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian hanya untuk pasien dengan status pulang Meninggal.');
            return true;
        }

        return false;
    }

    /** Cek kelengkapan data Perencanaan — dipanggil SETELAH validate(), lihat ttdDokter(). */
    private function perencanaanBelumLengkap(): bool
    {
        if ($this->perencanaanLengkap()) {
            return false;
        }

        $this->dispatch('toast', type: 'error', message: 'Lengkapi No. Surat Keterangan Meninggal & Tanggal Meninggal di Perencanaan terlebih dahulu.');
        return true;
    }

    /* ===============================
     | PERSIST
     =============================== */
    private function persist(bool $final, string $aksi): void
    {
        try {
            DB::transaction(function () use ($final, $aksi) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                if (!empty($data['suratKematianRI']['isFinal'])) {
                    throw new \RuntimeException('Surat sudah ditandatangani dan dikunci.');
                }

                $entry = $this->newForm;
                $entry['isFinal'] = $final;
                $entry['createdAt'] = $data['suratKematianRI']['createdAt'] ?? Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $entry['clauseVersion'] = $data['suratKematianRI']['clauseVersion'] ?? SuratKematianClause::CURRENT;

                $data['suratKematianRI'] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Surat Keterangan Kematian — ' . $aksi . ' (No. ' . ($this->nomorSurat() ?: '-') . ')', 'MR');
            });

            $this->muatData();
            $this->incrementVersion('modal-surat-kematian-ri');
            $this->dispatch('toast', type: 'success', message: $final ? 'Surat kematian ditandatangani & dikunci.' : 'Draft surat kematian tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ri-data', riHdrNo: $this->riHdrNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (inline — pola kerohanian RI)
     =============================== */
    public function cetak(): mixed
    {
        $dataRI = $this->findDataRI($this->riHdrNo);
        $form = $dataRI['suratKematianRI'] ?? null;
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian belum tersimpan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }

        $tindak = $dataRI['perencanaan']['tindakLanjut'] ?? [];

        // Nomor & tanggal ikut Perencanaan (sumber BPJS), bukan salinan di record surat.
        $form['nomorSurat'] = $tindak['noSuratMeninggal'] ?? '';
        $form['tanggalMeninggal'] = $tindak['tglMeninggal'] ?? '';

        $ttdDokterPath = null;
        if (!empty($form['dokterPenerangCode'])) {
            $ttdPath = DB::table('users')->where('myuser_code', $form['dokterPenerangCode'])->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdDokterPath = public_path('storage/' . $ttdPath);
            }
        }

        $data = array_merge($pasien, [
            'dataRI' => $dataRI,
            'form' => $form,
            'identitasRs' => DB::table('rsmst_identitases')->select('int_name', 'int_address', 'int_city')->first(),
            'ttdDokterPath' => $ttdDokterPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.surat-kematian-ri.cetak-surat-kematian-ri-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    /* ===============================
     | DEFAULT
     =============================== */
    private function defaultForm(): array
    {
        return [
            'tempatMeninggal' => 'Ruang Rawat Inap',
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
                            wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
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
                <p class="text-base text-muted dark:text-gray-400">
                    Surat keterangan kematian hanya dapat dibuat bila status pulang pasien di
                    <strong>Perencanaan</strong> adalah <strong>Meninggal</strong>.
                </p>
            @else
                <p class="text-base text-muted dark:text-gray-400">
                    Surat keterangan kematian yang diterbitkan rumah sakit untuk pasien meninggal saat rawat inap.
                    Nomor surat &amp; tanggal meninggal mengikuti <strong>Perencanaan</strong> (nomor yang sama dikirim
                    ke BPJS). Sekali ditandatangani, surat terkunci.
                </p>

                @if (!$this->perencanaanLengkap())
                    <div
                        class="flex items-start gap-2.5 px-3 py-2.5 text-base border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>
                            <strong>No. Surat Keterangan Meninggal</strong> dan/atau <strong>Tanggal Meninggal</strong>
                            belum diisi di <strong>Perencanaan</strong>. Keduanya dipakai surat ini dan dikirim ke BPJS,
                            jadi harus diisi di sana lebih dulu.
                        </span>
                    </div>
                @endif

                @if ($sudahAda)
                    <div class="overflow-x-auto">
                        <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Surat Tersimpan</h4>
                        <table class="min-w-full text-sm border rounded-lg border-hairline dark:border-gray-700">
                            <thead class="bg-surface-soft dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">No. Surat</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Tgl. Meninggal</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Dokter</th>
                                    <th class="px-3 py-2 font-semibold text-left text-body dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-t border-hairline dark:border-gray-700">
                                    <td class="px-3 py-2 text-body dark:text-gray-300">{{ $this->nomorSurat() ?: '-' }}</td>
                                    <td class="px-3 py-2 text-body dark:text-gray-300">{{ $this->tanggalMeninggal() ?: '-' }}</td>
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
    <x-modal name="rm-surat-kematian-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-surat-kematian-ri', [$riHdrNo ?? 'new']) }}">

            <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div>
                    <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Surat Keterangan Kematian</h2>
                    <p class="mt-0.5 text-base text-muted dark:text-gray-400">Rawat Inap</p>
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

            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien — surat legal, identitas harus terlihat saat mengisi --}}
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="sk-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-4 border shadow-sm bg-canvas border-hairline sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- Nomor & tanggal: milik Perencanaan, read-only di sini --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Nomor Surat" />
                        <x-text-input :value="$this->nomorSurat() ?: '—'" disabled class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Meninggal" />
                        <x-text-input :value="$this->tanggalMeninggal() ?: '—'" disabled class="w-full mt-1" />
                    </div>
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Nomor surat &amp; tanggal meninggal diambil dari <strong>Perencanaan</strong> — nomor yang sama
                    dikirim ke BPJS saat update pulang SEP. Ubah di Perencanaan bila keliru.
                </p>

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
                    <x-primary-button wire:click="cetak">Cetak</x-primary-button>
                @endif
            </div>

        </div>
    </x-modal>
</div>
