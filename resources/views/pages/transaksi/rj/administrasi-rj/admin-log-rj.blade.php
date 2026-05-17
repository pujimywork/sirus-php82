<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public ?int $rjNo = null;
    public array $adminLogs = [];

    public function mount(): void
    {
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    #[On('administrasi-rj.updated')]
    public function refresh(): void
    {
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    private function loadLogs(int $rjNo): void
    {
        $data = $this->findDataRJ($rjNo);
        $logs = $data['AdministrasiRJ']['userLogs'] ?? [];
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
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Admin Log</h3>
            <x-badge variant="gray">{{ count($adminLogs) }} entri</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($adminLogs as $log)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $log['userLogDate'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $log['userLog'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $log['userLogDesc'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada log administrasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
