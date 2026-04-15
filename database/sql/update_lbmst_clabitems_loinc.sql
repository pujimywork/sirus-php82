-- ============================================================
-- Update: LBMST_CLABITEMS — Mapping kode LOINC untuk Satu Sehat
-- Jalankan setelah alter_lbmst_clabitems_add_loinc.sql
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- PAKET HEADER (ServiceRequest / DiagnosticReport)
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '58410-2', loinc_display = 'CBC panel - Blood by Automated count'                WHERE clabitem_id = 'HE00001';  -- HEMATOLOGI 3 DIFF
UPDATE lbmst_clabitems SET loinc_code = '57021-8', loinc_display = 'CBC W Ordered Manual Differential panel - Blood'     WHERE clabitem_id = 'HE00005';  -- HEMATOLOGI 5 DIFF
UPDATE lbmst_clabitems SET loinc_code = '24362-6', loinc_display = 'Urinalysis complete panel - Urine'                   WHERE clabitem_id = 'UR00030';  -- URIN LENGKAP
UPDATE lbmst_clabitems SET loinc_code = '24331-1', loinc_display = 'Lipid 1996 panel - Serum or Plasma'                  WHERE clabitem_id = 'WI00021';  -- WIDAL (panel)
UPDATE lbmst_clabitems SET loinc_code = '24362-6', loinc_display = 'Urinalysis complete panel - Urine'                   WHERE clabitem_id = 'MA00067';  -- FECES LENGKAP → pakai panel serupa
UPDATE lbmst_clabitems SET loinc_code = '24363-4', loinc_display = 'Renal function panel - Serum or Plasma'              WHERE clabitem_id = 'RF00026';  -- RFT
UPDATE lbmst_clabitems SET loinc_code = '75377-2', loinc_display = 'Dengue virus NS1 Ag panel - Serum'                   WHERE clabitem_id = 'TE00080';  -- TEST DENGUE
UPDATE lbmst_clabitems SET loinc_code = '40675-1', loinc_display = 'Leptospira Ab panel - Serum'                         WHERE clabitem_id = 'LE00160';  -- LEPTOSPIRA
UPDATE lbmst_clabitems SET loinc_code = '56888-1', loinc_display = 'Toxoplasma gondii Ab panel - Serum'                  WHERE clabitem_id = 'TO00109';  -- TOXOPLASMA
UPDATE lbmst_clabitems SET loinc_code = '69668-2', loinc_display = 'Salmonella sp Ab panel - Serum'                      WHERE clabitem_id = 'AN00150';  -- ANTI SALMONELLA
UPDATE lbmst_clabitems SET loinc_code = '90423-5', loinc_display = 'HIV 1+2 Ab and HIV1 p24 Ag panel - Serum or Plasma'  WHERE clabitem_id = 'HI00156';  -- HIV/SYPHILIS COMBO
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00104'; -- BTA DOT

-- ────────────────────────────────────────────────────────────
-- HEMATOLOGI — Item Anak (Observation)
-- ────────────────────────────────────────────────────────────

