<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


use Exception;

trait iCareTrait
{
    public static function sendResponse($message, $data, $code = 200, $url, $requestTransferTime)
    {
        $response = [
            'response' => $data,
            'metadata' => [
                'message' => $message,
                'code' => $code,
            ],
        ];

        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }

    public static function sendError($error, $errorMessages = [], $code = 404, $url, $requestTransferTime)
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
        // Insert webLogStatus
        DB::table('web_log_status')->insert([
            'code' =>  $code,
            'date_ref' => Carbon::now(env('APP_TIMEZONE')),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }


    // API VCLAIM
    public static function signature()
    {
        $cons_id =  env('ICARE_CONS_ID');
        $secretKey = env('ICARE_SECRET_KEY');
        $userkey = env('ICARE_USER_KEY');

        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);

        $response = array(
            'user_key' => $userkey,
            'x-cons-id' => $cons_id,
            'x-timestamp' => $tStamp,
            'x-signature' => $encodedSignature,
            'decrypt_key' => $cons_id . $secretKey . $tStamp,
            'Content-Type' => 'application/json'

        );
        return $response;
    }
    public static function stringDecrypt($key, $string)
    {
        $encrypt_method = 'AES-256-CBC';
        $key_hash = hex2bin(hash('sha256', $key));
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
        $output = \LZCompressor\LZString::decompressFromEncodedURIComponent($output);
        return $output;
    }
    public static function response_decrypt($response, $signature, $url, $requestTransferTime)
    {
        if ($response->failed()) {
            return self::sendError($response->reason(),  $response->json('response'), $response->status(), $url, $requestTransferTime);
        } else {
            // Check Response !200           -> metaData D besar
            $code = $response->json('metaData.code'); //code 200 -201 500 dll

            if ($code == 200) {
                $decrypt = self::stringDecrypt($signature['decrypt_key'], $response->json('response'));
                $data = json_decode($decrypt, true);
            } else {

                $data = json_decode($response, true);
            }

            return self::sendResponse($response->json('metaData.message'), $data, $code, $url, $requestTransferTime);
        }
    }
    public static function response_no_decrypt($response)
    {
        if ($response->failed()) {
            return self::sendError($response->reason(),  $response->json('response'), $response->status(), null, null);
        } else {
            return self::sendResponse($response->json('metaData.message'), $response->json('response'), $response->json('metaData.code'), null, null);
        }
    }



    // icare

    public function icare($nomorKartu, $kodeDokter)
    {


        $messages = [
            'param.required'      => 'Nomor kartu BPJS wajib diisi.',
            'param.digits'        => 'Nomor kartu BPJS harus 13 digit.',
            'kodedokter.required' => 'Kode dokter wajib diisi.',
            'kodedokter.numeric'  => 'Kode dokter harus berupa angka.',
        ];

        $attributes = [
            'param'      => 'Nomor Kartu BPJS',
            'kodedokter' => 'Kode Dokter',
        ];



        // Masukkan Nilai dari parameter
        $r = [
            'param' => $nomorKartu,
            'kodedokter' => $kodeDokter,

        ];
        // lakukan validasis
        $validator = Validator::make($r, [
            'param'      => 'required|digits:13',
            'kodedokter' => 'required|numeric',
        ], $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {
            $url = env('ICARE_URL') . "api/rs/validate";
            $signature = self::signature();
            $data = $r;

            $start = microtime(true);
            $response = Http::timeout(10)
                ->withHeaders($signature)
                // ->send('POST', $url, [
                //     'body' => json_encode($data)
                // ]);
                ->send('POST', $url, [
                    'body' => '{
                    "param": "' . $data['param'] . '",
                    "kodedokter": ' . $data['kodedokter'] . '
                }'
                ]);

            $transferTime = microtime(true) - $start;
            return self::response_decrypt($response, $signature, $url, $transferTime);
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }
}
