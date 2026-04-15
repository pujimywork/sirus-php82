<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator trait untuk mengirim data Rawat Jalan ke Satu Sehat.
 *
 * Alur pengiriman sesuai standar Satu Sehat:
 *   1. Encounter       — Kunjungan (arrived → in-progress → finished)
 *   2. Condition        — Diagnosa ICD-10
 *   3. Observation      — Tanda vital (TD, Nadi, Suhu, RR)
 *   4. Procedure        — Tindakan medis ICD-9 (jika ada)
 *   5. Condition        — Keluhan Utama / Chief Complaint (SNOMED)
 *   6. AllergyIntolerance — Riwayat alergi (jika ada)
 *   7. MedicationRequest  — Resep obat (jika ada)
 *   8. MedicationDispense — Penyerahan obat (jika ada)
 *   9. ServiceRequest     — Permintaan lab/radiologi (jika ada)
 *  10. Observation        — Hasil lab (jika ada)
 *  11. DiagnosticReport   — Laporan hasil penunjang (jika ada)
 *
 * Cara pakai:
 *   use KirimRawatJalanTrait;
 *   $this->initializeSatuSehat();
 *   $result = $this->kirimRawatJalan($dataDaftarPoliRJ, $pasien);
 */
trait KirimRawatJalanTrait
{
    use EncounterTrait, ConditionTrait, ObservationTrait, ProcedureTrait,
        AllergyIntoleranceTrait, MedicationRequestTrait, MedicationDispenseTrait,
        ServiceRequestTrait, DiagnosticReportTrait;