-- Hematologi 3 Diff (HE001)
UPDATE lbmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HA00002';  -- HAEMOGLOBIN
UPDATE lbmst_clabitems SET loinc_code = '789-8',   loinc_display = 'Erythrocytes [#/volume] in Blood by Automated count' WHERE clabitem_id = 'ER00003';  -- ERITROSIT
UPDATE lbmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE00004';  -- LEUKOSIT
UPDATE lbmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE00005';  -- LED JAM KE 1
UPDATE lbmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE00140';  -- LED JAM KE 2
UPDATE lbmst_clabitems SET loinc_code = '713-8',   loinc_display = 'Eosinophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'EO00006';  -- EOSINOFIL %
UPDATE lbmst_clabitems SET loinc_code = '706-2',   loinc_display = 'Basophils/100 leukocytes in Blood'                   WHERE clabitem_id = 'BA00007';  -- BASOFIL %
UPDATE lbmst_clabitems SET loinc_code = '770-8',   loinc_display = 'Neutrophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'SE00009';  -- NEUTROFIL %
UPDATE lbmst_clabitems SET loinc_code = '736-9',   loinc_display = 'Lymphocytes/100 leukocytes in Blood'                 WHERE clabitem_id = 'LI00010';  -- LIMPHOSIT %
UPDATE lbmst_clabitems SET loinc_code = '5905-5',  loinc_display = 'Monocytes/100 leukocytes in Blood'                   WHERE clabitem_id = 'MO00011';  -- MONOSIT %
UPDATE lbmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR00012';  -- TROMBOSIT
UPDATE lbmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'HA00013';  -- HAEMATOKRIT
UPDATE lbmst_clabitems SET loinc_code = '731-0',   loinc_display = 'Lymphocytes [#/volume] in Blood'                     WHERE clabitem_id = 'LY00128';  -- LYMPH#
UPDATE lbmst_clabitems SET loinc_code = '751-8',   loinc_display = 'Neutrophils [#/volume] in Blood'                     WHERE clabitem_id = 'GR00130';  -- GRAN#
UPDATE lbmst_clabitems SET loinc_code = '787-2',   loinc_display = 'MCV [Entitic volume]'                                WHERE clabitem_id = 'MC00131';  -- MCV
UPDATE lbmst_clabitems SET loinc_code = '785-6',   loinc_display = 'MCH [Entitic mass]'                                  WHERE clabitem_id = 'MC001311'; -- MCH
UPDATE lbmst_clabitems SET loinc_code = '786-4',   loinc_display = 'MCHC [Mass/volume]'                                  WHERE clabitem_id = 'MC00132';  -- MCHC
UPDATE lbmst_clabitems SET loinc_code = '788-0',   loinc_display = 'Erythrocyte distribution width [Ratio] by Automated count' WHERE clabitem_id = 'RD00133'; -- RDW-CV
UPDATE lbmst_clabitems SET loinc_code = '21000-5', loinc_display = 'Erythrocyte distribution width [Entitic volume]'     WHERE clabitem_id = 'RD00134';  -- RDW-SD
UPDATE lbmst_clabitems SET loinc_code = '32207-3', loinc_display = 'Platelet distribution width [Entitic volume]'        WHERE clabitem_id = 'PD00136';  -- PDW
UPDATE lbmst_clabitems SET loinc_code = '32623-1', loinc_display = 'Platelet mean volume [Entitic volume]'               WHERE clabitem_id = 'MP00135';  -- MPV
UPDATE lbmst_clabitems SET loinc_code = '37854-8', loinc_display = 'Plateletcrit [Volume Fraction] in Blood'             WHERE clabitem_id = 'PC00137';  -- PCT
UPDATE lbmst_clabitems SET loinc_code = '49497-1', loinc_display = 'Platelets large [#/volume] in Blood'                 WHERE clabitem_id = 'P-00138';  -- P-LCC
UPDATE lbmst_clabitems SET loinc_code = '71260-4', loinc_display = 'Platelets large/100 platelets in Blood'              WHERE clabitem_id = 'P-00139';  -- P-LRC
UPDATE lbmst_clabitems SET loinc_code = '742-7',   loinc_display = 'Monocytes [#/volume] in Blood'                       WHERE clabitem_id = 'MI00129';  -- MID#

