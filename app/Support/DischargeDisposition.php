<?php

namespace App\Support;

/**
 * Status pulang RI (`perencanaan.tindakLanjut.tindakLanjutKode`) → SNOMED CT
 * untuk `Encounter.hospitalization.dischargeDisposition` (SATUSEHAT).
 *
 * Kode SNOMED-nya sudah tersimpan di EMR (opsi di rm-perencanaan-ri-actions), helper ini
 * hanya melengkapi `display` + menambal kode lama yang salah arti.
 *
 * ⚠️ RIWAYAT BUG (ditemukan 2026-07-15, semua diverifikasi via tx.fhir.org):
 *   - `266707007` dipakai untuk "Pulang atas Permintaan Sendiri", padahal artinya
 *     **"Drug addiction therapy"** — sama sekali tak berhubungan.
 *   - `371828006` dipakai untuk "Pulang Tanpa Perbaikan", padahal artinya
 *     **"Patient deceased during stay"** — pasien yang pulang HIDUP akan terlapor MENINGGAL.
 * Opsi di modul sudah dibetulkan, tapi kode lama TERLANJUR TERSIMPAN di record lama
 * (saat ditemukan: 1 record 266707007, 2 record 371828006). LEGACY_FIX menambalnya
 * saat kirim, supaya record lama tak ikut salah lapor. Belum ada yang terkirim ke
 * SATUSEHAT waktu itu, jadi tak ada data nasional yang perlu diralat.
 *
 * "Pulang Tanpa Perbaikan" tidak punya padanan SNOMED yang bersih → dipetakan ke
 * `371827001` (Patient discharged alive): benar secara fakta (pasien pulang hidup),
 * nuansa "tanpa perbaikan" tetap tersimpan di EMR lewat label Indonesianya.
 */
class DischargeDisposition
{
    /**
     * SUMBER TUNGGAL opsi status pulang RI.
     *
     * `kode` = KUNCI INTERNAL (value radio + tersimpan di JSON 2.613 record) — bentuknya
     * kebetulan SNOMED, tapi 2 di antaranya salah arti; JANGAN dibetulkan di sini, kunci
     * wajib unik & stabil. SNOMED yang benar diresolusi fromKode(). `bpjs` = status pulang BPJS.
     *
     * Sebelumnya peta ini disalin di 3 tempat (form perencanaan, cetak ringkasan pulang,
     * helper ini) dengan label yang BERTENTANGAN — cetakan menulis `371828006` = "Membaik",
     * form menulis "Pulang Tanpa Perbaikan" (berlawanan!), SNOMED sendiri berarti "Patient
     * deceased during stay". Label yang benar = versi form (keputusan user 2026-07-15).
     */
    public const OPTIONS = [
        ['tindakLanjut' => 'Pulang Sehat',                     'tindakLanjutKode' => '371827001', 'tindakLanjutKodeBpjs' => 1],
        ['tindakLanjut' => 'Pulang dengan Permintaan Sendiri', 'tindakLanjutKode' => '266707007', 'tindakLanjutKodeBpjs' => 3],
        ['tindakLanjut' => 'Pulang Pindah / Rujuk',            'tindakLanjutKode' => '306206005', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Pulang Tanpa Perbaikan',           'tindakLanjutKode' => '371828006', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Meninggal',                        'tindakLanjutKode' => '419099009', 'tindakLanjutKodeBpjs' => 4],
        ['tindakLanjut' => 'Lain-lain',                        'tindakLanjutKode' => '74964007',  'tindakLanjutKodeBpjs' => 5],
    ];

    /** Label Indonesia untuk kunci internal — dipakai form & cetakan. */
    public static function label(?string $kode): string
    {
        $k = trim((string) $kode);
        foreach (self::OPTIONS as $o) {
            if ($o['tindakLanjutKode'] === $k) {
                return $o['tindakLanjut'];
            }
        }

        return '';
    }

    /** Kode SNOMED sah → display resmi (dari terminology server). */
    private const SNOMED = [
        '371827001' => 'Patient discharged alive',
        '225928004' => 'Patient self-discharge against medical advice',
        '306206005' => 'Referral to service',
        '419099009' => 'Dead',
        '74964007'  => 'Other',
    ];

    /** Kode lama yang salah arti → kode yang benar. Lihat catatan kelas. */
    private const LEGACY_FIX = [
        '266707007' => '225928004', // "Drug addiction therapy" → self-discharge against medical advice
        '371828006' => '371827001', // "Patient deceased during stay" → discharged alive
    ];

    /**
     * @return array{code: string, display: string}|null null bila kode kosong/tak dikenal
     */
    public static function fromKode(?string $kode): ?array
    {
        $k = trim((string) $kode);
        if ($k === '') {
            return null;
        }

        $k = self::LEGACY_FIX[$k] ?? $k;

        if (!isset(self::SNOMED[$k])) {
            return null; // tak dikenal → jangan tebak, lebih baik tanpa dischargeDisposition
        }

        return ['code' => $k, 'display' => self::SNOMED[$k]];
    }
}
