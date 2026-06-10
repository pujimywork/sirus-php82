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

    /* View PDF (modal + iframe) — pola sama dengan radiologi/lab-luar-display */
    public string $viewFilePDF = '';
    public string $viewFileTitle = '';

    private array $labels = [
        1 => 'SEP',
        2 => 'GROUPING',
        3 => 'REKAM MEDIS',
        4 => 'SKDP',
        5 => 'LAIN-LAIN',
    ];

    private const SLOT_LAB_OFFSET = 100;
    private const SLOT_RAD_OFFSET = 200;

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
        $this->reset(['berkasRjNo', 'berkasFiles', 'uploadSlot', 'uploadFile', 'viewFilePDF', 'viewFileTitle']);
    }

    /* ===============================
     | View PDF — modal + iframe (sibling modal)
     | URL pakai route files.show (whitelist mount/bpjs, auto-fallback ke upload/bpjs/)
     =============================== */
    public function openViewPDF(?string $file, string $title = 'Lihat Berkas BPJS'): void
    {
        if (empty($file)) {
            $this->dispatch('toast', type: 'error', message: 'File tidak tersedia.');
            return;
        }
        $this->viewFilePDF = route('files.show', ['path' => 'mount/bpjs/' . $file]);
        $this->viewFileTitle = $title;
        $this->dispatch('open-modal', name: 'view-berkas-bpjs-ugd-pdf');
    }

    public function closeViewPDF(): void
    {
        $this->viewFilePDF = '';
        $this->viewFileTitle = '';
        $this->dispatch('close-modal', name: 'view-berkas-bpjs-ugd-pdf');
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
            $bySlot[$slot] = ['label' => $this->labels[$slot], 'file' => null, 'meta' => null];
        }

        // Slot dinamis Lab: 1 baris per checkup_no aktif.
        $labs = DB::table('lbtxn_checkuphdrs')
            ->where('ref_no', $this->berkasRjNo)
            ->where('status_rjri', 'UGD')
            ->where('checkup_status', '!=', 'B')
            ->orderBy('checkup_no')
            ->select('checkup_no', DB::raw("to_char(checkup_date,'dd/mm/yyyy hh24:mi') as cdate"))
            ->get();
        foreach ($labs as $idx => $lab) {
            $slot = self::SLOT_LAB_OFFSET + $idx;
            $bySlot[$slot] = [
                'label' => 'HASIL LAB #' . ($idx + 1) . ' — ' . $lab->checkup_no . ' (' . ($lab->cdate ?? '-') . ')',
                'file' => null,
                'meta' => ['type' => 'lab', 'checkup_no' => $lab->checkup_no],
            ];
        }

        // Slot dinamis Radiologi: 1 baris per rad_dtl.
        $rads = DB::table('rstxn_ugdrads as r')
            ->leftJoin('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->where('r.rj_no', $this->berkasRjNo)
            ->orderBy('r.rad_dtl')
            ->select('r.rad_dtl', 'm.rad_desc', DB::raw("to_char(r.waktu_entry,'dd/mm/yyyy hh24:mi') as rdate"))
            ->get();
        foreach ($rads as $idx => $rad) {
            $slot = self::SLOT_RAD_OFFSET + $idx;
            $bySlot[$slot] = [
                'label' => 'HASIL RADIOLOGI #' . ($idx + 1) . ' — ' . ($rad->rad_desc ?? '-') . ' (' . ($rad->rdate ?? '-') . ')',
                'file' => null,
                'meta' => ['type' => 'rad', 'rad_dtl' => (int) $rad->rad_dtl],
            ];
        }

        foreach ($rows as $r) {
            if (isset($bySlot[$r->seq_file])) {
                $bySlot[$r->seq_file]['file'] = $r->uploadbpjs;
            } else {
                $bySlot[$r->seq_file] = ['label' => 'LAIN-LAIN (#' . $r->seq_file . ')', 'file' => $r->uploadbpjs, 'meta' => null];
            }
        }
        ksort($bySlot);
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

    /**
     * Dipanggil setelah temp upload selesai (event livewire-upload-finish dari
     * hidden file input per-slot). Set slot lalu lanjut proses upload. Pola
     * 1-step: klik tombol Upload/Replace -> browse -> auto-save.
     */
    public function uploadBerkasForSlot(int $slot): void
    {
        $this->uploadSlot = $slot;
        $this->uploadBerkas();
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
     * Generate PDF 1 hasil lab untuk checkup_no tertentu → slot 100+idx.
     */
    public function generateLab(string $checkupNo, int $slot): void
    {
        if (!$this->berkasRjNo) return;
        if ($slot < self::SLOT_LAB_OFFSET) {
            $this->dispatch('toast', type: 'error', message: 'Slot tidak valid untuk Lab.');
            return;
        }
        $rjNo = $this->berkasRjNo;

        try {
            $valid = DB::table('lbtxn_checkuphdrs')
                ->where('ref_no', $rjNo)
                ->where('status_rjri', 'UGD')
                ->where('checkup_no', $checkupNo)
                ->where('checkup_status', '!=', 'B')
                ->exists();
            if (!$valid) {
                $this->dispatch('toast', type: 'error', message: 'Checkup tidak valid untuk UGD ini.');
                return;
            }

            set_time_limit(300);
            $header = collect(DB::select(
                "SELECT DISTINCT a.emp_id, a.checkup_no,
                        to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') AS checkup_date,
                        a.reg_no, reg_name, a.dr_id, dr_name,
                        sex, birth_date, c.address, emp_name,
                        waktu_selesai_pelayanan, checkup_kesimpulan
                 FROM lbtxn_checkuphdrs a
                 JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
                 JOIN rsmst_doctors f ON a.dr_id = f.dr_id
                 JOIN immst_employers g ON a.emp_id = g.emp_id
                 WHERE a.checkup_no = :cno",
                ['cno' => $checkupNo],
            ))->first();
            if (!$header) {
                throw new \RuntimeException('Header lab tidak ditemukan.');
            }

            $txn = DB::select(
                "SELECT b.clabitem_id, clabitem_desc, clab_desc, app_seq, item_seq,
                        lab_result, unit_desc, unit_convert, item_code,
                        normal_f, normal_m, high_limit_m, high_limit_f,
                        low_limit_m, low_limit_f, lowhigh_status, lab_result_status, d.nilai_kritis,
                        sex, a.dr_id, dr_name, a.emp_id, emp_name
                 FROM lbtxn_checkuphdrs a
                 JOIN lbtxn_checkupdtls b ON a.checkup_no = b.checkup_no
                 JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
                 JOIN lbmst_clabitems d ON b.clabitem_id = d.clabitem_id
                 JOIN lbmst_clabs e ON d.clab_id = e.clab_id
                 JOIN rsmst_doctors f ON a.dr_id = f.dr_id
                 JOIN immst_employers g ON a.emp_id = g.emp_id
                 WHERE a.checkup_no = :cno
                   AND nvl(hidden_status,'N') = 'N'
                 ORDER BY app_seq, item_seq, clabitem_desc",
                ['cno' => $checkupNo],
            );

            $txnLuar = DB::select(
                "SELECT ('  ' || labout_desc) AS labout_desc, labout_result, labout_normal
                 FROM lbtxn_checkuphdrs a
                 JOIN lbtxn_checkupoutdtls b ON a.checkup_no = b.checkup_no
                 WHERE a.checkup_no = :cno
                 ORDER BY labout_dtl, labout_desc",
                ['cno' => $checkupNo],
            );

            $pdf = Pdf::loadView(
                'pages.components.rekam-medis.penunjang.laboratorium-display.laboratorium-display-print',
                compact('header', 'txn', 'txnLuar'),
            )->setPaper('a4', 'portrait');

            $this->saveBerkasBpjs($rjNo, $slot, $pdf->output());
            $this->dispatch('toast', type: 'success', message: "Hasil lab {$checkupNo} di-generate & tersimpan.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate Laborat: ' . $e->getMessage());
        }
    }

    /**
     * Generate PDF 1 hasil radiologi untuk rad_dtl tertentu → slot 200+idx.
     */
    public function generateRadiologi(int $radDtl, int $slot): void
    {
        if (!$this->berkasRjNo) return;
        if ($slot < self::SLOT_RAD_OFFSET) {
            $this->dispatch('toast', type: 'error', message: 'Slot tidak valid untuk Radiologi.');
            return;
        }
        $rjNo = $this->berkasRjNo;

        try {
            $row = DB::table('rstxn_ugdrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->where('r.rj_no', $rjNo)
                ->where('r.rad_dtl', $radDtl)
                ->first([
                    'p.reg_no', 'p.reg_name', 'p.sex', 'p.birth_date', 'p.address',
                    'm.rad_desc', 'r.dr_pengirim', 'r.dr_radiologi', 'r.keterangan',
                    'r.waktu_entry', 'r.hasil_bacaan',
                ]);
            if (!$row) {
                $this->dispatch('toast', type: 'error', message: 'Data radiologi tidak ditemukan.');
                return;
            }
            if (is_resource($row->hasil_bacaan ?? null)) {
                $row->hasil_bacaan = stream_get_contents($row->hasil_bacaan);
            }

            set_time_limit(120);
            $pdf = Pdf::loadView(
                'pages.components.rekam-medis.penunjang.radiologi-display.radiologi-display-print',
                ['header' => $row],
            )->setPaper('A4', 'portrait');

            $this->saveBerkasBpjs($rjNo, $slot, $pdf->output());
            $this->dispatch('toast', type: 'success', message: "Hasil radiologi #{$radDtl} di-generate & tersimpan.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate Radiologi: ' . $e->getMessage());
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
     | GABUNG semua slot → 1 PDF (download saja, tidak disimpan)
     | - PDF langsung dipakai; non-PDF (JPG) di-render via DomPDF dulu.
     | - Merge pakai pdfunite (poppler-utils).
     =============================== */
    public function gabungBerkas()
    {
        if (!$this->berkasRjNo) {
            return null;
        }

        $rjNo = $this->berkasRjNo;
        $this->refreshFiles();

        $tmpRel = 'tmp/bpjs-merge-' . $rjNo . '-' . uniqid();
        Storage::disk('local')->makeDirectory($tmpRel);
        $tmpDir = Storage::disk('local')->path($tmpRel);

        $tempFiles = [];

        try {
            $pdfPaths = [];
            $skipped = 0;

            foreach ($this->berkasFiles as $slot => $info) {
                $filename = $info['file'] ?? null;
                if (empty($filename)) {
                    continue;
                }

                $sourcePath = $this->resolveBerkasPath($filename);
                if (!$sourcePath) {
                    $skipped++;
                    continue;
                }

                $fh = @fopen($sourcePath, 'rb');
                if (!$fh) {
                    $skipped++;
                    continue;
                }
                $header = fread($fh, 4);
                fclose($fh);

                if ($header === '%PDF') {
                    $pdfPaths[] = $sourcePath;
                } else {
                    set_time_limit(120);
                    $mime = @mime_content_type($sourcePath) ?: 'image/jpeg';
                    $imgBase64 = base64_encode(file_get_contents($sourcePath));
                    $html = '<html><body style="margin:0;padding:0;text-align:center;">'
                        . '<img src="data:' . $mime . ';base64,' . $imgBase64 . '" style="max-width:100%; height:auto;" />'
                        . '</body></html>';
                    $convPath = $tmpDir . '/conv_' . $slot . '.pdf';
                    file_put_contents($convPath, Pdf::loadHTML($html)->setPaper('A4')->output());
                    $pdfPaths[] = $convPath;
                    $tempFiles[] = $convPath;
                }
            }

            if (empty($pdfPaths)) {
                $this->dispatch('toast', type: 'warning', message: 'Belum ada berkas untuk digabung.');
                return null;
            }

            $outputPath = $tmpDir . '/merged.pdf';
            $tempFiles[] = $outputPath;

            if (count($pdfPaths) === 1) {
                copy($pdfPaths[0], $outputPath);
            } else {
                $cmd = 'pdfunite ' . implode(' ', array_map('escapeshellarg', $pdfPaths))
                    . ' ' . escapeshellarg($outputPath) . ' 2>&1';
                exec($cmd, $cmdOutput, $exitCode);

                if ($exitCode !== 0 || !file_exists($outputPath)) {
                    throw new \RuntimeException('pdfunite gagal (exit ' . $exitCode . '): ' . implode(' | ', $cmdOutput));
                }
            }

            $mergedContent = file_get_contents($outputPath);
            $length = strlen($mergedContent);

            $msg = 'Berhasil gabungkan ' . count($pdfPaths) . ' berkas.';
            if ($skipped > 0) {
                $msg .= ' (' . $skipped . ' dilewati)';
            }
            $this->dispatch('toast', type: 'success', message: $msg);

            // Nama file = No. SEP (sanitized). Fallback ke rj_no kalau SEP belum ada.
            $noSep = $this->findDataUGD($rjNo)['sep']['noSep'] ?? null;
            $base = !empty($noSep) ? preg_replace('/[^A-Za-z0-9\-]/', '', $noSep) : (string) $rjNo;
            $downloadName = $base . '.pdf';

            return response()->streamDownload(
                fn() => print($mergedContent),
                $downloadName,
                ['Content-Type' => 'application/pdf', 'Content-Length' => $length],
            );
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal gabungkan: ' . $e->getMessage());
            return null;
        } finally {
            foreach ($tempFiles as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    private function resolveBerkasPath(string $filename): ?string
    {
        foreach (['mount/bpjs/', 'upload/bpjs/'] as $prefix) {
            if (Storage::disk('local')->exists($prefix . $filename)) {
                return Storage::disk('local')->path($prefix . $filename);
            }
        }
        return null;
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
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                            Berkas BPJS
                        </h2>
                        <p class="text-xs text-muted">No. RJ:
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
                    <thead class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">Slot</th>
                            <th class="px-3 py-2 text-left">Jenis Berkas</th>
                            <th class="px-3 py-2 text-left">File</th>
                            <th class="px-3 py-2 text-center w-64">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @forelse ($berkasFiles as $slot => $info)
                            <tr wire:key="berkas-bpjs-ugd-slot-{{ $slot }}">
                                <td class="px-3 py-2 font-mono text-xs text-muted">{{ $slot }}</td>
                                <td class="px-3 py-2 font-medium text-body dark:text-gray-300">
                                    {{ $info['label'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-muted dark:text-gray-400">
                                    {{ $info['file'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Hidden file input per-slot. Klik tombol Upload/Replace
                                             trigger input ini via Alpine $refs. Livewire-upload-finish
                                             event lanjut panggil uploadBerkasForSlot($slot). --}}
                                        <input type="file" wire:model="uploadFile"
                                            x-ref="uploadInput{{ $slot }}"
                                            accept="application/pdf,image/jpeg" class="hidden"
                                            x-on:livewire-upload-finish="$wire.uploadBerkasForSlot({{ $slot }})">

                                        @if (!empty($info['file']))
                                            <x-outline-button type="button"
                                                wire:click="openViewPDF({{ json_encode($info['file']) }}, {{ json_encode($info['label'] ?? 'Lihat Berkas BPJS') }})"
                                                class="text-xs">Lihat</x-outline-button>
                                        @endif

                                        {{-- Generate auto:
                                             slot 1 = SEP, slot 3 = RM, slot 4 = SKDP,
                                             slot 100+ = Lab (per checkup_no),
                                             slot 200+ = Radiologi (per rad_dtl). --}}
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
                                        @elseif (($info['meta']['type'] ?? null) === 'lab')
                                            <x-info-button type="button"
                                                wire:click="generateLab('{{ $info['meta']['checkup_no'] }}', {{ $slot }})"
                                                wire:loading.attr="disabled"
                                                wire:target="generateLab('{{ $info['meta']['checkup_no'] }}', {{ $slot }})"
                                                class="text-xs">
                                                <span wire:loading.remove wire:target="generateLab('{{ $info['meta']['checkup_no'] }}', {{ $slot }})">Generate</span>
                                                <span wire:loading wire:target="generateLab('{{ $info['meta']['checkup_no'] }}', {{ $slot }})">...</span>
                                            </x-info-button>
                                        @elseif (($info['meta']['type'] ?? null) === 'rad')
                                            <x-info-button type="button"
                                                wire:click="generateRadiologi({{ $info['meta']['rad_dtl'] }}, {{ $slot }})"
                                                wire:loading.attr="disabled"
                                                wire:target="generateRadiologi({{ $info['meta']['rad_dtl'] }}, {{ $slot }})"
                                                class="text-xs">
                                                <span wire:loading.remove wire:target="generateRadiologi({{ $info['meta']['rad_dtl'] }}, {{ $slot }})">Generate</span>
                                                <span wire:loading wire:target="generateRadiologi({{ $info['meta']['rad_dtl'] }}, {{ $slot }})">...</span>
                                            </x-info-button>
                                        @endif

                                        @if (!empty($info['file']))
                                            <x-secondary-button type="button"
                                                x-on:click="$refs.uploadInput{{ $slot }}.click()"
                                                wire:loading.attr="disabled" wire:target="uploadBerkasForSlot,uploadFile"
                                                class="text-xs">
                                                <span wire:loading.remove wire:target="uploadBerkasForSlot,uploadFile">Replace</span>
                                                <span wire:loading wire:target="uploadBerkasForSlot,uploadFile">...</span>
                                            </x-secondary-button>
                                            <x-danger-button type="button" wire:click="hapusBerkas({{ $slot }})"
                                                wire:confirm="Yakin hapus berkas {{ $info['label'] }}?" class="text-xs">Hapus</x-danger-button>
                                        @else
                                            <x-primary-button type="button"
                                                x-on:click="$refs.uploadInput{{ $slot }}.click()"
                                                wire:loading.attr="disabled" wire:target="uploadBerkasForSlot,uploadFile"
                                                class="text-xs">
                                                <span wire:loading.remove wire:target="uploadBerkasForSlot,uploadFile">Upload</span>
                                                <span wire:loading wire:target="uploadBerkasForSlot,uploadFile">...</span>
                                            </x-primary-button>
                                        @endif
                                    </div>
                                    @error('uploadFile')
                                        <p class="mt-1 text-xs text-right text-red-500">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-sm text-center text-muted-soft">
                                    Slot belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-muted">
                    Format file PDF, maks 10 MB. Disimpan di
                    <code class="px-1 bg-surface-soft rounded dark:bg-gray-800">storage/app/private/bpjs/</code>.
                </p>
            </div>

            <div class="flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-900/40">
                <x-info-button type="button" wire:click="gabungBerkas"
                    wire:loading.attr="disabled" wire:target="gabungBerkas">
                    <span wire:loading.remove wire:target="gabungBerkas">Gabung jadi 1 PDF</span>
                    <span wire:loading wire:target="gabungBerkas">Menggabungkan...</span>
                </x-info-button>
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>

    {{-- ──────────────────────────────────────────────────────────────────
         MODAL: PDF Viewer (iframe pakai route files.show)
         Sibling modal — terbuka di atas berkas-bpjs-modal saat klik Lihat.
    ────────────────────────────────────────────────────────────────────── --}}
    <x-modal name="view-berkas-bpjs-ugd-pdf" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-berkas-bpjs-ugd-{{ $viewFilePDF }}">
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                    {{ $viewFileTitle ?: 'Lihat Berkas BPJS' }}
                </h2>
                <div class="flex items-center gap-2">
                    @if ($viewFilePDF)
                        <a href="{{ $viewFilePDF }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 text-xs font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Buka di Tab Baru
                        </a>
                    @endif
                    <x-icon-button color="gray" type="button" wire:click="closeViewPDF">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 p-2 bg-surface-soft dark:bg-gray-900">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"
                        type="application/pdf"></iframe>
                @endif
            </div>
        </div>
    </x-modal>
</div>
