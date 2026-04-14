<div class="space-y-4">

    @role(['Perawat', 'Admin'])
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                Upload Hasil Penunjang
            </h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-1">

                {{-- File PDF --}}
                <div>
                    <x-input-label for="filePDF" :value="__('File PDF')" />
                    <x-text-input id="filePDF" type="file" wire:model="filePDF" accept="application/pdf" :disabled="$isFormLocked"
                        :error="__($errors->has('filePDF'))" class="mt-1 block w-full" />
                    <div wire:loading wire:target="filePDF"
                        class="mt-1 h-1 w-full bg-primary/30 rounded-full overflow-hidden">
                        <div class="h-1 bg-primary animate-pulse rounded-full w-full"></div>
                    </div>
                </div>

                {{-- Keterangan --}}
                <div>
                    <x-input-label for="descPDF" :value="__('Keterangan')" />
                    <x-text-input id="descPDF" type="text" wire:model.live.debounce.400ms="descPDF"
                        placeholder="Keterangan hasil penunjang..." maxlength="255" :disabled="$isFormLocked" :error="__($errors->has('descPDF'))"
                        class="mt-1 block w-full" />
                </div>

                {{-- Tombol Upload --}}
                <div class="flex items-end">
                    <div wire:loading wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                        <x-primary-button disabled class="w-full justify-center">
                            <svg class="w-4 h-4 animate-spin mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            Mengupload...
                        </x-primary-button>
                    </div>
                    <div wire:loading.remove wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                        <x-primary-button type="button" wire:click="uploadHasilPenunjang" :disabled="$isFormLocked"
                            class="w-full justify-center">
                            Upload Penunjang
                        </x-primary-button>
                    </div>
                </div>

            </div>

            {{-- Row error — grid sama persis agar posisi sejajar dengan inputnya --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <x-input-error :messages="$errors->get('filePDF')" class="mt-1" />
                </div>
                <div>
                    <x-input-error :messages="$errors->get('descPDF')" class="mt-1" />
                </div>
                <div>{{-- kolom ketiga kosong, placeholder agar grid tetap 3 kolom --}}</div>
            </div>
        </div>
    @endrole

    {{-- ── TABEL DAFTAR FILE ───────────────────────────────────── --}}
    <div
        class="overflow-x-auto bg-white border border-gray-200 rounded-xl shadow-sm
                dark:bg-gray-900 dark:border-gray-700">
        <table class="w-full text-sm text-left text-gray-600 dark:text-gray-400">
            <thead class="text-xs font-semibold text-gray-700 uppercase bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 w-44">Tgl Upload</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3 w-28 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($dataDaftarPoliRJ['pemeriksaan']['uploadHasilPenunjang'] ?? [] as $item)
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition">

                        <td class="px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">
                            {{ $item['tglUpload'] ?? '-' }}
                        </td>

                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                            {{ $item['desc'] ?? '-' }}
                        </td>

                        <td class="px-4 py-2">
                            <div class="flex items-center justify-center gap-2">

                                {{-- Tombol Lihat PDF
                                     x-icon-button: icon only, aksi di row tabel --}}
                                @role(['Perawat', 'Admin', 'Dokter'])
                                    <x-icon-button wire:click="openModalViewPenunjang('{{ $item['file'] ?? '' }}')"
                                        title="Lihat PDF">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M5 6a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm0
                                                                                     12a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm1.65-9.76A1
                                                                                     1 0 0 0 5 9v6a1 1 0 0 0 1.65.76l3.5-3a1 1 0 0 0
                                                                                     0-1.52l-3.5-3ZM12 10a1 1 0 0 1 1-1h5a1 1 0 1 1 0
                                                                                     2h-5a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h5a1 1 0 1 1
                                                                                     0 2h-5a1 1 0 0 1-1-1Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </x-icon-button>
                                @endrole

                                {{-- Tombol Hapus
                                     x-confirm-button: row tabel, trigger confirm, aksi irreversible --}}
                                @role(['Perawat', 'Admin'])
                                    <x-confirm-button variant="danger" :action="'deleteHasilPenunjang(\'' . ($item['file'] ?? '') . '\')'" title="Hapus File Penunjang"
                                        message="Yakin ingin menghapus file {{ $item['desc'] ?? '' }}?"
                                        confirmText="Ya, hapus" cancelText="Batal" :disabled="$isFormLocked">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                            fill="currentColor" viewBox="0 0 18 20">
                                            <path d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1
                                             0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0
                                             2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1
                                             1-2 0V8a1 1 0 0 1 2 0v8Zm4 0a1 1 0 0 1-2 0V8a1
                                             1 0 0 1 2 0v8Z" />
                                        </svg>
                                    </x-confirm-button>
                                @endrole

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada file penunjang yang diupload.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── MODAL LIHAT PDF ─────────────────────────────────────── --}}
    <x-modal name="view-penunjang-pdf" size="full" height="full" focusable>

        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-penunjang-pdf-{{ $viewFilePDF }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);
                        background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl
                                bg-yellow-400/10 dark:bg-yellow-400/15">
                            <svg class="w-5 h-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1
                                     1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Hasil Penunjang
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Preview file PDF hasil penunjang pasien
                            </p>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModalViewPenunjang">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0
                                 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414
                                 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586
                                 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — iframe PDF --}}
            <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-950">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0" type="application/pdf"></iframe>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="shrink-0 px-6 py-4 bg-white border-t border-gray-200
                    dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        File dibuka dalam mode preview — tidak dapat diedit.
                    </p>
                    <x-secondary-button type="button" wire:click="closeModalViewPenunjang">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>

    </x-modal>

</div>
