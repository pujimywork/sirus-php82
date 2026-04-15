-- ============================================================
-- Tabel: rsmst_loinc_codes
-- Deskripsi: Cache LOINC dari FHIR server untuk LOV lab
-- Database: Oracle
-- ============================================================

CREATE TABLE rsmst_loinc_codes (
    loinc_code    VARCHAR2(20)   NOT NULL,
    display       VARCHAR2(500)  NOT NULL,
    display_id    VARCHAR2(500),
    component     VARCHAR2(200),
    loinc_class   VARCHAR2(100),
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_rsmst_loinc_codes PRIMARY KEY (loinc_code)
);

CREATE INDEX idx_loinc_display ON rsmst_loinc_codes (UPPER(display));
CREATE INDEX idx_loinc_display_id ON rsmst_loinc_codes (UPPER(display_id));

COMMENT ON TABLE rsmst_loinc_codes IS 'Cache data LOINC untuk LOV pemeriksaan lab (Satu Sehat)';
COMMENT ON COLUMN rsmst_loinc_codes.loinc_code IS 'Kode LOINC (contoh: 718-7)';
COMMENT ON COLUMN rsmst_loinc_codes.display IS 'Nama resmi LOINC dalam bahasa Inggris';
COMMENT ON COLUMN rsmst_loinc_codes.display_id IS 'Nama Indonesia (diisi manual/admin)';
COMMENT ON COLUMN rsmst_loinc_codes.component IS 'Komponen LOINC (contoh: Hemoglobin)';
COMMENT ON COLUMN rsmst_loinc_codes.loinc_class IS 'Kelas LOINC (contoh: HEM/BC, CHEM, UA)';

-- ============================================================
-- Seed data LOINC umum laboratorium
-- ============================================================

