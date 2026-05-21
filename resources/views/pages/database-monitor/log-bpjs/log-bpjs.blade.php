<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    // Filter — persist di query string supaya bisa di-share/bookmark
    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'svc', history: true)]
    public string $service = 'all'; // all | vclaim | antrian | aplicares | icare | sirs | idrg | other

    #[Url(as: 'st', history: true)]
    public string $statusFilter = 'all'; // all | 2xx | 4xx | 5xx | spesifik (200, 201, 404, ...)

    #[Url(as: 'from', history: true)]
    public string $dateFrom = '';

    #[Url(as: 'to', history: true)]
    public string $dateTo = '';

    #[Url(as: 'pp', history: true)]
    public int $perPage = 25;

    public function mount(): void
    {
        if (empty($this->dateFrom)) {
            $this->dateFrom = now()->startOfDay()->format('Y-m-d');
        }
        if (empty($this->dateTo)) {
            $this->dateTo = now()->endOfDay()->format('Y-m-d');
        }
    }

    public function updating($name): void
    {
        // Reset pagination kalau filter berubah
        if (in_array($name, ['search', 'service', 'statusFilter', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'service', 'statusFilter']);
        $this->dateFrom = now()->startOfDay()->format('Y-m-d');
        $this->dateTo = now()->endOfDay()->format('Y-m-d');
        $this->resetPage();
    }

    /**
     * Deteksi service dari URL.
     */
    public static function detectService(?string $url): string
    {
        if (empty($url)) return 'other';
        $u = strtolower($url);
        return match (true) {
            str_contains($u, 'vclaim-rest') => 'vclaim',
            str_contains($u, 'antreanrs') || str_contains($u, '/antrean/') => 'antrian',
            str_contains($u, 'aplicares') => 'aplicares',
            str_contains($u, 'icare') => 'icare',
            str_contains($u, 'sirsonline') || str_contains($u, 'sirs-online') => 'sirs',
            str_contains($u, 'inacbg') || str_contains($u, 'e-klaim') || str_contains($u, 'eklaim') => 'idrg',
            str_contains($u, 'satusehat') || str_contains($u, 'satu-sehat') || str_contains($u, 'dto.kemkes') => 'satusehat',
            default => 'other',
        };
    }

    /**
     * Klasifikasi badge berdasarkan HTTP code.
     */
    public static function statusVariant(?int $code): string
    {
        if (!$code) return 'gray';
        if ($code >= 200 && $code < 300) return $code === 200 ? 'success' : 'warning';
        if ($code >= 400 && $code < 500) return 'danger';
        if ($code >= 500) return 'danger';
        return 'gray';
    }

    public function with(): array
    {
        $q = DB::table('web_log_status')->select(
            'id',
            'code',
            'http_req',
            'http_payload',
            'response',
            // Oracle: kolom dibuat tanpa quoted DDL → tersimpan sebagai REQUESTTRANSFERTIME.
            // Jangan di-quote, biar Oracle fold ke uppercase otomatis.
            DB::raw('requesttransfertime as request_transfer_time'),
            DB::raw("to_char(date_ref, 'YYYY-MM-DD HH24:MI:SS') as date_ref_str"),
        );

        // Date range
        if (!empty($this->dateFrom)) {
            $q->whereRaw("trunc(date_ref) >= to_date(?, 'YYYY-MM-DD')", [$this->dateFrom]);
        }
        if (!empty($this->dateTo)) {
            $q->whereRaw("trunc(date_ref) <= to_date(?, 'YYYY-MM-DD')", [$this->dateTo]);
        }

        // Service filter — by URL pattern
        if ($this->service !== 'all') {
            $patterns = match ($this->service) {
                'vclaim' => ['vclaim-rest'],
                'antrian' => ['antreanrs', '/antrean/'],
                'aplicares' => ['aplicares'],
                'icare' => ['icare'],
                'sirs' => ['sirsonline', 'sirs-online'],
                'idrg' => ['inacbg', 'e-klaim', 'eklaim'],
                'other' => null,
                default => null,
            };
            if ($patterns) {
                $q->where(function ($sub) use ($patterns) {
                    foreach ($patterns as $p) {
                        $sub->orWhereRaw('LOWER(http_req) LIKE ?', ['%' . strtolower($p) . '%']);
                    }
                });
            } elseif ($this->service === 'other') {
                $q->where(function ($sub) {
                    foreach (['vclaim-rest', 'antreanrs', '/antrean/', 'aplicares', 'icare', 'sirsonline', 'sirs-online', 'inacbg', 'e-klaim', 'eklaim'] as $p) {
                        $sub->whereRaw('LOWER(http_req) NOT LIKE ?', ['%' . strtolower($p) . '%']);
                    }
                });
            }
        }

        // Status filter
        if ($this->statusFilter !== 'all') {
            switch ($this->statusFilter) {
                case '2xx':
                    $q->whereBetween('code', [200, 299]);
                    break;
                case '4xx':
                    $q->whereBetween('code', [400, 499]);
                    break;
                case '5xx':
                    $q->whereBetween('code', [500, 599]);
                    break;
                default:
                    if (is_numeric($this->statusFilter)) {
                        $q->where('code', (int) $this->statusFilter);
                    }
            }
        }

        // Free-text search di URL & response
        if (!empty($this->search)) {
            $kw = strtolower(trim($this->search));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('LOWER(http_req) LIKE ?', ['%' . $kw . '%'])
                    ->orWhereRaw('LOWER(response) LIKE ?', ['%' . $kw . '%']);
            });
        }

        // Rekap stats untuk badge counter
        $statsQ = clone $q;
        $statsRows = $statsQ->select(DB::raw('code, COUNT(*) as cnt'))
            ->reorder()
            ->groupBy('code')
            ->get();
        $stats = ['total' => 0, '2xx' => 0, '4xx' => 0, '5xx' => 0, 'other' => 0];
        foreach ($statsRows as $r) {
            $c = (int) $r->cnt;
            $stats['total'] += $c;
            $code = (int) $r->code;
            if ($code >= 200 && $code < 300) $stats['2xx'] += $c;
            elseif ($code >= 400 && $code < 500) $stats['4xx'] += $c;
            elseif ($code >= 500 && $code < 600) $stats['5xx'] += $c;
            else $stats['other'] += $c;
        }

        $rows = $q->orderByDesc('date_ref')->orderByDesc('id')->paginate($this->perPage);

        return [
            'rows' => $rows,
            'stats' => $stats,
            'serviceOptions' => [
                'all' => 'Semua Service',
                'vclaim' => 'V-Claim BPJS',
                'antrian' => 'Antrean BPJS',
                'aplicares' => 'Aplicares',
                'icare' => 'I-Care',
                'sirs' => 'SIRS Online',
                'idrg' => 'iDRG / INACBG (E-Klaim)',
                'satusehat' => 'SatuSehat (Kemenkes)',
                'other' => 'Lainnya',
            ],
            'statusOptions' => [
                'all' => 'Semua Status',
                '2xx' => '2xx Sukses',
                '4xx' => '4xx Client Error',
                '5xx' => '5xx Server Error',
                '200' => '200 OK',
                '201' => '201 Created/Warning',
                '400' => '400 Bad Request',
                '404' => '404 Not Found',
                '500' => '500 Server Error',
            ],
        ];
    }
};
?>

