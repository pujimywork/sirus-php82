<?php
// resources/views/pages/transaksi/kontrol/jadwal-kontrol/⚡jadwal-kontrol.blade.php
//
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  JADWAL KONTROL PASIEN — untuk PENDAFTARAN                           ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// TUJUAN
// ──────
// Petugas pendaftaran melihat jadwal kontrol (SKDP) pasien lintas RJ + RI
// dan MENGGESER tanggal kontrol bila perlu — kasus umum: jadwal kontrol
// kemarin, pasien baru datang hari ini → tanggal diubah ke hari ini lalu
// di-update ke BPJS (RencanaKontrol/update) supaya SEP kunjungan bisa terbit.
//
// SUMBER DATA
// ───────────
// JSON kontrol di rstxn_rjhdrs.datadaftarpolirj_json & rstxn_rihdrs.
// datadaftarri_json (key `kontrol`, dibuat dari modul SKDP RJ/RI).
// Oracle tidak support JSON_VALUE → filter pakai INSTR pattern-match:
//   • Tanggal tersimpan TER-ESCAPE oleh json_encode: "04\/07\/2026"
//     → pattern harus '"tglKontrol":"04\/07\/2026"'.
//   • Kontrol "betulan" = noKontrolRS terisi; objek kontrol default (belum
//     pernah disimpan via SKDP) punya noKontrolRS kosong → dibuang dengan
//     INSTR('"noKontrolRS":""') = 0.
//   • Dibatasi kunjungan 120 hari terakhir (kontrol dibuat saat kunjungan,\
//
//     ->whereRaw("h.{$sumber['kolomTgl']} >= sysdate - 120")
//     RJ: rj_date >= sysdate - 120
//     RI: entry_date >= sysdate - 120
//     default +8 hari) supaya scan CLOB tidak melebar ke data lama.
//
// DUA MODE PENCARIAN
// ──────────────────
// 1. Mode tanggal (default): filter exact tglKontrol = tanggal terpilih.
// 2. Mode pasien: ketik nama / No. RM ≥3 huruf → filter tanggal diabaikan,
//    tampil semua jadwal kontrol pasien tsb (utk pasien telat yang tidak
//    tahu tanggal jadwalnya).
//
// PENGEDITAN — SATU PINTU
// ───────────────────────
// Halaman ini READ-ONLY. Geser tanggal kontrol dilakukan lewat modal
// Riwayat Kontrol Pasien (komponen bersama riwayat-kontrol-pasien) —
// di sanalah push RencanaKontrol/update ke BPJS terjadi.

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /* ── Filter list ── */
    public string $tglFilter = '';
    public string $searchKeyword = '';
    public string $filterSumber = ''; // '' = semua | RJ | RI

    /** Saat halaman pertama dibuka: filter tanggal kontrol default = hari ini. */
    public function mount(): void
    {
        $this->tglFilter = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /**
     * Dipanggil saat modal Riwayat Kontrol selesai mengubah tanggal
     * (event 'riwayat-kontrol.updated'). unset($this->rows) membuang cache
     * #[Computed] supaya akses berikutnya query ulang ke DB — list langsung
     * menampilkan tanggal baru tanpa reload halaman.
     */
    #[On('riwayat-kontrol.updated')]
    public function refreshSetelahRiwayatUpdate(): void
    {
        unset($this->rows);
    }

    /**
     * Tombol Reset di toolbar (pola Apotek RJ): kosongkan pencarian & filter
     * sumber, kembalikan tanggal kontrol ke hari ini. rows otomatis re-query
     * karena property yang jadi dependensinya berubah.
     */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterSumber']);
        $this->tglFilter = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /**
     * Bangun pattern INSTR untuk mode tanggal: '"tglKontrol":"04\/07\/2026"'.
     * Slash HARUS di-escape jadi \/ karena json_encode menyimpannya begitu
     * di kolom CLOB — tanpa escape, INSTR tidak akan pernah match.
     */
    private function tglJsonPattern(string $tgl): string
    {
        return '"tglKontrol":"' . str_replace('/', '\/', $tgl) . '"';
    }

    /**
     * QUERY UTAMA LIST — hasilnya di-cache #[Computed] selama satu render.
     *
     * Alur kerja:
     * 1. Loop 2 sumber (RJ → rstxn_rjhdrs, RI → rstxn_rihdrs); sumber yang
     *    tidak dipilih di filter di-skip tanpa query.
     * 2. Query dasar per sumber: join rsmst_pasiens (identitas) + batas
     *    kunjungan 120 hari terakhir (biar scan CLOB JSON tidak melebar).
     * 3. Filter sesuai mode:
     *    • Mode pasien (ketikan ≥3 huruf) — persempit by nama/No. RM DULU
     *      (index-friendly), baru INSTR "ada kontrol & noKontrolRS terisi".
     *    • Mode tanggal — 1x INSTR pattern '"tglKontrol":"dd\/mm\/yyyy"';
     *      noKontrolRS kosong disaring belakangan saat decode PHP.
     * 4. Decode JSON di PHP (Oracle tanpa JSON_VALUE): ambil objek `kontrol`,
     *    buang yang noKontrolRS kosong, hitung umur realtime dari birth_date.
     * 5. Gabung RJ + RI ke satu collection, sort desc tglKontrol di PHP
     *    (tanggal di dalam JSON — tidak bisa ORDER BY di SQL).
     */
    #[Computed]
    public function rows()
    {
        $search = trim($this->searchKeyword);
        $modePasien = mb_strlen($search) >= 3;

        $sumberList = [['tabel' => 'rstxn_rjhdrs', 'kolomNo' => 'rj_no', 'kolomJson' => 'datadaftarpolirj_json', 'kolomTgl' => 'rj_date', 'sumber' => 'RJ'], ['tabel' => 'rstxn_rihdrs', 'kolomNo' => 'rihdr_no', 'kolomJson' => 'datadaftarri_json', 'kolomTgl' => 'entry_date', 'sumber' => 'RI']];

        $jadwalList = collect();

        foreach ($sumberList as $sumber) {
            // Filter sumber RJ/RI — skip query sumber yang tidak dipilih
            if ($this->filterSumber !== '' && $this->filterSumber !== $sumber['sumber']) {
                continue;
            }

            $query = DB::table($sumber['tabel'] . ' as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->whereRaw("h.{$sumber['kolomTgl']} >= sysdate - 120");

            if ($modePasien) {
                // Mode pasien: persempit by identitas DULU (index-friendly),
                // baru INSTR kontrol non-kosong di subset kecil itu.
                $keyword = mb_strtoupper($search);
                $query
                    ->where(function ($subQuery) use ($keyword) {
                        $subQuery->whereRaw('UPPER(p.reg_name) LIKE ?', ["%{$keyword}%"])->orWhereRaw('UPPER(h.reg_no) LIKE ?', ["%{$keyword}%"]);
                    })
                    ->whereRaw("INSTR(h.{$sumber['kolomJson']}, '\"noKontrolRS\"') > 0")
                    ->whereRaw("INSTR(h.{$sumber['kolomJson']}, '\"noKontrolRS\":\"\"') = 0");
            } else {
                // Mode tanggal: cukup 1 INSTR — pattern tanggal sudah mengimplikasikan
                // objek kontrol ada; noKontrolRS kosong disaring saat decode PHP.
                $query->whereRaw("INSTR(h.{$sumber['kolomJson']}, ?) > 0", [$this->tglJsonPattern($this->tglFilter)]);
            }

            $kunjunganList = $query
                ->select([
                    "h.{$sumber['kolomNo']} as trx_no", // nomor transaksi kunjungan (rj_no / rihdr_no)
                    'h.reg_no', // No. RM
                    'p.reg_name', // nama pasien
                    'p.sex',
                    'p.address',
                    DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                    DB::raw("to_char(h.{$sumber['kolomTgl']},'dd/mm/yyyy') as tgl_kunjungan"), // tgl kunjungan asal (rj_date / entry_date)
                    "h.{$sumber['kolomJson']} as json_daftar", // JSON pendaftaran — berisi objek `kontrol`
                ])
                ->get();

            foreach ($kunjunganList as $kunjungan) {
                $kontrol = json_decode($kunjungan->json_daftar ?? '{}', true)['kontrol'] ?? [];
                if (empty($kontrol['noKontrolRS'])) {
                    continue;
                }

                $jadwalList->push([
                    'sumber' => $sumber['sumber'],
                    'trx_no' => (string) $kunjungan->trx_no,
                    'reg_no' => $kunjungan->reg_no,
                    'reg_name' => $kunjungan->reg_name,
                    'sex' => $kunjungan->sex,
                    'address' => $kunjungan->address,
                    'birth_date' => $kunjungan->birth_date,
                    'tgl_kunjungan' => $kunjungan->tgl_kunjungan,
                    'tglKontrol' => $kontrol['tglKontrol'] ?? '-',
                    'poliKontrolDesc' => $kontrol['poliKontrolDesc'] ?? '-',
                    'drKontrolDesc' => $kontrol['drKontrolDesc'] ?? '-',
                    'noSKDPBPJS' => $kontrol['noSKDPBPJS'] ?? '',
                    'noSEP' => $kontrol['noSEP'] ?? '',
                ]);
            }
        }

        // Urut tanggal kontrol turun (terbaru di atas); tanggal tak terparse ditaruh paling bawah
        return $jadwalList
            ->sortByDesc(function ($jadwal) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $jadwal['tglKontrol'])->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values();
    }

    /**
     * Apakah tanggal kontrol sudah lewat dari hari ini (untuk highlight merah
     * + badge LEWAT di tabel). Bandingkan startOfDay supaya jam diabaikan;
     * tanggal yang gagal di-parse dianggap belum lewat.
     */
    public function sudahLewat(string $tgl): bool
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $tgl)
                ->startOfDay()
                ->lt(Carbon::now(config('app.timezone'))->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }
};
?>

