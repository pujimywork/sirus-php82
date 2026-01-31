@props([
    'variant' => 'danger', // danger|primary|secondary|outline
    'action', // contoh: "delete(10)" atau "delete('10')"
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'confirmText' => 'Ya',
    'cancelText' => 'Batal',
    'disabled' => false,
])

@php
    // id unik supaya aman dipakai berulang di table
    $confirmId = 'confirm_' . md5($action . '|' . ($attributes->get('wire:key') ?? '') . '|' . uniqid('', true));

    // class trigger button (fallback kalau tidak pakai x-danger-button dll)
    $triggerButtonClass = match ($variant) {
        'primary'
            => 'inline-flex items-center px-4 py-2 bg-brand-lime text-white rounded-lg font-semibold hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand-lime/40',
        'secondary'
            => 'inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-lg font-semibold hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600',
        'outline'
            => 'inline-flex items-center px-4 py-2 border border-gray-300 text-gray-800 rounded-lg font-semibold hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700',
        default
            => 'inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/40',
    };
@endphp

<div x-data="{
    show: false,
    open() {
        this.show = true;
        document.body.classList.add('overflow-hidden');
        this.$nextTick(() => {
            // fokus ke tombol confirm biar enak keyboard
            this.$refs.confirmButton?.focus();
        });
    },
    close() {
        this.show = false;
        document.body.classList.remove('overflow-hidden');
    },
    confirm() {
        this.close();
        $wire.{{ $action }};
    }
}" x-on:keydown.escape.window="if (show) close()" class="inline-block">
    {{-- Trigger --}}
    <button type="button" @disabled($disabled) x-on:click="open()"
        {{ $attributes->merge(['class' => $triggerButtonClass . ' disabled:opacity-60 disabled:cursor-not-allowed']) }}>
        {{ $slot }}
    </button>

    {{-- Modal Confirm (transisi DISAMAIN dengan <x-modal>) --}}
    <div x-cloak x-show="show" class="fixed inset-0 z-[90]" aria-labelledby="{{ $confirmId }}_title" role="dialog"
        aria-modal="true">
        {{-- Overlay (SAMA seperti modal) --}}
        <div class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70" x-on:click="close()"
            x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-120"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        {{-- Panel --}}
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md overflow-hidden bg-white border border-gray-200 shadow-2xl rounded-2xl dark:bg-gray-800 dark:border-gray-700"
                x-on:click.stop x-transition:enter="ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-120"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 sm:scale-95">
                {{-- Header --}}
                <div class="flex items-start justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 id="{{ $confirmId }}_title"
                            class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ $title }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $message }}
                        </p>
                    </div>

                    <button type="button"
                        class="inline-flex items-center justify-center text-gray-500 rounded-lg w-9 h-9 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        x-on:click="close()" aria-label="Tutup">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-2 px-5 py-4 bg-gray-50/70 dark:bg-gray-900/20">
                    <x-secondary-button type="button" x-on:click="close()">
                        {{ $cancelText }}
                    </x-secondary-button>

                    <x-danger-button type="button" x-ref="confirmButton" x-on:click="confirm()">
                        {{ $confirmText }}
                    </x-danger-button>
                </div>
            </div>
        </div>
    </div>
</div>
