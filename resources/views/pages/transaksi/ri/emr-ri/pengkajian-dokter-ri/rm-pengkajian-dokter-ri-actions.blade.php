<?php
// resources/views/pages/transaksi/ri/emr-ri/pengkajian-dokter/rm-pengkajian-dokter-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $rekonsiliasiObat = ['namaObat' => '', 'dosis' => '', 'rute' => ''];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-dokter-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-pengkajian-dokter-ri']);
    }

    #[On('open-rm-pengkajian-dokter-ri')]
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
        $this->dataDaftarRi['pengkajianDokter'] ??= [
            'anamnesa' => [
                'keluhanUtama' => '',
                'keluhanTambahan' => '',
                'riwayatPenyakit' => ['sekarang' => '', 'dahulu' => '', 'keluarga' => ''],
                'jenisAlergi' => '',
                'rekonsiliasiObat' => [],
            ],
            'fisik' => '',
            'anatomi' => collect(['kepala', 'mata', 'telinga', 'hidung', 'rambut', 'bibir', 'gigiGeligi', 'lidah', 'langitLangit', 'leher', 'tenggorokan', 'tonsil', 'dada', 'payudara', 'punggung', 'perut', 'genital', 'anus', 'lenganAtas', 'lenganBawah', 'jariTangan', 'kukuTangan', 'persendianTangan', 'tungkaiAtas', 'tungkaiBawah', 'jariKaki', 'kukuKaki', 'persendianKaki', 'faring'])
                ->mapWithKeys(fn($p) => [$p => ['kelainan' => 'Tidak Diperiksa', 'desc' => '']])
                ->toArray(),
            'statusLokalis' => ['deskripsiGambar' => ''],
            'hasilPemeriksaanPenunjang' => ['laboratorium' => '', 'radiologi' => '', 'penunjangLain' => ''],
            'diagnosaAssesment' => ['diagnosaAwal' => ''],
            'rencana' => ['penegakanDiagnosa' => '', 'terapi' => '', 'terapiPulang' => '', 'diet' => '', 'edukasi' => '', 'monitoring' => ''],
            'ringkasanPasienPulang' => ['kondisiPulang' => '', 'instruksiPulang' => '', 'kontrolKe' => ''],
            'tandaTanganDokter' => ['dokterPengkaji' => '', 'dokterPengkajiCode' => '', 'jamDokterPengkaji' => ''],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-pengkajian-dokter-ri');
    }

    #[On('save-rm-pengkajian-dokter-ri')]
    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $this->validate(['dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama' => 'required|string|max:1000'], ['dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama.required' => 'Keluhan utama wajib diisi.']);

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['pengkajianDokter'] = $this->dataDaftarRi['pengkajianDokter'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pengkajian Dokter berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function setDokterPengkaji(): void
    {
        if (!auth()->user()->hasRole('Dokter')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Dokter yang dapat melakukan TTD.');
            return;
        }
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji'] = auth()->user()->myuser_name;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkajiCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->store();
    }

    public function addRekonsiliasiObat(): void
    {
        if (empty($this->rekonsiliasiObat['namaObat'])) {
            $this->dispatch('toast', type: 'error', message: 'Nama obat kosong.');
            return;
        }

        $exists = collect($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [])->contains('namaObat', $this->rekonsiliasiObat['namaObat']);

        if ($exists) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada.');
            return;
        }

        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'][] = [
            'namaObat' => $this->rekonsiliasiObat['namaObat'],
            'dosis' => $this->rekonsiliasiObat['dosis'],
            'rute' => $this->rekonsiliasiObat['rute'],
        ];

        $this->store();
        $this->reset(['rekonsiliasiObat']);
    }

    public function removeRekonsiliasiObat(string $namaObat): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] = collect($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [])
            ->reject(fn($o) => $o['namaObat'] === $namaObat)
            ->values()
            ->toArray();

        $this->store();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-pengkajian-dokter-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    /* ── Buka E-Resep dari Pengkajian Dokter ── */
    public function openEresep(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RI tidak ditemukan.');
            return;
        }
        $this->dispatch('emr-ri.eresep.open', riHdrNo: (int) $this->riHdrNo);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['rekonsiliasiObat']);
    }

    /* ===============================
     | TERIMA DARI LABORATORIUM DISPLAY → Hasil Pemeriksaan Penunjang Laboratorium
     =============================== */
    #[On('laborat-kirim-penunjang')]
    public function terimaPenunjangLaborat(string $text): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        if (empty($this->dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?: [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                // Append ke pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium
                $existing = $data['pengkajianDokter']['hasilPemeriksaanPenunjang']['laboratorium'] ?? '';
                $data['pengkajianDokter']['hasilPemeriksaanPenunjang']['laboratorium'] = trim(($existing ? $existing . "\n" : '') . $text);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Data laboratorium berhasil dikirim ke Hasil Pemeriksaan Penunjang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-pengkajian-dokter-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ══════════════════════════════════════
    | BAGIAN 1 — ANAMNESA
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 1 — Anamnesa" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">

            <div>
                <x-input-label value="Keluhan Utama *" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama" class="w-full mt-1"
                    rows="3" :disabled="$isFormLocked" placeholder="Keluhan utama pasien..." />
                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama')" class="mt-1" />
            </div>

            <div>
                <x-input-label value="Keluhan Tambahan" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanTambahan" class="w-full mt-1"
                    rows="2" :disabled="$isFormLocked" placeholder="Keluhan tambahan..." />
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Riwayat Penyakit Sekarang" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.sekarang"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked" />
                </div>
                <div>
                    <x-input-label value="Riwayat Penyakit Dahulu" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.dahulu"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked" />
                </div>
                <div>
                    <x-input-label value="Riwayat Penyakit Keluarga" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.keluarga"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked" />
                </div>
            </div>

            <div>
                <x-input-label value="Jenis Alergi" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.jenisAlergi" class="w-full mt-1"
                    :disabled="$isFormLocked" placeholder="Alergi obat / makanan..." />
            </div>

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 1B — REKONSILIASI OBAT
    ══════════════════════════════════════ --}}
    <x-border-form title="Rekonsiliasi Obat" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">

            @if (!$isFormLocked)
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <x-input-label value="Nama Obat" />
                        <x-text-input wire:model="rekonsiliasiObat.namaObat" class="w-full mt-1"
                            placeholder="Nama obat..." />
                    </div>
                    <div>
                        <x-input-label value="Dosis" />
                        <x-text-input wire:model="rekonsiliasiObat.dosis" class="w-full mt-1" placeholder="500mg..." />
                    </div>
                    <div>
                        <x-input-label value="Rute" />
                        <x-text-input wire:model="rekonsiliasiObat.rute" class="w-full mt-1"
                            placeholder="Oral / IV / SC..." />
                    </div>
                </div>
                <div>
                    <x-primary-button wire:click="addRekonsiliasiObat" type="button" class="text-xs">
                        + Tambah Obat
                    </x-primary-button>
                </div>
            @endif

            @if (!empty($dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Nama Obat</th>
                                <th class="px-3 py-2">Dosis</th>
                                <th class="px-3 py-2">Rute</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 w-10"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] as $obat)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-3 py-2">{{ $obat['namaObat'] }}</td>
                                    <td class="px-3 py-2">{{ $obat['dosis'] }}</td>
                                    <td class="px-3 py-2">{{ $obat['rute'] }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeRekonsiliasiObat('{{ $obat['namaObat'] }}')"
                                                wire:confirm="Hapus obat {{ $obat['namaObat'] }}?">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-center text-gray-400 py-2">Belum ada obat.</p>
            @endif

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 2.1 — PEMERIKSAAN FISIK
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 2.1 — Pemeriksaan Fisik" align="start" bgcolor="bg-gray-50">
        <div class="mt-3">
            <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.fisik" class="w-full" rows="5"
                :disabled="$isFormLocked" placeholder="Deskripsi pemeriksaan fisik status generalis..." />
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 2.2 — PEMERIKSAAN ANATOMI
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 2.2 — Pemeriksaan Anatomi" align="start" bgcolor="bg-gray-50">
        @php
            $anatomiList = [
                'kepala' => 'Kepala',
                'mata' => 'Mata',
                'telinga' => 'Telinga',
                'hidung' => 'Hidung',
                'rambut' => 'Rambut',
                'bibir' => 'Bibir',
                'gigiGeligi' => 'Gigi Geligi',
                'lidah' => 'Lidah',
                'langitLangit' => 'Langit-Langit',
                'leher' => 'Leher',
                'tenggorokan' => 'Tenggorokan',
                'tonsil' => 'Tonsil',
                'dada' => 'Dada',
                'payudara' => 'Payudara',
                'punggung' => 'Punggung',
                'perut' => 'Perut',
                'genital' => 'Genital',
                'anus' => 'Anus',
                'lenganAtas' => 'Lengan Atas',
                'lenganBawah' => 'Lengan Bawah',
                'jariTangan' => 'Jari Tangan',
                'kukuTangan' => 'Kuku Tangan',
                'persendianTangan' => 'Persendian Tangan',
                'tungkaiAtas' => 'Tungkai Atas',
                'tungkaiBawah' => 'Tungkai Bawah',
                'jariKaki' => 'Jari Kaki',
                'kukuKaki' => 'Kuku Kaki',
                'persendianKaki' => 'Persendian Kaki',
                'faring' => 'Faring',
            ];
        @endphp

        <div class="mt-4" x-data="{ activeTabAnatomi: '{{ array_key_first($anatomiList) }}' }">
            <div class="flex gap-4">

                {{-- SIDEBAR TABS --}}
                <div
                    class="w-44 shrink-0 overflow-y-auto max-h-80 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    @foreach ($anatomiList as $key => $label)
                        <button type="button" @click="activeTabAnatomi = '{{ $key }}'"
                            class="w-full text-left px-3 py-2.5 text-xs font-medium border-b border-gray-100 dark:border-gray-700 transition-colors last:border-0"
                            :class="activeTabAnatomi === '{{ $key }}'
                                ?
                                'bg-brand text-white' :
                                'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800'">
                            {{ strtoupper($label) }}
                        </button>
                    @endforeach
                </div>

                {{-- PANEL KONTEN --}}
                <div class="flex-1 min-w-0">
                    @foreach ($anatomiList as $key => $label)
                        @php
                            $kelainan =
                                $dataDaftarRi['pengkajianDokter']['anatomi'][$key]['kelainan'] ?? 'Tidak Diperiksa';
                        @endphp

                        {{-- ✅ x-data per panel agar kelainan Alpine reactive --}}
                        <div x-show="activeTabAnatomi === '{{ $key }}'" x-data="{ kelainan: '{{ $kelainan }}' }"
                            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100" class="space-y-3">

                            {{-- Kelainan --}}
                            <div>
                                <x-input-label :value="__(strtoupper($label) . ' — Kelainan')" />
                                <x-select-input x-on:change="kelainan = $event.target.value"
                                    wire:model.live="dataDaftarRi.pengkajianDokter.anatomi.{{ $key }}.kelainan"
                                    :disabled="$isFormLocked" class="w-full mt-1">
                                    <option value="Tidak Diperiksa">Tidak Diperiksa</option>
                                    <option value="Tidak Ada Kelainan">Tidak Ada Kelainan</option>
                                    <option value="Ada">Ada Kelainan</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anatomi.' . $key . '.kelainan')" class="mt-1" />
                            </div>

                            {{-- Deskripsi — ✅ reactive via Alpine state --}}
                            <div x-show="kelainan === 'Ada'" x-cloak>
                                <x-input-label value="Deskripsi Kelainan" />
                                <x-textarea
                                    wire:model.live="dataDaftarRi.pengkajianDokter.anatomi.{{ $key }}.desc"
                                    placeholder="Deskripsi kelainan {{ $label }}..." :disabled="$isFormLocked"
                                    rows="4" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anatomi.' . $key . '.desc')" class="mt-1" />
                            </div>

                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 3 — STATUS LOKALIS
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 3 — Status Lokalis" align="start" bgcolor="bg-gray-50">
        <div class="mt-3">
            <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.statusLokalis.deskripsiGambar" class="w-full"
                rows="4" :disabled="$isFormLocked" placeholder="Deskripsi status lokalis..." />
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 4 — HASIL PEMERIKSAAN PENUNJANG
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 4 — Hasil Pemeriksaan Penunjang" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 grid grid-cols-3 gap-3">
            <div>
                <x-input-label value="Laboratorium" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked" />
            </div>
            <div>
                <x-input-label value="Radiologi" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.radiologi"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked" />
            </div>
            <div>
                <x-input-label value="Penunjang Lain" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked" />
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 5 — DIAGNOSA & RENCANA
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 5 — Diagnosa & Rencana Terapi" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Diagnosa Awal / Assessment" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.diagnosaAssesment.diagnosaAwal"
                    class="w-full mt-1" rows="2" :disabled="$isFormLocked" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([['key' => 'penegakanDiagnosa', 'label' => 'Penegakan Diagnosa'], ['key' => 'terapi', 'label' => 'Terapi'], ['key' => 'terapiPulang', 'label' => 'Terapi Pulang'], ['key' => 'diet', 'label' => 'Diet'], ['key' => 'edukasi', 'label' => 'Edukasi'], ['key' => 'monitoring', 'label' => 'Monitoring']] as $field)
                    <div>
                        <x-input-label value="{{ $field['label'] }}" />
                        <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.rencana.{{ $field['key'] }}"
                            class="w-full mt-1" rows="2" :disabled="$isFormLocked" />
                    </div>
                @endforeach
            </div>
            @role(['Dokter', 'Admin'])
                @if (!$isFormLocked)
                    <div class="flex justify-end">
                        <x-secondary-button wire:click="openEresep" type="button"
                            title="Buka E-Resep untuk menulis resep obat">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Buka E-Resep
                        </x-secondary-button>
                    </div>
                @endif
            @endrole
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 7 — RINGKASAN PASIEN PULANG
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 7 — Ringkasan Pasien Pulang" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Kondisi Saat Pulang" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.kondisiPulang"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked"
                    placeholder="Deskripsi kondisi pasien saat pulang..." />
            </div>
            <div>
                <x-input-label value="Instruksi / Saran Pulang" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.instruksiPulang"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked"
                    placeholder="Instruksi diet, aktivitas, obat pulang..." />
            </div>
            <div>
                <x-input-label value="Kontrol Ke" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.kontrolKe"
                    class="w-full mt-1" :disabled="$isFormLocked" placeholder="Poli / dokter tujuan kontrol..." />
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 6 — TANDA TANGAN DOKTER
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 6 — Tanda Tangan Dokter Pengkaji" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 flex items-center gap-4">
            <div class="flex-1">
                <x-input-label value="Dokter Pengkaji" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji'] ?? '-' }}"
                    class="w-full mt-1" readonly />
            </div>
            <div class="flex-1">
                <x-input-label value="Jam TTD" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji'] ?? '-' }}"
                    class="w-full mt-1" readonly />
            </div>
            @if (!$isFormLocked)
                @role('Dokter')
                    <div class="pt-5">
                        <x-primary-button wire:click="setDokterPengkaji" type="button">TTD Saya</x-primary-button>
                    </div>
                @endrole
            @endif
        </div>
    </x-border-form>

    {{-- ── TOMBOL SIMPAN ── --}}
    @if (!$isFormLocked)
        <div class="flex justify-end pt-2">
            <x-primary-button wire:click="store" type="button" wire:loading.attr="disabled" wire:target="store">
                <span wire:loading.remove wire:target="store" class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan Pengkajian Dokter
                </span>
                <span wire:loading wire:target="store" class="flex items-center gap-1">
                    <x-loading /> Menyimpan...
                </span>
            </x-primary-button>
        </div>
    @endif

</div>
