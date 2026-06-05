@props([
    'disabled' => false,
])

{{-- Tanpa text-sm bawaan — ukuran font ikut x-text-input (text-base) supaya
     TINGGI select = tinggi text input saat bersanding di toolbar/form.
     Pemakai yang butuh font kecil tetap bisa pass class="text-sm". --}}
<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge([
    'class' => '
            block w-full rounded-md border-gray-300 shadow-sm
            focus:border-brand-lime focus:ring-brand-lime
            disabled:opacity-90 disabled:bg-gray-100 disabled:cursor-not-allowed

            dark:border-gray-700
            dark:bg-gray-900
            dark:text-gray-100
            dark:focus:border-brand-lime dark:focus:ring-brand-lime
        ',
]) !!}>
    {{ $slot }}
</select>
