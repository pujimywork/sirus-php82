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
//   • Dibatasi kunjungan 120 hari terakhir (kontrol dibuat saat kunjungan,
//     default +8 hari) supaya scan CLOB tidak melebar ke data lama.
//
// DUA MODE PENCARIAN
// ──────────────────
// 1. Mode tanggal (default): filter exact tglKontrol = tanggal terpilih.
// 2. Mode pasien: ketik nama / No. RM ≥3 huruf → filter tanggal diabaikan,
//    tampil semua jadwal kontrol pasien tsb (utk pasien telat yang tidak
//    tahu tanggal jadwalnya).
//
// UPDATE KE BPJS
// ──────────────
// Hanya tglKontrol yang bisa diubah di sini (dokter/poli tetap — kalau ganti
// dokter, lewat modul SKDP di EMR). Bila klaim BPJS & noSKDPBPJS ada:
// push RencanaKontrol/update DULU; gagal → perubahan TIDAK disimpan lokal
// (jaga konsistensi RS ↔ BPJS). API call di luar DB::transaction.

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRJTrait, EmrRITrait, WithValidationToastTrait;

    /* ── Filter list ── */
    public string $tglFilter = '';
    public string $searchKeyword = '';
    public string $filterSumber = ''; // '' = semua | RJ | RI

    /* ── Modal ubah jadwal ── */
    public string $editSumber = ''; // RJ | RI
    public string $editTrxNo = '';
    public string $editRegNo = '';
    public string $editRegName = '';
    public array $editKontrol = [];
    public string $editKlaimStatus = '';
    public string $tglBaru = '';

    public function mount(): void
    {
        $this->tglFilter = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /** Reset semua filter ke kondisi awal (pola toolbar Apotek RJ). */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterSumber']);
        $this->tglFilter = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /** Escape slash tanggal sesuai penyimpanan json_encode ("04\/07\/2026"). */
    private function tglJsonPattern(string $tgl): string
    {
        return '"tglKontrol":"' . str_replace('/', '\/', $tgl) . '"';
    }

    #[Computed]
    public function rows()
    {
        $search = trim($this->searchKeyword);
        $modePasien = mb_strlen($search) >= 3;

        $sumberList = [
            ['tabel' => 'rstxn_rjhdrs', 'kolomNo' => 'rj_no', 'kolomJson' => 'datadaftarpolirj_json', 'kolomTgl' => 'rj_date', 'sumber' => 'RJ'],
            ['tabel' => 'rstxn_rihdrs', 'kolomNo' => 'rihdr_no', 'kolomJson' => 'datadaftarri_json', 'kolomTgl' => 'entry_date', 'sumber' => 'RI'],
        ];

        $hasil = collect();

        foreach ($sumberList as $src) {
            // Filter sumber RJ/RI — skip query sumber yang tidak dipilih
            if ($this->filterSumber !== '' && $this->filterSumber !== $src['sumber']) {
                continue;
            }

            $query = DB::table($src['tabel'] . ' as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->whereRaw("h.{$src['kolomTgl']} >= sysdate - 120");

            if ($modePasien) {
                // Mode pasien: persempit by identitas DULU (index-friendly),
                // baru INSTR kontrol non-kosong di subset kecil itu.
                $kw = mb_strtoupper($search);
                $query
                    ->where(function ($w) use ($kw) {
                        $w->whereRaw('UPPER(p.reg_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(h.reg_no) LIKE ?', ["%{$kw}%"]);
                    })
                    ->whereRaw("INSTR(h.{$src['kolomJson']}, '\"noKontrolRS\"') > 0")
                    ->whereRaw("INSTR(h.{$src['kolomJson']}, '\"noKontrolRS\":\"\"') = 0");
            } else {
                // Mode tanggal: cukup 1 INSTR — pattern tanggal sudah mengimplikasikan
                // objek kontrol ada; noKontrolRS kosong disaring saat decode PHP.
                $query->whereRaw("INSTR(h.{$src['kolomJson']}, ?) > 0", [$this->tglJsonPattern($this->tglFilter)]);
            }

            $baris = $query
                ->select(["h.{$src['kolomNo']} as trx_no", 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), DB::raw("to_char(h.{$src['kolomTgl']},'dd/mm/yyyy') as tgl_kunjungan"), "h.{$src['kolomJson']} as json_emr"])
                ->get();

            foreach ($baris as $b) {
                $kontrol = json_decode($b->json_emr ?? '{}', true)['kontrol'] ?? [];
                if (empty($kontrol['noKontrolRS'])) {
                    continue;
                }

                // Umur dihitung realtime dari birth_date (kolom thn/bln/hari stored
                // tidak refresh) — format standar identitas pasien Daftar RJ.
                $umurFormat = '-';
                if (!empty($b->birth_date)) {
                    try {
                        $selisih = Carbon::createFromFormat('d/m/Y', $b->birth_date)->diff(Carbon::now(config('app.timezone')));
                        $umurFormat = "{$b->birth_date} ({$selisih->y} Thn {$selisih->m} Bln {$selisih->d} Hr)";
                    } catch (\Throwable) {
                    }
                }

                $hasil->push([
                    'sumber' => $src['sumber'],
                    'trx_no' => (string) $b->trx_no,
                    'reg_no' => $b->reg_no,
                    'reg_name' => $b->reg_name,
                    'sex' => $b->sex,
                    'address' => $b->address,
                    'umur_format' => $umurFormat,
                    'tgl_kunjungan' => $b->tgl_kunjungan,
                    'tglKontrol' => $kontrol['tglKontrol'] ?? '-',
                    'poliKontrolDesc' => $kontrol['poliKontrolDesc'] ?? '-',
                    'drKontrolDesc' => $kontrol['drKontrolDesc'] ?? '-',
                    'noSKDPBPJS' => $kontrol['noSKDPBPJS'] ?? '',
                    'noSEP' => $kontrol['noSEP'] ?? '',
                ]);
            }
        }

        // Urut tanggal kontrol naik; tanggal tak terparse ditaruh paling bawah
        return $hasil
            ->sortBy(function ($r) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $r['tglKontrol'])->timestamp;
                } catch (\Throwable) {
                    return PHP_INT_MAX;
                }
            })
            ->values();
    }

    /** Apakah tanggal kontrol sudah lewat (utk highlight merah). */
    public function sudahLewat(string $tgl): bool
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $tgl)->startOfDay()->lt(Carbon::now(config('app.timezone'))->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

    /* ═══════════════════════════════════════
     | MODAL UBAH JADWAL
    ═══════════════════════════════════════ */
    public function openEdit(string $sumber, string $trxNo, string $regName = ''): void
    {
        $data = $sumber === 'RJ' ? $this->findDataRJ((int) $trxNo) : $this->findDataRI($trxNo);
        if (empty($data) || empty($data['kontrol']['noKontrolRS'])) {
            $this->dispatch('toast', type: 'error', message: 'Data jadwal kontrol tidak ditemukan.');
            return;
        }

        $this->editSumber = $sumber;
        $this->editTrxNo = $trxNo;
        $this->editRegNo = $data['regNo'] ?? '';
        $this->editRegName = $regName;
        $this->editKontrol = $data['kontrol'];
        $this->tglBaru = $data['kontrol']['tglKontrol'] ?? '';
        $this->editKlaimStatus = DB::table('rsmst_klaimtypes')->where('klaim_id', $data['klaimId'] ?? '')->value('klaim_status') ?? 'UMUM';

        $this->resetValidation();
        $this->dispatch('open-modal', name: 'edit-jadwal-kontrol');
    }

    protected function rules(): array
    {
        return [
            // Pendaftaran boleh geser ke HARI INI (kasus pasien telat) — beda dgn
            // pembuatan SKDP di EMR yang minimal besok (after:today).
            'tglBaru' => 'required|date_format:d/m/Y|after_or_equal:today',
        ];
    }

    protected function messages(): array
    {
        return [
            'tglBaru.required' => 'Tanggal kontrol baru wajib diisi.',
            'tglBaru.date_format' => 'Format tanggal harus dd/mm/yyyy.',
            'tglBaru.after_or_equal' => 'Tanggal kontrol baru minimal hari ini.',
        ];
    }

    public function simpan(): void
    {
        if (empty($this->editSumber) || empty($this->editTrxNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi edit tidak valid, buka ulang dari daftar.');
            return;
        }

        $this->validateWithToast();

        // Re-fetch fresh — kontrol bisa berubah sejak modal dibuka
        $data = $this->editSumber === 'RJ' ? $this->findDataRJ((int) $this->editTrxNo) : $this->findDataRI($this->editTrxNo);
        $kontrol = $data['kontrol'] ?? [];
        if (empty($kontrol['noKontrolRS'])) {
            $this->dispatch('toast', type: 'error', message: 'Data kontrol tidak ditemukan.');
            return;
        }

        $tglLama = $kontrol['tglKontrol'] ?? '-';
        if ($tglLama === $this->tglBaru) {
            $this->dispatch('toast', type: 'info', message: 'Tanggal tidak berubah.');
            return;
        }

        $kontrol['tglKontrol'] = $this->tglBaru;

        // Push update ke BPJS DULU (di luar transaksi DB). Gagal → JANGAN simpan
        // lokal, supaya tanggal di RS tidak beda dengan tanggal di BPJS.
        if ($this->editKlaimStatus === 'BPJS' && !empty($kontrol['noSKDPBPJS'])) {
            $response = VclaimTrait::suratkontrol_update($kontrol)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $message = $response['metadata']['message'] ?? '';

            if ($code != 200) {
                $this->dispatch('toast', type: 'error', message: "UPDATE KONTROL BPJS {$code} {$message} — perubahan TIDAK disimpan.");
                return;
            }
            $this->dispatch('toast', type: 'success', message: "UPDATE KONTROL BPJS {$code} {$message}");
        }

        try {
            DB::transaction(function () use ($tglLama) {
                if ($this->editSumber === 'RJ') {
                    $this->lockRJRow((int) $this->editTrxNo);
                    $fresh = $this->findDataRJ((int) $this->editTrxNo) ?? [];
                    $fresh['kontrol']['tglKontrol'] = $this->tglBaru;
                    $this->updateJsonRJ((int) $this->editTrxNo, $fresh);
                    $this->appendAdminLogRJ((int) $this->editTrxNo, "Ubah tgl jadwal kontrol {$tglLama} → {$this->tglBaru} (Pendaftaran)", 'MR');
                } else {
                    $this->lockRIRow($this->editTrxNo);
                    $fresh = $this->findDataRI($this->editTrxNo) ?? [];
                    $fresh['kontrol']['tglKontrol'] = $this->tglBaru;
                    $this->updateJsonRI((int) $this->editTrxNo, $fresh);
                    $this->appendAdminLogRI((int) $this->editTrxNo, "Ubah tgl jadwal kontrol {$tglLama} → {$this->tglBaru} (Pendaftaran)", 'MR');
                }
            });

            $this->dispatch('toast', type: 'success', message: "Jadwal kontrol diubah: {$tglLama} → {$this->tglBaru}.");
            $this->dispatch('close-modal', name: 'edit-jadwal-kontrol');
            unset($this->rows); // refresh computed
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function setTglBaruHariIni(): void
    {
        $this->tglBaru = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }
};
?>

<div>
    <x-page-title title="Jadwal Kontrol Pasien"
        subtitle="Lihat & geser tanggal rencana kontrol (SKDP) RJ/RI — update otomatis ke BPJS" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR — urutan filter mengikuti Daftar RJ: Search dulu, lalu Tanggal --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
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
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="tglFilter"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
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
                        $rekapLewat = $rekap->filter(fn($r) => $this->sudahLewat($r['tglKontrol']))->count();
                    @endphp
                    <div class="w-full space-y-1 text-xs text-gray-500 dark:text-gray-400" x-data="{ ket: false }">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span>
                                @if (mb_strlen(trim($searchKeyword)) >= 3)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">Mode pencarian pasien</span>
                                    — filter tanggal diabaikan, menampilkan SEMUA jadwal kontrol pasien tersebut
                                    (dari kunjungan 120 hari terakhir).
                                @else
                                    Menampilkan jadwal kontrol tanggal
                                    <span class="font-semibold">{{ $tglFilter }}</span>{{ $filterSumber !== '' ? ' — sumber ' . $filterSumber . ' saja' : '' }}.
                                @endif
                            </span>

                            {{-- Rekap jumlah --}}
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 font-medium border border-gray-200 rounded-full bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                Total <span class="font-bold text-gray-700 dark:text-gray-200">{{ $rekap->count() }}</span>
                                <span class="text-gray-300">·</span>
                                Rawat Jalan <span class="font-bold text-green-600">{{ $rekapRj }}</span>
                                <span class="text-gray-300">·</span>
                                Rawat Inap <span class="font-bold text-brand dark:text-brand-lime">{{ $rekapRi }}</span>
                                @if ($rekapLewat > 0)
                                    <span class="text-gray-300">·</span>
                                    Jadwal Lewat <span class="font-bold text-red-600">{{ $rekapLewat }}</span>
                                @endif
                            </span>

                            {{-- Toggle keterangan — default tertutup --}}
                            <button type="button" x-on:click="ket = !ket"
                                class="inline-flex items-center gap-1 font-medium text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
                                Keterangan
                                <svg class="w-3 h-3 transition-transform" :class="ket ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </p>

                        <p x-show="ket" x-collapse x-cloak
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[11px] text-gray-400">
                            <span class="font-semibold uppercase tracking-wide">Ket:</span>
                            <span><x-badge variant="success" class="!px-1.5 !py-0 text-[10px]">RJ</x-badge> = surat kontrol dari kunjungan rawat jalan</span>
                            <span class="text-gray-300">·</span>
                            <span><x-badge variant="brand" class="!px-1.5 !py-0 text-[10px]">RI</x-badge> = kontrol pasca rawat inap</span>
                            <span class="text-gray-300">·</span>
                            <span><span class="font-bold text-red-500">Tanggal merah (LEWAT)</span> = jadwal sudah terlewati, pasien belum datang</span>
                            <span class="text-gray-300">·</span>
                            <span><span class="font-semibold text-gray-600 dark:text-gray-300">Ubah Jadwal Kontrol</span> = geser tanggal kontrol (minimal hari ini) + otomatis update ke BPJS bila pasien BPJS</span>
                            <span class="text-gray-300">·</span>
                            <span>Ketik nama/No. RM untuk mencari pasien telat yang jadwalnya sudah lewat.</span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">Sumber</th>
                                <th class="px-4 py-3 font-semibold">Pasien</th>
                                <th class="px-4 py-3 font-semibold">Kontrol (Poli / Dokter / Tanggal)</th>
                                <th class="px-4 py-3 font-semibold">No. Surat BPJS</th>
                                <th class="px-4 py-3 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="jk-{{ $row['sumber'] }}-{{ $row['trx_no'] }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$row['sumber'] === 'RJ' ? 'success' : 'brand'">{{ $row['sumber'] }}</x-badge>
                                    </td>
                                    {{-- Identitas pasien — standar Daftar RJ (RM, nama brand, L/P 3-cabang, alamat, umur dari birth_date) --}}
                                    <td class="px-4 py-3">
                                        <div class="space-y-0 leading-tight">
                                            <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                {{ $row['reg_no'] ?? '-' }}
                                            </div>
                                            <div class="text-lg font-semibold text-brand dark:text-white">
                                                {{ $row['reg_name'] ?? '-' }}
                                            </div>
                                            <div class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                                ({{ $row['sex'] === 'L' ? 'Laki-Laki' : ($row['sex'] === 'P' ? 'Perempuan' : '-') }})
                                            </div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $row['address'] ?? '-' }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row['umur_format'] ?? '-' }}
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Kontrol: atas poli/dokter, bawah tgl kunjungan bersanding tgl kontrol --}}
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5 leading-tight">
                                            <div class="font-semibold text-brand dark:text-emerald-400">
                                                {{ $row['poliKontrolDesc'] }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $row['drKontrolDesc'] }}</div>
                                            <div class="flex flex-wrap items-center gap-x-2 pt-0.5 text-sm">
                                                <span class="text-gray-500">
                                                    Kunjungan: <span class="text-gray-700 dark:text-gray-300">{{ $row['tgl_kunjungan'] }}</span>
                                                </span>
                                                <span class="text-gray-300">→</span>
                                                <span class="text-gray-500">
                                                    Kontrol:
                                                    <span class="font-bold {{ $this->sudahLewat($row['tglKontrol']) ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-100' }}">
                                                        {{ $row['tglKontrol'] }}
                                                    </span>
                                                    @if ($this->sudahLewat($row['tglKontrol']))
                                                        <span class="text-[10px] font-bold text-red-500 uppercase">Lewat</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Nomor BPJS — grid label : isi (kolom label & titik dua sejajar rapi) --}}
                                    <td class="px-4 py-3">
                                        <table class="text-sm leading-snug">
                                            <tr>
                                                <td class="pr-1 text-xs font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap align-top">Surat Kontrol</td>
                                                <td class="pr-1.5 text-xs text-gray-400 align-top">:</td>
                                                <td class="font-mono font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row['noSKDPBPJS'] !== '' ? $row['noSKDPBPJS'] : '-' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="pr-1 text-xs font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap align-top">SEP</td>
                                                <td class="pr-1.5 text-xs text-gray-400 align-top">:</td>
                                                <td class="font-mono font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row['noSEP'] !== '' ? $row['noSEP'] : '-' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-outline-button type="button" class="whitespace-nowrap"
                                            wire:click="openEdit('{{ $row['sumber'] }}', '{{ $row['trx_no'] }}', '{{ addslashes($row['reg_name']) }}')">
                                            Ubah Jadwal Kontrol
                                        </x-outline-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-400">
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

    {{-- ═══ MODAL UBAH JADWAL ═══ --}}
    <x-modal name="edit-jadwal-kontrol" focusable>
        <div class="p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ubah Jadwal Kontrol</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Hanya tanggal yang bisa diubah di sini. Ganti dokter/poli kontrol lewat modul SKDP di EMR.
                </p>
            </div>

            {{-- Ringkasan --}}
            <div class="p-4 space-y-1 text-sm border border-gray-200 rounded-xl bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $editRegName ?: '-' }}</span>
                    <span class="font-mono text-xs text-gray-500">{{ $editRegNo }}</span>
                </div>
                <div class="text-gray-600 dark:text-gray-300">
                    {{ $editKontrol['poliKontrolDesc'] ?? '-' }} — {{ $editKontrol['drKontrolDesc'] ?? '-' }}
                </div>
                <div class="font-mono text-xs text-gray-500">
                    No. Surat BPJS: {{ ($editKontrol['noSKDPBPJS'] ?? '') !== '' ? $editKontrol['noSKDPBPJS'] : '-' }}
                </div>
                <div class="text-xs text-gray-500">
                    Tgl kontrol saat ini:
                    <span class="font-semibold">{{ $editKontrol['tglKontrol'] ?? '-' }}</span>
                </div>
            </div>

            {{-- Tanggal baru --}}
            <div>
                <x-input-label value="Tanggal Kontrol Baru *" class="mb-1" />
                <div class="flex items-center gap-1">
                    <x-text-input type="text" wire:model="tglBaru" placeholder="dd/mm/yyyy"
                        :error="$errors->has('tglBaru')" class="w-40" />
                    <x-secondary-button type="button" class="text-sm whitespace-nowrap" wire:click="setTglBaruHariIni">
                        Hari Ini
                    </x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('tglBaru')" class="mt-1" />
                @if ($editKlaimStatus === 'BPJS' && ($editKontrol['noSKDPBPJS'] ?? '') !== '')
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        ⚡ Perubahan akan langsung di-update ke BPJS (RencanaKontrol/update).
                        Jika BPJS menolak, perubahan tidak disimpan.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'edit-jadwal-kontrol' })">
                    Batal
                </x-secondary-button>
                <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
                    <span wire:loading.remove wire:target="simpan">Simpan & Update BPJS</span>
                    <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
