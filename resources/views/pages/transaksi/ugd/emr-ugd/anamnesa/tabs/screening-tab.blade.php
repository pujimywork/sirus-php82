{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/screening-tab.blade.php --}}
@php
    $sc = $dataDaftarUGD['screening'] ?? [];
@endphp

<div class="space-y-4">

    <x-border-form :title="__('Screening IGD')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- INFO PETUGAS --}}
            @if (!empty($sc['petugasPelayanan']))
                <div
                    class="flex items-center gap-3 px-4 py-2 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                    <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-700 dark:text-green-300">
                        Petugas: <strong>{{ $sc['petugasPelayanan'] }}</strong>
                        — {{ $sc['tanggalPelayanan'] ?? '' }}
                    </span>
                </div>
            @endif

            {{-- KELUHAN UTAMA --}}
            <div>
                <x-input-label value="Keluhan Utama" :required="true" />
                <x-textarea wire:model.live="dataDaftarUGD.screening.keluhanUtama"
                    placeholder="Deskripsikan keluhan utama pasien saat tiba di IGD..." :error="$errors->has('dataDaftarUGD.screening.keluhanUtama')"
                    :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.screening.keluhanUtama')" class="mt-1" />
            </div>

            <x-border-form :title="__('Kondisi Klinis')" :align="__('start')" :bgcolor="__('bg-white')">
                <div class="mt-4 space-y-3">

                    {{-- PERNAFASAN --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-input-label value="Pernafasan" :required="true" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($sc['pernafasanOptions'] ?? [] as $opt)
                                    <label
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg cursor-pointer transition-colors
                                        {{ ($sc['pernafasan'] ?? '') === $opt['pernafasan']
                                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                        <input type="radio" wire:model.live="dataDaftarUGD.screening.pernafasan"
                                            value="{{ $opt['pernafasan'] }}" class="sr-only"
                                            {{ $isFormLocked ? 'disabled' : '' }} />
                                        {{ $opt['pernafasan'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.pernafasan')" class="mt-1" />
                    </div>

                    {{-- KESADARAN --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-input-label value="Kesadaran" :required="true" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($sc['kesadaranOptions'] ?? [] as $opt)
                                    <label
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg cursor-pointer transition-colors
                                        {{ ($sc['kesadaran'] ?? '') === $opt['kesadaran']
                                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                        <input type="radio" wire:model.live="dataDaftarUGD.screening.kesadaran"
                                            value="{{ $opt['kesadaran'] }}" class="sr-only"
                                            {{ $isFormLocked ? 'disabled' : '' }} />
                                        {{ $opt['kesadaran'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.kesadaran')" class="mt-1" />
                    </div>

                    {{-- NYERI DADA --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-start">
                            <x-input-label value="Nyeri Dada" :required="true" />
                            <div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($sc['nyeriDadaOptions'] ?? [] as $opt)
                                        <label
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg cursor-pointer transition-colors
                                            {{ ($sc['nyeriDada'] ?? '') === $opt['nyeriDada']
                                                ? 'border-red-500 bg-red-50 text-red-600 font-semibold dark:bg-red-900/20'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                            <input type="radio" wire:model.live="dataDaftarUGD.screening.nyeriDada"
                                                value="{{ $opt['nyeriDada'] }}" class="sr-only"
                                                {{ $isFormLocked ? 'disabled' : '' }} />
                                            {{ $opt['nyeriDada'] }}
                                        </label>
                                    @endforeach
                                </div>
                                {{-- Tingkat nyeri dada --}}
                                @if (($sc['nyeriDada'] ?? '') === 'Ada')
                                    <div class="flex flex-wrap gap-2 mt-2 pl-2 border-l-2 border-red-300">
                                        @foreach ($sc['nyeriDadaTingkatOptions'] ?? [] as $opt)
                                            <label
                                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs border rounded-lg cursor-pointer transition-colors
                                                {{ ($sc['nyeriDadaTingkat'] ?? '') === $opt['nyeriDadaTingkat']
                                                    ? 'border-red-400 bg-red-100 text-red-700 font-semibold'
                                                    : 'border-gray-200 hover:border-gray-300' }}">
                                                <input type="radio"
                                                    wire:model.live="dataDaftarUGD.screening.nyeriDadaTingkat"
                                                    value="{{ $opt['nyeriDadaTingkat'] }}" class="sr-only"
                                                    {{ $isFormLocked ? 'disabled' : '' }} />
                                                {{ $opt['nyeriDadaTingkat'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.nyeriDada')" class="mt-1" />
                    </div>

                    {{-- PRIORITAS PELAYANAN --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-input-label value="Prioritas Pelayanan" :required="true" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($sc['prioritasPelayananOptions'] ?? [] as $opt)
                                    <label
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg cursor-pointer transition-colors
                                        {{ ($sc['prioritasPelayanan'] ?? '') === $opt['prioritasPelayanan']
                                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                        <input type="radio"
                                            wire:model.live="dataDaftarUGD.screening.prioritasPelayanan"
                                            value="{{ $opt['prioritasPelayanan'] }}" class="sr-only"
                                            {{ $isFormLocked ? 'disabled' : '' }} />
                                        {{ $opt['prioritasPelayanan'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.prioritasPelayanan')" class="mt-1" />
                    </div>

                </div>
            </x-border-form>

            <x-border-form :title="__('Petugas Screening')" :align="__('start')" :bgcolor="__('bg-white')">
                <div class="mt-4 space-y-3">

                    {{-- TANGGAL PELAYANAN --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-input-label value="Tanggal Pelayanan" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model.live="dataDaftarUGD.screening.tanggalPelayanan"
                                    placeholder="dd/mm/yyyy hh:mm:ss" class="w-full" :error="$errors->has('dataDaftarUGD.screening.tanggalPelayanan')"
                                    :disabled="$isFormLocked" />
                                @if (!$isFormLocked)
                                    <x-outline-button type="button" class="whitespace-nowrap"
                                        wire:click.prevent="autoSetTanggalPelayanan" wire:loading.attr="disabled"
                                        wire:target="autoSetTanggalPelayanan">
                                        Sekarang
                                    </x-outline-button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- PETUGAS PELAYANAN --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-input-label value="Petugas Pelayanan" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model.live="dataDaftarUGD.screening.petugasPelayanan"
                                    placeholder="Nama petugas screening" class="w-full" :error="$errors->has('dataDaftarUGD.screening.petugasPelayanan')"
                                    :disabled="true" />
                                @if (!$isFormLocked)
                                    @hasanyrole('Perawat|Dokter|Admin')
                                        <x-outline-button type="button" class="whitespace-nowrap"
                                            wire:click.prevent="setPetugasPelayanan" wire:loading.attr="disabled"
                                            wire:target="setPetugasPelayanan">
                                            <span wire:loading.remove wire:target="setPetugasPelayanan"
                                                class="inline-flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                </svg>
                                                Ttd Petugas
                                            </span>
                                            <span wire:loading wire:target="setPetugasPelayanan"
                                                class="inline-flex items-center gap-1.5">
                                                <x-loading /> Menyimpan...
                                            </span>
                                        </x-outline-button>
                                    @endhasanyrole
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </x-border-form>

        </div>
    </x-border-form>

</div>
