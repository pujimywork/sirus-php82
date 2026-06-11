<?php
// PTO — Panel Terapi (child)
// Menerima rihdrNo, membaca e-resep RI (read-only), menampilkan seluruh
// resep + obat. Tidak menyentuh data e-resep dokter sama sekali.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public ?int $rihdrNo = null;
    public ?array $pasien = null;
    public array $resepList = [];
    public array $obatAktif = [];

    #[On('pto.selectPasien')]
    public function selectPasien(int $rihdrNo): void
    {
        $this->rihdrNo = $rihdrNo;
        $this->load();
    }

    private function load(): void
    {
        $this->pasien = null;
        $this->resepList = [];
        $this->obatAktif = [];

        if (! $this->rihdrNo) {
            return;
        }

        $row = DB::table('rsview_rihdrs as rv')
            ->selectRaw("
                rv.rihdr_no, rv.reg_no, rv.reg_name, rv.sex, rv.address, rv.dr_name,
                rv.bangsal_name, rv.room_name, rv.bed_no, rv.ri_status,
                to_char(rv.birth_date,'dd/mm/yyyy') as birth_date,
                to_char(rv.entry_date,'dd/mm/yyyy hh24:mi') as entry_date_display,
                to_char(rv.exit_date,'dd/mm/yyyy hh24:mi') as exit_date_display,
                rv.datadaftarri_json
            ")
            ->where('rv.rihdr_no', $this->rihdrNo)
            ->first();

        if (! $row) {
            return;
        }

        $umur = '-';
        if ($row->birth_date) {
            try {
                $diff = Carbon::createFromFormat('d/m/Y', $row->birth_date)->diff(now());
                $umur = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Throwable $e) {
            }
        }

        $data = null;
        try {
            $data = $row->datadaftarri_json ? json_decode($row->datadaftarri_json, true) : null;
        } catch (\Throwable $e) {
        }

        // DPJP + level dokter (sama seperti Daftar RI); dr_name = Penerima
        $dpjpList = [];
        foreach (($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? []) as $ld) {
            if (! empty($ld['drName'])) {
                $level = $ld['levelDokter'] ?? '';
                $dpjpList[] = [
                    'drName' => $ld['drName'],
                    'level'  => $level === 'RawatGabung' ? 'Rawat Gabung' : $level,
                ];
            }
        }

        $this->pasien = [
            'reg_name'     => $row->reg_name,
            'reg_no'       => $row->reg_no,
            'sex'          => $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-'),
            'birth_date'   => $row->birth_date ?? '-',
            'umur'         => $umur,
            'address'      => $row->address ?? '-',
            'bangsal_name' => $row->bangsal_name ?? '-',
            'room_name'    => $row->room_name ?? '-',
            'bed_no'       => $row->bed_no ?? '-',
            'dpjp_list'    => $dpjpList,
            'penerima'     => $row->dr_name ?? '-',
            'masuk'        => $row->entry_date_display ?? '-',
            'keluar'       => $row->exit_date_display ?? null,
            'ri_status'    => $row->ri_status,
        ];

        $hdrs = is_array($data) ? ($data['eresepHdr'] ?? []) : [];

        // Status apotek untuk semua slsNo
        $slsNos = collect($hdrs)->pluck('slsNo')->filter()->values()->all();
        $apotekStatuses = [];
        if (! empty($slsNos)) {
            $apotekStatuses = DB::table('imtxn_slshdrs')
                ->whereIn('sls_no', $slsNos)
                ->pluck('status', 'sls_no')
                ->all();
        }

        // Normalisasi resep (terbaru di atas) + kumpulkan obat aktif
        $list = [];
        $aktif = [];
        foreach ($hdrs as $h) {
            $slsNo = $h['slsNo'] ?? null;
            $hasTTD = ! empty($h['tandaTanganDokter']['dokterPeresep'] ?? null);
            $apotekStatus = $slsNo ? ($apotekStatuses[$slsNo] ?? null) : null;

            if ($apotekStatus === 'L') {
                $status = ['label' => 'Selesai Diproses Apotek', 'variant' => 'gray'];
            } elseif ($slsNo) {
                $status = ['label' => 'Terkirim ke Apotek', 'variant' => 'success'];
            } elseif ($hasTTD) {
                $status = ['label' => 'TTD — menunggu kirim', 'variant' => 'warning'];
            } else {
                $status = ['label' => 'Draft', 'variant' => 'gray'];
            }

            // Non-racikan
            $obat = [];
            foreach ($h['eresep'] ?? [] as $it) {
                $signa = trim(($it['signaX'] ?? '') . ' dd ' . ($it['signaHari'] ?? ''), ' d');
                $obat[] = [
                    'productId'   => $it['productId'] ?? null,
                    'productName' => $it['productName'] ?? '-',
                    'qty'         => $it['qty'] ?? null,
                    'signa'       => $signa !== '' ? 'S ' . $signa : '-',
                    'catatan'     => $it['catatanKhusus'] ?? null,
                ];

                if (($hasTTD || $slsNo) && ! empty($it['productId'])) {
                    $aktif[$it['productId']] = [
                        'productName' => $it['productName'] ?? '-',
                        'signa'       => $signa !== '' ? 'S ' . $signa : '-',
                        'resepNo'     => $h['resepNo'] ?? null,
                        'jenis'       => 'Non-Racikan',
                    ];
                }
            }

            // Racikan
            $racikan = [];
            foreach ($h['eresepRacikan'] ?? [] as $it) {
                $racikan[] = [
                    'noRacikan'    => $it['noRacikan'] ?? null,
                    'productName'  => $it['productName'] ?? '-',
                    'dosis'        => $it['dosis'] ?? null,
                    'qty'          => $it['qty'] ?? null,
                    'catatan'      => $it['catatan'] ?? null,
                    'catatanKhusus'=> $it['catatanKhusus'] ?? null,
                ];
            }

            $list[] = [
                'resepNo'   => $h['resepNo'] ?? '-',
                'resepDate' => $h['resepDate'] ?? '-',
                'dokter'    => $h['tandaTanganDokter']['dokterPeresep'] ?? null,
                'slsNo'     => $slsNo,
                'status'    => $status,
                'obat'      => $obat,
                'racikan'   => $racikan,
            ];
        }

        // Terbaru di atas
        $this->resepList = array_reverse($list);
        $this->obatAktif = array_values($aktif);
    }
};
?>

