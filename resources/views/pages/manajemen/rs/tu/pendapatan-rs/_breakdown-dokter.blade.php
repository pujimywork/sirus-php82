{{--
    Breakdown total pasien & pendapatan per dokter.
    Props:
      $dokterRj      array of [dr_id, dr_name, poli_desc, jumlah, bpjs, umum, total]
      $dokterUgd     array of [dr_id, dr_name, jumlah, bpjs, umum, total]
      $dokterRi      array of [dr_id, dr_name, jumlah, bpjs, umum, total]  (dokter utama dari leveling)
      $periodeLabel  string label periode (mis. "2026" atau "2024–2026")
--}}
<div class="mt-6 space-y-4">

    {{-- RJ — per dokter × poli --}}
    <div class="bg-canvas border border-emerald-200 rounded-2xl dark:border-emerald-800 dark:bg-gray-900"
         x-data="{ open: true }">
        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
            <div class="flex-1">
                <div class="text-sm font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wide">
                    Breakdown RJ &mdash; per Dokter &times; Poli ({{ $periodeLabel }})
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    {{ count($dokterRj) }} kombinasi · status final <code>rj_status='L'</code>
                </div>
            </div>
            <svg class="w-4 h-4 text-muted-soft transition-transform" :class="open ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="overflow-auto border-t border-emerald-200 dark:border-emerald-800">
            <table class="w-full text-xs text-left text-body dark:text-gray-300 table-auto">
                <thead class="text-[10px] text-emerald-900 uppercase bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-200">
                    <tr>
                        <th class="px-3 py-2 text-left">Dokter</th>
                        <th class="px-3 py-2 text-left">Poli</th>
                        <th class="px-3 py-2 text-right">Pasien</th>
                        <th class="px-3 py-2 text-right">BPJS</th>
                        <th class="px-3 py-2 text-right">UMUM</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dokterRj as $row)
                        <tr class="border-t border-hairline dark:border-gray-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10">
                            <td class="px-3 py-1.5">{{ $row['dr_name'] }} <span class="text-muted-soft text-[10px]">({{ $row['dr_id'] }})</span></td>
                            <td class="px-3 py-1.5">{{ $row['poli_desc'] }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-muted">{{ number_format($row['umum'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-3 text-center text-muted italic">Tidak ada data RJ pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- UGD — per dokter --}}
    <div class="bg-canvas border border-rose-200 rounded-2xl dark:border-rose-800 dark:bg-gray-900"
         x-data="{ open: true }">
        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-rose-50 dark:hover:bg-rose-900/20">
            <div class="flex-1">
                <div class="text-sm font-bold text-rose-800 dark:text-rose-200 uppercase tracking-wide">
                    Breakdown UGD &mdash; per Dokter UGD ({{ $periodeLabel }})
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    {{ count($dokterUgd) }} dokter · status final <code>rj_status='L'</code>
                </div>
            </div>
            <svg class="w-4 h-4 text-muted-soft transition-transform" :class="open ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="overflow-auto border-t border-rose-200 dark:border-rose-800">
            <table class="w-full text-xs text-left text-body dark:text-gray-300 table-auto">
                <thead class="text-[10px] text-rose-900 uppercase bg-rose-100 dark:bg-rose-900/30 dark:text-rose-200">
                    <tr>
                        <th class="px-3 py-2 text-left">Dokter</th>
                        <th class="px-3 py-2 text-right">Pasien</th>
                        <th class="px-3 py-2 text-right">BPJS</th>
                        <th class="px-3 py-2 text-right">UMUM</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dokterUgd as $row)
                        <tr class="border-t border-hairline dark:border-gray-700 hover:bg-rose-50/50 dark:hover:bg-rose-900/10">
                            <td class="px-3 py-1.5">{{ $row['dr_name'] }} <span class="text-muted-soft text-[10px]">({{ $row['dr_id'] }})</span></td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-muted">{{ number_format($row['umum'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-3 text-center text-muted italic">Tidak ada data UGD pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- RI — per dokter utama (dari pengkajianAwalPasienRawatInap.levelingDokter where levelDokter='Utama') --}}
    <div class="bg-canvas border border-blue-200 rounded-2xl dark:border-blue-800 dark:bg-gray-900"
         x-data="{ open: true }">
        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-blue-50 dark:hover:bg-blue-900/20">
            <div class="flex-1">
                <div class="text-sm font-bold text-blue-800 dark:text-blue-200 uppercase tracking-wide">
                    Breakdown RI &mdash; per Dokter Utama / DPJP ({{ $periodeLabel }})
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    {{ count($dokterRi) }} dokter · sumber: <code>pengkajianAwalPasienRawatInap.levelingDokter</code> · fallback "(Tidak Ada Kategori)" kalau leveling kosong
                </div>
            </div>
            <svg class="w-4 h-4 text-muted-soft transition-transform" :class="open ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="overflow-auto border-t border-blue-200 dark:border-blue-800">
            <table class="w-full text-xs text-left text-body dark:text-gray-300 table-auto">
                <thead class="text-[10px] text-blue-900 uppercase bg-blue-100 dark:bg-blue-900/30 dark:text-blue-200">
                    <tr>
                        <th class="px-3 py-2 text-left">Dokter Utama</th>
                        <th class="px-3 py-2 text-right">Pasien</th>
                        <th class="px-3 py-2 text-right">BPJS</th>
                        <th class="px-3 py-2 text-right">UMUM</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dokterRi as $row)
                        <tr class="border-t border-hairline dark:border-gray-700 hover:bg-blue-50/50 dark:hover:bg-blue-900/10
                            @if ($row['dr_id'] === '__NONE__') bg-amber-50/40 dark:bg-amber-900/10 @endif">
                            <td class="px-3 py-1.5">
                                {{ $row['dr_name'] }}
                                @if ($row['dr_id'] !== '__NONE__')
                                    <span class="text-muted-soft text-[10px]">({{ $row['dr_id'] }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-muted">{{ number_format($row['umum'], 0, ',', '.') }}</td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-3 text-center text-muted italic">Tidak ada data RI pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
