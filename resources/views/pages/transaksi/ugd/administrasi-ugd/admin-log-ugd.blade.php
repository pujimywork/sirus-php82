<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public array $adminLogs = [];
    public string $filterCat = ''; // '' = semua | 'ADMIN' | 'MR'

    public function mount(): void
    {
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    #[On('administrasi-ugd.updated')]
    public function refresh(): void
    {
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    public function setFilter(string $cat): void
    {
        $this->filterCat = $cat;
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
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

<div class="space-y-4">
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Log Aktivitas</h3>
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
                <x-badge variant="gray">{{ count($adminLogs) }} entri</x-badge>
            </div>
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
                        <tr wire:key="admin-log-ugd-{{ $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
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
