<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/laborat/rm-laborat-luar-rj-actions.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use WithRenderVersioningTrait, EmrRJTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['lab-luar-order-modal'];

    public string $rjNo = '';
    public bool $disabled = false;

    public array $form = [
        'namaPemeriksaan' => '',
        'catatanKlinis' => '',
    ];

    public function mount(string $rjNo = '', bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->resetForm();
        $this->incrementVersion('lab-luar-order-modal');
        $this->dispatch('open-modal', name: "lab-luar-order-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "lab-luar-order-rj-{$this->rjNo}");
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = [
            'namaPemeriksaan' => '',
            'catatanKlinis' => '',
        ];
        $this->resetValidation();
    }

    public function kirimOrderLabLuar(): void
    {
        $this->validate(
            [
                'form.namaPemeriksaan' => 'bail|required|string|max:500',
                'form.catatanKlinis' => 'nullable|string|max:1000',
            ],
            [
                'form.namaPemeriksaan.required' => 'Nama pemeriksaan harus diisi.',
            ],
        );

        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah order.');
            return;
        }

        $rjData = DB::table('rstxn_rjhdrs')->select('reg_no', 'dr_id')->where('rj_no', $this->rjNo)->first();
        if (!$rjData) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($rjData) {
                $this->lockRJRow($this->rjNo);

                $now = Carbon::now(config('app.timezone'));
                $nowStr = $now->format('d/m/Y H:i:s');

                // Insert lbtxn_checkuphdrs (pola sama dengan kirim lab internal)
                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

                DB::table('lbtxn_checkuphdrs')->insert([
                    'checkup_no' => $checkupNo,
                    'reg_no' => $rjData->reg_no,
                    'dr_id' => $rjData->dr_id,
                    'checkup_date' => DB::raw("TO_DATE('{$nowStr}','dd/mm/yyyy hh24:mi:ss')"),
                    'status_rjri' => 'RJ',
                    'checkup_status' => 'P',
                    'ref_no' => $this->rjNo,
                ]);

                $desc = trim($this->form['namaPemeriksaan']);

                $laboutDtl = DB::scalar('SELECT NVL(MAX(labout_dtl) + 1, 1) FROM lbtxn_checkupoutdtls');

                DB::table('lbtxn_checkupoutdtls')->insert([
                    'labout_dtl' => $laboutDtl,
                    'checkup_no' => $checkupNo,
                    'labout_desc' => $desc,
                    'labout_price' => null,
                    'labout_result' => $this->form['catatanKlinis'] ? trim($this->form['catatanKlinis']) : null,
                    'labout_normal' => null,
                ]);
            });

            $this->dispatch('lab-luar-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Order Laboratorium Luar berhasil dikirim.');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <div class="mb-3">
        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
            :disabled="$disabled">
            <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v16m8-8H4" />
                </svg>
                Order Laboratorium Luar
            </span>
            <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                <x-loading /> Memuat...
            </span>
        </x-primary-button>
    </div>

    <x-modal name="lab-luar-order-rj-{{ $rjNo }}" size="2xl" focusable>
        <div wire:key="{{ $this->renderKey('lab-luar-order-modal', [$rjNo ?: 'empty']) }}">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-500/15">
                            <svg class="w-5 h-5 text-amber-700 dark:text-amber-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17v-2a4 4 0 014-4h4M5 7h14M5 11h6m-6 4h6m-6 4h6" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Order Pemeriksaan Laboratorium Luar
                            </h2>
                            <p class="text-xs text-gray-500">No. RJ: <span
                                    class="font-mono font-medium">{{ $rjNo }}</span></p>
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

            <div class="px-6 py-5 space-y-4">
                <div>
                    <x-input-label for="form-namaPemeriksaan" value="Nama Pemeriksaan" required />
                    <x-text-input wire:model.defer="form.namaPemeriksaan" id="form-namaPemeriksaan"
                        class="block w-full mt-1"
                        placeholder="contoh: PCR Covid-19 di PRODIA, BTA, Histopatologi"
                        :error="$errors->has('form.namaPemeriksaan')" />
                    @error('form.namaPemeriksaan')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-input-label for="form-catatanKlinis" value="Catatan Klinis (opsional)" />
                    <textarea wire:model.defer="form.catatanKlinis" id="form-catatanKlinis" rows="3"
                        placeholder="indikasi pemeriksaan / diagnosis kerja / catatan untuk lab"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                    @error('form.catatanKlinis')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="kirimOrderLabLuar" wire:loading.attr="disabled"
                    wire:target="kirimOrderLabLuar">
                    <span wire:loading.remove wire:target="kirimOrderLabLuar">Kirim Order</span>
                    <span wire:loading wire:target="kirimOrderLabLuar" class="flex items-center gap-1.5">
                        <x-loading /> Mengirim...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
