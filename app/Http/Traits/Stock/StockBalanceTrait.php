<?php

namespace App\Http\Traits\Stock;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trait pengecekan saldo stok untuk lokasi yang punya ledger view di sirus.
 *
 * Pola sirus = LEDGER (bukan state snapshot di kolom STOCK_*):
 *   saldo akhir(tahun) = saldo awal + Σ qty_d - Σ qty_k
 *
 * Cara nambah lokasi / kategori baru:
 *   1. Tambah konstanta nama kategori (kalau kategori baru) — biar tidak ada magic string.
 *   2. Tambah entry di {@see self::LEDGER_CONFIG} dengan key sl_code → ['view', 'saldoTable', 'saldoCol', 'label', 'strict'].
 *   3. Selesai — semua method publik otomatis support lokasi baru tanpa modifikasi.
 *
 * Flag 'strict' per lokasi menentukan kebijakan saat stok kurang dari qty:
 *   strict=true  → insert DIBLOKIR  (mis. Gudang — sumber resmi, tidak boleh minus).
 *   strict=false → insert DIIZINKAN (mis. Apotek RJ/UGD — boleh warn saja,
 *                                    karena posting otoritatif terjadi di telaah resep apotek).
 *
 * Pemakaian:
 *   use App\Http\Traits\Stock\StockBalanceTrait;
 *
 *   // Default medis:
 *   $saldo  = $this->saldoStok('04', 'PROD123');
 *   $sum    = $this->ringkasanStok('02', 'PROD123');           // ['awal','masuk','keluar','akhir']
 *   [$cukup, $avail] = $this->cekStokCukup('02', 'PROD123', 50);
 *
 *   // One-shot policy check (cek saldo + terapkan flag strict):
 *   $r = $this->terapkanKebijakanStok('02', 'PROD123', 50);
 *   // $r = ['boleh' => bool, 'cukup' => bool, 'strict' => bool, 'tersedia' => float]
 *
 *   // Non-medis (pakai konstanta supaya aman dari typo):
 *   $saldo  = $this->saldoStok('04', 'PROD123', null, self::KATEGORI_NONMEDIS);
 *
 *   // Discovery:
 *   $this->daftarLokasi(self::KATEGORI_MEDIS);                 // ['04', '02']
 *   $this->namaLokasi('04');                                   // 'Gudang Medis'
 *   $this->lokasiTerlacak('20');                               // false
 *   $this->stokDiblokirSaatKurang('04');                       // true (gudang strict)
 */
trait StockBalanceTrait
{
    /* ═══════════════════════════════════════════════════════════════
     | Konstanta kategori — pakai ini di caller supaya tidak typo.
     ═══════════════════════════════════════════════════════════════ */
    public const KATEGORI_MEDIS = 'medis';
    public const KATEGORI_NONMEDIS = 'nonmedis';
    public const DEFAULT_KATEGORI = self::KATEGORI_MEDIS;

    /* ═══════════════════════════════════════════════════════════════
     | Single source of truth — mapping ledger per (kategori, sl_code).
     | Tambah lokasi baru → cukup tambah baris di array ini.
     ═══════════════════════════════════════════════════════════════ */
    private const LEDGER_CONFIG = [
        self::KATEGORI_MEDIS => [
            '04' => [
                'view' => 'tkview_iostockwhs',
                'saldoTable' => 'tktxn_saldoawalstocks',
                'saldoCol' => 'sa_stockwh',
                'label' => 'Gudang Medis',
                'strict' => false,  // sumber resmi — stok kurang = block insert
            ],
            '02' => [
                'view' => 'tkview_iostockapts',
                'saldoTable' => 'tktxn_saldoawalstocks',
                'saldoCol' => 'sa_stockapt',
                'label' => 'Apotek',
                'strict' => false, // posting otoritatif di telaah resep — warn saja
            ],
        ],
        self::KATEGORI_NONMEDIS => [
            '04' => [
                'view' => 'tkview_iostockwhsnon',
                'saldoTable' => 'tktxn_saldoawalstocksnon',
                'saldoCol' => 'sa_stockwh',
                'label' => 'Gudang Non-Medis',
                'strict' => false,
            ],
        ],
    ];

