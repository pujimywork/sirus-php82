<?php

use Livewire\Component;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessFailedException;

new class extends Component {
    public string $shareServer = '//172.8.8.12/rad_path/';
    public string $mountPoint = '/opt/lampp/htdocs/sirus-php82/storage/app/private/mount/penunjang/radiologi';
    // (RAD Share — foto + hasil bacaan share folder ini, di-mount dari //172.8.8.12/rad_path/)
    public string $shareServerUpload = '//172.8.8.12/upload_path/';
    public string $mountPointUpload = '/opt/lampp/htdocs/sirus-php82/storage/app/private/mount/penunjang/emr/uploadHasilPenunjang';
    public string $shareServerLab = '//172.8.8.12/lab_path/';
    public string $mountPointLab = '/opt/lampp/htdocs/sirus-php82/storage/app/private/mount/penunjang/lab-luar';
    public string $shareServerBpjs = '//172.8.8.12/bpjs_path/';
    public string $mountPointBpjs = '/opt/lampp/htdocs/sirus-php82/storage/app/private/mount/bpjs';
    public string $statusMessage = '';
    public string $statusMessageUpload = '';
    public string $statusMessageLab = '';
    public string $statusMessageBpjs = '';
    public bool $isMounted = false;
    public bool $isMountedUpload = false;
    public bool $isMountedLab = false;
    public bool $isMountedBpjs = false;

    public function mount(): void
    {
        $this->checkMounted();
        $this->checkMountedUpload();
        $this->checkMountedLab();
        $this->checkMountedBpjs();
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

    /**
     * Setup awal: bikin folder mount point bila belum ada.
     * Idempotent — aman dipanggil berulang. Recursive = true biar parent path ikut dibuat.
     */
    public function createMountPoint(): void
    {
        $this->createDirectorySafe($this->mountPoint, 'RAD');
    }

    public function createMountPointUpload(): void
    {
        $this->createDirectorySafe($this->mountPointUpload, 'EMR');
    }

    public function createMountPointLab(): void
    {
        $this->createDirectorySafe($this->mountPointLab, 'Lab');
    }

    public function createMountPointBpjs(): void
    {
        $this->createDirectorySafe($this->mountPointBpjs, 'BPJS');
    }

    private function createDirectorySafe(string $path, string $label): void
    {
        try {
            if (is_dir($path)) {
                $this->dispatch('toast', type: 'info', message: 'Folder ' . $label . ' sudah ada: ' . $path);
                return;
            }
            if (mkdir($path, 0775, true)) {
                $this->dispatch('toast', type: 'success', message: '✓ Folder ' . $label . ' dibuat: ' . $path);
            } else {
                $this->dispatch('toast', type: 'error', message: '✗ Gagal buat folder ' . $label . '.');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: '✗ Error: ' . $e->getMessage());
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

    public function mountShareLab(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerLab, $this->mountPointLab]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageLab = '✓ Mount berhasil di: ' . $this->mountPointLab;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageLab);
        } catch (ProcessFailedException $e) {
            $this->statusMessageLab = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        } catch (\Throwable $e) {
            $this->statusMessageLab = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        }

        $this->checkMountedLab();
    }

    public function unmountShareLab(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointLab]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageLab = '✓ Unmount berhasil dari: ' . $this->mountPointLab;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageLab);
        } catch (ProcessFailedException $e) {
            $this->statusMessageLab = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        } catch (\Throwable $e) {
            $this->statusMessageLab = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        }

        $this->checkMountedLab();
    }

    public function checkMountedLab(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointLab]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedLab = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedLab = false;
        } catch (\Throwable) {
            $this->isMountedLab = false;
        }
    }

    public function mountShareBpjs(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerBpjs, $this->mountPointBpjs]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageBpjs = '✓ Mount berhasil di: ' . $this->mountPointBpjs;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageBpjs);
        } catch (ProcessFailedException $e) {
            $this->statusMessageBpjs = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        } catch (\Throwable $e) {
            $this->statusMessageBpjs = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        }

        $this->checkMountedBpjs();
    }

    public function unmountShareBpjs(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointBpjs]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageBpjs = '✓ Unmount berhasil dari: ' . $this->mountPointBpjs;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageBpjs);
        } catch (ProcessFailedException $e) {
            $this->statusMessageBpjs = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        } catch (\Throwable $e) {
            $this->statusMessageBpjs = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        }

        $this->checkMountedBpjs();
    }

    public function checkMountedBpjs(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointBpjs]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedBpjs = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedBpjs = false;
        } catch (\Throwable) {
            $this->isMountedBpjs = false;
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

                        <x-secondary-button type="button" wire:click="createMountPoint" wire:loading.attr="disabled"
                            wire:target="createMountPoint" class="whitespace-nowrap" title="Buat folder mount point (mkdir -p, idempotent)">
                            <span wire:loading.remove wire:target="createMountPoint" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2zM12 12v6m-3-3h6" />
                                </svg>
                                Buat Folder
                            </span>
                            <span wire:loading wire:target="createMountPoint" class="flex items-center gap-1">
                                <x-loading />
                                Membuat...
                            </span>
                        </x-secondary-button>

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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">

                        <div class="sm:col-span-4">
                            <x-input-label value="Share Server" />
                            <x-text-input wire:model.live="shareServer" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div class="sm:col-span-8">
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

                    <span class="text-sm font-semibold text-gray-500 uppercase dark:text-gray-400">EMR Share</span>

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

                        <x-secondary-button type="button" wire:click="createMountPointUpload" wire:loading.attr="disabled"
                            wire:target="createMountPointUpload" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                            <span wire:loading.remove wire:target="createMountPointUpload" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2zM12 12v6m-3-3h6" />
                                </svg>
                                Buat Folder
                            </span>
                            <span wire:loading wire:target="createMountPointUpload" class="flex items-center gap-1">
                                <x-loading />
                                Membuat...
                            </span>
                        </x-secondary-button>

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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">

                        <div class="sm:col-span-4">
                            <x-input-label value="Share Server (EMR)" />
                            <x-text-input wire:model.live="shareServerUpload" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div class="sm:col-span-8">
                            <x-input-label value="Mount Point (EMR)" />
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

            {{-- ================================================================ --}}
            {{-- SECTION: LAB SHARE --}}
            {{-- ================================================================ --}}

            {{-- TOOLBAR LAB --}}
            <div
                class="sticky z-30 px-4 py-3 mt-6 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">

                    <span class="text-sm font-semibold text-gray-500 uppercase dark:text-gray-400">Lab Share</span>

                    @if ($isMountedLab)
                        <x-badge variant="success">Terhubung</x-badge>
                    @else
                        <x-badge variant="danger">Tidak Terhubung</x-badge>
                    @endif

                    <div class="flex items-center gap-2 ml-auto">

                        <x-ghost-button type="button" wire:click="checkMountedLab" wire:loading.attr="disabled"
                            wire:target="checkMountedLab" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="checkMountedLab" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Cek Status
                            </span>
                            <span wire:loading wire:target="checkMountedLab" class="flex items-center gap-1">
                                <x-loading />
                                Mengecek...
                            </span>
                        </x-ghost-button>

                        <x-secondary-button type="button" wire:click="createMountPointLab" wire:loading.attr="disabled"
                            wire:target="createMountPointLab" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                            <span wire:loading.remove wire:target="createMountPointLab" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2zM12 12v6m-3-3h6" />
                                </svg>
                                Buat Folder
                            </span>
                            <span wire:loading wire:target="createMountPointLab" class="flex items-center gap-1">
                                <x-loading />
                                Membuat...
                            </span>
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="mountShareLab" wire:loading.attr="disabled"
                            wire:target="mountShareLab" :disabled="$isMountedLab" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="mountShareLab" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Mount Share
                            </span>
                            <span wire:loading wire:target="mountShareLab" class="flex items-center gap-1">
                                <x-loading />
                                Mounting...
                            </span>
                        </x-primary-button>

                        <x-warning-button type="button" wire:click="unmountShareLab" wire:loading.attr="disabled"
                            wire:target="unmountShareLab" :disabled="!$isMountedLab" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="unmountShareLab"
                                class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Unmount Share
                            </span>
                            <span wire:loading wire:target="unmountShareLab" class="flex items-center gap-1">
                                <x-loading />
                                Unmounting...
                            </span>
                        </x-warning-button>

                    </div>
                </div>
            </div>

            {{-- CARD LAB --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6 space-y-6">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">

                        <div class="sm:col-span-4">
                            <x-input-label value="Share Server (Lab)" />
                            <x-text-input wire:model.live="shareServerLab" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div class="sm:col-span-8">
                            <x-input-label value="Mount Point (Lab)" />
                            <x-text-input wire:model.live="mountPointLab" class="block w-full mt-1 font-mono"
                                placeholder="/mnt/target" />
                        </div>

                    </div>

                    @if ($statusMessageLab !== '')
                        <div @class([
                            'p-4 rounded-xl border text-sm font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with(
                                $statusMessageLab,
                                '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with(
                                $statusMessageLab,
                                '✗'),
                        ])>
                            <div class="flex items-start gap-3">

                                @if (str_starts_with($statusMessageLab, '✓'))
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

                                <p>{{ $statusMessageLab }}</p>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: BPJS SHARE --}}
            {{-- ================================================================ --}}

            {{-- TOOLBAR BPJS --}}
            <div
                class="sticky z-30 px-4 py-3 mt-6 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">

                    <span class="text-sm font-semibold text-gray-500 uppercase dark:text-gray-400">BPJS Share</span>

                    @if ($isMountedBpjs)
                        <x-badge variant="success">Terhubung</x-badge>
                    @else
                        <x-badge variant="danger">Tidak Terhubung</x-badge>
                    @endif

                    <div class="flex items-center gap-2 ml-auto">

                        <x-ghost-button type="button" wire:click="checkMountedBpjs" wire:loading.attr="disabled"
                            wire:target="checkMountedBpjs" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="checkMountedBpjs" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Cek Status
                            </span>
                            <span wire:loading wire:target="checkMountedBpjs" class="flex items-center gap-1">
                                <x-loading />
                                Mengecek...
                            </span>
                        </x-ghost-button>

                        <x-secondary-button type="button" wire:click="createMountPointBpjs" wire:loading.attr="disabled"
                            wire:target="createMountPointBpjs" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                            <span wire:loading.remove wire:target="createMountPointBpjs" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2zM12 12v6m-3-3h6" />
                                </svg>
                                Buat Folder
                            </span>
                            <span wire:loading wire:target="createMountPointBpjs" class="flex items-center gap-1">
                                <x-loading />
                                Membuat...
                            </span>
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="mountShareBpjs" wire:loading.attr="disabled"
                            wire:target="mountShareBpjs" :disabled="$isMountedBpjs" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="mountShareBpjs" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Mount Share
                            </span>
                            <span wire:loading wire:target="mountShareBpjs" class="flex items-center gap-1">
                                <x-loading />
                                Mounting...
                            </span>
                        </x-primary-button>

                        <x-warning-button type="button" wire:click="unmountShareBpjs" wire:loading.attr="disabled"
                            wire:target="unmountShareBpjs" :disabled="!$isMountedBpjs" class="whitespace-nowrap">
                            <span wire:loading.remove wire:target="unmountShareBpjs"
                                class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Unmount Share
                            </span>
                            <span wire:loading wire:target="unmountShareBpjs" class="flex items-center gap-1">
                                <x-loading />
                                Unmounting...
                            </span>
                        </x-warning-button>

                    </div>
                </div>
            </div>

            {{-- CARD BPJS --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6 space-y-6">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">

                        <div class="sm:col-span-4">
                            <x-input-label value="Share Server (BPJS)" />
                            <x-text-input wire:model.live="shareServerBpjs" class="block w-full mt-1 font-mono"
                                placeholder="//host/share" />
                        </div>

                        <div class="sm:col-span-8">
                            <x-input-label value="Mount Point (BPJS)" />
                            <x-text-input wire:model.live="mountPointBpjs" class="block w-full mt-1 font-mono"
                                placeholder="/mnt/target" />
                        </div>

                    </div>

                    @if ($statusMessageBpjs !== '')
                        <div @class([
                            'p-4 rounded-xl border text-sm font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with(
                                $statusMessageBpjs,
                                '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with(
                                $statusMessageBpjs,
                                '✗'),
                        ])>
                            <div class="flex items-start gap-3">

                                @if (str_starts_with($statusMessageBpjs, '✓'))
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

                                <p>{{ $statusMessageBpjs }}</p>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- VISUAL: ARSITEKTUR MOUNTING                                       --}}
            {{-- ================================================================ --}}
            <div class="mt-8 bg-gradient-to-br from-slate-50 to-slate-100 dark:from-gray-900 dark:to-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-900/40 backdrop-blur">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        Konsep Mounting File Server
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Alur upload → sync external → mount read. File besar tidak membebani disk Laravel.
                    </p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 items-stretch">

                        {{-- Step 1: Laravel Upload --}}
                        <div class="relative p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-500 text-white text-xs font-bold">1</span>
                                <p class="text-sm font-bold text-blue-900 dark:text-blue-200">Laravel Upload</p>
                            </div>
                            <p class="text-xs text-blue-800 dark:text-blue-300 mb-2">User upload file via UI → write ke cache lokal:</p>
                            <code class="block px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded border border-blue-200 dark:border-blue-700/50 break-all text-blue-900 dark:text-blue-300">
                                storage/app/private/<strong>upload</strong>/...
                            </code>
                        </div>

                        {{-- Arrow 1 → 2 --}}
                        <div class="flex items-center justify-center">
                            <div class="hidden lg:flex flex-col items-center text-purple-500 dark:text-purple-400">
                                <span class="text-[10px] font-semibold uppercase tracking-wide mb-1">sync</span>
                                <svg class="w-12 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                            <div class="lg:hidden flex flex-col items-center text-purple-500 dark:text-purple-400">
                                <svg class="w-6 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                </svg>
                            </div>
                        </div>

                        {{-- Step 2: External Sync Program --}}
                        <div class="relative p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-500 text-white text-xs font-bold">2</span>
                                <p class="text-sm font-bold text-purple-900 dark:text-purple-200">External Sync</p>
                            </div>
                            <p class="text-xs text-purple-800 dark:text-purple-300 mb-2">Program luar Laravel (cron/rsync/watchdog) memindahkan file:</p>
                            <code class="block px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded border border-purple-200 dark:border-purple-700/50 break-all text-purple-900 dark:text-purple-300">
                                upload/ → \\172.8.8.12\xxx_path
                            </code>
                        </div>

                        {{-- Arrow 2 → 3 --}}
                        <div class="flex items-center justify-center">
                            <div class="hidden lg:flex flex-col items-center text-emerald-500 dark:text-emerald-400">
                                <span class="text-[10px] font-semibold uppercase tracking-wide mb-1">CIFS mount</span>
                                <svg class="w-12 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                            <div class="lg:hidden flex flex-col items-center text-emerald-500 dark:text-emerald-400">
                                <svg class="w-6 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                </svg>
                            </div>
                        </div>

                        {{-- Step 3: Mount Point --}}
                        <div class="relative p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold">3</span>
                                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-200">Mount Read</p>
                            </div>
                            <p class="text-xs text-emerald-800 dark:text-emerald-300 mb-2">Laravel baca file dari mount (default), fallback ke upload bila belum sync:</p>
                            <code class="block px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded border border-emerald-200 dark:border-emerald-700/50 break-all text-emerald-900 dark:text-emerald-300">
                                storage/app/private/<strong>mount</strong>/...
                            </code>
                        </div>

                    </div>

                    {{-- Detail mapping per share — table format --}}
                    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800">
                                    <th class="px-3 py-2">Share</th>
                                    <th class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                            <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span> Upload (Laravel write)
                                        </span>
                                    </th>
                                    <th class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400">
                                            <span class="inline-block w-2 h-2 rounded-full bg-purple-500"></span> File Server (172.8.8.12)
                                        </span>
                                    </th>
                                    <th class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span> Mount (CIFS read)
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-[11px] text-gray-700 dark:text-gray-300">
                                <tr>
                                    <td class="px-3 py-2 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">RAD</td>
                                    <td class="px-3 py-2">upload/penunjang/radiologi</td>
                                    <td class="px-3 py-2">\\172.8.8.12\rad_path</td>
                                    <td class="px-3 py-2">mount/penunjang/radiologi</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">EMR</td>
                                    <td class="px-3 py-2">upload/penunjang/emr/uploadHasilPenunjang</td>
                                    <td class="px-3 py-2">\\172.8.8.12\upload_path</td>
                                    <td class="px-3 py-2">mount/penunjang/emr/uploadHasilPenunjang</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">Lab</td>
                                    <td class="px-3 py-2">upload/penunjang/lab-luar</td>
                                    <td class="px-3 py-2">\\172.8.8.12\lab_path</td>
                                    <td class="px-3 py-2">mount/penunjang/lab-luar</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">BPJS</td>
                                    <td class="px-3 py-2">upload/bpjs</td>
                                    <td class="px-3 py-2">\\172.8.8.12\bpjs_path</td>
                                    <td class="px-3 py-2">mount/bpjs</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Note: kenapa pisah upload & mount --}}
                    <div class="mt-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="text-xs text-amber-800 dark:text-amber-300">
                                <p class="font-semibold mb-1">Kenapa pisah upload &amp; mount?</p>
                                <p class="leading-relaxed">File medis (foto radiologi, hasil PDF) bisa berukuran besar. Disimpan di file server (NAS/SAN) supaya disk server Laravel tidak penuh, dan backup terpusat. <code class="px-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono">upload/</code> hanya cache sementara sebelum di-sync.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Note: Exception User TTD --}}
                    <div class="mt-3 p-4 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700/50">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="text-xs text-rose-800 dark:text-rose-300 space-y-1.5">
                                <p class="font-semibold">⚠️ Exception: User TTD (Tanda Tangan)</p>
                                <p class="leading-relaxed">
                                    Gambar TTD karyawan <span class="font-bold">tidak ikut sistem mount/share</span> seperti file medis lainnya. Disimpan di disk publik Laravel (<code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">storage/app/public/UserTtd/</code>) dan tidak di-sync ke file server.
                                </p>
                                <p class="leading-relaxed">
                                    <span class="font-semibold">Alasannya:</span> TTD digunakan sebagai inline <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">&lt;img src&gt;</code> di banyak template PDF (cetak rekam medis, e-resep, hasil lab/radiologi, general consent). DomPDF resolve image src relatif ke folder <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">public/</code> — kalau file dipindah ke private/share, semua template harus diubah pakai absolute path → refactor lintas 7+ file template, risiko tinggi.
                                </p>
                                <p class="leading-relaxed">
                                    <span class="font-semibold">Konsekuensi:</span> Backup TTD perlu dilakukan terpisah dari prosedur backup file medis, dan disk server Laravel akan menampung semua TTD (ukuran kecil per file, total tidak signifikan).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
