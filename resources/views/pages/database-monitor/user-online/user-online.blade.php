<?php
// resources/views/pages/database-monitor/user-online/user-online.blade.php
// User Online — list user yang sedang aktif login (last_seen_at < X menit lalu).
// last_seen_at di-update oleh middleware TrackUserActivity (throttled 1-menit per user).

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\AppMenu;

new class extends Component {
    // Threshold "online" — user dianggap aktif kalau last_seen_at dalam X menit terakhir.
    public int $thresholdMinutes = 5;

    // Search nama / email.
    public string $searchKeyword = '';

    public array $rows = [];
    public int $totalOnline = 0;
    public string $lastRefreshed = '';

    public function mount(): void
    {
        $this->refresh();
    }

    public function updatedThresholdMinutes(): void
    {
        $this->thresholdMinutes = max(1, min(60, (int) $this->thresholdMinutes));
        $this->refresh();
    }

    public function updatedSearchKeyword(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $minutes = max(1, $this->thresholdMinutes);
        $cutoffTime = Carbon::now()->subMinutes($minutes);
        $keyword = trim($this->searchKeyword);

        // Step 1: query users yang last_seen_at-nya dalam threshold.
        $usersQuery = DB::table('users as u')
            ->select(
                'u.id',
                'u.myuser_code',
                'u.myuser_name',
                'u.email',
                'u.emp_id',
                DB::raw("TO_CHAR(u.last_seen_at, 'dd/mm/yyyy HH24:MI:SS') as last_seen_at_fmt"),
                'u.last_seen_at',
                'u.last_seen_route',
            )
            ->where('u.last_seen_at', '>=', $cutoffTime);

        if ($keyword !== '') {
            $keywordLower = strtolower($keyword);
            $usersQuery->where(function ($subQuery) use ($keywordLower) {
                $subQuery->whereRaw('LOWER(u.myuser_name) LIKE ?', ["%{$keywordLower}%"])
                    ->orWhereRaw('LOWER(u.myuser_code) LIKE ?', ["%{$keywordLower}%"])
                    ->orWhereRaw('LOWER(u.email) LIKE ?', ["%{$keywordLower}%"]);
            });
        }

        $userRows = $usersQuery->orderByDesc('u.last_seen_at')->get();
        $userIds = $userRows->pluck('id')->all();

        // Step 2: batch lookup roles untuk semua user_id, group di PHP.
        // Pisah query ini supaya tidak butuh LISTAGG di Oracle (versi lama bisa belum support).
        $rolesByUserId = [];
        if (!empty($userIds)) {
            $userModelClass = \App\Models\User::class;
            $roleRows = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereIn('mhr.model_id', $userIds)
                ->where('mhr.model_type', $userModelClass)
                ->orderBy('r.name')
                ->select('mhr.model_id', 'r.name')
                ->get();
            foreach ($roleRows as $roleRow) {
                $rolesByUserId[$roleRow->model_id][] = $roleRow->name;
            }
        }

        // Map route name → label human-readable dari AppMenu. Sumber tunggal label
        // menu = AppMenu::all() (group + title). Kalau route name tidak terdaftar di
        // AppMenu (mis. profile.edit, dashboard), fallback ke route name raw.
        $routeLabelMap = collect(AppMenu::all())->mapWithKeys(fn ($m) => [
            $m['route'] => ($m['group'] ?? '') . ' › ' . ($m['title'] ?? $m['route']),
        ])->all();

        $this->rows = $userRows->map(function ($row) use ($rolesByUserId, $routeLabelMap) {
            $idleSeconds = $row->last_seen_at
                ? Carbon::parse($row->last_seen_at)->diffInSeconds(Carbon::now())
                : 0;
            $userRoles = $rolesByUserId[$row->id] ?? [];
            $routeName = $row->last_seen_route;
            $sedangDi = $routeName ? ($routeLabelMap[$routeName] ?? $routeName) : '-';
            return [
                'id' => $row->id,
                'kode' => $row->myuser_code,
                'name' => $row->myuser_name,
                'email' => $row->email,
                'emp_id' => $row->emp_id,
                'roles' => !empty($userRoles) ? implode(', ', $userRoles) : '-',
                'last_seen_at' => $row->last_seen_at_fmt,
                'last_seen_route' => $routeName ?: '-',
                'sedang_di' => $sedangDi,
                'idle_sec' => $idleSeconds,
            ];
        })->toArray();

        $this->totalOnline = count($this->rows);
        $this->lastRefreshed = Carbon::now()->format('H:i:s');
    }
};
?>

