<?php
// resources/views/pages/transaksi/ri/emr-ri/obat-dan-cairan-ri/rm-obat-dan-cairan-ri-actions.blade.php

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

    /* ── Form entry ── */
    public array $obatDanCairan = [
        'namaObatAtauJenisCairan' => '',
        'jumlah'                  => '',
        'dosis'                   => '',
        'rute'                    => '',
        'keterangan'              => '',
        'waktuPemberian'          => '',
        'pemeriksa'               => '',
    ];

    /* ── LOV Product (inline — pola asli dipertahankan) ── */
    public array  $dataProductLov             = [];
    public bool   $dataProductLovStatus       = false;
    public string $dataProductLovSearch       = '';
    public int    $selecteddataProductLovIndex = 0;
    public array  $collectingMyProduct        = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-obat-dan-cairan-ri'];

    /* ================================================================
     | MOUNT
     ================================================================ */
    public function mount(): void
    {
        $this->registerAreas(['modal-obat-dan-cairan-ri']);
    }

    /* ================================================================
     | OPEN
     ================================================================ */
    #[On('open-rm-obat-dan-cairan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
        $this->riHdrNo = $riHdrNo;
        $this->resetFormEntry();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->ensureObatNode();

        $this->incrementVersion('modal-obat-dan-cairan-ri');

        $riStatus = DB::scalar(
            "select ri_status from rstxn_rihdrs where rihdr_no = :r",
            ['r' => $riHdrNo]
        );
        $this->isFormLocked = ($riStatus !== 'I');
    }

    /* ================================================================
     | UPDATED — reset LOV kalau nama obat dikosongkan manual
     ================================================================ */
    public function updated(string $propertyName): void
    {
        if (
            $propertyName === 'obatDanCairan.namaObatAtauJenisCairan' &&
            empty($this->obatDanCairan['namaObatAtauJenisCairan'])
        ) {
            $this->resetcollectingMyProduct();
        }
    }

    /* ================================================================
     | SET WAKTU PEMBERIAN
     ================================================================ */
    public function setWaktuPemberian(): void
    {
        $this->obatDanCairan['waktuPemberian'] =
            Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ================================================================
     | ADD OBAT DAN CAIRAN
     ================================================================ */
    public function addObatDanCairan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $this->obatDanCairan['pemeriksa'] = auth()->user()->myuser_name;

        $this->validate([
            'obatDanCairan.namaObatAtauJenisCairan' => 'required',
            'obatDanCairan.jumlah'                  => 'required|numeric',
            'obatDanCairan.dosis'                   => 'required',
            'obatDanCairan.rute'                    => 'required',
            'obatDanCairan.keterangan'              => 'required',
            'obatDanCairan.waktuPemberian'          => 'required|date_format:d/m/Y H:i:s',
            'obatDanCairan.pemeriksa'               => 'required',
        ], [
            'obatDanCairan.namaObatAtauJenisCairan.required' => 'Nama obat / jenis cairan wajib diisi.',
            'obatDanCairan.jumlah.required'                  => 'Jumlah wajib diisi.',
            'obatDanCairan.jumlah.numeric'                   => 'Jumlah harus berupa angka.',
            'obatDanCairan.dosis.required'                   => 'Dosis wajib diisi.',
            'obatDanCairan.rute.required'                    => 'Rute pemberian wajib diisi.',
            'obatDanCairan.keterangan.required'              => 'Keterangan wajib diisi.',
            'obatDanCairan.waktuPemberian.required'          => 'Waktu pemberian wajib diisi.',
            'obatDanCairan.waktuPemberian.date_format'       => 'Format waktu harus d/m/Y H:i:s.',
        ]);

        $target = trim($this->obatDanCairan['waktuPemberian']);

        try {
            $this->withRiLock(function () use ($target) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $this->ensureObatNodeOn($fresh);

                $list = collect($fresh['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [])
                    ->map(fn($r) => is_array($r) ? $r : (array) $r)
                    ->values()
                    ->all();

                // Dedup by waktuPemberian
                $dup = collect($list)->contains(
                    fn($r) => trim((string) ($r['waktuPemberian'] ?? '')) === $target
                );
                if ($dup) {
                    throw new \RuntimeException('Waktu pemberian yang sama sudah ada, gunakan waktu berbeda.');
                }

                $list[] = [
                    'namaObatAtauJenisCairan' => (string) $this->obatDanCairan['namaObatAtauJenisCairan'],
                    'jumlah'                 => (float)  $this->obatDanCairan['jumlah'],
                    'dosis'                  => (string) $this->obatDanCairan['dosis'],
                    'rute'                   => (string) $this->obatDanCairan['rute'],
                    'keterangan'             => (string) $this->obatDanCairan['keterangan'],
                    'waktuPemberian'         => $target,
                    'pemeriksa'              => (string) $this->obatDanCairan['pemeriksa'],
                    /* simpan juga data product dari LOV kalau ada */
                    'productId'   => $this->collectingMyProduct['productId']   ?? '',
                    'productPrice'=> $this->collectingMyProduct['productPrice'] ?? '',
                ];

                $fresh['observasi']['obatDanCairan']['pemberianObatDanCairan'] = array_values($list);
                $fresh['observasi']['obatDanCairan']['pemberianObatDanCairanLog'] = [
                    'userLogDesc' => 'Form Entry obatDanCairan',
                    'userLog'     => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->resetFormEntry();
            $this->afterSave('Obat / Cairan berhasil ditambahkan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ================================================================
     | REMOVE OBAT DAN CAIRAN (by waktuPemberian)
     ================================================================ */
    public function removeObatDanCairan(string $waktuPemberian): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $target = trim($waktuPemberian);

        try {
            $this->withRiLock(function () use ($target) {
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $this->ensureObatNodeOn($fresh);

                $list = collect($fresh['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [])
                    ->map(fn($r) => is_array($r) ? $r : (array) $r)
                    ->values()
                    ->all();

                $removed  = false;
                $filtered = [];
                foreach ($list as $row) {
                    $rowTime = trim((string) ($row['waktuPemberian'] ?? ''));
                    if (!$removed && $rowTime === $target) {
                        $removed = true;
                        continue;
                    }
                    $filtered[] = $row;
                }

                if (!$removed) {
                    throw new \RuntimeException('Data dengan waktu pemberian tersebut tidak ditemukan.');
                }

                $fresh['observasi']['obatDanCairan']['pemberianObatDanCairan'] = array_values($filtered);
                $fresh['observasi']['obatDanCairan']['pemberianObatDanCairanLog'] = [
                    'userLogDesc' => 'Hapus obatDanCairan (by waktuPemberian)',
                    'userLog'     => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('Obat / Cairan berhasil dihapus.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ================================================================
     | LOV PRODUCT (inline — pola asli dipertahankan)
     ================================================================ */
    public function clickdataProductLov(): void
    {
        $this->dataProductLovStatus = true;
        $this->dataProductLov       = [];
    }

    public function updatedDataProductLovSearch(): void
    {
        $this->reset(['selecteddataProductLovIndex', 'dataProductLov']);
        $search = $this->dataProductLovSearch;

        /* Exact match by product_id */
        $exact = DB::table('immst_products')
            ->select('product_id', 'product_name', 'sales_price',
                DB::raw("(select string_agg(cont_desc, ', ')
                          from immst_productcontents z, immst_contents x
                          where z.product_id = immst_products.product_id
                          and z.cont_id = x.cont_id) as product_content"))
            ->where('active_status', '1')
            ->where('product_id', $search)
            ->first();

        if ($exact) {
            $this->addProduct($exact->product_id, $exact->product_name, $exact->sales_price);
            $this->resetdataProductLov();
            return;
        }

        if (strlen($search) < 3) {
            $this->dataProductLov       = [];
            $this->dataProductLovStatus = true;
            return;
        }

        /* Partial search via elasticSearch */
        $rows = DB::select("
            select * from (
                select product_id, product_name, sales_price,
                    (select replace(string_agg(cont_desc),',','')||product_name
                     from immst_productcontents z, immst_contents x
                     where z.product_id = a.product_id and z.cont_id = x.cont_id) elasticsearch,
                    (select string_agg(cont_desc)
                     from immst_productcontents z, immst_contents x
                     where z.product_id = a.product_id and z.cont_id = x.cont_id) product_content
                from immst_products a
                where active_status = '1'
                group by product_id, product_name, sales_price
                order by product_name
            ) where upper(elasticsearch) like '%'||:search||'%'
        ", ['search' => strtoupper($search)]);

        $this->dataProductLov       = json_decode(json_encode($rows, true), true);
        $this->dataProductLovStatus = true;
    }

    public function setMydataProductLov(int $id): void
    {
        if (!isset($this->dataProductLov[$id]['product_id'])) {
            $this->dispatch('toast', type: 'error', message: 'Data obat belum tersedia.');
            return;
        }

        $row = DB::table('immst_products')
            ->select('product_id', 'product_name', 'sales_price')
            ->where('active_status', '1')
            ->where('product_id', $this->dataProductLov[$id]['product_id'])
            ->first();

        $this->addProduct($row->product_id, $row->product_name, $row->sales_price);
        $this->resetdataProductLov();
    }

    public function enterMydataProductLov(int $id): void
    {
        if (isset($this->dataProductLov[$id]['product_id'])) {
            $this->addProduct(
                $this->dataProductLov[$id]['product_id'],
                $this->dataProductLov[$id]['product_name'],
                $this->dataProductLov[$id]['sales_price']
            );
            $this->resetdataProductLov();
        } else {
            $this->dispatch('toast', type: 'error', message: 'Data obat belum tersedia.');
        }
    }

    public function selectNextdataProductLov(): void
    {
        if ($this->selecteddataProductLovIndex === 0) {
            $this->selecteddataProductLovIndex = 0;
        }
        $this->selecteddataProductLovIndex++;
        if ($this->selecteddataProductLovIndex === count($this->dataProductLov)) {
            $this->selecteddataProductLovIndex = 0;
        }
    }

    public function selectPreviousdataProductLov(): void
    {
        $this->selecteddataProductLovIndex--;
        if ($this->selecteddataProductLovIndex === -1) {
            $this->selecteddataProductLovIndex = count($this->dataProductLov) - 1;
        }
    }

    public function resetdataProductLov(): void
    {
        $this->reset(['dataProductLov', 'dataProductLovStatus', 'dataProductLovSearch', 'selecteddataProductLovIndex']);
    }

    private function addProduct(string $productId, string $productName, $salesPrice): void
    {
        $this->collectingMyProduct = [
            'productId'       => $productId,
            'productName'     => $productName,
            'jenisKeterangan' => 'NonRacikan',
            'signaX'          => 1,
            'signaHari'       => 1,
            'qty'             => '',
            'sedia'           => 1,
            'dosis'           => '',
            'productPrice'    => $salesPrice,
            'catatanKhusus'   => '',
        ];
        $this->obatDanCairan['namaObatAtauJenisCairan'] = $productName;
    }

    public function resetcollectingMyProduct(): void
    {
        $this->reset(['collectingMyProduct']);
    }

    /* ================================================================
     | HELPERS
     ================================================================ */
    private function ensureObatNode(): void
    {
        $this->dataDaftarRi['observasi'] ??= [];
        $this->dataDaftarRi['observasi']['obatDanCairan'] ??= [
            'pemberianObatDanCairanTab' => 'Pemberian Obat Dan Cairan',
            'pemberianObatDanCairan'    => [],
        ];
    }

    private function ensureObatNodeOn(array &$data): void
    {
        $data['observasi'] ??= [];
        $data['observasi']['obatDanCairan'] ??= [
            'pemberianObatDanCairanTab' => 'Pemberian Obat Dan Cairan',
            'pemberianObatDanCairan'    => [],
        ];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-obat-dan-cairan-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    private function resetFormEntry(): void
    {
        $this->reset(['obatDanCairan', 'collectingMyProduct']);
        $this->resetdataProductLov();
        $this->resetValidation();
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-obat-dan-cairan-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- ============================================================
    | FORM ENTRY
    ============================================================= --}}
    @if (!$isFormLocked)
    <x-border-form title="Entry Obat / Cairan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">

            {{-- LOV Product --}}
            <div>
                <x-input-label value="Nama Obat / Jenis Cairan *" />
                <div class="relative mt-1">

                    @if (empty($collectingMyProduct))
                        {{-- Mode search --}}
                        <div class="flex gap-2">
                            <x-text-input
                                wire:model.live.debounce.300ms="dataProductLovSearch"
                                class="flex-1"
                                placeholder="Ketik kode atau nama obat/cairan..."
                                wire:keydown.arrow-down.prevent="selectNextdataProductLov"
                                wire:keydown.arrow-up.prevent="selectPreviousdataProductLov"
                                wire:keydown.enter.prevent="enterMydataProductLov({{ $selecteddataProductLovIndex }})" />
                            <x-secondary-button wire:click="clickdataProductLov" type="button">Cari</x-secondary-button>
                        </div>

                        {{-- Dropdown --}}
                        @if ($dataProductLovStatus && count($dataProductLov) > 0)
                        <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg
                                    dark:bg-gray-900 dark:border-gray-700 max-h-64 overflow-y-auto">
                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($dataProductLov as $i => $prod)
                                <li wire:key="prod-{{ $prod['product_id'] }}-{{ $i }}">
                                    <button type="button"
                                        wire:click="setMydataProductLov({{ $i }})"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-brand/5
                                               {{ $i === $selecteddataProductLovIndex ? 'bg-brand/10 font-semibold' : '' }}
                                               dark:hover:bg-gray-800 transition-colors">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $prod['product_name'] ?? '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 space-x-2">
                                            <span>{{ $prod['product_id'] }}</span>
                                            @if (!empty($prod['product_content']))
                                                <span>· {{ $prod['product_content'] }}</span>
                                            @endif
                                        </div>
                                    </button>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        {{-- Input manual jika tidak dari LOV --}}
                        @if (!empty($obatDanCairan['namaObatAtauJenisCairan']) && empty($collectingMyProduct))
                        <x-text-input wire:model.live="obatDanCairan.namaObatAtauJenisCairan"
                            class="w-full mt-1" placeholder="Atau ketik nama langsung..." />
                        @endif

                    @else
                        {{-- Mode selected --}}
                        <div class="flex items-center gap-2">
                            <div class="flex-1 rounded-lg border border-brand/20 bg-brand/5 px-3 py-2.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $collectingMyProduct['productName'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    ID: {{ $collectingMyProduct['productId'] }}
                                    @if (!empty($collectingMyProduct['productPrice']))
                                        · Harga: {{ number_format($collectingMyProduct['productPrice']) }}
                                    @endif
                                </p>
                            </div>
                            <x-secondary-button wire:click="resetcollectingMyProduct" type="button">Ubah</x-secondary-button>
                        </div>
                    @endif

                    <x-input-error :messages="$errors->get('obatDanCairan.namaObatAtauJenisCairan')" class="mt-1" />
                </div>
            </div>

            {{-- Grid detail --}}
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Jumlah *" />
                    <x-text-input wire:model="obatDanCairan.jumlah"
                        class="w-full mt-1" type="number" step="any" placeholder="0"
                        :error="$errors->has('obatDanCairan.jumlah')" />
                    <x-input-error :messages="$errors->get('obatDanCairan.jumlah')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Dosis *" />
                    <x-text-input wire:model="obatDanCairan.dosis"
                        class="w-full mt-1" placeholder="mis: 500mg, 100ml"
                        :error="$errors->has('obatDanCairan.dosis')" />
                    <x-input-error :messages="$errors->get('obatDanCairan.dosis')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Rute Pemberian *" />
                    <x-select-input wire:model="obatDanCairan.rute"
                        class="w-full mt-1"
                        :error="$errors->has('obatDanCairan.rute')">
                        <option value="">— Pilih —</option>
                        @foreach (['Oral','IV Bolus','IV Drip','IM','SC','Sublingual','Topikal','Inhalasi','Suppositoria','Lainnya'] as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('obatDanCairan.rute')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label value="Keterangan *" />
                <x-textarea wire:model="obatDanCairan.keterangan"
                    class="w-full mt-1" rows="2"
                    :error="$errors->has('obatDanCairan.keterangan')"
                    placeholder="Catatan pemberian, reaksi, instruksi khusus..." />
                <x-input-error :messages="$errors->get('obatDanCairan.keterangan')" class="mt-1" />
            </div>

            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Waktu Pemberian *" />
                    <x-text-input wire:model="obatDanCairan.waktuPemberian"
                        class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('obatDanCairan.waktuPemberian')"
                        placeholder="dd/mm/yyyy hh:mm:ss" />
                    <x-input-error :messages="$errors->get('obatDanCairan.waktuPemberian')" class="mt-1" />
                </div>
                <x-secondary-button wire:click="setWaktuPemberian" type="button">Sekarang</x-secondary-button>
            </div>

            <div class="flex justify-end pt-1">
                <x-primary-button wire:click="addObatDanCairan" type="button">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Obat / Cairan
                </x-primary-button>
            </div>

        </div>
    </x-border-form>
    @endif

    {{-- ============================================================
    | LIST PEMBERIAN OBAT DAN CAIRAN
    ============================================================= --}}
    <x-border-form title="Riwayat Pemberian Obat & Cairan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3">

            @php
                $list = $dataDaftarRi['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [];
            @endphp

            @if (count($list) > 0)
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Waktu</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Nama Obat / Cairan</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Jml</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Dosis</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Rute</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Keterangan</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Petugas</th>
                            @if (!$isFormLocked)
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-400 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($list as $idx => $item)
                        <tr wire:key="odc-{{ $idx }}-{{ $this->renderKey('modal-obat-dan-cairan-ri') }}"
                            class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-3 py-2.5 font-mono text-gray-500 whitespace-nowrap">
                                {{ $item['waktuPemberian'] ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5 font-semibold text-gray-900 dark:text-gray-100">
                                {{ $item['namaObatAtauJenisCairan'] ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $item['jumlah'] ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $item['dosis'] ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5">
                                <x-badge variant="default">{{ $item['rute'] ?? '-' }}</x-badge>
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 max-w-xs">
                                {{ $item['keterangan'] ?? '-' }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-500">
                                {{ $item['pemeriksa'] ?? '-' }}
                            </td>
                            @if (!$isFormLocked)
                            <td class="px-3 py-2.5 text-center">
                                <x-icon-button
                                    variant="danger"
                                    wire:click="removeObatDanCairan('{{ $item['waktuPemberian'] }}')"
                                    wire:confirm="Hapus data ini?"
                                    tooltip="Hapus">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                                 L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
            <p wire:key="odc-empty-{{ $this->renderKey('modal-obat-dan-cairan-ri') }}"
               class="text-xs text-center text-gray-400 py-6">
                Belum ada data pemberian obat / cairan.
            </p>
            @endif

        </div>
    </x-border-form>

</div>
