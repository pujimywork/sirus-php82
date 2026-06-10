<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public string $filterGroup   = '';   // gra_id filter
    public string $filterKas     = '';   // '1' = kas only

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedFilterGroup(): void   { $this->resetPage(); }
    public function updatedFilterKas(): void     { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->dispatch('master.akun.openCreate');
    }

    public function openEdit(string $accId): void
    {
        $this->dispatch('master.akun.openEdit', accId: $accId);
    }

    public function requestDelete(string $accId): void
    {
        $this->dispatch('master.akun.requestDelete', accId: $accId);
    }

    public function toggleActive(string $accId): void
    {
        $cur = (string) DB::table('acmst_accounts')->where('acc_id', $accId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('acmst_accounts')->where('acc_id', $accId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status akun → ' . ($next === '1' ? 'Aktif' : 'Non-aktif'));
        $this->resetPage();
    }

    public function toggleKas(string $accId): void
    {
        $cur = (string) DB::table('acmst_accounts')->where('acc_id', $accId)->value('kas_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('acmst_accounts')->where('acc_id', $accId)->update(['kas_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Tipe akun → ' . ($next === '1' ? 'Akun Kas' : 'Bukan Kas'));
        $this->resetPage();
    }

    #[On('master.akun.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function groupOptions()
    {
        return DB::table('tkacc_gr_accountses')
            ->select('gra_id', 'gra_desc')
            ->orderBy('gra_id')
            ->get();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('acmst_accounts as a')
            ->leftJoin('tkacc_gr_accountses as g', 'g.gra_id', '=', 'a.gra_id')
            ->select('a.acc_id', 'a.acc_name', 'a.active_status', 'a.kas_status',
                'a.gra_id', 'g.gra_desc', 'a.acc_dk_status')
            ->orderBy('a.acc_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(a.acc_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(g.gra_desc) LIKE ?', ["%{$kw}%"]);
            });
        }
        if ($this->filterGroup !== '') {
            $q->where('a.gra_id', $this->filterGroup);
        }
        if ($this->filterKas === '1') {
            $q->where('a.kas_status', '1');
        } elseif ($this->filterKas === '0') {
            $q->where('a.kas_status', '0');
        }

        return $q->paginate($this->itemsPerPage);
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterGroup', 'filterKas']);
        $this->resetPage();
    }
};
?>

<div>
    <x-page-title
        title="Master Akun"
        subtitle="Daftar pos akun untuk pencatatan transaksi keuangan (Kas, Piutang, Pendapatan, Beban, dsb). Akun bertanda Kas otomatis muncul sebagai pilihan di kasir &amp; metode cara bayar." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                @php
                    $filterActive = trim($searchKeyword) !== '' || $filterGroup !== '' || $filterKas !== '';
                @endphp

                <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">

                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-72">
                            <x-input-label for="searchKeyword" value="Cari" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Kode / nama akun / group..."
                                class="block w-full" />
                        </div>

                        <div class="w-full sm:w-60">
                            <x-input-label for="filterGroup" value="Group Akun" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-select-input id="filterGroup" wire:model.live="filterGroup" class="block w-full">
                                <option value="">— Semua Group —</option>
                                @foreach ($this->groupOptions as $g)
                                    <option value="{{ $g->gra_id }}">{{ $g->gra_id }} — {{ $g->gra_desc }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        <div class="w-full sm:w-44">
                            <x-input-label for="filterKas" value="Tipe Akun" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-select-input id="filterKas" wire:model.live="filterKas" class="block w-full">
                                <option value="">— Semua Tipe —</option>
                                <option value="1">Akun Kas</option>
                                <option value="0">Bukan Kas</option>
                            </x-select-input>
                        </div>

                        @if ($filterActive)
                            <div>
                                <x-secondary-button type="button" wire:click="resetFilters" class="px-3 py-2 text-xs">
                                    Reset Filter
                                </x-secondary-button>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-end justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per Halaman" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage" class="block w-full">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Akun
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="w-28">ID</th>
                                <th class="w-80">Deskripsi / Group</th>
                                <th class="ds-c w-16">D/K</th>
                                <th class="w-32">Kas</th>
                                <th class="w-32">Status</th>
                                <th class="ds-c w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="akun-{{ $row->acc_id }}">
                                    <td class="ds-td-token align-middle">{{ $row->acc_id }}</td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="font-medium text-gray-900 truncate dark:text-white">
                                            {{ $row->acc_name }}
                                        </div>
                                        @if (!empty($row->gra_id) || !empty($row->gra_desc))
                                            <div class="mt-0.5 text-[11px] truncate" style="color:var(--muted)">
                                                {{ $row->gra_id }}
                                                @if (!empty($row->gra_desc))
                                                    — {{ $row->gra_desc }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="ds-c px-6 py-4 align-middle">
                                        @if ((string) $row->acc_dk_status === 'D')
                                            <span class="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-700">D</span>
                                        @elseif ((string) $row->acc_dk_status === 'K')
                                            <span class="px-2 py-0.5 text-xs rounded bg-purple-100 text-purple-700">K</span>
                                        @else
                                            <span class="text-xs" style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <x-toggle :current="(string) $row->kas_status"
                                            trueValue="1" falseValue="0" size="md"
                                            wireClick="toggleKas('{{ $row->acc_id }}')">
                                            {{ (string) $row->kas_status === '1' ? 'Kas' : 'Non-Kas' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <x-toggle :current="(string) $row->active_status"
                                            trueValue="1" falseValue="0" size="md"
                                            wireClick="toggleActive('{{ $row->acc_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'Aktif' : 'Non-aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="ds-c px-6 py-4 align-middle">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->acc_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->acc_id . '\')'"
                                                title="Hapus Akun"
                                                message="Yakin hapus akun {{ $row->acc_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center" style="color:var(--muted)">
                                        Data akun tidak ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-akuntansi.master-akun.master-akun-actions wire:key="master-akun-actions" />
        </div>
    </div>
</div>
