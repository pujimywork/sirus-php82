<div
    class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs text-gray-500 dark:text-gray-400">
            @if ($formMode === 'create')
                Pastikan semua data diisi dengan benar sebelum menyimpan.
            @else
                Periksa perubahan data sebelum menyimpan.
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <x-secondary-button type="button" wire:click="closeModal">
                Batal
            </x-secondary-button>

            <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading>
                    <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Menyimpan...
                </span>
            </x-primary-button>
        </div>
    </div>
</div>