<div>

    <x-page-title title="User Online"
        subtitle="Daftar user yang sedang aktif login (last_seen_at dalam threshold menit terakhir)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter Threshold + Status badge --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari User" class="sr-only" />
                        <x-text-input id="searchKeyword" type="search"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama / kode / email..." class="block w-full" />
                    </div>

                    {{-- RIGHT: threshold + status + refresh --}}
                    <div class="flex items-center justify-end gap-3">
                        <span
                            class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 font-semibold text-sm">
                            ● {{ $totalOnline }} online
                        </span>
                        <span class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">
                            refreshed {{ $lastRefreshed }}
                        </span>
                        <div class="w-32">
                            <x-input-label for="thresholdMinutes" value="Threshold (menit)" class="sr-only" />
                            <x-text-input id="thresholdMinutes" type="number" min="1" max="60"
                                wire:model.live.debounce.500ms="thresholdMinutes" />
                        </div>
                        <x-secondary-button type="button" wire:click="refresh" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="refresh">↻ Refresh</span>
                            <span wire:loading wire:target="refresh"><x-loading />...</span>
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- Cara Pakai (collapsible) --}}
            <details class="mt-3 bg-surface-soft border border-hairline rounded-lg dark:bg-gray-800/50 dark:border-gray-700 group">
                <summary class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-body cursor-pointer select-none dark:text-gray-200">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    Cara Pakai
                    <span class="text-sm font-normal text-muted dark:text-gray-400">— monitor user yang sedang aktif</span>
                </summary>
                <div class="px-4 pt-1 pb-3 space-y-2 text-sm leading-relaxed text-muted dark:text-gray-300">
                    <p>Tabel menampilkan user yang <code>last_seen_at</code>-nya dalam <b>Threshold</b> menit terakhir.</p>
                    <ol class="space-y-1 list-decimal list-inside">
                        <li><b>Last Seen</b>: waktu request terakhir user (di-update tiap 1 menit lewat middleware <code>TrackUserActivity</code>).</li>
                        <li><b>Idle</b>: detik sejak last_seen_at — yang <i>baru saja</i> aktif idle-nya kecil.</li>
                        <li><b>Threshold</b>: ubah angka di toolbar atas untuk perluas/persempit window (1-60 menit).</li>
                        <li>Auto-refresh tiap 10 detik (Livewire <code>wire:poll</code>).</li>
                    </ol>
                    <p class="text-rose-700 dark:text-rose-300"><b>Catatan:</b> Lihat sesi DB aktif (bukan user app) di <a href="{{ route('database-monitor.monitoring-dashboard') }}" class="underline">Monitoring Dashboard</a>.</p>
                </div>
            </details>

            {{-- Auto-refresh polling (10s) --}}
            <div wire:poll.10s="refresh"></div>

            {{-- TABLE WRAPPER: card (flex-fill, scroll internal) --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">KODE</th>
                                <th class="px-4 py-3 font-semibold">NAMA</th>
                                <th class="px-4 py-3 font-semibold">EMAIL</th>
                                <th class="px-4 py-3 font-semibold">EMP ID</th>
                                <th class="px-4 py-3 font-semibold">ROLES</th>
                                <th class="px-4 py-3 font-semibold">SEDANG DI</th>
                                <th class="px-4 py-3 font-semibold">LAST SEEN</th>
                                <th class="px-4 py-3 font-semibold text-right">IDLE</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($rows as $r)
                                <tr wire:key="user-online-{{ $r['id'] }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono">{{ $r['kode'] ?? '-' }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $r['name'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $r['email'] ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $r['emp_id'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $r['roles'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm">{{ $r['sedang_di'] }}</div>
                                        <div class="text-xs font-mono text-muted dark:text-gray-400">{{ $r['last_seen_route'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $r['last_seen_at'] }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @php $idle = (int) $r['idle_sec']; @endphp
                                        @if ($idle <= 60)
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ $idle }}s</span>
                                        @elseif ($idle <= 180)
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ $idle }}s</span>
                                        @else
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">{{ $idle }}s</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                        Tidak ada user online dalam {{ $thresholdMinutes }} menit terakhir.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- FOOTER STICKY: ringkasan --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 text-sm text-muted bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
                    Total {{ $totalOnline }} user aktif dalam {{ $thresholdMinutes }} menit terakhir.
                </div>
            </div>

        </div>
    </div>
</div>
