@props([
    'pos' => 0,
    'total' => 0,
])

{{-- Navigasi antar-kunjungan (Prev/Next) di footer modal RM. --}}
{{-- wire:click terikat komponen pembungkus (harus punya navPrev()/navNext()). --}}
@if ($total > 1)
    {{-- Daftar urut DESC (terbaru di atas): tombol dibalik letaknya biar persepsinya --}}
    {{-- sama — "Berikutnya" (lebih baru) di kiri, "Sebelumnya" (lebih lama) di kanan. --}}
    <div class="flex items-center gap-2">
        <x-ghost-button type="button" wire:click="navNext" wire:loading.attr="disabled" :disabled="$pos <= 1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Berikutnya
        </x-ghost-button>
        <span class="px-1 text-sm font-semibold tabular-nums text-brand-green dark:text-brand-lime">{{ $pos }} / {{ $total }}</span>
        <x-ghost-button type="button" wire:click="navPrev" wire:loading.attr="disabled" :disabled="$pos >= $total">
            Sebelumnya
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </x-ghost-button>
    </div>
@else
    <div></div>
@endif
