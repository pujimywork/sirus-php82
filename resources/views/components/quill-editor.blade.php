@props([
    // Nama Livewire property yang akan di-bind (required).
    'name',
    // Placeholder teks editor.
    'placeholder' => 'Tulis di sini…',
    // Tinggi minimum host editor (px, hanya angka).
    'minHeight' => 280,
    // Preset toolbar: 'default' (Word-style lengkap) atau 'minimal'.
    'toolbar' => 'default',
    // Bila editor berada di dalam modal: nama modal supaya editor di-init
    // saat modal dibuka (kalau diinit saat modal hidden, Quill gagal mengukur DOM).
    'modalEvent' => null,
    // Nama custom event untuk paksa flush isi editor ke $wire (mis. sebelum klik save).
    // Dispatch via window.dispatchEvent(new Event('<flushEvent>')).
    'flushEvent' => null,
])

@php
    if (empty($name)) {
        throw new \InvalidArgumentException('<x-quill-editor> wajib menerima prop "name" (nama Livewire property).');
    }
@endphp

<div
    wire:ignore
    x-data="quillEditor({
        propName: @js($name),
        placeholder: @js($placeholder),
        toolbar: @js($toolbar),
        modalEvent: @js($modalEvent),
        flushEvent: @js($flushEvent),
    })"
    {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-900 rounded-md']) }}
>
    <div x-ref="host" style="min-height: {{ (int) $minHeight }}px;"></div>
</div>
