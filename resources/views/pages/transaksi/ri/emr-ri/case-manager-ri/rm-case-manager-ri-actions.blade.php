<?php
// resources/views/pages/transaksi/ri/emr-ri/case-manager/rm-case-manager-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool    $isFormLocked = false;
    public ?string $riHdrNo      = null;
    public array   $dataDaftarRi = [];
    public bool    $showFormB    = false;

    /* ── Form A: Skrining Awal MPP ── */
    public array $formA = [
        'formA_id'          => '',
        'tipeForm'          => 'FormA',
        'tanggal'           => '',
        'indentifikasiKasus'=> '',
        'assessment'        => '',
        'perencanaan'       => '',
        'tandaTanganPetugas'=> ['petugasCode'=>'','petugasName'=>'','jabatan'=>'MPP'],
    ];

    /* ── Form B: Pelaksanaan, Monitoring, Advokasi, Terminasi ── */
    public array $formB = [
        'formB_id'             => '',
        'tipeForm'             => 'FormB',
        'formA_id'             => '',
        'tanggal'              => '',
        'pelaksanaanMonitoring'=> '',
        'advokasiKolaborasi'   => '',
        'terminasi'            => '',
        'tandaTanganPetugas'   => ['petugasCode'=>'','petugasName'=>'','jabatan'=>'MPP'],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-case-manager-ri'];

    public function mount(): void { $this->registerAreas(['modal-case-manager-ri']); }

    #[On('open-rm-case-manager-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) { $this->dispatch('toast', type:'error', message:'Data RI tidak ditemukan.'); return; }
        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['formMPP'] ??= ['formA'=>[],'formB'=>[]];

        $this->setPetugasData();
        $this->incrementVersion('modal-case-manager-ri');
        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r'=>$riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    private function setPetugasData(): void
    {
        $code = auth()->user()->myuser_code ?? '';
        $name = auth()->user()->myuser_name ?? '';
        $this->formA['tandaTanganPetugas'] = ['petugasCode'=>$code,'petugasName'=>$name,'jabatan'=>'MPP'];
        $this->formB['tandaTanganPetugas'] = ['petugasCode'=>$code,'petugasName'=>$name,'jabatan'=>'MPP'];
    }

    public function setTanggalFormA(): void { $this->formA['tanggal'] = Carbon::now()->format('d/m/Y H:i:s'); }
    public function setTanggalFormB(): void { $this->formB['tanggal'] = Carbon::now()->format('d/m/Y H:i:s'); }

    /* ── SIMPAN FORM A ── */
    public function simpanFormA(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->validate([
            'formA.tanggal'                        => 'required|date_format:d/m/Y H:i:s',
            'formA.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
            'formA.tandaTanganPetugas.petugasName' => 'required|string|max:150',
        ],[
            'formA.tanggal.required' => 'Tanggal wajib diisi.',
            'formA.tandaTanganPetugas.petugasCode.required' => 'Kode petugas wajib diisi.',
            'formA.tandaTanganPetugas.petugasName.required' => 'Nama petugas wajib diisi.',
        ]);
        $this->formA['formA_id'] = (string) Str::uuid();
        $entry = array_merge($this->formA, ['created_at'=>now()->format('Y-m-d H:i:s')]);
        try {
            $this->withRiLock(function () use ($entry) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['formMPP']['formA'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->resetFormA();
            $this->afterSave('Form A (Skrining MPP) berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    /* ── SIMPAN FORM B ── */
    public function simpanFormB(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->validate([
            'formB.formA_id'                       => 'required|string',
            'formB.tanggal'                        => 'required|date_format:d/m/Y H:i:s',
            'formB.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
            'formB.tandaTanganPetugas.petugasName' => 'required|string|max:150',
        ],[
            'formB.formA_id.required' => 'Referensi Form A wajib dipilih.',
            'formB.tanggal.required'  => 'Tanggal wajib diisi.',
        ]);
        $this->formB['formB_id'] = (string) Str::uuid();
        $entry = array_merge($this->formB, ['created_at'=>now()->format('Y-m-d H:i:s')]);
        try {
            $this->withRiLock(function () use ($entry) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['formMPP']['formB'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->resetFormB();
            $this->showFormB = false;
            $this->afterSave('Form B (Pelaksanaan MPP) berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    /* ── HAPUS ── */
    public function hapusForm(string $tipe, string $id): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($tipe, $id) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list  = $fresh['formMPP'][$tipe] ?? [];
                $fresh['formMPP'][$tipe] = array_values(array_filter($list, fn($e)=>($e[$tipe.'_id']??null)!==$id));
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave("Data {$tipe} berhasil dihapus.");
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    public function tambahFormB(string $formA_id): void
    {
        $this->formB['formA_id'] = $formA_id;
        $this->formB['tanggal']  = Carbon::now()->format('d/m/Y H:i:s');
        $this->showFormB = true;
        $this->dispatch('scroll-to-form-b');
    }

    public function cetakFormA(string $id)
    {
        $formA = collect($this->dataDaftarRi['formMPP']['formA']??[])->firstWhere('formA_id',$id);
        if (!$formA) { $this->dispatch('toast', type:'error', message:'Data Form A tidak ditemukan.'); return; }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name','int_phone1','int_phone2','int_fax','int_address','int_city')->first();
            $dataPasien  = $this->findDataMasterPasien($this->dataDaftarRi['regNo']??'');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-a-print',['identitasRs'=>$identitasRs,'dataPasien'=>$dataPasien,'dataDaftarRi'=>$this->dataDaftarRi,'dataFormA'=>$formA])->output();
            $this->dispatch('toast', type:'success', message:'Berhasil mencetak Form A.');
            return response()->streamDownload(fn()=>print($pdf),'form-a-'.$id.'.pdf');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal cetak: '.$e->getMessage()); }
    }

    public function cetakFormB(string $id)
    {
        $formB = collect($this->dataDaftarRi['formMPP']['formB']??[])->firstWhere('formB_id',$id);
        if (!$formB) { $this->dispatch('toast', type:'error', message:'Data Form B tidak ditemukan.'); return; }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name','int_phone1','int_phone2','int_fax','int_address','int_city')->first();
            $dataPasien  = $this->findDataMasterPasien($this->dataDaftarRi['regNo']??'');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-b-print',['identitasRs'=>$identitasRs,'dataPasien'=>$dataPasien,'dataDaftarRi'=>$this->dataDaftarRi,'dataFormB'=>$formB])->output();
            $this->dispatch('toast', type:'success', message:'Berhasil mencetak Form B.');
            return response()->streamDownload(fn()=>print($pdf),'form-b-'.$id.'.pdf');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal cetak: '.$e->getMessage()); }
    }

    private function resetFormA(): void
    {
        $c = auth()->user()->myuser_code??''; $n = auth()->user()->myuser_name??'';
        $this->formA = ['formA_id'=>'','tipeForm'=>'FormA','tanggal'=>'','indentifikasiKasus'=>'','assessment'=>'','perencanaan'=>'','tandaTanganPetugas'=>['petugasCode'=>$c,'petugasName'=>$n,'jabatan'=>'MPP']];
    }

    private function resetFormB(): void
    {
        $c = auth()->user()->myuser_code??''; $n = auth()->user()->myuser_name??'';
        $this->formB = ['formB_id'=>'','tipeForm'=>'FormB','formA_id'=>'','tanggal'=>'','pelaksanaanMonitoring'=>'','advokasiKolaborasi'=>'','terminasi'=>'','tandaTanganPetugas'=>['petugasCode'=>$c,'petugasName'=>$n,'jabatan'=>'MPP']];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-case-manager-ri');
        $this->dispatch('toast', type:'success', message:$msg);
    }

    protected function resetForm(): void { $this->resetVersion(); $this->isFormLocked = false; $this->showFormB = false; }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 5)->block(3, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                $fn();
            }, 5);
        });
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-case-manager-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- FORM A: SKRINING AWAL MPP --}}
    @if (!$isFormLocked)
    <x-border-form title="Form A — Skrining Awal MPP" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formA.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formA.tanggal')" />
                    <x-input-error :messages="$errors->get('formA.tanggal')" class="mt-1" />
                </div>
                <x-secondary-button wire:click="setTanggalFormA" type="button">Sekarang</x-secondary-button>
            </div>
            @foreach ([['key'=>'indentifikasiKasus','label'=>'Identifikasi Kasus'],['key'=>'assessment','label'=>'Assessment'],['key'=>'perencanaan','label'=>'Perencanaan']] as $f)
            <div>
                <x-input-label value="{{ $f['label'] }}" />
                <x-textarea wire:model="formA.{{ $f['key'] }}" class="w-full mt-1" rows="3" placeholder="{{ $f['label'] }}..." />
            </div>
            @endforeach
            <div class="flex justify-end">
                <x-primary-button wire:click="simpanFormA" type="button">+ Simpan Form A</x-primary-button>
            </div>
        </div>
    </x-border-form>
    @endif

    {{-- LIST FORM A --}}
    <x-border-form title="Daftar Form MPP" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @forelse ($dataDaftarRi['formMPP']['formA'] ?? [] as $idx => $fa)
            <div wire:key="fa-{{ $fa['formA_id']??$idx }}"
                 class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700/60 border-b border-gray-100 dark:border-gray-700">
                    <div class="text-xs space-x-2">
                        <span class="font-bold text-brand">Form A</span>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $fa['tandaTanganPetugas']['petugasName']??'-' }}</span>
                        <span class="font-mono text-gray-400">{{ $fa['tanggal']??'-' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                        @if (!$isFormLocked)
                        <x-secondary-button wire:click="tambahFormB('{{ $fa['formA_id'] }}')" type="button" class="text-xs">
                            + Form B
                        </x-secondary-button>
                        @endif
                        <x-primary-button wire:click="cetakFormA('{{ $fa['formA_id'] }}')" type="button" class="text-xs">Cetak</x-primary-button>
                        @if (!$isFormLocked)
                        <x-icon-button variant="danger"
                            wire:click="hapusForm('formA','{{ $fa['formA_id'] }}')"
                            wire:confirm="Hapus Form A ini?" tooltip="Hapus">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </x-icon-button>
                        @endif
                    </div>
                </div>
                <div class="px-4 py-3 text-xs space-y-1 text-gray-700 dark:text-gray-300">
                    @if (!empty($fa['indentifikasiKasus']))
                    <p><span class="font-semibold">Identifikasi Kasus:</span> {{ $fa['indentifikasiKasus'] }}</p>
                    @endif
                    @if (!empty($fa['assessment']))
                    <p><span class="font-semibold">Assessment:</span> {{ $fa['assessment'] }}</p>
                    @endif
                    @if (!empty($fa['perencanaan']))
                    <p><span class="font-semibold">Perencanaan:</span> {{ $fa['perencanaan'] }}</p>
                    @endif

                    {{-- Form B milik Form A ini --}}
                    @php
                        $formBList = collect($dataDaftarRi['formMPP']['formB']??[])->where('formA_id', $fa['formA_id']);
                    @endphp
                    @if ($formBList->count() > 0)
                    <div class="mt-2 ml-3 space-y-1.5 border-l-2 border-brand/20 pl-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Form B — Pelaksanaan MPP</p>
                        @foreach ($formBList as $fb)
                        <div class="flex items-center justify-between">
                            <div class="space-x-2">
                                <span class="font-mono text-gray-400">{{ $fb['tanggal']??'-' }}</span>
                                @if (!empty($fb['pelaksanaanMonitoring']))
                                <span class="text-gray-600 dark:text-gray-300">{{ Str::limit($fb['pelaksanaanMonitoring'],60) }}</span>
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <x-primary-button wire:click="cetakFormB('{{ $fb['formB_id'] }}')" type="button" class="text-xs">Cetak</x-primary-button>
                                @if (!$isFormLocked)
                                <x-icon-button variant="danger"
                                    wire:click="hapusForm('formB','{{ $fb['formB_id'] }}')"
                                    wire:confirm="Hapus Form B ini?" tooltip="Hapus">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </x-icon-button>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-xs text-center text-gray-400 py-6">Belum ada data MPP.</p>
            @endforelse
        </div>
    </x-border-form>

    {{-- FORM B: PELAKSANAAN --}}
    @if ($showFormB && !$isFormLocked)
    <div id="form-b-section">
    <x-border-form title="Form B — Pelaksanaan, Monitoring, Advokasi, Terminasi" align="start" bgcolor="bg-amber-50 dark:bg-amber-900/10">
        <div class="mt-3 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formB.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formB.tanggal')" />
                </div>
                <x-secondary-button wire:click="setTanggalFormB" type="button">Sekarang</x-secondary-button>
            </div>
            <div class="bg-brand/5 rounded px-3 py-2 text-xs">
                <span class="text-gray-500">Referensi Form A:</span>
                <span class="ml-1 font-mono text-brand">{{ $formB['formA_id'] }}</span>
            </div>
            @foreach ([['key'=>'pelaksanaanMonitoring','label'=>'Pelaksanaan & Monitoring'],['key'=>'advokasiKolaborasi','label'=>'Advokasi / Kolaborasi'],['key'=>'terminasi','label'=>'Terminasi']] as $f)
            <div>
                <x-input-label value="{{ $f['label'] }}" />
                <x-textarea wire:model="formB.{{ $f['key'] }}" class="w-full mt-1" rows="3" placeholder="{{ $f['label'] }}..." />
            </div>
            @endforeach
            <div class="flex justify-end gap-2">
                <x-ghost-button wire:click="$set('showFormB', false)" type="button">Batal</x-ghost-button>
                <x-primary-button wire:click="simpanFormB" type="button">+ Simpan Form B</x-primary-button>
            </div>
        </div>
    </x-border-form>
    </div>
    @endif

</div>
