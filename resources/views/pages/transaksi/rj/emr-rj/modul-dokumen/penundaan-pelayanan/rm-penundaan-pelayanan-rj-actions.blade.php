<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/penundaan-pelayanan/rm-penundaan-pelayanan-rj-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penundaan-pelayanan-rj'];

    // ── Form entri baru ──
    public array $newForm = [
        'tglPemberitahuan' => '',
        'jenis' => '',
        'alasan' => '',
        'jadwalUlang' => '',
        'alternatif' => '',
        'respon' => '',
        'namaPenanda' => '',
        'hubunganPasien' => 'pasien',
        'pemberiInfo' => '',
        'pemberiInfoCode' => '',
        'pemberiInfoDate' => '',
    ];

    public string $signature = ''; // TTD pasien/keluarga untuk entri baru

    public array $penundaanList = [];

    public array $responOptions = ['Menerima penundaan', 'Memilih alternatif', 'Menolak'];

    public array $hubunganPasienOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-penundaan-pelayanan-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->penundaanList = $data['penundaanPelayananRJ'] ?? [];
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signature = '';
        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        if (!isset($this->dataDaftarPoliRJ['penundaanPelayananRJ']) || !is_array($this->dataDaftarPoliRJ['penundaanPelayananRJ'])) {
            $this->dataDaftarPoliRJ['penundaanPelayananRJ'] = [];
        }
        $this->penundaanList = $this->dataDaftarPoliRJ['penundaanPelayananRJ'];
        $this->newForm['namaPenanda'] = $this->dataDaftarPoliRJ['regName'] ?? '';
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-penundaan-pelayanan-rj');

        $this->dispatch('open-modal', name: "rm-penundaan-pelayanan-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-penundaan-pelayanan-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.jenis' => 'required|string|max:500',
            'newForm.alasan' => 'required|string|max:1000',
            'newForm.jadwalUlang' => 'nullable|date_format:d/m/Y H:i:s',
            'newForm.alternatif' => 'nullable|string|max:1000',
            'newForm.respon' => 'required|string',
            'newForm.namaPenanda' => 'required|string|max:200',
            'newForm.hubunganPasien' => 'required|string|max:50',
            'signature' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 25/06/2026 11:11:16).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'Tanggal/jam pemberitahuan',
            'newForm.jenis' => 'Jenis pelayanan yang ditunda',
            'newForm.alasan' => 'Alasan penundaan/kelambatan',
            'newForm.jadwalUlang' => 'Jadwal ulang',
            'newForm.alternatif' => 'Alternatif yang ditawarkan',
            'newForm.respon' => 'Respon pasien/keluarga',
            'newForm.namaPenanda' => 'Nama pasien/keluarga',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'signature' => 'Tanda tangan pasien/keluarga',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTglPemberitahuanSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['tglPemberitahuan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJadwalUlangSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['jadwalUlang'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | SIGNATURE (pasien/keluarga)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    /* ===============================
     | SET PEMBERI INFORMASI (DPJP/PPA) — isi ke entri baru
     =============================== */
    public function setPemberiInfo(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newForm['pemberiInfo'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan pemberi informasi sudah ada.');
            return;
        }

        $this->newForm['pemberiInfo'] = auth()->user()->myuser_name ?? '';
        $this->newForm['pemberiInfoCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['pemberiInfoDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan pemberi informasi berhasil ditambahkan.');
    }

    /* ===============================
     | SIMPAN ENTRI BARU
     =============================== */
    #[On('save-rm-penundaan-pelayanan-rj')]
    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan pasien/keluarga belum diisi.');
            return;
        }

        $this->validate();

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $entry = [
            'tglPemberitahuan' => $this->newForm['tglPemberitahuan'],
            'jenis' => $this->newForm['jenis'] ?? '',
            'alasan' => $this->newForm['alasan'],
            'jadwalUlang' => $this->newForm['jadwalUlang'] ?? '',
            'alternatif' => $this->newForm['alternatif'] ?? '',
            'respon' => $this->newForm['respon'],
            'namaPenanda' => $this->newForm['namaPenanda'] ?? '',
            'hubunganPasien' => $this->newForm['hubunganPasien'] ?? 'pasien',
            'signature' => $this->signature,
            'signatureDate' => $now,
            'pemberiInfo' => $this->newForm['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $this->newForm['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $this->newForm['pemberiInfoDate'] ?? '',
        ];

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['penundaanPelayananRJ']) || !is_array($data['penundaanPelayananRJ'])) {
                    $data['penundaanPelayananRJ'] = [];
                }

                $data['penundaanPelayananRJ'][] = $entry;

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->penundaanList = $data['penundaanPelayananRJ'];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Tambah Pemberitahuan Penundaan/Kelambatan — TTD ' . ($entry['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-penundaan-pelayanan-rj');
            $this->dispatch('toast', type: 'success', message: 'Formulir penundaan pelayanan berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);

            $this->resetNewForm();
            $this->newForm['namaPenanda'] = $this->dataDaftarPoliRJ['regName'] ?? '';
            $this->signature = '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $signatureDate);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-penundaan-pelayanan-rj.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data) || !isset($data['penundaanPelayananRJ'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['penundaanPelayananRJ'] = collect($data['penundaanPelayananRJ'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->penundaanList = $data['penundaanPelayananRJ'];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Pemberitahuan Penundaan/Kelambatan — TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-penundaan-pelayanan-rj');
            $this->dispatch('toast', type: 'success', message: 'Formulir penundaan berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tglPemberitahuan' => '',
            'jenis' => '',
            'alasan' => '',
            'jadwalUlang' => '',
            'alternatif' => '',
            'respon' => '',
            'namaPenanda' => '',
            'hubunganPasien' => 'pasien',
            'pemberiInfo' => '',
            'pemberiInfoCode' => '',
            'pemberiInfoDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->penundaanList = [];
        $this->resetNewForm();
        $this->signature = '';
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $ppCount = count($penundaanList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Pemberitahuan Penundaan / Kelambatan Pelayanan
                    </h3>
                    @if ($ppCount > 0)
                        <x-badge variant="success">{{ $ppCount }} catatan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>

                <p class="text-base text-muted dark:text-gray-400">
                    Formulir pemberitahuan kepada pasien/keluarga atas penundaan atau kelambatan pelayanan beserta alasan
                    dan alternatif yang ditawarkan. Dapat lebih dari satu catatan.
                </p>

                @if ($ppCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($penundaanList, 0, 3) as $pp)
                            <li>
                                <span class="font-medium">{{ \Illuminate\Support\Str::limit($pp['jenis'] ?? '-', 60) ?: '-' }}</span>
                                @if (!empty($pp['signatureDate']))
                                    <span class="text-sm text-muted-soft">— {{ $pp['signatureDate'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($ppCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $ppCount - 3 }} lainnya…</li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
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
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-penundaan-pelayanan-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-penundaan-pelayanan-rj', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-500/10">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">
                                    Pemberitahuan Penundaan / Kelambatan Pelayanan
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    Formulir diisi & dijelaskan kepada pasien/keluarga — tampilan dapat diputar ke arah
                                    pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="success">Rawat Jalan</x-badge>
                            @if (count($penundaanList) > 0)
                                <x-badge variant="info">{{ count($penundaanList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="pp-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ TANGGAL/JAM PEMBERITAHUAN ══ --}}
                        <section>
                            <x-input-label value="Tanggal / Jam Pemberitahuan *" class="mb-1" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model.live="newForm.tglPemberitahuan" placeholder="dd/mm/yyyy HH:mm:ss"
                                    :error="$errors->has('newForm.tglPemberitahuan')" :disabled="$isFormLocked"
                                    class="w-full max-w-xs" />
                                @if (!$isFormLocked)
                                    <x-now-button wire:click="setTglPemberitahuanSekarang" />
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('newForm.tglPemberitahuan')" class="mt-1" />
                        </section>

                        {{-- ══ JENIS PELAYANAN YANG DITUNDA / TERLAMBAT ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Pelayanan yang Ditunda / Terlambat *
                            </h3>
                            <x-textarea wire:model.live="newForm.jenis" :error="$errors->has('newForm.jenis')" rows="2"
                                placeholder="cth: Tindakan, Pengobatan, Pemeriksaan Penunjang (Lab), Radiologi, Operasi, Rawat Inap (daftar tunggu)..."
                                :disabled="$isFormLocked" class="w-full" />
                            <x-input-error :messages="$errors->get('newForm.jenis')" class="mt-1" />
                        </section>

                        {{-- ══ ALASAN & ALTERNATIF ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Alasan Penundaan / Kelambatan *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alasan" :error="$errors->has('newForm.alasan')" rows="3"
                                    placeholder="Jelaskan alasan penundaan / kelambatan pelayanan..."
                                    :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.alasan')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Jadwal Ulang" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jadwalUlang" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.jadwalUlang')" :disabled="$isFormLocked"
                                        class="w-full max-w-xs" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setJadwalUlangSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.jadwalUlang')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Alternatif yang Ditawarkan (sesuai kebutuhan klinis)" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alternatif" :error="$errors->has('newForm.alternatif')" rows="3"
                                    placeholder="Alternatif pelayanan/rujukan yang ditawarkan..."
                                    :disabled="$isFormLocked" class="w-full" />
                            </div>
                        </section>

                        {{-- ══ RESPON PASIEN/KELUARGA ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Respon Pasien / Keluarga *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($responOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="respon"
                                        wire:model.live="newForm.respon" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.respon')" class="mt-1" />
                        </section>

                        {{-- ══ CATATAN KEBIJAKAN ══ --}}
                        <div
                            class="px-4 py-3 text-sm border rounded-xl bg-surface-soft border-hairline text-muted dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                            Tidak berlaku untuk keterlambatan staf medis di RJ / IGD penuh. Onkologi &amp; transplantasi
                            mengikuti norma nasional. Dicatat di rekam medis (Lihat KE 2).
                        </div>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pasien / Keluarga --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pasien / Keluarga
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$isFormLocked" wireMethod="clearSignature" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Keluarga *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaPenanda" :error="$errors->has('newForm.namaPenanda')"
                                            placeholder="Nama penanda tangan..." :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.namaPenanda')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.hubunganPasien" :error="$errors->has('newForm.hubunganPasien')"
                                            :disabled="$isFormLocked" class="w-full">
                                            @foreach ($hubunganPasienOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.hubunganPasien')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Pemberi Informasi (DPJP/PPA) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pemberi Informasi (DPJP / PPA)
                                    </div>
                                    @if (empty($newForm['pemberiInfo']))
                                        @if (!$isFormLocked)
                                            <div
                                                class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPemberiInfo"
                                                    wire:loading.attr="disabled" wire:target="setPemberiInfo"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPemberiInfo"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD sebagai Pemberi Informasi
                                                    </span>
                                                    <span wire:loading wire:target="setPemberiInfo">
                                                        <x-loading class="w-4 h-4" /> Menyimpan...
                                                    </span>
                                                </x-primary-button>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="font-semibold text-center text-ink dark:text-gray-200">
                                                {{ $newForm['pemberiInfo'] }}
                                            </div>
                                            @if (!empty($newForm['pemberiInfoCode']))
                                                <div class="text-sm text-muted mt-0.5">
                                                    Kode: {{ $newForm['pemberiInfoCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-sm text-muted">
                                                {{ $newForm['pemberiInfoDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($penundaanList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Pemberitahuan Tersimpan
                                </h3>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tgl Pemberitahuan</th>
                                            <th class="px-4 py-2 border-b">Jenis</th>
                                            <th class="px-4 py-2 border-b">Respon</th>
                                            <th class="px-4 py-2 border-b">TTD Pasien</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($penundaanList as $pp)
                                            <tr
                                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $pp['tglPemberitahuan'] ?? '-' }}
                                                </td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                                    {{ $pp['jenis'] ? Str::limit($pp['jenis'], 45) : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $pp['respon'] ?? '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $pp['signatureDate'] ?? '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $pp['signatureDate'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="cetak('{{ $pp['signatureDate'] }}')"
                                                        class="text-sm py-1 px-2">
                                                        <span wire:loading.remove
                                                            wire:target="cetak('{{ $pp['signatureDate'] }}')"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="cetak('{{ $pp['signatureDate'] }}')"
                                                            class="flex items-center gap-1"><x-loading />
                                                            Mencetak...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button"
                                                            wire:click.prevent="hapus('{{ $pp['signatureDate'] }}')"
                                                            wire:confirm="Yakin hapus pemberitahuan ini?"
                                                            wire:loading.attr="disabled"
                                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                                            title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>

                    @if ($rjNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled"
                            wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Pemberitahuan</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.r-j.penundaan-pelayanan.cetak-penundaan-pelayanan-rj
        wire:key="cetak-penundaan-pelayanan-rj-{{ $rjNo ?? 'init' }}" />
</div>
