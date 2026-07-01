<?php
// Riwayat Grouping iDRG per pasien (lintas kunjungan RI + RJ, khusus BPJS).
// Contek pola rekam-medis-display: 1 komponen by regNo → tabel riwayat.
// Sumber: rstxn_rihdrs + rstxn_rjhdrs (filter klaim BPJS) → extract idrg dari JSON.

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $regNo = '';
    public string $currentTxnNo = ''; // sorot kunjungan yang sedang dibuka
    public array $rows = [];

    public function mount(string $regNo = '', string $currentTxnNo = ''): void
    {
        $this->regNo = $regNo;
        $this->currentTxnNo = $currentTxnNo;
        $this->loadHistory();
    }

    public function loadHistory(): void
    {
        $this->rows = [];
        if ($this->regNo === '') {
            return;
        }

        $out = [];

        // ── RI ──
        $ri = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->where('h.reg_no', $this->regNo)
            ->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select([
                'h.rihdr_no as txn_no',
                'h.datadaftarri_json as js',
                'k.klaim_desc',
                DB::raw("to_char(h.entry_date,'dd/mm/yyyy') as masuk"),
                DB::raw("to_char(h.exit_date,'dd/mm/yyyy') as pulang"),
                DB::raw("to_char(h.entry_date,'yyyymmddhh24miss') as sortkey"),
            ])
            ->get();
        foreach ($ri as $r) {
            $out[] = $this->buildRow($r, 'RI');
        }

        // ── RJ ──
        $rj = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->where('h.reg_no', $this->regNo)
            ->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            })
            ->select([
                'h.rj_no as txn_no',
                'h.datadaftarpolirj_json as js',
                'k.klaim_desc',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy') as masuk"),
                DB::raw("to_char(h.rj_date,'yyyymmddhh24miss') as sortkey"),
            ])
            ->get();
        foreach ($rj as $r) {
            $out[] = $this->buildRow($r, 'RJ');
        }

        // Urut terbaru di atas
        usort($out, fn($a, $b) => strcmp($b['sortkey'], $a['sortkey']));

        // Resolve nama petugas (coderNik = emp_id → users.myuser_name)
        $niks = array_values(array_filter(array_unique(array_map(fn($r) => $r['coderNik'], $out))));
        $names = $niks ? DB::table('users')->whereIn('emp_id', $niks)->pluck('myuser_name', 'emp_id')->toArray() : [];
        foreach ($out as &$r) {
            $r['petugas'] = $r['coderNik'] !== '' ? ($names[$r['coderNik']] ?? $r['coderNik']) : '-';
        }
        unset($r);

        $this->rows = $out;
    }

    private function buildRow($r, string $tipe): array
    {
        $js = json_decode($r->js ?? '{}', true) ?: [];
        $idrg = $js['idrg'] ?? [];

        return [
            'txn_no' => (string) $r->txn_no,
            'tipe' => $tipe,
            'masuk' => $r->masuk ?? '-',
            'pulang' => $tipe === 'RI' ? ($r->pulang ?? '-') : '-',
            'jaminan' => $r->klaim_desc ?: ($js['klaimDesc'] ?? 'BPJS'),
            'sep' => (string) (data_get($idrg, 'nomorSep') ?: (data_get($js, 'sep.noSep') ?: '-')),
            'cbg' => (string) (data_get($idrg, 'idrgGroup.drg_code') ?: '-'),
            'status' => $this->mapStatus($idrg),
            'coderNik' => (string) (data_get($idrg, 'coderNik') ?? ''),
            'sortkey' => (string) ($r->sortkey ?? ''),
        ];
    }

    private function mapStatus(array $idrg): string
    {
        if (!empty($idrg['klaimFinal'])) {
            return 'Klaim Final';
        }
        if (!empty($idrg['inacbgFinal'])) {
            return 'INACBG Final';
        }
        if (!empty($idrg['idrgFinal'])) {
            return 'iDRG Final';
        }
        if (!empty($idrg['idrgUngroupable'])) {
            return 'Ungroupable';
        }
        if (!empty($idrg['idrgGroup'])) {
            return 'Grouped';
        }
        if (!empty($idrg['nomorSep'])) {
            return 'Setup Klaim';
        }
        return '-';
    }
};
?>

<div>
    @php
        $statusStyle = fn($s) => match ($s) {
            'Klaim Final', 'INACBG Final', 'iDRG Final' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'Grouped' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'Setup Klaim' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            'Ungroupable' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            default => 'bg-surface-soft text-muted-soft',
        };
    @endphp

    <div class="overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-300">
                <tr>
                    <th class="px-3 py-2 whitespace-nowrap">Tgl Masuk</th>
                    <th class="px-3 py-2 whitespace-nowrap">Tgl Pulang</th>
                    <th class="px-3 py-2">Jenis Jaminan</th>
                    <th class="px-3 py-2 whitespace-nowrap">No. SEP</th>
                    <th class="px-3 py-2 text-center">Tipe</th>
                    <th class="px-3 py-2 whitespace-nowrap">CBG</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Petugas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                @forelse ($rows as $row)
                    <tr wire:key="idrg-hist-{{ $row['tipe'] }}-{{ $row['txn_no'] }}"
                        class="{{ $row['txn_no'] === $currentTxnNo ? 'bg-brand/5 dark:bg-brand/10' : 'bg-canvas dark:bg-gray-800' }} hover:bg-surface-soft dark:hover:bg-gray-700/40">
                        <td class="px-3 py-2 whitespace-nowrap font-mono text-body dark:text-gray-200">
                            {{ $row['masuk'] }}
                            @if ($row['txn_no'] === $currentTxnNo)
                                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand/10 text-brand">aktif</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap font-mono text-muted-soft">{{ $row['pulang'] }}</td>
                        <td class="px-3 py-2 text-muted dark:text-gray-300">{{ $row['jaminan'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap font-mono text-muted dark:text-gray-300">{{ $row['sep'] }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $row['tipe'] === 'RI' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' }}">
                                {{ $row['tipe'] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap font-mono font-semibold text-ink dark:text-gray-100">{{ $row['cbg'] }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusStyle($row['status']) }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted dark:text-gray-300">{{ $row['petugas'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-muted-soft">
                            Belum ada riwayat grouping BPJS untuk pasien ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
