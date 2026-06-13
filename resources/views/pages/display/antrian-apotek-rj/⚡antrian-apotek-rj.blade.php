<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('layouts.app-fullscreen')] class extends Component {
    /**
     * ════════════════════════════════════════════════════════════════════════
     *  DISPLAY PUBLIK — ANTRIAN APOTEK RAWAT JALAN
     * ════════════════════════════════════════════════════════════════════════
     *
     *  TUJUAN
     *  ──────
     *  Layar TV di area tunggu apotek RJ → pasien lihat antrian resep-nya
     *  diproses sampai mana (mirip tampilan Aplicares BPJS publik).
     *
     *  WORKFLOW DATA
     *  ─────────────
     *  Pasien daftar di loket  →  diperiksa dokter  →  dokter input eresep
     *  →  resep dikirim ke apotek (status="A" + noAntrianApotek terisi)
     *  →  apoteker telaah & racik  →  pasien dipanggil (status="L")
     *
     *  SUMBER
     *  ──────
     *  • View   : rsview_rjkasir  (single source — sudah join pasien/poli/dr)
     *  • JSON   : datadaftarpolirj_json (Oracle CLOB, dipotong 8x4000 chars
     *             via DBMS_LOB.SUBSTR supaya tidak return OCILob handle)
     *  • Filter : rj_date = today, klaim_id != 'KR' (exclude kredit ditolak),
     *             rj_status IN ('A','L')
     *
     *  JSON FIELDS YG DIPAKAI (hanya 4 — selebihnya dibuang utk privasi)
     *  ────────────────────────────────────────────────────────────────
     *  • noAntrianApotek.noAntrian   → nomor antrian apotek
     *  • eresep                       → flag dokter sudah input resep
     *  • eresepRacikan                → flag ada racikan (waktu +15-30')
     *  • taskIdPelayanan.taskId5/6    → timestamp utk sort fallback
     *
     *  STATUS UI
     *  ─────────
     *  • Kolom kiri  "🕐 Proses Resep"  ← rj_status='A'  (sedang diproses)
     *  • Kolom kanan "✅ Sudah Selesai" ← rj_status='L'  (resep diambil)
     *
     *  SORT
     *  ────
     *  Composite: yang punya nomor antrian apotek di atas, lalu by nomor
     *  antrian ASC. Yang belum ada nomor: by eresep/admin status →
     *  taskId timestamp.
     *
     *  PRIVASI (halaman publik!)
     *  ─────────────────────────
     *  JSON full EMR (anamnesa, diagnosis, SEP, asuhan kep, dll) di-decode
     *  server-side. Hanya 4 field di atas yang di-extract ke DTO bersih.
     *  Field JSON dibuang sebelum return ke view → tidak pernah serialized
     *  ke DOM/snapshot Livewire.
     * ════════════════════════════════════════════════════════════════════════
     */
    public string $myTitle = 'Antrian Resep Rawat Jalan';
    public string $mySnipt = 'Antrian Apotek — Pasien Rawat Jalan';

    public function placeholder(): string
    {
        return '<div class="p-8 text-center text-muted-soft">Memuat antrian…</div>';
    }

    public function with(): array
    {
        $refDate = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y');

        // Halaman PUBLIK. datadaftarpolirj_json mengandung full EMR (anamnesa, diagnosis,
        // eresep detail, SEP BPJS, asuhan keperawatan, leveling dokter, dll).
        // Decode server-side, ambil HANYA field yg perlu ditampilkan, lalu buang JSON.
        // Object ke view = DTO bersih tanpa data sensitif.
        $rows = DB::table('rsview_rjkasir')
            ->select([
                'rj_status',
                'reg_name',
                'poli_desc',
                'dr_name',
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000,     1) AS j1'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000,  4001) AS j2'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000,  8001) AS j3'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000, 12001) AS j4'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000, 16001) AS j5'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000, 20001) AS j6'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000, 24001) AS j7'),
                DB::raw('DBMS_LOB.SUBSTR(datadaftarpolirj_json, 4000, 28001) AS j8'),
            ])
            ->whereIn(DB::raw("nvl(rj_status,'A')"), ['A', 'L'])
            ->where('klaim_id', '!=', 'KR')
            ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)
            ->get()
            ->map(function ($r) {
                $raw = '';
                foreach (['j1', 'j2', 'j3', 'j4', 'j5', 'j6', 'j7', 'j8'] as $k) {
                    $part = $r->$k ?? '';
                    if ($part === '' || $part === null) {
                        break;
                    }
                    $raw .= $part;
                }
                $j = json_decode($raw !== '' ? $raw : '[]', true, 512, JSON_PARTIAL_OUTPUT_ON_ERROR) ?? [];

                $antrian = (int) ($j['noAntrianApotek']['noAntrian'] ?? 0);
                $task5 = isset($j['taskIdPelayanan']['taskId5']) ? strtotime(str_replace('/', '-', $j['taskIdPelayanan']['taskId5'])) : PHP_INT_MAX;
                $task6 = isset($j['taskIdPelayanan']['taskId6']) ? strtotime(str_replace('/', '-', $j['taskIdPelayanan']['taskId6'])) : PHP_INT_MAX;
                $rjDateTime = isset($j['rjDate']) ? strtotime(str_replace('/', '-', $j['rjDate'])) : PHP_INT_MAX;

                // DTO bersih — full JSON dibuang dari memory & tidak dilewatkan ke view.
                return (object) [
                    'rj_status' => (string) ($r->rj_status ?? 'A'),
                    'reg_name' => (string) ($r->reg_name ?? '-'),
                    'poli_desc' => (string) ($r->poli_desc ?? ''),
                    'dr_name' => (string) ($r->dr_name ?? ''),
                    'antrian' => $antrian,
                    'has_eresep' => isset($j['eresep']),
                    'racikan' => !empty($j['eresepRacikan'] ?? []),
                    '_q' => $antrian > 0 ? 1 : 0,
                    '_t5' => $task5,
                    '_t6' => $task6,
                    '_rj' => $rjDateTime,
                    '_admin' => isset($j['AdministrasiRj']) ? 1 : 0,
                ];
            });

        $sorter = fn($r) => [
            $r->_q,
            $r->_q ? $r->antrian : 1000,
            $r->_q ? 0 : ($r->has_eresep ? 1 : 0),
            $r->_q ? 0 : $r->_admin,
            $r->_q ? 0 : -$r->_t5,
            $r->_q ? 0 : -$r->_t6,
            $r->_q ? 0 : -$r->_rj,
        ];

        return [
            'rowsAntri' => $rows->filter(fn($r) => $r->rj_status === 'A')->sortBy($sorter)->values(),
            'rowsLunas' => $rows->filter(fn($r) => $r->rj_status === 'L')->sortByDesc($sorter)->values(),
            'refDateTime' => Carbon::now(env('APP_TIMEZONE'))->format('d-m-Y H:i:s'),
        ];
    }
};
?>

