<x-border-form :title="__('Identitas')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        {{-- Patient UUID --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-3">
                <x-input-label value="Patient UUID" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.patientUuid" :error="$errors->has('dataPasien.pasien.identitas.patientUuid')"
                    class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.patientUuid')" class="mt-1" />
            </div>
            <div class="flex items-end">
                <x-primary-button
                    wire:click.prevent="UpdatepatientUuid('{{ $dataPasien['pasien']['identitas']['nik'] ?? '' }}')"
                    type="button" wire:loading.remove class="w-full">
                    UUID Pasien
                </x-primary-button>
                <div wire:loading wire:target="UpdatepatientUuid">
                    <x-loading />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- NIK --}}
            <div>
                <x-input-label value="NIK" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.nik" :error="$errors->has('dataPasien.pasien.identitas.nik')" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.nik')" class="mt-1" />
            </div>

            {{-- ID BPJS --}}
            <div>
                <x-input-label value="ID BPJS" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.idbpjs" placeholder="13 digit"
                    class="w-full mt-1" />
            </div>

            {{-- Paspor --}}
            <div>
                <x-input-label value="Paspor" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.pasport" placeholder="untuk WNA / WNI"
                    class="w-full mt-1" />
            </div>
        </div>

        {{-- Catatan --}}
        <div class="p-3 rounded-lg bg-yellow-50">
            <p class="text-sm text-gray-600">
                <strong>Catatan:</strong><br>
                1. Jika Pasien (Tidak dikenal) NIK diisi Kosong<br>
                2. Isi alamat sesuai dengan ditemukannya pasien<br>
                3. Untuk Pasien Bayi Baru lahir:<br>
                &nbsp;&nbsp;&nbsp;- Isi NIK dengan "NIK Ibu bayi"<br>
                &nbsp;&nbsp;&nbsp;- Nama bayi dengan format "Bayi Ny(Nama Ibu)"
            </p>
        </div>
    </div>
</x-border-form>
