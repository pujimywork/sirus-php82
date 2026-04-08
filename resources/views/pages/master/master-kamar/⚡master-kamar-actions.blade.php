<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';   // create | edit
    public string $entityType = 'bangsal';  // bangsal | kamar | bed

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    // ─── Form Bangsal ──────────────────────────────────────────
    public array $formBangsal = [
        'bangsal_id'   => '',
        'bangsal_name' => '',
        'sl_codefrom'  => '',
        'bangsal_seq'  => '',
        'bed_bangsal'  => '',
    ];

    // ─── Form Kamar ────────────────────────────────────────────
    public array $formKamar = [
        'room_id'          => '',
        'room_name'        => '',
        'bangsal_id'       => '',
        'class_id'         => '',
        'room_price'       => '0',
        'perawatan_price'  => '0',
        'common_service'   => '0',
        'active_status'    => 'AC',
    ];

    // ─── Form Bed ──────────────────────────────────────────────
    public array $formBed = [
        'bed_no'   => '',
        'bed_desc' => '',
        'room_id'  => '',
    ];

    // ─── Referensi kelas (untuk select kamar) ──────────────────
    public array $kelasList = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->loadKelasList();
    }

    private function loadKelasList(): void
    {
        $this->kelasList = DB::table('rsmst_class')
            ->select('class_id', 'class_desc')
            ->orderBy('class_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    // =========================================================
    // BANGSAL
    // =========================================================

    #[On('master.kamar.openCreateBangsal')]
    public function openCreateBangsal(): void
    {
        $this->resetAll();
        $this->entityType = 'bangsal';
        $this->formMode   = 'create';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-bangsal-id');
    }

    #[On('master.kamar.openEditBangsal')]
    public function openEditBangsal(string $bangsalId): void
    {
        $row = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->first();
        if (!$row) return;

        $this->resetAll();
        $this->entityType  = 'bangsal';
        $this->formMode    = 'edit';
        $this->formBangsal = [
            'bangsal_id'   => (string) $row->bangsal_id,
            'bangsal_name' => (string) ($row->bangsal_name ?? ''),
            'sl_codefrom'  => (string) ($row->sl_codefrom ?? ''),
            'bangsal_seq'  => (string) ($row->bangsal_seq ?? ''),
            'bed_bangsal'  => (string) ($row->bed_bangsal ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-bangsal-name');
    }

    #[On('master.kamar.deleteBangsal')]
    public function deleteBangsal(string $bangsalId): void
    {
        try {
            $hasRooms = DB::table('rsmst_rooms')->where('bangsal_id', $bangsalId)->exists();
            if ($hasRooms) {
                $this->dispatch('toast', type: 'error', message: 'Bangsal tidak bisa dihapus karena masih memiliki kamar.');
                return;
            }

            $deleted = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data bangsal tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Bangsal berhasil dihapus.');
            $this->dispatch('master.kamar.saved', entity: 'bangsal');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Bangsal tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    private function saveBangsal(): void
    {
        $this->validate([
            'formBangsal.bangsal_id'   => $this->formMode === 'create'
                ? 'required|string|max:5|unique:rsmst_bangsals,bangsal_id'
                : 'required|string|max:5',
            'formBangsal.bangsal_name' => 'required|string|max:25',
            'formBangsal.sl_codefrom'  => 'nullable|string|max:3',
            'formBangsal.bangsal_seq'  => 'nullable|integer|min:0',
            'formBangsal.bed_bangsal'  => 'nullable|integer|min:0',
        ], [], [
            'formBangsal.bangsal_id'   => 'ID Bangsal',
            'formBangsal.bangsal_name' => 'Nama Bangsal',
            'formBangsal.sl_codefrom'  => 'Kode SL',
            'formBangsal.bangsal_seq'  => 'Urutan',
            'formBangsal.bed_bangsal'  => 'Bed Bangsal',
        ]);

        $payload = [
            'bangsal_name' => $this->formBangsal['bangsal_name'],
            'sl_codefrom'  => $this->formBangsal['sl_codefrom'] ?: null,
            'bangsal_seq'  => $this->formBangsal['bangsal_seq'] !== '' ? (int) $this->formBangsal['bangsal_seq'] : null,
            'bed_bangsal'  => $this->formBangsal['bed_bangsal'] !== '' ? (int) $this->formBangsal['bed_bangsal'] : null,
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_bangsals')->insert(['bangsal_id' => $this->formBangsal['bangsal_id'], ...$payload]);
        } else {
            DB::table('rsmst_bangsals')->where('bangsal_id', $this->formBangsal['bangsal_id'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data bangsal berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kamar.saved', entity: 'bangsal');
    }

    // =========================================================
    // KAMAR
    // =========================================================

    #[On('master.kamar.openCreateKamar')]
    public function openCreateKamar(string $bangsalId): void
    {
        $this->resetAll();
        $this->entityType             = 'kamar';
        $this->formMode               = 'create';
        $this->formKamar['bangsal_id'] = $bangsalId;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-room-id');
    }

    #[On('master.kamar.openEditKamar')]
    public function openEditKamar(string $roomId): void
    {
        $row = DB::table('rsmst_rooms')->where('room_id', $roomId)->first();
        if (!$row) return;

        $this->resetAll();
        $this->entityType = 'kamar';
        $this->formMode   = 'edit';
        $this->formKamar  = [
            'room_id'         => (string) $row->room_id,
            'room_name'       => (string) ($row->room_name ?? ''),
            'bangsal_id'      => (string) ($row->bangsal_id ?? ''),
            'class_id'        => (string) ($row->class_id ?? ''),
            'room_price'      => (string) ($row->room_price ?? '0'),
            'perawatan_price' => (string) ($row->perawatan_price ?? '0'),
            'common_service'  => (string) ($row->common_service ?? '0'),
            'active_status'   => (string) ($row->active_status ?? 'AC'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-room-name');
    }

    #[On('master.kamar.deleteKamar')]
    public function deleteKamar(string $roomId): void
    {
        try {
            $hasBeds = DB::table('rsmst_beds')->where('room_id', $roomId)->exists();
            if ($hasBeds) {
                $this->dispatch('toast', type: 'error', message: 'Kamar tidak bisa dihapus karena masih memiliki bed.');
                return;
            }

            $inUse = DB::table('rstxn_rihdrs')->where('room_id', $roomId)->exists();
            if ($inUse) {
                $this->dispatch('toast', type: 'error', message: 'Kamar tidak bisa dihapus karena masih dipakai pada transaksi RI.');
                return;
            }

            $deleted = DB::table('rsmst_rooms')->where('room_id', $roomId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kamar tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kamar berhasil dihapus.');
            $this->dispatch('master.kamar.saved', entity: 'kamar');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kamar tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    private function saveKamar(): void
    {
        $this->validate([
            'formKamar.room_id'         => $this->formMode === 'create'
                ? 'required|string|max:5|unique:rsmst_rooms,room_id'
                : 'required|string|max:5',
            'formKamar.room_name'       => 'required|string|max:25',
            'formKamar.bangsal_id'      => 'required|string',
            'formKamar.class_id'        => 'required',
            'formKamar.room_price'      => 'nullable|integer|min:0',
            'formKamar.perawatan_price' => 'nullable|integer|min:0',
            'formKamar.common_service'  => 'nullable|integer|min:0',
            'formKamar.active_status'   => 'required|string|max:3',
        ], [], [
            'formKamar.room_id'         => 'ID Kamar',
            'formKamar.room_name'       => 'Nama Kamar',
            'formKamar.bangsal_id'      => 'Bangsal',
            'formKamar.class_id'        => 'Kelas',
            'formKamar.room_price'      => 'Tarif Kamar',
            'formKamar.perawatan_price' => 'Tarif Perawatan',
            'formKamar.common_service'  => 'Pelayanan Umum',
            'formKamar.active_status'   => 'Status',
        ]);

        $payload = [
            'room_name'       => $this->formKamar['room_name'],
            'bangsal_id'      => $this->formKamar['bangsal_id'],
            'class_id'        => (int) $this->formKamar['class_id'],
            'room_price'      => (int) ($this->formKamar['room_price'] ?: 0),
            'perawatan_price' => (int) ($this->formKamar['perawatan_price'] ?: 0),
            'common_service'  => (int) ($this->formKamar['common_service'] ?: 0),
            'active_status'   => $this->formKamar['active_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_rooms')->insert(['room_id' => $this->formKamar['room_id'], ...$payload]);
        } else {
            DB::table('rsmst_rooms')->where('room_id', $this->formKamar['room_id'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kamar berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kamar.saved', entity: 'kamar');
    }

    // =========================================================
    // BED
    // =========================================================

    #[On('master.kamar.openCreateBed')]
    public function openCreateBed(string $roomId): void
    {
        $this->resetAll();
        $this->entityType           = 'bed';
        $this->formMode             = 'create';
        $this->formBed['room_id']   = $roomId;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-bed-no');
    }

    #[On('master.kamar.openEditBed')]
    public function openEditBed(string $bedNo, string $roomId): void
    {
        $row = DB::table('rsmst_beds')->where('bed_no', $bedNo)->where('room_id', $roomId)->first();
        if (!$row) return;

        $this->resetAll();
        $this->entityType = 'bed';
        $this->formMode   = 'edit';
        $this->formBed    = [
            'bed_no'   => (string) $row->bed_no,
            'bed_desc' => (string) ($row->bed_desc ?? ''),
            'room_id'  => (string) $row->room_id,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-actions');
        $this->dispatch('focus-bed-desc');
    }

    #[On('master.kamar.deleteBed')]
    public function deleteBed(string $bedNo, string $roomId): void
    {
        try {
            $deleted = DB::table('rsmst_beds')
                ->where('bed_no', $bedNo)
                ->where('room_id', $roomId)
                ->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data bed tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Bed berhasil dihapus.');
            $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $roomId);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Bed tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    private function saveBed(): void
    {
        $this->validate([
            'formBed.bed_no'   => $this->formMode === 'create'
                ? 'required|string|max:5'
                : 'required|string|max:5',
            'formBed.bed_desc' => 'nullable|string|max:50',
            'formBed.room_id'  => 'required|string',
        ], [], [
            'formBed.bed_no'   => 'No Bed',
            'formBed.bed_desc' => 'Keterangan Bed',
            'formBed.room_id'  => 'Kamar',
        ]);

        if ($this->formMode === 'create') {
            $exists = DB::table('rsmst_beds')
                ->where('bed_no', $this->formBed['bed_no'])
                ->where('room_id', $this->formBed['room_id'])
                ->exists();

            if ($exists) {
                $this->addError('formBed.bed_no', 'No Bed sudah ada di kamar ini.');
                return;
            }

            DB::table('rsmst_beds')->insert([
                'bed_no'   => $this->formBed['bed_no'],
                'bed_desc' => $this->formBed['bed_desc'] ?: null,
                'room_id'  => $this->formBed['room_id'],
            ]);
        } else {
            DB::table('rsmst_beds')
                ->where('bed_no', $this->formBed['bed_no'])
                ->where('room_id', $this->formBed['room_id'])
                ->update(['bed_desc' => $this->formBed['bed_desc'] ?: null]);
        }

        $this->dispatch('toast', type: 'success', message: 'Data bed berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $this->formBed['room_id']);
    }

    // =========================================================
    // SHARED
    // =========================================================

    public function save(): void
    {
        match ($this->entityType) {
            'bangsal' => $this->saveBangsal(),
            'kamar'   => $this->saveKamar(),
            'bed'     => $this->saveBed(),
        };
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-kamar-actions');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formBangsal = ['bangsal_id'=>'','bangsal_name'=>'','sl_codefrom'=>'','bangsal_seq'=>'','bed_bangsal'=>''];
        $this->formKamar   = ['room_id'=>'','room_name'=>'','bangsal_id'=>'','class_id'=>'','room_price'=>'0','perawatan_price'=>'0','common_service'=>'0','active_status'=>'AC'];
        $this->formBed     = ['bed_no'=>'','bed_desc'=>'','room_id'=>''];
        $this->resetValidation();
    }

    private function modalTitle(): string
    {
        $labels = ['bangsal' => 'Bangsal', 'kamar' => 'Kamar', 'bed' => 'Bed'];
        $entity = $labels[$this->entityType] ?? '';
        return ($this->formMode === 'edit' ? 'Ubah Data ' : 'Tambah Data ') . $entity;
    }
};
?>

<div>
    <x-modal name="master-kamar-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$formMode, $entityType]) }}">

            {{-- ── HEADER ────────────────────────────────────────── --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                     style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->modalTitle() }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi data berikut lalu klik Simpan.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── BODY ─────────────────────────────────────────── --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-2xl"
                     x-data
                     x-on:focus-bangsal-id.window="$nextTick(() => setTimeout(() => $refs.inputBangsalId?.focus(), 150))"
                     x-on:focus-bangsal-name.window="$nextTick(() => setTimeout(() => $refs.inputBangsalName?.focus(), 150))"
                     x-on:focus-room-id.window="$nextTick(() => setTimeout(() => $refs.inputRoomId?.focus(), 150))"
                     x-on:focus-room-name.window="$nextTick(() => setTimeout(() => $refs.inputRoomName?.focus(), 150))"
                     x-on:focus-bed-no.window="$nextTick(() => setTimeout(() => $refs.inputBedNo?.focus(), 150))"
                     x-on:focus-bed-desc.window="$nextTick(() => setTimeout(() => $refs.inputBedDesc?.focus(), 150))">

                    <div class="p-5 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ════ FORM BANGSAL ════ --}}
                        @if ($entityType === 'bangsal')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="ID Bangsal" />
                                    <x-text-input wire:model.live="formBangsal.bangsal_id" x-ref="inputBangsalId"
                                        :disabled="$formMode === 'edit'" maxlength="5"
                                        :error="$errors->has('formBangsal.bangsal_id')"
                                        class="w-full mt-1 uppercase"
                                        x-on:keydown.enter.prevent="$refs.inputBangsalName?.focus()" />
                                    <x-input-error :messages="$errors->get('formBangsal.bangsal_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Urutan (Seq)" />
                                    <x-text-input wire:model.live="formBangsal.bangsal_seq" type="number" min="0"
                                        :error="$errors->has('formBangsal.bangsal_seq')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('formBangsal.bangsal_seq')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Nama Bangsal" />
                                <x-text-input wire:model.live="formBangsal.bangsal_name" x-ref="inputBangsalName"
                                    maxlength="25" :error="$errors->has('formBangsal.bangsal_name')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputSlCode?.focus()" />
                                <x-input-error :messages="$errors->get('formBangsal.bangsal_name')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Kode SL" />
                                    <x-text-input wire:model.live="formBangsal.sl_codefrom" x-ref="inputSlCode"
                                        maxlength="3" :error="$errors->has('formBangsal.sl_codefrom')"
                                        class="w-full mt-1 uppercase"
                                        x-on:keydown.enter.prevent="$refs.inputBedBangsal?.focus()" />
                                    <x-input-error :messages="$errors->get('formBangsal.sl_codefrom')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Bed Bangsal" />
                                    <x-text-input wire:model.live="formBangsal.bed_bangsal" x-ref="inputBedBangsal"
                                        type="number" min="0"
                                        :error="$errors->has('formBangsal.bed_bangsal')"
                                        class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$wire.save()" />
                                    <x-input-error :messages="$errors->get('formBangsal.bed_bangsal')" class="mt-1" />
                                </div>
                            </div>
                        @endif

                        {{-- ════ FORM KAMAR ════ --}}
                        @if ($entityType === 'kamar')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="ID Kamar" />
                                    <x-text-input wire:model.live="formKamar.room_id" x-ref="inputRoomId"
                                        :disabled="$formMode === 'edit'" maxlength="5"
                                        :error="$errors->has('formKamar.room_id')"
                                        class="w-full mt-1 uppercase"
                                        x-on:keydown.enter.prevent="$refs.inputRoomName?.focus()" />
                                    <x-input-error :messages="$errors->get('formKamar.room_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Status" />
                                    <x-select-input wire:model.live="formKamar.active_status"
                                        :error="$errors->has('formKamar.active_status')" class="w-full mt-1">
                                        <option value="AC">Aktif</option>
                                        <option value="NA">Non Aktif</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('formKamar.active_status')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Nama Kamar" />
                                <x-text-input wire:model.live="formKamar.room_name" x-ref="inputRoomName"
                                    maxlength="25" :error="$errors->has('formKamar.room_name')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputClassId?.focus()" />
                                <x-input-error :messages="$errors->get('formKamar.room_name')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Kelas" />
                                <x-select-input wire:model.live="formKamar.class_id" x-ref="inputClassId"
                                    :error="$errors->has('formKamar.class_id')" class="w-full mt-1">
                                    <option value="">— Pilih Kelas —</option>
                                    @foreach ($kelasList as $kelas)
                                        <option value="{{ $kelas['class_id'] }}">{{ $kelas['class_desc'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKamar.class_id')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Tarif Kamar" />
                                    <x-text-input-number wire:model="formKamar.room_price"
                                        :error="$errors->has('formKamar.room_price')" class="mt-1"
                                        x-ref="inputRoomPrice"
                                        x-on:keydown.enter.prevent="$refs.inputPerawatan?.focus()" />
                                    <x-input-error :messages="$errors->get('formKamar.room_price')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tarif Perawatan" />
                                    <x-text-input-number wire:model="formKamar.perawatan_price"
                                        :error="$errors->has('formKamar.perawatan_price')" class="mt-1"
                                        x-ref="inputPerawatan"
                                        x-on:keydown.enter.prevent="$refs.inputCommon?.focus()" />
                                    <x-input-error :messages="$errors->get('formKamar.perawatan_price')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Pelayanan Umum" />
                                    <x-text-input-number wire:model="formKamar.common_service"
                                        :error="$errors->has('formKamar.common_service')" class="mt-1"
                                        x-ref="inputCommon"
                                        x-on:keydown.enter.prevent="$wire.save()" />
                                    <x-input-error :messages="$errors->get('formKamar.common_service')" class="mt-1" />
                                </div>
                            </div>
                        @endif

                        {{-- ════ FORM BED ════ --}}
                        @if ($entityType === 'bed')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="No Bed" />
                                    <x-text-input wire:model.live="formBed.bed_no" x-ref="inputBedNo"
                                        :disabled="$formMode === 'edit'" maxlength="5"
                                        :error="$errors->has('formBed.bed_no')"
                                        class="w-full mt-1 uppercase"
                                        x-on:keydown.enter.prevent="$refs.inputBedDesc?.focus()" />
                                    <x-input-error :messages="$errors->get('formBed.bed_no')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kamar" />
                                    <x-text-input :value="$formBed['room_id']" disabled class="w-full mt-1 bg-gray-50 dark:bg-gray-800" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Keterangan Bed" />
                                <x-text-input wire:model.live="formBed.bed_desc" x-ref="inputBedDesc"
                                    maxlength="50" :error="$errors->has('formBed.bed_desc')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Opsional — misal: "Bed A", "Bed Pojok", dll.</p>
                                <x-input-error :messages="$errors->get('formBed.bed_desc')" class="mt-1" />
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ── FOOTER ───────────────────────────────────────── --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field,</span>
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> di field terakhir untuk simpan</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
