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
    protected array $renderAreas = ['modal-visit-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $formEntry = [
        'visitDate'  => '',
        'drId'       => '',
        'drName'     => '',
        'visitPrice' => '',
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

        $this->formEntry['visitDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiVisit'] = [];
        }
    }

    /* ===============================
     | FIND DATA
     =============================== */
    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_rivisits')
            ->join('rsmst_doctors', 'rstxn_rivisits.dr_id', '=', 'rsmst_doctors.dr_id')
            ->select(
                DB::raw("to_char(visit_date, 'dd/mm/yyyy hh24:mi:ss') as visit_date"),
                'rstxn_rivisits.dr_id',
                'rsmst_doctors.dr_name',
                'rstxn_rivisits.visit_price',
                'rstxn_rivisits.visit_no',
            )
            ->where('rstxn_rivisits.rihdr_no', $riHdrNo)
            ->orderByDesc('visit_date')
            ->get();

        $this->dataDaftarRI['RiVisit'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — DOKTER
     =============================== */
    #[On('lov.selected.dokter-visit-ri')]
    public function onDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['drId']   = '';
            $this->formEntry['drName'] = '';
            return;
        }

        $this->formEntry['drId']   = $payload['dr_id'];
        $this->formEntry['drName'] = $payload['dr_name'];

        if (empty($this->formEntry['visitDate'])) {
            $this->formEntry['visitDate'] = $this->nowFormatted();
        }

        // Auto-harga: ambil dari rsmst_docvisits sesuai kelas kamar & klaim
        if (empty($this->formEntry['visitPrice'])) {
            $classId = DB::table('rstxn_rihdrs')
                ->join('rsmst_rooms', 'rstxn_rihdrs.room_id', '=', 'rsmst_rooms.room_id')
                ->where('rihdr_no', $this->riHdrNo)
                ->value('class_id');

            if ($classId) {
                $riData    = $this->findDataRI($this->riHdrNo);
                $klaimStatus = DB::table('rsmst_klaimtypes')
                    ->where('klaim_id', $riData['klaimId'] ?? '')
                    ->value('klaim_status') ?? 'UMUM';

                $col   = $klaimStatus === 'BPJS' ? 'visit_price_bpjs' : 'visit_price';
                $price = DB::table('rsmst_docvisits')
                    ->where('dr_id', $payload['dr_id'])
                    ->where('class_id', $classId)
                    ->value($col);

                $this->formEntry['visitPrice'] = $price ?? 0;
            }
        }

        $this->dispatch('focus-input-visit-price');
    }

    /* ===============================
     | INSERT VISIT
     =============================== */
    public function insertVisit(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.visitDate'  => 'bail|required|date_format:d/m/Y H:i:s',
                'formEntry.drId'       => 'bail|required|exists:rsmst_doctors,dr_id',
                'formEntry.visitPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntry.visitDate.required'    => 'Tanggal kunjungan wajib diisi.',
                'formEntry.visitDate.date_format' => 'Format tanggal: dd/mm/yyyy hh24:mi:ss.',
                'formEntry.drId.required'         => 'Dokter wajib dipilih.',
                'formEntry.drId.exists'           => 'Dokter tidak valid.',
                'formEntry.visitPrice.required'   => 'Tarif kunjungan wajib diisi.',
                'formEntry.visitPrice.numeric'    => 'Tarif harus berupa angka.',
                'formEntry.visitPrice.min'        => 'Tarif minimal 0.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_rivisits')
                    ->select(DB::raw("nvl(max(visit_no)+1,1) as visit_no_max"))
                    ->first();

                DB::table('rstxn_rivisits')->insert([
                    'visit_no'    => $last->visit_no_max,
                    'rihdr_no'    => $this->riHdrNo,
                    'dr_id'       => $this->formEntry['drId'],
                    'visit_date'  => DB::raw("to_date('" . $this->formEntry['visitDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'visit_price' => $this->formEntry['visitPrice'],
                ]);
                $this->appendAdminLogRI($this->riHdrNo, 'Tambah Visit: Dr. ' . $this->formEntry['drName']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Kunjungan dokter berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE VISIT
     =============================== */
    public function removeVisit(int $visitNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($visitNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_rivisits')->where('visit_no', $visitNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, "Hapus Visit #{$visitNo}");
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Kunjungan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SET DATE (dari x-datepicker)
     =============================== */
    public function setVisitDate(string $date): void
    {
        $this->formEntry['visitDate'] = $date;
    }

    public function refreshVisitDate(): void
    {
        $this->formEntry['visitDate'] = $this->nowFormatted();
        $this->resetErrorBag('formEntry.visitDate');
    }

    /* ===============================
     | RESET
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['visitDate'] = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-visit-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-visit-ri', [$riHdrNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    {{-- FORM INPUT --}}
    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-visit-price.window="$nextTick(() => $refs.inputVisitPrice?.focus())">

            @if (empty($formEntry['drId']))
                <livewire:lov.dokter.lov-dokter target="dokter-visit-ri" label="Dokter Kunjungan"
                    placeholder="Ketik kode/nama dokter..."
                    wire:key="lov-dokter-visit-{{ $riHdrNo }}-{{ $renderVersions['modal-visit-ri'] ?? 0 }}" />
            @else
                <div class="grid grid-cols-4 gap-3 items-end">
                    {{-- Dokter --}}
                    <div>
                        <x-input-label value="Dokter" class="mb-1" />
                        <x-text-input wire:model="formEntry.drName" disabled class="w-full text-sm" />
                    </div>
                    {{-- Tanggal --}}
                    <div>
                        <x-input-label value="Tanggal Kunjungan" class="mb-1" />
                        <div class="flex gap-1">
                            <x-text-input wire:model="formEntry.visitDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                class="flex-1 text-sm font-mono min-w-0"
                                x-on:keyup.enter="$refs.inputVisitPrice?.focus()" />
                            <button type="button" wire:click="refreshVisitDate" title="Waktu sekarang"
                                class="shrink-0 px-2 text-gray-400 hover:text-brand-green dark:hover:text-brand-lime transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                        @error('formEntry.visitDate') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Tarif --}}
                    <div>
                        <x-input-label value="Tarif Kunjungan" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.visitPrice"
                            x-ref="inputVisitPrice"
                            x-init="$nextTick(() => $refs.inputVisitPrice?.focus())"
                            x-on:keydown.enter.prevent="$wire.insertVisit()" />
                        @error('formEntry.visitPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Buttons --}}
                    <div class="flex gap-2 items-end">
                        <x-primary-button wire:click.prevent="insertVisit" wire:loading.attr="disabled"
                            wire:target="insertVisit" class="flex-1 justify-center">
                            <span wire:loading.remove wire:target="insertVisit">Simpan</span>
                            <span wire:loading wire:target="insertVisit"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                        <x-secondary-button wire:click.prevent="resetFormEntry">Batal</x-secondary-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Kunjungan Dokter</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiVisit'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Dokter</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked)
                            <th class="w-20 px-4 py-3 text-center">Hapus</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiVisit'] ?? [] as $item)
                        <tr class="transition group hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['visit_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['dr_name'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['visit_price'] ?? 0) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeVisit({{ $item['visit_no'] }})"
                                        wire:confirm="Hapus kunjungan ini?" wire:loading.attr="disabled"
                                        wire:target="removeVisit({{ $item['visit_no'] }})"
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
                            <td colspan="{{ $isFormLocked ? 3 : 4 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada kunjungan dokter
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiVisit']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiVisit'])->sum('visit_price')) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
