<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    #[Reactive]
    public ?string $checkupNo = null;

    public array $headerData = []; // info pemeriksaan lab (checkuphdrs)
    public array $pasienData = []; // identitas pasien (MasterPasienTrait, struktur samain RJ/RI/UGD)

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
            ->leftJoin('rsmst_doctors as d', 'a.dr_id', '=', 'd.dr_id')
            ->leftJoin('immst_employers as e', 'a.emp_id', '=', 'e.emp_id')
            ->select(
                'a.checkup_no',
                DB::raw("to_char(a.checkup_date,'dd/mm/yyyy hh24:mi:ss') as checkup_date"),
                'a.reg_no',
                'd.dr_name',
                'a.emp_id',
                'e.emp_name',
                'a.checkup_status',
                'a.status_rjri',
                'a.ref_no',
                'a.klinis_desc',
            )
            ->where('a.checkup_no', $this->checkupNo)
            ->first();

        if (!$header) {
            return;
        }

        $this->headerData = (array) $header;
        // Identitas pasien via trait — struktur & tema disamakan dengan display RJ/RI/UGD
        // (termasuk No. telp, NIK, BPJS, RT/RW, umur on-the-fly dari birth_date).
        $this->pasienData = $this->findDataMasterPasien($header->reg_no) ?? [];
    }
};
?>

