@props([
    // Konteks pelayanan: 'rj' | 'ugd' | 'ri'
    'context' => 'rj',
    // Tampilkan section "Pihak yg Diberi Akses Info Medis" (HPK 1 EP-c). Default false (RJ tidak butuh).
    'showReleaseInfo' => false,
    // List pihak (untuk PDF / read-only display). Format: [['nama'=>..,'hubungan'=>..,'noHp'=>..], ...]
    'pihakInfoList' => [],
])

@php
    $contextLabel = match ($context) {
        'ugd' => 'Unit Gawat Darurat',
        'ri' => 'Rawat Inap',
        default => 'Rawat Jalan',
    };

    // Daftar tindakan/pemeriksaan yang akan memerlukan Informed Consent terpisah (HPK 4 EP-b).
    // Bersifat informatif — surveyor cek bukti pemberian informasi ini di rekam medis.
    $tindakanButuhIC = [
        'Tindakan pembedahan / operatif',
        'Tindakan invasif (biopsi, endoskopi, pungsi, dll)',
        'Tindakan anestesi & sedasi',
        'Pemberian darah & produk darah (transfusi)',
        'Tindakan / pengobatan berisiko tinggi',
        'Penggunaan obat / terapi dengan risiko khusus',
    ];
@endphp

<div
    class="p-5 mb-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

    {{-- Judul --}}
    <div class="text-center">
        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
            Formulir Persetujuan Umum (General Consent)
        </h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pelayanan {{ $contextLabel }}</p>
    </div>

    {{-- Pernyataan Persetujuan --}}
    <div class="space-y-1">
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Pernyataan Persetujuan</h4>
        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
            Dengan ini, saya memberikan persetujuan untuk menerima perawatan kesehatan di {{ $contextLabel }}
            sesuai dengan kondisi saya. Saya telah menerima penjelasan yang jelas mengenai hak dan kewajiban saya
            sebagai pasien, dengan bahasa yang saya pahami.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        {{-- Hak Pasien --}}
        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Hak Sebagai Pasien</h4>
            <ol
                class="pl-5 space-y-1 text-sm leading-relaxed text-gray-700 list-decimal dark:text-gray-300 text-justify">
                <li>Mendapatkan informasi yang jelas tentang peraturan rumah sakit dan hak serta kewajiban saya
                    sebagai pasien.</li>
                <li>Mendapatkan layanan kesehatan yang baik, tanpa diskriminasi dan sesuai dengan standar
                    profesional.</li>
                <li>Memilih dokter dan jenis perawatan yang saya inginkan, sesuai ketentuan rumah sakit.</li>
                <li>Mendapatkan informasi tentang diagnosis, prosedur medis, tujuan, risiko, dan alternatif
                    tindakan medis.</li>
                <li>Memberikan persetujuan atau menolak tindakan medis yang akan dilakukan oleh tenaga kesehatan,
                    termasuk hak untuk menolak/menghentikan terapi serta menolak pelayanan resusitasi.</li>
                <li>Mendapatkan privasi dan kerahasiaan terkait penyakit dan data medis saya.</li>
                <li>Mengajukan keluhan atau saran mengenai pelayanan rumah sakit yang saya terima.</li>
                <li>Meminta konsultasi (second opinion) dengan dokter lain yang berizin jika diperlukan.</li>
            </ol>
        </div>

        {{-- Kewajiban Pasien --}}
        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Kewajiban Sebagai Pasien</h4>
            <ol
                class="pl-5 space-y-1 text-sm leading-relaxed text-gray-700 list-decimal dark:text-gray-300 text-justify">
                <li>Mematuhi peraturan rumah sakit dan menggunakan fasilitas dengan bertanggung jawab.</li>
                <li>Memberikan informasi yang akurat dan lengkap tentang kondisi kesehatan saya.</li>
                <li>Mematuhi rencana terapi yang disarankan oleh tenaga medis setelah mendapatkan penjelasan.</li>
                <li>Menanggung biaya pengobatan yang saya terima sesuai ketentuan yang berlaku.</li>
                <li>Menghormati hak pasien lain dan petugas medis yang memberikan pelayanan.</li>
            </ol>
        </div>
    </div>

    {{-- Klausul Persetujuan, Pelepasan Info, Kerahasiaan, Biaya --}}
    <div class="grid grid-cols-1 gap-3 pt-3 border-t border-gray-100 md:grid-cols-2 dark:border-gray-800">
        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                Persetujuan Pelayanan Kesehatan
            </h4>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
                Saya menyetujui untuk menerima pemeriksaan, pengobatan, dan tindakan medis yang dianggap perlu oleh
                tim medis di rumah sakit ini. Saya mengerti bahwa saya dapat menanyakan lebih lanjut tentang
                prosedur atau menolak tindakan yang diajukan, jika saya merasa perlu.
            </p>
        </div>

        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Pelepasan Informasi</h4>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
                Saya memberikan izin kepada rumah sakit untuk mengungkapkan informasi medis saya kepada pihak yang
                berwenang (seperti keluarga terdekat atau dokter rujukan) hanya untuk kepentingan perawatan saya.
            </p>
        </div>

        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Kerahasiaan Data</h4>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
                Saya memahami bahwa rumah sakit akan menjaga kerahasiaan informasi medis saya sesuai dengan
                ketentuan yang berlaku.
            </p>
        </div>

        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Tanggung Jawab atas Biaya</h4>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
                Saya memahami bahwa saya bertanggung jawab atas biaya yang timbul dari pelayanan medis yang saya
                terima.
            </p>
        </div>
    </div>

    {{-- Daftar Tindakan yang akan Memerlukan Informed Consent Terpisah (HPK 4 EP-b) --}}
    <div class="pt-3 space-y-2 border-t border-gray-100 dark:border-gray-800">
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            Tindakan / Pemeriksaan yang Memerlukan Persetujuan Tindakan (Informed Consent) Terpisah
        </h4>
        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
            Saya telah diberitahu bahwa selain persetujuan umum ini, untuk tindakan-tindakan berikut akan diminta
            persetujuan tindakan (informed consent) tersendiri:
        </p>
        <ul class="grid grid-cols-1 gap-1 pl-5 text-sm text-gray-700 list-disc md:grid-cols-2 dark:text-gray-300">
            @foreach ($tindakanButuhIC as $t)
                <li>{{ $t }}</li>
            @endforeach
        </ul>
    </div>

    {{-- Keterlibatan Peserta Didik (HPK 4 EP-c) --}}
    <div class="pt-3 space-y-2 border-t border-gray-100 dark:border-gray-800">
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            Keterlibatan Peserta Didik dalam Proses Perawatan
        </h4>
        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
            Saya telah diberitahu bahwa rumah sakit ini merupakan rumah sakit pendidikan, sehingga dalam proses
            perawatan dimungkinkan adanya keterlibatan peserta didik (mahasiswa kedokteran/koas, peserta didik
            keperawatan, residen, dokter <em>fellow</em>, dan peserta didik kesehatan lainnya) di bawah supervisi
            tenaga kesehatan yang berwenang. Saya berhak menolak keterlibatan peserta didik ini dengan menyampaikan
            kepada petugas.
        </p>
    </div>

    {{-- Pihak yg Diberi Akses Informasi Medis (HPK 1 EP-c) — UGD/RI saja --}}
    @if ($showReleaseInfo)
        <div class="pt-3 space-y-2 border-t border-gray-100 dark:border-gray-800">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                Pihak yang Diberi Akses Informasi Medis
            </h4>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 text-justify">
                Saya memberikan izin kepada rumah sakit untuk memberikan informasi mengenai kondisi medis, diagnosis,
                rencana perawatan, dan perkembangan kesehatan saya kepada pihak-pihak yang saya tunjuk di bawah ini.
                Selain pihak-pihak ini, rumah sakit hanya akan memberikan informasi sesuai ketentuan
                perundang-undangan yang berlaku.
            </p>

            @if (count($pihakInfoList ?? []) > 0)
                <table class="w-full mt-2 text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-1.5 border-b w-10 text-center">#</th>
                            <th class="px-3 py-1.5 border-b">Nama</th>
                            <th class="px-3 py-1.5 border-b">Hubungan</th>
                            <th class="px-3 py-1.5 border-b">No. HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pihakInfoList as $i => $row)
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-1.5 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $row['nama'] ?? '-' }}
                                </td>
                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">
                                    {{ $row['hubungan'] ?? '-' }}
                                </td>
                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">
                                    {{ $row['noHp'] ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm italic text-gray-400">Belum ada pihak yang ditunjuk.</p>
            @endif
        </div>
    @endif
</div>
