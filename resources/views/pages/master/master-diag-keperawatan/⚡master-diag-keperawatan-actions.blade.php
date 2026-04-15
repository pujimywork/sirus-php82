<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

// Deklarasi Render Versioning Trait //
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public string $originalDiagkepId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* -------------------------
     | Form state
     * ------------------------- */
    public string $diagkepId = '';
    public string $diagkepDesc = '';

    // SDKI
    public string $sdkiKategori = '';
    public string $sdkiSubkategori = '';
    public string $sdkiDefinisi = '';
    public string $sdkiPenyebabFisiologis = '';
    public string $sdkiPenyebabSituasional = '';
    public string $sdkiPenyebabPsikologis = '';
    public string $sdkiMayorSubjektif = '';
    public string $sdkiMayorObjektif = '';
    public string $sdkiMinorSubjektif = '';
    public string $sdkiMinorObjektif = '';
    public string $sdkiKondisiKlinis = '';

    // SLKI — array of items
    public array $slkiItems = [];

    // SIKI — array of items
    public array $sikiItems = [];

    /* -------------------------
     | Open Create
     * ------------------------- */
    #[On('master.diagkep.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->originalDiagkepId = '';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-diagkep-actions');
        $this->dispatch('focus-diagkep-id');
    }

    /* -------------------------
     | Open Edit
     * ------------------------- */
    #[On('master.diagkep.openEdit')]
    public function openEdit(string $diagkepId): void
    {
        $row = DB::table('rsmst_diagkeperawatans')->where('diagkep_id', $diagkepId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->originalDiagkepId = $diagkepId;

        $this->fillFormFromRow($row);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-diagkep-actions');
        $this->dispatch('focus-diagkep-desc');
    }

    /* -------------------------
     | Reset & Fill
     * ------------------------- */
    protected function resetFormFields(): void
    {
        $this->diagkepId = '';
        $this->diagkepDesc = '';

        $this->sdkiKategori = '';
        $this->sdkiSubkategori = '';
        $this->sdkiDefinisi = '';
        $this->sdkiPenyebabFisiologis = '';
        $this->sdkiPenyebabSituasional = '';
        $this->sdkiPenyebabPsikologis = '';
        $this->sdkiMayorSubjektif = '';
        $this->sdkiMayorObjektif = '';
        $this->sdkiMinorSubjektif = '';
        $this->sdkiMinorObjektif = '';
        $this->sdkiKondisiKlinis = '';

        $this->slkiItems = [];
        $this->sikiItems = [];

        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->diagkepId = (string) $row->diagkep_id;
        $this->diagkepDesc = (string) ($row->diagkep_desc ?? '');

        $json = is_string($row->diagkep_json) ? json_decode($row->diagkep_json, true) : ($row->diagkep_json ?? []);

        // SDKI
        $sdki = $json['sdki'] ?? [];
        $this->sdkiKategori = $sdki['kategori'] ?? '';
        $this->sdkiSubkategori = $sdki['subkategori'] ?? '';
        $this->sdkiDefinisi = $sdki['definisi'] ?? '';

        $penyebab = $sdki['penyebab'] ?? [];
        $this->sdkiPenyebabFisiologis = $this->arrayToLines($penyebab['fisiologis'] ?? []);
        $this->sdkiPenyebabSituasional = $this->arrayToLines($penyebab['situasional'] ?? []);
        $this->sdkiPenyebabPsikologis = $this->arrayToLines($penyebab['psikologis'] ?? []);

        $mayor = $sdki['gejala_tanda_mayor'] ?? [];
        $this->sdkiMayorSubjektif = $this->arrayToLines($mayor['subjektif'] ?? []);
        $this->sdkiMayorObjektif = $this->arrayToLines($mayor['objektif'] ?? []);

        $minor = $sdki['gejala_tanda_minor'] ?? [];
        $this->sdkiMinorSubjektif = $this->arrayToLines($minor['subjektif'] ?? []);
        $this->sdkiMinorObjektif = $this->arrayToLines($minor['objektif'] ?? []);

        $this->sdkiKondisiKlinis = $this->arrayToLines($sdki['kondisi_klinis_terkait'] ?? []);

        // SLKI
        $this->slkiItems = [];
        foreach ($json['slki'] ?? [] as $slki) {
            $this->slkiItems[] = [
                'kode' => $slki['kode'] ?? '',
                'nama' => $slki['nama'] ?? '',
                'kriteria_hasil' => $this->arrayToLines($slki['kriteria_hasil'] ?? []),
            ];
        }

        // SIKI
        $this->sikiItems = [];
        foreach ($json['siki'] ?? [] as $siki) {
            $tindakan = $siki['tindakan'] ?? [];
            $this->sikiItems[] = [
                'kode' => $siki['kode'] ?? '',
                'nama' => $siki['nama'] ?? '',
                'definisi' => $siki['definisi'] ?? '',
                'observasi' => $this->arrayToLines($tindakan['observasi'] ?? []),
                'terapeutik' => $this->arrayToLines($tindakan['terapeutik'] ?? []),
                'edukasi' => $this->arrayToLines($tindakan['edukasi'] ?? []),
                'kolaborasi' => $this->arrayToLines($tindakan['kolaborasi'] ?? []),
            ];
        }
    }

    /* -------------------------
     | SLKI / SIKI dynamic rows
     * ------------------------- */
    public function addSlki(): void
    {
        $this->slkiItems[] = ['kode' => '', 'nama' => '', 'kriteria_hasil' => ''];
    }

    public function removeSlki(int $index): void
    {
        unset($this->slkiItems[$index]);
        $this->slkiItems = array_values($this->slkiItems);
    }

    public function addSiki(): void
    {
        $this->sikiItems[] = ['kode' => '', 'nama' => '', 'definisi' => '', 'observasi' => '', 'terapeutik' => '', 'edukasi' => '', 'kolaborasi' => ''];
    }

    public function removeSiki(int $index): void
    {
        unset($this->sikiItems[$index]);
        $this->sikiItems = array_values($this->sikiItems);
    }

    /* -------------------------
     | Validation
     * ------------------------- */
    protected function rules(): array
    {
        return [
            'diagkepId' => $this->formMode === 'create'
                ? 'required|string|max:20|unique:rsmst_diagkeperawatans,diagkep_id'
                : 'required|string|max:20|unique:rsmst_diagkeperawatans,diagkep_id,' . $this->diagkepId . ',diagkep_id',
            'diagkepDesc' => 'required|string|max:500',
            'sdkiKategori' => 'required|string|max:100',
            'sdkiSubkategori' => 'required|string|max:100',
            'sdkiDefinisi' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'diagkepId.required' => ':attribute wajib diisi.',
            'diagkepId.unique' => ':attribute sudah digunakan.',
            'diagkepDesc.required' => ':attribute wajib diisi.',
            'sdkiKategori.required' => ':attribute wajib diisi.',
            'sdkiSubkategori.required' => ':attribute wajib diisi.',
            'sdkiDefinisi.required' => ':attribute wajib diisi.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'diagkepId' => 'Kode Diagnosis',
            'diagkepDesc' => 'Nama Diagnosis',
            'sdkiKategori' => 'Kategori SDKI',
            'sdkiSubkategori' => 'Subkategori SDKI',
            'sdkiDefinisi' => 'Definisi SDKI',
        ];
    }

    /* -------------------------
     | Save
     * ------------------------- */
    public function save(): void
    {
        $this->validate();

        $jsonData = $this->buildJsonData();

        $payload = [
            'diagkep_desc' => $this->diagkepDesc,
            'diagkep_json' => json_encode($jsonData, JSON_UNESCAPED_UNICODE),
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_diagkeperawatans')->insert([
                'diagkep_id' => $this->diagkepId,
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_diagkeperawatans')->where('diagkep_id', $this->diagkepId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data diagnosis keperawatan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.diagkep.saved');
    }

    /* -------------------------
     | Build JSON from form
     * ------------------------- */
    protected function buildJsonData(): array
    {
        $penyebab = [];
        if (trim($this->sdkiPenyebabFisiologis) !== '') {
            $penyebab['fisiologis'] = $this->linesToArray($this->sdkiPenyebabFisiologis);
        }
        if (trim($this->sdkiPenyebabSituasional) !== '') {
            $penyebab['situasional'] = $this->linesToArray($this->sdkiPenyebabSituasional);
        }
        if (trim($this->sdkiPenyebabPsikologis) !== '') {
            $penyebab['psikologis'] = $this->linesToArray($this->sdkiPenyebabPsikologis);
        }

        $sdki = [
            'kategori' => $this->sdkiKategori,
            'subkategori' => $this->sdkiSubkategori,
            'definisi' => $this->sdkiDefinisi,
            'penyebab' => $penyebab,
            'gejala_tanda_mayor' => [
                'subjektif' => $this->linesToArray($this->sdkiMayorSubjektif),
                'objektif' => $this->linesToArray($this->sdkiMayorObjektif),
            ],
            'gejala_tanda_minor' => [
                'subjektif' => $this->linesToArray($this->sdkiMinorSubjektif),
                'objektif' => $this->linesToArray($this->sdkiMinorObjektif),
            ],
            'kondisi_klinis_terkait' => $this->linesToArray($this->sdkiKondisiKlinis),
        ];

        $slki = [];
        foreach ($this->slkiItems as $item) {
            if (trim($item['kode']) === '' && trim($item['nama']) === '') {
                continue;
            }
            $slki[] = [
                'kode' => $item['kode'],
                'nama' => $item['nama'],
                'kriteria_hasil' => $this->linesToArray($item['kriteria_hasil'] ?? ''),
            ];
        }

        $siki = [];
        foreach ($this->sikiItems as $item) {
            if (trim($item['kode']) === '' && trim($item['nama']) === '') {
                continue;
            }
            $tindakan = [];
            foreach (['observasi', 'terapeutik', 'edukasi', 'kolaborasi'] as $key) {
                $lines = $this->linesToArray($item[$key] ?? '');
                if (!empty($lines)) {
                    $tindakan[$key] = $lines;
                }
            }
            $siki[] = [
                'kode' => $item['kode'],
                'nama' => $item['nama'],
                'definisi' => $item['definisi'] ?? '',
                'tindakan' => $tindakan,
            ];
        }

        return [
            'sdki' => $sdki,
            'slki' => $slki,
            'siki' => $siki,
        ];
    }

    /* -------------------------
     | Close & Delete
     * ------------------------- */
    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'master-diagkep-actions');
        $this->resetVersion();
    }

    #[On('master.diagkep.requestDelete')]
    public function deleteFromGrid(string $diagkepId): void
    {
        try {
            $deleted = DB::table('rsmst_diagkeperawatans')->where('diagkep_id', $diagkepId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data diagnosis tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data diagnosis berhasil dihapus.');
            $this->dispatch('master.diagkep.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Diagnosis tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    /* -------------------------
     | Helpers: lines ↔ array
     * ------------------------- */
    protected function arrayToLines(array $items): string
    {
        return implode("\n", $items);
    }

    protected function linesToArray(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($v) => $v !== ''));
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }
};
?>

<div>
    <x-modal name="master-diagkep-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $originalDiagkepId]) }}">

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
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Diagnosis Keperawatan' : 'Tambah Diagnosis Keperawatan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi data SDKI, SLKI, dan SIKI.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-6"
                    x-data
                    x-on:focus-diagkep-id.window="$nextTick(() => setTimeout(() => $refs.inputDiagkepId?.focus(), 150))"
                    x-on:focus-diagkep-desc.window="$nextTick(() => setTimeout(() => $refs.inputDiagkepDesc?.focus(), 150))">

                    {{-- ========================================
                         PANDUAN PENGISIAN
                    ========================================= --}}
                    <div class="p-4 border rounded-2xl border-amber-300 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700" x-data="{ showGuide: false }">
                        <button type="button" @click="showGuide = !showGuide" class="flex items-center justify-between w-full text-left">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Panduan Pengisian Data Diagnosis Keperawatan</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform text-amber-600 dark:text-amber-400" :class="showGuide ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="showGuide" x-transition class="mt-3 space-y-3 text-sm text-amber-900 dark:text-amber-200">
                            <div class="space-y-2">
                                <p class="font-semibold">Aturan Umum:</p>
                                <ul class="ml-4 space-y-1 list-disc">
                                    <li><strong>Kode Diagnosis</strong> menggunakan format <code class="px-1 py-0.5 rounded bg-amber-200/60 dark:bg-amber-800/40">D.0001</code>, <code class="px-1 py-0.5 rounded bg-amber-200/60 dark:bg-amber-800/40">D.0002</code>, dst. Kode tidak bisa diubah setelah disimpan.</li>
                                    <li><strong>Semua field textarea</strong> (penyebab, gejala, kriteria, tindakan) diisi <strong>satu item per baris</strong>. Tekan Enter untuk baris baru.</li>
                                    <li>Jangan menambahkan nomor urut, bullet, atau tanda strip di awal baris &mdash; sistem akan mengelolanya otomatis.</li>
                                    <li>Baris kosong akan diabaikan secara otomatis saat disimpan.</li>
                                </ul>
                            </div>

                            <div class="space-y-2">
                                <p class="font-semibold">Panduan Per Section:</p>
                                <ul class="ml-4 space-y-1 list-disc">
                                    <li><strong>SDKI</strong> &mdash; Isi sesuai buku SDKI (Standar Diagnosis Keperawatan Indonesia). Kategori, subkategori, dan definisi wajib diisi. Penyebab, gejala, dan kondisi klinis opsional sesuai referensi.</li>
                                    <li><strong>SLKI</strong> &mdash; Kode luaran format <code class="px-1 py-0.5 rounded bg-amber-200/60 dark:bg-amber-800/40">L.xxxxx</code>. Satu diagnosis bisa memiliki lebih dari satu luaran. Kriteria hasil diisi per baris.</li>
                                    <li><strong>SIKI</strong> &mdash; Kode intervensi format <code class="px-1 py-0.5 rounded bg-amber-200/60 dark:bg-amber-800/40">I.xxxxx</code>. Tindakan dibagi 4 kategori: Observasi, Terapeutik, Edukasi, Kolaborasi &mdash; isi sesuai kebutuhan, tidak harus semua terisi.</li>
                                </ul>
                            </div>

                            <div class="p-3 space-y-1 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                                <p class="font-semibold">Peringatan saat Edit:</p>
                                <ul class="ml-4 space-y-1 list-disc">
                                    <li>Jangan menghapus SLKI/SIKI yang sudah dipakai di asuhan keperawatan pasien &mdash; data referensi akan hilang.</li>
                                    <li>Saat mengubah data, pastikan sesuai dengan buku referensi SDKI/SLKI/SIKI terbaru.</li>
                                    <li>Jika ragu, konsultasikan dengan penanggung jawab keperawatan sebelum mengubah data.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- ========================================
                         IDENTITAS DIAGNOSIS
                    ========================================= --}}
                    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 pb-2 mb-4">Identitas Diagnosis</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Kode Diagnosis (cth: D.0001)" />
                                <x-text-input wire:model.live="diagkepId" x-ref="inputDiagkepId"
                                    :disabled="$formMode === 'edit'" :error="$errors->has('diagkepId')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('diagkepId')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Nama Diagnosis" />
                                <x-text-input wire:model.live="diagkepDesc" x-ref="inputDiagkepDesc"
                                    :error="$errors->has('diagkepDesc')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('diagkepDesc')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- ========================================
                         SDKI
                    ========================================= --}}
                    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 pb-2 mb-4">SDKI (Standar Diagnosis Keperawatan Indonesia)</h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Kategori" />
                                    <x-text-input wire:model.live="sdkiKategori" :error="$errors->has('sdkiKategori')" class="w-full mt-1" placeholder="cth: Fisiologis" />
                                    <x-input-error :messages="$errors->get('sdkiKategori')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Subkategori" />
                                    <x-text-input wire:model.live="sdkiSubkategori" :error="$errors->has('sdkiSubkategori')" class="w-full mt-1" placeholder="cth: Respirasi" />
                                    <x-input-error :messages="$errors->get('sdkiSubkategori')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Definisi" />
                                <textarea wire:model.live="sdkiDefinisi" rows="3"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                    placeholder="Definisi diagnosis..."></textarea>
                                <x-input-error :messages="$errors->get('sdkiDefinisi')" class="mt-1" />
                            </div>

                            {{-- Penyebab --}}
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div>
                                    <x-input-label value="Penyebab Fisiologis" />
                                    <textarea wire:model.live="sdkiPenyebabFisiologis" rows="4"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu penyebab per baris"></textarea>
                                </div>
                                <div>
                                    <x-input-label value="Penyebab Situasional" />
                                    <textarea wire:model.live="sdkiPenyebabSituasional" rows="4"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu penyebab per baris"></textarea>
                                </div>
                                <div>
                                    <x-input-label value="Penyebab Psikologis" />
                                    <textarea wire:model.live="sdkiPenyebabPsikologis" rows="4"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu penyebab per baris"></textarea>
                                </div>
                            </div>

                            {{-- Gejala Mayor --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Gejala/Tanda Mayor - Subjektif" />
                                    <textarea wire:model.live="sdkiMayorSubjektif" rows="3"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu gejala per baris"></textarea>
                                </div>
                                <div>
                                    <x-input-label value="Gejala/Tanda Mayor - Objektif" />
                                    <textarea wire:model.live="sdkiMayorObjektif" rows="3"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu gejala per baris"></textarea>
                                </div>
                            </div>

                            {{-- Gejala Minor --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Gejala/Tanda Minor - Subjektif" />
                                    <textarea wire:model.live="sdkiMinorSubjektif" rows="3"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu gejala per baris"></textarea>
                                </div>
                                <div>
                                    <x-input-label value="Gejala/Tanda Minor - Objektif" />
                                    <textarea wire:model.live="sdkiMinorObjektif" rows="3"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu gejala per baris"></textarea>
                                </div>
                            </div>

                            {{-- Kondisi Klinis --}}
                            <div>
                                <x-input-label value="Kondisi Klinis Terkait" />
                                <textarea wire:model.live="sdkiKondisiKlinis" rows="3"
                                    class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                    placeholder="Satu kondisi per baris"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- ========================================
                         SLKI
                    ========================================= --}}
                    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 pb-2">SLKI (Standar Luaran Keperawatan Indonesia)</h3>
                            <x-outline-button type="button" wire:click="addSlki">+ Tambah SLKI</x-outline-button>
                        </div>

                        @forelse($slkiItems as $idx => $slki)
                            <div wire:key="slki-{{ $idx }}" class="p-4 mb-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold text-gray-400">SLKI #{{ $idx + 1 }}</span>
                                    <button type="button" wire:click="removeSlki({{ $idx }})" class="text-xs text-red-500 hover:text-red-700">Hapus</button>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Kode" />
                                        <x-text-input wire:model.live="slkiItems.{{ $idx }}.kode" class="w-full mt-1" placeholder="cth: L.01001" />
                                    </div>
                                    <div>
                                        <x-input-label value="Nama Luaran" />
                                        <x-text-input wire:model.live="slkiItems.{{ $idx }}.nama" class="w-full mt-1" />
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <x-input-label value="Kriteria Hasil" />
                                    <textarea wire:model.live="slkiItems.{{ $idx }}.kriteria_hasil" rows="3"
                                        class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="Satu kriteria per baris"></textarea>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-sm text-center text-gray-400">Belum ada data SLKI. Klik "+ Tambah SLKI" untuk menambah.</p>
                        @endforelse
                    </div>

                    {{-- ========================================
                         SIKI
                    ========================================= --}}
                    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 pb-2">SIKI (Standar Intervensi Keperawatan Indonesia)</h3>
                            <x-outline-button type="button" wire:click="addSiki">+ Tambah SIKI</x-outline-button>
                        </div>

                        @forelse($sikiItems as $idx => $siki)
                            <div wire:key="siki-{{ $idx }}" class="p-4 mb-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold text-gray-400">SIKI #{{ $idx + 1 }}</span>
                                    <button type="button" wire:click="removeSiki({{ $idx }})" class="text-xs text-red-500 hover:text-red-700">Hapus</button>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Kode" />
                                        <x-text-input wire:model.live="sikiItems.{{ $idx }}.kode" class="w-full mt-1" placeholder="cth: I.01001" />
                                    </div>
                                    <div>
                                        <x-input-label value="Nama Intervensi" />
                                        <x-text-input wire:model.live="sikiItems.{{ $idx }}.nama" class="w-full mt-1" />
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <x-input-label value="Definisi" />
                                    <x-text-input wire:model.live="sikiItems.{{ $idx }}.definisi" class="w-full mt-1" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Tindakan: Observasi" />
                                        <textarea wire:model.live="sikiItems.{{ $idx }}.observasi" rows="3"
                                            class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                            placeholder="Satu tindakan per baris"></textarea>
                                    </div>
                                    <div>
                                        <x-input-label value="Tindakan: Terapeutik" />
                                        <textarea wire:model.live="sikiItems.{{ $idx }}.terapeutik" rows="3"
                                            class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                            placeholder="Satu tindakan per baris"></textarea>
                                    </div>
                                    <div>
                                        <x-input-label value="Tindakan: Edukasi" />
                                        <textarea wire:model.live="sikiItems.{{ $idx }}.edukasi" rows="3"
                                            class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                            placeholder="Satu tindakan per baris"></textarea>
                                    </div>
                                    <div>
                                        <x-input-label value="Tindakan: Kolaborasi" />
                                        <textarea wire:model.live="sikiItems.{{ $idx }}.kolaborasi" rows="3"
                                            class="w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                            placeholder="Satu tindakan per baris"></textarea>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-sm text-center text-gray-400">Belum ada data SIKI. Klik "+ Tambah SIKI" untuk menambah.</p>
                        @endforelse
                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Field bertanda * wajib diisi. Isi textarea satu item per baris.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
