<?php

namespace App\Support;

/**
 * Helper sanitasi teks log aktivitas.
 *
 * Dipakai oleh appendAdminLog{RJ,UGD,RI} di trait EMR. Dibuat sebagai helper
 * statis (bukan method trait) supaya komponen yang memakai >1 trait EMR
 * sekaligus (mis. cetak-sep / cetak-skdp BPJS) tidak kena tabrakan method trait.
 */
class LogText
{
    /**
     * Normalisasi teks ke ASCII. Karakter tipografis (em/en-dash, panah,
     * kutip melengkung, elipsis, bullet) tidak ter-map charset Oracle →
     * tersimpan sebagai '¿'. Ganti ke padanan ASCII sebelum disimpan.
     */
    public static function sanitize(?string $text): string
    {
        return strtr((string) $text, [
            '—' => '-', '–' => '-', '−' => '-',
            '→' => '->', '←' => '<-',
            '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            '…' => '...', '•' => '*',
        ]);
    }
}
