-- ============================================================
-- Seed: rsmst_loinc_codes — Data LOINC Radiologi
-- Jalankan setelah create_rsmst_loinc_codes.sql
-- ============================================================

INSERT ALL
    -- Rontgen — Thorax & Abdomen
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36643-5', 'XR Chest 2 Views',                        'Rontgen Dada PA/AP',        'Chest XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36554-4', 'XR Chest Lateral',                        'Rontgen Dada Lateral',      'Chest XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37439-8', 'XR Chest Oblique',                        'Rontgen Dada Oblique',      'Chest XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36687-2', 'XR Chest AP Lordotic',                    'Rontgen Dada Top Lordotic', 'Chest XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('43462-6', 'XR Abdomen AP',                           'Rontgen Perut (BOF)',       'Abdomen XR',     'RAD', SYSDATE)
    -- Rontgen — Pelvis & Hip
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37620-3', 'XR Pelvis AP',                            'Rontgen Pelvis',            'Pelvis XR',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37181-6', 'XR Hip AP',                               'Rontgen Panggul AP',        'Hip XR',         'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37182-4', 'XR Hip Lateral',                          'Rontgen Panggul Lateral',   'Hip XR',         'RAD', SYSDATE)
    -- Rontgen — Skull & Facial
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36287-1', 'XR Skull AP',                             'Rontgen Kepala AP',         'Skull XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36288-9', 'XR Skull Lateral',                        'Rontgen Kepala Lateral',    'Skull XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37017-2', 'XR Sinuses Waters view',                  'Rontgen Sinus Waters',      'Sinuses XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37339-0', 'XR Mandible AP',                          'Rontgen Mandibula',         'Mandible XR',    'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36957-0', 'XR Nasal bones Lateral',                  'Rontgen Hidung Lateral',    'Nasal XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36780-6', 'XR Mastoid',                              'Rontgen Mastoid',           'Mastoid XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37901-7', 'XR Temporomandibular joint',              'Rontgen TMJ',               'TMJ XR',         'RAD', SYSDATE)
    -- Rontgen — Spine
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36657-5', 'XR Cervical spine AP',                    'Rontgen Cervikal',          'C-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36659-1', 'XR Cervical spine Lateral',               'Rontgen Cervikal Lateral',  'C-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36660-9', 'XR Cervical spine Oblique',               'Rontgen Cervikal Oblique',  'C-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36661-7', 'XR Cervical spine Odontoid AP',           'Rontgen Odontoid',          'C-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37944-7', 'XR Thoracic spine AP',                    'Rontgen Thorakal AP',       'T-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37945-4', 'XR Thoracic spine Lateral',               'Rontgen Thorakal Lateral',  'T-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36589-0', 'XR Lumbar spine AP',                      'Rontgen Lumbosakral AP',    'L-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36591-6', 'XR Lumbar spine Lateral',                 'Rontgen Lumbosakral Lat',   'L-Spine XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37682-3', 'XR Sacrum AP',                            'Rontgen Sakrum AP',         'Sacrum XR',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37683-1', 'XR Sacrum Lateral',                       'Rontgen Sakrum Lateral',    'Sacrum XR',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36697-1', 'XR Coccyx AP',                            'Rontgen Coccyx AP',         'Coccyx XR',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36698-9', 'XR Coccyx Lateral',                       'Rontgen Coccyx Lateral',    'Coccyx XR',      'RAD', SYSDATE)
    -- Rontgen — Extremity
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36695-5', 'XR Clavicle',                             'Rontgen Klavikula',         'Clavicle XR',    'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37764-9', 'XR Shoulder AP',                          'Rontgen Bahu',              'Shoulder XR',    'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37220-2', 'XR Humerus',                              'Rontgen Humerus',           'Humerus XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36856-3', 'XR Elbow',                                'Rontgen Siku',              'Elbow XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37048-7', 'XR Forearm',                              'Rontgen Lengan Bawah',      'Forearm XR',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37974-4', 'XR Wrist',                                'Rontgen Pergelangan',       'Wrist XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37154-3', 'XR Hand',                                 'Rontgen Tangan',            'Hand XR',        'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37026-3', 'XR Femur',                                'Rontgen Femur',             'Femur XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37302-8', 'XR Knee',                                 'Rontgen Lutut',             'Knee XR',        'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37917-3', 'XR Tibia and Fibula',                     'Rontgen Cruris',            'Tibia/Fibula',   'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36619-5', 'XR Ankle',                                'Rontgen Pergelangan Kaki',  'Ankle XR',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36639-3', 'XR Calcaneus',                            'Rontgen Tumit',             'Calcaneus XR',   'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37042-0', 'XR Foot',                                 'Rontgen Kaki',              'Foot XR',        'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37629-4', 'XR Penis',                                'Rontgen Penis',             'Penis XR',       'RAD', SYSDATE)
    -- Kontras / Fluoroscopy
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24850-8', 'XR Urinary tract IVP',                    'IVP',                       'IVP',            'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24745-0', 'RF Colon Barium enema',                   'Colon in Loop',             'Barium enema',   'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24852-4', 'RF Urethra Urethrography',                'Uretrografi',               'Urethrography',  'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24695-7', 'RF Bladder Cystography',                  'Sistografi',                'Cystography',    'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24853-2', 'RF Uterus and Fallopian tubes HSG',       'HSG',                       'HSG',            'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24855-7', 'RF Upper GI tract with barium',           'Upper GI',                  'Upper GI',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24856-5', 'RF Esophagus with barium',                'Oesofagografi',             'Esophagography', 'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24626-2', 'RF Appendix with barium',                 'Appendicografi',            'Appendicography','RAD', SYSDATE)
    -- USG
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24590-2', 'US Head',                                 'USG Kepala',                'Head US',        'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24648-6', 'US Carotid artery Duplex',                'USG Doppler Karotis',       'Carotid US',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24558-9', 'US Abdomen',                              'USG Abdomen',               'Abdomen US',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24685-8', 'US Liver Doppler',                        'USG Hepar Doppler',         'Liver US',       'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24843-3', 'US Thyroid',                              'USG Tiroid',                'Thyroid US',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24838-3', 'US Salivary gland',                       'USG Kelenjar Liur',         'Salivary US',    'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24854-0', 'US Urinary tract',                        'USG Urologi',               'Urinary US',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24696-5', 'US Kidney Duplex',                        'USG Doppler Ginjal',        'Kidney US',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24604-9', 'US Breast',                               'USG Mammae',                'Breast US',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37432-3', 'US Axilla',                               'USG Aksila',                'Axilla US',      'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24841-7', 'US Scrotum',                              'USG Testis/Skrotum',        'Scrotum US',     'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24862-3', 'US Lower extremity vein Duplex',          'USG Doppler Vena Ext',      'DVT US',         'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24754-2', 'US Musculoskeletal',                      'USG Muskuloskeletal',       'MSK US',         'RAD', SYSDATE)
    INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24643-7', 'US Chest',                                'USG Thorax',                'Chest US',       'RAD', SYSDATE)
SELECT 1 FROM DUAL;

COMMIT;
