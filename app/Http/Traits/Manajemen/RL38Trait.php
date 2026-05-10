<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.8 Laboratorium (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ 138 jenis pemeriksaan resmi SIRS dibagi 22 grup (Hematologi, Kimia  │
 * │ Klinik, Imunologi, Urinalisis, Hemostasis, Mikroskopis TBC, Biakan, │
 * │ Molekuler, Mikroskopis Parasit, Pemeriksaan Jamur, Sitopatologi,    │
 * │ Histopatologi, Imunopatologi, Patologi Molekuler, Potong Beku),     │
 * │ + 1 row "0 - Tidak Ada Data" sebagai fallback.                      │
 * │                                                                     │
 * │ 4 kolom data per row:                                               │
 * │   - Jumlah Pemeriksaan: Laki-Laki | Perempuan                       │
 * │   - Rata-Rata Pemeriksaan/hari: Laki-Laki | Perempuan               │
 * │     (= Jumlah / hari_buka_lab)                                      │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ SUMBER DATA                                                         │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Item-level lab dari lbtxn_checkupdtls (1 row = 1 item kayak Hb,     │
 * │ GDS, dll), JOIN ke:                                                 │
 * │   - lbtxn_checkuphdrs (checkup_date filter, reg_no → pasien.sex)    │
 * │   - lbmst_clabitems   (clabitem_desc untuk classifier)              │
 * │   - rsmst_pasiens     (sex)                                         │
 * │                                                                     │
 * │ Filter: d.price IS NOT NULL (item billable, exclude header/grup     │
 * │ master kayak HEMATOLOGI 5DIFF parent row).                          │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING (KEYWORD-BASED)                                             │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ classifyRl38Item($clabitemDesc) — keyword match clabitem_desc       │
 * │ ke 138 SIRS row. Pattern dicek berurutan; yang paling spesifik      │
 * │ harus didahulukan (mis. "ANTI HBS" sebelum "HBS").                  │
 * │                                                                     │
 * │ Item yang tidak match keyword apa pun → row "0 Tidak Ada Data".     │
 * │                                                                     │
 * │ Item RS yg tidak ada bucket SIRS resmi (TOXOPLASMA, LEPTOSPIRA,     │
 * │ TUBEX, CEA, TESTOSTERON, PROCALCITONIN, TROPONIN, DLL) tetap        │
 * │ jatuh ke "0 Tidak Ada Data".                                        │
 * │                                                                     │
 * │ Items result-based (BTA result split per Negatif/1+/2+/3+, TCM TBC  │
 * │ result split) di-bucket ke 1 row generic karena hasil pemeriksaan   │
 * │ tidak tersimpan struktural per-result. Kalau mau split, butuh       │
 * │ baca lab_result text di lbtxn_checkupdtls dan parse.                │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL38Trait
{
    /**
     * Daftar 138 Jenis Pemeriksaan RL 3.8 SIRS Kemenkes + "0 Tidak Ada Data".
     */
    public const JENIS_PEMERIKSAAN_LIST = [
        // ── Grup 1: Hematologi ───────────────────────────────────────
        ['id' => '1.1', 'grup' => 'Hematologi', 'nama' => 'Kadar Hemoglobin'],
        ['id' => '1.2', 'grup' => 'Hematologi', 'nama' => 'Nilai Hematokrit'],
        ['id' => '1.3', 'grup' => 'Hematologi', 'nama' => 'Hitung Lekosit'],
        ['id' => '1.4', 'grup' => 'Hematologi', 'nama' => 'Hitung Eritrosit'],
        ['id' => '1.5', 'grup' => 'Hematologi', 'nama' => 'Hitung Eosinophil'],
        ['id' => '1.6', 'grup' => 'Hematologi', 'nama' => 'Hitung Jenis Lekosit (%/absolut)'],
        ['id' => '1.7', 'grup' => 'Hematologi', 'nama' => 'Laju Endap Darah'],
        ['id' => '1.8', 'grup' => 'Hematologi', 'nama' => 'Hitung Retikulosit'],
        ['id' => '1.9', 'grup' => 'Hematologi', 'nama' => 'Hitung Trombosit'],

        // ── Grup 2: Kimia Klinik ─────────────────────────────────────
        ['id' => '2.1',  'grup' => 'Kimia Klinik', 'nama' => 'Protein Total'],
        ['id' => '2.2',  'grup' => 'Kimia Klinik', 'nama' => 'Albumin'],
        ['id' => '2.3',  'grup' => 'Kimia Klinik', 'nama' => 'Globulin'],
        ['id' => '2.4',  'grup' => 'Kimia Klinik', 'nama' => 'Bilirubin Total/Direk/Indirek'],
        ['id' => '2.5',  'grup' => 'Kimia Klinik', 'nama' => 'SGOT/AST'],
        ['id' => '2.6',  'grup' => 'Kimia Klinik', 'nama' => 'SGPT/ALT'],
        ['id' => '2.7',  'grup' => 'Kimia Klinik', 'nama' => 'Ureum/BUN'],
        ['id' => '2.8',  'grup' => 'Kimia Klinik', 'nama' => 'Kreatinin (eGFR)'],
        ['id' => '2.9',  'grup' => 'Kimia Klinik', 'nama' => 'Asam Urat'],
        ['id' => '2.10', 'grup' => 'Kimia Klinik', 'nama' => 'Trigliserida'],
        ['id' => '2.11', 'grup' => 'Kimia Klinik', 'nama' => 'Kolesterol Total'],
        ['id' => '2.12', 'grup' => 'Kimia Klinik', 'nama' => 'Kolesterol HDL'],
        ['id' => '2.13', 'grup' => 'Kimia Klinik', 'nama' => 'Kolesterol LDL (direk)'],
        ['id' => '2.14', 'grup' => 'Kimia Klinik', 'nama' => 'Glukosa Sewaktu/Puasa / 2jam PP'],
        ['id' => '2.15', 'grup' => 'Kimia Klinik', 'nama' => 'HbA1c'],
        ['id' => '2.16', 'grup' => 'Kimia Klinik', 'nama' => 'Fosfatase alkali'],
        ['id' => '2.17', 'grup' => 'Kimia Klinik', 'nama' => 'Gamma GT'],
        ['id' => '2.18', 'grup' => 'Kimia Klinik', 'nama' => 'LDH'],
        ['id' => '2.19', 'grup' => 'Kimia Klinik', 'nama' => 'G 6 PD'],
        ['id' => '2.20', 'grup' => 'Kimia Klinik', 'nama' => 'Amilase'],
        ['id' => '2.21', 'grup' => 'Kimia Klinik', 'nama' => 'Lipase'],
        ['id' => '2.22', 'grup' => 'Kimia Klinik', 'nama' => 'Cholinesterase'],
        ['id' => '2.23', 'grup' => 'Kimia Klinik', 'nama' => 'CK Total - CK MB'],
        ['id' => '2.24', 'grup' => 'Kimia Klinik', 'nama' => 'SI/TIBC'],
        ['id' => '2.25', 'grup' => 'Kimia Klinik', 'nama' => 'Elektrolit Darah (Na, K, Cl, Ca, Mg, P)'],
        ['id' => '2.26', 'grup' => 'Kimia Klinik', 'nama' => 'Analisa Gas Darah'],

        // ── Grup 3: Imunologi Klinik ─────────────────────────────────
        ['id' => '3.1',  'grup' => 'Imunologi Klinik', 'nama' => 'Widal'],
        ['id' => '3.2',  'grup' => 'Imunologi Klinik', 'nama' => 'Antibodi anti SARS-CoV-2'],
        ['id' => '3.3',  'grup' => 'Imunologi Klinik', 'nama' => 'Antigen SARS-CoV-2'],
        ['id' => '3.4',  'grup' => 'Imunologi Klinik', 'nama' => 'Dengue IgG-IgM'],
        ['id' => '3.5',  'grup' => 'Imunologi Klinik', 'nama' => 'HBs Ag'],
        ['id' => '3.6',  'grup' => 'Imunologi Klinik', 'nama' => 'Anti HBs'],
        ['id' => '3.7',  'grup' => 'Imunologi Klinik', 'nama' => 'Anti HBc'],
        ['id' => '3.8',  'grup' => 'Imunologi Klinik', 'nama' => 'Anti HBe'],
        ['id' => '3.9',  'grup' => 'Imunologi Klinik', 'nama' => 'Hbe Ag'],
        ['id' => '3.10', 'grup' => 'Imunologi Klinik', 'nama' => 'Anti HCV'],
        ['id' => '3.11', 'grup' => 'Imunologi Klinik', 'nama' => 'IgM Anti HAV'],
        ['id' => '3.12', 'grup' => 'Imunologi Klinik', 'nama' => 'Anti HIV'],
        ['id' => '3.13', 'grup' => 'Imunologi Klinik', 'nama' => 'NS1 (non structure antigen) Dengue'],
        ['id' => '3.14', 'grup' => 'Imunologi Klinik', 'nama' => 'Tes Antigen Malaria'],
        ['id' => '3.15', 'grup' => 'Imunologi Klinik', 'nama' => 'T3/T4 total'],
        ['id' => '3.16', 'grup' => 'Imunologi Klinik', 'nama' => 'FT3/FT4'],
        ['id' => '3.17', 'grup' => 'Imunologi Klinik', 'nama' => 'TSH'],

        // ── Grup 4: Urinalisis dan Analisis Cairan ───────────────────
        ['id' => '4.1', 'grup' => 'Urinalisis dan analisis cairan', 'nama' => 'Protein/albumin'],
        ['id' => '4.2', 'grup' => 'Urinalisis dan analisis cairan', 'nama' => 'Urobilinogen'],
        ['id' => '4.3', 'grup' => 'Urinalisis dan analisis cairan', 'nama' => 'Bilirubin'],
        ['id' => '4.4', 'grup' => 'Urinalisis dan analisis cairan', 'nama' => 'Sedimen Urine'],
        ['id' => '4.5', 'grup' => 'Urinalisis dan analisis cairan', 'nama' => 'NAPZA Skrining'],

        // ── Grup 5: Hemostasis ───────────────────────────────────────
        ['id' => '5.1', 'grup' => 'Hemostasis', 'nama' => 'Masa perdarahan'],
        ['id' => '5.2', 'grup' => 'Hemostasis', 'nama' => 'Masa pembekuan'],
        ['id' => '5.3', 'grup' => 'Hemostasis', 'nama' => 'Masa prothrombin plasma'],
        ['id' => '5.4', 'grup' => 'Hemostasis', 'nama' => 'Masa tromboplastin partial teraktivasi'],
        ['id' => '5.5', 'grup' => 'Hemostasis', 'nama' => 'Masa thrombin'],
        ['id' => '5.6', 'grup' => 'Hemostasis', 'nama' => 'Fibrinogen'],
        ['id' => '5.7', 'grup' => 'Hemostasis', 'nama' => 'D-dimer'],
        ['id' => '5.8', 'grup' => 'Hemostasis', 'nama' => 'Lupus anticoagulant'],

        // ── Grup 6: Pemeriksaan Dahak Mikroskopis TBC ────────────────
        ['id' => '6.1', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA (Mycobakterium tuberkulosis) Negatif'],
        ['id' => '6.2', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA 1-9'],
        ['id' => '6.3', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA 1+'],
        ['id' => '6.4', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA 2+'],
        ['id' => '6.5', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA 3+'],
        ['id' => '6.6', 'grup' => 'Mikroskopis TBC', 'nama' => 'BTA Tidak Dilakukan'],

        // ── Grup 7-9: Biakan ─────────────────────────────────────────
        ['id' => '7.1', 'grup' => 'Biakan Bakteri Aerob', 'nama' => 'Biakan + identifikasi + uji kepekaan antibiotik'],
        ['id' => '8.1', 'grup' => 'Biakan Virus',         'nama' => 'Biakan virus + uji kepekaan antivirus'],
        ['id' => '9.1', 'grup' => 'Biakan M. tuberculosis','nama' => 'Biakan + identifikasi + uji kepekaan OAT'],

        // ── Grup 10: Molekuler Virus DNA/RNA ─────────────────────────
        ['id' => '10.1', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'PCR'],
        ['id' => '10.2', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'Real time PCR'],
        ['id' => '10.3', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'Tes Cepat Molekuler'],
        ['id' => '10.4', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'Hibridisasi'],
        ['id' => '10.5', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'Sekuensing'],
        ['id' => '10.6', 'grup' => 'Molekuler Virus DNA/RNA', 'nama' => 'Metode lainnya'],

        // ── Grup 11: TCM TBC ─────────────────────────────────────────
        ['id' => '11.1', 'grup' => 'TCM TBC', 'nama' => 'Negatif'],
        ['id' => '11.2', 'grup' => 'TCM TBC', 'nama' => 'Rif Sen'],
        ['id' => '11.3', 'grup' => 'TCM TBC', 'nama' => 'Rif Res'],
        ['id' => '11.4', 'grup' => 'TCM TBC', 'nama' => 'Rif Indet'],
        ['id' => '11.5', 'grup' => 'TCM TBC', 'nama' => 'Invalid'],
        ['id' => '11.6', 'grup' => 'TCM TBC', 'nama' => 'Error'],
        ['id' => '11.7', 'grup' => 'TCM TBC', 'nama' => 'No Result'],
        ['id' => '11.8', 'grup' => 'TCM TBC', 'nama' => 'Tidak Dilakukan'],

        // ── Grup 12: Molekuler Bakteri ───────────────────────────────
        ['id' => '12.1', 'grup' => 'Molekuler Bakteri', 'nama' => 'PCR'],
        ['id' => '12.2', 'grup' => 'Molekuler Bakteri', 'nama' => 'Real time PCR'],
        ['id' => '12.3', 'grup' => 'Molekuler Bakteri', 'nama' => 'Tes Cepat Molekuler'],
        ['id' => '12.4', 'grup' => 'Molekuler Bakteri', 'nama' => 'Hibridisasi'],
        ['id' => '12.5', 'grup' => 'Molekuler Bakteri', 'nama' => 'Sekuensing'],
        ['id' => '12.6', 'grup' => 'Molekuler Bakteri', 'nama' => 'Metode lainnya'],

        // ── Grup 13: Molekuler Resistensi Antimikroba ────────────────
        ['id' => '13.1', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'PCR'],
        ['id' => '13.2', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'Real time PCR'],
        ['id' => '13.3', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'Tes Cepat Molekuler'],
        ['id' => '13.4', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'Hibridisasi'],
        ['id' => '13.5', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'Sekuensing'],
        ['id' => '13.6', 'grup' => 'Molekuler Resistensi Antimikroba', 'nama' => 'Metode lainnya'],

        // ── Grup 14: Molekuler Jamur ─────────────────────────────────
        ['id' => '14.1', 'grup' => 'Molekuler Jamur', 'nama' => 'PCR'],
        ['id' => '14.2', 'grup' => 'Molekuler Jamur', 'nama' => 'Real time PCR'],
        ['id' => '14.3', 'grup' => 'Molekuler Jamur', 'nama' => 'Tes Cepat Molekuler'],
        ['id' => '14.4', 'grup' => 'Molekuler Jamur', 'nama' => 'Hibridisasi'],
        ['id' => '14.5', 'grup' => 'Molekuler Jamur', 'nama' => 'Sekuensing'],
        ['id' => '14.6', 'grup' => 'Molekuler Jamur', 'nama' => 'Metode lainnya'],

        // ── Grup 15: Mikroskopis Parasit ─────────────────────────────
        ['id' => '15.1', 'grup' => 'Mikroskopis Parasit', 'nama' => 'Identifikasi cacing, larva/proglottid'],
        ['id' => '15.2', 'grup' => 'Mikroskopis Parasit', 'nama' => 'Identifikasi arthropoda (tuma, tungau, pinjal, kutu, arachnida, crustacea)'],
        ['id' => '15.3', 'grup' => 'Mikroskopis Parasit', 'nama' => 'Identifikasi nyamuk, larva nyamuk'],
        ['id' => '15.4', 'grup' => 'Mikroskopis Parasit', 'nama' => 'Identifikasi lalat dan larva lalat'],

        // ── Grup 16: Pemeriksaan Jamur ───────────────────────────────
        ['id' => '16.1', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Pemeriksaan langsung KOH'],
        ['id' => '16.2', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Pemeriksaan langsung LPCB/tinta India'],
        ['id' => '16.3', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Pulasan khusus'],
        ['id' => '16.4', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Kultur dan identifikasi jamur dari spesimen kulit, rambut, kuku, mukosa, cairan tubuh'],
        ['id' => '16.5', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Identifikasi jamur dari biakan'],
        ['id' => '16.6', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Uji kepekaan jamur ragi (manual/semiotomatis)'],
        ['id' => '16.7', 'grup' => 'Pemeriksaan Jamur', 'nama' => 'Uji kepekaan jamur kapang (manual)'],

        // ── Grup 17: Biopsi Aspirasi ─────────────────────────────────
        ['id' => '17.1', 'grup' => 'Biopsi Aspirasi', 'nama' => 'Tindakan biopsi aspirasi jarum halus dan/atau tindakan kedokteran lainnya'],

        // ── Grup 18: Sitopatologi ────────────────────────────────────
        ['id' => '18.1', 'grup' => 'Sitopatologi', 'nama' => 'Pemeriksaan Sitopatologi'],
        ['id' => '18.2', 'grup' => 'Sitopatologi', 'nama' => 'Pemeriksaan Pap\'s Smear'],
        ['id' => '18.3', 'grup' => 'Sitopatologi', 'nama' => 'Pemeriksaan sitologi apus non ginekologi'],
        ['id' => '18.4', 'grup' => 'Sitopatologi', 'nama' => 'Pemeriksaan sitologi cairan'],

        // ── Grup 19: Histopatologi ───────────────────────────────────
        ['id' => '19.1', 'grup' => 'Histopatologi', 'nama' => 'Pemeriksaan jaringan kecil'],
        ['id' => '19.2', 'grup' => 'Histopatologi', 'nama' => 'Pemeriksaan jaringan sedang'],
        ['id' => '19.3', 'grup' => 'Histopatologi', 'nama' => 'Pemeriksaan jaringan besar'],

        // ── Grup 20: Imunopatologi ───────────────────────────────────
        ['id' => '20.1', 'grup' => 'Imunopatologi', 'nama' => 'Imunohistokimia Payudara'],
        ['id' => '20.2', 'grup' => 'Imunopatologi', 'nama' => 'Imunohistokimia Limfoma'],
        ['id' => '20.3', 'grup' => 'Imunopatologi', 'nama' => 'Imunohistokimia lanjutan (limfoma lanjut, kasus sulit, GIST, PD-L1, ALK, dll)'],
        ['id' => '20.4', 'grup' => 'Imunopatologi', 'nama' => 'Imunositokimia'],
        ['id' => '20.5', 'grup' => 'Imunopatologi', 'nama' => 'Imunofluoresensi (deteksi auto antibodi, deteksi komplek imun pada jaringan)'],

        // ── Grup 21: Patologi Molekuler ──────────────────────────────
        ['id' => '21.1', 'grup' => 'Patologi Molekuler', 'nama' => 'Deteksi mutasi EGFR'],
        ['id' => '21.2', 'grup' => 'Patologi Molekuler', 'nama' => 'Deteksi mutasi all-RAS'],
        ['id' => '21.3', 'grup' => 'Patologi Molekuler', 'nama' => 'Deteksi mutasi BRAF'],
        ['id' => '21.4', 'grup' => 'Patologi Molekuler', 'nama' => 'Deteksi HPV Genotyping'],
        ['id' => '21.5', 'grup' => 'Patologi Molekuler', 'nama' => 'ISH'],
        ['id' => '21.6', 'grup' => 'Patologi Molekuler', 'nama' => 'CISH'],
        ['id' => '21.7', 'grup' => 'Patologi Molekuler', 'nama' => 'FISH'],

        // ── Grup 22: Potong Beku ─────────────────────────────────────
        ['id' => '22.1', 'grup' => 'Potong Beku', 'nama' => 'Pemeriksaan Potong Beku'],

        // ── Fallback ─────────────────────────────────────────────────
        ['id' => '0',    'grup' => '-',           'nama' => 'Tidak Ada Data'],
    ];

    /**
     * Compute 1 bulan laporan. Output: 139 row × {jumlah_l, jumlah_p, rata_l, rata_p}.
     */
    protected function computeRL38(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        // Inisialisasi bucket per SIRS row × gender
        $buckets = [];
        foreach (self::JENIS_PEMERIKSAAN_LIST as $jp) {
            $buckets[$jp['id']] = ['L' => 0, 'P' => 0];
        }

        // Single fetch: aggregate per (clabitem_desc, sex) di periode
        $rows = DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->leftJoin('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price') // billable item only (exclude header/grup parent)
            ->select([
                DB::raw('MAX(m.clabitem_desc) as item_desc'),
                'p.sex',
                DB::raw('COUNT(*) as cnt'),
            ])
            ->groupBy('d.clabitem_id', 'p.sex')
            ->get();

        foreach ($rows as $r) {
            $sirsId = $this->classifyRl38Item((string) ($r->item_desc ?? ''));
            $sex = (string) ($r->sex ?? '');
            if ($sex !== 'L' && $sex !== 'P') {
                continue; // skip data tanpa gender
            }
            $buckets[$sirsId][$sex] += (int) $r->cnt;
        }

        $hariBuka = $this->countHariBukaLab($start, $end);

        // Build flat output
        $out = [];
        foreach (self::JENIS_PEMERIKSAAN_LIST as $jp) {
            $jl = $buckets[$jp['id']]['L'];
            $jp_ = $buckets[$jp['id']]['P'];
            $out[] = [
                'id'        => $jp['id'],
                'grup'      => $jp['grup'],
                'nama'      => $jp['nama'],
                'jumlah_l'  => $jl,
                'jumlah_p'  => $jp_,
                'rata_l'    => $hariBuka > 0 ? round($jl / $hariBuka, 1) : 0.0,
                'rata_p'    => $hariBuka > 0 ? round($jp_ / $hariBuka, 1) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Hari buka lab = COUNT DISTINCT TRUNC(checkup_date) di lbtxn_checkuphdrs
     * untuk periode (semua status_rjri, sumber: internal lab).
     */
    private function countHariBukaLab(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('lbtxn_checkuphdrs')
            ->whereBetween('checkup_date', [$start, $end])
            ->distinct()
            ->count(DB::raw("TRUNC(checkup_date)"));
    }

    /**
     * Klasifikasi clabitem_desc (UPPERCASE) → SIRS RL 3.8 ID.
     *
     * PRIORITAS PENTING: pattern paling spesifik HARUS didahulukan.
     *   - "ANTI HBS"        sebelum "HBS"       (3.6 vs 3.5)
     *   - "ANTI HBC"        sebelum "ANTI HBS"  (3.7 vs 3.6 — ANTI HBC tidak overlap, ok)
     *   - "BIL DIREC"/"BIL INDIREC"/"BIL.TOTAL"/"BILIRUBIN BAYI" → 2.4
     *     "BILIRUBIN" generic urin → 4.3 (priority urin context)
     *   - "ANTI SARS-COV-2"/"COV-2"  → 3.2 (antibodi)
     *   - "SWAB ANTIGEN"/"RAPID ANTIGEN" → 3.3 (antigen)
     *   - "SWAB PCR"/"PCR SARS"  → 10.1 (molekuler virus)
     *   - "TCM"/"TES CEPAT MOLEKULER" → 11.x (TBC)
     *   - "BTA" semua varian → 6.6 (generic, tidak ada split per result di lab order)
     *
     * Item RS yg tidak ada SIRS bucket (TOXOPLASMA, LEPTOSPIRA, TUBEX, CEA,
     * TESTOSTERON, PROCALCITONIN, TROPONIN, GOLONGAN DARAH, FECES, dll)
     * → row '0' Tidak Ada Data.
     *
     * @param string $desc clabitem_desc (akan di-uppercase)
     * @return string SIRS ID (e.g., '1.1', '2.4', '0')
     */
    protected function classifyRl38Item(string $desc): string
    {
        $u = mb_strtoupper(trim($desc));
        if ($u === '') {
            return '0';
        }

        // ─── Group 4 Urinalisis (cek sebelum bilirubin/protein generic) ──
        // Sub-items urin lengkap (parent UR00030 di master)
        if (preg_match('/^\s*[\*\-\s]*ALBUMIN\s*$/u', $u) && str_contains($u, 'NEGATIF')) {
            // edge case dari dump yg "ALBUMIN" sub-urin punya value NEGATIF
            return '4.1';
        }
        if ($u === 'UROBILINOGEN' || str_contains($u, 'UROBILIN')) {
            return '4.2';
        }
        if (str_contains($u, 'NAPZA')) {
            return '4.5';
        }
        // Sedimen urine — sub-items dalam urin lengkap (eritrosit/leukosit/sylinder/kristal/epytel di urin)
        // Tapi "ERITROSIT" dan "LEUKOSIT" generic itu hematologi, jadi cek prefix * (sedimen marker)
        if (preg_match('/^\s*\*\s*(ERY|LEUKO|SYLIND|KRIST|EPY|TROMBOCY|SEDIMEN|PARASIT|LAIN-LAIN)/u', $u)) {
            return '4.4';
        }

        // ─── Group 6 Mikroskopis TBC ─────────────────────────────────
        if (str_contains($u, 'BTA')) {
            return '6.6'; // generic — tanpa split per result, semua BTA → 6.6
        }

        // ─── Group 11 TCM TBC ────────────────────────────────────────
        if (str_contains($u, 'TCM') || str_contains($u, 'TES CEPAT MOLEKULER')) {
            return '11.8'; // generic — semua TCM → 11.8
        }

        // ─── Group 10 Molekuler Virus ────────────────────────────────
        if (str_contains($u, 'SWAB PCR') || (str_contains($u, 'PCR') && str_contains($u, 'SARS'))) {
            return '10.1';
        }
        if (str_contains($u, 'REAL TIME PCR')) {
            return '10.2';
        }
        if (str_contains($u, 'HIBRIDISASI')) {
            return '10.4';
        }
        if (str_contains($u, 'SEKUENSING')) {
            return '10.5';
        }

        // ─── Group 3 Imunologi (cek SARS antibodi/antigen sblm yg lain) ──
        if (str_contains($u, 'ANTI HBS')) {
            return '3.6';
        }
        if (str_contains($u, 'ANTI HBC')) {
            return '3.7';
        }
        if (str_contains($u, 'ANTI HBE')) {
            return '3.8';
        }
        if (str_contains($u, 'HBE AG')) {
            return '3.9';
        }
        if (str_contains($u, 'ANTI HCV') || str_contains($u, 'HCV')) {
            return '3.10';
        }
        if (str_contains($u, 'ANTI HIV') || str_contains($u, 'HIV ') || $u === 'HIV') {
            return '3.12';
        }
        if (str_contains($u, 'ANTI HAV') || str_contains($u, 'IGM HAV')) {
            return '3.11';
        }
        if (str_contains($u, 'HBS AG') || str_contains($u, 'HBSAG')) {
            return '3.5';
        }
        // SARS-CoV-2: bedakan antibodi (IgG/IgM) vs antigen
        if (str_contains($u, 'SARS-COV-2') || str_contains($u, 'SARS COV') || str_contains($u, 'COV-2') || str_contains($u, 'COVID')) {
            // antibodi: ada IGG/IGM/SCREENING/ANTIBODI
            if (str_contains($u, 'IGG') || str_contains($u, 'IGM') || str_contains($u, 'SCREENING') || str_contains($u, 'ANTIBODI') || str_contains($u, 'ANSWER') || str_contains($u, 'ANTI ')) {
                return '3.2';
            }
            // antigen (default kalau bukan antibodi)
            return '3.3';
        }
        if (str_contains($u, 'RAPID ANTIGEN') || (str_contains($u, 'SWAB') && str_contains($u, 'ANTIGEN'))) {
            return '3.3';
        }
        if (str_contains($u, 'WIDAL') || str_contains($u, 'S.TYPHY') || str_contains($u, 'S.PARATYPHY') || str_contains($u, 'S.PARATHYPHY')) {
            return '3.1';
        }
        if (str_contains($u, 'NS 1') || str_contains($u, 'NS1')) {
            return '3.13';
        }
        if (str_contains($u, 'DENGUE')) {
            return '3.4';
        }
        if (str_contains($u, 'MALARIA') || str_contains($u, 'PLASMADIUM') || str_contains($u, 'PLASMODIUM')) {
            return '3.14';
        }
        if (str_contains($u, 'FT3') || str_contains($u, 'FT4')) {
            return '3.16';
        }
        if (str_contains($u, 'TSH')) {
            return '3.17';
        }
        if ($u === 'T3' || $u === 'T4' || str_contains($u, 'T3 ') || str_contains($u, 'T4 ')) {
            return '3.15';
        }

        // ─── Group 5 Hemostasis ──────────────────────────────────────
        if (str_contains($u, 'D-DIMER') || str_contains($u, 'D DIMER')) {
            return '5.7';
        }
        if (str_contains($u, 'FIBRINOGEN')) {
            return '5.6';
        }
        if (str_contains($u, 'THROMBIN') && !str_contains($u, 'PROTHROMBIN') && !str_contains($u, 'TROMBOPLASTIN')) {
            return '5.5';
        }
        if (str_contains($u, 'TROMBOPLASTIN') || str_contains($u, 'APTT')) {
            return '5.4';
        }
        if (str_contains($u, 'PROTHROMBIN') || str_contains($u, 'PROTROMBIN') || $u === 'PT') {
            return '5.3';
        }
        if (str_contains($u, 'MASA PEMBEKUAN') || $u === 'CT') {
            return '5.2';
        }
        if (str_contains($u, 'MASA PERDARAHAN') || $u === 'BT') {
            return '5.1';
        }

        // ─── Group 2 Kimia Klinik (panjang — cek varian dahulu) ──────
        if (str_contains($u, 'BIL DIREC') || str_contains($u, 'BIL.TOTAL') || str_contains($u, 'BILIRUBIN BAYI') || str_contains($u, 'BIL INDIREC') || str_contains($u, 'BIL TOTAL') || str_contains($u, 'BIL.DIREK') || str_contains($u, 'BIL.INDIREK')) {
            return '2.4';
        }
        // Generic BILIRUBIN — bisa urin (4.3) atau kimia (2.4). Cek kalau kontekstualnya sub-urin lewat prefix * atau dalam urin lengkap.
        // Default ke 2.4 karena lebih umum.
        if ($u === 'BILIRUBIN' || str_contains($u, 'BILIRUBIN')) {
            return '2.4';
        }
        if (str_contains($u, 'TOT.PROTEIN') || str_contains($u, 'PROTEIN TOTAL') || str_contains($u, 'TOTAL PROTEIN')) {
            return '2.1';
        }
        if (str_contains($u, 'GLOBULIN')) {
            return '2.3';
        }
        // ALBUMIN kimia (bukan urin yg sudah di-handle di atas)
        if ($u === 'ALBUMIN' || str_contains($u, 'ALBUMIN')) {
            return '2.2';
        }
        if (str_contains($u, 'SGOT') || str_contains($u, 'AST')) {
            return '2.5';
        }
        if (str_contains($u, 'SGPT') || str_contains($u, 'ALT')) {
            return '2.6';
        }
        if (str_contains($u, 'UREUM') || str_contains($u, 'BUN')) {
            return '2.7';
        }
        if (str_contains($u, 'CREATININ') || str_contains($u, 'CREATINI') || str_contains($u, 'EGFR')) {
            return '2.8';
        }
        if (str_contains($u, 'URIC ACID') || str_contains($u, 'ASAM URAT')) {
            return '2.9';
        }
        if (str_contains($u, 'TRIGLYSERIDA') || str_contains($u, 'TRIGLISERIDA') || str_contains($u, 'TRIGLY') || str_contains($u, 'TG ')) {
            return '2.10';
        }
        if (str_contains($u, 'HDL')) {
            return '2.12';
        }
        if (str_contains($u, 'LDL')) {
            return '2.13';
        }
        if ($u === 'CHOLESTEROL' || str_contains($u, 'CHOLESTEROL TOTAL') || str_contains($u, 'KOLESTEROL TOTAL') || (str_contains($u, 'CHOLESTEROL') && !str_contains($u, 'HDL') && !str_contains($u, 'LDL'))) {
            return '2.11';
        }
        if (str_contains($u, 'GULA DARAH') || str_contains($u, 'GLUKOSA') || str_contains($u, 'GDS') || str_contains($u, 'GDP') || str_contains($u, 'GD2PP') || str_contains($u, 'GD-PP')) {
            return '2.14';
        }
        if (str_contains($u, 'HBA1C') || str_contains($u, 'HBA 1C') || str_contains($u, 'HBA-1C')) {
            return '2.15';
        }
        if (str_contains($u, 'FOSFATASE') || str_contains($u, 'ALKALINE PHOSP') || str_contains($u, 'PHOSPATASE') || str_contains($u, 'ALK PHOS')) {
            return '2.16';
        }
        if (str_contains($u, 'GAMMA GT') || str_contains($u, 'GGT')) {
            return '2.17';
        }
        if ($u === 'LDH' || str_contains($u, 'LDH ')) {
            return '2.18';
        }
        if (str_contains($u, 'G 6 PD') || str_contains($u, 'G6PD')) {
            return '2.19';
        }
        if (str_contains($u, 'AMILASE') || str_contains($u, 'AMYLASE')) {
            return '2.20';
        }
        if (str_contains($u, 'LIPASE')) {
            return '2.21';
        }
        if (str_contains($u, 'CHOLINESTERASE')) {
            return '2.22';
        }
        if (str_contains($u, 'CK MB') || str_contains($u, 'CK-MB') || str_contains($u, 'CK TOTAL') || str_contains($u, 'CK-TOTAL')) {
            return '2.23';
        }
        if (str_contains($u, 'TIBC') || (str_contains($u, 'SI ') && str_contains($u, 'IRON'))) {
            return '2.24';
        }
        if ($u === 'KALIUM' || $u === 'NATRIUM' || $u === 'CHLORIDA' || $u === 'CHLORIDE' || str_contains($u, 'ELEKTROLIT') || str_contains($u, 'CALCIUM') || str_contains($u, 'MAGNESIUM') || str_contains($u, 'KALSIUM') || $u === 'NA' || $u === 'K' || $u === 'CL' || $u === 'CA' || $u === 'MG') {
            return '2.25';
        }
        if (str_contains($u, 'GAS DARAH') || str_contains($u, 'BLOOD GAS') || str_contains($u, 'AGD')) {
            return '2.26';
        }

        // ─── Group 1 Hematologi ──────────────────────────────────────
        // Cek paling spesifik dulu
        if (str_contains($u, 'HEMATOKRIT') || str_contains($u, 'HAEMATOKRIT') || $u === 'HCT' || str_contains($u, 'HCT ') || $u === 'PCV') {
            return '1.2';
        }
        if (str_contains($u, 'HEMOGLOBIN') || str_contains($u, 'HAEMOGLOBIN') || $u === 'HGB' || str_contains($u, 'HGB ') || $u === 'HB') {
            return '1.1';
        }
        if (str_contains($u, 'EOSINOFIL') || str_contains($u, 'EOSINOPHIL')) {
            return '1.5';
        }
        if (str_contains($u, 'JENIS LEUKOSIT') || str_contains($u, 'DIFF COUNT')) {
            return '1.6';
        }
        if (str_contains($u, 'LED') || str_contains($u, 'LAJU ENDAP')) {
            return '1.7';
        }
        if (str_contains($u, 'RETIKULOSIT') || str_contains($u, 'RETICULOCYTE')) {
            return '1.8';
        }
        if (str_contains($u, 'TROMBOSIT') || str_contains($u, 'PLATELET') || $u === 'PLT' || str_contains($u, 'PLT ')) {
            return '1.9';
        }
        if (str_contains($u, 'LEUKOSIT') || $u === 'WBC' || str_contains($u, 'WBC ')) {
            return '1.3';
        }
        if (str_contains($u, 'ERITROSIT') || str_contains($u, 'ERYTHROCYTE') || $u === 'RBC' || str_contains($u, 'RBC ')) {
            return '1.4';
        }

        // ─── Default ─────────────────────────────────────────────────
        return '0'; // Tidak Ada Data
    }
}
