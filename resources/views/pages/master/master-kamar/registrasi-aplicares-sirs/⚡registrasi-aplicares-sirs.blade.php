<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  REGISTRASI APLICARES & SIRS                                       ║
// ║  master-kamar/registrasi-aplicares-sirs/registrasi-aplicares-sirs.blade.php║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  APA ITU APLICARES & SIRS?                                         │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  1. APLICARES (Aplikasi Komunikasi Antar Rumah Sakit)               │
// │     Sistem milik BPJS Kesehatan untuk mengelola ketersediaan        │
// │     tempat tidur RS secara real-time. RS wajib mendaftarkan         │
// │     setiap kamar + kode kelas agar BPJS bisa memonitor             │
// │     ketersediaan bed untuk pasien rujukan.                          │
// │     API Trait: AplicaresTrait                                       │
// │                                                                     │
// │  2. SIRS (Sistem Informasi Rumah Sakit) Kemenkes                    │
// │     Sistem milik Kementerian Kesehatan RI untuk pelaporan           │
// │     data tempat tidur RS. Setiap kamar didaftarkan dengan           │
// │     tipe TT (id_tt) agar masuk pelaporan nasional.                  │
// │     API Trait: SirsTrait                                            │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  STRUKTUR FOLDER registrasi-aplicares-sirs/                          │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  ⚡registrasi-aplicares-sirs.blade.php  ← FILE INI (tunggal)        │
// │     Komponen Livewire utama + 1 modal unified "kelola-aplicares-sirs"│
// │     berisi 2 tab (Aplicares, SIRS) × 3 section:                     │
// │        Section 1 — Status overview (cards: lokal aktif, terdaftar,  │
// │                    belum terdaftar, mismatch)                        │
// │        Section 2 — Daftarkan Massal (collapsible, muncul setelah    │
// │                    user klik Ambil Data di Section 3)               │
// │        Section 3 — Data Terdaftar online (tarik + tabel + Hapus +   │
// │                    Samakan Kapasitas untuk Aplicares)               │
// │                                                                     │
// │  bulk-results.blade.php                                             │
// │     Shared partial — tabel hasil proses bulk (ok/gagal/dilewati).   │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  CARA KERJA                                                         │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  Tombol [Kelola Aplicares & SIRS] di HEADER master-bangsal.blade.php│
// │  → Alpine $dispatch('registrasi.openKelolaAplicaresSirs')           │
// │  → #[On] openKelolaAplicaresSirs() prep class maps + buka modal     │
// │                                                                     │
// │  Alur per tab di dalam modal:                                       │
// │   1. User buka tab (Aplicares / SIRS)                               │
// │   2. Section 1 tampilkan rekap lokal vs online (lazy computed)       │
// │   3. User klik "Ambil Data" di Section 3 → tarik dari API online    │
// │      → toast feedback (success/info/error) + error box persistent   │
// │   4. Setelah tarik, Section 2 muncul dengan opsi Daftarkan Massal   │
// │      untuk kamar yang belum terdaftar (mapping kelas + bulk POST)   │
// │   5. Section 3 tabel daftar ruangan + aksi:                          │
// │      - Hapus (dari sisi online)                                     │
// │      - Samakan Kapasitas (khusus Aplicares, kalau ada mismatch)     │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  KOLOM DATABASE TERKAIT (di tabel rsmst_rooms)                     │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  aplic_kodekelas  → Kode kelas Aplicares (mapping dari class_id)   │
// │  sirs_id_tt       → ID tipe tempat tidur SIRS                      │
// │  sirs_id_t_tt     → ID transaksi TT SIRS (didapat setelah POST)   │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {
    use AplicaresTrait, SirsTrait;

    /* --- Bulk Daftarkan Semua --- */
    public array  $aplBulkResults  = [];
    public array  $sirsBulkResults = [];
    public array  $aplClassMap     = [];
    public array  $aplRefList      = [];
    public bool   $loadingAplRef   = false;
    public string $aplRefError     = '';
    public bool   $sudahTarikAplRef = false;
    public array  $sirsClassMap    = [];
    public array  $sirsRefList     = [];
    public bool   $loadingSirsRef  = false;
    public string $sirsRefError    = '';
    public bool   $sudahTarikSirsRef = false;

    /* --- Data Terdaftar Aplicares (Section 3 tab Aplicares) --- */
    public bool   $loadingAplicares     = false;
    public string $aplicaresError       = '';
    public array  $aplicaresData        = [];
    public int    $aplicTotal           = 0;
    public bool   $sudahTarikAplicares  = false;
    /** map room_id => jumlah bed lokal, untuk deteksi mismatch kapasitas Aplicares */
    public array  $bedCountLokal        = [];

    /* --- Data Terdaftar SIRS (Section 3 tab SIRS) --- */
    public bool   $loadingSirs          = false;
    public string $sirsError            = '';
    public array  $sirsData             = [];
    public bool   $sudahTarikSirs       = false;

    /* ═══════════════════════════════════════════════════════════════════
       STATUS OVERVIEW (Section 1) — computed dari state + DB
       ═══════════════════════════════════════════════════════════════════ */

    /** Rekap status Aplicares — lokal aktif vs online, belum terdaftar, mismatch kapasitas */
    #[Computed]
    public function rekapStatusAplicares(): array
    {
        $lokalAktif = DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_beds as bd', 'r.room_id', '=', 'bd.room_id')
            ->selectRaw('r.room_id, COUNT(bd.bed_no) AS jumlah_bed')
            ->where('r.active_status', '1')
            ->groupBy('r.room_id')
            ->pluck('jumlah_bed', 'room_id')
            ->toArray();

        $koderuangOnline = collect($this->aplicaresData)
            ->map(fn($r) => (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $totalLokal  = count($lokalAktif);
        $totalOnline = count($this->aplicaresData);
        $belumDaftar = count(array_diff(array_keys($lokalAktif), $koderuangOnline));

        return [
            'totalLokal'  => $totalLokal,
            'totalOnline' => $totalOnline,
            'belumDaftar' => $belumDaftar,
        ];
    }

    /** Rekap status SIRS — lokal aktif vs online, belum terdaftar (pakai sirs_id_t_tt) */
    #[Computed]
    public function rekapStatusSirs(): array
    {
        $totalLokal = (int) DB::table('rsmst_rooms')->where('active_status', '1')->count();

        // Kamar yang punya sirs_id_t_tt dianggap terdaftar (bukti sudah upload ke SIRS)
        $terdaftarLokal = (int) DB::table('rsmst_rooms')
            ->where('active_status', '1')
            ->whereNotNull('sirs_id_t_tt')
            ->where('sirs_id_t_tt', '!=', '')
            ->count();

        $belumDaftar = max(0, $totalLokal - $terdaftarLokal);

        return [
            'totalLokal'     => $totalLokal,
            'terdaftarLokal' => $terdaftarLokal,
            'belumDaftar'    => $belumDaftar,
        ];
    }

    /* --- Buka modal terpadu Kelola Aplicares & SIRS (dipanggil dari header) --- */
    #[On('registrasi.openKelolaAplicaresSirs')]
    public function openKelolaAplicaresSirs(): void
    {
        // Prep state Section 2 (Daftarkan Massal) — sama seperti openBulkRegistrasi lama
        $this->aplBulkResults  = [];
        $this->sirsBulkResults = [];
        $this->aplClassMap = DB::table('rsmst_rooms')
            ->whereNotNull('aplic_kodekelas')->where('aplic_kodekelas', '!=', '')
            ->selectRaw('class_id, MIN(aplic_kodekelas) as aplic_kodekelas')
            ->groupBy('class_id')->pluck('aplic_kodekelas', 'class_id')->toArray();

        $this->sirsClassMap = DB::table('rsmst_rooms')
            ->whereNotNull('sirs_id_tt')->where('sirs_id_tt', '!=', '')
            ->selectRaw('class_id, MIN(sirs_id_tt) as sirs_id_tt')
            ->groupBy('class_id')->pluck('sirs_id_tt', 'class_id')->toArray();

        $this->dispatch('open-modal', name: 'kelola-aplicares-sirs');
    }

    public function muatReferensiKamarAplicares(): void
    {
        $this->loadingAplRef = true;
        $this->aplRefList    = [];
        $this->aplRefError   = '';

        try {
            $res  = $this->referensiKamar()->getOriginalContent();
            $list = $res['response']['list'] ?? ($res['list'] ?? ($res['data'] ?? []));
            $this->aplRefList = is_array($list) ? array_values($list) : [];

            $jumlah = count($this->aplRefList);
            if ($jumlah === 0) {
                $this->dispatch('toast', type: 'info', message: 'Berhasil menarik, tapi referensi kamar dari Aplicares BPJS kosong.');
            } else {
                $this->dispatch('toast', type: 'success', message: "Berhasil menarik {$jumlah} referensi kelas dari Aplicares.");
            }
        } catch (\Throwable $e) {
            $this->aplRefError = $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal menarik referensi Aplicares: ' . $e->getMessage());
        }

        $this->sudahTarikAplRef = true;
        $this->loadingAplRef    = false;
    }

    public function muatReferensiTempatTidurSirs(): void
    {
        $this->loadingSirsRef = true;
        $this->sirsRefList    = [];
        $this->sirsRefError   = '';

        try {
            $res  = $this->sirsRefTempaTidur()->getOriginalContent();
            $list = $res['tempat_tidur'] ?? ($res['response'] ?? ($res['data'] ?? []));
            $this->sirsRefList = is_array($list) ? array_values($list) : [];

            $jumlah = count($this->sirsRefList);
            if ($jumlah === 0) {
                $this->dispatch('toast', type: 'info', message: 'Berhasil menarik, tapi referensi tempat tidur dari SIRS Kemenkes kosong.');
            } else {
                $this->dispatch('toast', type: 'success', message: "Berhasil menarik {$jumlah} referensi tipe TT dari SIRS.");
            }
        } catch (\Throwable $e) {
            $this->sirsRefError = $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal menarik referensi SIRS: ' . $e->getMessage());
        }

        $this->sudahTarikSirsRef = true;
        $this->loadingSirsRef    = false;
    }

    /* ═══════════════════════════════════════════════════════════════════
       DATA TERDAFTAR APLICARES (Section 3, tab Aplicares)
       Dipindah dari ⚡aplicares-actions.blade.php saat refactor unified.
       ═══════════════════════════════════════════════════════════════════ */
    public function muatDaftarKamarTerdaftarAplicares(): void
    {
        $this->loadingAplicares = true;
        $this->aplicaresError   = '';

        try {
            $res  = $this->ketersediaanKamarRS(1, 100)->getOriginalContent();
            $body = $res['response'] ?? [];
            $this->aplicaresData = $body['list'] ?? ($body['data'] ?? []);
            $this->aplicTotal    = (int) ($body['total'] ?? count($this->aplicaresData));

            $this->bedCountLokal = DB::table('rsmst_beds')
                ->selectRaw('room_id, COUNT(*) AS jumlah_bed')
                ->groupBy('room_id')
                ->pluck('jumlah_bed', 'room_id')
                ->toArray();

            $jumlah = count($this->aplicaresData);
            if ($jumlah === 0) {
                $this->dispatch('toast', type: 'info', message: 'Berhasil menarik data, tapi belum ada kamar yang terdaftar di Aplicares BPJS.');
            } else {
                $this->dispatch('toast', type: 'success', message: "Berhasil menarik {$jumlah} data kamar dari Aplicares.");
            }
        } catch (\Throwable $e) {
            $this->aplicaresError = $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal menarik data Aplicares: ' . $e->getMessage());
        }

        $this->sudahTarikAplicares = true;
        $this->loadingAplicares    = false;
    }

    public function hapusKamarDariAplicares(string $kodekelas, string $koderuang): void
    {
        try {
            $res  = $this->hapusRuangan($kodekelas, $koderuang)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            if ($code == 1) {
                $this->dispatch('toast', type: 'success', message: "Ruangan {$koderuang} berhasil dihapus dari Aplicares.");
                $this->muatDaftarKamarTerdaftarAplicares();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal hapus: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /** Hapus SEMUA ruangan dari Aplicares BPJS — loop toleran, tidak stop saat ada yang gagal */
    public function hapusSemuaDariAplicares(): void
    {
        if (empty($this->aplicaresData)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk dihapus.');
            return;
        }

        $ok = 0; $fail = 0; $gagalList = [];
        foreach ($this->aplicaresData as $r) {
            $koderuang = (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? ''));
            $kodekelas = (string) ($r['kodekelas'] ?? ($r['kode_kelas'] ?? ''));
            if ($koderuang === '' || $kodekelas === '') { $fail++; continue; }

            try {
                $res  = $this->hapusRuangan($kodekelas, $koderuang)->getOriginalContent();
                $code = $res['metadata']['code'] ?? 500;
                if ($code == 1) { $ok++; } else { $fail++; $gagalList[] = $koderuang; }
            } catch (\Throwable $e) {
                $fail++; $gagalList[] = $koderuang;
            }
        }

        $total = $ok + $fail;
        $msg   = "Hapus massal Aplicares selesai: {$ok} berhasil, {$fail} gagal (dari {$total} ruangan).";
        if ($gagalList) {
            $msg .= ' Gagal: ' . implode(', ', array_slice($gagalList, 0, 5)) . (count($gagalList) > 5 ? '…' : '');
        }
        $this->dispatch('toast', type: $fail === 0 ? 'success' : ($ok === 0 ? 'error' : 'warning'), message: $msg);
        $this->muatDaftarKamarTerdaftarAplicares();
    }

    /** Samakan kapasitas online Aplicares dengan bed count lokal (rsmst_beds = source of truth) */
    public function samakanKapasitasAplicares(string $kodekelas, string $koderuang): void
    {
        $bedLokal = (int) ($this->bedCountLokal[$koderuang] ?? 0);

        $online = collect($this->aplicaresData)->first(fn($r) =>
            (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? '')) === $koderuang
        );
        if (!$online) {
            $this->dispatch('toast', type: 'error', message: 'Data online tidak ditemukan, tarik ulang dulu.');
            return;
        }

        $terpakai = (int) DB::table('rstxn_rihdrs')
            ->where('room_id', $koderuang)
            ->where('ri_status', 'I')
            ->count();
        $tersedia = max(0, $bedLokal - $terpakai);

        $payload = [
            'kodekelas'          => $kodekelas,
            'koderuang'          => $koderuang,
            'namaruang'          => $online['namaruang'] ?? ($online['nama_ruang'] ?? ''),
            'kapasitas'          => $bedLokal,
            'tersedia'           => $tersedia,
            'tersediapria'       => 0,
            'tersediawanita'     => 0,
            'tersediapriawanita' => $tersedia,
        ];

        try {
            $res  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            if ($code == 1) {
                $this->dispatch('toast', type: 'success', message: "Kapasitas {$koderuang} disamakan: {$bedLokal} bed ({$tersedia} tersedia).");
                $this->muatDaftarKamarTerdaftarAplicares();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal samakan: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       DATA TERDAFTAR SIRS (Section 3, tab SIRS)
       Dipindah dari ⚡sirs-actions.blade.php saat refactor unified.
       ═══════════════════════════════════════════════════════════════════ */
    public function muatDaftarTempatTidurTerdaftarSirs(): void
    {
        $this->loadingSirs = true;
        $this->sirsError   = '';

        try {
            $res  = $this->sirsGetTempaTidur()->getOriginalContent();
            $list = $res['fasyankes'] ?? ($res['response'] ?? ($res['data'] ?? []));
            $this->sirsData = is_array($list) ? array_values($list) : [];

            $jumlah = count($this->sirsData);
            if ($jumlah === 0) {
                $this->dispatch('toast', type: 'info', message: 'Berhasil menarik data, tapi belum ada tempat tidur yang terdaftar di SIRS Kemenkes.');
            } else {
                $this->dispatch('toast', type: 'success', message: "Berhasil menarik {$jumlah} data tempat tidur dari SIRS.");
            }
        } catch (\Throwable $e) {
            $this->sirsError = $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal menarik data SIRS: ' . $e->getMessage());
        }

        $this->sudahTarikSirs = true;
        $this->loadingSirs    = false;
    }

    public function hapusTempatTidurDariSirs(string $idTTt): void
    {
        try {
            $res    = $this->sirsHapusTempaTidur($idTTt)->getOriginalContent();
            $first  = $res['fasyankes'][0] ?? [];
            $status = (string) ($first['status'] ?? '500');
            $msg    = $first['message'] ?? '-';

            // "tidak ditemukan" = sudah hilang di SIRS → tujuan tercapai, treat as success
            $sudahHilang = str_contains($msg, 'tidak ditemukan');

            if ($status === '200' || $sudahHilang) {
                DB::table('rsmst_rooms')
                    ->where('sirs_id_t_tt', $idTTt)
                    ->update(['sirs_id_t_tt' => null]);

                $toastMsg = $sudahHilang
                    ? "Data TT {$idTTt} sudah tidak ada di SIRS — referensi lokal dibersihkan."
                    : ($msg ?: "Data TT {$idTTt} berhasil dihapus dari SIRS.");
                $this->dispatch('toast', type: $sudahHilang ? 'info' : 'success', message: $toastMsg);
                $this->muatDaftarTempatTidurTerdaftarSirs();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal hapus SIRS: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error SIRS: ' . $e->getMessage());
        }
    }

    /** Hapus SEMUA tempat tidur dari SIRS Kemenkes — loop toleran; clear sirs_id_t_tt lokal per sukses */
    public function hapusSemuaDariSirs(): void
    {
        if (empty($this->sirsData)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk dihapus.');
            return;
        }

        $ok = 0; $stale = 0; $fail = 0; $skip = 0; $gagalList = [];
        foreach ($this->sirsData as $r) {
            $idTTt = (string) ($r['id_t_tt'] ?? '');
            if ($idTTt === '') { $skip++; continue; }

            try {
                $res    = $this->sirsHapusTempaTidur($idTTt)->getOriginalContent();
                $first  = $res['fasyankes'][0] ?? [];
                $status = (string) ($first['status'] ?? '500');
                $msg    = (string) ($first['message'] ?? '');

                // "tidak ditemukan" = sudah hilang di SIRS → tujuan tercapai
                $sudahHilang = str_contains($msg, 'tidak ditemukan');

                if ($status === '200' || $sudahHilang) {
                    DB::table('rsmst_rooms')
                        ->where('sirs_id_t_tt', $idTTt)
                        ->update(['sirs_id_t_tt' => null]);
                    $sudahHilang ? $stale++ : $ok++;
                } else {
                    $fail++; $gagalList[] = $idTTt;
                }
            } catch (\Throwable $e) {
                $fail++; $gagalList[] = $idTTt;
            }
        }

        $total = $ok + $stale + $fail + $skip;
        $parts = ["{$ok} berhasil"];
        if ($stale) { $parts[] = "{$stale} sudah hilang (dibersihkan lokal)"; }
        $parts[] = "{$fail} gagal";
        if ($skip)  { $parts[] = "{$skip} dilewati (tanpa id_t_tt)"; }
        $msg = 'Hapus massal SIRS selesai: ' . implode(', ', $parts) . " (dari {$total} TT).";
        if ($gagalList) {
            $msg .= ' Gagal: ' . implode(', ', array_slice($gagalList, 0, 5)) . (count($gagalList) > 5 ? '…' : '');
        }
        $sukses = $ok + $stale;
        $this->dispatch('toast', type: $fail === 0 ? 'success' : ($sukses === 0 ? 'error' : 'warning'), message: $msg);
        $this->muatDaftarTempatTidurTerdaftarSirs();
    }

    private function ambilKamarAktifUntukSinkronBulk(string $select): \Illuminate\Support\Collection
    {
        return DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
            ->leftJoin('rsmst_bangsals as b', 'r.bangsal_id', '=', 'b.bangsal_id')
            ->selectRaw("r.room_id, r.room_name, r.class_id, c.class_desc, b.bangsal_name,
                         (SELECT COUNT(*) FROM rsmst_beds bd WHERE bd.room_id = r.room_id) AS jumlah_bed, {$select}")
            ->where('r.active_status', '1')
            ->orderBy('b.bangsal_name')
            ->orderBy('r.room_name')
            ->get();
    }

    public function sinkronBulkKamarKeAplicares(): void
    {
        $rooms   = $this->ambilKamarAktifUntukSinkronBulk('r.aplic_kodekelas');
        $results = [];

        foreach ($rooms as $room) {
            $namaRuang = trim(($room->bangsal_name ?? '') . ' - ' . ($room->room_name ?? '') . ' - ' . ($room->class_desc ?? ''));
            $bedCount  = (int) ($room->jumlah_bed ?? 0);
            $row       = ['room_id' => $room->room_id, 'namaRuang' => $namaRuang, 'ok' => null, 'msg' => 'Kode Aplicares belum diisi'];

            $kodekelas = $room->aplic_kodekelas ?: ($this->aplClassMap[$room->class_id] ?? null);

            if ($kodekelas) {
                if (!$room->aplic_kodekelas) {
                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['aplic_kodekelas' => $kodekelas]);
                }
                $payload = [
                    'kodekelas'          => $kodekelas,
                    'koderuang'          => $room->room_id,
                    'namaruang'          => $namaRuang,
                    'kapasitas'          => $bedCount,
                    'tersedia'           => $bedCount,
                    'tersediapria'       => 0,
                    'tersediawanita'     => 0,
                    'tersediapriawanita' => 0,
                ];
                try {
                    $res  = $this->ruanganBaru($payload)->getOriginalContent();
                    $code = $res['metadata']['code'] ?? 500;
                    if ($code == 1) {
                        $row['ok']  = true;
                        $row['msg'] = 'Berhasil didaftarkan';
                    } else {
                        $resU  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
                        $codeU = $resU['metadata']['code'] ?? 500;
                        $row['ok']  = $codeU == 1;
                        $row['msg'] = $codeU == 1 ? 'Diperbarui' : ($resU['metadata']['message'] ?? 'Gagal');
                    }
                } catch (\Throwable $e) {
                    $row['ok']  = false;
                    $row['msg'] = $e->getMessage();
                }
            }

            $results[] = $row;
        }

        $this->aplBulkResults = $results;
    }

    public function sinkronBulkKamarKeSirs(): void
    {
        $rooms = $this->ambilKamarAktifUntukSinkronBulk('r.sirs_id_tt, r.sirs_id_t_tt');

        $sirsCache = [];
        try {
            $raw       = $this->sirsGetTempaTidur()->getOriginalContent();
            $sirsCache = $raw['fasyankes'] ?? [];
        } catch (\Throwable) {}

        $results = [];

        foreach ($rooms as $room) {
            $namaRuang = trim(($room->bangsal_name ?? '') . ' - ' . ($room->room_name ?? '') . ' - ' . ($room->class_desc ?? ''));
            $bedCount  = (int) ($room->jumlah_bed ?? 0);
            $sirsIdTt  = $room->sirs_id_tt ?: ($this->sirsClassMap[$room->class_id] ?? null);
            $row       = ['room_id' => $room->room_id, 'namaRuang' => $namaRuang, 'ok' => null, 'msg' => 'id_tt SIRS belum diisi'];

            if ($sirsIdTt) {
                if (!$room->sirs_id_tt) {
                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_tt' => $sirsIdTt]);
                }
                $payload = [
                    'ruang'               => $namaRuang,
                    'jumlah_ruang'        => 1,
                    'jumlah'              => $bedCount,
                    'terpakai'            => 0,
                    'terpakai_suspek'     => 0,
                    'terpakai_konfirmasi' => 0,
                    'antrian'             => 0,
                    'prepare'             => 0,
                    'prepare_plan'        => 0,
                    'covid'               => 0,
                ];
                try {
                    $localIdTTt = $room->sirs_id_t_tt;

                    // 1) Coba update dulu kalau punya id_t_tt lokal
                    if ($localIdTTt) {
                        $res    = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $localIdTTt]))->getOriginalContent();
                        $first  = $res['fasyankes'][0] ?? [];
                        $status = (string) ($first['status'] ?? '500');
                        $msg    = $first['message'] ?? 'Gagal';

                        if ($status === '200') {
                            $row['ok']  = true;
                            $row['msg'] = 'Diperbarui';
                        } elseif (str_contains($msg, 'tidak ditemukan')) {
                            // Stale → null-kan lokal & fallback ke insert
                            DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_t_tt' => null]);
                            $localIdTTt = null;
                        } else {
                            $row['ok']  = false;
                            $row['msg'] = $msg;
                        }
                    }

                    // 2) Insert baru — juga jalan sebagai fallback kalau update balas "tidak ditemukan"
                    if (!$localIdTTt && $row['ok'] === null) {
                        $res    = $this->sirsKirimTempaTidur(array_merge($payload, ['id_tt' => $sirsIdTt]))->getOriginalContent();
                        $first  = $res['fasyankes'][0] ?? [];
                        $status = (string) ($first['status'] ?? '500');
                        $msg    = $first['message'] ?? '-';

                        if ($status === '200' && !str_contains($msg, 'sudah ada')) {
                            $idTTt = (string) ($first['id_t_tt'] ?? '');
                            DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_t_tt' => $idTTt ?: null]);
                            $row['ok']  = true;
                            $row['msg'] = 'Berhasil didaftarkan';
                        } elseif ($status === '200' && str_contains($msg, 'sudah ada')) {
                            $match = collect($sirsCache)->first(fn($r) =>
                                (string) ($r['id_tt'] ?? '') === (string) $sirsIdTt &&
                                ($r['id_t_tt'] ?? null) !== null
                            );
                            if ($match) {
                                $idTTt  = (string) $match['id_t_tt'];
                                $resU   = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $idTTt]))->getOriginalContent();
                                $firstU = $resU['fasyankes'][0] ?? [];
                                $statU  = (string) ($firstU['status'] ?? '500');
                                if ($statU === '200') {
                                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_t_tt' => $idTTt]);
                                    $row['ok']  = true;
                                    $row['msg'] = 'Sudah ada, berhasil diperbarui';
                                } else {
                                    $row['ok']  = false;
                                    $row['msg'] = 'Gagal update: ' . ($firstU['message'] ?? '-');
                                }
                            } else {
                                $row['ok']  = null;
                                $row['msg'] = 'Sudah ada di SIRS, id_t_tt tidak ditemukan';
                            }
                        } else {
                            $row['ok']  = false;
                            $row['msg'] = $msg;
                        }
                    }
                } catch (\Throwable $e) {
                    $row['ok']  = false;
                    $row['msg'] = $e->getMessage();
                }
            }

            $results[] = $row;
        }

        $this->sirsBulkResults = $results;
    }

};
?>

