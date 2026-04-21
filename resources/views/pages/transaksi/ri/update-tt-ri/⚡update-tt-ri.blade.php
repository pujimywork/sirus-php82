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
                    'status_sirs' => null,
                    'pesan_sirs' => '',
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
            $this->addLog('APLIC', $row['rs_namakamar'], $ok ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_aplic'] = 'error';
            $this->rows[$index]['pesan_aplic'] = $e->getMessage();
            $this->addLog('APLIC', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function kirimAplicSemua(): void
    {
        $this->logLines = [];
        foreach (array_keys($this->rows) as $i) {
            $this->kirimAplicSatu($i);
        }
        $ok = collect($this->rows)->where('status_aplic', 'ok')->count();
        $this->dispatch('toast', type: 'success', message: "Kirim ke Aplicares selesai: {$ok} berhasil.");
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
            $this->addLog('SIRS', $row['rs_namakamar'], $ok === true ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_sirs'] = 'error';
            $this->rows[$index]['pesan_sirs'] = $e->getMessage();
            $this->addLog('SIRS', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function kirimSirsSemua(): void
    {
        $this->logLines = [];
        foreach (array_keys($this->rows) as $i) {
            $this->kirimSirsSatu($i);
        }
        $ok = collect($this->rows)->where('status_sirs', 'ok')->count();
        $this->dispatch('toast', type: 'success', message: "Kirim ke SIRS selesai: {$ok} berhasil.");
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

            {{-- ══ TABEL TERPADU ═══════════════════════════════════════════ --}}
            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left" rowspan="2">Kamar</th>
                            <th class="px-4 py-3 text-center" rowspan="2" title="Jumlah total tempat tidur di kamar">
                                Kapasitas</th>
                            <th class="px-4 py-3 text-center" rowspan="2" title="Jumlah pasien yang sedang dirawat">
                                Terisi</th>
                            <th class="px-4 py-3 text-center" rowspan="2" title="Tempat tidur yang masih kosong">
                                Sisa</th>
                            <th class="px-4 py-3 text-center" rowspan="2" title="Persentase hunian (terisi / kapasitas)">
                                Hunian %</th>
                            <th colspan="3"
                                class="px-4 py-2 text-center border-l border-blue-200 dark:border-blue-800
                               bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-bold normal-case text-[11px]"
                                title="Kirim ketersediaan kamar ke Aplicares BPJS">
                                Ketersediaan Kamar — BPJS Aplicares
                            </th>
                            <th colspan="3"
                                class="px-4 py-2 text-center border-l border-green-200 dark:border-green-800
                               bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 font-bold normal-case text-[11px]"
                                title="Kirim data tempat tidur ke SIRS Kemenkes">
                                Tempat Tidur — SIRS Kemenkes
                            </th>
                        </tr>
                        <tr>
                            <th class="px-3 py-2 text-center border-l border-blue-100 dark:border-blue-900 bg-blue-50/60 dark:bg-blue-900/10 font-medium normal-case"
                                title="Kode kelas ruangan di Aplicares BPJS">
                                Kode Kelas</th>
                            <th class="px-3 py-2 text-center bg-blue-50/60 dark:bg-blue-900/10 font-medium normal-case"
                                title="Hasil pengiriman terakhir ke Aplicares">
                                Status Kirim</th>
                            <th class="px-3 py-2 text-center bg-blue-50/60 dark:bg-blue-900/10 font-medium normal-case">
                                Kirim</th>
                            <th class="px-3 py-2 text-center border-l border-green-100 dark:border-green-900 bg-green-50/60 dark:bg-green-900/10 font-medium normal-case"
                                title="ID TT (dari master) dan ID T_TT (dari SIRS Kemenkes setelah terdaftar)">
                                ID TT / T_TT</th>
                            <th class="px-3 py-2 text-center bg-green-50/60 dark:bg-green-900/10 font-medium normal-case"
                                title="Hasil pengiriman terakhir ke SIRS">
                                Status Kirim</th>
                            <th class="px-3 py-2 text-center bg-green-50/60 dark:bg-green-900/10 font-medium normal-case">
                                Kirim / Perbarui</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($rows as $i => $row)
                            @php
                                $occ = $row['kapasitas'] > 0 ? round(($row['terpakai'] / $row['kapasitas']) * 100) : 0;
                                $occColor =
                                    $occ >= 90
                                        ? 'text-red-600 dark:text-red-400'
                                        : ($occ >= 70
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-emerald-600 dark:text-emerald-400');
                                $occBar = $occ >= 90 ? 'bg-red-500' : ($occ >= 70 ? 'bg-amber-400' : 'bg-emerald-500');
                                $belumDaftarSirs = empty($row['sirs_id_tt']);
                                $modeLabel = empty($row['id_t_tt_sirs']) ? 'Kirim' : 'Perbarui';
                            @endphp
                            <tr
                                class="bg-white dark:bg-gray-900 hover:bg-gray-50/70 dark:hover:bg-gray-800/40 transition">

                                {{-- Kamar --}}
                                <td class="px-4 py-3 min-w-[160px]">
                                    @if ($row['rs_namabangsal'])
                                        <div class="text-[11px] text-gray-400 dark:text-gray-500">
                                            {{ $row['rs_namabangsal'] }}</div>
                                    @endif
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 leading-tight">
                                        {{ $row['rs_namakamar'] }}</div>
                                    @if ($row['rs_namakelas'])
                                        <span
                                            class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold
                                             bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                            {{ $row['rs_namakelas'] }}
                                        </span>
                                    @endif
                                    @if ($row['anomali_selisih'] > 0)
                                        <div class="mt-1 flex items-start gap-1 px-1.5 py-1 rounded bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50"
                                            title="Jumlah pasien ri_status='I' melebihi kapasitas bed. Cek rstxn_rihdrs & tutup pasien yang sudah pulang.">
                                            <svg class="w-3 h-3 mt-0.5 shrink-0 text-red-600 dark:text-red-400"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                            </svg>
                                            <div class="text-[10px] leading-tight text-red-700 dark:text-red-300">
                                                <div class="font-bold">Anomali data</div>
                                                <div>Pasien 'I' = {{ $row['terpakai_raw'] }}, kapasitas
                                                    {{ $row['kapasitas'] }} (selisih
                                                    +{{ $row['anomali_selisih'] }}). Cek rstxn_rihdrs.</div>
                                            </div>
                                        </div>
                                    @endif
                                </td>

                                {{-- Angka --}}
                                <td
                                    class="px-4 py-3 text-center font-mono font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $row['kapasitas'] }}</td>
                                <td
                                    class="px-4 py-3 text-center font-mono font-semibold text-rose-600 dark:text-rose-400">
                                    {{ $row['terpakai'] }}</td>
                                <td
                                    class="px-4 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $row['tersedia'] }}</td>
                                <td class="px-4 py-3 text-center min-w-[80px]">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mb-1">
                                        <div class="{{ $occBar }} h-1.5 rounded-full"
                                            style="width: {{ $occ }}%"></div>
                                    </div>
                                    <span class="text-xs font-mono {{ $occColor }}">{{ $occ }}%</span>
                                </td>

                                {{-- BPJS Aplicares --}}
                                <td
                                    class="px-3 py-3 text-center border-l border-blue-100 dark:border-blue-900/40 bg-blue-50/30 dark:bg-blue-900/5">
                                    @if ($row['aplic_kodekelas'])
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                            {{ $row['aplic_kodekelas'] }}
                                        </span>
                                    @else
                                        <span class="text-[11px] text-gray-400 italic">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center bg-blue-50/30 dark:bg-blue-900/5">
                                    @include('pages.transaksi.ri.update-tt-ri.status-badge', [
                                        'status' => $row['status_aplic'],
                                        'pesan' => $row['pesan_aplic'],
                                    ])
                                </td>
                                <td class="px-3 py-3 text-center bg-blue-50/30 dark:bg-blue-900/5">
                                    @if ($row['aplic_kodekelas'])
                                        <x-ghost-button wire:click="kirimAplicSatu({{ $i }})"
                                            wire:loading.attr="disabled"
                                            wire:target="kirimAplicSatu({{ $i }})"
                                            class="!text-blue-700 !bg-blue-50 hover:!bg-blue-100 dark:!text-blue-300 dark:!bg-blue-900/30 dark:hover:!bg-blue-900/50 !px-2.5 !py-1 !text-[11px] !gap-1">
                                            <x-loading wire:loading wire:target="kirimAplicSatu({{ $i }})"
                                                class="w-3 h-3" />
                                            <svg wire:loading.remove wire:target="kirimAplicSatu({{ $i }})"
                                                class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            Kirim
                                        </x-ghost-button>
                                    @else
                                        <span class="text-[10px] text-gray-400 italic">Belum mapping</span>
                                    @endif
                                </td>

                                {{-- SIRS Kemenkes --}}
                                <td
                                    class="px-3 py-3 text-center border-l border-green-100 dark:border-green-900/40 bg-green-50/30 dark:bg-green-900/5">
                                    @if ($row['sirs_id_tt'])
                                        <div class="flex flex-col items-center gap-0.5">
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300"
                                                title="id_tt = {{ $row['sirs_id_tt'] }}">
                                                {{ $row['sirs_id_tt'] }}{{ $row['sirs_tt_label'] ? ' — ' . $row['sirs_tt_label'] : '' }}
                                            </span>
                                            @if ($row['id_t_tt_sirs'])
                                                <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500"
                                                    title="id_t_tt (auto dari SIRS setelah terdaftar)">
                                                    T_TT: {{ $row['id_t_tt_sirs'] }}
                                                </span>
                                            @else
                                                <span class="text-[10px] text-gray-300 dark:text-gray-600 italic">belum
                                                    ada id_t_tt</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[11px] text-gray-400 italic">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center bg-green-50/30 dark:bg-green-900/5">
                                    @include('pages.transaksi.ri.update-tt-ri.status-badge', [
                                        'status' => $row['status_sirs'],
                                        'pesan' => $row['pesan_sirs'],
                                    ])
                                </td>
                                <td class="px-3 py-3 text-center bg-green-50/30 dark:bg-green-900/5">
                                    @if ($belumDaftarSirs)
                                        <span class="text-[10px] text-gray-400 italic">Belum mapping</span>
                                    @else
                                        <x-ghost-button wire:click="kirimSirsSatu({{ $i }})"
                                            wire:loading.attr="disabled"
                                            wire:target="kirimSirsSatu({{ $i }})"
                                            class="{{ empty($row['id_t_tt_sirs'])
                                                ? '!text-amber-700 !bg-amber-50 hover:!bg-amber-100 dark:!text-amber-300 dark:!bg-amber-900/30'
                                                : '!text-emerald-700 !bg-emerald-50 hover:!bg-emerald-100 dark:!text-emerald-300 dark:!bg-emerald-900/30' }}
                                        !px-2.5 !py-1 !text-[11px] !gap-1">
                                            <x-loading wire:loading wire:target="kirimSirsSatu({{ $i }})"
                                                class="w-3 h-3" />
                                            <svg wire:loading.remove wire:target="kirimSirsSatu({{ $i }})"
                                                class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            {{ $modeLabel }}
                                        </x-ghost-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>{{-- end tabel --}}

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

</div>
