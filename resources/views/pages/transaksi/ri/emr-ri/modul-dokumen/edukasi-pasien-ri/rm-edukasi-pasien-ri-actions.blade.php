<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/edukasi-pasien-ri/rm-edukasi-pasien-ri-actions.blade.php
// Edukasi Pasien RI — multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json key `edukasiPasien`. Kunci entri stabil = createdAt.
// TTD = stempel nama petugas (user login) tanpa TTD gambar; FINALIZE = tombol "TTD Petugas & Kunci".

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'edukasiPasien';

    public array $formEntryEdukasi = [
        'tglEdukasi' => '',
        'petugasEdukasi' => '',
        'petugasEdukasiCode' => '',
        'sasaranEdukasi' => '',
        'hubunganSasaranEdukasidgnPasien' => '',
        'sasaranEdukasiSignature' => '',
        'edukasi' => [
            'kategoriEdukasi' => [],
            'materiTopikEdukasi' => '',
            'keteranganEdukasi' => '',
            'statusEdukasi' => '',
            'reEdukasi' => ['perlu' => false, 'tglReEdukasi' => '', 'petugasReEdukasi' => ''],
        ],
    ];

    public array $edukasiOptions = ['Pengobatan', 'Rencana Perawatan', 'Diagnosis Medis', 'Pencegahan Infeksi', 'Diet dan Nutrisi', 'Perawatan Luka', 'Aktivitas Fisik', 'Perawatan di Rumah', 'Manajemen Nyeri', 'Dukungan Emosional dan Spiritual', 'Lain-lain'];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-edukasi-ri'];

    protected function rules(): array
    {
        return [];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-edukasi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->dataDaftarRi[$this->jsonKey] ??= [];
                $this->regNo = $data['regNo'] ?? null;
                $this->entriList = $this->normalizeEntriList($this->dataDaftarRi[$this->jsonKey]);
                $this->formEntryEdukasi['sasaranEdukasi'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi[$this->jsonKey]) || !is_array($this->dataDaftarRi[$this->jsonKey])) {
            $this->dataDaftarRi[$this->jsonKey] = [];
        }
        $this->entriList = $this->normalizeEntriList($this->dataDaftarRi[$this->jsonKey]);
        $this->formEntryEdukasi['sasaranEdukasi'] = $data['regName'] ?? '';
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;

        $this->incrementVersion('modal-edukasi-ri');
        $this->dispatch('open-modal', name: "rm-edukasi-pasien-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-edukasi-pasien-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | SET TANGGAL EDUKASI SEKARANG
     =============================== */
    public function setTglEdukasi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->formEntryEdukasi['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD petugas (nama) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['petugasEdukasi']);
    }

    // Entri legacy (disimpan sebelum sebagian field ada) bisa kekurangan key → samakan ke
    // bentuk default agar blade tak "Undefined array key" (mis. sasaranEdukasi). Nilai entri
    // yang sudah ada tetap menang; hanya key yang hilang yang diisi default.
    private function normalizeEntriList(array $list): array
    {
        $default = $this->defaultFormEntry();

        return array_map(
            fn ($e) => is_array($e) ? array_replace_recursive($default, $e) : $e,
            $list,
        );
    }

    // Struktur kosong 1 entri edukasi (nested).
    private function defaultFormEntry(): array
    {
        return [
            'tglEdukasi' => '',
            'petugasEdukasi' => '',
            'petugasEdukasiCode' => '',
            'sasaranEdukasi' => '',
            'hubunganSasaranEdukasidgnPasien' => '',
            'sasaranEdukasiSignature' => '',
            'edukasi' => [
                'kategoriEdukasi' => [],
                'materiTopikEdukasi' => '',
                'keteranganEdukasi' => '',
                'statusEdukasi' => '',
                'reEdukasi' => ['perlu' => false, 'tglReEdukasi' => '', 'petugasReEdukasi' => ''],
            ],
        ];
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = $this->formEntryEdukasi;
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;
        return $entry;
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                $fresh[$this->jsonKey] = [];
            }

            $list = $fresh[$this->jsonKey];
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->entriList = $this->normalizeEntriList($fresh[$this->jsonKey]);

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Edukasi Pasien — ' . ($entry['tglEdukasi'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (blank($this->formEntryEdukasi['edukasi']['materiTopikEdukasi'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Materi / Topik Edukasi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-edukasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS & KUNCI = FINALIZE
     | Stempel nama petugas (user login) → validasi lengkap → kunci entri.
     =============================== */
    public function ttdPetugas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->validateWithToast(
            [
                'formEntryEdukasi.tglEdukasi' => 'required|date_format:d/m/Y H:i:s',
                'formEntryEdukasi.sasaranEdukasi' => 'required|string|max:100',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien' => 'required|string|max:100',
                'formEntryEdukasi.edukasi.kategoriEdukasi' => 'required|array|min:1',
                'formEntryEdukasi.edukasi.materiTopikEdukasi' => 'required|string|max:150',
                'formEntryEdukasi.edukasi.keteranganEdukasi' => 'required|string|max:255',
                'formEntryEdukasi.edukasi.statusEdukasi' => 'required|string|max:100',
            ],
            [
                'formEntryEdukasi.tglEdukasi.required' => 'Tanggal edukasi wajib diisi.',
                'formEntryEdukasi.sasaranEdukasi.required' => 'Sasaran edukasi wajib diisi.',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien.required' => 'Hubungan dengan pasien wajib diisi.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.required' => 'Kategori edukasi wajib dipilih.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.min' => 'Pilih minimal satu kategori edukasi.',
                'formEntryEdukasi.edukasi.materiTopikEdukasi.required' => 'Materi / topik edukasi wajib diisi.',
                'formEntryEdukasi.edukasi.keteranganEdukasi.required' => 'Keterangan edukasi wajib diisi.',
                'formEntryEdukasi.edukasi.statusEdukasi.required' => 'Status edukasi wajib diisi.',
            ],
        );

        // Stempel TTD petugas = user login.
        $this->formEntryEdukasi['petugasEdukasi'] = auth()->user()->myuser_name ?? '';
        $this->formEntryEdukasi['petugasEdukasiCode'] = auth()->user()->myuser_code ?? '';

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-edukasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Edukasi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->formEntryEdukasi = array_replace_recursive($this->defaultFormEntry(), array_intersect_key($entry, $this->defaultFormEntry()));
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-ri');
    }

    private function resetNewForm(): void
    {
        $this->formEntryEdukasi = $this->defaultFormEntry();
        $this->formEntryEdukasi['sasaranEdukasi'] = $this->dataDaftarRi['regName'] ?? '';
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->entriList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    /* ===============================
     | HAPUS entri (final atau draft)
     =============================== */
    public function hapus(string $createdAt): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $deletedRow = collect($fresh[$this->jsonKey] ?? [])->firstWhere('createdAt', $createdAt) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($e) => ($e['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $this->normalizeEntriList($fresh[$this->jsonKey]);

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Edukasi Pasien — ' . ($deletedRow['tglEdukasi'] ?? $createdAt), 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-edukasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-entri, cari by createdAt)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList ?? [])->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD petugas edukasi (dari petugasEdukasiCode -> users.myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['petugasEdukasiCode'] ?? null;
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-pasien.cetak-edukasi-pasien-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Edukasi Pasien.');
            return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-pasien-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $eduCount = count($entriList ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Edukasi Pasien</h3>
                    @if ($eduCount > 0)
                        <x-badge variant="success">{{ $eduCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Pemberian informasi &amp; edukasi kepada pasien/keluarga selama perawatan.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Edukasi Pasien
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @if ($eduCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tgl Edukasi</th>
                            <th class="px-3 py-2 border-b">Materi</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['tglEdukasi'] ?: ($e['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['edukasi']['materiTopikEdukasi'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($e['petugasEdukasi'])){{ $e['petugasEdukasi'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($e))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-edukasi-pasien-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-edukasi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700 shrink-0">
                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Edukasi Pasien</h2>
                <div class="flex items-center gap-2">
                    @if (count($entriList) > 0)
                        <x-badge variant="info">{{ count($entriList) }} tersimpan</x-badge>
                    @endif
                    @if ($isFormLocked)
                        <x-badge variant="danger">Read Only</x-badge>
                    @endif
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="edu-pasien-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI EDUKASI ── --}}
                    <fieldset @disabled($formRO) class="space-y-4">
                        <x-border-form title="Entry Edukasi Pasien" align="start" bgcolor="bg-surface-soft">
                            <div class="mt-3 space-y-3">
                                <div class="flex items-end gap-3">
                                    <div class="flex-1">
                                        <x-input-label value="Tanggal Edukasi *" />
                                        <x-text-input wire:model="formEntryEdukasi.tglEdukasi" class="w-full mt-1 font-mono" readonly
                                            :error="$errors->has('formEntryEdukasi.tglEdukasi')" />
                                    </div>
                                    @if (!$formRO)
                                        <x-now-button wire:click="setTglEdukasi" />
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Sasaran Edukasi *" />
                                        <x-text-input wire:model="formEntryEdukasi.sasaranEdukasi" class="w-full mt-1"
                                            placeholder="Nama yang menerima edukasi..." :error="$errors->has('formEntryEdukasi.sasaranEdukasi')" />
                                        <x-input-error :messages="$errors->get('formEntryEdukasi.sasaranEdukasi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Hubungan dengan Pasien *" />
                                        <x-select-input wire:model="formEntryEdukasi.hubunganSasaranEdukasidgnPasien"
                                            class="w-full mt-1" :error="$errors->has('formEntryEdukasi.hubunganSasaranEdukasidgnPasien')">
                                            <option value="">— Pilih —</option>
                                            @foreach (['Pasien', 'Suami/Istri', 'Orang Tua', 'Anak', 'Saudara', 'Lainnya'] as $hub)
                                                <option value="{{ $hub }}">{{ $hub }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Kategori Edukasi * (pilih satu atau lebih)" />
                                    <div class="grid grid-cols-1 gap-2 mt-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                        @foreach ($edukasiOptions as $opt)
                                            <x-toggle wire:model.live="formEntryEdukasi.edukasi.kategoriEdukasi" :label="$opt"
                                                :disabled="$formRO" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.kategoriEdukasi')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Materi / Topik Edukasi *" />
                                    <x-text-input wire:model="formEntryEdukasi.edukasi.materiTopikEdukasi" class="w-full mt-1"
                                        placeholder="Mis. Cara minum obat antihipertensi, Diet rendah garam..."
                                        :error="$errors->has('formEntryEdukasi.edukasi.materiTopikEdukasi')" :disabled="$formRO" />
                                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.materiTopikEdukasi')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Keterangan Edukasi *" />
                                    <x-textarea wire:model="formEntryEdukasi.edukasi.keteranganEdukasi" class="w-full mt-1"
                                        rows="3" placeholder="Penjelasan edukasi yang diberikan..." :error="$errors->has('formEntryEdukasi.edukasi.keteranganEdukasi')" />
                                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.keteranganEdukasi')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Status Edukasi *" />
                                    <div class="mt-2 flex gap-4">
                                        @foreach (['Mengerti', 'Tidak Mengerti', 'Perlu Pengulangan'] as $st)
                                            <x-radio-button :label="$st" :value="$st" name="statusEdukasi"
                                                wire:model.live="formEntryEdukasi.edukasi.statusEdukasi" :disabled="$formRO" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.statusEdukasi')" class="mt-1" />
                                </div>

                                <div>
                                    <x-toggle wire:model.live="formEntryEdukasi.edukasi.reEdukasi.perlu" label="Perlu Re-Edukasi"
                                        :disabled="$formRO" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$formEntryEdukasi['petugasEdukasi']" :code="$formEntryEdukasi['petugasEdukasiCode'] ?? ''"
                            :date="$formEntryEdukasi['tglEdukasi'] ?? ''" :locked="$formRO" sign="ttdPetugas" :allowClear="false"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas Edukasi" dateLabel="Tanggal Edukasi"
                            signLabel="TTD Petugas &amp; Kunci" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci edukasi ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── RIWAYAT EDUKASI (expandable) ── --}}
                    <x-border-form title="Riwayat Edukasi Pasien" align="start" bgcolor="bg-surface-soft">
                        @if (count($entriList ?? []))
                            <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tgl Edukasi</th>
                                            <th class="px-4 py-3 border-b">Sasaran</th>
                                            <th class="px-4 py-3 border-b">Materi</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($entriList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                        @endphp
                                        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                    {{ $entry['tglEdukasi'] ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <div class="font-medium text-ink dark:text-gray-200">{{ $entry['sasaranEdukasi'] ?: '-' }}</div>
                                                    <div class="text-xs text-muted-soft">{{ $entry['hubunganSasaranEdukasidgnPasien'] ?: '-' }}</div>
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['edukasi']['materiTopikEdukasi'] ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['petugasEdukasi']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['petugasEdukasi'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle" @click.stop>
                                                    <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjut Isi
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal)
                                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat
                                                            </x-secondary-button>
                                                        @endif
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')"
                                                            wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')"
                                                            class="gap-1.5" title="Cetak">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1">
                                                                <x-loading class="w-4 h-4" /> Mencetak...
                                                            </span>
                                                        </x-secondary-button>
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus data edukasi ini?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tglEdukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sasaran (Hubungan)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['sasaranEdukasi'] ?: '-' }} <span class="text-muted-soft">({{ $entry['hubunganSasaranEdukasidgnPasien'] ?: '-' }})</span></dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kategori Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                {{ !empty($entry['edukasi']['kategoriEdukasi']) ? implode(', ', $entry['edukasi']['kategoriEdukasi']) : '-' }}
                                                            </dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Materi / Topik Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['edukasi']['materiTopikEdukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keterangan Edukasi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['edukasi']['keteranganEdukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Status Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['edukasi']['statusEdukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Re-Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                @if ($entry['edukasi']['reEdukasi']['perlu'] ?? false)
                                                                    Perlu
                                                                    @if (!empty($entry['edukasi']['reEdukasi']['tglReEdukasi'])) — {{ $entry['edukasi']['reEdukasi']['tglReEdukasi'] }}@endif
                                                                    @if (!empty($entry['edukasi']['reEdukasi']['petugasReEdukasi'])) ({{ $entry['edukasi']['reEdukasi']['petugasReEdukasi'] }})@endif
                                                                @else
                                                                    Tidak perlu
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['petugasEdukasi']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['petugasEdukasi'] }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada data edukasi pasien.</p>
                        @endif
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER STICKY --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 border-t shrink-0 bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
