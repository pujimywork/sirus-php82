<div class="pt-0">
    @role('Dokter')
        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.umum-tab-dokter-view')
    @endrole

    @hasanyrole('Perawat|Admin|Casemix')
        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.umum-tab-perawat-view')
    @endhasanyrole

    @if (!auth()->user()->hasAnyRole(['Dokter', 'Perawat', 'Admin', 'Casemix']))
        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.umum-tab-dokter-view')
    @endif

    @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.fisik-tab')
    @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.suspek-akibat-kercelakaan-kerja-tab')
    @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.uji-fungsi-tab')
    @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.penunjang-tab')
    @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.fungsional-tab')
</div>
