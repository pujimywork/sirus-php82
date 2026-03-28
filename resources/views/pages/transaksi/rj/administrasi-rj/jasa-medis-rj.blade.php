<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-jasa-medis-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    public array $formEntryJasaMedis = [
        'jasaMedisId' => '',
        'jasaMedisDesc' => '',
        'jasaMedisPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        } else {
            $this->dataDaftarPoliRJ['JasaMedis'] = [];
            $this->dataDaftarPoliRJ['LainLain'] = [];
            $this->isFormLocked = false;
        }
    }

    /* ===============================
     | FIND DATA
     =============================== */
    private function findData(int $rjNo): void
    {
        $this->dataDaftarPoliRJ = $this->findDataRJ($rjNo) ?? [];
        $this->dataDaftarPoliRJ['JasaMedis'] ??= [];
        $this->dataDaftarPoliRJ['LainLain'] ??= [];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Patch hanya key JasaMedis + LainLain — key lain tidak tersentuh.
     =============================== */
    private function syncJasaMedisJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        $data['JasaMedis'] = $this->dataDaftarPoliRJ['JasaMedis'] ?? [];
        $data['LainLain'] = $this->dataDaftarPoliRJ['LainLain'] ?? [];

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dataDaftarPoliRJ = $data;
    }

    /* ===============================
     | LOV SELECTED — JASA MEDIS
     =============================== */
    #[On('lov.selected.jasa-medis')]
    public function onJasaMedisSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat memilih jasa medis.');
            return;
        }

        if (!$payload) {
            $this->formEntryJasaMedis['jasaMedisId'] = '';
            $this->formEntryJasaMedis['jasaMedisDesc'] = '';
            $this->formEntryJasaMedis['jasaMedisPrice'] = '';
            return;
        }

        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $this->dataDaftarPoliRJ['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $this->formEntryJasaMedis['jasaMedisId'] = $payload['pact_id'];
        $this->formEntryJasaMedis['jasaMedisDesc'] = $payload['pact_desc'];
        $this->formEntryJasaMedis['jasaMedisPrice'] = $klaimStatus === 'BPJS' ? $payload['pact_price_bpjs'] ?? $payload['pact_price'] : $payload['pact_price'];

        $this->dispatch('focus-input-tarif-jm');
    }

    /* ===============================
     | INSERT JASA MEDIS
     =============================== */
    public function insertJasaMedis(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryJasaMedis.jasaMedisId' => 'bail|required|exists:rsmst_actparamedics,pact_id',
                'formEntryJasaMedis.jasaMedisDesc' => 'bail|required',
                'formEntryJasaMedis.jasaMedisPrice' => 'bail|required|numeric',
            ],
            [
                'formEntryJasaMedis.jasaMedisId.required' => 'ID jasa medis harus diisi.',
                'formEntryJasaMedis.jasaMedisId.exists' => 'ID jasa medis tidak valid.',
                'formEntryJasaMedis.jasaMedisDesc.required' => 'Deskripsi jasa medis harus diisi.',
                'formEntryJasaMedis.jasaMedisPrice.required' => 'Harga jasa medis harus diisi.',
                'formEntryJasaMedis.jasaMedisPrice.numeric' => 'Harga jasa medis harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Insert ke tabel transaksi
                $lastInserted = DB::table('rstxn_rjactparams')->select(DB::raw('nvl(max(pact_dtl)+1,1) as pact_dtl_max'))->first();

                DB::table('rstxn_rjactparams')->insert([
                    'pact_dtl' => $lastInserted->pact_dtl_max,
                    'rj_no' => $this->rjNo,
                    'pact_id' => $this->formEntryJasaMedis['jasaMedisId'],
                    'pact_price' => $this->formEntryJasaMedis['jasaMedisPrice'],
                ]);

                // 3. Tambah ke array lokal
                $this->dataDaftarPoliRJ['JasaMedis'][] = [
                    'JasaMedisId' => $this->formEntryJasaMedis['jasaMedisId'],
                    'JasaMedisDesc' => $this->formEntryJasaMedis['jasaMedisDesc'],
                    'JasaMedisPrice' => $this->formEntryJasaMedis['jasaMedisPrice'],
                    'rjpactDtl' => $lastInserted->pact_dtl_max,
                    'rjNo' => $this->rjNo,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                // 4. Paket lain-lain + obat
                $this->paketLainLainJasaMedis($this->formEntryJasaMedis['jasaMedisId'], $this->rjNo, $lastInserted->pact_dtl_max);
                $this->paketObatJasaMedis($this->formEntryJasaMedis['jasaMedisId'], $this->rjNo, $lastInserted->pact_dtl_max);

                // 5. Sync JSON — row sudah di-lock
                $this->syncJasaMedisJson();
            });

            $this->resetFormEntry();
            $this->dispatch('focus-lov-jasa-medis');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Medis berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE JASA MEDIS
     =============================== */
    public function removeJasaMedis(int $rjpactDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjpactDtl) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Hapus paket lain-lain + obat
                $this->removepaketLainLainJasaMedis($rjpactDtl);
                $this->removepaketObatJasaMedis($rjpactDtl);

                // 3. Hapus dari tabel transaksi
                DB::table('rstxn_rjactparams')->where('pact_dtl', $rjpactDtl)->delete();

                // 4. Hapus dari array lokal
                $this->dataDaftarPoliRJ['JasaMedis'] = collect($this->dataDaftarPoliRJ['JasaMedis'])->where('rjpactDtl', '!=', $rjpactDtl)->values()->toArray();

                // 5. Sync JSON
                $this->syncJasaMedisJson();
            });

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('administrasi-obat-rj.updated');
            $this->dispatch('administrasi-lainlain-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Medis berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PAKET LAIN-LAIN
     | Dipanggil dari dalam transaksi + lock sudah ada di caller.
     =============================== */
    private function paketLainLainJasaMedis(string $pactId, int $rjNo, int $pactDtl): void
    {
        $items = DB::table('rsmst_actparothers')->select('other_id', 'acto_price')->where('pact_id', $pactId)->orderBy('pact_id')->get();

        foreach ($items as $item) {
            $this->insertLainLain($pactId, $rjNo, $pactDtl, $item->other_id, 'Paket JM', $item->acto_price);
        }
    }

    private function insertLainLain(string $pactId, int $rjNo, int $pactDtl, string $otherId, string $otherDesc, $otherPrice): void
    {
        $validator = Validator::make(
            [
                'LainLainId' => $otherId,
                'LainLainDesc' => $otherDesc,
                'LainLainPrice' => $otherPrice,
                'pactId' => $pactId,
                'pactDtl' => $pactDtl,
                'rjNo' => $rjNo,
            ],
            [
                'LainLainId' => 'bail|required|exists:rsmst_others,other_id',
                'LainLainDesc' => 'bail|required',
                'LainLainPrice' => 'bail|required|numeric',
                'pactId' => 'bail|required',
                'pactDtl' => 'bail|required|numeric',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket lain-lain gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjothers')->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();

        DB::table('rstxn_rjothers')->insert([
            'rjo_dtl' => $last->rjo_dtl_max,
            'pact_dtl' => $pactDtl,
            'rj_no' => $rjNo,
            'other_id' => $otherId,
            'other_price' => $otherPrice,
        ]);

        $this->dataDaftarPoliRJ['LainLain'][] = [
            'LainLainId' => $otherId,
            'LainLainDesc' => $otherDesc,
            'LainLainPrice' => $otherPrice,
            'rjotherDtl' => $last->rjo_dtl_max,
            'rjNo' => $rjNo,
            'pact_dtl' => $pactDtl,
        ];
    }

    private function removepaketLainLainJasaMedis(int $rjpactDtl): void
    {
        $items = DB::table('rstxn_rjothers')->select('rjo_dtl')->where('pact_dtl', $rjpactDtl)->get();

        foreach ($items as $item) {
            DB::table('rstxn_rjothers')->where('rjo_dtl', $item->rjo_dtl)->delete();

            $this->dataDaftarPoliRJ['LainLain'] = collect($this->dataDaftarPoliRJ['LainLain'] ?? [])
                ->where('rjotherDtl', '!=', $item->rjo_dtl)
                ->values()
                ->toArray();
        }
    }

    /* ===============================
     | PAKET OBAT
     | Dipanggil dari dalam transaksi + lock sudah ada di caller.
     =============================== */
    private function paketObatJasaMedis(string $pactId, int $rjNo, int $pactDtl): void
    {
        $items = DB::table('rsmst_actparproducts')->join('immst_products', 'immst_products.product_id', 'rsmst_actparproducts.product_id')->select('immst_products.product_id', 'immst_products.product_name', 'immst_products.sales_price', 'rsmst_actparproducts.actprod_qty')->where('pact_id', $pactId)->orderBy('pact_id')->get();

        foreach ($items as $item) {
            $this->insertObat($pactId, $rjNo, $pactDtl, $item->product_id, 'Paket JM ' . $item->product_name, $item->sales_price, $item->actprod_qty);
        }
    }

    private function insertObat(string $pactId, int $rjNo, int $pactDtl, string $productId, string $productName, $price, $qty): void
    {
        $validator = Validator::make(
            [
                'productId' => $productId,
                'productName' => $productName,
                'qty' => $qty,
                'productPrice' => $price,
                'pactDtl' => $pactDtl,
                'pactId' => $pactId,
                'rjNo' => $rjNo,
            ],
            [
                'productId' => 'bail|required|exists:immst_products,product_id',
                'productName' => 'bail|required',
                'qty' => 'bail|required|numeric|min:1',
                'productPrice' => 'bail|required|numeric',
                'pactDtl' => 'bail|required|numeric',
                'pactId' => 'bail|required',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket obat gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjobats')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

        DB::table('rstxn_rjobats')->insert([
            'rjobat_dtl' => $last->rjobat_dtl_max,
            'pact_dtl' => $pactDtl,
            'rj_no' => $rjNo,
            'product_id' => $productId,
            'qty' => $qty,
            'price' => $price,
            'rj_carapakai' => 1,
            'rj_kapsul' => 1,
            'rj_takar' => 'Tablet',
            'catatan_khusus' => '-',
            'exp_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')+30"),
            'etiket_status' => 0,
        ]);
    }

    private function removepaketObatJasaMedis(int $rjpactDtl): void
    {
        $items = DB::table('rstxn_rjobats')->select('rjobat_dtl')->where('pact_dtl', $rjpactDtl)->get();

        foreach ($items as $item) {
            DB::table('rstxn_rjobats')->where('rjobat_dtl', $item->rjobat_dtl)->delete();
        }
    }

    /* ===============================
     | RESET FORM ENTRY
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryJasaMedis']);
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-medis-rj');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-medis-rj', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- FORM INPUT --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-lov-jasa-medis.window="$nextTick(() => $refs.lovJasaMedis?.querySelector('input')?.focus())"
        x-on:focus-input-tarif-jm.window="$nextTick(() => $refs.inputTarif?.focus())">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryJasaMedis['jasaMedisId']))
            <div x-ref="lovJasaMedis">
                <livewire:lov.jasa-medis.lov-jasa-medis target="jasa-medis" label="Jasa Medis"
                    placeholder="Ketik kode/nama jasa medis..."
                    wire:key="lov-jmed-{{ $rjNo }}-{{ $renderVersions['modal-jasa-medis-rj'] ?? 0 }}" />
            </div>
        @else
            <div class="flex items-end gap-3">
                <div class="w-28">
                    <x-input-label value="Kode" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaMedis.jasaMedisId" placeholder="Kode" disabled
                        class="w-full text-sm" />
                    @error('formEntryJasaMedis.jasaMedisId')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex-1">
                    <x-input-label value="Jasa Medis" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaMedis.jasaMedisDesc" placeholder="Jasa Medis" disabled
                        class="w-full text-sm" />
                    @error('formEntryJasaMedis.jasaMedisDesc')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-40">
                    <x-input-label value="Tarif" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaMedis.jasaMedisPrice" placeholder="Tarif"
                        class="w-full text-sm" x-ref="inputTarif" x-init="$nextTick(() => $refs.inputTarif?.focus())"
                        x-on:keyup.enter="$wire.insertJasaMedis(); $nextTick(() => $refs.inputTarif?.focus())" />
                    @error('formEntryJasaMedis.jasaMedisPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertJasaMedis" wire:loading.attr="disabled"
                        wire:target="insertJasaMedis"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertJasaMedis">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertJasaMedis"><x-loading class="w-4 h-4" /></span>
                        Tambah
                    </button>
                    <button type="button" wire:click.prevent="resetFormEntry"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium
                            text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                            border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Batal
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Jasa Medis</h3>
            <x-badge variant="gray">{{ count($dataDaftarPoliRJ['JasaMedis'] ?? []) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Medis</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked)
                            <th class="w-20 px-4 py-3 text-center">Hapus</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarPoliRJ['JasaMedis'] ?? [] as $item)
                        <tr class="transition group hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['JasaMedisId'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['JasaMedisDesc'] }}
                            </td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['JasaMedisPrice']) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeJasaMedis({{ $item['rjpactDtl'] }})"
                                        wire:confirm="Hapus jasa medis ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaMedis({{ $item['rjpactDtl'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 3 : 4 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada jasa medis
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($dataDaftarPoliRJ['JasaMedis']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarPoliRJ['JasaMedis'])->sum('JasaMedisPrice')) }}
                            </td>
                            @if (!$isFormLocked)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
