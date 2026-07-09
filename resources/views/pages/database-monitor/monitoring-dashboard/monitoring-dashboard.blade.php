<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $connection = 'oracle';

    // ── Realtime perf (rolling window) ──────────────────────
    public int $perfWindow = 60;
    public array $perfSeries = [
        'ts'          => [],
        'aas'         => [],
        'dbcpu_ratio' => [],
        'host_cpu'    => [],
    ];

    // ── Filters (Locks) ─────────────────────────────────────
    public bool $onlyWaiting = true;
    public ?string $filterUser = null;
    public ?string $filterProgram = null;
    public ?int $minSecondsInWait = 5;

    // ── Filters (Heavy / Longops) ────────────────────────────
    public bool $excludeIdle = true;
    public ?int $minSecondsActive = 30;
    public ?int $minLongopsPct = 0;

    // ── Result sets ─────────────────────────────────────────
    public array $rows = [];
    public array $heavyRows = [];
    public array $longopsRows = [];

    // ── Tab ─────────────────────────────────────────────────
    public string $tab = 'locks';

    // ── Auto-refresh on/off ─────────────────────────────────
    public bool $autoRefresh = true;

    // ── Kill tracking ───────────────────────────────────────
    // Format: [sid => ['serial'=>..., 'at'=>ts, 'status'=>'sent|killed|gone|active|error', 'mode'=>'kill|disconnect']]
    public array $recentlyKilled = [];

    public function mount(): void
    {
        $this->refreshData();
    }

    public function setTab(string $t): void
    {
        $this->tab = in_array($t, ['locks', 'heavy', 'longops'], true) ? $t : 'locks';
    }

    public function refreshData(): void
    {
        $this->pruneRecentlyKilled();
        $this->refreshLocks();
        $this->refreshHeavy();
        $this->refreshLongops();
        $this->refreshPerf();
    }

    private function pruneRecentlyKilled(): void
    {
        $now = time();
        $this->recentlyKilled = collect($this->recentlyKilled)
            ->filter(fn($v) => $now - ($v['at'] ?? 0) < 120) // 2 menit TTL
            ->all();
    }

    // ── Perf chart ──────────────────────────────────────────
    public function refreshPerf(): void
    {
        $ts    = date('H:i:s');
        $aas   = 0.0;
        $dbcpu = 0.0;
        $host  = 0.0;

        try {
            $base = str_replace(
                search:  ':V:',
                replace: 'v$',
                subject: <<<'SQL'
                WITH snap AS (
                  SELECT metric_name, value
                  FROM   :V:sysmetric
                  WHERE  group_id = 2
                  AND    end_time = (SELECT MAX(end_time) FROM :V:sysmetric WHERE group_id = 2)
                  AND    metric_name IN (
                           'Average Active Sessions',
                           'Database CPU Time Ratio',
                           'Host CPU Utilization (%)',
                           'Database Time Per Sec',
                           'CPU Usage Per Sec'
                         )
                )
                SELECT
                  TO_CHAR(SYSDATE,'HH24:MI:SS')                                       AS ts,
                  MAX(CASE WHEN metric_name='Average Active Sessions'  THEN value END) AS aas,
                  MAX(CASE WHEN metric_name='Database CPU Time Ratio'  THEN value END) AS dbcpu_ratio,
                  MAX(CASE WHEN metric_name='Host CPU Utilization (%)' THEN value END) AS host_cpu,
                  MAX(CASE WHEN metric_name='Database Time Per Sec'    THEN value END) AS db_time_ps,
                  MAX(CASE WHEN metric_name='CPU Usage Per Sec'        THEN value END) AS cpu_ps
                FROM snap
SQL
            );

            $sql_v    = $base;
            $sql_gv   = str_replace(search: 'v$sysmetric', replace: 'gv$sysmetric',       subject: $base);
            $sql_hist = str_replace(search: 'v$sysmetric', replace: 'v$sysmetric_history', subject: $base);

            $db  = DB::connection($this->connection);
            $row = collect($db->select($sql_v))->first()
                ?? collect($db->select($sql_gv))->first()
                ?? collect($db->select($sql_hist))->first();

            if ($row) {
                $r     = collect((array) $row)->mapWithKeys(fn($v, $k) => [strtolower($k) => $v])->all();
                $ts    = $r['ts']         ?? $ts;
                $dbt   = (float) ($r['db_time_ps']  ?? 0);
                $cpu   = (float) ($r['cpu_ps']       ?? 0);
                $aas   = isset($r['aas'])        ? (float) $r['aas']         : $dbt;
                $dbcpu = isset($r['dbcpu_ratio']) ? (float) $r['dbcpu_ratio'] : ($dbt > 0 ? (100 * $cpu) / $dbt : 0);
                $host  = isset($r['host_cpu'])    ? (float) $r['host_cpu']    : 0.0;
            }
        } catch (\Throwable) {
            // silent — perf chart tidak perlu toast
        }

        foreach (['ts' => $ts, 'aas' => $aas, 'dbcpu_ratio' => $dbcpu, 'host_cpu' => $host] as $k => $v) {
            $this->perfSeries[$k][] = $v;
            if (count($this->perfSeries[$k]) > $this->perfWindow) {
                array_shift($this->perfSeries[$k]);
            }
        }

        $this->dispatch(
            'perf-sample',
            labels: $this->perfSeries['ts'],
            series: [
                'aas'        => $this->perfSeries['aas'],
                'dbcpuRatio' => $this->perfSeries['dbcpu_ratio'],
                'hostCpu'    => $this->perfSeries['host_cpu'],
            ],
        );
    }

    // ── Locks ────────────────────────────────────────────────
    public function refreshLocks(): void
    {
        try {
            $sql = str_replace(
                search:  ':V:',
                replace: 'v$',
                subject: <<<'SQL'
                WITH locks AS (
                    SELECT l1.sid AS blocker_sid,
                           l2.sid AS waiter_sid,
                           l1.id1, l1.id2
                    FROM :V:lock l1
                    JOIN :V:lock l2 ON l2.id1 = l1.id1 AND l2.id2 = l1.id2
                    WHERE l1.block = 1 AND l2.block = 0
                )
                SELECT
                    bs.sid                        AS blocker_sid,
                    bs.serial#                    AS blocker_serial,
                    bs.username                   AS blocker_user,
                    bs.program                    AS blocker_program,
                    bs.module                     AS blocker_module,
                    bs.machine                    AS blocker_machine,
                    bs.event                      AS blocker_event,
                    NVL(bs.seconds_in_wait,0)     AS blocker_seconds_wait,
                    NVL(bs.sql_id,bs.prev_sql_id) AS blocker_sql_id,
                    SUBSTR(sb.sql_text,1,1000)    AS blocker_sql_text,
                    ws.sid                        AS waiter_sid,
                    ws.serial#                    AS waiter_serial,
                    ws.username                   AS waiter_user,
                    ws.program                    AS waiter_program,
                    ws.module                     AS waiter_module,
                    ws.machine                    AS waiter_machine,
                    ws.event                      AS waiter_event,
                    NVL(ws.seconds_in_wait,0)     AS waiter_seconds_wait,
                    NVL(ws.sql_id,ws.prev_sql_id) AS waiter_sql_id,
                    SUBSTR(sw.sql_text,1,1000)    AS waiter_sql_text,
                    o.owner||'.'||o.object_name   AS locked_object,
                    o.object_type
                FROM locks k
                JOIN :V:session bs ON bs.sid = k.blocker_sid
                JOIN :V:session ws ON ws.sid = k.waiter_sid
                LEFT JOIN all_objects o  ON o.object_id = k.id1
                LEFT JOIN :V:sqlarea sb  ON sb.sql_id = NVL(bs.sql_id,bs.prev_sql_id)
                LEFT JOIN :V:sqlarea sw  ON sw.sql_id = NVL(ws.sql_id,ws.prev_sql_id)
                ORDER BY ws.seconds_in_wait DESC NULLS LAST
SQL
            );

            $min = (int) ($this->minSecondsInWait ?? 0);

            $this->rows = collect(DB::connection($this->connection)->select($sql))
                ->map(fn($r) => collect((array) $r)->mapWithKeys(fn($v, $k) => [strtolower($k) => $v])->all())
                ->when($this->onlyWaiting, fn($c) => $c->filter(fn($r) => (int) ($r['waiter_seconds_wait'] ?? 0) >= $min))
                ->when($this->filterUser, function ($c) {
                    $q = strtoupper(trim($this->filterUser ?? ''));
                    return $c->filter(fn($r) => $q === '' || str_contains(strtoupper(($r['waiter_user'] ?? '') . ' ' . ($r['blocker_user'] ?? '')), $q));
                })
                ->when($this->filterProgram, function ($c) {
                    $q = strtoupper(trim($this->filterProgram ?? ''));
                    return $c->filter(fn($r) => $q === '' || str_contains(strtoupper(($r['waiter_program'] ?? '') . ' ' . ($r['blocker_program'] ?? '')), $q));
                })
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            $this->rows = [];
        }
    }

    // ── Heavy (long-running) ────────────────────────────────
    public function refreshHeavy(): void
    {
        try {
            $sql = str_replace(
                search:  ':V:',
                replace: 'v$',
                subject: <<<'SQL'
                SELECT
                    s.sid,
                    s.serial#                    AS serial,
                    s.username,
                    s.program,
                    s.module,
                    s.machine,
                    s.status,
                    s.event,
                    s.wait_class,
                    s.last_call_et               AS seconds_active,
                    NVL(s.sql_id,s.prev_sql_id)  AS sql_id,
                    SUBSTR(sa.sql_text,1,1000)   AS sql_text,
                    sa.executions,
                    sa.elapsed_time/1e6          AS elapsed_sec,
                    sa.cpu_time/1e6              AS cpu_sec,
                    sa.buffer_gets,
                    sa.disk_reads,
                    sa.rows_processed,
                    sa.first_load_time,
                    sa.last_active_time
                FROM :V:session s
                LEFT JOIN :V:sqlarea sa ON sa.sql_id = NVL(s.sql_id,s.prev_sql_id)
                WHERE s.username IS NOT NULL
                  AND s.status = 'ACTIVE'
                ORDER BY s.last_call_et DESC NULLS LAST
SQL
            );

            $this->heavyRows = collect(DB::connection($this->connection)->select($sql))
                ->map(fn($r) => collect((array) $r)->mapWithKeys(fn($v, $k) => [strtolower($k) => $v])->all())
                ->filter(function ($r) {
                    $okTime = (int) ($r['seconds_active'] ?? 0) >= (int) ($this->minSecondsActive ?? 0);
                    $okIdle = $this->excludeIdle ? strtoupper($r['wait_class'] ?? '') !== 'IDLE' : true;
                    return $okTime && $okIdle;
                })
                ->values()
                ->toArray();

            $top = collect($this->heavyRows)
                ->sortByDesc('seconds_active')
                ->take(5)
                ->map(fn($r) => [
                    'label' => sprintf('%s (SID %s)', $r['username'] ?? 'SYS', $r['sid'] ?? '?'),
                    'value' => (int) ($r['seconds_active'] ?? 0),
                    'event' => $r['event'] ?? '',
                ])
                ->values()
                ->all();

            $this->dispatch('heavy-top', bars: $top);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            $this->heavyRows = [];
        }
    }

    // ── Long Ops ─────────────────────────────────────────────
    public function refreshLongops(): void
    {
        try {
            $sql = str_replace(
                search:  ':V:',
                replace: 'v$',
                subject: <<<'SQL'
                SELECT
                    sl.sid,
                    sl.serial#                                      AS serial,
                    sl.opname,
                    sl.target,
                    sl.sofar,
                    sl.totalwork,
                    ROUND((sl.sofar/NULLIF(sl.totalwork,0))*100,2) AS pct,
                    sl.elapsed_seconds,
                    sl.time_remaining,
                    s.username,
                    s.program,
                    s.module,
                    s.machine
                FROM :V:session_longops sl
                JOIN :V:session s ON s.sid = sl.sid AND s.serial# = sl.serial#
                WHERE sl.totalwork > 0
                  AND sl.sofar < sl.totalwork
                ORDER BY pct DESC NULLS LAST
SQL
            );

            $this->longopsRows = collect(DB::connection($this->connection)->select($sql))
                ->map(fn($r) => collect((array) $r)->mapWithKeys(fn($v, $k) => [strtolower($k) => $v])->all())
                ->filter(fn($r) => (float) ($r['pct'] ?? 0) >= (float) ($this->minLongopsPct ?? 0))
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            $this->longopsRows = [];
        }
    }

    // ── Kill session ─────────────────────────────────────────
    /**
     * Kill atau Disconnect sesi Oracle.
     *
     *   mode = 'kill'       → ALTER SYSTEM KILL SESSION ... IMMEDIATE
     *                         (umum; cepat tapi nunggu klien putus untuk kasus
     *                          'SQL*Net more data from client')
     *   mode = 'disconnect' → ALTER SYSTEM DISCONNECT SESSION ... IMMEDIATE
     *                         (lebih agresif; memutus socket TCP juga)
     *
     * ORA-00031 (session marked for kill) DIPERLAKUKAN SEBAGAI SUKSES:
     * Oracle sudah menandai sesi untuk dimatikan, hanya cleanup-nya async
     * (PMON akan beresin saat sesi target merespon / socket putus).
     */
    public function killSession(int $sid, int $serial, string $mode = 'kill'): void
    {
        $mode = $mode === 'disconnect' ? 'disconnect' : 'kill';

        // Tandai dulu di UI: badge "Sent" muncul instan setelah refreshData
        $this->recentlyKilled[$sid] = [
            'serial' => $serial,
            'at'     => time(),
            'status' => 'sent',
            'mode'   => $mode,
        ];

        $stmt = $mode === 'disconnect'
            ? "ALTER SYSTEM DISCONNECT SESSION '{$sid},{$serial}' IMMEDIATE"
            : "ALTER SYSTEM KILL SESSION '{$sid},{$serial}' IMMEDIATE";

        $markedForKill = false;
        $errorMsg      = null;

        try {
            DB::connection($this->connection)->statement($stmt);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // ORA-00031: session marked for kill — bukan error, hanya async cleanup
            if (str_contains($msg, 'ORA-00031')) {
                $markedForKill = true;
            } else {
                $errorMsg = $msg;
            }
        }

        // Verifikasi pasca-eksekusi: cek status di v$session
        $finalStatus = 'unknown';
        try {
            $row = collect(
                DB::connection($this->connection)->select(
                    'SELECT status FROM v$session WHERE sid = :sid AND serial# = :ser',
                    ['sid' => $sid, 'ser' => $serial],
                ),
            )->first();

            if (!$row) {
                $finalStatus = 'gone';
            } else {
                $arr = collect((array) $row)
                    ->mapWithKeys(fn($v, $k) => [strtolower($k) => $v])
                    ->all();
                $finalStatus = strtolower((string) ($arr['status'] ?? ''));
            }
        } catch (\Throwable) {
            // ignore — biarin UI tampilkan status fallback
        }

        // Resolve final state untuk badge & toast
        if ($errorMsg && !$markedForKill) {
            $this->recentlyKilled[$sid]['status'] = 'error';
            $this->recentlyKilled[$sid]['note']   = $errorMsg;
        } elseif ($finalStatus === 'gone') {
            $this->recentlyKilled[$sid]['status'] = 'gone';
        } elseif ($finalStatus === 'killed' || $markedForKill) {
            $this->recentlyKilled[$sid]['status'] = 'killed';
        } else {
            // Statement berhasil dieksekusi tapi sesi masih ACTIVE/INACTIVE — kemungkinan
            // ALTER SYSTEM diterima namun sesi belum diberhentikan oleh Oracle.
            $this->recentlyKilled[$sid]['status'] = 'active';
        }

        // ── Toast: pesan diferensiasi per-status ──
        $label = "SID {$sid}, SER# {$serial}";
        $verb  = $mode === 'disconnect' ? 'Disconnect' : 'Kill';

        if ($errorMsg && !$markedForKill) {
            $this->dispatch('toast',
                type:    'error',
                message: "❌ {$verb} {$label} gagal: {$errorMsg}",
                opts:    ['timeOut' => 10000, 'closeButton' => true],
            );
        } elseif ($finalStatus === 'gone') {
            $this->dispatch('toast',
                type:    'success',
                message: "✓ {$label} sudah hilang dari v\$session. Selesai.",
                opts:    ['timeOut' => 6000, 'closeButton' => true],
            );
        } elseif ($finalStatus === 'killed' || $markedForKill) {
            $hint = $mode === 'kill'
                ? "Sesi ditandai KILLED, menunggu PMON cleanup (biasanya 30–60s). Kalau lama mandek, coba tombol 'Disconnect' untuk paksa putus socket."
                : "Sesi ditandai untuk DISCONNECT, menunggu cleanup socket (biasanya beberapa detik).";
            $this->dispatch('toast',
                type:    'warning',
                message: "⏳ {$verb} {$label}: {$hint}",
                opts:    ['timeOut' => 12000, 'closeButton' => true],
            );
        } else {
            $this->dispatch('toast',
                type:    'info',
                message: "ℹ {$verb} {$label} dikirim, sesi masih {$finalStatus}. Coba ulangi atau pakai mode Disconnect.",
                opts:    ['timeOut' => 10000, 'closeButton' => true],
            );
        }

        $this->refreshData();
    }

    /**
     * Wrapper untuk varian DISCONNECT SESSION — lebih agresif memutus socket TCP.
     * Direkomendasikan ketika sesi blocker stuck di event 'SQL*Net more data from client'
     * atau 'SQL*Net message from client' selama > beberapa menit dengan transaksi terbuka.
     */
    public function disconnectSession(int $sid, int $serial): void
    {
        $this->killSession($sid, $serial, 'disconnect');
    }
};
?>