    /* ═══════════════════════════════════════════════════════════════
     | Public API — discovery
     ═══════════════════════════════════════════════════════════════ */

    /**
     * Daftar sl_code yang punya ledger view untuk kategori tertentu.
     * Berguna untuk filter LOV / dropdown.
     *
     * @return string[]  Contoh: ['04', '02']
     */
    public function daftarLokasi(string $kategori = self::DEFAULT_KATEGORI): array
    {
        return array_keys(self::LEDGER_CONFIG[$kategori] ?? []);
    }

    /** Apakah sl_code punya ledger view di kategori tersebut. */
    public function lokasiTerlacak(string $slCode, string $kategori = self::DEFAULT_KATEGORI): bool
    {
        return isset(self::LEDGER_CONFIG[$kategori][$slCode]);
    }

    /** Label deskriptif lokasi (mis. 'Gudang Medis'), atau null kalau belum terdaftar. */
    public function namaLokasi(string $slCode, string $kategori = self::DEFAULT_KATEGORI): ?string
    {
        return self::LEDGER_CONFIG[$kategori][$slCode]['label'] ?? null;
    }

    /**
     * Apakah lokasi pakai mode strict — stok kurang dari qty harus memblokir insert/posting.
     * Lokasi tidak terdaftar → dianggap strict (fail-safe: jangan izinkan minus di lokasi misterius).
     */
    public function stokDiblokirSaatKurang(string $slCode, string $kategori = self::DEFAULT_KATEGORI): bool
    {
        return (bool) (self::LEDGER_CONFIG[$kategori][$slCode]['strict'] ?? true);
    }

    /* ═══════════════════════════════════════════════════════════════
     | Public API — saldo & mutasi
     ═══════════════════════════════════════════════════════════════ */

    /** Saldo akhir produk di lokasi pada tahun tertentu (default: tahun berjalan). */
    public function saldoStok(
        string $slCode,
        string $productId,
        ?int $year = null,
        string $kategori = self::DEFAULT_KATEGORI,
    ): float {
        return $this->ringkasanStok($slCode, $productId, $year, $kategori)['akhir'];
    }

    /**
     * Ringkasan saldo tahun berjalan / tahun X.
     *
     * @return array{awal: float, masuk: float, keluar: float, akhir: float}
     */
    public function ringkasanStok(
        string $slCode,
        string $productId,
        ?int $year = null,
        string $kategori = self::DEFAULT_KATEGORI,
    ): array {
        $ledger = $this->ambilKonfigLedger($slCode, $kategori);
        if (!$ledger) {
            return $this->ringkasanKosong();
        }

        $year = $this->tahunDefault($year);

        $awal = (float) (DB::table($ledger['saldoTable'])
            ->where('product_id', $productId)
            ->where('sa_year', $year)
            ->value($ledger['saldoCol']) ?? 0);

        $mut = DB::table($ledger['view'])
            ->where('product_id', $productId)
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [$year])
            ->selectRaw('NVL(SUM(qty_d),0) as masuk, NVL(SUM(qty_k),0) as keluar')
            ->first();

        $masuk = (float) ($mut->masuk ?? 0);
        $keluar = (float) ($mut->keluar ?? 0);

