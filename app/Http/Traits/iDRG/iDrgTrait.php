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
 * Enkripsi: AES-256-CBC + HMAC-SHA256 signature (symmetric).
 *
 * Pola: tiru VclaimTrait — per-method self-contained (validator 5-step +
 * try-catch + Http::post + panggil response_decrypt). Helper sentral hanya
 * untuk encrypt/decrypt dan response handling.
 *
 * Env yang dipakai:
 *   IDRG_WS_URL    -> URL endpoint ws.php
 *   IDRG_KEY       -> encryption key (hex, 64 chars = 256 bit)
 *   IDRG_DEBUG     -> "true" jika ingin memakai mode=debug (plain JSON)
 */
trait iDrgTrait
{
    // ==============================================================
    // Response helpers (pola sama dengan VclaimTrait)
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
    // Helper URL & response handler (mirror response_decrypt VclaimTrait)
    // ==============================================================

    public static function eklaim_url($debug = false)
    {
        $base = env('IDRG_WS_URL');
        return $debug ? ($base . '?mode=debug') : $base;
    }

    /**
     * Handler universal response E-Klaim — tiru pola response_decrypt VclaimTrait.
     *
     * - Cek $response->failed() → sendError HTTP code.
     * - Mode debug: body langsung JSON.
     * - Mode normal: strip marker BEGIN/END, decrypt, cek SIGNATURE_NOT_MATCH.
     * - Decode JSON; kalau gagal, sertakan preview body untuk diagnosa.
     * - Cek metadata.code; 200 → sendResponse, lainnya → sendError.
     */
    public static function response_decrypt($response, $key, $url, $requestTransferTime, $debug = false)
    {
        if ($response->failed()) {
            return self::sendError(
                'HTTP Error: ' . $response->status(),
                $response->body(),
                $response->status(),
                $url,
                $requestTransferTime
            );
        }

        $raw = $response->body();

        if (!$debug) {
            // Strip marker BEGIN/END pakai regex (catatan sesi 2026-04-22:
            // strpos/strrpos lama bug saat trailing \r\n setelah END).
            $raw = preg_replace('/^[\s\S]*?----BEGIN ENCRYPTED DATA----\s*/', '', $raw);
            $raw = preg_replace('/\s*----END ENCRYPTED DATA----[\s\S]*$/', '', $raw);
            $raw = trim($raw);
            $raw = self::inacbgDecrypt($raw, $key);
            if ($raw === 'SIGNATURE_NOT_MATCH') {
                return self::sendError('Signature tidak cocok pada response E-Klaim', null, 500, $url, $requestTransferTime);
            }
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            // Sertakan preview 500 char pertama supaya bisa dilihat di toast — sering
            // E-Klaim balas plain text (notice PHP, HTML error, dll) saat encryption
            // gagal di sisi server.
            $preview = mb_substr((string) $raw, 0, 500);
            return self::sendError(
                'Response E-Klaim tidak bisa di-decode JSON: ' . $preview,
                $raw,
                500,
                $url,
                $requestTransferTime
            );
        }

        $code = (int) ($decoded['metadata']['code'] ?? 500);
        $message = $decoded['metadata']['message'] ?? 'Unknown';

        // Ambil seluruh body selain metadata (bisa berisi response/data/duplicate/dll).
        $payloadOut = $decoded;
        unset($payloadOut['metadata']);
        $dataOut = \count($payloadOut) === 1 ? reset($payloadOut) : $payloadOut;

        if ($code === 200) {
            return self::sendResponse($message, $dataOut, 200, $url, $requestTransferTime);
        }

        return self::sendError($message, $decoded, $code, $url, $requestTransferTime);
    }

