@props([
    'disabled' => false,
    'error' => false,
])

@php
    $baseClass = 'bg-gray-50 border-gray-300 text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100
        focus:border-brand-lime focus:ring-brand-lime
        rounded-lg shadow-sm disabled:opacity-90 disabled:bg-gray-100 disabled:cursor-not-allowed w-full';

    $errorClass = 'border-red-500 focus:border-red-500 focus:ring-red-500
        dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-400';
@endphp

<input @disabled($disabled)
    {{ $attributes->merge([
        'class' => $error ? "$baseClass $errorClass" : $baseClass,
    ]) }}>
