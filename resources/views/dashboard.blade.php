<x-app-layout>
    {{-- HEADER --}}
    <x-slot name="header">
        <div>
            <h2 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Dashboard
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pusat menu aplikasi —
                <span class="font-medium">
                    Role Aktif :{{ auth()->user()->getRoleNames()->implode(', ') }}
                </span>
            </p>
        </div>


    </x-slot>

    {{-- CONTENT --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6" x-data="menuDashboard()" x-init="init()">

            @php
                // role utama = role pertama (atau set prioritas sendiri)
                $userRoles = auth()->user()->getRoleNames()->toArray();
                $activeRole = $userRoles[0] ?? 'pendaftaran'; // fallback

                // MENU LIST: role harus sama persis dengan spatie role name kamu
                $menus = [
                    // DOKTER
                    [
                        'role' => 'dokter',
                        'title' => 'Antrian Poli',
                        'desc' => 'Daftar pasien hari ini',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'dokter',
                        'title' => 'Rekam Medis',
                        'desc' => 'Riwayat & SOAP',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'dokter',
                        'title' => 'E-Resep',
                        'desc' => 'Buat resep elektronik',
                        'href' => '#',
                        'badge' => 'Hot',
                    ],

                    // PERAWAT
                    [
                        'role' => 'perawat',
                        'title' => 'Triase / TTV',
                        'desc' => 'Tanda vital pasien',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'perawat',
                        'title' => 'Tindakan',
                        'desc' => 'Catatan tindakan',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'perawat',
                        'title' => 'Observasi',
                        'desc' => 'Monitoring pasien',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // APOTEKER
                    [
                        'role' => 'apoteker',
                        'title' => 'Validasi Resep',
                        'desc' => 'Verifikasi & racik',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'apoteker',
                        'title' => 'Stok Obat',
                        'desc' => 'Gudang & stok opname',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'apoteker',
                        'title' => 'Distribusi',
                        'desc' => 'Penyerahan obat',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // LABORAT
                    [
                        'role' => 'laborat',
                        'title' => 'Order Lab',
                        'desc' => 'Permintaan pemeriksaan',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'laborat',
                        'title' => 'Hasil Lab',
                        'desc' => 'Input hasil pemeriksaan',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // RADIOLOGI
                    [
                        'role' => 'radiologi',
                        'title' => 'Order Radiologi',
                        'desc' => 'Permintaan foto/CT',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'radiologi',
                        'title' => 'Hasil Radiologi',
                        'desc' => 'Input hasil & cetak',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // GIZI
                    [
                        'role' => 'gizi',
                        'title' => 'Diet Pasien',
                        'desc' => 'Atur menu & diet',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'gizi',
                        'title' => 'Distribusi Makan',
                        'desc' => 'Jadwal & pengiriman',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // PENDAFTARAN
                    [
                        'role' => 'pendaftaran',
                        'title' => 'Registrasi',
                        'desc' => 'Daftar kunjungan',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'pendaftaran',
                        'title' => 'Data Pasien',
                        'desc' => 'Master pasien',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'pendaftaran',
                        'title' => 'Rujukan',
                        'desc' => 'Input rujukan',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // CASEMIX
                    [
                        'role' => 'casemix',
                        'title' => 'Koding INA-CBG',
                        'desc' => 'Klaim & grouping',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'casemix',
                        'title' => 'Verifikasi Berkas',
                        'desc' => 'Check kelengkapan',
                        'href' => '#',
                        'badge' => null,
                    ],
                    [
                        'role' => 'casemix',
                        'title' => 'Laporan Klaim',
                        'desc' => 'Rekap & export',
                        'href' => '#',
                        'badge' => null,
                    ],

                    // ADMIN (opsionalx)
                    [
                        'role' => 'Admin',
                        'title' => 'Manajemen User',
                        'desc' => 'Role & permission',
                        'href' => '#',
                        'badge' => null,
                    ],
                ];
            @endphp



            {{-- GRID MENU --}}
            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                @foreach ($menus as $m)
                    <a href="{{ $m['href'] }}"
                        x-show="isVisible('{{ $m['role'] }}', @js($m['title']), @js($m['desc']))"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="flex items-center justify-between gap-4 p-4 transition-colors duration-200 bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">

                        {{-- LEFT: TITLE + DESC --}}
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3
                                    class="font-semibold text-gray-900 truncate transition-colors duration-200 group-hover:text-brand-green dark:text-gray-100 dark:group-hover:text-brand-lime">
                                    {{ $m['title'] }}
                                </h3>

                                @if (!empty($m['badge']))
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                       bg-emerald-50 text-emerald-700
                                       dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ $m['badge'] }}
                                    </span>
                                @endif
                            </div>

                            <p
                                class="text-xs text-gray-500 truncate transition-colors duration-200 group-hover:text-brand-green/80 dark:text-gray-400 dark:group-hover:text-brand-lime/80">
                                {{ $m['desc'] }}
                            </p>
                        </div>

                        {{-- RIGHT: CTA --}}
                        <span
                            class="pointer-events-none shrink-0
                             transition-transform duration-200
                             group-hover:translate-x-0.5">
                            <x-outline-button type="button">
                                Buka
                            </x-outline-button>
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Alpine --}}
            <script>
                function menuDashboard() {
                    return {
                        query: '',
                        activeRole: @js($activeRole),
                        activeRoleLabel: @js(ucfirst($activeRole)),

                        init() {
                            // kalau kamu mau label custom:
                            // map label biar rapi (dokter => Dokter, casemix => Casemix)
                            const labels = {
                                dokter: 'Dokter',
                                perawat: 'Perawat',
                                apoteker: 'Apoteker',
                                laborat: 'Laboratorium',
                                radiologi: 'Radiologi',
                                gizi: 'Gizi',
                                pendaftaran: 'Pendaftaran',
                                casemix: 'Casemix',
                                admin: 'Admin'
                            }
                            this.activeRoleLabel = labels[this.activeRole] ?? this.activeRole
                        },

                        isVisible(role, title, desc) {
                            if (role !== this.activeRole) return false

                            const q = (this.query || '').toLowerCase().trim()
                            if (!q) return true
                            return (title + ' ' + desc).toLowerCase().includes(q)
                        },
                    }
                }
            </script>

        </div>
    </div>
</x-app-layout>
