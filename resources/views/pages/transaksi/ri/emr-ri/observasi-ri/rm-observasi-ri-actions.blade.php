<?php
// resources/views/pages/transaksi/ri/emr-ri/observasi/rm-observasi-ri-actions.blade.php

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

    public array $formEntryObservasi = [
        'cairan'=>'','tetesan'=>'','sistolik'=>'','distolik'=>'',
        'frekuensiNafas'=>'','frekuensiNadi'=>'','suhu'=>'','spo2'=>'',
        'gda'=>'','gcs'=>'','waktuPemeriksaan'=>'','pemeriksa'=>'',
    ];

    public array $formEntryOksigen = [
        'jenisAlatOksigen'=>'Nasal Kanul','jenisAlatOksigenDetail'=>'',
        'dosisOksigen'=>'1-2 L/menit','dosisOksigenDetail'=>'',
        'modelPenggunaan'=>'Kontinu','durasiPenggunaan'=>'',
        'tanggalWaktuMulai'=>'','tanggalWaktuSelesai'=>'',
    ];

    public array $formEntryPengeluaran = [
        'waktuPengeluaran'=>'','jenisOutput'=>'','volume'=>'',
        'warnaKarakteristik'=>'','keterangan'=>'','pemeriksa'=>'',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-ri'];

    public function mount(): void { $this->registerAreas(['modal-observasi-ri']); }

    #[On('open-rm-observasi-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();
        $data = $this->findDataRI($riHdrNo);
        if (!$data) { $this->dispatch('toast', type:'error', message:'Data RI tidak ditemukan.'); return; }
        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['observasi'] ??= [];
        $this->dataDaftarRi['observasi']['observasiLanjutan'] ??= ['tandaVitalTab'=>'Observasi Lanjutan','tandaVital'=>[]];
        $this->dataDaftarRi['observasi']['pemakaianOksigen']  ??= ['pemakaianOksigenTab'=>'Pemakaian Oksigen','pemakaianOksigenData'=>[]];
        $this->dataDaftarRi['observasi']['pengeluaranCairan'] ??= ['pengeluaranCairanTab'=>'Pengeluaran Cairan','pengeluaranCairan'=>[]];
        $this->incrementVersion('modal-observasi-ri');
        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r'=>$riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    public function setWaktuPemeriksaan(): void
    {
        $this->formEntryObservasi['waktuPemeriksaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function addObservasiLanjutan(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->formEntryObservasi['pemeriksa'] = auth()->user()->myuser_name;
        $this->validate([
            'formEntryObservasi.waktuPemeriksaan' => 'required|date_format:d/m/Y H:i:s',
            'formEntryObservasi.sistolik'         => 'required|numeric',
            'formEntryObservasi.distolik'         => 'required|numeric',
            'formEntryObservasi.frekuensiNafas'   => 'required|numeric',
            'formEntryObservasi.frekuensiNadi'    => 'required|numeric',
            'formEntryObservasi.suhu'             => 'required|numeric',
            'formEntryObservasi.spo2'             => 'required|numeric',
        ]);
        $target = trim($this->formEntryObservasi['waktuPemeriksaan']);
        $dup = collect($this->dataDaftarRi['observasi']['observasiLanjutan']['tandaVital']??[])
            ->contains('waktuPemeriksaan', $target);
        if ($dup) { $this->dispatch('toast', type:'error', message:'Observasi dengan waktu tersebut sudah ada.'); return; }
        try {
            $this->withRiLock(function () use ($target) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['observasi']['observasiLanjutan']['tandaVital'][] = array_merge($this->formEntryObservasi, [
                    'sistolik'=>(int)$this->formEntryObservasi['sistolik'],
                    'distolik'=>(int)$this->formEntryObservasi['distolik'],
                    'frekuensiNafas'=>(int)$this->formEntryObservasi['frekuensiNafas'],
                    'frekuensiNadi'=>(int)$this->formEntryObservasi['frekuensiNadi'],
                    'suhu'=>(float)$this->formEntryObservasi['suhu'],
                    'spo2'=>(int)$this->formEntryObservasi['spo2'],
                    'gda'=>$this->formEntryObservasi['gda']===''?null:(float)$this->formEntryObservasi['gda'],
                    'gcs'=>$this->formEntryObservasi['gcs']===''?null:(int)$this->formEntryObservasi['gcs'],
                ]);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryObservasi']);
            $this->afterSave('Observasi berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function removeObservasiLanjutan(string $waktuPemeriksaan): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($waktuPemeriksaan) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = collect($fresh['observasi']['observasiLanjutan']['tandaVital'] ?? []);
                $fresh['observasi']['observasiLanjutan']['tandaVital'] =
                    $list->reject(fn($r) => trim($r['waktuPemeriksaan']??'') === trim($waktuPemeriksaan))->values()->all();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Observasi berhasil dihapus.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    // Oksigen
    public function setWaktuMulaiOksigen(): void
    {
        $this->formEntryOksigen['tanggalWaktuMulai'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function addPemakaianOksigen(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->validate([
            'formEntryOksigen.jenisAlatOksigen'  => 'required',
            'formEntryOksigen.dosisOksigen'      => 'required',
            'formEntryOksigen.tanggalWaktuMulai' => 'required|date_format:d/m/Y H:i:s',
        ]);
        $target = trim($this->formEntryOksigen['tanggalWaktuMulai']);
        try {
            $this->withRiLock(function () use ($target) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['observasi']['pemakaianOksigen']['pemakaianOksigenData'][] = array_merge($this->formEntryOksigen, ['pemeriksa'=>auth()->user()->myuser_name]);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryOksigen']);
            $this->afterSave('Pemakaian Oksigen berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function removePemakaianOksigen(string $waktuMulai): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($waktuMulai) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = collect($fresh['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? []);
                $fresh['observasi']['pemakaianOksigen']['pemakaianOksigenData'] =
                    $list->reject(fn($r) => trim($r['tanggalWaktuMulai']??'') === trim($waktuMulai))->values()->all();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pemakaian Oksigen berhasil dihapus.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    // Pengeluaran cairan
    public function setWaktuPengeluaran(): void
    {
        $this->formEntryPengeluaran['waktuPengeluaran'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function addPengeluaranCairan(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->formEntryPengeluaran['pemeriksa'] = auth()->user()->myuser_name;
        $this->validate([
            'formEntryPengeluaran.waktuPengeluaran' => 'required|date_format:d/m/Y H:i:s',
            'formEntryPengeluaran.jenisOutput'      => 'required',
            'formEntryPengeluaran.volume'           => 'required|numeric',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['observasi']['pengeluaranCairan']['pengeluaranCairan'][] = array_merge($this->formEntryPengeluaran, ['volume'=>(float)$this->formEntryPengeluaran['volume']]);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryPengeluaran']);
            $this->afterSave('Pengeluaran cairan berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function removePengeluaranCairan(string $waktu): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($waktu) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = collect($fresh['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? []);
                $fresh['observasi']['pengeluaranCairan']['pengeluaranCairan'] =
                    $list->reject(fn($r) => trim($r['waktuPengeluaran']??'') === trim($waktu))->values()->all();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pengeluaran cairan berhasil dihapus.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-observasi-ri');
        $this->dispatch('toast', type:'success', message:$msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion(); $this->isFormLocked = false;
        $this->reset(['formEntryObservasi','formEntryOksigen','formEntryPengeluaran']);
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-observasi-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- ── OBSERVASI TTV LANJUTAN ── --}}
    <x-border-form title="Observasi Tanda Vital Lanjutan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
            <div class="grid grid-cols-4 gap-3">
                @foreach ([['sistolik','Sistolik'],['distolik','Diastolik'],['frekuensiNadi','Nadi'],['frekuensiNafas','Nafas'],['suhu','Suhu'],['spo2','SpO2'],['gda','GDA'],['gcs','GCS'],['cairan','Cairan'],['tetesan','Tetesan']] as [$k,$l])
                <div>
                    <x-input-label value="{{ $l }}" />
                    <x-text-input wire:model="formEntryObservasi.{{ $k }}" class="w-full mt-1 text-sm"
                        type="{{ in_array($k,['cairan','tetesan'])?'text':'number' }}" step="any" />
                </div>
                @endforeach
                <div class="col-span-2">
                    <x-input-label value="Waktu Pemeriksaan *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryObservasi.waktuPemeriksaan"
                            class="flex-1 font-mono text-sm" readonly
                            :error="$errors->has('formEntryObservasi.waktuPemeriksaan')" />
                        <x-secondary-button wire:click="setWaktuPemeriksaan" type="button" class="text-xs">Sekarang</x-secondary-button>
                    </div>
                    <x-input-error :messages="$errors->get('formEntryObservasi.waktuPemeriksaan')" class="mt-1" />
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="addObservasiLanjutan" type="button">+ Tambah Observasi</x-primary-button>
            </div>
            @endif

            {{-- List --}}
            @if (!empty($dataDaftarRi['observasi']['observasiLanjutan']['tandaVital']))
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                        <tr>
                            @foreach (['Waktu','TD','Nadi','Nafas','Suhu','SpO2','GDA','GCS','Pemeriksa',''] as $h)
                            <th class="px-3 py-2 font-medium text-left">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($dataDaftarRi['observasi']['observasiLanjutan']['tandaVital'] as $obs)
                        <tr wire:key="obs-{{ $obs['waktuPemeriksaan'] }}" class="bg-white dark:bg-gray-800">
                            <td class="px-3 py-2 font-mono">{{ $obs['waktuPemeriksaan']??'-' }}</td>
                            <td class="px-3 py-2">{{ ($obs['sistolik']??'-').'/'.($obs['distolik']??'-') }}</td>
                            <td class="px-3 py-2">{{ $obs['frekuensiNadi']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['frekuensiNafas']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['suhu']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['spo2']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['gda']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['gcs']??'-' }}</td>
                            <td class="px-3 py-2">{{ $obs['pemeriksa']??'-' }}</td>
                            @if (!$isFormLocked)
                            <td class="px-3 py-2">
                                <x-icon-button variant="danger"
                                    wire:click="removeObservasiLanjutan('{{ $obs['waktuPemeriksaan'] }}')"
                                    wire:confirm="Hapus data observasi ini?" tooltip="Hapus">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                <p class="text-xs text-center text-gray-400 py-3">Belum ada data observasi.</p>
            @endif
        </div>
    </x-border-form>

    {{-- ── PEMAKAIAN OKSIGEN ── --}}
    <x-border-form title="Pemakaian Oksigen" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Jenis Alat Oksigen" />
                    <x-select-input wire:model="formEntryOksigen.jenisAlatOksigen" class="w-full mt-1">
                        @foreach (['Nasal Kanul','Masker Sederhana','Ventilator Non-Invasif','Lainnya'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Dosis Oksigen" />
                    <x-select-input wire:model="formEntryOksigen.dosisOksigen" class="w-full mt-1">
                        @foreach (['1-2 L/menit','3-4 L/menit','2-6 L/menit (Nasal Kanul)','5-10 L/menit (Masker)','Lainnya'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Model Penggunaan" />
                    <x-select-input wire:model="formEntryOksigen.modelPenggunaan" class="w-full mt-1">
                        <option value="Kontinu">Kontinu</option>
                        <option value="Intermiten">Intermiten</option>
                    </x-select-input>
                </div>
                <div class="col-span-2">
                    <x-input-label value="Waktu Mulai *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryOksigen.tanggalWaktuMulai"
                            class="flex-1 font-mono text-sm" readonly />
                        <x-secondary-button wire:click="setWaktuMulaiOksigen" type="button" class="text-xs">Sekarang</x-secondary-button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="addPemakaianOksigen" type="button">+ Tambah Oksigen</x-primary-button>
            </div>
            @endif

            @if (!empty($dataDaftarRi['observasi']['pemakaianOksigen']['pemakaianOksigenData']))
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                        <tr>
                            @foreach (['Jenis Alat','Dosis','Model','Waktu Mulai','Durasi',''] as $h)
                            <th class="px-3 py-2 font-medium text-left">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($dataDaftarRi['observasi']['pemakaianOksigen']['pemakaianOksigenData'] as $o2)
                        <tr wire:key="o2-{{ $o2['tanggalWaktuMulai']??'' }}" class="bg-white dark:bg-gray-800">
                            <td class="px-3 py-2">{{ $o2['jenisAlatOksigen']??'-' }}</td>
                            <td class="px-3 py-2">{{ $o2['dosisOksigen']??'-' }}</td>
                            <td class="px-3 py-2">{{ $o2['modelPenggunaan']??'-' }}</td>
                            <td class="px-3 py-2 font-mono">{{ $o2['tanggalWaktuMulai']??'-' }}</td>
                            <td class="px-3 py-2">{{ $o2['durasiPenggunaan']??'-' }}</td>
                            @if (!$isFormLocked)
                            <td class="px-3 py-2">
                                <x-icon-button variant="danger"
                                    wire:click="removePemakaianOksigen('{{ $o2['tanggalWaktuMulai'] }}')"
                                    wire:confirm="Hapus data oksigen ini?" tooltip="Hapus">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                <p class="text-xs text-center text-gray-400 py-3">Belum ada data oksigen.</p>
            @endif
        </div>
    </x-border-form>

    {{-- ── PENGELUARAN CAIRAN ── --}}
    <x-border-form title="Pengeluaran Cairan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Jenis Output" />
                    <x-text-input wire:model="formEntryPengeluaran.jenisOutput" class="w-full mt-1" placeholder="Urine / Feses / dll" />
                </div>
                <div>
                    <x-input-label value="Volume (ml) *" />
                    <x-text-input wire:model="formEntryPengeluaran.volume" class="w-full mt-1" type="number" step="any" />
                </div>
                <div>
                    <x-input-label value="Warna / Karakteristik" />
                    <x-text-input wire:model="formEntryPengeluaran.warnaKarakteristik" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="Keterangan" />
                    <x-text-input wire:model="formEntryPengeluaran.keterangan" class="w-full mt-1" />
                </div>
                <div class="col-span-2">
                    <x-input-label value="Waktu Pengeluaran *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryPengeluaran.waktuPengeluaran"
                            class="flex-1 font-mono text-sm" readonly />
                        <x-secondary-button wire:click="setWaktuPengeluaran" type="button" class="text-xs">Sekarang</x-secondary-button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="addPengeluaranCairan" type="button">+ Tambah Cairan</x-primary-button>
            </div>
            @endif

            @if (!empty($dataDaftarRi['observasi']['pengeluaranCairan']['pengeluaranCairan']))
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                        <tr>
                            @foreach (['Waktu','Jenis','Volume','Warna','Keterangan','Pemeriksa',''] as $h)
                            <th class="px-3 py-2 font-medium text-left">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($dataDaftarRi['observasi']['pengeluaranCairan']['pengeluaranCairan'] as $pc)
                        <tr wire:key="pc-{{ $pc['waktuPengeluaran']??'' }}" class="bg-white dark:bg-gray-800">
                            <td class="px-3 py-2 font-mono">{{ $pc['waktuPengeluaran']??'-' }}</td>
                            <td class="px-3 py-2">{{ $pc['jenisOutput']??'-' }}</td>
                            <td class="px-3 py-2">{{ $pc['volume']??'-' }} ml</td>
                            <td class="px-3 py-2">{{ $pc['warnaKarakteristik']??'-' }}</td>
                            <td class="px-3 py-2">{{ $pc['keterangan']??'-' }}</td>
                            <td class="px-3 py-2">{{ $pc['pemeriksa']??'-' }}</td>
                            @if (!$isFormLocked)
                            <td class="px-3 py-2">
                                <x-icon-button variant="danger"
                                    wire:click="removePengeluaranCairan('{{ $pc['waktuPengeluaran'] }}')"
                                    wire:confirm="Hapus data pengeluaran ini?" tooltip="Hapus">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                <p class="text-xs text-center text-gray-400 py-3">Belum ada data pengeluaran cairan.</p>
            @endif
        </div>
    </x-border-form>

</div>
