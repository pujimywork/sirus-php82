<?php
// resources/views/pages/transaksi/ugd/emr-ugd/log-aktivitas/log-aktivitas-ugd.blade.php
// Modul Log Aktivitas UGD — audit terpadu admin + rekam medis (listen: emr-ugd.log-aktivitas.open)

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;
    public array $adminLogs = [];
    public string $filterCat = ''; // '' = semua | 'ADMIN' | 'MR'

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-log-aktivitas-ugd'];

    public function mount(): void
    {
        $this->registerAreas(['modal-log-aktivitas-ugd']);
    }

    #[On('emr-ugd.log-aktivitas.open')]
    public function open(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        // Log Aktivitas hanya untuk Manager ke atas (leveling README: level 3-4).
        if (!auth()->user()->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Manager ke atas yang dapat melihat Log Aktivitas.');
            return;
        }

        $this->rjNo = $rjNo;
        $this->filterCat = '';
        $this->loadLogs($rjNo);

        $this->incrementVersion('modal-log-aktivitas-ugd');
        $this->dispatch('open-modal', name: 'log-aktivitas-ugd'); // ← WAJIB ada ini
    }

    public function setFilter(string $cat): void
    {
        $this->filterCat = $cat;
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'log-aktivitas-ugd');
    }

    private function loadLogs(int $rjNo): void
    {
        $data = $this->findDataUGD($rjNo);
        $logs = $data['AdministrasiUGD']['userLogs'] ?? [];

        // Entri lama tanpa flag dianggap 'ADMIN'.
        if ($this->filterCat !== '') {
            $logs = array_values(array_filter(
                $logs,
                fn($l) => ($l['userLogCat'] ?? 'ADMIN') === $this->filterCat,
            ));
        }

        usort($logs, function ($a, $b) {
            $ta = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $a['userLogDate'] ?? '01/01/1970 00:00:00')->getTimestamp();
            $tb = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $b['userLogDate'] ?? '01/01/1970 00:00:00')->getTimestamp();
            return $tb <=> $ta;
        });
        $this->adminLogs = $logs;
    }
};
?>

<div>
    <x-modal name="log-aktivitas-ugd" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0"
            wire:key="{{ $this->renderKey('modal-log-aktivitas-ugd', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="log-aktivitas-display-pasien-ugd-header-{{ $rjNo }}" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Log Aktivitas</h3>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <span class="sr-only">Close</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-1">
                            @php
                                $tabs = ['' => 'Semua', 'ADMIN' => 'Administrasi', 'MR' => 'Rekam Medis'];
                            @endphp
                            @foreach ($tabs as $val => $label)
                                <button type="button" wire:click="setFilter('{{ $val }}')"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition
                                    {{ $filterCat === $val
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <x-badge variant="gray">{{ count($adminLogs) }} entri</x-badge>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($adminLogs as $log)
                                    @php $cat = $log['userLogCat'] ?? 'ADMIN'; @endphp
                                    <tr wire:key="log-aktivitas-ugd-{{ $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $log['userLogDate'] ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $cat === 'MR'
                                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                                {{ $cat === 'MR' ? 'Rekam Medis' : 'Administrasi' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $log['userLog'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $log['userLogDesc'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                            Belum ada log aktivitas
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
