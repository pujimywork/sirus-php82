-- ============================================================
-- Alter: RSMST_RADIOLOGIS — Tambah kolom LOINC untuk Satu Sehat
-- ============================================================

ALTER TABLE rsmst_radiologis ADD loinc_code    VARCHAR2(20);
ALTER TABLE rsmst_radiologis ADD loinc_display VARCHAR2(200);

COMMENT ON COLUMN rsmst_radiologis.loinc_code IS 'Kode LOINC — untuk ServiceRequest/DiagnosticReport radiologi ke Satu Sehat';
COMMENT ON COLUMN rsmst_radiologis.loinc_display IS 'Nama resmi LOINC (contoh: XR Chest 2 Views)';

CREATE INDEX idx_rad_loinc ON rsmst_radiologis (loinc_code);

-- ============================================================
-- Update mapping LOINC untuk radiologi
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Thorax
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Chest 2 Views'                                  WHERE rad_id = 'R1';    -- THORAX PA / AP
UPDATE rsmst_radiologis SET loinc_code = '36554-4', loinc_display = 'XR Chest Lateral'                                  WHERE rad_id = 'R2';    -- THORAX LATERAL
UPDATE rsmst_radiologis SET loinc_code = '37439-8', loinc_display = 'XR Chest Oblique'                                  WHERE rad_id = 'R3';    -- THORAX OBLIQUE S
UPDATE rsmst_radiologis SET loinc_code = '37439-8', loinc_display = 'XR Chest Oblique'                                  WHERE rad_id = 'R22';   -- THORAX OBLIQUE D
UPDATE rsmst_radiologis SET loinc_code = '36687-2', loinc_display = 'XR Chest AP Lordotic'                              WHERE rad_id = 'R23';   -- THORAX TOP LORDOTIC
UPDATE rsmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Chest PA and Lateral'                           WHERE rad_id = 'R1036'; -- THORAX PA / LAT.

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Abdomen
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP'                                     WHERE rad_id = 'R4';    -- BOF
UPDATE rsmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP and Lateral decubitus'               WHERE rad_id = 'R5';    -- LLD & BOF
UPDATE rsmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP'                                     WHERE rad_id = 'R24';   -- BNO
UPDATE rsmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP upright'                             WHERE rad_id = 'R55';   -- BOF 1/2 DUDUK
UPDATE rsmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen 3 Views'                                WHERE rad_id = 'R121';  -- BOF 3 POSISI

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Pelvis & Hip
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis AP'                                      WHERE rad_id = 'R6';    -- PELVIS AP
UPDATE rsmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis Frog leg'                                WHERE rad_id = 'R25';   -- PELVIS FROG POSITION
UPDATE rsmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis Inlet and Outlet'                        WHERE rad_id = 'R125';  -- PELVIS INLET & OUTLET
UPDATE rsmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R57';   -- HIP JOINT D
UPDATE rsmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R58';   -- HIP JOINT S
UPDATE rsmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R115';  -- ART. COXAE AP D
UPDATE rsmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R116';  -- ART. COXAE AP S
UPDATE rsmst_radiologis SET loinc_code = '37182-4', loinc_display = 'XR Hip Lateral'                                    WHERE rad_id = 'R117';  -- ART. COXAE LAT D
UPDATE rsmst_radiologis SET loinc_code = '37182-4', loinc_display = 'XR Hip Lateral'                                    WHERE rad_id = 'R118';  -- ART. COXAE LAT S

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Skull & Facial
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '36287-1', loinc_display = 'XR Skull AP'                                       WHERE rad_id = 'R30';   -- SKULL AP
UPDATE rsmst_radiologis SET loinc_code = '36288-9', loinc_display = 'XR Skull Lateral'                                  WHERE rad_id = 'R31';   -- SKULL LATERAL
UPDATE rsmst_radiologis SET loinc_code = '37017-2', loinc_display = 'XR Sinuses Waters view'                            WHERE rad_id = 'R32';   -- WATER'S
UPDATE rsmst_radiologis SET loinc_code = '36287-1', loinc_display = 'XR Skull submentovertex'                           WHERE rad_id = 'R33';   -- BASIS CRANII
UPDATE rsmst_radiologis SET loinc_code = '37339-0', loinc_display = 'XR Mandible AP'                                    WHERE rad_id = 'R7';    -- MANDIBULA AP
UPDATE rsmst_radiologis SET loinc_code = '37339-0', loinc_display = 'XR Mandible'                                       WHERE rad_id = 'R46';   -- MANDIBULA EISLER
UPDATE rsmst_radiologis SET loinc_code = '36957-0', loinc_display = 'XR Nasal bones Lateral'                            WHERE rad_id = 'R48';   -- NASAL LATERAL
UPDATE rsmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid'                                        WHERE rad_id = 'R8';    -- MASTOIS SCHULLER D
UPDATE rsmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid'                                        WHERE rad_id = 'R18';   -- MASTOIS SCHULLER S
UPDATE rsmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid Stenvers'                               WHERE rad_id = 'R104';  -- STEVENVER'S D
UPDATE rsmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid Stenvers'                               WHERE rad_id = 'R105';  -- STEVENVER'S S
UPDATE rsmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R9';    -- TMJ D
UPDATE rsmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R14';   -- TMJ S
UPDATE rsmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R209';  -- TMJ D/S OPEN & CLOSE

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Spine
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine AP'                              WHERE rad_id = 'R10';   -- CERVICAL AP
UPDATE rsmst_radiologis SET loinc_code = '36659-1', loinc_display = 'XR Cervical spine Lateral'                         WHERE rad_id = 'R108';  -- CERVICAL LAT.
UPDATE rsmst_radiologis SET loinc_code = '36660-9', loinc_display = 'XR Cervical spine Oblique'                         WHERE rad_id = 'R11';   -- CERVICAL OBLIQUE D
UPDATE rsmst_radiologis SET loinc_code = '36660-9', loinc_display = 'XR Cervical spine Oblique'                         WHERE rad_id = 'R50';   -- CERVICAL OBLIQUE S
UPDATE rsmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine AP and Lateral'                  WHERE rad_id = 'R1041'; -- CERV. AP/LAT/OBL
UPDATE rsmst_radiologis SET loinc_code = '36661-7', loinc_display = 'XR Cervical spine Odontoid AP'                     WHERE rad_id = 'R16';   -- ODONTOID
UPDATE rsmst_radiologis SET loinc_code = '36661-7', loinc_display = 'XR Cervical spine Odontoid AP'                     WHERE rad_id = 'R109';  -- PROC. ODONTOIDEUS
UPDATE rsmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine Flexion'                         WHERE rad_id = 'R13';   -- DYNAMIC CERV FLEXI
UPDATE rsmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine Extension'                       WHERE rad_id = 'R112';  -- DYNAMIC CERV EXTENSI
UPDATE rsmst_radiologis SET loinc_code = '37944-7', loinc_display = 'XR Thoracic spine AP'                              WHERE rad_id = 'R101';  -- VERT. THORACALIS AP
UPDATE rsmst_radiologis SET loinc_code = '37945-4', loinc_display = 'XR Thoracic spine Lateral'                         WHERE rad_id = 'R103';  -- VERT. THORACALIS LAT
UPDATE rsmst_radiologis SET loinc_code = '37944-7', loinc_display = 'XR Thoracic spine AP and Lateral'                  WHERE rad_id = 'R120';  -- VERT. THORACALIS AP/LAT
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine AP'                                WHERE rad_id = 'R100';  -- VERT. LUMBOSACRAL AP
UPDATE rsmst_radiologis SET loinc_code = '36591-6', loinc_display = 'XR Lumbar spine Lateral'                           WHERE rad_id = 'R102';  -- VERT. LUMBOSACRAL LAT
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine AP and Lateral'                    WHERE rad_id = 'R1034'; -- VERT. LUMBOSACRAL AP/LAT
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Bending'                           WHERE rad_id = 'R12';   -- BENDING S
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Bending'                           WHERE rad_id = 'R15';   -- BENDING D
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Flexion'                           WHERE rad_id = 'R113';  -- DYNAMIC LS FLEXI
UPDATE rsmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Extension'                         WHERE rad_id = 'R114';  -- DYNAMIC LS EXTENSI
UPDATE rsmst_radiologis SET loinc_code = '37682-3', loinc_display = 'XR Sacrum AP'                                      WHERE rad_id = 'R39';   -- SACRUM AP
UPDATE rsmst_radiologis SET loinc_code = '37683-1', loinc_display = 'XR Sacrum Lateral'                                 WHERE rad_id = 'R40';   -- SACRUM LATERAL
UPDATE rsmst_radiologis SET loinc_code = '36697-1', loinc_display = 'XR Coccyx AP'                                      WHERE rad_id = 'R21';   -- COCCYGEUS AP
UPDATE rsmst_radiologis SET loinc_code = '36698-9', loinc_display = 'XR Coccyx Lateral'                                 WHERE rad_id = 'R54';   -- COCCYGEUS LATERAL
UPDATE rsmst_radiologis SET loinc_code = '36697-1', loinc_display = 'XR Sacrococcyx AP'                                 WHERE rad_id = 'R110';  -- SACRO-COCCYGEUS AP
UPDATE rsmst_radiologis SET loinc_code = '36698-9', loinc_display = 'XR Sacrococcyx Lateral'                            WHERE rad_id = 'R111';  -- SACRO-COCCYGEUS LAT

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Upper Extremity
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '36695-5', loinc_display = 'XR Clavicle'                                       WHERE rad_id = 'R34';   -- CLAVICULA D
UPDATE rsmst_radiologis SET loinc_code = '36695-5', loinc_display = 'XR Clavicle'                                       WHERE rad_id = 'R56';   -- CLAVICULA S
UPDATE rsmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder AP'                                    WHERE rad_id = 'R51';   -- SHOULDER AP D
UPDATE rsmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder AP'                                    WHERE rad_id = 'R59';   -- SHOULDER AP S
UPDATE rsmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder'                                       WHERE rad_id = 'R35';   -- SHOULDER JOINT D
UPDATE rsmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder'                                       WHERE rad_id = 'R60';   -- SHOULDER JOINT S
UPDATE rsmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder Axial'                                 WHERE rad_id = 'R107';  -- SHOULDER AXIAL
UPDATE rsmst_radiologis SET loinc_code = '37220-2', loinc_display = 'XR Humerus'                                        WHERE rad_id = 'R26';   -- HUMERUS D
UPDATE rsmst_radiologis SET loinc_code = '37220-2', loinc_display = 'XR Humerus'                                        WHERE rad_id = 'R47';   -- HUMERUS S
UPDATE rsmst_radiologis SET loinc_code = '36856-3', loinc_display = 'XR Elbow'                                          WHERE rad_id = 'R36';   -- ELBOW D
UPDATE rsmst_radiologis SET loinc_code = '36856-3', loinc_display = 'XR Elbow'                                          WHERE rad_id = 'R61';   -- ELBOW S
UPDATE rsmst_radiologis SET loinc_code = '37048-7', loinc_display = 'XR Forearm'                                        WHERE rad_id = 'R17';   -- ANTEBRACHII D
UPDATE rsmst_radiologis SET loinc_code = '37048-7', loinc_display = 'XR Forearm'                                        WHERE rad_id = 'R27';   -- ANTEBRACHII S
UPDATE rsmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R37';   -- WRIST JOINT D
UPDATE rsmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R62';   -- WRIST JOINT D (dup)
UPDATE rsmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R63';   -- WRIST JOINT S
UPDATE rsmst_radiologis SET loinc_code = '37154-3', loinc_display = 'XR Hand'                                           WHERE rad_id = 'R38';   -- MANUS S
UPDATE rsmst_radiologis SET loinc_code = '37154-3', loinc_display = 'XR Hand'                                           WHERE rad_id = 'R53';   -- MANUS D

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Lower Extremity
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '37026-3', loinc_display = 'XR Femur'                                          WHERE rad_id = 'R28';   -- FEMUR D
UPDATE rsmst_radiologis SET loinc_code = '37026-3', loinc_display = 'XR Femur'                                          WHERE rad_id = 'R49';   -- FEMUR S
UPDATE rsmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee'                                           WHERE rad_id = 'R19';   -- GENU D
UPDATE rsmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee'                                           WHERE rad_id = 'R52';   -- GENU S
UPDATE rsmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee Skyline'                                   WHERE rad_id = 'R64';   -- SKYLINE D
UPDATE rsmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee Skyline'                                   WHERE rad_id = 'R65';   -- SKYLINE S
UPDATE rsmst_radiologis SET loinc_code = '37917-3', loinc_display = 'XR Tibia and Fibula'                               WHERE rad_id = 'R20';   -- CRURIS S
UPDATE rsmst_radiologis SET loinc_code = '37917-3', loinc_display = 'XR Tibia and Fibula'                               WHERE rad_id = 'R29';   -- CRURIS D
UPDATE rsmst_radiologis SET loinc_code = '36619-5', loinc_display = 'XR Ankle'                                          WHERE rad_id = 'R41';   -- ANKLE JOINT D
UPDATE rsmst_radiologis SET loinc_code = '36619-5', loinc_display = 'XR Ankle'                                          WHERE rad_id = 'R67';   -- ANKLE JOINT S
UPDATE rsmst_radiologis SET loinc_code = '36639-3', loinc_display = 'XR Calcaneus'                                      WHERE rad_id = 'R42';   -- CALCANEUS D
UPDATE rsmst_radiologis SET loinc_code = '36639-3', loinc_display = 'XR Calcaneus'                                      WHERE rad_id = 'R70';   -- CALCANEUS S
UPDATE rsmst_radiologis SET loinc_code = '37042-0', loinc_display = 'XR Foot'                                           WHERE rad_id = 'R43';   -- PEDIS D
UPDATE rsmst_radiologis SET loinc_code = '37042-0', loinc_display = 'XR Foot'                                           WHERE rad_id = 'R68';   -- PEDIS S

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Kontras / Fluoroscopy
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract IVP'                              WHERE rad_id = 'R44';   -- IVP 1
UPDATE rsmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract IVP'                              WHERE rad_id = 'R45';   -- IVP 2
UPDATE rsmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract Single shot IVP'                  WHERE rad_id = 'R80';   -- SINGLE SHOT IVP
UPDATE rsmst_radiologis SET loinc_code = '24745-0', loinc_display = 'RF Colon Barium enema'                             WHERE rad_id = 'R71';   -- COLON IN LOOP
UPDATE rsmst_radiologis SET loinc_code = '24745-0', loinc_display = 'RF Colon Barium enema'                             WHERE rad_id = 'R81';   -- COLON IN LOOP BAYI
UPDATE rsmst_radiologis SET loinc_code = '24852-4', loinc_display = 'RF Urethra Urethrography'                          WHERE rad_id = 'R72';   -- URETHROGRAFI
UPDATE rsmst_radiologis SET loinc_code = '24695-7', loinc_display = 'RF Bladder Cystography'                            WHERE rad_id = 'R73';   -- SISTOGRAFI
UPDATE rsmst_radiologis SET loinc_code = '24852-4', loinc_display = 'RF Urethra and Bladder Voiding cystourethrography' WHERE rad_id = 'R77';   -- URETHRO-SISTOGRAFI
UPDATE rsmst_radiologis SET loinc_code = '24853-2', loinc_display = 'RF Uterus and Fallopian tubes HSG'                 WHERE rad_id = 'R74';   -- HSG
UPDATE rsmst_radiologis SET loinc_code = '24853-2', loinc_display = 'RF Uterus and Fallopian tubes HSG'                 WHERE rad_id = 'H01';   -- HISTEROSALPINGOGRAFI
UPDATE rsmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Upper GI tract with barium'                     WHERE rad_id = 'R75';   -- UPPER GI
UPDATE rsmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Upper GI tract with barium'                     WHERE rad_id = 'R82';   -- UPPER GI BAYI
UPDATE rsmst_radiologis SET loinc_code = '24856-5', loinc_display = 'RF Esophagus with barium'                          WHERE rad_id = 'R76';   -- OESOPHAGOGRAFI
UPDATE rsmst_radiologis SET loinc_code = '24626-2', loinc_display = 'RF Appendix with barium'                           WHERE rad_id = 'R78';   -- APPENDICOGRAFI 1
UPDATE rsmst_radiologis SET loinc_code = '24626-2', loinc_display = 'RF Appendix with barium'                           WHERE rad_id = 'R79';   -- APPENDICOGRAFI 2
UPDATE rsmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Barium follow through'                          WHERE rad_id = 'R83';   -- BARIUM FOLLOW THROUGH

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Bayi
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Babygram'                                       WHERE rad_id = 'R119';  -- BABYGRAM

