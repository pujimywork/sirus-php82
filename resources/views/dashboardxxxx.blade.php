@php
$polis = collect([
(object) ['id' => 1, 'kode' => '22', 'uuid' => null, 'nama' => 'Laboratorium', 'kategori' => 'Poli Penunjang'],
(object) [
'id' => 2,
'kode' => '17',
'uuid' => null,
'nama' => 'Obat Kronis/PRB',
'kategori' => 'Poli Penunjang',
],
(object) ['id' => 3, 'kode' => '10', 'uuid' => null, 'nama' => 'OK', 'kategori' => 'Poli Penunjang'],
(object) [
'id' => 4,
'kode' => '8',
'uuid' => '8fe75618-d18b-492c-9ea9-b0e66ae98db2',
'nama' => 'Poli Akupuntur',
'kategori' => 'Poli Penunjang',
],
(object) [
'id' => 5,
'kode' => '11',
'uuid' => 'aad9075c-e955-475d-a128-58325af0f2f9',
'nama' => 'Poli Anak',
'kategori' => 'Poli Spesialis',
],
(object) [
'id' => 6,
'kode' => '5',
'uuid' => '79d49aab-b5b9-4619-a03f-b94ea1819d57',
'nama' => 'Poli Bedah',
'kategori' => 'Poli Spesialis',
],
]);
@endphp

<x-app-layout>
    {{-- HEADER --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Data Poli</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Master Poli / Ruang Poli</p>
            </div>
        </div>
    </x-slot>

    <div class="pb-0">
        <div class="w-full px-4 sm:px-2 lg:px-0">
            <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
                <div class="px-6 pt-2 pb-6">
                    {{-- TOOLBAR --}}
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        {{-- Search --}}
                        <div class="w-full lg:max-w-xl">
                            <label class="relative block">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    {{-- icon search --}}
                                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387a1 1 0 01-1.414 1.414l-3.387-3.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <input type="text" name="q" placeholder="Cari Data"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-10 pr-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </label>
                        </div>

                        {{-- Right Actions --}}
                        <div class="flex items-center justify-end gap-3">
                            <a href=""
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <span class="text-lg leading-none">+</span>
                                Daftar Data Poli
                            </a>

                            {{-- Dropdown Tampil --}}
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open=!open"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    Tampil (10)
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div x-show="open" @click.outside="open=false" x-transition
                                    class="absolute right-0 z-20 w-40 mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:border-gray-700 dark:bg-gray-900">
                                    @foreach ([10, 25, 50, 100] as $n)
                                    <a href="{{ request()->fullUrlWithQuery(['per_page' => $n]) }}"
                                        class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        Tampil ({{ $n }})
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TABLE --}}
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr class="text-left text-gray-600 dark:text-gray-300">
                                    <th class="px-4 py-3 font-semibold">KODE</th>
                                    <th class="px-4 py-3 font-semibold">POLI</th>
                                    <th class="px-4 py-3 font-semibold">ACTION</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse($polis as $poli)
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                    {{-- KODE --}}
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-emerald-700 dark:text-emerald-400">
                                            {{ $poli->kode }}
                                        </div>
                                        @if (!empty($poli->uuid))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $poli->uuid }}
                                        </div>
                                        @endif
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-4 py-4">
                                        <div class="font-bold tracking-wide text-emerald-800 dark:text-emerald-300">
                                            {{ strtoupper($poli->nama) }}
                                        </div>
                                        <div class="text-sm italic text-gray-600 dark:text-gray-300">
                                            {{ $poli->kategori }}
                                        </div>
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-4 py-4">
                                        <div x-data="{ open: false }" class="relative inline-block">
                                            <button @click="open=!open"
                                                class="inline-flex items-center justify-center px-3 py-2 bg-white border border-gray-200 rounded-lg dark:border-gray-700 dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                aria-label="Action">
                                                <svg class="w-5 h-5 text-gray-700 dark:text-gray-200"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM18 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </button>

                                            <div x-show="open" @click.outside="open=false" x-transition
                                                class="absolute right-0 z-20 mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg w-36 rounded-xl dark:border-gray-700 dark:bg-gray-900">
                                                <a href=""
                                                    class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                    Ubah
                                                </a>

                                                <form action="" method="POST"
                                                    onsubmit="return confirm('Yakin hapus data ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data belum ada.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- PAGINATION --}}
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan 1–{{ $polis->count() }} dari {{ $polis->count() }} data
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>