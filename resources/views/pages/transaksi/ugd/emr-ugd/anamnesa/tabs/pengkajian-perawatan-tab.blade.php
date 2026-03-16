{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab.blade.php --}}
<div class="w-full mb-1">
    @if (auth()->user()->hasRole('Dokter'))
        @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @else
        @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab-perawat-view')
    @endif

    {{-- Include tab-tab anamnesa --}}
    @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.riwayat-penyakit-sekarang-tab')
    @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.riwayat-penyakit-dahulu-tab')
</div>
