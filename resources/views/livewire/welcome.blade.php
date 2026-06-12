<?php

use Livewire\Component;

new class extends Component {
    // state optional
};
?>

@extends('layouts.app-welcome')

@section('content')
    <div class="relative w-full min-h-[calc(100vh-5rem)] overflow-hidden bg-canvas dark:bg-gray-900">
        {{-- BG Welcome --}}
        <div class="absolute inset-0 z-10 overflow-hidden pointer-events-none">
            <img src="{{ asset('images/Logogram black solid.png') }}" alt=""
                class="absolute -left-[5%] -bottom-[10%] w-[40rem] lg:w-[48rem] opacity-5">
        </div>

        @php
            $slides = [
                asset('images/landing/slide-1.png'),
                asset('images/landing/slide-2.png'),
                asset('images/landing/slide-3.png'),
                asset('images/landing/slide-4.png'),
                asset('images/landing/slide-5.png'),
                asset('images/landing/slide-6.png'),
                asset('images/landing/slide-7.png'),
                asset('images/landing/slide-8.png'),
                asset('images/landing/slide-9.png'),
            ];
        @endphp

        {{-- WRAPPER flex supaya footer nempel bawah --}}
        <section class="relative z-20 flex flex-col min-h-[calc(100vh-5rem)]">
            {{-- HERO --}}
            <div class="flex-1">
                <div class="grid items-center grid-cols-1 gap-10 px-6 py-12 mx-auto max-w-7xl lg:grid-cols-2 lg:py-20">
                    {{-- LEFT --}}
                    <div>
                        <p
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-caption font-semibold uppercase tracking-wide rounded-full ring-1
                                   bg-brand-green/10 text-brand-green ring-brand-green/20
                                   dark:bg-brand-lime/10 dark:text-brand-lime dark:ring-brand-lime/20">
                            <span class="w-2 h-2 rounded-full bg-brand-lime animate-pulse"></span>
                            Sistem Internal Rumah Sakit
                        </p>

                        <h1 class="mt-5 font-bold tracking-tight text-5xl leading-tight sm:text-6xl text-ink dark:text-white">
                            Selamat Datang di
                            <span class="text-brand-green dark:text-brand-lime whitespace-nowrap">SIRus</span>
                        </h1>

                        <p class="max-w-xl mt-5 text-lg leading-relaxed text-body dark:text-gray-300">
                            Layanan RSI Madinah. Lihat antrian apotek dan jadwal poli hari ini
                            pada halaman publik berikut.
                        </p>

                        {{-- CTA --}}
                        <div class="flex flex-wrap items-center gap-3 mt-8">
                            <a href="{{ Route::has('login') ? route('login') : '#' }}" wire:navigate>
                                <x-primary-button type="button" class="px-5 py-3">
                                    Masuk
                                </x-primary-button>
                            </a>

                            <x-secondary-button type="button" onclick="location.href='#tentang'">
                                Pelajari lebih lanjut
                            </x-secondary-button>
                        </div>

                        {{-- Display publik (TV antrian) — semua on-brand --}}
                        <div class="mt-8">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block w-2 h-2 rounded-full bg-brand-lime"></span>
                                <p class="text-xs font-semibold tracking-[0.12em] uppercase text-brand-green dark:text-brand-lime">
                                    Display Publik
                                </p>
                            </div>
                            @php
                                $displays = [
                                    ['route' => 'display.antrian-apotek-rj', 'label' => 'Antrian Apotek RJ', 'icon' => 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25'],
                                    ['route' => 'display.antrian-apotek-ugd', 'label' => 'Antrian Apotek UGD', 'icon' => 'M4.501 4.5h15a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5h-15A1.5 1.5 0 013 18V6a1.5 1.5 0 011.5-1.5zM12 8v8m-4-4h8'],
                                    ['route' => 'display.antrian-apotek-ri', 'label' => 'Antrian Apotek RI', 'icon' => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h12a1 1 0 001-1V10M9 21V12h6v9'],
                                    ['route' => 'display.jadwal-poli', 'label' => 'Jadwal Poli Hari Ini', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                                ];
                            @endphp
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($displays as $d)
                                    <a href="{{ route($d['route']) }}" target="_blank"
                                        class="group inline-flex items-center gap-2.5 px-3.5 py-2.5 text-sm font-medium transition rounded-xl
                                               bg-surface-card text-body ring-1 ring-hairline
                                               hover:bg-brand-green hover:text-white hover:ring-brand-green
                                               dark:bg-white/5 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-brand-lime dark:hover:text-slate-900">
                                        <svg class="w-4 h-4 transition-colors text-brand-green shrink-0 group-hover:text-white dark:text-brand-lime dark:group-hover:text-slate-900"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d['icon'] }}" />
                                        </svg>
                                        {{ $d['label'] }}
                                        <svg class="w-3.5 h-3.5 ml-auto transition-opacity opacity-40 shrink-0 group-hover:opacity-90"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        {{-- Partner --}}
                        <div class="pt-6 mt-10 border-t border-hairline dark:border-white/10">
                            <div class="flex items-center gap-4 mt-3">
                                <p class="text-caption font-semibold text-muted dark:text-gray-400">
                                    Powered by Aturapi Data Technology
                                </p>
                                <span class="w-px h-6 bg-hairline dark:bg-white/10"></span>
                                <div class="w-16 h-1 rounded-full bg-brand-lime"></div>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT (Slider) --}}
                    <div x-data="{
                        i: 0,
                        slides: @js($slides),
                        interval: null,
                        next() { this.i = (this.i + 1) % this.slides.length },
                        prev() { this.i = (this.i - 1 + this.slides.length) % this.slides.length },
                        start() { this.interval = setInterval(() => this.next(), 3000) },
                        stop() { clearInterval(this.interval);
                            this.interval = null },
                    }" x-init="start()" class="relative">
                        <div
                            class="relative overflow-hidden border rounded-2xl border-hairline bg-surface-soft dark:border-white/10 dark:bg-white/5">
                            <div class="aspect-[16/10] relative">
                                <template x-for="(src, idx) in slides" :key="idx">
                                    <img x-show="i === idx" x-transition:enter="transition-opacity duration-500 ease-out"
                                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition-opacity duration-500 ease-in"
                                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                        :src="src" alt="Landing image"
                                        class="absolute inset-0 object-cover w-full h-full" />
                                </template>
                            </div>
                        </div>

                        <div
                            class="absolute w-24 h-24 rounded-full -top-6 -right-6 bg-brand-lime blur-2xl opacity-40 -z-10">
                        </div>
                        <div
                            class="absolute w-32 h-32 rounded-full opacity-25 -bottom-10 -left-10 bg-brand-green blur-3xl -z-10">
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <footer class="py-6 border-t border-hairline dark:border-white/10">
                <p class="text-center text-caption text-muted dark:text-gray-400">
                    © {{ date('Y') }} SIRus — RSI Madinah
                </p>
            </footer>
        </section>
    </div>
@endsection
