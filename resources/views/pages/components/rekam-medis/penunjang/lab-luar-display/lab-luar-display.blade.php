<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Display lab luar pasien — cross-kunjungan by reg_no.
     | Sumber: lbtxn_checkupoutdtls JOIN lbtxn_checkuphdrs JOIN rsmst_pasiens.
     | Status (hdr.checkup_status, sama dengan lab internal):
     |   P → Terdaftar (order baru)
     |   C → Proses (tarif sudah di-post)
     |   H → Selesai (PDF sudah di-upload)
     */

    #[Reactive]
    public string $regNo = '';

    // View PDF (modal + iframe)
    public string $viewFilePDF = '';

    public function mount(string $regNo = ''): void
    {
        $this->regNo = $regNo;
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->regNo)) {
            return collect();
        }

        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price',
                'o.labout_result', 'o.labout_normal', 'o.pdf_path', 'o.keterangan',
                'h.status_rjri', 'h.ref_no', 'h.checkup_status',
                DB::raw("TO_CHAR(h.checkup_date, 'dd/mm/yyyy hh24:mi:ss') as checkup_date"),
                'p.reg_name',
            )
            ->where('h.reg_no', $this->regNo)
            ->where('h.checkup_status', '!=', 'F')
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl')
            ->get();
    }

    /* =======================
     | Resolve URL file lab luar
     |
     | Standar: file di SMB share, di-mount ke storage/app/private/mount/penunjang/lab-luar/
     | → akses via route('files.show', path: 'mount/penunjang/lab-luar/<filename>').
     |
     | Backward-compat: row lama menyimpan full path 'lab-luar/x.pdf' (public legacy)
     | → fallback ke asset('storage/' . $name).
     |
     | Pola identik dengan ⚡lab-luar.blade.php:295-297 (sumber-of-truth).
     * ======================= */
    public function resolveFileUrl(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return str_contains($name, '/') ? asset('storage/' . $name) : route('files.show', ['path' => 'mount/penunjang/lab-luar/' . $name]);
    }

    /* =======================
     | Open / Close PDF Viewer — modal + iframe pakai URL files.show
     * ======================= */
    public function openViewPDF(?string $file): void
    {
        $url = $this->resolveFileUrl($file);

        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }

        $this->viewFilePDF = $url;
        $this->dispatch('open-modal', name: 'view-lab-luar-pdf');
    }

    public function closeViewPDF(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-lab-luar-pdf');
    }
};
?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="w-full">
                            <div class="mb-2 overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-muted table-auto dark:text-gray-400">
                                    <thead
                                        class="text-sm text-body uppercase bg-surface-soft dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    <span>Riwayat Pemeriksaan Laboratorium Luar</span>
                                                    @if ($regNo && count($this->rows) > 0)
                                                        <span
                                                            class="px-2 py-0.5 text-sm bg-amber-100 rounded-full text-amber-700">
                                                            {{ count($this->rows) }} Pemeriksaan
                                                        </span>
                                                    @endif
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="bg-canvas dark:bg-gray-800">
                                        @forelse ($this->rows as $row)
                                            @php
                                                // Status ngikut hdr.checkup_status (P=Terdaftar, C=Proses, H=Selesai)
                                                $statusCode = $row->checkup_status ?? '';
                                                $isSelesai = $statusCode === 'H';
                                                $isProses = $statusCode === 'C';
                                                $isTerdaftar = $statusCode === 'P';

                                                $statusText = $isSelesai
                                                    ? 'Selesai'
                                                    : ($isProses
                                                        ? 'Menunggu Hasil'
                                                        : ($isTerdaftar
                                                            ? 'Terdaftar'
                                                            : '-'));
                                                $statusClass = $isSelesai
                                                    ? 'text-green-700 bg-green-100'
                                                    : ($isProses
                                                        ? 'text-amber-700 bg-amber-100'
                                                        : 'text-muted bg-surface-soft');
                                                $statusIcon = $isSelesai ? '✓' : ($isProses ? '⏳' : '📋');
                                                $hasPdf = !empty($row->pdf_path);

                                                // Layanan: RJ, UGD, RI
                                                $layanan = $row->status_rjri ?? '';
                                                $isRI = $layanan === 'RI';
                                                $isUGD = $layanan === 'UGD';
                                                $isRJ = $layanan === 'RJ';

                                                $layananIcon = $isRI ? '🏥' : ($isUGD ? '🚑' : '🔬');
                                                $layananClass = $isRI
                                                    ? 'text-purple-600'
                                                    : ($isUGD
                                                        ? 'text-red-600'
                                                        : 'text-teal-600');
                                                $layananText = $isRI
                                                    ? 'Rawat Inap'
                                                    : ($isUGD
                                                        ? 'UGD'
                                                        : ($isRJ
                                                            ? 'Rawat Jalan'
                                                            : '-'));
                                            @endphp

                                            <tr class="border-b group dark:border-gray-700">
                                                <td
                                                    class="px-4 py-4 text-ink transition-colors group-hover:bg-surface-soft dark:text-gray-100 dark:group-hover:bg-gray-750">

                                                    {{-- Header Row --}}
                                                    <div class="flex items-start justify-between gap-2 flex-wrap">
                                                        <div class="flex items-center space-x-2 min-w-0 flex-1">
                                                            <span class="text-2xl">{{ $layananIcon }}</span>
                                                            <div>
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        class="font-bold {{ $layananClass }}">{{ $layananText }}</span>
                                                                    <span class="text-muted-soft">|</span>
                                                                    <span
                                                                        class="font-medium">{{ $row->reg_name ?? '-' }}</span>
                                                                    <span
                                                                        class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClass }}">
                                                                        {{ $statusIcon }} {{ $statusText }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-right text-muted">
                                                            <div>{{ $row->checkup_date }}</div>
                                                        </div>
                                                    </div>

                                                    {{-- Daftar Item Pemeriksaan --}}
                                                    <div class="p-2 mt-3 rounded bg-surface-soft dark:bg-gray-700">
                                                        <div class="flex items-center mb-1.5 space-x-1">
                                                            <svg class="w-3 h-3 text-amber-600" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M9 17v-2a4 4 0 014-4h4M5 7h14M5 11h6m-6 4h6m-6 4h6" />
                                                            </svg>
                                                            <span
                                                                class="text-xs font-semibold text-muted dark:text-gray-300">Pemeriksaan:</span>
                                                        </div>

                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200">
                                                                {{ $row->labout_desc }}
                                                            </span>
                                                        </div>

                                                        @if ($row->labout_result)
                                                            <p class="mt-1.5 text-xs italic text-muted">
                                                                Catatan klinis: {{ $row->labout_result }}
                                                            </p>
                                                        @endif
                                                        @if ($row->keterangan)
                                                            <p class="mt-1 text-xs italic text-amber-700">
                                                                Keterangan: {{ $row->keterangan }}
                                                            </p>
                                                        @endif
                                                        @if ($row->labout_normal)
                                                            <p class="mt-1 text-xs italic text-muted">
                                                                Keterangan lab: {{ $row->labout_normal }}
                                                            </p>
                                                        @endif
                                                    </div>

                                                    {{-- Actions --}}
                                                    @if ($hasPdf)
                                                        <div class="flex items-center gap-2 mt-3">
                                                            <x-outline-button type="button"
                                                                wire:click="openViewPDF({{ json_encode($row->pdf_path) }})">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat Hasil PDF
                                                            </x-outline-button>
                                                        </div>
                                                    @endif

                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="px-4 py-8 text-center">
                                                    @if ($regNo)
                                                        <svg class="w-12 h-12 mx-auto text-gray-300" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 17v-2a4 4 0 014-4h4M5 7h14M5 11h6m-6 4h6m-6 4h6" />
                                                        </svg>
                                                        <p class="mt-2 text-muted">Tidak ada data laboratorium luar</p>
                                                    @else
                                                        <p class="text-muted">Silakan pilih pasien terlebih dahulu</p>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────────────
         MODAL: PDF Viewer (iframe pakai URL files.show, bukan base64)
    ────────────────────────────────────────────────────────────────────── --}}
    <x-modal name="view-lab-luar-pdf" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-lab-luar-{{ $viewFilePDF }}">
            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                    Hasil Laboratorium Luar
                </h2>
                <div class="flex items-center gap-2">
                    @if ($viewFilePDF)
                        <a href="{{ $viewFilePDF }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 text-xs font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Buka di Tab Baru
                        </a>
                    @endif
                    <x-icon-button color="gray" type="button" wire:click="closeViewPDF">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — iframe streaming via route files.show --}}
            <div class="flex-1 p-2 bg-surface-soft dark:bg-gray-900">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"
                        type="application/pdf"></iframe>
                @endif
            </div>
        </div>
    </x-modal>
</div>
