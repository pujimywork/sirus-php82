<?php

namespace App\Http\Traits\Txn\Ugd;

/**
 * Hitung persentase kelengkapan EMR UGD dari JSON datadaftarugd_json.
 *
 * Bobot section (weighted):
 *   S — Anamnesa       15%
 *   O — Pemeriksaan    20%
 *   A — Diagnosa       20%
 *   P — Perencanaan    20%
 *   N — Penilaian      10%
 *   T — Triase/Screening 15%  (khusus UGD — wajib sebelum tindakan)
 *
 * Aturan "terisi" — sama dengan RJ: field screening (alergi, RPD) boleh
 * teks "Tidak ada", asal NON-EMPTY. Dokter wajib explicit isi negatif.
 *
 * Pure function — tidak query DB. Aman dipanggil per-row di list.
 */
trait EmrCompletenessUGDTrait
{
    /**
     * @return array{emr: int, sections: array{s: int, o: int, a: int, p: int, n: int, t: int}}
     */
    public function calculateEmrPercentUGD(array $json): array
    {
        $s = $this->scoreAnamnesaUGD($json['anamnesa'] ?? []);
        $o = $this->scorePemeriksaanUGD($json['pemeriksaan'] ?? []);
        $a = $this->scoreDiagnosaUGD($json);
        $p = $this->scorePerencanaanUGD($json['perencanaan'] ?? []);
        $n = $this->scorePenilaianUGD($json['penilaian'] ?? []);
        $t = $this->scoreTriaseUGD($json['screening'] ?? []);

        $total = ($s * 0.15) + ($o * 0.20) + ($a * 0.20) + ($p * 0.20) + ($n * 0.10) + ($t * 0.15);

        return [
            'emr' => (int) round($total),
            'sections' => [
                's' => (int) round($s),
                'o' => (int) round($o),
                'a' => (int) round($a),
                'p' => (int) round($p),
                'n' => (int) round($n),
                't' => (int) round($t),
            ],
        ];
    }

    /**
     * S — Anamnesa: 5 field wajib (sama dengan RJ).
     */
    private function scoreAnamnesaUGD(array $a): int
    {
        if (empty($a)) {
            return 0;
        }

        $checks = [
            filled($a['keluhanUtama']['keluhanUtama'] ?? null),
            filled($a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? null),
            filled($a['alergi']['alergi'] ?? null),
            filled($a['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? null),
            filled($a['pengkajianPerawatan']['jamDatang'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * O — Pemeriksaan: 8 field tanda vital + nutrisi + kesadaran (sama dengan RJ).
     */
    private function scorePemeriksaanUGD(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $tv = $p['tandaVital'] ?? [];
        $nutrisi = $p['nutrisi'] ?? [];

        $checks = [
            filled($tv['frekuensiNadi'] ?? null),
            filled($tv['frekuensiNafas'] ?? null),
            filled($tv['suhu'] ?? null),
            filled($tv['sistolik'] ?? null),
            filled($tv['distolik'] ?? null),
            filled($tv['tingkatKesadaran'] ?? null),
            filled($nutrisi['bb'] ?? null),
            filled($nutrisi['tb'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * A — Diagnosa: minimal 1 entry (sama dengan RJ).
     */
    private function scoreDiagnosaUGD(array $json): int
    {
        $hasIcd = !empty($json['diagnosis']) && is_array($json['diagnosis']);
        $hasFreeText = filled($json['diagnosisFreeText'] ?? null);

        return ($hasIcd || $hasFreeText) ? 100 : 0;
    }

    /**
     * P — Perencanaan: 2 komponen (sama dengan RJ).
     *   - Treatment plan (terapi ATAU tindakLanjut)
     *   - TTD dokter pemeriksa
     */
    private function scorePerencanaanUGD(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $hasTerapi = filled($p['terapi']['terapi'] ?? null);
        $hasTindakLanjut = filled($p['tindakLanjut']['tindakLanjut'] ?? null);
        $hasTreatmentPlan = $hasTerapi || $hasTindakLanjut;

        $hasDrPemeriksa = filled($p['pengkajianMedis']['drPemeriksa'] ?? null);

        $score = ($hasTreatmentPlan ? 1 : 0) + ($hasDrPemeriksa ? 1 : 0);
        return (int) round(($score / 2) * 100);
    }

    /**
     * N — Penilaian: nyeri + risikoJatuh (sama dengan RJ).
     */
    private function scorePenilaianUGD(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $hasNyeri = !empty($p['nyeri']);
        $hasResikoJatuh = !empty($p['resikoJatuh']);

        $score = ($hasNyeri ? 1 : 0) + ($hasResikoJatuh ? 1 : 0);
        return (int) round(($score / 2) * 100);
    }

    /**
     * T — Triase / Screening awal UGD: 5 field wajib (keluhan, pernafasan,
     * kesadaran, nyeri dada, prioritas pelayanan). Sumber rules:
     * rm-screening-ugd-actions.blade.php → required di rules().
     *
     * Standar IGD: triase wajib SEBELUM tindakan apapun.
     */
    private function scoreTriaseUGD(array $s): int
    {
        if (empty($s)) {
            return 0;
        }

        $checks = [
            filled($s['keluhanUtama'] ?? null),
            filled($s['pernafasan'] ?? null),
            filled($s['kesadaran'] ?? null),
            filled($s['nyeriDada'] ?? null),
            filled($s['prioritasPelayanan'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }
}
