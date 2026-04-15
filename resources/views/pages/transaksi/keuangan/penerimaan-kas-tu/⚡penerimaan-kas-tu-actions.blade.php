<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public ?string $editNo = null;
    public array $renderVersions = [];

    // ── Form fields ──
    public ?string $accId = null;
    public ?string $accName = null;
    public ?string $accIdKas = null;
    public ?string $accNameKas = null;
    public ?string $tucashkDate = null;
    public ?string $tucashkDesc = null;
    public ?int $tucashkNominal = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open Create ── */
    #[On('penerimaan-kas.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->editNo = null;
        $this->tucashkDate = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-kas-tu-actions');
        $this->dispatch('focus-tucashk-date');
    }

    /* ── Open Edit ── */
    #[On('penerimaan-kas.openEdit')]
    public function openEdit(string $tucashkNo): void
    {
        $row = DB::table('rstxn_tucashks')->where('tucashk_no', $tucashkNo)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        if ($row->tucashk_status === 'L') {
            $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah diposting, tidak bisa diedit.');
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->editNo = (string) $row->tucashk_no;
        $this->accId = $row->acc_id;
        $this->accIdKas = $row->acc_id_kas;
        $this->tucashkDate = $row->tucashk_date ? Carbon::parse($row->tucashk_date)->format('d/m/Y H:i:s') : null;
        $this->tucashkDesc = $row->tucashk_desc;
        $this->tucashkNominal = (int) ($row->tucashk_nominal ?? 0);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-kas-tu-actions');
        $this->dispatch('focus-tucashk-desc');
    }

    /* ── LOV Listeners ── */
    #[On('lov.selected.akun-ci-tu')]
    public function onAkunCISelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->dispatch('focus-lov-kas-ci');
    }

    #[On('lov.selected.akun-kas-tu')]
    public function onAkunKasSelected(string $target, ?array $payload): void
    {
        $this->accIdKas = $payload['acc_id'] ?? null;
        $this->accNameKas = $payload['acc_name'] ?? null;
        $this->dispatch('focus-btn-save-ci');
    }

    /* ── Save ── */
    public function save(): void
    {
        $this->validate([
            'accId' => 'required|string',
            'accIdKas' => 'required|string',
            'tucashkDate' => 'required|date_format:d/m/Y H:i:s',
            'tucashkDesc' => 'required|string|min:3|max:100',
            'tucashkNominal' => 'required|integer|min:1',
        ], [
            'accId.required' => 'Akun penerimaan wajib dipilih.',
            'accIdKas.required' => 'Akun kas wajib dipilih.',
            'tucashkDate.required' => 'Tanggal wajib diisi.',
            'tucashkDate.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
            'tucashkDesc.required' => 'Keterangan wajib diisi.',
            'tucashkDesc.min' => 'Keterangan minimal 3 karakter.',
            'tucashkNominal.required' => 'Nominal wajib diisi.',
            'tucashkNominal.min' => 'Nominal minimal Rp 1.',
        ]);

        // Cek akun kas user
        $cekakunkas = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->where('b.co', '1')
            ->whereIn('a.acc_id', function ($q) {
                $q->select('acc_id')->from('user_kas')->where('user_id', auth()->id());
            })
            ->count();

        if ($cekakunkas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas Anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        // Ambil shift saat ini
        $now = Carbon::now();
        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $shift = (string) ($findShift?->shift ?? 1);

        try {
            DB::transaction(function () use ($empId, $shift) {
                if ($this->editNo) {
                    DB::table('rstxn_tucashks')
                        ->where('tucashk_no', $this->editNo)
                        ->update([
                            'tucashk_date' => DB::raw("to_date('{$this->tucashkDate}','dd/mm/yyyy hh24:mi:ss')"),
                            'tucashk_desc' => $this->tucashkDesc,
                            'tucashk_nominal' => $this->tucashkNominal,
                            'acc_id' => $this->accId,
                            'acc_id_kas' => $this->accIdKas,
                            'emp_id' => $empId,
                            'shift' => $shift,
                            'tucashk_status' => 'L',
                        ]);
                } else {
                    $nextNo = DB::selectOne("SELECT tucashk_seq.NEXTVAL AS val FROM dual")->val;

                    DB::table('rstxn_tucashks')->insert([
                        'tucashk_no' => $nextNo,
                        'tucashk_date' => DB::raw("to_date('{$this->tucashkDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'tucashk_desc' => $this->tucashkDesc,
                        'tucashk_nominal' => $this->tucashkNominal,
                        'acc_id' => $this->accId,
                        'acc_id_kas' => $this->accIdKas,
                        'emp_id' => $empId,
                        'shift' => $shift,
                        'tucashk_status' => 'L',
                    ]);
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Transaksi penerimaan kas berhasil disimpan.');
            $this->closeModal();
            $this->dispatch('penerimaan-kas.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ── Delete ── */
    #[On('penerimaan-kas.requestDelete')]
    public function deleteFromGrid(string $tucashkNo): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        try {
            $deleted = DB::table('rstxn_tucashks')->where('tucashk_no', $tucashkNo)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dihapus.');
            $this->dispatch('penerimaan-kas.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ── Close Modal ── */
    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'penerimaan-kas-tu-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['editNo', 'accId', 'accName', 'accIdKas', 'accNameKas', 'tucashkDate', 'tucashkDesc', 'tucashkNominal']);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="penerimaan-kas-tu-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $editNo]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah" class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $editNo ? "Edit Penerimaan Kas #{$editNo}" : 'Tambah Penerimaan Kas Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Catat penerimaan kas (Cash-In) di luar transaksi pelayanan RS.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$editNo ? 'warning' : 'success'">
                                {{ $editNo ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl">
                    <x-border-form title="Data Penerimaan Kas (Cash-In)"
                        x-data
                        x-on:focus-tucashk-date.window="$nextTick(() => setTimeout(() => $refs.inputTucashkDate?.focus(), 150))"
                        x-on:focus-tucashk-desc.window="$nextTick(() => setTimeout(() => $refs.inputTucashkDesc?.focus(), 150))"
                        x-on:focus-lov-kas-ci.window="$nextTick(() => setTimeout(() => $refs.lovKasWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                        x-on:focus-btn-save-ci.window="$nextTick(() => setTimeout(() => $refs.btnSaveCi?.focus(), 150))">
                        <div class="space-y-5">

                            {{-- Tanggal --}}
                            <div>
                                <x-input-label value="Tanggal" :required="true" />
                                <x-text-input type="text" wire:model="tucashkDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    x-ref="inputTucashkDate"
                                    x-on:keydown.enter.prevent="$refs.inputTucashkDesc?.focus()"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('tucashkDate')" class="mt-1" />
                            </div>

                            {{-- Keterangan --}}
                            <div>
                                <x-input-label value="Keterangan" :required="true" />
                                <x-text-input type="text" wire:model="tucashkDesc" placeholder="Keterangan penerimaan kas"
                                    x-ref="inputTucashkDesc"
                                    x-on:keydown.enter.prevent="$refs.inputTucashkNominal?.focus()"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('tucashkDesc')" class="mt-1" />
                            </div>

                            {{-- Nominal (Rp) --}}
                            <div>
                                <x-input-label value="Nominal (Rp)" :required="true" />
                                <x-text-input-number wire:model="tucashkNominal"
                                    x-ref="inputTucashkNominal" />
                                <x-input-error :messages="$errors->get('tucashkNominal')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Akun Penerimaan (CI) --}}
                                <div>
                                    <livewire:lov.akun-ci.lov-akun-ci target="akun-ci-tu" label="Akun Penerimaan (CI)" :initialAccId="$accId"
                                        wire:key="lov-ci-{{ $editNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                </div>

                                {{-- Akun Kas --}}
                                <div x-ref="lovKasWrapper">
                                    <livewire:lov.kas.lov-kas target="akun-kas-tu" tipe="" label="Akun Kas" :initialAccId="$accIdKas"
                                        wire:key="lov-kas-{{ $editNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('accIdKas')" class="mt-1" />
                                </div>
                            </div>

                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Status transaksi otomatis <strong>Posted (L)</strong> saat disimpan.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" x-ref="btnSaveCi">
                            <span wire:loading.remove>Simpan & Posting</span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
