<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /**
     * ════════════════════════════════════════════════════════════════════════
     *  DISPLAY PUBLIK — ANTRIAN APOTEK UGD
     * ════════════════════════════════════════════════════════════════════════
     *
     *  TUJUAN
     *  ──────
     *  Layar TV di apotek UGD → pasien/keluarga lihat status resep darurat.
     *  Pola identik dengan display RJ — hanya ganti sumber data.
     *
     *  WORKFLOW DATA
     *  ─────────────
     *  Pasien masuk UGD (triase)  →  dokter periksa & input eresep  →
     *  resep masuk antrian apotek (rj_status="A" + noAntrianApotek)  →
     *  apoteker telaah/racik  →  obat diserahkan (rj_status="L")
     *
     *  SUMBER (BEDA dgn RJ: pakai view UGD)
     *  ────────────────────────────────────
     *  • View   : rsview_ugdkasir
     *  • JSON   : datadaftarugd_json (Oracle CLOB, chunked 8x4000)
     *  • Filter : rj_date = today, klaim_id != 'KR', rj_status IN ('A','L')
     *
     *  JSON FIELDS DIPAKAI (minimal, privasi-aware)
     *  ────────────────────────────────────────────
     *  • noAntrianApotek.noAntrian, eresep, eresepRacikan, AdministrasiUgd
     *
     *  STATUS UI
     *  ─────────
     *  • Proses Resep  ← rj_status='A'
     *  • Sudah Selesai ← rj_status='L'
     *
     *  SORT
     *  ────
     *  Sama dengan RJ tapi tanpa task5/6 timestamp (UGD lebih simple — tidak
     *  ada urutan taskId multi-step seperti BPJS antrian poli).
     *
     *  PRIVASI
     *  ───────
     *  JSON full EMR UGD dibuang setelah extract. DTO bersih ke view.
     * ════════════════════════════════════════════════════════════════════════
     */
    public string $myTitle = 'Antrian Resep UGD';
    public string $mySnipt = 'Antrian Apotek — Pasien Unit Gawat Darurat';

    public function placeholder(): string
    {
        return '<div class="p-8 text-center text-gray-400">Memuat antrian…</div>';
    }

    public function with(): array
    {
        $refDate = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y');

        // Halaman PUBLIK. datadaftarugd_json mengandung full EMR. Ekstrak field minimal
        // di server, buang JSON sebelum return ke view.
        $rows = DB::table('rsview_ugdkasir')
            ->select([
                'rj_status',
                'reg_name',
                'dr_name',
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000,     1) AS j1'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000,  4001) AS j2'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000,  8001) AS j3'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000, 12001) AS j4'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000, 16001) AS j5'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000, 20001) AS j6'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000, 24001) AS j7'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarugd_json, 4000, 28001) AS j8'),
            ])
            ->whereIn(DB::raw("nvl(rj_status,'A')"), ['A', 'L'])
            ->where('klaim_id', '!=', 'KR')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)
            ->get()
            ->map(function ($r) {
                $raw = '';
                foreach (['j1', 'j2', 'j3', 'j4', 'j5', 'j6', 'j7', 'j8'] as $k) {
                    $part = $r->$k ?? '';
                    if ($part === '' || $part === null) break;
                    $raw .= $part;
                }
                $j = json_decode($raw !== '' ? $raw : '[]', true, 512, JSON_PARTIAL_OUTPUT_ON_ERROR) ?? [];
                $antrian = (int) ($j['noAntrianApotek']['noAntrian'] ?? 0);

                return (object) [
                    'rj_status' => (string) ($r->rj_status ?? 'A'),
                    'reg_name' => (string) ($r->reg_name ?? '-'),
                    'dr_name' => (string) ($r->dr_name ?? ''),
                    'antrian' => $antrian,
                    'has_eresep' => isset($j['eresep']),
                    'racikan' => !empty($j['eresepRacikan'] ?? []),
                    '_q' => $antrian > 0 ? 1 : 0,
                    '_admin' => isset($j['AdministrasiUgd']) ? 1 : 0,
                ];
            });

        $sorter = fn($r) => [
            $r->_q,
            $r->_q ? $r->antrian : 1000,
            $r->_q ? 0 : ($r->has_eresep ? 1 : 0),
            $r->_q ? 0 : $r->_admin,
        ];

        return [
            'rowsAntri' => $rows->filter(fn($r) => $r->rj_status === 'A')->sortBy($sorter)->values(),
            'rowsLunas' => $rows->filter(fn($r) => $r->rj_status === 'L')->sortByDesc($sorter)->values(),
            'refDateTime' => Carbon::now(env('APP_TIMEZONE'))->format('d-m-Y H:i:s'),
        ];
    }
};
?>

@extends('layouts.app-welcome')