INSERT ALL
    -- Hematologi
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('58410-2', 'CBC panel - Blood by Automated count',                             'Panel Darah Lengkap',       'CBC panel',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('57021-8', 'CBC W Ordered Manual Differential panel - Blood',                  'Panel DL 5 Diff',           'CBC W Diff panel',   'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('718-7',   'Hemoglobin [Mass/volume] in Blood',                                'Hemoglobin',                'Hemoglobin',         'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('789-8',   'Erythrocytes [#/volume] in Blood by Automated count',              'Eritrosit',                 'Erythrocytes',       'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('6690-2',  'Leukocytes [#/volume] in Blood by Automated count',               'Leukosit',                  'Leukocytes',         'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('777-3',   'Platelets [#/volume] in Blood by Automated count',                'Trombosit',                 'Platelets',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4544-3',  'Hematocrit [Volume Fraction] of Blood',                            'Hematokrit',                'Hematocrit',         'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4537-7',  'Erythrocyte sedimentation rate',                                   'LED',                       'ESR',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('713-8',   'Eosinophils/100 leukocytes in Blood',                              'Eosinofil %',               'Eosinophils',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('706-2',   'Basophils/100 leukocytes in Blood',                                'Basofil %',                 'Basophils',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('770-8',   'Neutrophils/100 leukocytes in Blood',                              'Neutrofil %',               'Neutrophils',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('736-9',   'Lymphocytes/100 leukocytes in Blood',                              'Limfosit %',                'Lymphocytes',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5905-5',  'Monocytes/100 leukocytes in Blood',                                'Monosit %',                 'Monocytes',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('731-0',   'Lymphocytes [#/volume] in Blood',                                  'Limfosit #',                'Lymphocytes',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('751-8',   'Neutrophils [#/volume] in Blood',                                  'Neutrofil #',               'Neutrophils',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('742-7',   'Monocytes [#/volume] in Blood',                                    'Monosit #',                 'Monocytes',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('711-2',   'Eosinophils [#/volume] in Blood',                                  'Eosinofil #',               'Eosinophils',        'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('704-7',   'Basophils [#/volume] in Blood',                                    'Basofil #',                 'Basophils',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('787-2',   'MCV [Entitic volume]',                                             'MCV',                       'MCV',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('785-6',   'MCH [Entitic mass]',                                               'MCH',                       'MCH',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('786-4',   'MCHC [Mass/volume]',                                               'MCHC',                      'MCHC',               'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('788-0',   'Erythrocyte distribution width [Ratio] by Automated count',        'RDW-CV',                    'RDW',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('21000-5', 'Erythrocyte distribution width [Entitic volume]',                  'RDW-SD',                    'RDW',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32207-3', 'Platelet distribution width [Entitic volume]',                     'PDW',                       'PDW',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32623-1', 'Platelet mean volume [Entitic volume]',                            'MPV',                       'MPV',                'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('37854-8', 'Plateletcrit [Volume Fraction] in Blood',                          'PCT (Plateletcrit)',        'Plateletcrit',       'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('49497-1', 'Platelets large [#/volume] in Blood',                              'P-LCC',                     'Platelets.large',    'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('71260-4', 'Platelets large/100 platelets in Blood',                           'P-LRC',                     'Platelets.large',    'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('17849-1', 'Reticulocytes/100 erythrocytes in Blood',                          'Retikulosit',               'Reticulocytes',      'HEM/BC')
    -- Hemostasis
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3184-9',  'Coagulation tissue factor induced.clot time',                     'CT (Clotting Time)',        'CT',                 'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11067-6', 'Bleeding time',                                                    'BT (Bleeding Time)',        'Bleeding time',      'HEM/BC')
    -- Kimia Darah — Gula
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1558-6',  'Fasting glucose [Mass/volume] in Serum or Plasma',                'Gula Darah Puasa',          'Glucose',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1521-4',  'Glucose [Mass/volume] in Serum or Plasma --2 hours post meal',    'Gula Darah 2 Jam PP',       'Glucose',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2339-0',  'Glucose [Mass/volume] in Blood',                                   'Gula Darah Sewaktu',        'Glucose',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4548-4',  'Hemoglobin A1c/Hemoglobin.total in Blood',                         'HbA1c',                     'Hemoglobin A1c',     'CHEM')
    -- Kimia Darah — Lipid
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2571-8',  'Triglyceride [Mass/volume] in Serum or Plasma',                    'Trigliserida',              'Triglyceride',       'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2093-3',  'Cholesterol [Mass/volume] in Serum or Plasma',                     'Kolesterol Total',          'Cholesterol',        'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2085-9',  'HDL Cholesterol [Mass/volume] in Serum or Plasma',                'HDL Kolesterol',            'HDL Cholesterol',    'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2089-1',  'LDL Cholesterol [Mass/volume] in Serum or Plasma',                'LDL Kolesterol',            'LDL Cholesterol',    'CHEM')
    -- Kimia Darah — Fungsi Hati
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1920-8',  'Aspartate aminotransferase [Enzymatic activity/volume] in Serum or Plasma', 'SGOT',             'AST',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1742-6',  'Alanine aminotransferase [Enzymatic activity/volume] in Serum or Plasma',   'SGPT',             'ALT',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1975-2',  'Bilirubin.total [Mass/volume] in Serum or Plasma',                'Bilirubin Total',           'Bilirubin.total',    'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1968-7',  'Bilirubin.direct [Mass/volume] in Serum or Plasma',               'Bilirubin Direk',           'Bilirubin.direct',   'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1971-1',  'Bilirubin.indirect [Mass/volume] in Serum or Plasma',             'Bilirubin Indirek',         'Bilirubin.indirect', 'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1751-7',  'Albumin [Mass/volume] in Serum or Plasma',                         'Albumin',                   'Albumin',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2885-2',  'Protein [Mass/volume] in Serum or Plasma',                         'Total Protein',             'Protein',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('10834-0', 'Globulin [Mass/volume] in Serum by calculation',                  'Globulin',                  'Globulin',           'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('6768-6',  'Alkaline phosphatase [Enzymatic activity/volume] in Serum or Plasma', 'Alkaline Fosfatase',    'ALP',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2324-2',  'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma', 'Gamma GT',         'GGT',                'CHEM')
    -- Kimia Darah — Fungsi Ginjal
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('24363-4', 'Renal function panel - Serum or Plasma',                          'Panel Fungsi Ginjal',       'Renal function',     'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3091-6',  'Urea nitrogen [Mass/volume] in Serum or Plasma',                  'Ureum',                     'Urea nitrogen',      'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3094-0',  'Urea nitrogen [Mass/volume] in Serum or Plasma',                  'BUN',                       'BUN',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2160-0',  'Creatinine [Mass/volume] in Serum or Plasma',                     'Kreatinin',                 'Creatinine',         'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3084-1',  'Urate [Mass/volume] in Serum or Plasma',                           'Asam Urat',                 'Urate',              'CHEM')
    -- Elektrolit
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2823-3',  'Potassium [Moles/volume] in Serum or Plasma',                     'Kalium',                    'Potassium',          'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2951-2',  'Sodium [Moles/volume] in Serum or Plasma',                        'Natrium',                   'Sodium',             'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2075-0',  'Chloride [Moles/volume] in Serum or Plasma',                      'Klorida',                   'Chloride',           'CHEM')
    -- Cardiac Marker
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('49563-0', 'Troponin I.cardiac [Mass/volume] in Serum or Plasma',             'Troponin I',                'Troponin I',         'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32673-6', 'Creatine kinase.MB [Mass/volume] in Serum or Plasma',             'CK-MB',                     'CK-MB',              'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('48065-7', 'Fibrin D-dimer FEU [Mass/volume] in Platelet poor plasma',        'D-Dimer',                   'D-dimer',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('75241-0', 'Procalcitonin [Mass/volume] in Serum or Plasma',                  'Procalcitonin',             'Procalcitonin',      'CHEM')
    -- Hormon & Tumor Marker
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3016-3',  'Thyrotropin [Units/volume] in Serum or Plasma',                   'TSH',                       'TSH',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3053-6',  'Triiodothyronine (T3) [Moles/volume] in Serum or Plasma',         'T3',                        'T3',                 'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3026-2',  'Thyroxine (T4) [Moles/volume] in Serum or Plasma',                'T4',                        'T4',                 'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3024-7',  'Thyroxine (T4) free [Moles/volume] in Serum or Plasma',           'FT4',                       'T4 free',            'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2039-6',  'Carcinoembryonic Ag [Mass/volume] in Serum or Plasma',            'CEA',                       'CEA',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2986-8',  'Testosterone [Mass/volume] in Serum or Plasma',                   'Testosteron',               'Testosterone',       'CHEM')
    -- Serologi — Hepatitis
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5195-3',  'Hepatitis B virus surface Ag [Presence] in Serum',                'HBsAg',                     'HBsAg',              'SERO')
    -- Serologi — HIV & Syphilis
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('68961-2', 'HIV 1+2 Ab [Presence] in Serum or Plasma',                        'Anti HIV',                  'HIV 1+2 Ab',         'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('20507-0', 'Treponema pallidum Ab [Presence] in Serum',                       'Anti Sifilis',              'Treponema pallidum', 'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('90423-5', 'HIV 1+2 Ab and HIV1 p24 Ag panel - Serum or Plasma',             'Panel HIV/Syphilis',        'HIV+Syphilis',       'SERO')
    -- Serologi — Widal
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5765-5',  'Salmonella typhi O Ab [Titer] in Serum',                          'S. Typhi O',                'S.typhi O Ab',       'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5764-8',  'Salmonella typhi H Ab [Titer] in Serum',                          'S. Typhi H',                'S.typhi H Ab',       'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5758-0',  'Salmonella paratyphi A Ab [Titer] in Serum',                      'S. Paratyphi A',            'S.paratyphi A Ab',   'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5760-6',  'Salmonella paratyphi B Ab [Titer] in Serum',                      'S. Paratyphi B',            'S.paratyphi B Ab',   'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('69668-2', 'Salmonella sp Ab panel - Serum',                                  'Panel Anti Salmonella',     'Salmonella Ab',      'SERO')
    -- Serologi — Dengue
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('75377-2', 'Dengue virus NS1 Ag [Presence] in Serum by Immunoassay',         'Dengue NS1',                'Dengue NS1 Ag',      'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('29676-4', 'Dengue virus IgG Ab [Presence] in Serum',                        'Dengue IgG',                'Dengue IgG',         'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('29504-8', 'Dengue virus IgM Ab [Presence] in Serum',                        'Dengue IgM',                'Dengue IgM',         'SERO')
    -- Serologi — Toxoplasma
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('56888-1', 'Toxoplasma gondii Ab panel - Serum',                              'Panel Toxoplasma',          'Toxoplasma Ab',      'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('8039-1',  'Toxoplasma gondii IgG Ab [Units/volume] in Serum',               'Toxoplasma IgG',            'Toxoplasma IgG',     'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('8040-9',  'Toxoplasma gondii IgM Ab [Units/volume] in Serum',               'Toxoplasma IgM',            'Toxoplasma IgM',     'SERO')
    -- Serologi — Leptospira
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('40674-4', 'Leptospira sp IgG Ab [Presence] in Serum',                       'Leptospira IgG',            'Leptospira IgG',     'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('40675-1', 'Leptospira sp IgM Ab [Presence] in Serum',                       'Leptospira IgM',            'Leptospira IgM',     'SERO')
    -- COVID-19
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94500-6', 'SARS-CoV-2 RNA [Presence] in Respiratory specimen by NAA',       'PCR SARS-CoV-2',            'SARS-CoV-2 RNA',     'MICRO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('95209-3', 'SARS-CoV-2 Ag [Presence] in Respiratory specimen by Rapid immunoassay', 'Rapid Antigen',       'SARS-CoV-2 Ag',      'MICRO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94563-4', 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma',               'Anti SARS-CoV-2 IgG',       'SARS-CoV-2 IgG',     'SERO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94564-2', 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma',               'Anti SARS-CoV-2 IgM',       'SARS-CoV-2 IgM',     'SERO')
    -- Mikrobiologi — BTA
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11545-1', 'Mycobacterium sp identified in Specimen by Acid fast stain',     'BTA (Basil Tahan Asam)',    'Mycobacterium',      'MICRO')
    -- Parasitologi — Malaria
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('51587-4', 'Plasmodium falciparum Ag [Presence] in Blood',                   'P. Falciparum',             'P.falciparum Ag',    'MICRO')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('51588-2', 'Plasmodium vivax Ag [Presence] in Blood',                        'P. Vivax',                  'P.vivax Ag',         'MICRO')
    -- Urinalisa
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('24362-6', 'Urinalysis complete panel - Urine',                               'Panel Urin Lengkap',        'Urinalysis panel',   'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5770-3',  'Albumin [Presence] in Urine by Test strip',                      'Albumin Urin',              'Albumin',            'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1977-8',  'Bilirubin [Presence] in Urine by Test strip',                    'Bilirubin Urin',            'Bilirubin',          'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5792-7',  'Glucose [Presence] in Urine by Test strip',                      'Reduksi / Glukosa Urin',    'Glucose',            'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5818-0',  'Urobilinogen [Presence] in Urine by Test strip',                 'Urobilinogen',              'Urobilinogen',       'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5797-6',  'Ketones [Presence] in Urine by Test strip',                      'Keton Urin',                'Ketones',            'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5802-4',  'Nitrite [Presence] in Urine by Test strip',                      'Nitrit Urin',               'Nitrite',            'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5808-1',  'Erythrocytes [#/area] in Urine sediment by Microscopy',          'Eritrosit Urin',            'Erythrocytes',       'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5821-4',  'Leukocytes [#/area] in Urine sediment by Microscopy',            'Leukosit Urin',             'Leukocytes',         'UA')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11277-1', 'Epithelial cells [#/area] in Urine sediment by Microscopy',      'Epitel Urin',               'Epithelial cells',   'UA')
    -- Lain-lain
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('883-9',   'ABO group [Type] in Blood',                                       'Golongan Darah',            'ABO group',          'HEM/BC')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2106-3',  'Choriogonadotropin (pregnancy test) [Presence] in Urine',        'Tes Kehamilan',             'hCG',                'CHEM')
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('58408-6', 'Peripheral blood smear interpretation',                           'Hapusan Darah Tepi',        'Blood smear',        'HEM/BC')
SELECT 1 FROM DUAL;

COMMIT;
