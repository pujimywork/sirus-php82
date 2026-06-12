@props([
    'disabled' => false,
    'error' => false,
])

@php
    // v2: fokus ring brand (green) di terang, lime di gelap
    $baseClass = 'bg-gray-50 border-gray-300 text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100
        focus:border-brand-green focus:ring-brand-green/40
        dark:focus:border-brand-lime dark:focus:ring-brand-lime/40
        rounded-lg shadow-sm disabled:opacity-90 disabled:bg-gray-100 disabled:cursor-not-allowed w-full';

    $errorClass = 'border-error focus:border-error focus:ring-error/40
        dark:border-error dark:focus:border-error dark:focus:ring-error/40';
@endphp

<input @disabled($disabled)
    {{ $attributes->merge([
        'class' => $error ? "$baseClass $errorClass" : $baseClass,
    ]) }}>
