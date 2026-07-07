<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Perawat Penerima (Waktu Datang otomatis saat TTD) --}}
        <div>
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? ''"
                :date="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? ''"
                :code="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] ?? ''"
                :locked="$isFormLocked ?? false"
                sign="setPerawatPenerima" nameLabel="Perawat Penerima" dateLabel="Waktu Datang" signLabel="Ttd Perawat" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
        </div>

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" value="Keluhan Utama"
                :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama"
                wire:model.live="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :error="$errors->has('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

        {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
        <div>
            <livewire:lov.snomed.lov-snomed
                target="keluhanUtamaSnomed"
                label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
                valueSet="condition-code"
                :initialSnomedCode="$dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'] ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-snomed-keluhan-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-rj'] ?? 0 }}"
            />
        </div>

    </div>
</x-border-form>
