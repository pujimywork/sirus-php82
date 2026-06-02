<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-kasir-ri-toolbar'];

    public string $searchKeyword = '';
    public string $filterStatus = 'I';
    public string $filterBangsal = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kasir-ri-toolbar');
    }
    public function updatedFilterBangsal(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kasir-ri-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-kasir-ri-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterBangsal']);
        $this->filterStatus = 'I';
        $this->incrementVersion('daftar-kasir-ri-toolbar');
        $this->resetPage();
    }

    public function openAdministrasiPasien(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $riHdrNo);
    }

    #[On('refresh-after-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-kasir-ri-toolbar');
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('rsview_rihdrs as rv')
            ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'rv.klaim_id')
            ->select([
                'rv.rihdr_no',
                DB::raw("to_char(rv.entry_date,'dd/mm/yyyy hh24:mi') as entry_date_display"),
                DB::raw("to_char(rv.entry_date,'yyyymmddhh24miss') as entry_date_sort"),
                'rv.reg_no',
                'rv.reg_name',
                'rv.sex',
                'rv.address',
                DB::raw("to_char(rv.birth_date,'dd/mm/yyyy') as birth_date"),
                DB::raw("
                    CASE WHEN rv.birth_date IS NOT NULL THEN
                        trunc(months_between(sysdate, rv.birth_date) / 12) || ' Thn, ' ||
                        trunc(mod(months_between(sysdate, rv.birth_date), 12)) || ' Bln, ' ||
                        trunc(sysdate - add_months(rv.birth_date, trunc(months_between(sysdate, rv.birth_date)))) || ' Hr'
                    ELSE '-' END AS thn_umur
                "),
                'rv.dr_id',
                'rv.dr_name',
                'rv.klaim_id',
                'kt.klaim_status',
                'rv.ri_status',
                'rv.vno_sep',
                'rv.bangsal_id',
                'rv.bangsal_name',
                'rv.room_id',
                'rv.room_name',
                'rv.bed_no',
                'rv.datadaftarri_json',
            ])
            ->orderBy('entry_date_sort', 'desc');

        if ($this->filterStatus !== '') {
            $query->where(DB::raw("NVL(rv.ri_status,'I')"), $this->filterStatus);
        }
        if ($this->filterBangsal !== '') {
            $query->where('rv.bangsal_id', $this->filterBangsal);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('rv.rihdr_no', 'like', "%{$search}%")->orWhere('rv.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(rv.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.vno_sep)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.dr_name)'), 'like', "%{$kw}%");
            });
        }

        $paginator = $query->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarri_json ?? '{}', true) ?? [];
            $row->no_sep = $json['sep']['noSep'] ?? ($row->vno_sep ?? null);
            $row->admin_user = isset($json['AdministrasiRI']) ? $json['AdministrasiRI']['userLog'] ?? '✔' : '-';
            $row->umur_format = $row->thn_umur ?? '-';
            $row->leveling_dokter_list = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];

            $statusMap = ['I' => 'Dirawat', 'P' => 'Pulang'];
            $statusVariant = ['I' => 'brand', 'P' => 'success'];
            $row->status_text = $statusMap[$row->ri_status] ?? 'RI';
            $row->status_variant = $statusVariant[$row->ri_status] ?? 'gray';

            $row->klaim_label = match ($row->klaim_id ?? '') {
                'UM' => 'UMUM',
                'JM' => 'BPJS',
                'KR' => 'Kronis',
                default => 'Asuransi Lain',
            };
            $row->klaim_variant = match ($row->klaim_id ?? '') {
                'UM' => 'success',
                'JM' => 'brand',
                'KR' => 'warning',
                default => 'alternative',
            };

            return $row;
        });

        return $paginator;
    }

    #[Computed]
    public function bangsalList()
    {
        return DB::table('rsview_rihdrs')
            ->select('bangsal_id', DB::raw('MAX(bangsal_name) as bangsal_name'))
            ->where(DB::raw("NVL(ri_status,'I')"), 'I')
            ->whereNotNull('bangsal_id')
            ->groupBy('bangsal_id')
            ->orderBy('bangsal_name')
            ->get();
    }
};
?>

