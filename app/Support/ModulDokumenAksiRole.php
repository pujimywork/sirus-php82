<?php

namespace App\Support;

/**
 * SUMBER TUNGGAL daftar role yang boleh melakukan aksi sensitif pada
 * modul dokumen (formulir bertanda tangan: consent, laporan, pengkajian, dll).
 *
 * Untuk MENAMBAH / MENGURANGI role yang boleh Hapus atau Buka Kunci di SELURUH
 * modul dokumen (RI/UGD/RJ), cukup ubah konstanta di bawah — TIDAK perlu
 * menyunting tiap file modul lagi.
 *
 * Dikonsumsi lewat Gate 'dokumen.hapus' & 'dokumen.bukaKunci' yang didaftarkan
 * di App\Providers\AppServiceProvider::boot(). Pemakaian:
 *   - Blade  : @can('dokumen.hapus') ... @endcan
 *   - Server : auth()->user()?->can('dokumen.hapus')
 *
 * Keduanya sengaja dipisah agar kelak bisa diberi role berbeda tanpa mengubah
 * kode pemanggil; saat ini nilainya sama (triad pengelola).
 */
class ModulDokumenAksiRole
{
    /** Role yang boleh MENGHAPUS entri dokumen (draft maupun terkunci). */
    public const HAPUS = ['Admin', 'Manager Umum', 'Manager Medis'];

    /** Role yang boleh MEMBUKA KUNCI (mencabut TTD petugas) entri dokumen. */
    public const BUKA_KUNCI = ['Admin', 'Manager Umum', 'Manager Medis'];
}
