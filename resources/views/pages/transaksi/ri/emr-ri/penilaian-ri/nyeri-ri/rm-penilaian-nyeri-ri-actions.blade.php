<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/nyeri-ri/rm-penilaian-nyeri-ri-actions.blade.php

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
    protected array $renderAreas = ['modal-penilaian-nyeri-ri'];

    public array $formEntryNyeri = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'nyeri' => [
            'nyeri' => 'Tidak',
            'nyeriMetode' => ['nyeriMetode' => '', 'nyeriMetodeScore' => 0, 'dataNyeri' => []],
            'nyeriKet' => '',
            'pencetus' => '',
            'durasi' => '',
            'lokasi' => '',
            'waktuNyeri' => '',
            'tingkatKesadaran' => '',
            'tingkatAktivitas' => '',
            'sistolik' => '',
            'distolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'ketIntervensiFarmakologi' => '',
            'ketIntervensiNonFarmakologi' => '',
            'catatanTambahan' => '',
        ],
    ];

    public array $nyeriMetodeOptions = [['nyeriMetode' => 'NRS'], ['nyeriMetode' => 'BPS'], ['nyeriMetode' => 'NIPS'], ['nyeriMetode' => 'FLACC'], ['nyeriMetode' => 'VAS']];

    public array $vasOptions = [['vas' => '0', 'active' => true], ['vas' => '1', 'active' => false], ['vas' => '2', 'active' => false], ['vas' => '3', 'active' => false], ['vas' => '4', 'active' => false], ['vas' => '5', 'active' => false], ['vas' => '6', 'active' => false], ['vas' => '7', 'active' => false], ['vas' => '8', 'active' => false], ['vas' => '9', 'active' => false], ['vas' => '10', 'active' => false]];

    public array $flaccOptions = [
        'face' => [['score' => 0, 'description' => 'Ekspresi wajah netral atau tersenyum', 'active' => false], ['score' => 1, 'description' => 'Ekspresi wajah sedikit cemberut, menarik diri', 'active' => false], ['score' => 2, 'description' => 'Ekspresi wajah meringis, rahang mengatup rapat', 'active' => false]],
        'legs' => [['score' => 0, 'description' => 'Posisi normal atau relaks', 'active' => false], ['score' => 1, 'description' => 'Gelisah, tegang, atau menarik kaki', 'active' => false], ['score' => 2, 'description' => 'Menendang, atau kaki ditarik ke arah tubuh', 'active' => false]],
        'activity' => [['score' => 0, 'description' => 'Berbaring tenang, posisi normal, bergerak dengan mudah', 'active' => false], ['score' => 1, 'description' => 'Menggeliat, bergerak bolak-balik, tegang', 'active' => false], ['score' => 2, 'description' => 'Melengkungkan tubuh, kaku, atau menggeliat hebat', 'active' => false]],
        'cry' => [['score' => 0, 'description' => 'Tidak menangis (tertidur atau terjaga)', 'active' => false], ['score' => 1, 'description' => 'Merintih atau mengerang, sesekali menangis', 'active' => false], ['score' => 2, 'description' => 'Menangis terus-menerus, berteriak, atau merintih', 'active' => false]],
        'consolability' => [['score' => 0, 'description' => 'Tenang, tidak perlu ditenangkan', 'active' => false], ['score' => 1, 'description' => 'Dapat ditenangkan dengan sentuhan atau pelukan', 'active' => false], ['score' => 2, 'description' => 'Sulit ditenangkan, terus menangis atau merintih', 'active' => false]],
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-nyeri-ri']);
    }

    #[On('open-rm-penilaian-nyeri-ri')]
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
        $this->dataDaftarRi['penilaian']['nyeri'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-penilaian-nyeri-ri');
    }

    public function setTglPenilaianNyeri(): void
    {
        $this->formEntryNyeri['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function updatedFormEntryNyeriNyeriNyeriMetodeNyeriMetode(string $value): void
    {
        $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = match ($value) {
            'VAS' => $this->vasOptions,
            'FLACC' => $this->flaccOptions,
            default => [],
        };
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = 0;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = 'Tidak Nyeri';
    }

    public function updateVasNyeriScore(int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as &$opt) {
            $opt['active'] = $opt['vas'] == $score;
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $score;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $score === 0 ? 'Tidak Nyeri' : ($score <= 3 ? 'Nyeri Ringan' : ($score <= 6 ? 'Nyeri Sedang' : 'Nyeri Berat'));
    }

    public function updateFlaccScore(string $category, int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'][$category] as &$item) {
            $item['active'] = $item['score'] === $score;
        }
        $total = 0;
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $items) {
            foreach ($items as $item) {
                if ($item['active']) {
                    $total += $item['score'];
                    break;
                }
            }
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $total;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $total === 0 ? 'Santai dan nyaman' : ($total <= 3 ? 'Ketidaknyamanan ringan' : ($total <= 6 ? 'Nyeri sedang' : 'Nyeri berat'));
    }

    #[On('save-rm-penilaian-nyeri-ri')]
    public function addAssessmentNyeri(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryNyeri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-fill tanggal kalau Tidak nyeri & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryNyeri['nyeri']['nyeri'] ?? '') !== 'Ya' && empty($this->formEntryNyeri['tglPenilaian'])) {
            $this->setTglPenilaianNyeri();
        }

        $this->validateWithToast(
            [
                'formEntryNyeri.nyeri.nyeri' => 'required|in:Ya,Tidak',
                'formEntryNyeri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                'formEntryNyeri.nyeri.sistolik' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:300',
                'formEntryNyeri.nyeri.distolik' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:200',
                'formEntryNyeri.nyeri.frekuensiNafas' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:100',
                'formEntryNyeri.nyeri.frekuensiNadi' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:200',
                'formEntryNyeri.nyeri.suhu' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:30|max:45',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'required_if' => ':attribute wajib diisi saat Status Nyeri = Ya.',
                'in' => ':attribute harus salah satu dari: :values.',
                'numeric' => ':attribute harus berupa angka.',
                'min' => ':attribute minimal :min.',
                'max' => ':attribute maksimal :max.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryNyeri.nyeri.nyeri' => 'Status Nyeri',
                'formEntryNyeri.tglPenilaian' => 'Tanggal Penilaian',
                'formEntryNyeri.nyeri.sistolik' => 'Sistolik',
                'formEntryNyeri.nyeri.distolik' => 'Diastolik',
                'formEntryNyeri.nyeri.frekuensiNafas' => 'Frekuensi Nafas',
                'formEntryNyeri.nyeri.frekuensiNadi' => 'Frekuensi Nadi',
                'formEntryNyeri.nyeri.suhu' => 'Suhu',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['nyeri'][] = $this->formEntryNyeri;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryNyeri']);
            $this->afterSave('Penilaian Nyeri berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['nyeri'], $index, 1);
                $fresh['penilaian']['nyeri'] = array_values($fresh['penilaian']['nyeri']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Penilaian Nyeri dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-nyeri-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'nyeri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryNyeri']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-nyeri-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form title="Form Penilaian Nyeri" align="start" bgcolor="bg-gray-50">
            <div class="mt-4 space-y-4">

                <div @class([
                    'grid gap-4',
                    'grid-cols-3' => ($formEntryNyeri['nyeri']['nyeri'] ?? 'Tidak') === 'Ya',
                    'grid-cols-1' => ($formEntryNyeri['nyeri']['nyeri'] ?? 'Tidak') !== 'Ya',
                ])>
                    <div>
                        <x-input-label value="Status Nyeri *" />
                        <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeri" class="w-full mt-1">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                    </div>

                    @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryNyeri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                    :error="$errors->has('formEntryNyeri.tglPenilaian')" class="w-full" />
                                <x-secondary-button wire:click="setTglPenilaianNyeri" type="button"
                                    class="whitespace-nowrap text-xs">Sekarang</x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryNyeri.tglPenilaian')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Metode Penilaian *" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetode"
                                class="w-full mt-1">
                                <option value="">-- Pilih Metode --</option>
                                @foreach ($nyeriMetodeOptions as $opt)
                                    <option value="{{ $opt['nyeriMetode'] }}">{{ $opt['nyeriMetode'] }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                    @endif
                </div>

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'])
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 text-xs font-bold text-white rounded-lg bg-brand">
                                Skor: {{ $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] }}
                            </span>
                            @if ($formEntryNyeri['nyeri']['nyeriKet'])
                                @php $ket = $formEntryNyeri['nyeri']['nyeriKet']; @endphp
                                <span
                                    class="px-2 py-0.5 text-xs font-bold rounded-full
                                    {{ str_contains(strtolower($ket), 'berat')
                                        ? 'bg-red-100 text-red-700'
                                        : (str_contains(strtolower($ket), 'sedang')
                                            ? 'bg-yellow-100 text-yellow-700'
                                            : (str_contains(strtolower($ket), 'ringan')
                                                ? 'bg-orange-100 text-orange-700'
                                                : 'bg-green-100 text-green-700')) }}">
                                    {{ $ket }}
                                </span>
                            @endif
                        </div>
                    @endif

                    {{-- NRS --}}
                    @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'NRS')
                        <x-border-form title="Numeric Rating Scale (NRS)" align="start" bgcolor="bg-white">
                            <div class="mt-3">
                                <p class="text-xs text-gray-400 mb-2">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6
                                    Sedang | 7–10 Berat</p>
                                <x-input-label value="Skor NRS (0–10) *" />
                                <x-text-input type="number" min="0" max="10"
                                    wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                    class="w-32 mt-1" />
                            </div>
                        </x-border-form>
                    @endif

                    {{-- VAS --}}
                    @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'VAS')
                        <x-border-form title="Visual Analog Scale (VAS)" align="start" bgcolor="bg-white">
                            <div class="mt-3">
                                <p class="text-xs text-gray-400 mb-2">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6
                                    Sedang | 7–10 Berat</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $opt)
                                        <button type="button" wire:click="updateVasNyeriScore({{ $opt['vas'] }})"
                                            class="w-10 h-10 text-xs font-bold rounded-lg border-2 transition
                                                {{ $opt['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-brand hover:text-brand' }}">
                                            {{ $opt['vas'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </x-border-form>
                    @endif

                    {{-- FLACC --}}
                    @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'FLACC')
                        <x-border-form title="FLACC Scale" align="start" bgcolor="bg-white">
                            <div class="mt-3 space-y-3">
                                <p class="text-xs text-gray-400">Interpretasi: 0 Santai | 1–3 Ringan | 4–6 Sedang | 7–10
                                    Berat</p>
                                @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $category => $items)
                                    <div>
                                        <x-input-label :value="ucwords($category)" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($items as $item)
                                                <button type="button"
                                                    wire:click="updateFlaccScore('{{ $category }}', {{ $item['score'] }})"
                                                    class="px-3 py-1.5 text-xs rounded-lg border-2 transition
                                                        {{ $item['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-brand hover:text-brand' }}">
                                                    <span class="font-bold">{{ $item['score'] }}</span> —
                                                    {{ $item['description'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-border-form>
                    @endif

                    {{-- BPS / NIPS --}}
                    @if (in_array($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'], ['BPS', 'NIPS']))
                        <x-border-form :title="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']" align="start" bgcolor="bg-white">
                            <div class="mt-3">
                                <x-input-label value="Skor *" />
                                <x-text-input type="number" min="0"
                                    wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                    class="w-32 mt-1" />
                            </div>
                        </x-border-form>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                    {{-- Detail Nyeri --}}
                    <x-border-form title="Detail Nyeri" align="start" bgcolor="bg-white">
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label value="Pencetus" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.pencetus" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Durasi" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.durasi" placeholder="30 menit"
                                    class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Lokasi" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Waktu Nyeri" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.waktuNyeri" placeholder="Malam hari"
                                    class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tingkat Kesadaran" />
                                <x-select-input wire:model="formEntryNyeri.nyeri.tingkatKesadaran" class="w-full mt-1">
                                    <option value="">-- Pilih --</option>
                                    @foreach (['Composmentis', 'Apatis', 'Somnolen', 'Stupor', 'Koma'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label value="Tingkat Aktivitas" />
                                <x-select-input wire:model="formEntryNyeri.nyeri.tingkatAktivitas" class="w-full mt-1">
                                    <option value="">-- Pilih --</option>
                                    @foreach (['Mandiri', 'Dibantu Sebagian', 'Dibantu Penuh'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                    </x-border-form>

                    {{-- TTV --}}
                    <x-border-form title="Tanda-Tanda Vital" align="start" bgcolor="bg-white">
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            @foreach ([['key' => 'sistolik', 'label' => 'Sistolik (mmHg)'], ['key' => 'distolik', 'label' => 'Diastolik (mmHg)'], ['key' => 'frekuensiNafas', 'label' => 'Frek. Nafas (x/mnt)'], ['key' => 'frekuensiNadi', 'label' => 'Frek. Nadi (x/mnt)'], ['key' => 'suhu', 'label' => 'Suhu (°C)']] as $f)
                                <div>
                                    <x-input-label value="{{ $f['label'] }} *" />
                                    <x-text-input type="number" step="any"
                                        wire:model="formEntryNyeri.nyeri.{{ $f['key'] }}" :error="$errors->has('formEntryNyeri.nyeri.' . $f['key'])"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.' . $f['key'])" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </x-border-form>
                    </div>

                    {{-- Intervensi --}}
                    <x-border-form title="Intervensi & Catatan" align="start" bgcolor="bg-white">
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label value="Intervensi Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi"
                                    class="w-full mt-1" rows="2" placeholder="Nama obat, dosis, rute..." />
                            </div>
                            <div>
                                <x-input-label value="Intervensi Non-Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiNonFarmakologi"
                                    class="w-full mt-1" rows="2"
                                    placeholder="Kompres, relaksasi, distraksi..." />
                            </div>
                            <div>
                                <x-input-label value="Catatan Tambahan" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.catatanTambahan" class="w-full mt-1"
                                    rows="2" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarRi['penilaian']['nyeri']))
        <x-border-form title="Riwayat Penilaian Nyeri" align="start" bgcolor="bg-white">
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl / Petugas</th>
                            <th class="px-3 py-2">Nyeri</th>
                            <th class="px-3 py-2">Metode / Skor</th>
                            <th class="px-3 py-2">Tanda Vital</th>
                            <th class="px-3 py-2">Detail Nyeri</th>
                            <th class="px-3 py-2">Kondisi</th>
                            <th class="px-3 py-2">Intervensi & Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarRi['penilaian']['nyeri'] ?? [], true) as $i => $row)
                            @php
                                $ket = $row['nyeri']['nyeriKet'] ?? '-';
                                $rowBg = str_contains(strtolower($ket), 'berat')
                                    ? 'bg-red-50 hover:bg-red-100'
                                    : (str_contains(strtolower($ket), 'sedang')
                                        ? 'bg-yellow-50 hover:bg-yellow-100'
                                        : (str_contains(strtolower($ket), 'ringan')
                                            ? 'bg-orange-50 hover:bg-orange-100'
                                            : 'bg-green-50 hover:bg-green-100'));
                            @endphp
                            <tr class="{{ $rowBg }}">

                                {{-- Tgl / Petugas --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $row['tglPenilaian'] ?? '-' }}</div>
                                    <div class="text-gray-400">{{ $row['petugasPenilai'] ?? '-' }}</div>
                                </td>

                                {{-- Status Nyeri --}}
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ ($row['nyeri']['nyeri'] ?? '') == 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['nyeri']['nyeri'] ?? '-' }}
                                    </span>
                                </td>

                                {{-- Metode / Skor / Keterangan --}}
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $row['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }}
                                    </div>
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span
                                            class="font-bold">{{ $row['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }}</span>
                                        @if ($ket !== '-')
                                            <span
                                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                {{ str_contains(strtolower($ket), 'berat')
                                    ? 'bg-red-100 text-red-700'
                                    : (str_contains(strtolower($ket), 'sedang')
                                        ? 'bg-yellow-100 text-yellow-700'
                                        : (str_contains(strtolower($ket), 'ringan')
                                            ? 'bg-orange-100 text-orange-700'
                                            : 'bg-green-100 text-green-700')) }}">
                                                {{ $ket }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Tanda Vital --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div>TD: <span
                                            class="font-medium">{{ ($row['nyeri']['sistolik'] ?? '-') . '/' . ($row['nyeri']['distolik'] ?? '-') }}</span>
                                    </div>
                                    <div>Nadi: <span
                                            class="font-medium">{{ $row['nyeri']['frekuensiNadi'] ?? '-' }}</span>
                                    </div>
                                    <div>Nafas: <span
                                            class="font-medium">{{ $row['nyeri']['frekuensiNafas'] ?? '-' }}</span>
                                    </div>
                                    <div>Suhu: <span class="font-medium">{{ $row['nyeri']['suhu'] ?? '-' }}</span>
                                    </div>
                                </td>

                                {{-- Detail Nyeri --}}
                                <td class="px-3 py-2">
                                    @if ($row['nyeri']['pencetus'] ?? '')
                                        <div>Pencetus: <span
                                                class="font-medium">{{ $row['nyeri']['pencetus'] }}</span></div>
                                    @endif
                                    @if ($row['nyeri']['lokasi'] ?? '')
                                        <div>Lokasi: <span class="font-medium">{{ $row['nyeri']['lokasi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['durasi'] ?? '')
                                        <div>Durasi: <span class="font-medium">{{ $row['nyeri']['durasi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['waktuNyeri'] ?? '')
                                        <div>Waktu: <span class="font-medium">{{ $row['nyeri']['waktuNyeri'] }}</span>
                                        </div>
                                    @endif
                                    @if (
                                        !($row['nyeri']['pencetus'] ?? '') &&
                                            !($row['nyeri']['lokasi'] ?? '') &&
                                            !($row['nyeri']['durasi'] ?? '') &&
                                            !($row['nyeri']['waktuNyeri'] ?? ''))
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                {{-- Kondisi --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($row['nyeri']['tingkatKesadaran'] ?? '')
                                        <div>Kesadaran: <span
                                                class="font-medium">{{ $row['nyeri']['tingkatKesadaran'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['tingkatAktivitas'] ?? '')
                                        <div>Aktivitas: <span
                                                class="font-medium">{{ $row['nyeri']['tingkatAktivitas'] }}</span>
                                        </div>
                                    @endif
                                    @if (!($row['nyeri']['tingkatKesadaran'] ?? '') && !($row['nyeri']['tingkatAktivitas'] ?? ''))
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                {{-- Intervensi & Catatan --}}
                                <td class="px-3 py-2 max-w-[200px]">
                                    @if ($row['nyeri']['ketIntervensiFarmakologi'] ?? '')
                                        <div class="truncate">Farmako: <span
                                                class="font-medium">{{ $row['nyeri']['ketIntervensiFarmakologi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['ketIntervensiNonFarmakologi'] ?? '')
                                        <div class="truncate">Non-Farmako: <span
                                                class="font-medium">{{ $row['nyeri']['ketIntervensiNonFarmakologi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['catatanTambahan'] ?? '')
                                        <div class="truncate text-gray-400">{{ $row['nyeri']['catatanTambahan'] }}
                                        </div>
                                    @endif
                                    @if (
                                        !($row['nyeri']['ketIntervensiFarmakologi'] ?? '') &&
                                            !($row['nyeri']['ketIntervensiNonFarmakologi'] ?? '') &&
                                            !($row['nyeri']['catatanTambahan'] ?? ''))
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-icon-button variant="danger"
                                            wire:click="removeAssessmentNyeri({{ $i }})"
                                            wire:confirm="Hapus data nyeri ini?">
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
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian nyeri.</p>
    @endif
</div>
