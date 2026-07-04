<?php
// resources/views/pages/transaksi/penunjang/radiologi/⚡upload-radiologi-view-actions.blade.php
//
// Sibling action component — VIEW FILE RADIOLOGI (foto / hasil bacaan) di dalam modal.
// Baca file di iframe (pola radiologi-display RM), bukan buka tab baru.
// Listener event dari halaman utama:
//   - radiologi.view.open  (file, title)
//
// File di private disk → route('files.show'); legacy full-path → asset('storage/..').

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public string $viewFilePDF = '';
    public string $viewFileTitle = '';

    #[On('radiologi.view.open')]
    public function open(?string $file, string $title = 'File Radiologi'): void
    {
        $url = $this->resolveFileUrl($file);
        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }
        $this->viewFilePDF = $url;
        $this->viewFileTitle = $title;
        $this->dispatch('open-modal', name: 'view-radiologi-pdf');
    }

    public function closeView(): void
    {
        $this->reset(['viewFilePDF', 'viewFileTitle']);
        $this->dispatch('close-modal', name: 'view-radiologi-pdf');
    }

    private function resolveFileUrl(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }
        return str_contains($name, '/') ? asset('storage/' . $name) : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $name]);
    }
};
?>

<div>
    <x-modal name="view-radiologi-pdf" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-radiologi-{{ $viewFilePDF }}">
            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold truncate text-ink dark:text-gray-100">
                    {{ $viewFileTitle ?: 'Lihat File Radiologi' }}
                </h2>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($viewFilePDF)
                        <a href="{{ $viewFilePDF }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 text-xs font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Buka di Tab Baru
                        </a>
                    @endif
                    <x-icon-button color="gray" type="button" wire:click="closeView">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — iframe streaming via route files.show --}}
            <div class="flex-1 p-2 bg-surface-soft dark:bg-gray-900">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0" type="application/pdf"></iframe>
                @endif
            </div>
        </div>
    </x-modal>
</div>
