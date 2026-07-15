<?php

namespace App\Support;

/**
 * Pembaca JSON e-resep yang menyeragamkan RJ / UGD / RI.
 *
 * Polanya sama di tiga modul; bedanya cuma SATU: rawat inap bisa memberi lebih dari
 * satu resep dalam satu periode perawatan (perawatan >1 hari), jadi RI membungkus
 * resepnya dalam lembar-lembar. RJ/UGD = satu kunjungan, satu lembar.
 *
 *   RJ / UGD (datar, di akar):
 *     eresep[]         → obat non-racikan
 *     eresepRacikan[]  → baris bahan racikan
 *
 *   RI (berlembar):
 *     eresepHdr[] → { resepNo, resepDate, eresep[], eresepRacikan[], slsNo?, tandaTanganDokter? }
 *
 * lembar() memulangkan bentuk seragam, sehingga pemanggil tidak perlu tahu modulnya:
 *   [ ['resepNo'=>?, 'resepDate'=>?, 'nonRacikan'=>[...], 'racikan'=>['R1'=>[bahan...], ...]], ... ]
 *
 * Racikan dikelompokkan per `noRacikan` ("R1","R2"): satu grup = satu obat racikan,
 * anggotanya = bahan-bahannya. Baris tanpa noRacikan dikumpulkan ke grup '-'.
 *
 * CATATAN DATA (probe 2026-07-15): ~97% baris racikan RJ/UGD lama TIDAK punya `productId`
 * (hanya productName teks) sehingga tak bisa dipetakan ke KFA. Kolom product_id di
 * rstxn_rjobatracikans/rstxn_ugdobatracikans ADA tapi 0% terisi, jadi tak bisa
 * dipulihkan lewat join. Pemanggil WAJIB memperlakukan bahan tanpa productId sebagai
 * tak terkirim — dan MELAPORKANNYA, jangan dibuang diam-diam.
 */
class EresepJson
{
    /**
     * Normalkan node e-resep jadi daftar lembar resep.
     *
     * @return array<int, array{resepNo: int|null, resepDate: string|null, nonRacikan: array, racikan: array}>
     */
    public static function lembar(array $data): array
    {
        // RI: berlembar
        if (!empty($data['eresepHdr']) && is_array($data['eresepHdr'])) {
            $out = [];
            foreach ($data['eresepHdr'] as $hdr) {
                if (!is_array($hdr)) {
                    continue;
                }
                $out[] = [
                    'resepNo'    => isset($hdr['resepNo']) ? (int) $hdr['resepNo'] : null,
                    'resepDate'  => $hdr['resepDate'] ?? null,
                    'nonRacikan' => array_values(array_filter($hdr['eresep'] ?? [], 'is_array')),
                    'racikan'    => self::kelompokkanRacikan($hdr['eresepRacikan'] ?? []),
                ];
            }

            return $out;
        }

        // RJ/UGD: datar — satu lembar
        $nonRacikan = $data['eresep'] ?? ($data['resepObat'] ?? []);
        $racikan = $data['eresepRacikan'] ?? [];
        if (empty($nonRacikan) && empty($racikan)) {
            return [];
        }

        return [[
            'resepNo'    => null,
            'resepDate'  => null,
            'nonRacikan' => array_values(array_filter(is_array($nonRacikan) ? $nonRacikan : [], 'is_array')),
            'racikan'    => self::kelompokkanRacikan($racikan),
        ]];
    }

    /**
     * Kelompokkan baris bahan per noRacikan.
     *
     * @return array<string, array<int, array>>
     */
    private static function kelompokkanRacikan(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $grup = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $no = trim((string) ($row['noRacikan'] ?? ''));
            $grup[$no !== '' ? $no : '-'][] = $row;
        }

        return $grup;
    }

    /** Jumlah lembar resep (RJ/UGD selalu 0 atau 1; RI bisa banyak). */
    public static function jumlahLembar(array $data): int
    {
        return count(self::lembar($data));
    }

    /** Total grup racikan (= jumlah obat racikan) di seluruh lembar. */
    public static function jumlahRacikan(array $data): int
    {
        $n = 0;
        foreach (self::lembar($data) as $l) {
            $n += count($l['racikan']);
        }

        return $n;
    }

    /**
     * Grup racikan yang SIAP dipetakan ke KFA (semua bahannya punya productId)
     * vs yang tidak. Dipakai untuk melaporkan apa yang tak terkirim.
     *
     * @return array{siap: int, takLengkap: int, bahanTanpaProductId: int}
     */
    public static function kesiapanRacikan(array $data): array
    {
        $siap = 0;
        $takLengkap = 0;
        $bahanTanpa = 0;

        foreach (self::lembar($data) as $l) {
            foreach ($l['racikan'] as $bahanList) {
                $kurang = 0;
                foreach ($bahanList as $b) {
                    if (trim((string) ($b['productId'] ?? '')) === '') {
                        $kurang++;
                    }
                }
                $bahanTanpa += $kurang;
                $kurang > 0 ? $takLengkap++ : $siap++;
            }
        }

        return ['siap' => $siap, 'takLengkap' => $takLengkap, 'bahanTanpaProductId' => $bahanTanpa];
    }
}