<div class="flex flex-col h-full min-h-0">
    @if (! $rihdrNo || ! $pasien)
        <div class="flex flex-col items-center justify-center flex-1 py-12 text-muted-soft dark:text-gray-500 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <svg class="w-12 h-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <p class="text-sm">Pilih pasien di sebelah kiri untuk melihat seluruh terapi obatnya.</p>
        </div>
    @else
        <div class="flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

            {{-- Header pasien — tema Daftar RI --}}
            <div class="px-5 py-4 border-b border-hairline dark:border-gray-700 bg-surface-soft/70 dark:bg-gray-800/40 rounded-t-2xl">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    {{-- Identitas + Lokasi --}}
                    <div class="space-y-1 min-w-0">
                        <div class="text-base font-medium text-body dark:text-gray-300">
                            {{ $pasien['reg_no'] }}
                        </div>
                        <div class="text-lg font-semibold text-brand dark:text-white">
                            {{ $pasien['reg_name'] }} / ({{ $pasien['sex'] }})
                        </div>
                        <div class="text-sm text-body dark:text-gray-400">
                            {{ $pasien['birth_date'] }} <span class="text-muted">({{ $pasien['umur'] }})</span>
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400">{{ $pasien['address'] }}</div>
                        <div class="text-sm font-semibold text-blue-600 dark:text-blue-400 leading-tight mt-1">
                            {{ $pasien['bangsal_name'] }}
                        </div>
                        <div class="text-sm text-ink dark:text-gray-200 leading-tight">
                            {{ $pasien['room_name'] }}
                        </div>
                    </div>

                    {{-- DPJP / Penerima / Masuk --}}
                    <div class="space-y-1 min-w-0">
                        @if (! empty($pasien['dpjp_list']))
                            <div class="text-sm text-muted-soft">DPJP:</div>
                            @foreach ($pasien['dpjp_list'] as $ld)
                                <div class="text-sm text-body dark:text-gray-200 leading-tight">
                                    {{ $ld['drName'] }}
                                    @if ($ld['level']) <span class="text-sm text-muted">({{ $ld['level'] }})</span> @endif
                                </div>
                            @endforeach
                        @endif
                        <div class="text-xs italic text-muted dark:text-gray-400">Penerima: {{ $pasien['penerima'] }}</div>
                        <div class="text-xs italic text-muted dark:text-gray-400">
                            Masuk: {{ $pasien['masuk'] }}
                            @if ($pasien['keluar']) · Keluar: {{ $pasien['keluar'] }} @endif
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="shrink-0">
                        @if ($pasien['ri_status'] === 'P')
                            <x-badge variant="gray">Sudah Pulang</x-badge>
                        @else
                            <x-badge variant="success">Dirawat</x-badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 space-y-5">

                {{-- Ringkasan obat aktif --}}
                @if (! empty($obatAktif))
                    <div x-data="{ open: false }" class="border border-emerald-200 dark:border-emerald-800/40 rounded-xl overflow-hidden">
                        <button type="button" x-on:click="open = !open"
                            class="w-full flex items-center justify-between gap-3 px-4 py-2 bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800/40 hover:bg-emerald-100/70 dark:hover:bg-emerald-900/30 transition"
                            :class="open ? '' : 'border-b-transparent'">
                            <span class="text-left">
                                <span class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                                    Ringkasan Obat Aktif ({{ count($obatAktif) }})
                                </span>
                                <span class="hidden sm:inline text-sm text-emerald-700/70 dark:text-emerald-400/70 ml-1">— gabungan resep yang sudah ditandatangani/terkirim</span>
                            </span>
                            <svg class="w-4 h-4 text-success dark:text-success transition-transform shrink-0" :class="open ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @foreach ($obatAktif as $o)
                                <div class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                    <span class="font-medium text-ink dark:text-gray-100">{{ $o['productName'] }}</span>
                                    <span class="flex items-center gap-2 text-sm text-muted dark:text-gray-400 shrink-0">
                                        <span class="font-mono">{{ $o['signa'] }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-surface-soft dark:bg-gray-800">Resep #{{ $o['resepNo'] }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Daftar resep (terbaru di atas) --}}
                @forelse ($resepList as $r)
                    <div class="border border-hairline dark:border-gray-700 rounded-xl overflow-hidden">
                        {{-- Header resep --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-surface-soft dark:bg-gray-800/60 border-b border-hairline dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <span class="text-base font-semibold text-ink dark:text-gray-100">Resep #{{ $r['resepNo'] }}</span>
                                <span class="text-sm text-muted dark:text-gray-400">{{ $r['resepDate'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($r['slsNo'])
                                    <span class="text-sm font-mono text-muted-soft">SLS#{{ $r['slsNo'] }}</span>
                                @endif
                                <x-badge :variant="$r['status']['variant']">{{ $r['status']['label'] }}</x-badge>
                            </div>
                        </div>

                        @if ($r['dokter'])
                            <div class="px-4 pt-2 text-sm text-muted dark:text-gray-400">Peresep: {{ $r['dokter'] }}</div>
                        @endif

                        {{-- Obat non-racikan — gaya baris resep "R/" (selaras racikan) --}}
                        @if (! empty($r['obat']))
                            <div class="px-4 py-3">
                                <div class="text-sm uppercase tracking-wider text-muted-soft dark:text-gray-500 font-semibold mb-1">Non-Racikan</div>
                                <div class="space-y-1">
                                @foreach ($r['obat'] as $o)
                                    <div class="text-sm text-body dark:text-gray-200">
                                        <span class="font-mono text-muted-soft">R/</span>
                                        <span class="font-semibold text-ink dark:text-gray-100">{{ $o['productName'] }}</span>
                                        <span class="text-sm text-body dark:text-gray-300">| No. {{ $o['qty'] ?? '-' }}</span>
                                        <span class="text-sm font-mono text-body dark:text-gray-300">| {{ $o['signa'] }}</span>
                                        @if ($o['catatan']) <span class="text-sm text-body dark:text-gray-300 italic">({{ $o['catatan'] }})</span> @endif
                                    </div>
                                @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Racikan --}}
                        @if (! empty($r['racikan']))
                            <div class="px-4 pb-3 {{ empty($r['obat']) ? 'pt-3' : '' }}">
                                <div class="text-sm uppercase tracking-wider text-muted-soft dark:text-gray-500 font-semibold mb-1">Racikan</div>
                                <div class="space-y-1">
                                    @foreach ($r['racikan'] as $rc)
                                        <div class="text-sm text-body dark:text-gray-200">
                                            <span class="font-mono text-muted-soft">{{ $rc['noRacikan'] }}/</span>
                                            <span class="font-semibold text-ink dark:text-gray-100">{{ $rc['productName'] }}</span>
                                            @if ($rc['dosis']) <span class="text-body dark:text-gray-300">— {{ $rc['dosis'] }}</span> @endif
                                            @if ($rc['qty']) <span class="text-sm text-body dark:text-gray-300">| Jml {{ $rc['qty'] }}</span> @endif
                                            @if ($rc['catatanKhusus']) <span class="text-sm text-body dark:text-gray-300 italic">| S {{ $rc['catatanKhusus'] }}</span> @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (empty($r['obat']) && empty($r['racikan']))
                            <div class="px-4 py-3 text-sm text-muted-soft italic">Tidak ada item obat pada resep ini.</div>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center text-muted dark:text-gray-400">
                        Pasien ini belum memiliki e-resep.
                    </div>
                @endforelse

            </div>
        </div>
    @endif
</div>
