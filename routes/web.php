<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;



Route::livewire('/', 'welcome')->name('home');

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

    Route::livewire('/master/radiologis', 'pages::master.master-radiologis.master-radiologis')
        ->name('master.radiologis');

    Route::livewire('/master/diag-keperawatan', 'pages::master.master-diag-keperawatan.master-diag-keperawatan')
        ->name('master.diag-keperawatan');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR RAWAT JALAN
    // ===========================================
    Route::livewire('/rawat-jalan/daftar', 'pages::transaksi.rj.daftar-rj.daftar-rj')
        ->name('rawat-jalan.daftar');

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
    // UGD - DAFTAR UGD
    // ===========================================
    Route::livewire('/ugd/daftar', 'pages::transaksi.ugd.daftar-ugd.daftar-ugd')
        ->name('ugd.daftar');

    Route::livewire('/ugd/daftar-bulanan', 'pages::transaksi.ugd.daftar-ugd-bulanan.daftar-ugd-bulanan')
        ->name('ugd.daftar-bulanan');


    // ===========================================
    // TRANSAKSI UGD - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/transaksi/ugd/antrian-apotek-ugd', 'pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd')
        ->name('transaksi.ugd.antrian-apotek-ugd');


    // ===========================================
    // TRANSAKSI APOTEK - GABUNGAN RJ + UGD + RI (tab)
    // ===========================================
    Route::livewire('/transaksi/apotek', 'pages::transaksi.apotek.apotek')
        ->name('transaksi.apotek');

    // Direct route — Antrian Apotek RI (tanpa wrapper tab)
    Route::livewire('/transaksi/apotek/antrian-apotek-ri', 'pages::transaksi.apotek.antrian-apotek-ri.antrian-apotek-ri')
        ->name('transaksi.apotek.antrian-apotek-ri');


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

    Route::livewire('/manajemen/monitoring-kas', 'pages::manajemen.monitoring-kas.monitoring-kas')
        ->name('manajemen.monitoring-kas');

    Route::livewire('/manajemen/laporan-diagnosa', 'pages::manajemen.laporan-diagnosa.laporan-diagnosa')
        ->name('manajemen.laporan-diagnosa');

    Route::livewire('/manajemen/mutasi-obat', 'pages::manajemen.mutasi-obat.mutasi-obat')
        ->name('manajemen.mutasi-obat');

    Route::livewire('/manajemen/rj/laporan-task-id-rj', 'pages::manajemen.rj.laporan-task-id-rj.laporan-task-id-rj')
        ->name('manajemen.rj.laporan-task-id-rj');

    Route::livewire('/manajemen/ugd/laporan-task-id-ugd', 'pages::manajemen.ugd.laporan-task-id-ugd.laporan-task-id-ugd')
        ->name('manajemen.ugd.laporan-task-id-ugd');

    Route::livewire('/manajemen/rj/laporan-kunjungan-rj', 'pages::manajemen.rj.laporan-kunjungan-rj.laporan-kunjungan-rj')
        ->name('manajemen.rj.laporan-kunjungan-rj');

    Route::livewire('/manajemen/ugd/laporan-kunjungan-ugd', 'pages::manajemen.ugd.laporan-kunjungan-ugd.laporan-kunjungan-ugd')
        ->name('manajemen.ugd.laporan-kunjungan-ugd');

    Route::livewire('/manajemen/ri/laporan-kunjungan-ri', 'pages::manajemen.ri.laporan-kunjungan-ri.laporan-kunjungan-ri')
        ->name('manajemen.ri.laporan-kunjungan-ri');

    Route::livewire('/manajemen/ri/laporan-rl-3-2-rawat-inap', 'pages::manajemen.ri.laporan-rl-3-2-rawat-inap.laporan-rl-3-2-rawat-inap')
        ->name('manajemen.ri.laporan-rl-3-2-rawat-inap');

    Route::livewire('/manajemen/ugd/laporan-rl-3-3-rawat-darurat', 'pages::manajemen.ugd.laporan-rl-3-3-rawat-darurat.laporan-rl-3-3-rawat-darurat')
        ->name('manajemen.ugd.laporan-rl-3-3-rawat-darurat');

    Route::livewire('/manajemen/laporan-rl-3-4-pengunjung', 'pages::manajemen.laporan-rl-3-4-pengunjung.laporan-rl-3-4-pengunjung')
        ->name('manajemen.laporan-rl-3-4-pengunjung');

    Route::livewire('/manajemen/laporan-rl-3-5-kunjungan', 'pages::manajemen.laporan-rl-3-5-kunjungan.laporan-rl-3-5-kunjungan')
        ->name('manajemen.laporan-rl-3-5-kunjungan');

    Route::livewire('/manajemen/laporan-rl-3-8-laboratorium', 'pages::manajemen.laporan-rl-3-8-laboratorium.laporan-rl-3-8-laboratorium')
        ->name('manajemen.laporan-rl-3-8-laboratorium');

    Route::livewire('/manajemen/laporan-rl-3-9-radiologi', 'pages::manajemen.laporan-rl-3-9-radiologi.laporan-rl-3-9-radiologi')
        ->name('manajemen.laporan-rl-3-9-radiologi');

    Route::livewire('/manajemen/laporan-rl-3-15-kesehatan-jiwa', 'pages::manajemen.laporan-rl-3-15-kesehatan-jiwa.laporan-rl-3-15-kesehatan-jiwa')
        ->name('manajemen.laporan-rl-3-15-kesehatan-jiwa');

    Route::livewire('/manajemen/laporan-rl-3-19-cara-bayar', 'pages::manajemen.laporan-rl-3-19-cara-bayar.laporan-rl-3-19-cara-bayar')
        ->name('manajemen.laporan-rl-3-19-cara-bayar');

    Route::livewire('/manajemen/lab/laporan-permintaan-lab', 'pages::manajemen.lab.laporan-permintaan-lab.laporan-permintaan-lab')
        ->name('manajemen.lab.laporan-permintaan-lab');

    Route::livewire('/manajemen/rad/laporan-permintaan-rad', 'pages::manajemen.rad.laporan-permintaan-rad.laporan-permintaan-rad')
        ->name('manajemen.rad.laporan-permintaan-rad');

    Route::livewire('/manajemen/lab/laporan-pemeriksaan-lab', 'pages::manajemen.lab.laporan-pemeriksaan-lab.laporan-pemeriksaan-lab')
        ->name('manajemen.lab.laporan-pemeriksaan-lab');

    Route::livewire('/manajemen/rad/laporan-pemeriksaan-rad', 'pages::manajemen.rad.laporan-pemeriksaan-rad.laporan-pemeriksaan-rad')
        ->name('manajemen.rad.laporan-pemeriksaan-rad');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
