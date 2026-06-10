<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/gizi-ri/rm-penilaian-gizi-ri-actions.blade.php

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
    protected array $renderAreas = ['modal-penilaian-gizi-ri'];

    public array $formEntryGizi = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'gizi' => [
            'beratBadan' => '',
            'tinggiBadan' => '',
            'imt' => '',
            'kebutuhanGizi' => '',
            'skorSkrining' => 0,
            'kategoriGizi' => '',
            'skriningGizi' => [],
            'catatan' => '',
        ],
    ];

    public array $skriningGiziAwalOptions = [
        'perubahanBeratBadan' => [['perubahan' => 'Tidak ada perubahan', 'score' => 0], ['perubahan' => 'Turun 5-10%', 'score' => 1], ['perubahan' => 'Turun >10%', 'score' => 2]],
        'asupanMakanan' => [['asupan' => 'Cukup', 'score' => 0], ['asupan' => 'Kurang', 'score' => 1], ['asupan' => 'Sangat kurang', 'score' => 2]],
        'penyakit' => [['penyakit' => 'Tidak ada', 'score' => 0], ['penyakit' => 'Ringan', 'score' => 1], ['penyakit' => 'Berat', 'score' => 2]],
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-gizi-ri']);
    }

    #[On('open-rm-penilaian-gizi-ri')]
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
        $this->dataDaftarRi['penilaian']['gizi'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-penilaian-gizi-ri');
    }

    public function setTglPenilaianGizi(): void
    {
        $this->formEntryGizi['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function updatedFormEntryGiziGiziBeratBadan(): void
    {
        $this->hitungImt();
    }
    public function updatedFormEntryGiziGiziTinggiBadan(): void
    {
        $this->hitungImt();
    }

    private function hitungImt(): void
    {
        $bb = (float) ($this->formEntryGizi['gizi']['beratBadan'] ?? 0);
        $tb = (float) ($this->formEntryGizi['gizi']['tinggiBadan'] ?? 0);
        if ($bb > 0 && $tb > 0) {
            $this->formEntryGizi['gizi']['imt'] = round($bb / pow($tb / 100, 2), 2);
        }
    }

    public function updatedFormEntryGiziGiziSkriningGizi(): void
    {
        $this->hitungSkorGizi();
    }

    private function hitungSkorGizi(): void
    {
        $selected = $this->formEntryGizi['gizi']['skriningGizi'] ?? [];
        $fieldKeys = ['perubahanBeratBadan' => 'perubahan', 'asupanMakanan' => 'asupan', 'penyakit' => 'penyakit'];
        $skor = 0;
        foreach ($this->skriningGiziAwalOptions as $key => $opts) {
            if (!isset($selected[$key])) {
                continue;
            }
            $fk = $fieldKeys[$key] ?? $key;
            foreach ($opts as $opt) {
                if (($opt[$fk] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryGizi['gizi']['skorSkrining'] = $skor;
        $this->formEntryGizi['gizi']['kategoriGizi'] = $skor >= 2 ? 'Berisiko Malnutrisi' : 'Normal';
    }

    #[On('save-rm-penilaian-gizi-ri')]
    public function addAssessmentGizi(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryGizi['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryGizi['petugasPenilaiCode'] = auth()->user()->myuser_code;

        $this->validateWithToast(
            [
                'formEntryGizi.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                'formEntryGizi.gizi.beratBadan' => 'required|numeric|min:1',
                'formEntryGizi.gizi.tinggiBadan' => 'required|numeric|min:1',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'numeric' => ':attribute harus berupa angka.',
                'min' => ':attribute minimal :min.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryGizi.tglPenilaian' => 'Tanggal Penilaian',
                'formEntryGizi.gizi.beratBadan' => 'Berat Badan',
                'formEntryGizi.gizi.tinggiBadan' => 'Tinggi Badan',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['gizi'][] = $this->formEntryGizi;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Penilaian Gizi — ' . ($this->formEntryGizi['tglPenilaian'] ?? '-'), 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryGizi']);
            $this->afterSave('Penilaian Gizi berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentGizi(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $tglHapus = $fresh['penilaian']['gizi'][$index]['tglPenilaian'] ?? '-';
                array_splice($fresh['penilaian']['gizi'], $index, 1);
                $fresh['penilaian']['gizi'] = array_values($fresh['penilaian']['gizi']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Penilaian Gizi — entri ' . $tglHapus, 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Gizi dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-gizi-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'gizi');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryGizi']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-gizi-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form title="Form Penilaian Gizi" align="start" bgcolor="bg-surface-soft">
            <div class="mt-4 space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Penilaian *" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryGizi.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryGizi.tglPenilaian')" class="w-full" />
                            <x-now-button wire:click="setTglPenilaianGizi" />
                        </div>
                    </div>
                    <div>
                        <x-input-label value="Kebutuhan Gizi" />
                        <x-text-input wire:model="formEntryGizi.gizi.kebutuhanGizi" placeholder="1800 kkal/hari"
                            class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Berat Badan (kg) *" />
                        <x-text-input type="number" step="0.1" wire:model.live="formEntryGizi.gizi.beratBadan"
                            :error="$errors->has('formEntryGizi.gizi.beratBadan')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formEntryGizi.gizi.beratBadan')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tinggi Badan (cm) *" />
                        <x-text-input type="number" step="0.1" wire:model.live="formEntryGizi.gizi.tinggiBadan"
                            :error="$errors->has('formEntryGizi.gizi.tinggiBadan')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formEntryGizi.gizi.tinggiBadan')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="IMT (auto)" />
                        <x-text-input wire:model="formEntryGizi.gizi.imt" readonly
                            class="w-full mt-1 bg-surface-soft cursor-not-allowed" />
                    </div>
                </div>

                <x-border-form title="Skrining Gizi Awal" align="start" bgcolor="bg-canvas">
                    <div class="mt-3 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                Skor: {{ $formEntryGizi['gizi']['skorSkrining'] ?? 0 }}
                            </span>
                            @if ($formEntryGizi['gizi']['kategoriGizi'] ?? '')
                                <span
                                    class="px-2 py-0.5 text-xs font-bold rounded-full
                                    {{ ($formEntryGizi['gizi']['kategoriGizi'] ?? '') == 'Berisiko Malnutrisi' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $formEntryGizi['gizi']['kategoriGizi'] }}
                                </span>
                            @endif
                            <span class="text-xs text-muted-soft">Skor ≥2 = Berisiko Malnutrisi</span>
                        </div>
                        @php $fieldKeys = ['perubahanBeratBadan' => 'perubahan', 'asupanMakanan' => 'asupan', 'penyakit' => 'penyakit']; @endphp
                        @foreach ($skriningGiziAwalOptions as $key => $options)
                            @php
                                $fk = $fieldKeys[$key] ?? $key;
                                $lb = match ($key) {
                                    'perubahanBeratBadan' => 'Perubahan Berat Badan',
                                    'asupanMakanan' => 'Asupan Makanan',
                                    'penyakit' => 'Kondisi Penyakit',
                                    default => ucwords($key),
                                };
                            @endphp
                            <div>
                                <x-input-label :value="$lb" />
                                <x-select-input wire:model.live="formEntryGizi.gizi.skriningGizi.{{ $key }}"
                                    class="w-full mt-1">
                                    <option value="">-- Pilih --</option>
                                    @foreach ($options as $opt)
                                        <option value="{{ $opt[$fk] }}">
                                            {{ $opt[$fk] }} (Skor: {{ $opt['score'] }})
                                        </option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        @endforeach
                    </div>
                </x-border-form>

                <div>
                    <x-input-label value="Catatan" />
                    <x-textarea wire:model="formEntryGizi.gizi.catatan" class="w-full mt-1" rows="2" />
                </div>

            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarRi['penilaian']['gizi']))
        <x-border-form title="Riwayat Penilaian Gizi" align="start" bgcolor="bg-canvas">
            <div class="mt-3 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-xs text-left text-muted dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl Penilaian</th>
                            <th class="px-3 py-2">Petugas</th>
                            <th class="px-3 py-2">BB (kg)</th>
                            <th class="px-3 py-2">TB (cm)</th>
                            <th class="px-3 py-2">IMT</th>
                            <th class="px-3 py-2">Skor</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarRi['penilaian']['gizi'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['gizi']['kategoriGizi'] ?? '-';
                                $rowBg =
                                    $kat === 'Berisiko Malnutrisi'
                                        ? 'bg-orange-50 hover:bg-orange-100'
                                        : ($kat === 'Normal'
                                            ? 'bg-green-50 hover:bg-green-100'
                                            : 'hover:bg-surface-soft');
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['gizi']['beratBadan'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['gizi']['tinggiBadan'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">{{ $row['gizi']['imt'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">{{ $row['gizi']['skorSkrining'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $kat === 'Berisiko Malnutrisi' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-muted max-w-xs truncate">
                                    {{ $row['gizi']['catatan'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentGizi({{ $i }})"
                                            wire:confirm="Hapus data gizi ini?" wire:loading.attr="disabled"
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
        <p class="text-xs text-center text-muted-soft py-6">Belum ada data penilaian gizi.</p>
    @endif
</div>
