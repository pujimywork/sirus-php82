<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['matrix'];

    /* -------------------- DOKTER TERPILIH -------------------- */
    public ?string $drId = null;
    public string $drName = '';

    /* -------------------- MATRIX KELAS × TARIF -------------------- */
    public array $matrix = [];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — listen dari parent (master-dokter)
     =============================== */
    #[On('master.dokter.openTarif')]
    public function openTarif(string $drId, string $drName): void
    {
        $this->drId = $drId;
        $this->drName = $drName;
        $this->loadMatrix();
        $this->incrementVersion('matrix');
        $this->dispatch('open-modal', name: 'master-dokter-tarif-visit-konsul-actions');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'master-dokter-tarif-visit-konsul-actions');
    }

    /* ===============================
     | LOAD MATRIX
     =============================== */
    private function loadMatrix(): void
    {
        // Oracle treats '' as NULL — pakai whereNotNull saja.
        $kelas = DB::table('rsmst_class')->whereNotNull('class_desc')->orderBy('class_id')->select('class_id', 'class_desc')->get();

        $existing = DB::table('rsmst_docvisits')->where('dr_id', $this->drId)->select('id', 'class_id', 'visit_price', 'visit_price_bpjs', 'konsul_price', 'konsul_price_bpjs')->get()->keyBy('class_id');

        $this->matrix = $kelas
            ->map(function ($k) use ($existing) {
                $row = $existing[$k->class_id] ?? null;
                return [
                    'id' => $row->id ?? null,
                    'class_id' => (int) $k->class_id,
                    'class_desc' => (string) $k->class_desc,
                    'visit_price' => (int) ($row->visit_price ?? 0),
                    'visit_price_bpjs' => (int) ($row->visit_price_bpjs ?? 0),
                    'konsul_price' => (int) ($row->konsul_price ?? 0),
                    'konsul_price_bpjs' => (int) ($row->konsul_price_bpjs ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    /* ===============================
     | SAVE — batch upsert
     =============================== */
    public function save(): void
    {
        if (!$this->drId) {
            $this->dispatch('toast', type: 'error', message: 'Dokter tidak terpilih.');
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->matrix as $row) {
                    $allZero = (int) $row['visit_price'] === 0 && (int) $row['visit_price_bpjs'] === 0 && (int) $row['konsul_price'] === 0 && (int) $row['konsul_price_bpjs'] === 0;

                    $payload = [
                        'visit_price' => (int) ($row['visit_price'] ?? 0),
                        'visit_price_bpjs' => (int) ($row['visit_price_bpjs'] ?? 0),
                        'konsul_price' => (int) ($row['konsul_price'] ?? 0),
                        'konsul_price_bpjs' => (int) ($row['konsul_price_bpjs'] ?? 0),
                    ];

                    if ($row['id']) {
                        if ($allZero) {
                            DB::table('rsmst_docvisits')->where('id', $row['id'])->delete();
                        } else {
                            DB::table('rsmst_docvisits')->where('id', $row['id'])->update($payload);
                        }
                    } else {
                        if (!$allZero) {
                            $nextId = (int) (DB::table('rsmst_docvisits')->max('id') ?? 0) + 1;
                            DB::table('rsmst_docvisits')->insert([
                                'id' => $nextId,
                                'dr_id' => $this->drId,
                                'class_id' => (int) $row['class_id'],
                                ...$payload,
                            ]);
                        }
                    }
                }
            });

            $this->loadMatrix();
            $this->incrementVersion('matrix');
            $this->dispatch('toast', type: 'success', message: 'Tarif visit & konsul berhasil disimpan.');
            // Refresh expand row tarif di list master dokter.
            $this->dispatch('master.dokter.tarif-saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function copyDariBaris(int $idxSource): void
    {
        if (!isset($this->matrix[$idxSource])) {
            return;
        }
        $src = $this->matrix[$idxSource];
        foreach ($this->matrix as $i => $row) {
            if ($i === $idxSource) {
                continue;
            }
            $this->matrix[$i]['visit_price'] = $src['visit_price'];
            $this->matrix[$i]['visit_price_bpjs'] = $src['visit_price_bpjs'];
            $this->matrix[$i]['konsul_price'] = $src['konsul_price'];
            $this->matrix[$i]['konsul_price_bpjs'] = $src['konsul_price_bpjs'];
        }
        $this->incrementVersion('matrix');
        $this->dispatch('toast', type: 'success', message: 'Tarif disalin ke semua kelas. Klik Simpan untuk apply.');
    }

    public function formatRupiah($price): string
    {
        return 'Rp ' . number_format((int) ($price ?? 0), 0, ',', '.');
    }
};
?>

<div>
    <x-modal name="master-dokter-tarif-visit-konsul-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('matrix', [$drId ?? 'none']) }}">

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
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Tarif Visit &amp; Konsul per Kelas
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola tarif kunjungan & konsultasi dokter per kelas rawat (Umum & BPJS).
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline flex-wrap gap-x-3 gap-y-1">
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Dokter</div>
                            <div class="text-2xl font-bold text-brand-green dark:text-brand-lime">
                                {{ $drName }}
                            </div>
                            <div
                                class="px-2 py-0.5 text-xs font-mono bg-gray-200 dark:bg-gray-700 rounded text-gray-700 dark:text-gray-200">
                                {{ $drId }}
                            </div>
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
                <div class="max-w-full mx-auto space-y-3">

                    {{-- Hint info --}}
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Tarif 0 = tidak berlaku. Set semua kolom = 0 untuk menghapus tarif kelas tsb. Tombol <span
                            class="font-semibold">Copy ke Semua</span> menyalin tarif baris ke semua kelas lain.
                    </div>

                    {{-- Matrix card --}}
                    <div
                        class="bg-white border border-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-900 rounded-2xl overflow-hidden">
                        <div
                            class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Tarif per Kelas Rawat
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-xs text-gray-500 uppercase">
                                        <th class="px-4 py-3 text-left font-medium" rowspan="2">Kelas</th>
                                        <th class="px-4 py-2 text-center font-medium border-l border-gray-200 dark:border-gray-700"
                                            colspan="2">Visit</th>
                                        <th class="px-4 py-2 text-center font-medium border-l border-gray-200 dark:border-gray-700"
                                            colspan="2">Konsul</th>
                                        <th class="px-4 py-3 text-center font-medium border-l border-gray-200 dark:border-gray-700"
                                            rowspan="2">Aksi</th>
                                    </tr>
                                    <tr class="text-xs text-gray-500 uppercase">
                                        <th
                                            class="px-3 py-2 text-right font-medium border-l border-gray-200 dark:border-gray-700">
                                            Umum</th>
                                        <th class="px-3 py-2 text-right font-medium">BPJS</th>
                                        <th
                                            class="px-3 py-2 text-right font-medium border-l border-gray-200 dark:border-gray-700">
                                            Umum</th>
                                        <th class="px-3 py-2 text-right font-medium">BPJS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($matrix as $idx => $row)
                                        <tr wire:key="dok-tarif-row-{{ $row['class_id'] }}">
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ $row['class_desc'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 font-mono">ID: {{ $row['class_id'] }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 border-l border-gray-200 dark:border-gray-700">
                                                <x-text-input-number
                                                    wire:model="matrix.{{ $idx }}.visit_price" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <x-text-input-number
                                                    wire:model="matrix.{{ $idx }}.visit_price_bpjs" />
                                            </td>
                                            <td class="px-3 py-2 border-l border-gray-200 dark:border-gray-700">
                                                <x-text-input-number
                                                    wire:model="matrix.{{ $idx }}.konsul_price" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <x-text-input-number
                                                    wire:model="matrix.{{ $idx }}.konsul_price_bpjs" />
                                            </td>
                                            <td
                                                class="px-3 py-2 text-center border-l border-gray-200 dark:border-gray-700">
                                                <button type="button" wire:click="copyDariBaris({{ $idx }})"
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
                                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                                Data kelas belum tersedia.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Simpan Semua</span>
                        <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
