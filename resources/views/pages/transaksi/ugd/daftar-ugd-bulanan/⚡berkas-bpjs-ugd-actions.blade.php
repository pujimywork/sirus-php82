<?php
// resources/views/pages/transaksi/rj/daftar-ugd-bulanan/⚡berkas-bpjs-ugd-actions.blade.php
//
// Sibling action component — Berkas BPJS untuk satu RJ.
// Listen 'berkas-bpjs.open' → load list rstxn_ugduploadbpjses, normalize ke 5 slot
// (1=SEP, 2=GROUPING, 3=REKAM MEDIS, 4=SKDP, 5=LAIN-LAIN).
// Per slot: Lihat / Upload / Replace / Hapus. Mirror pola sirus-lite:
//   - disk('local')->put('bpjs/' . filename, content)
//   - filename = Carbon::now()->format('dmYhis') . '.pdf'
//   - insert/update rstxn_ugduploadbpjses (rj_no, seq_file, uploadbpjs, jenis_file).

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use WithFileUploads, EmrUGDTrait, MasterPasienTrait;

    public ?int $berkasRjNo = null;
    public array $berkasFiles = [];

    /* Upload state */
    public ?int $uploadSlot = null;     // seq_file yang lagi di-upload
    public $uploadFile = null;           // file dari user

    private array $labels = [
        1 => 'SEP',
        2 => 'GROUPING',
        3 => 'REKAM MEDIS',
        4 => 'SKDP',
        5 => 'LAIN-LAIN',
    ];

    #[On('berkas-bpjs.open')]
    public function open(int $rjNo): void
    {
        $this->berkasRjNo = $rjNo;
        $this->refreshFiles();
        $this->dispatch('open-modal', name: 'berkas-bpjs-modal');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'berkas-bpjs-modal');
        $this->reset(['berkasRjNo', 'berkasFiles', 'uploadSlot', 'uploadFile']);
    }

    private function refreshFiles(): void
    {
        if (!$this->berkasRjNo) {
            $this->berkasFiles = [];
            return;
        }
        $rows = DB::table('rstxn_ugduploadbpjses')
            ->select('seq_file', 'uploadbpjs', 'jenis_file')
            ->where('rj_no', $this->berkasRjNo)
            ->orderBy('seq_file')
            ->get();

        $bySlot = [];
        foreach ([1, 2, 3, 4, 5] as $slot) {
            $bySlot[$slot] = ['label' => $this->labels[$slot], 'file' => null];
        }
        foreach ($rows as $r) {
            if (isset($bySlot[$r->seq_file])) {
                $bySlot[$r->seq_file]['file'] = $r->uploadbpjs;
            } else {
                $bySlot[$r->seq_file] = ['label' => 'LAIN-LAIN (#' . $r->seq_file . ')', 'file' => $r->uploadbpjs];
            }
        }
        $this->berkasFiles = $bySlot;
    }

    /* ===============================
     | UPLOAD (Insert / Replace)
     =============================== */
    public function selectSlot(int $slot): void
    {
        $this->uploadSlot = $slot;
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function cancelUpload(): void
    {
        $this->uploadSlot = null;
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function uploadBerkas(): void
    {
        $this->validate(
            [
                'uploadFile' => 'required|file|mimes:pdf,jpg,jpeg|max:5120',
                'uploadSlot' => 'required|integer|min:1',
                'berkasRjNo' => 'required|integer',
            ],
            [
                'uploadFile.required' => 'Pilih file dulu.',
                'uploadFile.mimes' => 'Format harus PDF atau JPG.',
                'uploadFile.max' => 'Ukuran maksimal 5 MB.',
            ],
        );

        try {
            $content = file_get_contents($this->uploadFile->getRealPath());
            $this->saveBerkasBpjs($this->berkasRjNo, $this->uploadSlot, $content);

            $label = $this->labels[$this->uploadSlot] ?? '';
            $this->cancelUpload();
            $this->dispatch('toast', type: 'success', message: "Berkas berhasil di-upload: {$label}");
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | GENERATE PDF AUTO — SEP (slot 1) & RM (slot 3)
     | Pattern: replicate cetak-sep.blade.php / cetak-rekam-medis-open.blade.php,
     | tapi save ke disk('local') folder bpjs/ (bukan streamDownload).
     =============================== */
    public function generateSep(): void
    {
        if (!$this->berkasRjNo) return;
        $rjNo = $this->berkasRjNo;

        try {
            $dataRJ = $this->findDataUGD($rjNo);
            if (empty($dataRJ) || empty($dataRJ['sep']['noSep'])) {
                $this->dispatch('toast', type: 'error', message: 'Data SEP tidak ditemukan untuk RJ ini.');
                return;
            }

            $sep = $dataRJ['sep'];
            $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
            $resSep = $sep['resSep'] ?? [];

            $regNo = $dataRJ['regNo'] ?? '';
            $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
            $pasien = $pasienData['pasien'] ?? [];

            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

            $dokterDpjp = $resSep['dpjp']['nmDPJP'] ?? null;
            $kodeDpjpReq = $reqSep['dpjpLayan'] ?? $reqSep['skdp']['kodeDPJP'] ?? '';
            if (empty($dokterDpjp) && !empty($kodeDpjpReq)) {
                $dokterDpjp = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $kodeDpjpReq)->value('dr_name');
            }
            if (empty($dokterDpjp)) {
                $dokterDpjp = $dataRJ['drDesc'] ?? '-';
            }

            $data = [
                'sep' => $sep,
                'reqSep' => $reqSep,
                'resSep' => $resSep,
                'dataTxn' => $dataRJ,
                'pasien' => $pasien,
                'jenis' => 'ugd',
                'identitasRs' => $identitasRs,
                'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
                'dokterDpjp' => $dokterDpjp,
            ];

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.b-p-j-s.cetak-sep.cetak-sep-print', ['data' => $data])
                ->setPaper('A5', 'landscape');

            $this->saveBerkasBpjs($rjNo, 1, $pdf->output());
            $this->dispatch('toast', type: 'success', message: 'PDF SEP berhasil di-generate & tersimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate SEP: ' . $e->getMessage());
        }
    }

    public function generateRm(): void
    {
        if (!$this->berkasRjNo) return;
        $rjNo = $this->berkasRjNo;

        try {
            $dataRJ = $this->findDataUGD($rjNo);
            if (empty($dataRJ)) {
                $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
                return;
            }

            $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
            if (empty($pasienData)) {
                $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
                return;
            }

            $pasien = $pasienData['pasien'];
            if (!empty($pasien['tglLahir'])) {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            }

            $dokter = DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->select('dr_name')->first();

            $data = array_merge($pasien, [
                'dataDaftarTxn' => $dataRJ,
                'namaDokter' => $dokter->dr_name ?? null,
                'tglCetak' => $dataRJ['rjDate'] ?? Carbon::now()->format('d/m/Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.rekam-medis.u-g-d.cetak-rekam-medis.cetak-rekam-medis-print', ['data' => $data])
                ->setPaper('A4');

            $this->saveBerkasBpjs($rjNo, 3, $pdf->output());
            $this->dispatch('toast', type: 'success', message: 'PDF Rekam Medis berhasil di-generate & tersimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate RM: ' . $e->getMessage());
        }
    }

    public function generateSkdp(): void
    {
        if (!$this->berkasRjNo) return;
        $rjNo = $this->berkasRjNo;

        try {
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD) || empty($dataUGD['kontrol']['tglKontrol'])) {
                $this->dispatch('toast', type: 'error', message: 'Data surat kontrol (SKDP) belum tersedia.');
                return;
            }

            $kontrol = $dataUGD['kontrol'];
            $sep = $dataUGD['sep'] ?? [];

            $regNo = $dataUGD['regNo'] ?? '';
            $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['tglLahirFormatted'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->translatedFormat('j F Y');
                } catch (\Throwable) {
                    $pasien['tglLahirFormatted'] = $pasien['tglLahir'];
                }
            }
            if (!empty($kontrol['tglKontrol'])) {
                try {
                    $kontrol['tglKontrolFormatted'] = Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->translatedFormat('j F Y');
                } catch (\Throwable) {
                    $kontrol['tglKontrolFormatted'] = $kontrol['tglKontrol'];
                }
            }

            $resSep = $sep['resSep'] ?? [];
            $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
            $diagnosa = $resSep['diagnosa'] ?? ($reqSep['diagAwal'] ?? '-');

            $identitasRs = DB::table('rsmst_identitases')->select('int_name')->first();

            $data = [
                'kontrol' => $kontrol,
                'pasien' => $pasien,
                'dataTxn' => $dataUGD,
                'diagnosa' => $diagnosa,
                'jenis' => 'ugd',
                'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
            ];

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.b-p-j-s.cetak-skdp.cetak-skdp-print', ['data' => $data])
                ->setPaper('A5', 'landscape');

            $this->saveBerkasBpjs($rjNo, 4, $pdf->output());
            $this->dispatch('toast', type: 'success', message: 'PDF SKDP berhasil di-generate & tersimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate SKDP: ' . $e->getMessage());
        }
    }

    /**
     * Helper: simpan PDF content ke disk('local') folder bpjs/ dan
     * insert/update record rstxn_ugduploadbpjses.
     */
    private function saveBerkasBpjs(int $rjNo, int $seqFile, string $pdfContent): void
    {
        $namespace = 'upload/bpjs';
        Storage::disk('local')->makeDirectory($namespace);
        $filename = Carbon::now(config('app.timezone'))->format('dmYHis') . '.pdf';
        $filePath = $namespace . '/' . $filename;

        $cekFile = DB::table('rstxn_ugduploadbpjses')
            ->where('rj_no', $rjNo)
            ->where('seq_file', $seqFile)
            ->first();

        Storage::disk('local')->put($filePath, $pdfContent);

        if (!Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException('Gagal menyimpan PDF ke storage.');
        }

        DB::transaction(function () use ($cekFile, $rjNo, $seqFile, $filename) {
            if ($cekFile) {
                if (!empty($cekFile->uploadbpjs)) {
                    if (Storage::disk('local')->exists('bpjs/' . $cekFile->uploadbpjs)) {
                        Storage::disk('local')->delete('bpjs/' . $cekFile->uploadbpjs);
                    }
                    if (Storage::disk('local')->exists('upload/bpjs/' . $cekFile->uploadbpjs)) {
                        Storage::disk('local')->delete('upload/bpjs/' . $cekFile->uploadbpjs);
                    }
                }
                DB::table('rstxn_ugduploadbpjses')
                    ->where('rj_no', $rjNo)
                    ->where('seq_file', $seqFile)
                    ->update(['uploadbpjs' => $filename, 'jenis_file' => 'pdf']);
            } else {
                DB::table('rstxn_ugduploadbpjses')->insert([
                    'rj_no' => $rjNo,
                    'seq_file' => $seqFile,
                    'uploadbpjs' => $filename,
                    'jenis_file' => 'pdf',
                ]);
            }
        });

        $this->refreshFiles();
    }

    /* ===============================
     | HAPUS file
     =============================== */
    public function hapusBerkas(int $slot): void
    {
        if (!$this->berkasRjNo) {
            return;
        }

        $row = DB::table('rstxn_ugduploadbpjses')
            ->where('rj_no', $this->berkasRjNo)
            ->where('seq_file', $slot)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'warning', message: 'File tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($row) {
                if (!empty($row->uploadbpjs)) {
                    if (Storage::disk('local')->exists('bpjs/' . $row->uploadbpjs)) {
                        Storage::disk('local')->delete('bpjs/' . $row->uploadbpjs);
                    }
                    if (Storage::disk('local')->exists('upload/bpjs/' . $row->uploadbpjs)) {
                        Storage::disk('local')->delete('upload/bpjs/' . $row->uploadbpjs);
                    }
                }
                DB::table('rstxn_ugduploadbpjses')
                    ->where('rj_no', $row->rj_no)
                    ->where('seq_file', $row->seq_file)
                    ->delete();
            });

            $this->refreshFiles();
            $this->dispatch('toast', type: 'success', message: 'Berkas dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-modal name="berkas-bpjs-modal" size="full" height="full" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Berkas BPJS
                        </h2>
                        <p class="text-xs text-gray-500">No. RJ:
                            <span class="font-mono font-medium">{{ $berkasRjNo ?? '-' }}</span>
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="px-6 py-5">
                <table class="w-full text-sm">
                    <thead class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">Slot</th>
                            <th class="px-3 py-2 text-left">Jenis Berkas</th>
                            <th class="px-3 py-2 text-left">File</th>
                            <th class="px-3 py-2 text-center w-64">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($berkasFiles as $slot => $info)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $slot }}</td>
                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
                                    {{ $info['label'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $info['file'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    @if ($uploadSlot === $slot)
                                        {{-- Upload form aktif untuk slot ini --}}
                                        <div class="space-y-2">
                                            <x-file-upload name="uploadFile" accept="application/pdf,image/jpeg" :show-error="false" />
                                            <div class="flex items-center justify-end gap-2">
                                                <x-primary-button type="button" wire:click="uploadBerkas"
                                                    wire:loading.attr="disabled" wire:target="uploadBerkas,uploadFile" class="text-xs">
                                                    <span wire:loading.remove wire:target="uploadBerkas,uploadFile">Upload</span>
                                                    <span wire:loading wire:target="uploadBerkas,uploadFile">...</span>
                                                </x-primary-button>
                                                <x-secondary-button type="button" wire:click="cancelUpload" class="text-xs">Batal</x-secondary-button>
                                            </div>
                                            @error('uploadFile')
                                                <p class="mt-1 text-xs text-right text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @else
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if (!empty($info['file']))
                                                <a href="{{ route('files.show', ['path' => 'mount/bpjs/' . $info['file']]) }}" target="_blank"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                                    Lihat
                                                </a>
                                            @endif

                                            {{-- Generate auto untuk slot 1 (SEP), 3 (RM), 4 (SKDP) --}}
                                            @if ($slot === 1)
                                                <x-info-button type="button" wire:click="generateSep"
                                                    wire:loading.attr="disabled" wire:target="generateSep" class="text-xs">
                                                    <span wire:loading.remove wire:target="generateSep">Generate</span>
                                                    <span wire:loading wire:target="generateSep">...</span>
                                                </x-info-button>
                                            @elseif ($slot === 3)
                                                <x-info-button type="button" wire:click="generateRm"
                                                    wire:loading.attr="disabled" wire:target="generateRm" class="text-xs">
                                                    <span wire:loading.remove wire:target="generateRm">Generate</span>
                                                    <span wire:loading wire:target="generateRm">...</span>
                                                </x-info-button>
                                            @elseif ($slot === 4)
                                                <x-info-button type="button" wire:click="generateSkdp"
                                                    wire:loading.attr="disabled" wire:target="generateSkdp" class="text-xs">
                                                    <span wire:loading.remove wire:target="generateSkdp">Generate</span>
                                                    <span wire:loading wire:target="generateSkdp">...</span>
                                                </x-info-button>
                                            @endif

                                            @if (!empty($info['file']))
                                                <x-secondary-button type="button" wire:click="selectSlot({{ $slot }})"
                                                    class="text-xs">Replace</x-secondary-button>
                                                <x-danger-button type="button" wire:click="hapusBerkas({{ $slot }})"
                                                    wire:confirm="Yakin hapus berkas {{ $info['label'] }}?" class="text-xs">Hapus</x-danger-button>
                                            @else
                                                <x-primary-button type="button" wire:click="selectSlot({{ $slot }})"
                                                    class="text-xs">Upload</x-primary-button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-sm text-center text-gray-400">
                                    Slot belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-gray-500">
                    Format file PDF, maks 10 MB. Disimpan di
                    <code class="px-1 bg-gray-100 rounded dark:bg-gray-800">storage/app/private/bpjs/</code>.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