-- Hematologi 5 Diff (HE005) — kode LOINC sama, beda clabitem_id
UPDATE lbmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HA500002';
UPDATE lbmst_clabitems SET loinc_code = '789-8',   loinc_display = 'Erythrocytes [#/volume] in Blood by Automated count' WHERE clabitem_id = 'ER500003';
UPDATE lbmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE500004';
UPDATE lbmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE500005';
UPDATE lbmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE500140';
UPDATE lbmst_clabitems SET loinc_code = '713-8',   loinc_display = 'Eosinophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'EO500006';
UPDATE lbmst_clabitems SET loinc_code = '706-2',   loinc_display = 'Basophils/100 leukocytes in Blood'                   WHERE clabitem_id = 'BA500007';
UPDATE lbmst_clabitems SET loinc_code = '770-8',   loinc_display = 'Neutrophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'SE500009';
UPDATE lbmst_clabitems SET loinc_code = '736-9',   loinc_display = 'Lymphocytes/100 leukocytes in Blood'                 WHERE clabitem_id = 'LI500010';
UPDATE lbmst_clabitems SET loinc_code = '5905-5',  loinc_display = 'Monocytes/100 leukocytes in Blood'                   WHERE clabitem_id = 'MO500011';
UPDATE lbmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR500012';
UPDATE lbmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'HA500013';
UPDATE lbmst_clabitems SET loinc_code = '731-0',   loinc_display = 'Lymphocytes [#/volume] in Blood'                     WHERE clabitem_id = 'LY500128';
UPDATE lbmst_clabitems SET loinc_code = '751-8',   loinc_display = 'Neutrophils [#/volume] in Blood'                     WHERE clabitem_id = 'GR500130';
UPDATE lbmst_clabitems SET loinc_code = '787-2',   loinc_display = 'MCV [Entitic volume]'                                WHERE clabitem_id = 'MC500131';
UPDATE lbmst_clabitems SET loinc_code = '785-6',   loinc_display = 'MCH [Entitic mass]'                                  WHERE clabitem_id = 'MC5001311';
UPDATE lbmst_clabitems SET loinc_code = '786-4',   loinc_display = 'MCHC [Mass/volume]'                                  WHERE clabitem_id = 'MC500132';
UPDATE lbmst_clabitems SET loinc_code = '788-0',   loinc_display = 'Erythrocyte distribution width [Ratio]'              WHERE clabitem_id = 'RD500133';
UPDATE lbmst_clabitems SET loinc_code = '21000-5', loinc_display = 'Erythrocyte distribution width [Entitic volume]'     WHERE clabitem_id = 'RD500134';
UPDATE lbmst_clabitems SET loinc_code = '32207-3', loinc_display = 'Platelet distribution width [Entitic volume]'        WHERE clabitem_id = 'PD500136';
UPDATE lbmst_clabitems SET loinc_code = '32623-1', loinc_display = 'Platelet mean volume [Entitic volume]'               WHERE clabitem_id = 'MP500135';
UPDATE lbmst_clabitems SET loinc_code = '37854-8', loinc_display = 'Plateletcrit [Volume Fraction] in Blood'             WHERE clabitem_id = 'PC500137';
UPDATE lbmst_clabitems SET loinc_code = '49497-1', loinc_display = 'Platelets large [#/volume] in Blood'                 WHERE clabitem_id = 'P5-00138';
UPDATE lbmst_clabitems SET loinc_code = '71260-4', loinc_display = 'Platelets large/100 platelets in Blood'              WHERE clabitem_id = 'P5-00139';
UPDATE lbmst_clabitems SET loinc_code = '742-7',   loinc_display = 'Monocytes [#/volume] in Blood'                       WHERE clabitem_id = 'MI500129';
UPDATE lbmst_clabitems SET loinc_code = '711-2',   loinc_display = 'Eosinophils [#/volume] in Blood'                     WHERE clabitem_id = 'EO00205';  -- EOSINOFIL# (5diff)
UPDATE lbmst_clabitems SET loinc_code = '704-7',   loinc_display = 'Basophils [#/volume] in Blood'                       WHERE clabitem_id = 'BA00206';  -- BASOFIL# (5diff)

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Gula Darah
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '1558-6',  loinc_display = 'Fasting glucose [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'GU00014';  -- GULA DARAH PUASA
UPDATE lbmst_clabitems SET loinc_code = '1521-4',  loinc_display = 'Glucose [Mass/volume] in Serum or Plasma --2 hours post meal' WHERE clabitem_id = 'GU00015'; -- GD 2 JAM PP
UPDATE lbmst_clabitems SET loinc_code = '2339-0',  loinc_display = 'Glucose [Mass/volume] in Blood'                      WHERE clabitem_id = 'GU00016';  -- GD SEWAKTU
UPDATE lbmst_clabitems SET loinc_code = '4548-4',  loinc_display = 'Hemoglobin A1c/Hemoglobin.total in Blood'            WHERE clabitem_id = 'HB00090';  -- HBA1C

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Profil Lipid
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '2571-8',  loinc_display = 'Triglyceride [Mass/volume] in Serum or Plasma'       WHERE clabitem_id = 'TR00017';  -- TRIGLYSERIDA
UPDATE lbmst_clabitems SET loinc_code = '2093-3',  loinc_display = 'Cholesterol [Mass/volume] in Serum or Plasma'        WHERE clabitem_id = 'CH00018';  -- CHOLESTEROL
UPDATE lbmst_clabitems SET loinc_code = '2085-9',  loinc_display = 'HDL Cholesterol [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'HD00019';  -- HDL
UPDATE lbmst_clabitems SET loinc_code = '2089-1',  loinc_display = 'LDL Cholesterol [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'LD00020';  -- LDL

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Fungsi Hati
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '1920-8',  loinc_display = 'Aspartate aminotransferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'SG00044'; -- SGOT
UPDATE lbmst_clabitems SET loinc_code = '1742-6',  loinc_display = 'Alanine aminotransferase [Enzymatic activity/volume] in Serum or Plasma'   WHERE clabitem_id = 'SG00045'; -- SGPT
UPDATE lbmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00075';  -- BIL TOTAL
UPDATE lbmst_clabitems SET loinc_code = '1968-7',  loinc_display = 'Bilirubin.direct [Mass/volume] in Serum or Plasma'   WHERE clabitem_id = 'BI00042';  -- BIL DIRECT
UPDATE lbmst_clabitems SET loinc_code = '1971-1',  loinc_display = 'Bilirubin.indirect [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'BI00043';  -- BIL INDIRECT
UPDATE lbmst_clabitems SET loinc_code = '1751-7',  loinc_display = 'Albumin [Mass/volume] in Serum or Plasma'            WHERE clabitem_id = 'AL00046';  -- ALBUMIN
UPDATE lbmst_clabitems SET loinc_code = '2885-2',  loinc_display = 'Protein [Mass/volume] in Serum or Plasma'            WHERE clabitem_id = 'TO00073';  -- TOTAL PROTEIN
UPDATE lbmst_clabitems SET loinc_code = '10834-0', loinc_display = 'Globulin [Mass/volume] in Serum by calculation'      WHERE clabitem_id = 'GL00074';  -- GLOBULIN
UPDATE lbmst_clabitems SET loinc_code = '6768-6',  loinc_display = 'Alkaline phosphatase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'AL00146'; -- ALP
UPDATE lbmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00141'; -- GAMMA GT DEWASA
UPDATE lbmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00142'; -- GAMMA GT 1HR-6BLN
UPDATE lbmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00143'; -- GAMMA GT 6BLN-1TH
UPDATE lbmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00144'; -- GAMMA GT 1-12TH
UPDATE lbmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00145'; -- GAMMA GT 13-18TH

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Fungsi Ginjal (RFT)
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '3091-6',  loinc_display = 'Urea nitrogen [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'UR00027';  -- UREUM
UPDATE lbmst_clabitems SET loinc_code = '3094-0',  loinc_display = 'Urea nitrogen [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'BU00028';  -- BUN
UPDATE lbmst_clabitems SET loinc_code = '2160-0',  loinc_display = 'Creatinine [Mass/volume] in Serum or Plasma'         WHERE clabitem_id = 'CR00029';  -- CREATININ
UPDATE lbmst_clabitems SET loinc_code = '3084-1',  loinc_display = 'Urate [Mass/volume] in Serum or Plasma'              WHERE clabitem_id = 'UR00055';  -- URIC ACID

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Elektrolit
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '2823-3',  loinc_display = 'Potassium [Moles/volume] in Serum or Plasma'         WHERE clabitem_id = 'KA00164';  -- KALIUM
UPDATE lbmst_clabitems SET loinc_code = '2951-2',  loinc_display = 'Sodium [Moles/volume] in Serum or Plasma'            WHERE clabitem_id = 'NA00165';  -- NATRIUM
UPDATE lbmst_clabitems SET loinc_code = '2075-0',  loinc_display = 'Chloride [Moles/volume] in Serum or Plasma'          WHERE clabitem_id = 'CH00166';  -- CHLORIDA

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Cardiac Marker
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '49563-0', loinc_display = 'Troponin I.cardiac [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CT00092';  -- C-TROPONIN I
UPDATE lbmst_clabitems SET loinc_code = '32673-6', loinc_display = 'Creatine kinase.MB [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CK00154';  -- CK-MB
UPDATE lbmst_clabitems SET loinc_code = '48065-7', loinc_display = 'Fibrin D-dimer FEU [Mass/volume] in Platelet poor plasma' WHERE clabitem_id = 'D-00159'; -- D-DIMER
UPDATE lbmst_clabitems SET loinc_code = '75241-0', loinc_display = 'Procalcitonin [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'PR00163';  -- PROCALCITONIN