        return [
            'awal' => $awal,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'akhir' => $awal + $masuk - $keluar,
        ];
    }

    /**
     * Daftar mutasi (untuk kartu stock). Urut ASC (oldest first).
     *
     * Setiap row punya: txn_date_display, txn_date, txn_no, txn_status, qty_d, qty_k.
     */
    public function mutasiStok(
        string $slCode,
        string $productId,
        ?int $year = null,
        string $kategori = self::DEFAULT_KATEGORI,
    ): Collection {
        $ledger = $this->ambilKonfigLedger($slCode, $kategori);
        if (!$ledger) {
            return collect();
        }

        $year = $this->tahunDefault($year);

        return DB::table($ledger['view'])
            ->select([
                DB::raw("TO_CHAR(txn_date,'dd/mm/yyyy hh24:mi:ss') as txn_date_display"),
                'txn_date',
                'txn_no',
                'txn_status',
                DB::raw('NVL(qty_d,0) as qty_d'),
                DB::raw('NVL(qty_k,0) as qty_k'),
            ])
            ->where('product_id', $productId)
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [$year])
            ->orderBy('txn_date')
            ->orderBy('txn_no')
            ->get();
    }

    /**
     * Cek apakah saldo cukup untuk qty yang dibutuhkan.
     * Lokasi tanpa ledger view → cukup=false, tersedia=0.
     *
     * @return array{0: bool, 1: float}  [cukup, tersedia]
     */
    public function cekStokCukup(
        string $slCode,
        string $productId,
        float $qty,
        ?int $year = null,
        string $kategori = self::DEFAULT_KATEGORI,
    ): array {
        if (!$this->lokasiTerlacak($slCode, $kategori)) {
            return [false, 0.0];
        }
        $tersedia = $this->saldoStok($slCode, $productId, $year, $kategori);
        return [$tersedia >= $qty, $tersedia];
    }

    /**
     * Terapkan kebijakan stok berdasarkan flag 'strict' di LEDGER_CONFIG.
     * Dipakai oleh caller agar tidak perlu mengulang logika boleh/tidaknya insert
     * di tiap modul (RJ/UGD/RI/Apotek).
     *
     * Hasil:
     *   - 'boleh'    = true → insert/proses diizinkan (cukup, ATAU strict=false).
     *   - 'boleh'    = false → insert/proses harus diblokir (kurang & strict=true).
     *   - 'cukup'    = tersedia >= qty.
     *   - 'strict'   = mode lokasi (true=block-on-shortage).
     *   - 'tersedia' = saldo akhir produk di lokasi.
     *
     * Caller cukup:
     *   $r = $this->terapkanKebijakanStok($sl, $pid, $qty);
     *   if (!$r['boleh']) { $this->dispatch('toast', type:'error', ...); return; }
     *   if (!$r['cukup']) { $this->dispatch('toast', type:'warning', ...); }   // warn-mode
     *
     * @return array{boleh: bool, cukup: bool, strict: bool, tersedia: float}
     */
    public function terapkanKebijakanStok(
        string $slCode,
        string $productId,
        float $qty,
        ?int $year = null,
        string $kategori = self::DEFAULT_KATEGORI,
    ): array {
        [$cukup, $tersedia] = $this->cekStokCukup($slCode, $productId, $qty, $year, $kategori);
        $strict = $this->stokDiblokirSaatKurang($slCode, $kategori);

        return [
            'boleh' => $cukup || !$strict,
            'cukup' => $cukup,
            'strict' => $strict,
            'tersedia' => $tersedia,
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
     | Internal helpers — extracted biar single-purpose & gampang dites.
     ═══════════════════════════════════════════════════════════════ */

    /** Ambil konfigurasi ledger untuk (sl_code, kategori), atau null bila tidak ada. */
    private function ambilKonfigLedger(string $slCode, string $kategori): ?array
    {
        return self::LEDGER_CONFIG[$kategori][$slCode] ?? null;
    }

    /** Normalisasi tahun (null → tahun berjalan), return sebagai string 4-digit. */
    private function tahunDefault(?int $year): string
    {
        return (string) ($year ?? Carbon::now()->year);
    }

    /** Default ringkasan saat lokasi tidak ter-track. */
    private function ringkasanKosong(): array
    {
        return ['awal' => 0.0, 'masuk' => 0.0, 'keluar' => 0.0, 'akhir' => 0.0];
    }
}
