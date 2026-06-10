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
    protected array $renderAreas = ['modal-jasa-dokter-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    /** Status klaim ('BPJS' atau lainnya) — dipakai untuk pricing pas LOV select. */
    public string $klaimStatus = 'UMUM';

    /** Tanggal masuk RI — dipakai untuk hitung exp_date paket obat. */
    public string $riDateStr = '';

    public array $formEntry = [
        'actdDate'       => '',
        'drId'           => '',
        'drName'         => '',
        'jasaDokterId'   => '',
        'jasaDokterDesc' => '',
        'jasaDokterPrice'=> '',
        'jasaDokterQty'  => '1',
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

        $this->formEntry['actdDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->loadRIMeta($this->riHdrNo);
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiJasaDokter'] = [];
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
        $rows = DB::table('rstxn_riactdocs')
            ->join('rsmst_accdocs', 'rsmst_accdocs.accdoc_id', '=', 'rstxn_riactdocs.accdoc_id')
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_riactdocs.dr_id')
            ->select(
                DB::raw("to_char(actd_date, 'dd/mm/yyyy hh24:mi:ss') as actd_date"),
                'rstxn_riactdocs.dr_id',
                'rsmst_doctors.dr_name',
                'rstxn_riactdocs.accdoc_id',
                'rsmst_accdocs.accdoc_desc',
                'rstxn_riactdocs.actd_price',
                'rstxn_riactdocs.actd_qty',
                'rstxn_riactdocs.actd_no',
            )
            ->where('rstxn_riactdocs.rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_riactdocs.actd_date')
            ->get();

        $this->dataDaftarRI['RiJasaDokter'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — DOKTER
     =============================== */
    #[On('lov.selected.dokter-jasa-dokter-ri')]
    public function onDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['drId']   = '';
            $this->formEntry['drName'] = '';
            return;
        }

        $this->formEntry['drId']   = $payload['dr_id'];
        $this->formEntry['drName'] = $payload['dr_name'];
        $this->dispatch('focus-lov-jasa-dokter-ri');
    }

    /* ===============================
     | LOV SELECTED — JASA DOKTER
     =============================== */
    #[On('lov.selected.jasa-dokter-ri')]
    public function onJasaDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['jasaDokterId']    = '';
            $this->formEntry['jasaDokterDesc']  = '';
            $this->formEntry['jasaDokterPrice'] = '';
            return;
        }

        $this->formEntry['jasaDokterId']    = $payload['accdoc_id'];
        $this->formEntry['jasaDokterDesc']  = $payload['accdoc_desc'];
        // LOV khusus RI sudah resolve harga efektif (tarif per kelas kamar / fallback header, sesuai klaim).
        $this->formEntry['jasaDokterPrice'] = (int) ($payload['accdoc_price'] ?? 0);

        $this->dispatch('focus-input-jd-price');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertJasaDokter(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.jasaDokterId'    => 'bail|required|exists:rsmst_accdocs,accdoc_id',
                'formEntry.jasaDokterPrice' => 'bail|required|numeric|min:0',
                'formEntry.jasaDokterQty'   => 'bail|required|numeric|min:1',
                'formEntry.drId'            => 'bail|nullable|exists:rsmst_doctors,dr_id',
            ],
            [
                'formEntry.jasaDokterId.required'    => 'Jasa dokter wajib dipilih.',
                'formEntry.jasaDokterId.exists'      => 'Jasa dokter tidak valid.',
                'formEntry.jasaDokterPrice.required' => 'Tarif wajib diisi.',
                'formEntry.jasaDokterPrice.numeric'  => 'Tarif harus berupa angka.',
                'formEntry.jasaDokterQty.required'   => 'Jumlah wajib diisi.',
                'formEntry.jasaDokterQty.min'        => 'Jumlah minimal 1.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_riactdocs')
                    ->select(DB::raw("nvl(max(actd_no)+1,1) as actd_no_max"))
                    ->first();

                DB::table('rstxn_riactdocs')->insert([
                    'actd_no'    => $last->actd_no_max,
                    'rihdr_no'   => $this->riHdrNo,
                    'dr_id'      => $this->formEntry['drId'] ?: null,
                    'accdoc_id'  => $this->formEntry['jasaDokterId'],
                    'actd_date'  => DB::raw("TO_DATE('" . $this->formEntry['actdDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'actd_price' => $this->formEntry['jasaDokterPrice'],
                    'actd_qty'   => $this->formEntry['jasaDokterQty'],
                ]);

                // Paket lain-lain + obat (insert ke line table sibling — qty mengikuti form).
                $qty = (int) $this->formEntry['jasaDokterQty'];
                $this->paketLainLainJasaDokter($this->formEntry['jasaDokterId'], $this->riHdrNo, $qty);
                $this->paketObatJasaDokter($this->formEntry['jasaDokterId'], $this->riHdrNo, $qty);

                $this->appendAdminLogRI($this->riHdrNo, 'Tambah Jasa Dokter: ' . $this->formEntry['jasaDokterDesc']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('focus-lov-dokter-jasa-dokter-ri');
            $this->dispatch('toast', type: 'success', message: 'Jasa dokter berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeJasaDokter(int $actdNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($actdNo) {
                $this->lockRIRow($this->riHdrNo);

                // Baca dulu accdoc_id + actd_date + actd_qty sebelum delete — buat cleanup paket.
                $row = DB::table('rstxn_riactdocs')
                    ->select(
                        'accdoc_id',
                        DB::raw("to_char(actd_date, 'dd/mm/yyyy hh24:mi:ss') as actd_date_str"),
                        'actd_qty',
                    )
                    ->where('actd_no', $actdNo)
                    ->first();

                if ($row) {
                    $qty = (int) $row->actd_qty;
                    $this->removePaketLainLainJasaDokter($row->accdoc_id, $this->riHdrNo, $row->actd_date_str, $qty);
                    $this->removePaketObatJasaDokter($row->accdoc_id, $this->riHdrNo, $row->actd_date_str, $qty);
                }

                DB::table('rstxn_riactdocs')->where('actd_no', $actdNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Jasa Dokter #' . $actdNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa dokter berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PAKET LAIN-LAIN — insert ke line table rstxn_riothers
     | Catatan: rstxn_riothers tidak punya kolom FK ke actd_no, jadi
     | cleanup pakai composite match (rihdr_no + other_id + price + date)
     | sesuai komposisi yang diinsert oleh paket.
     =============================== */
    private function paketLainLainJasaDokter(string $accdocId, int $riHdrNo, int $qty): void
    {
        $items = DB::table('rsmst_accdocothers')
            ->select('other_id', 'accdother_price')
            ->where('accdoc_id', $accdocId)
            ->orderBy('accdoc_id')
            ->get();

        foreach ($items as $item) {
            // qty form > 1 → insert N baris terpisah (rstxn_riothers tidak punya kolom qty).
            for ($i = 0; $i < $qty; $i++) {
                $this->insertPaketLainLain($riHdrNo, $item->other_id, $item->accdother_price);
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
            'other_date'  => DB::raw("TO_DATE('" . $this->formEntry['actdDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'other_price' => $otherPrice,
        ]);
    }

    /**
     * Cleanup paket lain-lain saat jasa dokter dihapus.
     * Hapus N baris (= qty form) yang match komposit (rihdr_no, other_id, price, date).
     */
    private function removePaketLainLainJasaDokter(string $accdocId, int $riHdrNo, string $dateStr, int $qty): void
    {
        if ($qty < 1) return;

        $items = DB::table('rsmst_accdocothers')
            ->select('other_id', 'accdother_price')
            ->where('accdoc_id', $accdocId)
            ->get();

        foreach ($items as $item) {
            DB::statement(
                "DELETE FROM rstxn_riothers WHERE other_no IN (
                    SELECT other_no FROM rstxn_riothers
                    WHERE rihdr_no = ? AND other_id = ? AND other_price = ?
                      AND other_date = TO_DATE(?, 'dd/mm/yyyy hh24:mi:ss')
                      AND ROWNUM <= ?
                )",
                [$riHdrNo, $item->other_id, $item->accdother_price, $dateStr, $qty],
            );
        }
    }

    /* ===============================
     | PAKET OBAT — insert ke line table rstxn_riobats
     | qty paket master × qty form jasa dokter.
     =============================== */
    private function paketObatJasaDokter(string $accdocId, int $riHdrNo, int $qty): void
    {
        $items = DB::table('rsmst_accdocproducts')
            ->join('immst_products', 'immst_products.product_id', '=', 'rsmst_accdocproducts.product_id')
            ->select('immst_products.product_id', 'immst_products.sales_price', 'rsmst_accdocproducts.accdprod_qty')
            ->where('accdoc_id', $accdocId)
            ->orderBy('accdoc_id')
            ->get();

        foreach ($items as $item) {
            $totalQty = (int) $item->accdprod_qty * $qty;
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
            'riobat_date'  => DB::raw("TO_DATE('" . $this->formEntry['actdDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'riobat_price' => $price,
            'riobat_qty'   => $qty,
        ]);
    }

    /**
     * Cleanup paket obat saat jasa dokter dihapus.
     * Match (rihdr_no, product_id, price, date, qty_total = paket_qty * form_qty).
     */
    private function removePaketObatJasaDokter(string $accdocId, int $riHdrNo, string $dateStr, int $qty): void
    {
        if ($qty < 1) return;

        $items = DB::table('rsmst_accdocproducts')
            ->join('immst_products', 'immst_products.product_id', '=', 'rsmst_accdocproducts.product_id')
            ->select('immst_products.product_id', 'immst_products.sales_price', 'rsmst_accdocproducts.accdprod_qty')
            ->where('accdoc_id', $accdocId)
            ->get();

        foreach ($items as $item) {
            $totalQty = (int) $item->accdprod_qty * $qty;

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

    public function refreshActdDate(): void
    {
        $this->formEntry['actdDate'] = $this->nowFormatted();
        $this->resetErrorBag('formEntry.actdDate');
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['jasaDokterQty'] = '1';
        $this->formEntry['actdDate']      = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-dokter-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-dokter-ri', [$riHdrNo ?? 'new']) }}">

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
        <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40"
            x-data
            x-on:focus-lov-dokter-jasa-dokter-ri.window="$nextTick(() => $refs.lovDokter?.querySelector('input')?.focus())"
            x-on:focus-lov-jasa-dokter-ri.window="$nextTick(() => $refs.lovJasaDokter?.querySelector('input')?.focus())"
            x-on:focus-input-jd-price.window="$nextTick(() => $refs.inputJdPrice?.focus())">

            @if (empty($formEntry['drId']) || empty($formEntry['jasaDokterId']))
                <div class="grid grid-cols-2 gap-3">
                    <div x-ref="lovDokter">
                        <livewire:lov.dokter.lov-dokter target="dokter-jasa-dokter-ri" label="Dokter"
                            placeholder="Ketik kode/nama dokter..."
                            wire:key="lov-dokter-jd-{{ $riHdrNo }}-{{ $renderVersions['modal-jasa-dokter-ri'] ?? 0 }}" />
                    </div>
                    <div x-ref="lovJasaDokter">
                        {{-- LOV khusus RI: harga otomatis sesuai kelas kamar (rsmst_actdclasses) + status klaim --}}
                        <livewire:lov.jasa-dokter.lov-jasa-dokter-ri target="jasa-dokter-ri" label="Jasa Dokter"
                            placeholder="Ketik kode/nama jasa dokter..." :ri-hdr-no="$riHdrNo"
                            wire:key="lov-jd-{{ $riHdrNo }}-{{ $renderVersions['modal-jasa-dokter-ri'] ?? 0 }}" />
                    </div>
                </div>
            @else
                {{-- Baris 1: Tanggal, Dokter, Kode, Jasa Dokter --}}
                <div class="grid grid-cols-12 gap-3 items-end">
                    {{-- Tanggal --}}
                    <div class="col-span-3">
                        <x-input-label value="Tanggal" class="mb-1" />
                        <div class="flex gap-1">
                            <x-text-input wire:model="formEntry.actdDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                class="flex-1 text-sm font-mono min-w-0" />
                            <button type="button" wire:click="refreshActdDate" title="Waktu sekarang"
                                class="shrink-0 px-2 text-muted-soft hover:text-brand-green dark:hover:text-brand-lime transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{-- Dokter --}}
                    <div class="col-span-3">
                        <x-input-label value="Dokter" class="mb-1" />
                        <x-text-input wire:model="formEntry.drName" disabled class="w-full text-sm" />
                    </div>
                    {{-- Kode --}}
                    <div class="col-span-2">
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaDokterId" disabled class="w-full text-sm" />
                    </div>
                    {{-- Nama Jasa Dokter --}}
                    <div class="col-span-4">
                        <x-input-label value="Jasa Dokter" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaDokterDesc" disabled class="w-full text-sm" />
                    </div>
                </div>

                {{-- Baris 2: Tarif, Qty, Tombol --}}
                <div class="grid grid-cols-12 gap-3 items-end mt-3">
                    {{-- Tarif --}}
                    <div class="col-span-2">
                        <x-input-label value="Tarif" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.jasaDokterPrice"
                            x-ref="inputJdPrice"
                            x-on:keydown.enter.prevent="$refs.inputJdQty?.focus()" />
                        @error('formEntry.jasaDokterPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Qty --}}
                    <div class="col-span-3">
                        <x-input-label value="Qty" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.jasaDokterQty"
                            placeholder="1"
                            x-ref="inputJdQty"
                            x-on:keydown.enter.prevent="$el.blur(); $wire.insertJasaDokter()" />
                        @error('formEntry.jasaDokterQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Tombol --}}
                    <div class="col-span-4 flex gap-2 items-end">
                        <x-primary-button wire:click.prevent="insertJasaDokter" wire:loading.attr="disabled"
                            wire:target="insertJasaDokter">
                            <span wire:loading.remove wire:target="insertJasaDokter">Tambah</span>
                            <span wire:loading wire:target="insertJasaDokter"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                        <x-secondary-button wire:click.prevent="resetFormEntry">Batal</x-secondary-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Jasa Dokter</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiJasaDokter'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Dokter</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Dokter</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiJasaDokter'] ?? [] as $item)
                        <tr wire:key="jasa-dokter-ri-{{ $item['actd_no'] ?? $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $item['actd_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs text-muted dark:text-gray-400 whitespace-nowrap">{{ $item['dr_name'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted dark:text-gray-400 whitespace-nowrap">{{ $item['accdoc_id'] }}</td>
                            <td class="px-4 py-3 text-ink dark:text-gray-200 whitespace-nowrap">{{ $item['accdoc_desc'] }}</td>
                            <td class="px-4 py-3 text-right text-body dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['actd_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-body dark:text-gray-300">{{ $item['actd_qty'] ?? 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format(($item['actd_price'] ?? 0) * ($item['actd_qty'] ?? 1)) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <x-outline-button type="button"
                                        wire:click.prevent="removeJasaDokter({{ $item['actd_no'] }})"
                                        wire:confirm="Hapus jasa dokter ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaDokter({{ $item['actd_no'] }})"
                                        class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 7 : 8 }}"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada jasa dokter
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiJasaDokter']))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiJasaDokter'])->sum(fn($i) => ($i['actd_price'] ?? 0) * ($i['actd_qty'] ?? 1))) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
