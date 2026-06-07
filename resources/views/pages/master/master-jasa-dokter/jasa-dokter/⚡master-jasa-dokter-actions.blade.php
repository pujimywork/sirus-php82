<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* -------------------- HEADER -------------------- */
    public ?string $accdocId = null;
    public string $accdocDesc = '';
    public ?int $accdocPrice = null;
    public ?int $accdocPriceBpjs = null;
    public string $activeStatus = '1';

    /* -------------------- TARIF PER KELAS -------------------- */
    /** Matrix kelas rawat × tarif: ['id', 'class_id', 'class_desc', 'actd_price', 'actd_price_bpjs'] */
    public array $tarifKelas = [];

    /* -------------------- PAKET LAIN-LAIN -------------------- */
    /** array of ['other_id', 'other_desc', 'accdother_price'] */
    public array $paketLainLain = [];

    public array $formPaketLain = [
        'other_id' => '',
        'other_desc' => '',
        'accdother_price' => null,
    ];

    /* -------------------- PAKET OBAT -------------------- */
    /** array of ['product_id', 'product_name', 'sales_price', 'accdprod_qty'] */
    public array $paketObat = [];

    public array $formPaketObat = [
        'product_id' => '',
        'product_name' => '',
        'sales_price' => null,
        'accdprod_qty' => 1,
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | OPEN / CLOSE
     =============================== */
    #[On('master.jasa-dokter.openCreate')]
    public function openCreate(): void
    {
        $this->resetAllFields();
        $this->formMode = 'create';
        $this->loadTarifKelas(null);
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-jasa-dokter-actions');
    }

    #[On('master.jasa-dokter.openEdit')]
    public function openEdit(string $accdocId): void
    {
        $row = DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Jasa medis tidak ditemukan.');
            return;
        }

        $this->resetAllFields();
        $this->formMode = 'edit';

        $this->accdocId = (string) $row->accdoc_id;
        $this->accdocDesc = (string) ($row->accdoc_desc ?? '');
        $this->accdocPrice = (int) ($row->accdoc_price ?? 0);
        $this->accdocPriceBpjs = (int) ($row->accdoc_price_bpjs ?? 0);
        $this->activeStatus = (string) ($row->active_status ?? '1');

        $this->loadPaketFromDb($accdocId);
        $this->loadTarifKelas($accdocId);

        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-jasa-dokter-actions');
    }

    private function loadPaketFromDb(string $accdocId): void
    {
        $this->paketLainLain = DB::table('rsmst_accdocothers as ap')
            ->leftJoin('rsmst_others as o', 'o.other_id', '=', 'ap.other_id')
            ->where('ap.accdoc_id', $accdocId)
            ->select('ap.other_id', 'o.other_desc', 'ap.accdother_price')
            ->orderBy('ap.other_id')
            ->get()
            ->map(fn($r) => [
                'other_id' => (string) $r->other_id,
                'other_desc' => (string) ($r->other_desc ?? '-'),
                'accdother_price' => (int) ($r->accdother_price ?? 0),
            ])
            ->toArray();

        $this->paketObat = DB::table('rsmst_accdocproducts as ap')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'ap.product_id')
            ->where('ap.accdoc_id', $accdocId)
            ->select('ap.product_id', 'p.product_name', 'p.sales_price', 'ap.accdprod_qty')
            ->orderBy('ap.product_id')
            ->get()
            ->map(fn($r) => [
                'product_id' => (string) $r->product_id,
                'product_name' => (string) ($r->product_name ?? '-'),
                'sales_price' => (int) ($r->sales_price ?? 0),
                'accdprod_qty' => (int) ($r->accdprod_qty ?? 1),
            ])
            ->toArray();
    }

    /* ===============================
     | TARIF PER KELAS (rsmst_actdclasses)
     =============================== */
    private function loadTarifKelas(?string $accdocId): void
    {
        // Oracle treats '' as NULL — pakai whereNotNull saja.
        $kelas = DB::table('rsmst_class')->whereNotNull('class_desc')->orderBy('class_id')->select('class_id', 'class_desc')->get();

        $existing = $accdocId ? DB::table('rsmst_actdclasses')->where('accdoc_id', $accdocId)->select('id', 'class_id', 'actd_price', 'actd_price_bpjs')->get()->keyBy('class_id') : collect();

        $this->tarifKelas = $kelas
            ->map(function ($k) use ($existing) {
                $row = $existing[$k->class_id] ?? null;
                return [
                    'id' => $row->id ?? null,
                    'class_id' => (int) $k->class_id,
                    'class_desc' => (string) $k->class_desc,
                    'actd_price' => (int) ($row->actd_price ?? 0),
                    'actd_price_bpjs' => (int) ($row->actd_price_bpjs ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    public function copyTarifKelasDariBaris(int $idxSource): void
    {
        if (!isset($this->tarifKelas[$idxSource])) {
            return;
        }
        $src = $this->tarifKelas[$idxSource];
        foreach ($this->tarifKelas as $i => $row) {
            if ($i === $idxSource) {
                continue;
            }
            $this->tarifKelas[$i]['actd_price'] = $src['actd_price'];
            $this->tarifKelas[$i]['actd_price_bpjs'] = $src['actd_price_bpjs'];
        }
        $this->incrementVersion('modal');
        $this->dispatch('toast', type: 'success', message: 'Tarif disalin ke semua kelas. Klik Simpan untuk apply.');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-jasa-dokter-actions');
    }

    protected function resetAllFields(): void
    {
        $this->reset(['accdocId', 'accdocDesc', 'accdocPrice', 'accdocPriceBpjs', 'activeStatus', 'tarifKelas', 'paketLainLain', 'paketObat', 'formPaketLain', 'formPaketObat']);
        $this->activeStatus = '1';
        $this->formPaketObat['accdprod_qty'] = 1;
        $this->resetVersion();
    }

    /* ===============================
     | LOV LISTENERS — PAKET LAIN-LAIN
     =============================== */
    #[On('lov.selected.paket-lain-master-jd')]
    public function onLainLainSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'accdother_price' => null];
            return;
        }

        // Cegah duplikat — kalau sudah ada di list, skip + toast.
        foreach ($this->paketLainLain as $row) {
            if ($row['other_id'] === (string) $payload['other_id']) {
                $this->dispatch('toast', type: 'error', message: 'Item lain-lain sudah ada di paket.');
                return;
            }
        }

        $this->formPaketLain = [
            'other_id' => (string) $payload['other_id'],
            'other_desc' => (string) ($payload['other_desc'] ?? '-'),
            'accdother_price' => (int) ($payload['other_price'] ?? 0),
        ];

        $this->dispatch('focus-input-paket-lain-price');
    }

    public function addPaketLainLain(): void
    {
        $this->validate(
            [
                'formPaketLain.other_id' => ['required', 'exists:rsmst_others,other_id'],
                'formPaketLain.accdother_price' => ['required', 'numeric', 'min:0'],
            ],
            [
                'formPaketLain.other_id.required' => 'Item lain-lain wajib dipilih.',
                'formPaketLain.other_id.exists' => 'Item lain-lain tidak valid.',
                'formPaketLain.accdother_price.required' => 'Harga wajib diisi.',
                'formPaketLain.accdother_price.numeric' => 'Harga harus berupa angka.',
            ],
        );

        $this->paketLainLain[] = [
            'other_id' => $this->formPaketLain['other_id'],
            'other_desc' => $this->formPaketLain['other_desc'],
            'accdother_price' => (int) $this->formPaketLain['accdother_price'],
        ];

        $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'accdother_price' => null];
        $this->incrementVersion('modal');
    }

    public function removePaketLainLain(int $idx): void
    {
        if (isset($this->paketLainLain[$idx])) {
            unset($this->paketLainLain[$idx]);
            $this->paketLainLain = array_values($this->paketLainLain);
        }
    }

    public function cancelFormPaketLain(): void
    {
        $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'accdother_price' => null];
        $this->resetValidation('formPaketLain.*');
        $this->incrementVersion('modal');
    }

    /* ===============================
     | LOV LISTENERS — PAKET OBAT
     =============================== */
    #[On('lov.selected.paket-obat-master-jd')]
    public function onObatSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'accdprod_qty' => 1];
            return;
        }

        foreach ($this->paketObat as $row) {
            if ($row['product_id'] === (string) $payload['product_id']) {
                $this->dispatch('toast', type: 'error', message: 'Produk obat sudah ada di paket.');
                return;
            }
        }

        $this->formPaketObat = [
            'product_id' => (string) $payload['product_id'],
            'product_name' => (string) ($payload['product_name'] ?? '-'),
            'sales_price' => (int) ($payload['sales_price'] ?? 0),
            'accdprod_qty' => 1,
        ];

        $this->dispatch('focus-input-paket-obat-qty');
    }

    public function addPaketObat(): void
    {
        $this->validate(
            [
                'formPaketObat.product_id' => ['required', 'exists:immst_products,product_id'],
                'formPaketObat.accdprod_qty' => ['required', 'numeric', 'min:1'],
            ],
            [
                'formPaketObat.product_id.required' => 'Produk wajib dipilih.',
                'formPaketObat.product_id.exists' => 'Produk tidak valid.',
                'formPaketObat.accdprod_qty.required' => 'Qty wajib diisi.',
                'formPaketObat.accdprod_qty.min' => 'Qty minimal 1.',
            ],
        );

        $this->paketObat[] = [
            'product_id' => $this->formPaketObat['product_id'],
            'product_name' => $this->formPaketObat['product_name'],
            'sales_price' => (int) $this->formPaketObat['sales_price'],
            'accdprod_qty' => (int) $this->formPaketObat['accdprod_qty'],
        ];

        $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'accdprod_qty' => 1];
        $this->incrementVersion('modal');
    }

    public function removePaketObat(int $idx): void
    {
        if (isset($this->paketObat[$idx])) {
            unset($this->paketObat[$idx]);
            $this->paketObat = array_values($this->paketObat);
        }
    }

    public function cancelFormPaketObat(): void
    {
        $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'accdprod_qty' => 1];
        $this->resetValidation('formPaketObat.*');
        $this->incrementVersion('modal');
    }

    /* ===============================
     | SAVE — header + paket (delete-then-insert)
     =============================== */
    public function save(): void
    {
        $rules = [
            'accdocId' => ['required', 'string', 'max:10', $this->formMode === 'create' ? Rule::unique('rsmst_accdocs', 'accdoc_id') : Rule::unique('rsmst_accdocs', 'accdoc_id')->ignore($this->accdocId, 'accdoc_id')],
            'accdocDesc' => ['required', 'string', 'max:100'],
            'accdocPrice' => ['required', 'numeric', 'min:0'],
            'accdocPriceBpjs' => ['nullable', 'numeric', 'min:0'],
            'activeStatus' => ['required', Rule::in(['1', '0'])],
        ];

        $messages = [
            'accdocId.required' => 'Kode wajib diisi.',
            'accdocId.max' => 'Kode maksimal 10 karakter.',
            'accdocId.unique' => 'Kode jasa dokter sudah dipakai.',
            'accdocDesc.required' => 'Nama wajib diisi.',
            'accdocDesc.max' => 'Nama maksimal 100 karakter.',
            'accdocPrice.required' => 'Tarif Umum wajib diisi.',
            'accdocPrice.numeric' => 'Tarif Umum harus berupa angka.',
            'accdocPriceBpjs.numeric' => 'Tarif BPJS harus berupa angka.',
            'activeStatus.required' => 'Status wajib dipilih.',
        ];

        $this->validate($rules, $messages);

        try {
            DB::transaction(function () {
                $payload = [
                    'accdoc_desc' => $this->accdocDesc,
                    'accdoc_price' => (int) ($this->accdocPrice ?? 0),
                    'accdoc_price_bpjs' => (int) ($this->accdocPriceBpjs ?? 0),
                    'active_status' => $this->activeStatus,
                ];

                if ($this->formMode === 'create') {
                    DB::table('rsmst_accdocs')->insert([
                        'accdoc_id' => $this->accdocId,
                        ...$payload,
                    ]);
                } else {
                    DB::table('rsmst_accdocs')->where('accdoc_id', $this->accdocId)->update($payload);
                }

                // Delete-then-insert paket detail (idempotent, sederhana).
                DB::table('rsmst_accdocothers')->where('accdoc_id', $this->accdocId)->delete();
                foreach ($this->paketLainLain as $item) {
                    DB::table('rsmst_accdocothers')->insert([
                        'accdoc_id' => $this->accdocId,
                        'other_id' => $item['other_id'],
                        'accdother_price' => (int) ($item['accdother_price'] ?? 0),
                    ]);
                }

                DB::table('rsmst_accdocproducts')->where('accdoc_id', $this->accdocId)->delete();
                foreach ($this->paketObat as $item) {
                    DB::table('rsmst_accdocproducts')->insert([
                        'accdoc_id' => $this->accdocId,
                        'product_id' => $item['product_id'],
                        'accdprod_qty' => (int) ($item['accdprod_qty'] ?? 1),
                    ]);
                }

                // Upsert tarif per kelas (pola sama dgn rsmst_docvisits) — baris semua-nol dihapus.
                foreach ($this->tarifKelas as $row) {
                    $allZero = (int) $row['actd_price'] === 0 && (int) $row['actd_price_bpjs'] === 0;

                    $payloadKelas = [
                        'actd_price' => (int) ($row['actd_price'] ?? 0),
                        'actd_price_bpjs' => (int) ($row['actd_price_bpjs'] ?? 0),
                    ];

                    if ($row['id']) {
                        if ($allZero) {
                            DB::table('rsmst_actdclasses')->where('id', $row['id'])->delete();
                        } else {
                            DB::table('rsmst_actdclasses')->where('id', $row['id'])->update($payloadKelas);
                        }
                    } elseif (!$allZero) {
                        $nextId = (int) (DB::table('rsmst_actdclasses')->max('id') ?? 0) + 1;
                        DB::table('rsmst_actdclasses')->insert([
                            'id' => $nextId,
                            'accdoc_id' => $this->accdocId,
                            'class_id' => (int) $row['class_id'],
                            ...$payloadKelas,
                        ]);
                    }
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil disimpan.');
            $this->closeModal();
            $this->dispatch('master.jasa-dokter.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TOGGLE ACTIVE
     =============================== */
    #[On('master.jasa-dokter.toggleActive')]
    public function toggleActive(string $accdocId): void
    {
        $cur = (string) DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success', message: 'Status → ' . ($next === '1' ? 'Aktif' : 'Tidak Aktif'));
        $this->dispatch('master.jasa-dokter.saved');
    }

    /* ===============================
     | DELETE — cek transaksi dulu
     =============================== */
    #[On('master.jasa-dokter.requestDelete')]
    public function deleteFromGrid(string $accdocId): void
    {
        try {
            // Cek pemakaian di transaksi RJ/UGD/RI.
            $used = DB::table('rstxn_rjaccdocs')->where('accdoc_id', $accdocId)->exists() || DB::table('rstxn_ugdaccdocs')->where('accdoc_id', $accdocId)->exists() || DB::table('rstxn_riactdocs')->where('accdoc_id', $accdocId)->exists();

            if ($used) {
                $this->dispatch('toast', type: 'error', message: 'Jasa medis sudah dipakai di transaksi RJ/UGD/RI, tidak bisa dihapus.');
                return;
            }

            DB::transaction(function () use ($accdocId) {
                // Cascade hapus paket & tarif per kelas dulu, baru header.
                DB::table('rsmst_accdocothers')->where('accdoc_id', $accdocId)->delete();
                DB::table('rsmst_accdocproducts')->where('accdoc_id', $accdocId)->delete();
                DB::table('rsmst_actdclasses')->where('accdoc_id', $accdocId)->delete();
                $deleted = DB::table('rsmst_accdocs')->where('accdoc_id', $accdocId)->delete();

                if ($deleted === 0) {
                    throw new \RuntimeException('Jasa medis tidak ditemukan.');
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil dihapus.');
            $this->dispatch('master.jasa-dokter.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function formatRupiah($price): string
    {
        return 'Rp ' . number_format((int) ($price ?? 0), 0, ',', '.');
    }
};
?>

<div>
    <x-modal name="master-jasa-dokter-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-jasa-dokter-actions"
            event="master.jasa-dokter.saved"
            label="Jasa Dokter"
            :wireKey="$this->renderKey('modal', [$formMode, $accdocId ?? 'new'])">
            <div x-data
                x-on:focus-input-paket-lain-price.window="$nextTick(() => $refs.inputPaketLainPrice?.focus())"
                x-on:focus-input-paket-obat-qty.window="$nextTick(() => $refs.inputPaketObatQty?.focus())">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Jasa Dokter' : 'Tambah Jasa Dokter' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi tarif & paket bundling lain-lain / obat.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20 space-y-4">

                {{-- INFORMASI DASAR --}}
                <x-border-form title="Informasi Dasar">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        {{-- Kode --}}
                        <div class="md:col-span-2">
                            <x-input-label value="Kode" />
                            <x-text-input wire:model.live="accdocId" :disabled="$formMode === 'edit'"
                                :error="$errors->has('accdocId')" class="w-full mt-1 font-mono uppercase" maxlength="10" />
                            <x-input-error :messages="$errors->get('accdocId')" class="mt-1" />
                        </div>

                        {{-- Nama --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Nama Jasa Dokter" />
                            <x-text-input wire:model.live="accdocDesc" :error="$errors->has('accdocDesc')"
                                class="w-full mt-1" placeholder="Contoh: Visite Spesialis, Konsultasi" maxlength="50" />
                            <x-input-error :messages="$errors->get('accdocDesc')" class="mt-1" />
                        </div>

                        {{-- Status Aktif --}}
                        <div class="md:col-span-4">
                            <x-input-label value="Status Aktif" />
                            <x-select-input wire:model.live="activeStatus"
                                :error="$errors->has('activeStatus')" class="w-full mt-1">
                                <option value="1">Aktif</option>
                                <option value="0">Tidak Aktif</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                        </div>

                        {{-- Tarif Umum --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Tarif Umum" />
                            <x-text-input-number wire:model="accdocPrice" :error="$errors->has('accdocPrice')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('accdocPrice')" class="mt-1" />
                        </div>

                        {{-- Tarif BPJS --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Tarif BPJS" />
                            <x-text-input-number wire:model="accdocPriceBpjs"
                                :error="$errors->has('accdocPriceBpjs')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('accdocPriceBpjs')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                {{-- TARIF PER KELAS RAWAT --}}
                <x-border-form title="Tarif per Kelas Rawat">
                    <div class="space-y-3">
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Tarif 0 = tidak berlaku (pakai Tarif Umum/BPJS di atas). Set semua kolom = 0 untuk menghapus
                            tarif kelas tsb. Tombol <span class="font-semibold">Copy ke Semua</span> menyalin tarif
                            baris ke semua kelas lain.
                        </div>

                        <div class="overflow-hidden bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kelas</th>
                                        <th class="px-3 py-2 font-medium">Tarif Umum</th>
                                        <th class="px-3 py-2 font-medium">Tarif BPJS</th>
                                        <th class="px-3 py-2 w-36 text-center font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($tarifKelas as $idx => $row)
                                        <tr wire:key="tarif-kelas-{{ $row['class_id'] }}">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ $row['class_desc'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 font-mono">ID: {{ $row['class_id'] }}</div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.actd_price" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.actd_price_bpjs" />
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" wire:click="copyTarifKelasDariBaris({{ $idx }})"
                                                    wire:confirm="Salin tarif baris ini ke semua kelas lainnya?"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                    title="Salin tarif baris ini ke semua kelas lain">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    Copy ke Semua
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Data kelas belum tersedia.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- PAKET LAIN-LAIN --}}
                <x-border-form title="Paket Lain-Lain (auto-tambah saat jasa dokter dipilih di transaksi)">
                    <div class="space-y-3">
                        {{-- Form add --}}
                        <div class="p-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            @if (empty($formPaketLain['other_id']))
                                <livewire:lov.lain-lain.lov-lain-lain target="paket-lain-master-jd"
                                    label="Tambah Item Lain-Lain"
                                    placeholder="Ketik kode / nama lain-lain..."
                                    wire:key="lov-paket-lain-{{ $renderVersions['modal'] ?? 0 }}" />
                            @else
                                <div class="flex flex-wrap gap-3 items-end">
                                    <div class="w-24">
                                        <x-input-label value="Kode" class="mb-1" />
                                        <x-text-input wire:model="formPaketLain.other_id" disabled
                                            class="w-full text-sm font-mono" />
                                    </div>
                                    <div class="flex-1 min-w-[10rem]">
                                        <x-input-label value="Nama" class="mb-1" />
                                        <x-text-input wire:model="formPaketLain.other_desc" disabled
                                            class="w-full text-sm" />
                                    </div>
                                    <div class="w-32">
                                        <x-input-label value="Harga" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketLain.accdother_price"
                                            x-ref="inputPaketLainPrice"
                                            x-on:keydown.enter.prevent="$el.blur(); $wire.addPaketLainLain()" />
                                        <x-input-error :messages="$errors->get('formPaketLain.accdother_price')" class="mt-1" />
                                    </div>
                                    <div class="flex gap-2 items-end shrink-0">
                                        <x-primary-button type="button" wire:click="addPaketLainLain"
                                            class="text-xs px-3 py-1.5">+ Tambah</x-primary-button>
                                        <x-secondary-button type="button" wire:click="cancelFormPaketLain"
                                            class="text-xs px-3 py-1.5">Batal</x-secondary-button>
                                    </div>
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('formPaketLain.other_id')" class="mt-1" />
                        </div>

                        {{-- List --}}
                        <div class="overflow-hidden bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kode</th>
                                        <th class="px-3 py-2 font-medium">Nama</th>
                                        <th class="px-3 py-2 font-medium text-right">Harga</th>
                                        <th class="px-3 py-2 w-16 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($paketLainLain as $idx => $row)
                                        <tr wire:key="paket-lain-{{ $idx }}-{{ $row['other_id'] }}">
                                            <td class="px-3 py-2 font-mono text-xs">{{ $row['other_id'] }}</td>
                                            <td class="px-3 py-2">{{ $row['other_desc'] }}</td>
                                            <td class="px-3 py-2 text-right font-mono">
                                                {{ $this->formatRupiah($row['accdother_price']) }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button"
                                                    wire:click="removePaketLainLain({{ $idx }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4"
                                                class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Belum ada paket lain-lain
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>

                {{-- PAKET OBAT --}}
                <x-border-form title="Paket Obat (auto-tambah saat jasa dokter dipilih di transaksi)">
                    <div class="space-y-3">
                        {{-- Form add --}}
                        <div class="p-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            @if (empty($formPaketObat['product_id']))
                                <livewire:lov.product.lov-product target="paket-obat-master-jd"
                                    label="Tambah Item Obat"
                                    placeholder="Ketik kode / nama produk..."
                                    wire:key="lov-paket-obat-{{ $renderVersions['modal'] ?? 0 }}" />
                            @else
                                <div class="flex flex-wrap gap-3 items-end">
                                    <div class="w-20">
                                        <x-input-label value="Kode" class="mb-1" />
                                        <x-text-input wire:model="formPaketObat.product_id" disabled
                                            class="w-full text-sm font-mono" />
                                    </div>
                                    <div class="flex-1 min-w-[8rem]">
                                        <x-input-label value="Produk" class="mb-1" />
                                        <x-text-input wire:model="formPaketObat.product_name" disabled
                                            class="w-full text-sm" />
                                    </div>
                                    <div class="w-28">
                                        <x-input-label value="Harga Jual" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketObat.sales_price" disabled />
                                    </div>
                                    <div class="w-20">
                                        <x-input-label value="Qty" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketObat.accdprod_qty"
                                            x-ref="inputPaketObatQty"
                                            x-on:keydown.enter.prevent="$el.blur(); $wire.addPaketObat()" />
                                        <x-input-error :messages="$errors->get('formPaketObat.accdprod_qty')" class="mt-1" />
                                    </div>
                                    <div class="flex gap-2 items-end shrink-0">
                                        <x-primary-button type="button" wire:click="addPaketObat"
                                            class="text-xs px-3 py-1.5">+ Tambah</x-primary-button>
                                        <x-secondary-button type="button" wire:click="cancelFormPaketObat"
                                            class="text-xs px-3 py-1.5">Batal</x-secondary-button>
                                    </div>
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('formPaketObat.product_id')" class="mt-1" />
                        </div>

                        {{-- List --}}
                        <div class="overflow-hidden bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kode</th>
                                        <th class="px-3 py-2 font-medium">Produk</th>
                                        <th class="px-3 py-2 font-medium text-right">Harga Jual</th>
                                        <th class="px-3 py-2 font-medium text-right">Qty</th>
                                        <th class="px-3 py-2 w-16 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($paketObat as $idx => $row)
                                        <tr wire:key="paket-obat-{{ $idx }}-{{ $row['product_id'] }}">
                                            <td class="px-3 py-2 font-mono text-xs">{{ $row['product_id'] }}</td>
                                            <td class="px-3 py-2">{{ $row['product_name'] }}</td>
                                            <td class="px-3 py-2 text-right font-mono">
                                                {{ $this->formatRupiah($row['sales_price']) }}
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['accdprod_qty'] }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button"
                                                    wire:click="removePaketObat({{ $idx }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5"
                                                class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Belum ada paket obat
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Paket lain-lain & obat akan otomatis ter-insert saat jasa dokter dipilih di transaksi RJ/UGD/RI.
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">
                            Batal
                        </x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

            </div>
        </x-dirty-modal-content>
    </x-modal>
</div>
