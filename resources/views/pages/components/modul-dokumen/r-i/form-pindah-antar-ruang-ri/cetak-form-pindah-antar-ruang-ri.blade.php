<?php
// resources/views/pages/components/modul-dokumen/r-i/form-pindah-antar-ruang-ri/cetak-form-pindah-antar-ruang-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?string $riHdrNo = null;

    /* ===============================
     | OPEN & LANGSUNG CETAK (per-entry)
     =============================== */
    #[On('cetak-form-pindah-antar-ruang-ri.open')]
    public function open(string $riHdrNo, string $tglPindah): mixed
    {
        $this->riHdrNo = $riHdrNo;

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        // Pilih entry pindah yang dicetak (identifier tglPindah)
        $pindah = collect($dataRI['formPindahAntarRuangRI'] ?? [])->firstWhere('tglPindah', $tglPindah);
        if (empty($pindah)) {
            $this->dispatch('toast', type: 'error', message: 'Catatan pindah tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
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

        // Identitas RS
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'pindah' => $pindah,
            'dataRI' => $dataRI,
            'identitasRs' => $identitasRs,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.form-pindah-antar-ruang-ri.cetak-form-pindah-antar-ruang-ri-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'form-pindah-ri-' . ($dataRI['regNo'] ?? $riHdrNo) . '.pdf');
    }
};
?>
<div></div>
