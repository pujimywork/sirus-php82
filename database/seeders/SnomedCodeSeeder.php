<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SnomedCodeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rsmst_snomed_codes')->truncate();

        $data = $this->getData();

        $now = now();

        foreach (array_chunk($data, 50) as $chunk) {
            $chunk = array_map(fn($row) => [...$row, 'created_at' => $now], $chunk);
            DB::table('rsmst_snomed_codes')->insert($chunk);
        }

        $this->command->info('SnomedCodeSeeder: ' . count($data) . ' codes seeded.');
    }

    private function getData(): array
    {
        return [
            // ============================================================
            // Keluhan umum (condition-code)
            // ============================================================
            ['snomed_code' => '21522001',  'display_en' => 'Abdominal pain',                  'display_id' => 'Nyeri perut',              'value_set' => 'condition-code'],
            ['snomed_code' => '25064002',  'display_en' => 'Headache',                        'display_id' => 'Sakit kepala',             'value_set' => 'condition-code'],
            ['snomed_code' => '386661006', 'display_en' => 'Fever',                           'display_id' => 'Demam',                    'value_set' => 'condition-code'],
            ['snomed_code' => '49727002',  'display_en' => 'Cough',                           'display_id' => 'Batuk',                    'value_set' => 'condition-code'],
            ['snomed_code' => '267036007', 'display_en' => 'Dyspnea',                         'display_id' => 'Sesak napas',              'value_set' => 'condition-code'],
            ['snomed_code' => '422587007', 'display_en' => 'Nausea',                          'display_id' => 'Mual',                     'value_set' => 'condition-code'],
            ['snomed_code' => '422400008', 'display_en' => 'Vomiting',                        'display_id' => 'Muntah',                   'value_set' => 'condition-code'],
            ['snomed_code' => '62315008',  'display_en' => 'Diarrhea',                        'display_id' => 'Diare',                    'value_set' => 'condition-code'],
            ['snomed_code' => '271807003', 'display_en' => 'Eruption of skin',                'display_id' => 'Ruam kulit',               'value_set' => 'condition-code'],
            ['snomed_code' => '161891005', 'display_en' => 'Backache',                        'display_id' => 'Nyeri punggung',           'value_set' => 'condition-code'],
            ['snomed_code' => '29857009',  'display_en' => 'Chest pain',                      'display_id' => 'Nyeri dada',               'value_set' => 'condition-code'],
            ['snomed_code' => '84229001',  'display_en' => 'Fatigue',                         'display_id' => 'Kelelahan',                'value_set' => 'condition-code'],
            ['snomed_code' => '404640003', 'display_en' => 'Dizziness',                       'display_id' => 'Pusing',                   'value_set' => 'condition-code'],
            ['snomed_code' => '68962001',  'display_en' => 'Muscle pain',                     'display_id' => 'Nyeri otot',               'value_set' => 'condition-code'],
            ['snomed_code' => '57676002',  'display_en' => 'Joint pain',                      'display_id' => 'Nyeri sendi',              'value_set' => 'condition-code'],
            ['snomed_code' => '162397003', 'display_en' => 'Pain in throat',                  'display_id' => 'Sakit tenggorokan',        'value_set' => 'condition-code'],
            ['snomed_code' => '14760008',  'display_en' => 'Constipation',                    'display_id' => 'Sembelit',                 'value_set' => 'condition-code'],
            ['snomed_code' => '271825005', 'display_en' => 'Respiratory distress',            'display_id' => 'Gangguan pernapasan',      'value_set' => 'condition-code'],
            ['snomed_code' => '3006004',   'display_en' => 'Disturbance of consciousness',    'display_id' => 'Gangguan kesadaran',       'value_set' => 'condition-code'],
            ['snomed_code' => '267060006', 'display_en' => 'Swelling of limb',                'display_id' => 'Bengkak ekstremitas',      'value_set' => 'condition-code'],
            ['snomed_code' => '271757001', 'display_en' => 'Palpitations',                    'display_id' => 'Jantung berdebar',         'value_set' => 'condition-code'],
            ['snomed_code' => '193462001', 'display_en' => 'Insomnia',                        'display_id' => 'Susah tidur',              'value_set' => 'condition-code'],
            ['snomed_code' => '79890006',  'display_en' => 'Loss of appetite',                'display_id' => 'Tidak nafsu makan',        'value_set' => 'condition-code'],
            ['snomed_code' => '44169009',  'display_en' => 'Loss of sensation',               'display_id' => 'Mati rasa',                'value_set' => 'condition-code'],
            ['snomed_code' => '246636008', 'display_en' => 'Hematuria',                       'display_id' => 'Kencing berdarah',         'value_set' => 'condition-code'],
            ['snomed_code' => '82991003',  'display_en' => 'Generalized aches and pains',     'display_id' => 'Pegal-pegal',              'value_set' => 'condition-code'],
            ['snomed_code' => '91175000',  'display_en' => 'Seizure',                         'display_id' => 'Kejang',                   'value_set' => 'condition-code'],
            ['snomed_code' => '23924001',  'display_en' => 'Tight chest',                     'display_id' => 'Dada terasa berat',        'value_set' => 'condition-code'],
            ['snomed_code' => '248490000', 'display_en' => 'Bloating of abdomen',             'display_id' => 'Perut kembung',            'value_set' => 'condition-code'],
            ['snomed_code' => '267102003', 'display_en' => 'Sore mouth',                      'display_id' => 'Sariawan',                 'value_set' => 'condition-code'],
            ['snomed_code' => '74776002',  'display_en' => 'Itching of skin',                 'display_id' => 'Gatal-gatal',              'value_set' => 'condition-code'],
            ['snomed_code' => '64531003',  'display_en' => 'Nasal discharge',                 'display_id' => 'Pilek',                    'value_set' => 'condition-code'],
            ['snomed_code' => '56018004',  'display_en' => 'Wheezing',                        'display_id' => 'Mengi',                    'value_set' => 'condition-code'],
            ['snomed_code' => '60862001',  'display_en' => 'Tinnitus',                        'display_id' => 'Telinga berdenging',       'value_set' => 'condition-code'],
            ['snomed_code' => '246677007', 'display_en' => 'Blurred vision',                  'display_id' => 'Penglihatan kabur',        'value_set' => 'condition-code'],
            ['snomed_code' => '225549006', 'display_en' => 'Difficulty walking',              'display_id' => 'Sulit berjalan',           'value_set' => 'condition-code'],
            ['snomed_code' => '40739000',  'display_en' => 'Dysphagia',                       'display_id' => 'Sulit menelan',            'value_set' => 'condition-code'],
            ['snomed_code' => '103001002', 'display_en' => 'Feeling faint',                   'display_id' => 'Rasa mau pingsan',         'value_set' => 'condition-code'],
            ['snomed_code' => '95385002',  'display_en' => 'Sneezing',                        'display_id' => 'Bersin-bersin',            'value_set' => 'condition-code'],
            ['snomed_code' => '301354004', 'display_en' => 'Pain of lower limb',              'display_id' => 'Nyeri kaki',               'value_set' => 'condition-code'],
            ['snomed_code' => '162607003', 'display_en' => 'Cough with sputum',               'display_id' => 'Batuk berdahak',           'value_set' => 'condition-code'],
            ['snomed_code' => '247592009', 'display_en' => 'Poor appetite',                   'display_id' => 'Nafsu makan menurun',      'value_set' => 'condition-code'],
            ['snomed_code' => '271681002', 'display_en' => 'Stomachache',                     'display_id' => 'Sakit perut',              'value_set' => 'condition-code'],
            ['snomed_code' => '126485001', 'display_en' => 'Urticaria',                       'display_id' => 'Biduran',                  'value_set' => 'condition-code'],
            ['snomed_code' => '409668002', 'display_en' => 'Photophobia',                     'display_id' => 'Silau',                    'value_set' => 'condition-code'],
            ['snomed_code' => '162116003', 'display_en' => 'Loss of weight',                  'display_id' => 'Berat badan turun',        'value_set' => 'condition-code'],
            ['snomed_code' => '8943002',   'display_en' => 'Weight gain',                     'display_id' => 'Berat badan naik',         'value_set' => 'condition-code'],
            ['snomed_code' => '22253000',  'display_en' => 'Pain',                            'display_id' => 'Nyeri',                    'value_set' => 'condition-code'],
            ['snomed_code' => '182888003', 'display_en' => 'Excessive sweating',              'display_id' => 'Keringat berlebih',        'value_set' => 'condition-code'],

            // ============================================================
            // Alergi umum (substance-code)
            // ============================================================
            ['snomed_code' => '372687004', 'display_en' => 'Amoxicillin',                     'display_id' => 'Amoksisilin',              'value_set' => 'substance-code'],
            ['snomed_code' => '7034005',   'display_en' => 'Diclofenac',                      'display_id' => 'Diklofenak',               'value_set' => 'substance-code'],
            ['snomed_code' => '387207008', 'display_en' => 'Ibuprofen',                       'display_id' => 'Ibuprofen',                'value_set' => 'substance-code'],
            ['snomed_code' => '387517004', 'display_en' => 'Paracetamol',                     'display_id' => 'Parasetamol',              'value_set' => 'substance-code'],
            ['snomed_code' => '373270004', 'display_en' => 'Penicillin',                      'display_id' => 'Penisilin',                'value_set' => 'substance-code'],
            ['snomed_code' => '387170002', 'display_en' => 'Ciprofloxacin',                   'display_id' => 'Siprofloksasin',           'value_set' => 'substance-code'],
            ['snomed_code' => '372840008', 'display_en' => 'Cephalosporin',                   'display_id' => 'Sefalosporin',             'value_set' => 'substance-code'],
            ['snomed_code' => '387104009', 'display_en' => 'Ceftriaxone',                     'display_id' => 'Seftriakson',              'value_set' => 'substance-code'],
            ['snomed_code' => '363246002', 'display_en' => 'Erythromycin',                    'display_id' => 'Eritromisin',              'value_set' => 'substance-code'],
            ['snomed_code' => '387293003', 'display_en' => 'Metformin',                       'display_id' => 'Metformin',                'value_set' => 'substance-code'],
            ['snomed_code' => '387362001', 'display_en' => 'Amlodipine',                      'display_id' => 'Amlodipin',                'value_set' => 'substance-code'],
            ['snomed_code' => '386872004', 'display_en' => 'Captopril',                       'display_id' => 'Kaptopril',                'value_set' => 'substance-code'],
            ['snomed_code' => '372756006', 'display_en' => 'Sulfamethoxazole',                'display_id' => 'Sulfametoksazol',          'value_set' => 'substance-code'],
            ['snomed_code' => '387501005', 'display_en' => 'Metronidazole',                   'display_id' => 'Metronidazol',             'value_set' => 'substance-code'],
            ['snomed_code' => '372709008', 'display_en' => 'Ketoconazole',                    'display_id' => 'Ketokonazol',              'value_set' => 'substance-code'],
            ['snomed_code' => '387060005', 'display_en' => 'Ranitidine',                      'display_id' => 'Ranitidin',                'value_set' => 'substance-code'],
            ['snomed_code' => '372665008', 'display_en' => 'Aspirin',                         'display_id' => 'Aspirin',                  'value_set' => 'substance-code'],
            ['snomed_code' => '96067008',  'display_en' => 'Seafood allergy',                 'display_id' => 'Alergi seafood',           'value_set' => 'substance-code'],
            ['snomed_code' => '91935009',  'display_en' => 'Allergy to peanut',               'display_id' => 'Alergi kacang',            'value_set' => 'substance-code'],
            ['snomed_code' => '91934008',  'display_en' => 'Allergy to nut',                  'display_id' => 'Alergi kacang-kacangan',   'value_set' => 'substance-code'],
            ['snomed_code' => '418689008', 'display_en' => 'Allergy to grass pollen',         'display_id' => 'Alergi serbuk sari',       'value_set' => 'substance-code'],
            ['snomed_code' => '232347008', 'display_en' => 'Allergy to dust mite',            'display_id' => 'Alergi tungau debu',       'value_set' => 'substance-code'],
            ['snomed_code' => '424213003', 'display_en' => 'Allergy to latex',                'display_id' => 'Alergi lateks',            'value_set' => 'substance-code'],
            ['snomed_code' => '294505008', 'display_en' => 'Allergy to contrast media',       'display_id' => 'Alergi kontras',           'value_set' => 'substance-code'],
            ['snomed_code' => '91936005',  'display_en' => 'Allergy to penicillin',           'display_id' => 'Alergi penisilin',         'value_set' => 'substance-code'],
            ['snomed_code' => '91930004',  'display_en' => 'Allergy to egg',                  'display_id' => 'Alergi telur',             'value_set' => 'substance-code'],
            ['snomed_code' => '417532002', 'display_en' => 'Allergy to fish',                 'display_id' => 'Alergi ikan',              'value_set' => 'substance-code'],
            ['snomed_code' => '300913006', 'display_en' => 'Allergy to shrimp',               'display_id' => 'Alergi udang',             'value_set' => 'substance-code'],
            ['snomed_code' => '735029006', 'display_en' => 'Allergy to crab',                 'display_id' => 'Alergi kepiting',          'value_set' => 'substance-code'],
            ['snomed_code' => '414285001', 'display_en' => 'Allergy to food',                 'display_id' => 'Alergi makanan',           'value_set' => 'substance-code'],
            ['snomed_code' => '425525006', 'display_en' => 'Allergy to dairy product',        'display_id' => 'Alergi susu/produk susu',  'value_set' => 'substance-code'],
            ['snomed_code' => '89811004',  'display_en' => 'Allergy to gluten',               'display_id' => 'Alergi gluten',            'value_set' => 'substance-code'],
            ['snomed_code' => '91937001',  'display_en' => 'Allergy to shellfish',            'display_id' => 'Alergi kerang',            'value_set' => 'substance-code'],
            ['snomed_code' => '300916003', 'display_en' => 'Allergy to chocolate',            'display_id' => 'Alergi cokelat',           'value_set' => 'substance-code'],
            ['snomed_code' => '418184004', 'display_en' => 'Allergy to soy protein',          'display_id' => 'Alergi protein kedelai',   'value_set' => 'substance-code'],
            ['snomed_code' => '782555009', 'display_en' => 'Allergy to cow milk protein',     'display_id' => 'Alergi protein susu sapi', 'value_set' => 'substance-code'],
            ['snomed_code' => '390952000', 'display_en' => 'Allergy to dust',                 'display_id' => 'Alergi debu',              'value_set' => 'substance-code'],
            ['snomed_code' => '419474003', 'display_en' => 'Allergy to mold',                 'display_id' => 'Alergi jamur/kapang',      'value_set' => 'substance-code'],
            ['snomed_code' => '232350006', 'display_en' => 'Allergy to cat dander',           'display_id' => 'Alergi bulu kucing',       'value_set' => 'substance-code'],
            ['snomed_code' => '232349006', 'display_en' => 'Allergy to dog dander',           'display_id' => 'Alergi bulu anjing',       'value_set' => 'substance-code'],
            ['snomed_code' => '735030001', 'display_en' => 'Cold urticaria',                  'display_id' => 'Alergi udara dingin',      'value_set' => 'substance-code'],
            ['snomed_code' => '402387002', 'display_en' => 'Allergic contact dermatitis',     'display_id' => 'Dermatitis kontak alergi', 'value_set' => 'substance-code'],
            ['snomed_code' => '294716003', 'display_en' => 'Allergy to sulfonamide',          'display_id' => 'Alergi sulfonamida',       'value_set' => 'substance-code'],
            ['snomed_code' => '293586001', 'display_en' => 'Allergy to codeine',              'display_id' => 'Alergi kodein',            'value_set' => 'substance-code'],
            ['snomed_code' => '293584003', 'display_en' => 'Allergy to morphine',             'display_id' => 'Alergi morfin',            'value_set' => 'substance-code'],
            ['snomed_code' => '294921000', 'display_en' => 'Allergy to tetracycline',         'display_id' => 'Alergi tetrasiklin',       'value_set' => 'substance-code'],
            ['snomed_code' => '293963004', 'display_en' => 'Allergy to gentamicin',           'display_id' => 'Alergi gentamisin',        'value_set' => 'substance-code'],
            ['snomed_code' => '293747003', 'display_en' => 'Allergy to insulin',              'display_id' => 'Alergi insulin',           'value_set' => 'substance-code'],
            ['snomed_code' => '418038007', 'display_en' => 'Allergy to propylene glycol',     'display_id' => 'Alergi propilen glikol',   'value_set' => 'substance-code'],
            ['snomed_code' => '418325008', 'display_en' => 'Allergy to adhesive plaster',     'display_id' => 'Alergi plester',           'value_set' => 'substance-code'],

            // ============================================================
            // Tindakan umum (procedure-code)
            // ============================================================
            ['snomed_code' => '182813001', 'display_en' => 'Emergency treatment',             'display_id' => 'Penanganan darurat',       'value_set' => 'procedure-code'],
            ['snomed_code' => '225358003', 'display_en' => 'Wound care',                      'display_id' => 'Perawatan luka',           'value_set' => 'procedure-code'],
            ['snomed_code' => '274474001', 'display_en' => 'Bone fracture treatment',         'display_id' => 'Penanganan patah tulang',  'value_set' => 'procedure-code'],
            ['snomed_code' => '33195004',  'display_en' => 'Removal of foreign body',         'display_id' => 'Pengambilan benda asing',  'value_set' => 'procedure-code'],
            ['snomed_code' => '18949003',  'display_en' => 'Change of dressing',              'display_id' => 'Ganti perban',             'value_set' => 'procedure-code'],
            ['snomed_code' => '387713003', 'display_en' => 'Surgical procedure',              'display_id' => 'Prosedur bedah',           'value_set' => 'procedure-code'],
            ['snomed_code' => '71388002',  'display_en' => 'Procedure',                       'display_id' => 'Prosedur',                 'value_set' => 'procedure-code'],
            ['snomed_code' => '14768001',  'display_en' => 'Suturing of wound',               'display_id' => 'Penjahitan luka',          'value_set' => 'procedure-code'],
            ['snomed_code' => '74770003',  'display_en' => 'Splinting',                       'display_id' => 'Pembidaian',               'value_set' => 'procedure-code'],
            ['snomed_code' => '430193006', 'display_en' => 'Medication administration',       'display_id' => 'Pemberian obat',           'value_set' => 'procedure-code'],
            ['snomed_code' => '45211000',  'display_en' => 'Insertion of catheter',           'display_id' => 'Pemasangan kateter',       'value_set' => 'procedure-code'],
            ['snomed_code' => '397619005', 'display_en' => 'Injection',                       'display_id' => 'Injeksi',                  'value_set' => 'procedure-code'],
            ['snomed_code' => '386637004', 'display_en' => 'Infusion',                        'display_id' => 'Infus',                    'value_set' => 'procedure-code'],
            ['snomed_code' => '82078001',  'display_en' => 'Taking of blood specimen',        'display_id' => 'Pengambilan darah',        'value_set' => 'procedure-code'],
            ['snomed_code' => '363680008', 'display_en' => 'Radiographic imaging',            'display_id' => 'Rontgen',                  'value_set' => 'procedure-code'],
            ['snomed_code' => '16310003',  'display_en' => 'Ultrasonography',                 'display_id' => 'USG',                      'value_set' => 'procedure-code'],
            ['snomed_code' => '29303009',  'display_en' => 'Electrocardiographic procedure',  'display_id' => 'EKG',                      'value_set' => 'procedure-code'],
            ['snomed_code' => '241615005', 'display_en' => 'Magnetic resonance imaging',      'display_id' => 'MRI',                      'value_set' => 'procedure-code'],
            ['snomed_code' => '77477000',  'display_en' => 'Computerized axial tomography',   'display_id' => 'CT Scan',                  'value_set' => 'procedure-code'],
            ['snomed_code' => '40701008',  'display_en' => 'Echocardiography',                'display_id' => 'Ekokardiografi',           'value_set' => 'procedure-code'],
            ['snomed_code' => '386746003', 'display_en' => 'Endoscopy',                       'display_id' => 'Endoskopi',                'value_set' => 'procedure-code'],
            ['snomed_code' => '73761001',  'display_en' => 'Colonoscopy',                     'display_id' => 'Kolonoskopi',              'value_set' => 'procedure-code'],
            ['snomed_code' => '44608003',  'display_en' => 'Tonsillectomy',                   'display_id' => 'Tonsilektomi',             'value_set' => 'procedure-code'],
            ['snomed_code' => '80146002',  'display_en' => 'Appendectomy',                    'display_id' => 'Apendektomi',              'value_set' => 'procedure-code'],
            ['snomed_code' => '11466000',  'display_en' => 'Cesarean section',                'display_id' => 'Operasi sesar',            'value_set' => 'procedure-code'],
            ['snomed_code' => '176795006', 'display_en' => 'Circumcision',                    'display_id' => 'Sunat',                    'value_set' => 'procedure-code'],
            ['snomed_code' => '27114001',  'display_en' => 'Tooth extraction',                'display_id' => 'Cabut gigi',               'value_set' => 'procedure-code'],
            ['snomed_code' => '274031008', 'display_en' => 'Hemodialysis',                    'display_id' => 'Hemodialisis',             'value_set' => 'procedure-code'],
            ['snomed_code' => '35025007',  'display_en' => 'Manual reduction of fracture',    'display_id' => 'Reposisi patah tulang',    'value_set' => 'procedure-code'],
            ['snomed_code' => '5765007',   'display_en' => 'Debridement of wound',            'display_id' => 'Debridemen luka',          'value_set' => 'procedure-code'],
        ];
    }
}
