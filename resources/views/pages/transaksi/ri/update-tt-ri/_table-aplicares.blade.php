<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3 text-left">Kelas</th>
                <th class="px-4 py-3 text-center">Kode BPJS</th>
                <th class="px-4 py-3 text-center">Kapasitas</th>
                <th class="px-4 py-3 text-center">Terpakai</th>
                <th class="px-4 py-3 text-center">Tersedia</th>
                <th class="px-4 py-3 text-center">Occupancy</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($rows as $i => $row)
                @php
                    $occ      = $row['kapasitas'] > 0 ? round($row['terpakai'] / $row['kapasitas'] * 100) : 0;
                    $occColor = $occ >= 90 ? 'bg-red-500' : ($occ >= 70 ? 'bg-amber-400' : 'bg-emerald-500');
                @endphp
                <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">

                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-200">{{ $row['rs_namakelas'] }}</td>

                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            {{ $row['aplic_kodekelas'] }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-center font-mono font-semibold text-gray-700 dark:text-gray-300">
                        {{ $row['kapasitas'] }}
                    </td>

                    <td class="px-4 py-3 text-center font-mono font-semibold text-rose-600 dark:text-rose-400">
                        {{ $row['terpakai'] }}
                    </td>

                    <td class="px-4 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ $row['tersedia'] }}
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 min-w-[60px]">
                                <div class="{{ $occColor }} h-2 rounded-full transition-all" style="width: {{ $occ }}%"></div>
                            </div>
                            <span class="text-xs font-mono text-gray-500 dark:text-gray-400 w-8 text-right">{{ $occ }}%</span>
                        </div>
                    </td>

                    <td class="px-4 py-3 text-center">
                        @include('pages.transaksi.ri.update-tt-ri._status-badge', ['status' => $row['status_aplic'], 'pesan' => $row['pesan_aplic']])
                    </td>

                    <td class="px-4 py-3 text-center">
                        <button wire:click="syncAplicSatu({{ $i }})"
                                wire:loading.attr="disabled"
                                wire:target="syncAplicSatu({{ $i }})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                                       bg-blue-50 text-blue-700 hover:bg-blue-100
                                       dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50
                                       transition disabled:opacity-50">
                            <svg wire:loading wire:target="syncAplicSatu({{ $i }})" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="syncAplicSatu({{ $i }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Sync
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
