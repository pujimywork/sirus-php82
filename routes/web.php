<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;



Route::livewire('/', 'welcome')->name('home');

// Display publik (TV antrian) — tanpa auth supaya bisa dibuka di layar tunggu pasien.
Route::livewire('/display/antrian-apotek-rj', 'pages::display.antrian-apotek-rj.antrian-apotek-rj')
    ->name('display.antrian-apotek-rj');
Route::livewire('/display/antrian-apotek-ugd', 'pages::display.antrian-apotek-ugd.antrian-apotek-ugd')
    ->name('display.antrian-apotek-ugd');
Route::livewire('/display/antrian-apotek-ri', 'pages::display.antrian-apotek-ri.antrian-apotek-ri')
    ->name('display.antrian-apotek-ri');
Route::livewire('/display/jadwal-poli', 'pages::display.jadwal-poli.jadwal-poli')
    ->name('display.jadwal-poli');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
});
// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::livewire('/master/poli', 'pages::master.poli.index')
//         ->name('master.poli');
// });


Route::middleware(['auth'])->group(function () {
    Route::livewire('/master/poli', 'pages::master.master-poli.master-poli')
        ->name('master.poli');

    Route::livewire('/master/karyawan', 'pages::master.master-karyawan.master-karyawan')
        ->name('master.karyawan');

    // ===========================================
    // MASTER - SETUP JADWAL PELAYANAN DOKTER BPJS
    // ===========================================
    Route::livewire('/master/setup-jadwal-bpjs', 'pages::master.setup-jadwal-bpjs.setup-jadwal-bpjs')
        ->name('master.setup-jadwal-bpjs');

    Route::livewire('/master/dokter', 'pages::master.master-dokter.master-dokter')
        ->name('master.dokter');

    Route::livewire('/master/pasien', 'pages::master.master-pasien.master-pasien')
        ->name('master.pasien');

    Route::livewire('/master/obat', 'pages::master.master-obat.master-obat')
        ->name('master.obat');

    Route::livewire('/master/obat-kronis', 'pages::master.master-obat-kronis.master-obat-kronis')
        ->name('master.obat-kronis');

    Route::livewire('/master/stocklocations', 'pages::master.master-stocklocations.master-stocklocations')
        ->name('master.stocklocations');

    Route::livewire('/master/diagnosa', 'pages::master.master-diagnosa.master-diagnosa')
        ->name('master.diagnosa');

    Route::livewire('/master/kamar', 'pages::master.master-kamar.bangsal.master-bangsal')
        ->name('master.kamar');

    Route::livewire('/master/laborat', 'pages::master.master-laborat.clab.master-clab')
        ->name('master.laborat');

    Route::livewire('/master/kelas', 'pages::master.master-kelas-rawat.master-kelas-rawat')
        ->name('master.kelas');

    Route::livewire('/master/agama', 'pages::master.master-agama.master-agama')
        ->name('master.agama');

    Route::livewire('/master/others', 'pages::master.master-others.master-others')
        ->name('master.others');

    Route::livewire('/master/jasa-medis', 'pages::master.master-jasa-medis.jasa-medis.master-jasa-medis')
        ->name('master.jasa-medis');

    Route::livewire('/master/jasa-dokter', 'pages::master.master-jasa-dokter.jasa-dokter.master-jasa-dokter')
        ->name('master.jasa-dokter');

    Route::livewire('/master/radiologis', 'pages::master.master-radiologis.master-radiologis')
        ->name('master.radiologis');

    Route::livewire('/master/diag-keperawatan', 'pages::master.master-diag-keperawatan.master-diag-keperawatan')
        ->name('master.diag-keperawatan');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR RAWAT JALAN (Pendaftaran)
    // ===========================================
    Route::livewire('/rawat-jalan/daftar', 'pages::transaksi.rj.daftar-rj.daftar-rj')
        ->name('rawat-jalan.daftar');

    // ===========================================
    // RAWAT JALAN (RJ) - PELAYANAN POLI (Dokter/Perawat)
    // ===========================================
    Route::livewire('/rawat-jalan/pelayanan', 'pages::transaksi.rj.pelayanan-rj.pelayanan-rj')
        ->name('rawat-jalan.pelayanan');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR PASIEN BULANAN
    // ===========================================
    Route::livewire('/rawat-jalan/daftar-bulanan', 'pages::transaksi.rj.daftar-rj-bulanan.daftar-rj-bulanan')
        ->name('rawat-jalan.daftar-bulanan');

    // ===========================================
    // FILES — Serve private file (auth required)
    // ===========================================
    // Arsitektur file storage:
    //   1. Laravel WRITE upload ke 'upload/...' (storage lokal sementara)
    //   2. External program sync 'upload/...' → '\\fileserver\share' (offload file besar)
    //   3. SMB share di-mount ke 'mount/...' (read-only dari Laravel)
    //   4. View Lihat: baca dari 'mount/...' (utama), fallback 'upload/...' bila
    //      program sync belum jalan / file masih di cache lokal.
    //
    // Path bisa nested (mis. mount/penunjang/radiologi/foto/xxx.pdf) — catch-all {path}.
    // Whitelist = prefix yang diizinkan untuk akses publik via route ini.
    Route::get('/files/{path}', function (string $path) {
        // Whitelist pakai 'mount/...' sebagai canonical (sumber data terbaru di share).
        $allowedPrefixes = [
            'mount/bpjs',                       // Berkas BPJS (SEP/grouping/RM/SKDP/lain-lain)
            'mount/penunjang/radiologi',        // Foto + hasil bacaan radiologi (1 folder)
            'mount/penunjang/lab-luar',         // Hasil lab luar (PDF/JPG)
            'mount/penunjang/emr/uploadHasilPenunjang', // Hasil penunjang dari EMR RJ/UGD/RI
        ];

        $matched = null;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix . '/')) {
                $matched = $prefix;
                break;
            }
        }
        if ($matched === null) {
            abort(403, 'Path tidak diizinkan.');
        }

        $filename = basename($path);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            abort(403, 'Nama file tidak valid.');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        // Coba baca dari mount/... (canonical, file di share). Kalau tidak ada,
        // fallback ke upload/... (cache lokal — file belum di-sync external program).
        $mountPath = $matched . '/' . $filename;
        $uploadPath = preg_replace('#^mount/#', 'upload/', $matched, 1) . '/' . $filename;

        if ($disk->exists($mountPath)) {
            return $disk->response($mountPath);
        }
        if ($disk->exists($uploadPath)) {
            return $disk->response($uploadPath);
        }
        abort(404, 'Berkas tidak ditemukan.');
    })->name('files.show')->where('path', '[A-Za-z0-9._\-/]+');

    // ===========================================
    // RAWAT JALAN (RJ) - BOOKING RJ (Mobile JKN)
    // ===========================================
    Route::livewire('/rawat-jalan/booking', 'pages::transaksi.rj.booking-rj.booking-rj')
        ->name('rawat-jalan.booking');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/transaksi/rj/antrian-apotek-rj', 'pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj')
        ->name('transaksi.rj.antrian-apotek-rj');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN KASIR (clone Apotek RJ)
    // ===========================================
    Route::livewire('/transaksi/rj/antrian-kasir-rj', 'pages::transaksi.rj.antrian-kasir-rj.antrian-kasir-rj')
        ->name('transaksi.rj.antrian-kasir-rj');


    // ===========================================
    // UGD - DAFTAR UGD (Pendaftaran)
    // ===========================================
    Route::livewire('/ugd/daftar', 'pages::transaksi.ugd.daftar-ugd.daftar-ugd')
        ->name('ugd.daftar');

    // ===========================================
    // UGD - PELAYANAN UGD (Dokter/Perawat — EMR)
    // ===========================================
    Route::livewire('/ugd/pelayanan', 'pages::transaksi.ugd.pelayanan-ugd.pelayanan-ugd')
        ->name('ugd.pelayanan');

    Route::livewire('/ugd/daftar-bulanan', 'pages::transaksi.ugd.daftar-ugd-bulanan.daftar-ugd-bulanan')
        ->name('ugd.daftar-bulanan');


    // ===========================================
    // TRANSAKSI UGD - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/transaksi/ugd/antrian-apotek-ugd', 'pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd')
        ->name('transaksi.ugd.antrian-apotek-ugd');

    // ===========================================
    // TRANSAKSI UGD - ANTRIAN KASIR (clone Apotek UGD)
    // ===========================================
    Route::livewire('/transaksi/ugd/antrian-kasir-ugd', 'pages::transaksi.ugd.antrian-kasir-ugd.antrian-kasir-ugd')
        ->name('transaksi.ugd.antrian-kasir-ugd');


    // ===========================================
    // TRANSAKSI APOTEK - GABUNGAN RJ + UGD + RI (tab)
    // ===========================================
    Route::livewire('/transaksi/apotek', 'pages::transaksi.apotek.apotek')
        ->name('transaksi.apotek');

    // ===========================================
    // TRANSAKSI KASIR - GABUNGAN RJ + UGD + RI (tab) — clone Apotek
    // ===========================================
    Route::livewire('/transaksi/kasir', 'pages::transaksi.kasir.kasir')
        ->name('transaksi.kasir');

    // ===========================================
    // TRANSAKSI CASEMIX - GABUNGAN Bulanan RJ + UGD + RI (tab)
    // ===========================================
    Route::livewire('/transaksi/casemix', 'pages::transaksi.casemix.casemix')
        ->name('transaksi.casemix');

    // Direct route — Antrian Apotek RI (tanpa wrapper tab)
    Route::livewire('/transaksi/ri-resep/antrian-ri-resep', 'pages::transaksi.ri-resep.antrian-ri-resep.antrian-ri-resep')
        ->name('transaksi.ri-resep.antrian-ri-resep');

    // Direct route — Antrian Kasir RI (clone Apotek RI)
    Route::livewire('/transaksi/kasir/antrian-kasir-ri', 'pages::transaksi.kasir.antrian-kasir-ri.antrian-kasir-ri')
        ->name('transaksi.kasir.antrian-kasir-ri');

    // Direct route — Daftar Pasien RI Kasir (per rihdr, action: Administrasi saja)
    Route::livewire('/transaksi/kasir/daftar-kasir-ri', 'pages::transaksi.kasir.daftar-kasir-ri.daftar-kasir-ri')
        ->name('transaksi.kasir.daftar-kasir-ri');


    // ===========================================
    // RI - DAFTAR RI
    // ===========================================
    Route::livewire('/ri/daftar', 'pages::transaksi.ri.daftar-ri.daftar-ri')
        ->name('ri.daftar');

    Route::livewire('/ri/daftar-bulanan', 'pages::transaksi.ri.daftar-ri-bulanan.daftar-ri-bulanan')
        ->name('ri.daftar-bulanan');

    // ===========================================
    // RI — UPDATE TEMPAT TIDUR (Aplicares + SIRS)
    // ===========================================
    Route::livewire('/ri/update-tt-ri', 'pages::transaksi.ri.update-tt-ri.update-tt-ri')
        ->name('ri.update-tt-ri');
    // ===========================================
    // OPERASI - JADWAL OPERASI
    // ===========================================
    Route::livewire('/operasi/jadwal-operasi', 'pages::operasi.jadwal-operasi.jadwal-operasi')
        ->name('operasi.jadwal-operasi');

    // ===========================================
    // KEUANGAN - PENERIMAAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/penerimaan-kas-tu', 'pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu')
        ->name('keuangan.penerimaan-kas-tu');

    // ===========================================
    // KEUANGAN - PENGELUARAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/pengeluaran-kas-tu', 'pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu')
        ->name('keuangan.pengeluaran-kas-tu');

    // ===========================================
    // KEUANGAN - PEMBAYARAN HUTANG PBF (medis)
    // ===========================================
    Route::livewire('/keuangan/pembayaran-hutang-pbf', 'pages::transaksi.keuangan.pembayaran-hutang-pbf.pembayaran-hutang-pbf')
        ->name('keuangan.pembayaran-hutang-pbf');

    // ===========================================
    // KEUANGAN - PEMBAYARAN HUTANG NON-MEDIS
    // ===========================================
    Route::livewire('/keuangan/pembayaran-hutang-non-medis', 'pages::transaksi.keuangan.pembayaran-hutang-non-medis.pembayaran-hutang-non-medis')
        ->name('keuangan.pembayaran-hutang-non-medis');

    // ===========================================
    // KEUANGAN - TOPUP SUPPLIER PBF (medis)
    // ===========================================
    Route::livewire('/keuangan/topup-supplier-pbf', 'pages::transaksi.keuangan.topup-supplier-pbf.topup-supplier-pbf')
        ->name('keuangan.topup-supplier-pbf');

    // ===========================================
    // KEUANGAN - TOPUP SUPPLIER NON-MEDIS
    // ===========================================
    Route::livewire('/keuangan/topup-supplier-non-medis', 'pages::transaksi.keuangan.topup-supplier-non-medis.topup-supplier-non-medis')
        ->name('keuangan.topup-supplier-non-medis');

    // ===========================================
    // KEUANGAN - SALDO KAS
    // ===========================================
    Route::livewire('/keuangan/saldo-kas', 'pages::transaksi.keuangan.saldo-kas.saldo-kas')
        ->name('keuangan.saldo-kas');

    // ===========================================
    // KEUANGAN - BUKU BESAR
    // ===========================================
    Route::livewire('/keuangan/buku-besar', 'pages::transaksi.keuangan.buku-besar.buku-besar')
        ->name('keuangan.buku-besar');

    // ===========================================
    // KEUANGAN - LAPORAN LABA RUGI
    // ===========================================
    Route::livewire('/keuangan/laba-rugi', 'pages::transaksi.keuangan.laba-rugi.laba-rugi')
        ->name('keuangan.laba-rugi');

    // ===========================================
    // KEUANGAN - LAPORAN NERACA
    // ===========================================
    Route::livewire('/keuangan/neraca', 'pages::transaksi.keuangan.neraca.neraca')
        ->name('keuangan.neraca');

    // ===========================================
    // MASTER AKUNTANSI - GROUP AKUN
    // ===========================================
    Route::livewire('/master/group-akun', 'pages::master.master-akuntansi.master-group-akun.master-group-akun')
        ->name('master.group-akun');

    // ===========================================
    // MASTER AKUNTANSI - AKUN
    // ===========================================
    Route::livewire('/master/akun', 'pages::master.master-akuntansi.master-akun.master-akun')
        ->name('master.akun');

    // ===========================================
    // MASTER AKUNTANSI - KONFIGURASI AKUN TRANSAKSI
    // ===========================================
    Route::livewire('/master/konf-akun-trans', 'pages::master.master-akuntansi.master-konf-akun-trans.master-konf-akun-trans')
        ->name('master.konf-akun-trans');

    // ===========================================
    // GUDANG - PENERIMAAN MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-medis', 'pages::transaksi.gudang.penerimaan-medis.penerimaan-medis')
        ->name('gudang.penerimaan-medis');

    Route::livewire('/gudang/transfer-stock', 'pages::transaksi.gudang.transfer-stock.transfer-stock')
        ->name('gudang.transfer-stock');

    Route::livewire('/gudang/transfer-stock-non', 'pages::transaksi.gudang.transfer-stock-non.transfer-stock-non')
        ->name('gudang.transfer-stock-non');

    // ===========================================
    // GUDANG - PENERIMAAN NON-MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-non-medis', 'pages::transaksi.gudang.penerimaan-non-medis.penerimaan-non-medis')
        ->name('gudang.penerimaan-non-medis');

    // ===========================================
    // GUDANG - KARTU STOCK GUDANG (warehouse)
    // ===========================================
    Route::livewire('/gudang/kartu-stock', 'pages::transaksi.gudang.kartu-stock.kartu-stock')
        ->name('gudang.kartu-stock');

    // ===========================================
    // GUDANG - KARTU STOCK APOTEK
    // ===========================================
    Route::livewire('/gudang/kartu-stock-apt', 'pages::transaksi.gudang.kartu-stock-apt.kartu-stock-apt')
        ->name('gudang.kartu-stock-apt');

    // ===========================================
    // GUDANG - KARTU STOCK NON-MEDIS
    // ===========================================
    Route::livewire('/gudang/kartu-stock-non', 'pages::transaksi.gudang.kartu-stock-non.kartu-stock-non')
        ->name('gudang.kartu-stock-non');

    // ===========================================
    // TRANSAKSI PENUNJANG - LABORATORIUM
    // ===========================================
    Route::livewire('/transaksi/penunjang/laborat', 'pages::transaksi.penunjang.laborat.daftar-laborat')
        ->name('transaksi.penunjang.laborat');

    Route::livewire('/transaksi/penunjang/laborat/lab-luar', 'pages::transaksi.penunjang.laborat.lab-luar.lab-luar')
        ->name('transaksi.penunjang.laborat.lab-luar');

    Route::livewire('/transaksi/penunjang/radiologi/upload', 'pages::transaksi.penunjang.radiologi.upload-radiologi')
        ->name('transaksi.penunjang.radiologi.upload');

    // ===========================================
    // DATABASE MONITOR - MONITORING DASHBOARD
    // ===========================================
    Route::livewire('/database-monitor/monitoring-dashboard', 'pages::database-monitor.monitoring-dashboard.monitoring-dashboard')
        ->name('database-monitor.monitoring-dashboard');

    // ===========================================
    // DATABASE MONITOR - MONITORING MOUNT CONTROL
    // ===========================================
    Route::livewire('/database-monitor/monitoring-mount-control', 'pages::database-monitor.monitoring-mount-control.monitoring-mount-control')
        ->name('database-monitor.monitoring-mount-control');

    // ===========================================
    // DATABASE MONITOR - USER CONTROL
    // ===========================================
    Route::livewire('/database-monitor/user-control', 'pages::database-monitor.user-control.user-control')
        ->name('database-monitor.user-control');

    // ===========================================
    // DATABASE MONITOR - ROLE CONTROL
    // ===========================================
    Route::livewire('/database-monitor/role-control', 'pages::database-monitor.role-control.role-control')
        ->name('database-monitor.role-control');

    // ===========================================
    // DASHBOARD MANAJEMEN
    // ===========================================
    Route::livewire('/manajemen/indikator-pelayanan', 'pages::manajemen.indikator-pelayanan.indikator-pelayanan')
        ->name('manajemen.indikator-pelayanan');

    Route::livewire('/manajemen/indikator-penunjang', 'pages::manajemen.indikator-penunjang.indikator-penunjang')
        ->name('manajemen.indikator-penunjang');

    Route::livewire('/manajemen/indikator-tu', 'pages::manajemen.indikator-tu.indikator-tu')
        ->name('manajemen.indikator-tu');

    Route::livewire('/manajemen/monitoring-keuangan', 'pages::manajemen.monitoring-keuangan.monitoring-keuangan')
        ->name('manajemen.monitoring-keuangan');

    Route::livewire('/manajemen/laporan-diagnosa', 'pages::manajemen.laporan-diagnosa.laporan-diagnosa')
        ->name('manajemen.laporan-diagnosa');

    Route::livewire('/database-monitor/log-bpjs', 'pages::database-monitor.log-bpjs.log-bpjs')
        ->name('database-monitor.log-bpjs');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-dokter', 'pages::manajemen.rs.tu.pendapatan-jasa-dokter.pendapatan-jasa-dokter')
        ->name('manajemen.rs.tu.pendapatan-jasa-dokter');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-medis', 'pages::manajemen.rs.tu.pendapatan-jasa-medis.pendapatan-jasa-medis')
        ->name('manajemen.rs.tu.pendapatan-jasa-medis');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-karyawan', 'pages::manajemen.rs.tu.pendapatan-jasa-karyawan.pendapatan-jasa-karyawan')
        ->name('manajemen.rs.tu.pendapatan-jasa-karyawan');

    Route::livewire('/manajemen/rs/tu/pendapatan-rs', 'pages::manajemen.rs.tu.pendapatan-rs.pendapatan-rs')
        ->name('manajemen.rs.tu.pendapatan-rs');

    Route::livewire('/manajemen/mutasi-obat', 'pages::manajemen.mutasi-obat.mutasi-obat')
        ->name('manajemen.mutasi-obat');

    Route::livewire('/manajemen/transfer-antar-ruangan', 'pages::manajemen.transfer-antar-ruangan.transfer-antar-ruangan')
        ->name('manajemen.transfer-antar-ruangan');

    Route::livewire('/manajemen/rs/rj/laporan-task-id-rj', 'pages::manajemen.rs.rj.laporan-task-id-rj.laporan-task-id-rj')
        ->name('manajemen.rs.rj.laporan-task-id-rj');

    Route::livewire('/manajemen/rs/ugd/laporan-task-id-ugd', 'pages::manajemen.rs.ugd.laporan-task-id-ugd.laporan-task-id-ugd')
        ->name('manajemen.rs.ugd.laporan-task-id-ugd');

    Route::livewire('/manajemen/rs/rj/laporan-kunjungan-rj', 'pages::manajemen.rs.rj.laporan-kunjungan-rj.laporan-kunjungan-rj')
        ->name('manajemen.rs.rj.laporan-kunjungan-rj');

    Route::livewire('/manajemen/rs/ugd/laporan-kunjungan-ugd', 'pages::manajemen.rs.ugd.laporan-kunjungan-ugd.laporan-kunjungan-ugd')
        ->name('manajemen.rs.ugd.laporan-kunjungan-ugd');

    Route::livewire('/manajemen/rs/ri/laporan-kunjungan-ri', 'pages::manajemen.rs.ri.laporan-kunjungan-ri.laporan-kunjungan-ri')
        ->name('manajemen.rs.ri.laporan-kunjungan-ri');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-3-2-rawat-inap', 'pages::manajemen.sirs.ri.laporan-rl-3-2-rawat-inap.laporan-rl-3-2-rawat-inap')
        ->name('manajemen.sirs.ri.laporan-rl-3-2-rawat-inap');

    Route::livewire('/manajemen/sirs/ugd/laporan-rl-3-3-rawat-darurat', 'pages::manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat.laporan-rl-3-3-rawat-darurat')
        ->name('manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-4-pengunjung', 'pages::manajemen.sirs.rj.laporan-rl-3-4-pengunjung.laporan-rl-3-4-pengunjung')
        ->name('manajemen.sirs.rj.laporan-rl-3-4-pengunjung');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-5-kunjungan', 'pages::manajemen.sirs.rj.laporan-rl-3-5-kunjungan.laporan-rl-3-5-kunjungan')
        ->name('manajemen.sirs.rj.laporan-rl-3-5-kunjungan');

    Route::livewire('/manajemen/sirs/penunjang/laporan-rl-3-8-laboratorium', 'pages::manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium.laporan-rl-3-8-laboratorium')
        ->name('manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium');

    Route::livewire('/manajemen/sirs/penunjang/laporan-rl-3-9-radiologi', 'pages::manajemen.sirs.penunjang.laporan-rl-3-9-radiologi.laporan-rl-3-9-radiologi')
        ->name('manajemen.sirs.penunjang.laporan-rl-3-9-radiologi');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-15-kesehatan-jiwa', 'pages::manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa.laporan-rl-3-15-kesehatan-jiwa')
        ->name('manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-3-19-cara-bayar', 'pages::manajemen.sirs.ri.laporan-rl-3-19-cara-bayar.laporan-rl-3-19-cara-bayar')
        ->name('manajemen.sirs.ri.laporan-rl-3-19-cara-bayar');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-1-morbiditas', 'pages::manajemen.sirs.ri.laporan-rl-4-1-morbiditas.laporan-rl-4-1-morbiditas')
        ->name('manajemen.sirs.ri.laporan-rl-4-1-morbiditas');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-2-10besar', 'pages::manajemen.sirs.ri.laporan-rl-4-2-10besar.laporan-rl-4-2-10besar')
        ->name('manajemen.sirs.ri.laporan-rl-4-2-10besar');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-3-10besar-mati', 'pages::manajemen.sirs.ri.laporan-rl-4-3-10besar-mati.laporan-rl-4-3-10besar-mati')
        ->name('manajemen.sirs.ri.laporan-rl-4-3-10besar-mati');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-5-1-morbiditas', 'pages::manajemen.sirs.rj.laporan-rl-5-1-morbiditas.laporan-rl-5-1-morbiditas')
        ->name('manajemen.sirs.rj.laporan-rl-5-1-morbiditas');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-5-3-10besar-kunjungan', 'pages::manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan.laporan-rl-5-3-10besar-kunjungan')
        ->name('manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-permintaan-lab', 'pages::manajemen.rs.penunjang.lab.laporan-permintaan-lab.laporan-permintaan-lab')
        ->name('manajemen.rs.penunjang.lab.laporan-permintaan-lab');

    Route::livewire('/manajemen/rs/penunjang/rad/laporan-permintaan-rad', 'pages::manajemen.rs.penunjang.rad.laporan-permintaan-rad.laporan-permintaan-rad')
        ->name('manajemen.rs.penunjang.rad.laporan-permintaan-rad');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-pemeriksaan-lab', 'pages::manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab.laporan-pemeriksaan-lab')
        ->name('manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab');

    Route::livewire('/manajemen/rs/penunjang/rad/laporan-pemeriksaan-rad', 'pages::manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad.laporan-pemeriksaan-rad')
        ->name('manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
