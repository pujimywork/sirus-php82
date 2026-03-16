{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab.blade.php --}}
<div class="pt-0">
    @if (auth()->user()->hasRole('Dokter'))
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-dokter-view')
    @else
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-perawat-view')
    @endif

    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fisik-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.suspek-akibat-kercelakaan-kerja-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.uji-fungsi-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.penunjang-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fungsional-tab')
</div>
