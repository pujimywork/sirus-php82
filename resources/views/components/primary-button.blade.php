@props([
    'type' => 'submit',
    'disabled' => false,
])

<button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->class([
        // layout
        'inline-flex items-center justify-center',
        'px-6 py-2.5 rounded-xl',
        'text-sm font-semibold tracking-wide',
        'transition ease-in-out duration-150',

        // light mode - enabled
        'text-white bg-brand-green',
        'hover:bg-brand-green/90',
        'active:bg-brand-green',
        'focus:outline-none focus:ring-2 focus:ring-brand-lime',
        'focus:ring-offset-2 focus:ring-offset-white',

        // dark mode - enabled
        'dark:text-slate-900',
        'dark:bg-brand-lime',
        'dark:hover:bg-brand-lime/90',
        'dark:active:bg-brand-lime',
        'dark:focus:ring-brand-green',
        'dark:focus:ring-offset-[#0a0a0a]',

        // disabled state - light mode
        'disabled:opacity-50',
        'disabled:cursor-not-allowed',
        'disabled:bg-gray-400',
        'disabled:hover:bg-gray-400',
        'disabled:active:bg-gray-400',
        'disabled:focus:ring-0',
        'disabled:focus:ring-offset-0',

        // disabled state - dark mode
        'dark:disabled:bg-gray-600',
        'dark:disabled:hover:bg-gray-600',
        'dark:disabled:active:bg-gray-600',
        'dark:disabled:text-gray-300',
    ]) }}>
    {{ $slot }}
</button>
