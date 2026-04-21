<?php

namespace App\Http\Traits\iDRG;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * Trait untuk Web Service E-Klaim Kemenkes (iDRG / INACBG) build 5.10.x.
 * Endpoint: http://alamat_server/E-Klaim/ws.php
 * Enkripsi: AES-256-CBC + HMAC-SHA256 signature (symmetric, sesuai manual WS E-Klaim).
 *
 * Env yang dipakai:
 *   IDRG_WS_URL    -> URL endpoint ws.php
 *   IDRG_KEY       -> encryption key (hex, 64 chars = 256 bit)
 *   IDRG_DEBUG     -> "true" jika ingin memakai mode=debug (plain JSON)
 */
trait iDrgTrait
{
    // ==============================================================
    // Response helpers (pola sama dengan VclaimTrait / AplicaresTrait)
    // ==============================================================

    public static function sendResponse($message, $data, $code = 200, $url = null, $requestTransferTime = null)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => $message,
                'code' => $code,
            ],
        ];

        DB::table('web_log_status')->insert([
            'code' => $code,
            'date_ref' => Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime,
        ]);

        return response()->json($response, $code);
    }

    public static function sendError($error, $errorMessages = [], $code = 404, $url = null, $requestTransferTime = null)
    {
        $response = [
            'metadata' => [
                'message' => $error,
                'code' => $code,
            ],
        ];
        if (!empty($errorMessages)) {
            $response['response'] = $errorMessages;
        }

        DB::table('web_log_status')->insert([
            'code' => $code,
            'date_ref' => Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime,
        ]);

        return response()->json($response, $code);
    }

    // ==============================================================
    // Enkripsi / Dekripsi (sumber: Manual WS E-Klaim 5.10.x hal. 6-8)
    // ==============================================================

    public static function inacbgEncrypt($data, $strKey)
    {
        $key = hex2bin($strKey);
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key! Got " . mb_strlen($key, '8bit') . " bytes");
        }
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = random_bytes($ivSize);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $signature = mb_substr(hash_hmac('sha256', $encrypted, $key, true), 0, 10, '8bit');
        return chunk_split(base64_encode($signature . $iv . $encrypted));
    }

    public static function inacbgDecrypt($str, $strKey)
    {
        $key = hex2bin($strKey);
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception('Needs a 256-bit key!');
        }
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $decoded = base64_decode($str);
        $signature = mb_substr($decoded, 0, 10, '8bit');
        $iv = mb_substr($decoded, 10, $ivSize, '8bit');
        $encrypted = mb_substr($decoded, $ivSize + 10, null, '8bit');
        $calcSignature = mb_substr(hash_hmac('sha256', $encrypted, $key, true), 0, 10, '8bit');
        if (!self::inacbgCompare($signature, $calcSignature)) {
            return 'SIGNATURE_NOT_MATCH';
        }
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function inacbgCompare($a, $b)
    {
        if (\strlen($a) !== \strlen($b)) {
            return false;
        }
        $result = 0;
        for ($i = 0, $len = \strlen($a); $i < $len; $i++) {
            $result |= \ord($a[$i]) ^ \ord($b[$i]);
        }
        return $result === 0;
    }

    // ==============================================================
    // Core request — encrypt → POST → strip marker → decrypt → decode
    // ==============================================================

    public static function eklaimRequest(array $metadata, array $data = [])
    {
        $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $key = env('IDRG_KEY');
        $baseUrl = env('IDRG_WS_URL');
        $url = $debug ? ($baseUrl . '?mode=debug') : $baseUrl;

        $body = ['metadata' => $metadata];
        if (!empty($data)) {
            $body['data'] = $data;
        }
        $jsonRequest = json_encode($body);

        try {
            $payload = $debug ? $jsonRequest : self::inacbgEncrypt($jsonRequest, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            $transfer = $response->transferStats?->getTransferTime();

            if ($response->failed()) {
                return self::sendError(
                    'HTTP Error: ' . $response->status(),
                    $response->body(),
                    $response->status(),
                    $url,
                    $transfer
                );
            }

            $raw = $response->body();

            if (!$debug) {
                // Strip ----BEGIN ENCRYPTED DATA---- / ----END ENCRYPTED DATA----
                $first = strpos($raw, "\n");
                $last = strrpos($raw, "\n");
                if ($first !== false && $last !== false && $last > $first) {
                    $raw = substr($raw, $first + 1, $last - $first - 1);
                }
                $raw = self::inacbgDecrypt($raw, $key);
                if ($raw === 'SIGNATURE_NOT_MATCH') {
                    return self::sendError('Signature tidak cocok pada response E-Klaim', null, 500, $url, $transfer);
                }
            }

            $decoded = json_decode($raw, true);
            if (!\is_array($decoded)) {
                return self::sendError('Response E-Klaim tidak bisa di-decode JSON', $raw, 500, $url, $transfer);
            }

            $code = (int) ($decoded['metadata']['code'] ?? 500);
            $message = $decoded['metadata']['message'] ?? 'Unknown';

            // Ambil seluruh body selain metadata (bisa berisi response/data/duplicate/dll)
            $payloadOut = $decoded;
            unset($payloadOut['metadata']);
            $dataOut = \count($payloadOut) === 1 ? reset($payloadOut) : $payloadOut;

            if ($code === 200) {
                return self::sendResponse($message, $dataOut, 200, $url, $transfer);
            }

            return self::sendError($message, $decoded, $code, $url, $transfer);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), null, 500, $url, null);
        }
    }

    // ==============================================================
    // Validator helper — shortcut agar per-method tetap ringkas
    // ==============================================================

    protected static function validateOrError(array $data, array $rules, array $attributes = [])
    {
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus berformat :format.',
            'in' => ':attribute harus salah satu dari: :values.',
        ];
        $validator = Validator::make($data, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }
        return null;
    }

    // ==============================================================
    // 1. Membuat klaim baru (method: new_claim)
    // ==============================================================
    public static function newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)
    {
        if ($err = self::validateOrError(
            compact('nomorKartu', 'nomorSep', 'nomorRm', 'namaPasien', 'tglLahir', 'gender'),
            [
                'nomorKartu' => 'required',
                'nomorSep' => 'required',
                'nomorRm' => 'required',
                'namaPasien' => 'required',
                'tglLahir' => 'required|date_format:Y-m-d H:i:s',
                'gender' => 'required|in:1,2',
            ],
            [
                'nomorKartu' => 'Nomor Kartu',
                'nomorSep' => 'Nomor SEP',
                'nomorRm' => 'Nomor RM',
                'namaPasien' => 'Nama Pasien',
                'tglLahir' => 'Tanggal Lahir',
                'gender' => 'Gender',
            ]
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'new_claim'],
            [
                'nomor_kartu' => $nomorKartu,
                'nomor_sep' => $nomorSep,
                'nomor_rm' => $nomorRm,
                'nama_pasien' => $namaPasien,
                'tgl_lahir' => $tglLahir,
                'gender' => (int) $gender,
            ]
        );
    }

    // ==============================================================
    // 2. Update data pasien (method: update_patient)
    // ==============================================================
    public static function updatePatient($nomorRm, array $data)
    {
        if ($err = self::validateOrError(
            ['nomorRm' => $nomorRm],
            ['nomorRm' => 'required'],
            ['nomorRm' => 'Nomor RM']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'update_patient', 'nomor_rm' => $nomorRm],
            $data
        );
    }

    // ==============================================================
    // 3. Hapus data pasien (method: delete_patient)
    // ==============================================================
    public static function deletePatient($nomorRm, $coderNik)
    {
        if ($err = self::validateOrError(
            compact('nomorRm', 'coderNik'),
            ['nomorRm' => 'required', 'coderNik' => 'required'],
            ['nomorRm' => 'Nomor RM', 'coderNik' => 'NIK Coder']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'delete_patient'],
            ['nomor_rm' => $nomorRm, 'coder_nik' => $coderNik]
        );
    }

    // ==============================================================
    // 4. Isi / update data klaim (method: set_claim_data)
    // ==============================================================
    public static function setClaimData($nomorSep, array $data)
    {
        if ($err = self::validateOrError(
            ['nomorSep' => $nomorSep],
            ['nomorSep' => 'required'],
            ['nomorSep' => 'Nomor SEP']
        )) return $err;

        $data['nomor_sep'] = $nomorSep;
        return self::eklaimRequest(
            ['method' => 'set_claim_data', 'nomor_sep' => $nomorSep],
            $data
        );
    }

    // ==============================================================
    // 5. Set Diagnosa iDRG (method: idrg_diagnosa_set)
    // ==============================================================
    public static function setDiagnosaIdrg($nomorSep, $diagnosa)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_diagnosa_set', 'nomor_sep' => $nomorSep],
            ['diagnosa' => $diagnosa]
        );
    }

    // ==============================================================
    // 6. Get Diagnosa iDRG (method: idrg_diagnosa_get)
    // ==============================================================
    public static function getDiagnosaIdrg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_diagnosa_get'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 7. Set Prosedur iDRG (method: idrg_procedure_set)
    // ==============================================================
    public static function setProsedurIdrg($nomorSep, $procedure)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_procedure_set', 'nomor_sep' => $nomorSep],
            ['procedure' => $procedure]
        );
    }

    // ==============================================================
    // 8. Get Prosedur iDRG (method: inacbg_procedure_get — sesuai manual hal. 27)
    // ==============================================================
    public static function getProsedurIdrg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'inacbg_procedure_get'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 9. Grouping iDRG Stage 1 (method: grouper, grouper=idrg, stage=1)
    // ==============================================================
    public static function grouperIdrgStage1($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'grouper', 'stage' => 1, 'grouper' => 'idrg'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 10. Finalisasi iDRG (method: idrg_grouper_final)
    // ==============================================================
    public static function finalIdrg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_grouper_final'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 11. Re-edit coding iDRG (method: idrg_grouper_reedit)
    // ==============================================================
    public static function reeditIdrg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_grouper_reedit'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 12. Import Coding iDRG ke INACBG (method: idrg_to_inacbg_import)
    // ==============================================================
    public static function importIdrgToInacbg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'idrg_to_inacbg_import'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 13. Set Diagnosa INACBG (method: inacbg_diagnosa_set)
    // ==============================================================
    public static function setDiagnosaInacbg($nomorSep, $diagnosa)
    {
        return self::eklaimRequest(
            ['method' => 'inacbg_diagnosa_set', 'nomor_sep' => $nomorSep],
            ['diagnosa' => $diagnosa]
        );
    }

    // ==============================================================
    // 14. Set Prosedur INACBG (method: inacbg_procedure_set)
    // ==============================================================
    public static function setProsedurInacbg($nomorSep, $procedure)
    {
        return self::eklaimRequest(
            ['method' => 'inacbg_procedure_set', 'nomor_sep' => $nomorSep],
            ['procedure' => $procedure]
        );
    }

    // ==============================================================
    // 15. Grouping INACBG Stage 1 (method: grouper, grouper=inacbg, stage=1)
    // ==============================================================
    public static function grouperInacbgStage1($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'grouper', 'stage' => 1, 'grouper' => 'inacbg'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 16. Grouping INACBG Stage 2 (method: grouper, grouper=inacbg, stage=2)
    //     special_cmg: code hasil stage 1 (multi-code dipisah "#")
    // ==============================================================
    public static function grouperInacbgStage2($nomorSep, $specialCmg = null)
    {
        $data = ['nomor_sep' => $nomorSep];
        if (!empty($specialCmg)) {
            $data['special_cmg'] = \is_array($specialCmg) ? implode('#', $specialCmg) : $specialCmg;
        }
        return self::eklaimRequest(
            ['method' => 'grouper', 'stage' => 2, 'grouper' => 'inacbg'],
            $data
        );
    }

    // ==============================================================
    // 17. Finalisasi INACBG (method: inacbg_grouper_final)
    // ==============================================================
    public static function finalInacbg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'inacbg_grouper_final'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 18. Re-edit coding INACBG (method: inacbg_grouper_reedit)
    // ==============================================================
    public static function reeditInacbg($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'inacbg_grouper_reedit'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 19. Finalisasi Klaim (method: claim_final)
    // ==============================================================
    public static function finalClaim($nomorSep, $coderNik)
    {
        if ($err = self::validateOrError(
            compact('nomorSep', 'coderNik'),
            ['nomorSep' => 'required', 'coderNik' => 'required'],
            ['nomorSep' => 'Nomor SEP', 'coderNik' => 'NIK Coder']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'claim_final'],
            ['nomor_sep' => $nomorSep, 'coder_nik' => $coderNik]
        );
    }

    // ==============================================================
    // 20. Re-edit Klaim (method: reedit_claim)
    // ==============================================================
    public static function reeditClaim($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'reedit_claim'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 21. Kirim online klaim kolektif per hari (method: send_claim)
    //     jenis_rawat: 1=ranap, 2=rajal, 3=keduanya (default 3)
    //     date_type: 1=tgl pulang, 2=tgl grouping (default 1)
    // ==============================================================
    public static function sendClaim($startDt, $stopDt, $jenisRawat = 3, $dateType = 1)
    {
        if ($err = self::validateOrError(
            compact('startDt', 'stopDt'),
            [
                'startDt' => 'required|date_format:Y-m-d',
                'stopDt' => 'required|date_format:Y-m-d',
            ],
            ['startDt' => 'Start Date', 'stopDt' => 'Stop Date']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'send_claim'],
            [
                'start_dt' => $startDt,
                'stop_dt' => $stopDt,
                'jenis_rawat' => (string) $jenisRawat,
                'date_type' => (string) $dateType,
            ]
        );
    }

    // ==============================================================
    // 22. Kirim online klaim individual (method: send_claim_individual)
    // ==============================================================
    public static function sendClaimIndividual($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'send_claim_individual'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 23. Get data detail per klaim (method: get_claim_data)
    // ==============================================================
    public static function getClaimData($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'get_claim_data'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 24. Get status per klaim (method: get_claim_status)
    //     Butuh consumer_id & secret BPJS terpasang di konfigurasi E-Klaim
    // ==============================================================
    public static function getClaimStatus($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'get_claim_status'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 25. Hapus klaim (method: delete_claim)
    // ==============================================================
    public static function deleteClaim($nomorSep, $coderNik)
    {
        if ($err = self::validateOrError(
            compact('nomorSep', 'coderNik'),
            ['nomorSep' => 'required', 'coderNik' => 'required'],
            ['nomorSep' => 'Nomor SEP', 'coderNik' => 'NIK Coder']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'delete_claim'],
            ['nomor_sep' => $nomorSep, 'coder_nik' => $coderNik]
        );
    }

    // ==============================================================
    // 26. Cetak klaim (method: claim_print)
    //     Response: base64-encoded PDF. Decode via base64_decode(data).
    // ==============================================================
    public static function printClaim($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'claim_print'],
            ['nomor_sep' => $nomorSep]
        );
    }

    // ==============================================================
    // 27. Pencarian diagnosa iDRG (method: search_diagnosis_inagrouper)
    // ==============================================================
    public static function searchDiagnosaIdrg($keyword)
    {
        return self::eklaimRequest(
            ['method' => 'search_diagnosis_inagrouper'],
            ['keyword' => $keyword]
        );
    }

    // ==============================================================
    // 28. Pencarian prosedur iDRG (method: search_procedures_inagrouper)
    // ==============================================================
    public static function searchProsedurIdrg($keyword)
    {
        return self::eklaimRequest(
            ['method' => 'search_procedures_inagrouper'],
            ['keyword' => $keyword]
        );
    }

    // ==============================================================
    // 29. Pencarian diagnosa INACBG (method: search_diagnosis)
    // ==============================================================
    public static function searchDiagnosaInacbg($keyword)
    {
        return self::eklaimRequest(
            ['method' => 'search_diagnosis'],
            ['keyword' => $keyword]
        );
    }

    // ==============================================================
    // 30. Pencarian prosedur INACBG (method: search_procedures)
    // ==============================================================
    public static function searchProsedurInacbg($keyword)
    {
        return self::eklaimRequest(
            ['method' => 'search_procedures'],
            ['keyword' => $keyword]
        );
    }

    // ==============================================================
    // 31. Generate nomor pengajuan klaim (method: generate_claim_number)
    //     Dipakai sebelum new_claim untuk jenis pasien khusus
    //     (COVID-19, KIPI, Bayi Baru Lahir, Co-Insidense).
    // ==============================================================
    public static function generateClaimNumber()
    {
        return self::eklaimRequest(['method' => 'generate_claim_number']);
    }

    // ==============================================================
    // 32. Validasi Nomor Register SITB (method: sitb_validate)
    // ==============================================================
    public static function validateSitb($nomorSep, $nomorRegisterSitb)
    {
        if ($err = self::validateOrError(
            compact('nomorSep', 'nomorRegisterSitb'),
            ['nomorSep' => 'required', 'nomorRegisterSitb' => 'required'],
            ['nomorSep' => 'Nomor SEP', 'nomorRegisterSitb' => 'Nomor Register SITB']
        )) return $err;

        return self::eklaimRequest(
            ['method' => 'sitb_validate'],
            ['nomor_sep' => $nomorSep, 'nomor_register_sitb' => $nomorRegisterSitb]
        );
    }

    // ==============================================================
    // 33. Batalkan validasi Nomor Register SITB (method: sitb_invalidate)
    // ==============================================================
    public static function invalidateSitb($nomorSep)
    {
        return self::eklaimRequest(
            ['method' => 'sitb_invalidate'],
            ['nomor_sep' => $nomorSep]
        );
    }
}
