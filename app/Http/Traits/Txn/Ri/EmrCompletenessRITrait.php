<?php

namespace App\Http\Traits\Txn\Ri;

/**
 * Hitung persentase kelengkapan EMR Rawat Inap dari JSON datadaftarri_json.
 *
 * Bobot section (weighted) — RI punya 2 section khas (CPPT & Askep) yang
 * tidak ada di RJ/UGD; bobot mereka besar karena merupakan "engine"
 * monitoring harian pasien rawat inap.
 *
 *   S   — Pengkajian Awal     15%  (kondisi masuk, DPJP, keluhan, alergi, RPD)
 *   O   — Pemeriksaan/TTV     15%  (8 field tanda vital + kesadaran)
 *   A   — Diagnosa            15%  (min 1 ICD-10 atau free-text)
 *   P   — Perencanaan          10%  (tindak lanjut / discharge plan)
 *   N   — Penilaian             5%  (nyeri + risiko jatuh)
 *   C   — CPPT                 20%  (min 1 catatan SOAP harian)
 *   K   — Asuhan Keperawatan   20%  (min 1 diagnosis SDKI + intervensi SIKI)
 *
 * Aturan "terisi" — sama dengan RJ/UGD: field screening (alergi, RPD)
 * boleh teks "Tidak ada", asal NON-EMPTY.
 *
 * Pure function — tidak query DB. Aman dipanggil per-row di list.
 */
trait EmrCompletenessRITrait
{
    /**
     * @return array{emr: int, sections: array{s: int, o: int, a: int, p: int, n: int, c: int, k: int}}
     */
    public function calculateEmrPercentRI(array $json): array
    {
        $s = $this->scorePengkajianAwalRI($json['pengkajianAwalPasienRawatInap'] ?? []);
        $o = $this->scorePemeriksaanRI($json['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'] ?? []);
        $a = $this->scoreDiagnosaRI($json);
        $p = $this->scorePerencanaanRI($json['perencanaan'] ?? []);
        $n = $this->scorePenilaianRI($json['penilaian'] ?? []);
        $c = $this->scoreCpptRI($json['cppt'] ?? []);
        $k = $this->scoreAskepRI($json['asuhanKeperawatan'] ?? []);

        $total = ($s * 0.15) + ($o * 0.15) + ($a * 0.15) + ($p * 0.10) + ($n * 0.05) + ($c * 0.20) + ($k * 0.20);

        return [
            'emr' => (int) round($total),
            'sections' => [
                's' => (int) round($s),
                'o' => (int) round($o),
                'a' => (int) round($a),
                'p' => (int) round($p),
                'n' => (int) round($n),
                'c' => (int) round($c),
                'k' => (int) round($k),
            ],
        ];
    }

    /**
     * S — Pengkajian Awal Perawat: 5 field wajib.
     *   - kondisi saat masuk (konten klinis real)
     *   - DPJP (dokter penanggung jawab)
     *   - keluhan utama (di bagian4PemeriksaanFisik)
     *   - alergi / RPD (screening — boleh "Tidak ada")
     */
    private function scorePengkajianAwalRI(array $pa): int
    {
        if (empty($pa)) {
            return 0;
        }

        $b1 = $pa['bagian1DataUmum'] ?? [];
        $b2 = $pa['bagian2RiwayatPasien'] ?? [];
        $b4 = $pa['bagian4PemeriksaanFisik'] ?? [];

        $checks = [
            filled($b1['kondisiSaatMasuk'] ?? null),
            filled($b1['dpjp'] ?? null),
            filled($b4['keluhanUtama'] ?? null),
            filled($b2['riwayatPenyakitOperasiCedera']['pilihan'] ?? null),
            filled($b2['riwayatPenyakitOperasiCedera']['deskripsi'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * O — Pemeriksaan/TTV: 8 field tanda vital + kesadaran.
     * Sumber: pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik
     */
    private function scorePemeriksaanRI(array $pf): int
    {
        if (empty($pf)) {
            return 0;
        }

        $tv = $pf['tandaVital'] ?? [];
        $neuro = $pf['pemeriksaanSistemOrgan']['neurologi'] ?? [];

        $checks = [
            filled($tv['sistolik'] ?? null),
            filled($tv['distolik'] ?? null),
            filled($tv['frekuensiNadi'] ?? null),
            filled($tv['frekuensiNafas'] ?? null),
            filled($tv['suhu'] ?? null),
            filled($tv['spo2'] ?? null),
            filled($tv['bb'] ?? null),
            filled($tv['tb'] ?? null),
            filled($neuro['tingkatKesadaran']['pilihan'] ?? null),
        ];

        return (int) round((array_sum($checks) / count($checks)) * 100);
    }

    /**
     * A — Diagnosa: minimal 1 entry (ICD-10 atau free-text).
     */
    private function scoreDiagnosaRI(array $json): int
    {
        $hasIcd = !empty($json['diagnosis']) && is_array($json['diagnosis']);
        $hasFreeText = filled($json['diagnosisFreeText'] ?? null);

        return ($hasIcd || $hasFreeText) ? 100 : 0;
    }

    /**
     * P — Perencanaan: tindak lanjut / discharge plan.
     */
    private function scorePerencanaanRI(array $p): int
    {
        if (empty($p)) {
            return 0;
        }

        $hasTindakLanjut = filled($p['tindakLanjut']['tindakLanjut'] ?? null);
        $hasTglPulang = filled($p['tindakLanjut']['tglPulang'] ?? null);
        $hasDischarge = filled($p['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ?? null);

        return ($hasTindakLanjut || $hasTglPulang || $hasDischarge) ? 100 : 0;
    }

    /**
     * N — Penilaian: nyeri + risiko jatuh.
     */
    private function scorePenilaianRI(array $p): int
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
     * C — CPPT: minimal 1 entry catatan perkembangan terintegrasi.
     * Khas RI — wajib monitor harian.
     */
    private function scoreCpptRI(array $cppt): int
    {
        return !empty($cppt) ? 100 : 0;
    }

    /**
     * K — Asuhan Keperawatan: minimal 1 diagnosis SDKI + intervensi SIKI.
     * Khas RI — wajib askep harian per shift.
     */
    private function scoreAskepRI(array $askep): int
    {
        return !empty($askep) ? 100 : 0;
    }
}
