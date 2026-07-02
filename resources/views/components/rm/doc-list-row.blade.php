@props([
    'id',
    'title',
    'date' => null,
    'sub' => null,
])

{{-- Baris entri dokumen di display Rekam Medis RI: tombol Lihat (buka modal) + Cetak (PDF). --}}
{{-- wire:click terikat ke komponen Livewire pembungkus (harus punya method lihat() & cetak()). --}}
<div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-base font-semibold text-ink dark:text-gray-200">{{ $title }}</span>
            @if (filled($date))
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-green/20 dark:text-brand-lime">{{ $date }}</span>
            @endif
        </div>
        @if (filled($sub))
            <div class="mt-0.5 text-sm text-muted">{{ $sub }}</div>
        @endif
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <x-secondary-button type="button" class="gap-1.5" wire:click="lihat('{{ $id }}')" wire:loading.attr="disabled">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Lihat
        </x-secondary-button>
        <x-secondary-button type="button" class="gap-1.5" wire:click="cetak('{{ $id }}')" wire:loading.attr="disabled">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Cetak
        </x-secondary-button>
    </div>
</div>
