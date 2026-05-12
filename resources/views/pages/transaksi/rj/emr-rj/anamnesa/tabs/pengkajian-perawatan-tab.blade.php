<div class="w-full mb-1">
    @role('Dokter')
        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @endrole

    @hasanyrole('Perawat|Admin|Casemix')
        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab-perawat-view')
    @endhasanyrole

    @if (!auth()->user()->hasAnyRole(['Dokter', 'Perawat', 'Admin', 'Casemix']))
        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab-dokter-view')
    @endif

    {{-- Include tab-tab anamnesa --}}
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-sekarang-tab')
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-dahulu-tab')
</div>
