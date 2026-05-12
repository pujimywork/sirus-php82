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

    /*
     | Daftar Pasien Bulanan RI — read-only untuk casemix/admin/tu.
     | Filter bulan berdasarkan EXIT_DATE (tanggal pulang) — sesuai
     | reporting BPJS yang dihitung saat pasien discharge.
     | Aksi tersisa per row: Kirim iDRG, Berkas BPJS.
     */

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-ri-bulanan-toolbar'];

    public string $searchKeyword = '';
    public string $filterBulan = ''; // m/Y
    public string $filterStatus = '';
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM'
    public string $filterDokter = '';
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterBulan(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterStatus(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterKlaim(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterDokter(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedItemsPerPage(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->incrementVersion('daftar-ri-bulanan-toolbar');
        $this->resetPage();
    }

    public function openIdrg(string $rihdrNo): void
    {
        $this->dispatch('daftar-ri.idrg.open', riHdrNo: $rihdrNo);
    }

    public function openBerkasBpjs(int $rihdrNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rihdrNo);
    }

    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $d = Carbon::now()->startOfMonth();
        }
        return [$d, (clone $d)->endOfMonth()];
    }

    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select([
                'h.rihdr_no',
                DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display"),
                DB::raw("to_char(h.exit_date,'dd/mm/yyyy hh24:mi:ss') as exit_date_display"),
                'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'h.dr_id', 'd.dr_name',
                'h.klaim_id', 'k.klaim_desc', 'k.klaim_status',
                'h.ri_status', 'h.vno_sep',
                'r.bangsal_id', 'b.bangsal_name', 'h.room_id', 'h.bed_no',
            ])
            ->whereBetween('h.exit_date', [$start, $end]);

        if ($this->filterStatus !== '') {
            $query->where('h.ri_status', $this->filterStatus);
        }

        // Filter Klaim BPJS / UMUM
        if ($this->filterKlaim === 'BPJS') {
            $query->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            });
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $w->where('k.klaim_status', '!=', 'BPJS')->orWhereNull('k.klaim_status');
                })->where('h.klaim_id', '!=', 'JM');
            });
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rihdr_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rihdr_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $query->orderByDesc('h.exit_date')->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_rihdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT h.rihdr_no) as total_pasien'))
            ->whereBetween('h.exit_date', [$start, $end])
            ->groupBy('h.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Daftar Pasien Bulanan RI
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-700">
                List pasien rawat inap per bulan berdasarkan <strong>tanggal pulang</strong> (mm/yyyy)
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- FILTERS --}}
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="flex-1 min-w-[200px]">
                        <x-input-label value="Cari" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                            placeholder="No RI / No RM / Nama Pasien..." />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan (Tgl Pulang)" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="block w-full mt-1 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua</option>
                            <option value="I">Sedang Rawat</option>
                            <option value="L">Pulang/Selesai</option>
                            <option value="F">Batal</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter (DPJP)" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua</option>
                            @foreach ($this->dokterList as $dr)
                                <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }} ({{ $dr->total_pasien }})</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Halaman" />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-24">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    <div class="flex items-end">
                        <x-secondary-button type="button" wire:click="resetFilters">Reset</x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                wire:key="{{ $this->renderKey('daftar-ri-bulanan-toolbar') }}">
                <div class="overflow-x-auto rounded-t-2xl">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-3 py-2 text-left">Tgl Masuk</th>
                                <th class="px-3 py-2 text-left">Tgl Pulang</th>
                                <th class="px-3 py-2 text-left">No RI</th>
                                <th class="px-3 py-2 text-left">Pasien</th>
                                <th class="px-3 py-2 text-left">Bangsal</th>
                                <th class="px-3 py-2 text-left">Dokter</th>
                                <th class="px-3 py-2 text-left">Klaim</th>
                                <th class="px-3 py-2 text-center">Status</th>
                                <th class="px-3 py-2 text-center w-32">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100 dark:bg-gray-900 dark:divide-gray-800">
                            @forelse ($this->rows as $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $r->entry_date_display ?? '-' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $r->exit_date_display ?? '-' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $r->rihdr_no }}</td>
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->reg_name ?? '-' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $r->reg_no }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        {{ $r->bangsal_name ?? $r->bangsal_id ?? '-' }}{{ $r->room_id ? ' / ' . $r->room_id : '' }}{{ $r->bed_no ? ' / ' . $r->bed_no : '' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $r->dr_name ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($r->klaim_status === 'BPJS' || $r->klaim_id === 'JM')
                                            <x-badge variant="success">BPJS</x-badge>
                                        @else
                                            <x-badge variant="alternative">{{ $r->klaim_desc ?? 'UMUM' }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @switch($r->ri_status)
                                            @case('L')
                                                <x-badge variant="success">Pulang</x-badge>
                                                @break
                                            @case('F')
                                                <x-badge variant="danger">Batal</x-badge>
                                                @break
                                            @default
                                                <x-badge variant="warning">Rawat</x-badge>
                                        @endswitch
                                    </td>
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        {{-- iDRG — Admin, Casemix, Tu; BPJS + ri_status=Pulang --}}
                                        @hasanyrole('Admin|Casemix|Tu')
                                            @if (($r->klaim_status === 'BPJS' || $r->klaim_id === 'JM') && $r->ri_status === 'L')
                                                <x-secondary-button type="button"
                                                    wire:click="openIdrg('{{ $r->rihdr_no }}')" class="text-xs">iDRG</x-secondary-button>
                                            @endif
                                        @endhasanyrole
                                        @hasanyrole('Admin|Casemix|Tu')
                                            <x-primary-button type="button"
                                                wire:click="openBerkasBpjs({{ $r->rihdr_no }})" class="text-xs">Berkas</x-primary-button>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-12 text-sm text-center text-gray-400">
                                        Tidak ada pasien RI yang pulang di bulan ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Sibling action components --}}
            <livewire:pages::transaksi.ri.daftar-ri.idrg-ri-actions wire:key="idrg-ri-actions" />
            <livewire:pages::transaksi.ri.daftar-ri-bulanan.berkas-bpjs-ri-actions wire:key="berkas-bpjs-ri-actions" />

        </div>
    </div>
</div>
