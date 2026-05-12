<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/laborat/rm-laborat-luar-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithRenderVersioningTrait, EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['lab-luar-order-modal'];

    public ?string $riHdrNo = null;
    public bool $disabled = false;

    public array $form = [
        'drId' => '',
        'namaPemeriksaan' => '',
        'catatanKlinis' => '',
    ];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    public function openModal(): void
    {
        if ($this->disabled || empty($this->riHdrNo)) {
            return;
        }
        $this->resetForm();
        $this->incrementVersion('lab-luar-order-modal');
        $this->dispatch('open-modal', name: "lab-luar-order-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "lab-luar-order-ri-{$this->riHdrNo}");
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = [
            'drId' => '',
            'namaPemeriksaan' => '',
            'catatanKlinis' => '',
        ];
        $this->resetValidation();
    }

    /*
     | Daftar dokter terkait kunjungan RI ini, sebagai LOV picker dokter pengirim.
     | Sumber: DPJP (rstxn_rihdrs.dr_id) ∪ visite (rstxn_rivisits) ∪ jasa (rstxn_riactdocs).
     | Distinct, di-JOIN ke rsmst_doctors yang aktif.
     */
    #[Computed]
    public function relatedDoctors()
    {
        if (empty($this->riHdrNo)) {
            return collect();
        }

        $dpjp = DB::table('rstxn_rihdrs')->select('dr_id')->where('rihdr_no', $this->riHdrNo);
        $visite = DB::table('rstxn_rivisits')->select('dr_id')->where('rihdr_no', $this->riHdrNo);
        $jasa = DB::table('rstxn_riactdocs')->select('dr_id')->where('rihdr_no', $this->riHdrNo);

        $unionIds = $dpjp->union($visite)->union($jasa);

        return DB::table('rsmst_doctors as d')
            ->joinSub($unionIds, 'u', 'u.dr_id', '=', 'd.dr_id')
            ->select('d.dr_id', 'd.dr_name')
            ->where('d.active_status', '1')
            ->distinct()
            ->orderBy('d.dr_name')
            ->get();
    }

    public function kirimOrderLabLuar(): void
    {
        $this->validate(
            [
                'form.drId' => 'bail|required',
                'form.namaPemeriksaan' => 'bail|required|string|max:500',
                'form.catatanKlinis' => 'nullable|string|max:1000',
            ],
            [
                'form.drId.required' => 'Dokter pengirim harus dipilih.',
                'form.namaPemeriksaan.required' => 'Nama pemeriksaan harus diisi.',
            ],
        );

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah order.');
            return;
        }

        $riData = DB::table('rstxn_rihdrs')->select('reg_no')->where('rihdr_no', $this->riHdrNo)->first();
        if (!$riData) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($riData) {
                $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

                DB::table('lbtxn_checkuphdrs')->insert([
                    'checkup_no' => $checkupNo,
                    'reg_no' => $riData->reg_no,
                    'dr_id' => $this->form['drId'],
                    'checkup_date' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                    'status_rjri' => 'RI',
                    'checkup_status' => 'P',
                    'ref_no' => $this->riHdrNo,
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

            $this->dispatch('lab-luar-ri.updated');
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

    <x-modal name="lab-luar-order-ri-{{ $riHdrNo }}" size="2xl" focusable>
        <div wire:key="{{ $this->renderKey('lab-luar-order-modal', [$riHdrNo ?: 'empty']) }}">

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
                            <p class="text-xs text-gray-500">No. RI: <span
                                    class="font-mono font-medium">{{ $riHdrNo }}</span></p>
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
                    <x-input-label for="form-drId" value="Dokter Pengirim" required />
                    <select wire:model.defer="form.drId" id="form-drId"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Pilih dokter pengirim —</option>
                        @foreach ($this->relatedDoctors as $dr)
                            <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }}</option>
                        @endforeach
                    </select>
                    @error('form.drId')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Dokter terkait kunjungan ini (DPJP / visite / jasa).</p>
                </div>

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
