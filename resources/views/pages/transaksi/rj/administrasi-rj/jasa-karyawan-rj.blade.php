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
    protected array $renderAreas = ['modal-jasa-karyawan-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    public array $formEntryJasaKaryawan = [
        'jasaKaryawanId' => '',
        'jasaKaryawanDesc' => '',
        'jasaKaryawanPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo && $this->rjNo !== 'new') {
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        } else {
            $this->dataDaftarPoliRJ['JasaKaryawan'] = [];
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
        $this->dataDaftarPoliRJ['JasaKaryawan'] ??= [];
        $this->dataDaftarPoliRJ['LainLain'] ??= [];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Patch hanya key JasaKaryawan + LainLain — key lain tidak tersentuh.
     =============================== */
    private function syncJasaKaryawanJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        $data['JasaKaryawan'] = $this->dataDaftarPoliRJ['JasaKaryawan'] ?? [];
        $data['LainLain'] = $this->dataDaftarPoliRJ['LainLain'] ?? [];

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dataDaftarPoliRJ = $data;
    }

    /* ===============================
     | LOV SELECTED — JASA KARYAWAN
     =============================== */
    #[On('lov.selected.jasa-karyawan')]
    public function onJasaKaryawanSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat memilih jasa karyawan.');
            return;
        }

        if (!$payload) {
            $this->resetFormEntry();
            return;
        }

        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $this->dataDaftarPoliRJ['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $this->formEntryJasaKaryawan['jasaKaryawanId'] = $payload['acte_id'];
        $this->formEntryJasaKaryawan['jasaKaryawanDesc'] = $payload['acte_desc'];
        $this->formEntryJasaKaryawan['jasaKaryawanPrice'] = $klaimStatus === 'BPJS' ? $payload['acte_price_bpjs'] : $payload['acte_price'];
    }

    /* ===============================
     | INSERT JASA KARYAWAN
     =============================== */
    public function insertJasaKaryawan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryJasaKaryawan.jasaKaryawanId' => 'bail|required|exists:rsmst_actemps,acte_id',
                'formEntryJasaKaryawan.jasaKaryawanDesc' => 'bail|required',
                'formEntryJasaKaryawan.jasaKaryawanPrice' => 'bail|required|numeric',
            ],
            [
                'formEntryJasaKaryawan.jasaKaryawanId.required' => 'ID karyawan harus diisi.',
                'formEntryJasaKaryawan.jasaKaryawanId.exists' => 'ID karyawan tidak valid.',
                'formEntryJasaKaryawan.jasaKaryawanDesc.required' => 'Deskripsi jasa harus diisi.',
                'formEntryJasaKaryawan.jasaKaryawanPrice.required' => 'Harga jasa harus diisi.',
                'formEntryJasaKaryawan.jasaKaryawanPrice.numeric' => 'Harga jasa harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Insert ke tabel transaksi
                $lastInserted = DB::table('rstxn_rjactemps')->select(DB::raw('nvl(max(acte_dtl)+1,1) as acte_dtl_max'))->first();

                DB::table('rstxn_rjactemps')->insert([
                    'acte_dtl' => $lastInserted->acte_dtl_max,
                    'rj_no' => $this->rjNo,
                    'acte_id' => $this->formEntryJasaKaryawan['jasaKaryawanId'],
                    'acte_price' => $this->formEntryJasaKaryawan['jasaKaryawanPrice'],
                ]);

                // 3. Tambah ke array lokal
                $this->dataDaftarPoliRJ['JasaKaryawan'][] = [
                    'JasaKaryawanId' => $this->formEntryJasaKaryawan['jasaKaryawanId'],
                    'JasaKaryawanDesc' => $this->formEntryJasaKaryawan['jasaKaryawanDesc'],
                    'JasaKaryawanPrice' => $this->formEntryJasaKaryawan['jasaKaryawanPrice'],
                    'rjActeDtl' => $lastInserted->acte_dtl_max,
                    'rjNo' => $this->rjNo,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                // 4. Paket lain-lain + obat
                $this->paketLainLainJasaKaryawan($this->formEntryJasaKaryawan['jasaKaryawanId'], $this->rjNo, $lastInserted->acte_dtl_max);
                $this->paketObatJasaKaryawan($this->formEntryJasaKaryawan['jasaKaryawanId'], $this->rjNo, $lastInserted->acte_dtl_max);

                // 5. Sync JSON — row sudah di-lock
                $this->syncJasaKaryawanJson();
            });

            $this->resetFormEntry();
            $this->dispatch('focus-lov-jasa-karyawan');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Karyawan berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE JASA KARYAWAN
     =============================== */
    public function removeJasaKaryawan(int $rjActeDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjActeDtl) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Hapus paket lain-lain + obat dari tabel + array lokal
                $this->removepaketLainLainJasaKaryawan($rjActeDtl);
                $this->removepaketObatJasaKaryawan($rjActeDtl);

                // 3. Hapus dari tabel transaksi
                DB::table('rstxn_rjactemps')->where('acte_dtl', $rjActeDtl)->delete();

                // 4. Hapus dari array lokal
                $this->dataDaftarPoliRJ['JasaKaryawan'] = collect($this->dataDaftarPoliRJ['JasaKaryawan'])->where('rjActeDtl', '!=', $rjActeDtl)->values()->toArray();

                // 5. Sync JSON
                $this->syncJasaKaryawanJson();
            });

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('administrasi-obat-rj.updated');
            $this->dispatch('administrasi-lainlain-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Karyawan berhasil dihapus.');
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
    private function paketLainLainJasaKaryawan(string $acteId, int $rjNo, int $acteDtl): void
    {
        $items = DB::table('rsmst_acteothers')->select('other_id', 'acteother_price')->where('acte_id', $acteId)->orderBy('other_id')->get();

        foreach ($items as $item) {
            $this->insertLainLain($acteId, $rjNo, $acteDtl, $item->other_id, 'Paket JK', $item->acteother_price);
        }
    }

    private function insertLainLain(string $acteId, int $rjNo, int $acteDtl, string $otherId, string $otherDesc, $otherPrice): void
    {
        $validator = Validator::make(
            [
                'LainLainId' => $otherId,
                'LainLainDesc' => $otherDesc,
                'LainLainPrice' => $otherPrice,
                'acteId' => $acteId,
                'acteDtl' => $acteDtl,
                'rjNo' => $rjNo,
            ],
            [
                'LainLainId' => 'bail|required|exists:rsmst_others,other_id',
                'LainLainDesc' => 'bail|required',
                'LainLainPrice' => 'bail|required|numeric',
                'acteId' => 'bail|required',
                'acteDtl' => 'bail|required|numeric',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket lain-lain gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjothers')->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();

        DB::table('rstxn_rjothers')->insert([
            'rjo_dtl' => $last->rjo_dtl_max,
            'acte_dtl' => $acteDtl,
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
            'acte_dtl' => $acteDtl,
        ];
    }

    private function removepaketLainLainJasaKaryawan(int $rjActeDtl): void
    {
        $items = DB::table('rstxn_rjothers')->select('rjo_dtl')->where('acte_dtl', $rjActeDtl)->get();

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
    private function paketObatJasaKaryawan(string $acteId, int $rjNo, int $acteDtl): void
    {
        $items = DB::table('rsmst_acteprods')->join('immst_products', 'immst_products.product_id', 'rsmst_acteprods.product_id')->select('immst_products.product_id', 'immst_products.product_name', 'immst_products.sales_price', 'rsmst_acteprods.acteprod_qty')->where('acte_id', $acteId)->orderBy('acte_id')->get();

        foreach ($items as $item) {
            $this->insertObat($acteId, $rjNo, $acteDtl, $item->product_id, 'Paket JK ' . $item->product_name, $item->sales_price, $item->acteprod_qty);
        }
    }

    private function insertObat(string $acteId, int $rjNo, int $acteDtl, string $productId, string $productName, $price, $qty): void
    {
        $validator = Validator::make(
            [
                'productId' => $productId,
                'productName' => $productName,
                'qty' => $qty,
                'productPrice' => $price,
                'acteDtl' => $acteDtl,
                'acteId' => $acteId,
                'rjNo' => $rjNo,
            ],
            [
                'productId' => 'bail|required|exists:immst_products,product_id',
                'productName' => 'bail|required',
                'qty' => 'bail|required|numeric|min:1',
                'productPrice' => 'bail|required|numeric',
                'acteDtl' => 'bail|required|numeric',
                'acteId' => 'bail|required',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket obat gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjobats')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

        DB::table('rstxn_rjobats')->insert([
            'rjobat_dtl' => $last->rjobat_dtl_max,
            'acte_dtl' => $acteDtl,
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

    private function removepaketObatJasaKaryawan(int $rjActeDtl): void
    {
        $items = DB::table('rstxn_rjobats')->select('rjobat_dtl')->where('acte_dtl', $rjActeDtl)->get();

        foreach ($items as $item) {
            DB::table('rstxn_rjobats')->where('rjobat_dtl', $item->rjobat_dtl)->delete();
        }
    }

    /* ===============================
     | RESET FORM ENTRY
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryJasaKaryawan']);
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-karyawan-rj');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-karyawan-rj', [$rjNo ?? 'new']) }}">

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
        x-on:focus-lov-jasa-karyawan.window="$nextTick(() => $refs.lovJasaKaryawan?.querySelector('input')?.focus())">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryJasaKaryawan['jasaKaryawanId']))
            <div x-ref="lovJasaKaryawan">
                <livewire:lov.jasa-karyawan.lov-jasa-karyawan target="jasa-karyawan" label="Cari Jasa Karyawan"
                    placeholder="Ketik kode/nama jasa karyawan..."
                    wire:key="lov-jk-{{ $rjNo }}-{{ $renderVersions['modal-jasa-karyawan-rj'] ?? 0 }}" />
            </div>
        @else
            <div class="flex items-end gap-3" x-data>
                <div class="w-28">
                    <x-input-label value="Kode" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaKaryawan.jasaKaryawanId" placeholder="Kode" disabled
                        class="w-full text-sm" />
                    @error('formEntryJasaKaryawan.jasaKaryawanId')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex-1">
                    <x-input-label value="Jasa Karyawan" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaKaryawan.jasaKaryawanDesc" placeholder="Jasa Karyawan"
                        disabled class="w-full text-sm" />
                    @error('formEntryJasaKaryawan.jasaKaryawanDesc')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-40">
                    <x-input-label value="Tarif" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaKaryawan.jasaKaryawanPrice" placeholder="Tarif"
                        class="w-full text-sm" x-ref="inputTarif" x-init="$nextTick(() => $refs.inputTarif.focus())"
                        x-on:keyup.enter="$wire.insertJasaKaryawan(); $nextTick(() => $refs.inputTarif.focus())" />
                    @error('formEntryJasaKaryawan.jasaKaryawanPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertJasaKaryawan" wire:loading.attr="disabled"
                        wire:target="insertJasaKaryawan"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertJasaKaryawan">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertJasaKaryawan"><x-loading class="w-4 h-4" /></span>
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Jasa Karyawan</h3>
            <x-badge variant="gray">{{ count($dataDaftarPoliRJ['JasaKaryawan'] ?? []) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Karyawan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked)
                            <th class="w-20 px-4 py-3 text-center">Hapus</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarPoliRJ['JasaKaryawan'] ?? [] as $item)
                        <tr class="transition group hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['JasaKaryawanId'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['JasaKaryawanDesc'] }}
                            </td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['JasaKaryawanPrice']) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeJasaKaryawan({{ $item['rjActeDtl'] }})"
                                        wire:confirm="Hapus jasa karyawan ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaKaryawan({{ $item['rjActeDtl'] }})"
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
                                Belum ada jasa karyawan
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($dataDaftarPoliRJ['JasaKaryawan']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp
                                {{ number_format(collect($dataDaftarPoliRJ['JasaKaryawan'])->sum('JasaKaryawanPrice')) }}
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
