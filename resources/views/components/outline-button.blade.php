@props([
    'type' => 'button',
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
        'text-brand-green border border-brand-green/30',
        'hover:bg-brand-green/10',
        'focus:outline-none focus:ring-2 focus:ring-brand-lime',
        'focus:ring-offset-2 focus:ring-offset-white',
    
        // dark mode - enabled
        'dark:text-brand-lime',
        'dark:border-brand-lime/30',
        'dark:hover:bg-brand-lime/10',
        'dark:focus:ring-brand-green',
        'dark:focus:ring-offset-[#0a0a0a]',
    
        // disabled state - light mode
        'disabled:opacity-50',
        'disabled:cursor-not-allowed',
        'disabled:border-gray-300',
        'disabled:text-gray-400',
        'disabled:hover:bg-transparent',
        'disabled:focus:ring-0',
        'disabled:focus:ring-offset-0',
    
        // disabled state - dark mode
        'dark:disabled:border-gray-600',
        'dark:disabled:text-gray-500',
        'dark:disabled:hover:bg-transparent',
    ]) }}>
    {{ $slot }}
</button>