    // ==============================================================
    // 1. Membuat klaim baru (method: new_claim)
    // ==============================================================
    public static function newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)
    {
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus berformat :format.',
            'in' => ':attribute harus salah satu dari: :values.',
        ];
        $attributes = [
            'nomorKartu' => 'Nomor Kartu',
            'nomorSep' => 'Nomor SEP',
            'nomorRm' => 'Nomor RM',
            'namaPasien' => 'Nama Pasien',
            'tglLahir' => 'Tanggal Lahir',
            'gender' => 'Gender',
        ];
        $r = [
            'nomorKartu' => $nomorKartu,
            'nomorSep' => $nomorSep,
            'nomorRm' => $nomorRm,
            'namaPasien' => $namaPasien,
            'tglLahir' => $tglLahir,
            'gender' => $gender,
        ];
        $rules = [
            'nomorKartu' => 'required',
            'nomorSep' => 'required',
            'nomorRm' => 'required',
            'namaPasien' => 'required',
            'tglLahir' => 'required|date_format:Y-m-d H:i:s',
            'gender' => 'required|in:1,2',
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'new_claim'],
                'data' => [
                    'nomor_kartu' => $nomorKartu,
                    'nomor_sep' => $nomorSep,
                    'nomor_rm' => $nomorRm,
                    'nama_pasien' => $namaPasien,
                    'tgl_lahir' => $tglLahir,
                    'gender' => (int) $gender,
                ],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 2. Update data pasien (method: update_patient)
    // ==============================================================
    public static function updatePatient($nomorRm, array $data)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorRm' => 'Nomor RM'];
        $r = ['nomorRm' => $nomorRm];
        $rules = ['nomorRm' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'update_patient', 'nomor_rm' => $nomorRm],
                'data' => $data,
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 3. Hapus data pasien (method: delete_patient)
    // ==============================================================
    public static function deletePatient($nomorRm, $coderNik)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorRm' => 'Nomor RM', 'coderNik' => 'NIK Coder'];
        $r = ['nomorRm' => $nomorRm, 'coderNik' => $coderNik];
        $rules = ['nomorRm' => 'required', 'coderNik' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'delete_patient'],
                'data' => ['nomor_rm' => $nomorRm, 'coder_nik' => $coderNik],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 4. Isi / update data klaim (method: set_claim_data)
    // ==============================================================
    public static function setClaimData($nomorSep, array $data)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $data['nomor_sep'] = $nomorSep;
            $body = json_encode([
                'metadata' => ['method' => 'set_claim_data', 'nomor_sep' => $nomorSep],
                'data' => $data,
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 5. Set Diagnosa iDRG (method: idrg_diagnosa_set)
    // ==============================================================
    public static function setDiagnosaIdrg($nomorSep, $diagnosa)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_diagnosa_set', 'nomor_sep' => $nomorSep],
                'data' => ['diagnosa' => $diagnosa],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 6. Get Diagnosa iDRG (method: idrg_diagnosa_get)
    // ==============================================================
    public static function getDiagnosaIdrg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_diagnosa_get'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 7. Set Prosedur iDRG (method: idrg_procedure_set)
    // ==============================================================
    public static function setProsedurIdrg($nomorSep, $procedure)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_procedure_set', 'nomor_sep' => $nomorSep],
                'data' => ['procedure' => $procedure],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 8. Get Prosedur iDRG (method: inacbg_procedure_get — sesuai manual hal. 27)
    // ==============================================================
    public static function getProsedurIdrg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'inacbg_procedure_get'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 9. Grouping iDRG Stage 1 (method: grouper, grouper=idrg, stage=1)
    // ==============================================================
    public static function grouperIdrgStage1($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'grouper', 'stage' => 1, 'grouper' => 'idrg'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 10. Finalisasi iDRG (method: idrg_grouper_final)
    // ==============================================================
    public static function finalIdrg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_grouper_final'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 11. Re-edit coding iDRG (method: idrg_grouper_reedit)
    // ==============================================================
    public static function reeditIdrg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_grouper_reedit'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 12. Import Coding iDRG ke INACBG (method: idrg_to_inacbg_import)
    // ==============================================================
    public static function importIdrgToInacbg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'idrg_to_inacbg_import'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 13. Set Diagnosa INACBG (method: inacbg_diagnosa_set)
    // ==============================================================
    public static function setDiagnosaInacbg($nomorSep, $diagnosa)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'inacbg_diagnosa_set', 'nomor_sep' => $nomorSep],
                'data' => ['diagnosa' => $diagnosa],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 14. Set Prosedur INACBG (method: inacbg_procedure_set)
    // ==============================================================
    public static function setProsedurInacbg($nomorSep, $procedure)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'inacbg_procedure_set', 'nomor_sep' => $nomorSep],
                'data' => ['procedure' => $procedure],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 15. Grouping INACBG Stage 1 (method: grouper, grouper=inacbg, stage=1)
    // ==============================================================
    public static function grouperInacbgStage1($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'grouper', 'stage' => 1, 'grouper' => 'inacbg'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 16. Grouping INACBG Stage 2 (method: grouper, grouper=inacbg, stage=2)
    //     special_cmg: code hasil stage 1 (multi-code dipisah "#")
    // ==============================================================
    public static function grouperInacbgStage2($nomorSep, $specialCmg = null)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $data = ['nomor_sep' => $nomorSep];
            if (!empty($specialCmg)) {
                $data['special_cmg'] = \is_array($specialCmg) ? implode('#', $specialCmg) : $specialCmg;
            }

            $body = json_encode([
                'metadata' => ['method' => 'grouper', 'stage' => 2, 'grouper' => 'inacbg'],
                'data' => $data,
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 17. Finalisasi INACBG (method: inacbg_grouper_final)
    // ==============================================================
    public static function finalInacbg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'inacbg_grouper_final'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 18. Re-edit coding INACBG (method: inacbg_grouper_reedit)
    // ==============================================================
    public static function reeditInacbg($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'inacbg_grouper_reedit'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 19. Finalisasi Klaim (method: claim_final)
    // ==============================================================
    public static function finalClaim($nomorSep, $coderNik)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP', 'coderNik' => 'NIK Coder'];
        $r = ['nomorSep' => $nomorSep, 'coderNik' => $coderNik];
        $rules = ['nomorSep' => 'required', 'coderNik' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'claim_final'],
                'data' => ['nomor_sep' => $nomorSep, 'coder_nik' => $coderNik],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 20. Re-edit Klaim (method: reedit_claim)
    // ==============================================================
    public static function reeditClaim($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'reedit_claim'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 21. Kirim online klaim kolektif per hari (method: send_claim)
    //     jenis_rawat: 1=ranap, 2=rajal, 3=keduanya (default 3)
    //     date_type: 1=tgl pulang, 2=tgl grouping (default 1)
    // ==============================================================
    public static function sendClaim($startDt, $stopDt, $jenisRawat = 3, $dateType = 1)
    {
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus berformat :format.',
        ];
        $attributes = ['startDt' => 'Start Date', 'stopDt' => 'Stop Date'];
        $r = ['startDt' => $startDt, 'stopDt' => $stopDt];
        $rules = [
            'startDt' => 'required|date_format:Y-m-d',
            'stopDt' => 'required|date_format:Y-m-d',
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'send_claim'],
                'data' => [
                    'start_dt' => $startDt,
                    'stop_dt' => $stopDt,
                    'jenis_rawat' => (string) $jenisRawat,
                    'date_type' => (string) $dateType,
                ],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 22. Kirim online klaim individual (method: send_claim_individual)
    // ==============================================================
    public static function sendClaimIndividual($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'send_claim_individual'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 23. Get data detail per klaim (method: get_claim_data)
    // ==============================================================
    public static function getClaimData($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'get_claim_data'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 24. Get status per klaim (method: get_claim_status)
    //     Butuh consumer_id & secret BPJS terpasang di konfigurasi E-Klaim
    // ==============================================================
    public static function getClaimStatus($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'get_claim_status'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 25. Hapus klaim (method: delete_claim)
    // ==============================================================
    public static function deleteClaim($nomorSep, $coderNik)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP', 'coderNik' => 'NIK Coder'];
        $r = ['nomorSep' => $nomorSep, 'coderNik' => $coderNik];
        $rules = ['nomorSep' => 'required', 'coderNik' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'delete_claim'],
                'data' => ['nomor_sep' => $nomorSep, 'coder_nik' => $coderNik],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 26. Cetak klaim (method: claim_print)
    //     Response: base64-encoded PDF. Decode via base64_decode(data).
    // ==============================================================
    public static function printClaim($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'claim_print'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 27. Pencarian diagnosa iDRG (method: search_diagnosis_inagrouper)
    // ==============================================================
    public static function searchDiagnosaIdrg($keyword)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['keyword' => 'Keyword'];
        $r = ['keyword' => $keyword];
        $rules = ['keyword' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'search_diagnosis_inagrouper'],
                'data' => ['keyword' => $keyword],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 28. Pencarian prosedur iDRG (method: search_procedures_inagrouper)
    // ==============================================================
    public static function searchProsedurIdrg($keyword)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['keyword' => 'Keyword'];
        $r = ['keyword' => $keyword];
        $rules = ['keyword' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'search_procedures_inagrouper'],
                'data' => ['keyword' => $keyword],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 29. Pencarian diagnosa INACBG (method: search_diagnosis)
    // ==============================================================
    public static function searchDiagnosaInacbg($keyword)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['keyword' => 'Keyword'];
        $r = ['keyword' => $keyword];
        $rules = ['keyword' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'search_diagnosis'],
                'data' => ['keyword' => $keyword],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 30. Pencarian prosedur INACBG (method: search_procedures)
    // ==============================================================
    public static function searchProsedurInacbg($keyword)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['keyword' => 'Keyword'];
        $r = ['keyword' => $keyword];
        $rules = ['keyword' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'search_procedures'],
                'data' => ['keyword' => $keyword],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 31. Generate nomor pengajuan klaim (method: generate_claim_number)
    //     Dipakai sebelum new_claim untuk jenis pasien khusus
    //     (COVID-19, KIPI, Bayi Baru Lahir, Co-Insidense).
    // ==============================================================
    public static function generateClaimNumber()
    {
        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'generate_claim_number'],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), null, 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 32. Validasi Nomor Register SITB (method: sitb_validate)
    // ==============================================================
    public static function validateSitb($nomorSep, $nomorRegisterSitb)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP', 'nomorRegisterSitb' => 'Nomor Register SITB'];
        $r = ['nomorSep' => $nomorSep, 'nomorRegisterSitb' => $nomorRegisterSitb];
        $rules = ['nomorSep' => 'required', 'nomorRegisterSitb' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'sitb_validate'],
                'data' => ['nomor_sep' => $nomorSep, 'nomor_register_sitb' => $nomorRegisterSitb],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // 33. Batalkan validasi Nomor Register SITB (method: sitb_invalidate)
    // ==============================================================
    public static function invalidateSitb($nomorSep)
    {
        $messages = ['required' => ':attribute wajib diisi.'];
        $attributes = ['nomorSep' => 'Nomor SEP'];
        $r = ['nomorSep' => $nomorSep];
        $rules = ['nomorSep' => 'required'];

        $validator = Validator::make($r, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $debug = filter_var(env('IDRG_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            $key = env('IDRG_KEY');
            $url = self::eklaim_url($debug);

            $body = json_encode([
                'metadata' => ['method' => 'sitb_invalidate'],
                'data' => ['nomor_sep' => $nomorSep],
            ]);
            $payload = $debug ? $body : self::inacbgEncrypt($body, $key);

            $response = Http::timeout(30)
                ->withBody($payload, 'application/x-www-form-urlencoded')
                ->post($url);

            return self::response_decrypt($response, $key, $url, $response->transferStats?->getTransferTime(), $debug);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url ?? null, null);
        }
    }

    // ==============================================================
    // Error code mapping (Manual WS E-Klaim 5.10.x, hal. 55-57)
    // Sumber: Daftar Kode Error E2001-E2099.
    // ==============================================================
    private const EKLAIM_ERROR_MAP = [
        'E2001' => 'Method tidak ada',
        'E2002' => 'Klaim belum final',
        'E2003' => 'Nomor SEP terduplikasi',
        'E2004' => 'Nomor SEP tidak ditemukan',
        'E2005' => 'NIK Coder masih kosong',
        'E2006' => 'NIK Coder tidak ditemukan',
        'E2007' => 'Duplikasi nomor SEP',
        'E2008' => 'Nomor RM tidak ditemukan',
        'E2009' => 'Klaim sudah final',
        'E2010' => 'Nomor SEP baru sudah terpakai',
        'E2011' => 'Klaim tidak bisa diubah/edit',
        'E2012' => 'Tanggal Pulang mendahului Tanggal Masuk',
        'E2013' => 'Lama rawat intensif melebihi total lama rawat',
        'E2014' => 'Kode tarif invalid',
        'E2015' => 'Kode RS belum disetup',
        'E2016' => 'CBG Code invalid, tidak bisa final',
        'E2017' => 'Klaim belum di-grouping',
        'E2018' => 'Klaim masih belum final',
        'E2019' => 'Tanggal invalid',
        'E2020' => 'Response web service SEP kosong',
        'E2021' => 'Gagal men-decode JSON — Maximum stack depth exceeded',
        'E2022' => 'Gagal men-decode JSON — Underflow or the modes mismatch',
        'E2023' => 'Gagal men-decode JSON — Unexpected control character found',
        'E2024' => 'Gagal men-decode JSON — Syntax error, malformed JSON',
        'E2025' => 'Gagal men-decode JSON — Malformed UTF-8 characters',
        'E2026' => 'Gagal men-decode JSON — Unknown error',
        'E2027' => 'Rumah sakit belum terdaftar',
        'E2028' => 'Jenis rawat invalid',
        'E2029' => 'Koneksi gagal',
        'E2030' => 'Parameter tidak lengkap',
        'E2031' => 'Key Mismatch',
        'E2032' => 'Parameter kenaikan kelas tersebut tidak diperbolehkan',
        'E2033' => 'Parameter payor_id tidak boleh kosong',
        'E2034' => 'Nomor klaim tidak ditemukan',
        'E2035' => 'Lama hari episode ruang rawat tidak sama dengan total lama rawat',
        'E2036' => 'Tipe file tidak diterima',
        'E2037' => 'Gagal upload',
        'E2038' => 'Gagal hapus, klaim sudah diproses',
        'E2039' => 'Gagal edit ulang, klaim sudah dikirim',
        'E2040' => 'Gagal final. Belum ada berkas yang diunggah.',
        'E2041' => 'Gagal final. Ada berkas yang masih gagal diunggah.',
        'E2042' => 'Menyatakan covid19_cc_ind = 1 tanpa diagnosa sekunder',
        'E2043' => 'Nomor Klaim sudah terpakai',
        'E2044' => 'Gagal upload. Error ketika memindahkan berkas.',
        'E2045' => 'Gagal upload. Ukuran file melebihi batas maksimal.',
        'E2046' => 'Nilai parameter covid19_status_cd tidak berlaku',
        'E2047' => 'Gagal mendapatkan status klaim',
        'E2048' => 'Tanggal masuk tidak berlaku untuk Jaminan KIPI',
        'E2049' => 'Usia 7 hari ke atas tidak berlaku untuk Jaminan Bayi Baru Lahir',
        'E2050' => 'Tanggal masuk tidak berlaku untuk Jaminan Perpanjangan Masa Rawat / Co-Insidense',
        'E2051' => 'Parameter payor_id kosong atau invalid',
        'E2052' => 'Parameter nomor_kartu_t invalid',
        'E2053' => 'Nomor klaim ibu invalid',
        'E2054' => 'Parameter bayi_lahir_status_cd invalid',
        'E2055' => 'Kode jenis ruangan pada parameter episodes invalid',
        'E2056' => 'Parameter akses_naat invalid',
        'E2057' => 'Nilai terapi_konvalesen pada non ranap atau non terkonfirmasi COVID-19 tidak berlaku',
        'E2058' => 'Parameter file_class invalid',
        'E2059' => 'Parameter covid19_no_sep invalid',
        'E2060' => 'Diagnosa Primer untuk COVID-19 tidak sesuai ketentuan',
        'E2061' => 'Isolasi mandiri di RS pada rawat IGD',
        'E2062' => 'Lama rawat kelas upgrade lebih lama dari total lama rawat',
        'E2063' => 'Gagal final. Hasil INA Grouper tidak valid.',
        'E2064' => 'upgrade_class_payor masih kosong atau tidak sesuai ketentuan',
        'E2065' => 'Kelas 3 tidak diperkenankan naik kelas',
        'E2066' => 'Gagal final. Pasien dengan TB belum ada validasi SITB.',
        'E2099' => 'Error tidak diketahui',
    ];

    /**
     * Mapping kode Ungroupable/Unrelated (Manual WS E-Klaim 5.10.x, hal. 58-59).
     * Pola 'x' di tengah kode = digit bebas (e.g. 36000x9 cocok 36000[0-9]9).
     * Value = [deskripsi, saran kode yang harus ditambahkan (opsional)]
     */
    private const EKLAIM_UNGROUPABLE_MAP = [
        '3611199'  => ['Jenis kelamin pasien tidak sesuai dengan diagnosis primer.', 'Cek ulang gender pasien atau ganti diagnosis.'],
        '3611299'  => ['Usia pasien tidak sesuai dengan diagnosis.', 'Cek ulang tanggal lahir atau ganti diagnosis (mis. kasus perinatologi ≤28 hari).'],
        '36000x9'  => ['Prosedur tidak sesuai dengan diagnosis (Unrelated OR Procedure).', 'Periksa kesesuaian antara diagnosis dan tindakan.'],
        '3635929'  => ['Butuh kode konsultasi rehabilitasi medis.', 'Tambahkan kode 89.01–89.09 (variasi konsultasi rehabilitasi).'],
        '3612011'  => ['Butuh kode prosedur pemasangan lensa.', 'Tambahkan kode 13.70–13.72 (Insertion of pseudophakos).'],
        '3612031'  => ['Butuh kode prosedur pemasangan lensa.', 'Tambahkan kode 13.70–13.72 (Insertion of pseudophakos).'],
        '3614129'  => ['TB resisten obat tanpa jenis obat.', 'Tambahkan kode resistensi obat U82.20–U85.0.'],
        '3614229'  => ['TB resisten obat tanpa jenis episode.', 'Tambahkan kode episode TB U84.31–U84.35.'],
        '3615029'  => ['Butuh kode prosedur angiokardiografi.', 'Tambahkan kode 88.51–88.58.'],
        '3615129'  => ['Butuh kode prosedur kateterisasi.', 'Tambahkan kode 37.21–37.23.'],
        '3615229'  => ['Pemasangan stent tanpa jumlah stent/pembuluh.', 'Tambahkan kode 00.45–00.48 (jumlah stent/vessel).'],
        '3615329'  => ['Jumlah stent tanpa prosedur pemasangan.', 'Tambahkan kode 00.55 atau 39.90 (Stent insertion).'],
        '3615429'  => ['Angioplasty tanpa jumlah pembuluh.', 'Tambahkan kode 00.40–00.43.'],
        '3615529'  => ['Butuh kode tambahan defibrillator.', 'Tambahkan kode 00.56 atau 00.57 (Defibrillator implantation).'],
        '36001x9'  => ['Sesi radioterapi tanpa prosedur radioterapi.', 'Tambahkan kode radioterapi 92.20–92.27.'],
        '36002x9'  => ['Radioterapi tanpa diagnosis Z51.0.', 'Tambahkan diagnosis Z51.0 (Radiotherapy session).'],
        '36003x9'  => ['Kemoterapi tanpa diagnosis Z51.1.', 'Tambahkan diagnosis Z51.1 (Chemotherapy session).'],
        '3635129'  => ['Rehabilitasi tanpa prosedur rehabilitasi.', 'Tambahkan kode tindakan rehabilitasi medis yang spesifik.'],
    ];

    /**
     * Format pesan error E-Klaim: deteksi kode E20xx di raw message + lookup
     * ke EKLAIM_ERROR_MAP. Kalau kode tidak dikenali, return raw message.
     */
    public static function describeEklaimError(array $metadata, string $context = ''): string
    {
        $raw = (string) ($metadata['message'] ?? '-');
        $prefix = $context !== '' ? "{$context} gagal — " : '';

        if (preg_match('/\b(E20\d{2}|E2099)\b/', $raw, $m)) {
            $code = $m[1];
            $desc = self::EKLAIM_ERROR_MAP[$code] ?? null;
            if ($desc) {
                return "{$prefix}[{$code}] {$desc}";
            }
        }

        return "{$prefix}{$raw}";
    }

    /**
     * Format penjelasan ungroupable/unrelated berdasarkan kode di grouper response.
     */
    public static function describeUngroupable(array $groupResult): string
    {
        $candidates = array_filter([
            $groupResult['error_code'] ?? null,
            $groupResult['description'] ?? null,
            $groupResult['drg_code'] ?? null,
            $groupResult['message'] ?? null,
        ], fn($v) => \is_string($v) && $v !== '');

        foreach (self::EKLAIM_UNGROUPABLE_MAP as $pattern => [$desc, $saran]) {
            $regex = '/' . str_replace('x', '[0-9]', preg_quote($pattern, '/')) . '/i';
            foreach ($candidates as $text) {
                if (preg_match($regex, $text)) {
                    return "Tidak bisa dikelompokkan: {$desc} {$saran}";
                }
            }
        }

        return 'Tidak bisa dikelompokkan (ungroupable/unrelated). Penyebab umum: '
            . 'jenis kelamin tidak sesuai, usia tidak sesuai, kaidah pengodean kurang lengkap '
            . '(mis. pemasangan stent tanpa jumlah pembuluh), atau tindakan rehabilitasi medis '
            . 'tanpa kode Z50.-';
    }
}
