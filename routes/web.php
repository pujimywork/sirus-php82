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

    Route::livewire('/master/dokter', 'pages::master.master-dokter.master-dokter')
        ->name('master.dokter');

    Route::livewire('/master/pasien', 'pages::master.master-pasien.master-pasien')
        ->name('master.pasien');

    Route::livewire('/master/obat', 'pages::master.master-obat.master-obat')
        ->name('master.obat');

    Route::livewire('/master/diagnosa', 'pages::master.master-diagnosa.master-diagnosa')
        ->name('master.diagnosa');

    Route::livewire('/master/others', 'pages::master.master-others.master-others')
        ->name('master.others');

    Route::livewire('/master/radiologis', 'pages::master.master-radiologis.master-radiologis')
        ->name('master.radiologis');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR RAWAT JALAN
    // ===========================================
    Route::livewire('/rawat-jalan/daftar', 'pages::transaksi.rj.daftar-rj.daftar-rj')
        ->name('rawat-jalan.daftar');


    // ===========================================
    // DATABASE MONITOR - MONITORING DASHBOARD
    // ===========================================
    Route::livewire('/database-monitor/monitoring-dashboard', 'pages::database-monitor.monitoring-dashboard.monitoring-dashboard')
        ->name('database-monitor.monitoring-dashboard');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
