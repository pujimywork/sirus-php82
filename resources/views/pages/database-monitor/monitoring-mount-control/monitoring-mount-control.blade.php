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

    /* ===============================
     | BULK ACTIONS — Mount All / Unmount All
     =============================== */
    public function mountAll(): void
    {
        // Mount semua share yang belum ter-mount. Skip yg sudah, biar idempotent.
        if (!$this->isMounted)        $this->mountShare();
        if (!$this->isMountedUpload)  $this->mountShareUpload();
        if (!$this->isMountedLab)     $this->mountShareLab();
        if (!$this->isMountedBpjs)    $this->mountShareBpjs();

        $this->dispatch('toast', type: 'success', message: '✓ Mount All selesai. Cek status tiap share.');
    }

    public function unmountAll(): void
    {
        // Unmount semua share yang sedang ter-mount.
        if ($this->isMounted)        $this->unmountShare();
        if ($this->isMountedUpload)  $this->unmountShareUpload();
        if ($this->isMountedLab)     $this->unmountShareLab();
        if ($this->isMountedBpjs)    $this->unmountShareBpjs();

        $this->dispatch('toast', type: 'success', message: '✓ Unmount All selesai.');
    }
};
?>

<div>
    {{-- PAGE HEADER --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Mounting Control
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-400">
                    Manajemen mount/unmount share folder jaringan (CIFS/SMB)
                </p>
            </div>

            {{-- BULK ACTIONS — Mount All / Unmount All --}}
            <div class="flex items-center gap-2">
                <x-primary-button type="button" wire:click="mountAll" wire:loading.attr="disabled"
                    wire:target="mountAll" class="whitespace-nowrap" title="Mount semua share yang belum tersambung">
                    <span wire:loading.remove wire:target="mountAll" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Mount All
                    </span>
                    <span wire:loading wire:target="mountAll" class="flex items-center gap-1">
                        <x-loading /> Mounting...
                    </span>
                </x-primary-button>

                <x-warning-button type="button" wire:click="unmountAll" wire:loading.attr="disabled"
                    wire:target="unmountAll" class="whitespace-nowrap" title="Unmount semua share yang sedang tersambung">
                    <span wire:loading.remove wire:target="unmountAll" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Unmount All
                    </span>
                    <span wire:loading wire:target="unmountAll" class="flex items-center gap-1">
                        <x-loading /> Unmounting...
                    </span>
                </x-warning-button>
            </div>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- =================================================== --}}
                {{-- LEFT (2/3): 4 SHARE SECTIONS                          --}}
                {{-- =================================================== --}}
                <div class="lg:col-span-2 space-y-6">

            {{-- ================================================================ --}}
            {{-- SECTION: RAD SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                {{-- HEADER BAR (di dalam card, bukan sticky) --}}
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-700 uppercase dark:text-gray-200">RAD Share</span>
                            @if ($isMounted)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMounted" wire:loading.attr="disabled"
                                wire:target="checkMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMounted">Cek Status</span>
                                <span wire:loading wire:target="checkMounted"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPoint" wire:loading.attr="disabled"
                                wire:target="createMountPoint" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPoint">Buat Folder</span>
                                <span wire:loading wire:target="createMountPoint"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShare" wire:loading.attr="disabled"
                                wire:target="mountShare" :disabled="$isMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShare">Mount</span>
                                <span wire:loading wire:target="mountShare"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShare" wire:loading.attr="disabled"
                                wire:target="unmountShare" :disabled="!$isMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShare">Unmount</span>
                                <span wire:loading wire:target="unmountShare"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                {{-- BODY: Form --}}
                <div class="p-5 space-y-4">
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

                    @if ($statusMessage !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessage, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessage, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessage }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: EMR SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-700 uppercase dark:text-gray-200">EMR Share</span>
                            @if ($isMountedUpload)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedUpload" wire:loading.attr="disabled"
                                wire:target="checkMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedUpload">Cek Status</span>
                                <span wire:loading wire:target="checkMountedUpload"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointUpload" wire:loading.attr="disabled"
                                wire:target="createMountPointUpload" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointUpload">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointUpload"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareUpload" wire:loading.attr="disabled"
                                wire:target="mountShareUpload" :disabled="$isMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareUpload">Mount</span>
                                <span wire:loading wire:target="mountShareUpload"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareUpload" wire:loading.attr="disabled"
                                wire:target="unmountShareUpload" :disabled="!$isMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareUpload">Unmount</span>
                                <span wire:loading wire:target="unmountShareUpload"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (EMR)" />
                        <x-text-input wire:model.live="shareServerUpload" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (EMR)" />
                        <x-text-input wire:model.live="mountPointUpload" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageUpload !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageUpload, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageUpload, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageUpload }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: LAB SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-700 uppercase dark:text-gray-200">Lab Share</span>
                            @if ($isMountedLab)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedLab" wire:loading.attr="disabled"
                                wire:target="checkMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedLab">Cek Status</span>
                                <span wire:loading wire:target="checkMountedLab"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointLab" wire:loading.attr="disabled"
                                wire:target="createMountPointLab" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointLab">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointLab"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareLab" wire:loading.attr="disabled"
                                wire:target="mountShareLab" :disabled="$isMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareLab">Mount</span>
                                <span wire:loading wire:target="mountShareLab"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareLab" wire:loading.attr="disabled"
                                wire:target="unmountShareLab" :disabled="!$isMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareLab">Unmount</span>
                                <span wire:loading wire:target="unmountShareLab"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (Lab)" />
                        <x-text-input wire:model.live="shareServerLab" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (Lab)" />
                        <x-text-input wire:model.live="mountPointLab" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageLab !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageLab, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageLab, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageLab }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: BPJS SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-700 uppercase dark:text-gray-200">BPJS Share</span>
                            @if ($isMountedBpjs)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedBpjs" wire:loading.attr="disabled"
                                wire:target="checkMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedBpjs">Cek Status</span>
                                <span wire:loading wire:target="checkMountedBpjs"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointBpjs" wire:loading.attr="disabled"
                                wire:target="createMountPointBpjs" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointBpjs">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointBpjs"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareBpjs" wire:loading.attr="disabled"
                                wire:target="mountShareBpjs" :disabled="$isMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareBpjs">Mount</span>
                                <span wire:loading wire:target="mountShareBpjs"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareBpjs" wire:loading.attr="disabled"
                                wire:target="unmountShareBpjs" :disabled="!$isMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareBpjs">Unmount</span>
                                <span wire:loading wire:target="unmountShareBpjs"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (BPJS)" />
                        <x-text-input wire:model.live="shareServerBpjs" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (BPJS)" />
                        <x-text-input wire:model.live="mountPointBpjs" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageBpjs !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageBpjs, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageBpjs, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageBpjs }}</p>
                        </div>
                    @endif
                </div>
            </div>

                </div> {{-- /lg:col-span-2 (left side: 4 share sections) --}}

                {{-- =================================================== --}}
                {{-- RIGHT (1/3): VISUAL ARSITEKTUR MOUNTING (sticky)     --}}
                {{-- =================================================== --}}
                <div class="lg:col-span-1 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">

            {{-- ================================================================ --}}
            {{-- VISUAL: ARSITEKTUR MOUNTING (compact, vertikal — fit kolom 1/3)   --}}
            {{-- ================================================================ --}}
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-gray-900 dark:to-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-900/40 backdrop-blur">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                        Konsep Mounting File Server
                    </h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        Alur Upload → Sync external → Mount read.
                    </p>
                </div>

                <div class="p-4 space-y-3">

                    {{-- 3 STEP — selalu vertikal, compact --}}
                    {{-- Step 1: Laravel Upload --}}
                    <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-[10px] font-bold">1</span>
                            <p class="text-xs font-bold text-blue-900 dark:text-blue-200">Laravel Upload (write)</p>
                        </div>
                        <p class="text-[11px] text-blue-800 dark:text-blue-300 leading-relaxed">User upload via UI → cache lokal:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded break-all text-blue-900 dark:text-blue-300">storage/app/private/<strong>upload</strong>/...</code>
                    </div>

                    {{-- Arrow ↓ sync --}}
                    <div class="flex items-center justify-center gap-2 text-purple-500 dark:text-purple-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide">sync</span>
                    </div>

                    {{-- Step 2: External Sync --}}
                    <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-500 text-white text-[10px] font-bold">2</span>
                            <p class="text-xs font-bold text-purple-900 dark:text-purple-200">External Sync (cron/rsync)</p>
                        </div>
                        <p class="text-[11px] text-purple-800 dark:text-purple-300 leading-relaxed">Program di luar Laravel pindahkan file:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded break-all text-purple-900 dark:text-purple-300">upload/ → \\172.8.8.12\xxx_path</code>
                    </div>

                    {{-- Arrow ↓ CIFS mount --}}
                    <div class="flex items-center justify-center gap-2 text-emerald-500 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide">CIFS mount</span>
                    </div>

                    {{-- Step 3: Mount Read --}}
                    <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white text-[10px] font-bold">3</span>
                            <p class="text-xs font-bold text-emerald-900 dark:text-emerald-200">Mount Read (canonical)</p>
                        </div>
                        <p class="text-[11px] text-emerald-800 dark:text-emerald-300 leading-relaxed">Laravel baca dari mount, fallback upload:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-white/80 dark:bg-gray-900/60 rounded break-all text-emerald-900 dark:text-emerald-300">storage/app/private/<strong>mount</strong>/...</code>
                    </div>

                    {{-- Mapping per share — STACKED CARDS bukan table (cocok kolom sempit) --}}
                    <div class="pt-2 mt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Mapping per Share</p>

                        @foreach ([
                            [
                                'name' => 'Radiologi',
                                'desc' => 'Saat petugas radiologi upload foto rontgen/USG/CT-scan dan hasil bacaan dokter radiolog.',
                                'upload' => 'upload/penunjang/radiologi',
                                'server' => '\\\\172.8.8.12\\rad_path',
                                'mount' => 'mount/penunjang/radiologi',
                            ],
                            [
                                'name' => 'Hasil Penunjang dari EMR (RJ/UGD/RI)',
                                'desc' => 'Saat perawat/dokter di EMR rawat jalan/UGD/rawat inap meng-attach hasil pemeriksaan penunjang dari luar (mis. EKG, hasil rujukan).',
                                'upload' => 'upload/penunjang/emr/uploadHasilPenunjang',
                                'server' => '\\\\172.8.8.12\\upload_path',
                                'mount' => 'mount/penunjang/emr/uploadHasilPenunjang',
                            ],
                            [
                                'name' => 'Laboratorium Luar',
                                'desc' => 'Saat petugas lab menerima hasil dari laboratorium rujukan luar dan upload PDF/JPG hasilnya.',
                                'upload' => 'upload/penunjang/lab-luar',
                                'server' => '\\\\172.8.8.12\\lab_path',
                                'mount' => 'mount/penunjang/lab-luar',
                            ],
                            [
                                'name' => 'Berkas Klaim BPJS',
                                'desc' => 'Saat casemix/admin upload berkas pengajuan klaim BPJS — SEP, Grouping, Rekam Medis, SKDP, dan dokumen lain-lain.',
                                'upload' => 'upload/bpjs',
                                'server' => '\\\\172.8.8.12\\bpjs_path',
                                'mount' => 'mount/bpjs',
                            ],
                        ] as $share)
                            <div class="mb-2 p-2.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 mb-0.5">{{ $share['name'] }}</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug mb-2">{{ $share['desc'] }}</p>
                                <div class="space-y-1 text-[10px] font-mono break-all">
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5 shrink-0"></span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $share['upload'] }}</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5 shrink-0"></span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $share['server'] }}</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $share['mount'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap gap-3 text-[10px] text-gray-500 dark:text-gray-400 mt-2">
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span>upload</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>file server</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>mount</span>
                        </div>
                    </div>

                    {{-- Note: kenapa pisah upload & mount --}}
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50">
                        <p class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 mb-1">💡 Kenapa pisah upload &amp; mount?</p>
                        <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">File medis bisa besar → simpan di NAS/SAN supaya disk Laravel tidak penuh + backup terpusat. <code class="px-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono">upload/</code> = cache sementara.</p>
                    </div>

                    {{-- Note: Exception User TTD --}}
                    <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700/50">
                        <p class="text-[11px] font-semibold text-rose-800 dark:text-rose-300 mb-1">⚠️ Exception: User TTD</p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed mb-1.5">
                            Tanda tangan karyawan <strong>tidak ikut mount/share</strong>. Tetap di <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">storage/app/public/UserTtd/</code>.
                        </p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed mb-1.5">
                            <strong>Alasan:</strong> TTD dipakai sebagai <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">&lt;img&gt;</code> di 7+ template PDF (RM, eresep, lab/rad, GC). DomPDF resolve relative ke <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">public/</code> — pindah ke share = refactor lintas template.
                        </p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed">
                            <strong>Konsekuensi:</strong> backup TTD prosedur terpisah; ukuran kecil per file, total tidak signifikan.
                        </p>
                    </div>
                </div>
            </div>

                </div> {{-- /lg:col-span-1 (right side: visual arsitektur) --}}
            </div> {{-- /grid (2:1 split) --}}

        </div>
    </div>
</div>
