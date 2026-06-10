<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\AntrianTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {

    /* ─── State ─── */
    public string $searchPoli    = '';
    public string $dateRef       = '';
    public array  $poliLov       = [];
    public bool   $showLov       = false;
    public string $selectedPoliId   = '';
    public string $selectedPoliName = '';
    public array  $jadwalBpjs    = [];
    public bool   $loadingJadwal = false;

    /* ─── Sync All progress ─── */
    public bool   $syncingAll    = false;
    public int    $syncAllTotal  = 0;
    public int    $syncAllDone   = 0;
    public int    $syncAllOk     = 0;
    public int    $syncAllSkip   = 0;
    public string $syncAllLog    = '';

    /* ─── Mount ─── */
    public function mount(): void
    {
        $this->dateRef = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    /* ─── Search poli dari BPJS (min 3 karakter) ─── */
    public function updatedSearchPoli(): void
    {
        $this->showLov = false;
        $this->poliLov = [];

        if (strlen(trim($this->searchPoli)) < 3) {
            return;
        }

        $res = VclaimTrait::ref_poliklinik($this->searchPoli)->getOriginalContent();

        if (($res['metadata']['code'] ?? '') == 200) {
            $this->poliLov = $res['response']['poli'] ?? [];
            $this->showLov = true;
        } else {
            $this->dispatch('toast', type: 'error',
                message: ($res['metadata']['message'] ?? 'Gagal mengambil data poli BPJS'));
        }
    }

    /* ─── Pilih poli dari LOV → ambil jadwal dokter ─── */
    public function selectPoli(string $kdPoli, string $namaPoli): void
    {
        $this->selectedPoliId   = $kdPoli;
        $this->selectedPoliName = $namaPoli;
        $this->searchPoli       = $namaPoli;
        $this->showLov          = false;
        $this->jadwalBpjs       = [];

        $this->loadJadwalBpjs();
        $this->dispatch('open-modal', name: 'modal-jadwal-poli');
    }

    /* ─── Muat ulang jadwal ─── */
    public function loadJadwalBpjs(): void
    {
        if (empty($this->selectedPoliId)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih poli terlebih dahulu.');
            return;
        }

        try {
            $tgl = Carbon::createFromFormat('d/m/Y', $this->dateRef)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Format tanggal tidak valid (dd/mm/yyyy).');
            return;
        }

        $res = AntrianTrait::ref_jadwal_dokter($this->selectedPoliId, $tgl)->getOriginalContent();

        if (($res['metadata']['code'] ?? '') == 200) {
            $this->jadwalBpjs = $res['response'] ?? [];
            $this->dispatch('toast', type: 'success',
                message: count($this->jadwalBpjs) . ' jadwal ditemukan untuk ' . $this->selectedPoliName);
        } else {
            $this->jadwalBpjs = [];
            $this->dispatch('toast', type: 'warning',
                message: 'Jadwal tidak ditemukan: ' . ($res['metadata']['message'] ?? '-'));
        }
    }

    /* ─── Sync satu baris jadwal ke scmst_scpolis ─── */
    public function syncJadwal(string $kdPoliBpjs, string $kdDrBpjs, string $nmDokter, int $dayId, string $jamPraktek, int $kuota): void
    {
        // day_id 8 = hari libur nasional BPJS, tidak ada di scmst_scdays → skip
        if ($dayId >= 8) {
            $this->dispatch('toast', type: 'warning', message: "Jadwal hari libur nasional (hari={$dayId}) tidak disimpan.");
            return;
        }

        $poli = DB::table('rsmst_polis')->where('kd_poli_bpjs', $kdPoliBpjs)->first();
        if (!$poli) {
            $this->dispatch('toast', type: 'error', message: "Poli [{$kdPoliBpjs}] belum di-mapping di master poli RS.");
            return;
        }

        $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $kdDrBpjs)->first();
        if (!$dokter) {
            $this->dispatch('toast', type: 'warning', message: "Dokter [{$kdDrBpjs}] {$nmDokter} belum di-mapping, jadwal dilewati.");
            return;
        }

        $jammulai   = substr($jamPraktek, 0, 5);
        $jamselesai = substr($jamPraktek, 6, 5);

        $shiftRow = DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw("? BETWEEN shift_start AND shift_end", [$jammulai . ':00'])
            ->first();
        $shift = $shiftRow?->shift ?? 1;

        $payload = [
            'sc_poli_status_'      => '1',
            'sc_poli_ket'          => $jamPraktek,
            'day_id'               => $dayId,
            'poli_id'              => $poli->poli_id,
            'dr_id'                => $dokter->dr_id,
            'shift'                => $shift,
            'mulai_praktek'        => $jammulai . ':00',
            'selesai_praktek'      => $jamselesai . ':00',
            'pelayanan_perp_asien' => '',
            'no_urut'              => 1,
            'kuota'                => $kuota,
        ];

        try {
            $exists = DB::table('scmst_scpolis')
                ->where('day_id',     $dayId)
                ->where('poli_id',    $poli->poli_id)
                ->where('dr_id',      $dokter->dr_id)
                ->where('sc_poli_ket', $jamPraktek)
                ->exists();

            if ($exists) {
                DB::table('scmst_scpolis')
                    ->where('day_id',     $dayId)
                    ->where('poli_id',    $poli->poli_id)
                    ->where('dr_id',      $dokter->dr_id)
                    ->where('sc_poli_ket', $jamPraktek)
                    ->update($payload);
                $this->dispatch('toast', type: 'success', message: "Jadwal diperbarui: {$nmDokter} ({$jamPraktek})");
            } else {
                DB::table('scmst_scpolis')->insert($payload);
                $this->dispatch('toast', type: 'success', message: "Jadwal ditambahkan: {$nmDokter} ({$jamPraktek})");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal sync: ' . $e->getMessage());
        }
    }

    /* ─── Sync semua jadwal yang tampil ─── */
    public function syncSemua(): void
    {
        if (empty($this->jadwalBpjs)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada jadwal untuk di-sync.');
            return;
        }

        $ok = 0; $skip = 0;
        foreach ($this->jadwalBpjs as $j) {
            // day_id 8 = hari libur nasional, tidak ada di scmst_scdays → skip
            if ((int)$j['hari'] >= 8) { $skip++; continue; }

            $poli   = DB::table('rsmst_polis')->where('kd_poli_bpjs', $j['kodepoli'])->first();
            $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $j['kodedokter'])->first();

            if (!$poli || !$dokter) { $skip++; continue; }

            $jammulai   = substr($j['jadwal'], 0, 5);
            $jamselesai = substr($j['jadwal'], 6, 5);
            $shiftRow   = DB::table('rstxn_shiftctls')
                ->whereNotNull('shift_start')
                ->whereNotNull('shift_end')
                ->whereRaw("? BETWEEN shift_start AND shift_end", [$jammulai . ':00'])
                ->first();

            $payload = [
                'sc_poli_status_'      => '1',
                'sc_poli_ket'          => $j['jadwal'],
                'day_id'               => $j['hari'],
                'poli_id'              => $poli->poli_id,
                'dr_id'                => $dokter->dr_id,
                'shift'                => $shiftRow?->shift ?? 1,
                'mulai_praktek'        => $jammulai . ':00',
                'selesai_praktek'      => $jamselesai . ':00',
                'pelayanan_perp_asien' => '',
                'no_urut'              => 1,
                'kuota'                => (int) $j['kapasitaspasien'],
            ];

            try {
                $exists = DB::table('scmst_scpolis')
                    ->where('day_id',     $j['hari'])
                    ->where('poli_id',    $poli->poli_id)
                    ->where('dr_id',      $dokter->dr_id)
                    ->where('sc_poli_ket', $j['jadwal'])
                    ->exists();

                $exists
                    ? DB::table('scmst_scpolis')->where('day_id', $j['hari'])->where('poli_id', $poli->poli_id)->where('dr_id', $dokter->dr_id)->where('sc_poli_ket', $j['jadwal'])->update($payload)
                    : DB::table('scmst_scpolis')->insert($payload);
                $ok++;
            } catch (\Exception $e) {
                $skip++;
            }
        }

        $this->dispatch('toast', type: 'success', message: "Selesai: {$ok} jadwal berhasil diterapkan, {$skip} dilewati.");
    }

    /* ─── Sync SEMUA poli yang sudah di-mapping di RS ─── */
    public function syncAllPoli(): void
    {
        try {
            $tgl = Carbon::createFromFormat('d/m/Y', $this->dateRef)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Format tanggal tidak valid (dd/mm/yyyy).');
            return;
        }

        // Ambil semua poli RS yang sudah punya kd_poli_bpjs
        $allPolis = DB::table('rsmst_polis')
            ->whereNotNull('kd_poli_bpjs')
            ->where('kd_poli_bpjs', '!=', '')
            ->get();

        if ($allPolis->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada poli yang sudah di-mapping ke BPJS.');
            return;
        }

        $this->syncingAll   = true;
        $this->syncAllTotal = $allPolis->count();
        $this->syncAllDone  = 0;
        $this->syncAllOk    = 0;
        $this->syncAllSkip  = 0;
        $this->syncAllLog   = '';

        foreach ($allPolis as $poli) {
            $this->syncAllDone++;
            $this->syncAllLog = "Memproses: {$poli->poli_desc} ({$poli->kd_poli_bpjs})...";

            try {
                $res = AntrianTrait::ref_jadwal_dokter($poli->kd_poli_bpjs, $tgl)->getOriginalContent();
            } catch (\Exception $e) {
                $this->syncAllSkip++;
                continue;
            }

            if (($res['metadata']['code'] ?? '') != 200) {
                $this->syncAllSkip++;
                continue;
            }

            $jadwals = $res['response'] ?? [];
            foreach ($jadwals as $j) {
                // day_id 8 = hari libur nasional, tidak ada di scmst_scdays → skip
                if ((int)$j['hari'] >= 8) { $this->syncAllSkip++; continue; }

                $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $j['kodedokter'])->first();
                if (!$dokter) { $this->syncAllSkip++; continue; }

                $jammulai   = substr($j['jadwal'], 0, 5);
                $jamselesai = substr($j['jadwal'], 6, 5);
                $shiftRow   = DB::table('rstxn_shiftctls')
                    ->whereNotNull('shift_start')
                    ->whereNotNull('shift_end')
                    ->whereRaw("? BETWEEN shift_start AND shift_end", [$jammulai . ':00'])
                    ->first();

                $payload = [
                    'sc_poli_status_'      => '1',
                    'sc_poli_ket'          => $j['jadwal'],
                    'day_id'               => $j['hari'],
                    'poli_id'              => $poli->poli_id,
                    'dr_id'                => $dokter->dr_id,
                    'shift'                => $shiftRow?->shift ?? 1,
                    'mulai_praktek'        => $jammulai . ':00',
                    'selesai_praktek'      => $jamselesai . ':00',
                    'pelayanan_perp_asien' => '',
                    'no_urut'              => 1,
                    'kuota'                => (int) $j['kapasitaspasien'],
                ];

                try {
                    $exists = DB::table('scmst_scpolis')
                        ->where('day_id',      $j['hari'])
                        ->where('poli_id',     $poli->poli_id)
                        ->where('dr_id',       $dokter->dr_id)
                        ->where('sc_poli_ket', $j['jadwal'])
                        ->exists();

                    $exists
                        ? DB::table('scmst_scpolis')
                            ->where('day_id', $j['hari'])->where('poli_id', $poli->poli_id)
                            ->where('dr_id', $dokter->dr_id)->where('sc_poli_ket', $j['jadwal'])
                            ->update($payload)
                        : DB::table('scmst_scpolis')->insert($payload);
                    $this->syncAllOk++;
                } catch (\Exception $e) {
                    $this->syncAllSkip++;
                }
            }
        }

        $this->syncingAll  = false;
        $this->syncAllLog  = '';
        $this->dispatch('toast', type: 'success',
            message: "Selesai: {$this->syncAllOk} jadwal berhasil diterapkan, {$this->syncAllSkip} dilewati dari {$this->syncAllTotal} poli.",
            duration: 8000);
    }

    /* ─── Data jadwal RS saat ini per hari ─── */
    #[Computed]
    public function jadwalRS(): array
    {
        $hari = [];
        for ($i = 1; $i <= 7; $i++) {
            $day = DB::table('scmst_scdays')->where('day_id', $i)->first();
            $jadwal = DB::table('scview_scpolis')
                ->select('sc_poli_ket', 'day_id', 'dr_id', 'dr_name', 'poli_desc', 'poli_id',
                         'mulai_praktek', 'selesai_praktek', 'shift', 'kuota', 'no_urut',
                         'kd_dr_bpjs', 'kd_poli_bpjs')
                ->where('sc_poli_status_', '1')
                ->where('day_id', $i)
                ->orderBy('mulai_praktek')->orderBy('shift')->orderBy('dr_id')
                ->get()->toArray();

            $hari[] = [
                'day_id'   => $i,
                'day_desc' => $day->day_desc ?? "Hari {$i}",
                'jadwal'   => $jadwal,
            ];
        }
        return $hari;
    }

    /* ─── Dokter aktif (active_status=1) yang belum punya jadwal ─── */
    #[Computed]
    public function dokterBelumTerjadwal(): array
    {
        $sudahTerjadwal = DB::table('scmst_scpolis')
            ->where('sc_poli_status_', '1')
            ->distinct()->pluck('dr_id')->toArray();

        return DB::table('rsmst_doctors')
            ->select('rsmst_doctors.dr_id', 'rsmst_doctors.dr_name', 'rsmst_polis.poli_desc')
            ->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rsmst_doctors.poli_id')
            ->where('rsmst_doctors.active_status', '1')
            ->whereNotIn('rsmst_doctors.dr_id', $sudahTerjadwal)
            ->orderBy('rsmst_doctors.dr_name')
            ->get()->toArray();
    }
};

