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


    // ===========================================
    // TRANSAKSI UGD - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/transaksi/ugd/antrian-apotek-ugd', 'pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd')
        ->name('transaksi.ugd.antrian-apotek-ugd');


    // ===========================================
    // RI - DAFTAR RI
    // ===========================================
    Route::livewire('/ri/daftar', 'pages::transaksi.ri.daftar-ri.daftar-ri')
        ->name('ri.daftar');

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
    // GUDANG - PENERIMAAN MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-medis', 'pages::transaksi.gudang.penerimaan-medis.penerimaan-medis')
        ->name('gudang.penerimaan-medis');

    // ===========================================
    // TRANSAKSI PENUNJANG - LABORATORIUM
    // ===========================================
    Route::livewire('/transaksi/penunjang/laborat', 'pages::transaksi.penunjang.laborat.daftar-laborat')
        ->name('transaksi.penunjang.laborat');

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
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
