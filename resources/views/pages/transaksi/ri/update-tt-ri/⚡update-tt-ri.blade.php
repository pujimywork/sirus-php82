<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {
    use AplicaresTrait, SirsTrait;

    /**
     * Mapping id_tt → jenis tempat tidur SIRS Kemenkes (referensi resmi).
     * Sumber: LOV SIRS /Referensi/tempat_tidur yang dipakai di form master kamar.
     */
    private const SIRS_TT_LABEL = [
        '1'  => 'VVIP/ Super VIP',
        '2'  => 'VIP',
        '3'  => 'Kelas I',
        '4'  => 'Kelas II',
        '5'  => 'Kelas III',
        '6'  => 'ICU Tanpa Ventilator',
        '7'  => 'HCU',
        '8'  => 'ICCU/ICVCU Tanpa Ventilator',
        '9'  => 'RICU Tanpa Ventilator',
        '10' => 'NICU Tanpa Ventilator',
        '11' => 'PICU Tanpa Ventilator',
        '12' => 'Isolasi',
        '14' => 'Perinatologi',
        '24' => 'ICU Tekanan Negatif dengan Ventilator',
        '25' => 'ICU Tekanan Negatif tanpa Ventilator',
        '26' => 'ICU Tanpa Tekanan Negatif Dengan Ventilator',
        '27' => 'ICU Tanpa Tekanan Negatif Tanpa Ventilator',
        '28' => 'Isolasi Tekanan Negatif',
        '29' => 'Isolasi Tanpa Tekanan Negatif',
        '30' => 'NICU Khusus Covid',
        '31' => 'PICU Khusus Covid',
        '32' => 'IGD Khusus Covid',
        '33' => 'VK (TT Observasi di R Bersalin) Khusus Covid',
        '34' => 'Isolasi Perinatologi Khusus Covid',
        '36' => 'VK (TT Observasi di R Bersalin) Non Covid',
        '37' => 'Intermediate Ward (IGD)',
        '38' => 'ICU Dengan Ventilator',
        '39' => 'NICU Dengan Ventilator',
        '40' => 'PICU Dengan Ventilator',
        '50' => 'RICU Dengan Ventilator',
        '51' => 'ICCU/ICVCU Dengan Ventilator',
        '52' => 'KRIS JKN',
    ];

    /* -------------------------
     | State
     * ------------------------- */
    public array $rows = [];
    public array $logLines = [];

    /** Modal detail kamar — state minimal, query pasien dilakukan on-demand */
    public ?string $detailRoomId = null;
    public string $detailRoomName = '';
    public string $detailKelas = '';
    public array $detailPasien = [];

    /* -------------------------
     | Lifecycle
     * ------------------------- */
    public function mount(): void
    {
        $this->loadRows();
    }

    /* -------------------------
     | Data
     * ------------------------- */
    public function loadRows(): void
    {
        $this->rows = DB::table('rsmst_rooms as r')
            ->select(['r.room_id', 'r.room_name', 'r.class_id', 'r.aplic_kodekelas', 'r.sirs_id_tt', 'r.sirs_id_t_tt', 'c.class_desc', 'b.bangsal_name', DB::raw('(SELECT COUNT(*) FROM rsmst_beds WHERE room_id = r.room_id) as kapasitas'), DB::raw("(SELECT COUNT(*) FROM rstxn_rihdrs WHERE room_id = r.room_id AND ri_status = 'I') as terpakai")])
            ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
            ->leftJoin('rsmst_bangsals as b', 'r.bangsal_id', '=', 'b.bangsal_id')
            ->whereIn('r.active_status', ['1'])
            ->orderBy('b.bangsal_name')
            ->orderBy('r.room_name')
            ->orderBy('c.class_id')
            ->get()
            ->map(function ($r) {
                $kapasitas = (int) $r->kapasitas;
                $terpakaiRaw = (int) $r->terpakai;
                // Clamp terpakai ke kapasitas supaya SIRS/Aplicares tidak dapat data inkonsisten
                // (anomali: pasien pulang dibalikkan ke I sementara pasien lain masih I).
                $terpakai = min($kapasitas, $terpakaiRaw);
                $anomaliSelisih = $terpakaiRaw - $kapasitas; // >0 = ada anomali
                return [
                    'room_id' => (string) $r->room_id,
                    'rs_namabangsal' => (string) ($r->bangsal_name ?? ''),
                    'rs_namakamar' => (string) ($r->room_name ?? ''),
                    'rs_namakelas' => (string) ($r->class_desc ?? ''),
                    'class_id' => $r->class_id,
                    'aplic_kodekelas' => (string) ($r->aplic_kodekelas ?? ''),
                    'sirs_id_tt' => (string) ($r->sirs_id_tt ?? ''),
                    'sirs_tt_label' => self::SIRS_TT_LABEL[(string) ($r->sirs_id_tt ?? '')] ?? '',
                    'id_t_tt_sirs' => $r->sirs_id_t_tt ?? null ?: null,
                    'kapasitas' => $kapasitas,
                    'terpakai' => $terpakai,
                    'terpakai_raw' => $terpakaiRaw,
                    'anomali_selisih' => max(0, $anomaliSelisih),
                    'tersedia' => max(0, $kapasitas - $terpakai),
                    'status_aplic' => null,
                    'pesan_aplic' => '',
                    'last_sync_aplic' => null,
                    'status_sirs' => null,
                    'pesan_sirs' => '',
                    'last_sync_sirs' => null,
                ];
            })
            ->values()
            ->all();
    }

    public function refresh(): void
    {
        $this->loadRows();
        $this->logLines = [];
        $this->dispatch('toast', type: 'info', message: 'Data DB diperbarui.');
    }

    /** Buka modal daftar pasien yang sedang dirawat di kamar tersebut. */
    public function showDetailKamar(string $roomId): void
    {
        $room = collect($this->rows)->firstWhere('room_id', $roomId);
        if (!$room) {
            return;
        }

        $this->detailRoomId = $roomId;
        $this->detailRoomName = $room['rs_namakamar'] . ($room['rs_namabangsal'] ? ' · ' . $room['rs_namabangsal'] : '');
        $this->detailKelas = $room['rs_namakelas'] ?: '';

        // Pakai rsview_rihdrs (semua field sudah di-join di view) + parse JSON utk DPJP & leveling dokter.
        // Note: dr_name kolom view = dokter entry; DPJP sebenarnya ada di levelingDokter[level=Utama] dari JSON.
        $this->detailPasien = DB::table('rsview_rihdrs')
            ->where('ri_status', 'I')
            ->where('room_id', $roomId)
            ->select(['reg_no', 'reg_name', 'birth_date', 'sex', 'bed_no', 'entry_date', 'dr_name', 'datadaftarri_json'])
            ->orderBy('entry_date')
            ->get()
            ->map(function ($r) {
                $json = json_decode($r->datadaftarri_json ?? '{}', true) ?? [];
                $leveling = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
                $dpjp = collect($leveling)->firstWhere('levelDokter', 'Utama');
                $drList = collect($leveling)
                    ->map(fn($l) => trim(($l['levelDokter'] ?? '') . ' · ' . ($l['drName'] ?? '-'), ' ·'))
                    ->filter()
                    ->values()
                    ->all();
                return [
                    'reg_no' => $r->reg_no,
                    'reg_name' => $r->reg_name,
                    'birth_date' => $r->birth_date,
                    'sex' => $r->sex,
                    'bed_no' => $r->bed_no,
                    'entry_date' => $r->entry_date,
                    'dpjp_name' => $dpjp['drName'] ?? ($r->dr_name ?: '-'),
                    'dr_list' => $drList,
                ];
            })
            ->all();

        $this->dispatch('open-modal', name: 'detail-kamar-pasien');
    }

    /** Cari index baris di $rows by room_id (untuk panggil kirim*Satu by room_id). */
    private function indexByRoomId(string $roomId): ?int
    {
        $i = collect($this->rows)->search(fn($r) => (string) $r['room_id'] === (string) $roomId);
        return $i === false ? null : (int) $i;
    }

    public function kirimAplicByRoom(string $roomId): void
    {
        $i = $this->indexByRoomId($roomId);
        if ($i === null) {
            return;
        }
        $this->kirimAplicSatu($i);
        $this->toastDariStatus('Aplicares', $this->rows[$i]['rs_namakamar'] ?? '', $this->rows[$i]['status_aplic'] ?? null, $this->rows[$i]['pesan_aplic'] ?? '');
    }

    public function kirimSirsByRoom(string $roomId): void
    {
        $i = $this->indexByRoomId($roomId);
        if ($i === null) {
            return;
        }
        $this->kirimSirsSatu($i);
        $this->toastDariStatus('SIRS', $this->rows[$i]['rs_namakamar'] ?? '', $this->rows[$i]['status_sirs'] ?? null, $this->rows[$i]['pesan_sirs'] ?? '');
    }

    private function toastDariStatus(string $sistem, string $kamar, ?string $status, string $pesan): void
    {
        $pesan = $pesan !== '' ? $pesan : ($status === 'ok' ? 'berhasil dikirim' : ($status === 'skip' ? 'dilewati' : 'gagal'));
        $msg = "{$sistem} · {$kamar}: {$pesan}";
        $type = match ($status) {
            'ok' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'skip' => 'warning',
            default => 'info',
        };
        $this->dispatch('toast', type: $type, message: $msg);
    }

    /* -------------------------
     | Aplicares — kirim ketersediaan
     * ------------------------- */
    public function kirimAplicSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) {
            return;
        }

        if (!$row['aplic_kodekelas']) {
            $this->rows[$index]['status_aplic'] = 'skip';
            $this->rows[$index]['pesan_aplic'] = 'Kode Aplicares belum diisi';
            return;
        }

        $this->rows[$index]['status_aplic'] = 'loading';

        $payload = [
            'kodekelas' => $row['aplic_kodekelas'],
            'koderuang' => $row['room_id'],
            'namaruang' => trim($row['rs_namakamar'] . ' ' . $row['rs_namakelas']),
            'kapasitas' => $row['kapasitas'] ?: 1,
            'tersedia' => $row['tersedia'],
            'tersediapria' => 0,
            'tersediawanita' => 0,
            'tersediapriawanita' => $row['tersedia'],
        ];

        try {
            $res = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
            $ok = ($res['metadata']['code'] ?? 500) == 1;
            $msg = $res['metadata']['message'] ?? '-';

            $this->rows[$index]['status_aplic'] = $ok ? 'ok' : 'error';
            $this->rows[$index]['pesan_aplic'] = $msg;
            if ($ok) {
                $this->rows[$index]['last_sync_aplic'] = now()->toIso8601String();
            }
            $this->addLog('APLIC', $row['rs_namakamar'], $ok ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_aplic'] = 'error';
            $this->rows[$index]['pesan_aplic'] = $e->getMessage();
            $this->addLog('APLIC', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function kirimAplicSemua(): void
    {
        // Loop 43+ kamar × HTTP timeout 10s bisa lewat dari PHP max_execution_time default 30s.
        // Naikkan supaya seluruh kamar ter-proses, jangan dibunuh di tengah jalan.
        @set_time_limit(600);
        @ignore_user_abort(true);

        $this->logLines = [];
        $total = count($this->rows);
        $fail = 0;
        foreach (array_keys($this->rows) as $i) {
            $this->kirimAplicSatu($i);
            if (($this->rows[$i]['status_aplic'] ?? '') === 'error') {
                $fail++;
            }
            // Jeda kecil supaya tidak di-throttle BPJS Aplicares.
            usleep(150_000); // 150ms
        }
        $ok = collect($this->rows)->where('status_aplic', 'ok')->count();
        $type = $fail === 0 ? 'success' : 'warning';
        $this->dispatch('toast', type: $type, message: "Kirim ke Aplicares selesai: {$ok}/{$total} berhasil" . ($fail ? ", {$fail} gagal" : '') . '.');
    }

    /* -------------------------
     | SIRS — kirim tempat tidur
     * ------------------------- */
    public function kirimSirsSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) {
            return;
        }

        if (!$row['sirs_id_tt']) {
            $this->rows[$index]['status_sirs'] = 'skip';
            $this->rows[$index]['pesan_sirs'] = 'id_tt SIRS belum diisi';
            return;
        }

        $this->rows[$index]['status_sirs'] = 'loading';

        $payload = [
            'ruang' => trim($row['rs_namakamar'] . ' ' . $row['rs_namakelas']),
            'jumlah_ruang' => 1,
            'jumlah' => $row['kapasitas'] ?: 1,
            'terpakai' => $row['terpakai'],
            'terpakai_suspek' => 0,
            'terpakai_konfirmasi' => 0,
            'antrian' => 0,
            'prepare' => 0,
            'prepare_plan' => 0,
            'covid' => 0,
        ];

        try {
            if ($row['id_t_tt_sirs']) {
                // PUT — sudah punya id_t_tt
                $res = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $row['id_t_tt_sirs']]))->getOriginalContent();
                $first = $res['fasyankes'][0] ?? [];
                $ok = ((string) ($first['status'] ?? '')) === '200';
                $msg = $first['message'] ?? '-';
            } else {
                // POST — daftar baru
                $res = $this->sirsKirimTempaTidur(array_merge($payload, ['id_tt' => $row['sirs_id_tt']]))->getOriginalContent();
                $first = $res['fasyankes'][0] ?? [];
                $status = (string) ($first['status'] ?? '');
                $msg = $first['message'] ?? '-';

                if ($status === '200' && !str_contains($msg, 'sudah ada')) {
                    $idTTt = (string) ($first['id_t_tt'] ?? '');
                    if ($idTTt) {
                        $this->rows[$index]['id_t_tt_sirs'] = $idTTt;
                        DB::table('rsmst_rooms')
                            ->where('room_id', $row['room_id'])
                            ->update(['sirs_id_t_tt' => $idTTt]);
                    }
                    $ok = true;
                } elseif ($status === '200' && str_contains($msg, 'sudah ada')) {
                    // Sudah ada → auto GET id_t_tt lalu PUT
                    $listRes = $this->sirsGetTempaTidur()->getOriginalContent();
                    $match = collect($listRes['fasyankes'] ?? [])->first(fn($r) => (string) ($r['id_tt'] ?? '') === (string) $row['sirs_id_tt'] && ($r['id_t_tt'] ?? null) !== null);

                    if ($match) {
                        $idTTt = (string) $match['id_t_tt'];
                        $this->rows[$index]['id_t_tt_sirs'] = $idTTt;
                        DB::table('rsmst_rooms')
                            ->where('room_id', $row['room_id'])
                            ->update(['sirs_id_t_tt' => $idTTt]);

                        $resU = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $idTTt]))->getOriginalContent();
                        $firstU = $resU['fasyankes'][0] ?? [];
                        $ok = ((string) ($firstU['status'] ?? '')) === '200';
                        $msg = $ok ? 'Sudah ada, berhasil diperbarui' : $firstU['message'] ?? 'Gagal';
                    } else {
                        $ok = null; // warning
                        $msg = 'Sudah ada di SIRS, id_t_tt tidak ditemukan';
                    }
                } else {
                    $ok = false;
                }
            }

            $this->rows[$index]['status_sirs'] = $ok === true ? 'ok' : ($ok === null ? 'warning' : 'error');
            $this->rows[$index]['pesan_sirs'] = $msg;
            if ($ok === true) {
                $this->rows[$index]['last_sync_sirs'] = now()->toIso8601String();
            }
            $this->addLog('SIRS', $row['rs_namakamar'], $ok === true ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_sirs'] = 'error';
            $this->rows[$index]['pesan_sirs'] = $e->getMessage();
            $this->addLog('SIRS', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function kirimSirsSemua(): void
    {
        // SIRS bisa 2-3 round-trip per kamar (POST → "sudah ada" → GET listing → PUT).
        // 43 kamar × ~30s worst case = jauh dari default PHP timeout 30s → naikkan.
        @set_time_limit(900);
        @ignore_user_abort(true);

        $this->logLines = [];
        $total = count($this->rows);
        $fail = 0;
        foreach (array_keys($this->rows) as $i) {
            $this->kirimSirsSatu($i);
            if (($this->rows[$i]['status_sirs'] ?? '') === 'error') {
                $fail++;
            }
            // Jeda kecil supaya tidak di-throttle SIRS Kemenkes.
            usleep(200_000); // 200ms
        }
        $ok = collect($this->rows)->where('status_sirs', 'ok')->count();
        $type = $fail === 0 ? 'success' : 'warning';
        $this->dispatch('toast', type: $type, message: "Kirim ke SIRS selesai: {$ok}/{$total} berhasil" . ($fail ? ", {$fail} gagal" : '') . '.');
    }

    /* -------------------------
     | Helper
     * ------------------------- */
    private function addLog(string $sistem, string $kamar, string $status, string $msg): void
    {
        $this->logLines[] = [
            'waktu' => now()->format('H:i:s'),
            'sistem' => $sistem,
            'kamar' => $kamar,
            'status' => $status,
            'msg' => $msg,
        ];
    }
};
?>

<div>

    {{-- ══ HEADER (ikuti pola master-pasien) ══════════════════════════════ --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Aplicares &amp; SIRS
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Kirim ketersediaan kamar rawat inap ke Aplicares BPJS &amp; SIRS Kemenkes secara real-time.
                </p>
            </div>
            <x-secondary-button wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh"
                class="shrink-0 gap-2">
                <x-loading size="xs" wire:loading wire:target="refresh" />
                <svg wire:loading.remove wire:target="refresh" class="w-4 h-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
                </svg>
                Refresh DB
            </x-secondary-button>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- ══ TOOLBAR STICKY (ikuti pola master-pasien) ══════════════ --}}
            @php
                $totalKap = collect($rows)->sum('kapasitas');
                $totalTerp = collect($rows)->sum('terpakai');
                $totalTers = collect($rows)->sum('tersedia');
                $totalOcc = $totalKap > 0 ? round(($totalTerp / $totalKap) * 100) : 0;
                $aplOk = collect($rows)->where('status_aplic', 'ok')->count();
                $aplFail = collect($rows)->where('status_aplic', 'error')->count();
                $aplSkip = collect($rows)->where('status_aplic', 'skip')->count();
                $srsOk = collect($rows)->where('status_sirs', 'ok')->count();
                $srsFail = collect($rows)->where('status_sirs', 'error')->count();
                $srsSkip = collect($rows)->where('status_sirs', 'skip')->count();
                $aplSiap = collect($rows)->where('aplic_kodekelas', '!=', '')->count();
                $srsSiap = collect($rows)->where('sirs_id_tt', '!=', '')->count();
            @endphp
            <div
                class="sticky top-20 z-30 bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700 -mx-6 px-6 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

                    {{-- Ringkasan kapasitas --}}
                    <div class="flex items-center gap-3 flex-wrap text-sm">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Kapasitas</span>
                            <span class="font-bold text-gray-800 dark:text-gray-100">{{ $totalKap }}</span>
                        </div>
                        <span class="text-gray-200 dark:text-gray-700">·</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Terisi</span>
                            <span class="font-bold text-rose-600 dark:text-rose-400">{{ $totalTerp }}</span>
                        </div>
                        <span class="text-gray-200 dark:text-gray-700">·</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Sisa</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $totalTers }}</span>
                        </div>
                        <span class="text-gray-200 dark:text-gray-700">·</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Hunian</span>
                            <span class="font-bold text-blue-600 dark:text-blue-400">{{ $totalOcc }}%</span>
                        </div>

                        <span class="text-gray-200 dark:text-gray-700 mx-1">|</span>

                        {{-- Status BPJS --}}
                        <span
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                     bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                        @if ($aplOk || $aplFail || $aplSkip)
                            @if ($aplOk)
                                <span
                                    class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ $aplOk }}
                                    ok</span>
                            @endif
                            @if ($aplFail)
                                <span class="text-xs text-red-600 dark:text-red-400 font-semibold">{{ $aplFail }}
                                    gagal</span>
                            @endif
                            @if ($aplSkip)
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $aplSkip }} skip</span>
                            @endif
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $aplSiap }} siap
                                dikirim</span>
                        @endif

                        <span class="text-gray-200 dark:text-gray-700">|</span>

                        {{-- Status SIRS --}}
                        <span
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                     bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                        @if ($srsOk || $srsFail || $srsSkip)
                            @if ($srsOk)
                                <span
                                    class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ $srsOk }}
                                    ok</span>
                            @endif
                            @if ($srsFail)
                                <span class="text-xs text-red-600 dark:text-red-400 font-semibold">{{ $srsFail }}
                                    gagal</span>
                            @endif
                            @if ($srsSkip)
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $srsSkip }} skip</span>
                            @endif
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $srsSiap }} siap
                                dikirim</span>
                        @endif
                    </div>

                    {{-- Tombol Kirim Semua --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <x-primary-button wire:click="kirimAplicSemua" wire:loading.attr="disabled"
                            wire:target="kirimAplicSemua" wire:confirm="Kirim semua kamar ke Aplicares BPJS?"
                            class="gap-2">
                            <x-loading size="xs" wire:loading wire:target="kirimAplicSemua" />
                            <svg wire:loading.remove wire:target="kirimAplicSemua" class="w-3.5 h-3.5" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Kirim Semua ke Aplicares
                        </x-primary-button>
                        <x-primary-button wire:click="kirimSirsSemua" wire:loading.attr="disabled"
                            wire:target="kirimSirsSemua" wire:confirm="Kirim semua kamar ke SIRS Kemenkes?"
                            class="gap-2 !bg-green-600 hover:!bg-green-700 dark:!bg-green-700 dark:hover:!bg-green-600">
                            <x-loading size="xs" wire:loading wire:target="kirimSirsSemua" />
                            <svg wire:loading.remove wire:target="kirimSirsSemua" class="w-3.5 h-3.5" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Kirim Semua ke SIRS
                        </x-primary-button>
                    </div>
                </div>
            </div>


            {{-- ══ PRATINJAU PUBLIK: APLICARES & SIRS BERSEBELAHAN ═════════ --}}
            {{-- Mapping aplic_kodekelas vs sirs_id_tt terpisah → preview dipisah
                 supaya admin bisa lihat kedua tampilan akhir side-by-side. --}}
            @php
                $kelasLabel = fn($classId, string $fallback = '') => match ((int) $classId) {
                    1 => 'Kelas I',
                    2 => 'Kelas II',
                    3 => 'Kelas III',
                    4 => 'VIP',
                    5 => 'VVIP',
                    default => $fallback ?: 'Kelas ?',
                };
                $kelasShort = fn($classId) => match ((int) $classId) {
                    1 => 'I',
                    2 => 'II',
                    3 => 'III',
                    4 => 'VIP',
                    5 => 'VVIP',
                    default => '?',
                };
                $kelasWarna = fn($classId) => match ((int) $classId) {
                    1 => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 ring-blue-200 dark:ring-blue-800',
                    2 => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 ring-indigo-200 dark:ring-indigo-800',
                    3 => 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 ring-slate-200 dark:ring-slate-600',
                    4 => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 ring-amber-200 dark:ring-amber-800',
                    5 => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 ring-purple-200 dark:ring-purple-800',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 ring-gray-200 dark:ring-gray-700',
                };
                $rowsAplic = collect($rows)
                    ->filter(fn($r) => $r['aplic_kodekelas'] !== '')
                    ->sortBy(fn($r) => str_pad((string) ($r['class_id'] ?? '9'), 2, '0', STR_PAD_LEFT) . '|' . $r['rs_namakamar'])
                    ->values();
                $rowsAplicUnmapped = collect($rows)
                    ->filter(fn($r) => $r['aplic_kodekelas'] === '')
                    ->sortBy(fn($r) => str_pad((string) ($r['class_id'] ?? '9'), 2, '0', STR_PAD_LEFT) . '|' . $r['rs_namakamar'])
                    ->values();
                $rowsSirs = collect($rows)
                    ->filter(fn($r) => $r['sirs_id_tt'] !== '')
                    ->sortBy(fn($r) => str_pad((string) ($r['class_id'] ?? '9'), 2, '0', STR_PAD_LEFT) . '|' . $r['rs_namakamar'])
                    ->values();
                $rowsSirsUnmapped = collect($rows)
                    ->filter(fn($r) => $r['sirs_id_tt'] === '')
                    ->sortBy(fn($r) => str_pad((string) ($r['class_id'] ?? '9'), 2, '0', STR_PAD_LEFT) . '|' . $r['rs_namakamar'])
                    ->values();

                $rekap = function ($coll) {
                    $kap = $coll->sum('kapasitas');
                    $terisi = $coll->sum('terpakai');
                    $sisa = $coll->sum('tersedia');
                    $occ = $kap > 0 ? round(($terisi / $kap) * 100) : 0;
                    return compact('kap', 'terisi', 'sisa', 'occ');
                };
                $aplRekap = $rekap($rowsAplic);
                $sirsRekap = $rekap($rowsSirs);
            @endphp
            @if ($rowsAplic->isNotEmpty() || $rowsSirs->isNotEmpty() || $rowsAplicUnmapped->isNotEmpty() || $rowsSirsUnmapped->isNotEmpty())
                <div class="mt-6 grid grid-cols-1 xl:grid-cols-2 gap-4">

                    {{-- ── KIRI: APLICARES BPJS ──────────────────────────── --}}
                    @if ($rowsAplic->isNotEmpty() || $rowsAplicUnmapped->isNotEmpty())
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            {{-- Header --}}
                            <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-r from-blue-50 to-blue-50/40 dark:from-blue-900/30 dark:to-blue-900/10 border-b border-blue-100 dark:border-blue-900/40">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-600 text-white shadow-sm">BPJS</span>
                                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300 uppercase tracking-wide">
                                        Pratinjau Publik Aplicares
                                    </span>
                                </div>
                                <span class="text-[10px] text-blue-600/70 dark:text-blue-400/70 italic font-medium">
                                    {{ $rowsAplic->count() }} ruang terdaftar
                                </span>
                            </div>

{{-- Rekap total --}}
                            @php $aR = $aplRekap; @endphp
                            <div class="grid grid-cols-4 gap-px bg-gray-100 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-800">
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Kapasitas</div>
                                    <div class="text-base font-bold font-mono text-gray-800 dark:text-gray-200">{{ $aR['kap'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Terisi</div>
                                    <div class="text-base font-bold font-mono text-rose-600 dark:text-rose-400">{{ $aR['terisi'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Sisa</div>
                                    <div class="text-base font-bold font-mono text-brand-green dark:text-brand-lime">{{ $aR['sisa'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Hunian</div>
                                    <div class="text-base font-bold font-mono {{ $aR['occ'] >= 90 ? 'text-rose-600 dark:text-rose-400' : ($aR['occ'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-brand-green dark:text-brand-lime') }}">{{ $aR['occ'] }}%</div>
                                </div>
                            </div>

                            {{-- Daftar kamar --}}
                            <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                @foreach ($rowsAplic as $row)
                                    @php
                                        $tersedia = (int) $row['tersedia'];
                                        $kap = (int) $row['kapasitas'];
                                        $terisi = (int) $row['terpakai'];
                                        $occ = $kap > 0 ? round(($terisi / $kap) * 100) : 0;
                                        $dim = $tersedia <= 0;
                                        $occCls = $dim
                                            ? 'text-gray-400 dark:text-gray-600'
                                            : ($occ >= 90 ? 'text-rose-600 dark:text-rose-400' : ($occ >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-brand-green dark:text-brand-lime'));
                                        $occBar = $dim
                                            ? 'bg-gray-300 dark:bg-gray-600'
                                            : ($occ >= 90 ? 'bg-rose-500' : ($occ >= 70 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-lime'));
                                        $diupdate = $row['last_sync_aplic']
                                            ? \Carbon\Carbon::parse($row['last_sync_aplic'])->locale('id')->diffForHumans()
                                            : null;
                                    @endphp
                                    <div class="flex items-stretch gap-3 px-4 py-3 transition {{ $dim ? 'bg-gray-50/70 dark:bg-gray-800/40 hover:bg-gray-100/70 dark:hover:bg-gray-800/60' : 'bg-brand-green/5 dark:bg-brand-lime/[0.04] hover:bg-brand-green/10 dark:hover:bg-brand-lime/[0.08]' }}">
                                        {{-- Kelas badge --}}
                                        <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-lg ring-1 px-1 {{ $dim ? 'bg-gray-100 text-gray-400 dark:bg-gray-800/60 dark:text-gray-600 ring-gray-200 dark:ring-gray-700' : $kelasWarna($row['class_id']) }}">
                                            <span class="text-[8px] uppercase font-semibold tracking-wider opacity-80 leading-none mt-1">Kelas</span>
                                            <span class="text-sm font-bold leading-tight mb-1">{{ $kelasShort($row['class_id']) }}</span>
                                        </div>

                                        {{-- Middle: name + bar + meta --}}
                                        <div class="flex-1 min-w-0 flex flex-col justify-center gap-0.5">
                                            @if ($row['rs_namabangsal'])
                                                <div class="text-[10px] uppercase tracking-wide font-medium truncate {{ $dim ? 'text-gray-400 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500' }}" title="Bangsal {{ $row['rs_namabangsal'] }}">
                                                    {{ $row['rs_namabangsal'] }}
                                                </div>
                                            @endif
                                            <div class="flex items-baseline gap-2 min-w-0">
                                                <span class="font-semibold text-sm truncate {{ $dim ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $row['rs_namakamar'] }}</span>
                                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0">{{ $row['aplic_kodekelas'] }}</span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-1.5">
                                                <div class="flex items-center gap-1.5 w-24 shrink-0">
                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                        <div class="{{ $occBar }} h-1.5 rounded-full transition-all" style="width: {{ $occ }}%"></div>
                                                    </div>
                                                    <span class="text-xs font-mono {{ $occCls }} shrink-0">{{ $occ }}%</span>
                                                </div>

                                                {{-- Free-space info: anomali > status sync --}}
                                                @if ($row['anomali_selisih'] > 0)
                                                    <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50/70 dark:bg-rose-900/15 text-rose-600 dark:text-rose-400 ring-1 ring-rose-200/60 dark:ring-rose-800/40 shrink-0 truncate"
                                                          title="Pasien ri_status='I' melebihi kapasitas bed. Cek rstxn_rihdrs.">
                                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                        Anomali +{{ $row['anomali_selisih'] }}
                                                    </span>
                                                @elseif ($row['status_aplic'] === 'error')
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-rose-600 dark:text-rose-400 font-semibold truncate min-w-0"
                                                          title="{{ $row['pesan_aplic'] }}">
                                                        <span>✗</span>
                                                        <span class="truncate">{{ \Illuminate\Support\Str::limit($row['pesan_aplic'], 28) }}</span>
                                                    </span>
                                                @elseif ($row['status_aplic'] === 'ok')
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-brand-green dark:text-brand-lime font-semibold truncate min-w-0"
                                                          title="{{ $row['pesan_aplic'] }}">
                                                        <span>✓</span>
                                                        <span class="truncate text-gray-500 dark:text-gray-400 font-normal">{{ \Illuminate\Support\Str::limit($row['pesan_aplic'], 28) }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Aksi: Detail pasien + Kirim ke Aplicares --}}
                                        <div class="shrink-0 flex items-center gap-1.5">
                                            <x-icon-button color="gray" wire:click="showDetailKamar('{{ $row['room_id'] }}')"
                                                title="Lihat detail pasien" class="!p-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </x-icon-button>
                                            <x-icon-button color="blue" wire:click="kirimAplicByRoom('{{ $row['room_id'] }}')"
                                                wire:loading.attr="disabled" wire:target="kirimAplicByRoom('{{ $row['room_id'] }}')"
                                                title="Kirim ketersediaan kamar ini ke Aplicares BPJS"
                                                class="!px-2.5 !py-1.5 !text-[11px] !gap-1 font-semibold">
                                                <x-loading wire:loading wire:target="kirimAplicByRoom('{{ $row['room_id'] }}')" class="w-3.5 h-3.5"/>
                                                <svg wire:loading.remove wire:target="kirimAplicByRoom('{{ $row['room_id'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                </svg>
                                                Kirim BPJS
                                            </x-icon-button>
                                        </div>

                                        {{-- Right: sisa big (display only) --}}
                                        <div class="shrink-0 flex flex-col items-end justify-center gap-1 min-w-[88px] text-right">
                                            @if ($tersedia > 0)
                                                <div class="flex items-baseline gap-1 leading-none">
                                                    <span class="text-3xl font-bold text-brand-green dark:text-brand-lime leading-none">{{ $tersedia }}</span>
                                                    <span class="text-xs font-bold text-brand-green dark:text-brand-lime uppercase tracking-wide">sisa</span>
                                                </div>
                                            @else
                                                <div class="flex items-baseline gap-1 leading-none">
                                                    <span class="text-3xl font-bold text-gray-400 dark:text-gray-600 leading-none">0</span>
                                                    <span class="text-xs font-bold text-gray-400 dark:text-gray-600 uppercase tracking-wide">sisa</span>
                                                </div>
                                            @endif
                                            <div class="text-xs font-mono">
                                                @if ($dim)
                                                    <span class="font-bold text-gray-400 dark:text-gray-600">{{ $terisi }}</span><span class="text-gray-300 dark:text-gray-700">/</span><span class="font-semibold text-gray-400 dark:text-gray-600">{{ $kap }}</span>
                                                @else
                                                    <span class="font-bold text-rose-600 dark:text-rose-400">{{ $terisi }}</span><span class="text-gray-300 dark:text-gray-600">/</span><span class="font-semibold text-gray-500 dark:text-gray-400">{{ $kap }}</span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 italic text-right leading-tight">
                                                @if ($diupdate)
                                                    {{ $diupdate }}
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 not-italic">belum sinkron</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Belum dipetakan ke Aplicares (informatif) --}}
                            @if ($rowsAplicUnmapped->isNotEmpty())
                                <div class="border-t-2 border-dashed border-gray-200 dark:border-gray-700">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                                        <span class="text-[11px] uppercase tracking-wide text-gray-600 dark:text-gray-300 font-bold">
                                            Belum dipetakan
                                        </span>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 italic">
                                            {{ $rowsAplicUnmapped->count() }} kamar
                                        </span>
                                    </div>
                                    <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @foreach ($rowsAplicUnmapped as $row)
                                            @php $terisiUm = (int) $row['terpakai']; $kapUm = (int) $row['kapasitas']; $clickableUm = $terisiUm > 0; @endphp
                                            <div class="flex items-center gap-3 px-4 py-2 bg-gray-50/40 dark:bg-gray-800/30">
                                                <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-lg ring-1 bg-gray-100 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400 ring-gray-200 dark:ring-gray-700 px-1">
                                                    <span class="text-[8px] uppercase font-semibold tracking-wider opacity-80 leading-none mt-1">Kelas</span>
                                                    <span class="text-sm font-bold leading-tight mb-1">{{ $kelasShort($row['class_id']) }}</span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    @if ($row['rs_namabangsal'])
                                                        <div class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500 font-medium truncate">{{ $row['rs_namabangsal'] }}</div>
                                                    @endif
                                                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 truncate">{{ $row['rs_namakamar'] }}</div>
                                                </div>
                                                <div class="shrink-0 text-right">
                                                    <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                                        <span class="font-bold {{ $clickableUm ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $terisiUm }}</span>/{{ $kapUm }} bed
                                                    </div>
                                                    <div class="text-[10px] italic text-gray-400 dark:text-gray-500">belum mapping</div>
                                                </div>
                                                <x-icon-button color="gray" wire:click="showDetailKamar('{{ $row['room_id'] }}')"
                                                    :disabled="!$clickableUm"
                                                    title="{{ $clickableUm ? 'Lihat pasien di kamar ini (mapping belum di-set)' : 'Kamar kosong, tidak ada pasien' }}"
                                                    class="!p-1.5 shrink-0">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </x-icon-button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- ── KANAN: SIRS KEMENKES ──────────────────────────── --}}
                    @if ($rowsSirs->isNotEmpty() || $rowsSirsUnmapped->isNotEmpty())
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-r from-brand-green/10 to-brand-green/5 dark:from-brand-lime/15 dark:to-brand-lime/5 border-b border-brand-green/20 dark:border-brand-lime/20">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-brand-green text-white dark:bg-brand-lime dark:text-gray-900 shadow-sm">SIRS</span>
                                    <span class="text-xs font-bold text-brand-green dark:text-brand-lime uppercase tracking-wide">
                                        Pratinjau Publik SIRS Kemenkes
                                    </span>
                                </div>
                                <span class="text-[10px] text-brand-green/70 dark:text-brand-lime/70 italic font-medium">
                                    {{ $rowsSirs->count() }} ruang terdaftar
                                </span>
                            </div>

                            @php $sR = $sirsRekap; @endphp
                            <div class="grid grid-cols-4 gap-px bg-gray-100 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-800">
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Kapasitas</div>
                                    <div class="text-base font-bold font-mono text-gray-800 dark:text-gray-200">{{ $sR['kap'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Terisi</div>
                                    <div class="text-base font-bold font-mono text-rose-600 dark:text-rose-400">{{ $sR['terisi'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Sisa</div>
                                    <div class="text-base font-bold font-mono text-brand-green dark:text-brand-lime">{{ $sR['sisa'] }}</div>
                                </div>
                                <div class="bg-white dark:bg-gray-900 px-3 py-2 text-center">
                                    <div class="text-[9px] uppercase text-gray-400 dark:text-gray-500 font-semibold">Hunian</div>
                                    <div class="text-base font-bold font-mono {{ $sR['occ'] >= 90 ? 'text-rose-600 dark:text-rose-400' : ($sR['occ'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-brand-green dark:text-brand-lime') }}">{{ $sR['occ'] }}%</div>
                                </div>
                            </div>

                            <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                @foreach ($rowsSirs as $row)
                                    @php
                                        $tersedia = (int) $row['tersedia'];
                                        $kap = (int) $row['kapasitas'];
                                        $terisi = (int) $row['terpakai'];
                                        $occ = $kap > 0 ? round(($terisi / $kap) * 100) : 0;
                                        $dim = $tersedia <= 0;
                                        $occCls = $dim
                                            ? 'text-gray-400 dark:text-gray-600'
                                            : ($occ >= 90 ? 'text-rose-600 dark:text-rose-400' : ($occ >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-brand-green dark:text-brand-lime'));
                                        $occBar = $dim
                                            ? 'bg-gray-300 dark:bg-gray-600'
                                            : ($occ >= 90 ? 'bg-rose-500' : ($occ >= 70 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-lime'));
                                        $diupdate = $row['last_sync_sirs']
                                            ? \Carbon\Carbon::parse($row['last_sync_sirs'])->locale('id')->diffForHumans()
                                            : null;
                                        $jenisTt = $row['sirs_tt_label'] ?: $kelasLabel($row['class_id'], $row['rs_namakelas']);
                                    @endphp
                                    <div class="flex items-stretch gap-3 px-4 py-3 transition {{ $dim ? 'bg-gray-50/70 dark:bg-gray-800/40 hover:bg-gray-100/70 dark:hover:bg-gray-800/60' : 'bg-brand-green/5 dark:bg-brand-lime/[0.04] hover:bg-brand-green/10 dark:hover:bg-brand-lime/[0.08]' }}">
                                        <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-lg ring-1 px-1 {{ $dim ? 'bg-gray-100 text-gray-400 dark:bg-gray-800/60 dark:text-gray-600 ring-gray-200 dark:ring-gray-700' : $kelasWarna($row['class_id']) }}">
                                            <span class="text-[8px] uppercase font-semibold tracking-wider opacity-80 leading-none mt-1">Kelas</span>
                                            <span class="text-sm font-bold leading-tight mb-1">{{ $kelasShort($row['class_id']) }}</span>
                                        </div>

                                        <div class="flex-1 min-w-0 flex flex-col justify-center gap-0.5">
                                            @if ($row['rs_namabangsal'])
                                                <div class="text-[10px] uppercase tracking-wide font-medium truncate {{ $dim ? 'text-gray-400 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500' }}" title="Bangsal {{ $row['rs_namabangsal'] }}">
                                                    {{ $row['rs_namabangsal'] }}
                                                </div>
                                            @endif
                                            <div class="flex items-baseline gap-1.5 min-w-0 flex-wrap">
                                                <span class="font-semibold text-sm truncate {{ $dim ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $row['rs_namakamar'] }}</span>
                                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0" title="id_tt SIRS">{{ $row['sirs_id_tt'] }}</span>
                                                <span class="text-[11px] truncate {{ $dim ? 'text-gray-400 dark:text-gray-600' : 'text-gray-500 dark:text-gray-400' }}" title="Jenis TT SIRS: {{ $jenisTt }}">
                                                    {{ $jenisTt }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] font-mono truncate {{ $dim ? 'text-gray-300 dark:text-gray-700' : 'text-gray-400 dark:text-gray-600' }}" title="id_t_tt (token registrasi SIRS Kemenkes)">
                                                @if ($row['id_t_tt_sirs'])
                                                    T_TT: {{ $row['id_t_tt_sirs'] }}
                                                @else
                                                    <span class="italic">T_TT: —</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 mt-1.5">
                                                <div class="flex items-center gap-1.5 w-24 shrink-0">
                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                        <div class="{{ $occBar }} h-1.5 rounded-full transition-all" style="width: {{ $occ }}%"></div>
                                                    </div>
                                                    <span class="text-xs font-mono {{ $occCls }} shrink-0">{{ $occ }}%</span>
                                                </div>

                                                {{-- Free-space info: anomali > id_t_tt status > status sync --}}
                                                @if ($row['anomali_selisih'] > 0)
                                                    <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50/70 dark:bg-rose-900/15 text-rose-600 dark:text-rose-400 ring-1 ring-rose-200/60 dark:ring-rose-800/40 shrink-0 truncate"
                                                          title="Pasien ri_status='I' melebihi kapasitas bed. Cek rstxn_rihdrs.">
                                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                        Anomali +{{ $row['anomali_selisih'] }}
                                                    </span>
                                                @elseif ($row['status_sirs'] === 'error')
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-rose-600 dark:text-rose-400 font-semibold truncate min-w-0"
                                                          title="{{ $row['pesan_sirs'] }}">
                                                        <span>✗</span>
                                                        <span class="truncate">{{ \Illuminate\Support\Str::limit($row['pesan_sirs'], 28) }}</span>
                                                    </span>
                                                @elseif ($row['status_sirs'] === 'warning')
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400 font-semibold truncate min-w-0"
                                                          title="{{ $row['pesan_sirs'] }}">
                                                        <span>!</span>
                                                        <span class="truncate">{{ \Illuminate\Support\Str::limit($row['pesan_sirs'], 28) }}</span>
                                                    </span>
                                                @elseif ($row['status_sirs'] === 'ok')
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-brand-green dark:text-brand-lime font-semibold truncate min-w-0"
                                                          title="{{ $row['pesan_sirs'] }}">
                                                        <span>✓</span>
                                                        <span class="truncate text-gray-500 dark:text-gray-400 font-normal">{{ \Illuminate\Support\Str::limit($row['pesan_sirs'], 28) }}</span>
                                                    </span>
                                                @elseif (empty($row['id_t_tt_sirs']))
                                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500 italic shrink-0"
                                                          title="Kamar belum terdaftar di SIRS (belum pernah POST sukses).">
                                                        belum terdaftar SIRS
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Aksi: Detail pasien + Kirim ke SIRS --}}
                                        <div class="shrink-0 flex items-center gap-1.5">
                                            <x-icon-button color="gray" wire:click="showDetailKamar('{{ $row['room_id'] }}')"
                                                title="Lihat detail pasien" class="!p-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </x-icon-button>
                                            <x-icon-button color="green" wire:click="kirimSirsByRoom('{{ $row['room_id'] }}')"
                                                wire:loading.attr="disabled" wire:target="kirimSirsByRoom('{{ $row['room_id'] }}')"
                                                title="Kirim data tempat tidur kamar ini ke SIRS Kemenkes"
                                                class="!px-2.5 !py-1.5 !text-[11px] !gap-1 font-semibold">
                                                <x-loading wire:loading wire:target="kirimSirsByRoom('{{ $row['room_id'] }}')" class="w-3.5 h-3.5"/>
                                                <svg wire:loading.remove wire:target="kirimSirsByRoom('{{ $row['room_id'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                </svg>
                                                Kirim SIRS
                                            </x-icon-button>
                                        </div>

                                        {{-- Right: sisa big (display only) --}}
                                        <div class="shrink-0 flex flex-col items-end justify-center gap-1 min-w-[88px] text-right">
                                            @if ($tersedia > 0)
                                                <div class="flex items-baseline gap-1 leading-none">
                                                    <span class="text-3xl font-bold text-brand-green dark:text-brand-lime leading-none">{{ $tersedia }}</span>
                                                    <span class="text-xs font-bold text-brand-green dark:text-brand-lime uppercase tracking-wide">sisa</span>
                                                </div>
                                            @else
                                                <div class="flex items-baseline gap-1 leading-none">
                                                    <span class="text-3xl font-bold text-gray-400 dark:text-gray-600 leading-none">0</span>
                                                    <span class="text-xs font-bold text-gray-400 dark:text-gray-600 uppercase tracking-wide">sisa</span>
                                                </div>
                                            @endif
                                            <div class="text-xs font-mono">
                                                @if ($dim)
                                                    <span class="font-bold text-gray-400 dark:text-gray-600">{{ $terisi }}</span><span class="text-gray-300 dark:text-gray-700">/</span><span class="font-semibold text-gray-400 dark:text-gray-600">{{ $kap }}</span>
                                                @else
                                                    <span class="font-bold text-rose-600 dark:text-rose-400">{{ $terisi }}</span><span class="text-gray-300 dark:text-gray-600">/</span><span class="font-semibold text-gray-500 dark:text-gray-400">{{ $kap }}</span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 italic text-right leading-tight">
                                                @if ($diupdate)
                                                    {{ $diupdate }}
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 not-italic">belum sinkron</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Belum dipetakan ke SIRS (informatif) --}}
                            @if ($rowsSirsUnmapped->isNotEmpty())
                                <div class="border-t-2 border-dashed border-gray-200 dark:border-gray-700">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                                        <span class="text-[11px] uppercase tracking-wide text-gray-600 dark:text-gray-300 font-bold">
                                            Belum dipetakan
                                        </span>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 italic">
                                            {{ $rowsSirsUnmapped->count() }} kamar
                                        </span>
                                    </div>
                                    <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @foreach ($rowsSirsUnmapped as $row)
                                            @php $terisiUm = (int) $row['terpakai']; $kapUm = (int) $row['kapasitas']; $clickableUm = $terisiUm > 0; @endphp
                                            <div class="flex items-center gap-3 px-4 py-2 bg-gray-50/40 dark:bg-gray-800/30">
                                                <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-lg ring-1 bg-gray-100 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400 ring-gray-200 dark:ring-gray-700 px-1">
                                                    <span class="text-[8px] uppercase font-semibold tracking-wider opacity-80 leading-none mt-1">Kelas</span>
                                                    <span class="text-sm font-bold leading-tight mb-1">{{ $kelasShort($row['class_id']) }}</span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    @if ($row['rs_namabangsal'])
                                                        <div class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500 font-medium truncate">{{ $row['rs_namabangsal'] }}</div>
                                                    @endif
                                                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 truncate">{{ $row['rs_namakamar'] }}</div>
                                                </div>
                                                <div class="shrink-0 text-right">
                                                    <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                                        <span class="font-bold {{ $clickableUm ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $terisiUm }}</span>/{{ $kapUm }} bed
                                                    </div>
                                                    <div class="text-[10px] italic text-gray-400 dark:text-gray-500">belum mapping</div>
                                                </div>
                                                <x-icon-button color="gray" wire:click="showDetailKamar('{{ $row['room_id'] }}')"
                                                    :disabled="!$clickableUm"
                                                    title="{{ $clickableUm ? 'Lihat pasien di kamar ini (mapping belum di-set)' : 'Kamar kosong, tidak ada pasien' }}"
                                                    class="!p-1.5 shrink-0">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </x-icon-button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            @endif

            {{-- ══ LOG SYNC ═══════════════════════════════════════════════ --}}
            @if (!empty($logLines))
                <div class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div
                        class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                            Log Aktivitas
                        </span>
                        <x-ghost-button wire:click="$set('logLines', [])"
                            class="!text-gray-400 hover:!text-red-500 !px-2 !py-1 !text-xs">
                            Hapus Log
                        </x-ghost-button>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-52 overflow-y-auto">
                        @foreach (array_reverse($logLines) as $log)
                            <div class="flex items-center gap-3 px-4 py-2 text-xs">
                                <span class="font-mono text-gray-400 shrink-0 w-14">{{ $log['waktu'] }}</span>
                                @if ($log['sistem'] === 'APLIC')
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 shrink-0">BPJS</span>
                                @else
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 shrink-0">SIRS</span>
                                @endif
                                <span
                                    class="font-semibold text-gray-700 dark:text-gray-300 shrink-0 truncate max-w-[140px]">{{ $log['kamar'] }}</span>
                                @if ($log['status'] === 'ok')
                                    <span class="text-emerald-600 dark:text-emerald-400 shrink-0 font-bold">✓</span>
                                @else
                                    <span class="text-red-500 shrink-0 font-bold">✗</span>
                                @endif
                                <span class="text-gray-500 dark:text-gray-400 truncate">{{ $log['msg'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>{{-- end px-6 pt-2 pb-6 --}}
    </div>{{-- end w-full min-h --}}

    {{-- ══ MODAL DETAIL PASIEN per KAMAR ══════════════════════════════════ --}}
    <x-modal name="detail-kamar-pasien" size="2xl" padding="p-0">
        <div class="px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">
                        Pasien di {{ $detailRoomName ?: 'Kamar' }}
                    </h3>
                    @if ($detailKelas)
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $detailKelas }} · {{ count($detailPasien) }} pasien aktif
                        </p>
                    @endif
                </div>
                <x-secondary-button x-on:click="$dispatch('close-modal', { name: 'detail-kamar-pasien' })" class="!px-2 !py-1 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </x-secondary-button>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                @if (count($detailPasien) === 0)
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500 italic">
                        Belum ada pasien aktif di kamar ini.
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($detailPasien as $p)
                            @php
                                $masuk = $p['entry_date'] ? \Carbon\Carbon::parse($p['entry_date']) : null;
                                $lamaRawat = $masuk ? $masuk->locale('id')->diffForHumans(null, ['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : '-';
                            @endphp
                            <div class="flex items-start gap-3 px-4 py-3 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                {{-- Bed badge --}}
                                <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-lg bg-brand-green/10 dark:bg-brand-lime/15 ring-1 ring-brand-green/30 dark:ring-brand-lime/30 px-1 py-1">
                                    <span class="text-[8px] uppercase font-semibold text-brand-green/70 dark:text-brand-lime/70 tracking-wider leading-none">Bed</span>
                                    <span class="text-sm font-bold text-brand-green dark:text-brand-lime leading-tight mt-0.5">{{ $p['bed_no'] }}</span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $p['reg_name'] ?: '-' }}</div>
                                    <div class="mt-0.5 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 font-mono flex-wrap">
                                        <span>{{ $p['reg_no'] }}</span>
                                        @if ($p['sex'])
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            <span>{{ strtoupper($p['sex']) === 'L' ? 'Laki-laki' : (strtoupper($p['sex']) === 'P' ? 'Perempuan' : '-') }}</span>
                                        @endif
                                        @if ($p['birth_date'])
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            <span>lahir {{ \Carbon\Carbon::parse($p['birth_date'])->locale('id')->isoFormat('D MMM YYYY') }}</span>
                                        @endif
                                    </div>
                                    @if (!empty($p['dr_list']))
                                        <div class="mt-1 space-y-0.5">
                                            @foreach ($p['dr_list'] as $dr)
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate" title="{{ $dr }}">{{ $dr }}</div>
                                            @endforeach
                                        </div>
                                    @elseif ($p['dpjp_name'] && $p['dpjp_name'] !== '-')
                                        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 truncate" title="DPJP">
                                            <span class="text-gray-400 dark:text-gray-500">DPJP:</span> {{ $p['dpjp_name'] }}
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 text-right">
                                    @if ($masuk)
                                        <div class="text-[11px] font-mono text-gray-700 dark:text-gray-300">{{ $masuk->locale('id')->isoFormat('D MMM YYYY') }}</div>
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $masuk->format('H:i') }} · {{ $lamaRawat }}</div>
                                    @else
                                        <div class="text-[10px] italic text-gray-400">-</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- Footer: tutup saja, aksi kirim sudah pindah ke per-row preview --}}
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40">
            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'detail-kamar-pasien' })">
                Tutup
            </x-secondary-button>
        </div>
    </x-modal>

</div>
