@props([
    'variant' => 'gray', // brand | alternative | gray | danger | success | warning | info
])

@php
    $variants = [
        // "brand" kita map ke emerald supaya tetap feel green
        'brand' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
        'alternative' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
        'success' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
        'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200',
    ];

    $classes = $variants[$variant] ?? $variants['gray'];
@endphp

<span
    {{ $attributes->merge([
        'class' => $classes . ' inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
    ]) }}>
    {{ $slot }}
</span>
