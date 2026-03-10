<?php

use Livewire\Component;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessFailedException;

new class extends Component {
    public string $shareServer = '//172.8.8.12/rad_path/';
    public string $mountPoint = '/opt/lampp/htdocs/sirus-php82/storage/penunjang/rad';
    public string $shareServerUpload = '//172.8.8.12/upload_path/';
    public string $mountPointUpload = '/opt/lampp/htdocs/sirus-php82/storage/penunjang/upload';
    public string $statusMessage = '';
    public string $statusMessageUpload = '';
    public bool $isMounted = false;
    public bool $isMountedUpload = false;

    public function mount(): void
    {
        $this->checkMounted();
        $this->checkMountedUpload();
    }

    public function mountShare(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServer, $this->mountPoint]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessage = '✓ Mount berhasil di: ' . $this->mountPoint;
            $this->dispatch('toast', type: 'success', message: $this->statusMessage);
        } catch (ProcessFailedException $e) {
            $this->statusMessage = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        } catch (\Throwable $e) {
            $this->statusMessage = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        }

        $this->checkMounted();
    }

    public function unmountShare(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPoint]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessage = '✓ Unmount berhasil dari: ' . $this->mountPoint;
            $this->dispatch('toast', type: 'success', message: $this->statusMessage);
        } catch (ProcessFailedException $e) {
            $this->statusMessage = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        } catch (\Throwable $e) {
            $this->statusMessage = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        }

        $this->checkMounted();
    }

    public function checkMounted(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPoint]);

            $process->setTimeout(2);
            $process->run();

            $this->isMounted = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMounted = false;
        } catch (\Throwable) {
            $this->isMounted = false;
        }
    }

    public function mountShareUpload(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerUpload, $this->mountPointUpload]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageUpload = '✓ Mount berhasil di: ' . $this->mountPointUpload;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageUpload);
        } catch (ProcessFailedException $e) {
            $this->statusMessageUpload = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        } catch (\Throwable $e) {
            $this->statusMessageUpload = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        }

        $this->checkMountedUpload();
    }

    public function unmountShareUpload(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointUpload]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageUpload = '✓ Unmount berhasil dari: ' . $this->mountPointUpload;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageUpload);
        } catch (ProcessFailedException $e) {
            $this->statusMessageUpload = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        } catch (\Throwable $e) {
            $this->statusMessageUpload = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        }

        $this->checkMountedUpload();
    }

    public function checkMountedUpload(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointUpload]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedUpload = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedUpload = false;
        } catch (\Throwable) {
            $this->isMountedUpload = false;
        }
    }
};
?>

