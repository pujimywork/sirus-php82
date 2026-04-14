<x-border-form :title="__('Data Dasar Pasien')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- Reg No (auto-generate saat simpan, tidak bisa diedit) --}}
            <div>
                <x-input-label value="Reg No Pasien" />
                <x-text-input value="{{ $dataPasien['pasien']['regNo'] ?? '' }}" disabled
                    placeholder="{{ $formMode === 'create' ? 'Otomatis saat disimpan' : '' }}"
                    class="w-full mt-1 bg-gray-100 dark:bg-gray-800" />
            </div>

            {{-- Gelar Depan + Nama + Gelar Belakang + Nama Panggilan --}}
            <div class="col-span-1 sm:col-span-2">
                <x-input-label value="Nama Pasien" :required="true" />
                <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-4">
                    <x-text-input placeholder="Gelar depan" wire:model.live="dataPasien.pasien.gelarDepan"
                        :error="$errors->has('dataPasien.pasien.gelarDepan')" class="w-full" />
                    <x-text-input placeholder="Nama" wire:model.live="dataPasien.pasien.regName" :error="$errors->has('dataPasien.pasien.regName')"
                        class="w-full sm:col-span-2" style="text-transform:uppercase" />
                    <x-text-input placeholder="Gelar Belakang" wire:model.live="dataPasien.pasien.gelarBelakang"
                        :error="$errors->has('dataPasien.pasien.gelarBelakang')" class="w-full" />
                </div>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-gray-500">{{ ' / ' }}</span>
                    <x-text-input placeholder="Nama Panggilan" wire:model.live="dataPasien.pasien.namaPanggilan"
                        :error="$errors->has('dataPasien.pasien.namaPanggilan')" class="w-full" />
                </div>
                <x-input-error :messages="$errors->get('dataPasien.pasien.regName')" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- Tempat Lahir --}}
            <div>
                <x-input-label value="Tempat Lahir" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.tempatLahir" :error="$errors->has('dataPasien.pasien.tempatLahir')" class="w-full mt-1"
                    style="text-transform:uppercase" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.tempatLahir')" class="mt-1" />
            </div>

            {{-- Tanggal Lahir + Umur --}}
            <div>
                <x-input-label value="Tanggal Lahir & Umur" :required="true" />
                <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-4">
                    <x-text-input wire:key="tgl-lahir-{{ $regNo ?? 'new' }}-{{ $formMode }}"
                        wire:model.live.debounce.500ms="dataPasien.pasien.tglLahir" placeholder="dd/mm/yyyy" :error="$errors->has('dataPasien.pasien.tglLahir')"
                        class="w-full sm:col-span-2" />

                    <div class="mt-1 text-lg font-medium text-brand-green dark:text-brand-lime">
                        @if (isset($dataPasien['pasien']['thn']))
                            {{ $dataPasien['pasien']['thn'] ?? 0 }} th
                            {{ $dataPasien['pasien']['bln'] ?? 0 }} bln
                            {{ $dataPasien['pasien']['hari'] ?? 0 }} hr
                        @endif
                    </div>
                    {{-- <x-text-input wire:key="umur-thn-{{ $regNo ?? 'new' }}-{{ $formMode }}"
                        wire:model.live="dataPasien.pasien.thn" placeholder="Thn" :error="$errors->has('dataPasien.pasien.thn')" class="w-full" />
                    <x-text-input wire:key="umur-bln-{{ $regNo ?? 'new' }}-{{ $formMode }}"
                        wire:model.live="dataPasien.pasien.bln" placeholder="Bln" :error="$errors->has('dataPasien.pasien.bln')" class="w-full" />
                    <x-text-input wire:key="umur-hari-{{ $regNo ?? 'new' }}-{{ $formMode }}"
                        wire:model.live="dataPasien.pasien.hari" placeholder="Hari" :error="$errors->has('dataPasien.pasien.hari')" class="w-full" /> --}}
                </div>

                <x-input-error :messages="$errors->get('dataPasien.pasien.tglLahir')" class="mt-1" />
            </div>
        </div>
    </div>
</x-border-form>
