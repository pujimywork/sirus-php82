<?php
// resources/views/pages/master/master-kamar/registrasi-aplicares-sirs/sirs-actions.blade.php
//
// SIRS (Sistem Informasi Rumah Sakit) Kemenkes
// Sistem milik Kementerian Kesehatan RI untuk pelaporan data
// tempat tidur rumah sakit. Komponen ini menampilkan data kamar
// yang sudah terdaftar di SIRS beserta status tipe tempat tidur.

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {
    use SirsTrait;

    /* ─── State ───────────────────────────────────────────────── */
    public bool   $loadingSirs = false;
    public string $sirsError   = '';
    public array  $sirsData    = [];

    /* ─── Ambil data tempat tidur dari SIRS ───────────────────── */
    public function loadSirs(): void
    {
        $this->loadingSirs = true;
        $this->sirsError   = '';

        try {
            $res  = $this->sirsGetTempaTidur()->getOriginalContent();
            $list = $res['fasyankes'] ?? ($res['response'] ?? ($res['data'] ?? []));
            $this->sirsData = is_array($list) ? array_values($list) : [];
        } catch (\Throwable $e) {
            $this->sirsError = $e->getMessage();
        }

        $this->loadingSirs = false;
    }

    /* ─── Hapus data TT dari SIRS ─────────────────────────────── */
    public function hapusSirs(string $idTTt): void
    {
        try {
            $res    = $this->sirsHapusTempaTidur($idTTt)->getOriginalContent();
            $first  = $res['fasyankes'][0] ?? [];
            $status = (string) ($first['status'] ?? '500');
            $msg    = $first['message'] ?? '-';

            if ($status === '200') {
                DB::table('rsmst_rooms')
                    ->where('sirs_id_t_tt', $idTTt)
                    ->update(['sirs_id_t_tt' => null]);

                $this->dispatch('toast', type: 'success', message: $msg ?: "Data TT {$idTTt} berhasil dihapus dari SIRS.");
                $this->loadSirs();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal hapus SIRS: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error SIRS: ' . $e->getMessage());
        }
    }
};
?>

<div class="flex flex-col h-full">

    {{-- Toolbar --}}
    <div class="flex items-center justify-end px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
        <x-secondary-button wire:click="loadSirs" wire:loading.attr="disabled" wire:target="loadSirs,hapusSirs">
            <x-loading size="xs" wire:loading wire:target="loadSirs,hapusSirs" class="mr-1" />
            <svg wire:loading.remove wire:target="loadSirs,hapusSirs" class="w-3 h-3 mr-1" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
            </svg>
            <span wire:loading.remove wire:target="loadSirs,hapusSirs">
                {{ empty($sirsData) ? 'Ambil Data SIRS' : 'Perbarui Data' }}
            </span>
            <span wire:loading wire:target="loadSirs,hapusSirs">Mengambil data…</span>
        </x-secondary-button>
    </div>

    @if ($sirsError)
        <div class="px-5 py-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 shrink-0">
            {{ $sirsError }}
        </div>
    @else
        {{-- Loading state --}}
        <div wire:loading wire:target="loadSirs,hapusSirs"
            class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
            <x-loading size="md" class="block mb-2" />
            Memuat data dari SIRS Kemenkes…
        </div>

        {{-- Rekap Total --}}
        @if (!empty($sirsData))
            @php
                $sirsTotalRuang    = collect($sirsData)->sum('jumlah_ruang');
                $sirsTotalJumlah   = collect($sirsData)->sum('jumlah');
                $sirsTotalKosong   = collect($sirsData)->sum('kosong');
                $sirsTotalTerpakai = collect($sirsData)->sum('terpakai');
            @endphp
            <div wire:loading.remove wire:target="loadSirs,hapusSirs"
                class="px-5 py-2.5 border-b border-green-100 dark:border-green-900/40 bg-green-50/60 dark:bg-green-900/10 shrink-0 flex items-center justify-end">
                <div class="flex items-center gap-1.5 bg-green-600 dark:bg-green-700 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold">
                    <span>Total:</span>
                    <span>{{ $sirsTotalRuang }} ruang</span>
                    <span class="opacity-60">·</span>
                    <span>Jml: {{ $sirsTotalJumlah }}</span>
                    <span class="opacity-60">·</span>
                    <span>Kosong: {{ $sirsTotalKosong }}</span>
                    <span class="opacity-60">·</span>
                    <span>Pakai: {{ $sirsTotalTerpakai }}</span>
                </div>
            </div>
        @endif

        {{-- Table --}}
        @php
            $sirsDataSorted = collect($sirsData)->sortBy('ruang')->values()->all();
        @endphp
        <div wire:loading.remove wire:target="loadSirs,hapusSirs" class="flex-1 overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tipe TT</th>
                        <th class="px-4 py-3 text-left font-semibold">Ruang</th>
                        <th class="px-4 py-3 text-center font-semibold">Jml Ruang</th>
                        <th class="px-4 py-3 text-center font-semibold">Jumlah</th>
                        <th class="px-4 py-3 text-center font-semibold">Kosong</th>
                        <th class="px-4 py-3 text-center font-semibold">Terpakai</th>
                        <th class="px-4 py-3 text-center font-semibold">COVID</th>
                        <th class="px-4 py-3 text-left font-semibold">Update</th>
                        <th class="px-4 py-3 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse ($sirsDataSorted as $sirs)
                        @php
                            $idTTt    = (string) ($sirs['id_t_tt'] ?? '');
                            $kosong   = (int) ($sirs['kosong'] ?? 0);
                            $terpakai = (int) ($sirs['terpakai'] ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">

                            {{-- Tipe TT --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold
                                                 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                        {{ $sirs['id_tt'] ?? '-' }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[120px]"
                                        title="{{ $sirs['tt'] ?? '' }}">
                                        {{ $sirs['tt'] ?? '-' }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 font-medium">{{ $sirs['ruang'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $sirs['jumlah_ruang'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-center font-mono font-semibold">{{ $sirs['jumlah'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-center font-mono font-semibold
                                       {{ $kosong > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $kosong }}</td>
                            <td class="px-4 py-3 text-center font-mono font-semibold
                                       {{ $terpakai > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $terpakai }}</td>
                            <td class="px-4 py-3 text-center">
                                @if (!empty($sirs['covid']))
                                    <x-badge variant="danger">COVID</x-badge>
                                @else
                                    <x-badge variant="gray">Non</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                {{ $sirs['tglupdate'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($idTTt !== '')
                                    <x-ghost-button wire:click="hapusSirs('{{ $idTTt }}')"
                                        wire:confirm="Hapus data TT {{ $idTTt }} dari SIRS Kemenkes?"
                                        wire:loading.attr="disabled" wire:target="hapusSirs('{{ $idTTt }}')"
                                        class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-900/20 !px-3 !py-1.5 !text-xs">
                                        <x-loading size="xs" wire:loading
                                            wire:target="hapusSirs('{{ $idTTt }}')" />
                                        <svg wire:loading.remove wire:target="hapusSirs('{{ $idTTt }}')"
                                            class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Hapus
                                    </x-ghost-button>
                                @else
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 italic">Belum terdaftar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9"
                                class="px-5 py-16 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                Belum ada data. Klik <strong>Ambil Data SIRS</strong> untuk memuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (!empty($sirsData))
            <div class="px-5 py-2 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                {{ count($sirsData) }} data tempat tidur
            </div>
        @endif
    @endif

</div>