-- ────────────────────────────────────────────────────────────
-- KIMIA DARAH — Tumor Marker & Hormon
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '2039-6',  loinc_display = 'Carcinoembryonic Ag [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CE00091';  -- CEA
UPDATE lbmst_clabitems SET loinc_code = '2986-8',  loinc_display = 'Testosterone [Mass/volume] in Serum or Plasma'       WHERE clabitem_id = 'TE00093';  -- TESTOSTERON
UPDATE lbmst_clabitems SET loinc_code = '3026-2',  loinc_display = 'Thyroxine (T4) [Moles/volume] in Serum or Plasma'    WHERE clabitem_id = 'T400094';  -- T4
UPDATE lbmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00095';  -- TSH 21-54
UPDATE lbmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00096';  -- TSH 55-87
UPDATE lbmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00097';  -- TSH HAMIL TM1
UPDATE lbmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00098';  -- TSH HAMIL TM2
UPDATE lbmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00099';  -- TSH HAMIL TM3
UPDATE lbmst_clabitems SET loinc_code = '3024-7',  loinc_display = 'Thyroxine (T4) free [Moles/volume] in Serum or Plasma' WHERE clabitem_id = 'FT00171'; -- FT4
UPDATE lbmst_clabitems SET loinc_code = '3053-6',  loinc_display = 'Triiodothyronine (T3) [Moles/volume] in Serum or Plasma' WHERE clabitem_id = 'B-00127'; -- T3

