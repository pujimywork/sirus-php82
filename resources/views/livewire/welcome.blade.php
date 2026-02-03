<?php

use Livewire\Component;

new class extends Component {
    // state optional
};
?>

@extends('layouts.app-welcome')

@section('content')
    <div class="relative w-full min-h-[calc(100vh-5rem)] overflow-hidden">
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
                            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold bg-white border rounded-full border-slate-200 dark:bg-white/5 dark:border-white/10">
                            <span class="w-2 h-2 rounded-full bg-brand-lime"></span>
                            Sistem Informasi Rumah Sakit
                        </p>

                        <h1 class="mt-5 text-4xl font-extrabold sm:text-5xl lg:text-6xl">
                            Selamat Datang di
                            <span class="text-brand-green whitespace-nowrap">SIRus</span>
                        </h1>

                        <p class="max-w-xl mt-5 text-slate-600 dark:text-slate-300">
                            Sistem Informasi Rumah Sakit dan E-Rekam Medis untuk rawat jalan, unit gawat darurat & rawat
                            inap.
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

                        {{-- Partner --}}
                        <div class="pt-6 mt-10 border-t border-slate-200/70 dark:border-white/10">
                            <div class="flex items-center gap-4 mt-3">
                                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    Powered by Aturapi Data Technology
                                </p>
                                <span class="w-px h-6 bg-slate-200 dark:bg-white/10"></span>
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
                            class="relative overflow-hidden border rounded-2xl border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
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
            <footer class="py-6 border-t border-slate-200/70 dark:border-white/10">
                <p class="text-xs text-center text-slate-500 dark:text-slate-400">
                    © {{ date('Y') }} SIRus — RSI Madinah
                </p>
            </footer>
        </section>
    </div>
@endsection
