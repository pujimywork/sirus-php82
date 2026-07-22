<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public bool $isEmrLocked = false;   // kunci EMR-level (kunjungan selesai) — tak bisa dibuka dari sini
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // Radio properties — sync terpisah (pola sama dgn screening UGD)
    public string $kesadaran = '';
    public string $pernafasan = '';
    public string $nyeriDada = '';
    public string $gawatLain = '';
    public string $alatBantu = '';
    public string $batuk = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-screening-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-screening-rj']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultScreeningRJ();
        $current = $this->dataDaftarPoliRJ['screening'] ?? [];
        $this->dataDaftarPoliRJ['screening'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-screening-rj')]
    public function openScreening(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        // Tahan HANYA slice screening — record RJ penuh tidak dipakai blade & di-baca ulang
        // saat save(); menahannya bikin tiap roundtrip .live re-serialize payload besar (lag).
        $screeningData = $data['screening'] ?? null;
        $this->dataDaftarPoliRJ = [
            'screening' => is_array($screeningData) ? $screeningData : $this->getDefaultScreeningRJ(),
        ];

        // Kunci berlapis: (a) EMR-level (kunjungan selesai) → tak bisa dibuka dari sini;
        // (b) sudah TTD petugas → terkunci, TAPI bisa "Buka Kunci" (Admin/Manager).
        $screening = $this->dataDaftarPoliRJ['screening'];
        $this->isEmrLocked = $this->checkEmrRJStatus($rjNo);
        $this->isFormLocked = $this->isEmrLocked || filled($screening['petugasScreening'] ?? '');

        // Sync radio properties dari data
        $this->kesadaran = $screening['kesadaran'] ?? '';
        $this->pernafasan = $screening['pernafasan'] ?? '';
        $this->nyeriDada = $screening['nyeriDada'] ?? '';
        $this->gawatLain = $screening['gawatLain'] ?? '';
        $this->alatBantu = $screening['alatBantu'] ?? '';
        $this->batuk = $screening['batuk'] ?? '';

        $this->incrementVersion('modal-screening-rj');
        $this->dispatch('open-modal', name: 'rm-screening-rj-actions');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-screening-rj-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.screening.kesadaran' => 'required',
            'dataDaftarPoliRJ.screening.pernafasan' => 'required',
            'dataDaftarPoliRJ.screening.nyeriDada' => 'required',
            'dataDaftarPoliRJ.screening.gawatLain' => 'required',
            'dataDaftarPoliRJ.screening.gawatLainKet' => 'required_if:dataDaftarPoliRJ.screening.gawatLain,Ya',
            'dataDaftarPoliRJ.screening.alatBantu' => 'required',
            'dataDaftarPoliRJ.screening.batuk' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.screening.kesadaran' => 'Kesadaran',
            'dataDaftarPoliRJ.screening.pernafasan' => 'Pernafasan',
            'dataDaftarPoliRJ.screening.nyeriDada' => 'Nyeri Dada',
            'dataDaftarPoliRJ.screening.gawatLain' => 'Tanda Gawat Lain',
            'dataDaftarPoliRJ.screening.gawatLainKet' => 'Keterangan Tanda Gawat',
            'dataDaftarPoliRJ.screening.alatBantu' => 'Alat Bantu',
            'dataDaftarPoliRJ.screening.batuk' => 'Batuk',
        ];
    }

    /* ===============================
     | SIMPAN DRAFT — boleh sebagian, TANPA validasi penuh (pola baku modul-dokumen).
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->persistScreening('Simpan draft');
    }

    /* ===============================
     | TTD PETUGAS — pola baku: aksi yang MEMVALIDASI kelengkapan (rules) lalu
     | STEMPEL nama/kode/tgl + SIMPAN KE DB. Tak lagi cuma di memori → tak hilang
     | saat modal ditutup tanpa simpan.
     =============================== */
    public function setPetugasScreening(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menandatangani screening.');
            return;
        }

        // Screening wajib lengkap (rules) sebelum boleh TTD-E — field kosong memerah + toast.
        // validate() melempar bila gagal → stempel & simpan di bawah tak jalan.
        $this->validateWithToast();

        $this->dataDaftarPoliRJ['screening']['petugasScreening'] = auth()->user()->myuser_name;
        $this->dataDaftarPoliRJ['screening']['petugasScreeningCode'] = auth()->user()->myuser_code;
        $this->dataDaftarPoliRJ['screening']['tanggalScreening'] = now()->format('d/m/Y H:i:s');

        $this->persistScreening('TTD Petugas');

        // TTD = mengunci (pola baku modul-dokumen). Buka Kunci hanya lewat bukaKunci().
        $this->isFormLocked = true;
    }

    /* ===============================
     | BUKA KUNCI — cabut TTD petugas → screening editable lagi.
     | Hanya Admin / Manager Umum / Manager Medis. Tak bisa bila EMR-level terkunci.
     =============================== */
    private function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis']);
    }

    public function bukaKunci(): void
    {
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isEmrLocked) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan sudah selesai — screening read-only.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data) || empty($data['screening'])) {
                    throw new \RuntimeException('Data screening tidak ditemukan.');
                }

                // Cabut TTD petugas SAJA; isian screening dipertahankan.
                $data['screening']['petugasScreening'] = '';
                $data['screening']['petugasScreeningCode'] = '';
                $data['screening']['tanggalScreening'] = '';

                $this->updateJsonRJ((int) $this->rjNo, $data);
                $this->dataDaftarPoliRJ = ['screening' => $data['screening']];

                $this->appendAdminLogRJ(
                    (int) $this->rjNo,
                    'Buka kunci Screening RJ (oleh ' . (auth()->user()->myuser_name ?? '-') . ')',
                    'MR',
                );
            });

            $this->isFormLocked = false;   // isEmrLocked sudah dipastikan false di atas
            $this->incrementVersion('modal-screening-rj');
            $this->dispatch('toast', type: 'success', message: 'Kunci screening dibuka, silakan koreksi lalu TTD ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PERSIST (bersama Draft & TTD) — lock row → re-read → patch slice screening
     | + hitung keputusan/flag server-side (kebenaran) → tulis JSON → audit MR.
     =============================== */
    private function persistScreening(string $verb): void
    {
        try {
            DB::transaction(function () use ($verb) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataRJ($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['screening']);

                // 3. Patch hanya key screening + hitung keputusan & flag (server-side truth)
                $screening = $this->dataDaftarPoliRJ['screening'] ?? [];
                $screening = array_replace($screening, $this->hitungKeputusan($screening));
                $data['screening'] = $screening;

                $this->updateJsonRJ((int) $this->rjNo, $data);
                // Simpan hanya slice screening (lihat catatan di openScreening).
                $this->dataDaftarPoliRJ = ['screening' => $screening];

                // 4. Audit log
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Screening RJ (' . $verb . ') — keputusan ' . ($screening['keputusan'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-screening-rj');
            // Refresh tabel Daftar/Pelayanan RJ sengaja dilewati (perf: hindari requery + re-read CLOB).
            $this->dispatch('toast', type: 'success', message: $verb === 'TTD Petugas'
                ? 'Screening ditandatangani & disimpan.'
                : 'Draft screening disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        match ($name) {
            'kesadaran' => ($this->dataDaftarPoliRJ['screening']['kesadaran'] = $value),
            'pernafasan' => ($this->dataDaftarPoliRJ['screening']['pernafasan'] = $value),
            'nyeriDada' => ($this->dataDaftarPoliRJ['screening']['nyeriDada'] = $value),
            'gawatLain' => ($this->dataDaftarPoliRJ['screening']['gawatLain'] = $value),
            'alatBantu' => ($this->dataDaftarPoliRJ['screening']['alatBantu'] = $value),
            'batuk' => ($this->dataDaftarPoliRJ['screening']['batuk'] = $value),
            default => null,
        };

        // Keterangan tanda gawat hanya relevan saat "Ya" — kosongkan jika beralih ke "Tidak".
        if ($name === 'gawatLain' && $value !== 'Ya') {
            $this->dataDaftarPoliRJ['screening']['gawatLainKet'] = '';
        }
    }

    /* ===============================
     | SKOR per kriteria (0/1/2) — acuan rubrik:
     |   2 = tanda kegawatan → IGD
     |   1 = pakai alat bantu → disegerakan + flag jatuh
     |   0 = aman / tidak menambah skor
     | Batuk tidak menambah skor (hanya flag infeksius).
     =============================== */
    private function hitungSkor(array $screening): array
    {
        return [
            'kesadaran' => in_array($screening['kesadaran'] ?? '', ['Mengantuk / Gelisah', 'Tidak Sadar'], true) ? 2 : 0,
            'pernafasan' => in_array($screening['pernafasan'] ?? '', ['Sesak', 'Tidak Bernafas'], true) ? 2 : 0,
            'nyeriDada' => ($screening['nyeriDada'] ?? '') === 'Ada' ? 2 : 0,
            'gawatLain' => ($screening['gawatLain'] ?? '') === 'Ya' ? 2 : 0,
            'alatBantu' => ($screening['alatBantu'] ?? '') === 'Ya' ? 1 : 0,
            'batuk' => 0,
        ];
    }

    /* ===============================
     | KEPUTUSAN SCREENING — diturunkan dari SKOR MAKS (severity tertinggi menang):
     |   maks 2 → IGD        (ada tanda kegawatan, rujuk IGD)
     |   maks 1 → Disegerakan (pakai alat bantu, dahulukan + flag risiko jatuh)
     |   maks 0 → Aman       (poli reguler)
     | Batuk >2mgg tidak mengubah keputusan, hanya menyalakan flag infeksius.
     =============================== */
    private function hitungKeputusan(array $screening): array
    {
        $skor = $this->hitungSkor($screening);
        $skorMaks = max($skor);

        $keputusan = match (true) {
            $skorMaks >= 2 => 'IGD',
            $skorMaks === 1 => 'Disegerakan',
            default => 'Aman',
        };

        return [
            'skor' => $skor,
            'skorMaks' => $skorMaks,
            'keputusan' => $keputusan,
            'flagJatuh' => ($screening['alatBantu'] ?? '') === 'Ya',
            'flagInfeksius' => ($screening['batuk'] ?? '') === 'Lebih dari 2 Minggu',
        ];
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultScreeningRJ(): array
    {
        return [
            'kesadaran' => '',
            'kesadaranOptions' => [['v' => 'Sadar'], ['v' => 'Mengantuk / Gelisah'], ['v' => 'Tidak Sadar']],
            'pernafasan' => '',
            'pernafasanOptions' => [['v' => 'Normal'], ['v' => 'Sesak'], ['v' => 'Tidak Bernafas']],
            'nyeriDada' => '',
            'nyeriDadaOptions' => [['v' => 'Tidak'], ['v' => 'Ada']],
            'gawatLain' => '',
            'gawatLainKet' => '',
            'gawatLainOptions' => [['v' => 'Tidak'], ['v' => 'Ya']],
            'alatBantu' => '',
            'alatBantuOptions' => [['v' => 'Tidak'], ['v' => 'Ya']],
            'batuk' => '',
            'batukOptions' => [['v' => 'Tidak'], ['v' => 'Lebih dari 2 Minggu']],
            'skor' => [],
            'skorMaks' => 0,
            'keputusan' => '',
            'flagJatuh' => false,
            'flagInfeksius' => false,
            'tanggalScreening' => '',
            'petugasScreening' => '',
            'petugasScreeningCode' => '',
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->isEmrLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->kesadaran = '';
        $this->pernafasan = '';
        $this->nyeriDada = '';
        $this->gawatLain = '';
        $this->alatBantu = '';
        $this->batuk = '';
    }
};
?>

<div>
    <x-modal name="rm-screening-rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-screening-rj', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    {{-- Identitas pasien menggantikan judul --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                            wire:key="display-pasien-rj-screening-{{ $rjNo }}" />
                        @if ($isFormLocked)
                            <div class="mt-2">
                                <x-badge variant="danger">Read Only</x-badge>
                            </div>
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                @if (isset($dataDaftarPoliRJ['screening']))

                    {{-- Skor & keputusan live dari radio sync props (cermin hitungSkor/hitungKeputusan) --}}
                    @php
                        $skorRj = [
                            'kesadaran' => in_array($kesadaran, ['Mengantuk / Gelisah', 'Tidak Sadar'], true) ? 2 : 0,
                            'pernafasan' => in_array($pernafasan, ['Sesak', 'Tidak Bernafas'], true) ? 2 : 0,
                            'nyeriDada' => $nyeriDada === 'Ada' ? 2 : 0,
                            'gawatLain' => $gawatLain === 'Ya' ? 2 : 0,
                            'alatBantu' => $alatBantu === 'Ya' ? 1 : 0,
                        ];
                        $skorMaksRj = $skorRj ? max($skorRj) : 0;
                        $flagJatuhRj = $alatBantu === 'Ya';
                        $flagInfeksiusRj = $batuk === 'Lebih dari 2 Minggu';
                        $adaIsian = $kesadaran || $pernafasan || $nyeriDada || $gawatLain || $alatBantu || $batuk;
                        $keputusanRj = !$adaIsian
                            ? null
                            : ($skorMaksRj >= 2
                                ? 'IGD'
                                : ($skorMaksRj === 1
                                    ? 'Disegerakan'
                                    : 'Aman'));
                    @endphp

                    <div class="grid grid-cols-3 gap-4">

                        {{-- KIRI 2/3: Kriteria triase --}}
                        <div class="col-span-2">
                            <x-border-form :title="__('Kriteria Skrining')" :align="__('start')" :bgcolor="__('bg-canvas')" class="h-full">
                                <p class="mt-1 text-sm text-muted dark:text-gray-400">Skrining awal pasien poli —
                                    deteksi kegawatan, kebutuhan pendampingan &amp; infeksius.</p>
                                <div class="grid grid-cols-2 gap-x-6 gap-y-4 mt-3">

                                    {{-- Kesadaran --}}
                                    <div>
                                        <x-input-label value="Kesadaran" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['kesadaranOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="kesadaran"
                                                    wire:model.live="kesadaran" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.kesadaran')" class="mt-1" />
                                    </div>

                                    {{-- Pernafasan --}}
                                    <div>
                                        <x-input-label value="Pernafasan" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['pernafasanOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="pernafasan"
                                                    wire:model.live="pernafasan" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.pernafasan')" class="mt-1" />
                                    </div>

                                    {{-- Nyeri Dada --}}
                                    <div>
                                        <x-input-label value="Nyeri Dada" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['nyeriDadaOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="nyeriDada"
                                                    wire:model.live="nyeriDada" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.nyeriDada')" class="mt-1" />
                                    </div>

                                    {{-- Tanda Gawat Lain --}}
                                    <div>
                                        <x-input-label value="Tanda Gawat Lain" :required="true" />
                                        <p class="text-xs text-muted -mt-0.5 mb-1">mis. perdarahan hebat, kejang, nyeri
                                            hebat,
                                            lemas mendadak</p>
                                        <div class="flex flex-wrap items-center gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['gawatLainOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="gawatLain"
                                                    wire:model.live="gawatLain" :disabled="$isFormLocked" />
                                            @endforeach

                                            @if ($gawatLain === 'Ya')
                                                <x-text-input type="text"
                                                    wire:model.live="dataDaftarPoliRJ.screening.gawatLainKet"
                                                    placeholder="Sebutkan tanda gawat yang ditemukan..."
                                                    :disabled="$isFormLocked" :error="$errors->has('dataDaftarPoliRJ.screening.gawatLainKet')"
                                                    class="flex-1 min-w-[12rem]" />
                                            @endif
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.gawatLain')" class="mt-1" />
                                        @if ($gawatLain === 'Ya')
                                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.gawatLainKet')" class="mt-1" />
                                        @endif
                                    </div>

                                    {{-- Alat Bantu --}}
                                    <div>
                                        <x-input-label value="Alat Bantu / Pendampingan" :required="true" />
                                        <p class="text-xs text-muted -mt-0.5 mb-1">mis. kursi roda, brankar, tongkat,
                                            dipapah
                                            — memicu flag risiko jatuh</p>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['alatBantuOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="alatBantu"
                                                    wire:model.live="alatBantu" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.alatBantu')" class="mt-1" />
                                    </div>

                                    {{-- Batuk --}}
                                    <div>
                                        <x-input-label value="Batuk" :required="true" />
                                        <p class="text-xs text-muted -mt-0.5 mb-1">batuk &gt; 2 minggu → flag infeksius
                                            (tidak
                                            mengubah keputusan)</p>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarPoliRJ['screening']['batukOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['v']" :value="$opt['v']" name="batuk"
                                                    wire:model.live="batuk" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.screening.batuk')" class="mt-1" />
                                    </div>

                                </div>
                            </x-border-form>
                        </div>

                        {{-- KANAN 1/3: Keputusan + Flag --}}
                        <div class="col-span-1">
                            <x-border-form :title="__('Hasil Keputusan')" :align="__('start')" :bgcolor="__('bg-canvas')" class="h-full">
                                <div class="space-y-4 mt-2">

                                    {{-- 3 chip keputusan --}}
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach ([
        'Aman' => ['bg' => 'bg-green-500', 'ring' => 'ring-green-400', 'desc' => 'Poli reguler'],
        'Disegerakan' => ['bg' => 'bg-amber-500', 'ring' => 'ring-amber-400', 'desc' => 'Didahulukan'],
        'IGD' => ['bg' => 'bg-red-500', 'ring' => 'ring-red-400', 'desc' => 'Rujuk IGD'],
    ] as $k => $info)
                                            <div
                                                class="flex flex-col items-center justify-center p-3 rounded-lg text-white text-sm font-bold transition-all
                                            {{ $info['bg'] }}
                                            {{ $keputusanRj === $k ? 'opacity-100 ring-2 ring-offset-2 ' . $info['ring'] . ' scale-105 shadow-md' : 'opacity-30' }}">
                                                <span class="text-base">{{ $k }}</span>
                                                <span
                                                    class="font-normal text-[10px] opacity-90">{{ $info['desc'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Narasi keputusan --}}
                                    @if ($keputusanRj)
                                        <div
                                            class="px-3 py-2 rounded-lg text-base font-medium border
                                        {{ match ($keputusanRj) {
                                            'IGD' => 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
                                            'Disegerakan'
                                                => 'bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300',
                                            default
                                                => 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300',
                                        } }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <span>Keputusan: <strong>{{ $keputusanRj }}</strong></span>
                                                <span
                                                    class="shrink-0 px-2 py-0.5 text-sm font-bold rounded-md bg-white/60 dark:bg-black/20"
                                                    title="Skor maks dari seluruh kriteria">Skor maks:
                                                    {{ $skorMaksRj }}</span>
                                            </div>
                                            <span class="text-sm font-normal">
                                                {{ match ($keputusanRj) {
                                                    'IGD' => 'Terdapat tanda kegawatan, segera rujuk ke IGD.',
                                                    'Disegerakan' => 'Pasien perlu pendampingan, layanan didahulukan.',
                                                    default => 'Tidak ada tanda kegawatan, lanjut layanan poli reguler.',
                                                } }}
                                            </span>
                                        </div>
                                    @else
                                        <div
                                            class="px-3 py-2 text-base border rounded-lg bg-surface-soft border-hairline text-muted">
                                            Lengkapi kriteria di sebelah kiri untuk melihat keputusan.
                                        </div>
                                    @endif

                                    {{-- Flag --}}
                                    <div>
                                        <x-input-label value="Penanda (Flag)" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @if ($flagJatuhRj)
                                                <span
                                                    class="inline-flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-900/30 dark:text-amber-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                                    </svg>
                                                    Risiko Jatuh
                                                </span>
                                            @endif
                                            @if ($flagInfeksiusRj)
                                                <span
                                                    class="inline-flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-900/30 dark:text-rose-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 9v3m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Infeksius (batuk &gt; 2 minggu)
                                                </span>
                                            @endif
                                            @if (!$flagJatuhRj && !$flagInfeksiusRj)
                                                <span class="text-sm text-muted">Tidak ada flag.</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- TTD Petugas --}}
                                    <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                        :ttd="$dataDaftarPoliRJ['screening']['petugasScreening'] ?? ''"
                                        :date="$dataDaftarPoliRJ['screening']['tanggalScreening'] ?? ''"
                                        :code="$dataDaftarPoliRJ['screening']['petugasScreeningCode'] ?? ''"
                                        :locked="$isFormLocked"
                                        :canSign="auth()->user()?->hasAnyRole(['Perawat', 'Dokter', 'Admin'])"
                                        sign="setPetugasScreening" signLabel="TTD-E Petugas" />

                                </div>
                            </x-border-form>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <p class="text-base font-medium">Data RJ belum dimuat</p>
                    </div>
                @endif

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <div class="flex gap-3 ml-auto">
                        <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                        {{-- Terkunci oleh TTD (bukan EMR-level) → Admin/Manager boleh Buka Kunci --}}
                        @if ($isFormLocked && !$isEmrLocked)
                            @hasanyrole('Admin|Manager Umum|Manager Medis')
                                <x-confirm-button action="bukaKunci" title="Buka Kunci Screening"
                                    message="TTD petugas akan dicabut & screening kembali bisa diedit. Lanjutkan?"
                                    confirmText="Ya, Buka Kunci" class="gap-1.5">
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                    Buka Kunci
                                </x-confirm-button>
                            @endhasanyrole
                        @endif

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="saveDraft()" wire:loading.attr="disabled"
                                wire:target="saveDraft">
                                <span wire:loading.remove wire:target="saveDraft">
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan Draft
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
