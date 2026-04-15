@props([
'title' => '',
'align' => 'start',
'bgcolor' => 'bg-white',
'class' => '',
'titleClass' => '',
'padding' => 'p-4',
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

<div {{ $attributes->merge(['class' => "border border-gray-200 rounded-2xl shadow-sm dark:border-gray-700 {$bgcolor} dark:bg-gray-900 {$class}"]) }}>
    @if($title)
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 {{ $alignClass }} {{ $titleClass }}">
            {{ $title }}
        </h3>
    </div>
    @endif

    <div class="{{ $padding }} {{ !$title ? $contentPadding : '' }}">
        {{ $slot }}
    </div>
</div>