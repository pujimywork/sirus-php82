-- ============================================================
-- Tabel: rsmst_snomed_codes
-- Deskripsi: Cache SNOMED CT dari FHIR server (tx.fhir.org)
-- Database: Oracle
-- ============================================================

CREATE TABLE rsmst_snomed_codes (
    snomed_code   VARCHAR2(20)   NOT NULL,
    display_en    VARCHAR2(500)  NOT NULL,
    display_id    VARCHAR2(500),
    value_set     VARCHAR2(50)   DEFAULT 'condition-code' NOT NULL,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_rsmst_snomed_codes PRIMARY KEY (snomed_code)
);

-- Index untuk pencarian by value_set
CREATE INDEX idx_snomed_value_set ON rsmst_snomed_codes (value_set);

-- Index untuk pencarian teks (display_en + display_id)
CREATE INDEX idx_snomed_display_en ON rsmst_snomed_codes (UPPER(display_en));
CREATE INDEX idx_snomed_display_id ON rsmst_snomed_codes (UPPER(display_id));

-- Komentar tabel
COMMENT ON TABLE rsmst_snomed_codes IS 'Cache data SNOMED CT dari FHIR server untuk LOV keluhan utama, alergi, dll';
COMMENT ON COLUMN rsmst_snomed_codes.snomed_code IS 'Kode SNOMED CT (contoh: 21522001)';
COMMENT ON COLUMN rsmst_snomed_codes.display_en IS 'Nama Inggris dari FHIR server (otomatis)';
COMMENT ON COLUMN rsmst_snomed_codes.display_id IS 'Nama Indonesia (diisi manual/admin)';
COMMENT ON COLUMN rsmst_snomed_codes.value_set IS 'Jenis: condition-code, procedure-code, substance-code, dll';
COMMENT ON COLUMN rsmst_snomed_codes.created_at IS 'Waktu pertama kali di-cache';

-- ============================================================
-- Seed data keluhan umum (opsional, untuk starter)
-- ============================================================