-- ────────────────────────────────────────────────────────────
-- HEMOSTASIS
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '3184-9',  loinc_display = 'Coagulation tissue factor induced.clot time'         WHERE clabitem_id = 'CT00071';  -- CT
UPDATE lbmst_clabitems SET loinc_code = '11067-6', loinc_display = 'Bleeding time'                                       WHERE clabitem_id = 'BT00072';  -- BT

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI / SEROLOGI — Widal
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '5765-5',  loinc_display = 'Salmonella typhi O Ab [Titer] in Serum'              WHERE clabitem_id = 'S.00022';  -- S.TYPHI O
UPDATE lbmst_clabitems SET loinc_code = '5764-8',  loinc_display = 'Salmonella typhi H Ab [Titer] in Serum'              WHERE clabitem_id = 'S.00023';  -- S.TYPHI H
UPDATE lbmst_clabitems SET loinc_code = '5758-0',  loinc_display = 'Salmonella paratyphi A Ab [Titer] in Serum'          WHERE clabitem_id = 'S.00024';  -- S.PARATYPHY A
UPDATE lbmst_clabitems SET loinc_code = '5760-6',  loinc_display = 'Salmonella paratyphi B Ab [Titer] in Serum'          WHERE clabitem_id = 'S.00025';  -- S.PARATYPHY B

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — Hepatitis
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'HB00047';  -- HBS AG
UPDATE lbmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'HB00209';  -- HBS AG (ANSWER)
UPDATE lbmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'GB00210';  -- HBSAG FASTCLEAR

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — Dengue
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '75377-2', loinc_display = 'Dengue virus NS1 Ag [Presence] in Serum by Immunoassay' WHERE clabitem_id = 'PE00113'; -- NS1
UPDATE lbmst_clabitems SET loinc_code = '29676-4', loinc_display = 'Dengue virus IgG Ab [Presence] in Serum'             WHERE clabitem_id = 'DE00083';  -- DENGUE IGG
UPDATE lbmst_clabitems SET loinc_code = '29504-8', loinc_display = 'Dengue virus IgM Ab [Presence] in Serum'             WHERE clabitem_id = 'DE00084';  -- DENGUE IGM

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — Toxoplasma
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '8039-1',  loinc_display = 'Toxoplasma gondii IgG Ab [Units/volume] in Serum'    WHERE clabitem_id = 'TO00110';  -- TOXO IGG
UPDATE lbmst_clabitems SET loinc_code = '8040-9',  loinc_display = 'Toxoplasma gondii IgM Ab [Units/volume] in Serum'    WHERE clabitem_id = 'TO00112';  -- TOXO IGM

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — Leptospira
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '40674-4', loinc_display = 'Leptospira sp IgG Ab [Presence] in Serum'            WHERE clabitem_id = 'IG00161';  -- IGG LEPTOSPIRA
UPDATE lbmst_clabitems SET loinc_code = '40675-1', loinc_display = 'Leptospira sp IgM Ab [Presence] in Serum'            WHERE clabitem_id = 'IG00162';  -- IGM LEPTOSPIRA

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — HIV & Syphilis
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '68961-2', loinc_display = 'HIV 1+2 Ab [Presence] in Serum or Plasma'            WHERE clabitem_id = 'HI00157';  -- HIV (combo)
UPDATE lbmst_clabitems SET loinc_code = '20507-0', loinc_display = 'Treponema pallidum Ab [Presence] in Serum'           WHERE clabitem_id = 'SY00158';  -- SYPHILIS (combo)
UPDATE lbmst_clabitems SET loinc_code = '68961-2', loinc_display = 'HIV 1+2 Ab [Presence] in Serum or Plasma'            WHERE clabitem_id = 'HI00212';  -- HIV ORIENT GENE
UPDATE lbmst_clabitems SET loinc_code = '20507-0', loinc_display = 'Treponema pallidum Ab [Presence] in Serum'           WHERE clabitem_id = 'PR00148';  -- SYPHILIS TREPOCHECK

