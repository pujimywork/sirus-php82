<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /**
     * ════════════════════════════════════════════════════════════════════════
     *  DISPLAY PUBLIK — JADWAL POLI HARI INI + COUNTER PASIEN
     * ════════════════════════════════════════════════════════════════════════
     *
     *  TUJUAN
     *  ──────
     *  Layar TV di lobi RJ → pasien lihat jadwal dokter hari ini lengkap
     *  dengan status pelayanan (sudah berapa pasien? berapa sisa kuota?).
     *
     *  WORKFLOW DATA (3 sumber gabung)
     *  ───────────────────────────────
     *
     *  ┌──────────────────────────────────────────────────────────────────┐
     *  │ 1. JADWAL (scmst_scpolis)                                         │
     *  │    Master jadwal dokter per hari (day_id 1-7 = Senin-Minggu) +    │
     *  │    kuota harian. day_id sekarang = Carbon::dayOfWeekIso.          │
     *  │                                                                    │
     *  │ 2. PASIEN HARI INI (rsview_rjkasir)                               │
     *  │    Group by (poli_id, dr_id) → split berdasarkan tahap pelayanan: │
     *  │     • Proses Dilayani = rj_status='A' AND                         │
     *  │                 waktu_selesai_pelayanan IS NULL                   │
     *  │                 (baru daftar / sedang diperiksa)                  │
     *  │     • Selesai = waktu_selesai_pelayanan IS NOT NULL OR            │
     *  │                 rj_status='L' (keluar ruang periksa / sudah pulang)│
     *  │                                                                    │
     *  │ 3. BOOKING JKN (referensi_mobilejkn_bpjs)                         │
     *  │    Tabel pre-arrival booking dari BPJS Mobile JKN. Field penting: │
     *  │     • tanggalperiksa, kodepoli (=kd_poli_bpjs),                   │
     *  │       kodedokter (=kd_dr_bpjs), status                            │
     *  │     • status: 'Belum'   = booking belum checkin                   │
     *  │               'Checkin' = sudah datang & register                 │
     *  │               'Batal'   = dibatalkan (di-exclude)                 │
     *  │    Mapping kode BPJS → local lewat rsmst_polis.kd_poli_bpjs &     │
     *  │    rsmst_doctors.kd_dr_bpjs.                                      │
     *  └──────────────────────────────────────────────────────────────────┘
     *
     *  STATE SESI (jam sekarang vs jadwal)
     *  ───────────────────────────────────
     *  • BUKA  = mulai_praktek ≤ jam now ≤ selesai_praktek (ring brand-green)
     *  • AKAN  = jam now < mulai_praktek (amber badge)
     *  • TUTUP = jam now > selesai_praktek (gray, row di-dim)
     *
     *  HUNIAN
     *  ──────
     *  occ = (terdaftar / kuota) * 100
     *  Bar warna: <70% brand-green · 70-89% amber · ≥90% rose
     *  "Sisa" warning amber kalau ≤5 slot.
     *
     *  LAYOUT
     *  ──────
     *  Top: 5 stat box (Jadwal · Kuota · Booking · Proses Dilayani · Selesai)
     *  Grid card responsive: 1/2/3/4/5 cols (mobile → 2xl monitor)
     *  Per card: badge sesi · dokter · jam · bar hunian · 4-counter
     *  (Booking | Proses | Selesai | Sisa). Variable internal masih pakai
     *  $antri / n_antri supaya kode tetap ringkas.
     *
     *  PRIVASI
     *  ───────
     *  Aman — tidak ada nama pasien / NIK / EMR di display ini. Hanya
     *  agregat count + jadwal dokter (info publik).
     * ════════════════════════════════════════════════════════════════════════
     */
    public string $myTitle = 'Jadwal Poli Hari Ini';

    public function placeholder(): string
    {
        return '<div class="p-8 text-center text-gray-400">Memuat jadwal…</div>';
    }

    public function with(): array
    {
        $now = Carbon::now(env('APP_TIMEZONE'));
        $today = $now->format('Y-m-d');
        $dayId = $now->dayOfWeekIso; // 1=Senin .. 7=Minggu
        $nowTime = $now->format('H:i:s');

        // Count pasien per (poli_id, dr_id) hari ini, dipisah berdasarkan tahap pelayanan.
        // Bukan cuma pakai rj_status, tapi gabungkan dgn waktu_selesai_pelayanan supaya akurat:
        //   - Proses Dilayani (variabel: $antri / n_antri):
        //         rj_status='A' AND waktu_selesai_pelayanan IS NULL
        //         (mencakup yang baru daftar belum masuk ruang + yang sedang diperiksa)
        //   - Selesai = waktu_selesai_pelayanan IS NOT NULL OR rj_status='L'
        //             (sudah keluar ruang periksa — diperiksa selesai, atau sudah lunas/pulang)
        $counts = DB::table('rsview_rjkasir')->select('poli_id', 'dr_id', DB::raw("count(case when rj_status = 'A' and waktu_selesai_pelayanan is null then 1 end) as n_antri"), DB::raw("count(case when waktu_selesai_pelayanan is not null or rj_status = 'L' then 1 end) as n_selesai"), DB::raw('count(*) as n_total'))->where(DB::raw("to_char(rj_date,'yyyy-mm-dd')"), $today)->where('klaim_id', '!=', 'KR')->groupBy('poli_id', 'dr_id')->get()->keyBy(fn($r) => $r->poli_id . '|' . $r->dr_id);

        // Total booking JKN untuk pelayanan hari ini — termasuk yang belum datang.
        // Pola sumber sama dgn modul Daftar RJ (referensi_mobilejkn_bpjs).
        // Status: 'Belum' = belum checkin, 'Checkin' = sudah datang, 'Batal' = dibatalkan (di-exclude).
        $bookings = DB::table('referensi_mobilejkn_bpjs as b')->leftJoin('rsmst_polis as p', 'p.kd_poli_bpjs', '=', 'b.kodepoli')->leftJoin('rsmst_doctors as d', 'd.kd_dr_bpjs', '=', 'b.kodedokter')->where('b.tanggalperiksa', $today)->where('b.status', '!=', 'Batal')->select('p.poli_id', 'd.dr_id', 'b.status', DB::raw('count(*) as n'))->groupBy('p.poli_id', 'd.dr_id', 'b.status')->get()->groupBy(fn($r) => ($r->poli_id ?? '?') . '|' . ($r->dr_id ?? '?'));

        // Jadwal hari ini dari scmst_scpolis (status_=1 aktif).
        $jadwal = DB::table('scmst_scpolis as sc')
            ->leftJoin('rsmst_polis as p', 'sc.poli_id', '=', 'p.poli_id')
            ->leftJoin('rsmst_doctors as d', 'sc.dr_id', '=', 'd.dr_id')
            ->where('sc.day_id', $dayId)
            ->where('sc.sc_poli_status_', '1')
            ->select(['sc.poli_id', 'p.poli_desc', 'sc.dr_id', 'd.dr_name', 'sc.mulai_praktek', 'sc.selesai_praktek', 'sc.kuota', 'sc.sc_poli_ket'])
            ->orderBy('sc.mulai_praktek')
            ->orderBy('p.poli_desc')
            ->get()
            ->map(function ($r) use ($counts, $bookings, $nowTime) {
                $key = $r->poli_id . '|' . $r->dr_id;
                $c = $counts->get($key);
                $antri = (int) ($c->n_antri ?? 0);
                $selesai = (int) ($c->n_selesai ?? 0);
                $terdaftar = (int) ($c->n_total ?? 0);

                $b = $bookings->get($key, collect());
                $bookingBelum = (int) $b->where('status', 'Belum')->sum('n');
                $bookingCheckin = (int) $b->where('status', 'Checkin')->sum('n');
                $bookingTotal = $bookingBelum + $bookingCheckin;

                $kuota = (int) ($r->kuota ?: 0);
                $sisa = max(0, $kuota - $terdaftar);

                // Status sesi berdasarkan jam sekarang.
                $mulai = $r->mulai_praktek ?: '00:00:00';
                $selesaiJam = $r->selesai_praktek ?: '23:59:59';
                if ($nowTime < $mulai) {
                    $sesi = 'akan';
                } elseif ($nowTime > $selesaiJam) {
                    $sesi = 'tutup';
                } else {
                    $sesi = 'buka';
                }

                $occ = $kuota > 0 ? round(($terdaftar / $kuota) * 100) : 0;

                return [
                    'poli_id' => $r->poli_id,
                    'poli_desc' => $r->poli_desc,
                    'dr_id' => $r->dr_id,
                    'dr_name' => $r->dr_name ?: '(belum dipetakan)',
                    'mulai' => substr($mulai, 0, 5),
                    'selesai_jam' => substr($selesaiJam, 0, 5),
                    'kuota' => $kuota,
                    'antri' => $antri,
                    'selesai' => $selesai,
                    'booking' => $bookingTotal,
                    'booking_belum' => $bookingBelum,
                    'booking_checkin' => $bookingCheckin,
                    'terdaftar' => $terdaftar,
                    'sisa' => $sisa,
                    'occ' => $occ,
                    'sesi' => $sesi,
                ];
            });

        $totalAntri = $jadwal->sum('antri');
        $totalSelesai = $jadwal->sum('selesai');
        $totalBooking = $jadwal->sum('booking');
        $totalBookingBelum = $jadwal->sum('booking_belum');
        $totalKuota = $jadwal->sum('kuota');
        $totalTerdaftar = $jadwal->sum('terdaftar');

        return [
            'jadwal' => $jadwal->values(),
            'totalAntri' => $totalAntri,
            'totalSelesai' => $totalSelesai,
            'totalBooking' => $totalBooking,
            'totalBookingBelum' => $totalBookingBelum,
            'totalKuota' => $totalKuota,
            'totalTerdaftar' => $totalTerdaftar,
            'totalJadwal' => $jadwal->count(),
            'hariIni' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'jamNow' => $now->format('H:i:s'),
        ];
    }
};
?>