INSERT ALL
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('21522001',  'Abdominal pain',                        'Nyeri perut',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('25064002',  'Headache',                              'Sakit kepala',             'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386661006', 'Fever',                                 'Demam',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('49727002',  'Cough',                                 'Batuk',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267036007', 'Dyspnea',                               'Sesak napas',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('422587007', 'Nausea',                                'Mual',                     'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('422400008', 'Vomiting',                              'Muntah',                   'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('62315008',  'Diarrhea',                              'Diare',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271807003', 'Eruption of skin',                      'Ruam kulit',               'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('161891005', 'Backache',                              'Nyeri punggung',           'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('29857009',  'Chest pain',                            'Nyeri dada',               'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('84229001',  'Fatigue',                               'Kelelahan',                'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('404640003', 'Dizziness',                             'Pusing',                   'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('68962001',  'Muscle pain',                           'Nyeri otot',               'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('57676002',  'Joint pain',                            'Nyeri sendi',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162397003', 'Pain in throat',                        'Sakit tenggorokan',        'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('14760008',  'Constipation',                          'Sembelit',                 'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271825005', 'Respiratory distress',                  'Gangguan pernapasan',      'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('3006004',   'Disturbance of consciousness',          'Gangguan kesadaran',       'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267060006', 'Swelling of limb',                      'Bengkak ekstremitas',      'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271757001', 'Palpitations',                          'Jantung berdebar',         'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('193462001', 'Insomnia',                              'Susah tidur',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('79890006',  'Loss of appetite',                      'Tidak nafsu makan',        'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('44169009',  'Loss of sensation',                     'Mati rasa',                'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('246636008', 'Hematuria',                             'Kencing berdarah',         'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('82991003',  'Generalized aches and pains',           'Pegal-pegal',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91175000',  'Seizure',                               'Kejang',                   'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('23924001',  'Tight chest',                           'Dada terasa berat',        'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('248490000', 'Bloating of abdomen',                   'Perut kembung',            'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267102003', 'Sore mouth',                            'Sariawan',                 'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('74776002',  'Itching of skin',                       'Gatal-gatal',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('64531003',  'Nasal discharge',                       'Pilek',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('56018004',  'Wheezing',                              'Mengi',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('60862001',  'Tinnitus',                              'Telinga berdenging',       'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('246677007', 'Blurred vision',                        'Penglihatan kabur',        'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('225549006', 'Difficulty walking',                    'Sulit berjalan',           'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('40739000',  'Dysphagia',                             'Sulit menelan',            'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('103001002', 'Feeling faint',                         'Rasa mau pingsan',         'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('95385002',  'Sneezing',                              'Bersin-bersin',            'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('301354004', 'Pain of lower limb',                    'Nyeri kaki',               'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162607003', 'Cough with sputum',                     'Batuk berdahak',           'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('247592009', 'Poor appetite',                         'Nafsu makan menurun',      'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271681002', 'Stomachache',                           'Sakit perut',              'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('126485001', 'Urticaria',                             'Biduran',                  'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('409668002', 'Photophobia',                           'Silau',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162116003', 'Loss of weight',                        'Berat badan turun',        'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('8943002',   'Weight gain',                           'Berat badan naik',         'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('22253000',  'Pain',                                  'Nyeri',                    'condition-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('182888003', 'Excessive sweating',                    'Keringat berlebih',        'condition-code', SYSDATE)
SELECT 1 FROM DUAL;

-- ============================================================
-- Seed data alergi umum (substance-code)
-- ============================================================

INSERT ALL
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372687004', 'Amoxicillin',                           'Amoksisilin',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('7034005',   'Diclofenac',                            'Diklofenak',               'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387207008', 'Ibuprofen',                             'Ibuprofen',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387517004', 'Paracetamol',                           'Parasetamol',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('373270004', 'Penicillin',                            'Penisilin',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387170002', 'Ciprofloxacin',                         'Siprofloksasin',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372840008', 'Cephalosporin',                         'Sefalosporin',             'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387104009', 'Ceftriaxone',                           'Seftriakson',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('363246002', 'Erythromycin',                          'Eritromisin',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387293003', 'Metformin',                             'Metformin',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387362001', 'Amlodipine',                            'Amlodipin',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386872004', 'Captopril',                             'Kaptopril',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372756006', 'Sulfamethoxazole',                      'Sulfametoksazol',          'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387501005', 'Metronidazole',                         'Metronidazol',             'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372709008', 'Ketoconazole',                          'Ketokonazol',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387060005', 'Ranitidine',                            'Ranitidin',                'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372665008', 'Aspirin',                               'Aspirin',                  'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('96067008',  'Seafood allergy',                       'Alergi seafood',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91935009',  'Allergy to peanut',                     'Alergi kacang',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91934008',  'Allergy to nut',                        'Alergi kacang-kacangan',   'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418689008', 'Allergy to grass pollen',               'Alergi serbuk sari',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232347008', 'Allergy to dust mite',                  'Alergi tungau debu',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('424213003', 'Allergy to latex',                      'Alergi lateks',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294505008', 'Allergy to contrast media',             'Alergi kontras',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91936005',  'Allergy to penicillin',                 'Alergi penisilin',         'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91930004',  'Allergy to egg',                        'Alergi telur',             'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('417532002', 'Allergy to fish',                       'Alergi ikan',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('300913006', 'Allergy to shrimp',                     'Alergi udang',             'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('735029006', 'Allergy to crab',                       'Alergi kepiting',          'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('414285001', 'Allergy to food',                       'Alergi makanan',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('425525006', 'Allergy to dairy product',              'Alergi susu/produk susu',  'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('89811004',  'Allergy to gluten',                     'Alergi gluten',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91937001',  'Allergy to shellfish',                  'Alergi kerang',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('300916003', 'Allergy to chocolate',                  'Alergi cokelat',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418184004', 'Allergy to soy protein',                'Alergi protein kedelai',   'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('782555009', 'Allergy to cow milk protein',            'Alergi protein susu sapi', 'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('390952000', 'Allergy to dust',                       'Alergi debu',              'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('419474003', 'Allergy to mold',                       'Alergi jamur/kapang',      'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232350006', 'Allergy to cat dander',                 'Alergi bulu kucing',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232349006', 'Allergy to dog dander',                 'Alergi bulu anjing',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('735030001', 'Cold urticaria',                        'Alergi udara dingin',      'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('402387002', 'Allergic contact dermatitis',            'Dermatitis kontak alergi', 'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294716003', 'Allergy to sulfonamide',                'Alergi sulfonamida',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293586001', 'Allergy to codeine',                    'Alergi kodein',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293584003', 'Allergy to morphine',                   'Alergi morfin',            'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294921000', 'Allergy to tetracycline',               'Alergi tetrasiklin',       'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293963004', 'Allergy to gentamicin',                 'Alergi gentamisin',        'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293747003', 'Allergy to insulin',                    'Alergi insulin',           'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418038007', 'Allergy to propylene glycol',            'Alergi propilen glikol',   'substance-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418325008', 'Allergy to adhesive plaster',            'Alergi plester',           'substance-code', SYSDATE)
SELECT 1 FROM DUAL;

-- ============================================================
-- Seed data tindakan umum (procedure-code)
-- ============================================================

INSERT ALL
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('182813001', 'Emergency treatment',                   'Penanganan darurat',       'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('225358003', 'Wound care',                            'Perawatan luka',           'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('274474001', 'Bone fracture treatment',               'Penanganan patah tulang',  'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('33195004',  'Removal of foreign body',               'Pengambilan benda asing',  'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('18949003',  'Change of dressing',                    'Ganti perban',             'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387713003', 'Surgical procedure',                    'Prosedur bedah',           'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('71388002',  'Procedure',                             'Prosedur',                 'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('14768001',  'Suturing of wound',                     'Penjahitan luka',          'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('74770003',  'Splinting',                             'Pembidaian',               'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('430193006', 'Medication administration',             'Pemberian obat',           'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('45211000',  'Insertion of catheter',                 'Pemasangan kateter',       'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('397619005', 'Injection',                             'Injeksi',                  'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386637004', 'Infusion',                              'Infus',                    'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('82078001',  'Taking of blood specimen',              'Pengambilan darah',        'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('363680008', 'Radiographic imaging',                  'Rontgen',                  'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('16310003',  'Ultrasonography',                       'USG',                      'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('29303009',  'Electrocardiographic procedure',        'EKG',                      'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('241615005', 'Magnetic resonance imaging',            'MRI',                      'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('77477000',  'Computerized axial tomography',         'CT Scan',                  'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('40701008',  'Echocardiography',                      'Ekokardiografi',           'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386746003', 'Endoscopy',                             'Endoskopi',                'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('73761001',  'Colonoscopy',                           'Kolonoskopi',              'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('44608003',  'Tonsillectomy',                         'Tonsilektomi',             'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('80146002',  'Appendectomy',                          'Apendektomi',              'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('11466000',  'Cesarean section',                      'Operasi sesar',            'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('176795006', 'Circumcision',                          'Sunat',                    'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('27114001',  'Tooth extraction',                      'Cabut gigi',               'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('274031008', 'Hemodialysis',                          'Hemodialisis',             'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('35025007',  'Manual reduction of fracture',          'Reposisi patah tulang',    'procedure-code', SYSDATE)
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('5765007',   'Debridement of wound',                  'Debridemen luka',          'procedure-code', SYSDATE)
SELECT 1 FROM DUAL;

COMMIT;
