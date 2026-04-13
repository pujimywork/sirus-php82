{{-- resources/views/pages/master/master-kamar/registrasi-aplicares-sirs/bulk-results.blade.php --}}
{{-- Reusable bulk result table. Expects: $rows = [['room_id', 'namaRuang', 'ok', 'msg'], ...] --}}
<table class="min-w-full text-sm">
    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
            <th class="px-5 py-3 text-left font-semibold">Room ID</th>
            <th class="px-5 py-3 text-left font-semibold">Nama Ruang</th>
            <th class="px-5 py-3 text-center font-semibold">Status</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
        @foreach ($rows as $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ $row['room_id'] }}
                </td>
                <td class="px-5 py-3 text-sm">{{ $row['namaRuang'] }}</td>
                <td class="px-5 py-3 text-center">
                    @if ($row['ok'] === true)
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $row['msg'] }}
                        </span>
                    @elseif ($row['ok'] === false)
                        <span class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400"
                              title="{{ $row['msg'] }}">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span class="truncate max-w-[220px]">{{ $row['msg'] }}</span>
                        </span>
                    @else
                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">{{ $row['msg'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
