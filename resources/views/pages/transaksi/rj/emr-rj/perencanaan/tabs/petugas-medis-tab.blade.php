<div class="space-y-4">

    {{-- Terapi --}}
    <x-border-form :title="__('Terapi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="">
            @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.terapi-tab')

            <p class="mt-3 text-sm text-muted dark:text-gray-400">
                Waktu Pemeriksaan:
                <span class="font-medium text-body dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-' }}
                </span>
            </p>
        </div>
    </x-border-form>

    {{-- Dokter Pemeriksa --}}
    <x-border-form :title="__('Dokter Pemeriksa')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="space-y-3">

            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ''"
                :date="$dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? ''"
                :locked="$isFormLocked"
                sign="setDrPemeriksa" nameLabel="Dokter Pemeriksa" dateLabel="Selesai Pemeriksaan" signLabel="TTD Dokter" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />

        </div>
    </x-border-form>

</div>
