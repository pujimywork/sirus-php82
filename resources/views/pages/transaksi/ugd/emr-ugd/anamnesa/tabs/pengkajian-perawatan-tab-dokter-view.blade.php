{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab-dokter-view.blade.php --}}
<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">

        {{-- Perawat Penerima --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Perawat Penerima</span>
            <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
            </span>
        </div>

        {{-- Waktu Datang --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Waktu Datang</span>
            <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.jamDatang')" class="mt-1" />
            </span>
        </div>

        {{-- Tingkat Kegawatan --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tingkat Kegawatan</span>
            <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                @php
                    $triage = $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? '-';
                    $triageColor = match ($triage) {
                        'P1' => 'bg-red-100 text-red-700 border-red-200',
                        'P2' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                        'P3' => 'bg-green-100 text-green-700 border-green-200',
                        'P0' => 'bg-gray-200 text-gray-700 border-gray-300',
                        default => 'bg-gray-100 text-gray-500 border-gray-200',
                    };
                    $triageLabel = match ($triage) {
                        'P1' => 'P1 — Kritis (Merah)',
                        'P2' => 'P2 — Urgent (Kuning)',
                        'P3' => 'P3 — Minor (Hijau)',
                        'P0' => 'P0 — Meninggal (Hitam)',
                        default => '-',
                    };
                @endphp
                @if ($triage !== '-')
                    <span
                        class="inline-block border rounded-full px-3 py-0.5 text-xs font-semibold {{ $triageColor }}">
                        {{ $triageLabel }}
                    </span>
                @else
                    -
                @endif
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan')" class="mt-1" />
            </span>
        </div>

        {{-- Cara Masuk IGD --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Cara Masuk IGD</span>
            <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200">
                {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgd'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd')" class="mt-1" />
            </span>
        </div>

        {{-- Sarana Transportasi --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sarana Transportasi</span>
            <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200">
                {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiDesc'] ?? '-' }}
                @if (!empty($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiKet']))
                    — {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiKet'] }}
                @endif
                <x-input-error :messages="$errors->get('saranaTransportasiId')" class="mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiId')" class="mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiKet')" class="mt-1" />
            </span>
        </div>

        {{-- Anamnesa Diperoleh --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Anamnesa Diperoleh</span>
            <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200">
                @php
                    $anamnesaDiperoleh = $dataDaftarUGD['anamnesa']['anamnesaDiperoleh'] ?? [];
                    $sources = [];
                    if (!empty($anamnesaDiperoleh['autoanamnesa'])) {
                        $sources[] = 'Auto-anamnesa (Pasien)';
                    }
                    if (!empty($anamnesaDiperoleh['allonanamnesa'])) {
                        $sources[] = 'Allo-anamnesa (Keluarga)';
                    }
                @endphp
                {{ !empty($sources) ? implode(', ', $sources) : '-' }}
                @if (!empty($anamnesaDiperoleh['anamnesaDiperolehDari']))
                    — {{ $anamnesaDiperoleh['anamnesaDiperolehDari'] }}
                @endif
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari')" class="mt-1" />
            </span>
        </div>

        {{-- Keluhan Utama --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Keluhan Utama</span>
            <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                {{ $dataDaftarUGD['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
            </span>
        </div>

    </div>
</x-border-form>
