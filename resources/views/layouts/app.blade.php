<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    {{-- ✅ x-cloak harus jadi attribute, bukan di event --}}
    <div x-data="layoutDrawer()" x-init="init()" x-cloak x-on:keydown.escape.window="closeAll()">

        {{-- TOP BAR (harus paling atas) --}}
        <header
            class="fixed inset-x-0 top-0 z-50 bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between h-20 px-4">

                {{-- LEFT: sidebar hamburger + logo --}}
                <div class="flex items-center gap-3">
                    @auth
                        {{-- Toggle Sidebar (LOGIN MODE) --}}
                        <button type="button"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-600"
                            x-on:click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen.toString()">
                            <span class="sr-only">Toggle sidebar</span>

                            {{-- ICON: Open --}}
                            <svg x-show="!sidebarOpen" x-cloak class="w-6 h-6 text-gray-600 dark:text-gray-300"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 5A.75.75 0 012.75 9h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 9.75zm0 5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 14.75z" />
                            </svg>

                            {{-- ICON: Close --}}
                            <svg x-show="sidebarOpen" x-cloak class="w-6 h-6 text-gray-600 dark:text-gray-300"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" />
                            </svg>
                        </button>
                    @endauth


                    <a wire:navigate href="{{ url('/') }}" class="flex items-center gap-3">
                        <img src="{{ asset('images/Logo Horizontal.png') }}" alt="RSI Madinah"
                            class="block h-16 dark:hidden">
                        <img src="{{ asset('images/Logo Horizontal white.png') }}" alt="RSI Madinah"
                            class="hidden h-16 dark:block">
                    </a>
                </div>

                {{-- RIGHT --}}
                @include('layouts.app-topbar')
            </div>

            {{-- MOBILE TOP NAV DROPDOWN --}}
            <div x-cloak x-show="topNavOpen" x-transition
                class="px-4 py-3 bg-white border-t border-gray-200 lg:hidden dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-1">
                    <a href="{{ route('dashboard') }}"
                        class="px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        Home
                    </a>

                    <div class="px-3 py-2">
                        <button type="button"
                            class="flex items-center justify-between w-full text-sm font-medium text-gray-700 dark:text-gray-200"
                            x-on:click="topDropdownOpen = !topDropdownOpen">
                            Dropdown
                            <svg class="w-4 h-4 transition-transform" :class="topDropdownOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 10 6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m1 1 4 4 4-4" />
                            </svg>
                        </button>

                        <div x-cloak x-show="topDropdownOpen" x-collapse class="pl-2 mt-2 space-y-1">
                            <a href="#"
                                class="block px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Dashboard</a>
                            <a href="#"
                                class="block px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Settings</a>
                            <a href="#"
                                class="block px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Earnings</a>
                        </div>
                    </div>

                    <a href="#"
                        class="px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Services</a>
                    <a href="#"
                        class="px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Pricing</a>
                    <a href="#"
                        class="px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Contact</a>
                </div>
            </div>
        </header>

        {{-- ✅ OVERLAY mulai dari bawah header (top-20), jadi topbar tetap putih --}}
        <div x-cloak x-show="sidebarOpen" x-transition.opacity
            class="fixed inset-x-0 bottom-0 z-40 top-20 bg-gray-900/50" x-on:click="sidebarOpen = false"></div>

        {{-- ✅ SIDEBAR mulai dari bawah header (top-20), bukan mt-20 --}}
        @include('layouts.app-sidebar')

        <div class="pt-20">
            {{-- HEADER SLOT --}}
            @isset($header)
                <header class="bg-white shadow dark:bg-gray-800">
                    <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- PAGE CONTENT --}}
            <main class="min-h-[calc(100vh-5rem)]">
                {{ $slot }}
            </main>
        </div>

    </div>

    <script>
        function layoutDrawer() {
            return {
                sidebarOpen: false,
                openMenus: {},

                topNavOpen: false,
                topDropdownOpen: false,

                init() {
                    this.openMenus = {}

                    this.$watch('sidebarOpen', (val) => {
                        document.body.classList.toggle('overflow-hidden', val)
                    })
                },

                toggleMenu(key) {
                    this.openMenus[key] = !this.openMenus[key]
                },

                closeAll() {
                    this.sidebarOpen = false
                    this.topNavOpen = false
                    this.topDropdownOpen = false
                },
            }
        }
    </script>

    <script>
        document.addEventListener('livewire:init', () => {

            if (window.toastr) {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-left', // 🔥 kiri atas
                    timeOut: 4000, // 4 detik
                    extendedTimeOut: 1500,
                    showDuration: 300,
                    hideDuration: 300,
                    showEasing: 'swing',
                    hideEasing: 'linear',
                    showMethod: 'fadeIn',
                    hideMethod: 'fadeOut',
                    newestOnTop: true,
                    preventDuplicates: true,
                };
            }

            Livewire.on('toast', ({
                type,
                message
            }) => {
                if (window.toastr) {
                    toastr[type]?.(message) ?? toastr.info(message);
                } else {
                    console.log(`[${type}] ${message}`);
                }
            });
        });
    </script>



    @livewireScripts
</body>

</html>
