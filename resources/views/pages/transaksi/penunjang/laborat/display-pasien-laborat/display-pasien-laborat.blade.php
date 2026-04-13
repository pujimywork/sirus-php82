<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    #[Reactive]
    public ?string $checkupNo = null;

    public array $headerData = [];
    public array $pasienData = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function rendering(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        if (empty($this->checkupNo)) {
            return;
        }

        $header = DB::table('lbtxn_checkuphdrs as a')
            ->join('rsmst_pasiens as c', 'a.reg_no', '=', 'c.reg_no')
            ->leftJoin('rsmst_doctors as d', 'a.dr_id', '=', 'd.dr_id')
            ->leftJoin('immst_employers as e', 'a.emp_id', '=', 'e.emp_id')
            ->select(
                'a.checkup_no',
                DB::raw("to_char(a.checkup_date,'dd/mm/yyyy hh24:mi:ss') as checkup_date"),
                'a.reg_no',
                'c.reg_name',
                'c.sex',
                DB::raw("to_char(c.birth_date,'dd/mm/yyyy') as birth_date"),
                'c.address',
                'a.dr_id',
                'd.dr_name',
                'a.emp_id',
                'e.emp_name',
                'a.checkup_status',
                'a.status_rjri',
                'a.ref_no',
            )
            ->where('a.checkup_no', $this->checkupNo)
            ->first();

        if (!$header) {
            return;
        }

        $this->headerData = (array) $header;

        // Hitung umur
        $umur = '-';
        if (!empty($header->birth_date)) {
            try {
                $tglLahir = Carbon::createFromFormat('d/m/Y', $header->birth_date);
                $diff = $tglLahir->diff(now());
                $umur = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Exception $e) {
                $umur = '-';
            }
        }

        $this->pasienData = [
            'regNo' => $header->reg_no,
            'regName' => $header->reg_name,
            'sex' => $header->sex,
            'sexDesc' => $header->sex === 'L' ? 'Laki-Laki' : ($header->sex === 'P' ? 'Perempuan' : '-'),
            'birthDate' => $header->birth_date ?? '-',
            'umur' => $umur,
            'address' => $header->address ?? '-',
            'drName' => $header->dr_name ?? '-',
            'empId' => $header->emp_id ?? null,
            'empName' => $header->emp_name ?? null,
            'checkupDate' => $header->checkup_date ?? '-',
            'statusRjri' => $header->status_rjri ?? '-',
            'refNo' => $header->ref_no ?? '-',
            'checkupStatus' => $header->checkup_status ?? '-',
        ];
    }
};
?>

<div>
    @if (!empty($pasienData))
        @php
            $p = $pasienData;

            $statusLabel = ['P' => 'Terdaftar', 'C' => 'Proses', 'H' => 'Selesai'];
            $statusColor = [
                'P' => 'bg-gray-100 text-gray-700 border-gray-200',
                'C' => 'bg-amber-100 text-amber-700 border-amber-200',
                'H' => 'bg-green-100 text-green-700 border-green-200',
            ];
            $st = $p['checkupStatus'] ?? '';
            $statusText = $statusLabel[$st] ?? $st;
            $statusClass = $statusColor[$st] ?? 'bg-gray-100 text-gray-600';

            $layanan = strtoupper($p['statusRjri'] ?? '');
            $layananColor = match($layanan) {
                'RJ' => 'bg-blue-100 text-blue-700 border-blue-200',
                'UGD' => 'bg-red-100 text-red-700 border-red-200',
                'RI' => 'bg-purple-100 text-purple-700 border-purple-200',
                default => 'bg-gray-100 text-gray-600 border-gray-200',
            };
        @endphp

        <div class="grid grid-cols-5 gap-3">

            {{-- KIRI: Detail Pasien --}}
            <div class="col-span-3">
                <div class="h-full px-4 py-3 space-y-1 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">
                    {{-- Nama + JK --}}
                    <div class="flex items-center gap-2">
                        <div
                            class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full shrink-0
                            {{ $p['sex'] === 'L' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                            {{ $p['sex'] === 'L' ? 'L' : 'P' }}
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $p['regName'] }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $p['sexDesc'] }}
                            </div>
                        </div>
                    </div>

                    {{-- No RM + Umur --}}
                    <div class="flex items-center gap-4">
                        <div>
                            <span class="text-gray-500">No RM:</span>
                            <span class="ml-1 font-mono font-medium text-gray-800 dark:text-gray-200">{{ $p['regNo'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Tgl Lahir:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $p['birthDate'] }}
                                ({{ $p['umur'] }})</span>
                        </div>
                    </div>

                    {{-- Alamat --}}
                    <div>
                        <span class="text-gray-500">Alamat:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $p['address'] }}</span>
                    </div>
                </div>
            </div>

            {{-- KANAN: Info Pemeriksaan --}}
            <div class="col-span-2">
                <div class="h-full px-4 py-3 space-y-2 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">

                    {{-- Layanan + Status --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $layananColor }}">
                                {{ $layanan ?: '-' }}
                            </span>
                            <span
                                class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-gray-500">No Checkup:</span>
                            <span class="ml-1 font-mono font-bold text-brand">{{ $checkupNo }}</span>
                        </div>
                    </div>

                    {{-- Dokter + Petugas --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Dokter:</span>
                            <span
                                class="ml-1 font-semibold text-brand dark:text-emerald-400">{{ $p['drName'] }}</span>
                        </div>
                    </div>

                    {{-- Tanggal --}}
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Tanggal:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $p['checkupDate'] }}</span>
                        </div>
                    </div>

                    {{-- Ref No --}}
                    @if ($p['refNo'] !== '-' && !empty($p['refNo']))
                        <div>
                            <span class="text-gray-500">Ref No ({{ $layanan }}):</span>
                            <span class="ml-1 font-mono text-gray-700 dark:text-gray-300">{{ $p['refNo'] }}</span>
                        </div>
                    @endif

                    {{-- Petugas --}}
                    <div>
                        <span class="text-gray-500">Petugas:</span>
                        @if (!empty($p['empName']))
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $p['empName'] }}</span>
                        @else
                            <span class="ml-1 italic text-gray-400 dark:text-gray-500">Belum ada petugas</span>
                        @endif
                    </div>

                </div>
            </div>

        </div>
    @else
        <div class="flex flex-col items-center justify-center py-6 text-gray-300 dark:text-gray-600">
            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien belum dimuat</p>
        </div>
    @endif
</div>
