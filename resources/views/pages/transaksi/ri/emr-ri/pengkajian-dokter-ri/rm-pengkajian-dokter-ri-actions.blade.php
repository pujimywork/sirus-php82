<?php
// resources/views/pages/transaksi/ri/emr-ri/pengkajian-dokter/rm-pengkajian-dokter-ri-actions.blade.php

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

    /* form rekonsiliasi obat */
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
        if (empty($riHdrNo)) return;
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
                'keluhanUtama' => '', 'keluhanTambahan' => '',
                'riwayatPenyakit' => ['sekarang' => '', 'dahulu' => '', 'keluarga' => ''],
                'jenisAlergi' => '', 'rekonsiliasiObat' => [],
            ],
            'fisik'        => '',
            'statusLokalis' => ['deskripsiGambar' => ''],
            'hasilPemeriksaanPenunjang' => ['laboratorium' => '', 'radiologi' => '', 'penunjangLain' => ''],
            'diagnosaAssesment' => ['diagnosaAwal' => ''],
            'rencana' => ['penegakanDiagnosa' => '', 'terapi' => '', 'terapiPulang' => '', 'diet' => '', 'edukasi' => '', 'monitoring' => ''],
            'tandaTanganDokter' => ['dokterPengkaji' => '', 'dokterPengkajiCode' => '', 'jamDokterPengkaji' => ''],
        ];

        $this->incrementVersion('modal-pengkajian-dokter-ri');

        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r' => $riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }
        $this->validate([
            'dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama' => 'required|string|max:1000',
        ], [
            'dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama.required' => 'Keluhan utama wajib diisi.',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['pengkajianDokter'] = $this->dataDaftarRi['pengkajianDokter'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pengkajian Dokter berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
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
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji']     = auth()->user()->myuser_name;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkajiCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji']  = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->store();
    }

    public function addRekonsiliasiObat(): void
    {
        if (empty($this->rekonsiliasiObat['namaObat'])) {
            $this->dispatch('toast', type: 'error', message: 'Nama obat kosong.');
            return;
        }
        $exists = collect($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [])
            ->contains('namaObat', $this->rekonsiliasiObat['namaObat']);
        if ($exists) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada.');
            return;
        }
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'][] = [
            'namaObat' => $this->rekonsiliasiObat['namaObat'],
            'dosis'    => $this->rekonsiliasiObat['dosis'],
            'rute'     => $this->rekonsiliasiObat['rute'],
        ];
        $this->store();
        $this->reset(['rekonsiliasiObat']);
    }

    public function removeRekonsiliasiObat(string $namaObat): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] =
            collect($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [])
                ->reject(fn($o) => $o['namaObat'] === $namaObat)->values()->toArray();
        $this->store();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-pengkajian-dokter-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['rekonsiliasiObat']);
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-pengkajian-dokter-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- ── ANAMNESA ── --}}
    <x-border-form title="Anamnesa" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Keluhan Utama *" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked"
                    :error="$errors->has('dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama')"
                    placeholder="Keluhan utama pasien..." />
                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Keluhan Tambahan" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanTambahan"
                    class="w-full mt-1" rows="2" :disabled="$isFormLocked" placeholder="Keluhan tambahan..." />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Riwayat Penyakit Sekarang" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.sekarang"
                        class="w-full mt-1" rows="3" :disabled="$isFormLocked" />
                </div>
                <div>
                    <x-input-label value="Riwayat Penyakit Dahulu" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.dahulu"
                        class="w-full mt-1" rows="3" :disabled="$isFormLocked" />
                </div>
            </div>
            <div>
                <x-input-label value="Riwayat Penyakit Keluarga" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.keluarga"
                    class="w-full mt-1" rows="2" :disabled="$isFormLocked" />
            </div>
            <div>
                <x-input-label value="Jenis Alergi" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.jenisAlergi"
                    class="w-full mt-1" :disabled="$isFormLocked" placeholder="Alergi obat / makanan..." />
            </div>
        </div>
    </x-border-form>

    {{-- ── REKONSILIASI OBAT ── --}}
    <x-border-form title="Rekonsiliasi Obat" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @if (!$isFormLocked)
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <x-input-label value="Nama Obat" />
                        <x-text-input wire:model="rekonsiliasiObat.namaObat" class="w-full mt-1" placeholder="Nama obat..." />
                    </div>
                    <div>
                        <x-input-label value="Dosis" />
                        <x-text-input wire:model="rekonsiliasiObat.dosis" class="w-full mt-1" placeholder="Contoh: 500mg" />
                    </div>
                    <div>
                        <x-input-label value="Rute" />
                        <x-text-input wire:model="rekonsiliasiObat.rute" class="w-full mt-1" placeholder="Oral / IV / SC..." />
                    </div>
                </div>
                <x-primary-button wire:click="addRekonsiliasiObat" type="button" class="text-xs">+ Tambah Obat</x-primary-button>
            @endif

            @if (!empty($dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Nama Obat</th>
                                <th class="px-3 py-2">Dosis</th>
                                <th class="px-3 py-2">Rute</th>
                                @if (!$isFormLocked)<th class="px-3 py-2 w-10"></th>@endif
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
                                                wire:confirm="Hapus obat {{ $obat['namaObat'] }}?" tooltip="Hapus">
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
                <p class="text-xs text-center text-gray-400 py-3">Belum ada obat.</p>
            @endif
        </div>
    </x-border-form>

    {{-- ── PEMERIKSAAN FISIK ── --}}
    <x-border-form title="Pemeriksaan Fisik" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Status Generalis" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.fisik"
                    class="w-full mt-1" rows="4" :disabled="$isFormLocked"
                    placeholder="Deskripsi pemeriksaan fisik..." />
            </div>
            <div>
                <x-input-label value="Status Lokalis" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.statusLokalis.deskripsiGambar"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked"
                    placeholder="Deskripsi status lokalis..." />
            </div>
        </div>
    </x-border-form>

    {{-- ── HASIL PENUNJANG ── --}}
    <x-border-form title="Hasil Pemeriksaan Penunjang" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 grid grid-cols-3 gap-3">
            <div>
                <x-input-label value="Laboratorium" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked" />
            </div>
            <div>
                <x-input-label value="Radiologi" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.radiologi"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked" />
            </div>
            <div>
                <x-input-label value="Penunjang Lain" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked" />
            </div>
        </div>
    </x-border-form>

    {{-- ── DIAGNOSA & RENCANA ── --}}
    <x-border-form title="Diagnosa & Rencana Terapi" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Diagnosa Awal" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.diagnosaAssesment.diagnosaAwal"
                    class="w-full mt-1" rows="2" :disabled="$isFormLocked" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    ['key'=>'penegakanDiagnosa','label'=>'Penegakan Diagnosa'],
                    ['key'=>'terapi',           'label'=>'Terapi'],
                    ['key'=>'terapiPulang',     'label'=>'Terapi Pulang'],
                    ['key'=>'diet',             'label'=>'Diet'],
                    ['key'=>'edukasi',          'label'=>'Edukasi'],
                    ['key'=>'monitoring',       'label'=>'Monitoring'],
                ] as $field)
                    <div>
                        <x-input-label value="{{ $field['label'] }}" />
                        <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.rencana.{{ $field['key'] }}"
                            class="w-full mt-1" rows="2" :disabled="$isFormLocked" />
                    </div>
                @endforeach
            </div>
        </div>
    </x-border-form>

    {{-- ── TTD DOKTER ── --}}
    <x-border-form title="Tanda Tangan Dokter Pengkaji" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 flex items-center gap-4">
            <div class="flex-1">
                <x-input-label value="Dokter Pengkaji" />
                <x-text-input value="{{ $dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji'] ?? '-' }}"
                    class="w-full mt-1" readonly />
            </div>
            <div class="flex-1">
                <x-input-label value="Jam TTD" />
                <x-text-input value="{{ $dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji'] ?? '-' }}"
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

    @if (!$isFormLocked)
        <div class="flex justify-end pt-2">
            <x-primary-button wire:click="store" type="button">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Pengkajian Dokter
            </x-primary-button>
        </div>
    @endif

</div>
