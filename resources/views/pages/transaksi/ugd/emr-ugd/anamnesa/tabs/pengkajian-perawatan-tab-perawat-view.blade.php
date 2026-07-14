{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab.blade.php --}}
<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Perawat Penerima (Waktu Datang otomatis saat TTD) --}}
        <div>
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$dataDaftarUGD['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? ''"
                :date="$dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? ''"
                :code="$dataDaftarUGD['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] ?? ''"
                :locked="$isFormLocked ?? false"
                sign="setPerawatPenerima" nameLabel="Perawat Penerima" dateLabel="Waktu Datang" signLabel="Ttd Perawat" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
        </div>

        {{-- Tingkat Kegawatan --}}
        <div>
            <x-input-label value="Tingkat Kegawatan (Triage)" :required="true" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatanOption'] ?? [] as $opt)
                    <x-radio-button :label="$opt['tingkatKegawatan']" :value="$opt['tingkatKegawatan']" name="tingkatKegawatan"
                        wire:model.live="tingkatKegawatan" :disabled="$isFormLocked" />
                @endforeach
            </div>

            {{-- Indikator warna triage --}}
            <div class="grid grid-cols-4 gap-2 mt-2">
                @foreach ([
        'P1' => ['label' => 'Kritis', 'class' => 'bg-red-500'],
        'P2' => ['label' => 'Urgent', 'class' => 'bg-yellow-400'],
        'P3' => ['label' => 'Minor', 'class' => 'bg-green-500'],
        'P0' => ['label' => 'Meninggal', 'class' => 'bg-gray-700'],
    ] as $p => $info)
                    <div
                        class="flex items-center justify-center gap-1.5 px-2 py-1 rounded-full text-sm text-white font-medium {{ $info['class'] }}
            {{ $tingkatKegawatan === $p ? 'ring-2 ring-offset-1 ring-current opacity-100' : 'opacity-50' }}">
                        {{ $info['label'] }}
                    </div>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan')" class="mt-1" />
        </div>

        {{-- Status Medik --}}
        <div>
            <x-input-label value="Status Medik" />
            <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-2">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['statusMedik']['statusMedikOptions'] ?? [] as $opt)
                    <x-radio-button :label="$opt['statusMedik']" :value="$opt['statusMedik']" name="statusMedikUGD"
                        wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.statusMedik.statusMedik" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.statusMedik.statusMedik')" class="mt-1" />
        </div>

        {{-- Cara Masuk IGD --}}
        <div>
            <x-input-label value="Cara Masuk IGD" :required="true" />
            <div class="grid grid-cols-3 gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgdOption'] ?? [] as $opt)
                    <x-radio-button :label="$opt['caraMasukIgd']" :value="$opt['caraMasukIgd']" name="caraMasukIgd"
                        wire:model.live="caraMasukIgd" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd')" class="mt-1" />
        </div>

        {{-- Sarana Transportasi --}}
        <div>
            <x-input-label value="Sarana Transportasi" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiOptions'] ?? [] as $opt)
                    <x-radio-button :label="$opt['saranaTransportasiDesc']" :value="$opt['saranaTransportasiId']" name="saranaTransportasiId"
                        wire:model.live="saranaTransportasiId" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('saranaTransportasiId')" class="mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiId')" class="mt-1" />
            @if ($saranaTransportasiId === '4')
                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiKet"
                    placeholder="Sebutkan sarana transportasi..." class="w-full mt-2"
                    :error="$errors->has('dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiKet')"
                    :disabled="$isFormLocked" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiKet')" class="mt-1" />
            @endif
        </div>

        {{-- Anamnesa Diperoleh --}}
        <div>
            <x-input-label value="Anamnesa Diperoleh Dari" />
            <x-select-input wire:model.live="dataDaftarUGD.anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari"
                :error="$errors->has('dataDaftarUGD.anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari')"
                class="w-full" :disabled="$isFormLocked">
                <option value="">-- Pilih Sumber Anamnesa --</option>
                <option value="Auto-anamnesa (Pasien)">Auto-anamnesa (Pasien)</option>
                <option value="Allo-anamnesa (Keluarga)">Allo-anamnesa (Keluarga)</option>
                <option value="Allo-anamnesa (Lain-lain)">Allo-anamnesa (Lain-lain)</option>
            </x-select-input>
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari')" class="mt-1" />
        </div>

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label value="Keluhan Utama" :required="true" />
            <x-textarea wire:model.live="dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :error="$errors->has('dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

        {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
        <div>
            <livewire:lov.snomed.lov-snomed
                target="keluhanUtamaSnomed"
                label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
                valueSet="condition-code"
                :initialSnomedCode="$dataDaftarUGD['anamnesa']['keluhanUtama']['snomedCode'] ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-snomed-keluhan-ugd-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-ugd'] ?? 0 }}"
            />
        </div>

    </div>
</x-border-form>
