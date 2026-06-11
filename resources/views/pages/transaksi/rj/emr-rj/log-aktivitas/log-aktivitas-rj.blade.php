<?php
// resources/views/pages/transaksi/rj/emr-rj/log-aktivitas/log-aktivitas-rj.blade.php
// Modul Log Aktivitas RJ — audit terpadu admin + rekam medis (listen: emr-rj.log-aktivitas.open)

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;
    public array $adminLogs = [];
    public string $filterCat = ''; // '' = semua | 'ADMIN' | 'MR'

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-log-aktivitas-rj'];

    public function mount(): void
    {
        $this->registerAreas(['modal-log-aktivitas-rj']);
    }

    #[On('emr-rj.log-aktivitas.open')]
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

        $this->incrementVersion('modal-log-aktivitas-rj');
        $this->dispatch('open-modal', name: 'log-aktivitas-rj'); // ← WAJIB ada ini
    }

    public function setFilter(string $cat): void
    {
        $this->filterCat = $cat;
        if ($this->rjNo) {
            $this->loadLogs((int) $this->rjNo);
        }
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'log-aktivitas-rj');
    }

    private function loadLogs(int $rjNo): void
    {
        $data = $this->findDataRJ($rjNo);
        $logs = $data['AdministrasiRJ']['userLogs'] ?? [];

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
    <x-modal name="log-aktivitas-rj" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0"
            wire:key="{{ $this->renderKey('modal-log-aktivitas-rj', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                            wire:key="log-aktivitas-display-pasien-rj-header-{{ $rjNo }}" />
                        <h3 class="mt-2 text-sm font-semibold text-body dark:text-gray-300">Log Aktivitas</h3>
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
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="flex items-center gap-1">
                            @php
                                $tabs = ['' => 'Semua', 'ADMIN' => 'Administrasi', 'MR' => 'Rekam Medis'];
                            @endphp
                            @foreach ($tabs as $val => $label)
                                <button type="button" wire:click="setFilter('{{ $val }}')"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition
                                    {{ $filterCat === $val
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-surface-soft text-muted hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <x-badge variant="gray">{{ count($adminLogs) }} entri</x-badge>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                @forelse ($adminLogs as $log)
                                    @php $cat = $log['userLogCat'] ?? 'ADMIN'; @endphp
                                    <tr wire:key="log-aktivitas-rj-{{ $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $log['userLogDate'] ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $cat === 'MR'
                                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                    : 'bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400' }}">
                                                {{ $cat === 'MR' ? 'Rekam Medis' : 'Administrasi' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-muted dark:text-gray-400 whitespace-nowrap">{{ $log['userLog'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-ink dark:text-gray-200">{{ $log['userLogDesc'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
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
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
