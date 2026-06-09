@props([
    'value' => null,
    'required' => false,
])

<label
    {{ $attributes->merge([
        'class' => 'block text-sm font-medium text-gray-900 dark:text-white',
    ]) }}>
    {{ $value ?? $slot }}@if ($required)<span class="text-red-500" title="Wajib diisi"> *</span>@endif
</label>
