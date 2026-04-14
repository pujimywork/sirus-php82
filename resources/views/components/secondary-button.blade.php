@props([
    'type' => 'button',
    'disabled' => false,
])

<button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->class([
        // layout — Flowbite alternative: same size as primary
        'inline-flex items-center justify-center gap-2',
        'px-5 py-2.5 rounded-lg',
        'text-sm font-medium',
        'transition-colors duration-150',
    
        // light mode — light gray bg, gray border
        'text-gray-700 bg-gray-100',
        'border border-gray-300',
        'hover:bg-gray-200 hover:text-gray-900',
        'focus:outline-none focus:ring-4 focus:ring-gray-200',
    
        // dark mode
        'dark:bg-gray-800 dark:text-gray-200',
        'dark:border-gray-600',
        'dark:hover:bg-gray-700 dark:hover:text-white',
        'dark:focus:ring-gray-700',
    
        // disabled
        'disabled:opacity-50',
        'disabled:cursor-not-allowed',
        'disabled:pointer-events-none',
    ]) }}>
    {{ $slot }}
</button>
