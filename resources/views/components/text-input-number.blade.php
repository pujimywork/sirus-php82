{{-- resources/views/components/text-input-number.blade.php --}}
{{--
    Input numerik dengan auto-format 999,999,999.
    Pakai wire:model seperti biasa — komponen otomatis handle konversi.

    Pemakaian:
      <x-text-input-number
          wire:model="basicSalary"
          :disabled="$isFormLocked"
          :error="$errors->has('basicSalary')"
          x-ref="inputBasicSalary"
          x-on:keydown.enter.prevent="$refs.inputRsAdmin?.focus()" />

    Catatan:
      - Nilai yang dikirim ke PHP selalu integer bersih (tanpa koma)
      - wire:model.live TIDAK dipakai — sync dilakukan via $wire.set() saat blur
      - Initial value diambil otomatis dari $modelValue
--}}

@props([
    'disabled' => false,
    'error' => false,
    'extraBlur' => null,
])

@php
    // Ambil nama model dari wire:model (bisa 'basicSalary' atau 'data.nested.field')
    $wireModel = $attributes->whereStartsWith('wire:model')->first();

    // Ambil nilai awal dari komponen parent via $__livewire jika tersedia
    // Fallback ke value attribute jika ada
    $modelValue = null;
    if ($wireModel && isset($__livewire)) {
        try {
            $modelValue = data_get($__livewire, $wireModel);
        } catch (\Throwable) {
        }
    }
    $modelValue ??= $attributes->get('value');

    $initialValue = $modelValue ? number_format((int) $modelValue, 0, '.', ',') : '';

    // Strip wire:model dan value dari attributes — kita handle sendiri
    $attrs = $attributes->whereDoesntStartWith('wire:model')->whereDoesntStartWith('value');

    $baseClass = 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100
        focus:border-brand-lime focus:ring-brand-lime
        rounded-md shadow-sm disabled:opacity-60 disabled:cursor-not-allowed w-full tabular-nums text-right';
    $errorClass = 'border-red-500 focus:border-red-500 focus:ring-red-500
        dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-400';
@endphp

<input @disabled($disabled) value="{{ $initialValue }}" inputmode="numeric"
    @if ($wireModel)
        x-init="$wire.$watch('{{ $wireModel }}', (val) => {
            if (document.activeElement === $el) return;
            let raw = parseInt(val) || 0;
            $el.value = raw > 0 ? new Intl.NumberFormat('en-US').format(raw) : '';
        })"
    @endif
    x-on:focus="$el.value = $el.value.replace(/,/g, '')"
    x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
    x-on:blur="
        let raw = parseInt($el.value.replace(/,/g, '')) || 0;
        @if ($wireModel) $wire.set('{{ $wireModel }}', raw); @endif
        @if ($extraBlur) {!! $extraBlur !!}; @endif
        $el.value = raw > 0 ? new Intl.NumberFormat('en-US').format(raw) : '';
    "
    {{ $attrs->merge([
        'class' => $error ? "$baseClass $errorClass" : $baseClass,
    ]) }}>
