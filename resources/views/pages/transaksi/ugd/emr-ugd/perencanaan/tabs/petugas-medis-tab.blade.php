{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/petugas-medis-tab.blade.php --}}
<div class="space-y-4">

    {{-- Terapi --}}
    <x-border-form :title="__('Terapi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="">
            @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.terapi-tab')
            <p class="mt-3 text-sm text-muted dark:text-gray-400">
                Waktu Pemeriksaan:
                <span class="font-medium text-body dark:text-gray-200">
                    {{ $dataDaftarUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-' }}
                </span>
            </p>
        </div>
    </x-border-form>

    {{-- Dokter Pemeriksa --}}
    <x-border-form :title="__('Dokter Pemeriksa')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="space-y-3">
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$dataDaftarUGD['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ''"
                :date="$dataDaftarUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? ''"
                :locked="$isFormLocked"
                sign="setDrPemeriksa" nameLabel="Dokter Pemeriksa" dateLabel="Selesai Pemeriksaan" signLabel="TTD Dokter" />

            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />
        </div>
    </x-border-form>

</div>
