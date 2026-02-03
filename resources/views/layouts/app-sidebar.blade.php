<aside x-cloak id="app-sidebar"
    class="fixed top-20 left-0 z-[60] h-[calc(100vh-5rem)] w-80 max-w-[85vw]
           bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700
           transform transition-transform duration-300 ease-out"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

    {{-- header sidebar --}}
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-2.5">
            {{-- Green dot (brand) --}}
            <span class="relative flex h-2.5 w-2.5">
                <span
                    class="absolute inline-flex w-full h-full rounded-full opacity-60 bg-brand-lime animate-ping"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-green dark:bg-brand-lime"></span>
            </span>

            {{-- User info --}}
            <div class="leading-tight">
                @auth
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {{ auth()->user()->name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ auth()->user()->getRoleNames()->first() ?? 'User' }}
                    </div>
                @else
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Guest
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Silakan login
                    </div>
                @endauth
            </div>
        </div>
    </div>

    {{-- menu --}}
    <nav class="p-4 space-y-3 overflow-y-auto h-[calc(100vh-5rem-4rem)]">
        @php
            $menus = [
                'Rawat Jalan' => ['Poli', 'Dokter', 'Pendaftaran'],
                'IGD' => ['Triase', 'Tindakan'],
                'Rawat Inap' => ['Kamar', 'Mutasi'],
                'Resep' => ['E-Resep', 'Obat'],
                'Upload Data' => ['Import', 'Riwayat'],
                'Laporan' => ['Harian', 'Bulanan'],
                'Master' => ['Pasien', 'Dokter', 'Poli'],
            ];
        @endphp

        @foreach ($menus as $label => $subs)
            @php $key = Str::slug($label); @endphp

            <div
                class="overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800">
                {{-- Parent --}}
                <button type="button"
                    class="group flex items-center justify-between w-full px-4 py-2.5 rounded-xl
                           transition-colors duration-200
                           hover:bg-brand-green/10
                           dark:hover:bg-brand-lime/15"
                    x-on:click="toggleMenu('{{ $key }}')">

                    <div class="flex items-center gap-3">
                        {{-- Icon wrapper --}}
                        <span
                            class="inline-flex items-center justify-center transition-colors duration-200 rounded-lg w-7 h-7 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white dark:bg-brand-lime/15 dark:text-brand-lime dark:group-hover:bg-brand-lime dark:group-hover:text-slate-900">

                            {{-- SVG pakai currentColor --}}
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 15L12 18" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                                <path d="M21.6359 12.9579L21.3572 14.8952
                                        C20.8697 18.2827 20.626 19.9764
                                        19.451 20.9882C18.2759 22
                                        16.5526 22 13.1061 22H10.8939
                                        C7.44737 22 5.72409 22
                                        4.54903 20.9882C3.37396 19.9764
                                        3.13025 18.2827 2.64284 14.8952
                                        L2.36407 12.9579
                                        C1.98463 10.3208 1.79491 9.00229
                                        2.33537 7.87495
                                        C2.87583 6.7476 4.02619 6.06234
                                        6.32691 4.69181
                                        L7.71175 3.86687
                                        C9.80104 2.62229 10.8457 2
                                        12 2
                                        C13.1543 2 14.199 2.62229
                                        16.2882 3.86687
                                        L17.6731 4.69181
                                        C19.9738 6.06234 21.1242 6.7476
                                        21.6646 7.87495" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg>
                        </span>

                        {{-- Label --}}
                        <span
                            class="font-medium text-gray-800 transition-colors duration-200 text-md group-hover:text-brand-green dark:text-gray-100 dark:group-hover:text-brand-lime">
                            {{ $label }}
                        </span>
                    </div>

                    {{-- Chevron --}}
                    <svg class="w-4 h-4 text-gray-400 transition-colors duration-200 group-hover:text-brand-green dark:text-gray-500 dark:group-hover:text-brand-lime"
                        :class="openMenus['{{ $key }}'] ? 'rotate-180' : ''" fill="none"
                        stroke="currentColor" viewBox="0 0 10 6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                    </svg>
                </button>

                {{-- Children --}}
                <div x-cloak x-show="openMenus['{{ $key }}']" x-collapse class="px-4 pb-3">
                    <div class="pt-2 space-y-2">
                        @foreach ($subs as $sub)
                            <a href="#"
                                class="block px-3 py-1.5 text-md rounded-md
                                       text-gray-700 transition-colors duration-200
                                       hover:bg-brand-green/10 hover:text-brand-green
                                       dark:text-gray-300
                                       dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime">
                                {{ $sub }}
                            </a>
                        @endforeach


                    </div>
                </div>
            </div>
        @endforeach
        <a href="{{ route('master.poli') }}"
            class="block px-3 py-1.5 text-md rounded-md
                        text-gray-700 transition-colors duration-200
                        hover:bg-brand-green/10 hover:text-brand-green
                        dark:text-gray-300
                        dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime">Master
            Poli</a>
    </nav>
</aside>
