<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {
    use WithPagination, AplicaresTrait, SirsTrait;

    /* ─── Filter Bangsal ──────────────────────────────────────── */
    public string $searchBangsal = '';
    public int $itemsPerPage = 10;

    /* ─── Pilihan bangsal → tampil kamar ──────────────────────── */
    public ?string $selectedBangsalId = null;
    public string $selectedBangsalName = '';

    /* ─── Filter Kamar ────────────────────────────────────────── */
    public string $searchKamar = '';
    public int $itemsPerPageKamar = 10;

    /* ─── Expand bed per kamar ────────────────────────────────── */
    public array $expandedRooms = [];
    public array $bedsCache = [];

    /* ─── Bulk Daftarkan Semua ────────────────────────────────── */
    public array $aplBulkResults  = [];
    public array $sirsBulkResults = [];
    public array $aplClassMap     = []; // class_id => aplic_kodekelas (mapping bulk)
    public array $aplRefList      = []; // referensi dari Aplicares API
    public bool  $loadingAplRef   = false;
    public array $sirsClassMap    = []; // class_id => sirs_id_tt (mapping bulk)
    public array $sirsRefList     = []; // referensi tipe TT dari SIRS API
    public bool  $loadingSirsRef  = false;

    public function updatedSearchBangsal(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }
    public function updatedSearchKamar(): void
    {
        $this->resetPage('pageKamar');
    }
    public function updatedItemsPerPageKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    /* ─── Dispatch ke actions ─────────────────────────────────── */
    public function openCreateBangsal(): void
    {
        $this->dispatch('master.kamar.openCreateBangsal');
    }
    public function openEditBangsal(string $id): void
    {
        $this->dispatch('master.kamar.openEditBangsal', bangsalId: $id);
    }
    public function requestDeleteBangsal(string $id): void
    {
        $this->dispatch('master.kamar.deleteBangsal', bangsalId: $id);
    }

    public function openCreateKamar(): void
    {
        if (!$this->selectedBangsalId) {
            return;
        }
        $this->dispatch('master.kamar.openCreateKamar', bangsalId: $this->selectedBangsalId);
    }
    public function openEditKamar(string $id): void
    {
        $this->dispatch('master.kamar.openEditKamar', roomId: $id);
    }
    public function requestDeleteKamar(string $id): void
    {
        $this->dispatch('master.kamar.deleteKamar', roomId: $id);
    }

    public function openCreateBed(string $roomId): void
    {
        $this->dispatch('master.kamar.openCreateBed', roomId: $roomId);
    }
    public function openEditBed(string $bedNo, string $roomId): void
    {
        $this->dispatch('master.kamar.openEditBed', bedNo: $bedNo, roomId: $roomId);
    }
    public function requestDeleteBed(string $bedNo, string $roomId): void
    {
        $this->dispatch('master.kamar.deleteBed', bedNo: $bedNo, roomId: $roomId);
    }

    /* ─── Refresh setelah save/delete ────────────────────────── */
    #[On('master.kamar.saved')]
    public function afterSaved(string $entity, string $roomId = ''): void
    {
        if ($entity === 'bangsal') {
            $this->resetPage();
        }
        if ($entity === 'kamar') {
            unset($this->computedPropertyCache); // reset computed
            $this->resetPage('pageKamar');
        }
        if ($entity === 'bed' && $roomId) {
            // Hapus cache bed room ini supaya re-load saat expand
            unset($this->bedsCache[$roomId]);
            $this->expandedRooms = array_values(array_filter($this->expandedRooms, fn($id) => $id !== $roomId));
        }
    }

    /* ─── Query Bangsal ───────────────────────────────────────── */
    #[Computed]
    public function bangsals()
    {
        $q = DB::table(DB::raw('rsmst_bangsals b'))
            ->selectRaw(
                "
                b.bangsal_id,
                b.bangsal_name,
                b.sl_codefrom,
                b.bangsal_seq,
                b.bed_bangsal,
                COUNT(DISTINCT r.room_id) AS jumlah_kamar,
                COUNT(bd.bed_no)          AS jumlah_bed
            ",
            )
            ->leftJoin(DB::raw('rsmst_rooms r'), 'b.bangsal_id', '=', 'r.bangsal_id')
            ->leftJoin(DB::raw('rsmst_beds bd'), 'r.room_id', '=', 'bd.room_id')
            ->groupBy('b.bangsal_id', 'b.bangsal_name', 'b.sl_codefrom', 'b.bangsal_seq', 'b.bed_bangsal')
            ->orderBy('b.bangsal_seq')
            ->orderBy('b.bangsal_name');

        if (trim($this->searchBangsal) !== '') {
            $kw = mb_strtoupper(trim($this->searchBangsal));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(b.bangsal_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(b.bangsal_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    /* ─── Pilih Bangsal ───────────────────────────────────────── */
    public function selectBangsal(string $id, string $name): void
    {
        $this->selectedBangsalId = $id;
        $this->selectedBangsalName = $name;
        $this->searchKamar = '';
        $this->expandedRooms = [];
        $this->bedsCache = [];
        $this->resetPage('pageKamar');
    }

    /* ─── Query Kamar ─────────────────────────────────────────── */
    #[Computed]
    public function rooms()
    {
        if (!$this->selectedBangsalId) {
            return null;
        }

        $q = DB::table(DB::raw('rsmst_rooms r'))
            ->selectRaw(
                "
                r.room_id,
                r.room_name,
                r.class_id,
                c.class_desc,
                r.aplic_kodekelas,
                r.room_price,
                r.perawatan_price,
                r.common_service,
                r.active_status,
                COUNT(bd.bed_no) AS jumlah_bed
            ",
            )
            ->leftJoin(DB::raw('rsmst_class c'), 'r.class_id', '=', 'c.class_id')
            ->leftJoin(DB::raw('rsmst_beds bd'), 'r.room_id', '=', 'bd.room_id')
            ->where('r.bangsal_id', $this->selectedBangsalId)
            ->groupBy('r.room_id', 'r.room_name', 'r.class_id', 'c.class_desc', 'r.aplic_kodekelas', 'r.room_price', 'r.perawatan_price', 'r.common_service', 'r.active_status')
            ->orderBy('r.room_name');

        if (trim($this->searchKamar) !== '') {
            $kw = mb_strtoupper(trim($this->searchKamar));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(r.room_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(r.room_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPageKamar, ['*'], 'pageKamar');
    }

    /* ─── Toggle expand bed ───────────────────────────────────── */
    public function toggleRoom(string $roomId): void
    {
        if (in_array($roomId, $this->expandedRooms)) {
            $this->expandedRooms = array_values(array_filter($this->expandedRooms, fn($id) => $id !== $roomId));
            return;
        }

        $this->expandedRooms[] = $roomId;

        if (!isset($this->bedsCache[$roomId])) {
            $beds = DB::table('rsmst_beds')->select('bed_no', 'bed_desc')->where('room_id', $roomId)->orderBy('bed_no')->get();

            $this->bedsCache[$roomId] = $beds->map(fn($b) => (array) $b)->toArray();
        }
    }

    /* ─── Buka modal ketersediaan ────────────────────────────── */
    public function openPanel(): void
    {
        $this->dispatch('open-modal', name: 'ketersediaan-kamar');
    }

    /* ─── Buka modal bulk daftarkan ──────────────────────────── */
    public function openBulkDaftar(): void
    {
        $this->aplBulkResults  = [];
        $this->sirsBulkResults = [];
        // Pre-fill aplClassMap dari aplic_kodekelas yang sudah tersimpan per class_id
        $this->aplClassMap = DB::table('rsmst_rooms')
            ->whereNotNull('aplic_kodekelas')->where('aplic_kodekelas', '!=', '')
            ->selectRaw('class_id, MIN(aplic_kodekelas) as aplic_kodekelas')
            ->groupBy('class_id')->pluck('aplic_kodekelas', 'class_id')->toArray();

        // Pre-fill sirsClassMap dari sirs_id_tt yang sudah tersimpan per class_id
        $this->sirsClassMap = DB::table('rsmst_rooms')
            ->whereNotNull('sirs_id_tt')->where('sirs_id_tt', '!=', '')
            ->selectRaw('class_id, MIN(sirs_id_tt) as sirs_id_tt')
            ->groupBy('class_id')->pluck('sirs_id_tt', 'class_id')->toArray();
        $this->dispatch('open-modal', name: 'bulk-daftar-kamar');
    }

    public function loadAplRef(): void
    {
        $this->loadingAplRef = true;
        $this->aplRefList    = [];
        try {
            $res  = $this->referensiKamar()->getOriginalContent();
            $list = $res['response']['list'] ?? ($res['list'] ?? ($res['data'] ?? []));
            $this->aplRefList = is_array($list) ? array_values($list) : [];
        } catch (\Throwable) {}
        $this->loadingAplRef = false;
    }

    public function loadSirsRef(): void
    {
        $this->loadingSirsRef = true;
        $this->sirsRefList    = [];
        try {
            $res  = $this->sirsRefTempaTidur()->getOriginalContent();
            $list = $res['tempat_tidur'] ?? ($res['response'] ?? ($res['data'] ?? []));
            $this->sirsRefList = is_array($list) ? array_values($list) : [];
        } catch (\Throwable) {}
        $this->loadingSirsRef = false;
    }

    private function bulkRooms(string $select): \Illuminate\Support\Collection
    {
        return DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
            ->leftJoin('rsmst_bangsals as b', 'r.bangsal_id', '=', 'b.bangsal_id')
            ->selectRaw("r.room_id, r.room_name, r.class_id, c.class_desc, b.bangsal_name,
                         (SELECT COUNT(*) FROM rsmst_beds bd WHERE bd.room_id = r.room_id) AS jumlah_bed, {$select}")
            ->orderBy('b.bangsal_name')
            ->orderBy('r.room_name')
            ->get();
    }

    public function jalankanBulkAplic(): void
    {
        $rooms   = $this->bulkRooms('r.aplic_kodekelas');
        $results = [];

        foreach ($rooms as $room) {
            $namaRuang = trim(($room->bangsal_name ?? '') . ' - ' . ($room->room_name ?? '') . ' - ' . ($room->class_desc ?? ''));
            $bedCount  = (int) ($room->jumlah_bed ?? 0);
            $row       = ['room_id' => $room->room_id, 'namaRuang' => $namaRuang, 'ok' => null, 'msg' => 'Kode Aplicares belum diisi'];

            // Gunakan kode dari DB atau dari mapping kelas jika belum diisi
            $kodekelas = $room->aplic_kodekelas ?: ($this->aplClassMap[$room->class_id] ?? null);

            if ($kodekelas) {
                // Simpan mapping ke DB jika belum ada
                if (!$room->aplic_kodekelas) {
                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['aplic_kodekelas' => $kodekelas]);
                }
                $payload = [
                    'kodekelas'          => $kodekelas,
                    'koderuang'          => $room->room_id,
                    'namaruang'          => $namaRuang,
                    'kapasitas'          => $bedCount,
                    'tersedia'           => $bedCount,
                    'tersediapria'       => 0,
                    'tersediawanita'     => 0,
                    'tersediapriawanita' => 0,
                ];
                try {
                    $res  = $this->ruanganBaru($payload)->getOriginalContent();
                    $code = $res['metadata']['code'] ?? 500;
                    if ($code == 1) {
                        $row['ok']  = true;
                        $row['msg'] = 'Berhasil didaftarkan';
                    } else {
                        $resU  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
                        $codeU = $resU['metadata']['code'] ?? 500;
                        $row['ok']  = $codeU == 1;
                        $row['msg'] = $codeU == 1 ? 'Diperbarui' : ($resU['metadata']['message'] ?? 'Gagal');
                    }
                } catch (\Throwable $e) {
                    $row['ok']  = false;
                    $row['msg'] = $e->getMessage();
                }
            }

            $results[] = $row;
        }

        $this->aplBulkResults = $results;
    }

    public function jalankanBulkSirs(): void
    {
        $rooms = $this->bulkRooms('r.sirs_id_tt, r.sirs_id_t_tt');

        // Pre-fetch list SIRS sekali (untuk path "sudah ada")
        $sirsCache = [];
        try {
            $raw       = $this->sirsGetTempaTidur()->getOriginalContent();
            $sirsCache = $raw['fasyankes'] ?? [];
        } catch (\Throwable) {}

        $results = [];

        foreach ($rooms as $room) {
            $namaRuang = trim(($room->bangsal_name ?? '') . ' - ' . ($room->room_name ?? '') . ' - ' . ($room->class_desc ?? ''));
            $bedCount  = (int) ($room->jumlah_bed ?? 0);
            $sirsIdTt  = $room->sirs_id_tt ?: ($this->sirsClassMap[$room->class_id] ?? null);
            $row       = ['room_id' => $room->room_id, 'namaRuang' => $namaRuang, 'ok' => null, 'msg' => 'id_tt SIRS belum diisi'];

            if ($sirsIdTt) {
                // Simpan mapping ke DB jika belum ada
                if (!$room->sirs_id_tt) {
                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_tt' => $sirsIdTt]);
                }
                $payload = [
                    'ruang'               => $namaRuang,
                    'jumlah_ruang'        => 1,
                    'jumlah'              => $bedCount,
                    'terpakai'            => 0,
                    'terpakai_suspek'     => 0,
                    'terpakai_konfirmasi' => 0,
                    'antrian'             => 0,
                    'prepare'             => 0,
                    'prepare_plan'        => 0,
                    'covid'               => 0,
                ];
                try {
                    if ($room->sirs_id_t_tt) {
                        // PUT — sudah punya id_t_tt
                        $res    = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $room->sirs_id_t_tt]))->getOriginalContent();
                        $first  = $res['fasyankes'][0] ?? [];
                        $status = (string) ($first['status'] ?? '500');
                        $row['ok']  = $status === '200';
                        $row['msg'] = $status === '200' ? 'Diperbarui' : ($first['message'] ?? 'Gagal');
                    } else {
                        // POST — daftar baru
                        $res    = $this->sirsKirimTempaTidur(array_merge($payload, ['id_tt' => $sirsIdTt]))->getOriginalContent();
                        $first  = $res['fasyankes'][0] ?? [];
                        $status = (string) ($first['status'] ?? '500');
                        $msg    = $first['message'] ?? '-';

                        if ($status === '200' && !str_contains($msg, 'sudah ada')) {
                            $idTTt = (string) ($first['id_t_tt'] ?? '');
                            DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_t_tt' => $idTTt ?: null]);
                            $row['ok']  = true;
                            $row['msg'] = 'Berhasil didaftarkan';
                        } elseif ($status === '200' && str_contains($msg, 'sudah ada')) {
                            $match = collect($sirsCache)->first(fn($r) =>
                                (string) ($r['id_tt'] ?? '') === (string) $sirsIdTt &&
                                ($r['id_t_tt'] ?? null) !== null
                            );
                            if ($match) {
                                $idTTt  = (string) $match['id_t_tt'];
                                $resU   = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $idTTt]))->getOriginalContent();
                                $firstU = $resU['fasyankes'][0] ?? [];
                                $statU  = (string) ($firstU['status'] ?? '500');
                                if ($statU === '200') {
                                    DB::table('rsmst_rooms')->where('room_id', $room->room_id)->update(['sirs_id_t_tt' => $idTTt]);
                                    $row['ok']  = true;
                                    $row['msg'] = 'Sudah ada, berhasil diperbarui';
                                } else {
                                    $row['ok']  = false;
                                    $row['msg'] = 'Gagal update: ' . ($firstU['message'] ?? '-');
                                }
                            } else {
                                $row['ok']  = null;
                                $row['msg'] = 'Sudah ada di SIRS, id_t_tt tidak ditemukan';
                            }
                        } else {
                            $row['ok']  = false;
                            $row['msg'] = $msg;
                        }
                    }
                } catch (\Throwable $e) {
                    $row['ok']  = false;
                    $row['msg'] = $e->getMessage();
                }
            }

            $results[] = $row;
        }

        $this->sirsBulkResults = $results;
    }

};
?>

