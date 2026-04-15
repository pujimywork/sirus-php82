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
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('21522001',  'Abdominal pain',                        'Nyeri perut',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('25064002',  'Headache',                              'Sakit kepala',             'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('386661006', 'Fever',                                 'Demam',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('49727002',  'Cough',                                 'Batuk',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('267036007', 'Dyspnea',                               'Sesak napas',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('422587007', 'Nausea',                                'Mual',                     'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('422400008', 'Vomiting',                              'Muntah',                   'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('62315008',  'Diarrhea',                              'Diare',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('271807003', 'Eruption of skin',                      'Ruam kulit',               'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('161891005', 'Backache',                              'Nyeri punggung',           'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('29857009',  'Chest pain',                            'Nyeri dada',               'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('84229001',  'Fatigue',                               'Kelelahan',                'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('404640003', 'Dizziness',                             'Pusing',                   'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('68962001',  'Muscle pain',                           'Nyeri otot',               'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('57676002',  'Joint pain',                            'Nyeri sendi',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('162397003', 'Pain in throat',                        'Sakit tenggorokan',        'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('14760008',  'Constipation',                          'Sembelit',                 'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('271825005', 'Respiratory distress',                  'Gangguan pernapasan',      'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('3006004',   'Disturbance of consciousness',          'Gangguan kesadaran',       'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('267060006', 'Swelling of limb',                      'Bengkak ekstremitas',      'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('271757001', 'Palpitations',                          'Jantung berdebar',         'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('193462001', 'Insomnia',                              'Susah tidur',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('79890006',  'Loss of appetite',                      'Tidak nafsu makan',        'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('44169009',  'Loss of sensation',                     'Mati rasa',                'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('246636008', 'Hematuria',                             'Kencing berdarah',         'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('82991003',  'Generalized aches and pains',           'Pegal-pegal',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('91175000',  'Seizure',                               'Kejang',                   'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('23924001',  'Tight chest',                           'Dada terasa berat',        'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('248490000', 'Bloating of abdomen',                   'Perut kembung',            'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('267102003', 'Sore mouth',                            'Sariawan',                 'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('74776002',  'Itching of skin',                       'Gatal-gatal',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('64531003',  'Nasal discharge',                       'Pilek',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('56018004',  'Wheezing',                              'Mengi',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('60862001',  'Tinnitus',                              'Telinga berdenging',       'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('246677007', 'Blurred vision',                        'Penglihatan kabur',        'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('225549006', 'Difficulty walking',                    'Sulit berjalan',           'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('40739000',  'Dysphagia',                             'Sulit menelan',            'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('103001002', 'Feeling faint',                         'Rasa mau pingsan',         'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('95385002',  'Sneezing',                              'Bersin-bersin',            'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('301354004', 'Pain of lower limb',                    'Nyeri kaki',               'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('162607003', 'Cough with sputum',                     'Batuk berdahak',           'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('247592009', 'Poor appetite',                         'Nafsu makan menurun',      'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('271681002', 'Stomachache',                           'Sakit perut',              'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('126485001', 'Urticaria',                             'Biduran',                  'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('409668002', 'Photophobia',                           'Silau',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('162116003', 'Loss of weight',                        'Berat badan turun',        'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('8943002',   'Weight gain',                           'Berat badan naik',         'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('22253000',  'Pain',                                  'Nyeri',                    'condition-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('182888003', 'Excessive sweating',                    'Keringat berlebih',        'condition-code')
SELECT 1 FROM DUAL;

-- ============================================================
-- Seed data alergi umum (substance-code)
-- ============================================================

INSERT ALL
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('372687004', 'Amoxicillin',                           'Amoksisilin',              'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('7034005',   'Diclofenac',                            'Diklofenak',               'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387207008', 'Ibuprofen',                             'Ibuprofen',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387517004', 'Paracetamol',                           'Parasetamol',              'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('373270004', 'Penicillin',                            'Penisilin',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387170002', 'Ciprofloxacin',                         'Siprofloksasin',           'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('372840008', 'Cephalosporin',                         'Sefalosporin',             'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387104009', 'Ceftriaxone',                           'Seftriakson',              'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('363246002', 'Erythromycin',                          'Eritromisin',              'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387293003', 'Metformin',                             'Metformin',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387362001', 'Amlodipine',                            'Amlodipin',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('386872004', 'Captopril',                             'Kaptopril',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('372756006', 'Sulfamethoxazole',                      'Sulfametoksazol',          'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387501005', 'Metronidazole',                         'Metronidazol',             'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('372709008', 'Ketoconazole',                          'Ketokonazol',              'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387060005', 'Ranitidine',                            'Ranitidin',                'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('372665008', 'Aspirin',                               'Aspirin',                  'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('96067008',  'Seafood allergy',                       'Alergi seafood',           'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('91935009',  'Allergy to peanut',                     'Alergi kacang',            'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('91934008',  'Allergy to nut',                        'Alergi kacang-kacangan',   'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('418689008', 'Allergy to grass pollen',               'Alergi serbuk sari',       'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('232347008', 'Allergy to dust mite',                  'Alergi tungau debu',       'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('424213003', 'Allergy to latex',                      'Alergi lateks',            'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('294505008', 'Allergy to contrast media',             'Alergi kontras',           'substance-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('91936005',  'Allergy to penicillin',                 'Alergi penisilin',         'substance-code')
SELECT 1 FROM DUAL;

-- ============================================================
-- Seed data tindakan umum (procedure-code)
-- ============================================================

INSERT ALL
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('182813001', 'Emergency treatment',                   'Penanganan darurat',       'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('225358003', 'Wound care',                            'Perawatan luka',           'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('274474001', 'Bone fracture treatment',               'Penanganan patah tulang',  'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('33195004',  'Removal of foreign body',               'Pengambilan benda asing',  'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('18949003',  'Change of dressing',                    'Ganti perban',             'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('387713003', 'Surgical procedure',                    'Prosedur bedah',           'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('71388002',  'Procedure',                             'Prosedur',                 'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('14768001',  'Suturing of wound',                     'Penjahitan luka',          'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('74770003',  'Splinting',                             'Pembidaian',               'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('430193006', 'Medication administration',             'Pemberian obat',           'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('45211000',  'Insertion of catheter',                 'Pemasangan kateter',       'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('397619005', 'Injection',                             'Injeksi',                  'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('386637004', 'Infusion',                              'Infus',                    'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('82078001',  'Taking of blood specimen',              'Pengambilan darah',        'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('363680008', 'Radiographic imaging',                  'Rontgen',                  'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('16310003',  'Ultrasonography',                       'USG',                      'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('29303009',  'Electrocardiographic procedure',        'EKG',                      'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('241615005', 'Magnetic resonance imaging',            'MRI',                      'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('77477000',  'Computerized axial tomography',         'CT Scan',                  'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('40701008',  'Echocardiography',                      'Ekokardiografi',           'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('386746003', 'Endoscopy',                             'Endoskopi',                'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('73761001',  'Colonoscopy',                           'Kolonoskopi',              'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('44608003',  'Tonsillectomy',                         'Tonsilektomi',             'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('80146002',  'Appendectomy',                          'Apendektomi',              'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('11466000',  'Cesarean section',                      'Operasi sesar',            'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('176795006', 'Circumcision',                          'Sunat',                    'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('27114001',  'Tooth extraction',                      'Cabut gigi',               'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('274031008', 'Hemodialysis',                          'Hemodialisis',             'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('35025007',  'Manual reduction of fracture',          'Reposisi patah tulang',    'procedure-code')
    INTO rsmst_snomed_codes (snomed_code, display_en, display_id, value_set) VALUES ('5765007',   'Debridement of wound',                  'Debridemen luka',          'procedure-code')
SELECT 1 FROM DUAL;

COMMIT;
