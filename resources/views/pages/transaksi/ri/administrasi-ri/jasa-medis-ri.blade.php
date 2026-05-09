<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-jasa-medis-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    /** Status klaim ('BPJS' atau lainnya) — dipakai untuk pricing pas LOV select. */
    public string $klaimStatus = 'UMUM';

    public array $formEntry = [
        'actpDate'      => '',
        'jasaMedisId'   => '',
        'jasaMedisDesc' => '',
        'jasaMedisPrice'=> '',
        'jasaMedisQty'  => '1',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) return;
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        $this->formEntry['actpDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->loadRIMeta($this->riHdrNo);
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiJasaMedis'] = [];
        }
    }

    /**
     * Ambil status klaim (BPJS/UMUM) untuk pricing tarif saat LOV select.
     * Pakai findDataRI() di trait yang sudah populate klaimStatus.
     */
    private function loadRIMeta(int $riHdrNo): void
    {
        $data = $this->findDataRI($riHdrNo);
        $this->klaimStatus = $data['klaimStatus'] ?? 'UMUM';
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riactparams')
            ->join('rsmst_actparamedics', 'rsmst_actparamedics.pact_id', '=', 'rstxn_riactparams.pact_id')
            ->select(
                DB::raw("to_char(actp_date, 'dd/mm/yyyy hh24:mi:ss') as actp_date"),
                'rstxn_riactparams.pact_id',
                'rsmst_actparamedics.pact_desc',
                'rstxn_riactparams.actp_price',
                'rstxn_riactparams.actp_qty',
                'rstxn_riactparams.actp_no',
            )
            ->where('rstxn_riactparams.rihdr_no', $riHdrNo)
            ->orderByDesc('actp_date')
            ->get();

        $this->dataDaftarRI['RiJasaMedis'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — JASA MEDIS
     =============================== */
    #[On('lov.selected.jasa-medis-ri')]
    public function onJasaMedisSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['jasaMedisId']    = '';
            $this->formEntry['jasaMedisDesc']  = '';
            $this->formEntry['jasaMedisPrice'] = '';
            return;
        }

        $this->formEntry['jasaMedisId']    = $payload['pact_id'];
        $this->formEntry['jasaMedisDesc']  = $payload['pact_desc'];
        $this->formEntry['jasaMedisPrice'] = $this->klaimStatus === 'BPJS' ? ($payload['pact_price_bpjs'] ?? $payload['pact_price']) : $payload['pact_price'];

        $this->dispatch('focus-input-jm-price');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertJasaMedis(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.jasaMedisId'    => 'bail|required|exists:rsmst_actparamedics,pact_id',
                'formEntry.jasaMedisPrice' => 'bail|required|numeric|min:0',
                'formEntry.jasaMedisQty'   => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.jasaMedisId.required'    => 'Jasa medis wajib dipilih.',
                'formEntry.jasaMedisId.exists'      => 'Jasa medis tidak valid.',
                'formEntry.jasaMedisPrice.required' => 'Tarif wajib diisi.',
                'formEntry.jasaMedisPrice.numeric'  => 'Tarif harus berupa angka.',
                'formEntry.jasaMedisQty.required'   => 'Jumlah wajib diisi.',
                'formEntry.jasaMedisQty.min'        => 'Jumlah minimal 1.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_riactparams')
                    ->select(DB::raw("nvl(max(actp_no)+1,1) as actp_no_max"))
                    ->first();

                DB::table('rstxn_riactparams')->insert([
                    'actp_no'    => $last->actp_no_max,
                    'rihdr_no'   => $this->riHdrNo,
                    'pact_id'    => $this->formEntry['jasaMedisId'],
                    'actp_date'  => DB::raw("TO_DATE('" . $this->formEntry['actpDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'actp_price' => $this->formEntry['jasaMedisPrice'],
                    'actp_qty'   => $this->formEntry['jasaMedisQty'],
                ]);
                $this->appendAdminLogRI($this->riHdrNo, 'Tambah Jasa Medis: ' . $this->formEntry['jasaMedisDesc']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeJasaMedis(int $actpNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($actpNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_riactparams')->where('actp_no', $actpNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Jasa Medis #' . $actpNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function refreshActpDate(): void
    {
        $this->formEntry['actpDate'] = $this->nowFormatted();
        $this->resetErrorBag('formEntry.actpDate');
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['jasaMedisQty'] = '1';
        $this->formEntry['actpDate']     = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-jasa-medis-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-jasa-medis-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-jm-price.window="$nextTick(() => $refs.inputJmPrice?.focus())">

            @if (empty($formEntry['jasaMedisId']))
                <livewire:lov.jasa-medis.lov-jasa-medis target="jasa-medis-ri" label="Jasa Medis"
                    placeholder="Ketik kode/nama jasa medis..."
                    wire:key="lov-jm-{{ $riHdrNo }}-{{ $renderVersions['modal-jasa-medis-ri'] ?? 0 }}" />
            @else
                <div class="grid grid-cols-6 gap-3 items-end">
                    {{-- Tanggal --}}
                    <div>
                        <x-input-label value="Tanggal" class="mb-1" />
                        <div class="flex gap-1">
                            <x-text-input wire:model="formEntry.actpDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                class="flex-1 text-sm font-mono min-w-0" />
                            <button type="button" wire:click="refreshActpDate" title="Waktu sekarang"
                                class="shrink-0 px-2 text-gray-400 hover:text-brand-green dark:hover:text-brand-lime transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{-- Kode --}}
                    <div>
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaMedisId" disabled class="w-full text-sm" />
                    </div>
                    {{-- Nama --}}
                    <div class="col-span-2">
                        <x-input-label value="Jasa Medis" class="mb-1" />
                        <x-text-input wire:model="formEntry.jasaMedisDesc" disabled class="w-full text-sm" />
                    </div>
                    {{-- Tarif --}}
                    <div>
                        <x-input-label value="Tarif" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.jasaMedisPrice"
                            x-ref="inputJmPrice"
                            x-on:keydown.enter.prevent="$refs.inputJmQty?.focus()" />
                        @error('formEntry.jasaMedisPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Qty + Buttons --}}
                    <div class="flex gap-2 items-end">
                        <div class="flex-1">
                            <x-input-label value="Qty" class="mb-1" />
                            <x-text-input type="number" min="1" wire:model="formEntry.jasaMedisQty" placeholder="1"
                                class="w-full text-sm text-right tabular-nums"
                                x-ref="inputJmQty"
                                x-on:keydown.enter.prevent="$wire.insertJasaMedis()" />
                            @error('formEntry.jasaMedisQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        <x-primary-button wire:click.prevent="insertJasaMedis" wire:loading.attr="disabled"
                            wire:target="insertJasaMedis">
                            <span wire:loading.remove wire:target="insertJasaMedis">Tambah</span>
                            <span wire:loading wire:target="insertJasaMedis"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                        <x-secondary-button wire:click.prevent="resetFormEntry">Batal</x-secondary-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Jasa Medis</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiJasaMedis'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jasa Medis</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiJasaMedis'] ?? [] as $item)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['actp_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $item['pact_id'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['pact_desc'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['actp_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $item['actp_qty'] ?? 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format(($item['actp_price'] ?? 0) * ($item['actp_qty'] ?? 1)) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeJasaMedis({{ $item['actp_no'] }})"
                                        wire:confirm="Hapus jasa medis ini?" wire:loading.attr="disabled"
                                        wire:target="removeJasaMedis({{ $item['actp_no'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 6 : 7 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada jasa medis
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiJasaMedis']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiJasaMedis'])->sum(fn($i) => ($i['actp_price'] ?? 0) * ($i['actp_qty'] ?? 1))) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
