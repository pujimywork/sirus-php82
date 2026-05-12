<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-jasa-dokter-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    /** Daftar jasa dokter dari line table (gabungan sirus-php82 + Oradev 6i). */
    public array $rjJasaDokter = [];

    /** Tanggal RJ — dipakai untuk hitung exp_date paket obat. */
    public string $rjDateStr = '';

    /** Status klaim ('BPJS' atau lainnya) — dipakai untuk pricing pas LOV select. */
    public string $klaimStatus = 'UMUM';

    public array $formEntryJasaDokter = [
        'drId' => '',
        'drName' => '',
        'jasaDokterId' => '',
        'jasaDokterDesc' => '',
        'jasaDokterPrice' => '',
    ];

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('rj.administrasi-selesai')]
    public function onAdministrasiSelesai(int $rjNo): void
    {
        if ((int) ($this->rjNo ?? 0) === $rjNo) {
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->loadRJMeta($this->rjNo);
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        }
    }

    /**
     * Ambil status klaim (BPJS/UMUM) untuk pricing tarif saat LOV select,
     * dan tanggal RJ untuk exp_date paket obat. Pakai findDataRJ() di trait
     * yang sudah populate kedua field dari rsview_rjkasir.
     */
    private function loadRJMeta(int $rjNo): void
    {
        $data = $this->findDataRJ($rjNo);
        $this->klaimStatus = $data['klaimStatus'] ?? 'UMUM';
        $this->rjDateStr = $data['rjDate'] ?? '';
    }

    /* ===============================
     | FIND DATA — langsung dari line table
     =============================== */
    private function findData(int $rjNo): void
    {
        $this->rjJasaDokter = DB::table('rstxn_rjaccdocs as rja')
            ->leftJoin('rsmst_accdocs as rsm', 'rsm.accdoc_id', '=', 'rja.accdoc_id')
            ->leftJoin('rsmst_doctors as dok', 'dok.dr_id', '=', 'rja.dr_id')
            ->where('rja.rj_no', $rjNo)
            ->select('rja.rjhn_dtl', 'rja.dr_id', 'rja.accdoc_id', 'rja.accdoc_price', 'rsm.accdoc_desc', 'dok.dr_name')
            ->orderBy('rja.rjhn_dtl')
            ->get()
            ->map(fn($r) => [
                'DokterId' => $r->dr_id,
                'DokterName' => $r->dr_name ?? '-',
                'JasaDokterId' => $r->accdoc_id,
                'JasaDokterDesc' => $r->accdoc_desc ?? '-',
                'JasaDokterPrice' => (int) $r->accdoc_price,
                'rjaccdocDtl' => (int) $r->rjhn_dtl,
            ])
            ->toArray();
    }

    /* ===============================
     | REFRESH — event dari parent
     =============================== */
    #[On('administrasi-jasa-dokter-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    /* ===============================
     | LOV SELECTED — DOKTER
     =============================== */
    #[On('lov.selected.dokter-jasa-dokter')]
    public function onDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntryJasaDokter['drId'] = '';
            $this->formEntryJasaDokter['drName'] = '';
            return;
        }

        $this->formEntryJasaDokter['drId'] = $payload['dr_id'];
        $this->formEntryJasaDokter['drName'] = $payload['dr_name'];
        $this->dispatch('focus-lov-jasa-dokter');
    }

    /* ===============================
     | LOV SELECTED — JASA DOKTER
     =============================== */
    #[On('lov.selected.jasa-dokter')]
    public function onJasaDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat memilih jasa dokter.');
            return;
        }

        if (!$payload) {
            $this->formEntryJasaDokter['jasaDokterId'] = '';
            $this->formEntryJasaDokter['jasaDokterDesc'] = '';
            $this->formEntryJasaDokter['jasaDokterPrice'] = '';
            return;
        }

        $this->formEntryJasaDokter['jasaDokterId'] = $payload['accdoc_id'];
        $this->formEntryJasaDokter['jasaDokterDesc'] = $payload['accdoc_desc'];
        $this->formEntryJasaDokter['jasaDokterPrice'] = $this->klaimStatus === 'BPJS' ? $payload['accdoc_price_bpjs'] : $payload['accdoc_price'];

        $this->dispatch('focus-input-tarif');
    }

    /* ===============================
     | INSERT JASA DOKTER
     =============================== */
    public function insertJasaDokter(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryJasaDokter.jasaDokterId' => 'bail|required|exists:rsmst_accdocs,accdoc_id',
                'formEntryJasaDokter.jasaDokterDesc' => 'bail|required',
                'formEntryJasaDokter.jasaDokterPrice' => 'bail|required|numeric',
                'formEntryJasaDokter.drId' => 'bail|nullable|exists:rsmst_doctors,dr_id',
            ],
            [
                'formEntryJasaDokter.jasaDokterId.required' => 'ID jasa dokter harus diisi.',
                'formEntryJasaDokter.jasaDokterId.exists' => 'ID jasa dokter tidak valid.',
                'formEntryJasaDokter.jasaDokterDesc.required' => 'Deskripsi jasa dokter harus diisi.',
                'formEntryJasaDokter.jasaDokterPrice.required' => 'Harga jasa dokter harus diisi.',
                'formEntryJasaDokter.jasaDokterPrice.numeric' => 'Harga jasa dokter harus berupa angka.',
                'formEntryJasaDokter.drId.exists' => 'ID dokter tidak valid.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $lastInserted = DB::table('rstxn_rjaccdocs')->select(DB::raw('nvl(max(rjhn_dtl)+1,1) as rjhn_dtl_max'))->first();

                DB::table('rstxn_rjaccdocs')->insert([
                    'rjhn_dtl' => $lastInserted->rjhn_dtl_max,
                    'rj_no' => $this->rjNo,
                    'dr_id' => $this->formEntryJasaDokter['drId'] ?: null,
                    'accdoc_id' => $this->formEntryJasaDokter['jasaDokterId'],
                    'accdoc_price' => $this->formEntryJasaDokter['jasaDokterPrice'],
                ]);

                // Paket lain-lain + obat (insert ke line table only)
                $this->paketLainLainJasaDokter($this->formEntryJasaDokter['jasaDokterId'], $this->rjNo, $lastInserted->rjhn_dtl_max);
                $this->paketObatJasaDokter($this->formEntryJasaDokter['jasaDokterId'], $this->rjNo, $lastInserted->rjhn_dtl_max);

                $this->appendAdminLogRJ($this->rjNo, 'Tambah Jasa Dokter: ' . $this->formEntryJasaDokter['jasaDokterDesc']);
            });

            $this->findData($this->rjNo);

            $this->resetFormEntry();
            $this->dispatch('focus-lov-dokter');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('administrasi-obat-rj.updated');
            $this->dispatch('administrasi-lain-lain-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Dokter berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE JASA DOKTER
     =============================== */
    public function removeJasaDokter(int $rjaccdocDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $itemDesc = collect($this->rjJasaDokter)->firstWhere('rjaccdocDtl', $rjaccdocDtl)['JasaDokterDesc'] ?? '-';

        try {
            DB::transaction(function () use ($rjaccdocDtl, $itemDesc) {
                $this->lockRJRow($this->rjNo);

                $this->removepaketLainLainJasaDokter($rjaccdocDtl);
                $this->removepaketObatJasaDokter($rjaccdocDtl);

                DB::table('rstxn_rjaccdocs')->where('rjhn_dtl', $rjaccdocDtl)->delete();

                $this->appendAdminLogRJ($this->rjNo, 'Hapus Jasa Dokter: ' . $itemDesc . ' #' . $rjaccdocDtl);
            });

            $this->findData($this->rjNo);

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('administrasi-obat-rj.updated');
            $this->dispatch('administrasi-lain-lain-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa Dokter berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PAKET LAIN-LAIN — insert ke line table only
     =============================== */
    private function paketLainLainJasaDokter(string $accdocId, int $rjNo, int $accdocDtl): void
    {
        $items = DB::table('rsmst_accdocothers')->select('other_id', 'accdother_price')->where('accdoc_id', $accdocId)->orderBy('accdoc_id')->get();

        foreach ($items as $item) {
            $this->insertLainLain($rjNo, $accdocDtl, $item->other_id, $item->accdother_price);
        }
    }

    private function insertLainLain(int $rjNo, int $accdocDtl, string $otherId, $otherPrice): void
    {
        $validator = Validator::make(
            [
                'LainLainId' => $otherId,
                'LainLainPrice' => $otherPrice,
                'accdocDtl' => $accdocDtl,
                'rjNo' => $rjNo,
            ],
            [
                'LainLainId' => 'bail|required|exists:rsmst_others,other_id',
                'LainLainPrice' => 'bail|required|numeric',
                'accdocDtl' => 'bail|required|numeric',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket lain-lain gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjothers')->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();

        DB::table('rstxn_rjothers')->insert([
            'rjo_dtl' => $last->rjo_dtl_max,
            'rjhn_dtl' => $accdocDtl,
            'rj_no' => $rjNo,
            'other_id' => $otherId,
            'other_price' => $otherPrice,
        ]);
    }

    private function removepaketLainLainJasaDokter(int $rjaccdocDtl): void
    {
        DB::table('rstxn_rjothers')->where('rjhn_dtl', $rjaccdocDtl)->delete();
    }

    /* ===============================
     | PAKET OBAT — insert ke line table only
     =============================== */
    private function paketObatJasaDokter(string $accdocId, int $rjNo, int $accdocDtl): void
    {
        $items = DB::table('rsmst_accdocproducts')
            ->join('immst_products', 'immst_products.product_id', 'rsmst_accdocproducts.product_id')
            ->select('immst_products.product_id', 'immst_products.sales_price', 'rsmst_accdocproducts.accdprod_qty')
            ->where('accdoc_id', $accdocId)
            ->orderBy('accdoc_id')
            ->get();

        foreach ($items as $item) {
            $this->insertObat($rjNo, $accdocDtl, $item->product_id, $item->sales_price, $item->accdprod_qty);
        }
    }

    private function insertObat(int $rjNo, int $accdocDtl, string $productId, $price, $qty): void
    {
        $validator = Validator::make(
            [
                'productId' => $productId,
                'qty' => $qty,
                'productPrice' => $price,
                'accdocDtl' => $accdocDtl,
                'rjNo' => $rjNo,
            ],
            [
                'productId' => 'bail|required|exists:immst_products,product_id',
                'qty' => 'bail|required|numeric|min:1',
                'productPrice' => 'bail|required|numeric',
                'accdocDtl' => 'bail|required|numeric',
                'rjNo' => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket obat gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_rjobats')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

        DB::table('rstxn_rjobats')->insert([
            'rjobat_dtl' => $last->rjobat_dtl_max,
            'rjhn_dtl' => $accdocDtl,
            'rj_no' => $rjNo,
            'product_id' => $productId,
            'qty' => $qty,
            'price' => $price,
            'rj_carapakai' => 1,
            'rj_kapsul' => 1,
            'rj_takar' => 'Tablet',
            'catatan_khusus' => '-',
            'exp_date' => DB::raw("to_date('" . $this->rjDateStr . "','dd/mm/yyyy hh24:mi:ss')+30"),
            'etiket_status' => 0,
        ]);
    }

    private function removepaketObatJasaDokter(int $rjaccdocDtl): void
    {
        DB::table('rstxn_rjobats')->where('rjhn_dtl', $rjaccdocDtl)->delete();
    }

    /* ===============================
     | RESET FORM ENTRY
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryJasaDokter']);
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-dokter-rj');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-dokter-rj', [$rjNo ?? 'new']) }}">

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
        x-on:focus-lov-jasa-dokter.window="$nextTick(() => $refs.lovJasaDokter?.querySelector('input')?.focus())"
        x-on:focus-input-tarif.window="$nextTick(() => $refs.inputTarif?.focus())">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryJasaDokter['drId']) || empty($formEntryJasaDokter['jasaDokterId']))
            <div class="space-y-3">
                <div class="flex gap-3">
                    <div class="w-64">
                        <livewire:lov.dokter.lov-dokter target="dokter-jasa-dokter" label="Dokter"
                            placeholder="Ketik kode/nama dokter..."
                            wire:key="lov-dokter-jd-{{ $rjNo }}-{{ $renderVersions['modal-jasa-dokter-rj'] ?? 0 }}" />
                    </div>
                    <div class="flex-1" x-ref="lovJasaDokter">
                        <livewire:lov.jasa-dokter.lov-jasa-dokter target="jasa-dokter" label="Jasa Dokter"
                            placeholder="Ketik kode/nama jasa dokter..."
                            wire:key="lov-jasa-dokter-{{ $rjNo }}-{{ $renderVersions['modal-jasa-dokter-rj'] ?? 0 }}" />
                    </div>
                </div>
            </div>
        @else
            <div class="flex items-end gap-3">
                <div class="w-48">
                    <x-input-label value="Dokter" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaDokter.drName" placeholder="Dokter" disabled
                        class="w-full text-sm" />
                </div>
                <div class="w-28">
                    <x-input-label value="Kode" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaDokter.jasaDokterId" placeholder="Kode" disabled
                        class="w-full text-sm" />
                    @error('formEntryJasaDokter.jasaDokterId')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex-1">
                    <x-input-label value="Jasa Dokter" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaDokter.jasaDokterDesc" placeholder="Jasa Dokter" disabled
                        class="w-full text-sm" />
                    @error('formEntryJasaDokter.jasaDokterDesc')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-40">
                    <x-input-label value="Tarif" class="mb-1" />
                    <x-text-input wire:model="formEntryJasaDokter.jasaDokterPrice" placeholder="Tarif"
                        class="w-full text-sm" x-ref="inputTarif" x-init="$nextTick(() => $refs.inputTarif?.focus())"
                        x-on:keyup.enter="$wire.insertJasaDokter(); $nextTick(() => $refs.inputTarif?.focus())" />
                    @error('formEntryJasaDokter.jasaDokterPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertJasaDokter" wire:loading.attr="disabled"
                        wire:target="insertJasaDokter"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertJasaDokter">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertJasaDokter"><x-loading class="w-4 h-4" /></span>
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Jasa Dokter</h3>
            <x-badge variant="gray">{{ count($rjJasaDokter) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Dokter</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Dokter</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked)
                            <th class="w-20 px-4 py-3 text-center">Hapus</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjJasaDokter as $item)
                        <tr class="transition group hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['DokterName'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['JasaDokterId'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['JasaDokterDesc'] }}
                            </td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['JasaDokterPrice']) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeJasaDokter({{ $item['rjaccdocDtl'] }})"
                                        wire:confirm="Hapus jasa dokter ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaDokter({{ $item['rjaccdocDtl'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 4 : 5 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada jasa dokter
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjJasaDokter))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjJasaDokter)->sum('JasaDokterPrice')) }}
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
