<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {

    use AplicaresTrait, SirsTrait;

    // ─── Mapping kelas RS ────────────────────────────────────────────────────
    // rs_namakelas    : nama tampilan kelas (internal RS)
    // rs_class_id     : to_char(class_id) di rsmst_rooms (internal RS)
    // aplic_kodekelas : kode kelas Aplicares BPJS
    // sirs_id_tt      : id_tt dari referensi SIRS Kemenkes (GET /Referensi/tempat_tidur)
    //                   → sesuaikan dengan nilai yang dikembalikan API SIRS RS masing-masing
    private const MAPPING = [
        ['rs_namakelas' => 'VIP',       'rs_class_id' => 'VIP', 'aplic_kodekelas' => 'VIP', 'sirs_id_tt' => '1'],
        ['rs_namakelas' => 'Kelas I',   'rs_class_id' => '1',   'aplic_kodekelas' => 'KL1', 'sirs_id_tt' => '2'],
        ['rs_namakelas' => 'Kelas II',  'rs_class_id' => '2',   'aplic_kodekelas' => 'KL2', 'sirs_id_tt' => '3'],
        ['rs_namakelas' => 'Kelas III', 'rs_class_id' => '3',   'aplic_kodekelas' => 'KL3', 'sirs_id_tt' => '4'],
        ['rs_namakelas' => 'ICU',       'rs_class_id' => 'ICU', 'aplic_kodekelas' => 'ICU', 'sirs_id_tt' => '5'],
        ['rs_namakelas' => 'NICU',      'rs_class_id' => 'NIC', 'aplic_kodekelas' => 'NIC', 'sirs_id_tt' => '6'],
        ['rs_namakelas' => 'PICU',      'rs_class_id' => 'PIC', 'aplic_kodekelas' => 'PIC', 'sirs_id_tt' => '7'],
    ];

    // ─── State ───────────────────────────────────────────────────────────────
    public string $activeTab   = 'aplicares';   // 'aplicares' | 'sirs' | 'konfigurasi'
    public array  $rows        = [];
    public array  $logLines    = [];
    public bool   $syncingAll  = false;
    public int    $syncDone    = 0;
    public int    $syncTotal   = 0;

    // ─── Mount ───────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->loadRows();
    }

    // ─── LOAD ROWS dari DB ───────────────────────────────────────────────────
    public function loadRows(): void
    {
        $this->rows = collect(self::MAPPING)->map(function ($map) {
            $kapasitas = DB::table('rsmst_rooms as a')
                ->where(DB::raw("to_char(a.class_id)"), $map['rs_class_id'])
                ->count();

            $terpakai = DB::table('rsmst_rooms as a')
                ->join('rstxn_rihdrs as b', 'a.room_id', '=', 'b.room_id')
                ->where('b.ri_status', 'I')
                ->where(DB::raw("to_char(a.class_id)"), $map['rs_class_id'])
                ->count();

            return [
                'rs_namakelas'    => $map['rs_namakelas'],
                'rs_class_id'     => $map['rs_class_id'],
                'aplic_kodekelas' => $map['aplic_kodekelas'],
                'sirs_id_tt'      => $map['sirs_id_tt'],
                'id_t_tt_sirs' => null,        // diisi saat loadSirsExisting()
                'kapasitas'    => $kapasitas,
                'terpakai'     => $terpakai,
                'tersedia'     => max(0, $kapasitas - $terpakai),
                // status per sistem
                'status_aplic' => null,        // null | 'ok' | 'error' | 'loading'
                'pesan_aplic'  => '',
                'status_sirs'  => null,
                'pesan_sirs'   => '',
            ];
        })->values()->all();
    }

    // ─── Load id_t_tt existing dari SIRS ────────────────────────────────────
    public function loadSirsExisting(): void
    {
        try {
            $res  = $this->sirsGetTempaTidur()->getOriginalContent();
            $list = $res['data'] ?? $res ?? [];

            // list bisa berupa array flat atau nested; coba ambil array of rows
            if (isset($list[0])) {
                foreach ($this->rows as $i => $row) {
                    $found = collect($list)->firstWhere('id_tt', $row['sirs_id_tt']);
                    if ($found) {
                        $this->rows[$i]['id_t_tt_sirs'] = $found['id_t_tt'] ?? null;
                    }
                }
                $this->dispatch('toast', type: 'info', message: 'Data SIRS berhasil dimuat.');
            } else {
                $this->dispatch('toast', type: 'warning', message: 'Data SIRS kosong atau format berbeda.');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal ambil data SIRS: ' . $e->getMessage());
        }
    }

    // ─── Refresh DB ─────────────────────────────────────────────────────────
    public function refresh(): void
    {
        $this->loadRows();
        $this->logLines = [];
        $this->dispatch('toast', type: 'info', message: 'Data DB diperbarui.');
    }

    // =========================================================================
    // APLICARES — sync satu / semua
    // =========================================================================
    public function syncAplicSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        $this->rows[$index]['status_aplic'] = 'loading';

        $payload = [
            'kodekelas'          => $row['aplic_kodekelas'],
            'koderuang'          => $row['aplic_kodekelas'],
            'namaruang'          => $row['rs_namakelas'],
            'kapasitas'          => $row['kapasitas'] ?: 1,
            'tersedia'           => $row['tersedia'],
            'tersediapria'       => 0,
            'tersediawanita'     => 0,
            'tersediapriawanita' => $row['tersedia'],
        ];

        try {
            $res  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            $ok = $code == 1;
            $this->rows[$index]['status_aplic'] = $ok ? 'ok' : 'error';
            $this->rows[$index]['pesan_aplic']  = $msg;
            $this->addLog('APLIC', $row['rs_namakelas'], $ok ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_aplic'] = 'error';
            $this->rows[$index]['pesan_aplic']  = $e->getMessage();
            $this->addLog('APLIC', $row['rs_namakelas'], 'error', $e->getMessage());
        }
    }

    public function syncAplicSemua(): void
    {
        $this->syncingAll = true;
        $this->syncTotal  = count($this->rows);
        $this->syncDone   = 0;
        $this->logLines   = [];

        foreach ($this->rows as $i => $_) {
            $this->syncAplicSatu($i);
            $this->syncDone++;
        }

        $this->syncingAll = false;
        $this->dispatch('toast', type: 'success', message: 'Sync Aplicares selesai: ' . $this->syncDone . ' kelas.');
    }

    // =========================================================================
    // SIRS — sync satu / semua
    // =========================================================================
    public function syncSirsSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        $this->rows[$index]['status_sirs'] = 'loading';

        $payload = [
            'id_tt'              => $row['sirs_id_tt'],
            'ruang'              => $row['rs_namakelas'],
            'jumlah_ruang'       => 1,
            'jumlah'             => $row['kapasitas'] ?: 1,
            'terpakai'           => $row['terpakai'],
            'terpakai_suspek'    => 0,
            'terpakai_konfirmasi'=> 0,
            'antrian'            => 0,
            'prepare'            => 0,
            'prepare_plan'       => 0,
            'covid'              => 0,
        ];

        try {
            // Jika sudah punya id_t_tt → UPDATE, jika belum → INSERT
            if (!empty($row['id_t_tt_sirs'])) {
                $payload['id_t_tt'] = $row['id_t_tt_sirs'];
                unset($payload['id_tt']);
                $res = $this->sirsUpdateTempaTidur($payload)->getOriginalContent();
            } else {
                $res = $this->sirsKirimTempaTidur($payload)->getOriginalContent();
                // Simpan id_t_tt jika dikembalikan API
                if (!empty($res['id_t_tt'])) {
                    $this->rows[$index]['id_t_tt_sirs'] = $res['id_t_tt'];
                }
            }

            $ok  = ($res['status'] ?? $res['code'] ?? 200) == 200;
            $msg = $res['message'] ?? '-';
            $this->rows[$index]['status_sirs'] = $ok ? 'ok' : 'error';
            $this->rows[$index]['pesan_sirs']  = $msg;
            $this->addLog('SIRS', $row['rs_namakelas'], $ok ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_sirs'] = 'error';
            $this->rows[$index]['pesan_sirs']  = $e->getMessage();
            $this->addLog('SIRS', $row['rs_namakelas'], 'error', $e->getMessage());
        }
    }

    public function syncSirsSemua(): void
    {
        $this->syncingAll = true;
        $this->syncTotal  = count($this->rows);
        $this->syncDone   = 0;
        $this->logLines   = [];

        foreach ($this->rows as $i => $_) {
            $this->syncSirsSatu($i);
            $this->syncDone++;
        }

        $this->syncingAll = false;
        $this->dispatch('toast', type: 'success', message: 'Sync SIRS selesai: ' . $this->syncDone . ' kelas.');
    }

    // ─── Helper ─────────────────────────────────────────────────────────────
    private function addLog(string $sistem, string $kelas, string $status, string $msg): void
    {
        $this->logLines[] = [
            'waktu'   => now()->format('H:i:s'),
            'sistem'  => $sistem,
            'kelas'   => $kelas,
            'status'  => $status,
            'msg'     => $msg,
        ];
    }
};
?>

