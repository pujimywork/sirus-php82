<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Stock\StockBalanceTrait;

new class extends Component {
    use WithRenderVersioningTrait, StockBalanceTrait;

    /** Sumber transfer non-medis selalu Gudang Non-Medis. */
    public const WAREHOUSE_SL_CODE = '04';

    /** Status header IMTXN_TRFHDRNONMEDES. */
    public const STATUS_DRAFT = 'A';
    public const STATUS_POSTED = 'L';
    public const STATUS_BATAL = 'F';

    /** Kategori untuk StockBalanceTrait. */
    private const KATEGORI = 'nonmedis';

    public array $renderVersions = [];
    public string $formMode = 'create';

    // ── Header ──
    public ?int $trfNo = null;
    public ?string $trfDateDisplay = null;
    public ?string $slCodeFrom = null;
    public ?string $slNameFrom = null;
    public ?string $slCodeTo = null;
    public ?string $slNameTo = null;
    public string $trfStatus = self::STATUS_DRAFT;

    // ── Detail entry ──
    public ?string $entryProductId = null;
    public ?string $entryProductName = null;
    public ?int $entryQty = null;
    public ?string $entryExpDate = null;

    /** Saldo lokasi sumber untuk obat yang sedang dipilih di entry form (null = belum pilih). */
    public ?float $stokTersedia = null;

    // ── Daftar detail (in-memory sebelum save) ──
    public array $details = [];
    private int $detailCounter = 0;

    public function mount(): void
    {
        $this->registerAreas(['modal', 'entry', 'tujuan']);
    }

    #[On('transfer-stock-non.openCreate')]
    public function openCreate(string $slCodeFrom = self::WAREHOUSE_SL_CODE): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->trfStatus = self::STATUS_DRAFT;
        $this->trfDateDisplay = Carbon::now()->format('d/m/Y H:i:s');
        $this->slCodeFrom = $slCodeFrom;
        $this->slNameFrom = $this->lookupLocationName($slCodeFrom);

        $this->incrementVersion('modal');
        $this->incrementVersion('tujuan');
        $this->incrementVersion('entry');
        $this->dispatch('open-modal', name: 'transfer-stock-non-actions');
    }

    #[On('transfer-stock-non.openEdit')]
    public function openEdit(int $trfNo): void
    {
        $hdr = DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $trfNo)->first();
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Transfer tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->trfNo = (int) $hdr->trf_no;
        $this->trfStatus = (string) ($hdr->trf_status ?? '');
        $this->formMode = $this->trfStatus === self::STATUS_DRAFT ? 'edit' : 'view';
        $this->trfDateDisplay = Carbon::parse($hdr->trf_date)->format('d/m/Y H:i:s');
        $this->slCodeFrom = (string) $hdr->sl_codefrom;
        $this->slNameFrom = $this->lookupLocationName($this->slCodeFrom);
        $this->slCodeTo = (string) $hdr->sl_codeto;
        $this->slNameTo = $this->lookupLocationName($this->slCodeTo);

        $rows = DB::table('imtxn_trfdtlnonmedes as d')
            ->leftJoin('immst_productsnon as p', 'd.product_id', '=', 'p.product_id')
            ->where('d.trf_no', $trfNo)
            ->orderBy('d.trf_dtl')
            ->get(['d.trf_dtl', 'd.product_id', 'd.qty', 'd.exp_date', DB::raw('p.product_name as product_name')]);

        $this->details = [];
        foreach ($rows as $r) {
            $this->details[] = [
                'key' => 'd-' . (int) $r->trf_dtl,
                'trf_dtl' => (int) $r->trf_dtl,
                'product_id' => (string) $r->product_id,
                'product_name' => (string) ($r->product_name ?? ''),
                'qty' => (float) $r->qty,
                'exp_date' => $r->exp_date ? Carbon::parse($r->exp_date)->format('d/m/Y') : null,
            ];
        }

        $this->incrementVersion('modal');
        $this->incrementVersion('tujuan');
        $this->incrementVersion('entry');
        $this->dispatch('open-modal', name: 'transfer-stock-non-actions');
    }

    /* ══════════════════════════════ BATAL TRANSAKSI (POSTED → BATAL) ══════════════════════════════ */
    #[On('transfer-stock-non.requestBatalTransaksi')]
    public function batalTransaksi(int $trfNo): void
    {
        try {
            DB::transaction(function () use ($trfNo) {
                $hdr = DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $trfNo)->lockForUpdate()->first();
                if (!$hdr) {
                    throw new \DomainException('Transfer tidak ditemukan.');
                }
                if ($hdr->trf_status !== self::STATUS_POSTED) {
                    throw new \DomainException('Hanya transfer yang Sudah Diproses yang bisa dibatalkan.');
                }
                DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $trfNo)->update(['trf_status' => self::STATUS_BATAL]);
            });

            $this->dispatch('toast', type: 'success', message: "Transfer #{$trfNo} dibatalkan — mutasi stok dikembalikan.");
            $this->dispatch('transfer-stock-non.saved');

            if ($this->trfNo === $trfNo) {
                $this->closeModal();
            }
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    #[On('transfer-stock-non.requestBatal')]
    public function batalFromList(int $trfNo): void
    {
        try {
            DB::transaction(function () use ($trfNo) {
                $hdr = DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $trfNo)->lockForUpdate()->first();
                if (!$hdr) {
                    throw new \DomainException('Transfer tidak ditemukan.');
                }
                if ($hdr->trf_status !== self::STATUS_DRAFT) {
                    throw new \DomainException('Hanya transfer Draft yang bisa dihapus.');
                }
                DB::table('imtxn_trfdtlnonmedes')->where('trf_no', $trfNo)->delete();
                DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $trfNo)->delete();
            });

            $this->dispatch('toast', type: 'success', message: "Transfer #{$trfNo} dihapus.");
            $this->dispatch('transfer-stock-non.saved');

            // Tutup modal kalau yang dihapus = transfer yang sedang dibuka.
            if ($this->trfNo === $trfNo) {
                $this->closeModal();
            }
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    #[On('lov.selected.tujuan')]
    public function onPickTujuan(string $target, array $payload): void
    {
        $this->slCodeTo = (string) ($payload['sl_code'] ?? '');
        $this->slNameTo = (string) ($payload['sl_name'] ?? '');
    }

    #[On('lov.selected.entryproduct')]
    public function onPickEntryProduct(string $target, array $payload): void
    {
        $this->entryProductId = (string) ($payload['product_id'] ?? '');
        $this->entryProductName = (string) ($payload['product_name'] ?? '');

        // Muat saldo lokasi sumber begitu barang dipilih — badge stok langsung tampil.
        $this->stokTersedia = $this->entryProductId !== '' && $this->slCodeFrom
            ? $this->saldoStok($this->slCodeFrom, $this->entryProductId, null, self::KATEGORI)
            : null;

        $this->dispatch('focus-entry-qty');
    }

    /**
     * Status pengecekan stok untuk qty yang sedang diketik di entry form.
     *   'idle'   → belum pilih barang
     *   'cukup'  → qty <= stok
     *   'kurang' → qty > stok
     */
    #[Computed]
    public function stokStatus(): string
    {
        if ($this->stokTersedia === null) {
            return 'idle';
        }
        $qty = (float) ($this->entryQty ?? 0);
        return $qty > $this->stokTersedia ? 'kurang' : 'cukup';
    }

    public function addDetail(): void
    {
        if (!$this->entryProductId) {
            $this->dispatch('toast', type: 'error', message: 'Pilih barang dulu.');
            return;
        }
        if (!$this->entryQty || $this->entryQty <= 0) {
            $this->dispatch('toast', type: 'error', message: 'Qty harus > 0.');
            return;
        }

        foreach ($this->details as $d) {
            if ($d['product_id'] === $this->entryProductId) {
                $this->dispatch('toast', type: 'error', message: 'Barang ini sudah ada di daftar.');
                return;
            }
        }

        // Policy stok lokasi sumber — block/warn ikut flag strict di trait (kategori non-medis).
        $policy = $this->terapkanKebijakanStok($this->slCodeFrom, $this->entryProductId, (float) $this->entryQty, null, self::KATEGORI);
        if (!$policy['boleh']) {
            $stokDisplay = rtrim(rtrim(number_format($policy['tersedia'], 2, ',', '.'), '0'), ',');
            $this->dispatch('toast', type: 'error', message: 'Stok ' . $this->namaLokasi($this->slCodeFrom, self::KATEGORI) . ' hanya ' . $stokDisplay . ' — tidak cukup.');
            return;
        }

        $this->detailCounter++;
        $this->details[] = [
            'key' => 'new-' . $this->detailCounter,
            'trf_dtl' => null,
            'product_id' => $this->entryProductId,
            'product_name' => $this->entryProductName,
            'qty' => (float) $this->entryQty,
            'exp_date' => $this->entryExpDate ?: null,
        ];

        if (!$policy['cukup']) {
            $stokDisplay = rtrim(rtrim(number_format($policy['tersedia'], 2, ',', '.'), '0'), ',');
            $this->dispatch('toast', type: 'warning', message: 'Stok ' . $this->namaLokasi($this->slCodeFrom, self::KATEGORI) . ' hanya ' . $stokDisplay . ' — saldo akan minus saat posting.');
        }

        $this->entryProductId = null;
        $this->entryProductName = null;
        $this->entryQty = null;
        $this->entryExpDate = null;
        $this->stokTersedia = null;
        $this->incrementVersion('entry');
        $this->dispatch('focus-entry-product');
    }

    public function removeDetail(int $idx): void
    {
        if (!isset($this->details[$idx])) {
            return;
        }
        array_splice($this->details, $idx, 1);
    }

    public function saveDraft(): void
    {
        try {
            $this->validateBeforeSave();
            $empId = auth()->user()->emp_id ?? 'SYSTEM';

            DB::transaction(function () use ($empId) {
                if ($this->formMode === 'create' || !$this->trfNo) {
                    $nextNo = (int) DB::table('imtxn_trfhdrnonmedes')->max('trf_no') + 1;
                    DB::table('imtxn_trfhdrnonmedes')->insert([
                        'trf_no' => $nextNo,
                        'trf_date' => DB::raw('SYSDATE'),
                        'sl_codefrom' => $this->slCodeFrom,
                        'sl_codeto' => $this->slCodeTo,
                        'trf_status' => self::STATUS_DRAFT,
                        'emp_id' => $empId,
                    ]);
                    $this->trfNo = $nextNo;
                    $this->formMode = 'edit';
                } else {
                    $hdr = DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $this->trfNo)->lockForUpdate()->first();
                    if (!$hdr || $hdr->trf_status !== self::STATUS_DRAFT) {
                        throw new \DomainException('Header sudah tidak Draft — tidak bisa diubah.');
                    }
                    DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $this->trfNo)->update([
                        'sl_codeto' => $this->slCodeTo,
                    ]);
                    DB::table('imtxn_trfdtlnonmedes')->where('trf_no', $this->trfNo)->delete();
                }

                $nextDtl = (int) (DB::table('imtxn_trfdtlnonmedes')->max('trf_dtl') ?? 0);
                foreach ($this->details as $d) {
                    $nextDtl++;
                    DB::table('imtxn_trfdtlnonmedes')->insert([
                        'trf_no' => $this->trfNo,
                        'trf_dtl' => $nextDtl,
                        'product_id' => $d['product_id'],
                        'qty' => $d['qty'],
                        'exp_date' => $this->parseExpDate($d['exp_date']),
                    ]);
                }
            });

            $this->dispatch('toast', type: 'success', message: "Transfer #{$this->trfNo} tersimpan sementara.");
            $this->dispatch('transfer-stock-non.saved');
            $this->closeModal();
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function postTransfer(): void
    {
        try {
            $this->validateBeforeSave();

            $empId = auth()->user()->emp_id ?? null;
            $warnings = [];
            if (!$empId) {
                $empId = 'SYSTEM';
                $warnings[] = 'User belum dimapping ke karyawan. Dicatat sebagai SYSTEM.';
            }

            DB::transaction(function () use ($empId, &$warnings) {
                if ($this->formMode === 'create' || !$this->trfNo) {
                    $nextNo = (int) DB::table('imtxn_trfhdrnonmedes')->max('trf_no') + 1;
                    DB::table('imtxn_trfhdrnonmedes')->insert([
                        'trf_no' => $nextNo,
                        'trf_date' => DB::raw('SYSDATE'),
                        'sl_codefrom' => $this->slCodeFrom,
                        'sl_codeto' => $this->slCodeTo,
                        'trf_status' => self::STATUS_DRAFT,
                        'emp_id' => $empId,
                    ]);
                    $this->trfNo = $nextNo;

                    $nextDtl = (int) (DB::table('imtxn_trfdtlnonmedes')->max('trf_dtl') ?? 0);
                    foreach ($this->details as $d) {
                        $nextDtl++;
                        DB::table('imtxn_trfdtlnonmedes')->insert([
                            'trf_no' => $this->trfNo,
                            'trf_dtl' => $nextDtl,
                            'product_id' => $d['product_id'],
                            'qty' => $d['qty'],
                            'exp_date' => $this->parseExpDate($d['exp_date']),
                        ]);
                    }
                } else {
                    DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $this->trfNo)->update([
                        'sl_codeto' => $this->slCodeTo,
                    ]);
                    DB::table('imtxn_trfdtlnonmedes')->where('trf_no', $this->trfNo)->delete();
                    $nextDtl = (int) (DB::table('imtxn_trfdtlnonmedes')->max('trf_dtl') ?? 0);
                    foreach ($this->details as $d) {
                        $nextDtl++;
                        DB::table('imtxn_trfdtlnonmedes')->insert([
                            'trf_no' => $this->trfNo,
                            'trf_dtl' => $nextDtl,
                            'product_id' => $d['product_id'],
                            'qty' => $d['qty'],
                            'exp_date' => $this->parseExpDate($d['exp_date']),
                        ]);
                    }
                }

                $hdr = DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $this->trfNo)->lockForUpdate()->first();
                if (!$hdr) {
                    throw new \DomainException('Header tidak ditemukan.');
                }
                if ($hdr->trf_status !== self::STATUS_DRAFT) {
                    throw new \DomainException('Hanya transfer Draft yang bisa diposting.');
                }

                // Pengecekan stok per-item sudah dilakukan saat addDetail — tidak diulang di sini.
                $details = DB::table('imtxn_trfdtlnonmedes')->where('trf_no', $this->trfNo)->get();
                if ($details->isEmpty()) {
                    throw new \DomainException('Detail kosong — tambahkan barang dulu.');
                }

                DB::table('imtxn_trfhdrnonmedes')->where('trf_no', $this->trfNo)->update(['trf_status' => self::STATUS_POSTED]);
            });

            if (!empty($warnings)) {
                $this->dispatch(
                    'toast',
                    type: 'warning',
                    message: "Transfer #{$this->trfNo} berhasil diproses dengan peringatan:\n• " . implode("\n• ", $warnings),
                );
            } else {
                $this->dispatch('toast', type: 'success', message: "Transfer #{$this->trfNo} berhasil diproses — stok sudah tercatat.");
            }
            $this->dispatch('transfer-stock-non.saved');
            $this->closeModal();
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal posting: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'transfer-stock-non-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->trfNo = null;
        $this->trfDateDisplay = null;
        $this->slCodeFrom = null;
        $this->slNameFrom = null;
        $this->slCodeTo = null;
        $this->slNameTo = null;
        $this->trfStatus = self::STATUS_DRAFT;
        $this->details = [];
        $this->detailCounter = 0;
        $this->entryProductId = null;
        $this->entryProductName = null;
        $this->entryQty = null;
        $this->entryExpDate = null;
        $this->stokTersedia = null;
    }

    protected function validateBeforeSave(): void
    {
        if (!$this->slCodeFrom || !$this->slCodeTo) {
            throw new \DomainException('Lokasi asal dan tujuan wajib diisi.');
        }
        if ($this->slCodeFrom === $this->slCodeTo) {
            throw new \DomainException('Lokasi asal dan tujuan tidak boleh sama.');
        }
        if ($this->slCodeFrom !== self::WAREHOUSE_SL_CODE) {
            throw new \DomainException('Sumber transfer non-medis hanya dari Gudang Non-Medis (04).');
        }
        if (empty($this->details)) {
            throw new \DomainException('Tambahkan minimal 1 barang ke daftar.');
        }
        foreach ($this->details as $i => $d) {
            if (empty($d['product_id'])) {
                throw new \DomainException('Baris ke-' . ($i + 1) . ': barang kosong.');
            }
            if (!isset($d['qty']) || (float) $d['qty'] <= 0) {
                throw new \DomainException('Baris ke-' . ($i + 1) . ': qty harus > 0.');
            }
        }
    }

    protected function lookupLocationName(string $slCode): string
    {
        return (string) (DB::table('immst_stocklocations')->where('sl_code', $slCode)->value('sl_name') ?? '');
    }

    protected function parseExpDate(?string $display): ?string
    {
        if (!$display) {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $display)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function isReadonly(): bool
    {
        return $this->formMode === 'view';
    }

    #[Computed]
    public function statusLabel(): array
    {
        return match ($this->trfStatus) {
            self::STATUS_DRAFT => ['Belum Diproses', 'alternative'],
            self::STATUS_POSTED => ['Sudah Diproses', 'success'],
            self::STATUS_BATAL => ['Dibatalkan', 'danger'],
            default => [$this->trfStatus ?: '-', 'gray'],
        };
    }
};
?>