-- ────────────────────────────────────────────────────────────
-- RONTGEN — Lain-lain
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '37629-4', loinc_display = 'XR Penis'                                          WHERE rad_id = 'R106';  -- PENIS

-- ────────────────────────────────────────────────────────────
-- USG
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '24590-2', loinc_display = 'US Head'                                           WHERE rad_id = 'U1';    -- USG KEPALA
UPDATE rsmst_radiologis SET loinc_code = '24648-6', loinc_display = 'US Carotid artery Duplex'                          WHERE rad_id = 'U2';    -- USG DOPPLER CAROTIS
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen'                                        WHERE rad_id = 'U3';    -- USG ABDOMEN TOTAL
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen lower'                                  WHERE rad_id = 'U4';    -- USG ABDOMEN BAWAH
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen upper'                                  WHERE rad_id = 'U5';    -- USG ABDOMEN ATAS
UPDATE rsmst_radiologis SET loinc_code = '24685-8', loinc_display = 'US Liver Doppler'                                  WHERE rad_id = 'U6';    -- USG HEPAR DOPPLER
UPDATE rsmst_radiologis SET loinc_code = '24843-3', loinc_display = 'US Thyroid'                                        WHERE rad_id = 'U7';    -- USG TYROID
UPDATE rsmst_radiologis SET loinc_code = '24843-3', loinc_display = 'US Thyroid'                                        WHERE rad_id = 'U8';    -- USG TYROID
UPDATE rsmst_radiologis SET loinc_code = '24838-3', loinc_display = 'US Salivary gland'                                 WHERE rad_id = 'U9';    -- USG KELENJAR LIUR
UPDATE rsmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Urinary tract'                                  WHERE rad_id = 'U10';   -- USG UROLOGI
UPDATE rsmst_radiologis SET loinc_code = '24696-5', loinc_display = 'US Kidney Duplex'                                  WHERE rad_id = 'U11';   -- USG DOPPLER GINJAL D/S
UPDATE rsmst_radiologis SET loinc_code = '24696-5', loinc_display = 'US Kidney Duplex bilateral'                        WHERE rad_id = 'U12';   -- USG DOPPLER GINJAL D&S
UPDATE rsmst_radiologis SET loinc_code = '24604-9', loinc_display = 'US Breast unilateral'                              WHERE rad_id = 'U13';   -- USG MAMMAE D/S
UPDATE rsmst_radiologis SET loinc_code = '24604-9', loinc_display = 'US Breast bilateral'                               WHERE rad_id = 'U14';   -- USG MAMMAE D&S
UPDATE rsmst_radiologis SET loinc_code = '37432-3', loinc_display = 'US Axilla'                                         WHERE rad_id = 'U15';   -- USG AXILLA
UPDATE rsmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis transvaginal'                            WHERE rad_id = 'U16';   -- USG KANDUNGAN CDFI
UPDATE rsmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis 4D'                                      WHERE rad_id = 'U17';   -- USG KANDUNGAN 4D
UPDATE rsmst_radiologis SET loinc_code = '24841-7', loinc_display = 'US Scrotum'                                        WHERE rad_id = 'U18';   -- USG TESTIS
UPDATE rsmst_radiologis SET loinc_code = '37432-3', loinc_display = 'US Inguinal'                                       WHERE rad_id = 'U19';   -- USG INGUINAL
UPDATE rsmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Lower extremity vein Duplex'                    WHERE rad_id = 'U20';   -- USG DVT
UPDATE rsmst_radiologis SET loinc_code = '24754-2', loinc_display = 'US Musculoskeletal'                                WHERE rad_id = 'U21';   -- USG MUSCULOSKELETAL
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Doppler per organ'                              WHERE rad_id = 'U22';   -- USG DOPPLER PER ORGAN
UPDATE rsmst_radiologis SET loinc_code = '24643-7', loinc_display = 'US Chest'                                          WHERE rad_id = 'U23';   -- USG THORAX MARKER
UPDATE rsmst_radiologis SET loinc_code = '24643-7', loinc_display = 'US Chest'                                          WHERE rad_id = 'U24';   -- USG THORAX MARKER CITO
UPDATE rsmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Extremity vein Duplex'                          WHERE rad_id = 'U25';   -- USG DOPPLER EXTERMITAS
UPDATE rsmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Upper extremity Duplex'                         WHERE rad_id = 'U26';   -- USG DOPPLER EXT ATAS
UPDATE rsmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Lower extremity Duplex'                         WHERE rad_id = 'U27';   -- USG DOPPLER EXT BWH
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Neck'                                           WHERE rad_id = 'U28';   -- USG COLLI

-- ────────────────────────────────────────────────────────────
-- USG — CITO
-- ────────────────────────────────────────────────────────────
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen'                                        WHERE rad_id = 'R212';  -- USG ABDOMEN CITO
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen upper'                                  WHERE rad_id = 'R213';  -- USG UPPER ABDOMEN CITO
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen lower'                                  WHERE rad_id = 'R214';  -- USG LOWER ABDOMEN CITO
UPDATE rsmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Urinary tract'                                  WHERE rad_id = 'R215';  -- USG UROLOGI CITO
UPDATE rsmst_radiologis SET loinc_code = '24841-7', loinc_display = 'US Scrotum'                                        WHERE rad_id = 'R216';  -- USG TESTIS CITO
UPDATE rsmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis'                                         WHERE rad_id = 'R217';  -- USG OBGYN CITO
UPDATE rsmst_radiologis SET loinc_code = '24685-8', loinc_display = 'US Abdomen Doppler'                                WHERE rad_id = 'R218';  -- USG ABDOMEN + DOPPLER

COMMIT;
