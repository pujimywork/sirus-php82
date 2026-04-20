<?php
// resources/views/pages/master/master-kamar/registrasi-aplicares-sirs/aplicares-actions.blade.php
//
// APLICARES (Aplikasi Komunikasi Antar Rumah Sakit)
// Sistem milik BPJS Kesehatan untuk mengelola data ketersediaan
// tempat tidur rumah sakit secara real-time.
// Komponen ini menampilkan data kamar yang sudah terdaftar di Aplicares.

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;

new class extends Component {
    use AplicaresTrait;

    /* ─── State ───────────────────────────────────────────────── */
    public bool   $loadingAplicares = false;
    public string $aplicaresError   = '';
    public array  $aplicaresData    = [];
    public int    $aplicTotal       = 0;
    /** flag: true kalau sudah pernah klik tarik data (untuk bedakan "belum pernah" vs "hasil kosong") */
    public bool   $sudahTarikAplicares = false;
    /** map room_id => jumlah bed lokal, untuk deteksi mismatch kapasitas */
    public array  $bedCountLokal    = [];

    /* ─── Ambil data ketersediaan dari Aplicares + snapshot bed lokal ──── */
    public function muatDaftarKamarTerdaftarAplicares(): void
    {
        $this->loadingAplicares = true;
        $this->aplicaresError   = '';

        try {
            $res  = $this->ketersediaanKamarRS(1, 100)->getOriginalContent();
            $body = $res['response'] ?? [];
            $this->aplicaresData = $body['list'] ?? ($body['data'] ?? []);
            $this->aplicTotal    = (int) ($body['total'] ?? count($this->aplicaresData));

            $this->bedCountLokal = DB::table('rsmst_beds')
                ->selectRaw('room_id, COUNT(*) AS jumlah_bed')
                ->groupBy('room_id')
                ->pluck('jumlah_bed', 'room_id')
                ->toArray();

            $jumlah = count($this->aplicaresData);
            if ($jumlah === 0) {
                $this->dispatch('toast', type: 'info', message: 'Berhasil menarik data, tapi belum ada kamar yang terdaftar di Aplicares BPJS.');
            } else {
                $this->dispatch('toast', type: 'success', message: "Berhasil menarik {$jumlah} data kamar dari Aplicares.");
            }
        } catch (\Throwable $e) {
            $this->aplicaresError = $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal menarik data Aplicares: ' . $e->getMessage());
        }

        $this->sudahTarikAplicares = true;
        $this->loadingAplicares    = false;
    }

    /* ─── Hapus ruangan dari Aplicares ───────────────────────── */
    public function hapusKamarDariAplicares(string $kodekelas, string $koderuang): void
    {
        try {
            $res  = $this->hapusRuangan($kodekelas, $koderuang)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            if ($code == 1) {
                $this->dispatch('toast', type: 'success', message: "Ruangan {$koderuang} berhasil dihapus dari Aplicares.");
                $this->muatDaftarKamarTerdaftarAplicares();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal hapus: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /* ─── Samakan kapasitas online dengan bed count lokal (lokal = source of truth) ──── */
    public function samakanKapasitasAplicares(string $kodekelas, string $koderuang): void
    {
        $bedLokal = (int) ($this->bedCountLokal[$koderuang] ?? 0);

        $online = collect($this->aplicaresData)->first(fn($r) =>
            (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? '')) === $koderuang
        );
        if (!$online) {
            $this->dispatch('toast', type: 'error', message: 'Data online tidak ditemukan, tarik ulang dulu.');
            return;
        }

        $terpakai = (int) DB::table('rstxn_rihdrs')
            ->where('room_id', $koderuang)
            ->where('ri_status', 'I')
            ->count();
        $tersedia = max(0, $bedLokal - $terpakai);

        $payload = [
            'kodekelas'          => $kodekelas,
            'koderuang'          => $koderuang,
            'namaruang'          => $online['namaruang'] ?? ($online['nama_ruang'] ?? ''),
            'kapasitas'          => $bedLokal,
            'tersedia'           => $tersedia,
            'tersediapria'       => 0,
            'tersediawanita'     => 0,
            'tersediapriawanita' => $tersedia,
        ];

        try {
            $res  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';

            if ($code == 1) {
                $this->dispatch('toast', type: 'success', message: "Kapasitas {$koderuang} disamakan: {$bedLokal} bed ({$tersedia} tersedia).");
                $this->muatDaftarKamarTerdaftarAplicares();
            } else {
                $this->dispatch('toast', type: 'error', message: "Gagal samakan: {$msg}");
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
        <x-secondary-button wire:click="muatDaftarKamarTerdaftarAplicares" wire:loading.attr="disabled" wire:target="muatDaftarKamarTerdaftarAplicares">
            <x-loading size="xs" wire:loading wire:target="muatDaftarKamarTerdaftarAplicares" class="mr-1" />
            <svg wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares" class="w-3 h-3 mr-1" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5" />
            </svg>
            <span wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares">
                {{ empty($aplicaresData) ? 'Ambil Data Aplicares' : 'Perbarui Data' }}
            </span>
            <span wire:loading wire:target="muatDaftarKamarTerdaftarAplicares">Mengambil data…</span>
        </x-secondary-button>
    </div>

    @if ($aplicaresError)
        <div class="px-5 py-4 bg-red-50 dark:bg-red-900/20 shrink-0 border-l-4 border-red-500">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-red-700 dark:text-red-300">
                        Gagal menarik data dari Aplicares
                    </div>
                    <div class="mt-1 text-xs text-red-600 dark:text-red-400 break-words">
                        {{ $aplicaresError }}
                    </div>
                    <div class="mt-2 text-xs text-red-500/80 dark:text-red-400/80">
                        Cek koneksi ke API BPJS, kredensial, atau coba klik <strong>Ambil Data Aplicares</strong> lagi.
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Loading state --}}
        <div wire:loading wire:target="muatDaftarKamarTerdaftarAplicares"
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
                $aplMismatchCount  = collect($aplicaresData)->filter(function ($r) {
                    $kr = (string) ($r['koderuang'] ?? ($r['kode_ruang'] ?? ''));
                    $online = (int) ($r['kapasitas'] ?? 0);
                    $lokal  = (int) ($this->bedCountLokal[$kr] ?? 0);
                    return $kr !== '' && $online !== $lokal;
                })->count();
            @endphp
            <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares"
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
                <div class="ml-auto flex items-center gap-2">
                    @if ($aplMismatchCount > 0)
                        <div class="flex items-center gap-1.5 bg-amber-500 dark:bg-amber-600 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold" title="Kamar yang kapasitas online-nya beda dengan jumlah bed lokal">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            <span>{{ $aplMismatchCount }} Mismatch Kapasitas</span>
                        </div>
                    @endif
                    <div class="flex items-center gap-1.5 bg-blue-600 dark:bg-blue-700 rounded-lg px-2.5 py-1 text-[11px] text-white font-semibold">
                        <span>Total:</span>
                        <span>{{ $aplTotalRuang }} ruang</span>
                        <span class="opacity-60">·</span>
                        <span>Kap: {{ $aplTotalKapasitas }}</span>
                        <span class="opacity-60">·</span>
                        <span>Tersedia: {{ $aplTotalTersedia }}</span>
                    </div>
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
        <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares" class="flex-1 overflow-auto">
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
                            $koderuang       = $aplic['koderuang'] ?? ($aplic['kode_ruang'] ?? '');
                            $kodekelas       = $aplic['kodekelas'] ?? ($aplic['kode_kelas'] ?? '');
                            $kapasitasOnline = (int) ($aplic['kapasitas'] ?? 0);
                            $kapasitasLokal  = (int) ($this->bedCountLokal[$koderuang] ?? 0);
                            $isMismatch      = $koderuang !== '' && $kapasitasOnline !== $kapasitasLokal;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition {{ $isMismatch ? 'bg-amber-50/40 dark:bg-amber-900/10' : '' }}">
                            <td class="px-5 py-3 font-mono font-semibold">{{ $koderuang ?: '-' }}</td>
                            <td class="px-5 py-3">{{ $aplic['namaruang'] ?? ($aplic['nama_ruang'] ?? '-') }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $kodekelas ?: '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-semibold">
                                @if ($isMismatch)
                                    <div class="inline-flex items-center gap-1.5" title="Online: {{ $kapasitasOnline }} · Lokal (rsmst_beds): {{ $kapasitasLokal }}">
                                        <span class="text-amber-600 dark:text-amber-400">{{ $kapasitasOnline }}</span>
                                        <span class="text-gray-400">/</span>
                                        <span class="text-gray-700 dark:text-gray-200">{{ $kapasitasLokal }}</span>
                                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    </div>
                                @else
                                    {{ $kapasitasOnline }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ $aplic['tersedia'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediapria'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center font-mono text-gray-500 dark:text-gray-400">
                                {{ $aplic['tersediapriawanita'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if ($isMismatch)
                                        <x-confirm-button variant="outline"
                                            :action="'samakanKapasitasAplicares(\'' . $kodekelas . '\', \'' . $koderuang . '\')'"
                                            title="Samakan Kapasitas"
                                            :message="'Samakan kapasitas ruangan ' . $koderuang . ' di Aplicares dari ' . $kapasitasOnline . ' menjadi ' . $kapasitasLokal . ' (sesuai rsmst_beds)?'"
                                            confirmText="Ya, samakan" cancelText="Batal"
                                            class="text-xs">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/></svg>
                                            Samakan
                                        </x-confirm-button>
                                    @endif
                                    <x-confirm-button variant="danger"
                                        :action="'hapusKamarDariAplicares(\'' . $kodekelas . '\', \'' . $koderuang . '\')'"
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
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-16">
                                @if ($sudahTarikAplicares)
                                    <div class="flex flex-col items-center gap-2 text-center">
                                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                                            Data tidak tersedia
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md">
                                            Berhasil terhubung ke Aplicares BPJS, tapi belum ada kamar yang terdaftar di sisi BPJS.
                                            Gunakan menu <strong>"Daftarkan Semua ke Aplicares &amp; SIRS"</strong> untuk mulai mendaftarkan kamar.
                                        </p>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center gap-2 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                        Belum ada data. Klik <strong>Ambil Data Aplicares</strong> untuk memuat.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (!empty($aplicaresData))
            <div wire:loading.remove wire:target="muatDaftarKamarTerdaftarAplicares"
                class="px-5 py-2 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                {{ count($aplicaresData) }} data ruangan
            </div>
        @endif
    @endif

</div>
