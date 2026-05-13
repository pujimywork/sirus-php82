<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-jasa-medis-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    /** Status klaim ('BPJS' atau lainnya) — dipakai untuk pricing pas LOV select. */
    public string $klaimStatus = 'UMUM';

    /** Tanggal masuk RI — dipakai untuk hitung exp_date paket obat. */
    public string $riDateStr = '';

    public array $formEntry = [
        'actpDate'      => '',
        'jasaMedisId'   => '',
        'jasaMedisDesc' => '',
        'jasaMedisPrice'=> '',
        'jasaMedisQty'  => '1',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) return;
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        $this->formEntry['actpDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->loadRIMeta($this->riHdrNo);
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiJasaMedis'] = [];
        }
    }

    /**
     * Ambil status klaim (BPJS/UMUM) untuk pricing tarif saat LOV select,
     * dan tanggal masuk RI untuk exp_date paket obat. Pakai findDataRI()
     * di trait yang sudah populate kedua field.
     */
    private function loadRIMeta(int $riHdrNo): void
    {
        $data = $this->findDataRI($riHdrNo);
        $this->klaimStatus = $data['klaimStatus'] ?? 'UMUM';
        $this->riDateStr = $data['entryDate'] ?? '';
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riactparams')
            ->join('rsmst_actparamedics', 'rsmst_actparamedics.pact_id', '=', 'rstxn_riactparams.pact_id')
            ->select(
                DB::raw("to_char(actp_date, 'dd/mm/yyyy hh24:mi:ss') as actp_date"),
                'rstxn_riactparams.pact_id',
                'rsmst_actparamedics.pact_desc',
                'rstxn_riactparams.actp_price',
                'rstxn_riactparams.actp_qty',
                'rstxn_riactparams.actp_no',
            )
            ->where('rstxn_riactparams.rihdr_no', $riHdrNo)
            ->orderByDesc('actp_date')
            ->get();

        $this->dataDaftarRI['RiJasaMedis'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — JASA MEDIS
     =============================== */
    #[On('lov.selected.jasa-medis-ri')]
    public function onJasaMedisSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['jasaMedisId']    = '';
            $this->formEntry['jasaMedisDesc']  = '';
            $this->formEntry['jasaMedisPrice'] = '';
            return;
        }

        $this->formEntry['jasaMedisId']    = $payload['pact_id'];
        $this->formEntry['jasaMedisDesc']  = $payload['pact_desc'];
        $this->formEntry['jasaMedisPrice'] = $this->klaimStatus === 'BPJS' ? ($payload['pact_price_bpjs'] ?? $payload['pact_price']) : $payload['pact_price'];

        $this->dispatch('focus-input-jm-price');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertJasaMedis(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.jasaMedisId'    => 'bail|required|exists:rsmst_actparamedics,pact_id',
                'formEntry.jasaMedisPrice' => 'bail|required|numeric|min:0',
                'formEntry.jasaMedisQty'   => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.jasaMedisId.required'    => 'Jasa medis wajib dipilih.',
                'formEntry.jasaMedisId.exists'      => 'Jasa medis tidak valid.',
                'formEntry.jasaMedisPrice.required' => 'Tarif wajib diisi.',
                'formEntry.jasaMedisPrice.numeric'  => 'Tarif harus berupa angka.',
                'formEntry.jasaMedisQty.required'   => 'Jumlah wajib diisi.',
                'formEntry.jasaMedisQty.min'        => 'Jumlah minimal 1.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_riactparams')
                    ->select(DB::raw("nvl(max(actp_no)+1,1) as actp_no_max"))
                    ->first();

                DB::table('rstxn_riactparams')->insert([
                    'actp_no'    => $last->actp_no_max,
                    'rihdr_no'   => $this->riHdrNo,
                    'pact_id'    => $this->formEntry['jasaMedisId'],
                    'actp_date'  => DB::raw("TO_DATE('" . $this->formEntry['actpDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'actp_price' => $this->formEntry['jasaMedisPrice'],
                    'actp_qty'   => $this->formEntry['jasaMedisQty'],
                ]);

                // Paket lain-lain + obat (insert ke line table sibling — qty mengikuti form).
                $qty = (int) $this->formEntry['jasaMedisQty'];
                $this->paketLainLainJasaMedis($this->formEntry['jasaMedisId'], $this->riHdrNo, $qty);
                $this->paketObatJasaMedis($this->formEntry['jasaMedisId'], $this->riHdrNo, $qty);

                $this->appendAdminLogRI($this->riHdrNo, 'Tambah Jasa Medis: ' . $this->formEntry['jasaMedisDesc']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('focus-lov-jasa-medis-ri');
            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeJasaMedis(int $actpNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($actpNo) {
                $this->lockRIRow($this->riHdrNo);

                // Baca dulu pact_id + actp_date + actp_qty sebelum delete — buat cleanup paket.
                $row = DB::table('rstxn_riactparams')
                    ->select(
                        'pact_id',
                        DB::raw("to_char(actp_date, 'dd/mm/yyyy hh24:mi:ss') as actp_date_str"),
                        'actp_qty',
                    )
                    ->where('actp_no', $actpNo)
                    ->first();

                if ($row) {
                    $qty = (int) $row->actp_qty;
                    $this->removePaketLainLainJasaMedis($row->pact_id, $this->riHdrNo, $row->actp_date_str, $qty);
                    $this->removePaketObatJasaMedis($row->pact_id, $this->riHdrNo, $row->actp_date_str, $qty);
                }

                DB::table('rstxn_riactparams')->where('actp_no', $actpNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Jasa Medis #' . $actpNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PAKET LAIN-LAIN — insert ke line table rstxn_riothers
     | Catatan: rstxn_riothers tidak punya kolom FK ke actp_no, jadi
     | cleanup pakai composite match (rihdr_no + other_id + price + date)
     | sesuai komposisi yang diinsert oleh paket.
     =============================== */
    private function paketLainLainJasaMedis(string $pactId, int $riHdrNo, int $qty): void
    {
        $items = DB::table('rsmst_actparothers')
            ->select('other_id', 'acto_price')
            ->where('pact_id', $pactId)
            ->orderBy('pact_id')
            ->get();

        foreach ($items as $item) {
            // qty form > 1 → insert N baris terpisah (rstxn_riothers tidak punya kolom qty).
            for ($i = 0; $i < $qty; $i++) {
                $this->insertPaketLainLain($riHdrNo, $item->other_id, $item->acto_price);
            }
        }
    }

    private function insertPaketLainLain(int $riHdrNo, string $otherId, $otherPrice): void
    {
        $validator = Validator::make(
            [
                'LainLainId'    => $otherId,
                'LainLainPrice' => $otherPrice,
                'riHdrNo'       => $riHdrNo,
            ],
            [
                'LainLainId'    => 'bail|required|exists:rsmst_others,other_id',
                'LainLainPrice' => 'bail|required|numeric',
                'riHdrNo'       => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket lain-lain gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_riothers')
            ->select(DB::raw('nvl(max(other_no)+1,1) as other_no_max'))
            ->first();

        DB::table('rstxn_riothers')->insert([
            'other_no'    => $last->other_no_max,
            'rihdr_no'    => $riHdrNo,
            'other_id'    => $otherId,
            'other_date'  => DB::raw("TO_DATE('" . $this->formEntry['actpDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'other_price' => $otherPrice,
        ]);
    }

    /**
     * Cleanup paket lain-lain saat jasa medis dihapus.
     * Hapus N baris (= qty form) yang match komposit (rihdr_no, other_id, price, date).
     */
    private function removePaketLainLainJasaMedis(string $pactId, int $riHdrNo, string $dateStr, int $qty): void
    {
        if ($qty < 1) return;

        $items = DB::table('rsmst_actparothers')
            ->select('other_id', 'acto_price')
            ->where('pact_id', $pactId)
            ->get();

        foreach ($items as $item) {
            DB::statement(
                "DELETE FROM rstxn_riothers WHERE other_no IN (
                    SELECT other_no FROM rstxn_riothers
                    WHERE rihdr_no = ? AND other_id = ? AND other_price = ?
                      AND other_date = TO_DATE(?, 'dd/mm/yyyy hh24:mi:ss')
                      AND ROWNUM <= ?
                )",
                [$riHdrNo, $item->other_id, $item->acto_price, $dateStr, $qty],
            );
        }
    }

    /* ===============================
     | PAKET OBAT — insert ke line table rstxn_riobats
     | qty paket master × qty form jasa medis.
     =============================== */
    private function paketObatJasaMedis(string $pactId, int $riHdrNo, int $qty): void
    {
        $items = DB::table('rsmst_actparproducts')
            ->join('immst_products', 'immst_products.product_id', '=', 'rsmst_actparproducts.product_id')
            ->select('immst_products.product_id', 'immst_products.sales_price', 'rsmst_actparproducts.actprod_qty')
            ->where('pact_id', $pactId)
            ->orderBy('pact_id')
            ->get();

        foreach ($items as $item) {
            $totalQty = (int) $item->actprod_qty * $qty;
            $this->insertPaketObat($riHdrNo, $item->product_id, $item->sales_price, $totalQty);
        }
    }

    private function insertPaketObat(int $riHdrNo, string $productId, $price, int $qty): void
    {
        $validator = Validator::make(
            [
                'productId'    => $productId,
                'productPrice' => $price,
                'qty'          => $qty,
                'riHdrNo'      => $riHdrNo,
            ],
            [
                'productId'    => 'bail|required|exists:immst_products,product_id',
                'productPrice' => 'bail|required|numeric',
                'qty'          => 'bail|required|numeric|min:1',
                'riHdrNo'      => 'bail|required|numeric',
            ],
        );

        if ($validator->fails()) {
            throw new \RuntimeException('Validasi paket obat gagal: ' . $validator->errors()->first());
        }

        $last = DB::table('rstxn_riobats')
            ->select(DB::raw('nvl(max(riobat_no)+1,1) as riobat_no_max'))
            ->first();

        DB::table('rstxn_riobats')->insert([
            'riobat_no'    => $last->riobat_no_max,
            'rihdr_no'     => $riHdrNo,
            'product_id'   => $productId,
            'riobat_date'  => DB::raw("TO_DATE('" . $this->formEntry['actpDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'riobat_price' => $price,
            'riobat_qty'   => $qty,
        ]);
    }

    /**
     * Cleanup paket obat saat jasa medis dihapus.
     * Match (rihdr_no, product_id, price, date, qty_total = paket_qty * form_qty).
     */
    private function removePaketObatJasaMedis(string $pactId, int $riHdrNo, string $dateStr, int $qty): void
    {
        if ($qty < 1) return;

        $items = DB::table('rsmst_actparproducts')
            ->join('immst_products', 'immst_products.product_id', '=', 'rsmst_actparproducts.product_id')
            ->select('immst_products.product_id', 'immst_products.sales_price', 'rsmst_actparproducts.actprod_qty')
            ->where('pact_id', $pactId)
            ->get();

        foreach ($items as $item) {
            $totalQty = (int) $item->actprod_qty * $qty;

            // Hapus 1 baris paket (qty obat sudah dijadikan satu baris dengan qty terkalkulasi).
            DB::statement(
                "DELETE FROM rstxn_riobats WHERE riobat_no IN (
                    SELECT riobat_no FROM rstxn_riobats
                    WHERE rihdr_no = ? AND product_id = ? AND riobat_price = ? AND riobat_qty = ?
                      AND riobat_date = TO_DATE(?, 'dd/mm/yyyy hh24:mi:ss')
                      AND ROWNUM <= 1
                )",
                [$riHdrNo, $item->product_id, $item->sales_price, $totalQty, $dateStr],
            );
        }
    }

    public function refreshActpDate(): void
    {
        $this->formEntry['actpDate'] = $this->nowFormatted();
        $this->resetErrorBag('formEntry.actpDate');
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['jasaMedisQty'] = '1';
        $this->formEntry['actpDate']     = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-medis-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-medis-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-jm-price.window="$nextTick(() => $refs.inputJmPrice?.focus())"
            x-on:focus-lov-jasa-medis-ri.window="$nextTick(() => $refs.lovJasaMedis?.querySelector('input')?.focus())">

            @if (empty($formEntry['jasaMedisId']))
                <div x-ref="lovJasaMedis">
                    <livewire:lov.jasa-medis.lov-jasa-medis target="jasa-medis-ri" label="Jasa Medis"
                        placeholder="Ketik kode/nama jasa medis..."
                        wire:key="lov-jm-{{ $riHdrNo }}-{{ $renderVersions['modal-jasa-medis-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="grid grid-cols-12 gap-3 items-end">
                    {{-- Tanggal --}}
                    <div class="col-span-2">
                        <x-input-label value="Tanggal" class="mb-1" />
                        <div class="flex gap-1">
                            <x-text-input wire:model="formEntry.actpDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                class="flex-1 text-sm font-mono min-w-0" />
                            <button type="button" wire:click="refreshActpDate" title="Waktu sekarang"
                                class="shrink-0 px-2 text-gray-400 hover:text-brand-green dark:hover:text-brand-lime transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{-- Kode --}}
                    <div class="col-span-1">
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaMedisId" disabled class="w-full text-sm" />
                    </div>
                    {{-- Nama --}}
                    <div class="col-span-3">
                        <x-input-label value="Jasa Medis" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaMedisDesc" disabled class="w-full text-sm" />
                    </div>
                    {{-- Tarif --}}
                    <div class="col-span-2">
                        <x-input-label value="Tarif" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.jasaMedisPrice"
                            x-ref="inputJmPrice"
                            x-on:keydown.enter.prevent="$refs.inputJmQty?.focus()" />
                        @error('formEntry.jasaMedisPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Qty --}}
                    <div class="col-span-2">
                        <x-input-label value="Qty" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.jasaMedisQty"
                            placeholder="1"
                            x-ref="inputJmQty"
                            x-on:keydown.enter.prevent="$el.blur(); $wire.insertJasaMedis()" />
                        @error('formEntry.jasaMedisQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Buttons --}}
                    <div class="col-span-2 flex gap-2 items-end">
                        <x-primary-button wire:click.prevent="insertJasaMedis" wire:loading.attr="disabled"
                            wire:target="insertJasaMedis">
                            <span wire:loading.remove wire:target="insertJasaMedis">Tambah</span>
                            <span wire:loading wire:target="insertJasaMedis"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                        <x-secondary-button wire:click.prevent="resetFormEntry">Batal</x-secondary-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Jasa Medis</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiJasaMedis'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Medis</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiJasaMedis'] ?? [] as $item)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['actp_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $item['pact_id'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['pact_desc'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['actp_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $item['actp_qty'] ?? 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format(($item['actp_price'] ?? 0) * ($item['actp_qty'] ?? 1)) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeJasaMedis({{ $item['actp_no'] }})"
                                        wire:confirm="Hapus jasa medis ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaMedis({{ $item['actp_no'] }})"
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
                            <td colspan="{{ $isFormLocked ? 6 : 7 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada jasa medis
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiJasaMedis']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiJasaMedis'])->sum(fn($i) => ($i['actp_price'] ?? 0) * ($i['actp_qty'] ?? 1))) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
