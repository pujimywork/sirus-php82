<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('layouts.app-fullscreen')] class extends Component {
    /**
     * ════════════════════════════════════════════════════════════════════════
     *  DISPLAY PUBLIK — ANTRIAN APOTEK RAWAT INAP
     * ════════════════════════════════════════════════════════════════════════
     *
     *  TUJUAN
     *  ──────
     *  Layar di apotek / keluarga RI → info status resep pasien rawat inap
     *  yang akan diantar ke bangsal.
     *
     *  WORKFLOW DATA (BEDA dgn RJ/UGD!)
     *  ────────────────────────────────
     *  Pasien RI = 1 registrasi rawat inap (rstxn_rihdrs), tapi selama
     *  dirawat bisa keluar MULTIPLE resep harian. Setiap resep = 1 baris di
     *  imtxn_slshdrs (sales header).
     *
     *  Resep harian RI:
     *  DPJP visite & input eresep di EMR  →  resep ter-generate sebagai
     *  slshdr (status="A", no_antrian terisi)  →  apoteker telaah & siapkan
     *  →  diantar perawat ke bangsal (status="L")
     *
     *  SUMBER (multi-table, beda dgn RJ/UGD yang pakai 1 view)
     *  ──────────────────────────────────────────────────────
     *  • Hub    : imtxn_slshdrs (1 row = 1 resep RI hari ini)
     *  • Join   : rsmst_pasiens (nama), rsmst_doctors (DPJP),
     *             rstxn_rihdrs (header RI → JSON), rsmst_rooms +
     *             rsmst_bangsals (lokasi pasien dirawat)
     *  • JSON   : rihdrs.datadaftarri_json (chunked 8x4000)
     *  • Filter : sls_date hari ini, rihdr_no NOT NULL
     *
     *  JSON MATCHING (kompleks — kenapa?)
     *  ──────────────────────────────────
     *  1 registrasi RI punya BANYAK resep dalam JSON.eresepHdr[].
     *  Untuk detect racikan per resep tertentu, harus match sls_no slshdrs
     *  dgn eresepHdr[].slsNo. Fallback: kalau tidak match tapi ada
     *  eresepRacikan global, anggap racikan.
     *
     *  STATUS UI
     *  ─────────
     *  • Proses Resep  ← status='A'  (apoteker sedang siapkan)
     *  • Sudah Diantar ← status='L'  (perawat sudah antar ke bangsal)
     *    Label "Diantar" beda dgn RJ/UGD "Selesai" — obat RI memang diantar
     *    perawat, bukan diambil langsung pasien.
     *
     *  SORT
     *  ────
     *  Simple: yang punya no_antrian di atas, lalu ASC by nomor.
     *
     *  PRIVASI
     *  ───────
     *  JSON RI lebih besar (asuhan kep, eresep harian multi, dll). Decode
     *  cuma untuk match sls_no → flag racikan. JSON dibuang sebelum return.
     * ════════════════════════════════════════════════════════════════════════
     */
    public string $myTitle = 'Antrian Resep Rawat Inap';
    public string $mySnipt = 'Antrian Apotek — Pasien Rawat Inap';

    public function placeholder(): string
    {
        return '<div class="p-8 text-center text-muted-soft">Memuat antrian…</div>';
    }

    public function with(): array
    {
        $start = Carbon::now(env('APP_TIMEZONE'))->startOfDay();
        $end = Carbon::now(env('APP_TIMEZONE'))->endOfDay();

        // Halaman PUBLIK. datadaftarri_json mengandung full EMR RI (asesmen masuk,
        // asuhan keperawatan, eresep harian, dll). Decode di server hanya untuk match
        // sls_no → flag racikan. Buang JSON sebelum return.
        $rows = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_rooms as rm', 'rm.room_id', '=', 'r.room_id')
            ->leftJoin('rsmst_bangsals as bn', 'bn.bangsal_id', '=', 'rm.bangsal_id')
            ->whereNotNull('s.rihdr_no')
            ->whereBetween('s.sls_date', [$start, $end])
            ->select([
                's.sls_no', 's.status', 's.no_antrian', 'd.dr_name',
                'p.reg_name', 'rm.room_name', 'bn.bangsal_name',
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000,     1) AS j1'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000,  4001) AS j2'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000,  8001) AS j3'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000, 12001) AS j4'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000, 16001) AS j5'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000, 20001) AS j6'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000, 24001) AS j7'),
                DB::raw('DBMS_LOB.SUBSTR(r.datadaftarri_json, 4000, 28001) AS j8'),
            ])
            ->get()
            ->map(function ($r) {
                $raw = '';
                foreach (['j1', 'j2', 'j3', 'j4', 'j5', 'j6', 'j7', 'j8'] as $k) {
                    $part = $r->$k ?? '';
                    if ($part === '' || $part === null) break;
                    $raw .= $part;
                }
                $j = json_decode($raw !== '' ? $raw : '{}', true, 512, JSON_PARTIAL_OUTPUT_ON_ERROR) ?? [];

                $racikan = false;
                $hasResep = false;
                foreach ($j['eresepHdr'] ?? [] as $h) {
                    if ((int) ($h['slsNo'] ?? 0) === (int) $r->sls_no) {
                        $hasResep = true;
                        $racikan = (bool) ($h['isRacikan'] ?? false) || !empty($h['eresepRacikan'] ?? []);
                        break;
                    }
                }
                if (!$hasResep && !empty($j['eresepRacikan'])) {
                    $racikan = true;
                    $hasResep = true;
                }
                $noAntrian = (int) ($r->no_antrian ?? 0);

                // DTO bersih — JSON dibuang.
                return (object) [
                    'status' => strtoupper((string) ($r->status ?? 'A')),
                    'reg_name' => (string) ($r->reg_name ?? '-'),
                    'dr_name' => (string) ($r->dr_name ?? ''),
                    'room_name' => (string) ($r->room_name ?? ''),
                    'bangsal_name' => (string) ($r->bangsal_name ?? ''),
                    'no_antrian' => $noAntrian,
                    'has_resep' => $hasResep,
                    'racikan' => $racikan,
                ];
            });

        $sorter = fn($r) => [$r->no_antrian > 0 ? 0 : 1, $r->no_antrian];

        return [
            'rowsAntri' => $rows->filter(fn($r) => $r->status === 'A')->sortBy($sorter)->values(),
            'rowsLunas' => $rows->filter(fn($r) => $r->status === 'L')->sortByDesc($sorter)->values(),
            'refDateTime' => Carbon::now(env('APP_TIMEZONE'))->format('d-m-Y H:i:s'),
        ];
    }
};
?>

