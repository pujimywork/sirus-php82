<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * AppMenu — sumber tunggal definisi menu aplikasi (dashboard + sidebar).
 *
 * - `all()` mengembalikan semua entri (defensive: skip route yang tidak terdaftar)
 * - `forRoles($roles)` filter by role names (lowercase)
 * - `grouped(array $roles)` filter + sort + group by 'group' untuk render UI.
 *
 * Tambah/edit menu di method `definitions()` saja. Dashboard & sidebar otomatis ikut.
 */
class AppMenu
{
    /** Role yang berhak melihat semua menu Master. */
    private const MASTER_ROLES = ['admin', 'manager umum', 'manager medis', 'supervisor penunjang', 'supervisor tu'];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $entry = function (array $m): ?array {
            if (!Route::has($m['route'])) {
                return null;
            }
            $m['href'] = route($m['route']);
            return $m;
        };

        return array_values(array_filter(self::definitions($entry)));
    }

    /**
     * Filter entries by role names (case-insensitive).
     *
     * @param  array<int, string>  $userRoles
     * @return array<int, array<string, mixed>>
     */
    public static function forRoles(array $userRoles): array
    {
        $roles = array_map('strtolower', $userRoles);
        return array_values(array_filter(self::all(), fn($m) => !empty(array_intersect($m['roles'], $roles))));
    }

    /**
     * Sorted + grouped by 'group'. Cocok untuk render sidebar/dashboard.
     *
     * @param  array<int, string>  $userRoles
     */
    public static function grouped(array $userRoles): Collection
    {
        return collect(self::forRoles($userRoles))
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }

    /**
     * Definisi semua menu. Helper $entry guard route existence + inject 'href'.
     * Setiap entry: ['group','groupOrder','order','route','title','desc','roles','badge'].
     *
     * @param  callable(array):?array  $entry
     * @return array<int, ?array<string, mixed>>
     */
    private static function definitions(callable $entry): array
    {
        $masterRoles = self::MASTER_ROLES;

        return [
            // ── Dashboard Manajemen ────────────────────────────────────
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 1, 'route' => 'manajemen.indikator-pelayanan', 'title' => 'Indikator Pelayanan', 'desc' => 'BOR / ALOS / TOI / BTO — tren bulanan & tahunan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KPI']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 2, 'route' => 'manajemen.indikator-penunjang', 'title' => 'Indikator Penunjang', 'desc' => 'Oversight Lab, Radiologi & Apotek — Supervisor Penunjang', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'PNJ']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 3, 'route' => 'manajemen.indikator-tu', 'title' => 'Indikator Tu', 'desc' => 'Oversight Kas, Hutang & Administrasi — Supervisor Tu', 'roles' => ['admin', 'manager umum', 'supervisor tu'], 'badge' => 'TU']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 4, 'route' => 'manajemen.monitoring-keuangan', 'title' => 'Monitoring Keuangan', 'desc' => 'Pendapatan jasa dokter, kas/bank & arus keuangan rumah sakit', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 5, 'route' => 'manajemen.laporan-diagnosa', 'title' => 'Laporan Diagnosa', 'desc' => '10 besar diagnosa, tindakan & mortalitas — bulanan & tahunan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'DX']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 6, 'route' => 'manajemen.mutasi-obat', 'title' => 'Mutasi Obat', 'desc' => 'Keluar masuk obat — bulanan & tahunan, per gudang & per unit', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'OBT']),

            // ── Master Pelayanan ────────────────────────────────────────
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 1, 'route' => 'master.poli', 'title' => 'Master Poli', 'desc' => 'Kelola data poli & ruangan', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 2, 'route' => 'master.dokter', 'title' => 'Master Dokter', 'desc' => 'Kelola data dokter, spesialis, tarif poli/UGD/visit/konsul per kelas', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 3, 'route' => 'master.pasien', 'title' => 'Master Pasien', 'desc' => 'Kelola data pasien & rekam medis', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 4, 'route' => 'master.diagnosa', 'title' => 'Master Diagnosa', 'desc' => 'Kelola data diagnosa ICD-10', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 5, 'route' => 'master.radiologis', 'title' => 'Master Radiologi', 'desc' => 'Kelola data radiologi', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 6, 'route' => 'master.others', 'title' => 'Master Lain-lain', 'desc' => 'Kelola data lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 7, 'route' => 'master.agama', 'title' => 'Master Agama', 'desc' => 'Kelola data agama pasien', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 8, 'route' => 'master.diag-keperawatan', 'title' => 'Master Diagnosis Keperawatan', 'desc' => 'Kelola data SDKI, SLKI, SIKI asuhan keperawatan', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 9, 'route' => 'master.kamar', 'title' => 'Master Kamar', 'desc' => 'Kelola bangsal, kamar & bed rawat inap', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.kelas', 'title' => 'Master Kelas Rawat', 'desc' => 'Kelola kelas kamar & mapping Aplicares / SIRS', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan', 'title' => 'Master Karyawan', 'desc' => 'Kelola NIK karyawan untuk login user & coder iDRG (E-Klaim)', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.setup-jadwal-bpjs', 'title' => 'Pemetaan Jadwal Dokter', 'desc' => 'Ambil & terapkan jadwal praktek dokter dari BPJS ke data RS', 'roles' => [...$masterRoles, 'mr'], 'badge' => 'BPJS']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.stocklocations', 'title' => 'Master Lokasi Stok', 'desc' => 'Kelola kode lokasi stok (gudang, apotek, ruangan, klinik) untuk transfer obat & barang', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.jasa-medis', 'title' => 'Master Jasa Medis', 'desc' => 'Kelola tarif jasa medis (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.jasa-dokter', 'title' => 'Master Jasa Dokter', 'desc' => 'Kelola tarif jasa dokter (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),

            // ── Master Laboratorium ──────────────────────────────────
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kelola kategori lab & item pemeriksaan', 'roles' => $masterRoles, 'badge' => 'Lab']),

            // ── Master Apotek ────────────────────────────────────────
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.obat', 'title' => 'Master Obat', 'desc' => 'Kelola data obat & farmasi', 'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 2, 'route' => 'master.obat-kronis', 'title' => 'Master Obat Kronis', 'desc' => 'Daftar obat kronis BPJS — max qty per resep & tarif klaim', 'roles' => $masterRoles, 'badge' => 'Kronis']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 3, 'route' => 'master.signa-catatan', 'title' => 'Master Catatan Khusus Signa', 'desc' => 'LOV catatan khusus signa untuk e-resep (RJ/UGD/RI)', 'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 4, 'route' => 'master.rekonsiliasi-obat', 'title' => 'Master Rekonsiliasi Obat', 'desc' => 'Kelola kelompok rekonsiliasi obat & produk anggotanya', 'roles' => [...$masterRoles, 'apoteker'], 'badge' => 'Apotek']),

            // ── Master Akuntansi ──────────────────────────────────────
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun', 'title' => 'Master Group Akun', 'desc' => 'Kelola group akun (Aktiva, Kewajiban, Modal, Pendapatan, Beban)', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun', 'title' => 'Master Akun', 'desc' => 'Kelola COA (Chart of Account) — daftar akun & saldo awal', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konfigurasi Akun Transaksi', 'desc' => 'Mapping akun debit/kredit untuk tiap jenis transaksi (RJ, UGD, RI, Resep, dll.)', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),

            // ── Rawat Jalan ────────────────────────────────────────────
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 1, 'route' => 'rawat-jalan.daftar', 'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien poli rawat jalan harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rawat-jalan.pelayanan', 'title' => 'Pelayanan Rawat Jalan', 'desc' => 'EMR poli — anamnesis, pemeriksaan, diagnosa & resep oleh dokter/perawat', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'PEL-RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 3, 'route' => 'rawat-jalan.booking', 'title' => 'Booking Rawat Jalan', 'desc' => 'Pasien booking poli via Mobile JKN — siap diaktivasi jadi pendaftaran', 'roles' => ['admin', 'mr', 'manager umum', 'supervisor tu'], 'badge' => 'BKG']),

            // ── UGD ────────────────────────────────────────────────────
            $entry(['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 1, 'route' => 'ugd.daftar', 'title' => 'Daftar UGD', 'desc' => 'Pendaftaran & manajemen pasien Unit Gawat Darurat harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'UGD']),
            $entry(['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 2, 'route' => 'ugd.pelayanan', 'title' => 'Pelayanan UGD', 'desc' => 'EMR UGD — triase, asesmen, tindakan & resep oleh dokter/perawat UGD', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'PEL-UGD']),

            // ── Rawat Inap ─────────────────────────────────────────────
            $entry(['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 1, 'route' => 'ri.daftar', 'title' => 'Daftar Rawat Inap', 'desc' => 'Pendaftaran, transfer kamar, & manajemen pasien rawat inap', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu', 'apoteker'], 'badge' => 'RI']),
            $entry(['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.update-tt-ri', 'title' => 'Sinkronisasi Tempat Tidur', 'desc' => 'Sync ketersediaan TT rawat inap → Aplicares BPJS & SIRS Kemenkes', 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'TT']),

            // ── Casemix ──────────────────────────────────────
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 1, 'route' => 'transaksi.casemix', 'title' => 'Casemix', 'desc' => 'Daftar pasien bulanan — tab RJ, UGD, RI dalam 1 halaman', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'CSX']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 2, 'route' => 'rawat-jalan.daftar-bulanan', 'title' => 'Daftar Pasien Bulanan RJ', 'desc' => 'List pasien rawat jalan per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RJ-BLN']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 3, 'route' => 'ugd.daftar-bulanan', 'title' => 'Daftar Pasien Bulanan UGD', 'desc' => 'List pasien UGD per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'UGD-BLN']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 4, 'route' => 'ri.daftar-bulanan', 'title' => 'Daftar Pasien Bulanan RI', 'desc' => 'List pasien RI per bulan berdasarkan tgl pulang', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RI-BLN']),

            // ── Apotek (transaksi) ──────────────────────────────────
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 1, 'route' => 'transaksi.apotek', 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 2, 'route' => 'transaksi.rj.antrian-apotek-rj', 'title' => 'Antrian Apotek RJ', 'desc' => 'Antrian apotek khusus pasien rawat jalan', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RJ']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 3, 'route' => 'transaksi.ugd.antrian-apotek-ugd', 'title' => 'Antrian Apotek UGD', 'desc' => 'Antrian apotek khusus pasien UGD', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-UGD']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 4, 'route' => 'transaksi.ri-resep.antrian-ri-resep', 'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat inap', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RI']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 5, 'route' => 'ri.pto', 'title' => 'Pemantauan Terapi Obat (PTO) Rawat Inap', 'desc' => 'Pantau seluruh terapi obat pasien rawat inap dari e-resep — khusus apoteker', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'PTO']),

            // ── Kasir ─────────────────────────────────────────────────
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 1, 'route' => 'transaksi.kasir', 'title' => 'Antrian Kasir', 'desc' => 'Kasir — tab RJ, UGD, RI', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 2, 'route' => 'transaksi.rj.antrian-kasir-rj', 'title' => 'Antrian Kasir RJ', 'desc' => 'Antrian kasir khusus pasien rawat jalan', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RJ']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 3, 'route' => 'transaksi.ugd.antrian-kasir-ugd', 'title' => 'Antrian Kasir UGD', 'desc' => 'Antrian kasir khusus pasien UGD', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-UGD']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 4, 'route' => 'transaksi.kasir.antrian-kasir-ri', 'title' => 'Antrian Kasir RI', 'desc' => 'Kasir rawat inap', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RI']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 5, 'route' => 'transaksi.kasir.daftar-kasir-ri', 'title' => 'Daftar Pasien RI', 'desc' => 'List pasien rawat inap untuk administrasi & pembayaran', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-DAFTAR']),

            // ── Penunjang ──────────────────────────────────────────────
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 1, 'route' => 'transaksi.penunjang.laborat', 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 2, 'route' => 'transaksi.penunjang.laborat.lab-luar', 'title' => 'Upload Hasil Lab Luar', 'desc' => 'Upload PDF hasil lab luar yang sudah Selesai', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB-LUAR']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 3, 'route' => 'transaksi.penunjang.radiologi.upload', 'title' => 'Upload Hasil Radiologi', 'desc' => 'Upload foto radiologi & hasil bacaan PDF', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'radiologi'], 'badge' => 'RAD']),

            // ── Gudang ────────────────────────────────────────────────
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'RCV']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'title' => 'Barang dari Supplier (Non-Medis)', 'desc' => 'Penerimaan barang non-medis dari supplier', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'RCV-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 3, 'route' => 'gudang.transfer-stock', 'title' => 'Transfer Stok Medis', 'desc' => 'Pindahkan obat antar lokasi — sumber dari Gudang Medis atau Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'TRF']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 4, 'route' => 'gudang.transfer-stock-non', 'title' => 'Transfer Stok Non-Medis', 'desc' => 'Pindahkan barang non-medis (ATK, RT, dll.) dari Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'TRF-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 5, 'route' => 'gudang.kartu-stock', 'title' => 'Kartu Stock — Gudang Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 6, 'route' => 'gudang.kartu-stock-apt', 'title' => 'Kartu Stock — Apotek', 'desc' => 'Riwayat mutasi stok per produk — Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK-A']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 7, 'route' => 'gudang.kartu-stock-non', 'title' => 'Kartu Stock — Non-Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'STK-N']),

            // ── Keuangan ──────────────────────────────────────────────
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu', 'title' => 'Penerimaan Kas TU', 'desc' => 'Catat penerimaan kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CI']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu', 'title' => 'Pengeluaran Kas TU', 'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CO']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 3, 'route' => 'keuangan.pembayaran-hutang-pbf', 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 4, 'route' => 'keuangan.pembayaran-hutang-non-medis', 'title' => 'Pembayaran Hutang Non-Medis', 'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 5, 'route' => 'keuangan.topup-supplier-pbf', 'title' => 'Setor DP Supplier PBF', 'desc' => 'Setor uang muka / DP ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 6, 'route' => 'keuangan.topup-supplier-non-medis', 'title' => 'Setor DP Supplier Non-Medis', 'desc' => 'Setor uang muka / DP ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 7, 'route' => 'keuangan.saldo-kas', 'title' => 'Saldo Kas', 'desc' => 'Posisi saldo kas/bank per tanggal — riwayat transaksi & edit saldo awal tahun (admin)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'SK']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 8, 'route' => 'keuangan.buku-besar', 'title' => 'Buku Besar', 'desc' => 'Mutasi & saldo per akun dalam periode tertentu', 'roles' => ['admin', 'manager umum'], 'badge' => 'BB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 9, 'route' => 'keuangan.laba-rugi', 'title' => 'Laporan Laba Rugi', 'desc' => 'Pendapatan vs beban — laba/rugi periode berjalan', 'roles' => ['admin', 'manager umum'], 'badge' => 'LR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 10, 'route' => 'keuangan.neraca', 'title' => 'Laporan Neraca', 'desc' => 'Posisi aktiva, kewajiban & modal per tanggal', 'roles' => ['admin', 'manager umum'], 'badge' => 'NRC']),

            // ── Operasi ────────────────────────────────────────────────
            $entry(['group' => 'Operasi', 'groupOrder' => 13, 'order' => 1, 'route' => 'operasi.jadwal-operasi', 'title' => 'Jadwal Operasi', 'desc' => 'Booking & manajemen jadwal operasi pasien', 'roles' => ['admin', 'manager medis', 'perawat'], 'badge' => 'OK']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard', 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control', 'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin', 'perawat'], 'badge' => 'MNT']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 3, 'route' => 'database-monitor.user-control', 'title' => 'User Control', 'desc' => 'Kelola user & hak akses sistem', 'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 4, 'route' => 'database-monitor.role-control', 'title' => 'Role Control', 'desc' => 'Kelola role & permission sistem', 'roles' => ['admin'], 'badge' => 'ROL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 5, 'route' => 'database-monitor.user-online', 'title' => 'User Online', 'desc' => 'Daftar user yang sedang aktif login (last_seen_at < threshold menit)', 'roles' => ['admin'], 'badge' => 'ONL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 6, 'route' => 'database-monitor.log-bpjs', 'title' => 'Log BPJS / E-Klaim API', 'desc' => 'Riwayat pemanggilan V-Claim, Antrean, Aplicares, I-Care, SIRS, iDRG/INACBG', 'roles' => ['admin'], 'badge' => 'LOG']),
        ];
    }
}
