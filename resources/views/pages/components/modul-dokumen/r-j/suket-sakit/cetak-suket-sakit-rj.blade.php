<?php
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

    /* ===============================
     | OPEN & LANGSUNG CETAK
     =============================== */
    #[On('cetak-suket-sakit-rj.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        // Ambil data JSON RJ dari DB
        $dataRJ = $this->findDataRJ($rjNo);

        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        // Ambil data pasien via regNo
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');

        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];
        $suketIstirahat = $dataRJ['suket']['suketIstirahat'] ?? [];

        // Hitung umur realtime
        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        // Ambil data dokter langsung dari DB berdasarkan drId
        $drId = $dataRJ['drId'] ?? '';
        $dokter = DB::table('rsmst_doctors')->where('dr_id', $drId)->select('dr_name')->first();

        // TTD dokter dari storage (users.myuser_code == rsmst_doctors.dr_id)
        $ttdDokterPath = null;
        if ($drId) {
            $ttdPath = DB::table('users')->where('myuser_code', $drId)->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdDokterPath = public_path('storage/' . $ttdPath);
            }
        }

        // Hitung tgl selesai istirahat
        $mulaiRaw = (string) ($suketIstirahat['mulaiIstirahat'] ?? Carbon::now()->format('d/m/Y'));
        // Strip suffix legacy " (Hari Ini)" / " (Besok)" supaya Carbon parse aman
        $mulai = trim(preg_replace('/\s*\(.+?\)\s*$/', '', $mulaiRaw)) ?: Carbon::now()->format('d/m/Y');
        $lamaHari = (int) ($suketIstirahat['suketIstirahatHari'] ?? 1);
        $tglSelesai = Carbon::createFromFormat('d/m/Y', $mulai)
            ->copy()
            ->addDays($lamaHari - 1)
            ->format('d/m/Y');

        $data = array_merge($pasien, [
            'lamaIstirahat' => $lamaHari,
            'tglMulai' => $mulai,
            'tglSelesai' => $tglSelesai,
            'namaDokter' => $dokter->dr_name ?? null,
            'ttdDokterPath' => $ttdDokterPath,
            'tglCetak' => Carbon::now()->translatedFormat('d F Y'),
        ]);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.suket-sakit.cetak-suket-sakit-rj-print', [
            'data' => $data,
        ])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'suket-sakit-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>

<div></div>
