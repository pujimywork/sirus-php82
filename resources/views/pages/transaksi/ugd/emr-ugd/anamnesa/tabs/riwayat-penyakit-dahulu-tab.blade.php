{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/riwayat-penyakit-dahulu-tab.blade.php --}}
<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label value="Riwayat Penyakit Dahulu" :required="true" />
            <x-textarea wire:model.live="dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Ada alergi? — default "Tidak" (SNOMED 716186003 diisi di server, bukan lewat LOV
             zat: 716186003 itu konsep *situation*, bukan zat, jadi ditolak substance-code). --}}
        <div>
            <x-input-label value="Ada Alergi?" :required="false" />
            <div class="flex gap-4 mt-2">
                @foreach (['Ya', 'Tidak'] as $opt)
                    <x-radio-button :label="$opt" :value="$opt" name="adaAlergiUgd"
                        wire:model.live="dataDaftarUGD.anamnesa.alergi.adaAlergi" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        @php $adaAlergi = ($dataDaftarUGD['anamnesa']['alergi']['adaAlergi'] ?? 'Tidak') === 'Ya'; @endphp

        @if ($adaAlergi)
            {{-- Alergi (teks) — hanya saat "Ya" --}}
            <div>
                <x-input-label value="Alergi" :required="false" />
                <x-textarea wire:model.live="dataDaftarUGD.anamnesa.alergi.alergi"
                    placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('dataDaftarUGD.anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                    :rows="3" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.alergi.alergi')" class="mt-1" />
            </div>

            {{-- SNOMED CT — ZAT penyebab alergi (untuk Satu Sehat). Hanya relevan saat "Ya". --}}
            <div>
                <livewire:lov.snomed.lov-snomed target="alergiSnomed"
                    label="Kode SNOMED Zat Penyebab Alergi (Satu Sehat)"
                    placeholder="Ketik nama zat / obat penyebab..." valueSet="substance-code"
                    :initialSnomedCode="$dataDaftarUGD['anamnesa']['alergi']['snomedCode'] ?? null" :disabled="$isFormLocked"
                    wire:key="lov-snomed-alergi-ugd-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-ugd'] ?? 0 }}" />
            </div>
        @else
            <div class="text-xs text-muted dark:text-gray-400">
                Terekam sebagai <span class="font-semibold text-ink dark:text-gray-100">Tidak ada alergi</span>
                <span class="font-mono text-[10px] text-muted-soft">716186003</span> untuk Satu Sehat.
            </div>
        @endif

    </div>
</x-border-form>
