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
        'text-slate-700 bg-slate-200',
        'hover:bg-slate-300 hover:text-slate-900',
        'focus:outline-none focus:ring-2 focus:ring-slate-300',
        'focus:ring-offset-2 focus:ring-offset-white',
    
        // dark mode - enabled
        'dark:text-slate-200',
        'dark:bg-white/10',
        'dark:hover:bg-white/20',
        'dark:focus:ring-white/20',
        'dark:focus:ring-offset-[#0a0a0a]',
    
        // disabled state - light mode
        'disabled:opacity-50',
        'disabled:cursor-not-allowed',
        'disabled:bg-slate-100',
        'disabled:text-slate-400',
        'disabled:hover:bg-slate-100',
        'disabled:hover:text-slate-400',
        'disabled:focus:ring-0',
        'disabled:focus:ring-offset-0',
    
        // disabled state - dark mode
        'dark:disabled:bg-white/5',
        'dark:disabled:text-slate-400',
        'dark:disabled:hover:bg-white/5',
        'dark:disabled:hover:text-slate-400',
    ]) }}>
    {{ $slot }}
</button>