    /**
     * Kirim seluruh data rawat jalan ke Satu Sehat.
     *
     * @param  array  $dataRJ   dataDaftarPoliRJ dari EmrRJTrait
     * @param  array  $pasien   data master pasien
     * @return array  ['success' => bool, 'messages' => [...], 'satusehat' => [...]]
     */
    public function kirimRawatJalan(array $dataRJ, array $pasien): array
    {
        $this->initializeSatuSehat();

        $results = [];
        $errors = [];
        $ss = $dataRJ['satusehat'] ?? [];

        // ── Validasi data wajib ──
        $patientId = $pasien['satusehatId'] ?? '';
        if (empty($patientId)) {
            return ['success' => false, 'messages' => ['Patient belum terdaftar di Satu Sehat (IHS Number kosong).'], 'satusehat' => $ss];
        }

        $practitionerId = $this->getPractitionerIHS($dataRJ['drId'] ?? '');
        if (empty($practitionerId)) {
            return ['success' => false, 'messages' => ['Dokter belum terdaftar di Satu Sehat (IHS Number kosong).'], 'satusehat' => $ss];
        }

        $locationId = $this->getLocationIHS($dataRJ['poliId'] ?? '');
        if (empty($locationId)) {
            return ['success' => false, 'messages' => ['Lokasi/Poli belum terdaftar di Satu Sehat.'], 'satusehat' => $ss];
        }

        // Tanggal kunjungan
        $rjDate = $this->parseRjDate($dataRJ['rjDate'] ?? '');
        $patientName = $pasien['regName'] ?? ($dataRJ['regName'] ?? '');

        // ══════════════════════════════════════
        // 1. ENCOUNTER
        // ══════════════════════════════════════
        $ss = $this->stepEncounter($ss, $dataRJ, $patientId, $patientName, $practitionerId, $locationId, $rjDate, $results, $errors);

        if (empty($ss['encounterId'])) {
            return ['success' => false, 'messages' => [...$results, ...$errors], 'satusehat' => $ss];
        }

        // ══════════════════════════════════════
        // 2. CONDITION — Diagnosa ICD-10
        // ══════════════════════════════════════
        $ss = $this->stepDiagnosa($ss, $dataRJ, $patientId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 3. OBSERVATION — Tanda Vital
        // ══════════════════════════════════════
        $ss = $this->stepTandaVital($ss, $dataRJ, $patientId, $practitionerId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 4. PROCEDURE — Tindakan ICD-9
        // ══════════════════════════════════════
        $ss = $this->stepTindakan($ss, $dataRJ, $patientId, $practitionerId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 5. CONDITION — Keluhan Utama
        // ══════════════════════════════════════
        $ss = $this->stepKeluhanUtama($ss, $dataRJ, $patientId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 6. ALLERGY INTOLERANCE
        // ══════════════════════════════════════
        $ss = $this->stepAlergi($ss, $dataRJ, $pasien, $patientId, $practitionerId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 7. MEDICATION REQUEST — Resep Obat
        // ══════════════════════════════════════
        $ss = $this->stepMedicationRequest($ss, $dataRJ, $patientId, $patientName, $practitionerId, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 8. MEDICATION DISPENSE — Penyerahan Obat
        // ══════════════════════════════════════
        $ss = $this->stepMedicationDispense($ss, $dataRJ, $patientId, $patientName, $rjDate, $results, $errors);

        // ══════════════════════════════════════
        // 9-11. LAB: ServiceRequest → Observation → DiagnosticReport
        // ══════════════════════════════════════
        $ss = $this->stepLab($ss, $dataRJ, $patientId, $practitionerId, $rjDate, $results, $errors);

        return [
            'success'   => empty($errors),
            'messages'  => [...$results, ...$errors],
            'satusehat' => $ss,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // STEP 1: ENCOUNTER
    // ══════════════════════════════════════════════════════════
    private function stepEncounter(array $ss, array $dataRJ, string $patientId, string $patientName, string $practitionerId, string $locationId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            if (empty($ss['encounterId'])) {
                $encRes = $this->createNewEncounter([
                    'encounterId'      => 'RJ-' . ($dataRJ['rjNo'] ?? uniqid()),
                    'patientId'        => $patientId,
                    'patientName'      => $patientName,
                    'practitionerId'   => $practitionerId,
                    'practitionerName' => $dataRJ['drDesc'] ?? '',
                    'locationId'       => $locationId,
                    'class_code'       => 'AMB',
                    'startDate'        => $rjDate->toIso8601String(),
                ]);
                $ss['encounterId'] = $encRes['id'] ?? null;
                $results[] = 'Encounter created: ' . ($ss['encounterId'] ?? '-');
            }

            if (!empty($ss['encounterId']) && empty($ss['encounterInProgress'])) {
                $this->startRoomEncounter($ss['encounterId'], [
                    'startDate'  => $rjDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $ss['encounterInProgress'] = true;
                $results[] = 'Encounter updated: in-progress';
            }

            $isFinished = ($dataRJ['txnStatus'] ?? '') === 'CLOSED' || ($dataRJ['rjStatus'] ?? '') === '2';
            if (!empty($ss['encounterId']) && $isFinished && empty($ss['encounterFinished'])) {
                $existing = $this->getEncounter($ss['encounterId']);
                $existing['status'] = 'finished';
                $existing['statusHistory'][] = [
                    'status' => 'finished',
                    'period' => ['start' => $rjDate->toIso8601String(), 'end' => Carbon::now()->toIso8601String()],
                ];
                $existing['period']['end'] = Carbon::now()->toIso8601String();
                $this->makeRequest('put', "Encounter/{$ss['encounterId']}", $existing);
                $ss['encounterFinished'] = true;
                $results[] = 'Encounter updated: finished';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Encounter gagal: ' . $e->getMessage();
            Log::error('SatuSehat Encounter error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 2: CONDITION — Diagnosa ICD-10
    // ══════════════════════════════════════════════════════════
    private function stepDiagnosa(array $ss, array $dataRJ, string $patientId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $diagnosaList = $dataRJ['diagnpinaList'] ?? [];
            if (empty($diagnosaList) && !empty($dataRJ['diagnosaPinaUtama']['kodeIcdx'])) {
                $diagnosaList = [$dataRJ['diagnosaPinaUtama']];
            }

            if (!empty($diagnosaList) && empty($ss['conditionIds'])) {
                $ss['conditionIds'] = [];
                foreach ($diagnosaList as $diag) {
                    $icdCode = $diag['kodeIcdx'] ?? ($diag['icdx'] ?? '');
                    $icdDisplay = $diag['descIcdx'] ?? ($diag['icdxDesc'] ?? '');
                    if (empty($icdCode)) continue;

                    $condRes = $this->createFinalDiagnosis([
                        'patientId'     => $patientId,
                        'encounterId'   => $ss['encounterId'],
                        'icd10_code'    => $icdCode,
                        'icd10_display' => $icdDisplay,
                        'diagnosis_text' => "{$icdCode} - {$icdDisplay}",
                        'recordedDate'  => $rjDate->toIso8601String(),
                    ]);
                    if (!empty($condRes['id'])) {
                        $ss['conditionIds'][] = $condRes['id'];
                        $results[] = "Condition (Diagnosa) created: {$icdCode}";
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Condition (Diagnosa) gagal: ' . $e->getMessage();
            Log::error('SatuSehat Condition error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 3: OBSERVATION — Tanda Vital
    // ══════════════════════════════════════════════════════════
    private function stepTandaVital(array $ss, array $dataRJ, string $patientId, string $practitionerId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $pf = $dataRJ['pemeriksaanFisik'] ?? ($dataRJ['tandaVital'] ?? []);
            if (empty($pf) || !empty($ss['observationIds'])) return $ss;

            $ss['observationIds'] = [];
            $isoDate = $rjDate->toIso8601String();

            $vitals = [
                ['sistole' => $pf['sistole'] ?? null, 'diastole' => $pf['diastole'] ?? null],
                ['key' => 'nadi',  'val' => $pf['nadi'] ?? null,  'loinc' => '8867-4', 'display' => 'Heart rate',         'unit' => 'beats/minute', 'ucum' => '/min'],
                ['key' => 'suhu',  'val' => $pf['suhu'] ?? null,  'loinc' => '8310-5', 'display' => 'Body temperature',   'unit' => 'C',            'ucum' => 'Cel'],
                ['key' => 'rr',    'val' => $pf['rr'] ?? ($pf['respirasi'] ?? null), 'loinc' => '9279-1', 'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'ucum' => '/min'],
            ];

            // TD (component)
            if (!empty($vitals[0]['sistole']) && !empty($vitals[0]['diastole'])) {
                $res = $this->createObservation([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate,
                    'code' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
                    'components' => [
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]], 'valueQuantity' => ['value' => (float) $vitals[0]['sistole'], 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]], 'valueQuantity' => ['value' => (float) $vitals[0]['diastole'], 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                    ],
                ]);
                if (!empty($res['id'])) { $ss['observationIds'][] = $res['id']; $results[] = "Observation (TD) created"; }
            }

            // Single vitals
            foreach (array_slice($vitals, 1) as $v) {
                if (empty($v['val'])) continue;
                $res = $this->createObservation([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate,
                    'code' => ['system' => 'http://loinc.org', 'code' => $v['loinc'], 'display' => $v['display']],
                    'valueQuantity' => ['value' => (float) $v['val'], 'unit' => $v['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $v['ucum']],
                ]);
                if (!empty($res['id'])) { $ss['observationIds'][] = $res['id']; $results[] = "Observation ({$v['key']}) created"; }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Observation (Tanda Vital) gagal: ' . $e->getMessage();
            Log::error('SatuSehat Observation error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 4: PROCEDURE — Tindakan ICD-9
    // ══════════════════════════════════════════════════════════
    private function stepTindakan(array $ss, array $dataRJ, string $patientId, string $practitionerId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $tindakanList = $dataRJ['tindakanList'] ?? ($dataRJ['tindakan'] ?? []);
            if (empty($tindakanList) || !empty($ss['procedureIds'])) return $ss;

            $ss['procedureIds'] = [];
            foreach ($tindakanList as $t) {
                $code = $t['kodeIcd9'] ?? ($t['icd9'] ?? '');
                $display = $t['descIcd9'] ?? ($t['icd9Desc'] ?? '');
                if (empty($code)) continue;

                $res = $this->createProcedure([
                    'patientId'   => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId,
                    'code' => $code, 'display' => $display, 'codeSystem' => 'http://hl7.org/fhir/sid/icd-9-cm',
                    'performedDateTime' => $rjDate->toIso8601String(),
                ]);
                if (!empty($res['id'])) { $ss['procedureIds'][] = $res['id']; $results[] = "Procedure created: {$code}"; }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Procedure gagal: ' . $e->getMessage();
            Log::error('SatuSehat Procedure error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 5: CONDITION — Keluhan Utama (SNOMED)
    // ══════════════════════════════════════════════════════════
    private function stepKeluhanUtama(array $ss, array $dataRJ, string $patientId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $keluhan = $dataRJ['keluhanUtama'] ?? ($dataRJ['anamnpinaSubjektif'] ?? '');
            $snomedCode = $dataRJ['keluhanUtamaSnomedCode'] ?? '';

            if (empty($keluhan) || empty($snomedCode) || !empty($ss['chiefComplaintId'])) return $ss;

            $res = $this->createChiefComplaint([
                'patientId'      => $patientId,
                'encounterId'    => $ss['encounterId'],
                'snomed_code'    => $snomedCode,
                'snomed_display' => $dataRJ['keluhanUtamaSnomedDisplay'] ?? '',
                'complaint_text' => $keluhan,
                'recordedDate'   => $rjDate->toIso8601String(),
            ]);
            if (!empty($res['id'])) {
                $ss['chiefComplaintId'] = $res['id'];
                $results[] = 'Condition (Keluhan Utama) created';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Condition (Keluhan Utama) gagal: ' . $e->getMessage();
            Log::error('SatuSehat ChiefComplaint error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 6: ALLERGY INTOLERANCE
    // ══════════════════════════════════════════════════════════
    private function stepAlergi(array $ss, array $dataRJ, array $pasien, string $patientId, string $practitionerId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $alergiList = $pasien['riwayatAlergi'] ?? ($dataRJ['riwayatAlergi'] ?? []);
            if (empty($alergiList) || !empty($ss['allergyIds'])) return $ss;

            $ss['allergyIds'] = [];
            foreach ($alergiList as $alergi) {
                $snomedCode = $alergi['snomedCode'] ?? '';
                $display = $alergi['namaAlergi'] ?? ($alergi['display'] ?? '');
                if (empty($snomedCode) || empty($display)) continue;

                $res = $this->createAllergyIntolerance([
                    'patientId'   => $patientId,
                    'encounterId' => $ss['encounterId'],
                    'recorderId'  => $practitionerId,
                    'code'        => $snomedCode,
                    'display'     => $display,
                    'category'    => $alergi['category'] ?? 'medication',
                    'criticality' => $alergi['criticality'] ?? 'low',
                    'onset'       => $rjDate->toIso8601String(),
                    'note'        => $alergi['catatan'] ?? '',
                ]);
                if (!empty($res['id'])) { $ss['allergyIds'][] = $res['id']; $results[] = "AllergyIntolerance created: {$display}"; }
            }
        } catch (\Throwable $e) {
            $errors[] = 'AllergyIntolerance gagal: ' . $e->getMessage();
            Log::error('SatuSehat Allergy error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 7: MEDICATION REQUEST — Resep Obat
    // ══════════════════════════════════════════════════════════
    private function stepMedicationRequest(array $ss, array $dataRJ, string $patientId, string $patientName, string $practitionerId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $resepList = $dataRJ['eresep'] ?? ($dataRJ['resepObat'] ?? []);
            if (empty($resepList) || !empty($ss['medicationRequestIds'])) return $ss;

            $ss['medicationRequestIds'] = [];
            $orgId = $this->organizationId;
            $rjNo = $dataRJ['rjNo'] ?? '';
            $drDesc = $dataRJ['drDesc'] ?? '';

            foreach ($resepList as $idx => $obat) {
                $kfaCode = $obat['kfaCode'] ?? ($obat['product_id_satusehat'] ?? '');
                $kfaDisplay = $obat['kfaDisplay'] ?? ($obat['product_name_satusehat'] ?? ($obat['namaObat'] ?? ''));
                if (empty($kfaCode)) continue;

                $itemId = "{$rjNo}-" . ($idx + 1);

                $res = $this->createMedicationRequest([
                    'registrationId'        => $kfaCode,
                    'orgId'                 => $orgId,
                    'medContainedId'        => "med-{$itemId}",
                    'medicationCode'        => $kfaCode,
                    'medicationDisplay'     => $kfaDisplay,
                    'medicationFormCode'    => $obat['formCode'] ?? 'BS066',
                    'medicationFormDisplay' => $obat['formDisplay'] ?? 'Tablet',
                    'medicationTypeCode'    => $obat['isCompound'] ?? false ? 'SD' : 'NC',
                    'medicationTypeDisplay' => $obat['isCompound'] ?? false ? 'Compound' : 'Non-compound',
                    'prescriptionId'        => $rjNo,
                    'patientId'             => $patientId,
                    'patientName'           => $patientName,
                    'encounterId'           => $ss['encounterId'],
                    'requesterId'           => $practitionerId,
                    'requesterName'         => $drDesc,
                    'authoredOn'            => $rjDate->toIso8601String(),
                    'category'              => 'outpatient',
                    'dosageInstruction'     => $obat['dosageInstruction'] ?? [],
                    'dispenseRequest'       => $obat['dispenseRequest'] ?? [],
                    'reasonReference'       => [],
                ]);
                if (!empty($res['id'])) { $ss['medicationRequestIds'][] = $res['id']; $results[] = "MedicationRequest created: {$kfaDisplay}"; }
            }
        } catch (\Throwable $e) {
            $errors[] = 'MedicationRequest gagal: ' . $e->getMessage();
            Log::error('SatuSehat MedRequest error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 8: MEDICATION DISPENSE — Penyerahan Obat
    // ══════════════════════════════════════════════════════════
    private function stepMedicationDispense(array $ss, array $dataRJ, string $patientId, string $patientName, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $dispenseList = $dataRJ['dispenseObat'] ?? [];
            if (empty($dispenseList) || !empty($ss['medicationDispenseIds'])) return $ss;

            $ss['medicationDispenseIds'] = [];
            $orgId = $this->organizationId;
            $rjNo = $dataRJ['rjNo'] ?? '';
            $medReqIds = $ss['medicationRequestIds'] ?? [];

            foreach ($dispenseList as $idx => $obat) {
                $kfaCode = $obat['kfaCode'] ?? ($obat['product_id_satusehat'] ?? '');
                $kfaDisplay = $obat['kfaDisplay'] ?? ($obat['product_name_satusehat'] ?? ($obat['namaObat'] ?? ''));
                if (empty($kfaCode)) continue;

                $itemId = "{$rjNo}-" . ($idx + 1);
                $authRef = isset($medReqIds[$idx]) ? ['reference' => "MedicationRequest/{$medReqIds[$idx]}"] : [];

                $res = $this->createMedicationDispense([
                    'registrationId'        => $kfaCode,
                    'prescriptionItemId'    => $itemId,
                    'orgId'                 => $orgId,
                    'medContainedId'        => "med-disp-{$itemId}",
                    'medicationCode'        => $kfaCode,
                    'medicationDisplay'     => $kfaDisplay,
                    'medicationFormCode'    => $obat['formCode'] ?? 'BS066',
                    'medicationFormDisplay' => $obat['formDisplay'] ?? 'Tablet',
                    'medicationTypeCode'    => 'NC',
                    'medicationTypeDisplay' => 'Non-compound',
                    'patientId'             => $patientId,
                    'patientName'           => $patientName,
                    'encounterId'           => $ss['encounterId'],
                    'category'              => 'outpatient',
                    'whenPrepared'          => $rjDate->toIso8601String(),
                    'whenHandedOver'        => $rjDate->toIso8601String(),
                    'performer'             => [['actor' => ['reference' => "Organization/{$orgId}"]]],
                    'dosageInstruction'     => $obat['dosageInstruction'] ?? [],
                    'authorizingPrescription' => $authRef,
                    'quantity'              => $obat['quantity'] ?? ['value' => 1, 'unit' => 'TAB', 'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm', 'code' => 'TAB'],
                    'daysSupply'            => $obat['daysSupply'] ?? ['value' => 1, 'unit' => 'days', 'system' => 'http://unitsofmeasure.org', 'code' => 'd'],
                    'receiver'              => ['reference' => "Patient/{$patientId}"],
                ]);
                if (!empty($res['id'])) { $ss['medicationDispenseIds'][] = $res['id']; $results[] = "MedicationDispense created: {$kfaDisplay}"; }
            }
        } catch (\Throwable $e) {
            $errors[] = 'MedicationDispense gagal: ' . $e->getMessage();
            Log::error('SatuSehat MedDispense error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // STEP 9-11: LAB (ServiceRequest → Observation → DiagnosticReport)
    // ══════════════════════════════════════════════════════════
    private function stepLab(array $ss, array $dataRJ, string $patientId, string $practitionerId, Carbon $rjDate, array &$results, array &$errors): array
    {
        try {
            $labList = $dataRJ['hasilLab'] ?? ($dataRJ['pemeriksaanLab'] ?? []);
            if (empty($labList) || !empty($ss['labIds'])) return $ss;

            $ss['labIds'] = [];
            $orgId = $this->organizationId;
            $isoDate = $rjDate->toIso8601String();

            foreach ($labList as $idx => $lab) {
                $loincCode = $lab['loincCode'] ?? '';
                $loincDisplay = $lab['loincDisplay'] ?? ($lab['namaLab'] ?? '');
                if (empty($loincCode)) continue;

                $labResult = [];

                // 9. ServiceRequest
                $srRes = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => ($dataRJ['rjNo'] ?? '') . "-lab-{$idx}"],
                    'subject'    => "Patient/{$patientId}",
                    'encounter'  => "Encounter/{$ss['encounterId']}",
                    'requester'  => "Practitioner/{$practitionerId}",
                    'requesterDisplay' => $dataRJ['drDesc'] ?? '',
                    'code'       => ['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay],
                    'category'   => ['system' => 'http://snomed.info/sct', 'code' => '108252007', 'display' => 'Laboratory procedure'],
                    'occurrenceDateTime' => $isoDate,
                    'authoredOn' => $isoDate,
                ]);
                $labResult['serviceRequestId'] = $srRes['id'] ?? null;
                if (!empty($labResult['serviceRequestId'])) $results[] = "ServiceRequest (Lab) created: {$loincCode}";

                // 10. Observation — Hasil Lab
                $hasilItems = $lab['hasil'] ?? [];
                $labResult['observationIds'] = [];
                foreach ($hasilItems as $hasil) {
                    $hLoincCode = $hasil['loincCode'] ?? '';
                    $hDisplay = $hasil['loincDisplay'] ?? ($hasil['namaParameter'] ?? '');
                    if (empty($hLoincCode)) continue;

                    $obsData = [
                        'patientId'   => $patientId,
                        'encounterId' => $ss['encounterId'],
                        'performerId' => $practitionerId,
                        'effectiveDate' => $isoDate,
                        'code'     => ['system' => 'http://loinc.org', 'code' => $hLoincCode, 'display' => $hDisplay],
                        'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
                    ];
                    if (isset($hasil['value'])) {
                        $obsData['valueQuantity'] = ['value' => (float) $hasil['value'], 'unit' => $hasil['unit'] ?? '', 'system' => 'http://unitsofmeasure.org', 'code' => $hasil['unitCode'] ?? $hasil['unit'] ?? ''];
                    } elseif (isset($hasil['valueString'])) {
                        $obsData['valueString'] = $hasil['valueString'];
                    }

                    $obsRes = $this->createObservation($obsData);
                    if (!empty($obsRes['id'])) { $labResult['observationIds'][] = $obsRes['id']; $results[] = "Observation (Lab) created: {$hLoincCode}"; }
                }

                // 11. DiagnosticReport
                if (!empty($labResult['observationIds'])) {
                    $drRes = $this->createDiagnosticReport([
                        'patientId'     => $patientId,
                        'encounterId'   => $ss['encounterId'],
                        'code'          => $loincCode,
                        'display'       => $loincDisplay,
                        'categoryCode'  => 'LAB',
                        'categoryDisplay' => 'Laboratory',
                        'effectiveDate' => $isoDate,
                        'issued'        => $isoDate,
                        'performer'     => ["Practitioner/{$practitionerId}", "Organization/{$orgId}"],
                        'observationIds' => $labResult['observationIds'],
                        'basedOn'       => !empty($labResult['serviceRequestId']) ? [$labResult['serviceRequestId']] : [],
                    ]);
                    $labResult['diagnosticReportId'] = $drRes['id'] ?? null;
                    if (!empty($labResult['diagnosticReportId'])) $results[] = "DiagnosticReport (Lab) created: {$loincCode}";
                }

                $ss['labIds'][] = $labResult;
            }
        } catch (\Throwable $e) {
            $errors[] = 'Lab (ServiceRequest/Observation/DiagnosticReport) gagal: ' . $e->getMessage();
            Log::error('SatuSehat Lab error', ['rjNo' => $dataRJ['rjNo'] ?? '', 'error' => $e->getMessage()]);
        }
        return $ss;
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════

    protected function getPractitionerIHS(string $drId): string
    {
        if (empty($drId)) return '';
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
    }

    protected function getLocationIHS(string $poliId): string
    {
        if (empty($poliId)) return '';
        return (string) (DB::table('rsmst_polis')->where('poli_id', $poliId)->value('poli_uuid') ?? '');
    }

    private function parseRjDate(string $rjDateStr): Carbon
    {
        if (empty($rjDateStr)) return Carbon::now();
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $rjDateStr);
        } catch (\Throwable) {
            try { return Carbon::parse($rjDateStr); } catch (\Throwable) { return Carbon::now(); }
        }
    }
}
