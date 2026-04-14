<?php
// resources/views/pages/master/master-kamar/registrasi-aplicares-sirs/aplicares-actions.blade.php
//
// APLICARES (Aplikasi Komunikasi Antar Rumah Sakit)
// Sistem milik BPJS Kesehatan untuk mengelola data ketersediaan
// tempat tidur rumah sakit secara real-time.
// Komponen ini menampilkan data kamar yang sudah terdaftar di Aplicares.

use Livewire\Component;
use App\Http\Traits\BPJS\AplicaresTrait;

new class extends Component {
    use AplicaresTrait;

    /* ─── State ───────────────────────────────────────────────── */
    public bool   $loadingAplicares = false;
    public string $aplicaresError   = '';
    public array  $aplicaresData    = [];
    public int    $aplicTotal       = 0;

    /* ─── Ambil data ketersediaan dari Aplicares ──────────────── */
    public function loadAplicares(): void
    {
        $this->loadingAplicares = true;
        $this->aplicaresError   = '';

        try {
            $res  = $this->ketersediaanKamarRS(1, 100)->getOriginalContent();
            $body = $res['response'] ?? [];
            $this->aplicaresData = $body['list'] ?? ($body['data'] ?? []);
            $this->aplicTotal    = (int) ($body['total'] ?? count($this->aplicaresData));
        } catch (\Throwable $e) {
            $this->aplicaresError = $e->getMessage();
        }

        $this->loadingAplicares = false;
    }

    /* ─── Hapus ruangan dari Aplicares ───────────────────────── */
    public function hapusAplicares(string $kodekelas, string $koderuang): void
    {
        try {
            $res  = $this->hapusRuangan($kodekelas, $koderuang)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            if ($code == 1) {
                $this->dispatch('toast', type: 'success', message: "Ruangan {$koderuang} berhasil dihapus dari Aplicares.");
                $this->loadAplicares();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal hapus: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }
};
?>

<div class="flex flex-col h-full">

    {{-- Toolbar --}}
    <div class="flex items-center justify-end px-5 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
        <x-secondary-button wire:click="loadAplicares" wire:loading.attr="disabled" wire:target="loadAplicares">
            <x-loading size="xs" wire:loading wire:target="loadAplicares" class="mr-1" />
            <svg wire:loading.remove wire:target="loadAplicares" class="w-3 h-3 mr-1" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
            </svg>
            <span wire:loading.remove wire:target="loadAplicares">
                {{ empty($aplicaresData) ? 'Ambil Data Aplicares' : 'Perbarui Data' }}
            </span>
            <span wire:loading wire:target="loadAplicares">Mengambil data…</span>
        </x-secondary-button>
    </div>

    @if ($aplicaresError)
        <div class="px-5 py-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 shrink-0">
            {{ $aplicaresError }}
        </div>
    @else
        {{-- Loading state --}}
        <div wire:loading wire:target="loadAplicares"
            class="flex-1 flex flex-col items-center justify-center text-sm text-gray-400">
            <x-loading size="md" class="block mb-2" />
            Memuat data dari Aplicares…
        </div>

        {{-- Rekap Kelas & Kapasitas --}}
        @if (!empty($aplicaresData))
            @php
                $aplRekap = collect($aplicaresData)
                    ->groupBy(fn($r) => $r['kodekelas'] ?? ($r['kode_kelas'] ?? '-'))
                    ->map(fn($g) => [
                        'jumlah_ruang'    => $g->count(),
                        'total_kapasitas' => $g->sum('kapasitas'),
                        'total_tersedia'  => $g->sum('tersedia'),
                    ]);
                $aplTotalRuang     = collect($aplicaresData)->count();
                $aplTotalKapasitas = collect($aplicaresData)->sum('kapasitas');
                $aplTotalTersedia  = collect($aplicaresData)->sum('tersedia');
            @endphp
            <div wire:loading.remove wire:target="loadAplicares"
                class="px-5 py-2.5 border-b border-blue-100 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-900/10 shrink-0 flex flex-wrap gap-3 items-center">
                <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 self-center mr-1">Rekap per
                    Kelas:</span>
                @foreach ($aplRekap as $kode => $r)
                    <div class="flex items-center gap-1.5 bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-800 rounded-lg px-2.5 py-1 text-[11px]">
                        <span class="font-mono font-bold text-blue-700 dark:text-blue-300">{{ $kode }}</span>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $r['jumlah_ruang'] }} ruang</span>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <span class="text-gray-700 dark:text-gray-200">Kap: <span class="font-semibold">{{ $r['total_kapasitas'] }}</span></span>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <span class="text-emerald-600 dark:text-emerald-400">Tersedia: <span class="font-semibold">{{ $r['total_tersedia'] }}</span></span>
                    </div>
                @endforeach
                <div class="ml-auto flex items-center gap-1.5 bg-blue-600 dark:bg-blue-700 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold">
                    <span>Total:</span>
                    <span>{{ $aplTotalRuang }} ruang</span>
                    <span class="opacity-60">·</span>
                    <span>Kap: {{ $aplTotalKapasitas }}</span>
                    <span class="opacity-60">·</span>
                    <span>Tersedia: {{ $aplTotalTersedia }}</span>
                </div>
            </div>
        @endif

        {{-- Table --}}
        @php
            $aplicaresDataSorted = collect($aplicaresData)
                ->sortBy(fn($r) => $r['namaruang'] ?? ($r['nama_ruang'] ?? ''))
                ->values()
                ->all();
        @endphp
        <div wire:loading.remove wire:target="loadAplicares" class="flex-1 overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">Kode Ruang</th>
                        <th class="px-5 py-3 text-left font-semibold">Nama Ruang</th>
                        <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                        <th class="px-5 py-3 text-center font-semibold">Kapasitas</th>
                        <th class="px-5 py-3 text-center font-semibold">Tersedia</th>
                        <th class="px-5 py-3 text-center font-semibold">Pria</th>
                        <th class="px-5 py-3 text-center font-semibold">Wanita</th>
                        <th class="px-5 py-3 text-center font-semibold">Campuran</th>
                        <th class="px-5 py-3 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse ($aplicaresDataSorted as $aplic)
                        @php
                            $koderuang = $aplic['koderuang'] ?? ($aplic['kode_ruang'] ?? '');
                            $kodekelas = $aplic['kodekelas'] ?? ($aplic['kode_kelas'] ?? '');
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="px-5 py-3 font-mono font-semibold">{{ $koderuang ?: '-' }}</td>
                            <td class="px-5 py-3">{{ $aplic['namaruang'] ?? ($aplic['nama_ruang'] ?? '-') }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $kodekelas ?: '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-semibold">{{ $aplic['kapasitas'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ $aplic['tersedia'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediapria'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediapriawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <x-confirm-button variant="danger"
                                    :action="'hapusAplicares(\'' . $kodekelas . '\', \'' . $koderuang . '\')'"
                                    title="Hapus Aplicares"
                                    :message="'Hapus ruangan ' . $koderuang . ' (' . $kodekelas . ') dari Aplicares BPJS?'"
                                    confirmText="Ya, hapus" cancelText="Batal"
                                    class="text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus
                                </x-confirm-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9"
                                class="px-5 py-16 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                Belum ada data. Klik <strong>Ambil Data Aplicares</strong> untuk memuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (!empty($aplicaresData))
            <div wire:loading.remove wire:target="loadAplicares"
                class="px-5 py-2 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                {{ count($aplicaresData) }} data ruangan
            </div>
        @endif
    @endif

</div>
