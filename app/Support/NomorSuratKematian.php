<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Generator nomor Surat Keterangan Kematian — SUMBER TUNGGAL format.
 *
 * Dipakai DUA modul: Surat Kematian UGD (rm-surat-kematian-actions) dan Perencanaan RI
 * (rm-perencanaan-ri-actions → noSuratMeninggal). Ditaruh di sini supaya formatnya tak
 * melenceng antar-modul kalau salah satu diubah.
 *
 * Format: SKK/RSIM/<yyyymmddhh24miss>  → mis. SKK/RSIM/20260715183243
 *
 * Kenapa stempel waktu, bukan counter berurut:
 *   - Tak butuh sequence Oracle → tak butuh DDL di tiap environment.
 *   - Unik tanpa koordinasi antar-modul (UGD & RI menerbitkan dari tempat berbeda).
 *   - Tak bisa bentrok dengan nomor manual RS yang mungkin sudah dipakai.
 * Konsekuensi yang diterima: nomor TIDAK berurut, dan mencerminkan waktu surat DIBUAT
 * (bukan waktu pasien meninggal).
 *
 * Catatan BPJS: nomor RI dikirim saat update pulang SEP (VclaimTrait: noSuratMeninggal
 * required_if statusPulang=4|min:5). Panjang format ini 23 karakter — aman.
 */
class NomorSuratKematian
{
    /** Kode jenis surat + kode RS. Ubah di sini kalau RS ganti kode. */
    private const PREFIX = 'SKK/RSIM';

    public static function generate(): string
    {
        return self::PREFIX . '/' . Carbon::now(config('app.timezone'))->format('YmdHis');
    }

    /** Apakah string ini nomor hasil generate (bukan nomor manual RS)? */
    public static function isGenerated(?string $nomor): bool
    {
        return (bool) preg_match('#^' . preg_quote(self::PREFIX, '#') . '/\d{14}$#', trim((string) $nomor));
    }
}