<div>

    {{-- ══════════════════════════════════════════════════════════════════
         MODAL TERPADU: KELOLA APLICARES & SIRS
         Gabungan dari 2 modal lama (bulk-daftar-kamar + ketersediaan-kamar).
         Struktur: 2 tab (Aplicares, SIRS) × 3 section:
           1. Status ringkas (lokal vs online, mismatch, belum terdaftar)
           2. Daftarkan Massal (collapsible — mapping + tombol Daftarkan)
           3. Data Terdaftar Online (tarik data + tabel + Hapus + Samakan)
         ══════════════════════════════════════════════════════════════════ --}}
    <x-modal name="kelola-aplicares-sirs" size="full" height="full" focusable>
        <div x-data="{ tab: 'aplicares', showBulk: false }" class="flex flex-col h-[calc(100vh-8rem)]">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15 shrink-0">
                        <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                            Kelola Aplicares &amp; SIRS
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            Pantau status, daftarkan kamar, dan kelola data yang sudah terdaftar di BPJS Aplicares / SIRS Kemenkes.
                        </p>
                    </div>
                </div>
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'kelola-aplicares-sirs' })" class="!p-2 shrink-0">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-secondary-button>
            </div>

            {{-- Tab bar: Aplicares | SIRS --}}
            <div class="flex border-b border-gray-200 dark:border-gray-700 shrink-0 bg-white dark:bg-gray-900">
                <button type="button" @click="tab = 'aplicares'"
                    :class="tab === 'aplicares'
                        ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                    Aplicares
                </button>
                <button type="button" @click="tab = 'sirs'"
                    :class="tab === 'sirs'
                        ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                    Kemenkes
                </button>
            </div>

            {{-- Tab content: APLICARES --}}
            <div x-show="tab === 'aplicares'" class="flex flex-col flex-1 overflow-hidden">

                {{-- SECTION 1 — Status Aplicares --}}
                @php $statApl = $this->rekapStatusAplicares; @endphp
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 shrink-0">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {{-- Kamar Aktif (RS) --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                             title="Jumlah kamar aktif di master lokal RS (rsmst_rooms.active_status = '1')">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 00-2 2H6a2 2 0 00-2-2V6zM14 14a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Kamar Aktif (RS)</div>
                                <div class="text-lg font-bold text-gray-700 dark:text-gray-200 leading-tight">{{ $statApl['totalLokal'] }}</div>
                            </div>
                        </div>
                        {{-- Terdaftar Aplicares --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800"
                             title="Jumlah ruang yang sudah terdaftar di Aplicares BPJS (dari hasil tarik data online)">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-blue-600 dark:text-blue-400 font-semibold">Terdaftar Aplicares</div>
                                <div class="text-lg font-bold text-blue-700 dark:text-blue-300 leading-tight">
                                    @if ($sudahTarikAplicares){{ $statApl['totalOnline'] }}@else<span class="text-xs italic font-normal text-blue-400">belum ditarik</span>@endif
                                </div>
                            </div>
                        </div>
                        {{-- Belum di Aplicares --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ $sudahTarikAplicares && $statApl['belumDaftar'] > 0 ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800' }}"
                             title="Kamar aktif di RS yang belum ada di daftar ruang Aplicares BPJS (perlu didaftarkan)">
                            <svg class="w-5 h-5 {{ $sudahTarikAplicares && $statApl['belumDaftar'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider {{ $sudahTarikAplicares && $statApl['belumDaftar'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} font-semibold">Belum di Aplicares</div>
                                <div class="text-lg font-bold {{ $sudahTarikAplicares && $statApl['belumDaftar'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300' }} leading-tight">
                                    @if ($sudahTarikAplicares){{ $statApl['belumDaftar'] }}@else<span class="text-xs italic font-normal text-gray-400">—</span>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 2 — Daftarkan Massal Aplicares (muncul setelah user tarik Data Terdaftar) --}}
                @if ($sudahTarikAplicares)
                <div class="border-b border-gray-100 dark:border-gray-800 shrink-0">
                    <button type="button" @click="showBulk = !showBulk"
                        class="w-full px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Daftarkan Massal ke Aplicares</span>
                            @if (!empty($aplBulkResults))
                                @php
                                    $aplOk2   = collect($aplBulkResults)->where('ok', true)->count();
                                    $aplFail2 = collect($aplBulkResults)->where('ok', false)->count();
                                @endphp
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $aplOk2 }} ok</span>
                                @if ($aplFail2)<span class="text-[11px] text-red-600 dark:text-red-400 font-mono font-semibold">{{ $aplFail2 }} gagal</span>@endif
                            @else
                                <span class="text-[11px] text-gray-400 dark:text-gray-500">(klik untuk expand — mapping kelas + tombol daftarkan)</span>
                            @endif
                        </div>
                        <svg :class="showBulk ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="showBulk" x-transition.opacity x-cloak class="border-t border-gray-100 dark:border-gray-800 max-h-[55vh] overflow-auto">
                        {{-- Panduan 3-step --}}
                        <div class="px-5 py-3 border-b border-blue-100 dark:border-blue-900/40 bg-white dark:bg-gray-900">
                            <div class="flex items-start gap-0 flex-wrap">
                                <div class="flex items-center gap-2 pr-4">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">1</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Tarik referensi</strong> kode kelas dari BPJS</div>
                                </div>
                                <div class="flex items-center gap-2 pr-4">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">2</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Mapping</strong> tiap kelas RS ke kode Aplicares</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">3</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Klik Daftarkan</strong> — proses semua kamar aktif</div>
                                </div>
                            </div>
                        </div>

                        {{-- Mapping Kelas → Kode Aplicares --}}
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-blue-50/60 dark:bg-blue-900/10">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                    Mapping Kelas RS &rarr; Kode Aplicares
                                    <span class="font-normal text-blue-500 dark:text-blue-400 ml-1">(untuk kamar yang belum punya kode)</span>
                                </span>
                                <x-secondary-button wire:click="muatReferensiKamarAplicares" wire:loading.attr="disabled"
                                    wire:target="muatReferensiKamarAplicares" class="!py-1 !px-2.5 !text-xs shrink-0">
                                    <x-loading size="xs" wire:loading wire:target="muatReferensiKamarAplicares" class="mr-1" />
                                    <svg wire:loading.remove wire:target="muatReferensiKamarAplicares" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="muatReferensiKamarAplicares">Tarik Data Aplicares</span>
                                    <span wire:loading wire:target="muatReferensiKamarAplicares">Menarik&hellip;</span>
                                </x-secondary-button>
                            </div>

                            {{-- Feedback error / empty --}}
                            @if ($aplRefError)
                                <div class="mb-2 px-3 py-2 rounded border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-semibold text-red-700 dark:text-red-300">Gagal tarik referensi Aplicares</div>
                                            <div class="text-[11px] text-red-600 dark:text-red-400 break-words mt-0.5">{{ $aplRefError }}</div>
                                            <div class="text-[10px] text-red-500/80 mt-0.5">Cek koneksi API BPJS atau kredensial, lalu klik Tarik Data lagi.</div>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($sudahTarikAplRef && empty($aplRefList))
                                <div class="mb-2 px-3 py-2 rounded border-l-4 border-amber-400 bg-amber-50 dark:bg-amber-900/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="text-[11px] text-amber-700 dark:text-amber-400">
                                            <span class="font-semibold">Referensi kosong.</span> Berhasil terhubung ke Aplicares tapi tidak ada data kode kelas yang dikembalikan.
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @php $kelasList = DB::table('rsmst_class')->select('class_id', 'class_desc')->orderBy('class_id')->get(); @endphp
                            <div class="grid grid-cols-5 gap-3">
                                @foreach ($kelasList as $kls)
                                    <div>
                                        <x-input-label :value="$kls->class_desc" class="truncate" :title="$kls->class_desc" />
                                        <x-select-input wire:model.live="aplClassMap.{{ $kls->class_id }}" class="mt-1 w-full">
                                            <option value="">—</option>
                                            @foreach ($aplRefList as $ref)
                                                <option value="{{ $ref['kodekelas'] ?? '' }}">{{ $ref['kodekelas'] ?? '' }}</option>
                                            @endforeach
                                            @if (empty($aplRefList) && !empty($aplClassMap[$kls->class_id]))
                                                <option value="{{ $aplClassMap[$kls->class_id] }}" selected>{{ $aplClassMap[$kls->class_id] }}</option>
                                            @endif
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Toolbar Aplicares + tombol Daftarkan --}}
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                            @if (!empty($aplBulkResults))
                                @php
                                    $aplOk   = collect($aplBulkResults)->where('ok', true)->count();
                                    $aplFail = collect($aplBulkResults)->where('ok', false)->count();
                                    $aplSkip = collect($aplBulkResults)->where('ok', null)->count();
                                @endphp
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $aplOk }} ok</span>
                                    @if ($aplFail)<span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $aplFail }} gagal</span>@endif
                                    @if ($aplSkip)<span class="text-gray-400 dark:text-gray-500 font-mono">{{ $aplSkip }} dilewati</span>@endif
                                    <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                    <span class="text-gray-400 dark:text-gray-500">{{ count($aplBulkResults) }} kamar</span>
                                </div>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                            @endif
                            <x-primary-button wire:click="sinkronBulkKamarKeAplicares" wire:loading.attr="disabled" wire:target="sinkronBulkKamarKeAplicares" class="shrink-0 gap-2">
                                <x-loading size="xs" wire:loading wire:target="sinkronBulkKamarKeAplicares" />
                                <svg wire:loading.remove wire:target="sinkronBulkKamarKeAplicares" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span wire:loading.remove wire:target="sinkronBulkKamarKeAplicares">Daftarkan ke Aplicares</span>
                                <span wire:loading wire:target="sinkronBulkKamarKeAplicares">Memproses&hellip;</span>
                            </x-primary-button>
                        </div>

                        {{-- Loading proses bulk --}}
                        <div wire:loading wire:target="sinkronBulkKamarKeAplicares" class="flex flex-col items-center justify-center py-10 text-sm text-gray-400">
                            <x-loading size="md" class="block mb-2" />
                            Mendaftarkan semua kamar ke Aplicares&hellip;
                        </div>

                        {{-- Hasil bulk --}}
                        <div wire:loading.remove wire:target="sinkronBulkKamarKeAplicares">
                            @if (!empty($aplBulkResults))
                                @include('pages.master.master-kamar.registrasi-aplicares-sirs.bulk-results', ['rows' => $aplBulkResults])
                            @endif
                        </div>
                    </div>
                </div>

                @endif

                {{-- SECTION 3 — Data Terdaftar Aplicares Online --}}
                <div class="flex-1 overflow-hidden flex flex-col">
                    @unless ($sudahTarikAplicares)
                        <div class="px-5 py-3 bg-blue-50/60 dark:bg-blue-900/10 border-b border-blue-100 dark:border-blue-900/40 shrink-0">
                            <div class="flex items-start gap-2 text-[11px] text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Klik <strong>Ambil Data Aplicares</strong> di bawah untuk tarik daftar kamar yang sudah terdaftar di BPJS. Setelah ditarik, opsi <strong>Daftarkan Massal</strong> akan muncul di atas untuk tambah kamar yang belum terdaftar.</span>
                            </div>
                        </div>
                    @endunless
                    {{-- Toolbar: Ambil Data + Hapus Semua --}}
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Data Kamar Terdaftar di Aplicares BPJS</span>
                        <div class="flex items-center gap-2">
                            @if (!empty($aplicaresData))
                                <x-confirm-button variant="danger"
                                    action="hapusSemuaDariAplicares"
                                    title="Hapus Semua dari Aplicares"
                                    :message="'Yakin hapus SEMUA ' . count($aplicaresData) . ' ruangan dari Aplicares BPJS? Aksi ini TIDAK BISA DIBATALKAN — semua data ketersediaan kamar di sisi BPJS akan dihapus dan perlu didaftarkan ulang.'"
                                    confirmText="Ya, hapus semua" cancelText="Batal"
                                    class="text-xs">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Hapus Semua ({{ count($aplicaresData) }})
                                </x-confirm-button>
                            @endif
                            <x-secondary-button wire:click="muatDaftarKamarTerdaftarAplicares" wire:loading.attr="disabled" wire:target="muatDaftarKamarTerdaftarAplicares,hapusSemuaDariAplicares">
                                <x-loading size="xs" wire:loading wire:target="muatDaftarKamarTerdaftarAplicares,hapusSemuaDariAplicares" class="mr-1" />
                                <svg wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares,hapusSemuaDariAplicares" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
                                </svg>
                                <span wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares,hapusSemuaDariAplicares">
                                    {{ empty($aplicaresData) ? 'Ambil Data Aplicares' : 'Perbarui Data' }}
                                </span>
                                <span wire:loading wire:target="muatDaftarKamarTerdaftarAplicares">Mengambil data…</span>
                                <span wire:loading wire:target="hapusSemuaDariAplicares">Menghapus semua…</span>
                            </x-secondary-button>
                        </div>
                    </div>

                    @if ($aplicaresError)
                        <div class="px-5 py-4 bg-red-50 dark:bg-red-900/20 shrink-0 border-l-4 border-red-500">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-red-700 dark:text-red-300">Gagal menarik data dari Aplicares</div>
                                    <div class="mt-1 text-xs text-red-600 dark:text-red-400 break-words">{{ $aplicaresError }}</div>
                                    <div class="mt-2 text-xs text-red-500/80 dark:text-red-400/80">Cek koneksi ke API BPJS, kredensial, atau coba klik <strong>Ambil Data Aplicares</strong> lagi.</div>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Loading --}}
                        <div wire:loading wire:target="muatDaftarKamarTerdaftarAplicares" class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                            <x-loading size="md" class="block mb-2" />
                            Memuat data dari Aplicares…
                        </div>

                        {{-- Rekap per kelas + Mismatch badge --}}
                        @if (!empty($aplicaresData))
                            @php
                                $aplRekap = collect($aplicaresData)
                                    ->groupBy(fn($r) => $r['kodekelas'] ?? ($r['kode_kelas'] ?? '-'))
                                    ->map(fn($g) => [
                                        'jumlah_ruang'    => $g->count(),
                                        'total_kapasitas' => $g->sum('kapasitas'),
                                        'total_tersedia'  => $g->sum('tersedia'),
                                    ]);
                                $aplTotalRuang     = collect($aplicaresData)->count();
                                $aplTotalKapasitas = collect($aplicaresData)->sum('kapasitas');
                                $aplTotalTersedia  = collect($aplicaresData)->sum('tersedia');
                                $aplMismatchCount  = collect($aplicaresData)->filter(function ($r) {
                                    $kr = (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? ''));
                                    $online = (int) ($r['kapasitas'] ?? 0);
                                    $lokal  = (int) ($this->bedCountLokal[$kr] ?? 0);
                                    return $kr !== '' && $online !== $lokal;
                                })->count();
                            @endphp
                            <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares"
                                class="px-5 py-2.5 border-b border-blue-100 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-900/10 shrink-0 flex flex-wrap gap-3 items-center">
                                <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 self-center mr-1">Rekap per Kelas:</span>
                                @foreach ($aplRekap as $kode => $r)
                                    <div class="flex items-center gap-1.5 bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-800 rounded-lg px-2.5 py-1 text-[11px]">
                                        <span class="font-mono font-bold text-blue-700 dark:text-blue-300">{{ $kode }}</span>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $r['jumlah_ruang'] }} ruang</span>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-gray-700 dark:text-gray-200">Kap: <span class="font-semibold">{{ $r['total_kapasitas'] }}</span></span>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-emerald-600 dark:text-emerald-400">Tersedia: <span class="font-semibold">{{ $r['total_tersedia'] }}</span></span>
                                    </div>
                                @endforeach
                                <div class="ml-auto flex items-center gap-2">
                                    @if ($aplMismatchCount > 0)
                                        <div class="flex items-center gap-1.5 bg-amber-500 dark:bg-amber-600 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold" title="Kamar yang kapasitas online-nya beda dengan jumlah bed lokal">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                            <span>{{ $aplMismatchCount }} Selisih Kapasitas</span>
                                        </div>
                                    @endif
                                    <div class="flex items-center gap-1.5 bg-blue-600 dark:bg-blue-700 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold">
                                        <span>Total:</span>
                                        <span>{{ $aplTotalRuang }} ruang</span>
                                        <span class="opacity-60">·</span>
                                        <span>Kap: {{ $aplTotalKapasitas }}</span>
                                        <span class="opacity-60">·</span>
                                        <span>Tersedia: {{ $aplTotalTersedia }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Tabel --}}
                        @php
                            $aplicaresDataSorted = collect($aplicaresData)
                                ->sortBy(fn($r) => $r['namaruang'] ?? ($r['nama_ruang'] ?? ''))
                                ->values()
                                ->all();
                        @endphp
                        <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares" class="flex-1 overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-semibold">Kode Ruang</th>
                                        <th class="px-5 py-3 text-left font-semibold">Nama Ruang</th>
                                        <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                                        <th class="px-5 py-3 text-center font-semibold">Kapasitas</th>
                                        <th class="px-5 py-3 text-center font-semibold">Tersedia</th>
                                        <th class="px-5 py-3 text-center font-semibold">Pria</th>
                                        <th class="px-5 py-3 text-center font-semibold">Wanita</th>
                                        <th class="px-5 py-3 text-center font-semibold">Campuran</th>
                                        <th class="px-5 py-3 text-center font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                                    @forelse ($aplicaresDataSorted as $aplic)
                                        @php
                                            $koderuang       = $aplic['koderuang'] ?? ($aplic['kode_ruang'] ?? '');
                                            $kodekelas       = $aplic['kodekelas'] ?? ($aplic['kode_kelas'] ?? '');
                                            $kapasitasOnline = (int) ($aplic['kapasitas'] ?? 0);
                                            $kapasitasLokal  = (int) ($this->bedCountLokal[$koderuang] ?? 0);
                                            $isMismatch      = $koderuang !== '' && $kapasitasOnline !== $kapasitasLokal;
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition {{ $isMismatch ? 'bg-amber-50/40 dark:bg-amber-900/10' : '' }}">
                                            <td class="px-5 py-3 font-mono font-semibold">{{ $koderuang ?: '-' }}</td>
                                            <td class="px-5 py-3">{{ $aplic['namaruang'] ?? ($aplic['nama_ruang'] ?? '-') }}</td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ $kodekelas ?: '-' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-center font-mono font-semibold">
                                                @if ($isMismatch)
                                                    <div class="inline-flex items-center gap-1.5" title="Online: {{ $kapasitasOnline }} · Lokal (rsmst_beds): {{ $kapasitasLokal }}">
                                                        <span class="text-amber-600 dark:text-amber-400">{{ $kapasitasOnline }}</span>
                                                        <span class="text-gray-400">/</span>
                                                        <span class="text-gray-700 dark:text-gray-200">{{ $kapasitasLokal }}</span>
                                                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                    </div>
                                                @else
                                                    {{ $kapasitasOnline }}
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $aplic['tersedia'] ?? '-' }}</td>
                                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediapria'] ?? '-' }}</td>
                                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediawanita'] ?? '-' }}</td>
                                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $aplic['tersediapriawanita'] ?? '-' }}</td>
                                            <td class="px-5 py-3 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    @if ($isMismatch)
                                                        <x-confirm-button variant="outline"
                                                            :action="'samakanKapasitasAplicares(\'' . $kodekelas . '\', \'' . $koderuang . '\')'"
                                                            title="Samakan Kapasitas"
                                                            :message="'Samakan kapasitas ruangan ' . $koderuang . ' di Aplicares dari ' . $kapasitasOnline . ' menjadi ' . $kapasitasLokal . ' (sesuai rsmst_beds)?'"
                                                            confirmText="Ya, samakan" cancelText="Batal"
                                                            class="text-xs">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/></svg>
                                                            Samakan
                                                        </x-confirm-button>
                                                    @endif
                                                    <x-confirm-button variant="danger"
                                                        :action="'hapusKamarDariAplicares(\'' . $kodekelas . '\', \'' . $koderuang . '\')'"
                                                        title="Hapus Aplicares"
                                                        :message="'Hapus ruangan ' . $koderuang . ' (' . $kodekelas . ') dari Aplicares BPJS?'"
                                                        confirmText="Ya, hapus" cancelText="Batal"
                                                        class="text-xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-5 py-16">
                                                @if ($sudahTarikAplicares)
                                                    <div class="flex flex-col items-center gap-2 text-center">
                                                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <div class="text-sm font-semibold text-amber-700 dark:text-amber-400">Data tidak tersedia</div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md">
                                                            Berhasil terhubung ke Aplicares BPJS, tapi belum ada kamar yang terdaftar di sisi BPJS.
                                                            Buka <strong>Section Daftarkan Massal</strong> di atas untuk mulai mendaftarkan kamar.
                                                        </p>
                                                    </div>
                                                @else
                                                    <div class="flex flex-col items-center gap-2 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                                        Belum ada data. Klik <strong>Ambil Data Aplicares</strong> untuk memuat.
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if (!empty($aplicaresData))
                            <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares"
                                class="px-5 py-2 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                {{ count($aplicaresData) }} data ruangan
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Tab content: SIRS --}}
            <div x-show="tab === 'sirs'" class="flex flex-col flex-1 overflow-hidden">

                {{-- SECTION 1 — Status SIRS --}}
                @php $statSirs = $this->rekapStatusSirs; @endphp
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 shrink-0">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {{-- Kamar Aktif (RS) --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                             title="Jumlah kamar aktif di master lokal RS (rsmst_rooms.active_status = '1')">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 00-2 2H6a2 2 0 00-2-2V6zM14 14a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Kamar Aktif (RS)</div>
                                <div class="text-lg font-bold text-gray-700 dark:text-gray-200 leading-tight">{{ $statSirs['totalLokal'] }}</div>
                            </div>
                        </div>
                        {{-- Terdaftar SIRS --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
                             title="Kamar yang sudah terdaftar di SIRS Kemenkes (punya sirs_id_t_tt di master lokal)">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-green-600 dark:text-green-400 font-semibold">Terdaftar SIRS</div>
                                <div class="text-lg font-bold text-green-700 dark:text-green-300 leading-tight">{{ $statSirs['terdaftarLokal'] }}</div>
                            </div>
                        </div>
                        {{-- Belum di SIRS --}}
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ $statSirs['belumDaftar'] > 0 ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800' }}"
                             title="Kamar aktif di RS yang belum punya mapping SIRS (perlu dikirim ke SIRS Kemenkes)">
                            <svg class="w-5 h-5 {{ $statSirs['belumDaftar'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider {{ $statSirs['belumDaftar'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} font-semibold">Belum di SIRS</div>
                                <div class="text-lg font-bold {{ $statSirs['belumDaftar'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300' }} leading-tight">{{ $statSirs['belumDaftar'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 2 — Daftarkan Massal SIRS (muncul setelah user tarik Data Terdaftar) --}}
                @if ($sudahTarikSirs)
                <div class="border-b border-gray-100 dark:border-gray-800 shrink-0">
                    <button type="button" @click="showBulk = !showBulk"
                        class="w-full px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Daftarkan Massal ke SIRS</span>
                            @if (!empty($sirsBulkResults))
                                @php
                                    $srsOk2   = collect($sirsBulkResults)->where('ok', true)->count();
                                    $srsFail2 = collect($sirsBulkResults)->where('ok', false)->count();
                                @endphp
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $srsOk2 }} ok</span>
                                @if ($srsFail2)<span class="text-[11px] text-red-600 dark:text-red-400 font-mono font-semibold">{{ $srsFail2 }} gagal</span>@endif
                            @else
                                <span class="text-[11px] text-gray-400 dark:text-gray-500">(klik untuk expand — mapping tipe TT + tombol daftarkan)</span>
                            @endif
                        </div>
                        <svg :class="showBulk ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="showBulk" x-transition.opacity x-cloak class="border-t border-gray-100 dark:border-gray-800 max-h-[55vh] overflow-auto">
                        {{-- Panduan 3-step --}}
                        <div class="px-5 py-3 border-b border-green-100 dark:border-green-900/40 bg-white dark:bg-gray-900">
                            <div class="flex items-start gap-0 flex-wrap">
                                <div class="flex items-center gap-2 pr-4">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">1</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Tarik referensi</strong> tipe TT dari Kemenkes</div>
                                </div>
                                <div class="flex items-center gap-2 pr-4">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">2</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Mapping</strong> tiap kelas RS ke tipe TT SIRS</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">3</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Klik Daftarkan</strong> — proses semua kamar aktif</div>
                                </div>
                            </div>
                        </div>

                        {{-- Mapping Kelas → Tipe TT SIRS --}}
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-green-50/60 dark:bg-green-900/10">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <span class="text-xs font-semibold text-green-700 dark:text-green-300">
                                    Mapping Kelas RS &rarr; Kode Tipe TT SIRS
                                    <span class="font-normal text-green-500 dark:text-green-400 ml-1">(untuk kamar yang belum punya id_tt)</span>
                                </span>
                                <x-secondary-button wire:click="muatReferensiTempatTidurSirs" wire:loading.attr="disabled"
                                    wire:target="muatReferensiTempatTidurSirs" class="!py-1 !px-2.5 !text-xs shrink-0">
                                    <x-loading size="xs" wire:loading wire:target="muatReferensiTempatTidurSirs" class="mr-1" />
                                    <svg wire:loading.remove wire:target="muatReferensiTempatTidurSirs" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="muatReferensiTempatTidurSirs">Tarik Data SIRS</span>
                                    <span wire:loading wire:target="muatReferensiTempatTidurSirs">Menarik&hellip;</span>
                                </x-secondary-button>
                            </div>

                            {{-- Feedback error / empty --}}
                            @if ($sirsRefError)
                                <div class="mb-2 px-3 py-2 rounded border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-semibold text-red-700 dark:text-red-300">Gagal tarik referensi SIRS</div>
                                            <div class="text-[11px] text-red-600 dark:text-red-400 break-words mt-0.5">{{ $sirsRefError }}</div>
                                            <div class="text-[10px] text-red-500/80 mt-0.5">Cek koneksi API SIRS Kemenkes atau kredensial, lalu klik Tarik Data lagi.</div>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($sudahTarikSirsRef && empty($sirsRefList))
                                <div class="mb-2 px-3 py-2 rounded border-l-4 border-amber-400 bg-amber-50 dark:bg-amber-900/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="text-[11px] text-amber-700 dark:text-amber-400">
                                            <span class="font-semibold">Referensi kosong.</span> Berhasil terhubung ke SIRS tapi tidak ada data tipe tempat tidur yang dikembalikan.
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-5 gap-3">
                                @foreach (DB::table('rsmst_class')->select('class_id', 'class_desc')->orderBy('class_id')->get() as $kls)
                                    <div>
                                        <x-input-label :value="$kls->class_desc" class="truncate" :title="$kls->class_desc" />
                                        <x-select-input wire:model.live="sirsClassMap.{{ $kls->class_id }}" class="mt-1 w-full">
                                            <option value="">—</option>
                                            @foreach ($sirsRefList as $ref)
                                                <option value="{{ $ref['kode_tt'] ?? '' }}">{{ $ref['kode_tt'] ?? '' }} – {{ $ref['nama_tt'] ?? '' }}</option>
                                            @endforeach
                                            @if (empty($sirsRefList) && !empty($sirsClassMap[$kls->class_id]))
                                                <option value="{{ $sirsClassMap[$kls->class_id] }}" selected>{{ $sirsClassMap[$kls->class_id] }}</option>
                                            @endif
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Toolbar SIRS + tombol Daftarkan --}}
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                            @if (!empty($sirsBulkResults))
                                @php
                                    $srsOk   = collect($sirsBulkResults)->where('ok', true)->count();
                                    $srsFail = collect($sirsBulkResults)->where('ok', false)->count();
                                    $srsSkip = collect($sirsBulkResults)->where('ok', null)->count();
                                @endphp
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $srsOk }} ok</span>
                                    @if ($srsFail)<span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $srsFail }} gagal</span>@endif
                                    @if ($srsSkip)<span class="text-gray-400 dark:text-gray-500 font-mono">{{ $srsSkip }} dilewati</span>@endif
                                    <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                    <span class="text-gray-400 dark:text-gray-500">{{ count($sirsBulkResults) }} kamar</span>
                                </div>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                            @endif
                            <x-primary-button wire:click="sinkronBulkKamarKeSirs" wire:loading.attr="disabled" wire:target="sinkronBulkKamarKeSirs" class="shrink-0 gap-2">
                                <x-loading size="xs" wire:loading wire:target="sinkronBulkKamarKeSirs" />
                                <svg wire:loading.remove wire:target="sinkronBulkKamarKeSirs" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span wire:loading.remove wire:target="sinkronBulkKamarKeSirs">Daftarkan ke SIRS</span>
                                <span wire:loading wire:target="sinkronBulkKamarKeSirs">Memproses&hellip;</span>
                            </x-primary-button>
                        </div>

                        {{-- Loading proses bulk --}}
                        <div wire:loading wire:target="sinkronBulkKamarKeSirs" class="flex flex-col items-center justify-center py-10 text-sm text-gray-400">
                            <x-loading size="md" class="block mb-2" />
                            Mendaftarkan semua kamar ke SIRS Kemenkes&hellip;
                        </div>

                        {{-- Hasil bulk --}}
                        <div wire:loading.remove wire:target="sinkronBulkKamarKeSirs">
                            @if (!empty($sirsBulkResults))
                                @include('pages.master.master-kamar.registrasi-aplicares-sirs.bulk-results', ['rows' => $sirsBulkResults])
                            @endif
                        </div>
                    </div>
                </div>

                @endif

                {{-- SECTION 3 — Data Terdaftar SIRS Online --}}
                <div class="flex-1 overflow-hidden flex flex-col">
                    @unless ($sudahTarikSirs)
                        <div class="px-5 py-3 bg-green-50/60 dark:bg-green-900/10 border-b border-green-100 dark:border-green-900/40 shrink-0">
                            <div class="flex items-start gap-2 text-[11px] text-green-700 dark:text-green-300">
                                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Klik <strong>Ambil Data SIRS</strong> di bawah untuk tarik daftar tempat tidur yang sudah terdaftar di Kemenkes. Setelah ditarik, opsi <strong>Daftarkan Massal</strong> akan muncul di atas untuk tambah kamar yang belum terdaftar.</span>
                            </div>
                        </div>
                    @endunless
                    {{-- Toolbar: Ambil Data SIRS + Hapus Semua --}}
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Data Tempat Tidur Terdaftar di SIRS Kemenkes</span>
                        <div class="flex items-center gap-2">
                            @if (!empty($sirsData))
                                <x-confirm-button variant="danger"
                                    action="hapusSemuaDariSirs"
                                    title="Hapus Semua dari SIRS"
                                    :message="'Yakin hapus SEMUA ' . count($sirsData) . ' tempat tidur dari SIRS Kemenkes? Aksi ini TIDAK BISA DIBATALKAN — semua id_t_tt akan dihapus dari sisi Kemenkes dan di-null-kan di rsmst_rooms lokal; perlu didaftarkan ulang untuk pelaporan.'"
                                    confirmText="Ya, hapus semua" cancelText="Batal"
                                    class="text-xs">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Hapus Semua ({{ count($sirsData) }})
                                </x-confirm-button>
                            @endif
                            <x-secondary-button wire:click="muatDaftarTempatTidurTerdaftarSirs" wire:loading.attr="disabled" wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs,hapusSemuaDariSirs">
                                <x-loading size="xs" wire:loading wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs,hapusSemuaDariSirs" class="mr-1" />
                                <svg wire:loading.remove wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs,hapusSemuaDariSirs" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
                                </svg>
                                <span wire:loading.remove wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs,hapusSemuaDariSirs">
                                    {{ empty($sirsData) ? 'Ambil Data SIRS' : 'Perbarui Data' }}
                                </span>
                                <span wire:loading wire:target="muatDaftarTempatTidurTerdaftarSirs">Mengambil data…</span>
                                <span wire:loading wire:target="hapusSemuaDariSirs">Menghapus semua…</span>
                            </x-secondary-button>
                        </div>
                    </div>

                    @if ($sirsError)
                        <div class="px-5 py-4 bg-red-50 dark:bg-red-900/20 shrink-0 border-l-4 border-red-500">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-red-700 dark:text-red-300">Gagal menarik data dari SIRS Kemenkes</div>
                                    <div class="mt-1 text-xs text-red-600 dark:text-red-400 break-words">{{ $sirsError }}</div>
                                    <div class="mt-2 text-xs text-red-500/80 dark:text-red-400/80">Cek koneksi ke API SIRS, kredensial, atau coba klik <strong>Ambil Data SIRS</strong> lagi.</div>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Loading --}}
                        <div wire:loading wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs" class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                            <x-loading size="md" class="block mb-2" />
                            Memuat data dari SIRS Kemenkes…
                        </div>

                        {{-- Rekap Total --}}
                        @if (!empty($sirsData))
                            @php
                                $sirsTotalRuang    = collect($sirsData)->sum('jumlah_ruang');
                                $sirsTotalJumlah   = collect($sirsData)->sum('jumlah');
                                $sirsTotalKosong   = collect($sirsData)->sum('kosong');
                                $sirsTotalTerpakai = collect($sirsData)->sum('terpakai');
                            @endphp
                            <div wire:loading.remove wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs"
                                class="px-5 py-2.5 border-b border-green-100 dark:border-green-900/40 bg-green-50/60 dark:bg-green-900/10 shrink-0 flex items-center justify-end">
                                <div class="flex items-center gap-1.5 bg-green-600 dark:bg-green-700 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold">
                                    <span>Total:</span>
                                    <span>{{ $sirsTotalRuang }} ruang</span>
                                    <span class="opacity-60">·</span>
                                    <span>Jml: {{ $sirsTotalJumlah }}</span>
                                    <span class="opacity-60">·</span>
                                    <span>Kosong: {{ $sirsTotalKosong }}</span>
                                    <span class="opacity-60">·</span>
                                    <span>Pakai: {{ $sirsTotalTerpakai }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Tabel --}}
                        @php
                            $sirsDataSorted = collect($sirsData)->sortBy('ruang')->values()->all();
                        @endphp
                        <div wire:loading.remove wire:target="muatDaftarTempatTidurTerdaftarSirs,hapusTempatTidurDariSirs" class="flex-1 overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Tipe TT</th>
                                        <th class="px-4 py-3 text-left font-semibold">Ruang</th>
                                        <th class="px-4 py-3 text-center font-semibold">Jml Ruang</th>
                                        <th class="px-4 py-3 text-center font-semibold">Jumlah</th>
                                        <th class="px-4 py-3 text-center font-semibold">Kosong</th>
                                        <th class="px-4 py-3 text-center font-semibold">Terpakai</th>
                                        <th class="px-4 py-3 text-center font-semibold">COVID</th>
                                        <th class="px-4 py-3 text-left font-semibold">Update</th>
                                        <th class="px-4 py-3 text-center font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                                    @forelse ($sirsDataSorted as $sirs)
                                        @php
                                            $idTTt    = (string) ($sirs['id_t_tt'] ?? '');
                                            $kosong   = (int) ($sirs['kosong'] ?? 0);
                                            $terpakai = (int) ($sirs['terpakai'] ?? 0);
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                                        {{ $sirs['id_tt'] ?? '-' }}
                                                    </span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[120px]" title="{{ $sirs['tt'] ?? '' }}">
                                                        {{ $sirs['tt'] ?? '-' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 font-medium">{{ $sirs['ruang'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center font-mono text-gray-500 dark:text-gray-400">{{ $sirs['jumlah_ruang'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center font-mono font-semibold">{{ $sirs['jumlah'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center font-mono font-semibold {{ $kosong > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">{{ $kosong }}</td>
                                            <td class="px-4 py-3 text-center font-mono font-semibold {{ $terpakai > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">{{ $terpakai }}</td>
                                            <td class="px-4 py-3 text-center">
                                                @if (!empty($sirs['covid']))
                                                    <x-badge variant="danger">COVID</x-badge>
                                                @else
                                                    <x-badge variant="gray">Non</x-badge>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $sirs['tglupdate'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center">
                                                @if ($idTTt !== '')
                                                    <x-confirm-button variant="danger"
                                                        :action="'hapusTempatTidurDariSirs(\'' . $idTTt . '\')'"
                                                        title="Hapus Data SIRS"
                                                        :message="'Hapus data TT ' . $idTTt . ' dari SIRS Kemenkes?'"
                                                        confirmText="Ya, hapus" cancelText="Batal"
                                                        class="text-xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        Hapus
                                                    </x-confirm-button>
                                                @else
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 italic">Belum terdaftar</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-5 py-16">
                                                @if ($sudahTarikSirs)
                                                    <div class="flex flex-col items-center gap-2 text-center">
                                                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <div class="text-sm font-semibold text-amber-700 dark:text-amber-400">Data tidak tersedia</div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md">
                                                            Berhasil terhubung ke SIRS Kemenkes, tapi belum ada tempat tidur yang terdaftar.
                                                            Buka <strong>Section Daftarkan Massal</strong> di atas untuk mulai mendaftarkan.
                                                        </p>
                                                    </div>
                                                @else
                                                    <div class="flex flex-col items-center gap-2 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                                        Belum ada data. Klik <strong>Ambil Data SIRS</strong> untuk memuat.
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if (!empty($sirsData))
                            <div class="px-5 py-2 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                {{ count($sirsData) }} data tempat tidur
                            </div>
                        @endif
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

</div>
