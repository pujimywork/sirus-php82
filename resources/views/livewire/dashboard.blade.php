<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public string $search = '';

    // ✅ Semua role user (lowercase) dipakai untuk filtering menu
    #[Computed]
    public function userRoles(): array
    {
        return auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->values()->toArray();
    }

    #[Computed]
    public function masterMenus(): array
    {
        // Helper: build entry hanya kalau route terdaftar — defensive vs route renames/deletions
        $entry = function (array $m): ?array {
            if (!\Illuminate\Support\Facades\Route::has($m['route'])) return null;
            $m['href'] = route($m['route']);
            return $m;
        };

        // Role yang berhak melihat semua menu Master (Pelayanan, Laboratorium, Apotek, Akuntansi).
        // Tahap awal: admin + manager (Umum/Medis) + supervisor (Penunjang/Tu).
        // Role fungsional (mr, perawat, apoteker, lab, dll.) belum diberi akses Master
        // sampai pembagian per-master diputuskan lebih lanjut.
        $masterRoles = ['admin', 'manager umum', 'manager medis', 'supervisor penunjang', 'supervisor tu'];

        $rows = array_filter([
            // ── Dashboard Manajemen (laporan & monitoring untuk manajer/direksi) ────
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 1, 'route' => 'manajemen.indikator-pelayanan', 'title' => 'Indikator Pelayanan', 'desc' => 'BOR / ALOS / TOI / BTO — tren bulanan & tahunan',              'roles' => ['admin', 'manager'], 'badge' => 'KPI']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 2, 'route' => 'manajemen.monitoring-keuangan', 'title' => 'Monitoring Keuangan', 'desc' => 'Pendapatan jasa dokter, kas/bank & arus keuangan rumah sakit',  'roles' => ['admin', 'manager'], 'badge' => 'KEU']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 3, 'route' => 'manajemen.laporan-diagnosa',    'title' => 'Laporan Diagnosa',    'desc' => '10 besar diagnosa, tindakan & mortalitas — bulanan & tahunan', 'roles' => ['admin', 'manager'], 'badge' => 'DX']),
            $entry(['group' => 'Dashboard Manajemen', 'groupOrder' => 0, 'order' => 4, 'route' => 'manajemen.mutasi-obat',         'title' => 'Mutasi Obat',         'desc' => 'Keluar masuk obat — bulanan & tahunan, per gudang & per unit', 'roles' => ['admin', 'manager'], 'badge' => 'OBT']),
            // Sub-laporan diakses lewat hub-nya (Indikator Pelayanan / Monitoring Keuangan), tidak muncul di Dashboard utama:
            //   - Laporan Task ID RJ/UGD, Laporan Kunjungan RJ/UGD/RI, Transfer Antar Ruangan → hub Indikator Pelayanan
            //   - Pendapatan Jasa Dokter → hub Monitoring Keuangan

            // ── Master Pelayanan (data pelayanan & rekam medis RS) ────
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 1,  'route' => 'master.poli',              'title' => 'Master Poli',                    'desc' => 'Kelola data poli & ruangan',                                                              'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 2,  'route' => 'master.dokter',            'title' => 'Master Dokter',                  'desc' => 'Kelola data dokter & spesialis',                                                          'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 3,  'route' => 'master.pasien',            'title' => 'Master Pasien',                  'desc' => 'Kelola data pasien & rekam medis',                                                        'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 4,  'route' => 'master.diagnosa',          'title' => 'Master Diagnosa',                'desc' => 'Kelola data diagnosa ICD-10',                                                             'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 5,  'route' => 'master.radiologis',        'title' => 'Master Radiologi',               'desc' => 'Kelola data radiologi',                                                                   'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 6,  'route' => 'master.others',            'title' => 'Master Lain-lain',               'desc' => 'Kelola data lain-lain',                                                                   'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 7,  'route' => 'master.agama',             'title' => 'Master Agama',                   'desc' => 'Kelola data agama pasien',                                                                'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 8,  'route' => 'master.diag-keperawatan',  'title' => 'Master Diagnosis Keperawatan',   'desc' => 'Kelola data SDKI, SLKI, SIKI asuhan keperawatan',                                         'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 9,  'route' => 'master.kamar',             'title' => 'Master Kamar',                   'desc' => 'Kelola bangsal, kamar & bed rawat inap',                                                  'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.kelas',             'title' => 'Master Kelas Rawat',             'desc' => 'Kelola kelas kamar & mapping Aplicares / SIRS',                                           'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan',          'title' => 'Master Karyawan',                'desc' => 'Kelola NIK karyawan untuk login user & coder iDRG (E-Klaim)',                             'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.setup-jadwal-bpjs', 'title' => 'Pemetaan Jadwal Dokter',         'desc' => 'Ambil & terapkan jadwal praktek dokter dari BPJS ke data RS',                             'roles' => $masterRoles, 'badge' => 'BPJS']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.stocklocations',    'title' => 'Master Lokasi Stok',             'desc' => 'Kelola kode lokasi stok (gudang, apotek, ruangan, klinik) untuk transfer obat & barang', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),

            // ── Master Laboratorium ──────────────────────────────────
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kelola kategori lab & item pemeriksaan', 'roles' => $masterRoles, 'badge' => 'Lab']),

            // ── Master Apotek (master obat / produk farmasi) ─────────
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.obat',        'title' => 'Master Obat',        'desc' => 'Kelola data obat & farmasi',                                'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 2, 'route' => 'master.obat-kronis', 'title' => 'Master Obat Kronis', 'desc' => 'Daftar obat kronis BPJS — max qty per resep & tarif klaim', 'roles' => $masterRoles, 'badge' => 'Kronis']),

            // ── Master Akuntansi ──────────────────────────────────────
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun',      'title' => 'Master Group Akun',                'desc' => 'Kelola group akun (Aktiva, Kewajiban, Modal, Pendapatan, Beban)',                  'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun',            'title' => 'Master Akun',                      'desc' => 'Kelola COA (Chart of Account) — daftar akun & saldo awal',                         'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konfigurasi Akun Transaksi','desc' => 'Mapping akun debit/kredit untuk tiap jenis transaksi (RJ, UGD, RI, Resep, dll.)',  'roles' => $masterRoles, 'badge' => 'Akuntansi']),

            // ── Rawat Jalan ────────────────────────────────────────────
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 1, 'route' => 'rawat-jalan.daftar',         'title' => 'Daftar Rawat Jalan',       'desc' => 'Pendaftaran & manajemen pasien rawat jalan',        'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu'], 'badge' => 'RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rawat-jalan.daftar-bulanan','title' => 'Daftar Pasien Bulanan RJ', 'desc' => 'List pasien rawat jalan per bulan (mm/yyyy)',       'roles' => ['admin', 'manager umum', 'casemix', 'tu'],       'badge' => 'RJ-BLN']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 3, 'route' => 'rawat-jalan.booking',        'title' => 'Booking RJ',               'desc' => 'Daftar pasien booking rawat jalan via Mobile JKN',  'roles' => ['admin', 'mr'],                                 'badge' => 'BKG']),

            // ── UGD ────────────────────────────────────────────────────
            $entry(['group' => 'UGD', 'groupOrder' => 6, 'order' => 1, 'route' => 'ugd.daftar',          'title' => 'Daftar UGD',                 'desc' => 'Pendaftaran & manajemen pasien UGD',          'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu'], 'badge' => 'UGD']),
            $entry(['group' => 'UGD', 'groupOrder' => 6, 'order' => 2, 'route' => 'ugd.daftar-bulanan', 'title' => 'Daftar Pasien Bulanan UGD', 'desc' => 'List pasien UGD per bulan (mm/yyyy)',         'roles' => ['admin', 'manager umum', 'casemix', 'tu'],       'badge' => 'UGD-BLN']),

            // ── Rawat Inap ─────────────────────────────────────────────
            $entry(['group' => 'RI', 'groupOrder' => 7, 'order' => 1, 'route' => 'ri.daftar',          'title' => 'Daftar RI',                 'desc' => 'Pendaftaran & manajemen pasien Rawat Inap',          'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu'], 'badge' => 'RI']),
            $entry(['group' => 'RI', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.daftar-bulanan', 'title' => 'Daftar Pasien Bulanan RI', 'desc' => 'List pasien RI per bulan berdasarkan tgl pulang', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'],       'badge' => 'RI-BLN']),
            $entry(['group' => 'RI', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.update-tt-ri', 'title' => 'Update Tempat Tidur RI', 'desc' => 'Sync ketersediaan kamar RI ke Aplicares & SIRS Kemenkes', 'roles' => ['admin', 'mr', 'perawat', 'dokter'],            'badge' => 'TT']),

            // ── Apotek (transaksi) ────────────────────────────────────
            $entry(['group' => 'Apotek', 'groupOrder' => 8, 'order' => 1, 'route' => 'transaksi.apotek',                       'title' => 'Antrian Apotek',     'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker'], 'badge' => 'APT']),
            $entry(['group' => 'Apotek', 'groupOrder' => 8, 'order' => 2, 'route' => 'transaksi.rj.antrian-apotek-rj',        'title' => 'Antrian Apotek RJ', 'desc' => 'Antrian apotek khusus pasien rawat jalan',                'roles' => ['admin', 'apoteker'], 'badge' => 'APT-RJ']),
            $entry(['group' => 'Apotek', 'groupOrder' => 8, 'order' => 3, 'route' => 'transaksi.ugd.antrian-apotek-ugd',      'title' => 'Antrian Apotek UGD','desc' => 'Antrian apotek khusus pasien UGD',                        'roles' => ['admin', 'apoteker'], 'badge' => 'APT-UGD']),
            $entry(['group' => 'Apotek', 'groupOrder' => 8, 'order' => 4, 'route' => 'transaksi.apotek.antrian-apotek-ri',    'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat inap',         'roles' => ['admin', 'apoteker'], 'badge' => 'APT-RI']),

            // ── Penunjang ──────────────────────────────────────────────
            $entry(['group' => 'Penunjang', 'groupOrder' => 9, 'order' => 1, 'route' => 'transaksi.penunjang.laborat', 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'laboratorium'], 'badge' => 'LAB']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 9, 'order' => 2, 'route' => 'transaksi.penunjang.laborat.lab-luar', 'title' => 'Upload Hasil Lab Luar', 'desc' => 'Upload PDF hasil lab luar yang sudah Selesai', 'roles' => ['admin', 'laboratorium'], 'badge' => 'LAB-LUAR']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 9, 'order' => 3, 'route' => 'transaksi.penunjang.radiologi.upload', 'title' => 'Upload Hasil Radiologi', 'desc' => 'Upload foto radiologi & hasil bacaan PDF', 'roles' => ['admin', 'radiologi'], 'badge' => 'RAD']),

            // ── Gudang ────────────────────────────────────────────────
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 1, 'route' => 'gudang.penerimaan-medis',     'title' => 'Obat dari PBF',          'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)',                'roles' => ['admin', 'apoteker'], 'badge' => 'RCV']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'title' => 'Barang dari Supplier (Non-Medis)', 'desc' => 'Penerimaan barang non-medis dari supplier',           'roles' => ['admin', 'tu'],     'badge' => 'RCV-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 3, 'route' => 'gudang.transfer-stock',      'title' => 'Transfer Stok Medis',      'desc' => 'Pindahkan obat antar lokasi — sumber dari Gudang Medis atau Apotek',                  'roles' => ['admin', 'apoteker'], 'badge' => 'TRF']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 4, 'route' => 'gudang.transfer-stock-non',  'title' => 'Transfer Stok Non-Medis',   'desc' => 'Pindahkan barang non-medis (ATK, RT, dll.) dari Gudang Non-Medis',                       'roles' => ['admin', 'tu'],        'badge' => 'TRF-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 5, 'route' => 'gudang.kartu-stock',          'title' => 'Kartu Stock — Gudang Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Medis',     'roles' => ['admin', 'apoteker'], 'badge' => 'STK']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 6, 'route' => 'gudang.kartu-stock-apt',      'title' => 'Kartu Stock — Apotek',       'desc' => 'Riwayat mutasi stok per produk — Apotek',           'roles' => ['admin', 'apoteker'], 'badge' => 'STK-A']),
            $entry(['group' => 'Gudang', 'groupOrder' => 10, 'order' => 7, 'route' => 'gudang.kartu-stock-non',      'title' => 'Kartu Stock — Non-Medis',    'desc' => 'Riwayat mutasi stok per produk — Gudang Non-Medis', 'roles' => ['admin', 'tu'],     'badge' => 'STK-N']),

            // ── Keuangan ──────────────────────────────────────────────
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 1,  'route' => 'keuangan.penerimaan-kas-tu',           'title' => 'Penerimaan Kas TU',                'desc' => 'Catat penerimaan kas di luar transaksi pelayanan RS',                                  'roles' => ['admin', 'tu'], 'badge' => 'CI']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 2,  'route' => 'keuangan.pengeluaran-kas-tu',          'title' => 'Pengeluaran Kas TU',               'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan RS',                                 'roles' => ['admin', 'tu'], 'badge' => 'CO']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 3,  'route' => 'keuangan.pembayaran-hutang-pbf',       'title' => 'Pembayaran Hutang PBF',            'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)',                                   'roles' => ['admin', 'tu'], 'badge' => 'HTG']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 4,  'route' => 'keuangan.pembayaran-hutang-non-medis', 'title' => 'Pembayaran Hutang Non-Medis',      'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis',                                    'roles' => ['admin', 'tu'], 'badge' => 'HTG-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 5,  'route' => 'keuangan.topup-supplier-pbf',          'title' => 'Setor DP Supplier PBF',            'desc' => 'Setor uang muka / DP ke supplier obat (PBF)',                                          'roles' => ['admin', 'tu'], 'badge' => 'DP']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 6,  'route' => 'keuangan.topup-supplier-non-medis',    'title' => 'Setor DP Supplier Non-Medis',      'desc' => 'Setor uang muka / DP ke supplier non-medis',                                           'roles' => ['admin', 'tu'], 'badge' => 'DP-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 7,  'route' => 'keuangan.saldo-kas',                   'title' => 'Saldo Kas',                        'desc' => 'Posisi saldo kas/bank per tanggal — riwayat transaksi & edit saldo awal tahun (admin)','roles' => ['admin', 'tu'], 'badge' => 'SK']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 8,  'route' => 'keuangan.buku-besar',                  'title' => 'Buku Besar',                       'desc' => 'Mutasi & saldo per akun dalam periode tertentu',                                       'roles' => ['admin', 'tu'], 'badge' => 'BB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 9,  'route' => 'keuangan.laba-rugi',                   'title' => 'Laporan Laba Rugi',                'desc' => 'Pendapatan vs beban — laba/rugi periode berjalan',                                     'roles' => ['admin', 'tu'], 'badge' => 'LR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 11, 'order' => 10, 'route' => 'keuangan.neraca',                      'title' => 'Laporan Neraca',                   'desc' => 'Posisi aktiva, kewajiban & modal per tanggal',                                         'roles' => ['admin', 'tu'], 'badge' => 'NRC']),

            // ── Operasi ────────────────────────────────────────────────
            $entry(['group' => 'Operasi', 'groupOrder' => 12, 'order' => 1, 'route' => 'operasi.jadwal-operasi', 'title' => 'Jadwal Operasi', 'desc' => 'Booking & manajemen jadwal operasi pasien', 'roles' => ['admin', 'mr', 'perawat'], 'badge' => 'OK']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 13, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard',     'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session',         'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 13, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control',       'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin'], 'badge' => 'MNT']),
            $entry(['group' => 'Sistem', 'groupOrder' => 13, 'order' => 3, 'route' => 'database-monitor.user-control',             'title' => 'User Control',           'desc' => 'Kelola user & hak akses sistem',                 'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 13, 'order' => 4, 'route' => 'database-monitor.role-control',             'title' => 'Role Control',           'desc' => 'Kelola role & permission sistem',                'roles' => ['admin'], 'badge' => 'ROL']),
        ]);

        return array_values($rows);
    }

    #[Computed]
    public function visibleMenus(): array
    {
        $userRoles = auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->toArray();

        return collect($this->masterMenus)->filter(fn($m) => !empty(array_intersect($m['roles'], $userRoles)))->values()->toArray();
    }

    #[Computed]
    public function groupedMenus()
    {
        return collect($this->visibleMenus)
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }
};
?>