<div class="p-4 space-y-4">

    {{-- ══════════════════════════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Update Tempat Tidur RI</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Sync ketersediaan kamar rawat inap ke Aplicares BPJS & SIRS Kemenkes secara real-time.
            </p>
        </div>
        <x-secondary-button wire:click="refresh" wire:loading.attr="disabled" class="shrink-0">
            <svg wire:loading wire:target="refresh" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <svg wire:loading.remove wire:target="refresh" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
            </svg>
            Refresh DB
        </x-secondary-button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         RINGKASAN
    ══════════════════════════════════════════════════════════════ --}}
    @php
        $totalKap  = collect($rows)->sum('kapasitas');
        $totalTerp = collect($rows)->sum('terpakai');
        $totalTers = collect($rows)->sum('tersedia');
        $totalOcc  = $totalKap > 0 ? round($totalTerp / $totalKap * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $totalKap }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Kapasitas</div>
        </div>
        <div class="p-3 bg-rose-50 dark:bg-rose-900/20 rounded-xl border border-rose-200 dark:border-rose-800 text-center">
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $totalTerp }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Terisi</div>
        </div>
        <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800 text-center">
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalTers }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tersedia</div>
        </div>
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $totalOcc }}%</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Occupancy</div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         PROGRESS SYNC
    ══════════════════════════════════════════════════════════════ --}}
    @if ($syncingAll)
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 animate-spin text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                    Sync berjalan… {{ $syncDone }} / {{ $syncTotal }}
                </span>
            </div>
            <div class="mt-2 w-full bg-blue-200 dark:bg-blue-800 rounded-full h-1.5">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all"
                     style="width: {{ $syncTotal > 0 ? ($syncDone / $syncTotal * 100) : 0 }}%"></div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         TAB SWITCHER
    ══════════════════════════════════════════════════════════════ --}}
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-fit">
        <button wire:click="$set('activeTab','aplicares')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition
                       {{ $activeTab === 'aplicares'
                            ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-gray-100'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                             bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                Aplicares
            </span>
        </button>
        <button wire:click="$set('activeTab','sirs')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition
                       {{ $activeTab === 'sirs'
                            ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-gray-100'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                             bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                SIRS Kemenkes
            </span>
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         TAB: APLICARES
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'aplicares')
        <div class="space-y-3">
            {{-- Toolbar --}}
            <div class="flex justify-end">
                <x-primary-button wire:click="syncAplicSemua"
                                  wire:loading.attr="disabled"
                                  wire:confirm="Sync semua kelas ke Aplicares BPJS?">
                    <svg wire:loading wire:target="syncAplicSemua" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="syncAplicSemua" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Sync Semua ke Aplicares
                </x-primary-button>
            </div>

            {{-- Tabel --}}
            @include('pages.transaksi.ri.update-tt-ri._table-aplicares')
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         TAB: SIRS
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'sirs')
        <div class="space-y-3">
            {{-- Toolbar --}}
            <div class="flex items-center justify-between gap-2">
                <x-secondary-button wire:click="loadSirsExisting" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="loadSirsExisting" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="loadSirsExisting" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                    Ambil Data Existing SIRS
                </x-secondary-button>

                <x-primary-button wire:click="syncSirsSemua"
                                  wire:loading.attr="disabled"
                                  wire:confirm="Sync semua kelas ke SIRS Kemenkes?">
                    <svg wire:loading wire:target="syncSirsSemua" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="syncSirsSemua" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Sync Semua ke SIRS
                </x-primary-button>
            </div>

            {{-- Info id_t_tt --}}
            @if (collect($rows)->whereNull('id_t_tt_sirs')->count() > 0)
                <div class="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-700 dark:text-amber-300">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>
                        Beberapa kelas belum punya <code class="font-mono text-xs bg-amber-100 dark:bg-amber-800 px-1 rounded">id_t_tt</code> SIRS.
                        Klik <strong>Ambil Data Existing SIRS</strong> untuk load data yang sudah pernah dikirim, atau langsung Sync (akan Insert baru).
                    </span>
                </div>
            @endif

            {{-- Tabel --}}
            @include('pages.transaksi.ri.update-tt-ri._table-sirs')
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         LOG SYNC
    ══════════════════════════════════════════════════════════════ --}}
    @if (!empty($logLines))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                    Log Sync
                </span>
                <button wire:click="$set('logLines', [])" class="text-xs text-gray-400 hover:text-red-500 transition">
                    Hapus Log
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-52 overflow-y-auto">
                @foreach (array_reverse($logLines) as $log)
                    <div class="flex items-center gap-3 px-4 py-2 text-sm">
                        <span class="font-mono text-xs text-gray-400 shrink-0 w-14">{{ $log['waktu'] }}</span>
                        <span class="shrink-0">
                            @if ($log['sistem'] === 'APLIC')
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                            @endif
                        </span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300 shrink-0 w-20">{{ $log['kelas'] }}</span>
                        @if ($log['status'] === 'ok')
                            <span class="text-emerald-600 dark:text-emerald-400 shrink-0">&#10003;</span>
                        @else
                            <span class="text-red-500 shrink-0">&#10007;</span>
                        @endif
                        <span class="text-gray-500 dark:text-gray-400 truncate">{{ $log['msg'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
