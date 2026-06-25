<?php
// resources/views/pages/components/modul-dokumen/r-j/penundaan-pelayanan/cetak-penundaan-pelayanan-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?int $rjNo = null;
    public ?string $signatureDate = null;

    #[On('cetak-penundaan-pelayanan-rj.open')]
    public function open(int $rjNo, ?string $signatureDate = null): mixed
    {
        $this->rjNo = $rjNo;
        $this->signatureDate = $signatureDate;

        // ── 1. Data RJ ──
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return null;
        }

        $list = $dataRJ['penundaanPelayananRJ'] ?? [];
        if (empty($list)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada formulir penundaan yang tersimpan.');
            return null;
        }

        // Entri spesifik by signatureDate; jika kosong ambil terbaru.
        $form = empty($this->signatureDate)
            ? collect($list)->sortByDesc('signatureDate')->first()
            : collect($list)->firstWhere('signatureDate', $this->signatureDate);

        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir yang dipilih tidak ditemukan.');
            return null;
        }

        // ── 2. Data Pasien ──
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        // Hitung umur
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }

        // ── 3. Identitas RS ──
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();

        // ── 4. TTD Pemberi Informasi ──
        $ttdPemberiPath = null;
        $pemberiCode = $form['pemberiInfoCode'] ?? null;
        if ($pemberiCode) {
            $ttdPath = DB::table('users')->where('myuser_code', $pemberiCode)->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdPemberiPath = public_path('storage/' . $ttdPath);
            }
        }

        $data = array_merge($pasien, [
            'dataRJ' => $dataRJ,
            'form' => $form,
            'identitasRs' => $identitasRs,
            'ttdPemberiPath' => $ttdPemberiPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.penundaan-pelayanan.cetak-penundaan-pelayanan-rj-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'penundaan-pelayanan-rj-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