<div>
    {{-- HEADER (harus di sini, jangan di dalam div) --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Dashboard
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pusat menu aplikasi —
                <span class="font-medium">
                    Role Aktif : {{ auth()->user()->getRoleNames()->implode(', ') }}
                </span>
            </p>
        </div>
    </header>

    {{-- BODY WRAPPER: SAMA kayak Master Poli --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: mirip sticky toolbar poli (optional) --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label value="Cari Menu" class="sr-only" />
                        <x-text-input wire:model.live.debounce.250ms="search" placeholder="Cari menu..."
                            class="block w-full" />
                    </div>

                    {{-- (optional) right side action kalau mau nanti --}}
                    <div class="hidden lg:block"></div>
                </div>
            </div>

            {{-- GRID MENU — Accordion --}}
            <div x-data="{ activeGroup: null }">

                @forelse ($this->groupedMenus as $groupName => $menus)
                    <div x-data="{ group: '{{ $groupName }}' }">

                        {{-- GROUP HEADER --}}
                        <button type="button" @click="activeGroup = (activeGroup === group) ? null : group"
                            class="flex items-center gap-3 w-full mt-6 mb-3 group/header">
                            <h2 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors
                    text-gray-400 dark:text-gray-500
                    group-hover/header:text-gray-600 dark:group-hover/header:text-gray-300"
                                :class="activeGroup === group ? 'text-gray-700 dark:text-gray-200' : ''">
                                {{ $groupName }}
                            </h2>
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                :class="activeGroup === group ? 'rotate-0' : '-rotate-90'" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- GRID --}}
                        <div x-show="activeGroup === group" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">

                            @foreach ($menus as $m)
                                <a href="{{ $m['href'] }}" wire:navigate
                                    class="flex flex-col gap-3 p-4 transition-colors duration-200 bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                                    <div class="grid grid-cols-4 gap-2">
                                        @if (!empty($m['badge']))
                                            <span
                                                class="col-span-1 self-start inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                    bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                {{ $m['badge'] }}
                                            </span>
                                        @endif
                                        <div class="flex-1 min-w-0 col-span-3">
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $m['title'] }}</h3>
                                            <p class="mt-0.5 text-xs text-gray-500 truncate dark:text-gray-400">
                                                {{ $m['desc'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach

                        </div>
                    </div>

                @empty
                    <div class="py-10 text-center text-gray-500 dark:text-gray-400">
                        Menu tidak ditemukan / tidak ada akses.
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>