<div class="min-h-screen bg-white dark:bg-gray-800">

    <x-page-title
        title="Daftar Pasien RI — Kasir"
        subtitle="Administrasi &amp; Pembayaran per pasien rawat inap" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('daftar-kasir-ri-toolbar', []) }}">

                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" type="text"
                                placeholder="Cari No RM, nama pasien, no SEP, dokter..." class="block w-full pl-10" />
                        </div>
                    </div>

                    <div class="w-full sm:w-40">
                        <x-input-label value="Status" />
                        <select wire:model.live="filterStatus"
                            class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                            <option value="">Semua</option>
                            <option value="I">Dirawat</option>
                            <option value="P">Pulang</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-56">
                        <x-input-label value="Bangsal" />
                        <select wire:model.live="filterBangsal"
                            class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                            <option value="">Semua Bangsal</option>
                            @foreach ($this->bangsalList as $b)
                                <option value="{{ $b->bangsal_id }}">{{ $b->bangsal_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-28">
                        <x-input-label value="Per Hal." />
                        <select wire:model.live="itemsPerPage"
                            class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div>
                        <x-secondary-button wire:click="resetFilters" class="text-xs">Reset</x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 overflow-hidden bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-700 rounded-2xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Kamar / Dokter</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Administrasi</th>
                                <th class="px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($this->rows as $row)
                                <tr wire:key="daftar-kasir-ri-{{ $row->reg_no ?? $loop->index }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    {{-- PASIEN --}}
                                    <td class="px-6 py-4 align-top">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $row->reg_name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            No RM: <span class="font-medium">{{ $row->reg_no }}</span>
                                            · {{ $row->sex === 'L' ? 'Laki-laki' : 'Perempuan' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->umur_format }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Masuk: {{ $row->entry_date_display }}
                                        </div>
                                    </td>

                                    {{-- KAMAR / DOKTER --}}
                                    <td class="px-6 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                            {{ $row->bangsal_name ?? '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->room_name ?? '-' }} · Bed {{ $row->bed_no ?? '-' }}
                                        </div>

                                        @if (!empty($row->leveling_dokter_list))
                                            <div class="pt-1 space-y-0.5">
                                                <div class="text-xs text-gray-400">DPJP:</div>
                                                @foreach ($row->leveling_dokter_list as $ld)
                                                    @if (!empty($ld['drName']))
                                                        <div class="text-xs text-gray-700 dark:text-gray-200">
                                                            {{ $ld['drName'] }}
                                                            @if (!empty($ld['levelDokter']))
                                                                <span class="text-[10px] text-gray-500">
                                                                    ({{ $ld['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $ld['levelDokter'] }})
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="pt-1 text-[11px] italic text-gray-500 dark:text-gray-400">
                                            Penerima: {{ $row->dr_name ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-6 py-4 space-y-2 align-top">
                                        <x-badge :variant="$row->status_variant">{{ $row->status_text }}</x-badge>
                                        <div>
                                            <x-badge :variant="$row->klaim_variant">{{ $row->klaim_label }}</x-badge>
                                        </div>
                                        @if ($row->no_sep)
                                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row->no_sep }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ADMINISTRASI BADGE --}}
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            <span class="font-medium {{ $row->admin_user !== '-' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $row->admin_user }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-6 py-4 text-center align-top">
                                        @hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
                                            <x-secondary-button
                                                wire:click="openAdministrasiPasien('{{ $row->rihdr_no }}')"
                                                class="text-xs whitespace-nowrap justify-center !bg-purple-50 hover:!bg-purple-100 dark:!bg-purple-900/20">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                </svg>
                                                Administrasi
                                            </x-secondary-button>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Tidak ada data pasien RI</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>

        {{-- Modal Administrasi RI (shared dengan Daftar RI utama) --}}
        <livewire:pages::transaksi.ri.emr-ri.log-aktivitas.log-aktivitas-ri wire:key="log-aktivitas-ri" />
        <livewire:pages::transaksi.ri.administrasi-ri.administrasi-ri wire:key="daftar-kasir-ri-administrasi" />

    </div>
</div>
