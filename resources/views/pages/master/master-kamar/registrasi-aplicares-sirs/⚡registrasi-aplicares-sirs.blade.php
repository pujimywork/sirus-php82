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
// │  STRUKTUR FOLDER registrasi-aplicares-sirs/                                │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  ⚡registrasi-aplicares-sirs.blade.php  ← FILE INI                 │
// │     Komponen utama: tombol aksi, 2 modal (bulk + data terdaftar),   │
// │     logic bulk registrasi ke Aplicares & SIRS.                      │
// │                                                                     │
// │  ⚡aplicares-actions.blade.php                                      │
// │     Tab "Data Terdaftar Aplicares" — menampilkan daftar kamar       │
// │     yang sudah terdaftar di Aplicares beserta statusnya.            │
// │                                                                     │
// │  ⚡sirs-actions.blade.php                                           │
// │     Tab "Data Terdaftar SIRS" — menampilkan daftar kamar            │
// │     yang sudah terdaftar di SIRS beserta tipe tempat tidurnya.      │
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
// │  Tombol ada di HEADER halaman utama (master-bangsal.blade.php),     │
// │  memanggil komponen ini via Alpine $dispatch():                      │
// │                                                                     │
// │  [Daftarkan Semua ke Aplicares & SIRS]                              │
// │     → $dispatch('registrasi.openBulkRegistrasiAplicaresSirs')                        │
// │     → #[On('registrasi.openBulkRegistrasiAplicaresSirs')] openBulkRegistrasiAplicaresSirs()           │
// │     → Buka modal bulk registrasi                                    │
// │     → Tab Aplicares / Tab SIRS                                      │
// │     → Alur per tab:                                                 │
// │        1. Tarik data referensi dari API (kode kelas / tipe TT)      │
// │        2. User mapping: Kelas RS → Kode Aplicares / Tipe TT SIRS   │
// │        3. Klik "Daftarkan" → loop semua kamar dari rsmst_rooms:     │
// │           - Jika kamar sudah punya kode → update ketersediaan       │
// │           - Jika belum → ambil dari mapping kelas → daftarkan baru  │
// │           - Hasil per kamar ditampilkan di tabel (bulk-results)     │
// │                                                                     │
// │  [Data Kamar Terdaftar di Aplicares & SIRS]                         │
// │     → $dispatch('registrasi.openDataTerdaftarAplicaresSirs')                             │
// │     → #[On('registrasi.openDataTerdaftarAplicaresSirs')] openDataTerdaftarAplicaresSirs()                     │
// │     → Buka modal data terdaftar                                     │
// │     → Tab Aplicares (aplicares-actions) — tarik & tampilkan data    │
// │     → Tab SIRS (sirs-actions) — tarik & tampilkan data              │
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

    /* --- Buka modal ketersediaan (dipanggil dari header via event) --- */
    #[On('registrasi.openDataTerdaftarAplicaresSirs')]
    public function openDataTerdaftarAplicaresSirs(): void
    {
        $this->dispatch('open-modal', name: 'ketersediaan-kamar');
    }

    /* --- Buka modal bulk daftarkan (dipanggil dari header via event) --- */
    #[On('registrasi.openBulkRegistrasiAplicaresSirs')]
    public function openBulkRegistrasiAplicaresSirs(): void
    {
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
        $this->dispatch('open-modal', name: 'bulk-daftar-kamar');
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
                    if ($room->sirs_id_t_tt) {
                        $res    = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $room->sirs_id_t_tt]))->getOriginalContent();
                        $first  = $res['fasyankes'][0] ?? [];
                        $status = (string) ($first['status'] ?? '500');
                        $row['ok']  = $status === '200';
                        $row['msg'] = $status === '200' ? 'Diperbarui' : ($first['message'] ?? 'Gagal');
                    } else {
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

                {{-- SECTION 1 — Status (Aplicares) — TODO step 5 --}}
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 shrink-0">
                    <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500 italic">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H3m0 0l3-3m-3 3l3 3m8-8V5a4 4 0 014 4v2m0 0l3 3m-3-3l-3 3"/></svg>
                        <span>Section 1 — Status ringkas (akan diisi di step 5)</span>
                    </div>
                </div>

                {{-- SECTION 2 — Daftarkan Massal (collapsible) — TODO step 2 --}}
                <div class="border-b border-gray-100 dark:border-gray-800 shrink-0">
                    <button type="button" @click="showBulk = !showBulk"
                        class="w-full px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Daftarkan Massal</span>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">(klik untuk expand — mapping kelas + tombol daftarkan)</span>
                        </div>
                        <svg :class="showBulk ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="showBulk" x-transition.opacity class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 text-xs italic text-gray-400 dark:text-gray-500">
                        TODO step 2 — migrate konten "Daftarkan ke Aplicares" dari modal lama ke sini.
                    </div>
                </div>

                {{-- SECTION 3 — Data Terdaftar Online — TODO step 3 --}}
                <div class="flex-1 overflow-hidden flex flex-col">
                    <div class="px-5 py-8 text-xs italic text-gray-400 dark:text-gray-500">
                        TODO step 3 — migrate konten aplicares-actions.blade.php ke sini (tabel data terdaftar + Hapus + Samakan).
                    </div>
                </div>
            </div>

            {{-- Tab content: SIRS --}}
            <div x-show="tab === 'sirs'" class="flex flex-col flex-1 overflow-hidden">

                {{-- SECTION 1 — Status (SIRS) — TODO step 5 --}}
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 shrink-0">
                    <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500 italic">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H3m0 0l3-3m-3 3l3 3m8-8V5a4 4 0 014 4v2m0 0l3 3m-3-3l-3 3"/></svg>
                        <span>Section 1 — Status ringkas (akan diisi di step 5)</span>
                    </div>
                </div>

                {{-- SECTION 2 — Daftarkan Massal (collapsible) — TODO step 2 --}}
                <div class="border-b border-gray-100 dark:border-gray-800 shrink-0">
                    <button type="button" @click="showBulk = !showBulk"
                        class="w-full px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Daftarkan Massal</span>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">(klik untuk expand — mapping kelas + tombol daftarkan)</span>
                        </div>
                        <svg :class="showBulk ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="showBulk" x-transition.opacity class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 text-xs italic text-gray-400 dark:text-gray-500">
                        TODO step 2 — migrate konten "Daftarkan ke SIRS" dari modal lama ke sini.
                    </div>
                </div>

                {{-- SECTION 3 — Data Terdaftar Online — TODO step 4 --}}
                <div class="flex-1 overflow-hidden flex flex-col">
                    <div class="px-5 py-8 text-xs italic text-gray-400 dark:text-gray-500">
                        TODO step 4 — migrate konten sirs-actions.blade.php ke sini (tabel data terdaftar SIRS + Hapus).
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

    {{-- ══ MODAL KETERSEDIAAN KAMAR EKSTERNAL ═══════════════════ --}}
    <x-modal name="ketersediaan-kamar" size="full" height="full" focusable>
        <div x-data="{ tab: 'aplicares' }" class="flex flex-col h-[calc(100vh-8rem)]">

            {{-- Header --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.05] dark:opacity-[0.08]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15 shrink-0">
                            <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                                Data Kamar Terdaftar di Aplicares &amp; SIRS
                            </h3>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS Aplicares</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS Kemenkes</span>
                            </div>
                        </div>
                    </div>
                    <x-secondary-button type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ketersediaan-kamar' })" class="!p-2 shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- Tab bar --}}
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

            {{-- Tab content --}}
            <div class="flex-1 overflow-hidden flex flex-col bg-white dark:bg-gray-900">
                <div x-show="tab === 'aplicares'" class="flex flex-col h-full">
                    <livewire:pages::master.master-kamar.registrasi-aplicares-sirs.aplicares-actions wire:key="tab-aplicares" />
                </div>
                <div x-show="tab === 'sirs'" class="flex flex-col h-full">
                    <livewire:pages::master.master-kamar.registrasi-aplicares-sirs.sirs-actions wire:key="tab-sirs" />
                </div>
            </div>

        </div>
    </x-modal>

    {{-- ══ MODAL BULK DAFTARKAN SEMUA ══════════════════════════ --}}
    <x-modal name="bulk-daftar-kamar" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-8rem)]">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 shrink-0">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                            Daftarkan Semua Kamar ke Aplicares &amp; SIRS
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            Sinkronisasi seluruh kamar ke BPJS Aplicares dan/atau SIRS Kemenkes sekaligus.
                        </p>
                    </div>
                </div>
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'bulk-daftar-kamar' })" class="!p-2 shrink-0">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-secondary-button>
            </div>

            {{-- Tab bar: Aplicares | SIRS --}}
            <div x-data="{ tab: 'aplicares' }" class="flex flex-col flex-1 overflow-hidden">
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

                {{-- ── TAB APLICARES ──────────────────────────────── --}}
                <div x-show="tab === 'aplicares'" class="flex flex-col flex-1 overflow-hidden">

                    {{-- Panduan --}}
                    <div class="px-5 py-3 border-b border-blue-100 dark:border-blue-900/40 shrink-0 bg-white dark:bg-gray-900">
                        <div class="flex items-start gap-0">
                            <div class="flex flex-col items-center">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">1</div>
                                <div class="w-px flex-1 bg-blue-200 dark:bg-blue-800 mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Tarik data kode kelas dari Aplicares</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik tombol <strong class="text-blue-600 dark:text-blue-400">Tarik Data Aplicares</strong> di bawah untuk mengambil daftar kode kelas yang tersedia di sistem BPJS Aplicares.</p>
                            </div>
                            <div class="flex flex-col items-center ml-6">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">2</div>
                                <div class="w-px flex-1 bg-blue-200 dark:bg-blue-800 mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Sesuaikan mapping kelas</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Pilih kode Aplicares yang sesuai untuk setiap kelas kamar RS. Kamar yang sudah punya kode akan diperbarui; yang belum akan didaftarkan baru.</p>
                            </div>
                            <div class="flex flex-col items-center ml-6">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">3</div>
                                <div class="w-px flex-1 bg-transparent mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Jalankan pendaftaran massal</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik <strong class="text-blue-600 dark:text-blue-400">Daftarkan ke Aplicares</strong> — sistem akan memproses semua kamar sekaligus dan menampilkan hasilnya.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Mapping Kelas → Kode Aplicares --}}
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 bg-blue-50/60 dark:bg-blue-900/10">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                Mapping Kelas RS &rarr; Kode Aplicares
                                <span class="font-normal text-blue-500 dark:text-blue-400 ml-1">(untuk kamar yang belum punya kode)</span>
                            </span>
                            <x-secondary-button wire:click="muatReferensiKamarAplicares" wire:loading.attr="disabled"
                                wire:target="muatReferensiKamarAplicares" class="!py-1 !px-2.5 !text-xs shrink-0">
                                <x-loading size="xs" wire:loading wire:target="muatReferensiKamarAplicares" class="mr-1" />
                                <svg wire:loading.remove wire:target="muatReferensiKamarAplicares" class="w-3 h-3 mr-1"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                </svg>
                                <span wire:loading.remove wire:target="muatReferensiKamarAplicares">Tarik Data Aplicares</span>
                                <span wire:loading wire:target="muatReferensiKamarAplicares">Menarik&hellip;</span>
                            </x-secondary-button>
                        </div>

                        {{-- Feedback: error / empty --}}
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
                        <div class="grid grid-cols-5 gap-5">
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

                    {{-- Toolbar Aplicares --}}
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                        @if (!empty($aplBulkResults))
                            @php
                                $aplOk   = collect($aplBulkResults)->where('ok', true)->count();
                                $aplFail = collect($aplBulkResults)->where('ok', false)->count();
                                $aplSkip = collect($aplBulkResults)->where('ok', null)->count();
                            @endphp
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $aplOk }} ok</span>
                                @if ($aplFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $aplFail }} gagal</span> @endif
                                @if ($aplSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $aplSkip }} dilewati</span> @endif
                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                <span class="text-gray-400 dark:text-gray-500">{{ count($aplBulkResults) }} kamar</span>
                            </div>
                        @else
                            <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                        @endif
                        <x-primary-button wire:click="sinkronBulkKamarKeAplicares"
                            wire:loading.attr="disabled" wire:target="sinkronBulkKamarKeAplicares" class="shrink-0 gap-2">
                            <x-loading size="xs" wire:loading wire:target="sinkronBulkKamarKeAplicares" />
                            <svg wire:loading.remove wire:target="sinkronBulkKamarKeAplicares" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span wire:loading.remove wire:target="sinkronBulkKamarKeAplicares">Daftarkan ke Aplicares</span>
                            <span wire:loading wire:target="sinkronBulkKamarKeAplicares">Memproses&hellip;</span>
                        </x-primary-button>
                    </div>

                    {{-- Loading --}}
                    <div wire:loading wire:target="sinkronBulkKamarKeAplicares"
                         class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                        <x-loading size="md" class="block mb-2" />
                        Mendaftarkan semua kamar ke Aplicares&hellip;
                    </div>

                    {{-- Tabel --}}
                    <div wire:loading.remove wire:target="sinkronBulkKamarKeAplicares" class="flex-1 overflow-auto">
                        @if (empty($aplBulkResults))
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500 text-sm">
                                <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <p>Klik <strong>Daftarkan ke Aplicares</strong> untuk memulai sinkronisasi.</p>
                            </div>
                        @else
                            @include('pages.master.master-kamar.registrasi-aplicares-sirs.bulk-results', ['rows' => $aplBulkResults])
                        @endif
                    </div>
                </div>

                {{-- ── TAB SIRS ──────────────────────────────────── --}}
                <div x-show="tab === 'sirs'" class="flex flex-col flex-1 overflow-hidden">

                    {{-- Panduan --}}
                    <div class="px-5 py-3 border-b border-green-100 dark:border-green-900/40 shrink-0 bg-white dark:bg-gray-900">
                        <div class="flex items-start gap-0">
                            <div class="flex flex-col items-center">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">1</div>
                                <div class="w-px flex-1 bg-green-200 dark:bg-green-800 mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Tarik data tipe tempat tidur dari SIRS</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik tombol <strong class="text-green-600 dark:text-green-400">Tarik Data SIRS</strong> di bawah untuk mengambil daftar tipe tempat tidur dari SIRS Kemenkes.</p>
                            </div>
                            <div class="flex flex-col items-center ml-6">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">2</div>
                                <div class="w-px flex-1 bg-green-200 dark:bg-green-800 mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Sesuaikan mapping kelas ke tipe TT</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Pilih tipe tempat tidur SIRS yang sesuai untuk setiap kelas kamar RS. Kamar yang sudah terdaftar akan diperbarui; yang belum akan didaftarkan baru.</p>
                            </div>
                            <div class="flex flex-col items-center ml-6">
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">3</div>
                                <div class="w-px flex-1 bg-transparent mt-1 min-h-[20px]"></div>
                            </div>
                            <div class="ml-3 pb-4">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Jalankan pendaftaran massal</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik <strong class="text-green-600 dark:text-green-400">Daftarkan ke SIRS</strong> — sistem akan memproses semua kamar sekaligus dan menampilkan hasilnya per baris.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Mapping Kelas → Tipe TT SIRS --}}
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 bg-green-50/60 dark:bg-green-900/10">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <span class="text-xs font-semibold text-green-700 dark:text-green-300">
                                Mapping Kelas RS &rarr; Kode Tipe TT SIRS
                                <span class="font-normal text-green-500 dark:text-green-400 ml-1">(untuk kamar yang belum punya id_tt)</span>
                            </span>
                            <x-secondary-button wire:click="muatReferensiTempatTidurSirs" wire:loading.attr="disabled"
                                wire:target="muatReferensiTempatTidurSirs" class="!py-1 !px-2.5 !text-xs shrink-0">
                                <x-loading size="xs" wire:loading wire:target="muatReferensiTempatTidurSirs" class="mr-1" />
                                <svg wire:loading.remove wire:target="muatReferensiTempatTidurSirs" class="w-3 h-3 mr-1"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                </svg>
                                <span wire:loading.remove wire:target="muatReferensiTempatTidurSirs">Tarik Data SIRS</span>
                                <span wire:loading wire:target="muatReferensiTempatTidurSirs">Menarik&hellip;</span>
                            </x-secondary-button>
                        </div>

                        {{-- Feedback: error / empty --}}
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

                        <div class="grid grid-cols-5 gap-5">
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

                    {{-- Toolbar SIRS --}}
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                        @if (!empty($sirsBulkResults))
                            @php
                                $srsOk   = collect($sirsBulkResults)->where('ok', true)->count();
                                $srsFail = collect($sirsBulkResults)->where('ok', false)->count();
                                $srsSkip = collect($sirsBulkResults)->where('ok', null)->count();
                            @endphp
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $srsOk }} ok</span>
                                @if ($srsFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $srsFail }} gagal</span> @endif
                                @if ($srsSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $srsSkip }} dilewati</span> @endif
                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                <span class="text-gray-400 dark:text-gray-500">{{ count($sirsBulkResults) }} kamar</span>
                            </div>
                        @else
                            <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                        @endif
                        <x-primary-button wire:click="sinkronBulkKamarKeSirs"
                            wire:loading.attr="disabled" wire:target="sinkronBulkKamarKeSirs" class="shrink-0 gap-2">
                            <x-loading size="xs" wire:loading wire:target="sinkronBulkKamarKeSirs" />
                            <svg wire:loading.remove wire:target="sinkronBulkKamarKeSirs" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span wire:loading.remove wire:target="sinkronBulkKamarKeSirs">Daftarkan ke SIRS</span>
                            <span wire:loading wire:target="sinkronBulkKamarKeSirs">Memproses&hellip;</span>
                        </x-primary-button>
                    </div>

                    {{-- Loading --}}
                    <div wire:loading wire:target="sinkronBulkKamarKeSirs"
                         class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                        <x-loading size="md" class="block mb-2" />
                        Mendaftarkan semua kamar ke SIRS Kemenkes&hellip;
                    </div>

                    {{-- Tabel --}}
                    <div wire:loading.remove wire:target="sinkronBulkKamarKeSirs" class="flex-1 overflow-auto">
                        @if (empty($sirsBulkResults))
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500 text-sm">
                                <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <p>Klik <strong>Daftarkan ke SIRS</strong> untuk memulai sinkronisasi.</p>
                            </div>
                        @else
                            @include('pages.master.master-kamar.registrasi-aplicares-sirs.bulk-results', ['rows' => $sirsBulkResults])
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

</div>
