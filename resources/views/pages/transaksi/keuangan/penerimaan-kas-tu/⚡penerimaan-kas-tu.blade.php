<?php
// resources/views/pages/transaksi/keuangan/penerimaan-kas-tu/⚡penerimaan-kas-tu.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    public string $searchKeyword = '';
    public int $itemsPerPage = 10;
    public string $filterStatus = '';
    public string $filterBulan = '';

    // ── Form Input ──
    public bool $showForm = false;
    public ?string $editNo = null;
    public ?string $accId = null;
    public ?string $accName = null;
    public ?string $accIdKas = null;
    public ?string $accNameKas = null;
    public ?string $tucashkDate = null;
    public ?string $tucashkDesc = null;
    public ?int $tucashkNominal = null;

    public function mount(): void
    {
        $this->registerAreas(['form', 'list']);
        $this->filterBulan = Carbon::now()->format('Y-m');
    }

    /* ===============================
     | QUERY
     =============================== */
    public function baseQuery()
    {
        $query = DB::table('rstxn_tucashds as a')
            ->leftJoin('acmst_accounts as b', 'a.acc_id', '=', 'b.acc_id')
            ->leftJoin('acmst_accounts as c', 'a.acc_id_kas', '=', 'c.acc_id')
            ->leftJoin('immst_employers as d', 'a.emp_id', '=', 'd.emp_id')
            ->select(
                'a.tucashk_no', 'a.tucashk_date', 'a.tucashk_desc', 'a.tucashk_nominal',
                'a.acc_id', 'b.acc_name', 'a.acc_id_kas', 'c.acc_name as acc_name_kas',
                'a.emp_id', 'd.emp_name', 'a.shift', 'a.tucashk_status',
                'a.g_status', 'a.rj_rj_ugd_status', 'a.g_bulan'
            )
            ->orderByDesc('a.tucashk_date')
            ->orderByDesc('a.tucashk_no');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(a.tucashk_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(b.acc_name) LIKE ?', ["%{$upper}%"])
                  ->orWhere('a.tucashk_no', 'like', "%{$this->searchKeyword}%");
            });
        }

        if ($this->filterStatus !== '') {
            $query->where('a.tucashk_status', $this->filterStatus);
        }

        if ($this->filterBulan !== '') {
            $query->where('a.g_bulan', $this->filterBulan);
        }

        return $query;
    }

    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* ===============================
     | FORM: OPEN / CLOSE
     =============================== */
    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->tucashkDate = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('form');
    }

    public function openEdit(string $tucashkNo): void
    {
        $this->resetForm();

        $row = DB::table('rstxn_tucashds')->where('tucashk_no', $tucashkNo)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        if ($row->tucashk_status === 'L') {
            $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah diposting, tidak bisa diedit.');
            return;
        }

        $this->editNo = (string) $row->tucashk_no;
        $this->accId = $row->acc_id;
        $this->tucashkDate = $row->tucashk_date ? Carbon::parse($row->tucashk_date)->format('d/m/Y') : null;
        $this->tucashkDesc = $row->tucashk_desc;
        $this->tucashkNominal = (int) ($row->tucashk_nominal ?? 0);
        $this->showForm = true;
        $this->incrementVersion('form');
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editNo', 'accId', 'accName', 'accIdKas', 'accNameKas', 'tucashkDate', 'tucashkDesc', 'tucashkNominal']);
        $this->resetValidation();
    }

    /* ===============================
     | LOV LISTENERS
     =============================== */
    #[On('lov.selected.akun-ci-tu')]
    public function onAkunCISelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
    }

    #[On('lov.selected.akun-kas-tu')]
    public function onAkunKasSelected(string $target, ?array $payload): void
    {
        $this->accIdKas = $payload['acc_id'] ?? null;
        $this->accNameKas = $payload['acc_name'] ?? null;
    }

    /* ===============================
     | SIMPAN (POST TRANSAKSI)
     =============================== */
    public function simpan(): void
    {
        $this->validate([
            'accId' => 'required|string',
            'accIdKas' => 'required|string',
            'tucashkDate' => 'required|date_format:d/m/Y',
            'tucashkDesc' => 'required|string|min:3|max:100',
            'tucashkNominal' => 'required|integer|min:1',
        ], [
            'accId.required' => 'Akun penerimaan wajib dipilih.',
            'accIdKas.required' => 'Akun kas wajib dipilih.',
            'tucashkDate.required' => 'Tanggal wajib diisi.',
            'tucashkDate.date_format' => 'Format tanggal harus dd/mm/yyyy.',
            'tucashkDesc.required' => 'Keterangan wajib diisi.',
            'tucashkDesc.min' => 'Keterangan minimal 3 karakter.',
            'tucashkNominal.required' => 'Nominal wajib diisi.',
            'tucashkNominal.min' => 'Nominal minimal Rp 1.',
        ]);

        // Cek akun kas user
        $cekakunkas = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->where('b.co', '1')
            ->whereIn('a.acc_id', function ($q) {
                $q->select('acc_id')->from('user_kas')->where('user_id', auth()->id());
            })
            ->count();

        if ($cekakunkas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas Anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        // Ambil shift saat ini
        $now = Carbon::now();
        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $shift = (string) ($findShift?->shift ?? 1);

        $dateFormatted = Carbon::createFromFormat('d/m/Y', $this->tucashkDate);

        try {
            DB::transaction(function () use ($empId, $shift, $dateFormatted) {
                if ($this->editNo) {
                    // UPDATE
                    DB::table('rstxn_tucashds')
                        ->where('tucashk_no', $this->editNo)
                        ->update([
                            'tucashk_date' => DB::raw("to_date('{$this->tucashkDate}','dd/mm/yyyy')"),
                            'tucashk_desc' => $this->tucashkDesc,
                            'tucashk_nominal' => $this->tucashkNominal,
                            'acc_id' => $this->accId,
                            'acc_id_kas' => $this->accIdKas,
                            'emp_id' => $empId,
                            'shift' => $shift,
                            'tucashk_status' => 'L',
                            'g_bulan' => $dateFormatted->format('Y-m'),
                        ]);
                } else {
                    // INSERT
                    $nextNo = (int) DB::table('rstxn_tucashds')->max('tucashk_no') + 1;

                    DB::table('rstxn_tucashds')->insert([
                        'tucashk_no' => $nextNo,
                        'tucashk_date' => DB::raw("to_date('{$this->tucashkDate}','dd/mm/yyyy')"),
                        'tucashk_desc' => $this->tucashkDesc,
                        'tucashk_nominal' => $this->tucashkNominal,
                        'acc_id' => $this->accId,
                        'acc_id_kas' => $this->accIdKas,
                        'emp_id' => $empId,
                        'shift' => $shift,
                        'tucashk_status' => 'L',
                        'g_bulan' => $dateFormatted->format('Y-m'),
                    ]);
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Transaksi penerimaan kas berhasil disimpan.');
            $this->closeForm();
            $this->incrementVersion('list');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(string $tucashkNo): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        try {
            DB::table('rstxn_tucashds')
                ->where('tucashk_no', $tucashkNo)
                ->delete();

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dihapus.');
            $this->incrementVersion('list');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }
};
?>

<div class="p-4 space-y-4">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">Penerimaan Kas TU (Cash-In Lainnya)</h2>

        <div class="flex items-center gap-2">
            {{-- Filter Bulan --}}
            <div class="w-40">
                <x-text-input type="month" wire:model.live="filterBulan" class="w-full" />
            </div>

            {{-- Search --}}
            <div class="w-64">
                <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                    placeholder="Cari keterangan / akun..." class="w-full" />
            </div>

            {{-- Per Page --}}
            <div class="w-20">
                <x-select-input wire:model.live="itemsPerPage">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </x-select-input>
            </div>

            {{-- Tombol Tambah --}}
            <x-primary-button wire:click="openCreate">
                + Tambah Penerimaan Kas
            </x-primary-button>
        </div>
    </div>

    {{-- PANDUAN --}}
    <div class="flex items-start gap-2 px-3 py-2 text-xs text-gray-600 bg-gray-100 border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
            <p class="font-semibold text-gray-700 dark:text-gray-300">Panduan:</p>
            <ul class="mt-1 space-y-0.5 list-disc list-inside">
                <li>Modul ini untuk mencatat <strong>penerimaan kas di luar transaksi pelayanan RS</strong> (RJ/UGD/RI).</li>
                <li>Klik <strong>"+ Tambah Penerimaan Kas"</strong>, pilih akun penerimaan (CI), akun kas, isi keterangan & nominal, lalu simpan.</li>
                <li>Pembatalan hanya bisa dilakukan oleh <strong>Admin atau TU</strong>.</li>
            </ul>
        </div>
    </div>

    {{-- FORM INPUT --}}
    @if ($showForm)
        <div class="p-4 space-y-4 border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700"
            wire:key="{{ $this->renderKey('form') }}">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                    {{ $editNo ? 'Edit Penerimaan Kas #' . $editNo : 'Tambah Penerimaan Kas Baru' }}
                </h3>
                <x-secondary-button wire:click="closeForm" class="!py-1 !px-3 text-xs">Tutup</x-secondary-button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {{-- Tanggal --}}
                <div>
                    <x-input-label value="Tanggal" :required="true" />
                    <x-text-input type="text" wire:model="tucashkDate" placeholder="dd/mm/yyyy" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('tucashkDate')" class="mt-1" />
                </div>

                {{-- Nominal --}}
                <div>
                    <x-input-label value="Nominal (Rp)" :required="true" />
                    <x-text-input type="number" wire:model="tucashkNominal" placeholder="0" class="w-full mt-1 font-mono text-right" min="1" />
                    <x-input-error :messages="$errors->get('tucashkNominal')" class="mt-1" />
                </div>

                {{-- Akun Penerimaan (CI) --}}
                <div>
                    <livewire:lov.akun-ci.lov-akun-ci target="akun-ci-tu" label="Akun Penerimaan (CI)" :initialAccId="$accId"
                        wire:key="lov-ci-{{ $editNo ?? 'new' }}-{{ $renderVersions['form'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                </div>

                {{-- Akun Kas --}}
                <div>
                    <livewire:lov.kas.lov-kas target="akun-kas-tu" tipe="" label="Akun Kas" :initialAccId="$accIdKas"
                        wire:key="lov-kas-{{ $editNo ?? 'new' }}-{{ $renderVersions['form'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accIdKas')" class="mt-1" />
                </div>

                {{-- Keterangan --}}
                <div class="sm:col-span-2">
                    <x-input-label value="Keterangan" :required="true" />
                    <x-text-input type="text" wire:model="tucashkDesc" placeholder="Keterangan penerimaan kas" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('tucashkDesc')" class="mt-1" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-secondary-button wire:click="closeForm">Batal</x-secondary-button>
                <x-primary-button wire:click="simpan" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan & Posting</span>
                    <span wire:loading><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
    @endif

    {{-- TABEL DATA --}}
    @php($rows = $this->rows())
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                    <tr class="text-left">
                        <th class="px-3 py-2 font-semibold">NO</th>
                        <th class="px-3 py-2 font-semibold">TANGGAL</th>
                        <th class="px-3 py-2 font-semibold">KETERANGAN</th>
                        <th class="px-3 py-2 font-semibold">AKUN</th>
                        <th class="px-3 py-2 font-semibold text-right">NOMINAL</th>
                        <th class="px-3 py-2 font-semibold">KAS</th>
                        <th class="px-3 py-2 font-semibold">PETUGAS</th>
                        <th class="px-3 py-2 font-semibold">STATUS</th>
                        <th class="px-3 py-2 font-semibold">AKSI</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                    @forelse($rows as $row)
                        <tr wire:key="tu-row-{{ $row->tucashk_no }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                            <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ $row->tucashk_no }}</td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">
                                {{ $row->tucashk_date ? \Carbon\Carbon::parse($row->tucashk_date)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-3 py-2 max-w-xs truncate">{{ $row->tucashk_desc ?? '-' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <div class="font-semibold">{{ $row->acc_name ?? '-' }}</div>
                                <div class="text-gray-400">{{ $row->acc_id }}</div>
                            </td>
                            <td class="px-3 py-2 font-mono text-right whitespace-nowrap">Rp {{ number_format($row->tucashk_nominal ?? 0) }}</td>
                            <td class="px-3 py-2 text-xs">
                                <div>{{ $row->acc_name_kas ?? '-' }}</div>
                                <div class="text-gray-400">{{ $row->acc_id_kas }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $row->emp_name ?? $row->emp_id ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $row->tucashk_status === 'L' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $row->tucashk_status === 'L' ? 'Posted' : ($row->tucashk_status ?? '-') }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @hasanyrole('Admin|Tu')
                                    <x-confirm-button variant="danger" :action="'batalTransaksi(\'' . $row->tucashk_no . '\')'"
                                        title="Hapus Transaksi" message="Yakin ingin menghapus transaksi #{{ $row->tucashk_no }}?"
                                        confirmText="Ya, hapus" cancelText="Batal">
                                        Hapus
                                    </x-confirm-button>
                                @endhasanyrole
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Tidak ada data penerimaan kas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
