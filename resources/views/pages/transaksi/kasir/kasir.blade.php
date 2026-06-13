<?php
// resources/views/pages/transaksi/kasir/kasir.blade.php
// Wrapper 1 halaman untuk Antrian Kasir RJ & UGD dalam tab

use Livewire\Component;

new class extends Component {
    public string $activeTab = 'rj';

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['rj', 'ugd', 'ri', 'daftar-ri'])) {
            return;
        }
        $this->activeTab = $tab;
    }
};
?>

<div class="min-h-screen bg-surface-soft dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- HEADER --}}
        {{-- <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-ink dark:text-white">Kasir</h1>
                <p class="text-xs text-muted dark:text-gray-400">
                    Telaah resep, pelayanan kefarmasian, &amp; kasir resep
                </p>
            </div>
        </div> --}}
        {{-- TAB NAV --}}
        <x-tabs variant="underline">
            <x-tab :active="$activeTab === 'rj'" color="emerald" wire:click="setTab('rj')">Rawat Jalan</x-tab>
            <x-tab :active="$activeTab === 'ugd'" color="rose" wire:click="setTab('ugd')">UGD</x-tab>
            <x-tab :active="$activeTab === 'ri'" color="blue" wire:click="setTab('ri')">Rawat Inap</x-tab>
            <x-tab :active="$activeTab === 'daftar-ri'" color="purple" wire:click="setTab('daftar-ri')">Daftar Pasien RI</x-tab>
        </x-tabs>

        {{-- TAB CONTENT --}}
        <div class="mt-4">
            @if ($activeTab === 'rj')
                <livewire:pages::transaksi.rj.antrian-kasir-rj.antrian-kasir-rj
                    wire:key="antrian-kasir-rj-wrapper" />
            @elseif ($activeTab === 'ugd')
                <livewire:pages::transaksi.ugd.antrian-kasir-ugd.antrian-kasir-ugd
                    wire:key="antrian-kasir-ugd-wrapper" />
            @elseif ($activeTab === 'ri')
                <livewire:pages::transaksi.kasir.antrian-kasir-ri.antrian-kasir-ri
                    wire:key="antrian-kasir-ri-wrapper" />
            @elseif ($activeTab === 'daftar-ri')
                <livewire:pages::transaksi.kasir.daftar-kasir-ri.daftar-kasir-ri
                    wire:key="daftar-kasir-ri-wrapper" />
            @endif
        </div>

    </div>
</div>
