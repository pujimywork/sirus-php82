@props([
    'disabled' => false,
    'error' => false,
])

@php
    $baseClass = 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100
        focus:border-brand-lime focus:ring-brand-lime
        rounded-md shadow-sm disabled:opacity-60 disabled:cursor-not-allowed';

    $errorClass = 'border-red-500 focus:border-red-500 focus:ring-red-500
        dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-400';
@endphp

<textarea @disabled($disabled)
    {{ $attributes->merge([
        'class' => $error ? "$baseClass $errorClass" : $baseClass,
    ]) }}>{{ $slot }}</textarea>
