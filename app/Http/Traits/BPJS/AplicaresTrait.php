<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;


use Exception;

trait AplicaresTrait
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
        $code = $code == 1 ? 200 : 201;

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


    // API APLICARES
    public static function signature()
    {
        $cons_id =  env('APLICARES_CONS_ID');
        $secretKey = env('APLICARES_SECRET_KEY');
        $userkey = env('APLICARES_USER_KEY');

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
            $code = $response->json('metadata.code'); //code 200 -201 500 dll

            if ($code == 200) {
                $decrypt = self::stringDecrypt($signature['decrypt_key'], $response->json('response'));
                $data = json_decode($decrypt, true);
            } else {

                $data = json_decode($response, true);
            }

            return self::sendResponse($response->json('metadata.message'), $data, $code, $url, $requestTransferTime);
        }
    }
    public static function response_no_decrypt($response)
    {

        if ($response->failed()) {
            return self::sendError($response->reason(),  $response->json('response'), $response->status(), null, null);
        } else {
            return self::sendResponse($response->json('metadata.message'), $response->json('response'), $response->json('metadata.code'), null, null);
        }
    }


    // ---------------------------------------------------------
    // Referensi Kamar
    // GET /rest/ref/kelas
    // ---------------------------------------------------------
    public function referensiKamar()
    {
        try {
            $url      = env('APLICARES_URL') . "rest/ref/kelas";
            $signature = self::signature();
            $response  = Http::timeout(10)->withHeaders($signature)->get($url);

            return self::response_no_decrypt($response);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), [], 408, $url, null);
        }
    }

    // ---------------------------------------------------------
    // Ketersediaan Kamar RS
    // GET /rest/bed/read/{kodeppk}/{start}/{limit}
    // ---------------------------------------------------------
    public function ketersediaanKamarRS($start = 1, $limit = 10)
    {
        $validator = Validator::make(
            ['start' => $start, 'limit' => $limit],
            ['start' => 'required|integer|min:1', 'limit' => 'required|integer|min:1|max:100'],
            [
                'start.required' => 'Parameter start wajib diisi.',
                'start.integer'  => 'Parameter start harus berupa angka.',
                'start.min'      => 'Parameter start minimal 1.',
                'limit.required' => 'Parameter limit wajib diisi.',
                'limit.integer'  => 'Parameter limit harus berupa angka.',
                'limit.min'      => 'Parameter limit minimal 1.',
                'limit.max'      => 'Parameter limit maksimal 100.',
            ]
        );

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $url      = env('APLICARES_URL') . "rest/bed/read/" . env('APLICARES_PPKRS') . "/{$start}/{$limit}";
            $signature = self::signature();
            $response  = Http::timeout(10)->withHeaders($signature)->get($url);

            return self::response_no_decrypt($response);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), [], 408, $url, null);
        }
    }

    // ---------------------------------------------------------
    // Ruangan Baru
    // POST /rest/bed/create/{kodeppk}
    // ---------------------------------------------------------
    public function ruanganBaru($ruangBaru)
    {
        $messages = [
            'kodekelas.required'          => 'Kolom kode kelas wajib diisi.',
            'kodekelas.string'            => 'Kolom kode kelas harus berupa teks.',
            'kodekelas.max'               => 'Kolom kode kelas tidak boleh lebih dari :max karakter.',
            'koderuang.required'          => 'Kolom kode ruang wajib diisi.',
            'koderuang.string'            => 'Kolom kode ruang harus berupa teks.',
            'koderuang.max'               => 'Kolom kode ruang tidak boleh lebih dari :max karakter.',
            'namaruang.required'          => 'Kolom nama ruang wajib diisi.',
            'namaruang.string'            => 'Kolom nama ruang harus berupa teks.',
            'namaruang.max'               => 'Kolom nama ruang tidak boleh lebih dari :max karakter.',
            'kapasitas.required'          => 'Kolom kapasitas wajib diisi.',
            'kapasitas.numeric'           => 'Kolom kapasitas harus berupa angka.',
            'kapasitas.min'               => 'Kolom kapasitas minimal adalah :min.',
            'tersedia.required'           => 'Kolom tersedia wajib diisi.',
            'tersedia.numeric'            => 'Kolom tersedia harus berupa angka.',
            'tersedia.min'                => 'Kolom tersedia minimal adalah :min.',
            'tersedia.lte'                => 'Kolom tersedia tidak boleh lebih besar dari kapasitas.',
            'tersediapria.required'       => 'Kolom tersedia pria wajib diisi.',
            'tersediapria.numeric'        => 'Kolom tersedia pria harus berupa angka.',
            'tersediapria.min'            => 'Kolom tersedia pria minimal adalah :min.',
            'tersediawanita.required'     => 'Kolom tersedia wanita wajib diisi.',
            'tersediawanita.numeric'      => 'Kolom tersedia wanita harus berupa angka.',
            'tersediawanita.min'          => 'Kolom tersedia wanita minimal adalah :min.',
            'tersediapriawanita.required' => 'Kolom tersedia pria wanita wajib diisi.',
            'tersediapriawanita.numeric'  => 'Kolom tersedia pria wanita harus berupa angka.',
            'tersediapriawanita.min'      => 'Kolom tersedia pria wanita minimal adalah :min.',
        ];

        $r = [
            'kodekelas'          => $ruangBaru['kodekelas'],
            'koderuang'          => $ruangBaru['koderuang'],
            'namaruang'          => $ruangBaru['namaruang'],
            'kapasitas'          => $ruangBaru['kapasitas'],
            'tersedia'           => $ruangBaru['tersedia'],
            'tersediapria'       => $ruangBaru['tersediapria'],
            'tersediawanita'     => $ruangBaru['tersediawanita'],
            'tersediapriawanita' => $ruangBaru['tersediapriawanita'],
        ];

        $rules = [
            'kodekelas'          => 'required|string|max:10',
            'koderuang'          => 'required|string|max:10',
            'namaruang'          => 'required|string|max:100',
            'kapasitas'          => 'required|numeric|min:1',
            'tersedia'           => 'required|numeric|min:0|lte:kapasitas',
            'tersediapria'       => 'required|numeric|min:0',
            'tersediawanita'     => 'required|numeric|min:0',
            'tersediapriawanita' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($r, $rules, $messages);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $url      = env('APLICARES_URL') . "rest/bed/create/" . env('APLICARES_PPKRS');
            $signature = self::signature();
            $response  = Http::timeout(10)
                ->withHeaders($signature)
                ->send('POST', $url, ['body' => json_encode($r)]);

            return self::response_no_decrypt($response);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // ---------------------------------------------------------
    // Hapus Ruangan
    // POST /rest/bed/delete/{kodeppk}
    // ---------------------------------------------------------
    public function hapusRuangan($kodekelas, $koderuang)
    {
        $messages = [
            'kodekelas.required' => 'Kolom kodekelas wajib diisi.',
            'kodekelas.string'   => 'Kolom kodekelas harus berupa teks.',
            'kodekelas.max'      => 'Kolom kodekelas tidak boleh lebih dari :max karakter.',
            'koderuang.required' => 'Kolom koderuang wajib diisi.',
            'koderuang.string'   => 'Kolom koderuang harus berupa teks.',
            'koderuang.max'      => 'Kolom koderuang tidak boleh lebih dari :max karakter.',
        ];

        $r = [
            'kodekelas' => $kodekelas,
            'koderuang' => $koderuang,
        ];

        $rules = [
            'kodekelas' => 'required|string|max:255',
            'koderuang' => 'required|string|max:255',
        ];

        $validator = Validator::make($r, $rules, $messages);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $url      = env('APLICARES_URL') . "rest/bed/delete/" . env('APLICARES_PPKRS');
            $signature = self::signature();
            $response  = Http::timeout(10)
                ->withHeaders($signature)
                ->send('POST', $url, ['body' => json_encode($r)]);

            return self::response_no_decrypt($response);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    // ---------------------------------------------------------
    // Update Ketersediaan Tempat Tidur
    // POST /rest/bed/update/{kodeppk}
    // ---------------------------------------------------------
    public function updateKetersediaanTempatTidur($updateKetersediaanTempatTidur)
    {
        $messages = [
            'kodekelas.required'          => 'Kolom kode kelas wajib diisi.',
            'kodekelas.string'            => 'Kolom kode kelas harus berupa teks.',
            'kodekelas.max'               => 'Kolom kode kelas tidak boleh lebih dari :max karakter.',
            'koderuang.required'          => 'Kolom kode ruang wajib diisi.',
            'koderuang.string'            => 'Kolom kode ruang harus berupa teks.',
            'koderuang.max'               => 'Kolom kode ruang tidak boleh lebih dari :max karakter.',
            'namaruang.required'          => 'Kolom nama ruang wajib diisi.',
            'namaruang.string'            => 'Kolom nama ruang harus berupa teks.',
            'namaruang.max'               => 'Kolom nama ruang tidak boleh lebih dari :max karakter.',
            'kapasitas.required'          => 'Kolom kapasitas wajib diisi.',
            'kapasitas.numeric'           => 'Kolom kapasitas harus berupa angka.',
            'kapasitas.min'               => 'Kolom kapasitas minimal adalah :min.',
            'tersedia.required'           => 'Kolom tersedia wajib diisi.',
            'tersedia.numeric'            => 'Kolom tersedia harus berupa angka.',
            'tersedia.min'                => 'Kolom tersedia minimal adalah :min.',
            'tersedia.lte'                => 'Kolom tersedia tidak boleh lebih besar dari kapasitas.',
            'tersediapria.required'       => 'Kolom tersedia pria wajib diisi.',
            'tersediapria.numeric'        => 'Kolom tersedia pria harus berupa angka.',
            'tersediapria.min'            => 'Kolom tersedia pria minimal adalah :min.',
            'tersediawanita.required'     => 'Kolom tersedia wanita wajib diisi.',
            'tersediawanita.numeric'      => 'Kolom tersedia wanita harus berupa angka.',
            'tersediawanita.min'          => 'Kolom tersedia wanita minimal adalah :min.',
            'tersediapriawanita.required' => 'Kolom tersedia pria wanita wajib diisi.',
            'tersediapriawanita.numeric'  => 'Kolom tersedia pria wanita harus berupa angka.',
            'tersediapriawanita.min'      => 'Kolom tersedia pria wanita minimal adalah :min.',
        ];

        $r = [
            'kodekelas'          => $updateKetersediaanTempatTidur['kodekelas'],
            'koderuang'          => $updateKetersediaanTempatTidur['koderuang'],
            'namaruang'          => $updateKetersediaanTempatTidur['namaruang'],
            'kapasitas'          => $updateKetersediaanTempatTidur['kapasitas'],
            'tersedia'           => $updateKetersediaanTempatTidur['tersedia'],
            'tersediapria'       => $updateKetersediaanTempatTidur['tersediapria'],
            'tersediawanita'     => $updateKetersediaanTempatTidur['tersediawanita'],
            'tersediapriawanita' => $updateKetersediaanTempatTidur['tersediapriawanita'],
        ];

        $rules = [
            'kodekelas'          => 'required|string|max:10',
            'koderuang'          => 'required|string|max:10',
            'namaruang'          => 'required|string|max:100',
            'kapasitas'          => 'required|numeric|min:1',
            'tersedia'           => 'required|numeric|min:0|lte:kapasitas',
            'tersediapria'       => 'required|numeric|min:0',
            'tersediawanita'     => 'required|numeric|min:0',
            'tersediapriawanita' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($r, $rules, $messages);
        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        try {
            $url      = env('APLICARES_URL') . "rest/bed/update/" . env('APLICARES_PPKRS');
            $signature = self::signature();
            $response  = Http::timeout(10)
                ->withHeaders($signature)
                ->send('POST', $url, ['body' => json_encode($r)]);

            return self::response_no_decrypt($response);
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }
}
