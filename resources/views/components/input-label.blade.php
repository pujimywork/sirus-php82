@props([
    'value' => null,
    'required' => false,
])

<label
    {{ $attributes->merge([
        'class' => 'block text-sm font-medium text-gray-700 dark:text-gray-300',
    ]) }}>
    {{ $value ?? $slot }}@if ($required)<span class="text-red-500" title="Wajib diisi"> *</span>@endif
</label>
