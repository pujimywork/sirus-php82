{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab.blade.php --}}
<div class="w-full mb-1">
    @role('Dokter')
        @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @endrole

    @role('Perawat')
        @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab-perawat-view')
    @endrole

    @if (!auth()->user()->hasAnyRole(['Dokter', 'Perawat']))
        @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @endif

    {{-- Include tab-tab anamnesa --}}
    @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.riwayat-penyakit-sekarang-tab')
    @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.riwayat-penyakit-dahulu-tab')
</div>
