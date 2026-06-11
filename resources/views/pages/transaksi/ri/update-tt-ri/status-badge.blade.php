@if ($status === 'ok')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        Berhasil
    </span>
@elseif ($status === 'error')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
          title="{{ $pesan ?? '' }}">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
        Gagal
    </span>
@elseif ($status === 'warning')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
          title="{{ $pesan ?? '' }}">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        Peringatan
    </span>
@elseif ($status === 'skip')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-surface-soft text-muted dark:bg-gray-700 dark:text-gray-400"
          title="{{ $pesan ?? '' }}">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        Dilewati
    </span>
@elseif ($status === 'loading')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        Proses…
    </span>
@else
    <span class="text-xs text-muted-soft dark:text-gray-500">—</span>
@endif
