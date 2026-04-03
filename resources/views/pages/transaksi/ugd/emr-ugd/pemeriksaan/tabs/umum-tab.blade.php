{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab.blade.php --}}
<div class="pt-0">
    @role('Dokter')
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-dokter-view')
    @endrole

    @role('Perawat')
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-perawat-view')
    @endrole

    @if (!auth()->user()->hasAnyRole(['Dokter', 'Perawat']))
        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab-dokter-view')
    @endif

    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fisik-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.suspek-akibat-kercelakaan-kerja-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.uji-fungsi-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.penunjang-tab')
    @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.fungsional-tab')
</div>
