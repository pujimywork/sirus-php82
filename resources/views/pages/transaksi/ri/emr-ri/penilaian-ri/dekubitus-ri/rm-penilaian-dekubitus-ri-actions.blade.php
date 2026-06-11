<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/dekubitus-ri/rm-penilaian-dekubitus-ri-actions.blade.php

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
    protected array $renderAreas = ['modal-penilaian-dekubitus-ri'];

    public array $formEntryDekubitus = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'dekubitus' => [
            'dekubitus' => 'Tidak',
            'bradenScore' => 0,
            'kategoriResiko' => '',
            'dataBraden' => [],
            'rekomendasi' => '',
        ],
    ];

    public array $bradenScaleOptions = [
        'sensoryPerception' => [['score' => 4, 'description' => 'Tidak ada gangguan sensorik'], ['score' => 3, 'description' => 'Gangguan sensorik ringan'], ['score' => 2, 'description' => 'Gangguan sensorik sedang'], ['score' => 1, 'description' => 'Gangguan sensorik berat']],
        'moisture' => [['score' => 4, 'description' => 'Kulit kering'], ['score' => 3, 'description' => 'Kulit lembab'], ['score' => 2, 'description' => 'Kulit basah'], ['score' => 1, 'description' => 'Kulit sangat basah']],
        'activity' => [['score' => 4, 'description' => 'Berjalan secara teratur'], ['score' => 3, 'description' => 'Berjalan dengan bantuan'], ['score' => 2, 'description' => 'Duduk di kursi'], ['score' => 1, 'description' => 'Terbaring di tempat tidur']],
        'mobility' => [['score' => 4, 'description' => 'Mobilitas penuh'], ['score' => 3, 'description' => 'Mobilitas sedikit terbatas'], ['score' => 2, 'description' => 'Mobilitas sangat terbatas'], ['score' => 1, 'description' => 'Tidak bisa bergerak']],
        'nutrition' => [['score' => 4, 'description' => 'Asupan nutrisi baik'], ['score' => 3, 'description' => 'Asupan nutrisi cukup'], ['score' => 2, 'description' => 'Asupan nutrisi kurang'], ['score' => 1, 'description' => 'Asupan nutrisi sangat kurang']],
        'frictionShear' => [['score' => 3, 'description' => 'Tidak ada masalah gesekan atau geseran'], ['score' => 2, 'description' => 'Potensi masalah gesekan atau geseran'], ['score' => 1, 'description' => 'Masalah gesekan atau geseran yang signifikan']],
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-dekubitus-ri']);
    }

    #[On('open-rm-penilaian-dekubitus-ri')]
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
        $this->dataDaftarRi['penilaian']['dekubitus'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-penilaian-dekubitus-ri');
    }

    public function setTglPenilaianDekubitus(): void
    {
        $this->formEntryDekubitus['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function updatedFormEntryDekubitusDekubitusDataBraden(): void
    {
        $this->hitungSkorBraden();
    }

    public function hitungSkorBraden(): void
    {
        $data = $this->formEntryDekubitus['dekubitus']['dataBraden'] ?? [];
        $skor = 0;
        foreach ($this->bradenScaleOptions as $key => $opts) {
            if (!isset($data[$key])) {
                continue;
            }
            foreach ($opts as $opt) {
                if ((string) $opt['score'] === (string) $data[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryDekubitus['dekubitus']['bradenScore'] = $skor;
        $this->formEntryDekubitus['dekubitus']['kategoriResiko'] = $skor <= 12 ? 'Sangat Tinggi' : ($skor <= 14 ? 'Tinggi' : ($skor <= 18 ? 'Sedang' : 'Rendah'));
    }

    #[On('save-rm-penilaian-dekubitus-ri')]
    public function addAssessmentDekubitus(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryDekubitus['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryDekubitus['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-fill tanggal kalau Tidak & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryDekubitus['dekubitus']['dekubitus'] ?? '') !== 'Ya' && empty($this->formEntryDekubitus['tglPenilaian'])) {
            $this->setTglPenilaianDekubitus();
        }

        $this->validateWithToast(
            [
                'formEntryDekubitus.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                'formEntryDekubitus.dekubitus.dekubitus' => 'required|in:Ya,Tidak',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'in' => ':attribute harus salah satu dari: :values.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryDekubitus.tglPenilaian' => 'Tanggal Penilaian',
                'formEntryDekubitus.dekubitus.dekubitus' => 'Status Dekubitus',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['dekubitus'][] = $this->formEntryDekubitus;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Penilaian Dekubitus — ' . ($this->formEntryDekubitus['tglPenilaian'] ?? '-'), 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryDekubitus']);
            $this->afterSave('Penilaian Dekubitus berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentDekubitus(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $tglHapus = $fresh['penilaian']['dekubitus'][$index]['tglPenilaian'] ?? '-';
                array_splice($fresh['penilaian']['dekubitus'], $index, 1);
                $fresh['penilaian']['dekubitus'] = array_values($fresh['penilaian']['dekubitus']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Penilaian Dekubitus — entri ' . $tglHapus, 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Dekubitus dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-dekubitus-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'dekubitus');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryDekubitus']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-dekubitus-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <div class="space-y-4">

                <div @class([
                    'grid gap-4',
                    'grid-cols-2' => ($formEntryDekubitus['dekubitus']['dekubitus'] ?? 'Tidak') === 'Ya',
                    'grid-cols-1' => ($formEntryDekubitus['dekubitus']['dekubitus'] ?? 'Tidak') !== 'Ya',
                ])>
                    <div>
                        <x-input-label value="Status Dekubitus (Skala Braden) *" />
                        <x-select-input wire:model.live="formEntryDekubitus.dekubitus.dekubitus" class="w-full mt-1">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                    </div>

                    @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryDekubitus.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                    :error="$errors->has('formEntryDekubitus.tglPenilaian')" class="w-full" />
                                <x-now-button wire:click="setTglPenilaianDekubitus" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryDekubitus.tglPenilaian')" class="mt-1" />
                        </div>
                    @endif
                </div>

                @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')
                    <x-border-form title="Penilaian Skala Braden" align="start" bgcolor="bg-canvas">
                        <div class="mt-3 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                    Skor: {{ $formEntryDekubitus['dekubitus']['bradenScore'] ?? 0 }}
                                </span>
                                @if ($formEntryDekubitus['dekubitus']['kategoriResiko'] ?? '')
                                    @php $katForm = $formEntryDekubitus['dekubitus']['kategoriResiko']; @endphp
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ in_array($katForm, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($katForm === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $katForm }}
                                    </span>
                                @endif
                                <span class="text-xs text-muted-soft">≤12 Sangat Tinggi | 13–14 Tinggi | 15–18 Sedang |
                                    ≥19 Rendah</span>
                            </div>
                            @foreach ($bradenScaleOptions as $key => $options)
                                <div>
                                    <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                    <x-select-input
                                        wire:model.live="formEntryDekubitus.dekubitus.dataBraden.{{ $key }}"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach ($options as $opt)
                                            <option value="{{ $opt['score'] }}">
                                                {{ $opt['description'] }} (Skor: {{ $opt['score'] }})
                                            </option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            @endforeach
                        </div>
                    </x-border-form>

                    <div>
                        <x-input-label value="Rekomendasi" />
                        <x-textarea wire:model="formEntryDekubitus.dekubitus.rekomendasi" class="w-full mt-1"
                            rows="2" />
                    </div>
                @endif

        </div>
    @endif

    @if (!empty($dataDaftarRi['penilaian']['dekubitus']))
        <x-border-form title="Riwayat Penilaian Dekubitus" align="start" bgcolor="bg-canvas">
            <div class="mt-3 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-xs text-left text-muted dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl Penilaian</th>
                            <th class="px-3 py-2">Petugas</th>
                            <th class="px-3 py-2">Dekubitus</th>
                            <th class="px-3 py-2">Skor Braden</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Rekomendasi</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarRi['penilaian']['dekubitus'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['dekubitus']['kategoriResiko'] ?? '-';
                                $rowBg = in_array($kat, ['Sangat Tinggi', 'Tinggi'])
                                    ? 'bg-red-50 hover:bg-red-100'
                                    : ($kat === 'Sedang'
                                        ? 'bg-yellow-50 hover:bg-yellow-100'
                                        : ($kat === 'Rendah'
                                            ? 'bg-green-50 hover:bg-green-100'
                                            : 'hover:bg-surface-soft'));
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ ($row['dekubitus']['dekubitus'] ?? '') === 'Ya' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['dekubitus']['dekubitus'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-bold">{{ $row['dekubitus']['bradenScore'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ in_array($kat, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-muted">{{ $row['dekubitus']['rekomendasi'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentDekubitus({{ $i }})"
                                            wire:confirm="Hapus data dekubitus ini?" wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-muted-soft py-6">Belum ada data penilaian dekubitus.</p>
    @endif
</div>