@section('content')
<div class="bg-white">

    <style>
        @keyframes flash-pulse { 0%,96%,100%{opacity:1} 97%,99%{opacity:0.35} }
        .blink-soft { animation: flash-pulse 5s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .blink-soft { animation: none !important; } }
    </style>

    <div class="w-full min-h-screen px-4 pt-4 pb-2"
        x-data="autoScroller({ step: 1, interval: 25, waitTop: 800, waitBottom: 1200 })"
        x-init="start()">

        <div class="flex items-end justify-between gap-3 mb-2">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $myTitle }}</h1>
                <p class="text-sm text-gray-600">{{ $mySnipt }}</p>
            </div>
            <div class="text-right">
                <div class="text-[11px] text-gray-500">Data terakhir</div>
                <div class="text-sm font-mono font-semibold text-gray-700">{{ $refDateTime }}</div>
            </div>
        </div>

        <div class="p-3 mb-3 text-sm sm:text-base border rounded-lg bg-amber-50 border-amber-200 text-amber-900 blink-soft">
            Resep <strong>racikan</strong> memerlukan tambahan waktu <strong>±15–30 menit</strong>.
            Petugas akan memanggil saat obat siap diambil.
        </div>

        <div class="grid grid-cols-2 gap-3 mb-2">
            <div class="px-4 py-2 rounded-lg bg-rose-50 border border-rose-200">
                <h2 class="text-base font-bold text-rose-800">🕐 Proses Resep
                    <span class="ml-1 text-sm font-mono text-rose-700/70">({{ $rowsAntri->count() }})</span>
                </h2>
            </div>
            <div class="px-4 py-2 rounded-lg bg-brand-green/10 border border-brand-green/30">
                <h2 class="text-base font-bold text-brand-green">✅ Sudah Selesai
                    <span class="ml-1 text-sm font-mono text-brand-green/70">({{ $rowsLunas->count() }})</span>
                </h2>
            </div>
        </div>

        <div class="h-[calc(100vh-220px)] overflow-auto"
            x-ref="scroller"
            x-on:mouseenter="pause()"
            x-on:mouseleave="resume()">
            <div class="grid grid-cols-2 gap-4">

                {{-- KIRI: ANTRI --}}
                <div>
                    <table class="min-w-full text-sm text-left text-gray-700 border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[40%]"><col class="w-[42%]"><col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-gray-900 uppercase bg-gray-100">
                            <tr><th class="px-3 py-2">Nama Pasien</th><th class="px-3 py-2">Dokter</th><th class="px-2 py-2 text-center">Antrian</th></tr>
                        </thead>
                        <tbody class="bg-white">
                            @forelse ($rowsAntri as $row)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-3 py-2.5 font-semibold text-gray-900 truncate">{{ $row->reg_name }}</td>
                                    <td class="px-3 py-2.5 truncate"><span class="text-xs text-gray-500">{{ $row->dr_name }}</span><br><span class="text-xs">UGD</span></td>
                                    <td class="px-2 py-2.5 text-center">
                                        <div class="text-2xl font-bold text-gray-900 leading-none">{{ $row->antrian ?: '—' }}</div>
                                        <div class="mt-1">
                                            @if ($row->has_eresep)
                                                <x-badge :variant="$row->racikan ? 'warning' : 'success'">{{ $row->racikan ? 'racikan' : 'non racikan' }}</x-badge>
                                            @else
                                                <x-badge variant="danger">menunggu resep</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400 italic">Tidak ada antrian aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- KANAN: LUNAS --}}
                <div>
                    <table class="min-w-full text-sm text-left text-gray-700 border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[40%]"><col class="w-[42%]"><col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-gray-900 uppercase bg-gray-100">
                            <tr><th class="px-3 py-2">Nama Pasien</th><th class="px-3 py-2">Dokter</th><th class="px-2 py-2 text-center">Status</th></tr>
                        </thead>
                        <tbody class="bg-white">
                            @forelse ($rowsLunas as $row)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-3 py-2.5 font-semibold text-gray-900 truncate">{{ $row->reg_name }}</td>
                                    <td class="px-3 py-2.5 truncate"><span class="text-xs text-gray-500">{{ $row->dr_name }}</span><br><span class="text-xs">UGD</span></td>
                                    <td class="px-2 py-2.5 text-center">
                                        <div class="text-sm font-bold text-brand-green leading-none">Selesai</div>
                                        <div class="mt-1">
                                            @if ($row->has_eresep)
                                                <x-badge :variant="$row->racikan ? 'warning' : 'success'">{{ $row->racikan ? 'racikan' : 'non racikan' }}</x-badge>
                                            @else
                                                <x-badge variant="alternative">—</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400 italic">Belum ada yang selesai</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($rowsAntri->count() === 0 && $rowsLunas->count() === 0)
                <div class="w-full p-6 text-center text-sm text-gray-500">Belum ada antrian resep UGD hari ini.</div>
            @endif
        </div>

        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
            <span>SIRus · Antrian Apotek UGD</span>
            <span>© {{ date('Y') }} RSI Madinah</span>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            if (Alpine.$data?.autoScroller) return;
            Alpine.data('autoScroller', (opts = {}) => ({
                step: opts.step ?? 1, interval: opts.interval ?? 25,
                waitTop: opts.waitTop ?? 800, waitBottom: opts.waitBottom ?? 1200,
                timer: null, running: false,
                start() {
                    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
                    if (mql.matches) return;
                    this.running = true;
                    if (window.Livewire) Livewire.hook('morph.updated', () => this.restart());
                    this.scrollLoop(true);
                },
                restart() { this.pause(); const el = this.$refs.scroller; if (el) el.scrollTop = 0; this.resume(true); },
                pause() { this.running = false; if (this.timer) { clearTimeout(this.timer); this.timer = null; } },
                resume(fromTop = false) { if (this.running) return; this.running = true; this.scrollLoop(fromTop); },
                scrollLoop(fromTop = false) {
                    if (!this.running) return;
                    const el = this.$refs.scroller; if (!el) return;
                    if (fromTop) this.timer = setTimeout(() => this.tick(), this.waitTop);
                    else this.tick();
                },
                tick() {
                    if (!this.running) return;
                    const el = this.$refs.scroller; if (!el) return;
                    const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
                    if (atBottom) {
                        this.timer = setTimeout(() => {
                            el.scrollTop = 0;
                            this.timer = setTimeout(() => { if (this.running) this.tick(); }, this.waitTop);
                        }, this.waitBottom);
                        return;
                    }
                    el.scrollTop += this.step;
                    this.timer = setTimeout(() => this.tick(), this.interval);
                }
            }));
        });
    </script>

</div>
@endsection
