<?php

namespace App\Support;

/**
 * Master Kelas Kamar Rawat Inap — SUMBER TUNGGAL.
 *
 * Dipakai LOV kelas kamar (picker Form Penjaminan UGD) & cetak PDF penjaminan.
 * Ubah HANYA di sini agar tampilan form dan hasil cetak selalu sinkron.
 *   nama       : label kelas
 *   tarif      : nominal (int) untuk perhitungan
 *   tarifLabel : label tarif untuk ditampilkan
 *   fasilitas  : daftar fasilitas kamar
 */
class KelasKamar
{
    public static function all(): array
    {
        return [
            'VIP' => [
                'nama' => 'VIP',
                'tarif' => 700000,
                'tarifLabel' => 'Rp 700.000 / hari',
                'fasilitas' => ['1 tempat tidur pasien', 'AC', 'Kamar mandi di dalam', 'Sofa bed penunggu', 'Kulkas', 'Televisi LED', 'Almari', 'Overbed table', 'Dispenser air minum', 'Makan siang 1 penunggu'],
            ],
            'KELAS_I' => [
                'nama' => 'Kelas I',
                'tarif' => 275000,
                'tarifLabel' => 'Rp 275.000 / hari',
                'fasilitas' => ['1 tempat tidur pasien', 'Kamar mandi di dalam', 'Sofa bed penunggu', 'Kulkas', 'Televisi LED', 'Almari', 'Kipas angin', 'Makan siang 1 penunggu'],
            ],
            'KELAS_II' => [
                'nama' => 'Kelas II',
                'tarif' => 175000,
                'tarifLabel' => 'Rp 175.000 / hari',
                'fasilitas' => ['2 tempat tidur pasien', 'Kamar mandi di dalam', 'Kursi penunggu', 'Televisi', 'Almari', 'Kipas angin', 'Makan siang 1 penunggu'],
            ],
            'KELAS_III' => [
                'nama' => 'Kelas III',
                'tarif' => 175000,
                'tarifLabel' => 'Rp 175.000 / hari',
                'fasilitas' => ['4 tempat tidur pasien', 'Kamar mandi di dalam', 'Televisi di luar ruangan', 'Kursi', 'Almari', 'Kipas angin'],
            ],
        ];
    }

    /** Info satu kelas berdasarkan key (VIP/KELAS_I/…); null bila tak ada. */
    public static function find(?string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
