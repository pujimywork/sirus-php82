<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                value="Riwayat Penyakit Dahulu" :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Ada alergi? — default "Tidak" (SNOMED 716186003 diisi di server, bukan lewat LOV
             zat: 716186003 itu konsep *situation*, bukan zat, jadi ditolak substance-code). --}}
        <div>
            <x-input-label value="Ada Alergi?" :required="false" />
            <div class="flex gap-4 mt-2">
                @foreach (['Ya', 'Tidak'] as $opt)
                    <x-radio-button :label="$opt" :value="$opt" name="adaAlergi"
                        wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.adaAlergi" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        @php $adaAlergi = ($dataDaftarPoliRJ['anamnesa']['alergi']['adaAlergi'] ?? 'Tidak') === 'Ya'; @endphp

        @if ($adaAlergi)
            {{-- Alergi (teks) — hanya saat "Ya" --}}
            <div>
                <x-input-label for="dataDaftarPoliRJ.anamnesa.alergi.alergi" value="Alergi" :required="false" />

                <x-textarea id="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                    placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('dataDaftarPoliRJ.anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                    :rows="3" class="w-full mt-1" />

                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.alergi.alergi')" class="mt-1" />
            </div>

            {{-- SNOMED CT — ZAT penyebab alergi (untuk Satu Sehat). Hanya relevan saat "Ya". --}}
            <div>
                <livewire:lov.snomed.lov-snomed target="alergiSnomed"
                    label="Kode SNOMED Zat Penyebab Alergi (Satu Sehat)"
                    placeholder="Ketik nama zat / obat penyebab..." valueSet="substance-code"
                    :initialSnomedCode="$dataDaftarPoliRJ['anamnesa']['alergi']['snomedCode'] ?? null" :disabled="$isFormLocked"
                    wire:key="lov-snomed-alergi-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-rj'] ?? 0 }}" />
            </div>
        @else
            <div class="text-xs text-muted dark:text-gray-400">
                Terekam sebagai <span class="font-semibold text-ink dark:text-gray-100">Tidak ada alergi</span>
                <span class="font-mono text-[10px] text-muted-soft">716186003</span> untuk Satu Sehat.
            </div>
        @endif

    </div>
</x-border-form>