<div>
    <x-page-title title="Jadwal Kontrol Pasien"
        subtitle="Lihat & geser tanggal rencana kontrol (SKDP) RJ/RI — update otomatis ke BPJS" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR — urutan filter mengikuti Daftar RJ: Search dulu, lalu Tanggal --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RM / Nama Pasien (min. 3 huruf — tanggal diabaikan)..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL KONTROL — tanpa tombol "Hari Ini", standar spt Daftar RJ
                         (Reset sudah mengembalikan tanggal ke hari ini) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal Kontrol" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="tglFilter" class="block w-full pl-10 sm:w-40"
                                placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER SUMBER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sumber" />
                        <x-select-input wire:model.live="filterSumber" class="w-full mt-1 sm:w-44">
                            <option value="">Semua (RJ + RI)</option>
                            <option value="RJ">RJ — Rawat Jalan</option>
                            <option value="RI">RI — Rawat Inap</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS — tombol standar Refresh + Reset (komponen) --}}
                    <x-toolbar-refresh-reset class="ml-auto" />

                    {{-- HINT mode aktif + rekap jumlah + keterangan (collapsible, default tertutup) --}}
                    @php
                        $rekap = $this->rows;
                        $rekapRj = $rekap->where('sumber', 'RJ')->count();
                        $rekapRi = $rekap->where('sumber', 'RI')->count();
                        $rekapLewat = $rekap->filter(fn($jadwal) => $this->sudahLewat($jadwal['tglKontrol']))->count();
                    @endphp
                    <div class="w-full space-y-1 text-xs text-muted dark:text-gray-400" x-data="{ ket: false }">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span>
                                @if (mb_strlen(trim($searchKeyword)) >= 3)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">Mode pencarian
                                        pasien</span>
                                    — filter tanggal diabaikan, menampilkan SEMUA jadwal kontrol pasien tersebut
                                    (dari kunjungan 120 hari terakhir).
                                @else
                                    Menampilkan jadwal kontrol tanggal
                                    <span
                                        class="font-semibold">{{ $tglFilter }}</span>{{ $filterSumber !== '' ? ' — sumber ' . $filterSumber . ' saja' : '' }}.
                                @endif
                            </span>

                            {{-- Rekap jumlah --}}
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 font-medium border border-hairline rounded-full bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                Total <span
                                    class="font-bold text-body dark:text-gray-200">{{ $rekap->count() }}</span>
                                <span class="text-gray-300">·</span>
                                Rawat Jalan <span class="font-bold text-green-600">{{ $rekapRj }}</span>
                                <span class="text-gray-300">·</span>
                                Rawat Inap <span
                                    class="font-bold text-brand dark:text-brand-lime">{{ $rekapRi }}</span>
                                @if ($rekapLewat > 0)
                                    <span class="text-gray-300">·</span>
                                    Jadwal Lewat <span class="font-bold text-red-600">{{ $rekapLewat }}</span>
                                @endif
                            </span>

                            {{-- Toggle keterangan — default tertutup --}}
                            <button type="button" x-on:click="ket = !ket"
                                class="inline-flex items-center gap-1 font-medium text-muted-soft transition hover:text-muted dark:hover:text-gray-200">
                                Keterangan
                                <svg class="w-3 h-3 transition-transform" :class="ket ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </p>

                        <p x-show="ket" x-collapse x-cloak
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[11px] text-muted-soft">
                            <span class="font-semibold uppercase tracking-wide">Ket:</span>
                            <span><x-badge variant="success" class="!px-1.5 !py-0 text-[10px]">RJ</x-badge> = surat
                                kontrol dari kunjungan rawat jalan</span>
                            <span class="text-gray-300">·</span>
                            <span><x-badge variant="brand" class="!px-1.5 !py-0 text-[10px]">RI</x-badge> = kontrol
                                pasca rawat inap</span>
                            <span class="text-gray-300">·</span>
                            <span><span class="font-bold text-red-500">Tanggal merah (LEWAT)</span> = jadwal sudah
                                terlewati, pasien belum datang</span>
                            <span class="text-gray-300">·</span>
                            <span><span class="font-semibold text-muted dark:text-gray-300">Riwayat Kontrol</span> =
                                lihat seluruh riwayat jadwal pasien &amp; geser tanggal kontrol (minimal hari ini) +
                                otomatis update ke BPJS bila pasien BPJS</span>
                            <span class="text-gray-300">·</span>
                            <span>Ketik nama/No. RM untuk mencari pasien telat yang jadwalnya sudah lewat.</span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">Sumber</th>
                                <th class="px-4 py-3 font-semibold">Pasien</th>
                                <th class="px-4 py-3 font-semibold">Kontrol (Poli / Dokter / Tanggal)</th>
                                <th class="px-4 py-3 font-semibold">No. Surat BPJS</th>
                                <th class="px-4 py-3 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="jk-{{ $row['sumber'] }}-{{ $row['trx_no'] }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$row['sumber'] === 'RJ' ? 'success' : 'brand'">{{ $row['sumber'] }}</x-badge>
                                    </td>
                                    {{-- Identitas pasien — standar Daftar RJ (RM, nama brand, L/P 3-cabang, alamat, umur dari birth_date) --}}
                                    <td class="px-4 py-3">
                                        <x-list.identitas-pasien :regNo="$row['reg_no']" :nama="$row['reg_name']" :sex="$row['sex']" :tglLahir="$row['birth_date']" :alamat="$row['address']" :collapseUmur="false" />
                                    </td>
                                    {{-- Kontrol: atas poli/dokter, bawah tgl kunjungan bersanding tgl kontrol --}}
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5 leading-tight">
                                            <div class="font-semibold text-brand dark:text-emerald-400">
                                                {{ $row['poliKontrolDesc'] }}
                                            </div>
                                            <div class="text-xs text-muted">{{ $row['drKontrolDesc'] }}</div>
                                            <div class="flex flex-wrap items-center gap-x-2 pt-0.5 text-sm">
                                                <span class="text-muted">
                                                    Kunjungan: <span
                                                        class="text-body dark:text-gray-300">{{ $row['tgl_kunjungan'] }}</span>
                                                </span>
                                                <span class="text-gray-300">→</span>
                                                <span class="text-muted">
                                                    Kontrol:
                                                    <span
                                                        class="font-bold {{ $this->sudahLewat($row['tglKontrol']) ? 'text-red-600 dark:text-red-400' : 'text-ink dark:text-gray-100' }}">
                                                        {{ $row['tglKontrol'] }}
                                                    </span>
                                                    @if ($this->sudahLewat($row['tglKontrol']))
                                                        <span
                                                            class="text-[10px] font-bold text-red-500 uppercase">Lewat</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Nomor BPJS — grid label : isi (kolom label & titik dua sejajar rapi) --}}
                                    <td class="px-4 py-3">
                                        <table class="text-sm leading-snug">
                                            <tr>
                                                <td
                                                    class="pr-1 text-xs font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">
                                                    Surat Kontrol</td>
                                                <td class="pr-1.5 text-xs text-muted-soft align-top">:</td>
                                                <td class="font-mono font-medium text-body dark:text-gray-300">
                                                    {{ $row['noSKDPBPJS'] !== '' ? $row['noSKDPBPJS'] : '-' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="pr-1 text-xs font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">
                                                    SEP</td>
                                                <td class="pr-1.5 text-xs text-muted-soft align-top">:</td>
                                                <td class="font-mono font-medium text-body dark:text-gray-300">
                                                    {{ $row['noSEP'] !== '' ? $row['noSEP'] : '-' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{-- SATU PINTU: lihat riwayat + ubah tanggal di dalam modal riwayat --}}
                                        <x-outline-button type="button" class="whitespace-nowrap"
                                            wire:click="$dispatch('riwayat-kontrol.open', { regNo: '{{ $row['reg_no'] }}', regName: '{{ addslashes($row['reg_name']) }}' })">
                                            Riwayat Kontrol
                                        </x-outline-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-muted-soft">
                                        Tidak ada jadwal kontrol
                                        {{ mb_strlen(trim($searchKeyword)) >= 3 ? 'untuk pencarian ini' : 'pada tanggal ' . $tglFilter }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Riwayat Jadwal Kontrol per pasien (listen: riwayat-kontrol.open) —
         SATU PINTU pengeditan tanggal kontrol ada di dalam modal ini --}}
    <livewire:pages::components.rekam-medis.riwayat-kontrol-pasien.riwayat-kontrol-pasien
        wire:key="riwayat-kontrol-pasien" />
</div>
