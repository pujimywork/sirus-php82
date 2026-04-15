<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ─── Display-only (tidak disimpan ke DB langsung) ─── */
    public string $regName = '';
    public string $drName = '';
    public string $drPoliDesc = '';

    public array $form = [
        'no_rawat' => '',
        'reg_no' => '',
        'no_peserta' => '',
        'tanggal' => '', // dd/mm/yyyy di UI, Y-m-d saat simpan
        'jam_mulai' => '', // HH:MM:SS
        'jam_selesai' => '', // HH:MM:SS
        'kode_paket' => '',
        'nm_paket' => '',
        'dr_id' => '',
        'kd_ruang_ok' => '',
        'poli_id' => '',
        'status' => 'Menunggu',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ─── Open Create ─── */
    #[On('jadwal-operasi.openCreate')]
    public function openCreate(): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->form['no_rawat'] = Carbon::now()->format('YmdHis') . 'OPRRS';
        $this->form['tanggal'] = Carbon::now()->format('d/m/Y');
        $this->form['status'] = 'Menunggu';

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'jadwal-operasi-modal');
        $this->dispatch('focus-lov-pasien-ok');
    }

    /* ─── Open Edit ─── */
    #[On('jadwal-operasi.openEdit')]
    public function openEdit(string $noRawat): void
    {
        $row = DB::table('booking_operasi')->where('no_rawat', $noRawat)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';

        /* Konversi tanggal Y-m-d → dd/mm/yyyy untuk tampil di form */
        $tanggalDisplay = '';
        if (!empty($row->tanggal)) {
            try {
                $tanggalDisplay = Carbon::parse($row->tanggal)->format('d/m/Y');
            } catch (\Exception) {
                $tanggalDisplay = $row->tanggal;
            }
        }

        $this->form = [
            'no_rawat' => (string) $row->no_rawat,
            'reg_no' => (string) ($row->reg_no ?? ''),
            'no_peserta' => (string) ($row->no_peserta ?? ''),
            'tanggal' => $tanggalDisplay,
            'jam_mulai' => substr((string) ($row->jam_mulai ?? ''), 0, 8),
            'jam_selesai' => substr((string) ($row->jam_selesai ?? ''), 0, 8),
            'kode_paket' => (string) ($row->kode_paket ?? ''),
            'nm_paket' => (string) ($row->nm_paket ?? ''),
            'dr_id' => (string) ($row->dr_id ?? ''),
            'kd_ruang_ok' => (string) ($row->kd_ruang_ok ?? ''),
            'poli_id' => (string) ($row->poli_id ?? ''),
            'status' => (string) ($row->status ?? 'Menunggu'),
        ];

        /* Ambil nama pasien & dokter untuk display */
        if ($this->form['reg_no']) {
            $pasien = DB::table('rsmst_pasiens')->where('reg_no', $this->form['reg_no'])->value('reg_name');
            $this->regName = (string) ($pasien ?? '');
        }

        if ($this->form['dr_id']) {
            $dr = DB::table('rsmst_doctors as a')->leftJoin('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_name', 'b.poli_desc')->where('a.dr_id', $this->form['dr_id'])->first();
            $this->drName = (string) ($dr?->dr_name ?? '');
            $this->drPoliDesc = (string) ($dr?->poli_desc ?? '');
        }

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'jadwal-operasi-modal');
    }

    /* ─── Set jam sekarang ─── */
    public function setJamMulai(): void
    {
        $this->form['jam_mulai'] = Carbon::now()->format('H:i:s');
    }

    public function setJamSelesai(): void
    {
        $this->form['jam_selesai'] = Carbon::now()->format('H:i:s');
    }

    /* ─── LOV Pasien selected ─── */
    #[On('lov.selected.jadwalOpPasien')]
    public function onPasienSelected(string $target, array $payload): void
    {
        $this->form['reg_no'] = $payload['reg_no'] ?? '';
        $this->form['no_peserta'] = $payload['nokartu_bpjs'] ?? '';
        $this->regName = $payload['reg_name'] ?? '';
    }

    /* ─── LOV Dokter selected ─── */
    #[On('lov.selected.jadwalOpDokter')]
    public function onDokterSelected(string $target, array $payload): void
    {
        $this->form['dr_id'] = $payload['dr_id'] ?? '';
        $this->form['poli_id'] = $payload['poli_id'] ?? '';
        $this->drName = $payload['dr_name'] ?? '';
        $this->drPoliDesc = $payload['poli_desc'] ?? '';
    }

    /* ─── Delete ─── */
    #[On('jadwal-operasi.requestDelete')]
    public function deleteRow(string $noRawat): void
    {
        try {
            $deleted = DB::table('booking_operasi')->where('no_rawat', $noRawat)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Jadwal operasi berhasil dihapus.');
            $this->dispatch('jadwal-operasi.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    /* ─── Validation ─── */
    protected function rules(): array
    {
        return [
            'form.reg_no' => 'required|string|max:100',
            'form.tanggal' => 'required|date_format:d/m/Y',
            'form.jam_mulai' => 'required|date_format:H:i:s',
            'form.jam_selesai' => 'required|date_format:H:i:s',
            'form.nm_paket' => 'required|string|max:100',
            'form.dr_id' => 'required|string|max:100',
            'form.kode_paket' => 'nullable|string|max:100',
            'form.kd_ruang_ok' => 'nullable|string|max:100',
            'form.poli_id' => 'nullable|string|max:100',
            'form.no_peserta' => 'nullable|string|max:100',
            'form.status' => 'required|in:Menunggu,Selesai',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.reg_no.required' => ':attribute wajib dipilih.',
            'form.tanggal.required' => ':attribute wajib diisi.',
            'form.tanggal.date_format' => ':attribute harus format dd/mm/yyyy.',
            'form.jam_mulai.required' => ':attribute wajib diisi.',
            'form.jam_mulai.date_format' => ':attribute harus format HH:MM:SS.',
            'form.jam_selesai.required' => ':attribute wajib diisi.',
            'form.jam_selesai.date_format' => ':attribute harus format HH:MM:SS.',
            'form.nm_paket.required' => ':attribute wajib diisi.',
            'form.dr_id.required' => ':attribute wajib dipilih.',
            'form.status.required' => ':attribute wajib dipilih.',
            'form.status.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.reg_no' => 'Pasien',
            'form.tanggal' => 'Tanggal Operasi',
            'form.jam_mulai' => 'Jam Mulai',
            'form.jam_selesai' => 'Jam Selesai',
            'form.nm_paket' => 'Nama Paket',
            'form.dr_id' => 'Dokter',
            'form.kode_paket' => 'Kode Paket',
            'form.kd_ruang_ok' => 'Ruang OK',
            'form.poli_id' => 'Poli',
            'form.no_peserta' => 'No. Peserta',
            'form.status' => 'Status',
        ];
    }

    /* ─── Save ─── */
    public function save(): void
    {
        $this->validate();

        /* Konversi tanggal dd/mm/yyyy → Y-m-d untuk disimpan */
        $tanggalDb = Carbon::createFromFormat('d/m/Y', $this->form['tanggal'])->format('Y-m-d');

        $payload = [
            'reg_no' => $this->form['reg_no'],
            'no_peserta' => $this->form['no_peserta'] ?: null,
            'tanggal' => $tanggalDb,
            'jam_mulai' => $this->form['jam_mulai'],
            'jam_selesai' => $this->form['jam_selesai'],
            'kode_paket' => $this->form['kode_paket'] ?: null,
            'nm_paket' => $this->form['nm_paket'],
            'dr_id' => $this->form['dr_id'],
            'kd_ruang_ok' => $this->form['kd_ruang_ok'] ?: null,
            'poli_id' => $this->form['poli_id'] ?: null,
            'status' => $this->form['status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('booking_operasi')->insert([
                'no_rawat' => $this->form['no_rawat'],
                ...$payload,
            ]);
        } else {
            DB::table('booking_operasi')->where('no_rawat', $this->form['no_rawat'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Jadwal operasi berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('jadwal-operasi.saved');
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'jadwal-operasi-modal');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->regName = '';
        $this->drName = '';
        $this->drPoliDesc = '';
        $this->form = [
            'no_rawat' => '',
            'reg_no' => '',
            'no_peserta' => '',
            'tanggal' => '',
            'jam_mulai' => '',
            'jam_selesai' => '',
            'kode_paket' => '',
            'nm_paket' => '',
            'dr_id' => '',
            'kd_ruang_ok' => '',
            'poli_id' => '',
            'status' => 'Menunggu',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="jadwal-operasi-modal" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0" wire:key="{{ $this->renderKey('modal', [$formMode, $form['no_rawat']]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Jadwal Operasi
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi data berikut lalu klik Simpan.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                            <span class="text-xs text-gray-400 font-mono">{{ $form['no_rawat'] }}</span>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-lov-pasien-ok.window="$nextTick(() => setTimeout(() => $refs.lovPasienOk?.querySelector('input')?.focus(), 150))">

                <x-border-form title="Data Jadwal Operasi">
                    <div class="space-y-4">

                    {{-- LOV Pasien --}}
                    <div>
                        <div x-ref="lovPasienOk">
                            <livewire:lov.pasien.lov-pasien target="jadwalOpPasien" :initialRegNo="$form['reg_no']" />
                        </div>
                        <x-input-error :messages="$errors->get('form.reg_no')" class="mt-1" />

                        @if ($regName || $form['no_peserta'])
                            <div class="mt-2 px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                @if ($regName)
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $regName }}</span>
                                @endif
                                @if ($form['no_peserta'])
                                    <span class="ml-2 text-gray-400">| No. BPJS: {{ $form['no_peserta'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Tanggal & Status --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Tanggal Operasi" />
                            <x-text-input wire:model.live="form.tanggal" placeholder="dd/mm/yyyy" maxlength="10"
                                :error="$errors->has('form.tanggal')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.tanggal')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Status" />
                            <x-select-input wire:model.live="form.status" :error="$errors->has('form.status')" class="w-full mt-1">
                                <option value="Menunggu">Menunggu</option>
                                <option value="Selesai">Selesai</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.status')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Jam Mulai & Jam Selesai --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Jam Mulai" />
                            <div class="flex items-center gap-2 mt-1">
                                <x-text-input wire:model.live="form.jam_mulai" placeholder="HH:MM:SS" maxlength="8"
                                    :error="$errors->has('form.jam_mulai')" class="flex-1" />
                                <x-secondary-button type="button" wire:click="setJamMulai" class="shrink-0 px-2 py-1 text-xs">
                                    Jam Sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('form.jam_mulai')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Jam Selesai" />
                            <div class="flex items-center gap-2 mt-1">
                                <x-text-input wire:model.live="form.jam_selesai" placeholder="HH:MM:SS" maxlength="8"
                                    :error="$errors->has('form.jam_selesai')" class="flex-1" />
                                <x-secondary-button type="button" wire:click="setJamSelesai" class="shrink-0 px-2 py-1 text-xs">
                                    Jam Sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('form.jam_selesai')" class="mt-1" />
                        </div>
                    </div>

                    {{-- LOV Dokter --}}
                    <div>
                        <livewire:lov.dokter.lov-dokter label="Cari Dokter Operator" target="jadwalOpDokter"
                            :initialDrId="$form['dr_id']" />
                        <x-input-error :messages="$errors->get('form.dr_id')" class="mt-1" />

                        @if ($drName || $drPoliDesc)
                            <div
                                class="mt-2 px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                @if ($drName)
                                    <span
                                        class="font-semibold text-gray-700 dark:text-gray-200">{{ $drName }}</span>
                                @endif
                                @if ($drPoliDesc)
                                    <span class="ml-2 text-gray-400">| {{ $drPoliDesc }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Baris 3: Kode Paket & Nama Paket --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Kode Paket" />
                            <x-text-input wire:model.live="form.kode_paket" maxlength="100" :error="$errors->has('form.kode_paket')"
                                class="w-full mt-1 uppercase" />
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Opsional — misal: KATARAK.</p>
                            <x-input-error :messages="$errors->get('form.kode_paket')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Nama Paket Operasi" />
                            <x-text-input wire:model.live="form.nm_paket" maxlength="100" :error="$errors->has('form.nm_paket')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.nm_paket')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Baris 4: Ruang OK --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Ruang OK" />
                            <x-text-input wire:model.live="form.kd_ruang_ok" maxlength="100" :error="$errors->has('form.kd_ruang_ok')"
                                class="w-full mt-1 uppercase" x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('form.kd_ruang_ok')" class="mt-1" />
                        </div>
                    </div>

                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di field terakhir untuk simpan</span>
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
