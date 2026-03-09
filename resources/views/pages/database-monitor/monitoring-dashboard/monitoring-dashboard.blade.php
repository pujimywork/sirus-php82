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
        $this->refreshLocks();
        $this->refreshHeavy();
        $this->refreshLongops();
        $this->refreshPerf();
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
    public function killSession(int $sid, int $serial): void
    {
        try {
            DB::connection($this->connection)
                ->statement("ALTER SYSTEM KILL SESSION '{$sid},{$serial}' IMMEDIATE");

            $this->dispatch('toast', type: 'success', message: "Killed SID {$sid}, SERIAL# {$serial}.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } finally {
            $this->refreshData();
        }
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Oracle Session Monitor
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Locks, Long-Running SQL &amp; Kill Session
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- ── TOOLBAR (Tabs + Filters) ── --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
{{-- ── Tabs ── --}}
                <div class="flex items-center gap-2 mt-2">
                    @foreach (['locks' => 'Locks', 'heavy' => 'Long-Running', 'longops' => 'Long Ops'] as $key => $label)
                        <button wire:click="setTab('{{ $key }}')"
                            class="px-3 py-1 rounded-md border text-sm {{ $tab === $key ? 'bg-gray-900 text-white' : 'bg-gray-100' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                    <div class="ml-auto text-xs text-gray-500">
                        Auto-refresh
                        <span class="font-mono">{{ in_array($tab, ['locks', 'longops']) ? '5s' : '15s' }}</span>
                    </div>
                    @if ($tab === 'heavy')
                        <div wire:poll.3s="refreshPerf"></div>
                        <div wire:poll.3s="refreshHeavy"></div>
                    @endif
                </div>

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
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm" wire:ignore>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold">Database Performance (rolling)</h3>
                                    <span class="text-xs text-gray-500">Live</span>
                                </div>
                                <canvas id="perfChart" height="140"></canvas>
                            </div>
                            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm" wire:ignore>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold">Top Active Sessions (by seconds active)</h3>
                                    <span class="text-xs text-gray-500">Live</span>
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
                            <x-toggle id="excludeIdle" wire:model="excludeIdle" trueValue="1" falseValue="0" />
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
                                    @if ($tab === 'locks') wire:poll.5s="refreshLocks"
                                    @elseif ($tab === 'longops') wire:poll.5s="refreshLongops" @endif>

                                    
            </div>{{-- /toolbar --}}

            {{-- ── TABLE WRAPPER ── --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl"
                    @if ($tab === 'locks') wire:poll.5s="refreshLocks"
                    @elseif ($tab === 'longops') wire:poll.5s="refreshLongops" @endif>

{{-- ════ LOCKS ════ --}}
                                    @if ($tab === 'locks')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
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
                                                    @endphp
                                                    <tr class="transition bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-red-50 dark:hover:bg-gray-800 rounded-2xl">

                                                        {{-- Waiter SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-200 font-mono">
                                                                {{ $r['waiter_sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                                SER# {{ $r['waiter_serial'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Waiter User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['waiter_user'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                {{ $r['waiter_program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                                                {{ $r['waiter_module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Wait Event --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-sm text-gray-700 dark:text-gray-300">
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
                                                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-200 font-mono">
                                                                {{ $r['blocker_sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                                SER# {{ $r['blocker_serial'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Blocker User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['blocker_user'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                {{ $r['blocker_program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                                                {{ $r['blocker_module'] ?? '' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                Wait: {{ $r['blocker_seconds_wait'] ?? 0 }}s
                                                                &middot; {{ $r['blocker_event'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Locked Object --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-sm font-mono text-gray-700 dark:text-gray-300">
                                                                {{ $r['locked_object'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
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
                                                                        title="Kill Blocker"
                                                                        message="Yakin kill SID {{ $r['blocker_sid'] }} ({{ $r['blocker_user'] ?? '-' }})?"
                                                                        confirmText="Ya, kill"
                                                                        cancelText="Batal">
                                                                        Kill Blocker
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
                                                                        message="Yakin kill SID {{ $r['waiter_sid'] }} ({{ $r['waiter_user'] ?? '-' }})?"
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
                                                        <td colspan="8" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                                            Tidak ada blocking rows terdeteksi.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    @endif

                                    {{-- ════ HEAVY ════ --}}
                                    @if ($tab === 'heavy')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
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
                                                    @endphp
                                                    <tr class="transition bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-amber-50 dark:hover:bg-gray-800 rounded-2xl">

                                                        {{-- Session SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-200 font-mono">
                                                                {{ $r['sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                                SER# {{ $r['serial'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                {{ $r['machine'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['username'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                {{ $r['program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                                                {{ $r['module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Wait Class / Event --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="font-medium text-gray-700 dark:text-gray-300">
                                                                {{ $r['wait_class'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                                {{ $r['event'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- Active seconds --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                                                {{ $r['seconds_active'] ?? 0 }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">seconds</div>
                                                        </td>

                                                        {{-- SQL Info --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-mono text-xs text-gray-700 dark:text-gray-300">
                                                                {{ $r['sql_id'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-pre-wrap max-w-xs truncate" title="{{ $r['sql_text'] ?? '' }}">
                                                                {{ $r['sql_text'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Stats --}}
                                                        <td class="px-6 py-4 space-y-1 align-top text-sm text-gray-700 dark:text-gray-300">
                                                            <div>Elapsed: <span class="font-semibold">{{ number_format((float) ($r['elapsed_sec'] ?? 0), 2) }}s</span></div>
                                                            <div>CPU: <span class="font-semibold">{{ number_format((float) ($r['cpu_sec'] ?? 0), 2) }}s</span></div>
                                                            <div>Buffers: <span class="font-semibold">{{ $r['buffer_gets'] ?? 0 }}</span></div>
                                                            <div>Disk Reads: <span class="font-semibold">{{ $r['disk_reads'] ?? 0 }}</span></div>
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
                                                        <td colspan="7" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                                            Tidak ada sesi ACTIVE yang melebihi ambang waktu.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    @endif

                                    {{-- ════ LONGOPS ════ --}}
                                    @if ($tab === 'longops')
                                        <table class="min-w-full text-base border-separate border-spacing-y-3">
                                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                                <tr class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
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
                                                    @endphp
                                                    <tr class="transition bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800 rounded-2xl">

                                                        {{-- Session SID --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-200 font-mono">
                                                                {{ $r['sid'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                                SER# {{ $r['serial'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                {{ $r['machine'] ?? '-' }}
                                                            </div>
                                                        </td>

                                                        {{-- User / Program --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-semibold text-brand dark:text-white">
                                                                {{ $r['username'] ?? '-' }}
                                                            </div>
                                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                {{ $r['program'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                                                {{ $r['module'] ?? '' }}
                                                            </div>
                                                        </td>

                                                        {{-- Opname / Target --}}
                                                        <td class="px-6 py-4 space-y-1 align-top">
                                                            <div class="font-medium text-gray-700 dark:text-gray-300">
                                                                {{ $r['opname'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 break-all">
                                                                {{ $r['target'] ?? '-' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $r['sofar'] ?? 0 }} / {{ $r['totalwork'] ?? 0 }}
                                                            </div>
                                                        </td>

                                                        {{-- Progress --}}
                                                        <td class="px-6 py-4 align-top">
                                                            <div class="text-xl font-bold text-gray-700 dark:text-gray-200">
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
                                                            <div class="text-xl font-bold text-gray-700 dark:text-gray-300">
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
                                                        <td colspan="7" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                                            Tidak ada long operations yang berjalan.
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