-- ────────────────────────────────────────────────────────────
-- IMUNOLOGI — Anti SARS-CoV-2
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '94500-6', loinc_display = 'SARS-CoV-2 RNA [Presence] in Respiratory specimen by NAA' WHERE clabitem_id = 'SW00147'; -- SWAB PCR
UPDATE lbmst_clabitems SET loinc_code = '94563-4', loinc_display = 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00159';  -- ANTI SARS IGG
UPDATE lbmst_clabitems SET loinc_code = '94564-2', loinc_display = 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IGM00159'; -- ANTI SARS IGM
UPDATE lbmst_clabitems SET loinc_code = '95209-3', loinc_display = 'SARS-CoV-2 Ag [Presence] in Respiratory specimen by Rapid immunoassay' WHERE clabitem_id = 'SW00146'; -- SWAB RAPID ANTIGEN
UPDATE lbmst_clabitems SET loinc_code = '94563-4', loinc_display = 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00144';  -- SCREENING IGG
UPDATE lbmst_clabitems SET loinc_code = '94564-2', loinc_display = 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00145';  -- SCREENING IGM

-- ────────────────────────────────────────────────────────────
-- MIKROBIOLOGI — BTA
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00064'; -- BTA PAGI
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00065'; -- BTA SORE
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00066'; -- BTA SEWAKTU
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00105'; -- BTA SEWAKTU 1
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00106'; -- BTA PAGI (group)
UPDATE lbmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00107'; -- BTA SEWAKTU 2

