@props([
    'ttd' => '',
    'date' => '',
    'code' => '',
    'locked' => false,
    'sign' => 'ttdSaya',
    'clear' => 'hapusTtd',
    'allowClear' => true,
    // framed hanya mempengaruhi alignment (dipakai oleh wrapper ttd-petugas).
    'framed' => true,
    'label' => '',
    'signLabel' => 'TTD Saya',
    'clearLabel' => 'Ganti / Hapus TTD',
    'emptyText' => 'Belum ditandatangani.',
])

<div class="{{ $framed ? 'max-w-sm mx-auto' : 'flex flex-col' }}">
    @if ($label)
        <div class="mb-2 text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400 {{ $framed ? 'text-center' : 'text-left' }}">
            {{ $label }}
        </div>
    @endif
    @if (empty($ttd))
        @unless ($locked)
            <div class="flex items-center justify-center p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700 {{ $framed ? '' : 'flex-1' }}">
                <x-primary-button type="button" wire:click="{{ $sign }}" wire:loading.attr="disabled" wire:target="{{ $sign }}" class="gap-2">
                    <span wire:loading.remove wire:target="{{ $sign }}" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                        </svg>
                        {{ $signLabel }}
                    </span>
                    <span wire:loading wire:target="{{ $sign }}">Menyimpan...</span>
                </x-primary-button>
            </div>
        @else
            <p class="py-8 text-sm italic text-center text-muted-soft">{{ $emptyText }}</p>
        @endunless
    @else
        <div class="flex flex-col items-start p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700 {{ $framed ? '' : 'flex-1' }}">
            <div class="font-semibold text-ink dark:text-gray-200">{{ $ttd }}</div>
            @if (!empty($code))
                <div class="text-sm text-muted mt-0.5">Kode: {{ $code }}</div>
            @endif
            <div class="mt-1 text-sm text-muted">{{ $date ?: '-' }}</div>
            @unless ($locked || !$allowClear)
                <x-secondary-button type="button" wire:click="{{ $clear }}" wire:loading.attr="disabled" wire:target="{{ $clear }}" class="gap-1 mt-3 text-xs">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    {{ $clearLabel }}
                </x-secondary-button>
            @endunless
        </div>
    @endif
</div>
