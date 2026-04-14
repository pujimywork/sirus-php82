<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiagKeperawatanSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus semua data lama
        DB::table('rsmst_diagkeperawatans')->truncate();

        $data = $this->getData();

        foreach ($data as $row) {
            DB::table('rsmst_diagkeperawatans')->insert([
                'diagkep_id'   => $row['diagkep_id'],
                'diagkep_desc' => $row['diagkep_desc'],
                'diagkep_json' => json_encode($row['diagkep_json'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        $this->command->info('DiagKeperawatanSeeder: ' . count($data) . ' diagnoses seeded.');
    }

    private function getData(): array
    {
        return [

            // ================================================================
            // D.0001 — Bersihan Jalan Napas Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0001',
                'diagkep_desc' => 'Bersihan Jalan Napas Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Ketidakmampuan membersihkan sekret atau obstruksi jalan napas untuk mempertahankan jalan napas tetap paten',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Spasme jalan napas',
                                'Hipersekresi jalan napas',
                                'Disfungsi neuromuskuler',
                                'Benda asing dalam jalan napas',
                                'Adanya jalan napas buatan',
                                'Sekresi yang tertahan',
                                'Hiperplasia dinding jalan napas',
                                'Proses infeksi',
                                'Respon alergi',
                                'Efek agen farmakologis (mis. Anastesi)',
                            ],
                            'situasional' => [
                                'Merokok aktif',
                                'Merokok pasif',
                                'Terpajan polutan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Batuk tidak efektif atau tidak mampu batuk',
                                'Sputum berlebih / obstruksi di jalan napas / mekonium di jalan napas (pada neonatus)',
                                'Mengi, wheezing dan/atau ronkhi kering',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Dispnea', 'Sulit bicara', 'Ortopnea'],
                            'objektif'  => [
                                'Gelisah',
                                'Sianosis',
                                'Bunyi napas menurun',
                                'Frekuensi napas berubah',
                                'Pola napas berubah',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Guillain Barre syndrome',
                            'Sklerosis multipel',
                            'Myasthenia gravis',
                            'Prosedur diagnostik (mis. Bronkoskopi, transesophageal echocardiography)',
                            'Stroke',
                            'Kuadrifplegia',
                            'Sindrom aspirasi meconium',
                            'Infeksi saluran napas',
                            'Asma',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.01001',
                            'nama' => 'Bersihan Jalan Nafas',
                            'kriteria_hasil' => [
                                'Batuk efektif dari skala 1 menurun menjadi skala 5 meningkat',
                                'Produksi sputum dari skala 1 meningkat menjadi skala 5 menurun',
                                'Mengi dari skala 1 meningkat menjadi skala 5 menurun',
                                'Wheezing dari skala 1 meningkat menjadi skala 5 menurun',
                                'Mekonium (pada neonatus) dari skala 1 meningkat menjadi skala 5 menurun',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01001',
                            'nama'     => 'Latihan Batuk Efektif',
                            'definisi' => 'Melatih pasien yang tidak memiliki kemampuan batuk secara efektif untuk membersihkan laring, trakea dan bronkiolus dari sekret atau benda asing di jalan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi kemampuan batuk',
                                    'Monitor adanya retensi skutum',
                                    'Monitor adanya gejala infeksi saluran nafas',
                                    'Monitor input dan output cairan (mis. Jumlah dan karakteristik)',
                                ],
                                'terapeutik' => [
                                    'Atur posisi semi-Fowler atau fowler',
                                    'Pasang perlak dan bengkok di pangkuan pasien',
                                    'Buang sekret pada tempat sputum',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur batuk efektif',
                                    'Anjurkan tarik napas dalam melalui hidung selama 4 detik, di tahan sampai 2 detik, kemudian keluarkan dari mulut dengan bibir mencucu (dibulatkan) selama 8 detik',
                                    'Anjurkan mengulangi tarik napas dalam hingga 3 detik',
                                    'Anjurkan batuk dengan kuat langsung setelah tarik napas dalam yang ke-3',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian mukolitik atau ekspektoran, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.01011',
                            'nama'     => 'Manajemen Jalan Napas',
                            'definisi' => 'Mengidentifikasi dan mengelola kepatenan jalan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor pola napas (frekuensi, kedalaman, usaha napas)',
                                    'Monitor bunyi napas tambahan (mis. gurgling, mengi, wheezing, ronkhi kering)',
                                    'Monitor sputum (jumlah, warna, aroma)',
                                ],
                                'terapeutik' => [
                                    'Pertahankan kepatenan jalan napas dengan head-tilt dan chin-lift (jaw-thrust jika curiga trauma survikal)',
                                    'Posisikan semi-fowler atau fowler',
                                    'Berikan minuman hangat',
                                    'Lakukan fisioterapi dada, jika perlu',
                                    'Berikan oksigen, jika perlu',
                                ],
                                'edukasi' => [
                                    'Anjurkan asupan cairan 2000 ml/hari, jika tidak kontraindikasi',
                                    'Ajarkan teknik batuk efektif',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian bronkodilator, ekspektoran, mukolitik, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.01014',
                            'nama'     => 'Pemantauan Respirasi',
                            'definisi' => 'Mengumpulkan dan menganalisis data untuk memastikan kepatenan jalan napas dan keefektifan pertukaran gas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor frekuensi irama, kedalaman dan upaya napas',
                                    'Monitor pola napas (seperti bradypnea, takipnea, hiperventilasi, Kussmaul, Cheyne-Stokes, Biot, ataksik)',
                                    'Monitor kemampuan batuk efektif',
                                    'Monitor adanya produksi sputum',
                                    'Monitor adanya sumbatan jalan napas',
                                    'Palpasi kesimetrisan ekspansi paru',
                                    'Auskultasi bunyi napas',
                                    'Monitor saturasi oksigen',
                                    'Monitor nilai AGD',
                                    'Monitor hasil x-ray thoraks',
                                ],
                                'terapeutik' => [
                                    'Atur interval pemantauan respirasi sesuai kondisi pasien',
                                    'Dokumentasikan hasil pemantauan',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur pemantauan',
                                    'Informasikan hasil pemantauan, jika perlu',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0002 — Gangguan Penyapihan Ventilator
            // ================================================================
            [
                'diagkep_id'   => 'D.0002',
                'diagkep_desc' => 'Gangguan Penyapihan Ventilator',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Ketidakmampuan beradaptasi dengan pengurangan bantuan ventilator mekanik yang dapat menghambat dan memperlama proses penyapihan',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Hipersekresi jalan napas',
                                'Ketidakcukupan Energi',
                                'Hambatan upaya napas (mis. Nyeri saat bernapas, kelemahan otot pernapasan, efek sedasi)',
                            ],
                            'psikologis' => [
                                'Kecemasan',
                                'Perasaan tidak berdaya',
                                'Kurang informasi tentang proses penyapihan',
                                'Penurunan motivasi',
                            ],
                            'situasional' => [
                                'Ketidakadekuatan dukungan sosial',
                                'Ketidaktepatan kecepatan proses penyapihan',
                                'Riwayat kegagalan berulang dalam upaya penyapihan',
                                'Riwayat ketergantungan ventilator >4 hari',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Frekuensi napas meningkat',
                                'Penggunaan otot bantu napas',
                                'Napas megap megap (gasping)',
                                'Upaya napas dan bantuan ventilator tidak sinkron',
                                'Napas dangkal',
                                'Agitasi',
                                'Nilai gas darah arteri abnormal',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => [
                                'Lelah',
                                'Kuatir mesin rusak',
                                'Fokus meningkat pada pernapasan',
                            ],
                            'objektif' => [
                                'Auskultasi suara inspirasi menurun',
                                'Warna kulit abnormal (mis. pucat, sianosis)',
                                'Napas paradoks abdominal',
                                'Diaforesis',
                                'Ekspresi wajah takut',
                                'Tekanan darah meningkat',
                                'Frekuensi nadi meningkat',
                                'Kesadaran menurun',
                                'Gelisah',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Cedera kepala',
                            'Coronary artery bypass graft (CABG)',
                            'Gagal Napas',
                            'Cardiac Arrest',
                            'Transplantasi Jantung',
                            'Displasia Bronkupulmonal',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.01002',
                            'nama' => 'Penyapihan Ventilator',
                            'kriteria_hasil' => [
                                'Kesinkronan bantuan ventilator dari skala 1 menurun menjadi skala 5 meningkat',
                                'Penggunaan otot bantu napas dari skala 5 menurun menjadi skala 1 meningkat',
                                'Napas megap megap (gasping) dari skala 5 menurun menjadi skala 1 meningkat',
                                'Napas dangkal dari skala 5 menurun menjadi skala 1 meningkat',
                                'Agitasi dari skala 5 menurun menjadi skala 1 meningkat',
                                'Frekuensi napas nilai gas darah arteri dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01021',
                            'nama'     => 'Penyapihan Ventilasi Mekanik',
                            'definisi' => 'Memfasilitasi pasien bernapas tanpa bantuan ventilasi mekanis',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa kemampuan untuk disapih (meliputi hemodinamik stabil, kondisi optimal, bebas infeksi)',
                                    'Monitor prediktor kemampuan untuk mentolerir penyapihan (mis. tingkat kemampuan bernapas, kapasitas vital, Vd/Vt, MVV, kekuat an inspirasi, FEV1, tekanan inspirasi negatif)',
                                    'Monitor tanda kelelahan otot pernapasan (mis. Kenaikan PaCO2, napas cepat dan dangkal, gerakan dinding abdomen paradoks, hipoksemia, dan hipoksia jaringan saat penyapihan)',
                                    'Monitor status cairan dan elektrolit',
                                ],
                                'terapeutik' => [
                                    'Posisikan pasien semi fowler (30-45 derajat)',
                                    'Lakukan pengisapan jalan napas, jika perlu',
                                    'Berikan fisioterapi dada, jika perlu',
                                    'Lakukan uji coba penyapihan (30-120 menit dengan napas spontan yang dibantu ventilator)',
                                    'Gunakan teknik relaksasi, jika perlu',
                                    'Hindari pemberian sedasi farmakologis selama percobaan penyapihan',
                                    'Berikan dukungan psikologis',
                                ],
                                'edukasi' => [
                                    'Ajarkan cara pengontrolan napas saat penyapihan',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian obat yang meningkatkan kepatenan jalan napas dan pertukaran gas',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.01014',
                            'nama'     => 'Pemantauan Respirasi',
                            'definisi' => 'Mengumpulkan dan menganalisis data untuk memastikan kepatenan jalan napas dan keefektifan pertukaran gas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor frekuensi irama, kedalaman dan upaya napas',
                                    'Monitor pola napas (seperti bradypnea, takipnea, hiperventilasi, Kussmaul, Cheyne-Stokes, Biot, ataksik)',
                                    'Monitor kemampuan batuk efektif',
                                    'Monitor adanya produksi sputum',
                                    'Monitor adanya sumbatan jalan napas',
                                    'Palpasi kesimetrisan ekspansi paru',
                                    'Auskultasi bunyi napas',
                                    'Monitor saturasi oksigen',
                                    'Monitor nilai AGD',
                                    'Monitor hasil x-ray thoraks',
                                ],
                                'terapeutik' => [
                                    'Atur interval pemantauan respirasi sesuai kondisi pasien',
                                    'Dokumentasikan hasil pemantauan',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur pemantauan',
                                    'Informasikan hasil pemantauan, jika perlu',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0003 — Gangguan Pertukaran Gas
            // ================================================================
            [
                'diagkep_id'   => 'D.0003',
                'diagkep_desc' => 'Gangguan Pertukaran Gas',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Kelebihan atau kekurangan oksigenasi dan/atau eliminasi karbon dioksida pada membran alveolus-kapiler',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Ketidakseimbangan ventilasi-perfusi',
                                'Perubahan membran alveolus-kapiler',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Dispnea'],
                            'objektif'  => [
                                'PCO2 meningkat/menurun',
                                'PO2 menurun',
                                'Takikardi',
                                'pH arteri menurun/meningkat',
                                'Bunyi napas tambahan',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Pusing', 'Penglihatan kabur'],
                            'objektif'  => [
                                'Sianosis',
                                'Diaforesis',
                                'Gelisah',
                                'Napas cuping hidup',
                                'Pola napas abnormal (cepat/lambat, regular/iregular, dala/dangkal)',
                                'Warna kulit abnormal (mis. Pucat, kebiruan)',
                                'Kesadaran menurun',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Penyakit paru obstruktif kronis (PPOK)',
                            'Gagal jantung kongestif',
                            'Asma',
                            'Pneumonia',
                            'Tuberkulosis paru',
                            'Penyakit membran hialin',
                            'Asfiksia',
                            'Persistent pulmonary hypertension of newborn (PPHN)',
                            'Prematuritas',
                            'Infeksi saluran napas',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.01003',
                            'nama' => 'Pertukaran Gas',
                            'kriteria_hasil' => [
                                'Dispnea dari skala 1 meningkat menjadi skala 5 menurun',
                                'Bunyi napas tambahan dari skala 1 meningkat menjadi skala 5 menurun',
                                'Takikardi dari skala 1 meningkat menjadi skala 5 menurun',
                                'PCO2 dari skala 1 memburuk menjadi skala 5 membaik',
                                'PO2 dari skala 1 memburuk menjadi skala 5 membaik',
                                'pH arteri dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01014',
                            'nama'     => 'Pemantauan Respirasi',
                            'definisi' => 'Mengumpulkan dan menganalisis data untuk memastikan kepatenan jalan napas dan keefektifan pertukaran gas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor frekuensi irama, kedalaman dan upaya napas',
                                    'Monitor pola napas (seperti bradypnea, takipnea, hiperventilasi, Kussmaul, Cheyne-Stokes, Biot, ataksik)',
                                    'Monitor kemampuan batuk efektif',
                                    'Monitor adanya produksi sputum',
                                    'Monitor adanya sumbatan jalan napas',
                                    'Palpasi kesimetrisan ekspansi paru',
                                    'Auskultasi bunyi napas',
                                    'Monitor saturasi oksigen',
                                    'Monitor nilai AGD',
                                    'Monitor hasil x-ray thoraks',
                                ],
                                'terapeutik' => [
                                    'Atur interval pemantauan respirasi sesuai kondisi pasien',
                                    'Dokumentasikan hasil pemantauan',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur pemantauan',
                                    'Informasikan hasil pemantauan, jika perlu',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                        [
                            'kode'     => 'I.01026',
                            'nama'     => 'Terapi Oksigen',
                            'definisi' => 'Memberikan tambahan oksigen untuk mencegah dan mengatasi kondisi kekurangan oksigen jaringan',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor kecepatan aliran oksigen',
                                    'Monitor posisi alat terapi oksigen',
                                    'Monitor aliran oksigen secara periodik dan pastikan fraksi yang diberikan cukup',
                                    'Monitor efektifitas terapi oksigen (mis. Oksimetri, analisa gas darah), jika perlu',
                                    'Monitor kemampuan melepaskan oksigen saat makan',
                                    'Monitor tanda-tanda hipoventilasi',
                                    'Monitor tanda dan gejala toksikasi oksigen dan atelektasis',
                                    'Monitor tingkat kecemasan akibat terapi oksigen',
                                    'Monitor integritas mukosa hidung akibat pemasangan oksigen',
                                ],
                                'terapeutik' => [
                                    'Bersihkan sekret pada mulut, hidung dan trakea, jika perlu',
                                    'Pertahankan kepatenan jalan napas',
                                    'Siapkan dan atur peralatan pemberian oksigen',
                                    'Berikan oksigen tambahan, jika perlu',
                                    'Tetap berikan oksigen saat pasien ditransportasi',
                                    'Gunakan perangkat oksigen yang sesuai dengan tingkat mobilitas pasien',
                                ],
                                'edukasi' => [
                                    'Ajarkan pasien dan keluarga cara menggunakan oksigen di rumah',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi penentuan dosis oksigen',
                                    'Kolaborasi penggunaan oksigen saat aktivitas dan/atau tidur',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0004 — Gangguan Ventilasi Spontan
            // ================================================================
            [
                'diagkep_id'   => 'D.0004',
                'diagkep_desc' => 'Gangguan Ventilasi Spontan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Penurunan cadangan energi yang mengakibatkan individu tidak mampu bernafas secara adekuat',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Gangguan metabolisme',
                                'Kelemahan otot pernapasan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Dispnea'],
                            'objektif'  => [
                                'Penggunaan otot bantu nafas meningkat',
                                'Volume tidak menurun',
                                'PCO2 meningkat',
                                'PO2 menurun',
                                'SaO2 menurun',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Gelisah',
                                'Takikardia',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Penyakit paru obstruktif kronis (PPOK)',
                            'Asma',
                            'Cedera kepala',
                            'Gagal napas',
                            'Bedah jantung',
                            'Adult respiratory distress syndrome (ARDS)',
                            'Persistent pulmonary hypertension of newborn (PPHN)',
                            'Prematuritas',
                            'Infeksi saluran nafas',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02015',
                            'nama' => 'Sirkulasi Spontan',
                            'kriteria_hasil' => [
                                'Tingkat kesadaran dari skala 1 menurun menjadi skala 5 meningkat',
                                'Frekuensi nadi dari skala 1 memburuk menjadi skala 5 membaik',
                                'Tekanan darah dari skala 1 memburuk menjadi skala 5 membaik',
                                'Frekuensi napas dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02038',
                            'nama'     => 'Manajemen Defibrilasi',
                            'definisi' => 'Mengidentifikasi dan mengelola aliran listrik kuat dengan metode asinkron ke jantung melalui elektroda yang ditempatkan pada permukaan dada',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa irama pada monitor setelah RJP 2 menit',
                                ],
                                'terapeutik' => [
                                    'Lakukan resusitasi jantung paru (RJP) hingga mesin defibrillator siap',
                                    'Siapkan dan hidupkan mesin defibrilator',
                                    'Pasang monitor EKG',
                                    'Pastikan irama EKG henti jantung (VF atau VT tanpa nadi)',
                                    'Atur jumlah energi dengan mode Asynchronized (360 joule untuk monopasi dan 120-200 joule untuk bifasik)',
                                    'Angkat paddle dari mesin dan oleskan jeli pada paddle',
                                    'Tempelkan paddle sternum (kanan) pada sisi kanan sternum dibawah klavikula dan paddle apeks (kiri) pada garis midaksilaris setinggi elektroda V6',
                                    'Isi energi dengan menekan tombol charger pada paddle atau tombol charger pada mesin defibrilator dan menunggu hingga energi yang diinginkan tercapai',
                                    'Hentikan RJP saat defibrillator siap',
                                    'Teriak bahwa defibrilator telah siap (mis. I am clear, you re clear, everybody\'s clear)',
                                    'Berikan shock dengan menekan tombol pada kedua paddle bersamaan',
                                    'Angkat paddle dan langsung lanjutkan RJP tanpa menunggu hasil irama yang muncul pada monitor setelah pemberian defibrilasi',
                                    'Lanjutkan RJP sampai 2 menit',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur tindakan kepada keluarga atau pengantar pasien',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi tim medis untuk bantuan hidup lanjut',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.03139',
                            'nama'     => 'Resusitasi Cairan',
                            'definisi' => 'Memberikan penanganan segera pada kondisi kekurangan cairan tubuh',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi kelas syok untuk estimasi kehilangan darah',
                                    'Monitor status hemodinamik',
                                    'Monitor status oksigen',
                                    'Monitor kelebihan cairan',
                                    'Monitor output cairan tubuh (mis. Urine, cairan nasogastrik, cairan selang dada)',
                                    'Monitor nilai BUN, kreatinin, protein total, dan albumin, jika perlu',
                                    'Monitor tanda dan gejala edema paru',
                                ],
                                'terapeutik' => [
                                    'Pasang jalur IV berukuran besar (mis. Nomor 14-16)',
                                    'Berikan infus cairan kristaloid 1-2 liter pada dewasa',
                                    'Berikan infus cairan kristaloid 20mL/kgBB pada anak',
                                    'Lakukan kross matching produk darah',
                                ],
                                'edukasi' => [],
                                'kolaborasi' => [
                                    'Kolaborasi dalam menentukan jenis dan jumlah cairan (mis. Kristaloid, koloid)',
                                    'Kolaborasi dalam memberikan produk darah',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.02083',
                            'nama'     => 'Resusitasi Jantung Paru',
                            'definisi' => 'Memberikan pertolongan pertama pada kondisi henti napas dan henti jantung dengan teknik kombinasi kompresi pada dada dan bantuan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi keamanan penolong, lingkungan dan pasien',
                                    'Identifikasi respon pasien (mis. Memanggil pasien, menepuk bahu pasien)',
                                    'Monitor nadi karotis dan napas setiap 2 menit atau 5 siklus RJP',
                                ],
                                'terapeutik' => [
                                    'Pakai alat pelindung diri',
                                    'Aktifkan emergency medical system atau berteriak meminta tolong',
                                    'Posisikan pasien telentang di tempat datar dan keras',
                                    'Atur posisi penolong berlutut di samping korban',
                                    'Raba nadi karotis dalam waktu <10 detik',
                                    'Berikan rescue breathing jika di temukan ada nadi tetapi tidak ada nafas',
                                    'Kompresi dada 30 kali dikombinasikan dengan bantuan napas (ventilasi) 2 kali jika ditemukan tidak ada nadi dan tidak ada napas',
                                    'Kompresi dengan tumit telapak tangan menumpuk diatas telapak tangan yang lain tegak lurus pada pertengahan dada (seperdua bawah sternum)',
                                    'Kompresi dengan kedalaman kompresi 5-6 cm dengan kecepatan 100-120 kali per menit',
                                    'Bersihkan dan buka jalan nafas dengan head tlit-chin lift atau jaw thrust (jika curiga cedera servikal)',
                                    'Berikan bantuan nafas dengan menggunakan Bag Valve mask dengan teknik EC-Clamp',
                                    'Kombinasikan kompresi dan ventilasi selama 2 menit atau sebanyak 5 siklus',
                                    'Hentikan RJP jika ditemukan adanya tanda-tanda kehidupan, penolong yang lebih mahir datang, ditemukan adanya tanda-tanda kematian biologis, Do Not Resuscitation (DNR)',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur tindakan kepada keluarga atau pengantar pasien',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi tim medis untuk bantuan hidup lanjut',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0005 — Pola Napas Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0005',
                'diagkep_desc' => 'Pola Napas Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Inspirasi dan/atau ekspirasi yang tidak memberikan ventilasi adekuat',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Depresi pusat pernapasan',
                                'Hambatan upaya napas (mis. nyeri saat bernapas, kelemahan otot pernapasan)',
                                'Deformitas dinding dada',
                                'Deformitas tulang dada',
                                'Gangguan neuromuskular',
                                'Gangguan neurologis',
                                'Imaturitas neurologis',
                                'Penurunan energi',
                                'Obesitas',
                                'Posisi tubuh yang menghambat ekspansi paru',
                                'Sindrom hipoventilasi',
                                'Kerusakan inervasi diafragma (kerusakan saraf C5 ke atas)',
                                'Cedera pada medula spinalis',
                                'Efek agen farmakologis',
                                'Kecemasan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Dispnea'],
                            'objektif'  => [
                                'Penggunaan otot bantu pernapasan',
                                'Fase ekspirasi memanjang',
                                'Pola napas abnormal (mis. takipnea, bradipnea, hiperventilasi, kussmaul, cheyne-stokes)',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Ortopnea'],
                            'objektif'  => [
                                'Pernapasan pursed-lip',
                                'Pernapasan cuping hidung',
                                'Diameter thoraks anterior-posterior meningkat',
                                'Ventilasi semenit menurun',
                                'Kapasitas vital menurun',
                                'Tekanan ekspirasi menurun ekskursi dada berubah',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Depresi sistem saraf pusat',
                            'Cedera kepala',
                            'Trauma thoraks',
                            'Guillain Barre syndrome',
                            'Sklerosis multipel',
                            'Myasthenia gravis',
                            'Stroke',
                            'Kuadrifplegia',
                            'Intoksikasi alcohol',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.01004',
                            'nama' => 'Pola Napas',
                            'kriteria_hasil' => [
                                'Dispnea dari skala 1 meningkat menjadi skala 5 menurun',
                                'Penggunaan otot bantu napas dari skala 1 meningkat menjadi skala 3 sedang',
                                'Frekuensi napas dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01011',
                            'nama'     => 'Manajemen Jalan Napas',
                            'definisi' => 'Mengidentifikasi dan mengelola kepatenan jalan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor pola napas (frekuensi, kedalaman, usaha napas)',
                                    'Monitor bunyi napas tambahan (mis. gurgling, mengi, wheezing, ronkhi kering)',
                                    'Monitor sputum (jumlah, warna, aroma)',
                                ],
                                'terapeutik' => [
                                    'Pertahankan kepatenan jalan napas dengan head-tilt dan chin-lift (jaw-thrust jika curiga trauma survikal)',
                                    'Posisikan semi-fowler atau fowler',
                                    'Berikan minuman hangat',
                                    'Berikan oksigen, jika perlu',
                                ],
                                'edukasi' => [
                                    'Anjurkan asupan cairan 2000 ml/hari, jika tidak kontraindikasi',
                                    'Ajarkan teknik batuk efektif',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian bronkodilator, ekspektoran, mukolitik, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0006 — Risiko Aspirasi
            // ================================================================
            [
                'diagkep_id'   => 'D.0006',
                'diagkep_desc' => 'Risiko Aspirasi',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Respirasi',
                        'definisi'    => 'Beresiko mengalami masuknya sekresi gastrointestinal, sekresi orofaring, benda cair atau padat ke dalam saluran trakeobronkhial akibat disfungsi mekanisme protektif saluran napas',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Penurunan tingkat kesadaran',
                            'Penurunan refleks muntah dan/atau batuk',
                            'Gangguan menelan',
                            'Disfagia',
                            'Kerusakan mobilitas fisik',
                            'Peningkatan residu lambung',
                            'Peningkatan tekanan intragastric',
                            'Penurunan motilitas gastrointestinal',
                            'Sfingter esofagus bawah inkompeten',
                            'Perlambatan pengosongan lambung',
                            'Terpasang selang nasogastric',
                            'Terpasang trakeostomi atau endotracheal tube',
                            'Trauma/pembedahan leher, mulut, dan/atau wajah',
                            'Efek agen farmakologis',
                            'Ketidakmatangan koordinasi menghisap, menelan, bernapas',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Cedera kepala',
                            'Stroke',
                            'Cedera medulla spinalis',
                            'Guillain barre syndrome',
                            'Penyakit Parkinson',
                            'Keracunan obat dan alcohol',
                            'Pembesaran uterus',
                            'Miestenia gravis',
                            'Fistula trakeoesofagus',
                            'Striktura esofagus',
                            'Sclerosis multiple',
                            'Labiopalatoskizis',
                            'Atresia esofagus',
                            'Laringomalasia',
                            'Prematuritas',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.01006',
                            'nama' => 'Tingkat Aspirasi',
                            'kriteria_hasil' => [
                                'Tingkat kesadaran (5)',
                                'Kemampuan menelan (5)',
                                'Kebersihan mulut (5)',
                                'Dispnea (4)',
                                'Kelemahan otot (4)',
                                'Akumulasi secret (4)',
                                'Wheezing (4)',
                                'Batuk (4)',
                                'Penggunaan otot aksesoris (4)',
                                'Sianosis (4)',
                                'Gelisah (4)',
                                'Frekuensi napas (4)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01011',
                            'nama'     => 'Manajemen Jalan Napas',
                            'definisi' => 'Mengidentifikasi dan mengelola kepatenan jalan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor pola napas (frekuensi, kedalaman, usaha napas)',
                                    'Monitor bunyi napas tambahan (mis. Gurgling, mengi, wheezing, ronkhi kering)',
                                    'Monitor sputum (jumlah, warna, aroma)',
                                ],
                                'terapeutik' => [
                                    'Pertahankan kepatenan jalan napas dengan head ill dan chin lift',
                                    'Posisikan semi fowler atau fowler',
                                    'Berikan minuman hangat',
                                    'Lakukan fisioterapi dada, jika perlu',
                                    'Lakukan penghisapan lender kurang dari 15 detik',
                                    'Lakukan hiperoksigenasi sebelum penghisapan endotrakeal',
                                    'Keluarkan sumbatan benda padat dengan forsep McGill',
                                    'Berikan oksigen, jika perlu',
                                ],
                                'edukasi' => [
                                    'Anjurkan asupan cairan 2000 ml/hari, jika tidak kontraindikasi',
                                    'Ajarkan Teknik batuk efektif',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian bronkodilator, ekspektoran, mukolitik, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.01018',
                            'nama'     => 'Pencegahan Aspirasi',
                            'definisi' => 'Mengidentifikasi dan mengurangi risiko masuknya partikel makanan/cairan ke dalam paru-paru',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor tingkat kesadaran, batuk, muntah dan kemampuan menelan',
                                    'Monitor status pernapasan',
                                    'Monitor bunyi napas terutama setelah makan/minum',
                                    'Periksa residu gaster sebelum memberi asupan oral',
                                    'Periksa kepatenan selang nasogastric sebelum memberi asupan oral',
                                ],
                                'terapeutik' => [
                                    'Posisikan semi fowler (30-45 derajat) 30 menit sebelum memberi asupan oral',
                                    'Pertahankan posisi semi fowler pada pasien',
                                    'Pertahankan kepatenan jalan napas (mis. Teknik head tilt chin lift, jaw thrust, in line)',
                                    'Perhatikan pengembangan balon endotracheal tube (ETT)',
                                    'Lakukan penghisapan jalan napas, jika produksi secret meningkat',
                                    'Sediakan suction di ruangan',
                                    'Hindari memberi makan melalui selang gastrointestinal, jika residu banyak',
                                    'Berikan makanan dengan ukuran kecil atau lunak',
                                    'Berikan obat oral dalam bentuk cair',
                                ],
                                'edukasi' => [
                                    'Anjurkan makan secara perlahan',
                                    'Ajarkan strategi mencegah aspirasi',
                                    'Ajarkan Teknik mengunyah atau menelan, jika perlu',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0007 — Gangguan Sirkulasi Spontan
            // ================================================================
            [
                'diagkep_id'   => 'D.0007',
                'diagkep_desc' => 'Gangguan Sirkulasi Spontan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Ketidakmampuan untuk mempertahankan sirkulasi yang adekuat untuk menunjang keidupan',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Abnormalitas kelistrikan jantung',
                                'Abnormalitas struktur jantung',
                                'Penurunan fungsi ventrikel',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak berespon'],
                            'objektif'  => [
                                'Frekuensi nadi >50 kali/enit atau >150 kali/menit',
                                'Tekanan darah sistolik <60 mm Hg atau >200 mmHg',
                                'Frekuensi napas >6 kali/menit atau >30 kali/menit',
                                'Kesadaran menurun atau tidak sadar',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Suhu tubuh <34,5°C',
                                'Tidak ada produksi urin dalam 6 jam',
                                'Saturasi oksigen <85%',
                                'Gambaran EKG menunjukkan aritmia letal (mis. Ventricular tachycardia [VT], Ventricular fibrillation [VF], Asistol, Pulseless Electrical Activity [PEA])',
                                'Gambaran EKG menunjukkan aritmia mayor (mis. AV block derajat 2 tipe 2, AV block total, takiaritmia/bradiaritmia, supraventricular tachycardia [SVT], ventricular extrasystole [VES] simptomatik)',
                                'ETCO2>35 mmHg',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Henti jantung',
                            'Bradikardia',
                            'Takikardia',
                            'Sindrom coroner akut',
                            'Gagal jantung',
                            'Kardiomiopati',
                            'Miokarditis',
                            'Disrritmia',
                            'Trauma',
                            'Perdarahan (mis. Perdarahan gastrointestinal, rupture aorta, perdarahan intracranial)',
                            'Keracunan',
                            'Overdosis',
                            'Tenggelam',
                            'Emboli paru',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02015',
                            'nama' => 'Sirkulasi Spontan',
                            'kriteria_hasil' => [
                                'Tingkat kesadaran (5)',
                                'Frekuensi nadi (4)',
                                'Tekanan darah (3)',
                                'Frekuensi napas (3)',
                                'Suhu tubuh (3)',
                                'Saturasi oksigen (3)',
                                'Gambaran EKG aritmia (3)',
                                'ETCO2 (3)',
                                'Produksi urine (3)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02038',
                            'nama'     => 'Manajemen Defibrilasi',
                            'definisi' => 'Mengidentifikasi dan mengelola aliran listrik kuat dengan metode asinkron ke jantung melalui elektroda yang ditempatkan pada permukaan dada',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa irama pada monitor setelah RJP 2 menit',
                                ],
                                'terapeutik' => [
                                    'Lakukan resusitasi jantung paru (RJP) hingga mesin defibrillator siap',
                                    'Siapkan dan hidupkan mesin defibrilator',
                                    'Pasang monitor EKG',
                                    'Pastikan irama EKG henti jantung (VF atau VT tanpa nadi)',
                                    'Atur jumlah energi dengan mode asynchronized (360 joule untuk monopasi dan 120-200 joule untuk bifasik)',
                                    'Angkat paddle dari mesin dan oleskan jeli pada paddle',
                                    'Tempelkan paddle sternum (kanan) pada sisi kanan sternum di bawah klavikula dan paddle apeks (kiri) pada garis midaksilaris setinggi elektroda V6',
                                    'Hentikan RJP saat defibrillator siap',
                                    'Teriak bahwa defibrilator telah siap (mis. I am clear, you re clear, everybody\'s clear)',
                                    'Berikan shock dengan menekan tombol pada kedua paddle bersamaan',
                                    'Angkat paddle dan langsung lanjutkan RJP tanpa menunggu hasil irama yang muncul pada monitor setelah pemberian defibrilasi',
                                    'Lanjutkan RJP sampai 2 menit',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur tindakan kepada keluarga atau pengantar pasien',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi tim medis untuk bantuan hidup lanjut',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.02083',
                            'nama'     => 'Resusitasi Jantung Paru',
                            'definisi' => 'Memberikan pertolongan pertama pada kondisi henti napas dan henti jantung dengan Teknik kombinasi kompresi pada dada dan bantuan napas',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi keamanan penolong, lingkungan dan pasien',
                                    'Identifikasi respon pasien (mis. Memanggil pasien, menepuk bahu pasien)',
                                    'Monitor nadi karotis dan napas setiap 2 menit atau 5 siklus RJP',
                                ],
                                'terapeutik' => [
                                    'Pakai alat pelindung diri',
                                    'Aktifkan emergency medical system atau berteriak meminta tolong',
                                    'Posisikan pasien telentang di tempat datar dan keras',
                                    'Atur posisi penolong berlutut di samping korban',
                                    'Raba nadi karotis dalam waktu <10 detik',
                                    'Berikan rescue breathing jika ditemukan ada nadi tetapi tidak ada napas',
                                    'Kompresi dada 30 kali dikombinasikan dengan bantuan napas (ventilasi) 2 kali',
                                    'Kompresi dengan tumit telapak tangan menumpuk diatas telapak tangan yang lain tegak lurus pada pertengahan dada (seperdua bawah sternum)',
                                    'Kompresi dengan kedalaman kompresi 5-6 cm dengan kecepatan 100-120 kali per menit',
                                    'Bersihkan dan buka jalan nafas dengan head tilt-chin lift atau jaw thrust (jika curiga cedera servikal)',
                                    'Berikan bantuan nafas dengan menggunakan Bag Valve mask dengan Teknik EC-Clamp',
                                    'Kombinasikan kompresi dan ventilasi selama 2 menit atau sebanyak 5 siklus',
                                    'Hentikan RJP jika ditemukan adanya tanda-tanda kehidupan, penolong yang lebih mahir datang, ditemukan adanya tanda-tanda kematian biologis, Do Not Resuscitation (DNR)',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur tindakan kepada keluarga atau pengantar pasien',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi tim medis untuk bantuan hidup lanjut',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0008 — Penurunan Curah Jantung
            // ================================================================
            [
                'diagkep_id'   => 'D.0008',
                'diagkep_desc' => 'Penurunan Curah Jantung',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Ketidakmampuan jantung memompa darah untuk memenuhi kebutuhan metabolisme tubuh',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Perubahan irama jantung',
                                'Perubahan frekuensi jantung',
                                'Perubahan kontraktilitas',
                                'Perubahan preload',
                                'Perubahan afterload',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => [
                                'Perubahan irama jantung: Palpitasi',
                                'Perubahan preload: Lelah',
                                'Perubahan afterload: Dispnea',
                                'Perubahan kontraktilitas: Parocymal nocturnal dypnea (PND), Ortopnea, Batuk',
                            ],
                            'objektif' => [
                                'Perubahan irama jantung: Bradikardia/takikardia, Gambaran EKG aritmia atau gangguan konduksi',
                                'Perubahan preload: Edema, Distensi vena jugularis, Central venous pressure (CVP) meningkat/menurun, Hepatomegaly',
                                'Perubahan afterload: Tekanan darah meningkat/menurun, Nadi perifer teraba lemah, Capillary refill time >3 detik, Oliguria, Warna kulit pucat dan/atau sianosis',
                                'Perubahan kontraktilitas: Terdengar suara jantung S3 dan/atau S4, Ejection fraction (EF) menurun',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => [
                                'Perubahan preload (tidak tersedia)',
                                'Perubahan afterload (tidak tersedia)',
                                'Perubahan kontraktilitas (tidak tersedia)',
                                'Perilaku/emosional: Cemas, Gelisah',
                            ],
                            'objektif' => [
                                'Perubahan preload: Murmur jantung, Berat badan bertambah, Pulmonary artery wedge pressure (PAWP) menurun',
                                'Perubahan afterload: Pulmonary vascular resistance (PVR) meningkat/menurun, Systemic vascular resistance (SVR) meningkat/menurun',
                                'Perubahan kontraktilitas: Cardiac index (CI) menurun, Left ventricular stroke work index (LVSWI) menurun, Stroke volume index (SVI) menurun',
                                'Perilaku/emosional (tidak tersedia)',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Gagal jantung kongestif',
                            'Sindrom coroner akut',
                            'Stenosis mitral',
                            'Regurgitasi mitral',
                            'Stenosis aorta',
                            'Regurgitasi aorta',
                            'Stenosis trikuspidal',
                            'Regurgitasi trikuspidal',
                            'Stenosis pulmonal',
                            'Regurgitasi pulmonal',
                            'Aritmia',
                            'Penyakit jantung bawaan',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02008',
                            'nama' => 'Curah Jantung',
                            'kriteria_hasil' => [
                                'Kekuatan nadi perifer dari skala 5 meningkat menjadi skala 1 menurun',
                                'Palpitasi (3)',
                                'Tekanan darah (5)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02075',
                            'nama'     => 'Perawatan Jantung',
                            'definisi' => 'Mengidentifikasi, merawat dan membatasi komplikasi akibat ketidakseimbangan antara suplai dan konsumsi oksigen miokard',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi tanda/gejala primer penurunan curah jantung (meliputi dispnea, kelelahan, edema, ortopnea, paroxysmal nocturnal dyspnea, peningkatan CVP)',
                                    'Identifikasi tanda/gejala sekunder penurunan curah jantung (meliputi peningkatan berat badan, hepatomegaly, distensi vena jugularis, palpitasi, ronkhi basah, oliguria, batuk, kulit pucat)',
                                    'Monitor tekanan darah (termasuk tekanan darah ortostatik) jika perlu',
                                    'Monitor intake dan output cairan',
                                    'Monitor aritmia (kelainan irama dan frekuensi)',
                                    'Monitor fungsi alat pacu jantung',
                                    'Periksa tekanan darah dan frekuensi nadi sebelum dan sesudah aktivitas',
                                    'Periksa tekanan darah dan frekuensi nadi sebelum pemberian obat (mis. Beta blocker, ACE inhibitor, calcium channel blocker, digoksin)',
                                ],
                                'terapeutik' => [
                                    'Posisikan pasien semi-fowler atau fowler dengan kaki ke bawah atau posisi nyaman',
                                    'Berikan diet jantung yang sesuai (mis. Batasi asupan kafein, natrium, kolesterol, dan makanan tinggi lemak)',
                                    'Gunakan stocking elastis atau pneumatic intermiten, sesuai indikasi',
                                    'Fasilitasi pasien dan keluarga untuk modifikasi gaya hidup sehat',
                                    'Berikan terapi relaksasi untuk mengurangi stress, jika perlu',
                                    'Berikan dukungan emosional dan spiritual',
                                    'Berikan oksigen untuk memperthanakan saturasi oksigen >94%',
                                ],
                                'edukasi' => [
                                    'Anjurkan beraktivitas fisik sesuai toleransi',
                                    'Anjurkan beraktivitas fisik secara bertahap',
                                    'Anjurkan berhenti merokok',
                                    'Ajarkan pasien dan keluarga mengukur berat badan harian',
                                    'Ajarkan pasien dan keluarga mengukur intake dan output cairan harian',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian antiaritmia, jika perlu',
                                    'Rujuk ke program rehabilitasi jantung',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0009 — Perfusi Perifer Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0009',
                'diagkep_desc' => 'Perfusi Perifer Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Penurunan sirkulasi darah pada level kapiler yang dapat mengganggu metabolisme tubuh',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Hiperglikemia',
                                'Penurunan konsentrasi hemoglobin',
                                'Peningkatan tekanan darah',
                                'Kekurangan volume cairan',
                                'Penurunan aliran arteri dan/atau vena',
                                'Kurang terpapar informasi tentang faktor pemberat (mis. merokok, gaya hidup monoton, trauma, obesitas, asupan garam, imobilitas)',
                                'Kurang terpapar informasi tentang proses penyakit (mis. diabetes melitus, hiperlipidemia)',
                                'Kurang aktivitas fisik',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Pengisian kapiler >3 detik',
                                'Nadi perifer menurun atau tidak teraba',
                                'Akral teraba dingin',
                                'Warna kulit pucat',
                                'Turgor kulit menurun',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => [
                                'Parastesia',
                                'Nyeri ekstremitas (klaudikasi intermiten)',
                            ],
                            'objektif' => [
                                'Edema',
                                'Penyembuhan luka lambat',
                                'Indeks ankle-brachial kurang dari 0.90',
                                'Bruit femoralis',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Tromboflebitis',
                            'Diabetes melitus',
                            'Anemia',
                            'Gagal jantung kongestif',
                            'Kelainan jantung kongenital',
                            'Trombosis arteri',
                            'Varises',
                            'Trombosis vena dalam',
                            'Sindrom kompartemen',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02011',
                            'nama' => 'Perfusi Perifer',
                            'kriteria_hasil' => [
                                'Penyembuhan luka meningkat',
                                'Edema perifer menurun',
                                'Nyeri ekstremitas menurun',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02079',
                            'nama'     => 'Perawatan Sirkulasi',
                            'definisi' => 'Mengidentifikasi dan merawat area lokal dengan keterbatasan sirkulasi perifer',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa sirkulasi perifer (mis. Nadi periper, edema, pengisian kapiler, warna, suhu)',
                                    'Identifikasi faktor risiko gangguan sirkulasi (mis. diabetes, perokok, orang tua hipertensi dan kadar kolestrol tinggi)',
                                    'Monitor panas, kemerahan, nyeri atau bengkak pada ekstremitas',
                                ],
                                'terapeutik' => [
                                    'Hindari pemasangan infus atau pengambilan darah di area keterbatasan perfusi',
                                    'Hindari pengukuran tekanan darah pada ekstermitas dengan keterbatasan perfusi',
                                    'Hindari penekanan dan pemasangan tourniquet pada area yang cidera',
                                    'Lakukan pencegahan infeksi',
                                    'Lakukan perawatan kaki dan kuku',
                                    'Lakukan hidrasi',
                                ],
                                'edukasi' => [
                                    'Anjurkan berhenti merokok',
                                    'Anjurkan berolahraga rutin',
                                    'Anjurkan mengecek air mandi untuk menghindari kulit terbakar',
                                    'Anjurkan menggunakan obat penurun tekanan darah, anti koagulan, dan penurun kolestrol jika perlu',
                                    'Anjurkan minum obat pengontrol tekanan darah secara teratur',
                                    'Anjurkan menghindari penggunaan obat penyekat beta',
                                    'Anjurkan melakukan perawatan kulit yang tepat (mis. melembabkan kulit kering pada kaki)',
                                    'Anjurkan program rehabilitasi vaskular',
                                    'Ajarkan program diet untuk memperbaiki sirkulasi (mis. rendah lemak jenuh, minyak ikan omega 3)',
                                    'Informasikan tanda dan gejala darurat yang harus di laporkan (mis. rasa sakit yang tidak hilang saat istirahat, luka tidak sembuh, hilangnya rasa)',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                        [
                            'kode'     => 'I.06195',
                            'nama'     => 'Manajemen Sensasi Perifer',
                            'definisi' => 'Mengidentifikasi dan mengelola ketidaknyamanan pada perubahan sensasi perifer',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi penyebab perubahan sensasi',
                                    'Identifikasi penggunaan alat pengikat, prostesis, sepatu, dan pakaian',
                                    'Periksa perbedaan sensasi tajam atau tumpul',
                                    'Periksa perbedaan sensasi panas atau dingin',
                                    'Periksa kemampuan mengidentifikasi lokasi dan tekstur benda',
                                    'Monitor terjadinya parestesia, jika perlu',
                                    'Monitor perubahan kulit',
                                    'Monitor adanya troboflebitis dan tromboembolli vena',
                                ],
                                'terapeutik' => [
                                    'Hindari pemakaian benda benda yang berlebihan suhunya (terlalu panas atau dingin)',
                                ],
                                'edukasi' => [
                                    'Anjurkan penggunaan termometer untuk menguji suhu air',
                                    'Anjurkan penggunaan sarung tanan termal saat memasak',
                                    'Anjurkan memakai sepatu lembut dan bertumit rendah',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian analgesik jika perlu',
                                    'Kolaborasi pemberian kortikosteroid, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0010 — Risiko Gangguan Sirkulasi Spontan
            // ================================================================
            [
                'diagkep_id'   => 'D.0010',
                'diagkep_desc' => 'Risiko Gangguan Sirkulasi Spontan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami ketidakmampuan untuk mempertahankan sirkulasi yang adekuat untuk menunjang kehidupan',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Kekurangan volume cairan',
                            'Hipoksia',
                            'Hipotermia',
                            'Hipokalemia/hiperkalemia',
                            'Hipoglikemia/hiperglikemia',
                            'Asidosis',
                            'Toksin (mis. Keracunan, overdosis obat)',
                            'Tamponade jantung',
                            'Tension pneumothorax',
                            'Thrombosis jantung',
                            'Thrombosis paru (emboli paru)',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Bradikardia',
                            'Takikardia',
                            'Sindrom jantung coroner akut',
                            'Gagal jantung',
                            'Kardiomiopati',
                            'Miokarditis',
                            'Disritmia',
                            'Trauma',
                            'Perdarahan (mis. Perdarahan gastrointestinal, rupture aorta, perdarahan intracranial)',
                            'Keracunan',
                            'Overdosis',
                            'Tenggelam',
                            'Emboli paru',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02015',
                            'nama' => 'Sirkulasi Spontan',
                            'kriteria_hasil' => [
                                'Tingkat kesadaran (5)',
                                'Frekuensi nadi (4)',
                                'Tekanan darah (3)',
                                'Frekuensi napas (3)',
                                'Suhu tubuh (3)',
                                'Saturasi oksigen (3)',
                                'Gambaran EKG aritmia (3)',
                                'ETCO2 (3)',
                                'Produksi urine (3)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02080',
                            'nama'     => 'Pertolongan Pertama',
                            'definisi' => 'Memberikan penanganan dasar dan segera pada kondisi kegawat daruratan baik dengan alat maupun tanpa alat',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi keamanan penolong, pasien, lingkungan',
                                    'Identifikasi respon pasien dengan AVPU (alert, verbal, pain, unresponsive)',
                                    'Monitor tanda-tanda vital',
                                    'Monitor karakteristik luka (mis. Drainase, warna, ukuran, bau)',
                                ],
                                'terapeutik' => [
                                    'Meminta pertolongan, jika perlu',
                                    'Lakukan RICE (rest, ice, compression, elevation) pada cedera otot ekstremitas',
                                    'Lakukan penghentian perdarahan (mis. Penekanan, balut tekan, pengaturan posisi)',
                                    'Bersihkan kulit dari racun atau bahan kimia yang menempel dengan sabun dan air yang mengalir',
                                    'Lepaskan sengatan dari kulit',
                                    'Lepaskan gigitan serangga dari kulit menggunakan pinset atau alat yang sesuai',
                                ],
                                'edukasi' => [
                                    'Ajarkan Teknik perawatan luka',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian obat-obatan (mis. Antibiotic, profilaksis, vaksi, anti histamin, anti inflamasi, dan analgetik, jika perlu)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0011 — Risiko Penurunan Curah Jantung
            // ================================================================
            [
                'diagkep_id'   => 'D.0011',
                'diagkep_desc' => 'Risiko Penurunan Curah Jantung',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami pemompaan jantung yang tiak adekuat untuk memenuhi kebutuhan metabolisme tubuh',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Perubahan afterload',
                            'Perubahan frekuensi jantung',
                            'Perubahan irama jantung',
                            'Perubahan kontraktilitas',
                            'Perubahan preload',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Gagal jantung kongestif',
                            'Sindrom koroner akut',
                            'Gangguan katub jantung (stenosis/regurgitasi aorta, pulmonalis, trikuspidalis, atau mitralis)',
                            'Atrial/venticular septal defect',
                            'Aritmia',
                            'Penyakit Paru Obstruktif Kronis (PPOK)',
                            'Gangguan metabolik',
                            'Gangguan muskuloskeletal',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02008',
                            'nama' => 'Curah Jantung',
                            'kriteria_hasil' => [
                                'Kekuatan nadi perifer dari skala 5 meningkat menjadi skala 1 menurun',
                                'Bradikardi dari skala 1 meningkat menjadi skala 5 menurun',
                                'Takikardi dari skala 1 meningkat menjadi skala 5 menurun',
                                'Ortopnea dari skala 2 cukup meningkat menjadi skala 4 cukup menurun',
                                'Suara jantung S3 dan S4 dari skala 1 meningkat menjadi skala 3 sedang',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02075',
                            'nama'     => 'Perawatan Jantung',
                            'definisi' => 'Mengidentifikasi, merawat dan membatasi komplikasi akibat ketidakseimbangan antara suplai dan konsumsi oksigen miokard',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi tanda/gejala primer penurunan curah jantung (meliputi dispnea, kelelahan, edema, ortopnea, paroxysmal nocturnal dyspnea, peningkatan CVP)',
                                    'Identifikasi tanda/gejala sekunder penurunan curah jantung (meliputi peningkatan berat badan, hepatomegaly, distensi vena jugularis, palpitasi, ronkhi basah, oliguria, batuk, kulit pucat)',
                                    'Monitor intake dan output cairan',
                                    'Monitor aritmia (kelainan irama dan frekuensi)',
                                    'Monitor fungsi alat pacu jantung',
                                    'Periksa tekanan darah dan frekuensi nadi sebelum dan sesudah aktivitas',
                                ],
                                'terapeutik' => [
                                    'Posisikan pasien semi-fowler atau fowler dengan kaki ke bawah atau posisi nyaman',
                                    'Berikan diet jantung yang sesuai (mis. batasi asupan kafein, natrium, kolesterol, dan makanan tinggi lemak)',
                                    'Gunakan stocking elastis atau pneumatik intermiten, sesuai indikasi',
                                    'Berikan terapi relaksasi untuk mengurangi stress, jika perlu',
                                    'Berikan dukungan emosional dan spiritual',
                                    'Berikan oksigen untuk memperthanakan saturasi oksigen >94%',
                                ],
                                'edukasi' => [
                                    'Anjurkan beraktivitas fisik sesuai toleransi',
                                    'Ajarkan pasien dan keluarga mengukur intake dan output cairan harian',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian antiaritmia, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0012 — Risiko Perdarahan
            // ================================================================
            [
                'diagkep_id'   => 'D.0012',
                'diagkep_desc' => 'Risiko Perdarahan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami kehilangan darah baik internal (terjadi dalam tubuh) maupun eksternal (terjadi diluar tubuh)',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Aneurisma',
                            'Gangguan gastrointestinal (mis. Ulkus lambung, polip, varises)',
                            'Gangguan fungsi hati (mis. Sirosis hepatitis)',
                            'Komplikasi kehamilan (mis. Ketuban pecah sebelum waktunya, plasenta previa/abrubsio, kehamilan kembar)',
                            'Komplikasi pasca partum (mis. Atoni uterus, retensi plasenta)',
                            'Gangguan koagulasi (mis. Trombositopenia)',
                            'Efek agen farmakologis',
                            'Tindakan pembedahan',
                            'Trauma',
                            'Kurang terpapar informasi tentang pencegahan perdarahan',
                            'Proses keganasan',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Aneurisma',
                            'Koagulopati intravaskuler diseminata',
                            'Sirosis hepatis',
                            'Ulkus lambung',
                            'Varises',
                            'Trombositopenia',
                            'Ketuban pecah sebelum waktunya',
                            'Plasenta previa/abrubsio',
                            'Atonia uterus',
                            'Retensi plasenta',
                            'Tindakan pembedahan',
                            'Kanker',
                            'Trauma',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02017',
                            'nama' => 'Tingkat Perdarahan',
                            'kriteria_hasil' => [
                                'Hemoglobin membaik',
                                'Tekanan darah cukup membaik',
                                'Suhu tubuh membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02067',
                            'nama'     => 'Pencegahan Perdarahan',
                            'definisi' => 'Mengidentifikasi dan menurunkan risiko atau komplikasi stimulus yang menyebabkan perdarahan atau risiko perdarahan',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor tanda dan gejala perdarahan',
                                    'Monitor hematokrit/hemoglobin sebelum dan setelah kehilangan darah',
                                ],
                                'terapeutik' => [
                                    'Pertahankan bed rest selama perdarahan',
                                    'Batasi tindakan invasive, jika perlu',
                                    'Hindari pengukuran suhu rektal',
                                ],
                                'edukasi' => [
                                    'Jelaskan tanda dan gejala perdarahan',
                                    'Anjurkan meningkatkan asupan cairan untuk menghindari konstipasi',
                                    'Anjurkan menghindari aspirin atau antikoagulan',
                                    'Anjurkan meningkatkan asupan makanan dan vitamin K',
                                    'Anjurkan segera melapor jika terjadi perdarahan',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian obat pengontrol perdarahan, jika perlu',
                                    'Kolaborasi pemberian produk darah, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.02028',
                            'nama'     => 'Balut Tekan',
                            'definisi' => 'Membalut luka dengan tekanan untuk mencegah atau menghentikan perdarahan',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor perban untuk memantau drainase luka',
                                    'Monitor jumlah dan warna cairan drainase dari luka',
                                    'Periksa kecepatan dan denyut nadi distal',
                                    'Periksa akral, kondisi kulit dan pengisian kapiler distal',
                                ],
                                'terapeutik' => [
                                    'Pasang sarung tangan',
                                    'Tinggikan bagian tubuh yang cedera diatas level jantung, jika tidak ada fraktur',
                                    'Tutup luka dengan kasa tebal',
                                    'Tekan kasa dengan kuat diatas luka selama perdarahan',
                                    'Fiksasi kasa dengan plaster setelah perdarahan berhenti',
                                    'Tekan arteri (pressure point) yang mengarah ke area perdarahan',
                                ],
                                'edukasi' => [
                                    'Jelaskan tujuan dan prosedur balut tekan',
                                    'Anjurkan membatasi gerak pada area cidera',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],


            // ================================================================
            // D.0013 — Risiko Perfusi Gastrointestinal Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0013',
                'diagkep_desc' => 'Risiko Perfusi Gastrointestinal Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami penurunan sirkulasi gastrointestinal',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Perdarahan gastrointestinal akut',
                            'Trauma abdomen',
                            'Sindroma kompartemen abdomen',
                            'Aneurisma aorta abdomen',
                            'Varises gastroesofagus',
                            'Penurunan kinerja ventrikel kiri',
                            'Koagulatipati (mis. anemia sel sabit, koagulopati intravaskuler diseminata)',
                            'Penurunan konsentrasi hemoglobin',
                            'Keabnormalan masa protrombin dan/atau masa tromboplastin parsial',
                            'Disfungsi hati (mis. sirosis, hepatitis)',
                            'Disfungsi ginjal (mis. ginjal polikistik, stenosis arteri ginjal, gagal ginjal)',
                            'Disfungsi gastrointestinal (mis. ulkus duodenum atau ulkus lambung, kolitis iskemik, pankreatitis iskemik)',
                            'Hiperglikemia',
                            'Ketidakstabilan hemodinamik',
                            'Efek agen farmakologis',
                            'Usia > 60 tahun',
                            'Efek samping tindakan (cardiopulmonary bypass, anestesi, pembedahan lambung)',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Varises gastroesofagus',
                            'Aneurisma aorta abdomen',
                            'Diabetes melitus',
                            'Sirosis hepatis',
                            'Perdarahan gastrointestinal akut',
                            'Gagal jantung kongestif',
                            'Koagulasi intravaskuler diseminata',
                            'Ulkus duodenum atau ulkus lambung',
                            'Kolitis iskemik',
                            'Pankreatitis iskemik',
                            'Ginjal polikistik',
                            'Stenosis arteri ginjal',
                            'Gagal ginjal',
                            'Sindroma kompartemen abdomen',
                            'Trauma abdomen',
                            'Anemia',
                            'Pembedahan jantung',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02010',
                            'nama' => 'Perfusi Gastrointestinal',
                            'kriteria_hasil' => [
                                'Mual dari skala 1 meningkat menjadi skala 5 menurun',
                                'Muntah dari skala 1 meningkat menjadi skala 5 menurun',
                                'Nyeri dari abdomen skala 1 meningkat menjadi skala 5 menurun',
                                'Asites dari skala 1 meningkat menjadi skala 5 menurun',
                                'Konstipasi dari skala 1 meningkat menjadi skala 5 menurun',
                                'Bising usus dari skala 1 memburuk menjadi skala 5 membaik',
                                'Nafsu makan dari skala 1 memburuk menjadi skala 5 membaik',
                                'Frekuensi BAB dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.01004',
                            'nama'     => 'Manajemen Perdarahan',
                            'definisi' => 'Mengidentifikasi dan mengelola kehilangan darah saat terjadi perdarahan',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi penyebab perdarahan',
                                    'Periksa adanya darah pada muntah, sputum, feses, urine, pengeluaran NGT dan drainase luka, jika perlu',
                                    'Periksa ukuran dan karakteristik, jika ada',
                                    'Monitor terjadinya perdarahan (sifat dan jumlah)',
                                    'Monitor nilai hemoglobin dan hematokrit sebelum dan setelah kehilangan darah',
                                    'Monitor tekanan darah dan parameter hemodinamik (tekanan vena sentral dan tekanan baji kapiler atau arteri pulmonal), jika ada',
                                    'Monitor intake dan output cairan',
                                    'Monitor koagulasi darah (protrombin time (PT), partial thromboplastin time (PTT), fibrinogen, degradasi fibrin, dan jumlah trombosit), jika ada',
                                    'Monitor deliveri oksigen jaringan (mis. PaO2, SaO2, hemoglobin, dan curah jantung)',
                                    'Monitor tanda dan gejala perdarahan masif',
                                ],
                                'terapeutik' => [
                                    'Istirahatkan area yang mengalami perdarahan',
                                    'Berikan kompres dingin, jika perlu',
                                    'Lakukan penekanan atau balut tekan, jika perlu',
                                    'Tinggikan ekstremitas yang mengalami perdarahan',
                                    'Pertahankan akses IV',
                                ],
                                'edukasi' => [
                                    'Jelaskan tanda-tanda perdarahan',
                                    'Anjurkan melapor jika menemukan tanda-tanda perdarahan',
                                    'Anjurkan membatasi aktivitas',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian cairan, jika perlu',
                                    'Kolaborasi pemberian transfusi darah, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.03094',
                            'nama'     => 'Konseling Nutrisi',
                            'definisi' => 'Memberikan bimbingan dalam melakukan modifikasi asupan nutrisi',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi kebiasaan makan dan perilaku makan yang akan diubah',
                                    'Identifikasi kemajuan modifikasi diet secara reguler',
                                    'Monitor intake dan output cairan, nilai hemoglobin, tekanan darah, kenaikan berat badan, dan kebiasaan membeli makanan',
                                ],
                                'terapeutik' => [
                                    'Bina hubungan terapeutik',
                                    'Sepakati lama waktu pemberian konseling',
                                    'Tetapkan tujuan jangka pendek dan jangka Panjang yang realistis',
                                    'Gunakan standar nutrisi sesuai program diet dalam mengevaluasi kecukupan asupan makanan',
                                    'Pertimbangkan faktor-faktor yang mempengaruhi pemenuhan kebutuhan gizi (mis. Usia, tahap pertumbuhan dan perkembangan, penyakit)',
                                ],
                                'edukasi' => [
                                    'Informasikan perlunya modifikasi diet (misal: penurunan atau penambahan berat badan, pembatasan natrium atau cairan, pengurangan kolesterol)',
                                    'Jelaskan program gizi dan persepsi pasien terhadap diet yang diprogramkan',
                                ],
                                'kolaborasi' => [
                                    'Rujuk pada ahli gizi, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0014 — Risiko Perfusi Miokard Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0014',
                'diagkep_desc' => 'Risiko Perfusi Miokard Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami penurunan sirkulasi arteri koroner yang dapat mengganggu metabolisme miokard',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Hipertensi',
                            'Hiperlipidemia',
                            'Hiperglikemia',
                            'Hipoksemia',
                            'Hipoksia',
                            'Kekurangan volume cairan',
                            'Pembedahan jantung',
                            'Penyalahgunaan zat',
                            'Spasme arteri koroner',
                            'Peningkatan protein C-reaktif',
                            'Tamponade Jantung',
                            'Efek agen Farmakologis',
                            'Riwayat penyakit kardiovaskuler pada keluarga',
                            'Kurang terpapar informasi tentang faktor risiko yang dapat diubah (mis. Merokok, gaya hidup kurang gerak, obesitas)',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Bedah jantung',
                            'Tamponade jantung',
                            'Sindrom koroner akut',
                            'Diabetes mellitus',
                            'Hipertensi',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02011',
                            'nama' => 'Perfusi Miokard',
                            'kriteria_hasil' => [
                                'Tekanan darah membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02075',
                            'nama'     => 'Perawatan Jantung',
                            'definisi' => 'Mengidentifikasi, merawat dan membatasi komplikasi akibat ketidakseimbangan antara suplai dan konsumsi oksigen miokard',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor tekanan darah (termasuk tekanan darah ortostatik, jika perlu)',
                                    'Monitor intake dan output cairan saturasi oksigen',
                                    'Periksa tekanan darah dan frekuensi nadi sebelum dan sesudah aktivitas',
                                ],
                                'terapeutik' => [
                                    'Posisikan pasien semi-fowler atau fowler dengan kaki kebawah atau posisi nyaman',
                                    'Berikan diet jantung yang sesuai',
                                    'Berikan oksigen untuk mempertahankan saturasi oksigen >94%',
                                ],
                                'edukasi' => [
                                    'Anjurkan beraktivitas fisik sesuai toleransi',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0015 — Risiko Perfusi Perifer Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0015',
                'diagkep_desc' => 'Risiko Perfusi Perifer Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Berisiko mengalami penurunan sirkulasi darah pada level kapiler yang dapat mengganggu metabolisme tubuh',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Hiperglikemia',
                            'Gaya hidup kurang gerak',
                            'Hipertensi',
                            'Merokok',
                            'Prosedur endovaskuler',
                            'Trauma',
                            'Kurang terpapar informasi tentang faktor pemberat (mis. merokok, gaya hidup kurang gerak, obesitas, imobilitas)',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Arterosklerosis',
                            'Raynaud\'s disease',
                            'Trombosis arteri',
                            'Atritis reumatoid',
                            'Leriche\'s syndrome',
                            'Aneurisma',
                            'Buerger\'s disease',
                            'Varises',
                            'Diabetes melitus',
                            'Hipotensi',
                            'Kanker',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02011',
                            'nama' => 'Perfusi Perifer',
                            'kriteria_hasil' => [
                                'Kekuatan nadi prefier dari skala 1 menurun menjadi skala 5 meningkat',
                                'Warna kulit pucat dari skala 1 menjadiskala 5 membaik',
                                'Pengisian kapiler dari skala 1 memburuk menjadiskala 5 membaik',
                                'Akral skala 1 memburuk menjadiskala 5 membaik',
                                'Turgor kulit dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02068',
                            'nama'     => 'Pencegahan Syok',
                            'definisi' => 'Mengidentifikasi dan menurunkan risiko terjadinya ketidakmampuan tubuh menyediakan oksigen dan nutrien untuk mencukupi kebutuhan jaringan',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor status kardiopulmonal (frekuensi dan kekuatan nadi, frekuensi napas, TD, MAP)',
                                    'Monitor status oksigenasi (oksimetri nadi, AGD)',
                                    'Monitor status cairan (masukan dan haluaran, turgor kulit, CRT)',
                                    'Monitor tingkat kesadaran dan respon pupil',
                                    'Periksa riwayat alergi',
                                ],
                                'terapeutik' => [
                                    'Berikan oksigen untuk mempertahankan saturasi oksigen >94%',
                                    'Persiapkan intubasi dan ventilasi mekanis, jika perlu',
                                    'Pasang jalur IV, jika perlu',
                                    'Pasang kateter urine untuk menilai produksi urine, jika perlu',
                                    'Lakukan skin test untuk mencegah reaksi alergi',
                                ],
                                'edukasi' => [
                                    'Jelaskan penyebab/faktor risiko syok',
                                    'Jelaskan tanda dan gejala awal syok',
                                    'Anjurkan melapor jika menemukan/merasakan tanda dan gejala awal syok',
                                    'Anjurkan memperbanyak asupan cairan oral',
                                    'Anjurkan menghindari alergen',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian IV, jika perlu',
                                    'Kolaborasi pemberian transfusi darah, jika perlu',
                                    'Kolaborasi pemberian antiinflamasi, jika perlu',
                                ],
                            ],
                        ],
                        [
                            'kode'     => 'I.02079',
                            'nama'     => 'Perawatan Sirkulasi',
                            'definisi' => 'Mengidentifikasi dan merawat area lokal dengan keterbatasan sirkulasi perifer',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa sirkulasi perifer (mis. nadi perifer, edema, pengisian kapiler, warna, suhu, anklebrachial index)',
                                    'Identifikasi faktor risiko gangguan sirkulasi (mis. diabetes, perokok, orang tua, hipertensi dan kadar gula tinggi)',
                                    'Monitor panas, kemerahan, nyeri atau bengkak pada ekstremitas',
                                ],
                                'terapeutik' => [
                                    'Hindari pemasangan infus atau pengambilan darah di area keterbatasan perfusi',
                                    'Hindari pengukuran tekanan darah pada ekstremitas dengan keterbatasan perfusi',
                                    'Hindari penekanan dan pemasangan tourniquet pada area yang cedera',
                                    'Lakukan pencegahan infeksi',
                                    'Lakukan perawatan kaki dan kuku',
                                    'Lakukan hidrasi',
                                ],
                                'edukasi' => [
                                    'Anjurkan berhenti merokok',
                                    'Anjurkan berolahraga rutin',
                                    'Anjurkan mengecek air mandi untuk menghindari kulit terbakar',
                                    'Anjurkan menggunakan obat penurun tekanan darah, antikoagulan, dan penurunan kolestrol, jika perlu',
                                    'Anjurkan minum obat pengontrol tekanan darah secara teratur',
                                    'Anjurkan menghindari penggunaan obat penyekat beta',
                                    'Anjurkan melakukan perawatan kulit yang tepat (mis. melembabkan kulit kering pada kaki)',
                                    'Anjurkan program rehabilitasi vaskular',
                                    'Ajarkan program diet untuk memperbaiki sirkulasi (mis. rendah lemak jenuh, minyak ikan omega 3)',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0016 — Risiko Perfusi Renal Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0016',
                'diagkep_desc' => 'Risiko Perfusi Renal Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami penurunan sirkulasi darah ke ginjal',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Kekurangan volume cairan',
                            'Embolisme vaskuler',
                            'Vaskulitis',
                            'Hipertenai',
                            'Disfungsi ginjal',
                            'Hiperglikemia',
                            'Keganasan',
                            'Pembedahan jantung',
                            'Bypass kardiopulmonal',
                            'Hipoksemia',
                            'Hipoksia',
                            'Asidosis metabolik',
                            'Trauma',
                            'Sindrom kompartemen abdomen',
                            'Luka bakar',
                            'Sepsis',
                            'Sindrom respon inflamasi sistemik',
                            'Lanjut usia',
                            'Merokok',
                            'Penyalahgunaan zat',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Diabetes melitus',
                            'Hipertensi',
                            'Aterosklorosis',
                            'Syok',
                            'Keganasan',
                            'Luka bakar',
                            'Pembedahan jantung',
                            'Penyakit ginjal (mis. Ginjal polikistik, stenosis artesi ginjal, gagal ginjal, glumerulonefritis, nefritis intersisial, nekrosis kortokal bilateral, polinefritis)',
                            'Trauma',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02013',
                            'nama' => 'Perfusi Renal',
                            'kriteria_hasil' => [
                                'Jumlah urine dari skala 1 meningkat, menjadi skala 5 membaik',
                                'Tekanan arteri rata-rata dari skala 1 meningkat, menjadi skala 5 membaik',
                                'Kadar urine nitrogen darah dari skala 1 meningkat menjadi skala 5 membaik',
                                'Kadar kreatinin plasma dari skala 1 meningkat menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.02068',
                            'nama'     => 'Pencegahan Syok',
                            'definisi' => 'Mengidentifikasi dan menuruskan risiko terjadinya ketidakmampuan tubuh menyediakan oksigen dan nutrien untuk mencukupi kebutuhan jaringan',
                            'tindakan' => [
                                'observasi' => [
                                    'Monitor status kardiopulmonal (frekuensi dan kekuatan nadi, frekuensi nafas, TD, MAP)',
                                    'Monitor status oksigenasi (oksimetri nadi, AGD)',
                                    'Monitor status cairan (masukan dan haluaran, turgor kulit, CRT)',
                                    'Monitor tingkat kesadaran dan respon pupil',
                                    'Periksa riwayat alergi',
                                ],
                                'terapeutik' => [
                                    'Memberikan oksigen untuk mempertahankan saturasi oksigen >94%',
                                    'Mempersiapkan intubasi dan ventilasi mekanis, jika perlu',
                                    'Memasang jalur iv jika perlu',
                                    'Memasang kateter urine untuk menilai produksi urine, jika perlu',
                                    'Melakukan skin test untuk mencegah reaksi alergi',
                                ],
                                'edukasi' => [
                                    'Menjelaskan penyebab/faktor risiko syok',
                                    'Menjelaskan tanda dan gejala awal syok',
                                    'Anjurkan untuk melapor jika menemukan/merasakan tanda dan gejala awal syok',
                                    'Anjurkan untuk memperbanyak asupan cairan oral',
                                    'Anjurkan untuk menghindari alergen',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasikan pemberian IV, jika perlu',
                                    'Kolaborasikan pemberian transfusi darah, jika perlu',
                                    'Kolaborasikan pemberian antiinflamasi, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0017 — Risiko Perfusi Serebral Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0017',
                'diagkep_desc' => 'Risiko Perfusi Serebral Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Sirkulasi',
                        'definisi'    => 'Beresiko mengalami penurunan sirkulasi darah ke otak',
                        'penyebab'    => [],
                        'faktor_risiko' => [
                            'Keabnormalan masa protrombin dan/atau masa tromboplastin parsial',
                            'Penurunan kinerja ventrikel kiri',
                            'Aterosklerosis aorta',
                            'Diseksi arteri',
                            'Fibrilasi atrium',
                            'Tumor otak',
                            'Stenosis karotis',
                            'Miksoma atrium',
                            'Aneurisma serebri',
                            'Koagulopati (mis. anemia sel sabit)',
                            'Dilatasi kardiomiopati',
                            'Koagulasi intravaskuler diseminata',
                            'Embolisme',
                        ],
                        'kondisi_klinis_terkait' => [
                            'Stroke',
                            'Cedera kepala',
                            'Aterosklerotik aortik',
                            'Infark miokard akut',
                            'Diseksi arteri',
                            'Embolisme',
                            'Endokarditis infektif',
                            'Fibrilasi atrium',
                            'Hiperkolesterolemia',
                            'Hipertensi',
                            'Dilatasi kardiomiopati',
                            'Koagulasi intravaskular diseminata',
                            'Miksoma atrium',
                            'Neoplasma otak',
                            'Segmen ventrikel kiri akinetik',
                            'Sindrom sick sinus',
                            'Stenosis karotid',
                            'Stenosis mitral',
                            'Hidrosefalus',
                            'Infeksi otak (mis. meningtis, ensefalitis, abses serebri)',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.02014',
                            'nama' => 'Perfusi Serebral',
                            'kriteria_hasil' => [
                                'Tingkat kesadaran dari skala 1 menurun menjadi skala 5 meningkat',
                                'Sakit kepala dari skala 1 meningkat menjadi skala 5 menurun',
                                'Gelisah dari skala 1 meningkat menjadi skala 5 menurun',
                                'Tekanan intrakranial dari skala 1 memburuk menjadi skala 5 membaik',
                                'Tekanan arteri rata-rata dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.06194',
                            'nama'     => 'Manajemen Peningkatan Tekanan Intrakranial',
                            'definisi' => 'Mengidentifikasi dan mengelola peningkatan tekanan dalam rongga kranial',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi penyebab peningkatan TIK (mis. lesi, gangguan metabolisme, edema serebral)',
                                    'Monitor tanda/gejala peningkatan TIK (mis. tekanan darah meningkat, tekanan nadi melebar, bradikardia, pola napas iregular, kesadaran menurun)',
                                    'Monitor MAP (Mean Arterial Pressure)',
                                    'Monitor CVP (Central Venous Pressure), jika perlu',
                                    'Monitor PAWP, jika perlu',
                                    'Monitor PAP, jika perlu',
                                    'Monitor ICP (Intra Cranial Pressure), jika tersedia',
                                    'Monitor CCP (Cerebral Perfusion Pressure)',
                                    'Monitor gelombang ICP',
                                    'Monitor status pernapasan',
                                    'Monitor intake dan output cairan',
                                    'Monitor cairan serebro-spinalis (mis. warna, konsistensi)',
                                ],
                                'terapeutik' => [
                                    'Minimalkan stimulus dengan menyediakan lingkungan yang tenang',
                                    'Berikan posisi semi Fowler',
                                    'Hindari manuver Valsava',
                                    'Cegah terjadinya kejang',
                                    'Hindari penggunaan PEEP',
                                    'Hindari penggunaan cairan IV hipotonik',
                                    'Atur ventilator agar PaCO2 optimal',
                                    'Pertahankan suhu tubuh normal',
                                ],
                                'edukasi' => [],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian sedasi dan anti konvulsan, jika perlu',
                                    'Kolaborasi pemberian diuretik osmosis, jika perlu',
                                    'Kolaborasi pemberian pelunak tinja, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0018 — Berat Badan Lebih
            // ================================================================
            [
                'diagkep_id'   => 'D.0018',
                'diagkep_desc' => 'Berat Badan Lebih',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Akumulasi lemak berlebih atau abnormal yang tidak sesuai dengan usia dan jenis kelamin',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Kurang aktivitas fisik harian',
                                'Kelebihan konsumsi gula',
                                'Gangguan kebiasaan makan',
                                'Gangguan persepsi makan',
                                'Kelebihan konsumsi alcohol',
                                'Penggunaan energi kurang dari asipan',
                                'Sering mengemil',
                                'Sering memakan makanan berminyak/berlemak',
                                'Factor keturunan',
                                'Penggunaan makanan formula atau makanan campuran pada bayi',
                                'Asupan kalsium rendah',
                                'Berat badan bertambah cepat',
                                'Makanan padat sebagai sumber makanan utama pada usia <5 bulan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'IMT >25 kg/m2 (pada dewasa) atau berat dan Panjang badan lebih dari presentil 95 (pada anak >2tahun) atau IMT pada presentil ke 85-95 (pada anak 2-18 tahun)',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Tebal lipatan kulit trisep >25mm',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Gangguan Genetik',
                            'Faktor keturunan',
                            'Hipotiroid',
                            'Diabetes melitus maternal',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03018',
                            'nama' => 'Berat Badan',
                            'kriteria_hasil' => [
                                'Indeks massa tubuh membaik dari skala 1 (memburuk) menjadi skala 5 (membaik)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03095',
                            'nama'     => 'Manajemen Berat Badan',
                            'definisi' => 'Mengidentifikasi dan mengelola berat badan',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi kondisi kesehatan pasien yang dapat mempengaruhi berat badan',
                                    'Monitor intake dan output cairan, nilai hemoglobin, tekanan darah, kenaikan berat badan, dan kebiasaan membeli makanan',
                                ],
                                'terapeutik' => [
                                    'Hitung berat badan ideal pasien',
                                    'Hitung presentase lemak dan otot pasien',
                                    'Fasilitasi menentukan target berat badan yang realistis',
                                ],
                                'edukasi' => [
                                    'Jelaskan hubungan antara asupan makanan, aktivitas fisik, penambahan berat badan dan penurunan berat badan',
                                    'Jelaskan factor risiko berat badan lebih dan berat badan kurang',
                                    'Anjurkan mencatat berat badan setiap minggu, jika perlu',
                                    'Anjurkan melakukan pencatatan asupan makan, aktivitas fisik dan perubahan berat badan',
                                ],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0019 — Defisit Nutrisi
            // ================================================================
            [
                'diagkep_id'   => 'D.0019',
                'diagkep_desc' => 'Defisit Nutrisi',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Asupan nutrisi tidak cukup untuk memenuhi kebutuhan metabolisme',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Kurangnya asupan makanan',
                                'Ketidakmampuan menelan makanan',
                                'Ketidakmampuan mencerna makanan',
                                'Ketidakmampuan mengabsorbsi nutrient',
                                'Peningkatan kebutuhan metabolisme',
                                'Faktor ekonomi (mis, financial tidak mencukupi)',
                                'Faktor psikologis (mis. Stress, keengganan untuk makan)',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Berat badan menurun minimal 10% di bawah rentang ideal',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => [
                                'Cepat kenyang setelah makan',
                                'Kram/nyeri abdomen',
                                'Nafsu makan menurun',
                            ],
                            'objektif' => [
                                'Bising usus hiperaktif',
                                'Otot pengunyah lemah',
                                'Otot menelan lemah',
                                'Membrane mukosa pucat',
                                'Sariawan',
                                'Serum albumin turun',
                                'Rambut rontok berlebihan',
                                'Diare',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Stroke',
                            'Parkinsom',
                            'Mobius syndrome',
                            'Cerebral palsy',
                            'Cleft lip',
                            'Cleft palate',
                            'Amyotropic',
                            'Infeksi',
                            'AIDS',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03030',
                            'nama' => 'Status Nutrisi',
                            'kriteria_hasil' => [
                                'Porsi makanan yang dihabiskan meningkat',
                                'Kekuatan otot pengunyah meningkat',
                                'Verbalisasi keinginan untuk meningkatkan nutrisi meningkat',
                                'Pengetahuan tentang pilihan makana yang sehat meningkat',
                                'Pengetahuan tentang pilihan minuman yang sehat meningkat',
                                'Pengetahuan tentang standar asupan nutrisi yang tepat meningkat',
                                'Sikap terhadap makanan/minumam sesuai dengan tujuan kesehatan meningkat',
                                'Sariawan menurun',
                                'Berat badan membaik',
                                'Indeks massa tubuh membaik',
                                'Frekuensi makanan membaik',
                                'Nafsu makan membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03119',
                            'nama'     => 'Manajemen Nutrisi',
                            'definisi' => 'Mengidentifikasi dan mengelola asupan nutrisi yang seimbang',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi status nutrisi',
                                    'Identifikasi alergi dan intoleransi makanan',
                                    'Identifikasi makanan yang disukai',
                                    'Identifikasi kebutuhan kalori dan jenis nutrient',
                                    'Identifikasi perlunya penggunaan selang nasogastric',
                                    'Monitor asupan makanan',
                                    'Monitor berat badan',
                                    'Monitor hasil pemeriksaan laboratorium',
                                ],
                                'terapeutik' => [
                                    'Lakukan oral hygiene sebelum makan, jika perlu',
                                    'Fasilitasi menentukan pedoman diet (mis, piramida makanan)',
                                    'Sajikan makanan secara menarik dan suhu yang sesuai',
                                    'Berikan makanan tinggi serat untuk mencegah konstipasi',
                                    'Berikan makanan tinggi kalori dan tinggi protein',
                                    'Berikan suplemen makanan, jika perlu',
                                    'Hentikan pemberian makan melalui selang nasogatrik, jika asupan oral dapat di toleransi',
                                ],
                                'edukasi' => [
                                    'Anjurkan posisi duduk, jika mampu',
                                    'Ajarkan diet yang diprogramkan',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian medikasi sebelum makan (mis, pereda nyeri, antiemetic), jika perlu',
                                    'Kolaborasi dengan ahli gizi untuk menentukan jumlah kalori dan jenis nutrient yang di butuhkan, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0020 — Diare
            // ================================================================
            [
                'diagkep_id'   => 'D.0020',
                'diagkep_desc' => 'Diare',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Pengeluaran feses yang sering, lunak dan tidak berbentuk',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Inflamasi gastrointestinal',
                                'Iritasi gastrointestinal',
                                'Proses infeksi',
                                'Malabsorpsi',
                                'Kecemasan',
                                'Tingkat stress tinggi',
                                'Terpapar kontaminan',
                                'Terpapar toksin',
                                'Penyalahgunaan laksatif',
                                'Penyalahgunaan zat',
                                'Program pengobatan (mis. Agen tiroid, analgesic, pelunak feses, frosulfat, antasida, cimetidine dan antibiotik)',
                                'Perubahan air dan makanan',
                                'Bakteri pada air makanan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Defekasi lebih dari tiga kali dalam 24 jam',
                                'Feses lembek atau cair',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Urgency', 'Nyeri/Kram abdomen'],
                            'objektif'  => [
                                'Frekuensi peristaltik meningkat',
                                'Bising usus hiperaktif',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Kanker kolon',
                            'Diverticulitis',
                            'Iritasi usus',
                            'Crohn\'s disease',
                            'Ulkus peptikum',
                            'Gastritis',
                            'Spasme kolon',
                            'Kolitis ulseratif',
                            'Hipertiroidisme',
                            'Demam typoid',
                            'Malaria',
                            'Sigelosis',
                            'Kolera',
                            'Disentri',
                            'Hepatitis',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.04033',
                            'nama' => 'Eliminasi Fekal',
                            'kriteria_hasil' => [
                                'Kontrol pengeluaran feses dari skala 1 menurun menjadi skala 5 meningkat',
                                'Keluhan defekasi lama dan sulit dari skala 1 meningkat menjadi skala 5 menurun',
                                'Mengejan saat defekasi dari skala 1 meningkat menjadi skala 5 menurun',
                                'Konsistensi feses dari skala 1 memburuk menjadi skala 5 membaik',
                                'Frekuensi BAB dari skala 1 memburuk menjadi skala 5 membaik',
                                'Peristaltik usus dari skala 1 memburuk menjadi skala 5 membaik',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03101',
                            'nama'     => 'Manajemen Diare',
                            'definisi' => 'Mengidentifikasi dan mengelola diare dan dampaknya',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi penyebab diare (mis. Inflamasi gastrointestinal, iritasi gastrointestinal, proses infeksi, malabsorpsi, ansietas, stress, efek obat-obatan, pemberian botol susu)',
                                    'Identifikasi riwayat pemberian makanan',
                                    'Identifikasi gejala invaginasi (mis. tangisan keras, kepucatan pada bayi)',
                                    'Monitor warna, volume, frekuensi, dan konsistensi tinja',
                                    'Monitor tanda dan gejala hypovolemia (mis. takikardia, nadi teraba lemah, tekanan darah turun, turgor kulit turun, mukosa mulut kering, CRT melambat, BB menurun)',
                                    'Monitor iritasi dan ulserasi kulit di daerah perianal',
                                    'Monitor jumlah pengeluaran diare',
                                    'Monitor keamanan dan penyiapan makanan',
                                ],
                                'terapeutik' => [
                                    'Berikan asupan cairan oral (mis. larutan garam gula, oralit, Pedialyte, renalyte)',
                                    'Pasang jalur intravena',
                                    'Berikan cairan intravena (mis. ringer asetat, ringer laktat), jika perlu',
                                    'Ambil sampel darah untuk pemeriksaan darah lengkap dan elektrolit',
                                    'Ambil sampel feses untuk kultur, jika perlu',
                                ],
                                'edukasi' => [
                                    'Anjurkan makanan porsi kecil dan sering secara bertahap',
                                    'Anjurkan menghindari makanan pembentuk gas, pedas dan mengandung laktosa',
                                    'Anjurkan melanjutkan pemberian asi',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian obat antimotilitas (mis. loperamide, difenoksilat)',
                                    'Kolaborasi pemberian obat anti spasmodic / spasmolitis (mis papaverine ekstak belladonna mebeverine)',
                                    'Kolaborasi pemberian obat pengeras feses (mis. atapulgit, smektif, kaolin-pektin)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0021 — Disfungsi Motilitas Gastrointestinal
            // ================================================================
            [
                'diagkep_id'   => 'D.0021',
                'diagkep_desc' => 'Disfungsi Motilitas Gastrointestinal',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Peningkatan, penurunan, tidak efektif atau krangnya aktivitas peristaltic gastrointestinal',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Asupan enteral',
                                'Intoleransi makanan',
                                'Imobilisasi',
                                'Makanan kontaminan',
                                'Malnutrisi',
                                'Pembedahan',
                                'Efek agen farmakologis (mis. Narkotik/opiate, antibiotic, laksatif, anastesia)',
                                'Proses penuaan',
                                'Kecemasan',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => [
                                'Mengungkapkan flatus tidak ada',
                                'Nyeri/kram abdomen',
                            ],
                            'objektif' => [
                                'Suara peristaltic berubah (tidak ad, hipoaktif, atau hiperaktif)',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Merasa mual'],
                            'objektif'  => [
                                'Residu lambung meningkat/menurun',
                                'Muntah',
                                'Regurgitasi',
                                'Pengosongan lambung cepat',
                                'Distensi abdomen',
                                'Diare',
                                'Feses kering dan sulit keluar',
                                'Feses keras',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Pembedahan abdomen atau usus',
                            'Malnutrisi',
                            'Kecemasan',
                            'Kanker empedu',
                            'Kolesistektomi',
                            'Infeksi pencernaan',
                            'Gastroesophageal reflux disease (GERD)',
                            'Dialisis Paritoneal',
                            'Terapi Radiasi',
                            'Multiple organ disfunction syndrome',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03033',
                            'nama' => 'Tingkat Nyeri',
                            'kriteria_hasil' => [
                                'Nyeri menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Kram abdomen menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Mual menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Muntah menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Regurgitasi menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Distensi abdomen menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Diare menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Suara peristaltic menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'Pengosongan lambung menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'Flatus dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03119',
                            'nama'     => 'Manajemen Nutrisi',
                            'definisi' => 'Mengidentifikasi dan mengelola asupan nutrisi yang seimbang',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi status nutrisi',
                                    'Identifikasi alergi dan intoleransi makanan',
                                    'Identifikasi makanan yang disukai',
                                    'Identifikasi kebutuhan kalori dan jenis nutrient',
                                    'Identifikasi perlunya penggunaan selang nasogastric',
                                    'Monitor asupan makanan',
                                    'Monitor berat badan',
                                    'Monitor hasil pemeriksaan laboratorium',
                                ],
                                'terapeutik' => [
                                    'Lakukan oral hygiene sebelum makan, jika perlu',
                                    'Fasilitasi menentukan pedoman diet (mis. piramida makanan)',
                                    'Sajikan makanan secara menarik dan suhu yang sesuai',
                                    'Berikan makanan tinggi serat untuk mencegah konstipasi',
                                    'Berikan makanan tinggi kalori dan tinggi protein',
                                    'Berikan suplemen makana jika perlu',
                                    'Hentikan pemberian makan melalui selang nasogatrik jika asupan oral dapat di toleransi',
                                ],
                                'edukasi' => [
                                    'Anjurkan posisi duduk, jika mampu',
                                    'Ajarkan diet yang diprogramkan',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian medikasi sebelum makan mis. Pereda nyeri, antiemetic jika perlu',
                                    'Kolaborasi dengan ahli gizi untuk menentukan jumlah kalori dan jenis nutrient yang di butuhkan jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0022 — Hipervolemia
            // ================================================================
            [
                'diagkep_id'   => 'D.0022',
                'diagkep_desc' => 'Hipervolemia',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Peningkatan volume cairan intravaskuler, interstisiel, dan atau intraseluler',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Gangguan mekanisme regulasi',
                                'Kelebihan asupan cairan',
                                'Kelebihan asupan natrium',
                                'Gangguan aliran balik vena',
                                'Efek agen farmakologis (mis. kortikosteroid, chlorpropamide, tolbutamide, vincristine, tryptilines carbamazepine)',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Ortopnea', 'Dispnea', 'Paroxysmal nocturnal dyspnea (PND)'],
                            'objektif'  => [
                                'Edema anasarca dan/atau edema perifer',
                                'Berat badan meningkat dalam waktu singkat',
                                'Jugular venous pressure (JVP) dan/atau central venous pressure (CPV) meningkat',
                                'Refleks hepatojugular positif',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Distensi vena jugularis',
                                'Terdengar suara napas tembahan',
                                'Hepatomegaly',
                                'Kadar Hp/Ht turun',
                                'Oliguria',
                                'Intake lebih banyak dari output (balans cairan positif)',
                                'Kongesti paru',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Penyakit ginjal: gagal ginjak akut/kronis, sindrom nefrotik',
                            'Hipoalbuminemia',
                            'Gagal jantung kongestive',
                            'Kelainan hormone',
                            'Penyakit hati (mis serosis, asites, kanker hati)',
                            'Penyakit vena perifer (varises vena, thrombus vena, flebitis)',
                            'Imobilitas',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03020',
                            'nama' => 'Keseimbangan Cairan',
                            'kriteria_hasil' => [
                                'Asupan cairan menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Output urine menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Membran mukosa lembab menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Asupan makanan menurun dari skala 5 (meningkat) menjadi skala 1 (menurun)',
                                'Edema menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'Dehidrasi menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'Asites menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'Konfusi menurun dari skala 2 (cukup meningkat) menjadi skala 5 (menurun)',
                                'TTV membaik dari skala 2 (cukup memburuk) menjadi skala 5 (membaik)',
                                'Mata cekung membaik dari skala 2 (cukup memburuk) menjadi skala 5 (membaik)',
                                'Turgor kulit membaik dari skala 2 (cukup memburuk) menjadi skala 5 (membaik)',
                                'Berat badan membaik dari skala 2 (cukup memburuk) menjadi skala 5 (membaik)',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03114',
                            'nama'     => 'Manajemen Hipervolemia',
                            'definisi' => 'Mengidentifikasidan mengelola kelebihan volume cairan intravaskuler dan ekstraseluler serta mencegah terjadinya komplikasi',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa tanda dan gejala hypervolemia mis. ortopnea, dispnea, edema, JVP/CVP meningkat, reflex hepatojugular positif, suara nafas tambahan',
                                    'Identifikasi penyebab hipervolemia',
                                    'Monitor status hemodinamik mialnya frekuensi jantung, tekanan darah, MAP, CVP, PAP, PCWP, CO, Jika tersedia',
                                    'Monitor intake dan ouput cairan',
                                    'Monitor tanda hemo konsentrasi misalnya kadar natrium, BUN, Hematokrit, berat jenis urine',
                                    'Monitor tanda peningkatan tekanan onkotik plasma misalnya kadar protein dan albumin meningkat',
                                    'Monitor kecepatan infus secara ketat',
                                    'Monitor efek samping diuretic misalnya hipotensi ortortostatik, hypovolemia, hipokalemia, hyponatremia',
                                ],
                                'terapeutik' => [
                                    'Timbang berat badan setiap hari pada waktu yang sama',
                                    'Batasi asupan cairan dan garam',
                                    'Tinggikan kepala tempatbtidur 30-40 derajat',
                                ],
                                'edukasi' => [
                                    'Anjurkan melapor jika haluaran urine <0,5 ml/kg/jam dalam 6 jam',
                                    'Anjurkan melapor BB bertambah >1 kg dalam sehari',
                                    'Ajarkan cara mengukur dan mencatat asupan dan haluaran cairan',
                                    'Ajarkan cara mengatasi cairan',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian diuretic',
                                    'Kolaborasi penggantian kehilangan kalium akibat diuretic',
                                    'Kolaborasi pemberian continuous renal replacement therapy jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0023 — Hipovolemia
            // ================================================================
            [
                'diagkep_id'   => 'D.0023',
                'diagkep_desc' => 'Hipovolemia',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Penurunan volume cairan intravascular, interstisial, dan atau intraseluler',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Kehilangan cairan aktif',
                                'Kegagalan mekanisme regulasi',
                                'Peningkatan permeabilitas kapiler',
                                'Kekurangan intake cairan',
                                'Evaporasi',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => [
                                'Frekuensi nadi meningkat',
                                'Nadi teraba lemah',
                                'Tekanan darah menurun',
                                'Tekanan nadi menyempit',
                                'Turgor kulit menurun',
                                'Membrane mukosa kering',
                                'Volume urin menurun',
                                'Hematokrit meningkat',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Merasa lemah', 'Mengeluh haus'],
                            'objektif'  => [
                                'Pengisian vena menurun',
                                'Status mental berubah',
                                'Suhu tubuh meningkat',
                                'Konsentrasi urin meningkat',
                                'Berat badan turun tiba-tiba',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Penyakit Addison',
                            'Trauma/pendarahan',
                            'Luka bakar',
                            'AIDS',
                            'Penyakit Crohn',
                            'Muntah',
                            'Diare',
                            'Kolitis ulseratif',
                            'Hipoalbuminemia',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03028',
                            'nama' => 'Status Cairan',
                            'kriteria_hasil' => [
                                'Kekuatan nadi meningkat',
                                'Berat badan cukup meningkat',
                                'Perasaan lemah menurun',
                                'Frekuensi nadi normal',
                                'Tekanan darah normal',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03116',
                            'nama'     => 'Manajemen Hipovolemia',
                            'definisi' => 'Mengidentifikasi dan mengelola penurunan volume cairan intravaskuler',
                            'tindakan' => [
                                'observasi' => [
                                    'Periksa tanda dan gejala hipovolemia (mis. Frekuensi nadi meningkat, nadi teraba lemah, tekanan darah menurun, tekanan nadi menyempit, turgor kulit menurun, membran mukosa kering, volume urin menurun, hematokrit meningkat, haus, lemah)',
                                    'Monitor intake dan ouput cairan',
                                ],
                                'terapeutik' => [
                                    'Hitung kebutuhan cairan',
                                    'Berikan posisi modified trendelenbung',
                                    'Berikan asupan cairan oral',
                                ],
                                'edukasi' => [
                                    'Anjurkan memperbanyak asupan cairan oral',
                                    'Anjurkan menghindari perubahan posisi mendadak',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian cairan IV isotonis (mis. NaCl, RL)',
                                    'Kolaborasi pemberian produk darah',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0027 — Ketidakstabilan Kadar Glukosa Darah
            // ================================================================
            [
                'diagkep_id'   => 'D.0027',
                'diagkep_desc' => 'Ketidakstabilan Kadar Glukosa Darah',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Variasi kadar glukosa naik/turun dari rentang normal',
                        'penyebab'    => [
                            'hiperglikemia' => [
                                'Disfungsi pangkreas',
                                'Resistensi insulin',
                                'Gangguan toleransi glukosa darah',
                                'Gangguan glukosa darah puasa',
                                'Disfungsi hati',
                                'Disfungsi ginjal kronis',
                                'Efek agen farmakologis',
                                'Tindakan pembedahan neoplasma',
                                'Gangguan metabolik bawaan (mis. Gangguan penyimpanan lisosomal, galaktosemia, gangguan penyimpanan glikogen)',
                            ],
                            'hipoglikemia' => [
                                'Penggunaan insulin atau obat glikemik oral',
                                'Hiperinsulinemia (mis. Insulinoma)',
                                'Endokrinopati (mis. Kerusakan adrenal atau pituitari)',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => [
                                'Hipoglikemia: Mengantuk, Pusing',
                                'Hiperglikemia: Lelah atau lesu',
                            ],
                            'objektif' => [
                                'Hipoglikemia: Gangguan koordinasi, Kadar glukosa dalam darah atau urine rendah',
                                'Hiperglikemia: Kadar glukosa dalam darah atau urin tinggi',
                            ],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => [
                                'Hipoglikemia: Palpitasi, Mengeluh lapar',
                                'Hiperglikemia: Mulut kering, Haus meningkat',
                            ],
                            'objektif' => [
                                'Hipoglikemia: Gemetar, Kesadaran menurun, Perilaku aneh, Sulit bicara, Berkeringat',
                                'Hiperglikemia: Jumlah urine meningkat',
                            ],
                        ],
                        'kondisi_klinis_terkait' => [
                            'Diabetes melitus',
                            'Ketoasidosis diabetik',
                            'Hipoglikemia',
                            'Hiperglikemia',
                            'Diabetes gestasional',
                            'Penggunaan kortikostiroid',
                            'Nutrisi parenteral total (TPN)',
                        ],
                    ],
                    'slki' => [
                        [
                            'kode' => 'L.03022',
                            'nama' => 'Ketidakstabilan Kadar Glukosa Darah',
                            'kriteria_hasil' => [
                                'Mengantuk cukup menurun',
                                'Pusing menurun',
                                'Lelah/lesu cukup menurun',
                                'Rasa lapar sedang',
                                'Gemetar cukup menurun',
                                'Berkeringat cukup menurun',
                                'Rasa haus menurun',
                                'Perilaku aneh menurun',
                                'Kesulitan bicara menurun',
                                'Kadar glukosa dalam darah sedang',
                                'Kadar glukosa dalam urine sedang',
                                'Palpitasi sedang',
                            ],
                        ],
                    ],
                    'siki' => [
                        [
                            'kode'     => 'I.03115',
                            'nama'     => 'Manajemen Hiperglikemia',
                            'definisi' => 'Mengidentifikasi dan mengelola kadar glukosa darah diatas normal',
                            'tindakan' => [
                                'observasi' => [
                                    'Identifikasi kemungkinan penyebab hiperglikemia',
                                    'Monitor tanda dan gejala hiperglikemia',
                                ],
                                'terapeutik' => [
                                    'Berikan asupan cairan oral',
                                ],
                                'edukasi' => [
                                    'Anjurkan menghindari olahraga saat kadar glukosa darah lebih dari 250 mg/dL',
                                    'Anjurkan monitor kadar glukosa darah secara mandiri',
                                    'Anjurkan kepatuhan terhadap diet dan olahraga',
                                ],
                                'kolaborasi' => [
                                    'Kolaborasi pemberian insulin, jika perlu',
                                    'Kolaborasi pemberian kalium, jika perlu',
                                ],
                            ],
                        ],
                    ],
                ],
            ],


            // ================================================================
            // D.0024 — Ikterik Neonatus
            // ================================================================
            [
                'diagkep_id'   => 'D.0024',
                'diagkep_desc' => 'Ikterik Neonatus',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Kulit dan membrane mukosa neonatus menguning setelah 24 jam kelahiran akibat bilirubin tak terkonjugasi masuk kedalam sirkulasi',
                        'penyebab'    => [
                            'fisiologis' => [
                                'Penurunan berat badan abnormal (>7-8% pada bayi baru lahir yang menyusu ASI, >15% pada bayi cukup bulan)',
                                'Pola makan tidak ditetapkan dengan baik',
                                'Kesulitan transisi ke kehidupan ekstra uterin',
                                'Usia kurang dari 7 hari',
                                'Keterlambatan pengeluaran feses (mekonium)',
                                'Prematuritas (<37 minggu)',
                            ],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Profil darah abnormal (hemolisis, bilirubin serum total pada rentang risiko tinggi menurut usia)'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Tidak tersedia'],
                        ],
                        'kondisi_klinis_terkait' => ['Neonatus', 'Bayi prematur'],
                    ],
                    'slki' => [
                        ['kode' => 'L.14125', 'nama' => 'Integritas Kulit dan Jaringan', 'kriteria_hasil' => ['Kerusakan jaringan dari skala 1 meningkat menjadi skala 5 menurun', 'Kerusakan kulit dari skala 1 meningkat menjadi skala 5 menurun']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03091', 'nama' => 'Fototerapi Neonatus', 'definisi' => 'Memberikan terapi sinar fluorescent yang ditujukan kepada kulit neonates untuk menurunkan kadar bilirubin',
                            'tindakan' => [
                                'observasi' => ['Monitor ikteri pada sklera dan kulit bayi', 'Identifikasi kebutuhan cairan sesuai dengan usia gestasi dan berat badan', 'Monitor suhu dan tanda vital setiap 4 jam sekali', 'Monitor efek samping fototerapi mis. hipertermi, diare, rush pada kulit, penurunan BB 8-10%'],
                                'terapeutik' => ['Siapkan lampu fototerapi dan incubator atau kotak bayi', 'Lepaskan pakaian bayi kecuali popok', 'Berikan penutup mata pada bayi', 'Ukur jarak antara lampu dan permukaan kulit bayi 30 cm atau tergantung spesifikais lampu fototerapi', 'Berikan tubuh bayi terpapar sinar fototrapi secara berkelanjutan', 'Ganti segra alas dan popok bayi setelah BAK/BAB', 'Gunakan linen berwarna putih agar memantulkan cahaya sebanyak mungkin'],
                                'edukasi' => ['Ajarkan ibu menyusui sekitar 20-30 menit', 'Ajarkan ibu menyusui seseiring mungkin'],
                                'kolaborasi' => ['Kolaborasi pemeriksaan darah vena bilirubin direk dan indirek'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0025 — Kesiapan Peningkatan Keseimbangan Cairan
            // ================================================================
            [
                'diagkep_id'   => 'D.0025',
                'diagkep_desc' => 'Kesiapan Peningkatan Keseimbangan Cairan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Pola ekuilibrium antara volume cairan dan komposisi kimia cairan tubuh yang cukup untuk memenuhi kebutuhan fisik dan dapat ditingkatkan',
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Mengekspresiakan keinginan untuk meningkatkan keseimbangan cairan'],
                            'objektif'  => ['Membran mukosa lembab', 'Asupan makanan dan cairan adekuat untuk kebutuhan harian', 'Turgor jaringan baik', 'Tidak ada tanda edema atau dehidrasi'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Urin berwarna kuning bening dengan berat jenis dalam rentang normal', 'Haluaran urin sesuai dengan asupan', 'Berat badan stabil'],
                        ],
                        'kondisi_klinis_terkait' => ['Gagal jantung', 'Sindrom iritasi usus', 'Penyakit addison', 'Makanan enternal atau parenteral'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03020', 'nama' => 'Keseimbangan Cairan', 'kriteria_hasil' => ['Membran mukosa lembab dari skala 1 menurun menjadi skala 5 meningkat', 'Intake cairan dari skala 1 memburuk menjadi skala 5 membaik', 'Tekanan darah dari skala 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03098', 'nama' => 'Manajemen Cairan', 'definisi' => 'Mengidentifikasi dan mengelola keseimbangan cairan dan mencegah komplikasi akibat ketidakseimbangan cairan',
                            'tindakan' => [
                                'observasi' => ['Monitor status hidrasi (mis. Frekuensi nadi, kekuatan nadi, akral, pengisian kapiler, kelembapan mukosa, turgor kulit, tekanan darah)', 'Monitor berat badan harian', 'Monitor berat badan sebelum dan sesudah dialisis', 'Monitor hasil pemeriksaan laboratorium (mis. hematokrit, Na, K, Cl, berat jenis urine, BUN)', 'Monitor status hemodinamik (mis. MAP, CVP, PAP, PCWP jika tersedia)'],
                                'terapeutik' => ['Catat intake-output dan hitung balans cairan 24 jam', 'Berikan asupan cairan, sesuai kebutuhan', 'Berikan cairan intravena, jika perlu'],
                                'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan', 'Informasikan hasil pemantauan, jika perlu'],
                                'kolaborasi' => ['Kolaborasi pemberian diuretik, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0026 — Kesiapan Peningkatan Nutrisi
            // ================================================================
            [
                'diagkep_id'   => 'D.0026',
                'diagkep_desc' => 'Kesiapan Peningkatan Nutrisi',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Pola asupan nutrisi yang cukup untuk memenuhi kebutuhan metabolisme dan dapat ditingkatkan',
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Mengekspresikan keinginan untuk meningkatkan nutrisi'],
                            'objektif'  => ['Makan teratur dan adekuat'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Mengekspresikan pengetahuan tentang pilihan makanan dan cairan yang sehat', 'Mengikuti standar asupan nutrisi yang tepat (mis. Piramida makanan, pedoman American Diabetic Association atau pedoman lainnya)'],
                            'objektif'  => ['Penyiapan dan penyimpanan makanan dan minuman yang aman', 'Sikap terhadap makanan dan minuman sesuai dengan tujuan kesehatan'],
                        ],
                        'kondisi_klinis_terkait' => ['Perilaku upaya peningkatan kesehatan'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03030', 'nama' => 'Status Nutrisi', 'kriteria_hasil' => ['Porsi makan yang dihabiskan dari skala 1 menurun menjadi skala 4 cukup meningkat', 'Sikap terhadap makanan/minuman sesuai dengan tujuan kesehatan dari skala 1 menurun menjadi skala 5 meningkat', 'Perasaan cepat kenyang menurun dari skala 1 meningkat menjadi skala 3 sedang', 'Berat badan dari skala 1 memburuk menjadi skala 4 cukup membaik', 'Indeks Massa Tubuh (IMT) dari skala 1 memburuk menjadi skala 4 cukup membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03094', 'nama' => 'Konseling Nutrisi', 'definisi' => 'Memberikan bimbingan dalam melakukan modifikasi asupan nutrisi',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kebiasaan makan dan perilaku makan yang akan diubah', 'Identifikasi kemajuan modifikasi diet secara reguler', 'Monitor intake dan output cairan, nilai hemoglobin, tekanan darah, kenaikan berat badan, dan kebiasaan membeli makanan'],
                                'terapeutik' => ['Bina hubungan terapeutik', 'Sepakati lama waktu pemberian konseling', 'Tetapkan tujuan jangka pendek dan jangka panjang yang realistis', 'Gunakan standar nutrisi sesuai program diet dalam mengevaluasi kecukupan asupan makanan', 'Pertimbangkan faktor-faktor yang mempengaruhi pemenuhan kebutuhan gizi (mis. usia, tahap pertumbuhan dan perkembangan, penyakit)'],
                                'edukasi' => ['Informasikan perlunya modifikasi diet (mis. penurunan atau penambahan berat badan, pembatasan natrium atau cairan, pengurangan kolesterol)', 'Jelaskan program gizi dan persepsi pasien terhadap diet yang diprogramkan'],
                                'kolaborasi' => ['Rujuk pada ahli gizi, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0028 — Menyusui Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0028',
                'diagkep_desc' => 'Menyusui Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Pemberian ASI secara langsung dari payudaya kepada bayi dan anak yang dapat memenuhi kebutuhan nutrisi',
                        'penyebab'    => [
                            'fisiologis' => ['Hormon oksitosin dan prolaktin adekuat', 'Payudara membesar, alveoli mulai terisi ASI', 'Tidak ada kelainan pada struktur payudara', 'Puting menonjol', 'Bayi Aterm'],
                            'situasional' => ['Rawat gabung', 'Dukungan keluarga dan tenaga kesehatan adekuat', 'Faktor budaya'],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Ibu merasa percaya diri selama proses menyusui'],
                            'objektif'  => ['Bayi melekat pada payudara ibu dengan benar', 'Ibu mampu memposisikan bayi dengan benar', 'Miksi bayi lebih dari 8 kali dalam 24 jam', 'Berat badan bayi meningkat', 'ASI menetes/menancar', 'Suplai ASI adekuat', 'Puting tidak lecet setelah minggu kedua'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Bayi tidur setelah menyusui', 'Payudaya ibu kosong setelah menyusui', 'Bayi tidak rewel dan menangis setelah menyusui'],
                        ],
                        'kondisi_klinis_terkait' => ['Status kesehatan ibu baik', 'Status kesehatan bayi baik'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03029', 'nama' => 'Status Menyusui', 'kriteria_hasil' => ['Perlekatan bayi pada payudara ibu meningkat', 'Kemampuan ibu memposisikan bayi dengan benar meningkat', 'Tetesan/pancaran ASI meningkat', 'Suplai ASI adekuat meningkat', 'Kepercayaan diri ibu meningkat', 'Intake bayi meningkat', 'Hisapan bayi meningkat', 'Frekuensi miksi bayi membaik', 'Bayi rewel menurun', 'Lecet pada puting menurun']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03093', 'nama' => 'Konseling Laktasi', 'definisi' => 'Memberikan bimbingan teknik menyusui yang tepat dalam pemberian makanan bayi',
                            'tindakan' => [
                                'observasi' => ['Identifikasi keadaan emosional ibu saat akan dilakukan konseling menyusui', 'Identifikasi keinginan dan tujuan menyusui', 'Identifikasi permasalahan yang ibu alami selama proses menyusui'],
                                'terapeutik' => ['Gunakan teknik mendengar aktif (mis. duduk sama tinggi, dengarkan permasalahan ibu)', 'Berikan pujian terhadap perilaku ibu yang benar'],
                                'edukasi' => ['Ajarkan teknik menyusui yang tepat dan sesuai kebutuhan ibu'],
                                'kolaborasi' => [],
                            ],
                        ],
                        [
                            'kode' => 'I.03135', 'nama' => 'Promosi ASI Eksklusif', 'definisi' => 'Meningkatkan kemampuan ibu dalam memberikan ASI secara eksklusif (0-6 bulan)',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kebutuhan laktasi bagi ibu pada antenatal, intranatal, dan postnatal'],
                                'terapeutik' => ['Fasilitasi ibu melakukan IMD (inisiasi menyusu dini)', 'Fasilitasi ibu untuk rawat gabung atau rooming in', 'Gunakan sendok dan cangkir jika bayi belum bisa menyusu', 'Dukung ibu menyusui dengan mendampingi ibu selama kegiatan menyusui berlangsung', 'Diskusikan dengan keluarga tentang ASI eksklusif', 'Siapkan kelas menyusui pada masa prenatal minimal 2 kali dan periode pascapartum minimal 4 kali'],
                                'edukasi' => ['Jelaskan manfaat menyusui bagi ibu dan bayi', 'Jelaskan pentingnya menyusui di malam hari untuk mempertahankan dan meningkatkan produksi ASI', 'Jelaskan tanda-tanda bayi cukup ASI (Mis. berat badan meningkat, BAK lebih dari 10 kali/hari, warna urine tidak pekat)', 'Jelaskan manfaat rawat gabung (rooming in)', 'Anjurkan ibu menyusui sesegera mungkin setelah melahirkan', 'Anjurkan ibu memberikan nutrisi kepada bayi hanya dengan ASI', 'Anjurkan ibu menyusui sesering mungkin segera setelah lahir sesuai kebutuhan bayi', 'Anjurkan ibu menjaga produksi ASI dengan memerah ASI'],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0029 — Menyusui Tidak Efektif
            // ================================================================
            [
                'diagkep_id'   => 'D.0029',
                'diagkep_desc' => 'Menyusui Tidak Efektif',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Kondisi dimana ibu dan bayi mengalami ketidakpuasan atau kesukaran pada proses menyusui',
                        'penyebab'    => [
                            'fisiologis' => ['Ketidakadekuatan suplai ASI', 'Hambatan pada neonates (mis. Prematuris, sumbing)', 'Anomaly payudara ibu (mis. Putting yang masuk ke dalam)', 'Ketidakadekuatan refleks oksitosin', 'Ketidakadekuatan refleks menghisap bayi', 'Payudara bengkak', 'Riwayat operasi payudara', 'Kelahiran kembar'],
                            'situasional' => ['Tidak rawat gabung', 'Kurang terpapar informasi tentang pentingnya menyusui dan/atau metode menyusui', 'Kurangnya dukungan keluarga', 'Faktor budaya'],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Kelemahan maternal', 'Kecemasan maternal'],
                            'objektif'  => ['Bayi tidak mampu melekat pada payudara ibu', 'ASI tidak menetas/memancar', 'BAK bayi kurang dari 8 kali dalam 24 jam', 'Nyeri dan/atau lecet terus menerus sehingga minggu kedua'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Intake bayi tidak adekuat', 'Bayi menghisap tidak terus menerus', 'Bayi menangis saat disusui', 'Bayi rewel dan menangis terus dalam jam-jam pertama setelah menyusui', 'Menolak untuk mengisap'],
                        ],
                        'kondisi_klinis_terkait' => ['Abses payudara', 'Mastitis', 'Carpal tunnel syndrom'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03029', 'nama' => 'Status Menyusui', 'kriteria_hasil' => ['Perlekatan bayi pada payudara ibu meningkat', 'Kemampuan ibu memposisikan bayi dengan benar meningkat', 'Tetesan/pancaran ASI meningkat', 'Suplai ASI adekuat meningkat', 'Kepercayaan diri ibu meningkat', 'Intake bayi meningkat', 'Hisapan bayi meningkat', 'Frekuensi miksi bayi membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.12393', 'nama' => 'Edukasi Menyusui', 'definisi' => 'Memberikan informasi dan dukungan terhadap proses pemberian ASI',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi tujuan atau keinginan menyusui'],
                                'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya', 'Dukung ibu meningkatkan kepercayaan diri dalam menyusui', 'Libatkan sistem pendukung: suami, keluarga, tenaga kesehatan dan masyarakat'],
                                'edukasi' => ['Berikan konseling menyusui', 'Jelaskan manfaat menyusui bagi ibu dan bayi', 'Ajarkan 4 posisi menyusui dan perlekatan dengan benar', 'Ajarkan perawatan payudara postpartum (mis. memerah ASI, pijat payudara, pijat oksitosin)'],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0030 — Obesitas
            // ================================================================
            [
                'diagkep_id'   => 'D.0030',
                'diagkep_desc' => 'Obesitas',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Akumulasi lemak berlebih atau apnormal yang tidak sesuai dengan usia dan jenis kelamin, serta melampaui kondisi berat badan lebih (overweight)',
                        'penyebab'    => [
                            'fisiologis' => ['Kurang aktivitas fisik harian', 'Kelebihan konsumsi gula', 'Gangguan kebiasaan makan', 'Gangguan persepsi makan', 'Kelebihan konsumsi alkohol', 'Penggunaan energi kurang dari asupan', 'Sering mengemil', 'Sering memakan makanan berminyak/berlemak', 'Faktor keturunan (mis. distribusi jaringan adiposa, pengeluaran energi, aktivitas lipase lipoprotein, sintesis lipid, lipolisis)', 'Penggunaan makanan formula atau makanan campuran pada bayi', 'Asupan kalsium rendah pada anak-anak', 'Berat badan bertambah cepat', 'Makanan padat sebagai sumber makanan utama pada usia <5 bulan', 'Penggunaan otot bantu pernapasan', 'Fase ekspirasi memanjang'],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['IMT >27 kg/m2 (pada dewasa) atau lebih dari presentil ke 95 untuk usia dan jenis kelamin (pada anak)'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Tebal lipatan kulit triset > 25 mm'],
                        ],
                        'kondisi_klinis_terkait' => ['Gangguan genetik', 'Faktor keturunan', 'Hipotiroid', 'Diabetes melitus gestasional', 'Pola hidup kurang aktivitas'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03018', 'nama' => 'Berat Badan', 'kriteria_hasil' => ['Berat badan dari skala 1 memburuk menjadi skala 5 membaik', 'Tebal lipatan kulit dari skala 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03097', 'nama' => 'Manajemen Berat Badan', 'definisi' => 'Mengidentifikasi dan mengelola berat badan agar dalam rentang optimal',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kondisi kesehatan pasien yang dapat mempengaruhi berat badan'],
                                'terapeutik' => ['Hitung berat badan ideal pasien', 'Hitung presentase lemak dan otot pasien', 'Fasilitasi menentukan target berat badan yang realistis'],
                                'edukasi' => ['Jelaskan hubungan antara asupan makanan, aktivitas fisik, penambahan berat badan dan penurunan berat badan', 'Jelaskan faktor risiko berat badan lebih dan berat badan kurang', 'Anjurkan mencatat berat badan setiap minggu, jika perlu', 'Anjurkan untuk melakukan pencatatan asupan makan, aktivitas fisik dan perubahan berat badan'],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0031 — Risiko Berat Badan Lebih
            // ================================================================
            [
                'diagkep_id'   => 'D.0031',
                'diagkep_desc' => 'Risiko Berat Badan Lebih',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Berisiko mengalami akumulasi lemak berlebih atau abnormal yang tidak sesuai dengan usia dan jenis kelamin',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Kurang aktivitas fisikharian', 'Kelebihan konsumsi gula', 'Gangguan kebiasaan makan', 'Gangguan persepsi makan', 'Kelebihan konsumsi alkohol', 'Penggunaan energi kurang dari asupan', 'Sering mengemil', 'Sering memakan makanan berminyak/berlemak', 'Faktor keturunan', 'Penggunaan makanan formula atau makanan campuran pada bayi', 'Asupan kalsium rendah pada anak-anak', 'Berat badan bertambah cepat', 'Makanan padat sebagai sumber makanan utama pada usia <5 bulan', 'Penggunaan otot bantu pernapasan', 'Fase ekspirasi memanjang'],
                        'kondisi_klinis_terkait' => ['Gangguan genetik', 'Hipotiroid', 'Diabetes melitus gestasional', 'Pola hidup kurang aktivitas'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03018', 'nama' => 'Berat Badan', 'kriteria_hasil' => ['Berat badan dari skala 1 memburuk menjadi skala 5 membaik', 'Tebal lipatan kulit dari skala 1 memburuk menjadi skala 5 membaik', 'Indeks massa tubuh dari skala 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.12369', 'nama' => 'Edukasi Diet', 'definisi' => 'Mengajarkan jumlah, jenis dan jadwal asupan makanan yang diprogramkan',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kemampuan pasien dan keluarga menerima informasi', 'Identifikasi tingkat pengetahuan saat ini', 'Identifikasi kebiasaan pola makan saat ini dan masa lalu', 'Identifikasi persepsi pasien dan keluarga tentang diet yang diprogramkan', 'Identifikasi keterbatasan finansial untuk menyediakan makanan'],
                                'terapeutik' => ['Persiapkan materi, media dan alat peraga', 'Jadwalkan waktu yang tepat untuk memberikan pendidikan kesehatan', 'Berikan kesempatan pasien dan keluarga bertanya', 'Sediakan rencana makan tertulis, jika perlu'],
                                'edukasi' => ['Jelaskan tujuan kepatuhan diet terhadap kesehatan', 'Informasikan makanan yang diperbolehkan dan dilarang', 'Informasikan kemungkinan interaksi obat dan makanan, jika perlu', 'Anjurkan mempertahankan posisi semi fowler (30-45 derajat) 20-30 menit setelah makan', 'Anjurkan mengganti bahan makanan sesuai dengan diet yang diprogramkan', 'Anjurkan melakukan olahraga sesuai toleransi', 'Ajarkan cara membaca label dan memilih makanan yang sesuai', 'Rekomendasikan resep makanan yang sesuai dengan diet, jika perlu'],
                                'kolaborasi' => ['Rujuk ke ahli gizi dan sertakan keluarga, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0032 — Risiko Defisit Nutrisi
            // ================================================================
            [
                'diagkep_id'   => 'D.0032',
                'diagkep_desc' => 'Risiko Defisit Nutrisi',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Beresiko mengalami asupan nutrisi tidak cukup untuk memenuhi kebutuhan metabolisme',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Ketidakmampuan menelan makanan', 'Ketidakmampuan mencerna makanan', 'Ketidakmampuan mengabsorbsi nutrien', 'Peningkatan kebutuhan metabolisme', 'Faktor ekonomi (mis. finansial tidak mencukupi)', 'Faktor psikologis (mis. stress, keengganan untuk makan)'],
                        'kondisi_klinis_terkait' => ['Stroke', 'Parkinson', 'Mobius syndrome', 'Cerebral palsy', 'Cleft lip', 'Cleft palate', 'Amyotropic lateral sclerosis', 'Kerusakan neuromuskular', 'Luka bakar', 'Kanker', 'Infeksi', 'AIDS', 'Penyakit Crohn\'s', 'Enterokolitis', 'Fibrosis kistik'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03030', 'nama' => 'Status Nutrisi', 'kriteria_hasil' => ['Porsi makan yang dihabiskan dari skala 1 menurun menjadi skala 3 sedang', 'Berat badan dari skala 2 cukup memburuk menjadi skala 4 cukup membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03119', 'nama' => 'Manajemen Nutrisi', 'definisi' => 'Mengidentifikasi dan mengelola asupan nutrisi',
                            'tindakan' => [
                                'observasi' => ['Identifikasi status nutrisi', 'Identifikasi alergi dan intoleransi makanan', 'Identifikasi kebutuhan kalori dan nutrien', 'Monitor asupan makanan', 'Monitor berat badan'],
                                'terapeutik' => ['Lakukan oral hygiene sebelum makan, jika perlu', 'Berikan makanan tinggi serat untuk mencegah konstipasi', 'Berikan makanan tinggi kalori dan tinggi protein'],
                                'edukasi' => ['Anjurkan posisi duduk, jika mampu', 'Ajarkan diet yang diprogram'],
                                'kolaborasi' => ['Kolaborasi dengan ahli gizi untuk menentukan jumlah kalori dan jeni nutrien yang dibutuhkan, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0033 — Risiko Disfungsi Motilitas Gastrointestinal
            // ================================================================
            [
                'diagkep_id'   => 'D.0033',
                'diagkep_desc' => 'Risiko Disfungsi Motilitas Gastrointestinal',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi/Cairan',
                        'definisi'    => 'Risiko peningkatan, penurunan atau tidak efektifnya aktivitas peristaltik pada sistem gastrointestinal',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Pembedahan abdomen', 'Penurunan sirkulasi gastrointestinal', 'Intoleransi makanan', 'Refluks gastrointestinal', 'Hiperglikemia', 'Imobilitas', 'Proses penuaan', 'Infeksi gastrointestinal', 'Efek agen farmokologis (mis, antibiotik, laksatif, narkotika/opiat)', 'Prematuritas', 'Kecemasan', 'Stres', 'Kurangnya sanitasi pada persiapan makanan'],
                        'kondisi_klinis_terkait' => ['Pembedahan abdomen atau usus', 'Malnutrisi', 'Anemia', 'Kecemasan', 'Kanker empedu', 'Kolesistektomi', 'Infeksi pencernaan', 'Gastroesophageal Reflux Disease (GERD)', 'Dialisis peritoneal', 'Terapi radiasi', 'Multiple organ dysfunction syndrome'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03023', 'nama' => 'Motilitas Gastrointestinal', 'kriteria_hasil' => ['Nyeri dari skala 1 meningkat menjadi skala 5 menurun', 'Penggunaan otot bantu napas dari skala 1 meningkat menjadi skala 5 menurun', 'Suara Peristaltik dari 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.12369', 'nama' => 'Edukasi Diet', 'definisi' => 'Mengajarkan jumlah, jenis dan jadwal asupan makanan yang diprogramkan',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kemampuan pasien dan keluarga menerima informasi', 'Identifikasi tingkat pengetahuan saat ini', 'Identifikasi kebiasaan pola makan saat ini dan masa lalu', 'Identifikasi persepsi pasien dan keluarga tentang diet yang diprogramkan', 'Identifikasi keterbatasan finansial untuk menyediakan makanan'],
                                'terapeutik' => ['Persiapkan materi, media dan alat peraga', 'Jadwalkan waktu yang tepat untuk memberikan pendidikan kesehatan', 'Berikan kesempatan pasien dan keluarga bertanya', 'Sediakan rencana makan tertulis, jika perlu'],
                                'edukasi' => ['Jelaskan tujuan kepatuhan diet terhadap kesehatan', 'Informasikan makanan yang diperbolehkan dan dilarang', 'Informasikan kemungkinan interaksi obat dan makanan, jika perlu', 'Anjurkan mempertahankan posisi semi Fowler (30-45 derajat) 20-30 menit setelah makan', 'Anjurkan mengganti bahan makanan sesuai dengan diet yang diprogramkan', 'Anjurkan melakukan olahraga sesuai toleransi', 'Ajarkan cara membaca label dan memilih makanan yang sesuai'],
                                'kolaborasi' => ['Rujuk ke ahli gizi dan sertakan keluarga, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0034 — Risiko Hipovolemia
            // ================================================================
            [
                'diagkep_id'   => 'D.0034',
                'diagkep_desc' => 'Risiko Hipovolemia',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Berisiko mengalami penurunan volume cairan intravaskuler, interstisiel, dan/atau intraseluler',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Kehilangan cairan secara aktif', 'Gangguan absorbsi cairan', 'Usia lanjut', 'Kelebihan berat badan', 'Status hipermetabolik', 'Kegagalan mekanisme regulasi', 'Evaporasi', 'Kekurangan intake cairan', 'Efek agen farmakologis'],
                        'kondisi_klinis_terkait' => ['Penyakit Addison', 'Trauma/pendarahan', 'Luka bakar', 'AIDS', 'Penyakit Crohn', 'Muntah', 'Diare', 'Kolitis ulseratif'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03028', 'nama' => 'Status Cairan Membaik', 'kriteria_hasil' => ['Kekuatan nadi meningkat', 'Output urin meningkat', 'Membran mukosa lembab meningkat', 'Ortopnea menurun', 'Dispnea menurun', 'Paroxysmal nocturnal dyspnea (PND) menurun', 'Edema anasarka menurun', 'Edema perifer menurun', 'Frekuensi nadi membaik', 'Tekanan darah membaik', 'Turgor kulit membaik', 'Jugular venous pressure (JVP) membaik', 'Hemoglobin membaik', 'Hematokrit membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03116', 'nama' => 'Manajemen Hipovolemia', 'definisi' => 'Mengidentifikasi dan mengelola penurunan volume cairan intravaskuler',
                            'tindakan' => [
                                'observasi' => ['Periksa tanda dan gejala hipovolemia (mis. frekuensi nadi meningkat, nadi teraba lemah, tekanan darah menurun, tekanan nadi menyempit, turgor kulit menurun, membran mukosa kering, volume urin menurun, hematokrit meningkat, haus, lemah)', 'Monitor intake dan output cairan'],
                                'terapeutik' => ['Hitung kebutuhan cairan', 'Berikan posisi modified Trendelenburg', 'Berikan asupan cairan oral'],
                                'edukasi' => ['Anjurkan memperbanyak asupan cairan oral', 'Anjurkan menghindari perubahan posisi mendadak'],
                                'kolaborasi' => ['Kolaborasi pemberian cairan IV isotonis (mis. NaCl, RL)', 'Kolaborasi pemberian cairan IV hipotonis (mis. Glukosa 2,5%, NaCl 0,4%)', 'Kolaborasi pemberian cairan koloid (albumin, plasmanate)', 'Kolaborasi pemberian produk darah'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0035 — Risiko Ikterik Neonatus
            // ================================================================
            [
                'diagkep_id'   => 'D.0035',
                'diagkep_desc' => 'Risiko Ikterik Neonatus',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Beresiko mengalami kulit dan membran mukosa neonatus menguning setelah 24 jam kelahiran akibat bilirubin tak terkonjugasi masuk ke dalam sirkulasi',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Penurunan berat badan abnormal (>7-8% pada bayi baru lahir yang menyusu ASI, >15% pada bayi cukup bulan)', 'Pola makan tidak ditetapkan dengan baik', 'Kesulitan transisi ke kehidupan ekstra uterin', 'Usia kurang dari 7 hari', 'Keterlambatan pengeluaran feses (mekonium)', 'Prematuritas (<37 minggu)'],
                        'kondisi_klinis_terkait' => ['Neonatus', 'Bayi prematur'],
                    ],
                    'slki' => [
                        ['kode' => 'L.14125', 'nama' => 'Integritas Kulit dan Jaringan', 'kriteria_hasil' => ['Kerusakan jaringan dari skala 1 meningkat menjadi skala 5 menurun', 'Kerusakan kulit dari skala 1 meningkat menjadi skala 5 menurun']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03132', 'nama' => 'Perawatan Neonatus', 'definisi' => 'Mengidentifikasi dan merawat bayi setelah lahir sampai usia 28 hari',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kondisi awal bayi setelah lahir (mis. Kecukupan bulan, air ketuban jernih atau bercampur mekonium, menangis spontan, tonus otot)', 'Monitor tanda vital bayi (terutama suhu)'],
                                'terapeutik' => ['Lakukan inisiasi menyusui dini (IMD) segera setelah bayi lahir', 'Berikan vitamin K 1 mg intramuskuler untuk mencegah pendarahan', 'Mandikan selama 5-10 menit, minimal sehari sekali', 'Mandikan dengan air hangat (36-37°c)', 'Gunakan sabun yang mengandung provitamin B5', 'Oleskan beby oil untuk mempertahankan kelembaban kulit', 'Rawat tali pusat secara terbuka (tidak dibungkus)', 'Bersihkan pangkal tali pusat lidi kapas yang telah di beri air matang', 'Kenakan popok bayi di bawah umbilicus jika tali pusat belum terlepas', 'Lakukan pemijatan bayi', 'Ganti popok bayi jika basah', 'Kenakan pakaian bayi dari bahan katun'],
                                'edukasi' => ['Anjurkan ibu menyusui sesuai kebutuhan bayi', 'Ajarkan ibu cara merawat bayi di rumah', 'Ajarkan cara pemberian makanan pendamping ASI pada bayi >6 bulan'],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0036 — Risiko Ketidakseimbangan Cairan
            // ================================================================
            [
                'diagkep_id'   => 'D.0036',
                'diagkep_desc' => 'Risiko Ketidakseimbangan Cairan',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi/Cairan',
                        'definisi'    => 'Berisiko mengalami penurunan, peningkatan atau percepatan perpindahan cairan dari intravaskuler, interstisial atau intraseluler',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Prosedur pembedahan mayor', 'Trauma/perdarahan', 'Luka bakar', 'Aferesis', 'Asites', 'Obstruksi intestinal', 'Peradangan pankreas', 'Penyakit ginjal dan kelenjar', 'Disfungsi intestinal'],
                        'kondisi_klinis_terkait' => ['Prosedur pembedahan mayor', 'Penyakit ginjal dan kelenjar', 'Perdarahan', 'Luka bakar'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03020', 'nama' => 'Keseimbangan Cairan', 'kriteria_hasil' => ['Membran mukosa lembab dari skala 1 meningkat menjadi skala 5', 'Asupan makanan skala 1 meningkat menjadi skala 5', 'Tekanan darah dari skala 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03098', 'nama' => 'Manajemen Cairan', 'definisi' => 'Mengidentifikasi dan mengelola keseimbangan cairan dan mencegah komplikasi akibat ketidakseimbangan cairan',
                            'tindakan' => [
                                'observasi' => ['Monitor status hidrasi (mis. Frekuensi nadi, kekuatan nadi, akral, pengisian kapiler, kelembapan mukosa, turgor kulit, tekanan darah)', 'Monitor berat badan harian', 'Monitor berat badan sebelum dan sesudah dialisis', 'Monitor hasil pemeriksaan laboratorium (mis. hematokrit, Na, K, Cl, berat jenis urine, BUN)', 'Monitor status hemodinamik (mis. MAP, CVP, PAP, PCWP jika tersedia)'],
                                'terapeutik' => ['Catat intake-output dan hitung balans cairan 24 jam', 'Berikan asupan cairan, sesuai kebutuhan', 'Berikan cairan intravena, jika perlu'],
                                'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan', 'Informasikan hasil pemantauan, jika perlu'],
                                'kolaborasi' => ['Kolaborasi pemberian diuretic, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0037 — Risiko Ketidakseimbangan Elektrolit
            // ================================================================
            [
                'diagkep_id'   => 'D.0037',
                'diagkep_desc' => 'Risiko Ketidakseimbangan Elektrolit',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Beresiko mengalami perubahan kadar serum elektrolit',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Ketidakseimbangan cairan (mis. dehidrasi dan intoksikasi air)', 'Kelebihan volume cairan', 'Gangguan mekanisme regulasi (mis. diabetes)', 'Efek samping prosedur (mis. pembedahan)', 'Diare', 'Muntah', 'Disfungsi ginjal', 'Disfungsi regulasi endokrin'],
                        'kondisi_klinis_terkait' => ['Gagal ginjal', 'Anoreksia nervosa', 'Diabetes melitus', 'Penyakit Crohn', 'Gastrointeritis', 'Pankreatiti', 'Cedera kepala', 'Kanker', 'Trauma multipel', 'Luka bakar', 'Anemia sel sabit'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03021', 'nama' => 'Keseimbangan Elektrolit', 'kriteria_hasil' => ['Serum natrium dari skala 1 memburuk menjadi skala 5 membaik', 'Serum kalium dari skala 1 memburuk menjadi skala 5 membaik', 'Serum klorida dari skala 1 memburuk menjadi skala 5 membaik']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'L.03122', 'nama' => 'Pemantauan Elektrolit', 'definisi' => 'Mengumpulkan dan menganalisis data terkait regulasi keseimbangan elektrolit',
                            'tindakan' => [
                                'observasi' => ['Identifikasi kemungkinan penyebab ketidakseimbangan elektrolit', 'Monitor kadar elektrolit serum', 'Monitor mual, muntah dan diare', 'Monitor kehilangan cairan, jika perlu'],
                                'terapeutik' => ['Atur interval waktu pemantauan sesuai dengan kondisi pasien', 'Dokumentasi hasil pemantauan'],
                                'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan', 'Informasikan hasil pemantauan, jika perlu'],
                                'kolaborasi' => [],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0038 — Risiko Ketidakstabilan Glukosa Darah
            // ================================================================
            [
                'diagkep_id'   => 'D.0038',
                'diagkep_desc' => 'Risiko Ketidakstabilan Glukosa Darah',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi dan Cairan',
                        'definisi'    => 'Risiko terhadap variasi kadar glukosa darah dari rentang normal',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Kurang terpapar informasi', 'Ketidaktepatan pemantauan glukosa darah', 'Kurang patuh pada rencana manajemen diabetes', 'Manajemen mediakasi tidak terkontrol', 'Kehamilan', 'Periode pertumbuhan cepat', 'Stress berlebihan', 'Penambahan berat badan', 'Kurang dapat menerima diagnosis'],
                        'kondisi_klinis_terkait' => ['Diabetes', 'Ketoasidosis diabetic', 'Hipoglikemia', 'Diabetes gestasional', 'Penggunaan kortikosteroid', 'Nutrisi parenteral total (TPN)'],
                    ],
                    'slki' => [
                        ['kode' => 'L.03022', 'nama' => 'Kestabilan Glukosa Darah', 'kriteria_hasil' => ['Kadar glukosa dalam darah dari skala 1 (memburuk) menjadi skala 5 (membaik)']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.03115', 'nama' => 'Manajemen Hiperglikemia', 'definisi' => 'Mengidentifikasi dan mengelola kadar glukosa darah diatas normal',
                            'tindakan' => [
                                'observasi' => ['Identifikasi penyebab hiperglikemia', 'Identifikasi situasi yang menyebabkan kebutuhan insulin meningkat (mis. penyakit kambuhan)', 'Monitor kadar glukosa darah, bila perlu', 'Monitor gejala dan tanda hiperglikemia (mis. poliuria, polidipsia, polifagia, kelemahan, melaise, pandangan kabur, sakit kepala)', 'Monitor intake dan output cairan', 'Monitor keton urin, kadar analisa gas darah elektrolit, tekanan darah orstotatik dan frekuensi nadi'],
                                'terapeutik' => ['Berikan asupan cairan oral', 'Konsultasi dengan medis jika tanda gejala hiperglikemia tetap ada dan memburuk', 'Fasilitasi ambulasi jika ada hipotensi ortostatik'],
                                'edukasi' => ['Anjurkan menghindari olahraga saat kadar gula darah lebih dari 250 mg/dl', 'Anjurkan monitor kadar gula darah secara mandiri', 'Anjurkan kepatuhan terhadap diet dan olahraga', 'Ajarkan indikasi dan pentingnya pengujian keton urine, jika perlu', 'Ajarkan pengelolaan diabetes (mis. penggunaan insulin, obat oral, monitor asupan cairan, pengganti karbohidrat, dan bantuan profesional kesehatan)'],
                                'kolaborasi' => ['Kolaborasi pemberian insulin, jika perlu', 'Kolaborasi pemberian IV, jika perlu', 'Kolaborasi pemberian Kalium, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0039 — Risiko Syok
            // ================================================================
            [
                'diagkep_id'   => 'D.0039',
                'diagkep_desc' => 'Risiko Syok',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Nutrisi/Cairan',
                        'definisi'    => 'Berisiko mengalami ketidakcukupan aliran darah ke jaringan tubuh, yang dapat mengakibatkan disfungsi seluler yang mengancam jiwa',
                        'penyebab'    => [],
                        'faktor_risiko' => ['Hipoksemia', 'Hipoksia', 'Hipotensi', 'Kekurangan volume cairan', 'Sepsis', 'Sindrom Respons inflamasi sistemik (Systemic inflamatory response syndrome [SIRS])'],
                        'kondisi_klinis_terkait' => ['Perdarahan', 'Trauma Multipel', 'Pneumothoraks', 'Infark miokard', 'Kardiomiopati', 'Cedera Medula spinalis', 'Anafilaksis', 'Sepsis', 'Koagulasi intravaskuler diseminata', 'Sindrom Respons inflamasi sistemik (Systemic inflamatory response syndrome [SIRS])'],
                    ],
                    'slki' => [
                        ['kode' => 'L.02039', 'nama' => 'Tingkat Syok', 'kriteria_hasil' => ['Kekuatan nadi menurun dari skala 4 (meningkat) menjadi skala 2 (cukup menurun)', 'Tingkat kesadaran Meningkat yang awalnya skala 1 (menurun) menjadi skala 3 (sedang)', 'Saturasi oksigen Meningkat dengan skala awal 1 (menurun) menjadi skala 4 (cukup meningkat)', 'Akral dingin menurun dari skala 1 (meningkat) menjadi skala 4 (cukup menurun)', 'Pucat menurun dari skala awal 1 (meningkat) menjadi skala 4 (cukup menurun)']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.02068', 'nama' => 'Pencegahan Syok', 'definisi' => 'Mengidentifikasi dan menurunkan risiko terjadinya ketidakmampuan tubuh menyediakan oksigen dan nutrien untuk mencukupi kebutuhan jaringan',
                            'tindakan' => [
                                'observasi' => ['Monitor status kardiopulmonal (Frekuensi dan kekuatan nadi, frekuensi napas, TD, MAP)', 'Monitor status oksigenasi (Oksimetri nadi, AGD)', 'Monitor status Cairan (Masukan dan haluaran, turgor kulit, CRT)', 'Monitor tingkat kesadaran dan respon pupil', 'Periksa riwayat alergi'],
                                'terapeutik' => ['Berikan oksigen untuk mempertahankan saturasi oksigen >94%', 'Lakukan Skin Test untuk mencegah reaksi alergi'],
                                'edukasi' => ['Jelaskan penyebab atau faktor risiko syok', 'Jelaskan tanda dan gejala awal syok', 'Anjurkan melapor jika menemukan/merasakan tanda dan gejala awal syok', 'Anjurkan memperbanyak asupan cairan oral', 'Anjurkan menghindari alergen'],
                                'kolaborasi' => ['Kolaborasi pemberian IV, jika perlu', 'Kolaborasi pemberian transfusi darah, jika perlu', 'Kolaborasi pemberian antiinflamasi, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],

            // ================================================================
            // D.0040 — Gangguan Eliminasi Urin
            // ================================================================
            [
                'diagkep_id'   => 'D.0040',
                'diagkep_desc' => 'Gangguan Eliminasi Urin',
                'diagkep_json' => [
                    'sdki' => [
                        'kategori'    => 'Fisiologis',
                        'subkategori' => 'Eliminasi',
                        'definisi'    => 'Disfungsi eliminasi urin',
                        'penyebab'    => [
                            'fisiologis' => ['Penurunan kapasitas kandung kemih', 'Iritasi kandung kemih', 'Penurunan kemampuan menyadari tanda-tanda gangguan kandung kemih', 'Efek tindakan medis dan diagnostic (mis. Operasi ginjal, operasi saluran kemih, anestesi, dan obat-obatan)', 'Kelemahan otot pelvis', 'Ketidakmampuan mengakses toilet (mis. Imobilisasi)', 'Hambatan lingkungan', 'Ketidakmampuan mengkomunikasikan kebutuhan eliminasi', 'Outlet kandung kemih tidak lengkap (mis. Anomaly saluran kemih kongenital)', 'Imaturitas (pada anak usia <3 tahun)'],
                        ],
                        'gejala_tanda_mayor' => [
                            'subjektif' => ['Desakan berkemih (Urgensi)', 'Urin menetes (dribbling)', 'Sering buang air kecil', 'Nokturia', 'Mengompol', 'Enuresis'],
                            'objektif'  => ['Distensi kandung kemih', 'Berkemih tidak tuntas (hesitancy)', 'Volume residu urin meningkat'],
                        ],
                        'gejala_tanda_minor' => [
                            'subjektif' => ['Tidak tersedia'],
                            'objektif'  => ['Tidak tersedia'],
                        ],
                        'kondisi_klinis_terkait' => ['Infeksi ginjal dan saluran kemih', 'Hiperglikemi', 'Trauma', 'Kanker', 'Cedera/tumor/infeksi medulla spinalis', 'Neuropati diabetikum', 'Neuropati alkoholik', 'Stroke', 'Parkinson', 'Skleloris multipel'],
                    ],
                    'slki' => [
                        ['kode' => 'L.04034', 'nama' => 'Eliminasi Urine', 'kriteria_hasil' => ['Desakan berkemih (3)', 'Urin menetes dribbling (4)', 'Nokturia (3)', 'Mengompol (4)', 'Enuresis (3)']],
                    ],
                    'siki' => [
                        [
                            'kode' => 'I.04152', 'nama' => 'Manajemen Eliminasi Urine', 'definisi' => 'Mengidentifikasi dan mengelola gangguan pola eliminasi urine',
                            'tindakan' => [
                                'observasi' => ['Identifikasi tanda gejala retensi atau inkontinensia urine', 'Identifikasi faktor yang menyebabkan retensi atau Inkontinensia urine', 'Monitor eliminasi Urine'],
                                'terapeutik' => ['Catat waktu-waktu dan haluaran berkemih', 'Batasi asupan cairan, jika perlu', 'Ambil sampel urine tengah (mid stream) atau kultur'],
                                'edukasi' => ['Ajarkan tanda dan gejal infeksi saluran kemih', 'Ajarkan mengukur asupan cairan dan haluaran urine', 'Ajarkan mengambil spesimen urine mid stream', 'Ajarkan mengenali tanda berkemih dan waktu yang tepat untuk berkemih', 'Ajarkan terapi modalitas penguatan otot-otot panggul/berkemihan', 'Anjurkan minum yang cukup, jika tidak ada kontraindikasi', 'Anjurkan mengurangi minum menjelang tidur'],
                                'kolaborasi' => ['Pemberian obat supositoria uretra, jika perlu'],
                            ],
                        ],
                    ],
                ],
            ],


            // D.0041 — Inkontinensia Fekal (updated from PDF p.221-222)
            ['diagkep_id' => 'D.0041', 'diagkep_desc' => 'Inkontinensia Fekal', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Perubahan kebiasaan buang air besar dari pola normal yang ditandai dengan pengeluaran feses secara involunter (tidak disadari)', 'penyebab' => ['fisiologis' => ['Kerusakan susunan saraf motorik bawah', 'Penurunan tonus otot', 'Gangguan kognitif', 'Penyalahgunaan laksatif', 'Kehilangan fungsi pengendalian sfingter rectum', 'Pascaoperasi pullthrough dan penutupan kolosomi', 'Ketidakmampuan mencapai kamar kecil', 'Diare kronis', 'Stress berlebihan']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak mampu mengontrol pengeluaran feses', 'Tidak mampu menunda defekasi'], 'objektif' => ['Feses keluar sedikit-sedikit dan sering']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Bau feses', 'Kulit perianal kemerahan']], 'kondisi_klinis_terkait' => ['Spina bifida', 'Atresia ani', 'Penyakit Hirschsprung']], 'slki' => [['kode' => 'L.04033', 'nama' => 'Kontinensia Fekal', 'kriteria_hasil' => ['Pengontrolan pengeluaran feses membaik dari skala 3 (sedang) menjadi skala 5 (meningkat)', 'Defekasi membaik dari skala 1 (memburuk) menjadi skala 4 (cukup membaik)', 'Frekuensi buang air besar membaik dari skala 1 (memburuk) menjadi skala 5 (membaik)', 'Kondisi kulit perianal membaik dari skala 1 (memburuk) menjadi skala 4 (cukup membaik)']]], 'siki' => [['kode' => 'I.04151', 'nama' => 'Latihan Eliminasi Fekal', 'definisi' => 'Mengajarkan suatu kemampuan melatih usus untuk di evakuasi pada interval tertentu', 'tindakan' => ['observasi' => ['Monitor peristaltik usus secara teratur'], 'terapeutik' => ['Anjurkan waktu yang konsisten untuk buang air besar', 'Anjurkan mengkonsumsi makanan tertentu, sesuai program atau hasil konsultasi', 'Anjurkan asupan cairan yang adekuat sesuai kebutuhan', 'Anjurkan olahraga sesuai toleransi'], 'edukasi' => ['Ajarkan program latihan eliminasi fekal', 'Anjurkan asupan cairan yang adekuat', 'Anjurkan aktivitas fisik sesuai toleransi'], 'kolaborasi' => ['Kolaborasi penggunaan supositoria, jika perlu']]]]]],

            // D.0042 — Inkontinensia Urin Berlanjut (updated from PDF p.222-227)
            ['diagkep_id' => 'D.0042', 'diagkep_desc' => 'Inkontinensia Urin Berlanjut', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Pengeluaran urin tidak terkendali dan terus menerus tanpa distensi atau perasaan penuh pada kandung kemih', 'penyebab' => ['fisiologis' => ['Neuropati arkus refleks', 'Disfungsi neurologis', 'Kerusakan refleks kontraksi detrusor', 'Trauma', 'Kerusakan medula spinalis', 'Kelainan anatomis (mis. fistula)']], 'gejala_tanda_mayor' => ['subjektif' => ['Keluar urin konstan tanpa distensi'], 'objektif' => ['Nokturia lebih dari 2 kali sepanjang tidur']], 'gejala_tanda_minor' => ['subjektif' => ['Berkemih tanpa sadar', 'Tidak sadar inkontinensia urin'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Trauma', 'Tumor', 'Infeksi medula spinalis', 'Fistula saluran kemih']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Kemampuan mengontrol pengeluaran urine dari skala 1 menurun menjadi skala 5 meningkat', 'Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Residu volume urine setelah berkemih dari skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 5 menurun', 'Dribbling dari skala 1 meningkat menjadi skala 5 menurun', 'Hesitancy dari skala 1 meningkat menjadi skala 5 menurun', 'Enuresis dari skala 1 meningkat menjadi skala 5 menurun', 'Kemampuan menunda pengeluaran urine dari skala 1 memburuk menjadi skala 5 membaik', 'Frekuensi berkemih dari skala 1 memburuk menjadi skala 5 membaik', 'Sensasi berkemih dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.04148', 'nama' => 'Kateterisasi Urine', 'definisi' => 'Masukan selang kateter urine ke dalam kandung kemih', 'tindakan' => ['observasi' => ['Periksa kondisi pasien (mis. kesadaran, tanda-tanda vital, daerah perineal, distensi kandung kemih, inkontinensia urine, refleks berkemih)'], 'terapeutik' => ['Siapkan peralatan, bahan-bahan dan ruangan tindakan', 'Siapkan pasien: bebaskan pakaian bawah dan posisikan dorsal rekumben (untuk wanita) dan supine (untuk laki-laki)', 'Pasang sarung tangan', 'Bersihkan daerah perineal atau preposium dengan cairan NaCl atau aquades', 'Lakukan insersi kateter urine dengan menerapkan prinsip aseptik', 'Sambungkan kateter urin dengan urine bag', 'Isi balon dengan NaCl 0,9% sesuai anjuran pabrik', 'Fiksasi selang kateter diatas simpisis atau di paha', 'Pastikan kantung urine ditempatkan lebih rendah dari kandung kemih', 'Berikan label waktu pemasangan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemasangan kateter urine', 'Anjurkan menarik napas saat insersi selang kateter'], 'kolaborasi' => []]], ['kode' => 'I.04148', 'nama' => 'Perawatan Inkontinensia Urine', 'definisi' => 'Mengidentifikasi dan merawat pasien yang mengalami pengeluaran urin secara involunter (tidak disadari)', 'tindakan' => ['observasi' => ['Identifikasi penyebab inkontinensia urine (mis. disfungsi neurologis, gangguan medula spinalis, gangguan refleks destrusor, obat-obatan, usia, riwayat operasi, gangguan fungsi kognitif)', 'Identifikasi perasaan dan persepsi pasien terhadap inkontinensia urine yang dialaminya', 'Monitor keefektifan obat, pembedahan dan terapi modalitas berkemih', 'Monitor kebiasaan berkemih'], 'terapeutik' => ['Bersihkan genital dan kulit sekitar secara rutin', 'Berikan pujian atas keberhasilan mencegah inkontinensia', 'Buat jadwal konsumsi obat-obat diuretik', 'Ambil sampel urine untuk pemeriksaan urine lengkap atau kultur'], 'edukasi' => ['Jelaskan definisi, jenis inkontinensia, penyebab inkontinensia urine', 'Jelaskan program penanganan inkontinensia urine', 'Jelaskan jenis pakaian dan lingkungan yang mendukung proses berkemih', 'Anjurkan membatasi konsumsi cairan 2-3 jam menjelang tidur', 'Ajarkan memantau cairan keluar dan masuk serta pola eliminasi urine', 'Anjurkan minum minimal 1500 cc/hari, jika tidak kontraindikasi', 'Anjurkan menghindari kopi, minuman bersoda, teh dan cokelat', 'Anjurkan konsumsi buah dan sayur untuk menghindari konstipasi'], 'kolaborasi' => ['Rujuk ke ahli inkontinensia, jika perlu']]]]]],

            // D.0043 — Inkontinensia Urin Berlebih (updated from PDF p.228-230)
            ['diagkep_id' => 'D.0043', 'diagkep_desc' => 'Inkontinensia Urin Berlebih', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Kehilangan urin yang tidak terkendali akibat overdistensi kandung kemih', 'penyebab' => ['fisiologis' => ['Blok spingter', 'Kerusakan atau ketidakadekuatan jalur aferen', 'Obstruksi jalan keluar urin (mis. impaksi fekal, efek agen farmakologis)', 'Ketidakadekuatan detrusor', 'Kondisi stres atau tidak nyaman, deconditioned voiding']], 'gejala_tanda_mayor' => ['subjektif' => ['Residu volume urin setelah berkemih atau keluhan kebocoran sedikit urin', 'Nokturia'], 'objektif' => ['Kandungan kemih distensi (bukan berhubungan dengan penyebab reversible akut) atau kandung kemih distensi dengan sering, sedikit berkemih atau dribbling']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Residu urin 100ml atau lebih']], 'kondisi_klinis_terkait' => ['Asma', 'Alergi', 'Penyakit neurologi: cedera/tumor/infeksi medulla spinalis', 'Cedera kepala', 'Sklerosis multipel', 'Dimielinisasi saraf', 'Neuropati diabetikum', 'Neuropati alcohol', 'Striktura uretra/leher kandung kemih', 'Pembesaran prostat', 'Pembengkakan perineal']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Kemampuan mengontrol pengeluaran urin dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan menunda pengeluaran urin dari skala 1 memburuk menjadi skala 5 membaik', 'Frekuensi BAK dari skala 1 memburuk menjadi skala 5 membaik', 'Sensasi BAK dari skala 1 memburuk menjadi skala 5 membaik', 'Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Residu volume urine setelah BAK dari skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 5 menurun', 'Dribbling dari skala 1 meningkat menjadi skala 5 menurun', 'Verbalisasi pengeluaran urin tidak tuntas dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.04163', 'nama' => 'Perawatan Inkontinensia Urin', 'definisi' => 'Mengidentifikasi dan merawat pasien yang mengalami pengeluaran urin secara involunter (tidak disadari)', 'tindakan' => ['observasi' => ['Identifikasi penyebab inkontinensia urine (mis. disfungsi neurologis, gangguan medula spinalis, gangguan refleks destrusor, obat-obatan, usia, riwayat operasi, gangguan fungsi kognitif)', 'Identifikasi perasaan dan persepsi pasien terhadap inkontinensia urine yang dialaminya', 'Monitor keefektifan obat, pembedahan dan terapi modalitas berkemih', 'Monitor kebiasaan berkemih'], 'terapeutik' => ['Bersihkan genital dan kulit sekitar secara rutin', 'Berikan pujian atas keberhasilan mencegah inkontinensia', 'Buat jadwal konsumsi obat-obat diuretik', 'Ambil sampel urine untuk pemeriksaan urine lengkap atau kultur'], 'edukasi' => ['Jelaskan definisi, jenis inkontinensia, penyebab inkontinensia urine', 'Jelaskan program penanganan inkontinensia urine', 'Jelaskan jenis pakaian dan lingkungan yang mendukung proses berkemih', 'Anjurkan membatasi konsumsi cairan 2-3 jam menjelang tidur', 'Ajarkan memantau cairan keluar dan masuk serta pola eliminasi urine', 'Anjurkan minum minimal 1500 cc/hari, jika tidak kontraindikasi', 'Anjurkan menghindari kopi, minuman bersoda, teh dan cokelat', 'Anjurkan konsumsi buah dan sayur untuk menghindari konstipasi'], 'kolaborasi' => ['Rujuk ke ahli inkontinensia, jika perlu']]]]]],

            // D.0044 — Inkontinensia Urin Fungsional (updated from PDF p.231-235)
            ['diagkep_id' => 'D.0044', 'diagkep_desc' => 'Inkontinensia Urin Fungsional', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Pengeluaran urin tidak terkendali karena kesulitan dan tidak mampu mencapai toilet pada waktu yang tepat', 'penyebab' => ['fisiologis' => ['Ketidakmampuan atau penurunan mengenali tanda-tanda berkemih', 'Penurunan tonus kandung kemih', 'Hambatan mobilisasi', 'Faktor psikologis: penurunan perhatian pada tanda-tanda keinginan berkemih (depresi, bingung, delirium)', 'Hambatan lingkungan (toilet jauh, tempat tidur terlalu tinggi, lingkungan baru)', 'Kehilangan sensorik dan motorik (pada geriatri)', 'Gangguan penglihatan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengompol sebelum mencapai atau selama usaha mencapai toilet'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Mengompol di waktu pagi hari', 'Mampu mengosongkan kandung kemih lengkap'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Neuropati alkoholik', 'Penyakit Parkinson', 'Penyakit dimielinisasi', 'Sklerosis multipel', 'Stroke', 'Demensia progresif', 'Depresi']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Kemampuan mengontrol pengeluaran urine dari skala 1 menurun menjadi skala 5 meningkat', 'Nokturia menurun', 'Residu volume urine setelah BAK menurun', 'Distensi kandung kemih menurun', 'Dribbling menurun', 'Hesitancy menurun', 'Enuresis menurun', 'Verbalisasi pengeluaran urine tidak tuntas menurun', 'Kemampuan menunda pengeluaran urine membaik', 'Frekuensi BAK membaik', 'Sensasi BAK membaik']]], 'siki' => [['kode' => 'I.04149', 'nama' => 'Latihan Berkemih', 'definisi' => 'Mengajarkan suatu kemampuan melakukan eliminasi urine', 'tindakan' => ['observasi' => ['Periksa kembali penyebab gangguan berkemih (mis. kognitif, kehilangan ekstremitas/fungsi ekstremitas, kehilangan penglihatan)', 'Monitor pola dan kemampuan berkemih'], 'terapeutik' => ['Hindari penggunaan kateter indwelling', 'Siapkan area toilet yang aman', 'Sediakan peralatan yang dibutuhkan dekat dan mudah dijangkau (mis. kursi komode, pispot, urinal)'], 'edukasi' => ['Jelaskan arah-arah menuju kamar mandi/toilet pada pasien dengan gangguan penglihatan', 'Anjurkan intake cairan adekuat untuk mendukung output urine', 'Anjurkan eliminasi normal dengan beraktivitas dan olahraga sesuai kemampuan'], 'kolaborasi' => []]], ['kode' => 'I.04163', 'nama' => 'Perawatan Inkontinensia Urine', 'definisi' => 'Mengidentifikasi dan merawat pasien yang mengalami pengeluaran urine secara involunter', 'tindakan' => ['observasi' => ['Identifikasi penyebab inkontinensia urine', 'Identifikasi perasaan dan persepsi pasien terhadap inkontinensia urine', 'Monitor keefektifan obat, pembedahan dan terapi modalitas berkemih', 'Monitor kebiasaan berkemih'], 'terapeutik' => ['Bersihkan genital dan kulit sekitar secara rutin', 'Berikan pujian atas keberhasilan mencegah inkontinensia', 'Buat jadwal konsumsi obat-obat diuretik', 'Ambil sampel urine untuk pemeriksaan urine lengkap atau kultur'], 'edukasi' => ['Jelaskan definisi, jenis inkontinensia, penyebab inkontinensia urine', 'Jelaskan program penanganan inkontinensia urine', 'Jelaskan jenis pakaian dan lingkungan yang mendukung proses berkemih', 'Anjurkan membatasi konsumsi cairan 2-3 jam menjelang tidur', 'Ajarkan memantau cairan keluar dan masuk serta pola eliminasi urine', 'Anjurkan minum minimal 1500 cc/hari, jika tidak kontraindikasi', 'Anjurkan menghindari kopi, minuman bersoda, teh dan cokelat', 'Anjurkan konsumsi buah dan sayur untuk menghindari konstipasi'], 'kolaborasi' => ['Rujuk ke ahli inkontinensia, jika perlu']]]]]],

            // D.0045 — Inkontinensia Urin Refleks (updated from PDF p.236-239)
            ['diagkep_id' => 'D.0045', 'diagkep_desc' => 'Inkontinensia Urin Refleks', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Pengeluaran urin yang tidak terkendali saat volume kandung kemih tertentu tercapai', 'penyebab' => ['fisiologis' => ['Kerusakan konduksi impuls di atas arkus refleks', 'Kerusakan jaringan (mis. terapi radiasi)']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak mengalami sensasi berkemih', 'Dribbling', 'Sering buang air kecil', 'Hesitancy', 'Nokturia', 'Enuresis'], 'objektif' => ['Volume residu urin meningkat']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Cedera/tumor/infeksi medula spinalis', 'Cystitis', 'Pembedahan pelvis', 'Sklerosis multipel', 'Kanker kandung kemih atau pelvis', 'Penyakit Parkinson', 'Demensia']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Sensasi berkemih dari skala 1 memburuk menjadi skala 5 membaik', 'Dribbling dari skala 1 meningkat menjadi skala 5 menurun', 'Frekuensi berkemih dari skala 1 memburuk menjadi skala 5 membaik', 'Hesitancy dari skala 1 meningkat menjadi skala 5 menurun', 'Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Enuresis Verbalisasi pengeluaran urin tidak tuntas dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.04148', 'nama' => 'Kateterisasi Urine', 'definisi' => 'Masukan selang kateter urine ke dalam kandung kemih', 'tindakan' => ['observasi' => ['Periksa kondisi pasien (mis. kesadaran, tanda-tanda vital, daerah perineal, distensi kandung kemih, inkontinensia urine, refleks berkemih)'], 'terapeutik' => ['Siapkan peralatan, bahan-bahan dan ruangan tindakan', 'Siapkan pasien: bebaskan pakaian bawah dan posisikan dorsal rekumben (untuk wanita) dan supine (untuk laki-laki)', 'Pasang sarung tangan', 'Bersihkan daerah perineal atau preposium dengan cairan NaCl atau aquades', 'Lakukan insersi kateter urine dengan menerapkan prinsip aseptik', 'Sambungkan kateter urin dengan urine bag', 'Isi balon dengan NaCl 0,9% sesuai anjuran pabrik', 'Fiksasi selang kateter diatas simpisis atau di paha', 'Pastikan kantung urine ditempatkan lebih rendah dari kandung kemih', 'Berikan label waktu pemasangan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemasangan kateter urine', 'Anjurkan menarik napas saat insersi selang kateter'], 'kolaborasi' => []]]]]],

            // D.0046 — Inkontinensia Urin Stres (updated from PDF p.239-240)
            ['diagkep_id' => 'D.0046', 'diagkep_desc' => 'Inkontinensia Urin Stres', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Kebocoran urin mendadak dan tidak dapat dikendalikan karena aktivitas yang meningkatkan tekanan intraabdominal', 'penyebab' => ['fisiologis' => ['Kelemahan intrinsik spinkter uretra', 'Perubahan degenerasi/non degenerasi otot pelvis', 'Kekurangan estrogen', 'Peningkatan tekanan intraabdomen', 'Kelemahan otot pelvis']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh keluar urin <50ml saat tekanan abdominal meningkat (mis. saat berdiri, bersin, tertawa, berlari atau mengangkat benda berat)'], 'objektif' => ['Urin keluar saat tekanan intra-abdomen meningkat']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Multipara', 'Obesitas', 'Pasca melahirkan per vaginam', 'Menopause', 'Pasca prostatektomi']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Kemampuan mengontrol pengeluaran urine dari skala 1 menurun menjadi skala 5 meningkat', 'Nokturia dari skala 1 meningkat menjadi skala 3 sedang', 'Residu volume urine setelah berkemih dari skala 1 meningkat menjadi skala 3 sedang', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 3 sedang', 'Dribbling dari skala 1 meningkat menjadi skala 3 sedang', 'Hesitancy dari skala 1 meningkat menjadi skala 3 sedang']]], 'siki' => [['kode' => 'I.07215', 'nama' => 'Latihan Otot Panggul', 'definisi' => 'Mengajarkan kemampuan meningkatkan otot-otot elevator ani dan urogenitalis melalui kontraksi berulang untuk menurunkan inkontinensia urin dan ejakulasi dini', 'tindakan' => ['observasi' => ['Monitor pengeluaran urine'], 'terapeutik' => ['Berikan reinforcement positif selama melakukan latihan dengan benar'], 'edukasi' => ['Anjurkan berbaring', 'Anjurkan tidak mengkontraksikan perut, kaki dan bokong saat melakukan latihan otot panggul', 'Anjurkan menambah durasi kontraksi-relaksasi 10 detik'], 'kolaborasi' => []]]]]],

            // D.0047 — Inkontinensia Urin Urgensi (updated from PDF p.246-247)
            ['diagkep_id' => 'D.0047', 'diagkep_desc' => 'Inkontinensia Urin Urgensi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Keluarnya urin tidak terkendali sesaat setelah keinginan yang kuat untuk berkemih (Kebelet)', 'penyebab' => ['fisiologis' => ['Iritasi reseptor kontraksi kandung kemih', 'Penurunan kapasitas kandung kemih', 'Hiperaktivitas detrusor dengan kerusakan kontraktilitas kandung kemih', 'Efek agen farmakologis (mis. Deuretik)']], 'gejala_tanda_mayor' => ['subjektif' => ['Keinginan berkemih yang kuat disertai dengan inkontinensia'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Riwayat penyakit peradangan pelvis dan/atau vagina', 'Riwayat penggunaan kateter urin', 'Infeksi kandung kemih dan/atau uretra', 'Gangguan neurogenic/tumor/infeksi', 'Penyakit Parkinson', 'Neuropati diabetikum', 'Operasi abdomen']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Residu volume urine setelah berkemih dari skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 5 menurun', 'Dribbling dari skala 1 meningkat menjadi skala 5 menurun', 'Hesitancy dari skala 1 meningkat menjadi skala 5 menurun', 'Frekuensi berkemih dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.04149', 'nama' => 'Latihan Berkemih', 'definisi' => 'Mengajarkan suatu kemampuan melakukan eliminasi urine', 'tindakan' => ['observasi' => ['Periksa kembali penyebab gangguan berkemih (mis. Kognitif, kehilangan ekstremitas/fungsi ekstremitas, kehilangan penglihatan)', 'Monitor pola dan kemampuan berkemih'], 'terapeutik' => ['Hindari penggunaan kateter indwelling', 'Siapkan area toilet yang aman', 'Sediakan peralatan yang dibutuhkan dekat dan mudah dijangkau (mis. Kursi komode, pispot, urinal)'], 'edukasi' => ['Jelaskan arah-arah menuju kamar mandi/toilet pada pasien dengan gangguan penglihatan', 'Anjurkan intake cairan adekuat untuk mendukung output urine', 'Anjurkan eliminasi normal dengan beraktivitas dan olahraga sesuai kemampuan'], 'kolaborasi' => []]]]]],

            // D.0048 — Kesiapan Peningkatan Eliminasi Urin (updated from PDF p.248-249)
            ['diagkep_id' => 'D.0048', 'diagkep_desc' => 'Kesiapan Peningkatan Eliminasi Urin', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Pola fungsi sistem perkemihan yang cukup untuk memenuhi kebutuhan eliminasi yang dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan keinginan untuk meningkatkan eliminasi urin'], 'objektif' => ['Jumlah urin normal', 'Karakteristik urin normal']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Asupan cairan cukup']], 'kondisi_klinis_terkait' => ['Cedera medula spinalis', 'Sklerosis multipel', 'Kehamilan', 'Trauma pelvis', 'Pembedahan abdomen', 'Penyakit prostat']], 'slki' => [['kode' => 'L.04034', 'nama' => 'Eliminasi Urine', 'kriteria_hasil' => ['Sensasi berkemih skala 1 menurun menjadi skala 5 meningkat', 'Desakan berkemih (urgensi) skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih (hesitancy) skala 1 meningkat menjadi skala 5 menurun', 'Volume residu urine skala 1 meningkat menjadi skala 5 menurun', 'Urin menetes (dribbling) skala 1 meningkat menjadi skala 5 menurun', 'Nokturia skala 1 meningkat menjadi skala 5 menurun', 'Mengompol skala 1 meningkat menjadi skala 5 menurun', 'Enuresis skala 1 meningkat menjadi skala 5 menurun', 'Disuria skala 1 meningkat menjadi skala 5 menurun', 'Anuria skala 1 meningkat menjadi skala 5 menurun', 'Frekuensi BAK skala 1 memburuk menjadi skala 5 membaik', 'Karakteristik urine skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.04152', 'nama' => 'Manajemen Eliminasi Urine', 'definisi' => 'Mengidentifikasi dan mengelola gangguan pola eliminasi urine', 'tindakan' => ['observasi' => ['Identifikasi tanda dan gejala inkontinensia urine', 'Identifikasi faktor yang menyebabkan retensi atau inkontinensia urine', 'Monitor eliminasi urine (mis. frekuensi, konsistensi, aroma, volume, dan warna)'], 'terapeutik' => ['Catat waktu-waktu dan haluaran berkemih', 'Batasi asupan cairan, jika perlu', 'Ambil sampel urine tengah (midstream) atau kultur'], 'edukasi' => ['Ajarkan tanda dan gejala infeksi saluran kemih', 'Ajarkan mengukur asupan cairan dan haluaran urine', 'Ajarkan mengambil spesimen urine midstream', 'Ajarkan mengenali tanda berkemih dan waktu yang tepat untuk berkemih', 'Ajarkan terapi modalitas penguatan otot-otot panggul/berkemihan', 'Anjurkan minum yang cukup, jika tidak ada kontraindikasi', 'Anjurkan mengurangi minum menjelang tidur'], 'kolaborasi' => ['Kolaborasi pemberian obat supositoria uretra, jika perlu']]]]]],

            // D.0049 — Konstipasi (updated from PDF p.250-252)
            ['diagkep_id' => 'D.0049', 'diagkep_desc' => 'Konstipasi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Penurunan defekasi normal yang disertai pengeluaran feses sulit dan tidak tuntas serta feses kering dan banyak', 'penyebab' => ['fisiologis' => ['Penurunan mobilitas gastrointestinal', 'Ketidakadekuatan pertumbuhan gigi', 'Ketidakcukupan diet', 'Ketidakcukupan asupan serat', 'Ketidakcukupan asupan cairan', 'Aganglionik (mis. penyakit Hirscprung)'], 'psikologis' => ['Konfusi', 'Depresi', 'Gangguan emosional'], 'situasional' => ['Perubahan kebiasaan makan (mis. jenis makanan, jadwal makan)', 'Ketidakadekuatan toileting', 'Aktivitas fisik harian kurang dari yang dianjurkan', 'Penyalahgunaan laksatif', 'Efek agen farmakologis', 'Ketidakteraturan kebiasaan defekasi', 'Kebiasaan menahan dorongan defekasi', 'Perubahan lingkungan']], 'gejala_tanda_mayor' => ['subjektif' => ['Defekasi kurang dari 2 kali seminggu', 'Pengeluaran feses lama dan sulit'], 'objektif' => ['Feses keras', 'Peristaltik usus menurun']], 'gejala_tanda_minor' => ['subjektif' => ['Mengejan saat defekasi'], 'objektif' => ['Distensi abdomen', 'Kelemahan umum', 'Teraba massa pada rektal']], 'kondisi_klinis_terkait' => ['Lesi/cedera pada medula spinalis', 'Spina bifida', 'Stroke', 'Sklerosis multipel', 'Penyakit Parkinson', 'Demensia', 'Hiperparatiroidisme', 'Hipoparatiroidisme', 'Ketidakseimbangan elektrolit', 'Hemoroid', 'Obesitas', 'Pasca operasi obstruksi bowel', 'Kehamilan', 'Pembesaran prostat', 'Abses rektal', 'Fisura anorektal', 'Striktura anorektal', 'Prolaps rektal', 'Ulkus rektal', 'Rektokel', 'Tumor', 'Penyakit Hirscprung', 'Impaksi feses']], 'slki' => [['kode' => 'L.04033', 'nama' => 'Eliminasi Fekal', 'kriteria_hasil' => ['Kontrol pengeluaran feses dari skala 1 menurun menjadi skala 5 meningkat', 'Keluhan defekasi lama dan sulit dari skala 1 meningkat menjadi skala 5 menurun', 'Mengejan saat defekasi dari skala 1 meningkat menjadi skala 5 menurun', 'Konsistensi feses dari skala 1 memburuk menjadi skala 5 membaik', 'Frekuensi BAB dari skala 1 memburuk menjadi skala 5 membaik', 'Peristaltik usus dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.04151', 'nama' => 'Manajemen Eliminasi Fekal', 'definisi' => 'Mengidentifikasi dan mengelola gangguan pola eliminasi fekal', 'tindakan' => ['observasi' => ['Identifikasi masalah usus dan penggunaan obat pencahar', 'Identifikasi pengobatan yang berefek pada kondisi gastrointestinal', 'Monitor buang air besar (mis. warna, frekuensi, konsistensi, volume)', 'Monitor tanda dan gejala diare, konstipasi, atau impaksi'], 'terapeutik' => ['Berikan air hangat setelah makan', 'Jadwalkan waktu defekasi bersama pasien', 'Sediakan makanan tinggi serat'], 'edukasi' => ['Jelaskan jenis makanan yang membantu meningkatkan keteraturan peristaltik usus', 'Anjurkan mencatat warna, frekuensi, konsistensi, volume feses', 'Anjurkan meningkatkan aktivitas fisik, sesuai toleransi', 'Anjurkan pengurangan asupan makanan yang meningkatkan pembentukkan gas', 'Anjurkan mengkonsumsi makanan yang mengandung tinggi serat', 'Anjurkan meningkatkan asupan cairan, jika tidak ada kontraindikasi'], 'kolaborasi' => ['Kolaborasi pemberian obat suppositoria anal, jika perlu']]]]]],

            // D.0050 — Retensi Urin (updated from PDF p.253-256)
            ['diagkep_id' => 'D.0050', 'diagkep_desc' => 'Retensi Urin', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Pengosongan kandung kemih yang tidak lengkap', 'penyebab' => ['fisiologis' => ['Peningkatan tekanan uretra', 'Kerusakan arkus refleks', 'Blok spingter', 'Disfungsi neurologis (mis. trauma, penyakit saraf)', 'Efek agen farmakologis (mis. atropine, belladonna, psikotropik, antihistamin, opiate)']], 'gejala_tanda_mayor' => ['subjektif' => ['Sensasi penuh pada kandung kemih'], 'objektif' => ['Disuria/anuria', 'Distensi kandung kemih']], 'gejala_tanda_minor' => ['subjektif' => ['Dribbling'], 'objektif' => ['Inkontinensia berlebih', 'Residu urin 150 ml atau lebih']], 'kondisi_klinis_terkait' => ['Benigna prostat hiperplasia', 'Pembengkakan perineal', 'Cedera medula spinalis', 'Rektokel', 'Tumor di saluran kemih']], 'slki' => [['kode' => 'L.04034', 'nama' => 'Eliminasi Urine', 'kriteria_hasil' => ['Sensasi berkemih dari skala 1 menurun menjadi skala 5 meningkat', 'Desakan berkemih (urgensi) dari skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 5 menurun', 'Berkemih tidak tuntas (hesitancy) dari skala 1 meningkat menjadi skala 5 menurun', 'Volume residu urine dari skala 1 meningkat menjadi skala 5 menurun', 'Urine menetes (dribbling) dari skala 1 meningkat menjadi skala 5 menurun', 'Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Mengompol dari skala 1 meningkat menjadi skala 5 menurun', 'Enuresis dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.04148', 'nama' => 'Kateterisasi Urine', 'definisi' => 'Memasukkan selang kateter urine ke dalam kandung kemih', 'tindakan' => ['observasi' => ['Periksa kondisi pasien (mis. kesadaran, tanda-tanda vital, daerah perineal, distensi kandung kemih, inkontinensia urine, refleks berkemih)'], 'terapeutik' => ['Siapkan peralatan, bahan-bahan dan ruangan tindakan', 'Siapkan pasien: bebaskan pakaian bawah dan posisikan dorsal rekumben (untuk wanita) dan supine (untuk laki-laki)', 'Pasang sarung tangan', 'Bersihkan daerah perineal atau periposium dengan cairan NaCl', 'Lakukan insersi kateter urine dengan menerapkan prinsip aseptik', 'Sambungkan kateter urin dengan urine bag', 'Isi balon dengan NaCl 0,9% sesuai anjuran pabrik', 'Fiksasi selang kateter diatas simpisis atau di paha', 'Pastikan kantung urine ditempatkan lebih rendah dari kandung kemih', 'Berikan label waktu pemasangan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemasangan kateter urine', 'Anjurkan menarik napas saat insersi selang kateter'], 'kolaborasi' => []]]]]],

            // D.0051 — Risiko Inkontinensia Urin Urgensi (updated from PDF p.256-265)
            ['diagkep_id' => 'D.0051', 'diagkep_desc' => 'Risiko Inkontinensia Urin Urgensi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Berisiko mengalami pengeluaran urin yang tidak terkendali', 'penyebab' => [], 'faktor_risiko' => ['Efek samping obat, kopi dan alkohol', 'Hiperrefleks destrussor', 'Gangguan sistem saraf pusat', 'Kerusakan kontraksi kandung kemih: relaksasi spingter tidak terkendali', 'Ketidakefektifan kebiasaan berkemih', 'Kapasitas kandung kemih kecil'], 'kondisi_klinis_terkait' => ['Infeksi/tumor/batu saluran kemih dan/atau ginjal', 'Gangguan sistem saraf pusat']], 'slki' => [['kode' => 'L.04036', 'nama' => 'Kontinensia Urin', 'kriteria_hasil' => ['Kemampuan mengontrol pengeluaran urine dari skala 1 menurun menjadi skala 5 meningkat', 'Nokturia dari skala 1 meningkat menjadi skala 5 menurun', 'Residu volume urine setelah BAK dari skala 1 meningkat menjadi skala 5 menurun', 'Distensi kandung kemih dari skala 1 meningkat menjadi skala 5 menurun', 'Dribbling dari skala 1 meningkat menjadi skala 5 menurun', 'Hesitancy dari skala 1 meningkat menjadi skala 5 menurun', 'Enuresis dari skala 1 meningkat menjadi skala 5 menurun', 'Kemampuan menunda pengeluaran urine dari skala 1 memburuk menjadi skala 5 membaik', 'Frekuensi BAK dari skala 1 memburuk menjadi skala 5 membaik', 'Sensasi BAK dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.04152', 'nama' => 'Manajemen Eliminasi Urine', 'definisi' => 'Mengidentifikasi dan mengelola gangguan pola eliminasi urine', 'tindakan' => ['observasi' => ['Identifikasi tanda dan gejala retensi atau inkontinensia urine', 'Identifikasi faktor yang menyebabkan retensi atau inkontinensia urine', 'Monitor eliminasi urine (mis. frekuensi, konsistensi, aroma, volume, dan warna)'], 'terapeutik' => ['Catat waktu-waktu dan haluaran berkemih', 'Batasi asupan cairan, jika perlu', 'Ambil sampel urine tengah (midstream) atau kultur'], 'edukasi' => ['Ajarkan tanda dan gejala infeksi saluran kemih', 'Ajarkan mengukur asupan cairan dan haluaran urine', 'Ajarkan mengambil spesimen urine midstream', 'Ajarkan mengenali tanda berkemih dan waktu yang tepat untuk berkemih', 'Ajarkan terapi modalitas penguatan otot-otot panggul/berkemihan', 'Anjurkan minum yang cukup, jika tidak ada kontraindikasi', 'Anjurkan mengurangi minum menjelang tidur'], 'kolaborasi' => ['Kolaborasi pemberian obat supositoria uretra, jika perlu']]]]]],

            // D.0052 — Risiko Konstipasi (updated from PDF p.265)
            ['diagkep_id' => 'D.0052', 'diagkep_desc' => 'Risiko Konstipasi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Eliminasi', 'definisi' => 'Berisiko mengalami penurunan frekuensi normal defekasi disertai kesulitan dan pengeluaran feses yang tidak lengkap', 'penyebab' => [], 'faktor_risiko' => ['Penurunan motilitas gastrointestinal', 'Ketidakcukupan asupan serat', 'Ketidakcukupan asupan cairan', 'Aktivitas fisik harian kurang dari yang dianjurkan', 'Penyalahgunaan laksatif', 'Efek agen farmakologis', 'Ketidakteraturan kebiasaan defekasi', 'Kebiasaan menahan dorongan defekasi', 'Perubahan lingkungan', 'Konfusi', 'Depresi', 'Gangguan emosional'], 'kondisi_klinis_terkait' => ['Lesi/cedera pada medula spinalis', 'Stroke', 'Sklerosis multipel', 'Penyakit Parkinson', 'Demensia', 'Hiperparatiroidisme', 'Hipoparatiroidisme', 'Hemoroid', 'Obesitas', 'Kehamilan', 'Tumor']], 'slki' => [['kode' => 'L.04033', 'nama' => 'Eliminasi Fekal', 'kriteria_hasil' => ['Kontrol pengeluaran fefse membaik dari skala 2 menjadi skala 5 meningkat', 'Konsistensi feses membaik', 'Frekuensi defekasi membaik', 'Peristaltik usus membaik']]], 'siki' => [['kode' => 'I.04151', 'nama' => 'Pencegahan Konstipasi', 'definisi' => 'Mengidentifikasi dan menurunkan risiko terjadinya penurunan frekuensi normal defekasi yang disertai kesulitan pengeluaran feses yang tidak lengkap', 'tindakan' => ['observasi' => ['Identifikasi faktor risiko konstipasi (mis. asupan serat tidak adekuat, asupan cairan tidak adekuat)'], 'terapeutik' => ['Berikan air hangat setelah makan', 'Jadwalkan waktu defekasi bersama pasien', 'Sediakan makanan tinggi serat'], 'edukasi' => ['Jelaskan jenis makanan yang membantu meningkatkan keteraturan peristaltik usus', 'Anjurkan mencatat warna, frekuensi, konsistensi, volume feses', 'Anjurkan meningkatkan aktivitas fisik, sesuai toleransi', 'Anjurkan mengkonsumsi makanan yang mengandung tinggi serat', 'Anjurkan meningkatkan asupan cairan, jika tidak ada kontraindikasi'], 'kolaborasi' => ['Kolaborasi pemberian obat suppositoria anal, jika perlu']]]]]],

            // D.0053 — Disorganisasi Perilaku Bayi (updated from PDF p.267-270)
            ['diagkep_id' => 'D.0053', 'diagkep_desc' => 'Disorganisasi Perilaku Bayi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Disintegrasi respon fisiologis dan neurobehaviour bayi terhadap lingkungan', 'penyebab' => ['fisiologis' => ['Keterbatasan lingkungan fisik', 'Ketidaktepatan sensori', 'Kelebihan stimulasi sensoris', 'Imaturitas sistem sensoris', 'Prematuritas', 'Prosedur invasif', 'Malnutrisi', 'Gangguan motorik', 'Kelainan kongenital', 'Kelainan genetik', 'Terpapar teratogenik']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Hiperekstensi ekstremitas', 'Jari-jari meregang atau tangan menggenggam', 'Respon abnormal terhadap stimulus sensorik', 'Gerakan tidak terkoordinasi']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Menangis', 'Tidak mampu menghambat respon terkejut', 'Iritabilitas', 'Gangguan refleks', 'Tonus motorik berubah', 'Tangan di wajah', 'Gelisah', 'Tremor', 'Tersentak', 'Aritmia', 'Bradikardia atau takikardia', 'Saturasi menurun', 'Tidak mau menyusu', 'Warna kulit berubah']], 'kondisi_klinis_terkait' => ['Hospitalisasi', 'Prosedur invasif', 'Prematuritas', 'Gangguan neurologis', 'Gangguan pernafasan', 'Gangguan kardiovaskuler']], 'slki' => [['kode' => 'L.05043', 'nama' => 'Organisasi Perilaku Bayi', 'kriteria_hasil' => ['Gerakan pada ekstremitas dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan jari-jari menggenggam dari skala 1 menurun menjadi skala 5 meningkat', 'Gerakan terkoordinasi dari skala 1 menurun menjadi skala 5 meningkat', 'Respon normal terhadap stimulus sensorik dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.10338', 'nama' => 'Perawatan Bayi', 'definisi' => 'Mengidentifikasi dan merawat kesehatan bayi', 'tindakan' => ['observasi' => ['Monitor tanda-tanda vital bayi (terutama suhu 36,5C-37,5C)'], 'terapeutik' => ['Mandikan bayi dengan suhu ruangan 21-24C', 'Mandikan bayi dalam waktu 5-10 menit dan 2 kali dalam sehari', 'Rawat tali pusat secara terbuka (tali pusat tidak dibungkus apapun)', 'Bersihkan pangkal tali pusat lidi kapas yang telah diberi air matang', 'Kenakan popok bayi di bawah umbilikus jika tali pusat belum terlepas', 'Lakukan pemijatan bayi', 'Ganti popok bayi jika basah', 'Kenakan pakaian bayi dari bahan katun'], 'edukasi' => ['Ajarkan ibu menyusui sesuai kebutuhan bayi', 'Ajarkan ibu cara merawat bayi di rumah', 'Ajarkan cara pemberian makanan pendamping ASI pada bayi > 6 bulan'], 'kolaborasi' => []]]]]],

            // D.0054 — Gangguan Mobilitas Fisik (updated from PDF p.271-276)
            ['diagkep_id' => 'D.0054', 'diagkep_desc' => 'Gangguan Mobilitas Fisik', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Keterbatasan dalam gerak fisik dari satu atau lebih ekstremitas secara mandiri', 'penyebab' => ['fisiologis' => ['Kerusakan integritas struktur tulang', 'Perubahan metabolisme', 'Ketidakbugaran fisik', 'Penurunan kendali otot', 'Penurunan massa otot', 'Penurunan kekuatan otot', 'Keterlambatan perkembangan', 'Kekakuan sendi', 'Kontraktur', 'Malnutrisi', 'Gangguan muskuloskeletal', 'Gangguan neuromuskular', 'Indeks massa tubuh diatas persentil ke 75 sesuai usia', 'Efek agen farmakologis', 'Program pembatasan gerak', 'Nyeri', 'Kurang terpapar informasi tentang aktivitas fisik', 'Kecemasan', 'Gangguan kognitif', 'Keengganan melakukan pergerakan', 'Gangguan sensori persepsi']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh sulit menggerakkan ekstremitas'], 'objektif' => ['Kekuatan otot menurun', 'Rentang gerak (ROM) menurun']], 'gejala_tanda_minor' => ['subjektif' => ['Nyeri saat bergerak', 'Enggan melakukan pergerakan', 'Merasa cemas saat bergerak'], 'objektif' => ['Sendi kaku', 'Gerakan tidak terkoordinasi', 'Gerakan terbatas', 'Fisik lemah']], 'kondisi_klinis_terkait' => ['Stroke', 'Cedera medula spinalis', 'Trauma', 'Fraktur', 'Osteoartritis', 'Ostemalasia', 'Keganasan']], 'slki' => [['kode' => 'L.05042', 'nama' => 'Mobilitas Fisik', 'kriteria_hasil' => ['Pergerakan ekstremitas meningkat (5)', 'Kekuatan otot meningkat (5)', 'Rentang gerak (ROM) meningkat (5)', 'Kaku sendi menurun (5)', 'Gerakan tidak berkoordinasi menurun (5)', 'Kelemahan fisik menurun (5)']]], 'siki' => [['kode' => 'I.05173', 'nama' => 'Dukungan Mobilisasi', 'definisi' => 'Memfasilitasi pasien untuk meningkatkan aktivitas pergerakan fisik', 'tindakan' => ['observasi' => ['Identifikasi adanya nyeri atau keluhan fisik lainnya', 'Identifikasi toleransi fisik melakukan pergerakan', 'Monitor frekuensi jantung dan tekanan darah sebelum memulai mobilisasi', 'Monitor kondisi umum selama melakukan mobilisasi'], 'terapeutik' => ['Fasilitasi aktivitas mobilisasi dengan alat bantu (mis. pagar tempat tidur)', 'Fasilitasi melakukan pergerakan', 'Libatkan keluarga untuk membantu pasien dalam meningkatkan pergerakan'], 'edukasi' => ['Jelaskan tujuan dan prosedur mobilisasi', 'Anjurkan melakukan mobilisasi dini', 'Ajarkan mobilisasi sederhana yang harus dilakukan (mis. Duduk di tempat tidur, di sisi tempat tidur, pindah dari tempat tidur ke kursi)'], 'kolaborasi' => []]], ['kode' => 'I.12389', 'nama' => 'Edukasi Latihan Fisik', 'definisi' => 'Mengajarkan aktivitas fisik reguler untuk mempertahankan atau meningkatkan kebugaran dan kesehatan', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan jenis latihan yang sesuai dengan kondisi kesehatan', 'Ajarkan teknik pernapasan yang tepat untuk memaksimalkan penyerapan oksigen selama latihan fisik'], 'kolaborasi' => []]], ['kode' => 'I.11354', 'nama' => 'Perawatan Kaki', 'definisi' => 'Mengidentifikasi dan merawat luka untuk keperluan relaksasi, kebersihan, dan kesehatan kaki', 'tindakan' => ['observasi' => ['Periksa adanya iritasi, retak, lesi, kapalan, kelainan bentuk, atau edema'], 'terapeutik' => ['Berikan pelembab kaki, sesuai kebutuhan', 'Bersikan dan/atau potong kuku, sesuai kebutuhan', 'Lakukan perawatan luka sesuai kebutuhan'], 'edukasi' => ['Anjurkan pentingnya pemeriksaan kaki, terutama saat sensasi berkurang', 'Anjurkan menghindari penekanan pada kaki yang mengalami ulkus dengan menggunakan tongkat atau sepatu khusus'], 'kolaborasi' => ['Rujuk podiatrist untuk memotong kuku yang menebal, jika perlu']]], ['kode' => 'I.02079', 'nama' => 'Perawatan Sirkulasi', 'definisi' => 'Mengidentifikasi dan merawat area lokal dengan keterbatasan sirkulasi perifer', 'tindakan' => ['observasi' => ['Identifikasi faktor risiko gangguan sirkulasi (mis. diabetes, perokok, orang tua, hipertensi, dan kadar kolesterol tinggi)'], 'terapeutik' => ['Lakukan pencegahan infeksi', 'Lakukan hidrasi'], 'edukasi' => ['Anjurkan berolahraga rutin', 'Anjurkan menggunakan obat penurun tekanan darah, antikoagulan, dan penurun kolesterol, jika perlu', 'Anjurkan minum obat pengontrol tekanan darah secara teratur', 'Ajarkan program diet untuk memperbaiki sirkulasi (mis. rendah lemak jenuh, minyak ikan omega 3)', 'Informasikan tanda dan gejala darurat yang harus dilaporkan (mis. rasa sakit yang tidak hilang saat istirahat, luka tidak sembuh, hilangnya rasa)'], 'kolaborasi' => []]]]]],

            // D.0055 — Gangguan Pola Tidur (updated from PDF p.277-282)
            ['diagkep_id' => 'D.0055', 'diagkep_desc' => 'Gangguan Pola Tidur', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Gangguan kualitas dan kuantitas waktu tidur akibat faktor eksternal', 'penyebab' => ['fisiologis' => ['Hambatan lingkungan (mis. kelembaban lingkungan sekitar, suhu lingkungan, pencahayaan, kebisingan, bau tidak sedap, jadwal pemantauan/pemeriksaan/tindakan)', 'Kurangnya kontrol tidur', 'Kurangnya privasi', 'Restraint fisik', 'Ketiadaan teman tidur', 'Tidak familier dengan peralatan tidur']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh sulit tidur', 'Mengeluh sering terjaga', 'Mengeluh tidak puas tidur', 'Mengeluh pola tidur berubah', 'Mengeluh istirahat tidak cukup'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Mengeluh kemampuan beraktivitas menurun'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Nyeri/kolik', 'Hipertiroidisme', 'Kecemasan', 'Penyakit paru obstruktif kronik', 'Kehamilan', 'Periode pasca partum', 'Kondisi pasca operasi']], 'slki' => [['kode' => 'L.05045', 'nama' => 'Pola Tidur', 'kriteria_hasil' => ['Keluhan sulit tidur membaik', 'Keluhan sering terjaga cukup membaik', 'Keluhan tidak puas tidur cukup membaik', 'Keluhan pola tidur berubah sedang', 'Keluhan istirahat tidak cukup membaik']]], 'siki' => [['kode' => 'I.05174', 'nama' => 'Dukungan Tidur', 'definisi' => 'Memfasilitasi siklus tidur dan terjaga yang teratur', 'tindakan' => ['observasi' => ['Identifikasi pola aktivitas dan tidur', 'Identifikasi faktor pengganggu tidur (fisik dan/atau psikologis)'], 'terapeutik' => ['Modifikasi lingkungan (mis. Pencahayaan, kebisingan, suhu, matras dan tempat tidur)', 'Batasi waktu tidur siang, jika perlu', 'Fasilitasi menghilangkan stress sebelum tidur', 'Tetapkan jadwal tidur rutin', 'Lakukan prosedur untuk meningkatkan kenyamanan (mis. pijat, mengatur posisi, terapi akupresur)', 'Sesuaikan jadwal pemberian obat dan/atau tindakan untuk menunjang siklus tidur-terjaga'], 'edukasi' => ['Jelaskan pentingnya tidur cukup selama sakit', 'Anjurkan menepati kebiasaan waktu tidur', 'Anjurkan mengurangi makanan/minuman yang mengganggu tidur', 'Anjurkan penggunaan obat tidur yang tidak mengandung supresor terhadap tidur REM', 'Ajarkan faktor-faktor yang berkontribusi terhadap gangguan pola tidur (mis. psikologis, gaya hidup, sering berubah shift bekerja)', 'Ajarkan relaksasi otot autogenic atau cara nonfarmakologi lainnya'], 'kolaborasi' => []]]]]],

            // D.0056 — Intoleransi Aktivitas (updated from PDF p.283-286)
            ['diagkep_id' => 'D.0056', 'diagkep_desc' => 'Intoleransi Aktivitas', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Ketidakcukupan energi untuk melakukan aktivitas sehari-hari', 'penyebab' => ['fisiologis' => ['Ketidakseimbangan antara suplai dan kebutuhan oksigen', 'Tirah baring', 'Kelemahan', 'Imobilitas', 'Gaya hidup monoton']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh lelah'], 'objektif' => ['Frekuensi jantung meningkat >20% dari kondisi istirahat']], 'gejala_tanda_minor' => ['subjektif' => ['Dispnea saat/setelah aktivitas', 'Merasa tidak nyaman setelah beraktivitas', 'Merasa lemah'], 'objektif' => ['Tekanan darah berubah >20% dari kondisi istirahat', 'Gambaran EKG menunjukkan aritmia saat/setelah aktivitas', 'Gambaran EKG menunjukkan iskemia', 'Sianosis']], 'kondisi_klinis_terkait' => ['Anemia', 'Gagal jantung kongestif', 'Penyakit jantung koroner', 'Penyakit katup jantung', 'Aritmia', 'Penyakit paru obstruktif kronis (PPOK)', 'Gangguan metabolik', 'Gangguan muskuloskeletal']], 'slki' => [['kode' => 'L.05047', 'nama' => 'Toleransi Aktivitas', 'kriteria_hasil' => ['Kemudahan melakukan aktivitas sehari-hari meningkat', 'Keluhan lelah menurun', 'Dispnea saat aktivitas menurun', 'Tekanan darah membaik', 'Frekuensi napas membaik']]], 'siki' => [['kode' => 'I.05178', 'nama' => 'Manajemen Energi', 'definisi' => 'Mengidentifikasi dan mengelola penggunaan energi untuk mengatasi atau mencegah kelelahan dan mengoptimalkan proses pemulihan', 'tindakan' => ['observasi' => ['Monitor pola dan jam tidur', 'Monitor lokasi dan ketidaknyamanan selama melakukan aktivitas'], 'terapeutik' => ['Sediakan lingkungan nyaman dan rendah stimulus (mis. cahaya, suara, kunjungan)', 'Berikan aktivitas distraksi yang menenangkan'], 'edukasi' => ['Anjurkan tirah baring', 'Anjurkan melakukan aktivitas secara bertahap', 'Ajarkan strategi koping untuk mengurangi kelelahan'], 'kolaborasi' => ['Kolaborasi dengan ahli gizi tentang cara meningkatkan asupan makanan']]], ['kode' => 'I.05174', 'nama' => 'Dukungan Tidur', 'definisi' => 'Memfasilitasi siklus tidur dan terjaga yang teratur', 'tindakan' => ['observasi' => ['Identifikasi faktor pengganggu tidur (fisik dan/atau psikologis)'], 'terapeutik' => ['Modifikasi lingkungan (mis. Pencahayaan, kebisingan, suhu, matras dan tempat tidur)'], 'edukasi' => ['Jelaskan pentingnya tidur cukup selama sakit'], 'kolaborasi' => []]], ['kode' => 'I.05186', 'nama' => 'Terapi Aktivitas', 'definisi' => 'Menggunakan aktivitas fisik, kognitif, sosial, dan spiritual tertentu untuk memulihkan keterlibatan, frekuensi dan durasi aktivitas individu atau kelompok', 'tindakan' => ['observasi' => ['Monitor respons emosional, fisik, sosial dan spiritual terhadap aktivitas'], 'terapeutik' => ['Fasilitasi memilih aktivitas dan tetapkan tujuan aktivitas yang konsisten sesuai kemampuan fisik, psikologis dan sosial'], 'edukasi' => ['Jelaskan metode aktivitas fisik sehari-hari, jika perlu', 'Ajarkan cara melakukan aktivitas yang dipilih'], 'kolaborasi' => ['Kolaborasi dengan terapi okupasi dalam merencanakan dan memonitor program aktivitas, jika sesuai']]]]]],

            // D.0057 — Keletihan (updated from PDF p.288-289)
            ['diagkep_id' => 'D.0057', 'diagkep_desc' => 'Keletihan', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Penurunan kapasitas kerja fisik dan mental yang tidak pulih dengan istirahat', 'penyebab' => ['fisiologis' => ['Gangguan tidur', 'Gaya hidup monoton', 'Kondisi fisiologis (mis. penyakit kronis, penyakit terminal, anemia, malnutrisi, kehamilan)', 'Program perawatan/pengobatan jangka panjang', 'Peristiwa hidup negatif', 'Stres berlebihan', 'Depresi']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa energi tidak pulih walaupun terasa tidur', 'Merasa kurang tenaga', 'Mengeluh lelah'], 'objektif' => ['Tidak mampu mempertahankan aktivitas rutin', 'Tampak lesu']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa bersalah akibat tidak mampu menjalankan tanggung jawab', 'Libido menurun'], 'objektif' => ['Kebutuhan istirahat meningkat']], 'kondisi_klinis_terkait' => ['Anemia', 'Kanker', 'Hipotiroidisme/Hipertiroidisme', 'AIDS', 'Depresi', 'Menopause']], 'slki' => [['kode' => 'L.05046', 'nama' => 'Tingkat Keletihan', 'kriteria_hasil' => ['Kemampuan melakukan aktivita rutin dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi lelah dari skala 5 menurun menjadi skala 1 meningkat', 'Lesu dari skala 5 menurun menjadi skala 3 sedang']]], 'siki' => [['kode' => 'I.12362', 'nama' => 'Edukasi Aktivitas/Istirahat', 'definisi' => 'Mengajarkan pengaturan aktivitas dan istirahat', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pengaturan aktivitas dan istirahat', 'Jadwalkan pemberian pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan pada pien dan keluarga untuk bertanya'], 'edukasi' => ['Jelaskan pentingnya melakukan aktivitas fisik/olahraga secara rutin', 'Ajarkan cara mengidentifikasi kebutuhan istirahat (mis. kelelahan, sesak napas saat aktivitas)', 'Ajarkan cara mengidentifikasi target dan jenis aktivitas sesuai kemampuan'], 'kolaborasi' => []]]]]],

            // D.0058 — Kesiapan Peningkatan Tidur (updated from PDF p.290-293)
            ['diagkep_id' => 'D.0058', 'diagkep_desc' => 'Kesiapan Peningkatan Tidur', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Pola penurunan kesadaran alamiah dan periodik yang memungkinkan istirahat adekuat, mempertahankan gaya hidup yang diinginkan dan dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengekspresikan keinginan untuk meningkatkan tidur', 'Mengekspresikan perasaan cukup istrahat tidur'], 'objektif' => ['Jumlah waktu tidur sesuai dengan pertumbuhan']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak menggunakan obat tidur'], 'objektif' => ['Menerapkan rutinitas tidur yang meningkatkan kebiasaan tidur']], 'kondisi_klinis_terkait' => ['Pemulihan pasca operasi', 'Nyeri kronis', 'Kehamilan (periode prenatal/postnatal)', 'Sleep apnea']], 'slki' => [['kode' => 'L.05045', 'nama' => 'Pola Tidur', 'kriteria_hasil' => ['Kemampuan beraktivitas dari skala 1 dapat menurun menjadi skala 5 meningkat', 'Keluhan sulit tidur dari skala 1 dapat meningkat menjadi skala 5 menurun', 'Keluhan sering terjaga dari skala 1 dapat meningkat menjadi skala 5 menurun', 'Keluhan tidak puas tidur dari skala 1 dapat meningkat menjadi skala 5 menurun', 'Keluhan pola tidur berubah dari skala 1 dapat meningkat menjadi skala 5 menurun', 'Keluhan istirahat tidak cukup dari skala 1 dapat meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.13490', 'nama' => 'Promosi Keutuhan Keluarga', 'definisi' => 'Meningkatkan pengetahuan dan kemampuan untuk menjaga dan meningkatkan keutuhan keluarga', 'tindakan' => ['observasi' => ['Identifikasi pemahaman keluarga terhadap masalah', 'Identifikasi adanya konflik prioritas antar anggota keluarga', 'Identifikasi mekanisme koping keluarga', 'Monitor hubungan antar anggota keluarga'], 'terapeutik' => ['Hargai privasi keluarga', 'Fasilitasi kunjungan keluarga', 'Fasilitasi keluarga melakukan pengambilan keputusan dan pemecahan masalah', 'Fasilitasi komunikasi terbuka antar setiap anggota keluarga'], 'edukasi' => ['Informasikan kondisi pasien secara berkala kepada keluarga', 'Anjurkan anggota keluarga mempertahankan keharmonisan keluarga'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],

            // D.0059 — Risiko Disorganisasi Perilaku Bayi (updated from PDF p.293-294)
            ['diagkep_id' => 'D.0059', 'diagkep_desc' => 'Risiko Disorganisasi Perilaku Bayi', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Berisiko mengalami disintegrasi respon fisiologis dan Neurobehavior bayi terhadap lingkungan', 'penyebab' => [], 'faktor_risiko' => ['Kelebihan stimulasi sensorik', 'Prematuritas', 'Prosedur invasif', 'Gangguan motorik', 'Kelainan kongenital', 'Kelainan genetik'], 'kondisi_klinis_terkait' => ['Hospitalisasi', 'Prosedur invasif', 'Prematuritas', 'Gangguan neurologis', 'Gangguan pernafasan', 'Gangguan kardiovaskuler']], 'slki' => [['kode' => 'L.05043', 'nama' => 'Organisasi Perilaku Bayi', 'kriteria_hasil' => ['Gerakan pada ekstremitas dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan jari-jari menggenggam dari skala 1 menurun menjadi skala 5 meningkat', 'Gerakan terkoordinasi dari skala 1 menurun menjadi skala 5 meningkat', 'Gelisah dari skala 1 meningkat menjadi skala 5 menurun', 'Tremor dari skala 1 meningkat menjadi skala 5 menurun', 'Tersentak dari skala 1 meningkat menjadi skala 5 menurun', 'Aritmia dari skala 1 meningkat menjadi skala 5 menurun', 'Bradikardia dari skala 1 meningkat menjadi skala 5 menurun', 'Takikardia dari skala 1 meningkat menjadi skala 5 menurun', 'Kemampuan menyusu dari skala 1 memburuk menjadi 5 membaik', 'Warna kulit dari skala 1 memburuk menjadi 5 membaik', 'Menangis dari skala 1 memburuk menjadi 5 membaik', 'Mampu berespon kejut dari skala 1 memburuk menjadi 5 membaik', 'Irritabilitas dari skala 1 memburuk menjadi 5 membaik', 'Refleks dari skala 1 memburuk menjadi 5 membaik', 'Tonus Motorik dari skala 1 memburuk menjadi 5 membaik']]], 'siki' => [['kode' => 'I.12379', 'nama' => 'Edukasi Keamanan Bayi', 'definisi' => 'Menyediakan informasi dan dukungan terhadap pencegahan cedera pada bayi', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Anjurkan selalu mengawasi bayi agar selalu aman dan terjaga', 'Anjurkan tidak meninggalkan bayi sendirian agar bayi tidak mengalami kecelakaan yang tidak diinginkan', 'Anjurkan menjauhkan benda yang berisiko membahayakan bayi (mis. kantong plastik, karet, tali, kain, benda-benda kecil, benda tajam, pembersih lantai)', 'Anjurkan memasang penghalang pada sisi tempat tidur', 'Anjurkan menutup sumber listrik yang terjangkau oleh bayi', 'Anjurkan mengatur perabotan rumah tangga dirumah', 'Anjurkan memberikan pembatas pada area berisiko (mis. dapur, kamar mandi, kolam)', 'Anjurkan menggunakan kursi dan sabuk pengaman khusus bayi saat berkendara', 'Anjurkan penggunaan sabuk pengaman pada stroller (kursi dorong bayi), kursi khusus bayi dengan aman', 'Anjurkan tidak meletakkan bayi pada tempat tidur yang tinggi'], 'kolaborasi' => []]]]]],

            // D.0060 — Risiko Intoleransi Aktivitas (updated from PDF p.295-300)
            ['diagkep_id' => 'D.0060', 'diagkep_desc' => 'Risiko Intoleransi Aktivitas', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Aktivitas/Istirahat', 'definisi' => 'Berisiko mengalami ketidakcukupan energi untuk melakukan aktivitas sehari-hari', 'penyebab' => [], 'faktor_risiko' => ['Gangguan sirkulasi', 'Ketidakbugaran status fisik', 'Riwayat intoleransi aktivitas sebelumnya', 'Tidak berpengalaman dengan suatu aktivitas', 'Gangguan pernapasan'], 'kondisi_klinis_terkait' => ['Anemia', 'Gagal jantung kongestif', 'Penyakit katup jantung', 'Aritmia', 'Penyakit paru obstruktif kronis (PPOK)', 'Gangguan metabolik', 'Gangguan muskuloskeletal']], 'slki' => [['kode' => 'L.05047', 'nama' => 'Toleransi Aktivitas', 'kriteria_hasil' => ['Keluhan lelah dari skala 1 meningkat menjadi skala 5 menurun', 'Dispnea saat aktivitas dari skala 1 meningkat menjadi skala 5 menurun', 'Dispnea setelah aktivitas dari skala 1 meningkat menjadi skala 5 menurun', 'Frekuensi nadi dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.05178', 'nama' => 'Manajemen Energi', 'definisi' => 'Mengidentifikasi dan mengelola penggunaan energi untuk mengatasi atau mencegah kelelahan dan mengoptimalkan proses pemulihan', 'tindakan' => ['observasi' => ['Identifikasi gangguan fungsi tubuh yang mengakibatkan kelelahan', 'Monitor kelelahan fisik dan emosional', 'Monitor pola dan jam tidur', 'Monitor lokasi dan ketidaknyamanan selama melakukan aktivitas'], 'terapeutik' => ['Sediakan lingkungan nyaman dan rendah stimulus (mis. cahaya, suara, kunjungan)', 'Lakukan latihan rentang gerak pasif dan/atau aktif', 'Berikan aktivitas distraksi yang menenangkan', 'Fasilitasi duduk di sisi tempat tidur, jika tidak dapat berpindah atau berjalan'], 'edukasi' => ['Anjurkan tirah baring', 'Anjurkan melakukan aktivitas secara bertahap', 'Anjurkan menghubungi perawat jika tanda dan gejala kelelahan tidak berkurang', 'Ajarkan strategi koping untuk mengurangi kelelahan'], 'kolaborasi' => ['Kolaborasi dengan ahli gizi tentang cara meningkatkan asupan makanan']]], ['kode' => 'I.05186', 'nama' => 'Promosi Latihan Fisik', 'definisi' => 'Memfasilitasi aktifitas fisik reguler untuk mempertahankan atau meningkatkan ke tingkat kebugaran dan kesehatan yang lebih tinggi', 'tindakan' => ['observasi' => ['Identifikasi keyakinan kesehatan tentang latihan fisik', 'Identifikasi pengalaman latihan olahraga sebelumnya', 'Identifikasi motivasi individu untuk memulai atau melanjutkan program olahraga', 'Identifikasi hambatan untuk olahraga', 'Monitor kepatuhan menjalankan program latihan', 'Monitor respons terhadap program latihan'], 'terapeutik' => ['Motivasi mengungkapkan perasaan tentang olahraga/kebutuhan olahraga', 'Motivasi memulai atau melanjutkan olahraga', 'Fasilitasi dalam mengidentifikasi model peran positif untuk mempertahankan program latihan', 'Fasilitasi dalam mengembangkan program latihan yang sesuai untuk memenuhi kebutuhan', 'Fasilitasi dalam menetapkan tujuan jangka pendek dan panjang program latihan', 'Fasilitasi dalam menjadwalkan periode reguler latihan rutin mingguan', 'Fasilitasi dalam mempertahankan kemajuan program latihan', 'Lakukan aktivitas olahraga bersama pasien, jika perlu', 'Libatkan keluarga dalam merencanakan dan memelihara program latihan', 'Berikan umpan balik positif terhadap setiap upaya yang dijalankan pasien'], 'edukasi' => ['Jelaskan manfaat kesehatan dan efek fisiologis olahraga', 'Jelaskan jenis latihan yang sesuai dengan kondisi kesehatan', 'Jelaskan frekuensi, durasi dan intensitas program latihan yang diinginkan', 'Ajarkan latihan pemanasan dan pendinginan yang tepat', 'Ajarkan teknik menghindari cedera saat berolahraga', 'Ajarkan teknik pernapasan yang tepat untuk memaksimalkan penyerapan oksigen selama latihan fisik'], 'kolaborasi' => ['Kolaborasi dengan rehabilitasi medis atau ahli fisiologi olahraga, jika perlu']]]]]],


            // D.0061 — Disrefleksia Otonom (updated from PDF p.300-302)
            ['diagkep_id' => 'D.0061', 'diagkep_desc' => 'Disrefleksia Otonom', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Respon sistem saraf simpatis yang terjadi secara spontan dan berbahaya akibat cedera medula spinalis pada T7 atau diatasnya', 'penyebab' => ['fisiologis' => ['Cedera pada medula spinalis', 'Pembedahan medula spinalis T7 atau diatasnya', 'Proses keganasan pada medula spinalis']], 'gejala_tanda_mayor' => ['subjektif' => ['Sakit kepala'], 'objektif' => ['Tekanan darah sistolik meningkat >20%', 'Bercak merah pada kulit diatas lokasi cedera', 'Diaforesis diatas lokasi cedera', 'Pucat dibawah lokasi cedera', 'Bradikardia dan atau takikardia']], 'gejala_tanda_minor' => ['subjektif' => ['Nyeri dada', 'Pandangan kabur', 'Kongesti konjungtiva', 'Kongesti nasal', 'Parestesia', 'Sensasi logam di mulut'], 'objektif' => ['Menggigil', 'Sindrom horner', 'Refleks pilomotorik', 'Dilatasi pupil', 'Penile erection', 'Semen emission']], 'kondisi_klinis_terkait' => ['Cedera medula spinalis', 'Fraktur', 'Trombosis vena dalam']], 'slki' => [['kode' => 'L.06053', 'nama' => 'Status Neurologis', 'kriteria_hasil' => ['Tingkat kesadaran membaik', 'Reaksi pupil membaik', 'Sakit kepala membaik', 'Tekanan darah sistolik membaik', 'Frekuensi nadi membaik']]], 'siki' => [['kode' => 'I.06190', 'nama' => 'Manajemen Disrefleksia', 'definisi' => 'Mengidentifikasi dan mengelola refleks hiperaktif dan respon otonom yang tidak tepat pada Lesi servikal atau toraks', 'tindakan' => ['observasi' => ['Identifikasi rangsangan yang dapat memicu disrefleksia (mis. distensi kandung kemih, kalkuli ginjal, infeksi, impaksi feses, pemeriksaan rektal, supositoria, kerusakan kulit)', 'Identifikasi penyebab pemicu disrefleksia (mis. distensi kandung kemih, impaksi feses, lesi kulit, stoking suportif, dan pengikat perut)', 'Monitor tanda dan gejala disleksia otonom (mis. hipertensi paroksismal, bradikardia, takikardia, diaforesis di atas tingkat cedera, pucat di bawah tingkat cedera, sakit kepala, menggigil tanpa demam, ereksi pilomotor dan nyeri dada)', 'Monitor kepatenan kateter urin, jika terpasang', 'Monitor terjadinya hiperreflesia', 'Monitor tanda-tanda vital'], 'terapeutik' => ['Minimalkan rangsangan yang dapat memicu disrefleksia', 'Berikan posisi Fowler, jika perlu', 'Pasang kateter urin, jika perlu'], 'edukasi' => ['Jelaskan penyebab dan gejala disleksia', 'Jelaskan penanganan dan pencegahan disrefleksia', 'Anjurkan pasien dan atau keluarga jika mengalami tanda dan gejala disrefleksia'], 'kolaborasi' => ['Kolaborasi pemberian agen antihipertensi intravena, sesuai indikasi']]]]]],

            // D.0062 — Gangguan Memori (updated from PDF p.302-306)
            ['diagkep_id' => 'D.0062', 'diagkep_desc' => 'Gangguan Memori', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Ketidakmampuan mengingat beberapa informasi atau perilaku', 'penyebab' => ['fisiologis' => ['Ketidakadekuatan stimulasi intelektual', 'Gangguan sirkulasi ke otak', 'Gangguan volume cairan dan/atau elektrolit', 'Proses penuaan', 'Hipoksia', 'Gangguan neurologis (mis. EEG positif, cedera kepala, gangguan kejang)', 'Efek agen farmakologis', 'Penyalahgunaan zat', 'Faktor psikologis (mis. kecemasan, depresi, stress berlebihan, berduka, gangguan tidur)', 'Distraksi lingkungan']], 'gejala_tanda_mayor' => ['subjektif' => ['Melaporkan pernah mengalami pengalaman lupa', 'Tidak mampu mempelajari keterampilan baru', 'Tidak mampu mengingat informasi faktual', 'Tidak mampu mengingat perilaku tertentu yang pernah dilakukan', 'Tidak mampu mengingat peristiwa'], 'objektif' => ['Tidak mampu melakukan kemampuan yang dipelajari sebelumnya']], 'gejala_tanda_minor' => ['subjektif' => ['Lupa melakukan perilaku pada waktu yang telah dijadwalkan', 'Merasa mudah lupa'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Stroke', 'Cedera kepala', 'Kejang', 'Penyakit Alzheimer', 'Depresi', 'Intoksikasi alkohol']], 'slki' => [['kode' => 'L.09079', 'nama' => 'Memori', 'kriteria_hasil' => ['Verbalisasi kemampuan mempelajari hal baru meningkat', 'Verbalisasi kemampuan mengingat informasi faktual meningkat', 'Verbalisasi kemampuan mengingat perilaku tertentu yang pernah dilakukan meningkat', 'Verbalisasi kemampuan mengingat peristiwa meningkat', 'Verbalisasi pengalaman lupa menurun']]], 'siki' => [['kode' => 'I.06188', 'nama' => 'Latihan Memori', 'definisi' => 'Mengajarkan kemampuan untuk meningkatkan daya ingat', 'tindakan' => ['observasi' => ['Identifikasi masalah memori yang dialami', 'Identifikasi kesalahan terhadap orientasi', 'Monitor perilaku dan perubahan memori selama terapi'], 'terapeutik' => ['Rencanakan metode mengajar sesuai kemampuan pasien', 'Stimulasi memori dengan mengulang pikiran yang terakhir kali diucapkan, jika perlu', 'Koreksi kesalahan orientasi', 'Fasilitasi mengingat kembali pengalaman masa lalu, jika perlu', 'Fasilitasi tugas pembelajaran (mis. mengingat informasi verbal dan gambar)', 'Fasilitasi kemampuan konsentrasi (mis. bermain kartu pasangan) jika perlu', 'Stimulasi menggunakan memori pada peristiwa yang baru terjadi (mis. bertanya kemana saja ia pergi akhir-akhir ini), jika perlu'], 'edukasi' => ['Jelaskan tujuan dan prosedur latihan', 'Ajarkan Teknik memori yang tepat (mis. imajinasi visual, perangkat mnemonic, permainan memori, isyarat memori, teknik asosiasi, membuat daftar, komputer, papan nama)'], 'kolaborasi' => ['Rujuk pada terapi okupasi, jika perlu']]], ['kode' => 'I.09297', 'nama' => 'Orientasi Realita', 'definisi' => 'Meningkatkan kesadaran terhadap identitas diri, waktu, dan lingkungan', 'tindakan' => ['observasi' => ['Monitor perubahan orientasi', 'Monitor perubahan kognitif dan perilaku'], 'terapeutik' => ['Perkenalkan nama saat memulai interaksi', 'Orientasikan orang, tempat, dan waktu', 'Hadirkan realita (mis. beri penjelasan alternatif, hindari perdebatan)', 'Sediakan lingkungan dan rutinitas secara konsisten', 'Gunakan simbol dalam mengorientasikan lingkungan (mis. tanda, gambar, jam, kalender, dan kode warna pada lingkungan)', 'Libatkan dalam terapi kelompok orientasi', 'Berikan waktu istirahat dan tidur yang cukup', 'Fasilitasi akses informasi (mis. televisi, surat kabar, radio), jika perlu'], 'edukasi' => ['Anjurkan perawatan diri secara mandiri', 'Anjurkan penggunaan alat bantu (mis. kacamata, alat bantu dengar, dan gigi palsu)', 'Ajarkan keluarga dalam perawatan orientasi realita'], 'kolaborasi' => []]]]]],

            // D.0063 — Gangguan Menelan (updated from PDF p.306-310)
            ['diagkep_id' => 'D.0063', 'diagkep_desc' => 'Gangguan Menelan', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Respirasi', 'definisi' => 'Fungsi menelan abnormal akibat defisit struktur atau fungsi oral, faring atau esofagus', 'penyebab' => ['fisiologis' => ['Gangguan serebrovaskular', 'Gangguan saraf kranialis', 'Paralisis serebral', 'Akalasia', 'Abnormalitas laring', 'Abnormalitas orofaring', 'Anomali jalan napas atas', 'Defek anatomik kongenital', 'Defek laring', 'Defek nasal', 'Defek rongga nasofaring', 'Defek trakea', 'Refluks gastroesofagus', 'Obstruksi mekanis', 'Prematuritas']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh sulit menelan'], 'objektif' => ['Batuk sebelum menelan', 'Batuk setelah makan atau minum', 'Tersedak', 'Makanan tertinggal di rongga mulut']], 'gejala_tanda_minor' => ['subjektif' => ['Oral: Tidak tersedia', 'Faring: Menolak makan', 'Esofagus: Mengeluh bangun di malam hari, Nyeri epigastrik'], 'objektif' => ['Oral: Bolus masuk terlalu cepat, Refluks nasal, Tidak mampu membersihkan rongga mulut, Makanan jatuh dari mulut, Makanan terdorong keluar dari mulut, Sulit mengunyah, Muntah sebelum menelan, Bolus terbentuk lama, Waktu makan lama, Porsi makan tidak habis, Fase oral abnormal, Mengiler', 'Faring: Muntah, Posisi kepala kurang elevasi, Menelan berulang-ulang', 'Esofagus: Hematemesis, Gelisah, Regurgitasi, Odinofagia, Brukisme']], 'kondisi_klinis_terkait' => ['Stroke', 'Distrofi muskuler', 'Poliomielitis', 'Cerebral palsy', 'Penyakit Parkinson', 'Guillain Barre Syndrome', 'Myasthenia gravis', 'Amyotropic lateral sclerosis', 'Neoplasma otak', 'Kerusakan saraf kranialis V, VII, IX, X, XII', 'Esofagitis']], 'slki' => [['kode' => 'L.06052', 'nama' => 'Status Menelan', 'kriteria_hasil' => ['Mempertahankan makanan membaik', 'Reflek menelan membaik', 'Kemampuan mengosongkan mulut membaik', 'Frekuensi tersedak membaik', 'Batuk membaik']]], 'siki' => [['kode' => 'I.11351', 'nama' => 'Dukungan Perawatan Diri: Makan/Minum', 'definisi' => 'Memfasilitasi pemenuhan kebutuhan makan/minum', 'tindakan' => ['observasi' => ['Identifikasi diet yang dianjurkan', 'Monitor kemampuan menelan', 'Monitor status hidrasi pasien, jika perlu'], 'terapeutik' => ['Ciptakan lingkungan yang menyenangkan selama makan', 'Atur posisi yang nyaman untuk makan/minum', 'Lakukan oral hygiene sebelum makan, jika perlu', 'Letakkan makanan di sisi mata yang sehat', 'Sediakan sedotan untuk minum, sesuai kebutuhan', 'Siapkan makanan dengan suhu yang meningkatkan nafsu makan', 'Sediakan makanan dan minuman yang disukai', 'Berikan bantuan saat makan/minum sesuai tingkat kemandirian, jika perlu', 'Motivasi untuk makan di ruang makan, jika tersedia'], 'edukasi' => ['Jelaskan posisi makanan pada pasien yang mengalami gangguan penglihatan dengan menggunakan arah jarum jam (mis. sayur di jam 12, rendang di jam 3)'], 'kolaborasi' => ['Kolaborasi pemberian obat (mis. analgesik, antiemetik), sesuai indikasi']]]]]],

            // D.0064 — Konfusi Akut (updated from PDF p.310-312)
            ['diagkep_id' => 'D.0064', 'diagkep_desc' => 'Konfusi Akut', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Gangguan kesadaran, perhatian, kognitif dan persepsi yang reversible, berlangsung tiba-tiba dan singkat', 'penyebab' => ['fisiologis' => ['Delirium', 'Demensia', 'Fluktuasi siklus tidur-bangun', 'Usia lebih 60 tahun', 'Penyalahgunaan zat']], 'gejala_tanda_mayor' => ['subjektif' => ['Kurang motivasi untuk memulai/menyelesaikan perilaku', 'Kurang motivasi untuk menyelesaikan perilaku terarah'], 'objektif' => ['Fluktuasi fungsi kognitif', 'Fluktuasi tingkat kesadaran', 'Fluktuasi aktivitas psikomotor']], 'gejala_tanda_minor' => ['subjektif' => ['Salah persepsi'], 'objektif' => ['Halusinasi', 'Gelisah']], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Stroke', 'Penyakit Alzheimer', 'Penyalahgunaan zat', 'Demensia', 'Delirium']], 'slki' => [['kode' => 'L.06054', 'nama' => 'Tingkat Konfusi', 'kriteria_hasil' => ['Fungsi kognitif dari skala 2 meningkat menjadi 5', 'Tingkat kesadaran dari skala 2 meningkat menjadi 5', 'Aktivitas psikomotorik dari skala 2 meningkat menjadi 5', 'Motivasi memulai/menyelesaikan perilaku terarah dari skala 2 meningkat menjadi 5']]], 'siki' => [['kode' => 'I.06189', 'nama' => 'Manajemen Delirium', 'definisi' => 'Mengidentifikasi dan mengelola lingkungan terapeutik dan aman pada status konfusi akut', 'tindakan' => ['observasi' => ['Identifikasi factor resiko delirium (mis. usia >75 tahun, disfungsi kognitif, gangguan penglihatan/pendengaran, penurunan kemampuan fungsional, infeksi, hipo/hipertermia, hipoksia, malnutrisi, efek obat, toksin, gangguan tidur, stress)', 'Identifikasi tipe delirium (mis. Hipoaktif, hiperaktif, campuran)', 'Monitor status neurologis dan tingkat delirium'], 'terapeutik' => ['Berikan pencahayaan yang baik', 'Sediakan jam dan kalender yang mudah terbaca', 'Hindari stimulus sensorik berlebihan (mis. Televisi, pengumuman interkom)', 'Lakukan pengekangan fisik, sesuai indikasi', 'Sediakan informasi tentang apa yang terjadi dan apa yang dapat terjadi selanjutnya', 'Batasi pembuatan keputusan', 'Hindari memvalidasi mispersepsi atau interpretasi realita yang tidak akurat (mis. Halusinasi, waham)', 'Nyatakan persepsi dengan cara yang tenang, meyakinkan, dan tidak argumentatif', 'Fokus pada apa yang dikenali dan bermakna saat interaksi interpersonal', 'Lakukan reorientasi', 'Sediakan lingkungan fisik dan rutinitas harian yang konsisten', 'Gunakan isyarat lingkungan untuk stimulasi memori, reorientasi, dan meningkatkan perilaku yang sesuai (mis. tanda, gambar, jam, kalender, dan kode warna pada lingkungan)', 'Berikan informasi baru secara perlahan, sedikit demi sedikit, diulang-ulang'], 'edukasi' => ['Anjurkan kunjungan keluarga, jika perlu', 'Anjurkan penggunaan alat bantu sensorik (mis. Kacamata, alat bantu dengar, dan gigi palsu)'], 'kolaborasi' => ['Kolaborasi pemberian obat ansietas atau agitasi, jika perlu']]]]]],

            // D.0065 — Konfusi Kronis (updated from PDF p.312-314)
            ['diagkep_id' => 'D.0065', 'diagkep_desc' => 'Konfusi Kronis', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Gangguan kesadaran, perhatian, kognitif dan persepsi yang ireversibel, berlangsung lama, dan/atau progresif', 'penyebab' => ['fisiologis' => ['Cedera otak (mis. kerusakan serebrovaskular, penyakit neurologis, trauma, tumor)', 'Psikosis Korsakoff', 'Demensia multi infark']], 'gejala_tanda_mayor' => ['subjektif' => ['Kurang motivasi untuk memulai/menyelesaikan perilaku berorientasi tujuan', 'Kurang motivasi untuk memulai/menyelesaikan perilaku terarah'], 'objektif' => ['Fungsi kognitif berubah progresif', 'Memori jangka pendek dan/atau jangka panjang berubah', 'Interpretasi berubah', 'Fungsi sosial terganggu', 'Respon terhadap stimulus berubah']], 'gejala_tanda_minor' => ['subjektif' => ['Salah persepsi'], 'objektif' => ['Gangguan otak organik']], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Tumor otak', 'Stroke', 'Penyakit Alzheimer', 'Penyalahgunaan zat', 'Demensia multi infark']], 'slki' => [['kode' => 'L.06054', 'nama' => 'Tingkat Konfusi', 'kriteria_hasil' => ['Fungsi kognitif dari skala 1 meningkat menjadi skala 5', 'Tingkat kesadaran dari skala 1 meningkat menjadi skala 5', 'Aktivitas psikomotorik dari skala 1 meningkat menjadi skala 4 cukup meningkat', 'Motivasi memulai/menyelesaikan perilaku terarah dari skala 1 meningkat menjadi skala 5', 'Memori jangka pendek dari skala 1 meningkat menjadi skala 3 sedang', 'Memori jangka panjang dari skala 1 meningkat menjadi skala 4 cukup meningkat', 'Interpretasi dari skala 1 meningkat menjadi skala 5 membaik', 'Fungsi sosial dari skala 1 meningkat menjadi skala 5 membaik', 'Respon terhadap stimulus dari skala 1 meningkat menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.06189', 'nama' => 'Manajemen Delirium', 'definisi' => 'Mengidentifikasi dan mengelola lingkungan terapeutik dan aman pada status konfusi akut', 'tindakan' => ['observasi' => ['Identifikasi factor resiko delirium (mis. usia >75 tahun, disfungsi kognitif, gangguan penglihatan/pendengaran, penurunan kemampuan fungsional, infeksi, hipo/hipertermia, hipoksia, malnutrisi, efek obat, toksin, gangguan tidur, stress)', 'Identifikasi tipe delirium (mis. Hipoaktif, hiperaktif, campuran)', 'Monitor status neurologis dan tingkat delirium'], 'terapeutik' => ['Berikan pencahayaan yang baik', 'Sediakan jam dan kalender yang mudah terbaca', 'Hindari stimulus sensorik berlebihan (mis. Televisi, pengumuman interkom)', 'Lakukan pengekangan fisik, sesuai indikasi', 'Sediakan informasi tentang apa yang terjadi dan apa yang dapat terjadi selanjutnya', 'Batasi pembuatan keputusan', 'Hindari memvalidasi mispersepsi atau interpretasi realita yang tidak akurat (mis. Halusinasi, waham)', 'Nyatakan persepsi dengan cara yang tenang, meyakinkan, dan tidak argumentatif', 'Fokus pada apa yang dikenali dan bermakna saat interaksi interpersonal', 'Lakukan reorientasi', 'Sediakan lingkungan fisik dan rutinitas harian yang konsisten', 'Gunakan isyarat lingkungan untuk stimulasi memori, reorientasi, dan meningkatkan perilaku yang sesuai (mis. tanda, gambar, jam, kalender, dan kode warna pada lingkungan)', 'Berikan informasi baru secara perlahan, sedikit demi sedikit, diulang-ulang'], 'edukasi' => ['Anjurkan kunjungan keluarga, jika perlu', 'Anjurkan penggunaan alat bantu sensorik (mis. Kacamata, alat bantu dengar, dan gigi palsu)'], 'kolaborasi' => ['Kolaborasi pemberian obat ansietas atau agitasi, jika perlu']]]]]],

            // D.0066 — Penurunan Kapasitas Adaptif Intrakranial (updated from PDF p.314-318)
            ['diagkep_id' => 'D.0066', 'diagkep_desc' => 'Penurunan Kapasitas Adaptif Intrakranial', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Gangguan mekanisme dinamika intrakaranial dalam melakukan kompensasi terhadap stimulus yang dapat menurunkan kapasitas intrakanial', 'penyebab' => ['fisiologis' => ['Lesi menempati ruang (misalnya space occupaying lesion - akibat tumor, abses)', 'Gangguan metabolisme (misalnya akibat hiponatremia, ensefalopati uremikum, ensefalopati hepatikum, ketoasidosis diabetik, septikemia)', 'Edema serebral (misalnya akibat cedera kepala: hematoma epidural, hematoma subdural, hematoma subarachnoid, hematoma intraserebral, stroke iskemik, stroke hemoragik, hipoksia, ensefalopati iskemik)', 'Peningkatan tekanan vena (misalnya akibat trombosis sinus vena serebral, gagal jantung trombosit/obstruksi vena jugularis atau vena kava superior)', 'Obstruksi aliran cairan cerebrospinalis (misalnya hidrosefalus)', 'Hipertensi intrakanial idiopatik']], 'gejala_tanda_mayor' => ['subjektif' => ['Sakit kepala'], 'objektif' => ['Tekanan darah meningkat dengan tekanan nadi (pulse pressure) melebar', 'Bradikardia', 'Pola nafas irreguler', 'Tingkat kesadaran menurun', 'Respon pupil melambat atau tidak sama', 'Refleks neurologis terganggu']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Gelisah', 'Agitasi', 'Muntah (tanpa disertai mual)', 'Tampak lesu lemah', 'Fungsi kognitif terganggu', 'Tekanan intrakranial TIK >= 20 mmHg', 'Papiledema', 'Postur deserebrasi (ekstensi)']], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Iskemik serebral', 'Tumor serebral', 'Hidrosefalus', 'Hematoma kranial', 'Pembentukan arteriovenous', 'Edema vasogenik atau sitotoksik serebral', 'Hiperemia', 'Obstruksi aliran vena']], 'slki' => [['kode' => 'L.06049', 'nama' => 'Kapasitas Adaptif Intrakranial', 'kriteria_hasil' => ['Tingkat kesadaran dan fungsi kognitif dari skala 1 dapat meningkat menjadi skala 5', 'Sakit kepala, bradikardi, gelisah, agitasi, muntah, postur deserebrasi dan papiledema dari skala 1 menurun menjadi skala 5', 'Tekanan darah, tekanan nadi, pola napas, respon pupil, refleks neurologi dan tekanan intrakranial dari memburuk/skala 1 menjadi membaik/skala 5']]], 'siki' => [['kode' => 'I.06194', 'nama' => 'Manajemen Peningkatan Tekanan Intrakranial', 'definisi' => 'Mengidentifikasi dan mengelola peningkatan tekanan dalam rongga kranial', 'tindakan' => ['observasi' => ['Identifikasi penyebab peningkatan TIK (misalnya lesi, gangguan metabolisme, edema serebral)', 'Monitor tanda gejala peningkatan TIK (misalnya tekanan darah meningkat, tekanan nadi melebar, bradikardi, pola nafas irreguler, kesadaran menurun)', 'Monitor MAP (Mean Arterial Pressure)', 'Monitor CVP (Central Venous Pressure), jika perlu', 'Monitor PAWP, jika perlu', 'Monitor PAP, jika perlu', 'Monitor ICP (Intra Cranial Pressure), jika tersedia', 'Monitor CPP (Cerebral Perfusion Pressure)', 'Monitor gelombang ICP', 'Monitor status pernapasan', 'Monitor intake dan output cairan', 'Monitor cairan serebro-spinalis (misalnya warna, konsistensi)'], 'terapeutik' => ['Minimalkan stimulus dengan menyediakan lingkungan yang tenang', 'Berikan posisi semi fowler', 'Hindari manuver valsava', 'Cegah terjadinya kejang', 'Hindari penggunaan PEEP', 'Hindari pemberian cairan IV hipotonik', 'Atur ventilator agar PaCO2 optimal', 'Pertahankan suhu tubuh normal'], 'edukasi' => ['Jelaskan tujuan dan prosedur kepada keluarga/pasien'], 'kolaborasi' => ['Kolaborasi pemberian sedasi dan anti konvulsan, jika perlu', 'Kolaborasi pemberian diuretik osmosis, jika perlu', 'Kolaborasi pemberian pelunak tinja, jika perlu']]], ['kode' => 'I.06198', 'nama' => 'Pemantauan Tekanan Intrakranial', 'definisi' => 'Mengumpulkan dan menganalisis data terkait regulasi tekanan intrakranial', 'tindakan' => ['observasi' => ['Identifikasi penyebab peningkatan TIK', 'Monitor peningkatan tekanan darah', 'Monitor pelebaran tekanan nadi', 'Monitor penurunan frekuensi jantung', 'Monitor ireguleritas irama napas', 'Monitor penurunan tingkat kesadaran', 'Monitor perlambatan atau ketidaksimetrisan respon pupil', 'Monitor tekanan perfusi serebral', 'Monitor jumlah, kecepatan, dan karakteristik drainase cairan serebrospinalis', 'Monitor efek stimulus lingkungan terhadap TIK'], 'terapeutik' => ['Ambil sampel drainase cairan serebrospinalis', 'Kalibrasi transduser', 'Pertahankan sterilitas sistem pemantauan', 'Pertahankan posisi kepala dan leher netral', 'Dokumentasikan hasil pemantauan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan', 'Informasikan hasil pemantauan, jika perlu'], 'kolaborasi' => []]]]]],

            // D.0067 — Risiko Disfungsi Neurovaskuler Perifer (updated from PDF p.318-319)
            ['diagkep_id' => 'D.0067', 'diagkep_desc' => 'Risiko Disfungsi Neurovaskuler Perifer', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Berisiko mengalami gangguan sirkulasi, sensasi dan pergerakan pada ekstermitas', 'penyebab' => [], 'faktor_risiko' => ['Hiperglikemia', 'Obstruksi vaskuler', 'Fraktur', 'Imobilisasi', 'Penekanan mekanis (mis. torniket, gips, balutan, restraint)', 'Pembedahan ortopedi', 'Trauma', 'Luka bakar'], 'kondisi_klinis_terkait' => ['Diabetes melitus', 'Obstruksi vaskuler', 'Fraktur', 'Pembedahan ortopedi', 'Trauma', 'Luka bakar']], 'slki' => [['kode' => 'L.06051', 'nama' => 'Neurovaskuler Perifer', 'kriteria_hasil' => ['Sirkulasi arteri dari menurun skala 1 menjadi meningkat skala 5', 'Sirkulasi vena dari menurun skala 1 menjadi meningkat skala 5', 'Pergerakan sendi dari menurun skala 1 menjadi meningkat skala 5', 'Pergerakan ekstremitas dari menurun skala 1 menjadi meningkat skala 5', 'Nyeri dari meningkat skala 1 menjadi menurun skala 5', 'Perdarahan dari meningkat skala 1 menjadi menurun skala 5', 'Luka tekan dari meningkat skala 1 menjadi menurun skala 5', 'Frekuensi nadi dari memburuk skala 1 menjadi membaik skala 5', 'Suhu tubuh dari memburuk skala 1 menjadi membaik skala 5']]], 'siki' => [['kode' => 'I.06195', 'nama' => 'Manajemen Sensasi Perifer', 'definisi' => 'Mengidentifikasi dan mengelola ketidaknyamanan pada perubahan sensasi perifer', 'tindakan' => ['observasi' => ['Identifikasi penyebab perubahan sensasi', 'Identifikasi penggunaan alat pengikat, prostesis, sepatu, dan pakaian', 'Periksa perbedaan sensasi tajam atau tumpul', 'Periksa perbedaan sensasi panas atau dingin', 'Periksa kemampuan mengidentifikasi lokasi dan tekstur benda', 'Monitor terjadinya parestesia, jika perlu', 'Monitor perubahan kulit', 'Monitor adanya tromboflebitis dan tromboemboli vena'], 'terapeutik' => ['Hindari pemakaian benda-benda yang berlebihan suhunya (terlalu panas atau dingin)'], 'edukasi' => ['Anjurkan penggunaan termometer untuk menguji suhu air', 'Anjurkan penggunaan sarung tangan termal saat memasak', 'Anjurkan memakai sepatu lembut dan bertumit rendah'], 'kolaborasi' => ['Kolaborasi pemberian analgesik, jika perlu', 'Kolaborasi pemberian kortikosteroid, jika perlu']]]]]],

            // D.0068 — Risiko Konfusi Akut (updated from PDF p.327-329)
            ['diagkep_id' => 'D.0068', 'diagkep_desc' => 'Risiko Konfusi Akut', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Neurosensori', 'definisi' => 'Beresiko mengalami gangguan kesadaran, perhatian, kognisi dan persepsi yang revensibel dan terjadi dalam periode waktu singkat', 'penyebab' => [], 'faktor_risiko' => ['Usia di atas 60 tahun', 'Perubahan fungsi kongnitif', 'Perubahan siklus tidur-bangun', 'Dehidrasi', 'Demensia', 'Riwayat stroke', 'Gangguan fungsi metabolik (mis. azotemia, penurunan hemoglobin, ketidakseimbangan elektrolit, peningkatan nitrogen urea darah [BUN]/kreatinin)', 'Gangguan mobilitas', 'Penggunaan restraint yang tidak tepat', 'Infeksi', 'Malnutrisi', 'Nyeri', 'Efek agen farmakologis', 'Deprivasi sensori', 'Penyalahgunaan zat'], 'kondisi_klinis_terkait' => ['Cedera kepala', 'Stroke', 'Penyakit Alzheimer', 'Penyalahgunaan zat', 'Demensia', 'Delirium']], 'slki' => [['kode' => 'L.06054', 'nama' => 'Tingkat Konfusi', 'kriteria_hasil' => ['Fungsi kogitif dari skala 1 menurun menjadi skala sedang', 'Tingkat kesadaran dari skala 2 cukup menurun menjadi skala 4 cukup meningkat', 'Respon terhadap stimulus dari skala 1 memburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.08238', 'nama' => 'Manajemen Nyeri', 'definisi' => 'Mengidentifikasi dan mengelola pengalaman sensorik atau emosional yang berkaitan dengan kerusakan jaringan atau fungsional dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan', 'tindakan' => ['observasi' => ['Identifikasi lokasi, karakteristik, durasi, frekuensi, kualitas, intensitas nyeri', 'Identifikasi skala nyeri', 'Identifikasi respon nyeri non verbal', 'Identifikasi faktor yang memperberat dan memperingan nyeri'], 'terapeutik' => ['Berikan teknik nonfarmakologis untuk mengurangi rasa nyeri (mis. TENS, hipnosis, akupresur, terapi musik, biofeedback, terapi pijat, aromaterapi, teknik imajinasi terbimbing, kompres hangat/dingin, terapi bermain)', 'Kontrol lingkungan yang memperberat rasa nyeri (mis. suhu ruangan, pencahayaan, kebisingan)'], 'edukasi' => ['Jelaskan penyebab, periode, dan pemicu nyeri', 'Jelaskan strategi meredakan nyeri', 'Anjurkan memonitor nyeri secara mandiri', 'Ajarkan teknik nonfarmakologis untuk mengurangi rasa nyeri'], 'kolaborasi' => ['Kolaborasi pemberian analgetik, jika perlu']]]]]],

            // D.0069 — Disfungsi Seksual (updated from PDF p.329-332)
            ['diagkep_id' => 'D.0069', 'diagkep_desc' => 'Disfungsi Seksual', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Reproduksi dan Seksualitas', 'definisi' => 'Perubahan fungsi seksual selama fase respon seksual berupa hasrat, terangsang, orgasme, dan/atau relaksasi yang dirasa tidak memuaskan, tidak bermakna atau tidak adekuat', 'penyebab' => ['fisiologis' => ['Perubahan fungsi/sturktur tubuh (mis. kehamilan, baru melahirkan, obat-obatan, pembedahan, anomali, proses penyakit, trauma, radiasi)', 'Perubahan biopskisosial seksualitas', 'Ketiadaan model peran', 'Model peran tidak dapat mempengaruhi', 'Kurang privasi', 'Ketiadaan pasangan', 'Kesalahan informasi', 'Kelainan seksualitas (mis. hubungan penuh kekerasan)', 'Konflik nilai', 'Penganiyaan fisik (mis. Kekerasan dalam rumah tangga)', 'Kurang terpapar informasi']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan aktivitas seksual berubah', 'Mengungkapkan eksitasi seksual berubah', 'Merasa hubungan seksual tidak memusakan', 'Mengungkapkan peran seksual berubah', 'Mengeluhkan hasrat seksual menurun', 'Mengungkapkan fungsi seksual berubah', 'Mengeluh nyeri saat berhubungan seksual (dispareunia)'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Mengungkapkan ketertarikan pasangan berubah', 'Mengeluh hubungan seksual terbatas', 'Mencari informasi tentang kemampuan mencapai kepuasan seksual'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Gangguan endokrin, perkemihan, neuromuskuler, muskuloskeletas, kardiovaskuler', 'Trauma genital', 'Pembedahan pelvis', 'Kanker', 'Menopause', 'Gangguan psikiatrik seperti mania, depresi berat, demensia, gangguan kepribadian, penyalahgunaan atau pengunaan zat, gangguan kecemasan, dan schizophrenia']], 'slki' => [['kode' => 'L.07055', 'nama' => 'Fungsi Seksual', 'kriteria_hasil' => ['Kepuasan hubungan seksual dari skala 2 (cukup menurun) menjadi skala 4 (cukup meningkat)', 'Verbalisasi aktivitas seksual berubah dari skala 5 (menurun) menjadi skala 1 (meningkat)', 'Verbalisasi eksitasi peran seksual berubah dari skala 5 (menurun) menjadi skala 1 (meningkat)', 'Verbalisasi peran seksual berubah dari skala 5 (menurun) menjadi skala 1 (meningkat)', 'Verbalisasi fungsi seksual berubah dari skala 5 (menurun) menjadi skala 1 (meningkat)', 'Keluhan nyeri saat berhubungan seksual (dispareunia) dari skala 5 (menurun) menjadi skala 1 (meningkat)']]], 'siki' => [['kode' => 'I.07214', 'nama' => 'Konseling Seksualitas', 'definisi' => 'Memberikan bimbingan seksual pada pasangan sehingga mampu menjalankan fungsinya secara optimal', 'tindakan' => ['observasi' => ['Identifikasi waktu disfungsi seksual dan kemungkinan penyebab', 'Monitor stress, kecemasan, depresi dan penyebab disfungsi seksual'], 'terapeutik' => ['Berikan kesempatan kepada pasangan untuk menceritakan permasalahan seksual', 'Berikan saran yang sesuai kebutuhan pasangan dengan menggunakan bahasa yang mudah diterima, dipahami dan tidak menghakimi'], 'edukasi' => ['Jelaskan efek pengobatan, kesehatan dan penyakit terhadap disfungsi seksual'], 'kolaborasi' => ['Kolaborasi dengan spesialis seksologi, jika perlu']]]]]],

            // D.0070 — Kesiapan Persalinan (updated from PDF p.333-334)
            ['diagkep_id' => 'D.0070', 'diagkep_desc' => 'Kesiapan Persalinan', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Reproduksi dan Seksualitas', 'definisi' => 'Pola mempersiapkan, mempertahankan dan memperkuat proses kehamilan dan persalinan serta perawatan bayi baru lahir', 'gejala_tanda_mayor' => ['subjektif' => ['Menyatakan keinginan untuk menerapkan gaya hidup yang tepat untuk persalinan', 'Menyatakan keinginan untuk menerapkan penatalaksanaan gejala ketidaknyamanan selama persalinan', 'Menyatakan rasa percaya diri menjalani persalinan'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Menunjukkan perilaku proaktif selama persiapan persalinan']], 'kondisi_klinis_terkait' => ['Status kesehatan ibu sehat', 'Status kesehatan janin sehat']], 'slki' => [['kode' => 'L.07059', 'nama' => 'Status Antepartum', 'kriteria_hasil' => ['Nausea membaik', 'Muntah membaik', 'Edema membaik', 'Nyeri abdomen membaik', 'Nyeri epigastrik membaik', 'Perdarahan vagina membaik', 'Konstipasi membaik']]], 'siki' => [['kode' => 'I.12437', 'nama' => 'Edukasi Persalinan', 'definisi' => 'Memberikan informasi tentang proses persalinan', 'tindakan' => ['observasi' => ['Identifikasi tingkat pengetahuan', 'Identifikasi pemahaman ibu tentang persalinan'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya', 'Berikan reinforcement positif terhadap perubahan perilaku ibu'], 'edukasi' => ['Jelaskan metode persalinan yang ibu inginkan', 'Jelaskan persiapan dan tempat persalinan', 'Anjurkan ibu mengikuti kelas ibu hamil pada usia kehamilan lebih dari 36 minggu', 'Anjurkan ibu menggunakan teknik manajemen nyeri persalinan tiap kala', 'Anjurkan ibu cukup nutrisi', 'Ajarkan teknik relaksasi untuk meredakan kecemasan dan ketidaknyamanan persalinan', 'Ajarkan ibu cara mengenali tanda-tanda persalinan', 'Ajarkan ibu mengenali tanda bahaya persalinan'], 'kolaborasi' => []]]]]],

            // D.0071 — Pola Seksual Tidak Efektif (updated from PDF p.335)
            ['diagkep_id' => 'D.0071', 'diagkep_desc' => 'Pola Seksual Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Reproduksi dan Seksualitas', 'definisi' => 'Kekhawatiran individu melakukan hubungan seksual yang beresiko menyebabkan perubahan kesehatan', 'penyebab' => ['fisiologis' => ['Kurang privasi', 'Ketiadaan pasangan', 'Konflik orientasi seksual', 'Ketakutan hamil', 'Ketakutan terinfeksi penyakit menular seksual', 'Hambatan hubungan dengan pasangan', 'Kurang terpapar informasi tentang seksualitas']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh sulit melakukan aktivitas seksual'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Gangguan endokrin, perkemihan, neuromuskuler, muskuloskeletas, kardiovaskuler', 'Trauma genital', 'Pembedahan pelvis', 'Kanker', 'Menopause', 'Gangguan psikiatrik']], 'slki' => [['kode' => 'L.07056', 'nama' => 'Identitas Seksual', 'kriteria_hasil' => ['Menunjukkan pendirian seksual yang jelas (4)', 'Integrasi orientasi seksual ke dalam kehidupan sehari-hari (4)', 'Menyusun batasan-batasan sesuai jenis kelamin (4)']]], 'siki' => [['kode' => 'I.12447', 'nama' => 'Edukasi Seksualitas', 'definisi' => 'Memberikan informasi dalam memahami dimensi fisik dan psikososial seksualitas', 'tindakan' => ['observasi' => ['Identifikasi kesiapan menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan anatomi dan fisiologi system reproduksi laki-laki dan perempuan', 'Jelaskan perkembangan emosi anak dan remaja', 'Jelaskan konsekuensi negatif mengasuh anak pada usia dini (mis. kemiskinan, kehilangan pendidikan)'], 'kolaborasi' => []]]]]],

            // D.0072 — Risiko Disfungsi Seksual (updated from PDF p.349-351)
            ['diagkep_id' => 'D.0072', 'diagkep_desc' => 'Risiko Disfungsi Seksual', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Reproduksi dan Seksualitas', 'definisi' => 'Beresiko mengalami perubahan fungsi seksual selama fase respon seksual berupa hasrat, terangsang orgasme dan relaksasi yang dipandang tidak memuaskan, tidak bermakna tidak adekuat', 'penyebab' => [], 'faktor_risiko' => ['Gangguan neurologi', 'Gangguan urologi', 'Gangguan endokrin', 'Keganasan', 'Faktor ginekologi (mis. kehamilan, pasca persalinan)', 'Efek agen farmakologis', 'Depresi', 'Kecemasan', 'Penganiayaan psikologis/seksual', 'Penyalahgunaan obat/zat', 'Konflik hubungan', 'Kurangnya privasi', 'Pola seksual pasangan menyimpang', 'Ketiadaan pasangan', 'Ketidakadekuatan edukasi', 'Konflik nilai personal dalam keluarga, budaya dan agama'], 'kondisi_klinis_terkait' => ['Diabetes melitus', 'Penyakit jantung (mis. hipertensi, penyakit jantung koroner)', 'Penyakit paru (mis. TB, PPOK, asma)', 'Stroke', 'Kehamilan', 'Kanker', 'Gangguan endokrin, perkemihan, neuromuskuler, muskuloskeletal, kardiovaskuler', 'Trauma genital', 'Pembedahan pelvis', 'Menopause', 'Gangguan psikiatrik seperti mania, depresi berat, demensia, gangguan kepribadian, penyalahgunaan atau pengunaan zat, gangguan kecemasan, dan skizofrenia']], 'slki' => [['kode' => 'L.07005', 'nama' => 'Fungsi Seksual', 'kriteria_hasil' => ['Kepuasan hubungan seksual membaik', 'Mencari informasi untuk mencapai kepuasan seksual membaik', 'Verbalisasi aktivitas seksual berubah membaik', 'Verbalisasi eksitasi seksual berubah membaik', 'Verbalisasi peran seksual berubah membaik', 'Verbalisasi fungsi seksual berubah membaik', 'Keluhan nyeri saat berhubungan seksual (dispareunia) membaik', 'Keluhan hubungan seksual terbatas membaik', 'Keluhan sulit melakukan hubungan seksual membaik', 'Verbalisasi aktivitas seksual berubah membaik', 'Verbalisasi perilaku seksual berubah membaik', 'Konflik nilai membaik', 'Hasrat seksual membaik', 'Orientasi seksual membaik', 'Ketertarikan pada pasangan membaik']]], 'siki' => [['kode' => 'I.12447', 'nama' => 'Edukasi Seksualitas', 'definisi' => 'Memberikan informasi dalam memahami dimensi fisik dan psikososial seksualitas', 'tindakan' => ['observasi' => ['Identifikasi kesiapan informasi dalam memahami menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya', 'Fasilitasi kesadaran keluarga terhadap anak dan remaja serta pengaruh media'], 'edukasi' => ['Jelaskan anatomi dan fisiologi system reproduksi laki-laki dan perempuan', 'Jelaskan perkembangan sesualitas sepanjang siklus kehidupan', 'Jelaskan perkembangan emosi masa anak dan remaja', 'Jelaskan pengaruh tekanan kelompok dan sosial terhadap aktivitas seksual', 'Jelaskan konsekuensi negatif mengasuh anak pada usia dini (mis. kemiskinan, kehilangan karir dan pendidikan)', 'Jelaskan risiko tertular penyakit menular seksual dan AIDS akibat seks bebas', 'Anjurkan orang tua menjadi edukator seksualitas bagi anak-anaknya', 'Anjurkan anak/remaja tidak melakukan seksual di luar nikah', 'Ajarkan keterampilan komunikasi asertif untuk menolak tekanan teman sebaya dan sosial dalam aktivitas seksual'], 'kolaborasi' => []]]]]],

            // D.0073 — Risiko Kehamilan Tidak Dikehendaki (updated from PDF p.351-355)
            ['diagkep_id' => 'D.0073', 'diagkep_desc' => 'Risiko Kehamilan Tidak Dikehendaki', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Reproduksi dan Seksualitas', 'definisi' => 'Beresiko mengalami kehamilan yang tidak diharapkan baik karena alasan waktu yang tidak tepat atau karena kehamilan tidak diinginkan', 'penyebab' => [], 'faktor_risiko' => ['Pemerkosaan', 'Hubungan seksual sedarah (incest)', 'Gangguan jiwa', 'Kegagalan penggunaan alat kontrasepsi', 'Kekerasan dalam rumah tangga (KDRT)', 'Tidak menggunakan alat kontrasepsi', 'Faktor sosial-ekonomi'], 'kondisi_klinis_terkait' => ['Penyakit menular seksual', 'Gangguan jiwa', 'Kegagalan penggunaan alat kontrasepsi', 'Kekerasan dalam rumah tangga']], 'slki' => [['kode' => 'L.07057', 'nama' => 'Penerimaan Kehamilan', 'kriteria_hasil' => ['Verbalisasi penerimaan kehamilan meningkat dengan skala 5', 'Verbalisasi perasaan yang dialami meningkat dengan skala 5', 'Perilaku mencari perawatan kehamilan meningkat dengan skala 5', 'Menyusun perencanaan kehamilan meningkat dengan skala 5']]], 'siki' => [['kode' => 'I.12381', 'nama' => 'Edukasi Keluarga Berencana', 'definisi' => 'Memberikan informasi dan memfasilitasi ibu dan pasangan dalam penggunaan alat kontrasepsi untuk mengatur jarak kelahiran', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi pengetahuan tentang alat kontrasepsi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya', 'Lakukan pemeriksaan fisik', 'Fasilitasi ibu dan pasangan dalam mengambil keputusan dalam menggunakan alat kontrasepsi', 'Diskusikan pertimbangan agama, budaya, perkembangan, sosial ekonomi terhadap pemilihan alat kontrasepsi'], 'edukasi' => ['Jelaskan tentang sistem reproduksi', 'Jelaskan metode-metode alat kontrasepsi', 'Jelaskan aktivitas seksualitas setelah mengikuti program KB'], 'kolaborasi' => []]], ['kode' => 'I.07216', 'nama' => 'Manajemen Kehamilan Tidak Dikehendaki', 'definisi' => 'Mengidentifikasi dan mengelola pengambilan keputusan terhadap kehamilan yang tidak di rencanakan', 'tindakan' => ['observasi' => ['Identifikasi nilai-nilai dan keyakinan terhadap kehamilan', 'Identifikasi pilihan terhadap kehamilannya'], 'terapeutik' => ['Fasilitasi mengungkapkan perasaan', 'Diskusikan nilai-nilai dan keyakinan yang keliru terhadap kehamilan', 'Diskusikan konflik yang terjadi dengan adanya kehamilan', 'Fasilitasi mengembangkan teknik penyelesaian masalah', 'Berikan konseling kehamilan', 'Fasilitasi mengidentifikasi sistem pendukung'], 'edukasi' => ['Informasikan pentingnya meningkatkan status nutrisi selama kehamilan', 'Informasikan perubahan yang terjadi selama kehamilan'], 'kolaborasi' => ['Rujuk jika mengalami komplikasi kehamilan']]]]]],

            // D.0074 — Gangguan Rasa Nyaman (updated from PDF p.356-368)
            ['diagkep_id' => 'D.0074', 'diagkep_desc' => 'Gangguan Rasa Nyaman', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Perasaan kurang senang, lega dan sempurna dalam dimensi fisik, psikospiritual, lingkungan dan sosial', 'penyebab' => ['fisiologis' => ['Gejala penyakit', 'Kurang pengendalian situasional/lingkungan', 'Ketidakadekuatan sumber daya (mis. dukungan finansial, sosial, dan pengetahuan)', 'Kurangnya privasi', 'Gangguan stimulus lingkungan', 'Efek samping terapi', 'Gangguan adaptasi kehamilan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh tidak nyaman'], 'objektif' => ['Gelisah']], 'gejala_tanda_minor' => ['subjektif' => ['Mengeluh sulit tidur', 'Tidak mampu rileks', 'Mengeluh kedinginan/kepanasan', 'Merasa gatal', 'Mengeluh mual', 'Mengeluh lelah'], 'objektif' => ['Menunjukkan gejala distress', 'Tampak merintih/menangis', 'Pola eliminasi berubah', 'Postur tubuh berubah', 'Iritabilitas']], 'kondisi_klinis_terkait' => ['Penyakit kronis', 'Keganasan', 'Distres psikologis', 'Kehamilan']], 'slki' => [['kode' => 'L.08064', 'nama' => 'Status Kenyamanan', 'kriteria_hasil' => ['Keluhan tidak nyaman dari skala 1 (meningkat) menjadi skala 5 (menurun)']]], 'siki' => [['kode' => 'I.01019', 'nama' => 'Pengaturan Posisi', 'definisi' => 'Menempatkan bagian tubuh untuk meningkatkan kesehatan fisiologis dan/atau psikologis', 'tindakan' => ['observasi' => ['Monitor status oksigenasi sebelum dan sesudah mengubah posisi', 'Monitor alat traksi agar selalu tepat'], 'terapeutik' => ['Tempatkan pada matras/tempat tidur terapeutik yang tepat', 'Tempatkan pada posisi terapeutik', 'Tempatkan objek yang sering digunakan dalam jangkauan', 'Berikan bantal yang tepat pada leher', 'Hindari menempatkan pada posisi yang dapat meningkatkan nyeri', 'Ubah posisi pada setiap dua jam'], 'edukasi' => ['Informasikan saat akan dilakukan perubahan posisi', 'Ajarkan cara menggunakan postur yang baik dan mekanika tubuh yang baik selama melakukan perubahan posisi'], 'kolaborasi' => ['Kolaborasi pemberian premedikasi sebelum mengubah posisi, jika perlu']]], ['kode' => 'I.09326', 'nama' => 'Terapi Relaksasi', 'definisi' => 'Menggunakan teknik peregangan untuk mengurangi tanda dan gejala ketidaknyamanan seperti nyeri, ketegangan otot, atau kecemasan', 'tindakan' => ['observasi' => ['Identifikasi penurunan tingkat energi, ketidakmampuan berkonsentrasi, atau gejala lain yang mengganggu kemampuan kognitif', 'Identifikasi teknik relaksasi yang pernah efektif digunakan', 'Identifikasi kesediaan, kemampuan, dan penggunaan teknik sebelumnya', 'Periksa ketegangan otot, frekuensi nadi, tekanan darah, dan suhu sebelum dan sesudah latihan', 'Monitor respons terhadap terapi relaksasi'], 'terapeutik' => ['Ciptakan lingkungan tenang dan tanpa gangguan dengan pencahayaan dan suhu ruang nyaman, jika memungkinkan', 'Berikan informasi tertulis tentang persiapan dan prosedur teknik relaksasi', 'Gunakan pakaian longgar', 'Gunakan nada suara lembut dengan irama lambat dan berirama', 'Gunakan relaksasi sebagai strategi penunjang dengan analgetik atau tindakan medis lain, jika sesuai'], 'edukasi' => ['Jelaskan tujuan, manfaat, batasan, dan jenis relaksasi yang tersedia (mis. musik, meditasi, napas dalam, relaksasi otot progresif)', 'Jelaskan secara rinci intervensi relaksasi yang dipilih', 'Anjurkan mengambil posisi nyaman', 'Anjurkan rileks dan merasakan sensasi relaksasi', 'Anjurkan sering mengulangi atau melatih teknik yang dipilih', 'Demonstrasikan dan latih teknik relaksasi (mis. napas dalam, peregangan, atau imajinasi terbimbing)'], 'kolaborasi' => []]], ['kode' => 'I.09266', 'nama' => 'Dukungan Pengungkapan Kebutuhan', 'definisi' => 'Memudahkan mengungkapkan kebutuhan dan keinginan secara efektif', 'tindakan' => ['observasi' => ['Periksa gangguan komunikasi verbal (mis. ketidakmampuan berbicara, kesulitan mengekspresikan fikiran secara verbal)'], 'terapeutik' => ['Ciptakan lingkungan yang tenang', 'Hindari berbicara keras', 'Anjurkan pertanyaan dengan jawaban singkat, dengan isyarat anggukan kepala jika mengalami kesulitan berbicara', 'Jadwalkan waktu istirahat sebelum waktu kunjungan dan sesi terapi wicara', 'Fasilitasi komunikasi dengan media (mis. pensil dan kertas, komputer, kartu kata)'], 'edukasi' => ['Informasikan keluarga dan tenaga kesehatan lain teknik berkomunikasi dan gunakan secara konsisten', 'Anjurkan keluarga dan staf mengajak bicara meskipun tidak mampu berkomunikasi'], 'kolaborasi' => ['Rujuk pada terapis wicara jika perlu']]]]]],

            // D.0075 — Ketidaknyamanan Pasca Partum (updated from PDF p.370-375)
            ['diagkep_id' => 'D.0075', 'diagkep_desc' => 'Ketidaknyamanan Pasca Partum', 'diagkep_json' => ['sdki' => ['kategori' => 'Fisiologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Perasaan tidak nyaman berhubungan dengan kondisi setelah melahirkan', 'penyebab' => ['fisiologis' => ['Trauma perineum selama persalinan dan kelahiran', 'Involusi uterus, proses pengembalian ukuran rahim keukuran semula', 'Pembengkakan payudara dimana alveoli mulai terisi ASI', 'Kekurangan dukungan dari keluarga dan tenaga kesehatan', 'Ketidaktepatan posisi duduk', 'Faktor budaya']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh tidak nyaman'], 'objektif' => ['Tampak meringis', 'Terdapat kontraksi uterus', 'Luka episiotomi', 'Payudara bengkak', 'Haemorroid']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tekanan darah meningkat', 'Frekuensi nadi meningkat', 'Berkeringat berlebihan', 'Menangis/merintih']], 'kondisi_klinis_terkait' => ['Kondisi pasca persalinan']], 'slki' => [['kode' => 'L.07061', 'nama' => 'Status Kenyamanan Pasca Partum', 'kriteria_hasil' => ['Keluhan tidak nyaman menurun (1)', 'Frekuensi darah meningkat (1)']]], 'siki' => [['kode' => 'I.08238', 'nama' => 'Manajemen Nyeri', 'definisi' => 'Mengidentifikasi dan mengelola pengalaman sensori atau emosional yang berkaitan dengan kerusakan jaringan atau fungsional dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan', 'tindakan' => ['observasi' => ['Identifikasi lokasi, karakteristik, durasi, frekuensi, kualitas, intensitas nyeri', 'Identifikasi skala nyeri', 'Identifikasi respons nyeri non verbal', 'Identifikasi faktor yang memperberat dan memperingan nyeri', 'Identifikasi pengetahuan dan keyakinan tentang nyeri', 'Identifikasi pengaruh budaya terhadap respon nyeri', 'Identifikasi pengaruh nyeri pada kualitas hidup', 'Monitor keberhasilan terapi komplementer yang sudah diberikan', 'Monitor efek samping penggunaan analgetik'], 'terapeutik' => ['Berikan teknik nonfarmakologis untuk mengurangi rasa nyeri (mis. TENS, hypnosis, akupresur, terapi musik, biofeedback, terapi pijat, aromaterapi, teknik imajinasi terbimbing, kompres hangat/dingin, terapi bermain)', 'Kontrol lingkungan yang memperberat rasa nyeri (mis. suhu ruangan, pencahayaan, kebisingan)', 'Pertimbangkan jenis dan sumber nyeri dalam pemilihan strategi meredam nyeri'], 'edukasi' => ['Jelaskan penyebab, periode, dan pemicu nyeri', 'Jelaskan strategi meredam nyeri', 'Anjurkan memonitor nyeri secara mandiri', 'Anjurkan menggunakan analgetik secara tepat', 'Ajarkan teknik nonfarmakologis untuk mengurangi rasa nyeri'], 'kolaborasi' => ['Kolaborasi pemberian analgetik, jika perlu']]], ['kode' => 'I.08242', 'nama' => 'Pemantauan Nyeri', 'definisi' => 'Mengumpulkan dan menganalisa data nyeri', 'tindakan' => ['observasi' => ['Identifikasi faktor pencetus dan pereda nyeri', 'Monitor kualitas nyeri (mis. terasa tajam, tumpul, diremas-remas, ditimpa beban berat)', 'Monitor lokasi dan penyebaran nyeri', 'Monitor intensitas nyeri dengan menggunakan skala', 'Monitor durasi dan frekuensi nyeri'], 'terapeutik' => ['Atur interval waktu pemantauan sesuai dengan kondisi pasien', 'Dokumentasikan hasil pemantauan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan', 'Informasikan hasil pemantauan, jika perlu'], 'kolaborasi' => []]], ['kode' => 'I.08245', 'nama' => 'Perawatan Kenyamanan', 'definisi' => 'Mengidentifikasi dan merawat pasien untuk meningkatkan rasa nyaman', 'tindakan' => ['observasi' => ['Identifikasi gejala yang tidak menyenangkan (mis. mual, nyeri, gatal, sesak)', 'Identifikasi pemahaman tentang kondisi, situasi dan perasaan', 'Identifikasi masalah emosional dan spiritual'], 'terapeutik' => ['Berikan posisi yang nyaman', 'Berikan kompres dingin atau hangat', 'Ciptakan lingkungan yang nyaman', 'Berikan pemijatan', 'Berikan terapi akupresur', 'Berikan terapi hypnosis', 'Dukung keluarga dan pengasuh terlibat dalam terapi/pengobatan', 'Diskusikan mengenai situasi dan pilihan terapi/pengobatan yang diinginkan'], 'edukasi' => ['Jelaskan mengenai kondisi dan pilihan terapi/pengobatan', 'Ajarkan terapi relaksasi', 'Ajarkan teknik distraksi dan imajinasi terbimbing'], 'kolaborasi' => ['Kolaborasi pemberian premedikasi sebelum mengubah posisi, jika perlu']]]]]],

            // D.0076 — Nausea (updated from PDF p.376-379)
            ['diagkep_id' => 'D.0076', 'diagkep_desc' => 'Nausea', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Perasaan tidak nyaman pada bagian belakang tenggorokan atau lambung yang dapat mengakibatkan muntah', 'penyebab' => ['fisiologis' => ['Gangguan biokimiawi (mis. Uremia, ketoasidosis diabetik)', 'Gangguan pada esofagus', 'Distensi lambung', 'Iritasi lambung', 'Gangguan pankreas', 'Peregangan kapsul limpa', 'Tumor terlokalisasi (mis. neuroma akustik, tumor otak primer atau sekunder, metastasis tulang di dasar tengkorak)', 'Peningkatan tekanan intraabdominal (mis. keganasan intraabdomen)', 'Peningkatan tekanan intrakranial', 'Peningkatan tekanan intraorbital (mis. glaukoma)', 'Mabuk perjalanan', 'Kehamilan', 'Aroma tidak sedap', 'Rasa makanan/minuman yang tidak enak', 'Stimulus penglihatan tidak menyenangkan', 'Faktor psikologis (mis. kecemasan, ketakutan, stres)', 'Efek agen farmakologis', 'Efek toksin']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh mual', 'Merasa ingin munta', 'Tidak berniat makan'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa asam di mulut', 'Sensasi panas/dingin', 'Sering menelan'], 'objektif' => ['Saliva meningkat', 'Pucat', 'Diaforesis', 'Takikardia', 'Pupil dilatasi']], 'kondisi_klinis_terkait' => ['Meningitis', 'Labirinitis', 'Uremia', 'Ketoasidosis diabetik', 'Ulkus peptikum', 'Penyakit esofagus', 'Tumor intraabdomen', 'Penyakit meniere', 'Neuroma akustik', 'Tumor otak', 'Kanker', 'Glaukoma']], 'slki' => [['kode' => 'L.08065', 'nama' => 'Tingkat Nausea', 'kriteria_hasil' => ['Perasaan ingin muntah menurun']]], 'siki' => [['kode' => 'I.03117', 'nama' => 'Manajemen Mual', 'definisi' => 'Mengidentifikasi dan mengelola perasaan tidak nyaman pada bagian belakang tenggorok atau lambung yang mengakibatkan muntah', 'tindakan' => ['observasi' => ['Identifikasi pengalaman mual'], 'terapeutik' => ['Berikan makanan dalam jumlah kecil dan menarik', 'Kurangi atau hilangkan keadaan penyebab muntah (mis. kecemasan dan ketakutan)'], 'edukasi' => ['Anjurkan memperbaiki istirahat dan tidur yang cukup'], 'kolaborasi' => ['Kolaborasi pemberian anti emetik, jika perlu']]], ['kode' => 'I.03118', 'nama' => 'Manajemen Muntah', 'definisi' => 'Mengidentifikasi, mencegah dan mengelola refleks pengeluaran isi lambung', 'tindakan' => ['observasi' => ['Periksa volume muntah'], 'terapeutik' => ['Kurangi atau hilangkan keadaan penyebab muntah (mis. Kecemasan dan ketakutan)'], 'edukasi' => ['Anjurkan memperbaiki istirahat dan tidur yang cukup'], 'kolaborasi' => ['Kolaborasi pemberian anti emetik jika perlu']]], ['kode' => 'I.01007', 'nama' => 'Edukasi Teknik Napas', 'definisi' => 'Mengajarkan teknik pernapasan untuk meningkatkan relaksasi, meredakan nyeri dan ketidaknyamanan', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan'], 'edukasi' => ['Jelaskan tujuan dan manfaat teknik napas', 'Anjurkan memposisikan tubuh senyaman mungkin'], 'kolaborasi' => []]], ['kode' => 'I.08247', 'nama' => 'Dukungan Hypnosis Diri', 'definisi' => 'Memfasilitasi penggunaan kondisi hipnosis yang dilakukan sendiri untuk manfaat terapeutik', 'tindakan' => ['observasi' => ['Identifikasi apakah hipnosis diri dapat digunakan', 'Identifikasi masalah yang akan diatasi dengan hipnosis diri', 'Identifikasi penerimaan terhadap hipnosis diri'], 'terapeutik' => ['Tetapkan tujuan hipnosis diri', 'Buatkan jadwal latihan, jika perlu'], 'edukasi' => ['Jelaskan jenis hipnosis diri sebagai penunjang terapi modalitas (mis. hipnoterapi, psikoterapi, terapi kelompok, terapi keluarga)', 'Ajarkan prosedur hipnosis diri sesuai kebutuhan dan tujuan', 'Anjurkan memodifikasi prosedur hipnosis diri (frekuensi, intensitas, teknik) berdasarkan respons dan kenyamanan'], 'kolaborasi' => []]]]]],

            // D.0077 — Nyeri Akut (updated from PDF p.380-384)
            ['diagkep_id' => 'D.0077', 'diagkep_desc' => 'Nyeri Akut', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Pengalaman sensorik atau emosional yang berkaitan dengan kerusakan jaringan aktual atau fungsional, dengan onset mendadak atau lambat dan berintensitas ringan hingga berat yang berlangsung kurang dari 3 bulan', 'penyebab' => ['fisiologis' => ['Agen pencedera fisiologis (mis. inflamasi, iskemia, neoplasma)', 'Agen pencedera kimiawi (mis. terbakar, bahan kimia iritan)', 'Agen pencedera fisik (mis. abses, amputasi, terbakar, terpotong, mengangkat berat, prosedur operasi, trauma, latihan fisik berlebihan)']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh nyeri'], 'objektif' => ['Tampak meringis', 'Bersikap protektif (misalnya waspada, posisi menghindari nyeri)', 'Gelisah', 'Frekuensi nadi meningkat', 'Sulit tidur']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tekanan darah meningkat', 'Pola napas berubah', 'Nafsu makan berubah', 'Proses berfikir terganggu', 'Menarik diri', 'Berfokus pada diri sendiri', 'Diaforesis']], 'kondisi_klinis_terkait' => ['Kondisi pembedahan', 'Cedera traumatis', 'Infeksi', 'Sindrom koroner akut', 'Glaukoma']], 'slki' => [['kode' => 'L.08066', 'nama' => 'Tingkat Nyeri', 'kriteria_hasil' => ['Keluhan nyeri menurun', 'Meringis menurun']]], 'siki' => [['kode' => 'I.08238', 'nama' => 'Manajemen Nyeri', 'definisi' => 'Mengidentifikasi dan mengelola pengalaman sensori atau emosional yang berkaitan dengan kerusakan jaringan atau fungsional dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan', 'tindakan' => ['observasi' => ['Identifikasi lokasi, karakteristik, durasi, frekuensi, kualitas, intensitas nyeri', 'Identifikasi skala nyeri', 'Identifikasi respons nyeri non verbal', 'Identifikasi faktor yang memperberat dan memperingan nyeri', 'Identifikasi pengetahuan dan keyakinan tentang nyeri', 'Identifikasi pengaruh budaya terhadap respon nyeri', 'Identifikasi pengaruh nyeri pada kualitas hidup', 'Monitor keberhasilan terapi komplementer yang sudah diberikan', 'Monitor efek samping penggunaan analgetik'], 'terapeutik' => ['Berikan teknik nonfarmakologis untuk mengurangi rasa nyeri (mis. TENS, hipnosis, akupresur, terapi musik, biofeedback, terapi pijat, aromaterapi, teknik imajinasi terbimbing, kompres hangat/dingin, terapi bermain)', 'Kontrol lingkungan yang memperberat rasa nyeri (mis. Suhu ruangan, pencahayaan, kebisingan)', 'Pertimbangkan jenis dan sumber nyeri dalam pemilihan strategi meredam nyeri'], 'edukasi' => ['Jelaskan penyebab, periode, dan pemicu nyeri', 'Jelaskan strategi meredakan nyeri', 'Anjurkan memonitor nyeri secara mandiri', 'Anjurkan menggunakan analgetik secara tepat', 'Ajarkan teknik nonfarmakologis untuk mengurangi rasa nyeri'], 'kolaborasi' => ['Kolaborasi pemberian analgetik, jika perlu']]], ['kode' => 'I.08243', 'nama' => 'Pemberian Analgesik', 'definisi' => 'Menyiapkan dan memberikan agen farmakologis untuk mengurangi atau menghilangkan rasa sakit', 'tindakan' => ['observasi' => ['Identifikasi riwayat alergi obat'], 'terapeutik' => ['Diskusikan jenis analgesik yang disukai untuk mencapai analgesik yang optimal'], 'edukasi' => ['Jelaskan efek samping obat'], 'kolaborasi' => ['Kolaborasi pemberian dosis dan jenis analgesik, sesuai indikasi']]], ['kode' => 'I.08242', 'nama' => 'Pemantauan Nyeri', 'definisi' => 'Mengumpulkan dan menganalisa data nyeri', 'tindakan' => ['observasi' => ['Identifikasi faktor pencetus dan pereda nyeri', 'Monitor kualitas nyeri', 'Monitor lokasi dan penyebaran nyeri', 'Monitor intensitas nyeri dengan menggunakan skala', 'Monitor durasi dan frekuensi nyeri'], 'terapeutik' => ['Atur interval waktu pemantauan sesuai dengan kondisi pasien', 'Dokumentasikan hasil pemantauan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan'], 'kolaborasi' => []]], ['kode' => 'I.08249', 'nama' => 'Terapi Murattal', 'definisi' => 'Menggunakan media Al-Quran (baik dengan mendengar atau membaca) untuk membantu meningkatkan perubahan yang spesifik dalam tubuh baik secara fisiologis maupun psikologis', 'tindakan' => ['observasi' => ['Identifikasi lama dan durasi pemberian sesuai dengan kondisi pasien'], 'terapeutik' => ['Posisikan dalam posisi lingkunga yang nyaman'], 'edukasi' => ['Jelaskan tujuan dan manfaat terapi'], 'kolaborasi' => []]], ['kode' => 'I.01006', 'nama' => 'Latihan Pernapasan', 'definisi' => 'Melatih teknik pernapasan untuk relaksasi dan meredakan nyeri', 'tindakan' => ['observasi' => ['Identifikasi indikasi dilakukan latihan pernapasan', 'Monitor frekuensi, irama dan kedalaman napas sebelum dan sesudah latihan'], 'terapeutik' => ['Sediakan tempat yang tenang', 'Posisikan pasien nyaman dan rileks', 'Tempatkan satu tangan di dada dan satu tangan di perut', 'Pastikan tangan di dada mundur kebelakang dan telapak tangan di perut maju ke depan saat menarik napas', 'Ambil napas dalam secara perlahan melalui hidung dan tahan selama tujuh hitungan', 'Hitungan ke delapan hembuskan napas melalui mulut dengan perlahan'], 'edukasi' => ['Jelaskan tujuan dan prosedur latihan pernapasan', 'Anjurkan mengulangi latihan 4-5 kali'], 'kolaborasi' => []]]]]],

            // D.0078 — Nyeri Kronis (updated from PDF p.385-389)
            ['diagkep_id' => 'D.0078', 'diagkep_desc' => 'Nyeri Kronis', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Pengalaman sensorik atau emosional yang berkaitan dengan kerusakan jaringan aktual atau fungsional, dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan, yang berlangsung lebih dari 3 bulan', 'penyebab' => ['fisiologis' => ['Kondisi muskuloskeletal kronis', 'Kerusakan sistem saraf', 'Penekanan saraf', 'Infiltrasi tumor', 'Ketidakseimbangan neurotransmiter, neuromodulator, dan reseptor', 'Gangguan imunitas (mis. neuropati terkait HIV, virus varicella-zoster)', 'Gangguan fungsi metabolik', 'Riwayat posisi kerja statis', 'Peningkatan indeks massa tubuh', 'Kondisi pasca trauma', 'Tekanan emosional', 'Riwayat penganiayaan (mis. fisik, psikologis, seksual)', 'Riwayat penyalahgunaan obat/zat']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh nyeri', 'Merasa depresi (tertekan)'], 'objektif' => ['Tampak meringis', 'Gelisah', 'Tidak mampu menuntaskan aktivitas']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa takut mengalami cedera berulang'], 'objektif' => ['Bersikap protektif (mis. posisi menghindari nyeri)', 'Waspada', 'Pola tidur berubah', 'Anoreksia', 'Fokus menyempit', 'Berfokus pada diri sendiri']], 'kondisi_klinis_terkait' => ['Kondisi kronis (mis. arthritis rheumatoid)', 'Infeksi', 'Cedera medula spinalis', 'Kondisi pasca trauma', 'Tumor']], 'slki' => [['kode' => 'L.08066', 'nama' => 'Tingkat Nyeri', 'kriteria_hasil' => ['Keluhan nyeri menurun', 'Meringis menurun', 'Sikap protektif menurun', 'Gelisah menurun', 'Kesulitan tidur menurun', 'Frekuensi nadi membaik']]], 'siki' => [['kode' => 'I.08238', 'nama' => 'Manajemen Nyeri', 'definisi' => 'Mengidentifikasi dan mengelola pengalaman sensori atau emosional yang berkaitan dengan kerusakan jaringan atau fungsional dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan', 'tindakan' => ['observasi' => ['Identifikasi lokasi, karakteristik, durasi, frekuensi, kualitas, intensitas nyeri', 'Identifikasi skala nyeri', 'Identifikasi respons nyeri non verbal', 'Identifikasi faktor yang memperberat dan memperingan nyeri', 'Identifikasi pengetahuan dan keyakinan tentang nyeri', 'Identifikasi pengaruh budaya terhadap respon nyeri', 'Identifikasi pengaruh nyeri pada kualitas hidup', 'Monitor keberhasilan terapi komplementer yang sudah diberikan', 'Monitor efek samping penggunaan analgetik'], 'terapeutik' => ['Berikan teknik nonfarmakologis untuk mengurangi rasa nyeri (mis. TENS, hipnosis, akupresur, terapi musik, biofeedback, terapi pijat, aromaterapi, teknik imajinasi terbimbing, kompres hangat/dingin, terapi bermain)', 'Kontrol lingkungan yang memperberat rasa nyeri (mis. suhu ruangan, pencahayaan, kebisingan)', 'Fasilitasi istirahat dan tidur', 'Pertimbangkan jenis dan sumber nyeri dalam pemilihan strategi meredakan nyeri'], 'edukasi' => ['Jelaskan penyebab, periode, dan pemicu nyeri', 'Jelaskan strategi meredakan nyeri', 'Anjurkan memonitor nyeri secara mandiri', 'Anjurkan menggunakan analgetik secara tepat', 'Ajarkan teknik nonfarmakologis untuk mengurangi rasa nyeri'], 'kolaborasi' => ['Kolaborasi pemberian analgetik, jika perlu']]], ['kode' => 'I.08245', 'nama' => 'Perawatan Kenyamanan', 'definisi' => 'Mengidentifikasi dan merawat pasien untuk meningkatkan rasa nyaman', 'tindakan' => ['observasi' => ['Identifikasi gejala yang tidak menyenangkan (mis. mual, nyeri, gatal, sesak)', 'Identifikasi pemahaman tentang kondisi, situasi dan perasaan', 'Identifikasi masalah emosional dan spiritual'], 'terapeutik' => ['Berikan posisi yang nyaman', 'Berikan kompres dingin atau hangat', 'Ciptakan lingkungan yang nyaman', 'Berikan pemijatan', 'Berikan terapi akupresur', 'Berikan terapi hypnosis', 'Dukung keluarga dan pengasuh terlibat dalam terapi/pengobatan', 'Diskusikan mengenai situasi dan pilihan terapi/pengobatan yang diinginkan'], 'edukasi' => ['Jelaskan mengenai kondisi dan pilihan terapi/pengobatan', 'Ajarkan terapi relaksasi', 'Ajarkan teknik distraksi dan imajinasi terbimbing'], 'kolaborasi' => []]]]]],

            // D.0079 — Nyeri Melahirkan (updated from PDF p.393-395)
            ['diagkep_id' => 'D.0079', 'diagkep_desc' => 'Nyeri Melahirkan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Nyeri dan Kenyamanan', 'definisi' => 'Pengalaman sensorik dan emosional yang bervariasi dari menyenangkan sampai tidak menyenangkan yang berhubungan dengan persalinan', 'penyebab' => ['fisiologis' => ['Dilatasi serviks', 'Pengeluaran janin']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh nyeri', 'Perineum terasa tertekan', 'Uterus teraba membulat'], 'objektif' => ['Ekspresi wajah meringis', 'Berposisi meringankan nyeri', 'Uterus teraba membulat']], 'gejala_tanda_minor' => ['subjektif' => ['Mual', 'Nafsu makan menurun/meningkat'], 'objektif' => ['Tekanan darah meningkat', 'Frekuensi nadi meningkat', 'Ketegangan otot meningkat', 'Pola tidur berubah', 'Fungsi berkemih berubah', 'Diaforesis', 'Gangguan perilaku', 'Perilaku ekspresif', 'Pupil dilatasi', 'Muntah', 'Fokus pada diri sendiri']], 'kondisi_klinis_terkait' => ['Proses persalinan']], 'slki' => [['kode' => 'L.08066', 'nama' => 'Tingkat Nyeri', 'kriteria_hasil' => ['Keluhan nyeri menurun', 'Meringis menurun', 'Sikap protektif menurun', 'Gelisah menurun', 'Kesulitan tidur menurun', 'Frekuensi nadi membaik']]], 'siki' => [['kode' => 'I.08238', 'nama' => 'Manajemen Nyeri', 'definisi' => 'Mengidentifikasi dan mengelola pengalaman sensori atau emosional yang berkaitan dengan kerusakan jaringan atau fungsional dengan onset mendadak atau lambat dan berintensitas ringan hingga berat dan konstan', 'tindakan' => ['observasi' => ['Identifikasi lokasi, karakteristik, durasi, frekuensi, kualitas, intensitas nyeri', 'Identifikasi skala nyeri', 'Identifikasi respons nyeri non verbal', 'Identifikasi faktor yang memperberat dan memperingan nyeri', 'Identifikasi pengetahuan dan keyakinan tentang nyeri', 'Identifikasi pengaruh budaya terhadap respon nyeri', 'Identifikasi pengaruh nyeri pada kualitas hidup', 'Monitor keberhasilan terapi komplementer yang sudah diberikan', 'Monitor efek samping penggunaan analgetik'], 'terapeutik' => ['Berikan teknik nonfarmakologis untuk mengurangi rasa nyeri (mis. TENS, hipnosis, akupresur, terapi musik, biofeedback, terapi pijat, aroma terapi, teknik imajinasi terbimbing, kompres hangat/dingin, terapi bermain)', 'Kontrol lingkungan yang memperberat rasa nyeri (mis. Suhu ruangan, pencahayaan, kebisingan)', 'Fasilitasi istirahat dan tidur', 'Pertimbangkan jenis dan sumber nyeri dalam pemilihan strategi meredakan nyeri'], 'edukasi' => ['Jelaskan penyebab, periode, dan pemicu nyeri', 'Jelaskan strategi meredakan nyeri', 'Anjurkan memonitor nyeri secara mandiri', 'Anjurkan menggunakan analgetik secara tepat', 'Ajarkan teknik nonfarmakologis untuk mengurangi rasa nyeri'], 'kolaborasi' => ['Kolaborasi pemberian analgetik, jika perlu']]]]]],

            // D.0080 — Ansietas (updated from PDF p.395-398)
            ['diagkep_id' => 'D.0080', 'diagkep_desc' => 'Ansietas', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Kondisi emosi dan pengalaman subyektif individu terhadap objek yang tidak jelas dan spesifik akibat antisipasi bahaya yang memungkinkan individu melakukan tindakan untuk menghadapi ancaman', 'penyebab' => ['fisiologis' => ['Krisis situasional', 'Kebutuhan tidak terpenuhi', 'Krisis maturasional', 'Ancaman terhadap konsep diri', 'Ancaman terhadap kematian', 'Kekhawatiran mengalami kegagalan', 'Disfungsi sistem keluarga', 'Hubungan orang tua-anak tidak memuaskan', 'Faktor keturunan (temperamen mudah teragitasi sejak lahir)', 'Penyalahgunaan zat', 'Terpapar bahaya lingkungan (mis. toksin, volutan, dan lain-lain)', 'Kurang terpapar informasi']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa bingung', 'Merasa khawatir dengan akibat dari kondisi yang dihadapi', 'Sulit berkonsentrasi'], 'objektif' => ['Tampak gelisah', 'Tampak tegang', 'Sulit tidur']], 'gejala_tanda_minor' => ['subjektif' => ['Mengeluh pusing', 'Anoreksia', 'Palpitasi', 'Merasa tidak berdaya'], 'objektif' => ['Frekuensi napas meningkat', 'Frekuensi nadi meningkat', 'Tekanan darah meningkat', 'Diaforesis', 'Tremor', 'Muka tampak pucat', 'Suara bergetar', 'Kontak mata buruk', 'Sering berkemih', 'Berorientasi pada masa lalu']], 'kondisi_klinis_terkait' => ['Penyakit kronis progresif (mis: penyakit autoimun)', 'Penyakit akut', 'Hospitalisasi', 'Rencana operasi', 'Kondisi diagnosis penyakit belum jelas', 'Penyakit neurologis', 'Tahap tumbuh kembang']], 'slki' => [['kode' => 'L.09093', 'nama' => 'Tingkat Ansietas', 'kriteria_hasil' => ['Verbalisasi kebingungan menurun', 'Verbalisasi khawatir akibat kondisi yang dihadapi menurun']]], 'siki' => [['kode' => 'I.09314', 'nama' => 'Reduksi Ansietas', 'definisi' => 'Meminimalkan kondisi individu dan pengalaman subyektif terhadap objek yang tidak jelas dan spesifik akibat antisipasi bahaya yang memungkinkan individu melakukan tindakan untuk menghadapi ancaman', 'tindakan' => ['observasi' => ['Identifikasi saat singkat ansietas berubah (mis. kondisi, waktu, stresor)', 'Monitor tanda-tanda ansietas (verbal dan nonverbal)'], 'terapeutik' => ['Ciptakan suasana terapeutik untuk menumbuhkan kepercayaan', 'Pahami situasi yang membuat ansietas'], 'edukasi' => ['Jelaskan prosedur, termasuk sensasi yang mungkin dialami', 'Anjurkan mengungkapkan perasaan dan persepsi', 'Latih teknik relaksasi'], 'kolaborasi' => ['Kolaborasi pemberian obat antiansietas, jika perlu']]], ['kode' => 'I.09326', 'nama' => 'Terapi Relaksasi', 'definisi' => 'Menggunakan teknik peregangan untuk mengurangi tanda dan gejala ketidaknyamanan seperti nyeri, ketegangan otot, atau kecemasan', 'tindakan' => ['observasi' => ['Monitor respon terhadap terapi relaksasi'], 'terapeutik' => ['Gunakan pakaian longgar'], 'edukasi' => ['Jelaskan tujuan, manfaat, batasan, dan jenis relaksasi yang tersedia (mis. musik, meditasi, napas dalam, relaksasi otot progresif)'], 'kolaborasi' => []]], ['kode' => 'I.09269', 'nama' => 'Biblioterapi', 'definisi' => 'Menggunakan literatur untuk mengekspresikan perasaan, menyelesaikan masalah secara aktif, meningkatkan kemampuan koping atau pengetahuan', 'tindakan' => ['observasi' => ['Identifikasi kemampuan emosional, kognitif, perkembangan dan situasional'], 'terapeutik' => ['Pilih literatur (cerita, puisi, esai, artikel, buku, atau novel) berdasarkan kemampuan membaca, atau sesuai situasi/perasaan yang dialami'], 'edukasi' => ['Jelaskan tujuan dan prosedur biblioterapi', 'Anjurkan membaca ulang'], 'kolaborasi' => ['Konsultasikan dengan pustakawan untuk penelusuran buku/literatur yang tepat']]], ['kode' => 'I.09256', 'nama' => 'Dukungan Emosional', 'definisi' => 'Memfasilitasi penerimaan kondisi emosional selama masa stress', 'tindakan' => ['observasi' => ['Identifikasi hal yang memicu emosi'], 'terapeutik' => ['Fasilitasi mengungkapkan perasaan cemas, marah, atau sedih'], 'edukasi' => ['Anjurkan mengungkapkan perasaan yang dialami (mis. ansietas, marah dan sedih)'], 'kolaborasi' => ['Rujuk untuk konseling, jika perlu']]]]]],
            // D.0081 — Berduka (updated from PDF p.399-401)
            ['diagkep_id' => 'D.0081', 'diagkep_desc' => 'Berduka', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Respon psikososial yang ditunjukkan oleh klien akibat kehilangan (orang, objek, fungsi, status, bagian tubuh atau hubungan)', 'penyebab' => ['fisiologis' => ['Kematian keluarga atau orang yang berarti', 'Antisipasi kematian keluarga atau orang yang berarti', 'Kehilangan (objek, pekerjaan, fungsi, status, bagian tubuh, hubungan sosial)', 'Antisipasi kehilangan (objek, pekerjaan, fungsi, status, bagian tubuh, hubungan sosial)']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa sedih', 'Merasa bersalah atau menyalahkan orang lain', 'Tidak menerim kehilangan', 'Merasa tidak ada harapan'], 'objektif' => ['Menangis', 'Pola tidur berubah', 'Tidak mampu berkonsentrasi']], 'gejala_tanda_minor' => ['subjektif' => ['Mimpi buruk atau pola mimpi berubah', 'Merasa tidak berguna', 'Fobia'], 'objektif' => ['Marah', 'Tampak panik', 'Fungsi imunitas terganggu']], 'kondisi_klinis_terkait' => ['Kematian anggota keluarga atau orang terdekat', 'Amputasi', 'Cedera medulla spinalis', 'Kondisi kehilangan perinatal', 'Penyakit terminal (mis. kanker)', 'Putus hubungan kerja']], 'slki' => [['kode' => 'L.09094', 'nama' => 'Tingkat Berduka', 'kriteria_hasil' => ['Verbalisasi menerima kehilangan (membaik)', 'Verbalisasi perasaan sedih (membaik)', 'Verbalisasi perasaan bersalah atau menyalahkan orang lain (membaik)', 'Pola tidur (membaik)', 'Interaksi dengan orang terdekat/tokoh agama (membaik)']]], 'siki' => [['kode' => 'I.09274', 'nama' => 'Dukungan Proses Berduka', 'definisi' => 'Memfasilitasi resolusi berduka yang efektif', 'tindakan' => ['observasi' => ['Identifikasi proses berduka yang dialami', 'Identifikasi keterikatan pada benda yang hilang atau orang yang meninggal'], 'terapeutik' => ['Motivasi agar mau mengungkapkan perasaan kehilangan', 'Motivasi untuk menguatkan dukungan keluarga atau orang terdekat', 'Diskusikan strategi koping yang dapat digunakan'], 'edukasi' => ['Ajarkan melewati proses berduka secara bertahap'], 'kolaborasi' => []]]]]],
            // D.0082 — Distres Spiritual (updated from PDF p.401-406)
            ['diagkep_id' => 'D.0082', 'diagkep_desc' => 'Distres Spiritual', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Gangguan pada keyakinan atau sistem nilai berupa kesulitan merasakan makna dan tujuan hidup melalui hubungan dengan diri, orang lain, lingkungan atau tuhan', 'penyebab' => ['fisiologis' => ['Menjelang ajal', 'Kondisi penyakit kronis', 'Kematian orang terdekat', 'Perubahan pola hidup', 'Kesepian', 'Pengasingan diri', 'Pengasingan sosial', 'Gangguan sosio-kultural', 'Peningkatan ketergantungan pada orang lain', 'Kejadian hidup yang tidak diharapkan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mempertanyakan makna/tujuan hidupnya', 'Menyatakan hidupnya terasa tidak/kurang bermakna', 'Merasa menderita/tidak berdaya'], 'objektif' => ['Tidak mampu beribadah', 'Marah pada tuhan']], 'gejala_tanda_minor' => ['subjektif' => ['Menyatakan hidupnya terasa tidak/kurang tenang', 'Mengeluh tidak dapat menerima (kurang pasrah)', 'Merasa bersalah', 'Merasa terasing', 'Menyatakan telah diabaikan'], 'objektif' => ['Menolak berinteraksi dengan orang terdekat/pemimpin spiritual', 'Tidak mampu berkreativitas (mis. menyanyi, mendengarkan musik, menulis)', 'Koping tidak efektif', 'Tidak berminat pada alam/literatur spiritual']], 'kondisi_klinis_terkait' => ['Penyakit kronis (mis. arthritis rheumatoid, sklerosis multipel)', 'Penyakit terminal (mis. kanker)', 'Retardasi mental', 'Kehilangan bagian tubuh', 'Sudden infant death syndrome (SIDS)', 'Kelahiran mati, kematian janin, keguguran', 'Kemandulan', 'Gangguan psikiatrik']], 'slki' => [['kode' => 'L.09091', 'nama' => 'Status Spiritual', 'kriteria_hasil' => ['Verbalisasi makna dan tujuan hidup dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap makna hidup dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi perasaan keberdayaan dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.09276', 'nama' => 'Dukungan Spiritual', 'definisi' => 'Memfasilitasi peningkatan perasaan seimbang dan terhubung dengan kekuatan yang lebih besar', 'tindakan' => ['observasi' => ['Identifikasi perasaan khawatir, kesepian dan ketidakberdayaan', 'Identifikasi pandangan tentang hubungan antara spiritual dan kesehatan', 'Identifikasi harapan dan kekuatan pasien', 'Identifikasi ketaatan dalam beragama'], 'terapeutik' => ['Berikan kesempatan mengekspresikan perasaan tentang penyakit dan kematian', 'Berikan kesempatan mengekspresikan dan meredakan marah secara tepat', 'Yakinkan bahwa perawat bersedia mendukung selama masa ketidakberdayaan', 'Sediakan privasi dan waktu tenang untuk aktivitas spiritual', 'Diskusikan keyakinan tentang makna dan tujuan hidup, jika perlu', 'Fasilitasi melakukan kegiatan ibadah'], 'edukasi' => ['Anjurkan berinteraksi dengan keluarga, teman, dan/atau orang lain', 'Anjurkan berpartisipasi dalam kelompok pendukung', 'Ajarkan metode relaksasi, meditasi, dan imajinasi terbimbing'], 'kolaborasi' => ['Atur kunjungan dengan rohaniawan (mis. ustadz, pendeta, romo, biksu)']]]]]],
            // D.0083 — Gangguan Citra Tubuh (updated from PDF p.407-408)
            ['diagkep_id' => 'D.0083', 'diagkep_desc' => 'Gangguan Citra Tubuh', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Perubahan persepsi tentang penampilan, struktur dan fungsi fisik individual', 'penyebab' => ['fisiologis' => ['Perubahan struktur/bentuk tubuh (mis. amputasi, trauma, luka bakar, obesitas, jerawat)', 'Perubahan fungsi tubuh (mis. proses penyakit, kehamilan, kelumpuhan)', 'Perubahan fungsi kognitif', 'Ketidak sesuaian budaya, keyakinan atau sistem nilai', 'Transisi perkembangan', 'Gangguan psikososial', 'Efek tindakan/pengobatan (mis. pembedahan, kemoterapi, terapi radiasi)']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan kecacatan/kehilangan bagian tubuh'], 'objektif' => ['Kehilangan bagian tubuh', 'Fungsi/struktur tubuh berubah/hilang']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak mau mengungkapkan kecacatan/kehilangan bagian tubuh', 'Mengungkapkan perasaan negatif tentang perubahan tubuh', 'Mengungkapkan kekhawatiran pada penolakan/reaksi orang lain', 'Mengungkapkan perubahan gaya hidup'], 'objektif' => ['Menyembunyikan/menunjukkan bagian tubuh secara berlebihan', 'Menghindari melihat dan/atau menyentuh bagian tubuh', 'Fokus berlebihan pada perubahan tubuh', 'Respon nonverbal pada perubahan dan persepsi tubuh', 'Fokus pada penampilan dan kekuatan masa lalu', 'Hubungan sosial berubah']], 'kondisi_klinis_terkait' => ['Mastektomi', 'Amputasi', 'Jerawat', 'Parut atau luka bakar yang terlihat', 'Obesitas', 'Hiperpigmentasi pada kehamilan']], 'slki' => [['kode' => 'L.09067', 'nama' => 'Citra Tubuh', 'kriteria_hasil' => ['Melihat bagian tubuh dari skala 1 meningkat menjadi skala 5 membaik', 'Menyentuh bagian tubuh dari skala 1 meningkat menjadi skala 5 membaik', 'Verbalisasi kecacatan bagian tubuh dari skala 1 meningkat menjadi skala 5 membaik', 'Verbalisasi kehilangan bagian tubuh dari skala 1 meningkat menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.09305', 'nama' => 'Promosi Citra Tubuh', 'definisi' => 'Meningkatkan perbaikan perubahan persepsi terhadap fisik pasien', 'tindakan' => ['observasi' => ['Identifikasi harapan citra tubuh berdasarkan tahap perkembangan', 'Identifikasi budaya, agama, jenis kelamin, dan umur terkait citra tubuh', 'Identifikasi perubahan citra tubuh yang mengakibatkan isolasi sosial', 'Monitor frekuensi pernyataan kritik terhadap diri sendiri', 'Monitor apakah pasien bisa melihat bagian tubuh yang berubah'], 'terapeutik' => ['Diskusikan perubahan tubuh dan fungsinya', 'Diskusikan perbedaan penampilan fisik terhadap harga diri', 'Diskusikan perubahan akibat pubertas, kehamilan dan penuaan', 'Diskusikan kondisi stres yang mempengaruhi citra tubuh (mis. luka, penyakit, pembedahan)', 'Diskusikan cara mengembangkan harapan citra tubuh secara realistis', 'Diskusikan persepsi pasien dan keluarga tentang perubahan citra tubuh'], 'edukasi' => ['Jelaskan kepada keluarga tentang perawatan perubahan citra tubuh', 'Anjurkan mengungkapkan gambaran diri terhadap citra tubuh', 'Anjurkan menggunakan alat bantu (mis. pakaian, wig, kosmetik)', 'Anjurkan mengikuti kelompok pendukung (mis. kelompok sebaya)', 'Latih fungsi tubuh yang dimiliki', 'Latih peningkatan penampilan diri (mis. berdandan)', 'Latih pengungkapan kemampuan diri kepada orang lain maupun kelompok'], 'kolaborasi' => []]]]]],
            // D.0084 — Gangguan Identitas Diri (updated from PDF p.409-412)
            ['diagkep_id' => 'D.0084', 'diagkep_desc' => 'Gangguan Identitas Diri', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Tidak mampu mempertahankan keutuhan persepsi terhadap identitas diri', 'penyebab' => ['fisiologis' => ['Gangguan peran sosial', 'Tidak terpenuhinya tugas perkembangan', 'Gangguan neurologis', 'Ketidakadekuatan stimulasi sensori']], 'gejala_tanda_mayor' => ['subjektif' => ['Persepsi terhadap diri berubah', 'Bingung dengan nilai-nilai budaya, tujuan hidup, jenis kelamin, dan/atau nilai-nilai ideal'], 'objektif' => ['Perilaku tidak konsisten', 'Hubungan yang tidak efektif', 'Strategi koping tidak efektif', 'Penampilan peran tidak efektif']], 'gejala_tanda_minor' => ['subjektif' => ['Perasaan yang fluktuatif terhadap diri'], 'objektif' => ['Perilaku tidak konsisten', 'Hubungan yang tidak efektif', 'Strategi koping tidak efektif', 'Penampilan peran tidak efektif']], 'kondisi_klinis_terkait' => ['Gangguan autistik', 'Gangguan orientasi seksual', 'Periode perkembangan remaja', 'Cedera kepala', 'Trauma thoraks', 'Guillain Barre syndrome', 'Sklerosis multipel', 'Myasthenia gravis', 'Stroke', 'Kuadrifplegia', 'Intoksikasi alkohol']], 'slki' => [['kode' => 'L.09070', 'nama' => 'Identitas Diri', 'kriteria_hasil' => ['Perilaku konsisten dari skala 1 meningkat menjadi skala 5 menurun', 'Perasaan fluktuatif terhadap diri dari skala 1 meningkat menjadi skala 3 sedang', 'Persepsi terhadap diri dari memburuk/skala 1 menjadi membaik/skala 5']]], 'siki' => [['kode' => 'I.09297', 'nama' => 'Orientasi Realita', 'definisi' => 'Meningkatkan kesadaran terhadap identitas diri, waktu, dan lingkungan', 'tindakan' => ['observasi' => ['Monitor perubahan orientasi', 'Monitor perubahan kognitif dan perilaku'], 'terapeutik' => ['Perkenalkan nama saat memulai interaksi', 'Orientasikan orang, tempat, dan waktu', 'Hadirkan realita (mis. beri penjelasan alternatif, hindari perdebatan)', 'Sediakan lingkungan dan rutinitas secara konsisten', 'Atur stimulasi sensorik dan lingkungan (mis. kunjungan, pemandangan, suara, pencahayaan, bau, dan sentuhan)', 'Gunakan simbol dalam mengorientasikan lingkungan (mis. tanda, gambar, dan warna)', 'Libatkan dalam terapi kelompok orientasi', 'Berikan waktu istirahat dan tidur yang cukup, sesuai kebutuhan', 'Fasilitasi akses informasi (mis. televisi, surat kabar, radio), jika perlu'], 'edukasi' => ['Anjurkan perawatan diri secara mandiri', 'Anjurkan penggunaan alat bantu (mis. kacamata, alat bantu dengar, dan gigi palsu)', 'Ajarkan keluarga dalam perawatan orientasi realita'], 'kolaborasi' => []]]]]],
            // D.0085 — Gangguan Persepsi Sensori (updated from PDF p.416-420)
            ['diagkep_id' => 'D.0085', 'diagkep_desc' => 'Gangguan Persepsi Sensori', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Perubahan persepsi terhadap stimulus baik internal maupun eksternal yang disertai dengan respon yang berkurang, berlebihan atau terdistorsi', 'penyebab' => ['fisiologis' => ['Gangguan penglihatan', 'Gangguan pendengaran', 'Gangguan penghidu', 'Gangguan perabaan', 'Hipoksia serebral', 'Penyalahgunaan zat', 'Usia lanjut', 'Pemajanan toksin lingkungan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mendengar suara bisikan atau melihat bayangan', 'Merasakan sesuatu melalui indera perabaan, penciuman atau pengecapan'], 'objektif' => ['Distorsi sensori', 'Respons tidak sesuai', 'Bersikap seolah melihat, mendengar, mengecap, meraba atau mencium sesuatu']], 'gejala_tanda_minor' => ['subjektif' => ['Menyatakan kesal'], 'objektif' => ['Menyendiri', 'Melamun', 'Konsentrasi buruk', 'Distorsi waktu, tempat, orang atau situasi', 'Curiga', 'Melihat ke satu arah', 'Mondar mandir', 'Bicara sendiri']], 'kondisi_klinis_terkait' => ['Glaukoma', 'Katarak', 'Gangguan refraksi (miopia, hiperopia, astigmatisma, presbiopia)', 'Trauma okuler', 'Trauma pada saraf kranialis II, III, IV akibat stroke, aneurima intrakranial, trauma/tumor otak', 'Infeksi okuler', 'Presbikusis', 'Malfungsi alat bantu dengar', 'Delirium', 'Demensia', 'Gangguan amnestik', 'Penyakit terminal', 'Gangguan psikotik']], 'slki' => [['kode' => 'L.06048', 'nama' => 'Fungsi Sensori', 'kriteria_hasil' => ['Keluhan ketajaman pendengaran meningkat dari skala 1 (menurun) menjadi skala 5 (meningkat)']]], 'siki' => [['kode' => 'I.09288', 'nama' => 'Manajemen Halusinasi', 'definisi' => 'Mengidentifikasi dan mengelola peningkatan keamanan, kenyamanan dan orientasi realita', 'tindakan' => ['observasi' => ['Monitor perilaku yang mengindikasikan halusinasi', 'Monitor isi halusinasi (mis. kekerasan atau membahayakan diri)'], 'terapeutik' => ['Pertahankan lingkungan yang aman', 'Diskusikan perasaan dan respon terhadap halusinasi'], 'edukasi' => ['Anjurkan memonitor sendiri situasi terjadi halusinasi', 'Anjurkan melakukan distraksi (mis. mendengarkan musik, melakukan aktivitas, dan teknik relaksasi)', 'Ajarkan pasien dan keluarga cara mengontrol halusinasi'], 'kolaborasi' => ['Kolaborasi pemberian obat antipsikotik dan antiansietas, jika perlu']]]]]],
            // D.0086 — Harga Diri Rendah Kronis (updated from PDF p.420-423)
            ['diagkep_id' => 'D.0086', 'diagkep_desc' => 'Harga Diri Rendah Kronis', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Evaluasi atau perasaan negatif terhadap diri sendiri atau kemampuan klien seperti tidak berarti, tidak berharga, tidak berdaya yang berlangsung dalam waktu yang lama dan terus menerus', 'penyebab' => ['fisiologis' => ['Terpapar situasi traumatis', 'Kegagalan berulang', 'Kurangnya pengakuan dari orang lain', 'Ketidakefektifan mengatasi masalah kehilangan', 'Gangguan psikiatri', 'Penguatan negatif berulang', 'Ketidaksesuaian budaya']], 'gejala_tanda_mayor' => ['subjektif' => ['Menilai diri negatif (mis. tidak berguna, tidak tertolong)', 'Merasa malu/bersalah', 'Merasa tidak mampu melakukan apapun', 'Meremehkan kemampuan mengatasi masalah', 'Merasa tidak memiliki kelebihan atau kemampuan positif', 'Melebih-lebihkan penilaian negatif tentang diri sendiri', 'Menolak penilaian positif tentang diri sendiri'], 'objektif' => ['Enggan mencoba hal baru', 'Berjalan menunduk', 'Postur tubuh menunduk']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa sulit konsentrasi', 'Sulit tidur', 'Mengungkapkan keputusasaan'], 'objektif' => ['Kontak mata kurang', 'Lesu dan tidak bergairah', 'Berbicara pelan dan lirih', 'Pasif', 'Perilaku tidak asertif', 'Mencari penguatan secara berlebihan', 'Bergantung pada pendapat orang lain', 'Sulit membuat keputusan', 'Sering kali mencari penegasan']], 'kondisi_klinis_terkait' => ['Cedera traumatis', 'Pembedahan', 'Kehamilan', 'Stroke', 'Penyalahgunaan zat', 'Demensia', 'Penyakit kronis', 'Pengalaman tidak menyenangkan']], 'slki' => [['kode' => 'L.09069', 'nama' => 'Harga Diri', 'kriteria_hasil' => ['Penilaian diri positif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Perasaan memiliki kelebihan atau kemampuan positif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Penerimaan penilaian positif terhadap diri sendiri dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Minat mencoba hal baru dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Berjalan menampakkan wajah dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Postur tubuh menampakkan wajah dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Konsentrasi dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Tidur dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Kontak mata dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Gairah aktivitas dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Aktif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Percaya diri berbicara dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Perilaku asertif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Kemampuan membuat keputusan dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Perasaan malu dari meningkat skala 5 dapat menurun skala 1', 'Perasaan bersalah dari meningkat skala 5 dapat menjadi skala 1 menurun', 'Perasaan tidak mampu melakukan apapun dari meningkat skala 5 dapat menjadi skala 1 menurun', 'Meremehkan kemampuan mengatasi masalah dari meningkat skala 1 menjadi skala 5 menurun', 'Ketergantungan pada penguatan secara berlebihan dari meningkat skala 5 dapat menjadi menurun skala 1', 'Pencairan penguatan secara berlebihan dari meningkat skala 5 dapat menjadi skala 1 menurun']]], 'siki' => [['kode' => 'I.12463', 'nama' => 'Manajemen Perilaku', 'definisi' => 'Mengidentifikasi dan mengelola perilaku negatif', 'tindakan' => ['observasi' => ['Identifikasi harapan untuk mengendalikan perilaku'], 'terapeutik' => ['Diskusikan tanggung jawab terhadap perilaku', 'Jadwalkan kegiatan terstruktur', 'Ciptakan dan pertahankan lingkungan dan kegiatan perawatan konsisten setiap dinas', 'Tingkatkan aktivitas fisik sesuai kemampuan', 'Batasi jumlah pengunjung', 'Bicara dengan nada rendah dan tenang', 'Lakukan kegiatan pengalihan terhadap sumber agitasi', 'Cegah perilaku pasif dan agresif', 'Beri penguatan positif terhadap keberhasilan mengendalikan perilaku', 'Lakukan pengekangan fisik sesuai indikasi', 'Hindari bersikap menyudutkan dan menghentikan pembicaraan', 'Hindari sikap mengancam dan berdebat', 'Hindari berdebat atau menawar batas perilaku yang telah ditetapkan'], 'edukasi' => ['Informasikan keluarga bahwa keluarga sebagai dasar pembentukan kognitif'], 'kolaborasi' => []]]]]],
            // D.0087 — Harga Diri Rendah Situasional (updated from PDF p.424-426)
            ['diagkep_id' => 'D.0087', 'diagkep_desc' => 'Harga Diri Rendah Situasional', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Evaluasi atau perasaan negatif terhadap diri sendiri atau kemampuan klien sebagai respon terhadap situasi saat ini', 'penyebab' => ['fisiologis' => ['Perubahan pada citra tubuh', 'Perubahan peran sosial', 'Ketidakadekuatan pemahaman', 'Perilaku tidak konsisten dengan nilai', 'Kegagalan hidup berulang', 'Riwayat kehilangan', 'Riwayat penolakan', 'Transisi perkembangan']], 'gejala_tanda_mayor' => ['subjektif' => ['Menilai diri negatif (mis. tidak merasa berguna, tidak tertolong)', 'Merasa malu/bersalah', 'Melebih-lebihkan penilaian negatif tentang diri sendiri', 'Menolak penilaian positif tentang diri sendiri'], 'objektif' => ['Berbicara pelan dan lirih', 'Menolak berinteraksi dengan orang lain', 'Berjalan menunduk', 'Postur tubuh menunduk']], 'gejala_tanda_minor' => ['subjektif' => ['Sulit berkonsentrasi'], 'objektif' => ['Kontak mata kurang', 'Lesu dan tidak bergairah', 'Pasif', 'Tidak mampu membuat keputusan']], 'kondisi_klinis_terkait' => ['Cedera traumatis', 'Pembedahan', 'Kehamilan', 'Kondisi baru terdiagnosis (mis. diabetes melitus)', 'Stroke', 'Penyalahgunaan zat', 'Demensia', 'Pengalaman tidak menyenangkan']], 'slki' => [['kode' => 'L.09069', 'nama' => 'Harga Diri', 'kriteria_hasil' => ['Penilaian diri positif dari menurun skala 1 menjadi skala 5 meningkat', 'Perasaan memiliki kelebihan atau kemampuan positif dari menurun skala 1 menjadi skala 5 meningkat', 'Penerimaan penilaian positif terhadap diri sendiri dari menurun skala 1 menjadi skala 5 meningkat', 'Minat mencoba hal baru dari menurun skala 1 menjadi skala 5 meningkat', 'Berjalan menampakkan wajah dari menurun skala 1 menjadi skala 5 meningkat', 'Postur tubuh menampakkan wajah dari menurun skala 1 menjadi skala 5 meningkat', 'Perasaan malu dari meningkat skala 1 menjadi skala 5 menurun', 'Perasaan bersalah dari meningkat skala 1 menjadi skala 5 menurun', 'Perasaan tidak mampu melakukan apapun dari meningkat skala 1 menjadi skala 5 menurun', 'Meremehkan kemampuan mengatasi masalah dari meningkat skala 1 menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.12463', 'nama' => 'Manajemen Perilaku', 'definisi' => 'Mengidentifikasi dan mengelola perilaku negatif', 'tindakan' => ['observasi' => ['Identifikasi harapan untuk mengendalikan perilaku'], 'terapeutik' => ['Diskusikan tanggung jawab terhadap perilaku', 'Jadwalkan kegiatan terstruktur', 'Ciptakan dan pertahankan lingkungan dan kegiatan perawatan konsisten setiap dinas', 'Tingkatkan aktivitas fisik sesuai kemampuan', 'Batasi jumlah pengunjung', 'Bicara dengan nada rendah dan tenang', 'Lakukan kegiatan pengalihan terhadap sumber agitasi', 'Cegah perilaku pasif dan agresif', 'Beri penguatan positif terhadap keberhasilan mengendalikan perilaku', 'Lakukan pengekangan fisik sesuai indikasi', 'Hindari bersikap menyudutkan dan menghentikan pembicaraan', 'Hindari sikap mengancam dan berdebat', 'Hindari berdebat atau menawar batas perilaku yang telah ditetapkan'], 'edukasi' => ['Informasikan keluarga bahwa keluarga sebagai dasar pembentukan kognitif'], 'kolaborasi' => []]]]]],
            // D.0088 — Keputusasaan (updated from PDF p.426-429)
            ['diagkep_id' => 'D.0088', 'diagkep_desc' => 'Keputusasaan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Kondisi individu yang memandang adanya keterbatasan atau tidak tersedianya alternatif pemecahan masalah yang dihadapi', 'penyebab' => ['fisiologis' => ['Stres jangka panjang', 'Penurunan kondisi fisiologis', 'Kehilangan kepercayaan pada kekuatan spiritual', 'Kehilangan kepercayaan pada nilai-nilai penting', 'Pembatasan aktivitas jangka panjang', 'Pengasingan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan keputusasaan'], 'objektif' => ['Kurang terlibat dalam aktivitas perawatan', 'Afek datar']], 'gejala_tanda_minor' => ['subjektif' => ['Sulit tidur', 'Selera makan menurun'], 'objektif' => ['Berperilaku pasif', 'Kurang inisiatif', 'Meninggalkan lawan bicara', 'Mengangkat bahu sebagai respon pada lawan bicara']], 'kondisi_klinis_terkait' => ['Penyakit kronis', 'Penyakit terminal', 'Penyakit yang tidak dapat disembuhkan']], 'slki' => [['kode' => 'L.09068', 'nama' => 'Harapan', 'kriteria_hasil' => ['Keterlibatan dalam aktivitas perawatan dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi keputusasaan dari skala 1 meningkat menjadi skala 5 menurun', 'Perilaku pasif dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.09256', 'nama' => 'Dukungan Emosional', 'definisi' => 'Memfasilitasi penerimaan kondisi emosional selama masa stres', 'tindakan' => ['observasi' => ['Identifikasi fungsi marah, frustasi, dan amuk bagi pasien', 'Identifikasi hal yang telah memicu emosi'], 'terapeutik' => ['Fasilitasi mengungkapkan perasaan cemas, marah, atau sedih', 'Buat pernyataan suportif atau empati selama fase berduka', 'Lakukan sentuhan untuk memberikan dukungan (mis. merangkul, menepuk-nepuk)', 'Tetap bersama pasien dan pastikan keamanan selama ansietas, jika perlu', 'Kurangi tuntutan berpikir saat sakit atau lelah'], 'edukasi' => ['Jelaskan konsekuensi tidak menghadapi rasa bersalah dan malu', 'Anjurkan mengungkapkan perasaan yang dialami (mis. ansietas, marah, sedih)', 'Anjurkan mengungkapkan pengalaman emosional sebelumnya dan pola respons yang biasa digunakan', 'Ajarkan penggunaan mekanisme pertahanan yang tepat'], 'kolaborasi' => ['Rujuk untuk konseling, jika perlu']]]]]],
            // D.0089 — Kesiapan Peningkatan Konsep Diri (updated from PDF p.429-435)
            ['diagkep_id' => 'D.0089', 'diagkep_desc' => 'Kesiapan Peningkatan Konsep Diri', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Pola persepsi diri yang cukup untuk merasa sejahtera dan dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengekspresikan keinginan untuk meningkatkan konsep diri', 'Mengekspresikan kepuasan dengan diri, harga diri, penampilan peran, citra tubuh, dan identitas pribadi'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa percaya diri', 'Menerima kelebihan dan keterbatasan'], 'objektif' => ['Tindakan sesuai dengan perasaan dan pikiran yang diekspresikan']], 'kondisi_klinis_terkait' => ['Perilaku upaya peningkatan kesehatan']], 'slki' => [['kode' => 'L.09076', 'nama' => 'Konsep Diri', 'kriteria_hasil' => ['Verbalisasi kepuasan terhadap diri dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap harga diri dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap penampilan peran dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap citra tubuh dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap identitas diri dari skala 1 menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.09308', 'nama' => 'Promosi Harga Diri', 'definisi' => 'Meningkatkan penilaian perasaan/persepsi terhadap diri sendiri atau kemampuan diri', 'tindakan' => ['observasi' => ['Identifikasi budaya, agama, ras, jenis kelamin, dan usia terhadap harga diri', 'Monitor verbalisasi yang merendahkan diri sendiri', 'Monitor tingkat harga diri setiap waktu, sesuai kebutuhan'], 'terapeutik' => ['Motivasi terlibat dalam verbalisasi positif untuk diri sendiri', 'Motivasi menerima tantangan atau hal baru', 'Diskusikan pernyataan tentang harga diri', 'Diskusikan kepercayaan terhadap penilaian diri', 'Diskusikan pengalaman yang meningkatkan harga diri', 'Diskusikan persepsi negatif diri', 'Diskusikan alasan mengkritik diri atau rasa bersalah', 'Diskusikan penetapan tujuan realistis untuk mencapai harga diri yang lebih tinggi', 'Diskusikan bersama keluarga untuk menetapkan harapan dan batasan yang jelas', 'Berikan umpan balik positif atas peningkatan mencapai tujuan', 'Fasilitasi lingkungan dan aktivitas yang meningkatkan harga diri'], 'edukasi' => ['Jelaskan kepada keluarga pentingnya dukungan dalam perkembangan konsep positif diri pasien', 'Anjurkan mengidentifikasi kekuatan yang dimiliki', 'Anjurkan mempertahankan kontak mata saat berkomunikasi dengan orang lain', 'Anjurkan membuka diri terhadap kritik negatif', 'Anjurkan mengevaluasi perilaku', 'Anjurkan cara mengatasi bullying', 'Latih peningkatan tanggung jawab untuk diri sendiri', 'Latih pernyataan/kemampuan positif diri', 'Latih cara berfikir dan berperilaku positif', 'Latih meningkatkan kepercayaan pada kemampuan dalam menangani situasi'], 'kolaborasi' => []]]]]],
            // D.0090 — Kesiapan Peningkatan Koping Keluarga (updated from PDF p.435-440)
            ['diagkep_id' => 'D.0090', 'diagkep_desc' => 'Kesiapan Peningkatan Koping Keluarga', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Pola adaptasi anggota keluarga dalam mengatasi situasi yang dialami klien secara efektif dan menunjukkan keinginan serta kesiapan untuk meningkatkan kesehatan keluarga dan klien', 'gejala_tanda_mayor' => ['subjektif' => ['Anggota keluarga menetapkan tujuan untuk meningkatkan gaya hidup sehat', 'Anggota keluarga menetapkan sasaran untuk meningkatkan kesehatan'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Anggota keluarga mengidentifikasi pengalaman yang mengoptimalkan kesejahteraan', 'Anggota keluarga berupaya menjelaskan dampak krisis terhadap perkembangan', 'Anggota keluarga mengungkapkan minat dalam membuat kontak dengan orang lain yang mengalami situasi yang sama'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Kelainan genetik (mis. sindrom down, fibrosis kistik)', 'Cedera traumatis (mis. amputasi, cedera spinal)', 'Kondisi kronis (mis. asma, AIDS, penyakit alzhaimer)']], 'slki' => [['kode' => 'L.09088', 'nama' => 'Status Koping Keluarga', 'kriteria_hasil' => ['Kepuasan terhadap perilaku bantuan anggota keluarga meningkat']]], 'siki' => [['kode' => 'I.09260', 'nama' => 'Dukungan Koping Keluarga', 'definisi' => 'Memfasilitasi peningkatan nilai-nilai, minat dan tujuan dalam keluarga', 'tindakan' => ['observasi' => ['Identifikasi respons emosional terhadap kondisi saat ini', 'Identifikasi beban prognosis secara psikologis', 'Identifikasi pemahaman keluarga tentang keputusan perawatan setelah pulang', 'Identifikasi kesesuaian antara harapan pasien, keluarga, dan tenaga kesehatan'], 'terapeutik' => ['Dengarkan masalah, perasaan, dan pertanyaan keluarga', 'Terima nilai-nilai keluarga dengan cara yang tidak menghakimi', 'Diskusikan rencana medis dan perawatan', 'Fasilitasi pengungkapan perasaan antara pasien dan keluarga atau antar anggota keluarga', 'Fasilitasi pengambilan keputusan dalam merencanakan perawatan jangka panjang, jika perlu', 'Fasilitasi anggota keluarga dalam mengidentifikasi dan menyelesaikan konflik nilai', 'Fasilitasi pemenuhan kebutuhan dasar keluarga (mis. tempat tinggal, makanan, pakaian)', 'Fasilitasi anggota keluarga melalui proses kematian dan berduka, jika perlu', 'Fasilitasi memperoleh pengetahuan, keterampilan, dan peralatan yang diperlukan untuk mempertahankan keputusan perawatan pasien', 'Bersikap sebagai pengganti keluarga untuk menenangkan pasien dan/atau jika keluarga tidak dapat memberikan perawatan', 'Hargai dan dukung mekanisme koping adaptif yang digunakan', 'Berikan kesempatan berkunjung bagi anggota keluarga'], 'edukasi' => ['Informasikan kemajuan pasien secara berkala', 'Informasikan fasilitas perawatan kesehatan yang tersedia'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0091 — Kesiapan Peningkatan Koping Komunitas (updated from PDF p.449-451)
            ['diagkep_id' => 'D.0091', 'diagkep_desc' => 'Kesiapan Peningkatan Koping Komunitas', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Pola adaptasi dan penyelesaian masalah komunitas yang memuaskan untuk memenuhi tuntutan atau kebutuhan masyarakat, serta dapat ditingkatkan untuk penatalaksanaan masalah saat ini dan mendatang', 'gejala_tanda_mayor' => ['subjektif' => ['Perencanaan aktif oleh komunitas mengenai prediksi stressor', 'Pemecahan masalah aktif oleh komunitas saat menghadapi masalah'], 'objektif' => ['Terdapat sumber-sumber daya yang adekuat untuk mengatasi stressor']], 'gejala_tanda_minor' => ['subjektif' => ['Bersepakat bahwa komunitas bertanggung jawab terhadap penatalaksanaan stres', 'Berkomunikasi positif di antara komunitas', 'Berkomunikasi positif diantara komunitas'], 'objektif' => ['Tersedia program untuk rekreasi', 'Tersedia program untuk relaksasi/bersantai']], 'kondisi_klinis_terkait' => ['Penurunan tingkat penyakit, kecelakaan atau kekerasan']], 'slki' => [['kode' => 'L.09089', 'nama' => 'Status Koping Komunitas', 'kriteria_hasil' => ['Keberdayaan komunitas dari skala 1 menurun menjadi skala 5 meningkat', 'Perencanaan komunitas dari skala 1 menurun menjadi skala 3 sedang']]], 'siki' => [['kode' => 'I.12383', 'nama' => 'Edukasi Kesehatan', 'definisi' => 'Mengajarkan pengelolaan faktor risiko penyakit dan perilaku hidup bersih serta sehat', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi faktor-faktor yang dapat meningkatkan dan menurunkan motivasi perilaku hidup bersih dan sehat'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan faktor risiko yang dapat mempengaruhi kesehatan', 'Ajarkan perilaku hidup bersih dan sehat', 'Ajarkan strategi yang dapat digunakan untuk meningkatkan perilaku hidup bersih dan sehat'], 'kolaborasi' => []]]]]],
            // D.0092 — Ketidakberdayaan (updated from PDF p.451-460)
            ['diagkep_id' => 'D.0092', 'diagkep_desc' => 'Ketidakberdayaan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Persepsi bahwa tindakan seseorang tidak akan memengaruhi hasil secara signifikan; persepsi kurang kontrol pada situasi saat ini atau yang akan datang', 'penyebab' => ['fisiologis' => ['Program perawatan/pengobatan yang komplek atau jangka panjang', 'Lingkungan tidak mendukung perawatan/pengobatan', 'Interaksi interpersonal tidak memuaskan']], 'gejala_tanda_mayor' => ['subjektif' => ['Menyatakan frustasi atau tidak mampu melaksanakan aktivitas sebelumnya'], 'objektif' => ['Bergabung pada orang lain']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa diasingkan', 'Menyatakan keraguan tentang kinerja peran', 'Menyatakan kurang kontrol', 'Menyatakan rasa malu', 'Merasa tertekan (depresi)'], 'objektif' => ['Tidak berpartisipasi dalam perawatan', 'Pengasingan']], 'kondisi_klinis_terkait' => ['Diagnosa yang tidak terduga atau baru', 'Peristiwa traumatis', 'Diagnosa penyakit kronis', 'Diagnosa penyakit terminal', 'Rawat inap']], 'slki' => [['kode' => 'L.09071', 'nama' => 'Keberdayaan', 'kriteria_hasil' => ['Verbalisasi mampu melaksanakan aktivitas dari skala 1 meningkat menjadi skala 5', 'Verbalisasi frustasi kebergantungan pada orang lain dari skala 1 meningkat menjadi skala 3 sedang']]], 'siki' => [['kode' => 'I.09307', 'nama' => 'Promosi Harapan', 'definisi' => 'Meningkatkan kepercayaan pada kemampuan untuk memulai dan mempertahankan tindakan', 'tindakan' => ['observasi' => ['Identifikasi harapan pasien dan keluarga dalam pencapaian hidup'], 'terapeutik' => ['Sadarkan bahwa kondisi yang dialami memiliki nilai penting', 'Pandu mengingat kembali kenangan yang menyenangkan', 'Libatkan pasien secara aktif dalam perawatan', 'Kembangkan rencana perawatan yang melibatkan tingkat pencapaian tujuan sederhana sampai dengan kompleks', 'Berikan kesempatan kepada pasien dan keluarga terlibat dengan dukungan kelompok', 'Ciptakan lingkungan yang memudahkan mempraktikkan kebutuhan spiritual'], 'edukasi' => ['Anjurkan mengungkapkan perasaan terhadap kondisi dengan realistis', 'Anjurkan mempertahankan hubungan (mis. menyebutkan nama orang yang dicintai)', 'Anjurkan mempertahankan hubungan terapeutik dengan orang lain', 'Latih menyusun tujuan yang sesuai dengan harapan', 'Latih cara mengembangkan spiritual diri', 'Latih cara mengenang dan menikmati masa lalu (mis. prestasi, pengalaman)'], 'kolaborasi' => []]]]]],
            // D.0093 — Ketidakmampuan Koping Keluarga (updated from PDF p.461-463)
            ['diagkep_id' => 'D.0093', 'diagkep_desc' => 'Ketidakmampuan Koping Keluarga', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Perilaku orang terdekat (anggota keluarga atau orang berarti) yang membatasi kemampuan dirinya dan klien untuk beradaptasi dengan masalah kesehatan yang dihadapi klien', 'penyebab' => ['fisiologis' => ['Hubungan keluarga ambivalen', 'Pola koping yang berbeda diantara klien dan orang terdekat', 'Resistensi keluarga terhadap perawatan/pengobatan yang kompleks', 'Ketidakmampuan orang terdekat mengungkapkan perasaan']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa diabaikan'], 'objektif' => ['Tidak memenuhi kebutuhan anggota keluarga', 'Tidak toleran', 'Mengabaikan anggota keluarga']], 'gejala_tanda_minor' => ['subjektif' => ['Terlalu khawatir dengan anggota keluarga', 'Merasa tertekan (depresi)'], 'objektif' => ['Perilaku menyerang (agresi)', 'Perilaku menghasut (agitasi)', 'Tidak berkomitmen', 'Menunjukkan gejala psikosomatis', 'Perilaku menolak', 'Perawatan yang mengabaikan kebutuhan dasar klien', 'Mengabaikan perawatan/pengobatan anggota keluarga', 'Perilaku bermusuhan', 'Perilaku individualistik', 'Upaya membangun hidup bermakna terganggu', 'Perilaku sehat terganggu', 'Ketergantungan anggota keluarga meningkat', 'Realitas kesehatan anggota keluarga terganggu']], 'kondisi_klinis_terkait' => ['Penyakit Alzheimer', 'AIDS', 'Kelainan yang menyebabkan paralisis permanen', 'Kanker', 'Penyakit kronis (mis. kanker, arthritis reumatoid)', 'Penyalahgunaan zat', 'Krisis keluarga', 'Konflik keluarga yang belum terselesaikan']], 'slki' => [['kode' => 'L.09088', 'nama' => 'Status Koping Keluarga', 'kriteria_hasil' => ['Keputusan terhadap perilaku bantuan anggota keluarga lain meningkat', 'Keterpaparan informasi meningkat', 'Perasaan diabaikan menurun', 'Kekhawatiran tentang anggota keluarga menurun', 'Perilaku mengabaikan anggota keluarga menurun', 'Komitmen pada perawatan/pengobatan meningkat', 'Komunikasi antara anggota keluarga meningkat', 'Perasaan tertekan (depresi) menurun', 'Perilaku menyerang (agresi) menurun', 'Perilaku penghasutan menurun', 'Gejala psikosomatis menurun', 'Perilaku menolak perawatan menurun', 'Perilaku bermusuhan menurun', 'Perilaku individualistik menurun', 'Ketergantungan pada anggota keluarga lain menurun', 'Perilaku overprotektif menurun', 'Toleransi membaik', 'Perilaku bertujuan membaik', 'Perilaku sehat membaik']]], 'siki' => [['kode' => 'I.09260', 'nama' => 'Dukungan Koping Keluarga', 'definisi' => 'Memfasilitasi peningkatan nilai-nilai, minat dan tujuan dalam keluarga', 'tindakan' => ['observasi' => ['Identifikasi respons emosional terhadap kondisi saat ini', 'Identifikasi beban prognosis secara psikologis', 'Identifikasi pemahaman tentang keputusan perawatan setelah pulang', 'Identifikasi kesesuaian antara harapan pasien, keluarga, dan tenaga kesehatan'], 'terapeutik' => ['Dengarkan masalah, perasaan, dan pertanyaan keluarga', 'Terima nilai-nilai keluarga dengan cara yang tidak menghakimi', 'Diskusikan rencana medis dan perawatan', 'Fasilitasi pengungkapan perasaan antara pasien dan keluarga atau antar anggota keluarga', 'Fasilitasi pengambilan keputusan dalam merencanakan perawatan jangka panjang, jika perlu', 'Fasilitasi anggota keluarga dalam mengidentifikasi dan menyelesaikan konflik nilai', 'Fasilitasi pemenuhan kebutuhan dasar keluarga (mis. tempat tinggal, makanan, pakaian)', 'Fasilitasi anggota keluarga melalui proses kematian dan berduka, jika perlu', 'Fasilitasi memperoleh pengetahuan, keterampilan, dan peralatan yang diperlukan untuk mempertahankan keputusan perawatan pasien', 'Bersikap sebagai pengganti keluarga untuk menenangkan pasien dan/atau jika keluarga tidak dapat memberikan perawatan', 'Hargai dan dukung mekanisme koping adaptif yang digunakan', 'Berikan kesempatan berkunjung bagi anggota keluarga'], 'edukasi' => ['Informasikan kemajuan pasien secara berkala', 'Informasikan fasilitas perawatan kesehatan yang tersedia'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0094 — Koping Defensif (updated from PDF p.463-469)
            ['diagkep_id' => 'D.0094', 'diagkep_desc' => 'Koping Defensif', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Proyeksi evaluasi diri untuk melindungi diri dari ancaman terhadap harga diri', 'penyebab' => ['fisiologis' => ['Konflik antar persepsi diri dan sistem nilai', 'Takut mengalami kegagalan', 'Takut mengalami penghinaan', 'Takut terhadap dampak situasi yang dihadapi', 'Kurangnya rasa percaya kepada orang lain', 'Kurangnya kepercayaan diri', 'Kurangnya dukungan sistem pendukung (support system)', 'Harapan yang tidak realistis']], 'gejala_tanda_mayor' => ['subjektif' => ['Menyalalahkan orang lain', 'Menyangkal adanya masalah', 'Menyangkal kelemahan diri', 'Merasionalisasi kegagalan'], 'objektif' => ['Hipersensitif terhadap kritik']], 'gejala_tanda_minor' => ['subjektif' => ['Meremehkan orang lain'], 'objektif' => ['Melemparkan tanggung jawab', 'Tawa permusuhan', 'Sikap superior terhadap orang lain', 'Tidak dapat membedakan realitas', 'Kurang minat mengikuti perawatan/pengobatan', 'Sulit membangun atau mempertahankan hubungan']], 'kondisi_klinis_terkait' => ['Penyakit kronis', 'Penyalahgunaan zat', 'Attention-Deficit/Hyperactivity Disorder (ADHD)', 'Gangguan perilaku', 'Oppositional Defiant Disorder', 'Delirium']], 'slki' => [['kode' => 'L.09086', 'nama' => 'Status Koping', 'kriteria_hasil' => ['Komunikasi jelas sesuai usia dari skala 1 menurun menjadi skala 5 meningkat', 'Pemahaman makna situasi dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan membuat keputusan dari skala 1 menurun menjadi skala 5 meningkat', 'Perhatian dari skala 1 menurun menjadi skala 5 meningkat', 'Konsentrasi dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.09308', 'nama' => 'Promosi Harga Diri', 'definisi' => 'Meningkatkan penilaian perasaan/persepsi terhadap diri sendiri atau kemampuan diri', 'tindakan' => ['observasi' => ['Identifikasi budaya, agama, ras, jenis kelamin, dan usia terhadap harga diri', 'Monitor verbalisasi yang merendahkan diri sendiri', 'Monitor tingkat harga diri setiap waktu, sesuai kebutuhan'], 'terapeutik' => ['Motivasi terlibat dalam verbalisasi positif untuk diri sendiri', 'Motivasi menerima tantangan atau hal baru', 'Diskusikan pernyataan tentang harga diri', 'Diskusikan kepercayaan terhadap penilaian diri', 'Diskusikan pengalaman yang meningkatkan harga diri', 'Diskusikan persepsi negatif diri', 'Diskusikan alasan mengkritik diri atau rasa bersalah', 'Diskusikan penetapan tujuan realistis untuk mencapai harga diri yang lebih tinggi', 'Diskusikan bersama keluarga untuk menetapkan harapan dan batasan yang jelas', 'Berikan umpan balik positif atas peningkatan mencapai tujuan', 'Fasilitasi lingkungan dan aktivitas yang meningkatkan harga diri'], 'edukasi' => ['Jelaskan kepada keluarga pentingnya dukungan dalam perkembangan konsep positif diri pasien', 'Anjurkan mengidentifikasi kekuatan yang dimiliki', 'Anjurkan mempertahankan kontak mata saat berkomunikasi dengan orang lain', 'Anjurkan membuka diri terhadap kritik negatif', 'Anjurkan mengevaluasi perilaku', 'Ajarkan cara mengatasi bullying', 'Latih peningkatan tanggung jawab untuk diri sendiri', 'Latih pernyataan/kemampuan positif diri', 'Latih cara berfikir dan berperilaku positif', 'Latih meningkatkan kepercayaan pada kemampuan dalam menangani situasi'], 'kolaborasi' => []]]]]],
            // D.0095 — Koping Komunitas Tidak Efektif (updated from PDF p.469-472)
            ['diagkep_id' => 'D.0095', 'diagkep_desc' => 'Koping Komunitas Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Pola adaptasi aktivitas komunitas dan penyelesaian masalah yang tidak memuaskan untuk memenuhi tuntutan atau kebutuhan masyarakat', 'penyebab' => ['fisiologis' => ['Paparan bencana (alam atau buatan manusia)', 'Riwayat bencana (alam atau buatan manusia)', 'Ketidakadekuatan sumber daya untuk pemecahan masalah', 'Ketidakcukupan sumber daya masyarakat (mis. istirahat, rekreasi, dukungan sosial)', 'Tidak adanya sistem masyarakat']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan ketidakberdayaan komunitas'], 'objektif' => ['Komunitas tidak memenuhi harapan anggotanya', 'Konflik masyarakat meningkat', 'Insiden masalah masyarakat meningkat tinggi (mis. pembunuhan, pengerusakan, pelecehan, pengangguran, kemiskinan, penyakit mental)']], 'gejala_tanda_minor' => ['subjektif' => ['Mengungkapkan kerentanan komunitas'], 'objektif' => ['Partisipasi masyarakat kurang', 'Tingkat penyakit masyarakat menungkat', 'Stress meningkat']], 'kondisi_klinis_terkait' => ['Insiden kekerasan tinggi', 'Tingkat penyakit tinggi', 'Sedikitnya kesempatan atau lokasi untuk interaksi komunitas']], 'slki' => [['kode' => 'L.09089', 'nama' => 'Status Koping Komunitas', 'kriteria_hasil' => ['Keberdayaan komunitas meningkat', 'Perencanaan komunitas meningkat', 'Pemecahan masalah komunitas meningkat', 'Sumber daya komunitas meningkat', 'Partisipasi masyarakat meningkat', 'Insiden masalah kesehatan dalam komunitas menurun']]], 'siki' => [['kode' => 'I.14515', 'nama' => 'Manajemen Lingkungan Komunitas', 'definisi' => 'Mengidentifikasi dan mengelola kondisi lingkungan fisik, sosial, budaya, ekonomi, dan politik yang mempengaruhi kesehatan masyarakat', 'tindakan' => ['observasi' => ['Lakukan skrining risiko gangguan kesehatan lingkungan', 'Identifikasi faktor risiko kesehatan yang diketahui'], 'terapeutik' => ['Libatkan partisipasi masyarakat dalam memelihara keamanan lingkungan'], 'edukasi' => ['Promosikan kebijakan pemerintah untuk mengurangi risiko penyakit', 'Berikan pendidikan kesehatan untuk kelompok risiko', 'Informasikan layanan kesehatan ke individu, keluarga, kelompok berisiko dan masyarakat'], 'kolaborasi' => ['Kolaborasi dalam tim multidisiplin untuk mengidentifikasi ancaman keamanan di masyarakat', 'Kolaborasi dengan tim kesehatan lain dalam program kesehatan komunitas untuk menghadapi risiko yang diketahui', 'Kolaborasi dalam pengembangan program aksi masyarakat', 'Kolaborasi dengan kelompok masyarakat dalam menjalankan peraturan pemerintah']]]]]],
            // D.0096 — Koping Tidak Efektif (updated from PDF p.472-480)
            ['diagkep_id' => 'D.0096', 'diagkep_desc' => 'Koping Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Ketidakmampuan menilai dan merespons stressor dan/atau ketidakmampuan menggunakan sumber-sumber yang ada untuk mengatasi masalah', 'penyebab' => ['fisiologis' => ['Ketidakpercayaan terhadap kemampuan diri mengatasi masalah', 'Ketidakadekuatan sistem pendukung', 'Ketidakadekuatan strategi koping', 'Ketidakteraturan atau kekacauan lingkungan', 'Ketidakcukupan persiapan untuk menghadapi stresor', 'Disfungsi sistem keluarga', 'Krisis situasional', 'Krisis maturasional', 'Kerentanan personalitas', 'Ketidakpastian']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan tidak mampu mengatasi masalah'], 'objektif' => ['Tidak mampu memenuhi peran yang diharapkan (sesuai usia)', 'Menggunakan mekanisme koping yang tidak sesuai']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak mampu memenuhi kebutuhan dasar', 'Kekhawatiran kronis'], 'objektif' => ['Penyalahgunaan zat', 'Memanipulasi orang lain untuk memenuhi keinginannya sendiri', 'Perilaku tidak asertif', 'Partisipasi sosial kurang']], 'kondisi_klinis_terkait' => ['Kondisi perawatan kritis', 'Attention Deficit/Hyperactivity Disorder (ADHD)', 'Gangguan perilaku', 'Oppositional Defiant Disorder', 'Gangguan kecemasan perpisahan', 'Delirium', 'Demensia', 'Gangguan amnestic', 'Intoksikasi zat', 'Putus zat']], 'slki' => [['kode' => 'L.09086', 'nama' => 'Status Koping', 'kriteria_hasil' => ['Kemampuan memenuhi peran sesuai usia meningkat', 'Perilaku koping adaptif meningkat', 'Verbalisasi kemampuan mengatasi masalah meningkat', 'Verbalisasi pengakuan masalah meningkat', 'Perilaku asertif meningkat', 'Verbalisasi menyalahkan orang lain menurun', 'Verbalisasi rasionalisasi kegagalan menurun', 'Hipersensitif terhadap kritik menurun']]], 'siki' => [['kode' => 'I.09265', 'nama' => 'Dukungan Pengambilan Keputusan', 'definisi' => 'Memberikan informasi dan dukungan saat pembuatan keputusan kesehatan', 'tindakan' => ['observasi' => ['Identifikasi persepsi mengenai masalah dan informasi yang memicu konflik'], 'terapeutik' => ['Fasilitasi mengklarifikasi nilai dan harapan yang membantu membuat pilihan', 'Diskusikan kelebihan dan kekurangan dari setiap solusi', 'Fasilitasi melihat situasi secara realistis', 'Motivasi mengungkapkan tujuan perawatan yang diharapkan', 'Fasilitasi pengambilan keputusan secara kolaboratif', 'Hormati hak pasien untuk menerima atau menolak informasi', 'Fasilitasi menjelaskan keputusan kepada orang lain, jika perlu', 'Fasilitasi hubungan antara pasien, keluarga, dan tenaga kesehatan lainnya'], 'edukasi' => ['Informasikan alternatif solusi secara jelas', 'Berikan informasi yang diminta pasien'], 'kolaborasi' => ['Kolaborasi dengan tenaga kesehatan lain dalam memfasilitasi pengambilan keputusan']]]]]],
            // D.0097 — Penurunan Koping Keluarga (updated from PDF p.480-483)
            ['diagkep_id' => 'D.0097', 'diagkep_desc' => 'Penurunan Koping Keluarga', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Ketidakadekuatan atau ketidakefektifan dukungan, rasa nyaman, bantuan dan motivasi orang terdekat (anggota keluarga atau orang berarti) yang dibutuhkan klien untuk mengelola atau mengatasi masalah kesehatannya', 'penyebab' => ['fisiologis' => ['Situasi penyerta yang mempengaruhi orang terdekat', 'Krisis perkembangan yang dihadapi orang terdekat', 'Kelelahan orang terdekat dalam memberikan dukungan', 'Disorganisasi keluarga', 'Perubahan peran keluarga', 'Tidak tersedianya informasi bagi orang terdekat', 'Kurangnya saling mendukung', 'Tidak cukupnya dukungan yang diberikan klien pada orang terdekat', 'Orang terdekat kurang terpapar informasi', 'Salahnya/tidak pahamnya informasi yang didapatkan orang terdekat', 'Orang terdekat terlalu fokus pada kondisi di luar keluarga', 'Penyakit kronis yang menghabiskan kemampuan dukungan orang terdekat', 'Krisis situasional yang dialami orang terdekat']], 'gejala_tanda_mayor' => ['subjektif' => ['Klien mengeluh/khawatir tentang respon orang terdekat pada masalah kesehatan'], 'objektif' => ['Orang terdekat menarik diri dari klien', 'Terbatasnya komunikasi orang terdekat dengan klien']], 'gejala_tanda_minor' => ['subjektif' => ['Orang terdekat menyatakan kurang terpapar informasi tentang upaya mengatasi masalah klien'], 'objektif' => ['Bantuan yang dilakukan orang terdekat menunjukkan hasil yang tidak memuaskan', 'Orang terdekat berperilaku protektif yang tidak sesuai dengan kemampuan/kemandirian klien']], 'kondisi_klinis_terkait' => ['Penyakit alzheimer', 'AIDS', 'Kelainan yang menyebabkan paralisis permanen', 'Kanker', 'Penyakit kronis (mis. kanker, arthritis reumatoid)', 'Penyalahgunaan zat', 'Krisis keluarga', 'Konflik keluarga yang belum terselesaikan']], 'slki' => [['kode' => 'L.09088', 'nama' => 'Status Koping Keluarga', 'kriteria_hasil' => ['Perasaan diabaikan membaik', 'Kekhawatiran tentang anggota keluarga membaik', 'Perilaku mengabaikan anggota keluarga membaik', 'Kemampuan memenuhi kebutuhan anggota keluarga membaik', 'Komitmen pada perawatan/pengobatan membaik', 'Komunikasi antara anggota keluarga membaik', 'Toleransi membaik']]], 'siki' => [['kode' => 'I.09260', 'nama' => 'Dukungan Koping Keluarga', 'definisi' => 'Memfasilitasi peningkatan nilai-nilai, minat dan tujuan dalam keluarga', 'tindakan' => ['observasi' => ['Identifikasi respons emosional terhadap kondisi saat ini', 'Identifikasi beban prognosis secara psikologis', 'Identifikasi pemahaman tentang keputusan perawatan setelah pulang', 'Identifikasi kesesuaian antara harapan pasien, keluarga, dan tenaga kesehatan'], 'terapeutik' => ['Dengarkan masalah, perasaan, dan pertanyaan keluarga', 'Terima nilai-nilai keluarga dengan cara yang tidak menghakimi', 'Diskusikan rencana medis dan perawatan', 'Fasilitasi pengungkapan perasaan antara pasien dan keluarga atau antar anggota keluarga', 'Fasilitasi pengambilan keputusan dalam merencanakan perawatan jangka panjang, jika perlu', 'Fasilitasi anggota keluarga dalam mengidentifikasi dan menyelesaikan konflik nilai', 'Fasilitasi pemenuhan kebutuhan dasar keluarga (mis. tempat tinggal, makanan, pakaian)', 'Fasilitasi anggota keluarga melalui proses kematian dan berduka, jika perlu', 'Fasilitasi memperoleh pengetahuan, keterampilan, dan peralatan yang diperlukan untuk mempertahankan keputusan perawatan pasien', 'Bersikap sebagai pengganti keluarga untuk menenangkan pasien dan/atau jika keluarga tidak dapat memberikan perawatan', 'Hargai dan dukung mekanisme koping adaptif yang digunakan', 'Berikan kesempatan berkunjung bagi anggota keluarga'], 'edukasi' => ['Informasikan kemajuan pasien secara berkala', 'Informasikan fasilitas perawatan kesehatan yang tersedia'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0098 — Penyangkalan Tidak Efektif (updated from PDF p.483-485)
            ['diagkep_id' => 'D.0098', 'diagkep_desc' => 'Penyangkalan Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Upaya mengingkari pemahaman atau makna suatu peristiwa secara sadar atau tidak sadar untuk menurunkan kecemasan/ketakutan yang dapat menyebabkan gangguan kesehatan', 'penyebab' => ['fisiologis' => ['Kecemasan', 'Ketakutan terhadap kematian', 'Ketakutan mengalami kehilangan kemandirian', 'Ketakutan terhadap perpisahan', 'Ketidakefektifan strategi koping', 'Ketidakpercayaan terhadap kemampuan mengatasi masalah', 'Ancaman terhadap realitas yang tidak menyenangkan']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak mengakui dirinya mengalami gejala atau bahaya (walaupun kenyataan sebaliknya)'], 'objektif' => ['Menunda mencari pertolongan pelayanan kesehatan']], 'gejala_tanda_minor' => ['subjektif' => ['Mengaku tidak takut dengan kematian', 'Mengaku tidak takut dengan penyakit kronis', 'Tidak mengakui bahwa penyakit berdampak pada pola hidup'], 'objektif' => ['Melakukan pengobatan mandiri', 'Mengalihkan sumber gejala ke orang lain', 'Berperilaku acuh tak acuh saat membicarakan peristiwa penyebab stress', 'Menunjukkan afek yang tidak sesuai']], 'kondisi_klinis_terkait' => ['Penyakit kronis', 'Intoksikasi zat', 'Penyakit Alzheimer', 'Penyakit terminal']], 'slki' => [['kode' => 'L.09082', 'nama' => 'Penerimaan', 'kriteria_hasil' => ['Verbalisasi penerimaan meningkat', 'Verbalisasi perasaan yang dialami meningkat', 'Perilaku mencari perawatan/pengobatan meningkat', 'Menyusun perencanaan masa depan meningkat']]], 'siki' => [['kode' => 'I.09311', 'nama' => 'Promosi Kesadaran Diri', 'definisi' => 'Meningkatkan pemahaman dan mengeksplorasi pikiran, perasaan, motivasi, dan perilaku', 'tindakan' => ['observasi' => ['Identifikasi keadaan emosional saat ini', 'Identifikasi respons yang ditunjukkan berbagai situasi'], 'terapeutik' => ['Diskusikan nilai-nilai yang berkontribusi terhadap konsep diri', 'Diskusikan tentang pikiran, perilaku atau respons terhadap kondisi', 'Diskusikan dampak penyakit pada konsep diri', 'Ungkapkan penyangkalan tentang kenyataan', 'Motivasi dalam meningkatkan kemampuan belajar'], 'edukasi' => ['Anjurkan mengenali pikiran dan perasaan tentang diri', 'Anjurkan menyadari bahwa setiap orang unik', 'Anjurkan mengungkapkan perasaan (mis. marah atau depresi)', 'Anjurkan meminta bantuan orang lain, sesuai kebutuhan', 'Anjurkan mengubah pandangan diri sebagai korban', 'Anjurkan mengidentifikasi perasaan bersalah', 'Anjurkan mengidentifikasi situasi yang memicu kecemasan', 'Anjurkan mengevaluasi kembali persepsi negatif tentang diri', 'Anjurkan dalam mengekspresikan diri dengan kelompok sebaya', 'Ajarkan cara membuat prioritas hidup', 'Latih kemampuan positif diri yang dimiliki'], 'kolaborasi' => []]]]]],
            // D.0099 — Perilaku Kesehatan Cenderung Berisiko (updated from PDF p.485-486)
            ['diagkep_id' => 'D.0099', 'diagkep_desc' => 'Perilaku Kesehatan Cenderung Berisiko', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Hambatan kemampuan dalam mengubah gaya hidup/perilaku untuk memperbaiki status kesehatan', 'penyebab' => ['fisiologis' => ['Kurang terpapar informasi', 'Ketidakadekuatan dukungan sosial', 'Self efficacy rendah', 'Status sosio-ekonomi rendah', 'Stresor berlebihan', 'Sikap negatif terhadap pelayanan kesehatan', 'Pemilihan gaya hidup tidak sehat (mis. merokok, konsumsi alkohol berlebihan)']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Menunjukkan penolakan terhadap perubahan status kesehatan', 'Gagal melakukan tindakan pencegahan masalah kesehatan']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Gagal mencapai pengendalian yang optimal', 'Menunjukkan upaya peningkatan status kesehatan yang minimal']], 'kondisi_klinis_terkait' => ['Kondisi baru terdiagnosis penyakit', 'Kondisi perubahan gaya hidup baru akibat penyakit', 'Tumor otak', 'Penyalahgunaan zat', 'Gangguan kepribadian dan psikotik', 'Depresi/psikosis pasca persalinan']], 'slki' => [['kode' => 'L.03025', 'nama' => 'Perilaku Kesehatan', 'kriteria_hasil' => ['Penerimaan terhadap perubahan status kesehatan dari skala 1 meningkat menjadi skala 5 meningkat', 'Kemampuan melakukan tindakan pencegahan masalah dari skala 1 meningkat menjadi skala 5 meningkat', 'Kemampuan peningkatan kesehatan dari skala 1 meningkat menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.12472', 'nama' => 'Promosi Perilaku Upaya Kesehatan', 'definisi' => 'Meningkatkan perubahan perilaku penderita/klien agar memiliki kemauan dan kemampuan yang kondusif bagi kesehatan secara menyeluruh baik bagi lingkungan maupun masyarakat sekitarnya', 'tindakan' => ['observasi' => ['Identifikasi perilaku upaya kesehatan yang dapat ditingkatkan'], 'terapeutik' => ['Berikan lingkungan yang mendukung kesehatan', 'Orientasi pelayanan kesehatan yang dapat dimanfaatkan'], 'edukasi' => ['Anjurkan persalinan ditolong oleh tenaga kesehatan', 'Anjurkan memberi bayi ASI Ekslusif', 'Anjurkan menimbang balita setiap bulan', 'Anjurkan menggunakan air bersih', 'Anjurkan mencuci tangan dengan air bersih dan sabun', 'Anjurkan menggunakan jamban sehat', 'Anjurkan memberantas jentik di rumah seminggu sekali', 'Anjurkan makan sayur dan buah setiap hari', 'Anjurkan melakukan aktivitas fisik setiap hari', 'Anjurkan tidak merokok di dalam rumah'], 'kolaborasi' => []]]]]],
            // D.0100 — Risiko Distres Spiritual (updated from PDF p.486-488)
            ['diagkep_id' => 'D.0100', 'diagkep_desc' => 'Risiko Distres Spiritual', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Berisiko mengalami gangguan keyakinan atau sistem nilai pada individu atau kelompok berupa kekuatan, harapan dan makna hidup', 'penyebab' => [], 'faktor_risiko' => ['Perubahan hidup', 'Perubahan lingkungan', 'Bencana alam', 'Sakit kronis', 'Sakit fisik', 'Penyalahgunaan zat', 'Kecemasan', 'Perubahan dalam ritual agama', 'Perubahan dalam praktik spiritual', 'Konflik spiritual', 'Depresi', 'Ketidakmampuan memaafkan', 'Kehilangan', 'Harga diri rendah', 'Hubungan buruk', 'Konflik rasial', 'Berpisah dengan sistem pendukung', 'Stres'], 'kondisi_klinis_terkait' => ['Penyakit kronis (mis. arthritis rheumatoid, sklerosis multiple)', 'Penyakit terminal (mis. kanker)', 'Retardasi mental', 'Kehilangan ekstermitas', 'Sudden infant death syndrome (SIDS)', 'Kelahiran mati, kematian janin, keguguran, kemandulan']], 'slki' => [['kode' => 'L.09091', 'nama' => 'Status Spiritual', 'kriteria_hasil' => ['Verbalisasi makna dan tujuan hidup dari skala 1 meningkat menjadi skala 5 meningkat', 'Verbalisasi kepuasan terhadap makna hidup dari skala 1 meningkat menjadi skala 3 sedang', 'Perilaku marah pada Tuhan dari skala 1 meningkat menjadi skala 5 menurun', 'Kemampuan beribadah dari skala 1 meningkat menjadi skala 3 sedang']]], 'siki' => [['kode' => 'I.09269', 'nama' => 'Dukungan Perkembangan Spiritual', 'definisi' => 'Memfasilitasi pengembangan kemampuan mengidentifikasi, berhubungan, dan mencari sumber makna, tujuan, kekuatan dan harapan dalam hidup', 'tindakan' => ['terapeutik' => ['Sediakan lingkungan yang tenang untuk refleksi diri', 'Fasilitasi mengidentifikasi masalah spiritual', 'Fasilitasi mengidentifikasi hambatan dalam pengenalan diri', 'Fasilitasi mengeksplorasi keyakinan terkait pemulihan tubuh, pikiran, dan jiwa', 'Fasilitasi hubungan persahabatan dengan orang lain dan pelayanan keagamaan'], 'edukasi' => ['Anjurkan membuat komitmen spiritual berdasarkan keyakinan dan nilai', 'Anjurkan berpartisipasi dalam kegiatan ibadah (hari raya, ritual) dan meditasi'], 'kolaborasi' => ['Rujuk pada pemuka agama/kelompok agama, jika perlu', 'Rujuk kepada kelompok pendukung, swabantu, atau program spiritual, jika perlu']]]]]],
            // D.0101 — Risiko Harga Diri Rendah Kronis (updated from PDF p.488-502)
            ['diagkep_id' => 'D.0101', 'diagkep_desc' => 'Risiko Harga Diri Rendah Kronis', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Berisiko mengalami evaluasi atau perasaan negatif terhadap diri sendiri atau kemampuan klien yang berlangsung dalam waktu lama dan terus menerus', 'penyebab' => [], 'faktor_risiko' => ['Gangguan psikiatrik', 'Kegagalan berulang', 'Ketidaksesuaian budaya', 'Ketidaksesuaian spiritual', 'Ketidakefektifan koping terhadap kehilangan', 'Kurang mendapat kasih sayang', 'Kurang keterlibatan dalam kelompok/masyarakat', 'Kurang penghargaan dari orang lain', 'Ketidakmampuan menunjukkan perasaan', 'Perasaan kurang didukung orang lain', 'Pengalaman traumatik'], 'kondisi_klinis_terkait' => ['Penyakit kronis', 'Penyakit degeneratif', 'Gangguan perilaku', 'Gangguan perkembangan', 'Gangguan mental', 'Penyalahgunaan zat', 'Gangguan mood', 'Trauma', 'Pasca pembedahan', 'Kehilangan fungsi tubuh']], 'slki' => [['kode' => 'L.09069', 'nama' => 'Harga Diri', 'kriteria_hasil' => ['Penilaian diri positif dari menurun skala 1 menjadi skala 5 meningkat', 'Perasaan memiliki kelebihan atau kemampuan positif dari menurun skala 1 menjadi skala 5 meningkat', 'Penerimaan penilaian positif terhadap diri sendiri dari menurun skala 1 menjadi skala 5 meningkat', 'Minat mencoba hal baru dari menurun skala 1 menjadi skala 5 meningkat', 'Berjalan menampakkan wajah dari menurun skala 1 menjadi skala 5 meningkat', 'Postur tubuh menampakkan wajah dari menurun skala 1 menjadi skala 5 meningkat', 'Konsentrasi dari menurun skala 1 menjadi skala 5 meningkat', 'Tidur dari menurun skala 1 menjadi skala 5 meningkat', 'Kontak mata dari menurun skala 1 menjadi skala 5 meningkat', 'Gairah aktivitas aktif dari menurun skala 1 menjadi skala 5 meningkat', 'Perilaku asertif dari menurun skala 1 menjadi skala 5 meningkat', 'Percaya diri berbicara dari menurun skala 1 menjadi skala 5 meningkat', 'Kemampuan membuat keputusan dari menurun skala 1 menjadi skala 5 meningkat', 'Perasaan malu dari meningkat skala 1 menjadi menurun skala 5', 'Perasaan bersalah dari meningkat skala 1 menjadi menurun skala 5', 'Perasaan tidak mampu melakukan apapun dari menurun skala 1 menjadi menurun skala 5', 'Meremehkan kemampuan mengatasi masalah dari meningkat skala 1 menjadi skala 5 menurun', 'Ketergantungan pada penguatan secara berlebihan dari meningkat skala 1 menjadi menurun skala 5', 'Pencarian penguatan secara berlebihan dari meningkat skala 1 menjadi menurun skala 5']]], 'siki' => [['kode' => 'I.09308', 'nama' => 'Promosi Harga Diri', 'definisi' => 'Meningkatkan penilaian perasaan/persepsi terhadap diri sendiri atau kemampuan diri', 'tindakan' => ['observasi' => ['Identifikasi budaya, agama, ras, jenis kelamin, dan usia terhadap harga diri', 'Monitor verbalisasi yang merendahkan diri sendiri', 'Monitor tingkat harga diri setiap waktu, sesuai kebutuhan'], 'terapeutik' => ['Motivasi terlibat dalam verbalisasi positif untuk diri sendiri', 'Motivasi menerima tantangan atau hal baru', 'Diskusikan pernyataan tentang harga diri', 'Diskusikan kepercayaan terhadap penilaian diri', 'Diskusikan pengalaman yang meningkatkan harga diri', 'Diskusikan persepsi negatif diri', 'Diskusikan alasan mengkritik diri atau rasa bersalah', 'Diskusikan penetapan tujuan realistis untuk mencapai harga diri yang lebih tinggi', 'Diskusikan bersama keluarga untuk menetapkan harapan dan batasan yang jelas', 'Berikan umpan balik positif atas peningkatan mencapai tujuan', 'Fasilitasi lingkungan dan aktivitas yang meningkatkan harga diri'], 'edukasi' => ['Jelaskan kepada keluarga pentingnya dukungan dalam perkembangan konsep positif diri pasien', 'Anjurkan mengidentifikasi kekuatan yang dimiliki', 'Anjurkan mempertahankan kontak mata saat berkomunikasi dengan orang lain', 'Anjurkan membuka diri terhadap kritik negatif', 'Anjurkan mengevaluasi perilaku', 'Ajarkan cara mengatasi bullying', 'Latih peningkatan tanggung jawab untuk diri sendiri', 'Latih pernyataan/kemampuan positif diri', 'Latih cara berfikir dan berperilaku positif', 'Latih meningkatkan kepercayaan pada kemampuan dalam menangani situasi'], 'kolaborasi' => []]]]]],
            // D.0102 — Risiko Harga Diri Rendah Situasional (updated from PDF p.503-504)
            ['diagkep_id' => 'D.0102', 'diagkep_desc' => 'Risiko Harga Diri Rendah Situasional', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Berisiko mengalami evaluasi atau perasaan negatif terhadap diri sendiri atau kemampuan klien sebagai respon terhadap situasi saat ini', 'penyebab' => [], 'faktor_risiko' => ['Gangguan gambaran diri', 'Gangguan fungsi', 'Gangguan peran sosial', 'Harapan tidak realistis', 'Kurang pemahaman terhadap situasi', 'Penurunan kontrol terhadap lingkungan', 'Penyakit fisik', 'Perilaku tidak sesuai dengan nilai setempat', 'Kegagalan', 'Perasaan tidak berdaya', 'Riwayat kehilangan', 'Riwayat pengabaian', 'Riwayat penolakan', 'Riwayat penganiayaan (mis. fisik, psikologis, seksual)', 'Transisi perkembangan'], 'kondisi_klinis_terkait' => ['Cedera traumatis', 'Pembedahan', 'Kehamilan', 'Kondisi baru terdiagnosa (mis. diabetes melitu)', 'Stroke', 'Penyalahgunaan zat', 'Demensia']], 'slki' => [['kode' => 'L.09069', 'nama' => 'Harga Diri', 'kriteria_hasil' => ['Penilaian diri positif meningkat dari skala 1 (menurun) menjadi skala 5 (meningkat)', 'Perasaan malu menurun dari skala 1 (meningkat) menjadi skala 5 (menurun)']], ['kode' => 'L.09067', 'nama' => 'Citra Tubuh', 'kriteria_hasil' => ['Verbalisasi perasaan negatif tentang perubahan tubuh menurun dari skala 1 (meningkat) menjadi skala 5 (menurun)']]], 'siki' => [['kode' => 'I.09312', 'nama' => 'Promosi Koping', 'definisi' => 'Meningkatkan upaya kognitif dan perilaku untuk menilai dan merespon stressor dan/atau kemampuan menggunakan sumber-sumber yang ada', 'tindakan' => ['observasi' => ['Identifikasi kegiatan jangka pendek dan panjang sesuai tujuan', 'Identifikasi kemampuan yang dimiliki', 'Identifikasi sumber daya yang tersedia untuk memenuhi tujuan', 'Identifikasi pemahaman proses penyakit', 'Identifikasi dampak situasi terhadap peran dan hubungan', 'Identifikasi metode penyelesaian masalah', 'Identifikasi kebutuhan dan keinginan terhadap dukungan sosial'], 'terapeutik' => ['Diskusikan perubahan peran yang dialami', 'Gunakan pendekatan yang tenang dan meyakinkan', 'Diskusikan alasan mengkritik diri sendiri', 'Diskusikan untuk mengklarifikasi kesalahpahaman dan mengevaluasi perilaku sendiri', 'Diskusikan konsekuensi tidak menggunakan rasa bersalah dan rasa malu', 'Diskusikan risiko yang menimbulkan bahaya pada diri sendiri', 'Fasilitasi dalam memperoleh informasi yang dibutuhkan', 'Berikan pilihan realistis mengenai aspek-aspek tertentu dalam perawatan', 'Motivasi untuk menentukan harapan yang realistis', 'Tinjau kembali kemampuan dalam pengambilan keputusan', 'Hindari mengambil keputusan saat pasien berada di bawah tekanan', 'Motivasi terlibat dalam kegiatan sosial', 'Motivasi mengidentifikasi sistem pendukung yang tersedia', 'Dampingi saat berduka (mis. penyakit kronis, kecacatan)', 'Perkenalkan dengan orang atau kelompok yang berhasil mengalami pengalaman sama', 'Dukung penggunaan mekanisme pertahanan yang tepat', 'Kurangi rangsangan lingkungan yang mengancam'], 'edukasi' => ['Anjurkan menjalin hubungan yang memiliki kepentingan dan tujuan sama', 'Anjurkan penggunaan sumber spiritual, jika perlu', 'Anjurkan mengungkapkan perasaan dan persepsi', 'Anjurkan keluarga terlibat', 'Anjurkan membuat tujuan yang lebih spesifik', 'Ajarkan cara memecahkan masalah secara konstruktif', 'Latih penggunaan teknik relaksasi', 'Latih keterampilan sosial, sesuai kebutuhan', 'Latih mengembangkan penilaian obyektif'], 'kolaborasi' => []]]]]],
            // D.0103 — Risiko Ketidakberdayaan (updated from PDF p.511-512)
            ['diagkep_id' => 'D.0103', 'diagkep_desc' => 'Risiko Ketidakberdayaan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Persepsi bahwa tindakan seseorang tidak akan memengaruhi hasil secara signifikan; persepsi kurang kontrol pada situasi saat ini atau yang akan datang', 'penyebab' => [], 'faktor_risiko' => ['Perjalanan penyakit yang berlangsung lama atau tidak dapat diprediksi', 'Harga diri rendah yang berlangsung lama', 'Status ekonomi rendah', 'Ketidakmampuan mengatasi masalah', 'Kurang dukungan sosial', 'Penyakit yang melemahkan secara progresif', 'Marginalisasi sosial', 'Kondisi terstigma', 'Penyakit terstigma', 'Kurang terpapar informasi', 'Kecemasan'], 'kondisi_klinis_terkait' => ['Diagnosis yang tidak terduga atau baru', 'Peristiwa traumatis', 'Diagnosis penyakit kronis', 'Diagnosis penyakit terminal', 'Rawat inap']], 'slki' => [['kode' => 'L.09071', 'nama' => 'Keberdayaan', 'kriteria_hasil' => ['Verbalisasi mampu melaksanakan aktivitas meningkat', 'Verbalisasi frustasi ketergantungan pada orang lain meningkat']]], 'siki' => [['kode' => 'I.09307', 'nama' => 'Promosi Harapan', 'definisi' => 'Meningkatkan kepercayaan pada kemampuan untuk memulai dan mempertahankan tindakan', 'tindakan' => ['observasi' => ['Identifikasi harapan pasien dan keluarga dalam pencapaian hidup'], 'terapeutik' => ['Sadarkan bahwa kondisi sekarang memiliki nilai penting', 'Pandu kembali mengingat kenangan yang menyenangkan', 'Libatkan pasien secara aktif dalam perawatan', 'Kembangkan rencana perawatan yang melibatkan tingkat pencapaian tujuan sederhana sampai dengan kompleks', 'Berikan kesempatan kepada pasien dan keluarga terlibat dengan dukungan kelompok', 'Ciptakan lingkungan yang memudahkan mempraktikkan kebutuhan spiritual'], 'edukasi' => ['Anjurkan mengungkapkan perasaan terhadap kondisi dengan realistis', 'Anjurkan mempertahankan hubungan (mis. menyebutkan nama orang yang dicintai)', 'Anjurkan mempertahankan hubungan terapeutik dengan orang lain', 'Latih menyusun tujuan sesuai yang diharapkan', 'Latih cara mengembangkan spiritual diri', 'Latih cara mengenang dan menikmati masa lalu (mis. prestasi, pengalaman)'], 'kolaborasi' => []]]]]],
            // D.0104 — Sindrom Pasca Trauma (updated from PDF p.512-515)
            ['diagkep_id' => 'D.0104', 'diagkep_desc' => 'Sindrom Pasca Trauma', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Respon maladaptif berkelanjutan terhadap kejadian trauma', 'penyebab' => ['fisiologis' => ['Bencana', 'Peperangan', 'Riwayat korban kekerasan', 'Kecelakaan', 'Saksi pembunuhan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan secara berlebihan atau menghindari pembicaraan kejadian trauma', 'Merasa cemas', 'Teringat kembali kejadian traumatis'], 'objektif' => ['Memori masa lalu terganggu', 'Mimpi buruk berulang', 'Ketakutan berulang', 'Menghindari aktivitas, tempat atau orang yang membangkitkan kejadian trauma']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak percaya pada orang lain', 'Menyalahkan diri sendiri'], 'objektif' => ['Konfusi atau disosiasi', 'Gangguan interprestasi realitas', 'Sulit berkonsentrasi', 'Waspada berlebihan', 'Pola hidup terganggu', 'Tidur terganggu', 'Merusak diri sendiri (mis. konsumsi alkohol, penggunaan zat, percobaan bunuh diri, tindakan kriminal)', 'Minat berinteraksi dengan orang lain menurun']], 'kondisi_klinis_terkait' => ['Korban kekerasan', 'Post traumatic stress disorder (PTSD)', 'Korban bencana alam', 'Multiple personality disorder', 'Korban kekerasan seksual', 'Korban peperangan', 'Cedera multiple (kecelakaan lalu lintas)']], 'slki' => [['kode' => 'L.09073', 'nama' => 'Ketahanan Personal', 'kriteria_hasil' => ['Verbalisasi harapan yang positif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menggunakan strategi koping yang efektif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Verbalisasi perasaan menunjukkan harga diri positif dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Mengambil tanggung jawab mencari dukungan emosional dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menganggap kesulitan sebagai tantangan dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menggunakan strategi untuk meningkatkan keamanan dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menghindari bahaya dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menghindari penyalahgunaan obat dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Menahan diri menyakiti orang lain dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Mengidentifikasi model peran mengidentifikasi sumber daya dikomunitas dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Memanfaatkan sumber daya komunitas dari menurun skala 1 dapat menjadi skala 5 meningkat', 'Verbalisasi kesiapan untuk belajar dari menurun skala 1 dapat menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.09274', 'nama' => 'Dukungan Proses Berduka', 'definisi' => 'Memfasilitasi menyelesaikan proses berduka terhadap kehilangan yang bermakna', 'tindakan' => ['observasi' => ['Identifikasi kehilangan yang dihadapi', 'Identifikasi proses berduka yang dialami', 'Identifikasi sifat ketertarikan pada benda yang hilang atau orang yang meninggal', 'Identifikasi reaksi awal terhadap kehilangan'], 'terapeutik' => ['Tunjukkan sikap menerima dan empati', 'Motivasi agar mau mengungkapkan perasaan kehilangan', 'Motivasi untuk menguatkan dukungan keluarga atau orang terdekat', 'Fasilitasi melakukan kebiasaan sesuai dengan budaya, agama dan norma sosial', 'Fasilitasi mengekspresikan perasaan dengan cara yang nyaman (mis. membaca buku, menulis, menggambar atau bermain)', 'Diskusikan strategi koping yang dapat digunakan'], 'edukasi' => ['Jelaskan kepada pasien dan keluarga bahwa sikap mengingkari, marah, tawar, sepresi, dan menerima adalah wajar dalam menghadapi kehilangan', 'Anjurkan mengidentifikasi ketakutan terbesar pada kehilangan', 'Anjurkan mengekspresikan perasaan tentang kehilangan', 'Ajarkan melewati proses berduka secara bertahap'], 'kolaborasi' => []]]]]],
            // D.0105 — Waham (updated from PDF p.516-518)
            ['diagkep_id' => 'D.0105', 'diagkep_desc' => 'Waham', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Integritas Ego', 'definisi' => 'Keyakinan yang keliru tentang isi pikiran yang dipertahankan secara kuat atau terus menerus namun tidak sesuai dengan kenyataan', 'penyebab' => ['fisiologis' => ['Faktor biologis: kelainan genetik atau keturunan, kelainan neurologis (mis. gangguan sistem lindik, gangguan ganglia basalis, tumor otak)', 'Faktor psikodinamik (mis. isolasi sosial, hipersensitif)', 'Maladaptasi', 'Stress berlebihan']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan isi waham'], 'objektif' => ['Menunjukkan perilaku sesuai isi waham', 'Isi pikiran tidak sesuai realitas', 'Isi pembicaraan sulit dimengerti']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa sulit berkonsentrasi', 'Merasa khawatir'], 'objektif' => ['Curiga berlebihan', 'Waspada berlebihan', 'Bicara berlebihan', 'Sikap menentang atau permusuhan', 'Wajah tegang', 'Pola tidur berubah', 'Tidak mampu mengambil keputusan', 'Flight of idea', 'Produktifitas kerja menurun', 'Tidak mampu merawat diri', 'Menarik diri']], 'kondisi_klinis_terkait' => ['Skizofrenia', 'Gangguan sistem lindik', 'Gangguan ganglia basalis', 'Tumor otak', 'Depresi']], 'slki' => [['kode' => 'L.09090', 'nama' => 'Status Orientasi', 'kriteria_hasil' => ['Perilaku sesuai realitas meningkat dengan skala 5', 'Isi pikir sesuai realita meningkat dengan skala 5', 'Verbalisasi waham menurun dengan skala 5', 'Perilaku waham menurun dengan skala 5', 'Pembicaraan membaik dengan skala 5']]], 'siki' => [['kode' => 'I.09295', 'nama' => 'Manajemen Waham', 'definisi' => 'Mengidentifikasi dan mengelola kenyamanan, keamanan, dan orientasi realitas pasien yang mengalami keyakinan yang keliru dan menetap yang sedikit atau sama sekali tidak berdasar pada kenyataan', 'tindakan' => ['observasi' => ['Monitor waham yang isinya membahayakan diri sendiri, orang lain dan lingkungan', 'Monitor efek terapeutik dan efek samping obat'], 'terapeutik' => ['Bina hubungan interpersonal saling percaya', 'Tunjukan sikap tidak menghakimi secara konsisten', 'Diskusikan waham dengan berfokus pada perasaan yang mendasari waham', 'Hindari perdebatan tentang keyakinan yang keliru, nyatakan keraguan sesuai fakta', 'Hindari memperkuat gagasan waham', 'Sediakan lingkungan aman dan nyaman', 'Berikan aktivitas rekreasi dan pengalihan sesuai kebutuhan', 'Lakukan intervensi pengentrolan perilaku waham (mis. limit setting, pembatasan wilayah, penegakan fisik, atau seklusi)'], 'edukasi' => ['Anjurkan mengungkapkan dan memvalidasi waham (uji realitas) dengan orang yang dipercaya (pemberian asuhan atau keluarga)', 'Anjurkan melakukan rutinitas harian secara konsisten', 'Latihan manajemen stress', 'Jelaskan tentang waham serta penyakit terkait (mis. delirium, skizofrenia, atau depresi), cara mengatasi dan obat yang diberikan'], 'kolaborasi' => ['Kolaborasi pemberian obat, sesuai indikasi']]]]]],
            // D.0106 — Gangguan Tumbuh Kembang (updated from PDF p.518-521)
            ['diagkep_id' => 'D.0106', 'diagkep_desc' => 'Gangguan Tumbuh Kembang', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Pertumbuhan dan Perkembangan', 'definisi' => 'Kondisi individu mengalami gangguan kemampuan bertumbuh dan berkembang sesuai dengan kelompok usia', 'penyebab' => ['fisiologis' => ['Efek ketidakmampuan fisik', 'Keterbatasan lingkungan', 'Inkonsistensi respon', 'Pengabaian', 'Terpisah dari orang tua dan/atau orang terdekat', 'Defisiensi stimulus']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak mampu melakukan keterampilan atau perilaku khas sesuai usia (fisik, bahasa, motorik, psikososial)', 'Pertumbuhan fisik terganggu']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak mampu melakukan perawatan diri sesuai usia', 'Afek datar', 'Respon sosial lambat', 'Kontak mata terbatas', 'Nafsu makan menurun', 'Lesu', 'Mudah marah', 'Regresi', 'Pola tidur terganggu (pada bayi)']], 'kondisi_klinis_terkait' => ['Hipotiroidisme', 'Sindrom gagal tumbuh (Failure to Thrive Syndrome)', 'Leukemia', 'Defisiensi hormon pertumbuhan', 'Demensia', 'Delirium', 'Kelainan jantung bawaan', 'Penyakit kronis', 'Gangguan kepribadian (personality disorder)']], 'slki' => [['kode' => 'L.10101', 'nama' => 'Status Perkembangan', 'kriteria_hasil' => ['Keterampilan/perilaku sesuai usia meningkat', 'Kemampuan melakukan perawatan diri meningkat']]], 'siki' => [['kode' => 'I.10339', 'nama' => 'Perawatan Perkembangan', 'definisi' => 'Mengidentifikasi dan merawat untuk memfasilitasi perkembangan yang optimal pada aspek motorik halus, motorik kasar, bahasa, kognitif, sosial, emosional di tiap tahapan usia anak', 'tindakan' => ['observasi' => ['Identifikasi pencapaian tugas perkembangan anak', 'Identifikasi isyarat perilaku dan fisiologis yang ditunjukkan bayi (mis. lapar, tidak nyaman)'], 'terapeutik' => ['Pertahankan sentuhan seminimal mungkin pada bayi prematur', 'Berikan sentuhan yang bersifat gentle dan tidak ragu-ragu', 'Minimalkan nyeri', 'Minimalkan kebisingan ruangan', 'Pertahankan lingkungan yang mendukung perkembangan optimal', 'Motivasi anak berinteraksi dengan anak lain', 'Sediakan aktivitas yang memotivasi anak berinteraksi dengan anak lainnya', 'Fasilitasi anak berbagi dan bergantian/bergilir', 'Dukung anak mengekspresikan diri melalui penghargaan positif atau umpan balik atas usahanya', 'Pertahankan kenyamanan anak', 'Fasilitasi anak melatih keterampilan pemenuhan kebutuhan secara mandiri (mis. makan, sikat gigi, cuci tangan, memakai baju)', 'Bernyanyi bersama anak lagu-lagu yang disukai', 'Bacakan cerita atau dongeng', 'Dukung partisipasi anak di sekolah, ekstrakurikuler dan aktivitas komunitas'], 'edukasi' => ['Jelaskan orang tua dan/atau pengasuh tentang milestone perkembangan anak dan perilaku anak', 'Anjurkan orang tua menyentuh dan menggendong bayinya', 'Anjurkan orang tua berinteraksi dengan anaknya', 'Ajarkan anak keterampilan berinteraksi', 'Ajarkan anak teknik asertif'], 'kolaborasi' => ['Rujuk untuk konseling, jika perlu']]]]]],
            // D.0107 — Risiko Gangguan Perkembangan (updated from PDF p.526-528)
            ['diagkep_id' => 'D.0107', 'diagkep_desc' => 'Risiko Gangguan Perkembangan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Pertumbuhan dan Perkembangan', 'definisi' => 'Berisiko mengalami gangguan untuk berkembang sesuai dengan kelompok usianya', 'penyebab' => [], 'faktor_risiko' => ['Ketidakadekuatan nutrisi', 'Ketidakadekuatan perawatan prenatal', 'Keterlambatan perawatan prenatal', 'Usia hamil di bawah 15 tahun', 'Usia hamil di atas 35 tahun', 'Kehamilan tidak terencana', 'Kehamilan tidak diinginkan', 'Gangguan endokrin', 'Prematuritas', 'Kelainan genetik/kongenital', 'Kerusakan otak (mis. perdarahan selama periode pascanatal, penganiayaan kecelakaan)', 'Penyakit kronis', 'Infeksi', 'Efek samping terapi (mis. kemoterapi, terapi radiasi, agen farmakologis)', 'Penganiayaan (mis. fisik, psikologis, seksual)', 'Gangguan pendengaran', 'Gangguan penglihatan', 'Penyalahgunaan zat', 'Ketidakmampuan belajar', 'Anak adopsi', 'Kejadian bencana', 'Ekonomi lemah'], 'kondisi_klinis_terkait' => ['Hipotiroidisme', 'Sindrom gagal tumbuh (Failure to Thrive Syndrome)', 'Leukemia', 'Defisiensi hormon pertumbuhan', 'Demensia', 'Delirium', 'Kelainan jantung bawaan', 'Penyakit kronis', 'Gangguan kepribadian (personality disorder)']], 'slki' => [['kode' => 'L.10101', 'nama' => 'Status Perkembangan', 'kriteria_hasil' => ['Keterampilan/perilaku sesuai usia meningkat', 'Kemampuan melakukan perawatan diri meningkat']]], 'siki' => [['kode' => 'I.10340', 'nama' => 'Promosi Perkembangan Anak', 'definisi' => 'Meningkatkan dan memfasilitasi kemampuan orangtua/pengasuh untuk mengoptimalkan perkembangan motorik kasar, motorik halus, bahasa, kognitif, sosial dan emosional pada anak usia prasekolah dan usia sekolah', 'tindakan' => ['observasi' => ['Identifikasi kebutuhan khusus anak dan kemampuan adaptasi anak'], 'terapeutik' => ['Fasilitasi hubungan anak dengan teman sebaya', 'Dukung anak berinteraksi dengan anak lain', 'Dukung anak mengekspresikan perasaannya secara positif', 'Dukung anak dalam bermimpi atau berfantasi sewajarnya', 'Dukung partisipasi anak di sekolah, ekstrakurikuler dan aktivitas komunitas', 'Berikan mainan yang sesuai dengan usia anak', 'Bernyanyi bersama anak lagu-lagu yang disukai anak', 'Bacakan cerita/dongeng untuk anak', 'Diskusikan bersama remaja tujuan dan harapannya', 'Sediakan kesempatan dan alat-alat untuk menggambar, melukis, dan mewarnai', 'Sediakan mainan berupa puzzle dan maze'], 'edukasi' => ['Jelaskan nama-nama benda obyek yang ada di lingkungan sekitar', 'Ajarkan pengasuh milestones perkembangan dan perilaku yang dibentuk', 'Ajarkan sikap kooperatif, bukan kompetisi diantara anak', 'Ajarkan anak cara meminta bantuan dari anak lain, jika perlu', 'Ajarkan teknik asertif pada anak dan remaja', 'Demonstrasikan kegiatan yang meningkatkan perkembangan pada pengasuh'], 'kolaborasi' => ['Rujuk untuk konseling, jika perlu']]]]]],
            // D.0108 — Risiko Gangguan Pertumbuhan (updated from PDF p.528-530)
            ['diagkep_id' => 'D.0108', 'diagkep_desc' => 'Risiko Gangguan Pertumbuhan', 'diagkep_json' => ['sdki' => ['kategori' => 'Psikologis', 'subkategori' => 'Pertumbuhan dan Perkembangan', 'definisi' => 'Berisiko mengalami gangguan untuk bertumbuh sesuai dengan kelompok usianya', 'penyebab' => [], 'faktor_risiko' => ['Ketidakadekuatan nutrisi', 'Penyakit kronis', 'Nafsu makan tidak terkontrol', 'Prematuritas', 'Terpapar teratogen', 'Ketidakadekuatan nutrisi maternal', 'Proses infeksi', 'Proses infeksi maternal', 'Perilaku makan maladaptif', 'Penyalahgunaan zat', 'Kelainan genetik/kongenital', 'Penganiayaan (mis. fisik, psikologis, seksual)', 'Ekonomi lemah'], 'kondisi_klinis_terkait' => ['Hipotiroidisme', 'Sindrom gagal tumbuh (Failure to Thrive Syndrome)', 'Leukemia', 'Defisiensi hormon pertumbuhan', 'Demensia', 'Delirium', 'Kelainan jantung bawaan', 'Penyakit kronis', 'Gangguan kepribadian (personality disorder)']], 'slki' => [['kode' => 'L.10102', 'nama' => 'Status Pertumbuhan', 'kriteria_hasil' => ['Berat badan sesuai usia dari skala 1 menurun menjadi skala 5 meningkat', 'Panjang/tinggi badan sesuai usia dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.03119', 'nama' => 'Manajemen Nutrisi', 'definisi' => 'Mengidentifikasi dan mengelola asupan nutrisi yang seimbang', 'tindakan' => ['observasi' => ['Identifikasi status nutrisi', 'Identifikasi alergi dan intoleransi makanan', 'Identifikasi makanan yang disukai', 'Identifikasi kebutuhan kalori dan jenis nutrien', 'Identifikasi perlunya penggunaan selang nasogastric', 'Monitor asupan makanan', 'Monitor berat badan', 'Monitor hasil pemeriksaan'], 'terapeutik' => ['Lakukan oral hygiene sebelum makan, jika perlu', 'Fasilitasi menentukan pedoman diet (mis. piramida makanan)', 'Sajikan makanan secara menarik dan suhu yang sesuai', 'Berikan makanan tinggi serat untuk mencegah konstipasi', 'Berikan makanan tinggi kalori dan tinggi protein', 'Berikan suplemen makanan, jika perlu', 'Hentikan pemberian makan melalui selang nasogastrik, jika asupan oral dapat ditoleransi'], 'edukasi' => ['Anjurkan posisi duduk, jika mampu', 'Ajarkan diet yang diprogramkan'], 'kolaborasi' => ['Kolaborasi pemberian medikasi sebelum makan (mis. pereda nyeri, antiemetic), jika perlu', 'Kolaborasi dengan ahli gizi untuk menentukan jumlah kalori dan jenis nutrien yang dibutuhkan, jika perlu']]]]]],
            // D.0109 — Defisit Perawatan Diri (updated from PDF p.531-534)
            ['diagkep_id' => 'D.0109', 'diagkep_desc' => 'Defisit Perawatan Diri', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Kebersihan Diri', 'definisi' => 'Tidak mampu melakukan atau menyelesaikan aktivitas perawatan diri', 'penyebab' => ['fisiologis' => ['Gangguan muskuloskeletal', 'Gangguan neuromuskuler', 'Kelemahan', 'Gangguan psikologis dan/atau psikotik', 'Penurunan motivasi/minat']], 'gejala_tanda_mayor' => ['subjektif' => ['Menolak melakukan perawatan diri'], 'objektif' => ['Tidak mampu mandi/mengenakan pakaian/makan/ke toilet/berhias secara mandiri', 'Minat melakukan perawatan diri kurang']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Stroke', 'Cedera medula spinalis', 'Depresi', 'Arthritis reumatoid', 'Retardasi mental', 'Delirium', 'Demensia', 'Gangguan amnestik', 'Skizofrenia dan gangguan psikotik lain', 'Fungsi penilaian terganggu'], 'keterangan' => 'Diagnosis ini dispesifikkan menjadi salah satu atau lebih dari: 1. Mandi, 2. Berpakaian, 3. Makan, 4. Toileting/Berhias'], 'slki' => [['kode' => 'L.11103', 'nama' => 'Perawatan Diri', 'kriteria_hasil' => ['Minat melakukan perawatan diri meningkat dari skala 2 (cukup menurun) menjadi skala 4 (cukup meningkat)']]], 'siki' => [['kode' => 'I.11348', 'nama' => 'Dukungan Perawatan Diri', 'definisi' => 'Memfasilitasi pemenuhan kebutuhan perawatan diri', 'tindakan' => ['observasi' => ['Identifikasi kebiasaan aktivitas perawatan diri sesuai usia', 'Identifikasi kebutuhan alat bantu kebersihan diri, berpakaian, berhias, dan makan'], 'terapeutik' => ['Sediakan lingkungan yang terapeutik (mis. suasana hangat, rileks, privasi)', 'Siapkan keperluan pribadi (mis. parfum, sikat gigi, dan sabun mandi)', 'Dampingi dalam melakukan perawatan diri sampai mandiri'], 'edukasi' => ['Anjurkan melakukan perawatan diri secara konsisten sesuai kemampuan'], 'kolaborasi' => []]], ['kode' => 'I.11349', 'nama' => 'Dukungan Perawatan Diri: BAB/BAK', 'definisi' => 'Memfasilitasi pemenuhan kebutuhan buang air kecil (BAK) dan buang air besar (BAB)', 'tindakan' => ['observasi' => ['Identifikasi kebiasaan BAK/BAB sesuai usia', 'Monitor integritas kulit pasien'], 'terapeutik' => ['Bersihkan alat bantu BAK/BAB setelah digunakan'], 'edukasi' => ['Anjurkan BAK/BAB secara rutin'], 'kolaborasi' => []]]]]],
            // D.0110 — Defisit Kesehatan Komunitas (updated from PDF p.534-539)
            ['diagkep_id' => 'D.0110', 'diagkep_desc' => 'Defisit Kesehatan Komunitas', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Terdapat masalah kesehatan atau faktor risiko yang dapat mengganggu kesejahteraan pada suatu kelompok', 'penyebab' => ['fisiologis' => ['Hambatan akses ke pemberi pelayanan kesehatan', 'Keterbatasan sumber daya', 'Program tidak memiliki anggaran yang cukup', 'Program tidak atau kurang didukung komunitas', 'Komunitas kurang puas dengan program yang dijalankan', 'Program tidak memiliki rencana evaluasi yang optimal', 'Program tidak memiliki data hasil yang memadai', 'Program tidak mengatasi seluruh masalah kesehatan komunitas']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Terjadi masalah kesehatan yang dialami komunitas', 'Terdapat faktor risiko fisiologis dan/atau psikologis yang menyebabkan anggota komunitas menjalani perawatan']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia program untuk meningkatkan kesejahteraan bagi komunitas', 'Tidak tersedia program untuk mencegah masalah kesehatan komunitas', 'Tidak tersedia program untuk mengurangi masalah kesehatan komunitas', 'Tidak tersedia program untuk mengatasi masalah kesehatan komunitas']], 'kondisi_klinis_terkait' => ['HIV/AIDS', 'Penyalahgunaan zat', 'Penyakit menular seksual', 'Kehamilan diluar nikah', 'Gizi buruk', 'Infeksi saluran pernapasan atas (ISPA)', 'Severe acute respiratory syndrome (SARS)']], 'slki' => [['kode' => 'L.12109', 'nama' => 'Status Kesehatan Komunitas', 'kriteria_hasil' => ['Ketersediaan program promosi kesehatan dari skala 1 menurun menjadi skala 5 meningkat', 'Pemantauan standar kesehatan komunitas dari skala 1 menurun menjadi skala 5 meningkat', 'Partisipasi dalam program kesehatan komunitas dari skala 1 menurun menjadi skala 5 meningkat', 'Angka gangguan kesehatan mental dari skala 1 meningkat menjadi skala 5 menurun', 'Angka kejadian cedera dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.14547', 'nama' => 'Pengembangan Kesehatan Masyarakat', 'definisi' => 'Memfasilitasi anggota kelompok atau masyarakat untuk mengidentifikasi isu kesehatan komunitas dan mengimplementasikan solusi yang ada', 'tindakan' => ['observasi' => ['Identifikasi masalah atau isu kesehatan dan prioritasnya', 'Identifikasi potensi atau aset dalam masyarakat terkait isu yang dihadapi', 'Identifikasi kekuatan dan partner dalam pengembangan kesehatan', 'Identifikasi pemimpin/tokoh dalam masyarakat'], 'terapeutik' => ['Berikan kesempatan kepada setiap anggota masyarakat untuk berpartisipasi sesuai aset yang dimiliki', 'Libatkan anggota masyarakat untuk meningkatkan kesadaran terhadap isu dan masalah kesehatan yang dihadapi', 'Libatkan masyarakat dalam musyawarah untuk mendefinisikan isu kesehatan dan mengembangkan rencana kerja', 'Libatkan masyarakat dalam proses perencanaan dan implementasi serta revisinya', 'Libatkan anggota masyarakat dalam mengembangkan jaringan kesehatan', 'Pertahankan komunikasi yang terbuka dengan anggota masyarakat dan pihak-pihak yang terlibat', 'Perkuat komunikasi antara individu dan kelompok untuk bermusyawarah terkait daya tarik yang sama', 'Fasilitasi struktur organisasi untuk meningkatkan kemampuan berkomunikasi dan bernegosiasi', 'Kembangkan strategi dalam manajemen konflik', 'Persatukan anggota masyarakat dengan cita-cita komunitas yang sama', 'Bangun komitmen antar anggota masyarakat', 'Kembangkan mekanisme keterlibatan tatanan lokal, regional bahkan nasional terkait isu kesehatan komunitas'], 'edukasi' => ['Tidak tersedia'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0111 — Defisit Pengetahuan (updated from PDF p.539-543)
            ['diagkep_id' => 'D.0111', 'diagkep_desc' => 'Defisit Pengetahuan', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Ketiadaanataau kurangnya informasi kognitif yang berkaitan dengan topik tertentu', 'penyebab' => ['fisiologis' => ['Keteratasan kognitif', 'Gangguan fungsi kognitif', 'Kekeliruan mengikuti anjuran', 'Kurang terpapar informasi', 'Kurang minat dalam belajar', 'Kurang mampu mengingat', 'Ketidaktahuan menemukan sumber informasi']], 'gejala_tanda_mayor' => ['subjektif' => ['Menanyakan masalah yang dihadapi'], 'objektif' => ['Menunjukkan perilaku tidak sesuai anjuran', 'Menunjukkan presepsi yang keliru terhadap masalah']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Menjalani pemeriksaan yang tidak tepat', 'Menunjukan perilaku berlebihan (mis. apatis, bermusuhan, adikasi dan histerria)']], 'kondisi_klinis_terkait' => ['Kondisi klinis yang baru dihadapi oleh klien', 'Penyakit akut', 'Penyakit kronis']], 'slki' => [['kode' => 'L.12111', 'nama' => 'Tingkat Pengetahuan', 'kriteria_hasil' => ['Perilaku sesuai anjuran meningkat', 'Perilaku sesuai dengan pengetahuan meningkat']]], 'siki' => [['kode' => 'I.12383', 'nama' => 'Edukasi Kesehatan', 'definisi' => 'Mengajarkan pengelolaan faktor risiko penyakit dan perilaku hidup bersih serta sehat', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi faktor-faktor yang dapat meningkatkan dan menurunkan motivasi perilaku hidup bersih dan sehat'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan faktor risiko yang dapat mempengaruhi kesehatan', 'Ajarkan perilaku hidup bersih dan sehat'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0112 — Kesiapan Peningkatan Manajemen Kesehatan (updated from PDF p.544-552)
            ['diagkep_id' => 'D.0112', 'diagkep_desc' => 'Kesiapan Peningkatan Manajemen Kesehatan', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Pola pengaturan dan pengintegrasian program kesehatan ke dalam kehidupan sehari-hari yang cukup untuk memenuhi tujuan kesehatan dan dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengekspresikan keinginan untuk mengelola masalah kesehatan dan pencegahannya'], 'objektif' => ['Pilihan hidup sehari-hari tepat untuk memenuhi tujuan program kesehatan']], 'gejala_tanda_minor' => ['subjektif' => ['Mengekspresikan tidak adanya hambatan yang berarti dalam mengintegrasikan program yang ditetapkan untuk mengatasi masalah kesehatan', 'Menggambarkan berkurangnya faktor risiko terjadinya masalah kesehatan'], 'objektif' => ['Tidak ditemukan adanya gejala masalah kesehatan atau penyakit yang tidak terduga']], 'kondisi_klinis_terkait' => ['Diabetes mellitus', 'Penyakit jantung kongestif', 'Penyakit paru obstruktif kronis', 'Asma', 'Sklerosis multipel', 'Lupus sistemik', 'HIV positif', 'AIDS', 'Prematuritas']], 'slki' => [['kode' => 'L.12104', 'nama' => 'Manajemen Kesehatan', 'kriteria_hasil' => ['Melakukan tindakan untuk mengurangi faktor risiko dari skala 1 menurun menjadi skala 5 meningkat', 'Menerapkan program perawatan meningkat dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi kesulitan dalam menjalani program perawatan/pengobatan menurun']]], 'siki' => [['kode' => 'I.12359', 'nama' => 'Bimbingan Antisipatif', 'definisi' => 'Mempersiapkan pasien dan keluarga untuk mengantisipasi perkembangan atau krisis situasional', 'tindakan' => ['observasi' => ['Identifikasi metode penyelesaian masalah yang biasa digunakan', 'Identifikasi kemungkinan perkembangan atau krisis situasional yang akan terjadi serta dampaknya pada individu dan keluarga'], 'terapeutik' => ['Fasilitasi memutuskan bagaimana masalah akan diselesaikan', 'Fasilitasi memutuskan siapa yang akan dilibatkan dalam menyelesaikan masalah', 'Gunakan contoh kasus untuk meningkatkan keterampilan menyelesaikan masalah', 'Fasilitasi mengidentifikasi sumber daya yang tersedia', 'Fasilitasi menyesuaikan diri dengan perubahan peran', 'Jadwalkan kunjungan pada setiap tahap perkembangan atau sesuai kebutuhan', 'Jadwalkan tindak lanjut untuk memantau atau memberi dukungan', 'Berikan nomor kontak yang dapat dihubungi, jika perlu', 'Libatkan keluarga dan pihak terkait, jika perlu', 'Berikan referensi baik cetak ataupun elektronik (mis. materi pendidikan, pamflet)'], 'edukasi' => ['Jelaskan perkembangan dan perilaku normal', 'Informasikan harapan yang realistis terkait perilaku pasien', 'Latih teknik koping yang dibutuhkan untuk mengatasi perkembangan atau krisis situasional'], 'kolaborasi' => ['Rujuk ke lembaga pelayanan masyarakat, jika perlu']]]]]],
            // D.0113 — Kesiapan Peningkatan Pengetahuan (updated from PDF p.553-554)
            ['diagkep_id' => 'D.0113', 'diagkep_desc' => 'Kesiapan Peningkatan Pengetahuan', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Perkembangan informasi kognitif yang berhubungan dengan topik spesifik cukup untuk memenuhi tujuan kesehatan dan dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan minat dalam belajar', 'Menjelaskan pengetahuan tentang suatu topik', 'Menggambarkan pengalaman sebelumnya yang sesuai dengan topik'], 'objektif' => ['Perilaku sesuai dengan pengetahuan']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Perilaku upaya peningkatan kesehatan']], 'slki' => [['kode' => 'L.12111', 'nama' => 'Tingkat Pengetahuan', 'kriteria_hasil' => ['Perilaku sesuai anjuran meningkat', 'Verbalisasi minat dalam belajar meningkat', 'Kemampuan menjelaskan pengetahuan tentang suatu topik meningkat', 'Kemampuan menggambarkan pengalaman sebelumnya yang sesuai dengan topik meningkat', 'Perilaku sesuai dengan pengetahuan meningkat', 'Pertanyaan tentang masalah yang dihadapi meningkat', 'Persepsi yang keliru terhadap masalah menurun', 'Menjalani pemeriksaan yang tidak tepat menurun']]], 'siki' => [['kode' => 'I.12383', 'nama' => 'Edukasi Kesehatan', 'definisi' => 'Mengajarkan pengelolaan faktor risiko penyakit dan perilaku hidup bersih serta sehat', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi faktor-faktor yang dapat meningkatkan dan menurunkan motivasi perilaku hidup bersih dan sehat'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan faktor risiko yang dapat mempengaruhi kesehatan', 'Ajarkan perilaku hidup bersih dan sehat', 'Ajarkan strategi yang dapat digunakan untuk meningkatkan perilaku hidup bersih dan sehat'], 'kolaborasi' => ['Tidak tersedia']]], ['kode' => 'I.12470', 'nama' => 'Promosi Kesiapan Penerimaan Informasi', 'definisi' => 'Meningkatkan kesiapan pasien dalam menerima informasi tentang kondisi kesehatan', 'tindakan' => ['observasi' => ['Identifikasi informasi yang akan disampaikan', 'Identifikasi pemahaman tentang kondisi kesehatan saat ini', 'Identifikasi kesiapan menerima informasi'], 'terapeutik' => ['Lakukan penguatan potensi pasien dan keluarga untuk menerima informasi', 'Libatkan pengambilan keputusan dalam keluarga untuk menerima informasi', 'Fasilitasi mengenali kondisi tubuh yang membutuhkan layanan keperawatan', 'Dahulukan menyampaikan informasi baik (positif) sebelum menyampaikan informasi kurang baik (negatif) terkait kondisi pasien', 'Berikan nomor kontak yang dapat dihubungi jika pasien membutuhkan bantuan', 'Catat identitas dan nomor kontak pasien untuk mengingatkan atau follow up kondisi pasien', 'Fasilitasi akses pelayanan pada saat dibutuhkan'], 'edukasi' => ['Berikan informasi berupa alur, leaflet atau gambar untuk memudahkan pasien mendapatkan informasi kesehatan', 'Anjurkan keluarga mendampingi pasien selama fase akut, progresif atau terminal, jika memungkinkan'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0114 — Ketidakpatuhan (updated from PDF p.555-559)
            ['diagkep_id' => 'D.0114', 'diagkep_desc' => 'Ketidakpatuhan', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Perilaku individu dan/atau pemberi asuhan tidak mengikuti rencana perawatan/pengobatan yang disepakati dengan tenaga kesehatan, sehingga hasil perawatan/pengobatan tidak efektif', 'penyebab' => ['fisiologis' => ['Disabilitas (mis. penurunan daya ingat, defisit sensorik/motorik)', 'Efek samping program perawatan/pengobatan', 'Beban pembiayaan program perawatan/pengobatan', 'Lingkungan tidak terapeutik', 'Program terapi kompleks dan/atau lama', 'Hambatan mengakses pelayanan kesehatan (mis. gangguan mobilisasi, masalah transportasi, ketiadaan orang merawat anak di rumah, cuaca tidak menentu)', 'Program terapi tidak ditanggung asuransi', 'Ketidakadekuatan pemahaman (sekunder akibat defisit kognitif, kecemasan, gangguan penglihatan/pendengaran, kelelahan, kurang motivasi)']], 'gejala_tanda_mayor' => ['subjektif' => ['Menolak menjalani perawatan/pengobatan', 'Menolak mengikuti anjuran'], 'objektif' => ['Perilaku tidak mengikuti program perawatan/pengobatan', 'Perilaku tidak menjalankan anjuran']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tampak tanda/gejala penyakit/masalah kesehatan masih ada atau meningkat', 'Tampak komplikasi penyakit/masalah kesehatan menetap atau meningkat']], 'kondisi_klinis_terkait' => ['Kondisi baru terdiagnosis penyakit', 'Kondisi penyakit kronis', 'Masalah kesehatan yang membutuhkan perubahan pola hidup']], 'slki' => [['kode' => 'L.12110', 'nama' => 'Tingkat Kepatuhan', 'kriteria_hasil' => ['Verbalisasi kemauan mematuhi program perawatan atau pengobatan dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi mengikuti anjuran dari skala 1 menurun menjadi skala 5 meningkat', 'Perilaku mengikuti program perawatan/pengobatan dari skala 1 meburuk menjadi skala 5 membaik']]], 'siki' => [['kode' => 'I.12361', 'nama' => 'Dukungan Kepatuhan Program Pengobatan', 'definisi' => 'Memfasilitasi ketepatan dan keteraturan menjalani program pengobatan yang sudah ditentukan', 'tindakan' => ['observasi' => ['Identifikasi kepatuhan menjalani program pengobatan'], 'terapeutik' => ['Buat komitmen menjalani program pengobatan dengan baik', 'Buat jadwal pendampingan keluarga untuk bergantian menemani pasien selama menjalani program pengobatan, jika perlu', 'Dokumentasikan aktivitas selama menjalani proses pengobatan', 'Diskusikan hal-hal yang dapat mendukung atau menghambat berjalannya program pengobatan', 'Libatkan keluarga untuk mendukung program pengobatan yang dijalani'], 'edukasi' => ['Informasi program pengobatan yang harus dijalani', 'Informasikan manfaat yang akan diperoleh jika teratur menjalani program pengobatan', 'Anjurkan keluarga untuk mendampingi dan merawat pasien selama menjalani program pengobatan', 'Anjurkan pasien dan keluarga melakukan konsultasi ke pelayanan kesehatan terdekat, jika pengobatan'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0115 — Manajemen Kesehatan Keluarga Tidak Efektif (updated from PDF p.560-562)
            ['diagkep_id' => 'D.0115', 'diagkep_desc' => 'Manajemen Kesehatan Keluarga Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Pola penanganan masalah kesehatan dalam keluarga tidak memuaskan untuk memulihkan kondisi kesehatan anggota keluarga', 'penyebab' => ['fisiologis' => ['Kompleksitas sistem pelayanan kesehatan', 'Kompleksitas program perawatan/pengobatan', 'Konflik pengambilan keputusan', 'Kesulitan ekonomi', 'Banyak tuntutan', 'Konflik keluarga']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan tidak memahami masalah kesehatan yang diderita', 'Mengungkapkan kesulitan menjalankan perawatan yang ditetapkan'], 'objektif' => ['Gejala penyakit anggota keluarga semakin memberat', 'Aktivitas keluarga untuk mengatasi masalah kesehatan tidak tepat']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Gagal melakukan tindakan untuk mengurangi faktor risiko']], 'kondisi_klinis_terkait' => ['PPOK', 'Sklerosis multipel', 'Arthritis rheumatoid', 'Nyeri kronis', 'Penyalahgunaan zat', 'Gagal ginjal/hati tahap terminal']], 'slki' => [['kode' => 'L.09088', 'nama' => 'Status Koping Keluarga', 'kriteria_hasil' => ['Komunikasi antara anggota keluarga membaik']]], 'siki' => [['kode' => 'I.09260', 'nama' => 'Dukungan Koping Keluarga', 'definisi' => 'Memfasilitasi peningkatan nilai-nilai, minat dan tujuan dalam keluarga', 'tindakan' => ['observasi' => ['Identifikasi respons emosional terhadap kondisi saat ini', 'Identifikasi beban prognosis secara psikologis', 'Identifikasi pemahaman tentang keputusan perawatan setelah pulang', 'Identifikasi kesesuaian antara harapan pasien, keluarga, dan tenaga kesehatan'], 'terapeutik' => ['Dengarkan masalah, perasaan, dan pertanyaan keluarga', 'Fasilitasi pengungkapan perasaan antara pasien dan keluarga atau antar anggota keluarga', 'Fasilitasi pengambilan keputusan dalam merencanakan perawatan jangka panjang, jika perlu'], 'edukasi' => ['Informasikan kemajuan pasien secara berkala', 'Informasikan fasilitas perawatan kesehatan yang tersedia'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0116 — Manajemen Kesehatan Tidak Efektif (updated from PDF p.563-564)
            ['diagkep_id' => 'D.0116', 'diagkep_desc' => 'Manajemen Kesehatan Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Pola pengaturan dan pengintegrasian penanganan masalah kesehatan ke dalam kebiasaan hidup sehari-hari tidak memuaskan untuk mencapai status kesehatan yang diharapkan', 'penyebab' => ['fisiologis' => ['Kompleksitas sistem pelayanan kesehatan', 'Kompleksitas program perawatan/pengobatan', 'Konflik pengambilan keputusan', 'Kurang terpapar informasi', 'Kesulitan ekonomi', 'Tuntutan berlebih (mis. individu, keluarga)', 'Konflik keluarga', 'Ketidakefektifan pola perawatan kesehatan keluarga', 'Ketidakcukupan petunjuk untuk bertindak', 'Kekurangan dukungan sosial']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan kesulitan dalam menjalani program perawatan/pengobatan'], 'objektif' => ['Gagal melakukan tindakan untuk mengurangi faktor risiko', 'Gagal menerapkan program perawatan/pengobatan dalam kehidupan sehari-hari', 'Aktivitas hidup sehari-hari tidak efektif untuk memenuhi tujuan kesehatan']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak tersedia']], 'kondisi_klinis_terkait' => ['Kondisi kronis (mis. kanker, penyakit paru obstruktif kronis, sklerosis multipel, arthritis, gagal ginjal, hati atau jantung kronis)', 'Diagnosis baru yang mengharuskan perubahan gaya hidup']], 'slki' => [['kode' => 'L.12104', 'nama' => 'Manajemen Kesehatan', 'kriteria_hasil' => ['Melakukan tindakan untuk mengurangi faktor risiko meningkat (5)', 'Menerapkan program perawatan meningkat (5)', 'Aktivitas hidup sehari-hari efektif memenuhi tujuan kesehatan meningkat (5)', 'Verbalisasi kesulitan dalam menjalani program perawatan/pengobatan menurun (5)']]], 'siki' => [['kode' => 'I.09265', 'nama' => 'Dukungan Pengambilan Keputusan', 'definisi' => 'Memberikan informasi dan dukungan saat pembuatan keputusan kesehatan', 'tindakan' => ['observasi' => ['Identifikasi persepsi mengenai masalah dan informasi yang memicu konflik'], 'terapeutik' => ['Fasilitasi mengklarifikasi nilai dan harapan yang membantu membuat pilihan', 'Diskusikan kelebihan dan kekurangan dari setiap solusi', 'Fasilitasi melihat situasi secara realistis', 'Motivasi mengungkapkan tujuan perawatan yang diharapkan', 'Fasilitasi pengambilan keputusan secara kolaboratif', 'Hormati hak pasien untuk menerima atau menolak informasi', 'Fasilitasi menjelaskan keputusan kepada orang lain, jika perlu', 'Fasilitasi hubungan antara pasien, keluarga, dan tenaga kesehatan lainnya'], 'edukasi' => ['Informasikan alternatif solusi secara jelas', 'Berikan informasi yang diminta pasien'], 'kolaborasi' => ['Kolaborasi dengan tenaga kesehatan lain dalam memfasilitasi pengambilan keputusan']]]]]],
            // D.0117 — Pemeliharaan Kesehatan Tidak Efektif (updated from PDF p.565-567)
            ['diagkep_id' => 'D.0117', 'diagkep_desc' => 'Pemeliharaan Kesehatan Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Perilaku', 'subkategori' => 'Penyuluhan dan Pembelajaran', 'definisi' => 'Ketidakmampuan mengidentifikasi, mengelola, dan/atau menemukan bantuan untuk mempertahankan kesehatan', 'penyebab' => ['fisiologis' => ['Hambatan kognitif', 'Ketidaktuntasan proses berduka', 'Ketidakadekuatan keterampilan berkomunikasi', 'Kurangnya keterampilan motorik halus/kasar', 'Ketidakmampuan membuat penilaian yang tepat', 'Ketidak mampuan mengatasi masalah (individu atau keluarga)', 'Ketidakcukupan sumber daya (mis. keuangan, fasilitas)', 'Gangguan depresi', 'Tidak terpenuhinya tugas perkembangan']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Kurang menunjukkan perilaku adaptif terhadap perubahan lingkungan', 'Kurang menunjukkan pemahaman tentang perilaku sehat', 'Tidak mampu menjalankan perilaku sehat']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Memiliki riwayat perilaku mencari bantuan kesehatan yang kurang', 'Kurang menunjukkan minat untuk meningkatkan perilaku sehat', 'Tidak memiliki sistem pendukung (support system)']], 'kondisi_klinis_terkait' => ['Kondisi kronis (mis. sklerosis multipel, arthritis, nyeri kronis)', 'Cedera otak', 'Stroke', 'Paralisis']], 'slki' => [['kode' => 'L.12106', 'nama' => 'Pemeliharaan Kesehatan', 'kriteria_hasil' => ['Menunjukkan perilaku adaptif meningkat (5)', 'Menunjukkan pemahaman perilaku sehat meningkat (5)', 'Kemampuan menjalankan perilaku sehat meningkat (5)', 'Perilaku mencari bantuan meningkat (5)', 'Menunjukkan minat meningkatkan perilaku sehat meningkat (5)', 'Memiliki sistem pendukung meningkat (5)']]], 'siki' => [['kode' => 'I.12383', 'nama' => 'Edukasi Kesehatan', 'definisi' => 'Mengajarkan pengelolaan faktor risiko penyakit dan perilaku hidup bersih serta sehat', 'tindakan' => ['observasi' => ['Identifikasi kesiapan dan kemampuan menerima informasi', 'Identifikasi faktor-faktor yang dapat meningkatkan dan menurunkan motivasi perilaku hidup bersih dan sehat'], 'terapeutik' => ['Sediakan materi dan media pendidikan kesehatan', 'Jadwalkan pendidikan kesehatan sesuai kesepakatan', 'Berikan kesempatan untuk bertanya'], 'edukasi' => ['Jelaskan faktor risiko yang dapat mempengaruhi kesehatan', 'Ajarkan perilaku hidup bersih dan sehat', 'Ajarkan strategi yang dapat digunakan untuk meningkatkan perilaku hidup bersih dan sehat'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0118 — Gangguan Interaksi Sosial (updated from PDF p.568-570)
            ['diagkep_id' => 'D.0118', 'diagkep_desc' => 'Gangguan Interaksi Sosial', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Kuantitas dan/atau kualitas hubungan sosial yang kurang atau berlebihan', 'penyebab' => ['fisiologis' => ['Defisiensi bicara', 'Hambatan perkembangan/maturasi', 'Ketiadaan orang terdekat', 'Perubahan neurologis (mis. kelahiran prematur, distres fetal, persalinan cepat atau persalinan lama)', 'Disfungsi sistem keluarga', 'Ketidakteraturan atau kekacauan lingkungan', 'Penganiayaan atau pengabaian anak', 'Hubungan orang tua-anak tidak memuaskan', 'Model peran negatif', 'Impulsif', 'Perilaku menentang', 'Perilaku agresif', 'Keengganan berpisah dengan orang terdekat']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasakan tidak nyaman dengan situasi sosial', 'Merasakan sulit menerima atau mengkomunikasikan perasaan'], 'objektif' => ['Kurang responsif atau tertarik pada orang lain', 'Tidak berminat melakukan kontak emosi dan fisik']], 'gejala_tanda_minor' => ['subjektif' => ['Sulit mengungkapkan kasih sayang'], 'objektif' => ['Gejala cemas berat', 'Kontak mata kurang', 'Ekspresi wajah tidak responsif', 'Tidak kooperatif dalam bermain dan berteman dengan sebaya', 'Perilaku tidak sesuai usia']], 'kondisi_klinis_terkait' => ['Retardasi mental', 'Gangguan autistik', 'Attention deficit/hyperactivity disorder (ADHD)', 'Gangguan perilaku', 'Oppositional defiant disorder', 'Gangguan tourette', 'Gangguan kecemasan perpisahan', 'Sindrom down']], 'slki' => [['kode' => 'L.13115', 'nama' => 'Interaksi Sosial', 'kriteria_hasil' => ['Perasaan nyaman dengan situasi sosial dari skala 1 menurun menjadi skala 5 meningkat', 'Perasaan mudah menerima atau mengkomunikasikan perasaan dari skala 1 menurun menjadi skala 5 meningkat', 'Responsif pada orang lain minat melakukan kontak emosi dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.13484', 'nama' => 'Modifikasi Perilaku Keterampilan Sosial', 'definisi' => 'Mengubah pengembangan atau peningkatan keterampilan sosial interpersonal', 'tindakan' => ['observasi' => ['Identifikasi penyebab kurangnya keterampilan sosial', 'Identifikasi fokus pelatihan keterampilan sosial'], 'terapeutik' => ['Motivasi untuk berlatih keterampilan sosial', 'Beri umpan balik positif (mis. pujian atau penghargaan) terhadap kemampuan sosialisasi', 'Libatkan keluarga selama latihan keterampilan sosial, jika perlu'], 'edukasi' => ['Jelaskan tujuan melatih keterampilan sosial', 'Jelaskan respons dan konsekuensi keterampilan sosial', 'Anjurkan mengungkapkan perasaan akibat masalah yang dialami', 'Anjurkan mengevaluasi pencapaian setiap interaksi', 'Edukasi keluarga untuk dukungan keterampilan sosial', 'Latih keterampilan sosial secara bertahap'], 'kolaborasi' => []]]]]],
            // D.0119 — Gangguan Komunikasi Verbal (updated from PDF p.571-579)
            ['diagkep_id' => 'D.0119', 'diagkep_desc' => 'Gangguan Komunikasi Verbal', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Penurunan, perlambatan, atau ketiadaan kemampuan untuk menerima, memproses, mengirim, dan/atau menggunakan sistem simbol', 'penyebab' => ['fisiologis' => ['Penurunan sirkulasi serebral', 'Gangguan neuromuskuler', 'Gangguan pendengaran', 'Gangguan muskuloskeletal', 'Kelainan palatum', 'Hambatan fisik (mis. terpasang trakheostomi, intubasi, krikotiroidektomi)', 'Hambatan individu (mis. ketakutan, kecemasan, merasa malu, emosional, kurang privasi)', 'Hambatan psikologis (mis. gangguan psikotik, gangguan konsep diri, harga diri rendah, gangguan emosi)', 'Hambatan lingkungan (mis. ketidakcukupan informasi, ketiadaan orang terdekat, ketidaksesuaian budaya, bahasa asing)']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Tidak mampu berbicara atau mendengar', 'Menunjukkan respon tidak sesuai']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Afasia', 'Disfasia', 'Apraksia', 'Disleksia', 'Disartria', 'Afonia', 'Dislalia', 'Pelo', 'Gagap', 'Tidak ada kontak mata', 'Sulit memahami komunikasi', 'Sulit mempertahankan komunikasi', 'Sulit menggunakan ekspresi wajah atau tubuh', 'Tidak mampu menggunakan ekspresi wajah atau tubuh', 'Sulit menyusun kalimat', 'Verbalisasi tidak tepat', 'Sulit mengungkapkan kata-kata', 'Disorientasi orang, ruang, waktu', 'Defisit penglihatan', 'Delusi']], 'kondisi_klinis_terkait' => ['Stroke', 'Cedera kepala', 'Tumor otak', 'Autisme', 'Alzheimer']], 'slki' => [['kode' => 'L.13118', 'nama' => 'Komunikasi Verbal', 'kriteria_hasil' => ['Kemampuan berbicara meningkat', 'Kemampuan mendengar meningkat', 'Kesesuaian ekspresi wajah/tubuh meningkat', 'Kontak mata meningkat', 'Respon perilaku membaik', 'Pemahaman komunikasi membaik']]], 'siki' => [['kode' => 'I.06206', 'nama' => 'Perawatan Telinga', 'definisi' => 'Mengidentifikasi, merawat dan mencegah gangguan pada telinga dan pendengaran', 'tindakan' => ['observasi' => ['Pemeriksaan fungsi pendengaran', 'Monitor tanda dan gejala infeksi telinga (mis. inflamasi dan pengeluaran cairan)', 'Monitor tanda dan gejala disfungsi telinga (mis. nyeri, nyeri tekan, gatal, perubahan pendengaran, tinitus, vertigo)'], 'terapeutik' => ['Bersihkan telinga luar', 'Bersihkan serumen telinga dengan kapas yang lembut', 'Lakukan irigasi telinga, jika perlu', 'Hindari paparan suara keras'], 'edukasi' => ['Jelaskan tanda dan gejala disfungsi pendengaran', 'Anjurkan menggunakan sumbat telinga saat berenang atau dalam pesawat, jika perlu', 'Ajarkan membersihkan telinga luar'], 'kolaborasi' => []]]]]],
            // D.0120 — Gangguan Proses Keluarga (updated from PDF p.580-599)
            ['diagkep_id' => 'D.0120', 'diagkep_desc' => 'Gangguan Proses Keluarga', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Perubahan dalam hubungan atau fungsi keluarga', 'penyebab' => ['fisiologis' => ['Perubahan status kesehatan anggota keluarga', 'Perubahan finansial keluarga', 'Perubahan status sosial keluarga', 'Perubahan interaksi dengan masyarakat', 'Krisis perkembangan', 'Transisi perkembangan', 'Peralihan pengambilan keputusan dalam keluarga', 'Perubahan peran keluarga', 'Krisis situasional', 'Transisi situasional']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Keluarga tidak mampu beradaptasi terhadap situasi', 'Tidak mampu berkomunikasi secara terbuka diantara anggota keluarga']], 'gejala_tanda_minor' => ['subjektif' => ['Keluarga tidak mampu mengungkapkan perasaan secara leluasan'], 'objektif' => ['Keluarga tidak mampu memenuhi kebutuhan fisik/emosional/spiritual anggota keluarga', 'Keluarga tidak mampu mencari atau menerima bantuan secara tepat']], 'kondisi_klinis_terkait' => ['Hospitalisasi', 'Kondisi penyakit kronis', 'Prosedur pembedahan', 'Cedera traumatis', 'Penyalahgunaan zat', 'Penyakit Alzheimer', 'Kehamilan']], 'slki' => [['kode' => 'L.13123', 'nama' => 'Proses Keluarga', 'kriteria_hasil' => ['Adaptasi keluarga terhadap situasi dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan keluarga berkomunikasi secara terbuka diantara anggota keluarga dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.09260', 'nama' => 'Dukungan Koping Keluarga', 'definisi' => 'Memfasilitasi peningkatan nilai-nilai, minat dan tujuan dalam keluarga', 'tindakan' => ['observasi' => ['Identifikasi respons emosional terhadap kondisi saat ini', 'Identifikasi beban prognosis secara psikologis', 'Identifikasi pemahaman tentang keputusan perawatan setelah pulang', 'Identifikasi kesesuaian antara harapan pasien, keluarga, dan tenaga kesehatan'], 'terapeutik' => ['Dengarkan masalah, perasaan, dan pernyataan keluarga', 'Terima nilai-nilai keluarga dengan cara yang tidak menghakimi', 'Diskusikan rencana medis dan perawatan', 'Fasilitasi pengungkapan perasaan antara pasien dan keluarga atau antar anggota keluarga', 'Fasilitasi pengambilan keputusan dalam merencanakan perawatan jangka panjang, jika perlu', 'Fasilitasi anggota keluarga dalam mengidentifikasi dan menyelesaikan konflik nilai', 'Fasilitasi pemenuhan kebutuhan dasar keluarga (mis. tempat tinggal, makanan, pakaian)', 'Fasilitasi anggota keluarga melalui proses kematian dan berduka, jika perlu', 'Fasilitasi memperoleh pengetahuan, keterampilan, dan peralatan yang diperlukan untuk mempertahankan keputusan perawatan pasien', 'Bersikap sebagai pengganti keluarga untuk menenangkan pasien dan/atau jika keluarga tidak dapat memberikan perawatan', 'Hargai dan dukung mekanisme koping adaptif yang digunakan', 'Berikan kesempatan berkunjung bagi anggota keluarga'], 'edukasi' => ['Informasikan kemajuan pasien secara berkala', 'Informasikan fasilitas perawatan kesehatan yang tersedia'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0121 — Isolasi Sosial (updated from PDF p.600-618)
            ['diagkep_id' => 'D.0121', 'diagkep_desc' => 'Isolasi Sosial', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Ketidakmampuan untuk membina hubungan yang erat, hangat, terbuka dan interpenden dengan orang lain', 'penyebab' => ['fisiologis' => ['Keterlambatan perkembangan', 'Ketidakmampuan menjalin hubungan yang memuaskan', 'Ketidaksesuaian minat dengan tahap perkembangan', 'Ketidaksesuaian nilai-nilai dengan norma', 'Ketidaksesuaian perilaku sosial dengan norma', 'Perubahan penampilan fisik', 'Perubahan status mental', 'Ketidakadekuatan sumber daya personal (mis. disfungsi berduka, pengendalian diri buruk)']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa ingin sendirian', 'Merasa tidak aman di tempat umum'], 'objektif' => ['Menarik diri', 'Tidak berminat/menolak berinteraksi dengan orang lain']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa berbeda dengan orang lain', 'Merasa asyik dengan pikiran sendiri', 'Merasa tidak mempunyai tujuan yang jelas'], 'objektif' => ['Afek datar', 'Afek sedih', 'Riwayat ditolak', 'Menunjukkan permusuhan', 'Tidak mampu memenuhi harapan orang lain', 'Kondisi difabel', 'Tindakan tidak berarti', 'Tidak ada kontak mata', 'Perkembangan terlambat', 'Tidak bergairah/lesu']], 'kondisi_klinis_terkait' => ['Penyakit Alzheimer', 'AIDS', 'Tuberkulosis', 'Kondisi yang menyebabkan gangguan mobilisasi', 'Gangguan psikiatrik (mis. depresi mayor dan schizophrenia)']], 'slki' => [['kode' => 'L.13116', 'nama' => 'Keterlibatan Sosial', 'kriteria_hasil' => ['Minat interaksi dari skala 1 menurun menjadi skala 5 meningkat', 'Verbalisasi isolasi dari skala 1 meningkat menjadi skala 5 menurun', 'Verbalisasi ketidakamanan di tempat umum dari skala 1 meningkat menjadi skala 5 menurun', 'Perilaku menarik diri dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.13498', 'nama' => 'Promosi Sosialisasi', 'definisi' => 'Meningkatkan kemampuan untuk berinteraksi dengan orang lain', 'tindakan' => ['observasi' => ['Identifikasi kemampuan melakukan interaksi dengan orang lain', 'Identifikasi hambatan melakukan interaksi dengan orang lain'], 'terapeutik' => ['Motivasi meningkatkan keterlibatan dalam suatu hubungan', 'Motivasi kesabaran dalam mengembangkan suatu hubungan', 'Motivasi berpartisipasi dalam aktivitas baru dan kegiatan kelompok', 'Motivasi berinteraksi di luar lingkungan (mis. jalan-jalan, ke toko buku)', 'Diskusikan kekuatan dan keterbatasan dalam berkomunikasi dengan orang lain', 'Diskusikan perencanaan kegiatan di masa depan', 'Berikan umpan balik positif dalam perawatan diri', 'Berikan umpan balik positif pada setiap peningkatan kemampuan'], 'edukasi' => ['Anjurkan berinteraksi dengan orang lain secara bertahap', 'Anjurkan ikut serta kegiatan sosial dan kemasyarakatan', 'Anjurkan berbagi pengalaman dengan orang lain', 'Anjurkan meningkatkan kejujuran diri dan menghormati hak orang lain', 'Anjurkan penggunaan alat bantu (mis. kacamata dan alat bantu dengar)', 'Anjurkan membuat perencanaan kelompok kecil untuk kegiatan khusus', 'Latih bermain peran untuk meningkatkan keterampilan komunikasi', 'Latih mengekspresikan marah dengan tepat'], 'kolaborasi' => ['Tidak tersedia']]], ['kode' => 'I.05186', 'nama' => 'Terapi Aktivitas', 'definisi' => 'Menggunakan aktivitas fisik, kognitif, sosial, dan spiritual tertentu untuk memulihkan keterlibatan, frekuensi, atau durasi aktivitas individu atau kelompok', 'tindakan' => ['observasi' => ['Identifikasi defisit tingkat aktivitas', 'Identifikasi kemampuan berpartisipasi dalam aktivitas tertentu', 'Identifikasi sumber daya untuk aktivitas yang diinginkan', 'Identifikasi strategi meningkatkan partisipasi dalam aktivitas', 'Identifikasi makna aktivitas rutin (mis. bekerja) dan waktu luang', 'Monitor respons emosional, fisik, sosial, dan spiritual terhadap aktivitas'], 'terapeutik' => ['Fasilitasi fokus pada kemampuan, bukan defisit yang dialami', 'Sepakati komitmen untuk meningkatkan frekuensi dan rentang aktivitas', 'Fasilitasi memilih aktivitas dan tetapkan tujuan aktivitas yang konsisten sesuai kemampuan fisik, psikologis, dan sosial', 'Koordinasikan pemilihan aktivitas sesuai usia', 'Fasilitasi makna aktivitas yang dipilih', 'Fasilitasi transportasi untuk menghadiri aktivitas, jika sesuai', 'Fasilitasi pasien dan keluarga dalam menyesuaikan lingkungan untuk mengakomodasi aktivitas yang dipilih', 'Fasilitasi aktivitas fisik rutin (mis. ambulasi, mobilisasi, dan perawatan diri), sesuai kebutuhan', 'Fasilitasi aktivitas pengganti saat mengalami keterbatasan waktu, energi, atau gerak', 'Fasilitasi aktivitas motorik kasar untuk pasien hiperaktif', 'Tingkatkan aktivitas fisik untuk memelihara berat badan, jika sesuai', 'Fasilitasi aktivitas motorik untuk merelaksasi otot', 'Fasilitasi aktivitas dengan komponen memori implisit dan emosional (mis. kegiatan keagamaan khusus) untuk pasien demensia, jika sesuai', 'Libatkan dalam permainan kelompok yang tidak kompetitif, terstruktur, dan aktif', 'Tingkatkan keterlibatan dalam aktivitas rekreasi dan diversifikasi untuk menurunkan kecemasan', 'Libatkan keluarga dalam aktivitas, jika perlu', 'Fasilitasi mengembangkan motivasi dan penguatan diri', 'Fasilitasi pasien dan keluarga memantau kemajuannya sendiri untuk mencapai tujuan', 'Jadwalkan aktivitas dalam rutinitas sehari-hari', 'Berikan penguatan positif atas partisipasi dalam aktivitas'], 'edukasi' => ['Jelaskan metode aktivitas fisik sehari-hari, jika perlu', 'Ajarkan cara melakukan aktivitas yang dipilih', 'Anjurkan melakukan aktivitas fisik, sosial, spiritual, dan kognitif dalam menjaga fungsi dan kesehatan', 'Anjurkan terlibat dalam aktivitas kelompok atau terapi, jika sesuai', 'Anjurkan keluarga memberi penguatan positif atas partisipasi dalam aktivitas'], 'kolaborasi' => ['Kolaborasi dengan terapis okupasi dalam merencanakan dan memonitor program aktivitas, jika sesuai', 'Rujuk pada pusat atau program aktivitas komunitas, jika perlu']]]]]],
            // D.0122 — Kesiapan Peningkatan Menjadi Orang Tua (updated from PDF p.619-620)
            ['diagkep_id' => 'D.0122', 'diagkep_desc' => 'Kesiapan Peningkatan Menjadi Orang Tua', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Pola pemberian lingkungan bagi anak atau anggota keluarga yang cukup untuk memfasilitasi pertumbuhan dan perkembangan serta dapat ditingkatkan', 'gejala_tanda_mayor' => ['subjektif' => ['Mengekspresikan keinginan untuk meningkatkan peran menjadi orang tua'], 'objektif' => ['Tampak adanya dukungan emosi dan pengertian pada anak atau anggota keluarga']], 'gejala_tanda_minor' => ['subjektif' => ['Anak atau anggota keluarga lainnya mengekspresikan kepuasan dengan lingkungan rumah', 'Anak atau anggota keluarga mengungkapkan harapan yang realistis'], 'objektif' => ['Kebutuhan fisik dan emosi anak/anggota keluarga terpenuhi']], 'kondisi_klinis_terkait' => ['Perilaku upaya peningkatan kesehatan']], 'slki' => [['kode' => 'L.13120', 'nama' => 'Peran Menjadi Orang Tua', 'kriteria_hasil' => ['Bounding attachment dari keluarga dari skala 1 menjadi skala 5 meningkat', 'Perilaku positif menjadi orang tua dari skala 1 menjadi skala 5 meningkat', 'Interaksi perawatan bayi dari skala 1 menjadi skala 5 meningkat', 'Verbalisasi kepuasan dengan lingkungan rumah memiliki bayi dari skala 1 menjadi skala 5 meningkat', 'Kebutuhan fisik anak/anggota keluarga terpenuhi dari skala 1 meningkat menjadi skala 5 meningkat', 'Kebutuhan emosi anak/anggota keluarga terpenuhi dari skala 1 meningkat menjadi skala meningkat', 'Keinginan meningkatkan peran menjadi orang tua dari skala 1 menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.12466', 'nama' => 'Promosi Antisipasi Keluarga', 'definisi' => 'Meningkatkan kesiapan keluarga untuk mencegah perkembangan atau krisis situasi akibat masalah kesehatan', 'tindakan' => ['observasi' => ['Identifikasi kemungkinan krisis situasi atau masalah perkembangan serta dampaknya pada kehidupan pasien dan keluarganya', 'Identifikasi metode pemecahan masalah yang sering digunakan keluarga'], 'terapeutik' => ['Fasilitasi dalam memutuskan strategi pemecahan masalah yang dihadapi keluarga', 'Libatkan seluruh anggota keluarga dalam upaya antisipasi masalah kesehatan, jika memungkinkan', 'Lakukan kunjungan kepada keluarga secara berkala, jika perlu', 'Buat jadwal aktivitas bersama keluarga terkait masalah kesehatan yang dihadapi'], 'edukasi' => ['Jelaskan perkembangan perilaku yang normal kepada keluarga'], 'kolaborasi' => ['Kerjasama dengan tenaga kesehatan terkait lainnya, jika perlu']]]]]],
            // D.0123 — Kesiapan Peningkatan Proses Keluarga (updated from PDF p.621-622)
            ['diagkep_id' => 'D.0123', 'diagkep_desc' => 'Kesiapan Peningkatan Proses Keluarga', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Pola fungsi keluarga yang cukup untuk mendukung kesejahteraan anggota keluarga dan dapat ditingkatkan', 'penyebab' => [], 'gejala_tanda_mayor' => ['subjektif' => ['Mengekspresikan keinginan untuk meningkatkan dinamika keluarga'], 'objektif' => ['Menunjukan fungsi keluarga dalam memenuhi kebutuhan fisik, sosial, dan psikologi anggota keluarga', 'Menunjukkan aktivitas untuk mendukung keselamatan dan pertumbuhan anggota keluarga', 'Peran keluarga fleksibel dan tepat dengan tahap perkembangan', 'Terlihat adanya respek dengan anggota keluarga']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Keluarga menunjukkan minat melakukan aktivitas hidup sehari-hari yang positif', 'Terlihat adanya kemampuan keluarga untuk pulih dari kondisi sulit', 'Tampak keseimbangan antara otonomi dan kebersamaan', 'Batasan-batasan anggota keluarga dipertahankan', 'Hubungan dengan masyarakat terjalin positif', 'Keluarga beradaptasi dengan perubahan']], 'kondisi_klinis_terkait' => ['Kondisi kesehatan kronis (mis. asma, diabetes melitus, lupus sistemik, sklerosis multipel, AIDS)', 'Gangguan jiwa (mis. gangguan afektif, gangguan perhatian, sindrom down)']], 'slki' => [['kode' => 'L.13123', 'nama' => 'Proses Keluarga', 'kriteria_hasil' => ['Adaptasi keluarga terhadap situasi dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan keluarga berkomunikasi secara terbuka diantara anggota keluarga dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.13490', 'nama' => 'Promosi Keutuhan Keluarga', 'definisi' => 'Meningkatkan pengetahuan dan kemampuan pasien untuk menjaga dan meningkatkan kerekatan dan keutuhan keluarga', 'tindakan' => ['observasi' => ['Identifikasi pemahaman keluarga terhadap masalah', 'Identifikasi adanya konflik prioritas antar anggota keluarga', 'Identifikasi mekanisme koping keluarga', 'Monitor hubungan antara anggota keluarga'], 'terapeutik' => ['Hargai privasi keluarga', 'Fasilitasi kunjungan keluarga', 'Fasilitasi keluarga melakukan pengambilan keputusan dan pemecahan masalah', 'Fasilitasi komunikasi terbuka antar setiap anggota keluarga'], 'edukasi' => ['Informasikan kondisi pasien secara berkala kepada keluarga', 'Anjurkan anggota keluarga mempertahankan keharmonisan keluarga'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            // D.0124 — Ketegangan Peran Pemberi Asuhan (updated from PDF p.623-627)
            ['diagkep_id' => 'D.0124', 'diagkep_desc' => 'Ketegangan Peran Pemberi Asuhan', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Kesulitan dalam melakukan peran pemberi asuhan dalam keluarga', 'penyebab' => ['fisiologis' => ['Beratnya penyakit penerima asuhan', 'Kronisnya penyakit penerima asuhan', 'Pemberi asuhan kurang mendapatkan waktu istirahat dan rekreasi', 'Persaingan komitmen peran pemberi asuhan', 'Ketidakadekuatan lingkungan fisik dalam pemberian asuhan', 'Keluarga atau pemberi asuhan jauh dari kerabat lain', 'Kompleksitas dan jumlah aktivitas pemberian asuhan']], 'gejala_tanda_mayor' => ['subjektif' => ['Khawatir klien akan kembali dirawat di rumah sakit', 'Khawatir tentang kelanjutan perawatan klien', 'Khawatir tentang ketidakmampuan pemberi asuhan dalam merawat klien'], 'objektif' => ['Tidak tersedia']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Sulit melakukan dan/atau menyelesaikan tugas merawat klien']], 'kondisi_klinis_terkait' => ['Kondisi kronis (mis. cedera kepala berat, cedera medulla spinalis, keterlambatan perkembangan)', 'Kondisi kelemahan progresif (mis. distrofi muskuler, sklerosis multiple, demensia, penyakit Alzheimer, PPOK tahap terminal, gagal ginjal, dialysis ginjal)', 'Penyalahgunaan zat', 'Kondisi akhir khayat (menjelang ajal)', 'Kondisi psikiatrik (mis. gangguan kepribadian, skizofrenia)']], 'slki' => [['kode' => 'L.13121', 'nama' => 'Peran Pemberi Asuhan', 'kriteria_hasil' => ['Kemampuan memberi asuhan dari skala 1 menurun menjadi skala 5 meningkat', 'Kemampuan merawat pasien dari skala 1 menurun menjadi skala 5 meningkat', 'Kekhawatiran dirawat kembali dari skala 1 meningkat menjadi skala 5 menurun', 'Kekhawatiran kelanjutan perawatan dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.12402', 'nama' => 'Edukasi Pada Pengasuh', 'definisi' => 'Memberikan informasi dan dukungan untuk memfasilitasi perawatan oleh pengasuh', 'tindakan' => ['observasi' => ['Identifikasi pemahaman dan kesiapan peran pengasuh', 'Identifikasi sumber dukungan pasien dan kebutuhan istirahat pengasuh'], 'terapeutik' => ['Berikan dukungan pada pengasuh selama mengalami kemunduran', 'Dukung keterbatasan pengasuh dan diskusikan dengan pasien', 'Fasilitasi pengasuh untuk bertanya'], 'edukasi' => ['Jelaskan dampak ketergantungan anak pada pengasuh', 'Ajarkan pengasuh mengeksplorasi kekuatan dan kelemahannya', 'Ajarkan pengasuh cara memberikan dukungan perawatan diri (mis. mandi, BAB/BAK, berpakaian/berhias, makan/minum)'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0125 — Penampilan Peran Tidak Efektif (updated from PDF p.627-629)
            ['diagkep_id' => 'D.0125', 'diagkep_desc' => 'Penampilan Peran Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Pola perilaku yang berubah atau tidak sesuai dengan harapan, norma dan lingkungan', 'penyebab' => ['fisiologis' => ['Harapan peran tidak realistis', 'Hambatan fisik', 'Harga diri rendah', 'Perubahan citra tubuh', 'Ketidakadekuatan sistem pendukung (support system)', 'Stres', 'Perubahan peran', 'Faktor ekonomi']], 'gejala_tanda_mayor' => ['subjektif' => ['Merasa bingung menjalankan peran', 'Merasa harapan tidak terpenuhi', 'Merasa tidak puas dalam menjalankan peran'], 'objektif' => ['Konflik peran', 'Adaptasi tidak adekuat', 'Strategi koping tidak efektif']], 'gejala_tanda_minor' => ['subjektif' => ['Merasa cemas'], 'objektif' => ['Depresi', 'Dukungan sosial kurang', 'Kurang bertanggung jawab menjalankan peran']], 'kondisi_klinis_terkait' => ['Penyakit keganasan organ reproduksi', 'Kondisi kronis', 'Pembedahan mayor', 'Penyalahgunaan zat', 'Cedera medula spinalis', 'Sindrom keletihan kronis', 'Depresi mayor']], 'slki' => [['kode' => 'L.13119', 'nama' => 'Penampilan Peran', 'kriteria_hasil' => ['Verbalisasi harapan terpenuhi dari skala 1 menurun meningkat menjadi 5', 'Verbalisasi kepuasan peran dari skala 1 menurun meningkat menjadi 5', 'Verbalisasi perasaan bingung menjalankan peran dari skala 1 menurun meningkat menjadi 5']]], 'siki' => [['kode' => 'I.13478', 'nama' => 'Dukungan Penampilan Peran', 'definisi' => 'Memfasilitasi pasien dan keluarga untuk memperbaiki hubungan dengan mengklarifikasi dan memenuhi perilaku peran tertentu', 'tindakan' => ['observasi' => ['Identifikasi berbagai peran dan periode transisi sesuai tingkat perkembangan', 'Identifikasi peran yang ada dalam keluarga', 'Identifikasi adanya peran yang tidak terpenuhi'], 'terapeutik' => ['Fasilitasi adaptasi peran keluarga terhadap perubahan peran yang tidak diinginkan', 'Fasilitasi bermain peran dalam mengantisipasi reaksi orang lain terhadap perilaku', 'Fasilitasi diskusi perubahan peran anak terhadap bayi baru lahir, jika perlu', 'Fasilitasi diskusi tentang peran orang tua, jika perlu', 'Fasilitasi diskusi tentang adaptasi peran saat anak meninggalkan rumah, jika perlu', 'Fasilitasi diskusi harapan dengan keluarga dalam peran timbal balik'], 'edukasi' => ['Diskusikan perilaku yang dibutuhkan untuk pengembangan peran', 'Diskusikan perubahan peran yang diperlukan akibat penyakit atau ketidakmampuan', 'Diskusikan perubahan peran dalam menerima ketergantungan orang tua', 'Diskusikan strategi positif untuk mengelola perubahan peran', 'Ajarkan perilaku baru yang dibutuhkan oleh pasien/orang tua untuk memenuhi peran'], 'kolaborasi' => ['Rujuk dalam kelompok untuk mempelajari peran baru']]]]]],
            // D.0126 — Pencapaian Peran Menjadi Orang Tua (updated from PDF p.629-630)
            ['diagkep_id' => 'D.0126', 'diagkep_desc' => 'Pencapaian Peran Menjadi Orang Tua', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Terjadinya proses interaktif antar anggota keluarga (suami-istri, anggota keluarga dan bayi) yang ditunjukkan dengan perkembangan bayi yang optimal', 'gejala_tanda_mayor' => ['subjektif' => ['Mengungkapkan kepuasan dengan bayi'], 'objektif' => ['Melakukan stimulasi visual, taktil atau pendengaran terhadap bayi', 'Saling berinteraksi dalam merawat bayi']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Bounding attachment optimal', 'Perilaku positif menjadi orang tua']], 'kondisi_klinis_terkait' => ['Status kesehatan ibu', 'Status kesehatan bayi']], 'slki' => [['kode' => 'L.13120', 'nama' => 'Peran Menjadi Orang Tua', 'kriteria_hasil' => ['Perilaku positif menjadi orang tua dari skala 1 menjadi skala 5 meningkat', 'Interaksi perawatan bayi dari skala 1 menjadi skala 5 meningkat', 'Bounding attachment dari skala 1 menurun menjadi skala 5 meningkat']]], 'siki' => [['kode' => 'I.12466', 'nama' => 'Promosi Antisipasi Keluarga', 'definisi' => 'Meningkatkan kesiapan keluarga untuk mencegah perkembangan atau krisis situasi akibat masalah kesehatan', 'tindakan' => ['observasi' => ['Identifikasi kemungkinan krisis situasi atau masalah perkembangan serta dampaknya pada kehidupan pasien dan keluarganya', 'Identifikasi metode pemecahan masalah yang sering digunakan keluarga'], 'terapeutik' => ['Fasilitasi dalam memutuskan strategi pemecahan masalah yang dihadapi keluarga', 'Libatkan seluruh anggota keluarga dalam upaya antisipasi masalah kesehatan, jika memungkinkan', 'Lakukan kunjungan kepada keluarga secara berkala, jika perlu', 'Buat jadwal aktivitas bersama keluarga terkait masalah kesehatan yang dihadapi'], 'edukasi' => ['Jelaskan perkembangan dan perilaku yang normal kepada keluarga'], 'kolaborasi' => ['Kerjasama dengan tenaga kesehatan terkait lainnya, jika perlu']]]]]],
            // D.0127 — Risiko Gangguan Perlekatan (updated from PDF p.631-633)
            ['diagkep_id' => 'D.0127', 'diagkep_desc' => 'Risiko Gangguan Perlekatan', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Berisiko mengalami gangguan interaksi antara orang tua atau orang terdekat dengan bayi/anak yang dapat mempengaruhi proses asah, asih dan asuh', 'penyebab' => [], 'faktor_risiko' => ['Kekhawatiran menjalankan peran sebagai orang tua', 'Perpisahan antara ibu dan bayi/anak akibat hospitalisasi', 'Penghalang fisik (mis. inkubator, baby warmer)', 'Ketidakmampuan orang tua memenuhi kebutuhan bayi/anak', 'Perawatan dalam ruang isolasi', 'Prematuritas', 'Penyalahgunaan zat', 'Konflik hubungan antara orang tua dan anak', 'Perilaku bayi tidak terkoordinasi'], 'kondisi_klinis_terkait' => ['Hospitalis', 'Prematuritas', 'Penyakit kronis pada orang tua atau anak', 'Retardasi mental', 'Komplikasi maternal', 'Sakit selama periode hamil dan melahirkan', 'Post parfum blues']], 'slki' => [['kode' => 'L.13122', 'nama' => 'Perlekatan Meningkat', 'kriteria_hasil' => ['Mempraktikkan perilaku sehat selama hamil meningkat', 'Menyiapkan perlengkapan bayi sebelum kelahiran meningkat', 'Verbalisasi perasaan positif terhadap bayi meningkat', 'Mencium bayi meningkat', 'Melakukan kontak mata dengan bayi meningkat', 'Berbicara dengan bayi meningkat', 'Bermain dengan bayi meningkat', 'Berespons dengan isyarat bayi meningkat', 'Kekhawatiran menjalankan peran orang tua menurun', 'Konflik hubungan orang tua dan bayi/anak menurun']]], 'siki' => [['kode' => 'I.10342', 'nama' => 'Promosi Perlekatan', 'definisi' => 'Meningkatkan dan mempertahankan perlekatan atau latch on secara tepat', 'tindakan' => ['observasi' => ['Monitor kegiatan menyusul', 'Identifikasi kemampuan bayi mengisap dan menelan ASI', 'Identifikasi payudara ibu (mis. bengkak, putting lecet, mastitis, nyeri pada payudara)', 'Monitor perlekatan saat menyusi (mis. areola bagian bawah lebih kecil daripada areola bagian atas, mulut bayi terbuka lebar, bibir bayi terputar keluar dan dagu bayi menempel pada payudara ibu)'], 'terapeutik' => ['Hindari memegang kepala bayi', 'Diskusikan dengan ibu masalah selama proses menyusui'], 'edukasi' => ['Ajarkan ibu menopeng seluruh tubuh bayi', 'Anjurkan ibu melepas pakaian bagian atas agar bayi dapat menyentuh payudara ibu', 'Anjurkan bayi yang mendekati kearah payudara ibu dan bagian bawah', 'Anjurkan ibu untuk memegang payudara menggunakan jarinya seperti huruf C pada posisi jam 12-6 atau 3-9 saat mengarahkan ke mulut bayi', 'Anjurkan ibu untuk menyusui menunggu mulut bayi terbuka lebar sehingga areola bagian bawah dapat masuk sempurna', 'Anjurkan ibu mengenali tanda bayi siap menyusu'], 'kolaborasi' => ['Tidak tersedia']]]]]],
            // D.0128 — Risiko Proses Pengasuhan Tidak Efektif (updated from PDF p.634-636)
            ['diagkep_id' => 'D.0128', 'diagkep_desc' => 'Risiko Proses Pengasuhan Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Relasional', 'subkategori' => 'Interaksi Sosial', 'definisi' => 'Berisiko mengalami gangguan proses kehamilan, persalinan dan setelah melahirkan termasuk perawatan bayi baru lahir yang tidak sesuai dengan konteks norma dan harapan', 'penyebab' => [], 'faktor_risiko' => ['Kekerasan dalam rumah tangga', 'Kehamilan tidak diinginkan/direncanakan', 'Kurang terpapar informasi tentang proses persalinan/pengasuhan', 'Ketidakberdayaan maternal', 'Distres psikologis', 'Penyalahgunaan obat', 'Ketidakadekuatan manajemen ketidaknyamanan selama persalinan', 'Akses pelayanan kesehatan sulit dijangkau', 'Kurangnya minat/proaktif dalam proses persalinan', 'Ketidaksesuaian kondisi bayi dengan harapan', 'Ketidaksesuaian kondisi bayi dengan harapan'], 'kondisi_klinis_terkait' => ['Gangguan pertumbuhan janin', 'Gangguan kesehatan fisik dan psikologis ibu']], 'slki' => [['kode' => 'L.13124', 'nama' => 'Proses Pengasuhan', 'kriteria_hasil' => ['Terpapar informasi tentang proses persalinan/pengasuh dari skala 1 menurun menjadi skala 5 meningkat', 'Kekerasan dalam ruma tangga dari skala 1 meningkat menjadi skala 5 menurun']]], 'siki' => [['kode' => 'I.13490', 'nama' => 'Promosi Keutuhan Keluarga', 'definisi' => 'Meningkatkan pengetahuan dan kemampuan pasien untuk menjaga dan meningkatkan kerekatan dan keutuhan keluarga', 'tindakan' => ['observasi' => ['Identifikasi pemahaman keluarga terhadap masalah', 'Identifikasi adanya konflik prioritas antar anggota keluarga', 'Identifikasi mekanisme koping keluarga', 'Monitor hubungan antara anggota keluarga'], 'terapeutik' => ['Hargai privasi keluarga', 'Fasilitasi kunjungan keluarga', 'Fasilitasi keluarga melakukan pengambilan keputusan dan pemecahan masalah', 'Fasilitasi komunikasi terbuka antar setiap anggota keluarga'], 'edukasi' => ['Informasikan kondisi pasien secara berkala kepada keluarga', 'Anjurkan anggota keluarga mempertahankan keharmonisan keluarga'], 'kolaborasi' => ['Rujuk untuk terapi keluarga, jika perlu']]]]]],
            ['diagkep_id' => 'D.0129', 'diagkep_desc' => 'Gangguan Integritas Kulit/Jaringan', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Kerusakan kulit (dermis dan/atau epidermis) atau jaringan', 'penyebab' => ['fisiologis' => ['Perubahan sirkulasi', 'Perubahan status nutrisi', 'Penurunan mobilitas', 'Bahan kimia iritatif', 'Suhu lingkungan yang ekstrem', 'Faktor mekanis', 'Efek samping terapi radiasi', 'Neuropati perifer']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Kerusakan jaringan dan/atau lapisan kulit']], 'kondisi_klinis_terkait' => ['Imobilisasi', 'Gagal jantung kongestif', 'Diabetes melitus', 'Imunodefisiensi']], 'slki' => [['kode' => 'L.14125', 'nama' => 'Integritas Kulit dan Jaringan', 'kriteria_hasil' => ['Kerusakan jaringan menurun', 'Kerusakan lapisan kulit menurun', 'Nyeri menurun', 'Kemerahan menurun']]], 'siki' => [['kode' => 'I.11353', 'nama' => 'Perawatan Integritas Kulit', 'definisi' => 'Mengidentifikasi dan merawat kulit', 'tindakan' => ['observasi' => ['Identifikasi penyebab gangguan integritas kulit'], 'terapeutik' => ['Ubah posisi tiap 2 jam jika tirah baring', 'Gunakan produk berbahan petroleum pada kulit kering'], 'edukasi' => ['Anjurkan menggunakan pelembab', 'Anjurkan minum air yang cukup', 'Anjurkan meningkatkan asupan nutrisi'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0130', 'diagkep_desc' => 'Hipertermia', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Suhu tubuh meningkat di atas rentang normal tubuh', 'penyebab' => ['fisiologis' => ['Dehidrasi', 'Terpapar lingkungan panas', 'Proses penyakit (mis. infeksi, kanker)', 'Peningkatan laju metabolisme', 'Aktivitas berlebihan']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Suhu tubuh diatas nilai normal']], 'kondisi_klinis_terkait' => ['Proses infeksi', 'Hipertiroid', 'Stroke', 'Dehidrasi']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Kulit merah menurun', 'Kejang menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.15506', 'nama' => 'Manajemen Hipertermia', 'definisi' => 'Mengidentifikasi dan mengelola peningkatan suhu tubuh', 'tindakan' => ['observasi' => ['Identifikasi penyebab hipertermia', 'Monitor suhu tubuh', 'Monitor kadar elektrolit'], 'terapeutik' => ['Sediakan lingkungan yang dingin', 'Longgarkan atau lepaskan pakaian', 'Berikan cairan oral', 'Lakukan pendinginan eksternal'], 'edukasi' => ['Anjurkan tirah baring'], 'kolaborasi' => ['Kolaborasi pemberian cairan dan elektrolit intravena, jika perlu']]]]]],
            ['diagkep_id' => 'D.0131', 'diagkep_desc' => 'Hipotermia', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Suhu tubuh berada di bawah rentang normal tubuh', 'penyebab' => ['fisiologis' => ['Kerusakan hipotalamus', 'Terpapar suhu lingkungan rendah', 'Malnutrisi', 'Prematuritas', 'Kekurangan lemak subkutan']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Kulit teraba dingin', 'Menggigil', 'Suhu tubuh di bawah nilai normal']], 'kondisi_klinis_terkait' => ['Hipotiroidisme', 'Anoreksia nervosa', 'Gagal jantung', 'Prematuritas']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Menggigil menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.14507', 'nama' => 'Manajemen Hipotermia', 'definisi' => 'Mengidentifikasi dan mengelola suhu tubuh dibawah normal', 'tindakan' => ['observasi' => ['Monitor suhu tubuh', 'Identifikasi penyebab hipotermia'], 'terapeutik' => ['Sediakan lingkungan yang hangat', 'Lakukan penghangatan pasif (mis. selimut, menutup kepala)', 'Lakukan penghangatan aktif eksternal'], 'edukasi' => ['Anjurkan makan/minum hangat'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0132', 'diagkep_desc' => 'Perilaku Kekerasan', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Tindakan yang dapat membahayakan secara fisik, baik terhadap diri sendiri, orang lain maupun lingkungan', 'penyebab' => ['fisiologis' => ['Ketidakmampuan mengendalikan dorongan marah', 'Gangguan persepsi', 'Gangguan proses pikir']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengancam', 'Mengumpat'], 'objektif' => ['Menyerang orang lain', 'Melukai diri sendiri/orang lain', 'Merusak lingkungan', 'Perilaku agresif']], 'kondisi_klinis_terkait' => ['Skizofrenia', 'Gangguan bipolar', 'Penyalahgunaan zat']], 'slki' => [['kode' => 'L.09076', 'nama' => 'Kontrol Diri', 'kriteria_hasil' => ['Perilaku menyerang menurun', 'Perilaku melukai diri sendiri/orang lain menurun']]], 'siki' => [['kode' => 'I.09287', 'nama' => 'Manajemen Perilaku Kekerasan', 'definisi' => 'Mengidentifikasi dan mengelola perilaku kekerasan', 'tindakan' => ['observasi' => ['Monitor adanya benda yang berpotensi membahayakan'], 'terapeutik' => ['Pertahankan lingkungan yang aman', 'Lakukan restrain fisik, jika perlu'], 'edukasi' => ['Anjurkan pengungkapan perasaan secara asertif', 'Latih teknik relaksasi'], 'kolaborasi' => ['Kolaborasi pemberian obat penenang, jika perlu']]]]]],
            ['diagkep_id' => 'D.0133', 'diagkep_desc' => 'Perlambatan Pemulihan Pascabedah', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Perpanjangan jumlah hari pasca operasi yang diperlukan untuk memulai dan melaksanakan aktivitas guna mempertahankan hidup, kesehatan, dan kesejahteraan', 'penyebab' => ['fisiologis' => ['Kontaminasi luka bedah', 'Malnutrisi', 'Nyeri', 'Obesitas', 'Infeksi pada area insisi', 'Diabetes melitus']], 'gejala_tanda_mayor' => ['subjektif' => ['Mengeluh tidak nyaman', 'Nyeri area insisi'], 'objektif' => ['Membutuhkan bantuan untuk menyelesaikan aktivitas', 'Luka insisi tidak sembuh sesuai waktu']], 'kondisi_klinis_terkait' => ['Prosedur pembedahan', 'Diabetes melitus', 'Obesitas']], 'slki' => [['kode' => 'L.14130', 'nama' => 'Pemulihan Pasca-Bedah', 'kriteria_hasil' => ['Kenyamanan meningkat', 'Nyeri menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.14569', 'nama' => 'Perawatan Pasca-Bedah', 'definisi' => 'Mengidentifikasi dan merawat pasien setelah pembedahan', 'tindakan' => ['observasi' => ['Monitor kondisi kesehatan pasien', 'Monitor tanda-tanda vital', 'Monitor area operasi terhadap tanda infeksi'], 'terapeutik' => ['Pertahankan jalan napas tetap paten', 'Fasilitasi mobilisasi dini', 'Berikan perawatan luka operasi'], 'edukasi' => ['Ajarkan cara merawat luka operasi', 'Anjurkan mobilisasi secara bertahap'], 'kolaborasi' => ['Kolaborasi pemberian analgesik, jika perlu']]]]]],
            ['diagkep_id' => 'D.0134', 'diagkep_desc' => 'Risiko Alergi', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami respons imun yang berlebihan atau menyimpang', 'penyebab' => [], 'faktor_risiko' => ['Terpapar bahan alergen', 'Riwayat alergi pada individu', 'Riwayat alergi pada keluarga', 'Status asthmatikus'], 'kondisi_klinis_terkait' => ['Asma', 'Rhinitis alergi', 'Dermatitis kontak']], 'slki' => [['kode' => 'L.14127', 'nama' => 'Respons Alergi Lokal', 'kriteria_hasil' => ['Ruam kulit menurun', 'Gatal-gatal menurun', 'Edema menurun']]], 'siki' => [['kode' => 'I.14536', 'nama' => 'Manajemen Alergi', 'definisi' => 'Mengidentifikasi, merawat dan mencegah respons alergi', 'tindakan' => ['observasi' => ['Identifikasi riwayat alergi', 'Monitor tanda dan gejala reaksi alergi'], 'terapeutik' => ['Pasang gelang identifikasi alergi, jika perlu'], 'edukasi' => ['Ajarkan menghindari faktor pemicu reaksi alergi'], 'kolaborasi' => ['Kolaborasi skin test, jika perlu']]]]]],
            ['diagkep_id' => 'D.0135', 'diagkep_desc' => 'Risiko Bunuh Diri', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko melakukan upaya menyakiti diri sendiri untuk mengakhiri kehidupan', 'penyebab' => [], 'faktor_risiko' => ['Gangguan psikiatrik', 'Masalah kesehatan fisik', 'Isolasi sosial', 'Riwayat upaya bunuh diri'], 'kondisi_klinis_terkait' => ['Depresi', 'Gangguan kepribadian', 'Skizofrenia', 'Penyakit terminal']], 'slki' => [['kode' => 'L.09076', 'nama' => 'Kontrol Diri', 'kriteria_hasil' => ['Perilaku melukai diri sendiri menurun']]], 'siki' => [['kode' => 'I.09290', 'nama' => 'Pencegahan Bunuh Diri', 'definisi' => 'Mengidentifikasi dan menurunkan risiko untuk menyakiti diri sendiri', 'tindakan' => ['observasi' => ['Identifikasi gejala risiko bunuh diri', 'Monitor lingkungan bebas bahaya secara rutin'], 'terapeutik' => ['Libatkan dalam perencanaan perawatan mandiri', 'Berikan lingkungan dengan pengamanan ketat'], 'edukasi' => ['Anjurkan mendiskusikan perasaan', 'Informasikan sumber daya dan hotline yang tersedia'], 'kolaborasi' => ['Rujuk ke pelayanan kesehatan mental']]]]]],
            ['diagkep_id' => 'D.0136', 'diagkep_desc' => 'Risiko Cedera', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami bahaya atau kerusakan fisik', 'penyebab' => [], 'faktor_risiko' => ['Ketidakamanan transportasi', 'Perubahan sensasi', 'Malnutrisi', 'Perubahan fungsi kognitif', 'Terpapar patogen', 'Terpapar zat kimia toksik'], 'kondisi_klinis_terkait' => ['Epilepsi', 'Gangguan penglihatan', 'Gangguan keseimbangan', 'Osteoporosis', 'Diabetes melitus']], 'slki' => [['kode' => 'L.14136', 'nama' => 'Tingkat Cedera', 'kriteria_hasil' => ['Kejadian cedera menurun', 'Luka/lecet menurun']]], 'siki' => [['kode' => 'I.14513', 'nama' => 'Manajemen Keselamatan Lingkungan', 'definisi' => 'Mengidentifikasi dan mengelola lingkungan fisik untuk meningkatkan keselamatan', 'tindakan' => ['observasi' => ['Identifikasi kebutuhan keselamatan', 'Monitor perubahan status keselamatan lingkungan'], 'terapeutik' => ['Hilangkan bahaya keselamatan lingkungan', 'Modifikasi lingkungan untuk meminimalkan bahaya', 'Sediakan alat bantu keamanan lingkungan'], 'edukasi' => ['Ajarkan individu dan keluarga tentang bahaya lingkungan'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0137', 'diagkep_desc' => 'Risiko Cedera pada Ibu', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami bahaya atau kerusakan fisik pada ibu selama proses kehamilan, persalinan, dan nifas', 'penyebab' => [], 'faktor_risiko' => ['Kehamilan ektopik', 'Usia ibu <15 tahun atau >35 tahun', 'Ketuban pecah dini', 'Plasenta previa', 'Preeklampsia'], 'kondisi_klinis_terkait' => ['Kehamilan risiko tinggi', 'Preeklampsia/eklampsia']], 'slki' => [['kode' => 'L.14136', 'nama' => 'Tingkat Cedera', 'kriteria_hasil' => ['Kejadian cedera menurun']]], 'siki' => [['kode' => 'I.14513', 'nama' => 'Manajemen Keselamatan Lingkungan', 'definisi' => 'Mengidentifikasi dan mengelola lingkungan fisik', 'tindakan' => ['observasi' => ['Identifikasi kebutuhan keselamatan'], 'terapeutik' => ['Modifikasi lingkungan untuk meminimalkan bahaya'], 'edukasi' => ['Ajarkan tentang risiko tinggi'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0138', 'diagkep_desc' => 'Risiko Cedera pada Janin', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami bahaya atau kerusakan fisik pada janin selama proses kehamilan dan persalinan', 'penyebab' => [], 'faktor_risiko' => ['Komplikasi kehamilan', 'Penyalahgunaan zat pada ibu', 'Merokok', 'Malnutrisi pada ibu', 'Kehamilan kembar'], 'kondisi_klinis_terkait' => ['Kehamilan risiko tinggi', 'Gawat janin']], 'slki' => [['kode' => 'L.14136', 'nama' => 'Tingkat Cedera', 'kriteria_hasil' => ['Kejadian cedera menurun']]], 'siki' => [['kode' => 'I.07221', 'nama' => 'Pemantauan Denyut Jantung Janin', 'definisi' => 'Mengumpulkan dan menganalisis data denyut jantung janin', 'tindakan' => ['observasi' => ['Identifikasi status obstetrik', 'Monitor denyut jantung janin'], 'terapeutik' => ['Lakukan manuver Leopold', 'Dokumentasikan hasil pemantauan'], 'edukasi' => ['Jelaskan tujuan dan prosedur pemantauan'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0139', 'diagkep_desc' => 'Risiko Gangguan Integritas Kulit/Jaringan', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami kerusakan kulit atau jaringan', 'penyebab' => [], 'faktor_risiko' => ['Perubahan sirkulasi', 'Perubahan status nutrisi', 'Penurunan mobilitas', 'Bahan kimia iritatif', 'Suhu lingkungan ekstrem', 'Faktor mekanis', 'Neuropati perifer'], 'kondisi_klinis_terkait' => ['Imobilisasi', 'Gagal jantung kongestif', 'Diabetes melitus', 'Imunodefisiensi']], 'slki' => [['kode' => 'L.14125', 'nama' => 'Integritas Kulit dan Jaringan', 'kriteria_hasil' => ['Kerusakan jaringan menurun', 'Kerusakan lapisan kulit menurun']]], 'siki' => [['kode' => 'I.11353', 'nama' => 'Perawatan Integritas Kulit', 'definisi' => 'Mengidentifikasi dan merawat kulit', 'tindakan' => ['observasi' => ['Identifikasi penyebab gangguan integritas kulit'], 'terapeutik' => ['Ubah posisi tiap 2 jam jika tirah baring', 'Gunakan produk berbahan petroleum pada kulit kering'], 'edukasi' => ['Anjurkan menggunakan pelembab', 'Anjurkan minum air yang cukup'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0140', 'diagkep_desc' => 'Risiko Hipotermia', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami penurunan suhu tubuh di bawah rentang normal', 'penyebab' => [], 'faktor_risiko' => ['Kerusakan hipotalamus', 'Berat badan ekstrem', 'Kekurangan lemak subkutan', 'Terpapar suhu lingkungan rendah', 'Malnutrisi', 'Prematuritas', 'Efek agen farmakologis'], 'kondisi_klinis_terkait' => ['Hipotiroidisme', 'Anoreksia nervosa', 'Gagal jantung', 'Prematuritas']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Menggigil menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.14507', 'nama' => 'Manajemen Hipotermia', 'definisi' => 'Mengidentifikasi dan mengelola suhu tubuh dibawah normal', 'tindakan' => ['observasi' => ['Monitor suhu tubuh', 'Identifikasi penyebab hipotermia'], 'terapeutik' => ['Sediakan lingkungan yang hangat', 'Lakukan penghangatan pasif (selimut, menutup kepala)'], 'edukasi' => ['Anjurkan makan/minum hangat'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0141', 'diagkep_desc' => 'Risiko Hipotermia Perioperatif', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami penurunan suhu tubuh di bawah 36°C secara tidak sengaja perioperatif', 'penyebab' => [], 'faktor_risiko' => ['Prosedur pembedahan', 'Suhu ruang operasi rendah', 'Berat badan rendah', 'Neuropati diabetik', 'Usia lanjut', 'Kombinasi anestesi regional dan umum'], 'kondisi_klinis_terkait' => ['Prosedur pembedahan', 'Luka bakar']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Menggigil menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.14507', 'nama' => 'Manajemen Hipotermia', 'definisi' => 'Mengidentifikasi dan mengelola suhu tubuh', 'tindakan' => ['observasi' => ['Monitor suhu tubuh'], 'terapeutik' => ['Sediakan lingkungan yang hangat', 'Lakukan penghangatan pasif dan aktif'], 'edukasi' => ['Anjurkan makan/minum hangat'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0142', 'diagkep_desc' => 'Risiko Infeksi', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami peningkatan terserang organisme patogenik', 'penyebab' => [], 'faktor_risiko' => ['Penyakit kronis (mis. diabetes melitus)', 'Efek prosedur invasif', 'Malnutrisi', 'Peningkatan paparan organisme patogen lingkungan', 'Ketidakadekuatan pertahanan tubuh primer', 'Ketidakadekuatan pertahanan tubuh sekunder'], 'kondisi_klinis_terkait' => ['AIDS', 'Luka bakar', 'PPOK', 'Diabetes melitus', 'Tindakan invasif', 'Kanker', 'Gagal ginjal', 'Imunosupresi']], 'slki' => [['kode' => 'L.14137', 'nama' => 'Tingkat Infeksi', 'kriteria_hasil' => ['Kebersihan tangan meningkat', 'Kebersihan badan meningkat', 'Demam menurun', 'Kemerahan menurun', 'Nyeri menurun', 'Bengkak menurun']]], 'siki' => [['kode' => 'I.14539', 'nama' => 'Pencegahan Infeksi', 'definisi' => 'Mengidentifikasi dan menurunkan risiko terserang organisme patogenik', 'tindakan' => ['observasi' => ['Monitor tanda dan gejala infeksi lokal dan sistemik'], 'terapeutik' => ['Batasi jumlah pengunjung', 'Cuci tangan sebelum dan sesudah kontak dengan pasien', 'Pertahankan teknik aseptik pada pasien berisiko tinggi'], 'edukasi' => ['Jelaskan tanda dan gejala infeksi', 'Ajarkan cara mencuci tangan dengan benar', 'Ajarkan etika batuk', 'Anjurkan meningkatkan asupan nutrisi dan cairan'], 'kolaborasi' => ['Kolaborasi pemberian imunisasi, jika perlu']]]]]],
            ['diagkep_id' => 'D.0143', 'diagkep_desc' => 'Risiko Jatuh', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami kerusakan fisik dan gangguan kesehatan akibat terjatuh', 'penyebab' => [], 'faktor_risiko' => ['Usia ≥65 tahun atau ≤2 tahun', 'Riwayat jatuh', 'Penurunan tingkat kesadaran', 'Lingkungan tidak aman', 'Kekuatan otot menurun', 'Gangguan keseimbangan', 'Gangguan penglihatan', 'Neuropati', 'Efek agen farmakologis'], 'kondisi_klinis_terkait' => ['Osteoporosis', 'Kejang', 'Katarak', 'Glaukoma', 'Demensia', 'Amputasi']], 'slki' => [['kode' => 'L.14138', 'nama' => 'Tingkat Jatuh', 'kriteria_hasil' => ['Jatuh dari tempat tidur menurun', 'Jatuh saat berdiri menurun', 'Jatuh saat berjalan menurun']]], 'siki' => [['kode' => 'I.14540', 'nama' => 'Pencegahan Jatuh', 'definisi' => 'Mengidentifikasi dan menurunkan risiko terjatuh', 'tindakan' => ['observasi' => ['Identifikasi faktor risiko jatuh', 'Identifikasi faktor lingkungan yang meningkatkan risiko jatuh', 'Hitung risiko jatuh dengan menggunakan skala', 'Monitor kemampuan berpindah'], 'terapeutik' => ['Orientasikan ruangan pada pasien dan keluarga', 'Pastikan roda tempat tidur dan kursi roda selalu terkunci', 'Pasang handrail tempat tidur', 'Atur tempat tidur mekanis pada posisi terendah', 'Gunakan alat bantu berjalan', 'Dekatkan bel pemanggil dalam jangkauan pasien'], 'edukasi' => ['Anjurkan memanggil perawat jika membutuhkan bantuan untuk berpindah', 'Anjurkan menggunakan alas kaki yang tidak licin', 'Ajarkan cara menggunakan bel pemanggil'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0144', 'diagkep_desc' => 'Risiko Luka Tekan', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami cedera lokal pada kulit dan/atau jaringan di atas tonjolan tulang akibat tekanan', 'penyebab' => [], 'faktor_risiko' => ['Perubahan sirkulasi', 'Penurunan mobilitas', 'Penurunan sensasi', 'Kekurangan nutrisi', 'Kulit kering atau lembab berlebih', 'Tekanan pada tonjolan tulang', 'Inkontinensia'], 'kondisi_klinis_terkait' => ['Cedera medula spinalis', 'Diabetes melitus', 'Stroke', 'Anemia']], 'slki' => [['kode' => 'L.14125', 'nama' => 'Integritas Kulit dan Jaringan', 'kriteria_hasil' => ['Kerusakan jaringan menurun', 'Kerusakan lapisan kulit menurun']]], 'siki' => [['kode' => 'I.14566', 'nama' => 'Pencegahan Luka Tekan', 'definisi' => 'Mengidentifikasi dan menurunkan risiko cedera lokal', 'tindakan' => ['observasi' => ['Monitor warna, suhu, kelembaban, dan kekeringan kulit', 'Monitor mobilitas dan aktivitas individu', 'Monitor status nutrisi'], 'terapeutik' => ['Ubah posisi tiap 1-2 jam, jika tirah baring', 'Gunakan bantal di antara kaki saat posisi lateral', 'Jaga area perineal tetap bersih dan kering', 'Gunakan kasur khusus'], 'edukasi' => ['Jelaskan tanda-tanda kerusakan kulit'], 'kolaborasi' => []]]]]],
            ['diagkep_id' => 'D.0145', 'diagkep_desc' => 'Risiko Mutilasi Diri', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko melakukan mutilasi pada diri sendiri dengan sengaja untuk meredakan ketegangan (bukan dengan tujuan bunuh diri)', 'penyebab' => [], 'faktor_risiko' => ['Gangguan kepribadian (mis. borderline)', 'Riwayat perilaku melukai diri sendiri', 'Harga diri rendah', 'Isolasi dari teman sebaya', 'Penyalahgunaan zat'], 'kondisi_klinis_terkait' => ['Gangguan kepribadian ambang', 'PTSD', 'Depresi', 'Autisme']], 'slki' => [['kode' => 'L.09076', 'nama' => 'Kontrol Diri', 'kriteria_hasil' => ['Perilaku melukai diri sendiri menurun']]], 'siki' => [['kode' => 'I.09287', 'nama' => 'Manajemen Perilaku Kekerasan', 'definisi' => 'Mengidentifikasi dan mengelola perilaku kekerasan', 'tindakan' => ['observasi' => ['Monitor adanya benda yang berpotensi membahayakan'], 'terapeutik' => ['Pertahankan lingkungan yang aman'], 'edukasi' => ['Anjurkan pengungkapan perasaan secara asertif', 'Latih teknik relaksasi'], 'kolaborasi' => ['Kolaborasi pemberian obat penenang, jika perlu', 'Rujuk ke pelayanan kesehatan mental']]]]]],
            ['diagkep_id' => 'D.0146', 'diagkep_desc' => 'Risiko Perilaku Kekerasan', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko melakukan tindakan yang dapat membahayakan secara fisik baik terhadap diri sendiri, orang lain maupun lingkungan', 'penyebab' => [], 'faktor_risiko' => ['Riwayat kekerasan terhadap orang lain', 'Riwayat penyalahgunaan zat', 'Gangguan kognitif', 'Gangguan psikiatrik', 'Impulsivitas'], 'kondisi_klinis_terkait' => ['Skizofrenia', 'Gangguan kepribadian antisosial', 'Intoksikasi zat', 'Demensia', 'Delirium']], 'slki' => [['kode' => 'L.09076', 'nama' => 'Kontrol Diri', 'kriteria_hasil' => ['Perilaku menyerang menurun', 'Perilaku melukai orang lain menurun']]], 'siki' => [['kode' => 'I.09287', 'nama' => 'Manajemen Perilaku Kekerasan', 'definisi' => 'Mengidentifikasi dan mengelola perilaku kekerasan', 'tindakan' => ['observasi' => ['Monitor adanya benda yang berpotensi membahayakan', 'Monitor perilaku kekerasan'], 'terapeutik' => ['Pertahankan lingkungan yang aman', 'Lakukan restrain fisik, jika perlu'], 'edukasi' => ['Anjurkan pengungkapan perasaan secara asertif', 'Latih teknik relaksasi'], 'kolaborasi' => ['Kolaborasi pemberian obat penenang, jika perlu']]]]]],
            ['diagkep_id' => 'D.0147', 'diagkep_desc' => 'Risiko Perlambatan Pemulihan Pascabedah', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami perpanjangan jumlah hari pasca operasi yang diperlukan untuk memulai dan melaksanakan aktivitas guna mempertahankan hidup, kesehatan, dan kesejahteraan', 'penyebab' => [], 'faktor_risiko' => ['Prosedur bedah ekstensif', 'Prosedur bedah diperpanjang', 'Kontaminasi luka bedah', 'Gangguan mobilitas', 'Malnutrisi', 'Nyeri', 'Diabetes melitus', 'Edema di tempat insisi', 'Agen farmakologis', 'Obesitas', 'Infeksi pada area insisi bedah', 'Mual atau muntah persisten', 'Respons emosional pasca operasi'], 'kondisi_klinis_terkait' => ['Prosedur pembedahan', 'Diabetes melitus', 'Obesitas', 'Kanker']], 'slki' => [['kode' => 'L.14130', 'nama' => 'Pemulihan Pasca-Bedah', 'kriteria_hasil' => ['Kenyamanan meningkat', 'Perawatan diri meningkat', 'Mual menurun', 'Muntah menurun', 'Nyeri menurun', 'Area luka operasi membaik', 'Suhu tubuh membaik', 'Tekanan darah membaik']]], 'siki' => [['kode' => 'I.14569', 'nama' => 'Perawatan Pasca-Bedah', 'definisi' => 'Mengidentifikasi dan merawat pasien setelah pembedahan', 'tindakan' => ['observasi' => ['Monitor kondisi kesehatan pasien', 'Monitor tanda-tanda vital', 'Monitor area operasi terhadap adanya tanda infeksi', 'Monitor tingkat nyeri dan respons terhadap analgesik', 'Monitor status keseimbangan cairan'], 'terapeutik' => ['Pertahankan jalan napas tetap paten', 'Pertahankan posisi yang aman dan nyaman', 'Fasilitasi mobilisasi dini', 'Berikan perawatan luka operasi'], 'edukasi' => ['Jelaskan tujuan dan langkah-langkah perawatan', 'Ajarkan cara merawat luka operasi di rumah', 'Anjurkan melakukan mobilisasi secara bertahap', 'Anjurkan kontrol/kunjungan ulang sesuai jadwal'], 'kolaborasi' => ['Kolaborasi pemberian analgesik, jika perlu', 'Kolaborasi pemberian antiemetik, jika perlu']]]]]],
            ['diagkep_id' => 'D.0148', 'diagkep_desc' => 'Risiko Termoregulasi Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Berisiko mengalami kegagalan mempertahankan suhu tubuh dalam rentang normal', 'penyebab' => [], 'faktor_risiko' => ['Perubahan laju metabolisme', 'Dehidrasi', 'Terpapar suhu lingkungan yang ekstrem', 'Proses penyakit', 'Prematuritas', 'Proses penuaan', 'Berat badan ekstrem', 'Efek agen farmakologis'], 'kondisi_klinis_terkait' => ['Infeksi', 'Sepsis', 'Prematuritas', 'Luka bakar', 'Trauma']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Menggigil menurun', 'Kulit merah menurun', 'Suhu tubuh membaik']]], 'siki' => [['kode' => 'I.14578', 'nama' => 'Regulasi Temperatur', 'definisi' => 'Mempertahankan suhu tubuh dalam rentang normal', 'tindakan' => ['observasi' => ['Monitor suhu tubuh', 'Monitor tekanan darah, frekuensi pernapasan dan nadi', 'Monitor warna dan suhu kulit'], 'terapeutik' => ['Tingkatkan asupan cairan dan nutrisi yang adekuat', 'Sesuaikan suhu lingkungan dengan kebutuhan pasien'], 'edukasi' => ['Jelaskan cara pencegahan heat exhaustion dan hipotermia'], 'kolaborasi' => ['Kolaborasi pemberian antipiretik, jika perlu']]]]]],
            ['diagkep_id' => 'D.0149', 'diagkep_desc' => 'Termoregulasi Tidak Efektif', 'diagkep_json' => ['sdki' => ['kategori' => 'Lingkungan', 'subkategori' => 'Keamanan dan Proteksi', 'definisi' => 'Kegagalan mempertahankan suhu tubuh dalam rentang normal', 'penyebab' => ['fisiologis' => ['Stimulasi pusat termoregulasi hipotalamus', 'Fluktuasi suhu lingkungan', 'Proses penyakit (mis. infeksi)', 'Proses penuaan', 'Dehidrasi', 'Ketidaksesuaian pakaian untuk suhu lingkungan', 'Peningkatan kebutuhan oksigen', 'Perubahan laju metabolisme', 'Suhu lingkungan ekstrem', 'Ketidakadekuatan suplai lemak subkutan', 'Berat badan ekstrem', 'Efek agen farmakologis (mis. sedasi)']], 'gejala_tanda_mayor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Kulit dingin/hangat', 'Menggigil', 'Suhu tubuh fluktuatif']], 'gejala_tanda_minor' => ['subjektif' => ['Tidak tersedia'], 'objektif' => ['Piloereksi', 'Pengisian kapiler >3 detik', 'Tekanan darah meningkat/menurun', 'Pucat', 'Frekuensi napas meningkat', 'Takikardia', 'Kejang', 'Kulit kemerahan', 'Dasar kuku sianotik']], 'kondisi_klinis_terkait' => ['Cedera medula spinalis', 'Infeksi/sepsis', 'Pembedahan', 'Cedera otak akut', 'Trauma']], 'slki' => [['kode' => 'L.14134', 'nama' => 'Termoregulasi', 'kriteria_hasil' => ['Menggigil menurun', 'Kulit merah menurun', 'Kejang menurun', 'Akrosianosis menurun', 'Konsumsi oksigen menurun', 'Piloereksi menurun', 'Vasokonstriksi perifer menurun', 'Kutis memorata menurun', 'Pucat menurun', 'Takikardi menurun', 'Takipnea menurun', 'Bradikardi menurun', 'Dasar kuku sianotik menurun', 'Hipoksia menurun', 'Suhu tubuh membaik', 'Suhu kulit membaik', 'Kadar glukosa darah membaik', 'Pengisian kapiler membaik', 'Ventilasi membaik', 'Tekanan darah membaik']]], 'siki' => [['kode' => 'I.14578', 'nama' => 'Regulasi Temperatur', 'definisi' => 'Mempertahankan suhu tubuh dalam rentang normal', 'tindakan' => ['observasi' => ['Monitor suhu bayi sampai stabil (36,5C-37,5C)', 'Monitor suhu tubuh anak tiap 2 jam, jika perlu', 'Monitor tekanan darah, frekuensi pernapasan dan nadi', 'Monitor warna dan suhu kulit', 'Monitor dan catat tanda dan gejala hipotermia atau hipertermia'], 'terapeutik' => ['Pasang alat pemantau suhu kontinu, jika perlu', 'Tingkatkan asupan cairan dan nutrisi yang adekuat', 'Masukan bayi BBLR kedalam plastik segera setelah lahir (mis. bahan polyethylene, polyurethane)', 'Gunakan topi bayi untuk mencegah kehilangan panas pada bayi baru lahir', 'Tempatkan bayi baru lahir dibawah radiant warmer', 'Pertahankan kelembaban incubator 50% atau lebih untuk mengurangi kehilangan panas karena proses evorasi', 'Atur suhu incubator sesuai kebutuhan', 'Hangatkan terlebih dahulu bahan-bahan yang akan kontak dengan bayi (mis. selimut, kain bedongan, stetoskop)', 'Hindari meletakkan bayi di dekat jendela terbuka atau di area aliran pendingin ruangan atau kipas angin', 'Gunakan matras penghangat, selimut hangat, dan penghangat ruangan untuk menaikkan suhu tubuh, jika perlu', 'Gunakan kasur pendingin, water circulating blankets, ice pack atau gel pad dan intravascular cooling catherization untuk menurunkan suhu tubuh', 'Sesuaikan suhu lingkungan dengan kebutuhan pasien'], 'edukasi' => ['Jelaskan cara pencegahan heat exhaustion dan heat stroke', 'Jelaskan cara pencegahan hipotermia karena terpapar udara dingin', 'Demonstrasikan teknik perawatan metode kanguru (PMK) untuk bayi BBLR'], 'kolaborasi' => ['Kolaborasi pemberian antipiretik, jika perlu']]]]]],

        ];
    }
}