@extends('layouts.app-welcome')

@section('content')
    <div class="bg-white min-h-screen">

        <div class="w-full px-4 pt-4 pb-2" x-data="autoScroller({ step: 1, interval: 25, waitTop: 800, waitBottom: 1200 })" x-init="start()">

            {{-- Header — 3 kolom: TITLE kiri · INFO BAR tengah · JAM kanan --}}
            <div class="flex flex-wrap items-stretch justify-between gap-3 mb-3">
                {{-- KIRI: title + tanggal --}}
                <div class="shrink-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">{{ $myTitle }}</h1>
                    <p class="text-sm text-gray-600 capitalize">{{ $hariIni }}</p>
                </div>

                {{-- TENGAH: stats (atas) + legend (bawah), grow ke kanan, isi rata tengah --}}
                <div class="flex-1 min-w-[280px] flex flex-col items-end justify-center gap-0.5 px-3 py-1.5">
                    {{-- Stats real-time — neutral gray, hanya angka bold mono (selaras gaya "Sekarang") --}}
                    <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-0.5 text-xs text-gray-500">
                        <span>Jadwal <span class="font-bold font-mono text-gray-700">{{ $totalJadwal }}</span></span>
                        <span class="text-gray-300">·</span>
                        <span>Kuota <span class="font-bold font-mono text-gray-700">{{ $totalKuota }}</span></span>
                        <span class="text-gray-300">·</span>
                        <span title="Total booking JKN (Belum datang + Sudah Checkin)">
                            Booking <span class="font-bold font-mono text-gray-700">{{ $totalBooking }}</span>
                            @if ($totalBookingBelum > 0)
                                <span class="text-[10px] text-gray-400">({{ $totalBookingBelum }} blm dtg)</span>
                            @endif
                        </span>
                        <span class="text-gray-300">·</span>
                        <span title="Masih dalam proses (belum keluar ruang periksa)">
                            Proses Dilayani <span class="font-bold font-mono text-gray-700">{{ $totalAntri }}</span>
                        </span>
                        <span class="text-gray-300">·</span>
                        <span title="Sudah selesai pelayanan / pulang">
                            Selesai <span class="font-bold font-mono text-gray-700">{{ $totalSelesai }}</span>
                        </span>
                    </div>

                    {{-- Legend ringkas (sub-row hint) — neutral --}}
                    <div class="flex flex-wrap items-center justify-end gap-x-2.5 gap-y-0.5 text-[10px] text-gray-400">
                        <span class="font-semibold uppercase tracking-wide">Ket:</span>
                        <span><span class="font-semibold text-gray-600">Booking</span> = daftar JKN</span>
                        <span class="text-gray-300">·</span>
                        <span><span class="font-semibold text-gray-600">Proses</span> = antri / diperiksa</span>
                        <span class="text-gray-300">·</span>
                        <span><span class="font-semibold text-gray-600">Selesai</span> = sudah diperiksa</span>
                        <span class="text-gray-300">·</span>
                        <span><span class="font-semibold text-gray-600">Sisa</span> = slot tersisa</span>
                    </div>
                </div>

                {{-- KANAN: jam sekarang --}}
                <div class="shrink-0 text-right self-center">
                    <div class="text-[11px] text-gray-500">Sekarang</div>
                    <div class="text-sm font-mono font-semibold text-gray-700">{{ $jamNow }}</div>
                </div>
            </div>

            {{-- Grid cards — flow ke kanan supaya tampil dalam 1 layar --}}
            <div class="h-[calc(100vh-220px)] overflow-auto" x-ref="scroller" x-on:mouseenter="pause()"
                x-on:mouseleave="resume()">

                @if ($jadwal->isEmpty())
                    <div class="w-full p-8 text-center text-sm text-gray-500">
                        Tidak ada jadwal poli untuk hari ini.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2">
                        @foreach ($jadwal as $it)
                            @php
                                $sesiClass = match ($it['sesi']) {
                                    'buka' => 'bg-brand-green text-white',
                                    'akan' => 'bg-amber-400 text-white',
                                    'tutup' => 'bg-gray-300 text-gray-600',
                                };
                                $sesiLabel = match ($it['sesi']) {
                                    'buka' => 'BUKA',
                                    'akan' => 'AKAN',
                                    'tutup' => 'TUTUP',
                                };
                                $occBar =
                                    $it['occ'] >= 90
                                        ? 'bg-rose-500'
                                        : ($it['occ'] >= 70
                                            ? 'bg-amber-400'
                                            : 'bg-brand-green');
                                $occCls =
                                    $it['occ'] >= 90
                                        ? 'text-rose-600'
                                        : ($it['occ'] >= 70
                                            ? 'text-amber-600'
                                            : 'text-brand-green');
                                $dim = $it['sesi'] === 'tutup';
                                $cardBg = $dim
                                    ? 'bg-gray-50 border-gray-200'
                                    : ($it['sesi'] === 'buka'
                                        ? 'bg-white border-brand-green/40 ring-1 ring-brand-green/10'
                                        : 'bg-white border-gray-200');
                            @endphp
                            <div class="rounded-lg border {{ $cardBg }} overflow-hidden flex flex-col">
                                {{-- Header card: poli + sesi --}}
                                <div class="flex items-center justify-between gap-2 px-3 py-1.5 border-b border-gray-100">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-700 truncate"
                                        title="{{ $it['poli_desc'] }}">
                                        {{ $it['poli_desc'] ?: 'POLI -' }}
                                    </h3>
                                    <span
                                        class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold tracking-wider {{ $sesiClass }}">
                                        {{ $sesiLabel }}
                                    </span>
                                </div>

                                {{-- Body card --}}
                                <div class="px-3 py-2 flex flex-col gap-1.5 flex-1">
                                    {{-- Dokter + jam --}}
                                    <div>
                                        <div class="text-sm font-semibold leading-tight truncate {{ $dim ? 'text-gray-500' : 'text-gray-900' }}"
                                            title="{{ $it['dr_name'] }}">
                                            {{ $it['dr_name'] }}
                                        </div>
                                        <div class="text-[11px] font-mono text-gray-500 mt-0.5">
                                            {{ $it['mulai'] }} – {{ $it['selesai_jam'] }}
                                        </div>
                                    </div>

                                    {{-- Progress bar + terdaftar --}}
                                    <div class="flex items-center gap-1.5">
                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5 min-w-[40px]">
                                            <div class="{{ $occBar }} h-1.5 rounded-full transition-all"
                                                style="width: {{ min(100, $it['occ']) }}%"></div>
                                        </div>
                                        <span
                                            class="text-[10px] font-mono font-bold {{ $occCls }} shrink-0">{{ $it['occ'] }}%</span>
                                    </div>

                                    {{-- 4 counter row: Booking · Proses · Selesai · Sisa
                                         Setiap cell pakai flex-col + justify-between + min-height supaya
                                         label rata atas · angka rata tengah · subteks rata bawah,
                                         konsisten lintas kolom meski ada/tidak subteks. --}}
                                    @php
                                        $sisaEfektif = max(0, $it['sisa'] - $it['booking_belum']);
                                        $tooltipSisa =
                                            $it['booking_belum'] > 0
                                                ? "Sisa kuota: {$it['sisa']} · Estimasi setelah {$it['booking_belum']} booking masuk: {$sisaEfektif}"
                                                : 'Slot kuota yang masih tersedia';
                                    @endphp
                                    <div class="grid grid-cols-4 gap-0.5 mt-1 pt-1.5 border-t border-gray-100">

                                        {{-- Booking --}}
                                        <div class="flex flex-col items-center justify-between text-center min-h-[3.5rem] py-0.5"
                                            title="Pasien daftar online via aplikasi JKN — Belum datang: {{ $it['booking_belum'] }} · Sudah checkin: {{ $it['booking_checkin'] }}">
                                            <div
                                                class="text-[8px] uppercase tracking-wider text-blue-700/80 font-bold leading-none whitespace-nowrap">
                                                Booking
                                            </div>
                                            <div
                                                class="text-base font-bold font-mono leading-none {{ $it['booking'] > 0 ? 'text-blue-600' : 'text-gray-300' }}">
                                                {{ $it['booking'] }}
                                            </div>
                                            <div class="text-[9px] text-blue-500/80 leading-tight whitespace-nowrap">
                                                @if ($it['booking_belum'] > 0)
                                                    {{ $it['booking_belum'] }} blm dtg
                                                @else
                                                    &nbsp;
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Proses Dilayani (label dipendekkan jadi "Proses" supaya 1 baris) --}}
                                        <div class="flex flex-col items-center justify-between text-center min-h-[3.5rem] py-0.5 border-l border-gray-100"
                                            title="Proses Dilayani — sedang antri di poli atau sedang diperiksa dokter">
                                            <div
                                                class="text-[8px] uppercase tracking-wider text-rose-700/70 font-bold leading-none whitespace-nowrap">
                                                Dilayani
                                            </div>
                                            <div
                                                class="text-base font-bold font-mono leading-none {{ $it['antri'] > 0 ? 'text-rose-600' : 'text-gray-300' }}">
                                                {{ $it['antri'] }}
                                            </div>
                                            <div class="text-[9px] leading-tight">&nbsp;</div>
                                        </div>

                                        {{-- Selesai --}}
                                        <div class="flex flex-col items-center justify-between text-center min-h-[3.5rem] py-0.5 border-l border-gray-100"
                                            title="Pasien yang sudah keluar ruang periksa (selesai diperiksa atau sudah lunas)">
                                            <div
                                                class="text-[8px] uppercase tracking-wider text-brand-green/80 font-bold leading-none whitespace-nowrap">
                                                Selesai
                                            </div>
                                            <div
                                                class="text-base font-bold font-mono leading-none {{ $it['selesai'] > 0 ? 'text-brand-green' : 'text-gray-300' }}">
                                                {{ $it['selesai'] }}
                                            </div>
                                            <div class="text-[9px] leading-tight">&nbsp;</div>
                                        </div>

                                        {{-- Sisa --}}
                                        <div class="flex flex-col items-center justify-between text-center min-h-[3.5rem] py-0.5 border-l border-gray-100"
                                            title="{{ $tooltipSisa }}">
                                            <div
                                                class="text-[8px] uppercase tracking-wider text-gray-500 font-bold leading-none whitespace-nowrap">
                                                Sisa
                                            </div>
                                            <div
                                                class="text-base font-bold font-mono leading-none {{ $it['sisa'] <= 5 ? 'text-amber-600' : 'text-gray-700' }}">
                                                {{ $it['sisa'] }}
                                            </div>
                                            <div class="text-[9px] text-gray-400 leading-tight whitespace-nowrap">
                                                @if ($it['booking_belum'] > 0)
                                                    ≈ {{ $sisaEfektif }} efektif
                                                @else
                                                    &nbsp;
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Kuota footer --}}
                                    <div class="text-[9px] text-gray-400 text-right mt-auto pt-1">
                                        Kuota: <span
                                            class="font-mono font-semibold text-gray-500">{{ $it['kuota'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                <span>SIRus · Jadwal Poli RJ</span>
                <span>© {{ date('Y') }} RSI Madinah</span>
            </div>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                // re-use definisi yang sama dgn antrian-apotek-rj; aman didefinisikan ulang
                if (Alpine.$data?.autoScroller) return;
                Alpine.data('autoScroller', (opts = {}) => ({
                    step: opts.step ?? 1,
                    interval: opts.interval ?? 25,
                    waitTop: opts.waitTop ?? 800,
                    waitBottom: opts.waitBottom ?? 1200,
                    timer: null,
                    running: false,
                    start() {
                        const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
                        if (mql.matches) return;
                        this.running = true;
                        if (window.Livewire) Livewire.hook('morph.updated', () => this.restart());
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
                        if (this.timer) {
                            clearTimeout(this.timer);
                            this.timer = null;
                        }
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
                        if (fromTop) this.timer = setTimeout(() => this.tick(), this.waitTop);
                        else this.tick();
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
@endsection
