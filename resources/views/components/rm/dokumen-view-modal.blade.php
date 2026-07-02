@props([
    'name',
    'title',
    'subtitle' => null,
    'cetakId' => null,
    // showCetak null = otomatis (tampil bila cetakId terisi). Set eksplisit true/false
    // untuk dokumen objek-tunggal yg method cetak()-nya tak butuh id.
    'showCetak' => null,
    // HTML dokumen cetak yg sudah di-render (self-contained). Bila diisi, ditampilkan
    // dalam iframe → isi modal Lihat = persis tampilan cetak. Kalau null, pakai slot.
    'previewHtml' => null,
    // Navigasi antar-record (Prev/Next) — diisi komponen list; >1 → tombol muncul.
    'navTotal' => 0,
    'navPos' => 0,
])

{{-- Shell modal viewer read-only dokumen Rekam Medis RI. --}}
{{-- Tutup via Alpine (tanpa roundtrip). Cetak terikat method cetak() komponen pembungkus. --}}
<x-modal :name="$name" size="full" height="full" focusable>
    <div class="flex flex-col h-full">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-hairline dark:border-gray-700">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">{{ $title }}</h2>
                @if (filled($subtitle))
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: '{{ $name }}' })" class="!p-2 shrink-0">
                <span class="sr-only">Tutup</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </x-secondary-button>
        </div>

        {{-- Body --}}
        @if (filled($previewHtml))
            {{-- Preview dokumen cetak (iframe self-contained) — isi = persis tampilan Cetak PDF. --}}
            <div class="flex-1 min-h-0 p-3 bg-gray-100 dark:bg-gray-950/40">
                <iframe srcdoc="{{ $previewHtml }}" class="w-full h-full bg-white border border-hairline rounded-lg shadow-sm dark:border-gray-700"
                    title="Preview {{ $title }}"></iframe>
            </div>
        @else
            <div class="flex-1 min-h-0 px-6 py-4 overflow-y-auto text-base">
                <div class="max-w-4xl mx-auto">
                    {{ $slot }}
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-6 py-3 border-t border-hairline dark:border-gray-700">
            {{-- Navigasi antar-record (tanpa buka-tutup modal) --}}
            <div class="flex items-center gap-1.5">
                @if ($navTotal > 1)
                    <x-secondary-button type="button" wire:click="prevRecord" wire:loading.attr="disabled" :disabled="$navPos <= 1" class="gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        Sebelumnya
                    </x-secondary-button>
                    <span class="px-2 text-sm font-medium tabular-nums text-muted">{{ $navPos }} / {{ $navTotal }}</span>
                    <x-secondary-button type="button" wire:click="nextRecord" wire:loading.attr="disabled" :disabled="$navPos >= $navTotal" class="gap-1">
                        Berikutnya
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </x-secondary-button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: '{{ $name }}' })">Tutup</x-secondary-button>
                @if ($showCetak ?? filled($cetakId))
                <x-primary-button type="button" wire:click="cetak('{{ $cetakId }}')" wire:loading.attr="disabled" wire:target="cetak">
                    <span wire:loading.remove wire:target="cetak" class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Cetak PDF
                    </span>
                    <span wire:loading wire:target="cetak" class="inline-flex items-center gap-1.5"><x-loading /> Memuat...</span>
                </x-primary-button>
                @endif
            </div>
        </div>
    </div>
</x-modal>
