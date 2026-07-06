<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $accId       = '';
    public string $accDesc     = '';
    public string $accDkStatus = 'D';

    /** Mode tampilan: 'harian' (satu tanggal) | 'shift' (satu tanggal, dipotong per shift) | 'bulanan' (satu bulan penuh) */
    public string $mode = 'harian';

    /** Format internal: 'YYYY-MM' (mode bulanan) */
    public string $periode = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Tanggal untuk mode harian: 'YYYY-MM-DD' */
    public string $tanggalHarian = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /** Mode berbasis satu tanggal (harian & per-shift), lawan dari bulanan. */
    private function isModeHarian(): bool
    {
        return in_array($this->mode, ['harian', 'shift'], true);
    }

    #[Computed]
    public function dariTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }
        return $this->periode === '' ? '' : $this->periode . '-01';
    }

    #[Computed]
    public function sampaiTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }
        if ($this->periode === '') return '';
        return Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString();
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, ['harian', 'shift', 'bulanan'], true)) return;
        $this->mode = $mode;
        // Pindah ke mode harian/shift tanpa tanggal → default ke hari terakhir periode terpilih.
        if ($this->isModeHarian() && $this->tanggalHarian === '' && $this->periode !== '') {
            $this->tanggalHarian = Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString();
        }
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->addMonth()->format('Y-m'));
    }

    public function prevDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->addDay()->toDateString());
    }

    /**
     * User mengubah text "MM/YYYY" → parse → set internal YYYY-MM.
     * Kalau format invalid, biarin saja (tabel jadi kosong sampai dikoreksi).
     */
    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $m)) {
            $this->periode = '';
            return;
        }
        [$_, $bulan, $tahun] = $m;
        $this->periode = "{$tahun}-{$bulan}";
    }

    /** Date input harian berubah → sinkron periode agar konsisten saat balik ke bulanan. */
    public function updatedTanggalHarian(string $value): void
    {
        if ($value !== '') {
            $this->setPeriode(substr($value, 0, 7));
        }
    }

    private function setPeriode(string $ym): void
    {
        $this->periode = $ym;
        $this->periodeInput = Carbon::parse($ym . '-01')->format('m/Y');
    }

    private function setTanggalHarian(string $ymd): void
    {
        $this->tanggalHarian = $ymd;
        $this->setPeriode(substr($ymd, 0, 7));
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('keuangan.saldo-kas.openHistory')]
    public function openHistory(string $accId, string $tanggal): void
    {
        $row = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name', 'acc_dk_status')
            ->where('acc_id', $accId)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas tidak ditemukan.');
            return;
        }

        $this->accId       = (string) $row->acc_id;
        $this->accDesc     = (string) ($row->acc_name ?? '');
        $this->accDkStatus = (string) ($row->acc_dk_status ?? 'D');

        // Default: mode harian pada tanggal terpilih di parent; periode disinkron utk mode bulanan.
        $this->mode = 'harian';
        $this->setPeriode(substr($tanggal, 0, 7));
        $this->tanggalHarian = $tanggal;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-history');
    }

    /**
     * Saldo per tanggal (sama formula dgn parent).
     */
    private function hitungSaldoTanggal(string $tanggal): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)->first();

        $saldoAwalTahun = $this->accDkStatus === 'D'
            ? (float) ($sa->sa_acc_d ?? 0)
            : (float) ($sa->sa_acc_k ?? 0);

        if ($this->accDkStatus === 'D') {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc_k', $this->accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun), $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_k,0) - NVL(txn_d,0)'));
        } else {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc', $this->accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun), $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_d,0) - NVL(txn_k,0)'));
        }

        return $saldoAwalTahun + $arus;
    }

    /**
     * Saldo awal periode = saldo per (dari_tanggal - 1 hari).
     */
    #[Computed]
    public function saldoAwalPeriode(): float
    {
        if ($this->dariTanggal === '') return 0;
        $prev = Carbon::parse($this->dariTanggal)->subDay()->toDateString();
        return $this->hitungSaldoTanggal($prev);
    }

    #[Computed]
    public function totalDebit(): float
    {
        return (float) $this->rows->sum('debit_kita');
    }

    #[Computed]
    public function totalKredit(): float
    {
        return (float) $this->rows->sum('kredit_kita');
    }

    #[Computed]
    public function saldoAkhir(): float
    {
        return $this->saldoAwalPeriode + $this->totalDebit - $this->totalKredit;
    }

    /**
     * Daftar transaksi dalam rentang dgn running saldo.
     */
    #[Computed]
    public function rows()
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            return collect();
        }

        // Untuk D-acc: filter txn_acc_k = acc, "counter row" → txn_acc = lawan, txn_d = lawan didebit (kita dikredit), txn_k = lawan dikredit (kita didebit)
        // Untuk K-acc: filter txn_acc = acc, langsung "row tentang akun kita".
        if ($this->accDkStatus === 'D') {
            $q = DB::table('tkview_accounts as v')
                ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'v.txn_acc')
                ->select(
                    'v.txn_date', 'v.txn_name',
                    'v.txn_acc as lawan_acc_id', 'a.acc_name as lawan_acc_name',
                    DB::raw('NVL(v.txn_k,0) AS debit_kita'),
                    DB::raw('NVL(v.txn_d,0) AS kredit_kita'),
                )
                ->where('v.txn_acc_k', $this->accId);
        } else {
            $q = DB::table('tkview_accounts as v')
                ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'v.txn_acc_k')
                ->select(
                    'v.txn_date', 'v.txn_name',
                    'v.txn_acc_k as lawan_acc_id', 'a.acc_name as lawan_acc_name',
                    DB::raw('NVL(v.txn_d,0) AS debit_kita'),
                    DB::raw('NVL(v.txn_k,0) AS kredit_kita'),
                )
                ->where('v.txn_acc', $this->accId);
        }

        $rows = $q->whereBetween(DB::raw("TO_CHAR(v.txn_date,'YYYY-MM-DD')"), [
                    $this->dariTanggal, $this->sampaiTanggal,
                ])
                ->orderBy('v.txn_date')
                ->get();

        // Hitung running saldo
        $saldo = $this->saldoAwalPeriode;
        return $rows->map(function ($r) use (&$saldo) {
            $mutasi = (float) $r->debit_kita - (float) $r->kredit_kita;
            $saldo += $mutasi;
            $r->saldo_berjalan = $saldo;
            $r->mutasi = $mutasi;
            return $r;
        });
    }

    /** Definisi shift (rstxn_shiftctls) — pola konsisten dgn penerimaan/pengeluaran kas TU. */
    #[Computed]
    public function shiftDefs()
    {
        return DB::table('rstxn_shiftctls')
            ->select('shift', 'shift_start', 'shift_end')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->get();
    }

    /** Resolve nomor shift dari jam transaksi (mirror `time BETWEEN shift_start AND shift_end`, fallback '1'). */
    private function resolveShift(string $txnDate): string
    {
        $jam = Carbon::parse($txnDate)->format('H:i:s');
        foreach ($this->shiftDefs as $def) {
            $mulai   = (string) $def->shift_start;
            $selesai = (string) $def->shift_end;
            $cocok = $mulai <= $selesai
                ? ($jam >= $mulai && $jam <= $selesai)          // rentang normal
                : ($jam >= $mulai || $jam <= $selesai);         // rentang melewati tengah malam
            if ($cocok) {
                return (string) $def->shift;
            }
        }
        return '1';
    }

    /**
     * Kelompokkan transaksi harian per shift (urut kemunculan/kronologis), dengan subtotal & saldo per shift.
     * Dipakai tampilan mode 'shift' & cetak rekap per shift.
     */
    private function susunKelompokShift(): array
    {
        $perShift = [];
        foreach ($this->rows as $row) {
            $perShift[$this->resolveShift($row->txn_date)][] = $row;
        }

        $kelompok = [];
        $saldoAwalShift = $this->saldoAwalPeriode;
        foreach ($perShift as $shift => $items) {
            $def = $this->shiftDefs->first(fn($d) => (string) $d->shift === (string) $shift);
            $subtotalDebit = 0.0;
            $subtotalKredit = 0.0;
            foreach ($items as $item) {
                $subtotalDebit  += (float) $item->debit_kita;
                $subtotalKredit += (float) $item->kredit_kita;
            }
            $saldoAkhirShift = (float) end($items)->saldo_berjalan;

            $kelompok[] = (object) [
                'shift'          => (string) $shift,
                'range'          => $def ? substr((string) $def->shift_start, 0, 5) . '–' . substr((string) $def->shift_end, 0, 5) : null,
                'items'          => $items,
                'subtotalDebit'  => $subtotalDebit,
                'subtotalKredit' => $subtotalKredit,
                'saldoAwal'      => $saldoAwalShift,
                'saldoAkhir'     => $saldoAkhirShift,
            ];
            $saldoAwalShift = $saldoAkhirShift;
        }
        return $kelompok;
    }

    #[Computed]
    public function shiftGroups(): array
    {
        if ($this->mode !== 'shift' || $this->dariTanggal === '') {
            return [];
        }
        return $this->susunKelompokShift();
    }

    public function cetakRekap(): mixed
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        // Pra-format tanggal di sini (class zone) supaya blade cetak bebas Carbon.
        $formatItem = function ($row) {
            $tglTransaksi = Carbon::parse($row->txn_date);
            return (object) [
                'tglLabel'      => $tglTransaksi->format('d/m/Y'),
                'jamLabel'      => $tglTransaksi->format('H:i'),
                'deskripsi'     => $row->txn_name,
                'lawanAccId'    => $row->lawan_acc_id,
                'lawanAccName'  => $row->lawan_acc_name,
                'debit'         => (float) $row->debit_kita,
                'kredit'        => (float) $row->kredit_kita,
                'saldoBerjalan' => (float) $row->saldo_berjalan,
            ];
        };

        $transaksiList = $this->rows->map($formatItem)->values();

        // Mode per shift: susun kelompok dgn item ter-format.
        $shiftGroups = [];
        if ($this->mode === 'shift') {
            foreach ($this->susunKelompokShift() as $kelompok) {
                $shiftGroups[] = (object) [
                    'shift'          => $kelompok->shift,
                    'range'          => $kelompok->range,
                    'items'          => collect($kelompok->items)->map($formatItem)->values(),
                    'subtotalDebit'  => $kelompok->subtotalDebit,
                    'subtotalKredit' => $kelompok->subtotalKredit,
                    'saldoAwal'      => $kelompok->saldoAwal,
                    'saldoAkhir'     => $kelompok->saldoAkhir,
                ];
            }
        }

        $tglMulai  = Carbon::parse($this->dariTanggal);
        $tglSampai = Carbon::parse($this->sampaiTanggal);
        $modeLabel = ['harian' => 'Harian', 'shift' => 'Harian (per Shift)', 'bulanan' => 'Bulanan'][$this->mode] ?? 'Harian';

        $dataCetak = [
            'accId'        => $this->accId,
            'accDesc'      => $this->accDesc,
            'mode'         => $this->mode,
            'modeLabel'    => $modeLabel,
            'periodeLabel' => $this->isModeHarian()
                ? $tglMulai->format('d/m/Y')
                : $tglMulai->format('d/m/Y') . ' — ' . $tglSampai->format('d/m/Y'),
            'saldoAwalTgl' => $tglMulai->copy()->subDay()->format('d/m/Y'),
            'sampaiLabel'  => $tglSampai->format('d/m/Y'),
            'dicetakPada'  => now()->format('d/m/Y H:i'),
            'saldoAwal'    => $this->saldoAwalPeriode,
            'totalDebit'   => $this->totalDebit,
            'totalKredit'  => $this->totalKredit,
            'saldoAkhir'   => $this->saldoAkhir,
            'transaksiList' => $transaksiList,
            'shiftGroups'   => $shiftGroups,
        ];

        $pdf = Pdf::loadView('pages.transaksi.keuangan.saldo-kas.saldo-kas-history-print', $dataCetak)
            ->setPaper('a4', 'portrait');

        $akhiranPeriode = $this->isModeHarian()
            ? $tglMulai->format('Ymd')
            : $tglMulai->format('Ym');
        $namaFile = 'rekap-kas-' . $this->accId . '-' . $akhiranPeriode . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }

    public function closeModal(): void
    {
        $this->reset(['accId', 'accDesc', 'accDkStatus', 'mode', 'periode', 'periodeInput', 'tanggalHarian']);
        $this->dispatch('close-modal', name: 'saldo-kas-history');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-history" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$accId, $mode, $periode, $tanggalHarian]) }}">

            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">
                            Riwayat Transaksi — {{ $accDesc }}
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Akun <span class="font-mono">{{ $accId }}</span>
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="px-4 py-3 bg-canvas border-b border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        {{-- Toggle mode: Harian / Per Shift / Bulanan (Standar UI: x-tabs variant pill) --}}
                        <div>
                            <x-input-label value="Tampilan" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-tabs variant="pill">
                                <x-tab :active="$mode === 'harian'" color="emerald" wire:click="setMode('harian')">Harian</x-tab>
                                <x-tab :active="$mode === 'shift'" color="emerald" wire:click="setMode('shift')">Per Shift</x-tab>
                                <x-tab :active="$mode === 'bulanan'" color="emerald" wire:click="setMode('bulanan')">Bulanan</x-tab>
                            </x-tabs>
                        </div>

                        @if ($mode === 'bulanan')
                            {{-- Picker bulan --}}
                            <div>
                                <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevMonth"
                                        class="px-3" title="Bulan sebelumnya">
                                        ◀
                                    </x-secondary-button>
                                    <x-text-input id="periodeInput" type="text"
                                        wire:model.live.debounce.500ms="periodeInput"
                                        placeholder="01/2026" maxlength="7"
                                        class="w-28 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextMonth"
                                        class="px-3" title="Bulan berikutnya">
                                        ▶
                                    </x-secondary-button>
                                </div>
                                <p class="mt-1 text-[11px] text-muted dark:text-gray-400">
                                    @if ($periode !== '')
                                        {{ \Carbon\Carbon::parse("{$periode}-01")->format('d/m/Y') }}
                                        — {{ \Carbon\Carbon::parse("{$periode}-01")->endOfMonth()->format('d/m/Y') }}
                                    @else
                                        <span class="text-error">Format: mm/yyyy (mis. 04/2026)</span>
                                    @endif
                                </p>
                            </div>
                        @else
                            {{-- Picker harian --}}
                            <div>
                                <x-input-label for="tanggalHarian" value="Tanggal (dd/mm/yyyy)" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevDay"
                                        class="px-3" title="Hari sebelumnya">
                                        ◀
                                    </x-secondary-button>
                                    <x-text-input id="tanggalHarian" type="date"
                                        wire:model.live="tanggalHarian"
                                        class="w-40 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextDay"
                                        class="px-3" title="Hari berikutnya">
                                        ▶
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex-1 px-4 py-3 overflow-hidden bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="h-full overflow-y-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">TANGGAL</th>
                                <th class="px-3 py-2 font-semibold">DESKRIPSI</th>
                                <th class="px-3 py-2 font-semibold w-56">LAWAN AKUN</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">DEBIT</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">KREDIT</th>
                                <th class="px-3 py-2 font-semibold w-36 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @if ($this->dariTanggal !== '')
                                <tr class="bg-surface-soft dark:bg-gray-800/40">
                                    <td colspan="5" class="px-3 py-2 text-xs italic text-muted">
                                        Saldo per {{ \Carbon\Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-sm font-semibold text-right">
                                        {{ number_format($this->saldoAwalPeriode, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif

                            @if ($mode === 'shift')
                                {{-- Mode Per Shift: transaksi dikelompokkan per shift + subtotal --}}
                                @forelse ($this->shiftGroups as $group)
                                    <tr class="bg-brand-green/10 dark:bg-emerald-900/30">
                                        <td colspan="6" class="px-3 py-1.5 text-xs font-bold tracking-wide uppercase text-emerald-800 dark:text-emerald-200">
                                            Shift {{ $group->shift }}@if ($group->range)<span class="ml-1 font-normal normal-case text-muted">({{ $group->range }})</span>@endif
                                        </td>
                                    </tr>
                                    @foreach ($group->items as $i => $row)
                                        <tr wire:key="shift-{{ $group->shift }}-{{ $i }}-{{ $row->txn_date }}"
                                            class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-3 py-2 font-mono text-xs leading-tight align-top">
                                                <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                                <div class="text-[10px] text-muted-soft">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-xs align-top">{{ $row->txn_name }}</td>
                                            <td class="px-3 py-2 text-xs text-muted align-top dark:text-gray-400">
                                                <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                                @if (!empty($row->lawan_acc_name))
                                                    <div class="text-[10px] truncate">{{ $row->lawan_acc_name }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 font-mono text-sm text-right align-top text-blue-700 dark:text-blue-300">
                                                @if ((float) $row->debit_kita > 0)
                                                    {{ number_format((float) $row->debit_kita, 0, ',', '.') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 font-mono text-sm text-right align-top text-error dark:text-rose-300">
                                                @if ((float) $row->kredit_kita > 0)
                                                    {{ number_format((float) $row->kredit_kita, 0, ',', '.') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-top {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                                {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-semibold bg-surface-soft dark:bg-gray-800/50">
                                        <td colspan="3" class="px-3 py-1.5 text-xs text-right uppercase text-muted">
                                            Subtotal Shift {{ $group->shift }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right text-blue-700 dark:text-blue-300">
                                            {{ number_format($group->subtotalDebit, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right text-error dark:text-rose-300">
                                            {{ number_format($group->subtotalKredit, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right {{ $group->saldoAkhir < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format($group->saldoAkhir, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                            Tidak ada transaksi pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                {{-- Mode Harian / Bulanan: daftar transaksi datar --}}
                                @forelse ($this->rows as $i => $row)
                                    <tr wire:key="hist-{{ $i }}-{{ $row->txn_date }}"
                                        class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-3 py-2 font-mono text-xs leading-tight align-top">
                                            <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-muted-soft">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs align-top">{{ $row->txn_name }}</td>
                                        <td class="px-3 py-2 text-xs text-muted align-top dark:text-gray-400">
                                            <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_name))
                                                <div class="text-[10px] truncate">{{ $row->lawan_acc_name }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right align-top text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit_kita > 0)
                                                {{ number_format((float) $row->debit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right align-top text-error dark:text-rose-300">
                                            @if ((float) $row->kredit_kita > 0)
                                                {{ number_format((float) $row->kredit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-top {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif

                        </tbody>
                        @if ($this->rows->count() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="sticky bottom-0 z-10 px-3 py-3 text-sm font-bold uppercase border-t-2 bg-emerald-100 border-emerald-300 text-emerald-800 dark:bg-emerald-900 dark:border-emerald-700 dark:text-emerald-200">
                                        Saldo per {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-sm font-semibold text-right border-t-2 bg-emerald-100 border-emerald-300 text-blue-700 dark:bg-emerald-900 dark:border-emerald-700 dark:text-blue-300">
                                        {{ number_format($this->totalDebit, 0, ',', '.') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-sm font-semibold text-right border-t-2 bg-emerald-100 border-emerald-300 text-error dark:bg-emerald-900 dark:border-emerald-700 dark:text-rose-300">
                                        {{ number_format($this->totalKredit, 0, ',', '.') }}
                                    </td>
                                    <td class="sticky bottom-0 z-10 px-3 py-3 font-mono text-lg font-bold text-right border-t-2 bg-emerald-100 border-emerald-300 {{ $this->saldoAkhir < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-800 dark:text-emerald-200' }} dark:bg-emerald-900 dark:border-emerald-700">
                                        {{ number_format($this->saldoAkhir, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-primary-button type="button" wire:click="cetakRekap"
                        wire:loading.attr="disabled" wire:target="cetakRekap"
                        @disabled($this->rows->count() === 0)>
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span wire:loading.remove wire:target="cetakRekap">Cetak Rekap</span>
                        <span wire:loading wire:target="cetakRekap">Menyiapkan…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
