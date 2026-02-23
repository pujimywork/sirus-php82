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
                    <x-loading />
                    Menyimpan...
                </span>
            </x-primary-button>
        </div>
    </div>
</div>