<div>
    {{-- PAGE HEADER --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Mounting Control
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Manajemen mount/unmount share folder jaringan (CIFS/SMB)
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- ================================================================ --}}
            {{-- SECTION: RAD SHARE --}}
            {{-- ================================================================ --}}

            {{-- TOOLBAR RAD --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">

                    <span class="text-sm font-semibold text-gray-500 uppercase dark:text-gray-400">RAD Share</span>

                    @if ($isMounted)
                        <x-badge variant="success">Terhubung</x-badge>
                    @else
                        <x-badge variant="danger">Tidak Terhubung</x-badge>
                    @endif

                    <div class="flex items-center gap-2 ml-auto">

                        <x-ghost-button type="button" wire:click="checkMounted" wire:loading.attr="disabled"
                            wire:target="checkMounted" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="checkMounted" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Cek Status
                            </span>
                            <span wire:loading wire:target="checkMounted" class="flex items-center gap-1">
                                <x-loading />
                                Mengecek...
                            </span>
                        </x-ghost-button>

                        <x-primary-button type="button" wire:click="mountShare" wire:loading.attr="disabled"
                            wire:target="mountShare" :disabled="$isMounted" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="mountShare" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Mount Share
                            </span>
                            <span wire:loading wire:target="mountShare" class="flex items-center gap-1">
                                <x-loading />
                                Mounting...
                            </span>
                        </x-primary-button>

                        <x-warning-button type="button" wire:click="unmountShare" wire:loading.attr="disabled"
                            wire:target="unmountShare" :disabled="!$isMounted" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="unmountShare" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Unmount Share
                            </span>
                            <span wire:loading wire:target="unmountShare" class="flex items-center gap-1">
                                <x-loading />
                                Unmounting...
                            </span>
                        </x-warning-button>

                    </div>
                </div>
            </div>

            {{-- CARD RAD --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6 space-y-6">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                        <div>
                            <x-input-label value="Share Server" />
                            <x-text-input wire:model.live="shareServer" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div>
                            <x-input-label value="Mount Point" />
                            <x-text-input wire:model.live="mountPoint" class="block w-full mt-1 font-mono"
                                placeholder="/mnt/target" />
                        </div>

                    </div>

                    @if ($statusMessage !== '')
                        <div @class([
                            'p-4 rounded-xl border text-sm font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with(
                                $statusMessage,
                                '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with(
                                $statusMessage,
                                '✗'),
                        ])>
                            <div class="flex items-start gap-3">

                                @if (str_starts_with($statusMessage, '✓'))
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif

                                <p>{{ $statusMessage }}</p>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: UPLOAD SHARE --}}
            {{-- ================================================================ --}}

            {{-- TOOLBAR UPLOAD --}}
            <div
                class="sticky z-30 px-4 py-3 mt-6 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">

                    <span class="text-sm font-semibold text-gray-500 uppercase dark:text-gray-400">Upload Share</span>

                    @if ($isMountedUpload)
                        <x-badge variant="success">Terhubung</x-badge>
                    @else
                        <x-badge variant="danger">Tidak Terhubung</x-badge>
                    @endif

                    <div class="flex items-center gap-2 ml-auto">

                        <x-ghost-button type="button" wire:click="checkMountedUpload" wire:loading.attr="disabled"
                            wire:target="checkMountedUpload" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="checkMountedUpload" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Cek Status
                            </span>
                            <span wire:loading wire:target="checkMountedUpload" class="flex items-center gap-1">
                                <x-loading />
                                Mengecek...
                            </span>
                        </x-ghost-button>

                        <x-primary-button type="button" wire:click="mountShareUpload" wire:loading.attr="disabled"
                            wire:target="mountShareUpload" :disabled="$isMountedUpload" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="mountShareUpload" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Mount Share
                            </span>
                            <span wire:loading wire:target="mountShareUpload" class="flex items-center gap-1">
                                <x-loading />
                                Mounting...
                            </span>
                        </x-primary-button>

                        <x-warning-button type="button" wire:click="unmountShareUpload" wire:loading.attr="disabled"
                            wire:target="unmountShareUpload" :disabled="!$isMountedUpload" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="unmountShareUpload"
                                class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Unmount Share
                            </span>
                            <span wire:loading wire:target="unmountShareUpload" class="flex items-center gap-1">
                                <x-loading />
                                Unmounting...
                            </span>
                        </x-warning-button>

                    </div>
                </div>
            </div>

            {{-- CARD UPLOAD --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6 space-y-6">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                        <div>
                            <x-input-label value="Share Server (Upload)" />
                            <x-text-input wire:model.live="shareServerUpload" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div>
                            <x-input-label value="Mount Point (Upload)" />
                            <x-text-input wire:model.live="mountPointUpload" class="block w-full mt-1 font-mono"
                                placeholder="/mnt/target" />
                        </div>

                    </div>

                    @if ($statusMessageUpload !== '')
                        <div @class([
                            'p-4 rounded-xl border text-sm font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with(
                                $statusMessageUpload,
                                '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with(
                                $statusMessageUpload,
                                '✗'),
                        ])>
                            <div class="flex items-start gap-3">

                                @if (str_starts_with($statusMessageUpload, '✓'))
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif

                                <p>{{ $statusMessageUpload }}</p>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>
