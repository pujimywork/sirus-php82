<?php
// resources/views/pages/transaksi/casemix/casemix.blade.php
// Wrapper 1 halaman untuk Daftar Pasien Bulanan RJ / UGD / RI dalam tab — view Casemix

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'rj';

    private const TABS = ['rj', 'ugd', 'ri', 'rekap', 'rekap-rj'];

    public function mount(): void
    {
        // Deep-link dari AppMenu: /transaksi/casemix?tab=rekap-rj
        $tab = (string) request()->query('tab', '');
        if (in_array($tab, self::TABS, true)) {
            $this->activeTab = $tab;
        }
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, self::TABS, true)) {
            return;
        }
        $this->activeTab = $tab;
    }
};
?>

<div class="min-h-screen bg-surface-soft dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- TAB NAV --}}
        <x-tabs variant="underline">
            <x-tab :active="$activeTab === 'rj'" color="emerald" wire:click="setTab('rj')">Rawat Jalan</x-tab>
            <x-tab :active="$activeTab === 'ugd'" color="rose" wire:click="setTab('ugd')">UGD</x-tab>
            <x-tab :active="$activeTab === 'ri'" color="blue" wire:click="setTab('ri')">Rawat Inap</x-tab>
            <x-tab :active="$activeTab === 'rekap-rj'" color="violet" wire:click="setTab('rekap-rj')">Rekap iDRG Rawat Jalan</x-tab>
            <x-tab :active="$activeTab === 'rekap'" color="violet" wire:click="setTab('rekap')">Rekap iDRG Rawat Inap</x-tab>
        </x-tabs>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.daftar-rj-bulanan.daftar-rj-bulanan
                    wire:key="daftar-rj-bulanan-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.daftar-ugd-bulanan.daftar-ugd-bulanan
                    wire:key="daftar-ugd-bulanan-wrapper" />
            @elseif ($activeTab === 'ri')
                <livewire:pages::transaksi.ri.daftar-ri-bulanan.daftar-ri-bulanan
                    wire:key="daftar-ri-bulanan-wrapper" />
            @elseif ($activeTab === 'rekap-rj')
                <livewire:pages::transaksi.casemix.rekap-idrg-rj.rekap-idrg-rj wire:key="rekap-idrg-rj-wrapper" />
            @elseif ($activeTab === 'rekap')
                <livewire:pages::transaksi.casemix.rekap-idrg-ri.rekap-idrg-ri wire:key="rekap-idrg-ri-wrapper" />
            @endif
        </div>

    </div>
</div>
