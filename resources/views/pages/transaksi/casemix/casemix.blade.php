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

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="px-4 py-4 mx-auto max-w-[1920px]">

        {{-- TAB NAV --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700">
            <button type="button" wire:click="setTab('rj')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'rj' ? 'text-emerald-700 border-emerald-600 dark:text-emerald-300 dark:border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Rawat Jalan
            </button>
            <button type="button" wire:click="setTab('ugd')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'ugd' ? 'text-rose-700 border-rose-600 dark:text-rose-300 dark:border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                UGD
            </button>
            <button type="button" wire:click="setTab('ri')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'ri' ? 'text-blue-700 border-blue-600 dark:text-blue-300 dark:border-blue-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Rawat Inap
            </button>
            <button type="button" wire:click="setTab('rekap-rj')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'rekap-rj' ? 'text-violet-700 border-violet-600 dark:text-violet-300 dark:border-violet-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Rekap iDRG Rawat Jalan
            </button>
            <button type="button" wire:click="setTab('rekap')"
                class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2 {{ $activeTab === 'rekap' ? 'text-violet-700 border-violet-600 dark:text-violet-300 dark:border-violet-400' : 'text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Rekap iDRG Rawat Inap
            </button>
        </div>

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