?>

<div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- MODAL — Jadwal BPJS per Poli                                  --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <x-modal name="modal-jadwal-poli" size="full" height="full">
        <div class="flex flex-col h-full" x-enter-chain>

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft shrink-0">
                {{-- Dot pattern background --}}

                <div class="relative flex items-start justify-between gap-4 mb-4">
                    {{-- Icon + judul --}}
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                        </div>
                        <div>
                            <h2 class="ds-display-sm dark:text-gray-100">Jadwal Dokter BPJS</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Cari poli BPJS, muat jadwal, lalu terapkan ke data jadwal RS.</p>
                        </div>
                    </div>
                    {{-- Close --}}
                    <x-secondary-button type="button"
                        x-on:click="$dispatch('close-modal', { name: 'modal-jadwal-poli' })"
                        class="!p-2 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-secondary-button>
                </div>

                {{-- Search poli + datepicker + tombol muat --}}
                <div class="relative flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <x-text-input type="text" wire:model.live.debounce.400ms="searchPoli"
                            placeholder="Cari poli BPJS (min. 3 karakter)" class="block w-full" />
                        <div wire:loading wire:target="searchPoli,selectPoli" class="absolute right-2 top-2">
                            <x-loading />
                        </div>

                        @if($showLov && count($poliLov))
                        <div class="absolute z-50 left-0 right-0 mt-1 bg-canvas dark:bg-gray-700 border border-hairline dark:border-gray-600 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                            @foreach($poliLov as $p)
                            <button wire:key="poli-lov-{{ $p['kode'] ?? $loop->index }}" type="button"
                                wire:click="selectPoli('{{ $p['kode'] }}', '{{ addslashes($p['nama']) }}')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-brand-green/10 dark:hover:bg-brand-lime/10 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                <span class="font-semibold text-brand-green dark:text-brand-lime">{{ $p['kode'] }}</span>
                                <span class="ml-2 text-gray-700 dark:text-gray-200">{{ $p['nama'] }}</span>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    <div class="relative shrink-0">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <x-text-input datepicker datepicker-autohide datepicker-format="dd/mm/yyyy"
                            type="text" class="pl-9 w-36" placeholder="dd/mm/yyyy"
                            wire:model.lazy="dateRef" />
                    </div>

                    <x-primary-button wire:click="loadJadwalBpjs" wire:loading.attr="disabled" wire:target="loadJadwalBpjs" class="shrink-0">
                        <div wire:loading wire:target="loadJadwalBpjs" class="mr-1"><x-loading /></div>
                        <span wire:loading.remove wire:target="loadJadwalBpjs">Muat Jadwal</span>
                    </x-primary-button>
                </div>

                @if($selectedPoliName)
                <p class="relative mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Poli:
                    <span class="font-semibold text-brand-green dark:text-brand-lime">{{ $selectedPoliName }}</span>
                    <span class="text-gray-400">({{ $selectedPoliId }})</span>
                    @if(count($jadwalBpjs))
                    <span class="mx-1 text-gray-300">·</span>
                    <span class="text-brand-green dark:text-brand-lime">{{ count($jadwalBpjs) }} jadwal ditemukan</span>
                    @endif
                </p>
                @endif
            </div>

            {{-- Panduan singkat --}}
            <div x-data="{ open: false }" class="px-6 py-2 bg-brand-green/5 dark:bg-brand-lime/10 border-b border-brand-green/20 dark:border-brand-lime/20 shrink-0">
                <button type="button" x-on:click="open = !open"
                    class="flex items-center gap-1.5 text-xs font-medium text-brand-green dark:text-brand-lime hover:underline">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span x-text="open ? 'Sembunyikan panduan' : 'Cara penggunaan'"></span>
                    <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-transition class="mt-2 pb-1 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1.5 text-xs text-brand-green dark:text-brand-lime/80">
                    <div class="flex gap-2">
                        <span class="mt-0.5 shrink-0">1.</span>
                        <span>Ketik nama poli di kolom pencarian (min. 3 huruf), lalu pilih poli dari daftar yang muncul.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="mt-0.5 shrink-0">2.</span>
                        <span>Pilih tanggal referensi, lalu klik <strong>Muat Jadwal</strong> untuk mengambil jadwal dokter dari BPJS.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="mt-0.5 shrink-0">3.</span>
                        <span>Klik <strong>Terapkan</strong> di baris tertentu untuk menyimpan satu jadwal dokter ke data RS.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="mt-0.5 shrink-0">4.</span>
                        <span>Klik <strong>Terapkan Semua</strong> di bawah untuk menyimpan seluruh jadwal poli ini sekaligus ke data RS.</span>
                    </div>
                    <div class="flex gap-2 sm:col-span-2">
                        <span class="mt-0.5 shrink-0">5.</span>
                        <span>Baris bertanda <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">Hari Libur</span> tidak dapat diterapkan karena tidak ada dalam jadwal mingguan RS.</span>
                    </div>
                </div>
            </div>

            {{-- Body modal — tabel jadwal --}}
            <div class="flex-1 overflow-y-auto">
                <table class="ds-table">
                    <thead class="sticky top-0 z-10">
                        <tr class="text-left">
                            <th>Dokter</th>
                            <th>Jadwal BPJS</th>
                            <th>Status Mapping RS</th>
                            <th class="ds-c w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jadwalBpjs as $jd)
                        <tr wire:key="jadwal-bpjs-{{ ($jd['kodedokter'] ?? '') . '-' . ($jd['hari'] ?? '') . '-' . $loop->index }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-ink dark:text-white">{{ $jd['namadokter'] }}</div>
                                <div class="text-xs text-blue-500">{{ $jd['kodedokter'] }} / {{ $jd['kodesubspesialis'] }}</div>
                                <div class="text-xs text-gray-400">{{ $jd['namapoli'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-ink dark:text-white">{{ $jd['jadwal'] }}</div>
                                <div class="text-xs text-gray-400">Hari {{ $jd['hari'] }} — {{ $jd['namahari'] }}</div>
                                <div class="text-xs text-gray-400">Kapasitas: {{ $jd['kapasitaspasien'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $drMap   = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $jd['kodedokter'])->first();
                                    $poliMap = DB::table('rsmst_polis')->where('kd_poli_bpjs', $jd['kodepoli'])->first();
                                @endphp
                                <div class="text-xs {{ $drMap ? 'text-green-600' : 'text-red-500' }} mb-1">
                                    Dokter: {{ $drMap ? '✓ ' . $drMap->dr_name : '✗ Belum di-mapping' }}
                                </div>
                                <div class="text-xs {{ $poliMap ? 'text-green-600' : 'text-red-500' }}">
                                    Poli: {{ $poliMap ? '✓ ' . $poliMap->poli_desc : '✗ Belum di-mapping' }}
                                </div>
                            </td>
                            <td class="ds-c px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    @if((int)$jd['hari'] >= 8)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                            Hari Libur
                                        </span>
                                    @else
                                        <x-primary-button type="button" class="px-2 py-1 text-sm"
                                            wire:click="syncJadwal('{{ $jd['kodepoli'] }}','{{ $jd['kodedokter'] }}','{{ addslashes($jd['namadokter']) }}',{{ (int)$jd['hari'] }},'{{ $jd['jadwal'] }}',{{ (int)$jd['kapasitaspasien'] }})">
                                            Terapkan
                                        </x-primary-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center" style="color:var(--muted)">
                                <div wire:loading wire:target="loadJadwalBpjs">
                                    <x-loading /> Memuat jadwal...
                                </div>
                                <span wire:loading.remove wire:target="loadJadwalBpjs">
                                    {{ $selectedPoliId ? 'Jadwal tidak ditemukan untuk poli ini.' : 'Pilih poli untuk memuat jadwal dari BPJS.' }}
                                </span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer modal --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-hairline dark:border-gray-700 shrink-0">
                <div class="flex items-center gap-2">
                    <x-primary-button wire:click="loadJadwalBpjs" wire:loading.attr="disabled" wire:target="loadJadwalBpjs">
                        <div wire:loading wire:target="loadJadwalBpjs" class="mr-1"><x-loading /></div>
                        <span wire:loading.remove wire:target="loadJadwalBpjs">Muat Ulang</span>
                    </x-primary-button>

                    @if(count($jadwalBpjs))
                    <x-primary-button wire:click="syncSemua" wire:loading.attr="disabled" wire:target="syncSemua"
                        wire:confirm="Terapkan semua {{ count($jadwalBpjs) }} jadwal poli ini ke database RS?">
                        <div wire:loading wire:target="syncSemua" class="mr-1"><x-loading /></div>
                        <span wire:loading.remove wire:target="syncSemua">Terapkan Semua ({{ count($jadwalBpjs) }})</span>
                    </x-primary-button>
                    @endif
                </div>

                <x-secondary-button x-on:click="$dispatch('close-modal', { name: 'modal-jadwal-poli' })">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>

    {{-- HEADER --}}
    <x-page-title
        title="Pemetaan Jadwal Praktek Dokter"
        subtitle="Ambil jadwal dokter dari BPJS dan terapkan ke data jadwal RS" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-900">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">

                    {{-- Tombol buka modal cari jadwal per poli --}}
                    <x-secondary-button type="button" x-on:click="$dispatch('open-modal', { name: 'modal-jadwal-poli' })">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        Cari Jadwal per Poli
                    </x-secondary-button>

                    <x-primary-button
                        wire:click="syncAllPoli"
                        wire:loading.attr="disabled"
                        wire:target="syncAllPoli"
                        wire:confirm="Terapkan jadwal dari SEMUA poli yang sudah di-mapping ke BPJS? Proses ini akan memperbarui semua jadwal dokter di database RS.">
                        <div wire:loading wire:target="syncAllPoli" class="mr-1"><x-loading /></div>
                        <svg wire:loading.remove wire:target="syncAllPoli" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span wire:loading.remove wire:target="syncAllPoli">Terapkan Semua Poli</span>
                        <span wire:loading wire:target="syncAllPoli">
                            @if($syncingAll && $syncAllTotal > 0)
                                {{ $syncAllDone }}/{{ $syncAllTotal }}
                            @else
                                Memproses...
                            @endif
                        </span>
                    </x-secondary-button>
                </div>

                {{-- Progress bar Terapkan Semua Poli --}}
                @if($syncingAll && $syncAllTotal > 0)
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-1 text-xs text-gray-600 dark:text-gray-400">
                        <span>{{ $syncAllLog }}</span>
                        <span>{{ $syncAllDone }}/{{ $syncAllTotal }} poli diproses</span>
                    </div>
                    <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-brand-green dark:bg-brand-lime transition-all duration-300"
                            style="width: {{ $syncAllTotal > 0 ? round(($syncAllDone / $syncAllTotal) * 100) : 0 }}%"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- ═══ TAB: Jadwal RS & Dokter Belum Terjadwal ═══ --}}
            <div x-data="{ tab: 'jadwal' }" class="mt-6">

                {{-- Tab Header --}}
                <div class="flex gap-1 border-b border-hairline dark:border-gray-700">
                    <button type="button" x-on:click="tab = 'jadwal'"
                        :class="tab === 'jadwal'
                            ? 'border-b-2 border-brand-green dark:border-brand-lime text-brand-green dark:text-brand-lime font-semibold'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Jadwal Dokter RS
                        <span class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ collect($this->jadwalRS)->sum(fn($h) => count($h['jadwal'])) }}
                        </span>
                    </button>

                    <button type="button" x-on:click="tab = 'belum'"
                        :class="tab === 'belum'
                            ? 'border-b-2 border-red-500 text-red-500 font-semibold'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        Belum Terjadwal
                        @if(count($this->dokterBelumTerjadwal))
                        <span class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                            {{ count($this->dokterBelumTerjadwal) }}
                        </span>
                        @endif
                    </button>
                </div>

                {{-- Tab: Jadwal Dokter RS --}}
                <div x-show="tab === 'jadwal'" x-transition.opacity class="mt-4">
                    <div class="grid grid-cols-2 gap-4">
                        @foreach($this->jadwalRS as $hari)
                        <div class="bg-canvas border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700 overflow-hidden">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-600 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">
                                            {{ $hari['day_desc'] }}
                                            <span class="ml-1 text-gray-400 normal-case font-normal">({{ count($hari['jadwal']) }})</span>
                                        </th>
                                        <th class="px-4 py-3">Jadwal</th>
                                        <th class="px-4 py-3">Kuota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($hari['jadwal'] as $key => $jd)
                                    <tr class="border-t border-gray-100 dark:border-gray-700 group hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $jd->dr_name }}</span><br>
                                            @if(in_array($jd->poli_desc, ['POLI UMUM', 'OK']))
                                                <span class="text-xs text-gray-400">{{ $jd->poli_desc }}</span>
                                            @else
                                                <x-badge>{{ $jd->poli_desc }}</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="font-semibold text-gray-700 dark:text-white">{{ substr($jd->mulai_praktek, 0, 5) }}&ndash;{{ substr($jd->selesai_praktek, 0, 5) }}</span><br>
                                            <span class="text-xs text-gray-400">Shift {{ $jd->shift }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            {{ $jd->kuota }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-xs text-gray-400 text-center">Tidak ada jadwal</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tab: Dokter Belum Terjadwal --}}
                <div x-show="tab === 'belum'" x-transition.opacity class="mt-4">
                    @if(count($this->dokterBelumTerjadwal))
                    <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto rounded-2xl">
                            <table class="ds-table">
                                <thead>
                                    <tr class="text-left">
                                        <th>#</th>
                                        <th>Dokter</th>
                                        <th>Poli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->dokterBelumTerjadwal as $key => $d)
                                    <tr class="hover:bg-red-50 dark:hover:bg-gray-800/60">
                                        <td class="px-4 py-3 text-xs w-8" style="color:var(--muted)">{{ $key + 1 }}</td>
                                        <td class="px-4 py-3 font-semibold text-red-500">{{ $d->dr_name }}</td>
                                        <td class="px-4 py-3">
                                            @if(in_array($d->poli_desc, ['POLI UMUM', 'OK']))
                                                <span class="text-xs text-gray-500">{{ $d->poli_desc }}</span>
                                            @else
                                                <x-badge>{{ $d->poli_desc }}</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @else
                    <div class="py-12 text-center text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mx-auto mb-3 text-brand-green/40 dark:text-brand-lime/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-medium">Semua dokter aktif sudah memiliki jadwal</p>
                    </div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</div>
