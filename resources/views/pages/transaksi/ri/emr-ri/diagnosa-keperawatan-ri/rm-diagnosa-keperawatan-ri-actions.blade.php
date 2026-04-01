<?php
// resources/views/pages/transaksi/ri/emr-ri/diagnosa-keperawatan/rm-diagnosa-keperawatan-ri-actions.blade.php

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

    public bool $showFormB = false;

    /* ── Form A: Diagnosa Keperawatan ── */
    public array $formDiagnosaKeperawatan = [
        'formDiagnosaKeperawatan_id' => '',
        'tipeForm'             => 'FormA',
        'tanggal'              => '',
        'dataSubyektif'        => '',
        'dataObyektif'         => '',
        'diagnosaKeperawatan'  => '',
        'tandaTanganPetugas'   => ['petugasCode'=>'','petugasName'=>'','jabatan'=>'Perawat'],
    ];

    /* ── Form B: Intervensi & Implementasi ── */
    public array $formIntervensiImplementasi = [
        'formIntervensiImplementasi_id'  => '',
        'tipeForm'                       => 'FormB',
        'formDiagnosaKeperawatan_id'     => '',
        'tanggal'                        => '',
        'intervensi'                     => '',
        'implementasi'                   => '',
        'tandaTanganPetugas'             => ['petugasCode'=>'','petugasName'=>'','jabatan'=>'Perawat'],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosa-keperawatan-ri'];

    public function mount(): void { $this->registerAreas(['modal-diagnosa-keperawatan-ri']); }

    #[On('open-rm-diagnosa-keperawatan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) { $this->dispatch('toast', type:'error', message:'Data RI tidak ditemukan.'); return; }
        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['diagKeperawatan'] ??= ['formDiagnosaKeperawatan'=>[],'formIntervensiImplementasi'=>[]];

        $this->setPetugasData();
        $this->incrementVersion('modal-diagnosa-keperawatan-ri');
        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r'=>$riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    private function setPetugasData(): void
    {
        $code = auth()->user()->myuser_code ?? '';
        $name = auth()->user()->myuser_name ?? '';
        $this->formDiagnosaKeperawatan['tandaTanganPetugas'] = ['petugasCode'=>$code,'petugasName'=>$name,'jabatan'=>'Perawat'];
        $this->formIntervensiImplementasi['tandaTanganPetugas'] = ['petugasCode'=>$code,'petugasName'=>$name,'jabatan'=>'Perawat'];
    }

    public function setTanggalFormA(): void { $this->formDiagnosaKeperawatan['tanggal'] = Carbon::now()->format('d/m/Y H:i:s'); }
    public function setTanggalFormB(): void { $this->formIntervensiImplementasi['tanggal'] = Carbon::now()->format('d/m/Y H:i:s'); }

    /* ── SIMPAN FORM A ── */
    public function simpanFormA(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->validate([
            'formDiagnosaKeperawatan.tanggal'              => 'required|date_format:d/m/Y H:i:s',
            'formDiagnosaKeperawatan.diagnosaKeperawatan'  => 'required|string|max:1000',
            'formDiagnosaKeperawatan.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
            'formDiagnosaKeperawatan.tandaTanganPetugas.petugasName' => 'required|string|max:150',
        ],[
            'formDiagnosaKeperawatan.tanggal.required'             => 'Tanggal wajib diisi.',
            'formDiagnosaKeperawatan.diagnosaKeperawatan.required' => 'Diagnosa keperawatan wajib diisi.',
            'formDiagnosaKeperawatan.tandaTanganPetugas.petugasCode.required' => 'Kode petugas wajib diisi.',
            'formDiagnosaKeperawatan.tandaTanganPetugas.petugasName.required' => 'Nama petugas wajib diisi.',
        ]);
        $this->formDiagnosaKeperawatan['formDiagnosaKeperawatan_id'] = (string) Str::uuid();
        $entry = array_merge($this->formDiagnosaKeperawatan, ['created_at'=>now()->format('Y-m-d H:i:s')]);
        try {
            $this->withRiLock(function () use ($entry) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['diagKeperawatan']['formDiagnosaKeperawatan'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->resetFormA();
            $this->afterSave('Diagnosa Keperawatan berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    /* ── SIMPAN FORM B ── */
    public function simpanFormB(): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        $this->validate([
            'formIntervensiImplementasi.formDiagnosaKeperawatan_id' => 'required|string',
            'formIntervensiImplementasi.tanggal'                    => 'required|date_format:d/m/Y H:i:s',
            'formIntervensiImplementasi.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
            'formIntervensiImplementasi.tandaTanganPetugas.petugasName' => 'required|string|max:150',
        ],[
            'formIntervensiImplementasi.formDiagnosaKeperawatan_id.required' => 'Referensi Form A wajib dipilih.',
            'formIntervensiImplementasi.tanggal.required'                    => 'Tanggal wajib diisi.',
        ]);
        $this->formIntervensiImplementasi['formIntervensiImplementasi_id'] = (string) Str::uuid();
        $entry = array_merge($this->formIntervensiImplementasi, ['created_at'=>now()->format('Y-m-d H:i:s')]);
        try {
            $this->withRiLock(function () use ($entry) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['diagKeperawatan']['formIntervensiImplementasi'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->resetFormB();
            $this->showFormB = false;
            $this->afterSave('Intervensi & Implementasi berhasil disimpan.');
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    /* ── HAPUS FORM A / B ── */
    public function hapusForm(string $tipe, string $id): void
    {
        if ($this->isFormLocked) { $this->dispatch('toast', type:'error', message:'Pasien sudah pulang.'); return; }
        try {
            $this->withRiLock(function () use ($tipe, $id) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list  = $fresh['diagKeperawatan'][$tipe] ?? [];
                $fresh['diagKeperawatan'][$tipe] = array_values(
                    array_filter($list, fn($e) => ($e[$tipe.'_id']??null) !== $id)
                );
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave("Data {$tipe} berhasil dihapus.");
        } catch (LockTimeoutException) { $this->dispatch('toast', type:'error', message:'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal: '.$e->getMessage()); }
    }

    /* ── FORM B DARI TABLE FORM A ── */
    public function tambahFormB(string $formDiagnosaKeperawatan_id): void
    {
        $this->formIntervensiImplementasi['formDiagnosaKeperawatan_id'] = $formDiagnosaKeperawatan_id;
        $this->formIntervensiImplementasi['tanggal'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->showFormB = true;
        $this->dispatch('scroll-to-form-b');
    }

    /* ── CETAK ── */
    public function cetakFormA(string $id)
    {
        $formA = collect($this->dataDaftarRi['diagKeperawatan']['formDiagnosaKeperawatan']??[])->firstWhere('formDiagnosaKeperawatan_id',$id);
        if (!$formA) { $this->dispatch('toast', type:'error', message:'Data Form A tidak ditemukan.'); return; }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name','int_phone1','int_phone2','int_fax','int_address','int_city')->first();
            $dataPasien  = $this->findDataMasterPasien($this->dataDaftarRi['regNo']??'');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-a-print', ['identitasRs'=>$identitasRs,'dataPasien'=>$dataPasien,'dataDaftarRi'=>$this->dataDaftarRi,'dataFormA'=>$formA])->output();
            $this->dispatch('toast', type:'success', message:'Berhasil mencetak Form A.');
            return response()->streamDownload(fn()=>print($pdf), 'form-a-'.$id.'.pdf');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal cetak: '.$e->getMessage()); }
    }

    public function cetakFormB(string $id)
    {
        $formB = collect($this->dataDaftarRi['diagKeperawatan']['formIntervensiImplementasi']??[])->firstWhere('formIntervensiImplementasi_id',$id);
        if (!$formB) { $this->dispatch('toast', type:'error', message:'Data Form B tidak ditemukan.'); return; }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name','int_phone1','int_phone2','int_fax','int_address','int_city')->first();
            $dataPasien  = $this->findDataMasterPasien($this->dataDaftarRi['regNo']??'');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-b-print', ['identitasRs'=>$identitasRs,'dataPasien'=>$dataPasien,'dataDaftarRi'=>$this->dataDaftarRi,'dataFormB'=>$formB])->output();
            $this->dispatch('toast', type:'success', message:'Berhasil mencetak Form B.');
            return response()->streamDownload(fn()=>print($pdf), 'form-b-'.$id.'.pdf');
        } catch (\Throwable $e) { $this->dispatch('toast', type:'error', message:'Gagal cetak: '.$e->getMessage()); }
    }

    private function resetFormA(): void
    {
        $this->formDiagnosaKeperawatan = [
            'formDiagnosaKeperawatan_id'=>'','tipeForm'=>'FormA','tanggal'=>'',
            'dataSubyektif'=>'','dataObyektif'=>'','diagnosaKeperawatan'=>'',
            'tandaTanganPetugas'=>['petugasCode'=>auth()->user()->myuser_code??'','petugasName'=>auth()->user()->myuser_name??'','jabatan'=>'Perawat'],
        ];
    }

    private function resetFormB(): void
    {
        $this->formIntervensiImplementasi = [
            'formIntervensiImplementasi_id'=>'','tipeForm'=>'FormB','formDiagnosaKeperawatan_id'=>'','tanggal'=>'',
            'intervensi'=>'','implementasi'=>'',
            'tandaTanganPetugas'=>['petugasCode'=>auth()->user()->myuser_code??'','petugasName'=>auth()->user()->myuser_name??'','jabatan'=>'Perawat'],
        ];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-diagnosa-keperawatan-ri');
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-diagnosa-keperawatan-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- FORM A: DIAGNOSA KEPERAWATAN --}}
    @if (!$isFormLocked)
    <x-border-form title="Form A — Diagnosa Keperawatan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formDiagnosaKeperawatan.tanggal"
                        class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formDiagnosaKeperawatan.tanggal')" />
                </div>
                <x-secondary-button wire:click="setTanggalFormA" type="button">Sekarang</x-secondary-button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Data Subyektif" />
                    <x-textarea wire:model="formDiagnosaKeperawatan.dataSubyektif" class="w-full mt-1" rows="3" placeholder="S:" />
                </div>
                <div>
                    <x-input-label value="Data Obyektif" />
                    <x-textarea wire:model="formDiagnosaKeperawatan.dataObyektif" class="w-full mt-1" rows="3" placeholder="O:" />
                </div>
            </div>
            <div>
                <x-input-label value="Diagnosa Keperawatan *" />
                <x-textarea wire:model="formDiagnosaKeperawatan.diagnosaKeperawatan" class="w-full mt-1" rows="3"
                    :error="$errors->has('formDiagnosaKeperawatan.diagnosaKeperawatan')"
                    placeholder="Masukkan diagnosa keperawatan..." />
                <x-input-error :messages="$errors->get('formDiagnosaKeperawatan.diagnosaKeperawatan')" class="mt-1" />
            </div>
            <div class="flex justify-end">
                <x-primary-button wire:click="simpanFormA" type="button">+ Simpan Diagnosa Keperawatan</x-primary-button>
            </div>
        </div>
    </x-border-form>
    @endif

    {{-- LIST FORM A --}}
    <x-border-form title="Daftar Diagnosa Keperawatan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @forelse ($dataDaftarRi['diagKeperawatan']['formDiagnosaKeperawatan'] ?? [] as $idx => $fa)
            <div wire:key="fa-{{ $fa['formDiagnosaKeperawatan_id']??$idx }}"
                 class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700/60 border-b border-gray-100 dark:border-gray-700">
                    <div class="text-xs space-x-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $fa['tandaTanganPetugas']['petugasName']??'-' }}</span>
                        <span class="font-mono text-gray-400">{{ $fa['tanggal']??'-' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                        @if (!$isFormLocked)
                        <x-secondary-button wire:click="tambahFormB('{{ $fa['formDiagnosaKeperawatan_id'] }}')" type="button" class="text-xs">
                            + Form B
                        </x-secondary-button>
                        @endif
                        <x-primary-button wire:click="cetakFormA('{{ $fa['formDiagnosaKeperawatan_id'] }}')" type="button" class="text-xs">Cetak</x-primary-button>
                        @if (!$isFormLocked)
                        <x-icon-button variant="danger"
                            wire:click="hapusForm('formDiagnosaKeperawatan','{{ $fa['formDiagnosaKeperawatan_id'] }}')"
                            wire:confirm="Hapus Form A ini?" tooltip="Hapus">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </x-icon-button>
                        @endif
                    </div>
                </div>
                <div class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300">
                    <p class="font-semibold text-brand">{{ $fa['diagnosaKeperawatan']??'-' }}</p>
                    @if (!empty($fa['dataSubyektif']))
                    <p class="mt-1"><span class="font-medium">S:</span> {{ $fa['dataSubyektif'] }}</p>
                    @endif
                    @if (!empty($fa['dataObyektif']))
                    <p><span class="font-medium">O:</span> {{ $fa['dataObyektif'] }}</p>
                    @endif

                    {{-- Form B milik Form A ini --}}
                    @php
                        $formBList = collect($dataDaftarRi['diagKeperawatan']['formIntervensiImplementasi']??[])
                            ->where('formDiagnosaKeperawatan_id', $fa['formDiagnosaKeperawatan_id']);
                    @endphp
                    @if ($formBList->count() > 0)
                    <div class="mt-2 ml-3 space-y-1.5 border-l-2 border-brand/20 pl-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Intervensi & Implementasi</p>
                        @foreach ($formBList as $fb)
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-mono text-gray-400">{{ $fb['tanggal']??'-' }}</span>
                                @if (!empty($fb['intervensi']))
                                <span class="ml-2 text-gray-600 dark:text-gray-300">{{ Str::limit($fb['intervensi'],60) }}</span>
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <x-primary-button wire:click="cetakFormB('{{ $fb['formIntervensiImplementasi_id'] }}')" type="button" class="text-xs">Cetak</x-primary-button>
                                @if (!$isFormLocked)
                                <x-icon-button variant="danger"
                                    wire:click="hapusForm('formIntervensiImplementasi','{{ $fb['formIntervensiImplementasi_id'] }}')"
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
            <p class="text-xs text-center text-gray-400 py-6">Belum ada diagnosa keperawatan.</p>
            @endforelse
        </div>
    </x-border-form>

    {{-- FORM B: INTERVENSI & IMPLEMENTASI --}}
    @if ($showFormB && !$isFormLocked)
    <div id="form-b-section">
    <x-border-form title="Form B — Intervensi & Implementasi" align="start" bgcolor="bg-amber-50 dark:bg-amber-900/10">
        <div class="mt-3 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formIntervensiImplementasi.tanggal"
                        class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formIntervensiImplementasi.tanggal')" />
                </div>
                <x-secondary-button wire:click="setTanggalFormB" type="button">Sekarang</x-secondary-button>
            </div>
            <div>
                <x-input-label value="Referensi Diagnosa (Form A)" />
                <p class="mt-1 text-sm text-brand font-mono">{{ $formIntervensiImplementasi['formDiagnosaKeperawatan_id'] }}</p>
            </div>
            <div>
                <x-input-label value="Intervensi" />
                <x-textarea wire:model="formIntervensiImplementasi.intervensi" class="w-full mt-1" rows="3" placeholder="Rencana intervensi keperawatan..." />
            </div>
            <div>
                <x-input-label value="Implementasi" />
                <x-textarea wire:model="formIntervensiImplementasi.implementasi" class="w-full mt-1" rows="3" placeholder="Implementasi yang dilakukan..." />
            </div>
            <div class="flex justify-end gap-2">
                <x-ghost-button wire:click="$set('showFormB', false)" type="button">Batal</x-ghost-button>
                <x-primary-button wire:click="simpanFormB" type="button">+ Simpan Intervensi & Implementasi</x-primary-button>
            </div>
        </div>
    </x-border-form>
    </div>
    @endif

</div>
