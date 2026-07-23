<?php

namespace App\Http\Traits\Txn\Ugd;

/**
 * Hitung persentase kelengkapan EMR UGD dari JSON datadaftarugd_json.
 *
 * Bobot section (weighted):
 *   S — Anamnesa         15%
 *   O — Pemeriksaan      15%
 *   A — Diagnosa         20%
 *   P — Perencanaan      15%
 *   N — Penilaian        10%
 *   T — Triase/Screening 15%  (khusus UGD — wajib sebelum tindakan)
 *   K — Koding SNOMED    10%
 *
 * Aturan "terisi" — sama dengan RJ: field screening (alergi, RPD) boleh
 * teks "Tidak ada", asal NON-EMPTY. Dokter wajib explicit isi negatif.
 *
 * Pure function — tidak query DB. Aman dipanggil per-row di list.
 */
trait EmrCompletenessUGDTrait
{
    /**
     * @return array{emr: int, sections: array{s: int, o: int, a: int, p: int, n: int, t: int, k: int}}
     */
    public function calculateEmrPercentUGD(array $json): array
    {
        $s = $this->scoreAnamnesaUGD($json['anamnesa'] ?? []);
        $o = $this->scorePemeriksaanUGD($json['pemeriksaan'] ?? []);
        $a = $this->scoreDiagnosaUGD($json);
        $p = $this->scorePerencanaanUGD($json['perencanaan'] ?? []);
        $n = $this->scorePenilaianUGD($json['penilaian'] ?? []);
        $t = $this->scoreTriaseUGD($json['screening'] ?? []);
        $k = $this->scoreSnomedUGD($json['anamnesa'] ?? []);

        $total = ($s * 0.15) + ($o * 0.15) + ($a * 0.20) + ($p * 0.15) + ($n * 0.10) + ($t * 0.15) + ($k * 0.10);

        return [
            'emr' => (int) round($total),
            'sections' => [
                's' => (int) round($s),
                'o' => (int) round($o),
                'a' => (int) round($a),
                'p' => (int) round($p),
                'n' => (int) round($n),
                't' => (int) round($t),
                'k' => (int) round($k),
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

    /**
     * K — Koding SNOMED: field anamnesa yang punya slot SNOMED (Keluhan Utama & Alergi)
     * harus di-coding (snomedCode terisi), bukan sekadar free-text. Sama dengan RJ.
     *
     * Hanya field yang KONTENnya terisi yang dinilai — field kosong tak menyeret skor
     * (sudah dipenalti di Anamnesa; jangan double-penalti, jangan beri poin gratis
     * → return 0 bila belum ada konten).
     */
    private function scoreSnomedUGD(array $a): int
    {
        $fieldSnomed = [
            ['konten' => $a['keluhanUtama']['keluhanUtama'] ?? null, 'snomedCode' => $a['keluhanUtama']['snomedCode'] ?? null],
            ['konten' => $a['alergi']['alergi'] ?? null, 'snomedCode' => $a['alergi']['snomedCode'] ?? null],
        ];

        $fieldTerisi = array_filter($fieldSnomed, fn($field) => filled($field['konten']));
        if (empty($fieldTerisi)) {
            return 0; // belum ada konten yang bisa di-coding
        }

        $jumlahTerkode = array_sum(array_map(fn($field) => filled($field['snomedCode']) ? 1 : 0, $fieldTerisi));
        return (int) round(($jumlahTerkode / count($fieldTerisi)) * 100);
    }

    /**
     * Checklist per-section: label + filled status untuk setiap field yang
     * dihitung di score* methods. Dipakai modal info-kelengkapan-emr agar
     * user tahu field mana yang sudah/belum diisi (bukan cuma % total).
     *
     * @return array<string, array{label: string, weight: int, items: array<int, array{label: string, filled: bool}>}>
     */
    public function collectChecklistUGD(array $json): array
    {
        $a = $json['anamnesa'] ?? [];
        $p = $json['pemeriksaan'] ?? [];
        $r = $json['perencanaan'] ?? [];
        $n = $json['penilaian'] ?? [];
        $sc = $json['screening'] ?? [];
        $tv = $p['tandaVital'] ?? [];
        $nutrisi = $p['nutrisi'] ?? [];

        return [
            't' => [
                'label' => 'Triase / Screening',
                'weight' => 15,
                'items' => [
                    ['label' => 'Keluhan utama (versi triase)', 'filled' => filled($sc['keluhanUtama'] ?? null)],
                    ['label' => 'Pernafasan', 'filled' => filled($sc['pernafasan'] ?? null)],
                    ['label' => 'Kesadaran', 'filled' => filled($sc['kesadaran'] ?? null)],
                    ['label' => 'Nyeri dada', 'filled' => filled($sc['nyeriDada'] ?? null)],
                    ['label' => 'Prioritas pelayanan', 'filled' => filled($sc['prioritasPelayanan'] ?? null)],
                ],
            ],
            's' => [
                'label' => 'Anamnesa',
                'weight' => 15,
                'items' => [
                    ['label' => 'Keluhan utama', 'filled' => filled($a['keluhanUtama']['keluhanUtama'] ?? null)],
                    ['label' => 'Riwayat penyakit sekarang', 'filled' => filled($a['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? null)],
                    ['label' => 'Alergi (boleh "Tidak ada")', 'filled' => filled($a['alergi']['alergi'] ?? null)],
                    ['label' => 'Riwayat penyakit dahulu (boleh "Tidak ada")', 'filled' => filled($a['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? null)],
                    ['label' => 'Waktu datang', 'filled' => filled($a['pengkajianPerawatan']['jamDatang'] ?? null)],
                ],
            ],
            'o' => [
                'label' => 'Pemeriksaan',
                'weight' => 15,
                'items' => [
                    ['label' => 'Frekuensi nadi', 'filled' => filled($tv['frekuensiNadi'] ?? null)],
                    ['label' => 'Frekuensi napas', 'filled' => filled($tv['frekuensiNafas'] ?? null)],
                    ['label' => 'Suhu', 'filled' => filled($tv['suhu'] ?? null)],
                    ['label' => 'Tekanan darah sistolik', 'filled' => filled($tv['sistolik'] ?? null)],
                    ['label' => 'Tekanan darah distolik', 'filled' => filled($tv['distolik'] ?? null)],
                    ['label' => 'Tingkat kesadaran', 'filled' => filled($tv['tingkatKesadaran'] ?? null)],
                    ['label' => 'Berat badan', 'filled' => filled($nutrisi['bb'] ?? null)],
                    ['label' => 'Tinggi badan', 'filled' => filled($nutrisi['tb'] ?? null)],
                ],
            ],
            'a' => [
                'label' => 'Diagnosa',
                'weight' => 20,
                'items' => [
                    [
                        'label' => 'Minimal 1 diagnosa (ICD-10 atau free-text)',
                        'filled' => (!empty($json['diagnosis']) && is_array($json['diagnosis'])) || filled($json['diagnosisFreeText'] ?? null),
                    ],
                ],
            ],
            'p' => [
                'label' => 'Perencanaan',
                'weight' => 15,
                'items' => [
                    [
                        'label' => 'Terapi ATAU tindak lanjut',
                        'filled' => filled($r['terapi']['terapi'] ?? null) || filled($r['tindakLanjut']['tindakLanjut'] ?? null),
                    ],
                    ['label' => 'TTD dokter pemeriksa', 'filled' => filled($r['pengkajianMedis']['drPemeriksa'] ?? null)],
                ],
            ],
            'n' => [
                'label' => 'Penilaian',
                'weight' => 10,
                'items' => [
                    ['label' => 'Penilaian nyeri (min 1 entry)', 'filled' => !empty($n['nyeri'])],
                    ['label' => 'Risiko jatuh (min 1 entry)', 'filled' => !empty($n['resikoJatuh'])],
                ],
            ],
            'k' => [
                'label' => 'Koding SNOMED',
                'weight' => 10,
                'items' => [
                    [
                        'label' => 'Keluhan Utama ter-coding SNOMED (bila diisi)',
                        'filled' => blank($a['keluhanUtama']['keluhanUtama'] ?? null) || filled($a['keluhanUtama']['snomedCode'] ?? null),
                    ],
                    [
                        'label' => 'Alergi ter-coding SNOMED (bila diisi)',
                        'filled' => blank($a['alergi']['alergi'] ?? null) || filled($a['alergi']['snomedCode'] ?? null),
                    ],
                ],
            ],
        ];
    }
}