<div class="relative overflow-hidden bg-canvas h-screen">

    <style>
        @keyframes flash-pulse { 0%,96%,100%{opacity:1} 97%,99%{opacity:0.35} }
        .blink-soft { animation: flash-pulse 5s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .blink-soft { animation: none !important; } }
    </style>

    {{-- Watermark logogram brand (pola welcome) --}}
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img src="{{ asset('images/Logogram black solid.png') }}" alt=""
            class="absolute -right-[6%] -bottom-[12%] w-[34rem] opacity-5">
    </div>

    <div class="relative z-10 flex flex-col h-full w-full px-4 pt-4 pb-2"
        x-data="autoScroller({ step: 1, interval: 25, waitTop: 800, waitBottom: 1200 })"
        x-init="start()">

        <div class="flex items-end justify-between gap-3 mb-2">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/Logo Persegi.png') }}" alt="RSI Madinah"
                    class="h-16 w-auto shrink-0">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-brand-green">{{ $myTitle }}</h1>
                    <p class="text-sm text-body">{{ $mySnipt }}</p>
                    {{-- Garis aksen brand: segmen lime (Graphic Standard Manual) --}}
                    <div class="mt-1.5 h-0.5 w-28 bg-brand-lime"></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-[11px] text-muted">Data terakhir</div>
                <div class="text-sm font-mono font-semibold text-body-strong">{{ $refDateTime }}</div>
            </div>
        </div>

        <div class="p-3 mb-3 text-sm sm:text-base border rounded-lg bg-warning-tint border-warning/30 text-warning-deep blink-soft">
            Obat untuk pasien rawat inap akan diantar ke bangsal masing-masing. Resep <strong>racikan</strong>
            memerlukan tambahan waktu <strong>±15–30 menit</strong>.
        </div>

        <div class="grid grid-cols-2 gap-3 mb-2">
            <div class="px-4 py-2 rounded-lg bg-error-tint border border-error/30">
                <h2 class="text-base font-bold text-error-deep">🕐 Proses Resep
                    <span class="ml-1 text-sm font-mono text-error-deep/70">({{ $rowsAntri->count() }})</span>
                </h2>
            </div>
            <div class="px-4 py-2 rounded-lg bg-brand-green/10 border border-brand-green/30">
                <h2 class="text-base font-bold text-brand-green">✅ Sudah Selesai
                    <span class="ml-1 text-sm font-mono text-brand-green/70">({{ $rowsLunas->count() }})</span>
                </h2>
            </div>
        </div>

        <div class="flex-1 min-h-0 overflow-auto"
            x-ref="scroller"
            x-on:mouseenter="pause()"
            x-on:mouseleave="resume()">
            <div class="grid grid-cols-2 gap-4">

                {{-- KIRI: ANTRI --}}
                <div>
                    <table class="min-w-full text-sm text-left text-body-strong border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[36%]"><col class="w-[46%]"><col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-ink uppercase bg-surface-soft">
                            <tr><th class="px-3 py-2">Nama Pasien</th><th class="px-3 py-2">Bangsal / Dokter</th><th class="px-2 py-2 text-center">Antrian</th></tr>
                        </thead>
                        <tbody class="bg-canvas">
                            @forelse ($rowsAntri as $row)
                                <tr class="border-b hover:bg-surface-soft">
                                    <td class="px-3 py-2.5 font-semibold text-ink truncate">{{ $row->reg_name }}</td>
                                    <td class="px-3 py-2.5 truncate">
                                        <span class="text-xs text-muted">{{ $row->bangsal_name ?: '-' }}{{ $row->room_name ? ' · ' . $row->room_name : '' }}</span><br>
                                        {{ $row->dr_name ?: '-' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-center">
                                        <div class="text-2xl font-bold text-ink leading-none">{{ $row->no_antrian ?: '—' }}</div>
                                        <div class="mt-1">
                                            @if ($row->has_resep)
                                                <x-badge :variant="$row->racikan ? 'warning' : 'success'">{{ $row->racikan ? 'racikan' : 'non racikan' }}</x-badge>
                                            @else
                                                <x-badge variant="danger">menunggu resep</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted-soft italic">Tidak ada antrian aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- KANAN: LUNAS --}}
                <div>
                    <table class="min-w-full text-sm text-left text-body-strong border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[36%]"><col class="w-[46%]"><col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-ink uppercase bg-surface-soft">
                            <tr><th class="px-3 py-2">Nama Pasien</th><th class="px-3 py-2">Bangsal / Dokter</th><th class="px-2 py-2 text-center">Status</th></tr>
                        </thead>
                        <tbody class="bg-canvas">
                            @forelse ($rowsLunas as $row)
                                <tr class="border-b hover:bg-surface-soft">
                                    <td class="px-3 py-2.5 font-semibold text-ink truncate">{{ $row->reg_name }}</td>
                                    <td class="px-3 py-2.5 truncate">
                                        <span class="text-xs text-muted">{{ $row->bangsal_name ?: '-' }}{{ $row->room_name ? ' · ' . $row->room_name : '' }}</span><br>
                                        {{ $row->dr_name ?: '-' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-center">
                                        <div class="text-sm font-bold text-brand-green leading-none">Diantar</div>
                                        <div class="mt-1">
                                            @if ($row->has_resep)
                                                <x-badge :variant="$row->racikan ? 'warning' : 'success'">{{ $row->racikan ? 'racikan' : 'non racikan' }}</x-badge>
                                            @else
                                                <x-badge variant="alternative">—</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted-soft italic">Belum ada yang selesai</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($rowsAntri->count() === 0 && $rowsLunas->count() === 0)
                <div class="w-full p-6 text-center text-sm text-muted">Belum ada antrian resep RI hari ini.</div>
            @endif
        </div>

        {{-- Footer brand — garis hairline + segmen lime kiri (Graphic Standard Manual) --}}
        <div class="relative h-px bg-hairline mt-2">
            <div class="absolute left-0 -top-px h-0.5 w-20 bg-brand-lime"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[11px] text-muted-soft">
            <span>SIRus · Antrian Apotek RI</span>
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