<div>
    <x-page-title
        title="Oracle Session Monitor"
        subtitle="Locks, Long-Running SQL &amp; Kill Session" />

    {{-- ── BANNER: indikator request kill sedang dikirim ke Oracle ──
         Posisi: top-24 (di bawah topbar h-20) + z-[60] (di atas topbar z-50).
         style="display:none" sebagai default sebelum Livewire boot supaya
         tidak flash saat halaman pertama dirender. --}}
    <div wire:loading.flex wire:target="killSession,disconnectSession"
         style="display: none"
         class="fixed top-24 right-6 z-[60] items-center gap-3 px-4 py-3
                rounded-lg shadow-lg bg-amber-50 border border-amber-300
                text-amber-800 dark:bg-amber-900/40 dark:border-amber-600 dark:text-amber-200">
        <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"></circle>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        <div class="text-sm leading-tight">
            <div class="font-semibold">Mengirim perintah ke Oracle…</div>
            <div class="text-xs opacity-80">Menunggu verifikasi v$session (1–3 detik).</div>
        </div>
    </div>

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- ── TOOLBAR (Tabs + Filters) ── --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                {{-- ── Tabs (gaya underline kasir) ── --}}
                @php
                    $tabs = [
                        'locks'   => ['label' => 'Locks',        'active' => 'text-rose-700 border-rose-600 dark:text-rose-300 dark:border-rose-400'],
                        'heavy'   => ['label' => 'Long-Running', 'active' => 'text-amber-700 border-amber-600 dark:text-amber-300 dark:border-amber-400'],
                        'longops' => ['label' => 'Long Ops',     'active' => 'text-emerald-700 border-emerald-600 dark:text-emerald-300 dark:border-emerald-400'],
                    ];
                @endphp
                <div class="flex items-center border-b border-hairline dark:border-gray-700">
                    @foreach ($tabs as $key => $meta)
                        <button type="button" wire:click="setTab('{{ $key }}')"
                            class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $tab === $key ? $meta['active'] : 'text-muted border-transparent hover:text-body dark:text-gray-400 dark:hover:text-gray-200' }}">
                            {{ $meta['label'] }}
                        </button>
                    @endforeach
                    <div class="ml-auto flex items-center gap-3">
                        {{-- Tombol refresh manual (komponen ghost-button) --}}
                        <x-ghost-button wire:click="refreshData"
                            wire:loading.attr="disabled" wire:target="refreshData">
                            <svg wire:loading.remove wire:target="refreshData" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <svg wire:loading wire:target="refreshData" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"></circle>
                                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            </svg>
                            Refresh
                        </x-ghost-button>

                        {{-- Toggle auto-refresh (komponen x-toggle) --}}
                        <x-toggle wire:model.live="autoRefresh" :trueValue="true" :falseValue="false">
                            <span class="text-xs">
                                Auto-refresh
                                @if ($autoRefresh)
                                    <span class="font-mono text-emerald-600 dark:text-emerald-400">ON · {{ in_array($tab, ['locks', 'longops']) ? '5s' : '3s' }}</span>
                                @else
                                    <span class="font-mono text-muted-soft">OFF</span>
                                @endif
                            </span>
                        </x-toggle>
                    </div>
                    @if ($autoRefresh && $tab === 'heavy')
                        <div wire:poll.3s="refreshPerf"></div>
                        <div wire:poll.3s="refreshHeavy"></div>
                    @endif
                </div>

                {{-- ── Panel: Cara Pakai (per-tab) ── --}}
                <details class="mt-3 group overflow-hidden bg-blue-50 border border-blue-200 rounded-2xl dark:bg-blue-900/20 dark:border-blue-700">
                    <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-blue-900 dark:text-blue-200 flex items-center gap-2">
                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        Cara Pakai
                        @if ($tab === 'locks')        <span class="text-rose-600 dark:text-rose-300">— Locks: identifikasi & matikan sesi yang saling blok</span>
                        @elseif ($tab === 'heavy')    <span class="text-amber-600 dark:text-amber-300">— Long-Running: pantau sesi ACTIVE yang berjalan lama</span>
                        @elseif ($tab === 'longops')  <span class="text-emerald-600 dark:text-emerald-300">— Long Ops: operasi server dengan progress %</span>
                        @endif
                    </summary>
                    <div class="px-4 pb-3 pt-1 text-xs leading-relaxed text-muted dark:text-gray-300 space-y-2">
                        @if ($tab === 'locks')
                            <p>Tabel menampilkan pasangan <b>Blocker</b> ↔ <b>Waiter</b>. Waiter terkunci karena Blocker memegang row lock.</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Klik <b class="text-red-600">Kill Blocker</b> dulu — semua Waiter akan release otomatis tanpa perlu di-kill satu per satu.</li>
                                <li>Toast akan menampilkan status: <b>✓ Gone</b> (sesi hilang dari <code>v$session</code>), <b>⏳ Marked KILL</b> (ditandai, tunggu PMON cleanup 30–60s), atau <b>✗ Failed</b>.</li>
                                <li>Kalau status <b>⏳ Marked KILL</b> tidak juga hilang setelah ~1 menit (sesi stuck di event <code>SQL*Net more data from client</code>), klik <b class="text-brand-green dark:text-brand-lime">Disconnect</b> untuk paksa putus socket TCP.</li>
                                <li><b>Kill Waiter</b> hanya untuk kasus khusus — biasanya tidak perlu, karena killing Blocker sudah cukup.</li>
                            </ol>
                            <p class="text-rose-700 dark:text-rose-300"><b>Catatan ORA-00031:</b> "session marked for kill" <i>bukan error fatal</i>. Oracle sudah terima perintah, hanya cleanup-nya asynchronous (menunggu sesi target merespon / socket putus).</p>
                        @elseif ($tab === 'heavy')
                            <p>Sesi dengan status <code>ACTIVE</code> yang berjalan lebih lama dari filter <b>Active ≥ Xs</b>. Berguna untuk menemukan query yang lambat / hang.</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Lihat kolom <b>SQL Info</b> untuk identifikasi query yang berjalan lama.</li>
                                <li>Chart <b>Database Performance</b> & <b>Top Active Sessions</b> di atas tabel update tiap 3 detik.</li>
                                <li>Klik <b class="text-red-600">Kill Session</b> kalau yakin sesi tersebut bermasalah. Pakai <b>Disconnect</b> untuk varian agresif.</li>
                            </ol>
                        @elseif ($tab === 'longops')
                            <p>Operasi server-side dengan progress terukur (mis. <code>RMAN backup</code>, <code>CREATE INDEX</code>, <code>ANALYZE</code>, <code>parallel scan</code>). Kolom <b>Progress</b> menunjukkan persentase, <b>ETA</b> sisa waktu (detik).</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Filter <b>Min Progress ≥ X%</b> untuk fokus ke operasi yang sudah jauh.</li>
                                <li>Hanya kill kalau memang operasi salah jalan — kebanyakan long-ops legitimate dan akan selesai sendiri.</li>
                            </ol>
                        @endif
                    </div>
                </details>

                {{-- ── Filters + Charts ── --}}
                <div class="grid grid-cols-1 gap-2 mt-3 md:grid-cols-3">

                    @if ($tab === 'heavy')
                        @once
                            <script>
                                (function() {
                                    let perfChart = null,
                                        heavyChart = null;

                                    function loadChartJs() {
                                        return new Promise((resolve, reject) => {
                                            if (window.Chart) return resolve();
                                            let tag = document.querySelector('script[data-chartjs]');
                                            if (tag) {
                                                tag.addEventListener('load', resolve);
                                                tag.addEventListener('error', reject);
                                                return;
                                            }
                                            tag = document.createElement('script');
                                            tag.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                                            tag.defer = tag.async = true;
                                            tag.dataset.chartjs = '';
                                            tag.onload = resolve;
                                            tag.onerror = reject;
                                            document.head.appendChild(tag);
                                        });
                                    }

                                    function ensurePerfChart() {
                                        const el = document.getElementById('perfChart');
                                        if (!el || perfChart) return;
                                        perfChart = new Chart(el.getContext('2d'), {
                                            type: 'line',
                                            data: {
                                                labels: [],
                                                datasets: [
                                                    {
                                                        label: 'Average Active Sessions',
                                                        data: [],
                                                        tension: 0.3
                                                    },
                                                    {
                                                        label: 'DB CPU Time Ratio (%)',
                                                        data: [],
                                                        yAxisID: 'y1',
                                                        tension: 0.3
                                                    },
                                                    {
                                                        label: 'Host CPU Util (%)',
                                                        data: [],
                                                        yAxisID: 'y1',
                                                        tension: 0.3
                                                    },
                                                ]
                                            },
                                            options: {
                                                responsive: true,
                                                animation: false,
                                                plugins: {
                                                    legend: { position: 'bottom' }
                                                },
                                                scales: {
                                                    y: {
                                                        title: { display: true, text: 'AAS' }
                                                    },
                                                    y1: {
                                                        position: 'right',
                                                        title: { display: true, text: '%' },
                                                        min: 0,
                                                        max: 100,
                                                        grid: { drawOnChartArea: false }
                                                    }
                                                }
                                            }
                                        });
                                    }

                                    function ensureHeavyChart() {
                                        const el = document.getElementById('heavyChart');
                                        if (!el || heavyChart) return;
                                        heavyChart = new Chart(el.getContext('2d'), {
                                            type: 'bar',
                                            data: {
                                                labels: [],
                                                datasets: [{ label: 'Seconds Active', data: [] }]
                                            },
                                            options: {
                                                responsive: true,
                                                animation: false,
                                                parsing: false,
                                                plugins: {
                                                    legend: { display: false },
                                                    tooltip: {
                                                        callbacks: {
                                                            afterLabel: ctx => ctx?.raw?.event ? '\n' + ctx.raw.event : ''
                                                        }
                                                    }
                                                },
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        title: { display: true, text: 'sec' }
                                                    }
                                                }
                                            }
                                        });
                                    }

                                    window.addEventListener('perf-sample', async (ev) => {
                                        try {
                                            await loadChartJs();
                                            if (!perfChart) ensurePerfChart();
                                            if (!perfChart) return;
                                            const { labels, series } = ev.detail;
                                            perfChart.data.labels = labels;
                                            perfChart.data.datasets[0].data = series.aas;
                                            perfChart.data.datasets[1].data = series.dbcpuRatio;
                                            perfChart.data.datasets[2].data = series.hostCpu;
                                            perfChart.update('none');
                                        } catch {}
                                    });

                                    window.addEventListener('heavy-top', async (ev) => {
                                        try {
                                            await loadChartJs();
                                            if (!heavyChart) ensureHeavyChart();
                                            if (!heavyChart) return;
                                            const bars = ev.detail.bars || [];
                                            heavyChart.data.labels = bars.map(b => b.label);
                                            heavyChart.data.datasets[0].data = bars.map(b => ({
                                                x: b.label,
                                                y: b.value,
                                                event: b.event
                                            }));
                                            heavyChart.update('none');
                                        } catch {}
                                    });
                                })();
                            </script>
                        @endonce

                        <div class="grid grid-cols-1 gap-4 mt-4 lg:col-span-3 lg:grid-cols-2">
                            <div class="p-4 bg-canvas border border-hairline rounded-lg shadow-sm" wire:ignore>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold">Database Performance (rolling)</h3>
                                    <span class="text-xs text-muted">Live</span>
                                </div>
                                <canvas id="perfChart" height="140"></canvas>
                            </div>
                            <div class="p-4 bg-canvas border border-hairline rounded-lg shadow-sm" wire:ignore>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold">Top Active Sessions (by seconds active)</h3>
                                    <span class="text-xs text-muted">Live</span>
                                </div>
                                <canvas id="heavyChart" height="140"></canvas>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 lg:col-span-3">
                            <x-input-label for="minSecondsActive" :value="__('Active ≥')" />
                            <x-text-input id="minSecondsActive" type="number" min="0" class="w-20"
                                wire:model.blur="minSecondsActive" />
                            <span class="text-sm">s</span>
                            <x-input-label for="excludeIdle" :value="__('Exclude Idle')" class="ml-4" />
                            <x-toggle id="excludeIdle" wire:model.live="excludeIdle" trueValue="1" falseValue="0" />
                        </div>
                    @endif

                    @if ($tab === 'locks')
                        <div class="flex items-center gap-2">
                            <x-input-label for="minSecondsInWait" :value="__('Only Waiting ≥')" />
                            <x-text-input id="minSecondsInWait" type="number" min="0" class="w-20"
                                wire:model.blur="minSecondsInWait" />
                            <span class="text-sm">s</span>
                            <x-input-label for="filterUser" :value="__('User')" class="ml-4" />
                            <x-text-input id="filterUser" type="text" placeholder="SCOTT..."
                                wire:model.live.debounce.500ms="filterUser" />
                            <x-input-label for="filterProgram" :value="__('Program')" class="ml-2" />
                            <x-text-input id="filterProgram" type="text" placeholder="JDBC..."
                                wire:model.live.debounce.500ms="filterProgram" />
                        </div>
                    @endif

                    @if ($tab === 'longops')
                        <div class="flex items-center gap-2">
                            <x-input-label for="minLongopsPct" :value="__('Min Progress ≥')" />
                            <x-text-input id="minLongopsPct" type="number" min="0" max="100" class="w-20"
                                wire:model.blur="minLongopsPct" />
                            <span class="text-sm">%</span>
                        </div>
                    @endif
                </div>

                {{-- ── Data Tables ── --}}
                <div class="flex flex-col mt-4">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden shadow sm:rounded-lg">

                                <div class="overflow-auto border rounded"
                                    @if ($autoRefresh && $tab === 'locks') wire:poll.5s="refreshLocks"
                                    @elseif ($autoRefresh && $tab === 'longops') wire:poll.5s="refreshLongops" @endif>

                                    
            </div>{{-- /toolbar --}}

            {{-- ── TABLE WRAPPER ── --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl"
                    @if ($autoRefresh && $tab === 'locks') wire:poll.5s="refreshLocks"
                    @elseif ($autoRefresh && $tab === 'longops') wire:poll.5s="refreshLongops" @endif>

{{-- ════ LOCKS ════ --}}
                                    @if ($tab === 'locks')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                                    <th class="px-6 py-3">Waiter</th>
                                                    <th class="px-6 py-3">Waiter User / Program</th>
                                                    <th class="px-6 py-3">Wait Event</th>
                                                    <th class="px-6 py-3">Wait (s)</th>
                                                    <th class="px-6 py-3">Blocker</th>
                                                    <th class="px-6 py-3">Blocker User / Program</th>
                                                    <th class="px-6 py-3">Locked Object</th>
                                                    <th class="px-6 py-3 text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $r)
                                                    @php
                                                        $bOk = isset($r['blocker_sid'], $r['blocker_serial'])
                                                            && is_numeric($r['blocker_sid'])
                                                            && is_numeric($r['blocker_serial']);
                                                        $wOk = isset($r['waiter_sid'], $r['waiter_serial'])
                                                            && is_numeric($r['waiter_sid'])
                                                            && is_numeric($r['waiter_serial']);
                                                        $bKill = $recentlyKilled[(int) ($r['blocker_sid'] ?? 0)] ?? null;
                                                        $wKill = $recentlyKilled[(int) ($r['waiter_sid'] ?? 0)] ?? null;
                                                        $rowFlash = ($bKill || $wKill) ? 'ring-2 ring-amber-400 dark:ring-amber-500' : '';
                                                    @endphp
                                                    <tr class="transition bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-red-50 dark:hover:bg-gray-800 rounded-2xl {{ $rowFlash }}">

                                                        {{-- Waiter SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-body dark:text-gray-200 font-mono">
                                                                {{ $r['waiter_sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 font-mono">
                                                                SER# {{ $r['waiter_serial'] ?? '-' }}
                                                            </div>
                                                            <x-kill-badge :kill="$wKill" />
                                                        </td>

                                                        {{-- Waiter User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['waiter_user'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-muted dark:text-gray-400">
                                                                {{ $r['waiter_program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-500">
                                                                {{ $r['waiter_module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Wait Event --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-sm text-body dark:text-gray-300">
                                                                {{ $r['waiter_event'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Wait seconds --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-xl font-bold text-rose-600 dark:text-rose-400">
                                                                {{ $r['waiter_seconds_wait'] ?? 0 }}
                                                            </div>
                                                        </td>

                                                        {{-- Blocker SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-body dark:text-gray-200 font-mono">
                                                                {{ $r['blocker_sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 font-mono">
                                                                SER# {{ $r['blocker_serial'] ?? '-' }}
                                                            </div>
                                                            <x-kill-badge :kill="$bKill" />
                                                        </td>

                                                        {{-- Blocker User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['blocker_user'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-muted dark:text-gray-400">
                                                                {{ $r['blocker_program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-500">
                                                                {{ $r['blocker_module'] ?? '' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400">
                                                                Wait: {{ $r['blocker_seconds_wait'] ?? 0 }}s
                                                                &middot; {{ $r['blocker_event'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Locked Object --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-sm font-mono text-body dark:text-gray-300">
                                                                {{ $r['locked_object'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400">
                                                                {{ $r['object_type'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Action --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="flex flex-col gap-2">
                                                                @if ($bOk)
                                                                    <x-confirm-button
                                                                        variant="danger"
                                                                        :action="'killSession(' . $r['blocker_sid'] . ',' . $r['blocker_serial'] . ')'"
                                                                        title="Kill Blocker (IMMEDIATE)"
                                                                        message="Kirim ALTER SYSTEM KILL SESSION ke SID {{ $r['blocker_sid'] }} ({{ $r['blocker_user'] ?? '-' }})? Catatan: kalau sesi stuck di 'SQL*Net more data from client', Oracle hanya akan menandai KILLED — pakai Disconnect untuk paksa putus socket."
                                                                        confirmText="Ya, kill"
                                                                        cancelText="Batal">
                                                                        Kill Blocker
                                                                    </x-confirm-button>
                                                                    <x-confirm-button
                                                                        variant="outline"
                                                                        :action="'disconnectSession(' . $r['blocker_sid'] . ',' . $r['blocker_serial'] . ')'"
                                                                        title="Disconnect Blocker (paksa putus socket)"
                                                                        message="ALTER SYSTEM DISCONNECT SESSION lebih agresif memutus socket TCP — efektif untuk sesi blocker yang stuck 'SQL*Net more data from client'. Lanjut untuk SID {{ $r['blocker_sid'] }}?"
                                                                        confirmText="Ya, disconnect"
                                                                        cancelText="Batal">
                                                                        Disconnect
                                                                    </x-confirm-button>
                                                                @else
                                                                    <x-confirm-button variant="danger" :disabled="true">
                                                                        Kill Blocker
                                                                    </x-confirm-button>
                                                                @endif

                                                                @if ($wOk)
                                                                    <x-confirm-button
                                                                        variant="secondary"
                                                                        :action="'killSession(' . $r['waiter_sid'] . ',' . $r['waiter_serial'] . ')'"
                                                                        title="Kill Waiter"
                                                                        message="Catatan: cukup kill BLOCKER — semua waiter akan release otomatis. Kalau memang perlu kill waiter ini saja (SID {{ $r['waiter_sid'] }}), lanjut?"
                                                                        confirmText="Ya, kill"
                                                                        cancelText="Batal">
                                                                        Kill Waiter
                                                                    </x-confirm-button>
                                                                @else
                                                                    <x-confirm-button variant="secondary" :disabled="true">
                                                                        Kill Waiter
                                                                    </x-confirm-button>
                                                                @endif
                                                            </div>
                                                        </td>

                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada blocking rows terdeteksi.</p>
                                        </div>
                                    </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    @endif

                                    {{-- ════ HEAVY ════ --}}
                                    @if ($tab === 'heavy')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                                    <th class="px-6 py-3">Session</th>
                                                    <th class="px-6 py-3">User / Program</th>
                                                    <th class="px-6 py-3">Wait Class / Event</th>
                                                    <th class="px-6 py-3">Active (s)</th>
                                                    <th class="px-6 py-3">SQL Info</th>
                                                    <th class="px-6 py-3">Stats</th>
                                                    <th class="px-6 py-3 text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($heavyRows as $r)
                                                    @php
                                                        $ok = isset($r['sid'], $r['serial'])
                                                            && is_numeric($r['sid'])
                                                            && is_numeric($r['serial']);
                                                        $kill = $recentlyKilled[(int) ($r['sid'] ?? 0)] ?? null;
                                                        $rowFlash = $kill ? 'ring-2 ring-amber-400 dark:ring-amber-500' : '';
                                                    @endphp
                                                    <tr class="transition bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-amber-50 dark:hover:bg-gray-800 rounded-2xl {{ $rowFlash }}">

                                                        {{-- Session SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-body dark:text-gray-200 font-mono">
                                                                {{ $r['sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 font-mono">
                                                                SER# {{ $r['serial'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 mt-1">
                                                                {{ $r['machine'] ?? '-' }}
                                                            </div>
                                                            <x-kill-badge :kill="$kill" />
                                                        </td>

                                                        {{-- User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['username'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-muted dark:text-gray-400">
                                                                {{ $r['program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-500">
                                                                {{ $r['module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Wait Class / Event --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="font-medium text-body dark:text-gray-300">
                                                                {{ $r['wait_class'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-muted dark:text-gray-400">
                                                                {{ $r['event'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Active seconds --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                                                {{ $r['seconds_active'] ?? 0 }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400">seconds</div>
                                                        </td>

                                                        {{-- SQL Info --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-mono text-xs text-body dark:text-gray-300">
                                                                {{ $r['sql_id'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 font-mono whitespace-pre-wrap max-w-xs truncate" title="{{ $r['sql_text'] ?? '' }}">
                                                                {{ $r['sql_text'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Stats --}}
                                                        <td class="px-6 py-4 space-y-1 align-top text-sm text-body dark:text-gray-300">
                                                            <div>Elapsed: <span class="font-semibold">{{ number_format((float) ($r['elapsed_sec'] ?? 0), 2) }}s</span></div>
                                                            <div>CPU: <span class="font-semibold">{{ number_format((float) ($r['cpu_sec'] ?? 0), 2) }}s</span></div>
                                                            <div>Buffers: <span class="font-semibold">{{ $r['buffer_gets'] ?? 0 }}</span></div>
                                                            <div>Disk Reads: <span class="font-semibold">{{ $r['disk_reads'] ?? 0 }}</span></div>
                                                        </td>

                                                        {{-- Action --}}
                                                        <td class="px-6 py-4 align-top text-center">
                                                            @if ($ok)
                                                                <div class="flex flex-col gap-2">
                                                                    <x-confirm-button
                                                                        variant="danger"
                                                                        :action="'killSession(' . $r['sid'] . ',' . $r['serial'] . ')'"
                                                                        title="Kill Session"
                                                                        message="Kirim ALTER SYSTEM KILL SESSION ke SID {{ $r['sid'] }} ({{ $r['username'] ?? '-' }})?"
                                                                        confirmText="Ya, kill"
                                                                        cancelText="Batal">
                                                                        Kill Session
                                                                    </x-confirm-button>
                                                                    <x-confirm-button
                                                                        variant="outline"
                                                                        :action="'disconnectSession(' . $r['sid'] . ',' . $r['serial'] . ')'"
                                                                        title="Disconnect Session"
                                                                        message="DISCONNECT lebih agresif memutus socket TCP. Lanjut untuk SID {{ $r['sid'] }}?"
                                                                        confirmText="Ya, disconnect"
                                                                        cancelText="Batal">
                                                                        Disconnect
                                                                    </x-confirm-button>
                                                                </div>
                                                            @else
                                                                <x-confirm-button variant="danger" :disabled="true">
                                                                    Kill Session
                                                                </x-confirm-button>
                                                            @endif
                                                        </td>

                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada sesi ACTIVE yang melebihi ambang waktu.</p>
                                        </div>
                                    </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    @endif

                                    {{-- ════ LONGOPS ════ --}}
                                    @if ($tab === 'longops')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                                    <th class="px-6 py-3">Session</th>
                                                    <th class="px-6 py-3">User / Program</th>
                                                    <th class="px-6 py-3">Opname / Target</th>
                                                    <th class="px-6 py-3">Progress</th>
                                                    <th class="px-6 py-3">Elapsed (s)</th>
                                                    <th class="px-6 py-3">ETA (s)</th>
                                                    <th class="px-6 py-3 text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($longopsRows as $r)
                                                    @php
                                                        $ok = isset($r['sid'], $r['serial'])
                                                            && is_numeric($r['sid'])
                                                            && is_numeric($r['serial']);
                                                        $pct = (float) ($r['pct'] ?? 0);
                                                        $pctColor = $pct >= 80
                                                            ? 'bg-emerald-500/80 dark:bg-emerald-400'
                                                            : ($pct >= 50
                                                                ? 'bg-amber-400/80 dark:bg-amber-400'
                                                                : 'bg-rose-400/80 dark:bg-rose-400');
                                                        $kill = $recentlyKilled[(int) ($r['sid'] ?? 0)] ?? null;
                                                        $rowFlash = $kill ? 'ring-2 ring-amber-400 dark:ring-amber-500' : '';
                                                    @endphp
                                                    <tr class="transition bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800 rounded-2xl {{ $rowFlash }}">

                                                        {{-- Session SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-body dark:text-gray-200 font-mono">
                                                                {{ $r['sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 font-mono">
                                                                SER# {{ $r['serial'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 mt-1">
                                                                {{ $r['machine'] ?? '-' }}
                                                            </div>
                                                            <x-kill-badge :kill="$kill" />
                                                        </td>

                                                        {{-- User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['username'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-muted dark:text-gray-400">
                                                                {{ $r['program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-500">
                                                                {{ $r['module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Opname / Target --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-medium text-body dark:text-gray-300">
                                                                {{ $r['opname'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400 break-all">
                                                                {{ $r['target'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-muted dark:text-gray-400">
                                                                {{ $r['sofar'] ?? 0 }} / {{ $r['totalwork'] ?? 0 }}
                                                            </div>
                                                        </td>

                                                        {{-- Progress --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-xl font-bold text-body dark:text-gray-200">
                                                                {{ $pct }}%
                                                            </div>
                                                            <div class="w-28 h-1.5 bg-gray-200 rounded-full dark:bg-gray-700 mt-2">
                                                                <div class="h-1.5 rounded-full transition-all duration-500 {{ $pctColor }}"
                                                                    style="width: {{ $pct }}%"></div>
                                                            </div>
                                                        </td>

                                                        {{-- Elapsed --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-xl font-bold text-amber-600 dark:text-amber-400">
                                                                {{ $r['elapsed_seconds'] ?? 0 }}
                                                            </div>
                                                        </td>

                                                        {{-- ETA --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-xl font-bold text-body dark:text-gray-300">
                                                                {{ $r['time_remaining'] ?? 0 }}
                                                            </div>
                                                        </td>

                                                        {{-- Action --}}
                                                        <td class="px-6 py-4 align-top text-center">
                                                            @if ($ok)
                                                                <x-confirm-button
                                                                    variant="danger"
                                                                    :action="'killSession(' . $r['sid'] . ',' . $r['serial'] . ')'"
                                                                    title="Kill Session"
                                                                    message="Yakin kill SID {{ $r['sid'] }} ({{ $r['username'] ?? '-' }})?"
                                                                    confirmText="Ya, kill"
                                                                    cancelText="Batal">
                                                                    Kill Session
                                                                </x-confirm-button>
                                                            @else
                                                                <x-confirm-button variant="danger" :disabled="true">
                                                                    Kill Session
                                                                </x-confirm-button>
                                                            @endif
                                                        </td>

                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada long operations yang berjalan.</p>
                                        </div>
                                    </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    @endif

                
                </div>{{-- /scroll --}}
            </div>{{-- /table wrapper --}}

        </div>
    </div>
</div>