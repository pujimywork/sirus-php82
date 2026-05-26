<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/resiko-jatuh-ri/rm-penilaian-resiko-jatuh-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-resiko-jatuh-ri'];

    public array $formEntryResikoJatuh = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'resikoJatuh' => [
            'resikoJatuh' => 'Tidak',
            'resikoJatuhMetode' => ['resikoJatuhMetode' => '', 'resikoJatuhMetodeScore' => 0, 'dataResikoJatuh' => []],
            'kategoriResiko' => '',
            'rekomendasi' => '',
        ],
    ];

    public array $skalaMorseOptions = [
        'riwayatJatuh' => [['riwayatJatuh' => 'Ya', 'score' => 25], ['riwayatJatuh' => 'Tidak', 'score' => 0]],
        'diagnosisSekunder' => [['diagnosisSekunder' => 'Ya', 'score' => 15], ['diagnosisSekunder' => 'Tidak', 'score' => 0]],
        'alatBantu' => [['alatBantu' => 'Tidak Ada / Bed Rest', 'score' => 0], ['alatBantu' => 'Tongkat / Alat Penopang / Walker', 'score' => 15], ['alatBantu' => 'Furnitur', 'score' => 30]],
        'terapiIV' => [['terapiIV' => 'Ya', 'score' => 20], ['terapiIV' => 'Tidak', 'score' => 0]],
        'gayaBerjalan' => [['gayaBerjalan' => 'Normal / Tirah Baring / Tidak Bergerak', 'score' => 0], ['gayaBerjalan' => 'Lemah', 'score' => 10], ['gayaBerjalan' => 'Terganggu', 'score' => 20]],
        'statusMental' => [['statusMental' => 'Baik', 'score' => 0], ['statusMental' => 'Lupa / Pelupa', 'score' => 15]],
    ];

    public array $humptyDumptyOptions = [
        'umur' => [['umur' => '< 3 tahun', 'score' => 4], ['umur' => '3-7 tahun', 'score' => 3], ['umur' => '7-13 tahun', 'score' => 2], ['umur' => '13-18 tahun', 'score' => 1]],
        'jenisKelamin' => [['jenisKelamin' => 'Laki-laki', 'score' => 2], ['jenisKelamin' => 'Perempuan', 'score' => 1]],
        'diagnosis' => [['diagnosis' => 'Diagnosis neurologis atau perkembangan', 'score' => 4], ['diagnosis' => 'Diagnosis ortopedi', 'score' => 3], ['diagnosis' => 'Diagnosis lainnya', 'score' => 2], ['diagnosis' => 'Tidak ada diagnosis khusus', 'score' => 1]],
        'gangguanKognitif' => [['gangguanKognitif' => 'Gangguan kognitif berat', 'score' => 3], ['gangguanKognitif' => 'Gangguan kognitif sedang', 'score' => 2], ['gangguanKognitif' => 'Gangguan kognitif ringan', 'score' => 1], ['gangguanKognitif' => 'Tidak ada gangguan kognitif', 'score' => 0]],
        'faktorLingkungan' => [['faktorLingkungan' => 'Lingkungan berisiko tinggi', 'score' => 3], ['faktorLingkungan' => 'Lingkungan berisiko sedang', 'score' => 2], ['faktorLingkungan' => 'Lingkungan berisiko rendah', 'score' => 1], ['faktorLingkungan' => 'Lingkungan aman', 'score' => 0]],
        'responObat' => [['responObat' => 'Efek samping obat yang meningkatkan risiko jatuh', 'score' => 3], ['responObat' => 'Efek samping obat ringan', 'score' => 2], ['responObat' => 'Tidak ada efek samping obat', 'score' => 1]],
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-resiko-jatuh-ri']);
    }

    #[On('open-rm-penilaian-resiko-jatuh-ri')]
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
        $this->dataDaftarRi['penilaian']['resikoJatuh'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-penilaian-resiko-jatuh-ri');
    }

    public function setTglPenilaianResikoJatuh(): void
    {
        $this->formEntryResikoJatuh['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function updatedFormEntryResikoJatuhResikoJatuhResikoJatuhMetodeResikoJatuhMetode(): void
    {
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] = [];
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = 0;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = '';
    }

    public function updatedFormEntryResikoJatuhResikoJatuhResikoJatuhMetodeDataResikoJatuh(): void
    {
        $metode = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '';
        $selected = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] ?? [];
        $options = $metode === 'Skala Morse' ? $this->skalaMorseOptions : $this->humptyDumptyOptions;

        $skor = 0;
        foreach ($options as $key => $opts) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($opts as $opt) {
                if (($opt[$key] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = $skor;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = $metode === 'Skala Morse' ? ($skor >= 45 ? 'Tinggi' : ($skor >= 25 ? 'Sedang' : 'Rendah')) : ($skor >= 16 ? 'Tinggi' : ($skor >= 12 ? 'Sedang' : 'Rendah'));
    }

    #[On('save-rm-penilaian-resiko-jatuh-ri')]
    public function addAssessmentResikoJatuh(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryResikoJatuh['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoJatuh['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-fill tanggal kalau Tidak & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] ?? '') !== 'Ya' && empty($this->formEntryResikoJatuh['tglPenilaian'])) {
            $this->setTglPenilaianResikoJatuh();
        }

        $this->validateWithToast([
            'formEntryResikoJatuh.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
            'formEntryResikoJatuh.resikoJatuh.resikoJatuh' => 'required|in:Ya,Tidak',
            'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode' => 'required_if:formEntryResikoJatuh.resikoJatuh.resikoJatuh,Ya|string',
        ]);

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['resikoJatuh'][] = $this->formEntryResikoJatuh;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryResikoJatuh']);
            $this->afterSave('Penilaian Risiko Jatuh berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentResikoJatuh(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['resikoJatuh'], $index, 1);
                $fresh['penilaian']['resikoJatuh'] = array_values($fresh['penilaian']['resikoJatuh']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Risiko Jatuh dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-resiko-jatuh-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'resikoJatuh');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryResikoJatuh']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-resiko-jatuh-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form title="Form Penilaian Risiko Jatuh" align="start" bgcolor="bg-gray-50">
            <div class="mt-4 space-y-4">

                <div>
                    <x-input-label value="Risiko Jatuh *" />
                    <x-select-input wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuh"
                        class="w-full mt-1">
                        <option value="Tidak">Tidak</option>
                        <option value="Ya">Ya</option>
                    </x-select-input>
                </div>

                @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                    <div>
                        <x-input-label value="Tanggal Penilaian *" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryResikoJatuh.tglPenilaian"
                                placeholder="dd/mm/yyyy hh:ii:ss" :error="$errors->has('formEntryResikoJatuh.tglPenilaian')" class="w-full" />
                            <x-secondary-button wire:click="setTglPenilaianResikoJatuh" type="button"
                                class="whitespace-nowrap text-xs">Sekarang</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryResikoJatuh.tglPenilaian')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Metode *" />
                        <x-select-input
                            wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode"
                            class="w-full mt-1">
                            <option value="">-- Pilih Metode --</option>
                            <option value="Skala Morse">Skala Morse (Dewasa)</option>
                            <option value="Humpty Dumpty">Humpty Dumpty (Pediatrik)</option>
                        </x-select-input>
                    </div>

                    @php $metodeRJ = $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? ''; @endphp

                    @if (in_array($metodeRJ, ['Skala Morse', 'Humpty Dumpty']))
                        @php
                            $skorRJ =
                                $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'];
                            $katRJ = $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'];
                            $optionsRJ = $metodeRJ === 'Skala Morse' ? $skalaMorseOptions : $humptyDumptyOptions;
                            $interpretasiRJ =
                                $metodeRJ === 'Skala Morse'
                                    ? '<25 Rendah | 25–44 Sedang | ≥45 Tinggi'
                                    : '<12 Rendah | 12–15 Sedang | ≥16 Tinggi';
                        @endphp
                        <x-border-form :title="$metodeRJ" align="start" bgcolor="bg-white">
                            <div class="mt-3 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                        Skor: {{ $skorRJ }}
                                    </span>
                                    @if ($katRJ)
                                        <span
                                            class="px-2 py-0.5 text-xs font-bold rounded-full
                                            {{ $katRJ === 'Tinggi' ? 'bg-red-100 text-red-700' : ($katRJ === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                            {{ $katRJ }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400">Interpretasi: {{ $interpretasiRJ }}</span>
                                </div>
                                @foreach ($optionsRJ as $key => $opts)
                                    <div>
                                        <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                        <x-select-input
                                            wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh.{{ $key }}"
                                            class="w-full mt-1">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($opts as $opt)
                                                <option value="{{ $opt[$key] }}">
                                                    {{ $opt[$key] }} (Skor: {{ $opt['score'] }})
                                                </option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </x-border-form>
                    @endif

                    <div>
                        <x-input-label value="Rekomendasi" />
                        <x-textarea wire:model="formEntryResikoJatuh.resikoJatuh.rekomendasi" class="w-full mt-1"
                            rows="2" />
                    </div>
                @endif

            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarRi['penilaian']['resikoJatuh']))
        <x-border-form title="Riwayat Penilaian Risiko Jatuh" align="start" bgcolor="bg-white">
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl Penilaian</th>
                            <th class="px-3 py-2">Petugas</th>
                            <th class="px-3 py-2">Risiko</th>
                            <th class="px-3 py-2">Metode</th>
                            <th class="px-3 py-2">Skor</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Rekomendasi</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarRi['penilaian']['resikoJatuh'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['resikoJatuh']['kategoriResiko'] ?? '-';
                                $rowBg =
                                    $kat === 'Tinggi'
                                        ? 'bg-red-50 hover:bg-red-100'
                                        : ($kat === 'Sedang'
                                            ? 'bg-yellow-50 hover:bg-yellow-100'
                                            : ($kat === 'Rendah'
                                                ? 'bg-green-50 hover:bg-green-100'
                                                : 'hover:bg-gray-50'));
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ ($row['resikoJatuh']['resikoJatuh'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['resikoJatuh']['resikoJatuh'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">
                                    {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $kat === 'Tinggi' ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['resikoJatuh']['rekomendasi'] ?? '-' }}
                                </td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-icon-button variant="danger"
                                            wire:click="removeAssessmentResikoJatuh({{ $i }})"
                                            wire:confirm="Hapus data risiko jatuh ini?">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
        </x-border-form>
    @else
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian risiko jatuh.</p>
    @endif
</div>
