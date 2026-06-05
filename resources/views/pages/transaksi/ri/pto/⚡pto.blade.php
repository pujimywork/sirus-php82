<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  PTO — PEMANTAUAN TERAPI OBAT (program apoteker)                    ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /ri/pto → pages::transaksi.ri.pto.pto
//
// Tujuan: apoteker memantau SELURUH terapi obat pasien rawat inap tanpa
// mengganggu e-resep dokter. Data dibaca dari e-resep RI (read-only):
//   rsview_rihdrs.datadaftarri_json → eresepHdr[] (resep 1, 2, 3, ...)
//
// Layout 2 kolom:
//   KIRI  : daftar pasien RI aktif (ri_status = 'I')
//   KANAN : panel terapi (child component pto-terapi) — semua resep + obat

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'I'; // '' = Semua, I = Dirawat, P = Pulang
    public int $itemsPerPage = 10;
    public ?int $selectedRihdrNo = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function selectPasien(int $rihdrNo): void
    {
        $this->selectedRihdrNo = $rihdrNo;
        $this->dispatch('pto.selectPasien', rihdrNo: $rihdrNo);
    }

    /* --- Daftar pasien RI aktif --- */
    #[Computed]
    public function pasiens()
    {
        $q = DB::table('rsview_rihdrs as rv')
            ->selectRaw("
                rv.rihdr_no,
                rv.reg_no,
                rv.reg_name,
                rv.sex,
                rv.address,
                rv.dr_name,
                rv.bangsal_name,
                rv.room_name,
                rv.bed_no,
                to_char(rv.birth_date,'dd/mm/yyyy') as birth_date,
                to_char(rv.entry_date,'dd/mm/yyyy hh24:mi') as entry_date_display,
                to_char(rv.exit_date,'dd/mm/yyyy hh24:mi') as exit_date_display,
                rv.ri_status,
                rv.datadaftarri_json
            ");

        if ($this->filterStatus !== '') {
            $q->where(DB::raw("NVL(rv.ri_status,'I')"), $this->filterStatus);
        } else {
            // "Semua" = hanya Dirawat (I) + Pulang (P); exclude status lain (batal, dll.)
            $q->whereIn(DB::raw("NVL(rv.ri_status,'I')"), ['I', 'P']);
        }

        if (trim($this->search) !== '' && mb_strlen(trim($this->search)) >= 2) {
            $kw = mb_strtoupper(trim($this->search));
            $s = trim($this->search);
            $q->where(function ($sub) use ($kw, $s) {
                if (ctype_digit($s)) {
                    $sub->orWhere('rv.reg_no', 'like', "%{$s}%");
                }
                $sub->orWhereRaw('UPPER(rv.reg_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(rv.dr_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(rv.bed_no) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(rv.room_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        // Pasien masih dirawat (exit null) di atas, lalu yang baru pulang, lalu admission terbaru
        $q->orderByRaw('rv.exit_date DESC NULLS FIRST')->orderByRaw('rv.entry_date DESC');

        $paginator = $q->paginate($this->itemsPerPage);

        // Hitung jumlah resep per pasien dari JSON (resep terkirim & draft)
        $paginator->getCollection()->transform(function ($row) {
            $jumlahResep = 0;
            $jumlahTerkirim = 0;
            $dpjpList = [];
            try {
                $data = $row->datadaftarri_json ? json_decode($row->datadaftarri_json, true) : null;
                if (is_array($data)) {
                    $hdrs = $data['eresepHdr'] ?? [];
                    $jumlahResep = count($hdrs);
                    foreach ($hdrs as $h) {
                        if (! empty($h['slsNo'] ?? null)) {
                            $jumlahTerkirim++;
                        }
                    }
                    // DPJP + level dokter (sama seperti Daftar RI)
                    foreach ($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $ld) {
                        if (! empty($ld['drName'])) {
                            $level = $ld['levelDokter'] ?? '';
                            $dpjpList[] = [
                                'drName' => $ld['drName'],
                                'level'  => $level === 'RawatGabung' ? 'Rawat Gabung' : $level,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // abaikan JSON rusak
            }
            $row->jumlah_resep = $jumlahResep;
            $row->jumlah_terkirim = $jumlahTerkirim;
            $row->dpjp_list = $dpjpList;
            $st = $row->ri_status ?: 'I';
            $row->status_text = $st === 'P' ? 'Pulang' : 'Dirawat';
            $row->status_variant = $st === 'P' ? 'gray' : 'success';
            unset($row->datadaftarri_json);
            return $row;
        });

        return $paginator;
    }

    private function umur(?string $birthDate): string
    {
        if (! $birthDate) {
            return '-';
        }
        try {
            $diff = \Carbon\Carbon::createFromFormat('d/m/Y', $birthDate)->diff(now());
            return "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
        } catch (\Throwable $e) {
            return '-';
        }
    }

    public function with(): array
    {
        return ['umurFn' => fn($bd) => $this->umur($bd)];
    }
};
?>

<div>
    <x-page-title
        title="Pemantauan Terapi Obat (PTO)"
        subtitle="Pantau seluruh terapi obat pasien rawat inap dari e-resep — khusus apoteker" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 flex-1 min-h-0">

                {{-- ── KIRI: DAFTAR PASIEN RI AKTIF ──────────────────── --}}
                <div class="flex flex-col min-h-0">
                    {{-- Toolbar --}}
                    <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <x-text-input type="text" wire:model.live.debounce.300ms="search"
                                placeholder="Cari pasien — nama / No. RM / bed / kamar..." class="block flex-1 min-w-0" />
                            <div class="w-28 shrink-0">
                                <x-select-input wire:model.live="filterStatus">
                                    <option value="I">Dirawat</option>
                                    <option value="P">Pulang</option>
                                    <option value="">Semua</option>
                                </x-select-input>
                            </div>
                            <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0 whitespace-nowrap">
                                {{ $this->pasiens->total() }} pasien
                                @if ($filterStatus === 'I') dirawat
                                @elseif ($filterStatus === 'P') pulang
                                @endif
                            </span>
                            <div class="w-20 shrink-0">
                                <x-select-input wire:model.live="itemsPerPage">
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                </x-select-input>
                            </div>
                        </div>
                    </div>

                    {{-- Tabel pasien --}}
                    <div class="flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex-1 min-h-0 px-3 overflow-x-auto overflow-y-auto rounded-t-2xl">
                            <table class="w-full min-w-full text-sm border-separate border-spacing-y-2">
                                <tbody>
                                    @forelse ($this->pasiens as $p)
                                        @php $isActive = $selectedRihdrNo === (int) $p->rihdr_no; @endphp
                                        <tr wire:key="pto-pasien-{{ $p->rihdr_no }}"
                                            wire:click="selectPasien({{ (int) $p->rihdr_no }})"
                                            class="cursor-pointer transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                           {{ $isActive
                                               ? 'bg-green-50 dark:bg-emerald-900/15 ring-2 ring-brand-green/50 border-l-4 border-brand-green'
                                               : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800' }}">
                                            <td class="px-4 py-3 align-top rounded-l-2xl">
                                                <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                                    {{-- Kolom kiri: Identitas + Lokasi --}}
                                                    <div class="min-w-0">
                                                        <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                            {{ $p->reg_no }}
                                                        </div>
                                                        <div class="text-lg font-semibold text-brand dark:text-white leading-tight">
                                                            {{ $p->reg_name ?? '-' }} /
                                                            ({{ $p->sex === 'L' ? 'Laki-Laki' : ($p->sex === 'P' ? 'Perempuan' : '-') }})
                                                        </div>
                                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                                            {{ $p->birth_date ?? '-' }} <span class="text-gray-500">({{ $umurFn($p->birth_date) }})</span>
                                                        </div>
                                                        <div class="text-sm font-semibold text-blue-600 dark:text-blue-400 leading-tight mt-1">{{ $p->bangsal_name ?? '-' }}</div>
                                                        <div class="text-sm text-gray-700 dark:text-gray-300 leading-tight">
                                                            {{ $p->room_name ?? '-' }}
                                                        </div>
                                                    </div>

                                                    {{-- Kolom kanan: DPJP / Penerima / Masuk --}}
                                                    <div class="min-w-0">
                                                        @if (! empty($p->dpjp_list))
                                                            <div class="text-sm text-gray-400 mt-0.5">DPJP:</div>
                                                            @foreach ($p->dpjp_list as $ld)
                                                                <div class="text-sm text-gray-700 dark:text-gray-200 leading-tight">
                                                                    {{ $ld['drName'] }}
                                                                    @if ($ld['level']) <span class="text-sm text-gray-500">({{ $ld['level'] }})</span> @endif
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                        <div class="text-xs italic text-gray-500 dark:text-gray-400 mt-0.5">Penerima: {{ $p->dr_name ?? '-' }}</div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            Masuk: {{ $p->entry_date_display ?? '-' }}
                                                            @if ($p->exit_date_display) · Keluar: {{ $p->exit_date_display }} @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top text-right rounded-r-2xl whitespace-nowrap">
                                                <div class="flex flex-col items-end gap-1.5">
                                                    <x-badge :variant="$p->status_variant">{{ $p->status_text }}</x-badge>
                                                    <x-badge :variant="$p->jumlah_resep > 0 ? 'info' : 'gray'">
                                                        {{ $p->jumlah_resep }} resep
                                                    </x-badge>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                                Tidak ada pasien rawat inap aktif.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->pasiens->links() }}
                        </div>
                    </div>
                </div>

                {{-- ── KANAN: PANEL TERAPI (child) ───────────────────── --}}
                <div class="flex flex-col min-h-0">
                    <livewire:pages::transaksi.ri.pto.pto-terapi wire:key="pto-terapi" />
                </div>

            </div>
        </div>
    </div>
</div>