<div>
    <x-page-title
        title="Log BPJS / E-Klaim API"
        subtitle="Riwayat pemanggilan API eksternal (V-Claim, Antrean, Aplicares, I-Care, SIRS, iDRG/INACBG)" />

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-0 pb-6">

            {{-- TOOLBAR (sticky) --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.400ms="search" type="search"
                                class="block w-full pl-10"
                                placeholder="Cari URL endpoint / response (noKartu, vclaim, error...)" />
                        </div>
                    </div>

                    {{-- TANGGAL DARI --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dari Tgl" />
                        <x-text-input type="date" wire:model.live="dateFrom" class="block w-full mt-1 sm:w-40" />
                    </div>

                    {{-- TANGGAL SAMPAI --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sampai Tgl" />
                        <x-text-input type="date" wire:model.live="dateTo" class="block w-full mt-1 sm:w-40" />
                    </div>

                    {{-- SERVICE --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Service" />
                        <x-select-input wire:model.live="service" class="w-full mt-1 sm:w-44">
                            @foreach ($serviceOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="statusFilter" class="w-full mt-1 sm:w-40">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-secondary-button type="button" wire:click="resetFilters" title="Reset filter"
                            class="p-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="sr-only">Reset</span>
                        </x-secondary-button>

                        <div class="w-20">
                            <x-select-input wire:model.live="perPage" class="text-sm" title="Per halaman">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>

                {{-- REKAP STATS — chip row di bawah toolbar --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-gray-300">
                        Total <span class="font-bold">{{ number_format($stats['total']) }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                        2xx Sukses <span class="font-bold">{{ number_format($stats['2xx']) }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-amber-800 rounded-full bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                        4xx Client <span class="font-bold">{{ number_format($stats['4xx']) }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-300">
                        5xx Server <span class="font-bold">{{ number_format($stats['5xx']) }}</span>
                    </span>
                    @if ($stats['other'] > 0)
                        <span
                            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                            Lainnya <span class="font-bold">{{ number_format($stats['other']) }}</span>
                        </span>
                    @endif
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div
                    class="overflow-x-auto overflow-y-auto min-h-[calc(100dvh-360px)] max-h-[calc(100dvh-360px)] rounded-t-2xl">
                    <table class="w-full min-w-full text-sm border-separate border-spacing-y-2 table-fixed">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-4 py-3 w-[10%]">Service</th>
                                <th class="px-4 py-3 w-[78%]">Waktu / URL Endpoint</th>
                                <th class="px-4 py-3 w-[12%] text-center">Status</th>
                            </tr>
                        </thead>

                        @forelse ($rows as $r)
                            @php
                                $svc = $this::detectService($r->http_req);
                                $svcLabel = $serviceOptions[$svc] ?? ucfirst($svc);
                                $svcShort = match ($svc) {
                                    'vclaim' => 'V-CLAIM',
                                    'antrian' => 'ANTREAN',
                                    'aplicares' => 'APLICARES',
                                    'icare' => 'I-CARE',
                                    'sirs' => 'SIRS',
                                    'idrg' => 'iDRG',
                                    'satusehat' => 'SATUSEHAT',
                                    default => 'LAIN',
                                };
                                $variant = $this::statusVariant((int) $r->code);
                                $svcStyle = match ($svc) {
                                    'vclaim' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                    'antrian' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                    'aplicares' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
                                    'icare' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
                                    'sirs' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
                                    'idrg' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                    'satusehat' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                };
                                $rowBg = match (true) {
                                    ($r->code >= 500) => 'bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/20 border-l-4 border-red-400',
                                    ($r->code >= 400 && $r->code < 500) => 'bg-amber-50 dark:bg-amber-900/10 hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400',
                                    (!$r->code) => 'bg-gray-50 dark:bg-gray-900/30 hover:bg-gray-100 dark:hover:bg-gray-900/50 border-l-4 border-gray-300',
                                    default => 'bg-white dark:bg-gray-900 hover:bg-emerald-50 dark:hover:bg-gray-800',
                                };
                                $rtt = $r->request_transfer_time !== null ? (float) $r->request_transfer_time : null;
                                $decoded = json_decode($r->response ?? '', true);
                                $pretty = $decoded !== null
                                    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : ($r->response ?? '(kosong)');
                                $metaCode = $decoded['metadata']['code'] ?? ($decoded['metaData']['code'] ?? null);
                                $metaMsg = $decoded['metadata']['message'] ?? ($decoded['metaData']['message'] ?? null);
                                // Oracle CLOB bisa di-fetch sebagai resource/object — normalize ke string.
                                $payloadRaw = $r->http_payload ?? null;
                                if (is_object($payloadRaw) && method_exists($payloadRaw, 'load')) {
                                    $payloadRaw = $payloadRaw->load();
                                } elseif (is_resource($payloadRaw)) {
                                    $payloadRaw = stream_get_contents($payloadRaw);
                                }
                                $payloadDecoded = is_string($payloadRaw) && $payloadRaw !== '' ? json_decode($payloadRaw, true) : null;
                                $payloadPretty = $payloadDecoded !== null
                                    ? json_encode($payloadDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : ($payloadRaw ?: null);
                                // Parse URL untuk tampilan terstruktur
                                $parsedUrl = parse_url($r->http_req ?? '');
                                $urlHost = $parsedUrl['host'] ?? '';
                                $urlPath = $parsedUrl['path'] ?? '';
                                $urlQuery = $parsedUrl['query'] ?? '';
                                $urlScheme = $parsedUrl['scheme'] ?? 'https';
                                $codeLabel = match (true) {
                                    !$r->code => 'No Response',
                                    $r->code >= 200 && $r->code < 300 => $r->code === 200 ? 'OK' : 'Warning',
                                    $r->code >= 400 && $r->code < 500 => 'Client Error',
                                    $r->code >= 500 => 'Server Error',
                                    default => '-',
                                };
                            @endphp

                            {{-- Pakai <tbody> per row supaya Alpine state share antara row ringkas + row detail --}}
                            <tbody wire:key="log-{{ $r->id }}" x-data="{ expanded: false }">

                                {{-- ROW RINGKAS --}}
                                <tr class="transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 hover:shadow-md cursor-pointer {{ $rowBg }}"
                                    x-on:click="expanded = !expanded">

                                    {{-- SERVICE (kompak, vertikal) --}}
                                    <td class="px-3 py-2 align-middle">
                                        <div
                                            class="flex flex-col items-center justify-center px-2 py-1.5 rounded-lg {{ $svcStyle }}">
                                            <span
                                                class="text-[10px] font-bold leading-none uppercase tracking-wide text-center whitespace-nowrap">
                                                {{ $svcShort }}
                                            </span>
                                            <span
                                                class="text-[9px] font-medium mt-1 opacity-70">#{{ $r->id }}</span>
                                        </div>
                                    </td>

                                    {{-- WAKTU + URL (2 baris terpisah) --}}
                                    <td class="px-3 py-2 align-middle">
                                        {{-- Baris 1: tanggal · durasi · pesan metadata (preview) --}}
                                        <div
                                            class="flex flex-wrap items-center mb-1 text-[11px] text-gray-500 dark:text-gray-400 gap-x-3 gap-y-0.5">
                                            <span class="font-mono whitespace-nowrap">
                                                <svg class="inline w-3 h-3 mr-0.5 -mt-0.5" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                {{ $r->date_ref_str ?? '-' }}
                                            </span>
                                            @if ($rtt !== null)
                                                <span
                                                    class="font-mono tabular-nums whitespace-nowrap {{ $rtt > 1 ? 'text-amber-600 dark:text-amber-400 font-semibold' : '' }}">
                                                    <svg class="inline w-3 h-3 mr-0.5 -mt-0.5" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ number_format($rtt, 4) }} dtk
                                                </span>
                                            @endif
                                            @if ($metaMsg)
                                                <span x-show="!expanded"
                                                    class="truncate max-w-md text-gray-600 dark:text-gray-300">
                                                    <span class="font-semibold">Pesan:</span> {{ $metaMsg }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Baris 2: URL endpoint --}}
                                        <div class="font-mono text-xs text-gray-800 dark:text-gray-200 break-all leading-snug"
                                            :class="expanded ? '' : 'line-clamp-2'">
                                            <span
                                                class="text-gray-400 dark:text-gray-500">{{ $urlScheme }}://{{ $urlHost }}</span><span
                                                class="font-semibold text-gray-900 dark:text-gray-100">{{ $urlPath }}</span>@if ($urlQuery)<span
                                                    class="text-emerald-700 dark:text-emerald-400">?{{ $urlQuery }}</span>@endif
                                        </div>
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-3 py-2 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1">
                                            <x-badge :variant="$variant">
                                                <span class="text-base font-bold">{{ $r->code ?: '-' }}</span>
                                            </x-badge>
                                            <span
                                                class="text-[10px] text-gray-500 dark:text-gray-400">{{ $codeLabel }}</span>
                                            <svg class="w-4 h-4 text-gray-400 transition-transform"
                                                :class="expanded ? 'rotate-180' : ''" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </td>
                                </tr>

                                {{-- ROW DETAIL — full width, side-by-side Request | Response --}}
                                <tr x-show="expanded" x-collapse>
                                    <td colspan="3" class="px-3 pt-0 pb-3">
                                        <div
                                            class="grid grid-cols-1 gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl lg:grid-cols-2 dark:bg-gray-800/50 dark:border-gray-700">

                                            {{-- REQUEST PANEL --}}
                                            <div
                                                class="overflow-hidden bg-white border border-blue-200 rounded-lg dark:bg-gray-900 dark:border-blue-900/50">
                                                <div
                                                    class="flex items-center justify-between px-3 py-2 bg-blue-50 border-b border-blue-200 dark:bg-blue-900/20 dark:border-blue-900/50">
                                                    <span
                                                        class="text-xs font-bold tracking-wide text-blue-700 uppercase dark:text-blue-300">
                                                        ← Request
                                                    </span>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button"
                                                            x-on:click.stop="navigator.clipboard.writeText(@js($r->http_req ?? ''))"
                                                            class="text-[10px] font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                                                            title="Salin URL">
                                                            Copy URL
                                                        </button>
                                                        @if ($payloadPretty)
                                                            <button type="button"
                                                                x-on:click.stop="navigator.clipboard.writeText(@js($payloadPretty))"
                                                                class="text-[10px] font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                                                                title="Salin Request Body">
                                                                Copy Body
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    <div class="grid grid-cols-3 text-xs gap-x-2 gap-y-1">
                                                        <span
                                                            class="font-semibold text-gray-500 dark:text-gray-400">Host</span>
                                                        <span
                                                            class="col-span-2 font-mono text-gray-800 break-all dark:text-gray-200">{{ $urlHost ?: '-' }}</span>

                                                        <span
                                                            class="font-semibold text-gray-500 dark:text-gray-400">Path</span>
                                                        <span
                                                            class="col-span-2 font-mono text-gray-800 break-all dark:text-gray-200">{{ $urlPath ?: '-' }}</span>

                                                        @if ($urlQuery)
                                                            <span
                                                                class="font-semibold text-gray-500 dark:text-gray-400">Query</span>
                                                            <span
                                                                class="col-span-2 font-mono text-emerald-700 break-all dark:text-emerald-400">{{ $urlQuery }}</span>
                                                        @endif

                                                        <span
                                                            class="font-semibold text-gray-500 dark:text-gray-400">Waktu</span>
                                                        <span
                                                            class="col-span-2 font-mono text-gray-700 dark:text-gray-300">{{ $r->date_ref_str ?? '-' }}</span>

                                                        @if ($rtt !== null)
                                                            <span
                                                                class="font-semibold text-gray-500 dark:text-gray-400">Durasi</span>
                                                            <span
                                                                class="col-span-2 font-mono tabular-nums {{ $rtt > 1 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                                                {{ number_format($rtt, 4) }} detik
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Request Body --}}
                                                    @if ($payloadPretty)
                                                        <div class="pt-2 border-t border-blue-100 dark:border-blue-900/30">
                                                            <div
                                                                class="flex items-center justify-between mb-1 text-[10px] font-semibold tracking-wide text-blue-700 uppercase dark:text-blue-300">
                                                                <span>Body</span>
                                                                @if ($payloadDecoded !== null)
                                                                    <span
                                                                        class="px-1.5 py-0.5 text-[9px] font-medium tracking-normal normal-case bg-blue-100 text-blue-700 rounded dark:bg-blue-900/40 dark:text-blue-300">
                                                                        JSON
                                                                    </span>
                                                                @else
                                                                    <span
                                                                        class="px-1.5 py-0.5 text-[9px] font-medium tracking-normal normal-case bg-gray-100 text-gray-600 rounded dark:bg-gray-700 dark:text-gray-300">
                                                                        RAW
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <pre
                                                                class="p-3 overflow-x-auto text-[11px] leading-relaxed text-gray-100 bg-gray-900 rounded font-mono max-h-80">{{ $payloadPretty }}</pre>
                                                        </div>
                                                    @else
                                                        <div
                                                            class="pt-2 mt-1 border-t border-blue-100 text-[11px] italic text-gray-400 dark:border-blue-900/30 dark:text-gray-500">
                                                            (tidak ada request body — mungkin GET request, atau log lama sebelum
                                                            fitur ini)
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- RESPONSE PANEL --}}
                                            <div
                                                class="overflow-hidden bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:border-gray-700
                                                {{ $variant === 'danger' ? 'border-red-200 dark:border-red-900/50' : ($variant === 'warning' ? 'border-amber-200 dark:border-amber-900/50' : ($variant === 'success' ? 'border-emerald-200 dark:border-emerald-900/50' : '')) }}">
                                                <div
                                                    class="flex items-center justify-between px-3 py-2 border-b
                                                    {{ $variant === 'danger' ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-900/50' : ($variant === 'warning' ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-900/50' : ($variant === 'success' ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-900/50' : 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700')) }}">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="text-xs font-bold tracking-wide uppercase
                                                            {{ $variant === 'danger' ? 'text-red-700 dark:text-red-300' : ($variant === 'warning' ? 'text-amber-700 dark:text-amber-300' : ($variant === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300')) }}">
                                                            Response →
                                                        </span>
                                                        <x-badge :variant="$variant">
                                                            {{ $r->code ?: '-' }} {{ $codeLabel }}
                                                        </x-badge>
                                                    </div>
                                                    <button type="button"
                                                        x-on:click.stop="navigator.clipboard.writeText(@js($pretty))"
                                                        class="text-[10px] font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                                        title="Salin JSON">
                                                        Copy JSON
                                                    </button>
                                                </div>
                                                <div class="p-3 space-y-2">
                                                    @if ($metaMsg)
                                                        <div
                                                            class="flex items-start gap-2 px-2 py-1.5 text-xs rounded
                                                            {{ $variant === 'danger' ? 'bg-red-50 dark:bg-red-900/10' : ($variant === 'warning' ? 'bg-amber-50 dark:bg-amber-900/10' : 'bg-gray-50 dark:bg-gray-800/50') }}">
                                                            <span
                                                                class="font-semibold text-gray-600 dark:text-gray-400 shrink-0">Metadata
                                                                {{ $metaCode ?: '-' }}:</span>
                                                            <span
                                                                class="text-gray-700 dark:text-gray-300">{{ $metaMsg }}</span>
                                                        </div>
                                                    @endif
                                                    <pre
                                                        class="p-3 overflow-x-auto text-[11px] leading-relaxed text-gray-100 bg-gray-900 rounded font-mono max-h-80">{{ $pretty }}</pre>
                                                </div>
                                            </div>

                                        </div>
                                    </td>
                                </tr>

                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="3"
                                        class="px-6 py-16 text-center text-gray-700 dark:text-gray-400">
                                        Tidak ada log untuk filter yang dipilih.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse

                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $rows->links() }}
                </div>

            </div>

        </div>
    </div>
</div>
