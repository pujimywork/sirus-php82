@props([
'title' => '',
'align' => 'start',
'bgcolor' => 'bg-white',
'class' => '',
'titleClass' => '',
'padding' => 'p-4',
'collapsible' => false,
'open' => true,
])

@php
// Set alignment class
$alignClass = match($align) {
'center' => 'text-center',
'end' => 'text-right',
'right' => 'text-right',
default => 'text-left',
};

// Set padding for content based on title existence
$contentPadding = $title ? 'pt-3' : 'pt-4';
@endphp

<div {{ $attributes->merge(['class' => "border border-gray-200 rounded-2xl shadow-sm dark:border-gray-700 {$bgcolor} dark:bg-gray-900 {$class}"]) }}
    @if($collapsible) x-data="{ open: @js($open) }" @endif>
    @if($title)
        @if($collapsible)
            <button type="button" @click="open = !open"
                class="w-full px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 transition-colors hover:bg-gray-100 dark:hover:bg-gray-700/70 flex items-center justify-between gap-2"
                :class="open ? '' : 'border-b-transparent'">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 {{ $alignClass }} {{ $titleClass }}">
                    {{ $title }}
                </h3>
                <svg class="w-4 h-4 text-gray-500 transition-transform shrink-0" :class="open ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        @else
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 {{ $alignClass }} {{ $titleClass }}">
                    {{ $title }}
                </h3>
            </div>
        @endif
    @endif

    <div class="{{ $padding }} {{ !$title ? $contentPadding : '' }}"
        @if($collapsible) x-show="open" x-collapse @endif>
        {{ $slot }}
    </div>
</div>
