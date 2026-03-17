<div x-data="{ activeTab: 'laboratorium' }">

    {{-- ── TAB NAV ──────────────────────────────────────────────── --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <ul class="flex flex-wrap -mb-px text-xs font-medium text-gray-500 dark:text-gray-400">

            <li class="mr-2">
                <button type="button" @click="activeTab = 'laboratorium'"
                    :class="activeTab === 'laboratorium'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6
                           6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586
                           1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.78 0-2.674-2.154-1.414-3.414l5-5A2
                           2 0 009 10.172V5L8 4z" />
                    </svg>
                    Laboratorium
                </button>
            </li>

            <li class="mr-2">
                <button type="button" @click="activeTab = 'radiologi'"
                    :class="activeTab === 'radiologi'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0
                           001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                    </svg>
                    Radiologi
                </button>
            </li>

            <li class="mr-2">
                <button type="button" @click="activeTab = 'upload'"
                    :class="activeTab === 'upload'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload Penunjang
                </button>
            </li>

        </ul>
    </div>

    {{-- ── TAB CONTENT ──────────────────────────────────────────── --}}

    <div x-show="activeTab === 'laboratorium'" x-cloak>
        <livewire:pages::components.rekam-medis.penunjang.laboratorium-display.laboratorium-display :regNo="$dataDaftarPoliRJ['regNo'] ?? ''"
            wire:key="emr-rj.laboratorium-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>

    <div x-show="activeTab === 'radiologi'" x-cloak>
        <livewire:pages::components.rekam-medis.penunjang.radiologi-display.radiologi-display :regNo="$dataDaftarPoliRJ['regNo'] ?? ''"
            wire:key="emr-rj.radiologi-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>

    <div x-show="activeTab === 'upload'" x-cloak>
        <livewire:pages::components.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" wire:key="emr-rj.upload-penunjang-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>

</div>