<div>

    {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Master Kamar
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Bangsal, kamar & bed rawat inap
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
            <x-outline-button wire:click="openBulkDaftar" class="shrink-0 gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Daftarkan Semua ke Aplicares &amp; SIRS
            </x-outline-button>
            <x-outline-button wire:click="openPanel" class="shrink-0 gap-2">
                <svg class="w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Data Kamar Terdaftar di Aplicares &amp; SIRS
            </x-outline-button>
            </div>{{-- closes flex gap-2 shrink-0 --}}
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6 space-y-6 ">


            <div class="grid grid-cols-2 gap-2"> {{-- Tabel Bangsal --}}
                <div>
                    {{-- ══ BANGSAL ══════════════════════════════════════════ --}}
                    {{-- Toolbar Bangsal --}}
                    <div
                        class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-xs">
                                <x-input-label for="searchBangsal" value="Cari Bangsal" class="sr-only" />
                                <x-text-input id="searchBangsal" type="text"
                                    wire:model.live.debounce.300ms="searchBangsal" placeholder="Cari bangsal..."
                                    class="block w-full" />
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-28">
                                    <x-select-input wire:model.live="itemsPerPage">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="15">15</option>
                                        <option value="20">20</option>
                                    </x-select-input>
                                </div>
                                <x-primary-button type="button" wire:click="openCreateBangsal">
                                    + Tambah Bangsal
                                </x-primary-button>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-5 py-3 font-semibold">BANGSAL</th>
                                        <th class="px-5 py-3 font-semibold">KAPASITAS</th>
                                        <th class="px-5 py-3 font-semibold">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                    @forelse ($this->bangsals as $bangsal)
                                        @php $isActive = $selectedBangsalId === $bangsal->bangsal_id; @endphp
                                        <tr wire:key="bangsal-{{ $bangsal->bangsal_id }}"
                                            wire:click="selectBangsal('{{ $bangsal->bangsal_id }}', '{{ addslashes($bangsal->bangsal_name) }}')"
                                            class="cursor-pointer transition
                                           {{ $isActive
                                               ? 'bg-brand-green/5 dark:bg-brand-green/10 ring-1 ring-inset ring-brand-green/30 dark:ring-brand-green/40'
                                               : 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                            {{-- BANGSAL: nama + id + kode sl + seq --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    @if ($isActive)
                                                        <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                    <span
                                                        class="font-semibold text-base {{ $isActive ? 'text-brand dark:text-brand-lime' : 'text-gray-800 dark:text-gray-100' }}">
                                                        {{ $bangsal->bangsal_name }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-mono">{{ $bangsal->bangsal_id }}</span>
                                                    @if ($bangsal->sl_codefrom)
                                                        <span>SL: <span
                                                                class="font-mono">{{ $bangsal->sl_codefrom }}</span></span>
                                                    @endif
                                                    @if ($bangsal->bangsal_seq)
                                                        <span>Seq: {{ $bangsal->bangsal_seq }}</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- KAPASITAS: jumlah kamar + bed + bed bangsal --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <x-badge variant="info">{{ $bangsal->jumlah_kamar }} Kamar</x-badge>
                                                    <x-badge variant="success">{{ $bangsal->jumlah_bed }} Bed</x-badge>
                                                </div>
                                                @if ($bangsal->bed_bangsal)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        Bed bangsal: <span
                                                            class="font-mono">{{ $bangsal->bed_bangsal }}</span>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- AKSI --}}
                                            <td class="px-5 py-4 align-top" wire:click.stop>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-outline-button type="button"
                                                        wire:click="openEditBangsal('{{ $bangsal->bangsal_id }}')">
                                                        Edit
                                                    </x-outline-button>
                                                    <x-confirm-button variant="danger" :action="'requestDeleteBangsal(\'' . $bangsal->bangsal_id . '\')'"
                                                        title="Hapus Bangsal"
                                                        message="Yakin hapus bangsal {{ $bangsal->bangsal_name }}?"
                                                        confirmText="Ya, hapus" cancelText="Batal">
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3"
                                                class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                                Data bangsal tidak ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->bangsals->links() }}
                        </div>
                    </div>
                </div>
                {{-- ══ KAMAR (muncul setelah bangsal dipilih) ═══════════ --}}
                @if ($selectedBangsalId)
                    <div wire:loading.class="opacity-60" wire:target="selectBangsal">

                        {{-- Toolbar Kamar --}}
                        <div
                            class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div class="flex items-center gap-3 w-full lg:max-w-xs">
                                    <x-text-input type="text" wire:model.live.debounce.300ms="searchKamar"
                                        placeholder="Cari kamar..." class="block w-full" />
                                </div>
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-28">
                                        <x-select-input wire:model.live="itemsPerPageKamar">
                                            <option value="5">5</option>
                                            <option value="10">10</option>
                                            <option value="15">15</option>
                                            <option value="20">20</option>
                                        </x-select-input>
                                    </div>
                                    <x-primary-button type="button" wire:click="openCreateKamar">
                                        + Tambah Kamar
                                    </x-primary-button>
                                </div>
                            </div>
                        </div>

                        {{-- Rekap Kamar --}}
                        @php
                            $rekapRooms  = $this->rooms;
                            $totalKamar  = $rekapRooms->total();
                            $aktifKamar  = collect($rekapRooms->items())->where('active_status', '1')->count();
                            $nonAktif    = collect($rekapRooms->items())->where('active_status', '0')->count();
                        @endphp
                        <div class="flex items-center gap-3 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span class="text-gray-400 dark:text-gray-500">Total</span>
                                <span class="font-bold text-gray-700 dark:text-gray-200">{{ $totalKamar }} kamar</span>
                            </div>
                            <span class="text-gray-200 dark:text-gray-700">·</span>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-gray-500 dark:text-gray-400">Aktif</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $aktifKamar }}</span>
                            </div>
                            <span class="text-gray-200 dark:text-gray-700">·</span>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-gray-500 dark:text-gray-400">Non Aktif</span>
                                <span class="font-bold text-red-500 dark:text-red-400">{{ $nonAktif }}</span>
                            </div>
                        </div>

                        {{-- Tabel Kamar --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                        <tr class="text-left">
                                            <th class="px-4 py-3 w-8"></th>
                                            <th class="px-5 py-3 font-semibold">
                                                KAMAR
                                                <span class="font-normal text-brand dark:text-brand-lime ml-1">—
                                                    {{ $selectedBangsalName }}</span>
                                            </th>
                                            <th class="px-5 py-3 font-semibold">TARIF</th>
                                            <th class="px-5 py-3 font-semibold">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                        @forelse ($this->rooms as $room)
                                            @php
                                                $isExpanded = in_array($room->room_id, $expandedRooms);
                                                $beds = $bedsCache[$room->room_id] ?? [];
                                                $isActive = (string) $room->active_status === '1';
                                            @endphp

                                            {{-- Row Kamar --}}
                                            <tr wire:key="room-{{ $room->room_id }}"
                                                wire:click="toggleRoom('{{ $room->room_id }}')"
                                                class="cursor-pointer transition
                                                   {{ $isExpanded ? 'bg-indigo-50 dark:bg-indigo-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                                {{-- Chevron --}}
                                                <td class="px-4 py-4 text-center text-gray-400 align-top">
                                                    <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>

                                                {{-- KAMAR: nama + id + kelas + badge BPJS + status + bed --}}
                                                <td class="px-5 py-4 align-top space-y-1">
                                                    <div
                                                        class="font-semibold text-base text-gray-800 dark:text-gray-100">
                                                        {{ $room->room_name }}
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                        <span class="font-mono">{{ $room->room_id }}</span>
                                                        <span>{{ $room->class_desc ?? 'Kelas ' . $room->class_id }}</span>
                                                        @if ($room->aplic_kodekelas)
                                                            <span
                                                                class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold
                                                                         bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                                                BPJS: {{ $room->aplic_kodekelas }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-2 pt-0.5">
                                                        <x-badge :variant="$isActive ? 'success' : 'danger'">
                                                            {{ $isActive ? 'Aktif' : 'Non Aktif' }}
                                                        </x-badge>
                                                        <x-badge variant="info">{{ $room->jumlah_bed }} Bed</x-badge>
                                                    </div>
                                                </td>

                                                {{-- TARIF: kamar | perawatan --}}
                                                <td class="px-5 py-4 align-top">
                                                    <div class="flex items-start gap-6">
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Kamar</div>
                                                            <div
                                                                class="font-mono font-semibold text-gray-700 dark:text-gray-200">
                                                                {{ number_format($room->room_price ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Perawatan</div>
                                                            <div class="font-mono text-gray-600 dark:text-gray-300">
                                                                {{ number_format($room->perawatan_price ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Pel. Umum</div>
                                                            <div class="font-mono text-gray-600 dark:text-gray-300">
                                                                {{ number_format($room->common_service ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {{-- AKSI --}}
                                                <td class="px-5 py-4 align-top" wire:click.stop>
                                                    <div class="flex flex-wrap gap-2">
                                                        <x-outline-button type="button"
                                                            wire:click="openEditKamar('{{ $room->room_id }}')">
                                                            Edit
                                                        </x-outline-button>
                                                        <x-confirm-button variant="danger" :action="'requestDeleteKamar(\'' . $room->room_id . '\')'"
                                                            title="Hapus Kamar"
                                                            message="Yakin hapus kamar {{ $room->room_name }}?"
                                                            confirmText="Ya, hapus" cancelText="Batal">
                                                            Hapus
                                                        </x-confirm-button>
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- Row Bed (expandable) --}}
                                            @if ($isExpanded)
                                                <tr wire:key="beds-{{ $room->room_id }}"
                                                    class="bg-indigo-50/60 dark:bg-indigo-900/5">
                                                    <td colspan="4" class="px-8 py-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            {{-- Tambah bed --}}
                                                            <x-ghost-button
                                                                wire:click="openCreateBed('{{ $room->room_id }}')"
                                                                class="!text-indigo-500 hover:!bg-indigo-50 dark:!text-indigo-400 dark:hover:!bg-indigo-900/20
                                                                       !px-3 !py-1.5 !text-xs border border-dashed border-indigo-300 dark:border-indigo-600
                                                                       focus:!ring-indigo-200 dark:focus:!ring-indigo-900/40">
                                                                <svg class="w-3.5 h-3.5" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="M12 4v16m8-8H4" />
                                                                </svg>
                                                                Tambah Bed
                                                            </x-ghost-button>

                                                            @if (empty($beds))
                                                                <p class="text-xs text-gray-400 italic self-center">
                                                                    Belum
                                                                    ada bed.</p>
                                                            @else
                                                                @foreach ($beds as $bed)
                                                                    <div
                                                                        class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                                                                            border border-indigo-200 dark:border-indigo-700
                                                                            bg-white dark:bg-gray-800 shadow-sm text-xs">
                                                                        <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M5 12h14M5 12c0-2.761 2.686-5 6-5s6 2.239 6 5M5 12c0 2.761 2.686 5 6 5s6-2.239 6-5" />
                                                                        </svg>
                                                                        <span
                                                                            class="font-bold text-gray-700 dark:text-gray-200 font-mono">{{ $bed['bed_no'] }}</span>
                                                                        @if (!empty($bed['bed_desc']))
                                                                            <span
                                                                                class="text-gray-400 dark:text-gray-500">{{ $bed['bed_desc'] }}</span>
                                                                        @endif
                                                                        <span
                                                                            class="hidden group-hover:inline-flex items-center gap-1 ml-1">
                                                                            <x-ghost-button
                                                                                wire:click="openEditBed('{{ $bed['bed_no'] }}', '{{ $room->room_id }}')"
                                                                                class="!text-indigo-500 hover:!bg-indigo-50 dark:!text-indigo-400 dark:hover:!bg-indigo-900/20
                                                                                       !p-1 !rounded focus:!ring-indigo-200">
                                                                                <svg class="w-3 h-3" fill="none"
                                                                                    stroke="currentColor"
                                                                                    viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828A2 2 0 019 16H7v-2a2 2 0 01.586-1.414z" />
                                                                                </svg>
                                                                            </x-ghost-button>
                                                                            <x-ghost-button
                                                                                wire:click="requestDeleteBed('{{ $bed['bed_no'] }}', '{{ $room->room_id }}')"
                                                                                wire:confirm="Hapus bed {{ $bed['bed_no'] }}?"
                                                                                class="!text-red-400 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-900/20
                                                                                       !p-1 !rounded focus:!ring-red-200">
                                                                                <svg class="w-3 h-3" fill="none"
                                                                                    stroke="currentColor"
                                                                                    viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M6 18L18 6M6 6l12 12" />
                                                                                </svg>
                                                                            </x-ghost-button>
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif

                                        @empty
                                            <tr>
                                                <td colspan="4"
                                                    class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                    Tidak ada kamar untuk bangsal ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                                {{ $this->rooms->links() }}
                            </div>
                        </div>

                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <p class="text-sm">Pilih bangsal di atas untuk melihat daftar kamar & bed.</p>
                    </div>
                @endif
            </div>

            {{-- ══ MODAL KETERSEDIAAN KAMAR EKSTERNAL ═══════════════ --}}
            <x-modal name="ketersediaan-kamar" size="full" height="full" focusable>
                <div x-data="{ tab: 'aplicares' }" class="flex flex-col h-[calc(100vh-8rem)]">

                    {{-- Header --}}
                    <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                        <div class="absolute inset-0 opacity-[0.05] dark:opacity-[0.08]"
                            style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                        </div>
                        <div class="relative flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-9 h-9 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15 shrink-0">
                                    <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                                        Data Kamar Terdaftar di Aplicares &amp; SIRS
                                    </h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                                     bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS
                                            Aplicares</span>
                                        <span
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                                     bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS
                                            Kemenkes</span>
                                    </div>
                                </div>
                            </div>
                            <x-secondary-button type="button"
                                x-on:click="$dispatch('close-modal', { name: 'ketersediaan-kamar' })"
                                class="!p-2 shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </x-secondary-button>
                        </div>
                    </div>

                    {{-- Tab bar --}}
                    <div class="flex border-b border-gray-200 dark:border-gray-700 shrink-0 bg-white dark:bg-gray-900">
                        <button type="button" @click="tab = 'aplicares'"
                            :class="tab === 'aplicares'
                                ?
                                'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold' :
                                'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                            class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                            Aplicares
                        </button>
                        <button type="button" @click="tab = 'sirs'"
                            :class="tab === 'sirs'
                                ?
                                'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold' :
                                'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                            class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                            Kemenkes
                        </button>
                    </div>

                    {{-- Tab content --}}
                    <div class="flex-1 overflow-hidden flex flex-col bg-white dark:bg-gray-900">
                        <div x-show="tab === 'aplicares'" class="flex flex-col h-full">
                            <livewire:pages::master.master-kamar.tabs.aplicares-actions wire:key="tab-aplicares" />
                        </div>
                        <div x-show="tab === 'sirs'" class="flex flex-col h-full">
                            <livewire:pages::master.master-kamar.tabs.sirs-actions wire:key="tab-sirs" />
                        </div>
                    </div>

                </div>
            </x-modal>

            {{-- ══ MODAL BULK DAFTARKAN SEMUA ══════════════════════════ --}}
            <x-modal name="bulk-daftar-kamar" size="full" height="full" focusable>
                <div class="flex flex-col h-[calc(100vh-8rem)]">

                    {{-- Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 shrink-0">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                                    Daftarkan Semua Kamar ke Aplicares &amp; SIRS
                                </h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    Sinkronisasi seluruh kamar ke BPJS Aplicares dan/atau SIRS Kemenkes sekaligus.
                                </p>
                            </div>
                        </div>
                        <x-secondary-button type="button"
                            x-on:click="$dispatch('close-modal', { name: 'bulk-daftar-kamar' })"
                            class="!p-2 shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-secondary-button>
                    </div>

                    {{-- Tab bar: Aplicares | SIRS --}}
                    <div x-data="{ tab: 'aplicares' }" class="flex flex-col flex-1 overflow-hidden">
                        <div class="flex border-b border-gray-200 dark:border-gray-700 shrink-0 bg-white dark:bg-gray-900">
                            <button type="button" @click="tab = 'aplicares'"
                                :class="tab === 'aplicares'
                                    ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                             bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                                Aplicares
                            </button>
                            <button type="button" @click="tab = 'sirs'"
                                :class="tab === 'sirs'
                                    ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                             bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                                Kemenkes
                            </button>
                        </div>

                        {{-- ── TAB APLICARES ────────────────────────────────── --}}
                        <div x-show="tab === 'aplicares'" class="flex flex-col flex-1 overflow-hidden">

                            {{-- Panduan Langkah Aplicares --}}
                            <div class="px-5 py-3 border-b border-blue-100 dark:border-blue-900/40 shrink-0 bg-white dark:bg-gray-900">
                                <div class="flex items-start gap-0">
                                    {{-- Step 1 --}}
                                    <div class="flex flex-col items-center">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">1</div>
                                        <div class="w-px flex-1 bg-blue-200 dark:bg-blue-800 mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Tarik data kode kelas dari Aplicares</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik tombol <strong class="text-blue-600 dark:text-blue-400">Tarik Data Aplicares</strong> di bawah untuk mengambil daftar kode kelas yang tersedia di sistem BPJS Aplicares.</p>
                                    </div>

                                    <div class="flex flex-col items-center ml-6">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">2</div>
                                        <div class="w-px flex-1 bg-blue-200 dark:bg-blue-800 mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Sesuaikan mapping kelas</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Pilih kode Aplicares yang sesuai untuk setiap kelas kamar RS. Kamar yang sudah punya kode akan diperbarui; yang belum akan didaftarkan baru.</p>
                                    </div>

                                    <div class="flex flex-col items-center ml-6">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold shrink-0">3</div>
                                        <div class="w-px flex-1 bg-transparent mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Jalankan pendaftaran massal</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik <strong class="text-blue-600 dark:text-blue-400">Daftarkan ke Aplicares</strong> — sistem akan memproses semua kamar sekaligus dan menampilkan hasilnya.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Mapping Kelas → Kode Aplicares --}}
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 bg-blue-50/60 dark:bg-blue-900/10">
                                <div class="flex items-center justify-between gap-3 mb-2">
                                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                        Mapping Kelas RS → Kode Aplicares
                                        <span class="font-normal text-blue-500 dark:text-blue-400 ml-1">(untuk kamar yang belum punya kode)</span>
                                    </span>
                                    <x-secondary-button wire:click="loadAplRef" wire:loading.attr="disabled"
                                        wire:target="loadAplRef" class="!py-1 !px-2.5 !text-xs shrink-0">
                                        <x-loading size="xs" wire:loading wire:target="loadAplRef" class="mr-1" />
                                        <svg wire:loading.remove wire:target="loadAplRef" class="w-3 h-3 mr-1"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                        </svg>
                                        <span wire:loading.remove wire:target="loadAplRef">Tarik Data Aplicares</span>
                                        <span wire:loading wire:target="loadAplRef">Menarik…</span>
                                    </x-secondary-button>
                                </div>
                                @php
                                    $kelasList = DB::table('rsmst_class')->select('class_id', 'class_desc')->orderBy('class_id')->get();
                                @endphp
                                <div class="grid grid-cols-5 gap-5">
                                    @foreach ($kelasList as $kls)
                                        <div>
                                            <x-input-label :value="$kls->class_desc" class="truncate" :title="$kls->class_desc" />
                                            <x-select-input wire:model.live="aplClassMap.{{ $kls->class_id }}" class="mt-1 w-full">
                                                <option value="">—</option>
                                                @foreach ($aplRefList as $ref)
                                                    <option value="{{ $ref['kodekelas'] ?? '' }}">{{ $ref['kodekelas'] ?? '' }}</option>
                                                @endforeach
                                                @if (empty($aplRefList) && !empty($aplClassMap[$kls->class_id]))
                                                    <option value="{{ $aplClassMap[$kls->class_id] }}" selected>{{ $aplClassMap[$kls->class_id] }}</option>
                                                @endif
                                            </x-select-input>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Toolbar Aplicares --}}
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                                @if (!empty($aplBulkResults))
                                    @php
                                        $aplOk   = collect($aplBulkResults)->where('ok', true)->count();
                                        $aplFail = collect($aplBulkResults)->where('ok', false)->count();
                                        $aplSkip = collect($aplBulkResults)->where('ok', null)->count();
                                    @endphp
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $aplOk }} ok</span>
                                        @if ($aplFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $aplFail }} gagal</span> @endif
                                        @if ($aplSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $aplSkip }} dilewati</span> @endif
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-gray-400 dark:text-gray-500">{{ count($aplBulkResults) }} kamar</span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                                @endif
                                <x-primary-button wire:click="jalankanBulkAplic"
                                    wire:loading.attr="disabled" wire:target="jalankanBulkAplic"
                                    class="shrink-0 gap-2">
                                    <x-loading size="xs" wire:loading wire:target="jalankanBulkAplic" />
                                    <svg wire:loading.remove wire:target="jalankanBulkAplic"
                                         class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span wire:loading.remove wire:target="jalankanBulkAplic">Daftarkan ke Aplicares</span>
                                    <span wire:loading wire:target="jalankanBulkAplic">Memproses…</span>
                                </x-primary-button>
                            </div>

                            {{-- Loading Aplicares --}}
                            <div wire:loading wire:target="jalankanBulkAplic"
                                 class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                                <x-loading size="md" class="block mb-2" />
                                Mendaftarkan semua kamar ke Aplicares…
                            </div>

                            {{-- Tabel Aplicares --}}
                            <div wire:loading.remove wire:target="jalankanBulkAplic" class="flex-1 overflow-auto">
                                @if (empty($aplBulkResults))
                                    <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500 text-sm">
                                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        <p>Klik <strong>Daftarkan ke Aplicares</strong> untuk memulai sinkronisasi.</p>
                                    </div>
                                @else
                                    @include('pages.master.master-kamar.tabs.bulk-results', ['rows' => $aplBulkResults])
                                @endif
                            </div>
                        </div>

                        {{-- ── TAB SIRS ──────────────────────────────────────── --}}
                        <div x-show="tab === 'sirs'" class="flex flex-col flex-1 overflow-hidden">

                            {{-- Panduan Langkah SIRS --}}
                            <div class="px-5 py-3 border-b border-green-100 dark:border-green-900/40 shrink-0 bg-white dark:bg-gray-900">
                                <div class="flex items-start gap-0">
                                    {{-- Step 1 --}}
                                    <div class="flex flex-col items-center">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">1</div>
                                        <div class="w-px flex-1 bg-green-200 dark:bg-green-800 mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Tarik data tipe tempat tidur dari SIRS</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik tombol <strong class="text-green-600 dark:text-green-400">Tarik Data SIRS</strong> di bawah untuk mengambil daftar tipe tempat tidur dari SIRS Kemenkes.</p>
                                    </div>

                                    <div class="flex flex-col items-center ml-6">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">2</div>
                                        <div class="w-px flex-1 bg-green-200 dark:bg-green-800 mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Sesuaikan mapping kelas ke tipe TT</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Pilih tipe tempat tidur SIRS yang sesuai untuk setiap kelas kamar RS. Kamar yang sudah terdaftar akan diperbarui; yang belum akan didaftarkan baru.</p>
                                    </div>

                                    <div class="flex flex-col items-center ml-6">
                                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-[11px] font-bold shrink-0">3</div>
                                        <div class="w-px flex-1 bg-transparent mt-1 min-h-[20px]"></div>
                                    </div>
                                    <div class="ml-3 pb-4">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Jalankan pendaftaran massal</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Klik <strong class="text-green-600 dark:text-green-400">Daftarkan ke SIRS</strong> — sistem akan memproses semua kamar sekaligus dan menampilkan hasilnya per baris.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Mapping Kelas → Tipe TT SIRS --}}
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 bg-green-50/60 dark:bg-green-900/10">
                                <div class="flex items-center justify-between gap-3 mb-2">
                                    <span class="text-xs font-semibold text-green-700 dark:text-green-300">
                                        Mapping Kelas RS → Kode Tipe TT SIRS
                                        <span class="font-normal text-green-500 dark:text-green-400 ml-1">(untuk kamar yang belum punya id_tt)</span>
                                    </span>
                                    <x-secondary-button wire:click="loadSirsRef" wire:loading.attr="disabled"
                                        wire:target="loadSirsRef" class="!py-1 !px-2.5 !text-xs shrink-0">
                                        <x-loading size="xs" wire:loading wire:target="loadSirsRef" class="mr-1" />
                                        <svg wire:loading.remove wire:target="loadSirsRef" class="w-3 h-3 mr-1"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
                                        </svg>
                                        <span wire:loading.remove wire:target="loadSirsRef">Tarik Data SIRS</span>
                                        <span wire:loading wire:target="loadSirsRef">Menarik…</span>
                                    </x-secondary-button>
                                </div>
                                <div class="grid grid-cols-5 gap-5">
                                    @foreach (DB::table('rsmst_class')->select('class_id', 'class_desc')->orderBy('class_id')->get() as $kls)
                                        <div>
                                            <x-input-label :value="$kls->class_desc" class="truncate" :title="$kls->class_desc" />
                                            <x-select-input wire:model.live="sirsClassMap.{{ $kls->class_id }}" class="mt-1 w-full">
                                                <option value="">—</option>
                                                @foreach ($sirsRefList as $ref)
                                                    <option value="{{ $ref['kode_tt'] ?? '' }}">{{ $ref['kode_tt'] ?? '' }} – {{ $ref['nama_tt'] ?? '' }}</option>
                                                @endforeach
                                                @if (empty($sirsRefList) && !empty($sirsClassMap[$kls->class_id]))
                                                    <option value="{{ $sirsClassMap[$kls->class_id] }}" selected>{{ $sirsClassMap[$kls->class_id] }}</option>
                                                @endif
                                            </x-select-input>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Toolbar SIRS --}}
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0 flex items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/60">
                                @if (!empty($sirsBulkResults))
                                    @php
                                        $srsOk   = collect($sirsBulkResults)->where('ok', true)->count();
                                        $srsFail = collect($sirsBulkResults)->where('ok', false)->count();
                                        $srsSkip = collect($sirsBulkResults)->where('ok', null)->count();
                                    @endphp
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $srsOk }} ok</span>
                                        @if ($srsFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $srsFail }} gagal</span> @endif
                                        @if ($srsSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $srsSkip }} dilewati</span> @endif
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-gray-400 dark:text-gray-500">{{ count($sirsBulkResults) }} kamar</span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">Belum diproses</span>
                                @endif
                                <x-primary-button wire:click="jalankanBulkSirs"
                                    wire:loading.attr="disabled" wire:target="jalankanBulkSirs"
                                    class="shrink-0 gap-2">
                                    <x-loading size="xs" wire:loading wire:target="jalankanBulkSirs" />
                                    <svg wire:loading.remove wire:target="jalankanBulkSirs"
                                         class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span wire:loading.remove wire:target="jalankanBulkSirs">Daftarkan ke SIRS</span>
                                    <span wire:loading wire:target="jalankanBulkSirs">Memproses…</span>
                                </x-primary-button>
                            </div>

                            {{-- Loading SIRS --}}
                            <div wire:loading wire:target="jalankanBulkSirs"
                                 class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
                                <x-loading size="md" class="block mb-2" />
                                Mendaftarkan semua kamar ke SIRS Kemenkes…
                            </div>

                            {{-- Tabel SIRS --}}
                            <div wire:loading.remove wire:target="jalankanBulkSirs" class="flex-1 overflow-auto">
                                @if (empty($sirsBulkResults))
                                    <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500 text-sm">
                                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        <p>Klik <strong>Daftarkan ke SIRS</strong> untuk memulai sinkronisasi.</p>
                                    </div>
                                @else
                                    @include('pages.master.master-kamar.tabs.bulk-results', ['rows' => $sirsBulkResults])
                                @endif
                            </div>
                        </div>
                    </div>{{-- end x-data tab --}}

                </div>
            </x-modal>

        </div>{{-- closes px-6 pt-2 pb-6 space-y-6 --}}
    </div>{{-- closes w-full min-h --}}

    {{-- Child actions (modal CRUD bangsal / kamar / bed) --}}
    <livewire:pages::master.master-kamar.bangsal-actions wire:key="bangsal-actions" />
    <livewire:pages::master.master-kamar.kamar-actions wire:key="kamar-actions" />
    <livewire:pages::master.master-kamar.bed-actions wire:key="bed-actions" />

</div>
