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
            ->whereRaw("? BETWEEN shift_sta AND shift_end", [$jammulai . ':00'])
            ->first();
        $shift = $shiftRow->shift ?? 1;

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
                $this->dispatch('toast', type: 'success', message: "Update OK: {$nmDokter} ({$jamPraktek})");
            } else {
                DB::table('scmst_scpolis')->insert($payload);
                $this->dispatch('toast', type: 'success', message: "Insert OK: {$nmDokter} ({$jamPraktek})");
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
            $poli   = DB::table('rsmst_polis')->where('kd_poli_bpjs', $j['kodepoli'])->first();
            $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $j['kodedokter'])->first();

            if (!$poli || !$dokter) { $skip++; continue; }

            $jammulai   = substr($j['jadwal'], 0, 5);
            $jamselesai = substr($j['jadwal'], 6, 5);
            $shiftRow   = DB::table('rstxn_shiftctls')
                ->whereRaw("? BETWEEN shift_sta AND shift_end", [$jammulai . ':00'])
                ->first();

            $payload = [
                'sc_poli_status_'      => '1',
                'sc_poli_ket'          => $j['jadwal'],
                'day_id'               => $j['hari'],
                'poli_id'              => $poli->poli_id,
                'dr_id'                => $dokter->dr_id,
                'shift'                => $shiftRow->shift ?? 1,
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

        $this->dispatch('toast', type: 'success', message: "Sync selesai: {$ok} berhasil, {$skip} dilewati.");
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

    {{-- HEADER --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Setup Jadwal BPJS
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Sinkronisasi jadwal pelayanan dokter dari BPJS ke database RS
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- Search poli + LOV --}}
                    <div class="relative w-full lg:max-w-md">
                        <x-text-input type="text" wire:model.live.debounce.400ms="searchPoli"
                            placeholder="Cari poli BPJS (min. 3 karakter)" class="block w-full" autofocus />
                        <div wire:loading wire:target="searchPoli" class="absolute right-2 top-2">
                            <x-loading />
                        </div>

                        @if($showLov && count($poliLov))
                        <div class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            @foreach($poliLov as $p)
                            <button type="button"
                                wire:click="selectPoli('{{ $p['kode'] }}', '{{ addslashes($p['nama']) }}')"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $p['kode'] }}</span>
                                <span class="ml-2 text-gray-700 dark:text-gray-200">{{ $p['nama'] }}</span>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Right: datepicker + tombol --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <x-text-input datepicker datepicker-autohide datepicker-format="dd/mm/yyyy"
                                type="text" class="pl-9 w-40" placeholder="dd/mm/yyyy"
                                wire:model.lazy="dateRef" />
                        </div>

                        <x-primary-button wire:click="loadJadwalBpjs" wire:loading.attr="disabled" wire:target="loadJadwalBpjs">
                            <div wire:loading wire:target="loadJadwalBpjs" class="mr-1"><x-loading /></div>
                            <span wire:loading.remove wire:target="loadJadwalBpjs">Muat Jadwal</span>
                        </x-primary-button>

                        @if(count($jadwalBpjs))
                        <x-primary-button wire:click="syncSemua" wire:loading.attr="disabled" wire:target="syncSemua"
                            wire:confirm="Sync semua {{ count($jadwalBpjs) }} jadwal ke database RS?">
                            <div wire:loading wire:target="syncSemua" class="mr-1"><x-loading /></div>
                            <span wire:loading.remove wire:target="syncSemua">Sync Semua ({{ count($jadwalBpjs) }})</span>
                        </x-primary-button>
                        @endif
                    </div>
                </div>

                @if($selectedPoliName)
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Poli terpilih:
                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $selectedPoliName }}</span>
                    <span class="text-gray-400">({{ $selectedPoliId }})</span>
                </p>
                @endif
            </div>

            {{-- ═══ Tabel Jadwal dari BPJS ═══ --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">Dokter</th>
                                <th class="px-4 py-3 font-semibold">Jadwal BPJS</th>
                                <th class="px-4 py-3 font-semibold">Status Mapping RS</th>
                                <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($jadwalBpjs as $jd)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold">{{ $jd['namadokter'] }}</div>
                                    <div class="text-xs text-blue-500">{{ $jd['kodedokter'] }} / {{ $jd['kodesubspesialis'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $jd['namapoli'] }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold">{{ $jd['jadwal'] }}</div>
                                    <div class="text-xs text-gray-400">Hari {{ $jd['hari'] }} — {{ $jd['namahari'] }}</div>
                                    <div class="text-xs text-gray-400">Kapasitas: {{ $jd['kapasitaspasien'] }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $drMap   = \Illuminate\Support\Facades\DB::table('rsmst_doctors')->where('kd_dr_bpjs', $jd['kodedokter'])->first();
                                        $poliMap = \Illuminate\Support\Facades\DB::table('rsmst_polis')->where('kd_poli_bpjs', $jd['kodepoli'])->first();
                                    @endphp
                                    <div class="text-xs {{ $drMap ? 'text-green-600' : 'text-red-500' }} mb-1">
                                        Dokter: {{ $drMap ? '✓ ' . $drMap->dr_name : '✗ Belum di-mapping' }}
                                    </div>
                                    <div class="text-xs {{ $poliMap ? 'text-green-600' : 'text-red-500' }}">
                                        Poli: {{ $poliMap ? '✓ ' . $poliMap->poli_desc : '✗ Belum di-mapping' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-primary-button type="button"
                                        wire:click="syncJadwal('{{ $jd['kodepoli'] }}','{{ $jd['kodedokter'] }}','{{ addslashes($jd['namadokter']) }}',{{ (int)$jd['hari'] }},'{{ $jd['jadwal'] }}',{{ (int)$jd['kapasitaspasien'] }})">
                                        Sync
                                    </x-primary-button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ $selectedPoliId ? 'Jadwal tidak ditemukan untuk poli ini.' : 'Pilih poli untuk memuat jadwal dari BPJS.' }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ═══ Jadwal Aktif RS ═══ --}}
            <div class="pb-2 mb-4 mt-6 bg-gray-400 bg-opacity-25 rounded-lg">
                <div class="my-4 text-3xl font-bold text-center">
                    Jadwal Dokter {{ env('SATUSEHAT_ORGANIZATION_NAME') }}
                </div>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($this->jadwalRS as $hari)
                    <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">{{ $hari['day_desc'] }} ({{ count($hari['jadwal']) }})</th>
                                <th class="px-4 py-3">Jadwal</th>
                                <th class="px-4 py-3">Kuota</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800">
                            @forelse($hari['jadwal'] as $key => $jd)
                            <tr class="border-b group dark:border-gray-700">
                                <td class="px-4 py-3 group-hover:bg-gray-100 dark:group-hover:bg-gray-700 whitespace-nowrap">
                                    <span class="font-semibold text-primary">{{ ($key + 1) . '. ' . $jd->dr_name }}</span><br>
                                    @if(in_array($jd->poli_desc, ['POLI UMUM', 'OK']))
                                        <span class="text-xs text-gray-500">{{ $jd->poli_desc }}</span>
                                    @else
                                        <x-badge>{{ $jd->poli_desc }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 group-hover:bg-gray-100 dark:group-hover:bg-gray-700 whitespace-nowrap">
                                    <span class="font-semibold text-gray-700 dark:text-white">{{ substr($jd->mulai_praktek, 0, 5) }}&ndash;{{ substr($jd->selesai_praktek, 0, 5) }}</span><br>
                                    <span class="text-xs text-gray-500">Shift {{ $jd->shift }}</span>
                                </td>
                                <td class="px-4 py-3 group-hover:bg-gray-100 dark:group-hover:bg-gray-700 whitespace-nowrap">
                                    Kuota {{ $jd->kuota }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-xs text-gray-400 text-center">Tidak ada jadwal</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @endforeach
                </div>
            </div>

            {{-- ═══ Dokter Aktif Belum Terjadwal ═══ --}}
            @if(count($this->dokterBelumTerjadwal))
            <div class="p-4 my-4 bg-red-100 dark:bg-red-900/20 rounded-lg">
                <div class="my-4 text-3xl font-bold text-center text-red-500">
                    Dokter Aktif Belum Masuk Jadwal {{ env('SATUSEHAT_ORGANIZATION_NAME') }}
                    <span class="text-xl">({{ count($this->dokterBelumTerjadwal) }})</span>
                </div>
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="overflow-x-auto rounded-2xl">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="px-4 py-3 font-semibold">Dokter</th>
                                    <th class="px-4 py-3 font-semibold">Poli</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                @foreach($this->dokterBelumTerjadwal as $key => $d)
                                <tr class="hover:bg-red-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-semibold text-red-500">
                                        {{ ($key + 1) . '. ' . $d->dr_name }}
                                    </td>
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
            </div>
            @endif

        </div>
    </div>
</div>
