<x-border-form :title="__('Identitas')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">
        {{-- Patient UUID · NIK · ID BPJS · Paspor — 1 baris --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
            {{-- Patient UUID + tombol generate (inline) --}}
            <div class="sm:col-span-2">
                <x-input-label value="Patient UUID" :required="true" />
                <div class="flex gap-1 mt-1">
                    <x-text-input wire:model.live="dataPasien.pasien.identitas.patientUuid"
                        :error="$errors->has('dataPasien.pasien.identitas.patientUuid')" class="w-full" />
                    <x-primary-button type="button"
                        wire:click.prevent="UpdatepatientUuid('{{ $dataPasien['pasien']['identitas']['nik'] ?? '' }}')"
                        wire:loading.attr="disabled" wire:target="UpdatepatientUuid"
                        title="Ambil UUID Satusehat dari NIK" class="!px-3 shrink-0">
                        <span wire:loading.remove wire:target="UpdatepatientUuid">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </span>
                        <span wire:loading wire:target="UpdatepatientUuid"><x-loading /></span>
                    </x-primary-button>
                </div>
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.patientUuid')" class="mt-1" />
            </div>

            {{-- NIK --}}
            <div>
                <x-input-label value="NIK" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.nik" :error="$errors->has('dataPasien.pasien.identitas.nik')" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.nik')" class="mt-1" />
            </div>

            {{-- ID BPJS --}}
            <div>
                <x-input-label value="ID BPJS" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.idbpjs" placeholder="13 digit" class="w-full mt-1" />
            </div>

            {{-- Paspor --}}
            <div>
                <x-input-label value="Paspor" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.pasport" placeholder="untuk WNA / WNI" class="w-full mt-1" />
            </div>
        </div>

        {{-- Catatan (buka/tutup) — aksen brand biar terkesan bisa diklik --}}
        <div x-data="{ open: false }"
            class="overflow-hidden border rounded-lg border-brand-green/30 dark:border-brand-lime/30">
            <button type="button" x-on:click="open = !open"
                class="flex items-center justify-between w-full gap-2 px-3 py-2 transition-colors bg-brand-green/5 text-brand-green hover:bg-brand-green/10 dark:bg-brand-lime/10 dark:text-brand-lime dark:hover:bg-brand-lime/20">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Catatan pengisian
                </span>
                <svg class="w-4 h-4 transition-transform shrink-0" :class="open ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="px-3 py-2 text-sm text-body dark:text-gray-300">
                1. Jika Pasien (Tidak dikenal) NIK diisi Kosong<br>
                2. Isi alamat sesuai dengan ditemukannya pasien<br>
                3. Untuk Pasien Bayi Baru lahir:<br>
                &nbsp;&nbsp;&nbsp;- Isi NIK dengan "NIK Ibu bayi"<br>
                &nbsp;&nbsp;&nbsp;- Nama bayi dengan format "Bayi Ny(Nama Ibu)"
            </div>
        </div>
    </div>
</x-border-form>