<div>
    <x-modal name="transfer-stock-non-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $trfNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                            @if ($formMode === 'create')
                                Buat Transfer Non-Medis Baru
                            @elseif($formMode === 'edit')
                                Edit Transfer Non-Medis #{{ $trfNo }}
                            @else
                                Detail Transfer Non-Medis #{{ $trfNo }}
                            @endif
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            Pindahkan barang non-medis dari {{ $slNameFrom ?? 'Gudang' }} ke lokasi tujuan.
                        </p>
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <x-badge
                                :variant="$formMode === 'create' ? 'success' : ($formMode === 'edit' ? 'warning' : 'gray')">
                                {{ match ($formMode) {
                                    'create' => 'Transfer Baru',
                                    'edit' => 'Sedang Diedit',
                                    default => 'Hanya Lihat',
                                } }}
                            </x-badge>
                            @if ($trfNo)
                                <x-badge :variant="$this->statusLabel[1]">{{ $this->statusLabel[0] }}</x-badge>
                            @endif
                            <span class="text-xs text-muted dark:text-gray-400">{{ $trfDateDisplay }}</span>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-entry-qty.window="$nextTick(() => setTimeout(() => $refs.entryQty?.focus(), 150))"
                x-on:focus-entry-exp.window="$nextTick(() => setTimeout(() => $refs.entryExpDate?.focus(), 150))"
                x-on:focus-entry-product.window="$nextTick(() => setTimeout(() => document.querySelector('[wire\\:key^=lov-entry-product] input')?.focus(), 200))">

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:items-start">
                    {{-- ── Lokasi (2/5) ── --}}
                    <x-border-form title="Lokasi" class="lg:col-span-2">
                        <div class="space-y-3">
                            <div>
                                <x-input-label value="Dari" />
                                <x-text-input type="text"
                                    class="w-full mt-1 bg-surface-soft cursor-not-allowed dark:bg-gray-800"
                                    :value="($slCodeFrom ?? '') . ' — ' . ($slNameFrom ?? '')" disabled />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                    Sumber transfer non-medis selalu Gudang Non-Medis.
                                </p>
                            </div>
                            <div wire:key="{{ $this->renderKey('tujuan') }}">
                                @if ($this->isReadonly)
                                    <x-input-label value="Ke" />
                                    <x-text-input type="text"
                                        class="w-full mt-1 bg-surface-soft cursor-not-allowed dark:bg-gray-800"
                                        :value="($slCodeTo ?? '') . ' — ' . ($slNameTo ?? '')" disabled />
                                @else
                                    <livewire:lov.stocklocation.lov-stocklocation target="tujuan" label="Ke"
                                        placeholder="Pilih lokasi tujuan..." jenisLokasi="nonmedis"
                                        :initialSlCode="$slCodeTo" :excludeSlCode="[$slCodeFrom]"
                                        wire:key="lov-tujuan-{{ $this->renderKey('tujuan') }}" />
                                @endif
                            </div>
                        </div>
                    </x-border-form>

                    {{-- ── Kolom kanan: Tambah + Daftar dalam 1 card (3/5) ── --}}
                    <div class="lg:col-span-3">
                    <x-border-form :title="$this->isReadonly ? 'Daftar Barang (' . count($details) . ')' : 'Tambah Barang — ' . count($details) . ' di daftar'">
                        <div class="space-y-3">
                            @unless ($this->isReadonly)
                                {{-- Input row --}}
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12"
                                    wire:key="{{ $this->renderKey('entry') }}">
                                    <div class="sm:col-span-5">
                                        <livewire:lov.product-non.lov-product-non target="entryproduct" label="Barang"
                                            placeholder="Cari barang non-medis..."
                                            wire:key="lov-entry-product-{{ $this->renderKey('entry') }}" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <x-input-label value="Qty" />
                                        <x-text-input-number wire:model.live.debounce.150ms="entryQty"
                                            x-ref="entryQty" class="mt-1"
                                            x-on:keydown.enter.prevent="$refs.entryExpDate?.focus()" />

                                        {{-- Indikator saldo lokasi sumber — live ketika qty berubah --}}
                                        @if ($this->stokStatus !== 'idle')
                                            @php
                                                $stokDisplay = rtrim(rtrim(number_format((float) $stokTersedia, 2, ',', '.'), '0'), ',');
                                            @endphp
                                            @if ($this->stokStatus === 'cukup')
                                                <div class="flex items-center gap-1 mt-1 text-xs font-medium text-green-700 dark:text-green-400"
                                                    title="Saldo {{ $slNameFrom }}">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Stok: {{ $stokDisplay }}
                                                </div>
                                            @else
                                                <div class="flex items-center gap-1 mt-1 text-xs font-medium text-red-700 dark:text-red-400"
                                                    title="Saldo {{ $slNameFrom }} kurang dari qty diminta">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-3l-7.07-12a2 2 0 00-3.48 0l-7.07 12a2 2 0 001.74 3z" />
                                                    </svg>
                                                    Stok: {{ $stokDisplay }} (kurang)
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="sm:col-span-3">
                                        <x-input-label value="ED (dd/mm/yyyy)" />
                                        <x-text-input wire:model.live="entryExpDate" x-ref="entryExpDate" type="text"
                                            placeholder="dd/mm/yyyy" class="w-full mt-1"
                                            x-on:keydown.enter.prevent="$wire.addDetail()" />
                                    </div>
                                    <div class="flex items-end sm:col-span-2">
                                        <x-primary-button type="button" wire:click="addDetail"
                                            class="w-full whitespace-nowrap">
                                            + Masukkan
                                        </x-primary-button>
                                    </div>
                                </div>

                                <hr class="border-hairline dark:border-gray-700" />
                            @endunless

                            {{-- Daftar Barang (tabel) --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="px-3 py-2 font-semibold">#</th>
                                    <th class="px-3 py-2 font-semibold">KODE</th>
                                    <th class="px-3 py-2 font-semibold">NAMA BARANG</th>
                                    <th class="px-3 py-2 font-semibold text-right">QTY</th>
                                    <th class="px-3 py-2 font-semibold">ED</th>
                                    @unless ($this->isReadonly)
                                        <th class="px-3 py-2 font-semibold">AKSI</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                @forelse ($details as $idx => $d)
                                    <tr wire:key="dtl-{{ $d['key'] }}">
                                        <td class="px-3 py-2 font-mono text-xs text-muted-soft">{{ $idx + 1 }}</td>
                                        <td class="px-3 py-2 font-mono">{{ $d['product_id'] }}</td>
                                        <td class="px-3 py-2">{{ $d['product_name'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-right">
                                            {{ rtrim(rtrim(number_format((float) $d['qty'], 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-3 py-2">{{ $d['exp_date'] ?? '-' }}</td>
                                        @unless ($this->isReadonly)
                                            <td class="px-3 py-2">
                                                <x-confirm-button variant="danger" :action="'removeDetail(' . $idx . ')'"
                                                    title="Hapus Baris" message="Hapus barang ini dari daftar?"
                                                    confirmText="Ya, hapus" cancelText="Batal"
                                                    class="px-2 py-1 text-xs">
                                                    Hapus
                                                </x-confirm-button>
                                            </td>
                                        @endunless
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $this->isReadonly ? 5 : 6 }}"
                                            class="px-3 py-6 text-center text-muted dark:text-gray-400">
                                            Belum ada barang. Tambahkan di atas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                        </div>{{-- /space-y-3 --}}
                    </x-border-form>
                    </div>{{-- /kolom kanan --}}
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    {{-- KIRI: Batal Transaksi (hanya saat view + posted) --}}
                    <div>
                        @if ($formMode === 'view' && $trfStatus === self::STATUS_POSTED && $trfNo)
                            <x-confirm-button variant="danger" :action="'batalTransaksi(' . $trfNo . ')'"
                                title="Batalkan Transaksi"
                                message="Yakin batalkan transaksi ini? Mutasi stok di Kartu Stock akan dikembalikan (saldo asal & tujuan kembali seperti sebelum diproses)."
                                confirmText="Ya, batalkan" cancelText="Batal">
                                Batal Transaksi
                            </x-confirm-button>
                        @endif
                    </div>

                    {{-- KANAN: Tutup + tombol mode edit --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($formMode === 'edit' && $trfNo)
                            <x-confirm-button variant="danger" :action="'batalFromList(' . $trfNo . ')'"
                                title="Hapus Transfer"
                                message="Yakin hapus transfer ini? Header & semua barang di daftar akan dihapus permanen — hanya transfer yang belum diproses."
                                confirmText="Ya, hapus" cancelText="Batal">
                                Hapus Transfer
                            </x-confirm-button>
                        @endif

                        @unless ($this->isReadonly)
                            <x-outline-button type="button" wire:click="saveDraft" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveDraft">Simpan Sementara</span>
                                <span wire:loading wire:target="saveDraft">Menyimpan...</span>
                            </x-outline-button>
                            <x-confirm-button variant="primary" action="postTransfer()" title="Proses Transfer Stok"
                                message="Yakin proses transfer ini? Stok di lokasi asal & tujuan akan langsung tercatat di Kartu Stock."
                                confirmText="Ya, proses" cancelText="Batal">
                                Proses Stok
                            </x-confirm-button>
                        @endunless

                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
