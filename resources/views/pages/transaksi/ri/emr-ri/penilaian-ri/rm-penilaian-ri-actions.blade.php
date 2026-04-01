<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/rm-penilaian-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool    $isFormLocked = false;
    public ?string $riHdrNo      = null;
    public array   $dataDaftarRi = [];

    public array $formEntryNyeri = [
        'tglPenilaian'=>'','petugasPenilai'=>'','petugasPenilaiCode'=>'',
        'nyeri'=>[
            'nyeri'=>'Tidak','nyeriMetode'=>['nyeriMetode'=>'','nyeriMetodeScore'=>0,'dataNyeri'=>[]],
            'nyeriKet'=>'','pencetus'=>'','durasi'=>'','lokasi'=>'',
            'waktuNyeri'=>'','sistolik'=>'','distolik'=>'',
            'frekuensiNafas'=>'','frekuensiNadi'=>'','suhu'=>'',
            'ketIntervensiFarmakologi'=>'','ketIntervensiNonFarmakologi'=>'','catatanTambahan'=>'',
        ],
    ];

    public array $formEntryResikoJatuh = [
        'tglPenilaian'=>'','petugasPenilai'=>'','petugasPenilaiCode'=>'',
        'resikoJatuh'=>['resikoJatuh'=>'Tidak','resikoJatuhMetode'=>['resikoJatuhMetode'=>'','resikoJatuhMetodeScore'=>0,'dataResikoJatuh'=>[]],'kategoriResiko'=>'','rekomendasi'=>''],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-ri'];

    public function mount(): void { $this->registerAreas(['modal-penilaian-ri']); }

    #[On('open-rm-penilaian-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
        $this->riHdrNo = $riHdrNo;
        $this->resetForm(); $this->resetValidation();
        $data = $this->findDataRI($riHdrNo);
        if (!$data) { $this->dispatch('toast', type:'error', message:'Data RI tidak ditemukan.'); return; }
        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['penilaian'] ??= ['nyeri'=>[],'resikoJatuh'=>[],'dekubitus'=>[],'gizi'=>[]];
        $this->incrementVersion('modal-penilaian-ri');
        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r'=>$riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    public function setTglPenilaianNyeri(): void { $this->formEntryNyeri['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'); }
    public function setTglPenilaianResikoJatuh(): void { $this->formEntryResikoJatuh['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'); }

    public function addAssessmentNyeri(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->formEntryNyeri['petugasPenilai']     = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryNyeri.tglPenilaian'       => 'required|date_format:d/m/Y H:i:s',
            'formEntryNyeri.nyeri.nyeri'         => 'required|in:Ya,Tidak',
            'formEntryNyeri.nyeri.sistolik'      => 'required|numeric',
            'formEntryNyeri.nyeri.distolik'      => 'required|numeric',
            'formEntryNyeri.nyeri.frekuensiNafas'=> 'required|numeric',
            'formEntryNyeri.nyeri.frekuensiNadi' => 'required|numeric',
            'formEntryNyeri.nyeri.suhu'          => 'required|numeric',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['nyeri'][] = $this->formEntryNyeri;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryNyeri']);
            $this->afterSave('Penilaian Nyeri berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['nyeri'], $index, 1);
                $fresh['penilaian']['nyeri'] = array_values($fresh['penilaian']['nyeri']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Penilaian Nyeri berhasil dihapus.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function addAssessmentResikoJatuh(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->formEntryResikoJatuh['petugasPenilai']     = auth()->user()->myuser_name;
        $this->formEntryResikoJatuh['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryResikoJatuh.tglPenilaian'                 => 'required|date_format:d/m/Y H:i:s',
            'formEntryResikoJatuh.resikoJatuh.resikoJatuh'      => 'required|in:Ya,Tidak',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['resikoJatuh'][] = $this->formEntryResikoJatuh;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryResikoJatuh']);
            $this->afterSave('Penilaian Risiko Jatuh berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function removeAssessmentResikoJatuh(int $index): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['resikoJatuh'], $index, 1);
                $fresh['penilaian']['resikoJatuh'] = array_values($fresh['penilaian']['resikoJatuh']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Risiko Jatuh berhasil dihapus.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-ri');
        $this->dispatch('toast', type:'success', message:$msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion(); $this->isFormLocked = false;
        $this->reset(['formEntryNyeri','formEntryResikoJatuh']);
    }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                $fn();
            }, 5);
        });
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-penilaian-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── PENILAIAN NYERI ── --}}
    <x-border-form title="Penilaian Nyeri" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <x-input-label value="Tanggal Penilaian *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryNyeri.tglPenilaian"
                            class="flex-1 font-mono text-sm" readonly
                            :error="$errors->has('formEntryNyeri.tglPenilaian')" />
                        <x-secondary-button wire:click="setTglPenilaianNyeri" type="button" class="text-xs">Sekarang</x-secondary-button>
                    </div>
                </div>
                <div>
                    <x-input-label value="Nyeri" />
                    <div class="mt-2 flex gap-3">
                        @foreach (['Ya','Tidak'] as $opt)
                            <x-radio-button
                                :label="$opt"
                                :value="$opt"
                                name="nyeriNyeri"
                                wire:model.live="formEntryNyeri.nyeri.nyeri"
                                :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                </div>
                @foreach ([['sistolik','Sistolik'],['distolik','Diastolik'],['frekuensiNafas','Nafas'],['frekuensiNadi','Nadi'],['suhu','Suhu']] as [$k,$l])
                <div>
                    <x-input-label value="{{ $l }}" />
                    <x-text-input wire:model="formEntryNyeri.nyeri.{{ $k }}" class="w-full mt-1" type="number" step="any"
                        :error="$errors->has('formEntryNyeri.nyeri.'.$k)" />
                </div>
                @endforeach
                <div>
                    <x-input-label value="Lokasi Nyeri" />
                    <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1" placeholder="Lokasi..." />
                </div>
                <div>
                    <x-input-label value="Intervensi Farmakologi" />
                    <x-text-input wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi" class="w-full mt-1" />
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="addAssessmentNyeri" type="button">+ Tambah Penilaian Nyeri</x-primary-button>
            </div>
            @endif

            {{-- List Nyeri --}}
            @forelse ($dataDaftarRi['penilaian']['nyeri'] ?? [] as $idx => $nyeri)
            <div wire:key="nyeri-{{ $idx }}" class="border border-gray-200 rounded-lg p-3 bg-white dark:bg-gray-800 text-xs space-y-1">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-semibold">{{ $nyeri['petugasPenilai']??'-' }}</span>
                        <span class="ml-2 font-mono text-gray-500">{{ $nyeri['tglPenilaian']??'-' }}</span>
                    </div>
                    @if (!$isFormLocked)
                    <x-icon-button variant="danger" wire:click="removeAssessmentNyeri({{ $idx }})"
                        wire:confirm="Hapus penilaian nyeri ini?" tooltip="Hapus">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </x-icon-button>
                    @endif
                </div>
                <p>Nyeri: <x-badge variant="{{ ($nyeri['nyeri']['nyeri']??'Tidak')==='Ya'?'danger':'success' }}">{{ $nyeri['nyeri']['nyeri']??'-' }}</x-badge>
                   | TD: {{ ($nyeri['nyeri']['sistolik']??'-').'/'.($nyeri['nyeri']['distolik']??'-') }}
                   | Nadi: {{ $nyeri['nyeri']['frekuensiNadi']??'-' }}
                   | Nafas: {{ $nyeri['nyeri']['frekuensiNafas']??'-' }}
                   | Suhu: {{ $nyeri['nyeri']['suhu']??'-' }}
                </p>
                @if (!empty($nyeri['nyeri']['lokasi']))
                    <p>Lokasi: {{ $nyeri['nyeri']['lokasi'] }}</p>
                @endif
            </div>
            @empty
                <p class="text-xs text-center text-gray-400 py-3">Belum ada penilaian nyeri.</p>
            @endforelse
        </div>
    </x-border-form>

    {{-- ── RISIKO JATUH ── --}}
    <x-border-form title="Penilaian Risiko Jatuh" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <x-input-label value="Tanggal Penilaian *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryResikoJatuh.tglPenilaian"
                            class="flex-1 font-mono text-sm" readonly />
                        <x-secondary-button wire:click="setTglPenilaianResikoJatuh" type="button" class="text-xs">Sekarang</x-secondary-button>
                    </div>
                </div>
                <div>
                    <x-input-label value="Risiko Jatuh" />
                    <div class="mt-2 flex gap-3">
                        @foreach (['Ya','Tidak'] as $opt)
                            <x-radio-button
                                :label="$opt"
                                :value="$opt"
                                name="resikoJatuh"
                                wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuh"
                                :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                </div>
                @if (($formEntryResikoJatuh['resikoJatuh']['resikoJatuh']??'Tidak') === 'Ya')
                <div>
                    <x-input-label value="Metode Penilaian" />
                    <x-select-input wire:model="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode" class="w-full mt-1">
                        <option value="">— Pilih —</option>
                        <option value="Skala Morse">Skala Morse</option>
                        <option value="Humpty Dumpty">Humpty Dumpty (Pediatrik)</option>
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Skor" />
                    <x-text-input wire:model="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetodeScore"
                        class="w-full mt-1" type="number" />
                </div>
                <div>
                    <x-input-label value="Kategori Risiko" />
                    <x-select-input wire:model="formEntryResikoJatuh.resikoJatuh.kategoriResiko" class="w-full mt-1">
                        <option value="">— Pilih —</option>
                        @foreach (['Rendah','Sedang','Tinggi'] as $kat)
                        <option value="{{ $kat }}">{{ $kat }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div class="col-span-3">
                    <x-input-label value="Rekomendasi" />
                    <x-textarea wire:model="formEntryResikoJatuh.resikoJatuh.rekomendasi" class="w-full mt-1" rows="2" />
                </div>
                @endif
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="addAssessmentResikoJatuh" type="button">+ Tambah Risiko Jatuh</x-primary-button>
            </div>
            @endif

            @forelse ($dataDaftarRi['penilaian']['resikoJatuh'] ?? [] as $idx => $rj)
            <div wire:key="rj-{{ $idx }}" class="border border-gray-200 rounded-lg p-3 bg-white dark:bg-gray-800 text-xs space-y-1">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-semibold">{{ $rj['petugasPenilai']??'-' }}</span>
                        <span class="ml-2 font-mono text-gray-500">{{ $rj['tglPenilaian']??'-' }}</span>
                    </div>
                    @if (!$isFormLocked)
                    <x-icon-button variant="danger" wire:click="removeAssessmentResikoJatuh({{ $idx }})"
                        wire:confirm="Hapus penilaian risiko jatuh ini?" tooltip="Hapus">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </x-icon-button>
                    @endif
                </div>
                <p>Risiko Jatuh: <x-badge variant="{{ ($rj['resikoJatuh']['resikoJatuh']??'Tidak')==='Ya'?'danger':'success' }}">{{ $rj['resikoJatuh']['resikoJatuh']??'-' }}</x-badge>
                   | Kategori: <strong>{{ $rj['resikoJatuh']['kategoriResiko']??'-' }}</strong>
                   | Skor: {{ $rj['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore']??0 }}
                </p>
            </div>
            @empty
                <p class="text-xs text-center text-gray-400 py-3">Belum ada penilaian risiko jatuh.</p>
            @endforelse
        </div>
    </x-border-form>

</div>