<div class="relative overflow-hidden bg-canvas h-screen">

    <style>
        @keyframes flash-pulse {
            0%, 96%, 100% { opacity: 1; }
            97%, 99% { opacity: 0.35; }
        }
        .blink-soft { animation: flash-pulse 5s linear infinite; }
        @media (prefers-reduced-motion: reduce) {
            .blink-soft { animation: none !important; }
        }
    </style>

    {{-- Watermark logogram brand (pola welcome) --}}
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img src="{{ asset('images/Logogram black solid.png') }}" alt=""
            class="absolute -right-[6%] -bottom-[12%] w-[34rem] opacity-5">
    </div>

    <div class="relative z-10 flex flex-col h-full w-full px-4 pt-4 pb-2"
        x-data="autoScroller({ step: 1, interval: 25, waitTop: 800, waitBottom: 1200 })"
        x-init="start()">

        {{-- Header --}}
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

        {{-- Notice racikan --}}
        <div class="p-3 mb-3 text-sm sm:text-base border rounded-lg bg-warning-tint border-warning/30 text-warning-deep blink-soft">
            Kepada pasien yang memiliki resep mengandung <strong>obat racikan</strong>, proses peracikan memerlukan
            waktu tambahan sekitar <strong>±15–30 menit</strong> demi ketepatan dosis dan keselamatan. Terima kasih
            atas kesabaran dan pengertiannya.
            <strong>Kami akan menginformasikan saat obat siap diambil.</strong>
        </div>

        {{-- Headers 2-kolom --}}
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

        {{-- Scroller area --}}
        <div class="flex-1 min-h-0 overflow-auto"
            x-ref="scroller"
            x-on:mouseenter="pause()"
            x-on:mouseleave="resume()">

            <div class="grid grid-cols-2 gap-4">

                {{-- KIRI: ANTRI --}}
                <div>
                    <table class="min-w-full text-sm text-left text-body-strong border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[40%]">
                            <col class="w-[42%]">
                            <col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-ink uppercase bg-surface-soft">
                            <tr>
                                <th class="px-3 py-2">Nama Pasien</th>
                                <th class="px-3 py-2">Dokter / Poli</th>
                                <th class="px-2 py-2 text-center">Antrian</th>
                            </tr>
                        </thead>
                        <tbody class="bg-canvas">
                            @forelse ($rowsAntri as $row)
                                <tr class="border-b hover:bg-surface-soft">
                                    <td class="px-3 py-2.5 font-semibold text-ink truncate">
                                        {{ $row->reg_name }}
                                    </td>
                                    <td class="px-3 py-2.5 truncate">
                                        <span class="text-xs text-muted">{{ $row->dr_name }}</span><br>
                                        {{ $row->poli_desc }}
                                    </td>
                                    <td class="px-2 py-2.5 text-center">
                                        <div class="text-2xl font-bold text-ink leading-none">{{ $row->antrian ?: '—' }}</div>
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
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted-soft italic">Tidak ada antrian aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- KANAN: LUNAS --}}
                <div>
                    <table class="min-w-full text-sm text-left text-body-strong border-collapse table-fixed">
                        <colgroup>
                            <col class="w-[40%]">
                            <col class="w-[42%]">
                            <col class="w-[18%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 text-xs text-ink uppercase bg-surface-soft">
                            <tr>
                                <th class="px-3 py-2">Nama Pasien</th>
                                <th class="px-3 py-2">Dokter / Poli</th>
                                <th class="px-2 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-canvas">
                            @forelse ($rowsLunas as $row)
                                <tr class="border-b hover:bg-surface-soft">
                                    <td class="px-3 py-2.5 font-semibold text-ink truncate">
                                        {{ $row->reg_name }}
                                    </td>
                                    <td class="px-3 py-2.5 truncate">
                                        <span class="text-xs text-muted">{{ $row->dr_name }}</span><br>
                                        {{ $row->poli_desc }}
                                    </td>
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
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted-soft italic">Belum ada yang selesai</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>

            @if ($rowsAntri->count() === 0 && $rowsLunas->count() === 0)
                <div class="w-full p-6 text-center text-sm text-muted">Belum ada antrian resep hari ini.</div>
            @endif

        </div>

        {{-- Footer brand — garis hairline + segmen lime kiri (Graphic Standard Manual) --}}
        <div class="relative h-px bg-hairline mt-2">
            <div class="absolute left-0 -top-px h-0.5 w-20 bg-brand-lime"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[11px] text-muted-soft">
            <span>SIRus · Antrian Apotek RJ</span>
            <span>© {{ date('Y') }} RSI Madinah</span>
        </div>

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('autoScroller', (opts = {}) => ({
                step: opts.step ?? 1,
                interval: opts.interval ?? 25,
                waitTop: opts.waitTop ?? 800,
                waitBottom: opts.waitBottom ?? 1200,
                timer: null,
                running: false,

                start() {
                    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
                    if (mql.matches) return; // hormati prefers-reduced-motion
                    this.running = true;
                    // restart kalau ada update Livewire (mis. dari livewire navigate atau dispatch lain)
                    if (window.Livewire) {
                        Livewire.hook('morph.updated', () => this.restart());
                    }
                    this.scrollLoop(true);
                },

                restart() {
                    this.pause();
                    const el = this.$refs.scroller;
                    if (el) el.scrollTop = 0;
                    this.resume(true);
                },

                pause() {
                    this.running = false;
                    if (this.timer) { clearTimeout(this.timer); this.timer = null; }
                },

                resume(fromTop = false) {
                    if (this.running) return;
                    this.running = true;
                    this.scrollLoop(fromTop);
                },

                scrollLoop(fromTop = false) {
                    if (!this.running) return;
                    const el = this.$refs.scroller;
                    if (!el) return;
                    if (fromTop) {
                        this.timer = setTimeout(() => this.tick(), this.waitTop);
                    } else {
                        this.tick();
                    }
                },

                tick() {
                    if (!this.running) return;
                    const el = this.$refs.scroller;
                    if (!el) return;

                    const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
                    if (atBottom) {
                        this.timer = setTimeout(() => {
                            el.scrollTop = 0;
                            this.timer = setTimeout(() => {
                                if (this.running) this.tick();
                            }, this.waitTop);
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