<div>
    @if (!empty($pasienData) && !empty($headerData))
        @php
            $p = $pasienData['pasien'] ?? [];
            $h = $headerData;

            $statusLabel = ['P' => 'Terdaftar', 'C' => 'Proses', 'H' => 'Selesai', 'F' => 'Batal'];
            $statusColor = [
                'P' => 'bg-surface-soft text-body border-hairline',
                'C' => 'bg-amber-100 text-amber-700 border-amber-200',
                'H' => 'bg-green-100 text-green-700 border-green-200',
                'F' => 'bg-error/10 text-error border-error/30',
            ];
            $st = $h['checkup_status'] ?? '';
            $statusText = $statusLabel[$st] ?? $st;
            $statusClass = $statusColor[$st] ?? 'bg-surface-soft text-muted';

            $layanan = strtoupper($h['status_rjri'] ?? '');
            $layananColor = match ($layanan) {
                'RJ' => 'bg-blue-100 text-blue-700 border-blue-200',
                'UGD' => 'bg-red-100 text-red-700 border-red-200',
                'RI' => 'bg-purple-100 text-purple-700 border-purple-200',
                default => 'bg-surface-soft text-muted border-hairline',
            };

            $alamat = trim($p['identitas']['alamat'] ?? '');
            $rt = trim($p['identitas']['rt'] ?? '');
            $rw = trim($p['identitas']['rw'] ?? '');
            $alamatLine = $alamat;
            if ($rt !== '' || $rw !== '') {
                $alamatLine .= " RT {$rt}/RW {$rw}";
            }

            $tglLahirRaw = $p['tglLahir'] ?? '';
            $tglLahirFmt = '-';
            if (!empty($tglLahirRaw)) {
                try {
                    $tglLahirFmt = \Carbon\Carbon::parse($tglLahirRaw)->format('d/m/Y');
                } catch (\Exception) {
                    $tglLahirFmt = $tglLahirRaw;
                }
            }
            $tempatLahir = trim($p['tempatLahir'] ?? '');
            $tglLahirLabel = $tempatLahir !== '' ? "{$tempatLahir}, {$tglLahirFmt}" : $tglLahirFmt;
        @endphp

        {{-- ================================================================
        | CARD UTAMA: Pasien (kiri) + Info Pemeriksaan Lab (kanan) — 1 card
        | Tema disamakan dengan display-pasien RJ/RI/UGD.
        ================================================================= --}}
        <div class="px-4 py-3 text-base leading-relaxed border border-hairline rounded-lg bg-canvas dark:bg-gray-900">
            <div class="grid grid-cols-5 gap-x-6 gap-y-2.5">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="col-span-3 space-y-2 sm:border-r sm:border-hairline dark:sm:border-gray-700 sm:pr-4">
                    {{-- Nama + No RM --}}
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xl font-bold text-ink dark:text-white">
                            {{ $p['regName'] ?? '-' }}
                        </span>
                        <span class="font-mono text-base text-muted dark:text-gray-400 shrink-0">
                            {{ $p['regNo'] ?? '-' }}
                        </span>
                    </div>

                    {{-- Detail: 2 kolom (demografi | kontak) --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">

                        {{-- Kolom kiri: Demografi --}}
                        <div class="space-y-1">
                            <div>
                                <span class="text-muted">Jenis Kelamin:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $p['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Umur:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $p['thn'] ?? 0 }} Thn {{ $p['bln'] ?? 0 }} Bln {{ $p['hari'] ?? 0 }} Hr
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Tgl Lahir:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $tglLahirLabel }}</span>
                            </div>
                        </div>

                        {{-- Kolom kanan: Kontak & Identitas --}}
                        <div class="space-y-1">
                            @if ($alamatLine !== '')
                                <div class="text-body dark:text-gray-300">
                                    📍 {{ $alamatLine }}
                                </div>
                            @endif

                            @if (!empty($p['kontak']['nomerTelponSelulerPasien']))
                                <div class="text-body dark:text-gray-300">
                                    📞 {{ $p['kontak']['nomerTelponSelulerPasien'] }}
                                </div>
                            @endif

                            <div class="text-xs font-mono text-muted dark:text-gray-400">
                                🆔
                                NIK: {{ $p['identitas']['nik'] ?? '-' }}
                                @if (!empty($p['identitas']['idbpjs']))
                                    • BPJS: {{ $p['identitas']['idbpjs'] }}
                                @endif
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== KANAN: Info Pemeriksaan Lab ===== --}}
                <div class="col-span-2 space-y-2">
                    {{-- BARIS 1: Layanan + Status | No Checkup --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $layananColor }}">
                                {{ $layanan ?: '-' }}
                            </span>
                            <span class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-muted">No Checkup:</span>
                            <span class="ml-1 font-mono font-bold text-brand">{{ $checkupNo }}</span>
                        </div>
                    </div>

                    {{-- BARIS 2: Dokter --}}
                    <div>
                        <span class="text-muted">Dokter:</span>
                        <span class="ml-1 font-semibold text-brand dark:text-brand-lime">{{ $h['dr_name'] ?? '-' }}</span>
                    </div>

                    {{-- BARIS 3: Diagnosis/Ket. Klinis (jika ada) --}}
                    @if (!empty($h['klinis_desc']))
                        <div>
                            <span class="text-muted">Diagnosis/Ket. Klinis:</span>
                            <span class="ml-1 font-medium text-amber-700 dark:text-amber-400">{{ $h['klinis_desc'] }}</span>
                        </div>
                    @endif

                    {{-- BARIS 4: Tanggal --}}
                    <div>
                        <span class="text-muted">Tanggal:</span>
                        <span class="ml-1 text-body dark:text-gray-300">{{ $h['checkup_date'] ?? '-' }}</span>
                    </div>

                    {{-- BARIS 5: Ref No (jika ada) --}}
                    @if (!empty($h['ref_no']))
                        <div>
                            <span class="text-muted">Ref No ({{ $layanan }}):</span>
                            <span class="ml-1 font-mono text-body dark:text-gray-300">{{ $h['ref_no'] }}</span>
                        </div>
                    @endif

                    {{-- BARIS 6: Petugas --}}
                    <div>
                        <span class="text-muted">Petugas:</span>
                        @if (!empty($h['emp_name']))
                            <span class="ml-1 text-body dark:text-gray-300">{{ $h['emp_name'] }}</span>
                        @else
                            <span class="ml-1 italic text-muted-soft dark:text-gray-500">Belum ada petugas</span>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien belum dimuat</p>
        </div>
    @endif
</div>
