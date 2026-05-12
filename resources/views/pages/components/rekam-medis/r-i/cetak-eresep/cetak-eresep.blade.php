<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $slsNo = null;

    /* ═══════════════════════════════════════
     | OPEN — cetak e-resep RI per slsNo
     | Source:
     |   - imtxn_slshdrs (sls_no → rihdr_no, dr_id, sls_date)
     |   - rstxn_rihdrs.datadaftarri_json
     |       - eresepHdr[i] (matched by slsNo) → eresep + eresepRacikan (dokter)
     |       - apotekHdr[i] (matched by slsNo) → telaahResep + telaahObat (apoteker)
    ═══════════════════════════════════════ */
    #[On('cetak-eresep-ri.open')]
    public function open(int $slsNo): mixed
    {
        $this->slsNo = $slsNo;

        $hdr = DB::selectOne(
            "
            SELECT
                s.sls_no, s.rihdr_no, s.dr_id, s.reg_no,
                TO_CHAR(s.sls_date,'DD/MM/YYYY HH24:MI:SS') AS sls_date,
                r.klaim_id,
                rm.room_name,
                bg.bangsal_name
            FROM imtxn_slshdrs s
            JOIN rstxn_rihdrs r ON r.rihdr_no = s.rihdr_no
            LEFT JOIN (
                SELECT t.rihdr_no, MAX(t.trfr_no) AS trfr_no
                FROM rsmst_trfrooms t
                GROUP BY t.rihdr_no
            ) tlast ON tlast.rihdr_no = s.rihdr_no
            LEFT JOIN rsmst_trfrooms trf ON trf.rihdr_no = tlast.rihdr_no AND trf.trfr_no = tlast.trfr_no
            LEFT JOIN rsmst_rooms rm ON rm.room_id = trf.room_id
            LEFT JOIN rsmst_bangsals bg ON bg.bangsal_id = rm.bangsal_id
            WHERE s.sls_no = :slsno
            ",
            ['slsno' => $slsNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            return null;
        }

        $dataRI = $this->findDataRI((int) $hdr->rihdr_no);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return null;
        }

        // Cari eresepHdr (dokter) & apotekHdr (apoteker) yang matched slsNo
        $eresep = collect($dataRI['eresepHdr'] ?? [])->firstWhere('slsNo', $slsNo);
        $apotek = collect($dataRI['apotekHdr'] ?? [])->firstWhere('slsNo', $slsNo);

        if (empty($eresep)) {
            $this->dispatch('toast', type: 'error', message: 'Header resep tidak ditemukan di JSON RI.');
            return null;
        }

        // Build $dataDaftarPoliRJ-shaped payload utk reuse RJ-style template
        $payload = [
            'rjNo' => $slsNo,                                                 // alias untuk template
            'rjDate' => $hdr->sls_date,                                       // tanggal cetak
            'klaimId' => $hdr->klaim_id,
            'klaimStatus' => null,                                            // diisi dari klaim row di bawah
            'drId' => $hdr->dr_id,
            'regNo' => $hdr->reg_no,
            'eresep' => $eresep['eresep'] ?? [],
            'eresepRacikan' => $eresep['eresepRacikan'] ?? [],
            'telaahResep' => $apotek['telaahResep'] ?? [],
            'telaahObat' => $apotek['telaahObat'] ?? [],
            'poliDesc' => trim(($hdr->room_name ?? '') . ($hdr->bangsal_name ? ' (' . $hdr->bangsal_name . ')' : '')) ?: '-',
            'sep' => ['noSep' => $dataRI['vnoSep'] ?? '-'],
            'statusResep' => [],
            'statusPRB' => [],
            'perencanaan' => [],
        ];

        $pasienData = $this->findDataMasterPasien($hdr->reg_no);
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        if (!empty($pasienData['pasien']['tglLahir'])) {
            try {
                $pasienData['pasien']['thn'] = Carbon::createFromFormat('d/m/Y', $pasienData['pasien']['tglLahir'])
                    ->diff(Carbon::now(env('APP_TIMEZONE')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Exception $e) {
                $pasienData['pasien']['thn'] = '-';
            }
        }

        $klaim = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $hdr->klaim_id ?? '')
            ->select('klaim_status', 'klaim_desc')
            ->first();

        if ($klaim) {
            $payload['klaimStatus'] = $klaim->klaim_status;
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $hdr->dr_id ?? '')
            ->select('dr_name')
            ->first();

        $pdf = Pdf::loadView('pages.components.rekam-medis.r-i.cetak-eresep.cetak-eresep-print', [
            'dataDaftarPoliRJ' => $payload,
            'dataPasien' => $pasienData,
            'klaim' => $klaim,
            'dokter' => $dokter,
        ])->setPaper('A4');

        $filename = 'eresep-ri-' . ($hdr->reg_no ?? $slsNo) . '-' . $slsNo . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>
<div></div>