-- ────────────────────────────────────────────────────────────
-- PARASITOLOGI — Malaria
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '51587-4', loinc_display = 'Plasmodium falciparum Ag [Presence] in Blood'        WHERE clabitem_id = 'PL00088';  -- P. FALCIPARUM
UPDATE lbmst_clabitems SET loinc_code = '51588-2', loinc_display = 'Plasmodium vivax Ag [Presence] in Blood'             WHERE clabitem_id = 'PL00089';  -- P. VIVAX

-- ────────────────────────────────────────────────────────────
-- URINALISA — Item Anak
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '5770-3',  loinc_display = 'Albumin [Presence] in Urine by Test strip'           WHERE clabitem_id = 'AL00031';  -- ALBUMIN URIN
UPDATE lbmst_clabitems SET loinc_code = '1977-8',  loinc_display = 'Bilirubin [Presence] in Urine by Test strip'         WHERE clabitem_id = 'BI00032';  -- BILIRUBIN URIN
UPDATE lbmst_clabitems SET loinc_code = '5792-7',  loinc_display = 'Glucose [Presence] in Urine by Test strip'           WHERE clabitem_id = 'RE00033';  -- REDUKSI
UPDATE lbmst_clabitems SET loinc_code = '5818-0',  loinc_display = 'Urobilinogen [Presence] in Urine by Test strip'      WHERE clabitem_id = 'UR00034';  -- UROBILINOGEN
UPDATE lbmst_clabitems SET loinc_code = '5797-6',  loinc_display = 'Ketones [Presence] in Urine by Test strip'           WHERE clabitem_id = 'KE00035';  -- KETON
UPDATE lbmst_clabitems SET loinc_code = '5802-4',  loinc_display = 'Nitrite [Presence] in Urine by Test strip'           WHERE clabitem_id = 'NI00172';  -- NITRIT
UPDATE lbmst_clabitems SET loinc_code = '5808-1',  loinc_display = 'Erythrocytes [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'ER00036'; -- ERYTROSIT URIN
UPDATE lbmst_clabitems SET loinc_code = '5821-4',  loinc_display = 'Leukocytes [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'LE00037';  -- LEUKOSIT URIN
UPDATE lbmst_clabitems SET loinc_code = '11277-1', loinc_display = 'Epithelial cells [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'EP00038'; -- EPYTHEL

-- ────────────────────────────────────────────────────────────
-- LAIN-LAIN
-- ────────────────────────────────────────────────────────────
UPDATE lbmst_clabitems SET loinc_code = '883-9',   loinc_display = 'ABO group [Type] in Blood'                           WHERE clabitem_id = 'GO00056';  -- GOLONGAN DARAH
UPDATE lbmst_clabitems SET loinc_code = '2106-3',  loinc_display = 'Choriogonadotropin (pregnancy test) [Presence] in Urine' WHERE clabitem_id = 'PL00049'; -- PLANO TEST
UPDATE lbmst_clabitems SET loinc_code = '58408-6', loinc_display = 'Peripheral blood smear interpretation'               WHERE clabitem_id = 'HA00050';  -- HAPUSAN DARAH
UPDATE lbmst_clabitems SET loinc_code = '58408-6', loinc_display = 'Peripheral blood smear interpretation'               WHERE clabitem_id = 'HA00152';  -- HAPUSAN DARAH TEPI
UPDATE lbmst_clabitems SET loinc_code = '17849-1', loinc_display = 'Reticulocytes/100 erythrocytes in Blood'             WHERE clabitem_id = 'RE00153';  -- RETIKULOSIT
UPDATE lbmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HE00114';  -- HEMOGLOBIN (standalone)
UPDATE lbmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE00115';  -- LEUKOSIT (standalone)
UPDATE lbmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR00116';  -- TROMBOSIT (standalone)
UPDATE lbmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'PC00117';  -- PCV
UPDATE lbmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00207';  -- BIL BAYI 24JAM
UPDATE lbmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00208';  -- BIL BAYI 25-48JAM

COMMIT;
