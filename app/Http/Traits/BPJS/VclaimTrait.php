<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;


use Exception;

trait VclaimTrait
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
            'date_ref' => Carbon::now(),
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
            'date_ref' => Carbon::now(),
            'response' => json_encode($response, true),
            'http_req' => $url,
            'requestTransferTime' => $requestTransferTime
        ]);

        return response()->json($response, $code);
    }

    // API VCLAIM
    public static function signature()
    {
        $cons_id =  env('VCLAIM_CONS_ID');
        $secretKey = env('VCLAIM_SECRET_KEY');
        $userkey = env('VCLAIM_USER_KEY');


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

    // PESERTA
    public static function peserta_nomorkartu($nomorKartu, $tanggal)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'digits' => ':attribute harus terdiri dari :digits digit.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'nomorKartu' => 'Nomor Kartu',
            'tanggal' => 'Tanggal',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'nomorKartu' => $nomorKartu,
            "tanggal" => $tanggal,
        ];

        // 4. Rules validasi
        $rules = [
            "nomorKartu" => "required|digits:13",
            "tanggal" => "required|date",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), null, 400, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "Peserta/nokartu/" . $nomorKartu . "/tglSEP/" . $tanggal;

            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function peserta_nik($nik, $tanggal)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'digits' => ':attribute harus terdiri dari :digits digit.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'nik' => 'NIK',
            'tanggal' => 'Tanggal',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'nik' => $nik,
            'tanggal' => $tanggal,
        ];

        // 4. Rules validasi
        $rules = [
            "nik" => "required|digits:16",
            "tanggal" => "required|date",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "Peserta/nik/" . $nik . "/tglSEP/" . $tanggal;
            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }



    // REFERENSI

    public static function ref_poliklinik($poliklinik)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'poliklinik' => 'Poli Klinik',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'poliklinik' => $poliklinik,
        ];

        // 4. Rules validasi
        $rules = [
            "poliklinik" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "referensi/poli/" . $poliklinik;
            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);

            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }



    // RENCANA KONTROL
    public static function suratkontrol_insert($kontrol)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'request.noSEP' => 'Nomor SEP',
            'request.tglRencanaKontrol' => 'Tanggal Rencana Kontrol',
            'request.poliKontrol' => 'Poli Kontrol',
            'request.kodeDokter' => 'Kode Dokter',
            'request.user' => 'User',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            "request" => [
                "noSEP" => $kontrol['noSEP'],
                "tglRencanaKontrol" => Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->format('Y-m-d'),
                "poliKontrol" => $kontrol['poliKontrolBPJS'],
                "kodeDokter" => $kontrol['drKontrolBPJS'],
                "user" => 'Sirus',
            ]
        ];

        // 4. Rules validasi
        $rules = [
            "request.noSEP" => "required",
            "request.tglRencanaKontrol" => "required|date",
            "request.kodeDokter" => "required",
            "request.poliKontrol" => "required",
            "request.user" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "RencanaKontrol/insert";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $r;

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post($url, $data);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function suratkontrol_update($kontrol)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'request.noSuratKontrol' => 'Nomor Surat Kontrol',
            'request.noSEP' => 'Nomor SEP',
            'request.tglRencanaKontrol' => 'Tanggal Rencana Kontrol',
            'request.poliKontrol' => 'Poli Kontrol',
            'request.kodeDokter' => 'Kode Dokter',
            'request.user' => 'User',
        ];

        // 3. Data yang akan divalidasi (struktur diperbaiki)
        $r = [
            "request" => [
                "noSuratKontrol" => $kontrol['noSKDPBPJS'],      // ← perbaikan: hapus "request."
                "noSEP" => $kontrol['noSEP'],
                "tglRencanaKontrol" => Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->format('Y-m-d'),
                "poliKontrol" => $kontrol['poliKontrolBPJS'],
                "kodeDokter" => $kontrol['drKontrolBPJS'],
                "user" => 'Sirus',
            ]
        ];

        // 4. Rules validasi
        $rules = [
            "request.noSuratKontrol" => "required",
            "request.noSEP" => "required",
            "request.tglRencanaKontrol" => "required|date",
            "request.kodeDokter" => "required",
            "request.poliKontrol" => "required",
            "request.user" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $url = env('VCLAIM_URL') . "RencanaKontrol/Update";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $r;

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post($url, $data);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }



    public static function spri_insert($kontrol)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'request.noKartu' => 'Nomor Kartu',
            'request.tglRencanaKontrol' => 'Tanggal Rencana Kontrol',
            'request.poliKontrol' => 'Poli Kontrol',
            'request.kodeDokter' => 'Kode Dokter',
            'request.user' => 'User',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            "request" => [
                "noKartu" => $kontrol['noKartu'],
                "tglRencanaKontrol" => Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->format('Y-m-d'),
                "poliKontrol" => $kontrol['poliKontrolBPJS'],
                "kodeDokter" => $kontrol['drKontrolBPJS'],
                "user" => 'Sirus',
            ]
        ];

        // 4. Rules validasi
        $rules = [
            "request.noKartu" => "required",
            "request.tglRencanaKontrol" => "required|date",
            "request.kodeDokter" => "required",
            "request.poliKontrol" => "required",
            "request.user" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "RencanaKontrol/InsertSPRI";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $r;

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post($url, $data);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function spri_update($kontrol)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'request.noSPRI' => 'Nomor SPRI',
            'request.noKartu' => 'Nomor Kartu',
            'request.tglRencanaKontrol' => 'Tanggal Rencana Kontrol',
            'request.poliKontrol' => 'Poli Kontrol',
            'request.kodeDokter' => 'Kode Dokter',
            'request.user' => 'User',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            "request" => [
                "noSPRI" => $kontrol['noSPRIBPJS'],
                "noKartu" => $kontrol['noKartu'],
                "tglRencanaKontrol" => Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])->format('Y-m-d'),
                "poliKontrol" => $kontrol['poliKontrolBPJS'],
                "kodeDokter" => $kontrol['drKontrolBPJS'],
                "user" => 'Sirus',
            ]
        ];

        // 4. Rules validasi
        $rules = [
            "request.noSPRI" => "required",
            "request.noKartu" => "required",
            "request.tglRencanaKontrol" => "required|date",
            "request.kodeDokter" => "required",
            "request.poliKontrol" => "required",
            "request.user" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $url = env('VCLAIM_URL') . "RencanaKontrol/UpdateSPRI";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $r;

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->put($url, $r);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function suratkontrol_nomor($noSPRI)
    {
        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'noSuratKontrol' => 'Nomor Surat Kontrol',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'noSuratKontrol' => $noSPRI,
        ];

        // 4. Rules validasi
        $rules = [
            "noSuratKontrol" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        try {

            $url = env('VCLAIM_URL') . "RencanaKontrol/noSuratKontrol/" . $noSPRI;

            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);

            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }




    // RUJUKAN
    public static function rujukan_peserta($nomorKartu) //fktp dari
    {
        // Custom messages manual berdasarkan rules
        $messages = [
            'nomorKartu.required' => 'Nomor Kartu BPJS wajib diisi.',
            'nomorKartu.digits' => 'Nomor Kartu BPJS harus terdiri dari :digits digit angka.',
            'nomorKartu.numeric' => 'Nomor Kartu BPJS hanya boleh berisi angka.',
        ];

        // Atribut untuk memperjelas field (opsional)
        $attributes = [
            'nomorKartu' => 'Nomor Kartu BPJS',
        ];

        // Masukkan Nilai dari parameter
        $data = [
            'nomorKartu' => $nomorKartu,
        ];

        // Rules validasi
        $rules = [
            "nomorKartu" => "required|digits:13",
        ];

        // Lakukan validasi
        $validator = Validator::make($data, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "Rujukan/List/Peserta/" . $nomorKartu;
            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function rujukan_rs_peserta($nomorKartu) //fktl dari
    {
        // 1. Custom error messages spesifik per field
        $messages = [
            'nomorKartu.required' => 'Nomor Kartu wajib diisi.',
            'nomorKartu.digits' => 'Nomor Kartu harus 13 digit angka.',
        ];

        // 2. Attributes (opsional jika sudah spesifik)
        $attributes = [
            'nomorKartu' => 'Nomor Kartu',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            'nomorKartu' => $nomorKartu,
        ];

        // 4. Rules validasi
        $rules = [
            "nomorKartu" => "required|digits:13",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "Rujukan/RS/List/Peserta/" . $nomorKartu;
            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }



    // SEP
    public static function sep_insert($SEPJsonReq)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'noKartu' => 'Nomor Kartu',
            'tglSep' => 'Tanggal SEP',
            'ppkPelayanan' => 'PPK Pelayanan',
            'jnsPelayanan' => 'Jenis Pelayanan',
            'klsRawatHak' => 'Kelas Rawat Hak',
            'asalRujukan' => 'Asal Rujukan',
            'tglRujukan' => 'Tanggal Rujukan',
            'noRujukan' => 'Nomor Rujukan',
            'ppkRujukan' => 'PPK Rujukan',
            'catatan' => 'Catatan',
            'diagAwal' => 'Diagnosa Awal',
            'tujuan' => 'Tujuan Poli',
            'eksekutif' => 'Eksekutif',
            'tujuanKunj' => 'Tujuan Kunjungan',
            'dpjpLayan' => 'DPJP Layan',
            'noTelp' => 'Nomor Telepon',
            'user' => 'User',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            "noKartu" => $SEPJsonReq['request']['t_sep']['noKartu'],
            "tglSep" => $SEPJsonReq['request']['t_sep']['tglSep'],
            "ppkPelayanan" => $SEPJsonReq['request']['t_sep']['ppkPelayanan'],
            "jnsPelayanan" => $SEPJsonReq['request']['t_sep']['jnsPelayanan'],
            "klsRawatHak" => $SEPJsonReq['request']['t_sep']['klsRawat']['klsRawatHak'],
            "asalRujukan" => $SEPJsonReq['request']['t_sep']['rujukan']['asalRujukan'],
            "tglRujukan" => $SEPJsonReq['request']['t_sep']['rujukan']['tglRujukan'],
            "noRujukan" => $SEPJsonReq['request']['t_sep']['rujukan']['noRujukan'],
            "ppkRujukan" => $SEPJsonReq['request']['t_sep']['rujukan']['ppkRujukan'],
            "catatan" => $SEPJsonReq['request']['t_sep']['catatan'],
            "diagAwal" => $SEPJsonReq['request']['t_sep']['diagAwal'],
            "tujuan" => $SEPJsonReq['request']['t_sep']['poli']['tujuan'] ?? '',
            "eksekutif" => $SEPJsonReq['request']['t_sep']['poli']['eksekutif'] ?? '',
            "tujuanKunj" => $SEPJsonReq['request']['t_sep']['tujuanKunj'],
            "dpjpLayan" => $SEPJsonReq['request']['t_sep']['dpjpLayan'],
            "noTelp" => $SEPJsonReq['request']['t_sep']['noTelp'],
            "user" => $SEPJsonReq['request']['t_sep']['user'],
        ];
        // 4. Rules validasi (sesuai dengan yang aktif/tidak dikomentari)
        $rules = [
            "noKartu" => "required",
            "tglSep" => "required",
            "ppkPelayanan" => "required",
            "jnsPelayanan" => "required",
            "klsRawatHak" => "required",
            "asalRujukan" => "required",
            "tglRujukan" => "required",
            // "noRujukan" => "required", // dikomentari
            // "ppkRujukan" => "required", // dikomentari
            "catatan" => "required",
            "diagAwal" => "required",
            // "tujuan" => "required", // dikomentari
            // "eksekutif" => "required", // dikomentari
            "tujuanKunj" => "required",
            // "dpjpLayan" => "required", // dikomentari
            "noTelp" => "required",
            "user" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {
            $url = env('VCLAIM_URL') . "SEP/2.0/insert";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $SEPJsonReq;
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post($url, $data);
            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function sep_update($SEPJsonReq)
    {
        /* ------------------------------------------------------------------
     * 1. Siapkan data yang ingin divalidasi.
     *    – Update SEP WAJIB menyertakan nomor SEP (`noSep`).
     *    – Beberapa field pada proses insert tidak wajib/diabaikan
     *      ketika update (mis. `ppkPelayanan`, `asalRujukan`, dll.),
     *      tapi tetap kita sertakan bila Anda ingin mengubahnya.
     * -----------------------------------------------------------------*/
        $r = [
            "noSep"        => $SEPJsonReq['request']['t_sep']['noSep']       ?? '',
            "noKartu"      => $SEPJsonReq['request']['t_sep']['noKartu']     ?? '',
            "tglSep"       => $SEPJsonReq['request']['t_sep']['tglSep']      ?? '',
            "ppkPelayanan" => $SEPJsonReq['request']['t_sep']['ppkPelayanan'] ?? '',
            "jnsPelayanan" => $SEPJsonReq['request']['t_sep']['jnsPelayanan'] ?? '',
            "klsRawatHak"  => $SEPJsonReq['request']['t_sep']['klsRawat']['klsRawatHak'] ?? '',
            "diagAwal"     => $SEPJsonReq['request']['t_sep']['diagAwal']    ?? '',
            "tujuanKunj"   => $SEPJsonReq['request']['t_sep']['tujuanKunj']  ?? '',
            "dpjpLayan"    => $SEPJsonReq['request']['t_sep']['dpjpLayan']   ?? '',
            "catatan"      => $SEPJsonReq['request']['t_sep']['catatan']     ?? '',
            "noTelp"       => $SEPJsonReq['request']['t_sep']['noTelp']      ?? '',
            "user"         => $SEPJsonReq['request']['t_sep']['user']        ?? '',
        ];

        /* ------------------------------------------------------------------
     * 2. Validasi minimal – hanya field yang memang diwajibkan
     *    oleh spesifikasi *update* VClaim.
     * -----------------------------------------------------------------*/
        $validator = Validator::make($r, [
            "noSep"      => "required",
            "noKartu"    => "required",
            "tglSep"     => "required|date_format:Y-m-d",
            "jnsPelayanan" => "required|in:1,2",          // 1=RJ, 2=RI
            "diagAwal"   => "required",
            "tujuanKunj" => "required",
            "user"       => "required",
        ]);

        if ($validator->fails()) {
            return self::sendError(
                $validator->errors()->first(),
                $validator->errors(),
                201,
                null,
                null
            );
        }

        /* ------------------------------------------------------------------
     * 3. Panggil endpoint UPDATE.
     *    – Metode HTTP: PUT
     *    – Endpoint   : SEP/2.0/update
     * -----------------------------------------------------------------*/
        try {
            $url        = env('VCLAIM_URL') . "SEP/2.0/update";
            $signature  = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $SEPJsonReq;
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->put($url, $data);

            // Semua respons (sukses/gagal) di-decrypt & diproses di helper
            return self::response_decrypt(
                $response,
                $signature,
                $url,
                $response->transferStats->getTransferTime()
            );
        } catch (\Throwable $e) {
            // Catatan: $validator->errors() kosong di sini; bisa diganti []
            return self::sendError($e->getMessage(), [], 408, $url ?? null, null);
        }
    }


    public static function sep_delete(string $noSep)
    {
        /* ------------------------------------------------------------------
         * 1. Siapkan data yang ingin divalidasi.
         * -----------------------------------------------------------------*/
        $r = [
            'noSep' => $noSep,
            // ganti sesuai nama user/aplikasi Anda, bisa juga config('app.name') atau env('APP_NAME')
            'user'  => 'siRUS',
        ];

        /* ------------------------------------------------------------------
         * 2. Validasi minimal – hanya field yang diwajibkan oleh DELETE VClaim.
         * -----------------------------------------------------------------*/
        $validator = Validator::make($r, [
            'noSep' => 'required',
            'user'  => 'required',
        ]);

        if ($validator->fails()) {
            return self::sendError(
                $validator->errors()->first(),
                $validator->errors(),
                201,
                null,
                null
            );
        }

        /* ------------------------------------------------------------------
         * 3. Panggil endpoint DELETE.
         *    – Metode HTTP: DELETE
         *    – Endpoint   : SEP/2.0/delete
         * -----------------------------------------------------------------*/
        try {
            $url       = env('VCLAIM_URL') . 'SEP/2.0/delete';
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $payload = [
                'request' => [
                    't_sep' => [
                        'noSep' => $r['noSep'],
                        'user'  => $r['user'],
                    ],
                ],
            ];

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->delete($url, $payload);

            return self::response_decrypt(
                $response,
                $signature,
                $url,
                $response->transferStats->getTransferTime()
            );
        } catch (\Throwable $e) {
            return self::sendError(
                $e->getMessage(),
                [],
                408,
                $url ?? null,
                null
            );
        }
    }



    public static function sep_nomor($noSep)
    {

        // 1. Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
        ];

        // 2. Attributes (nama field yang user-friendly)
        $attributes = [
            'noSep' => 'Nomor SEP',
        ];

        // 3. Data yang akan divalidasi
        $r = [
            "noSep" => $noSep,
        ];

        // 4. Rules validasi
        $rules = [
            "noSep" => "required",
        ];

        // 5. Validator
        $validator = Validator::make($r, $rules, $messages, $attributes);


        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }



        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "SEP/" . $noSep;
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function sep_updtglplg($SEPJsonReq)
    {

        /**
         * 2. Lengkapi data bantu yang dipakai validasi lintas-field.
         *    Contoh: baca tgl SEP & info lain dari DB SEP (table sep_master).
         */
        $tglSep = $SEPJsonReq['request']['t_sep']['tglSep'];                         // yyyy-MM-dd (string)
        $today  = Carbon::now()->toDateString();     // “hari ini” versi WIB

        $isKLL              = (int) $SEPJsonReq['request']['t_sep']['isKLL'] == 1; // atau field Anda sendiri
        $isAlreadyReferred  = (bool) $SEPJsonReq['request']['t_sep']['statusPulang']; //true/false   // contoh: sudah “Dirujuk”
        $tglSep             = $SEPJsonReq['request']['t_sep']['tglSep'] ?? null;


        // customErrorMessages
        $messages = [
            // Umum
            'noSep.required'             => 'Nomor SEP wajib diisi.',
            'statusPulang.required'      => 'Status pulang wajib diisi.',
            'statusPulang.integer'       => 'Status pulang harus berupa angka.',
            'statusPulang.in'            => 'Status pulang tidak valid (hanya 1, 3, 4, atau 5).',

            // Tanggal Pulang
            'tglPulang.required'         => 'Tanggal pulang wajib diisi.',
            'tglPulang.date_format'      => 'Format tanggal pulang harus YYYY-MM-DD.',
            'tglPulang.before_or_equal' => 'Tanggal pulang tidak boleh melebihi hari ini.',
            'tglPulang.after_or_equal'  => 'Tanggal pulang tidak boleh sebelum tanggal SEP.',

            // Meninggal
            'noSuratMeninggal.required_if' => 'Nomor surat meninggal wajib diisi jika pasien meninggal.',
            'noSuratMeninggal.min'         => 'Nomor surat meninggal minimal 5 karakter.',
            'tglMeninggal.required_if'     => 'Tanggal meninggal wajib diisi jika pasien meninggal.',
            'tglMeninggal.date_format'     => 'Format tanggal meninggal harus YYYY-MM-DD.',

            // KLL
            'noLPManual.required_if'    => 'Nomor laporan polisi wajib diisi untuk kasus KLL.',
            'noLPManual.min'            => 'Nomor laporan polisi minimal 5 karakter.',
        ];
        // Masukkan Nilai dari parameter


        $r = [
            'noSep'            => $SEPJsonReq['request']['t_sep']['noSep'],
            'statusPulang'     => $SEPJsonReq['request']['t_sep']['statusPulang']         ?? null,
            'tglPulang'        => $SEPJsonReq['request']['t_sep']['tglPulang']            ?? null,
            'noSuratMeninggal' => $SEPJsonReq['request']['t_sep']['noSuratMeninggal']     ?? null,
            'tglMeninggal'     => $SEPJsonReq['request']['t_sep']['tglMeninggal']         ?? null,
            'noLPManual'       => $SEPJsonReq['request']['t_sep']['noLPManual']           ?? null,

            // field bantu – tidak ikut dikirim ke BPJS, hanya untuk closure
            'tglSep'           => $tglSep,
            'isKLL'            => $isKLL,
            'isAlreadyReferred' => $isAlreadyReferred,
        ];

        $rules = [
            'noSep'        => 'required',
            'statusPulang' => 'required|integer|in:1,3,4,5',

            // tglPulang: pakai rule string bawaan + 1 closure singkat
            'tglPulang' => [
                "required",
                "date_format:Y-m-d",
                "before_or_equal:$today",        // ≤ hari ini
                // "after_or_equal:$tglSep",        // ≥ tgl SEP
                // fn($attr, $value, $fail) => $isAlreadyReferred
                //     && $fail('tanggal pulang tidak bisa diupdate'),
            ],

            // cara pulang meninggal
            'noSuratMeninggal' => 'required_if:statusPulang,4|min:5',
            'tglMeninggal'     => 'required_if:statusPulang,4|nullable|date_format:Y-m-d',

            // SEP KLL
            'noLPManual' => 'required_if:isKLL,1|min:5',
        ];

        // lakukan validasis
        $validator = Validator::make($r, $rules, $messages);

        if ($validator->fails()) {
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {

            $url = env('VCLAIM_URL') . "SEP/2.0/updtglplg";
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = $SEPJsonReq;
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->put($url, $data);
            // dd($response->transferStats->getTransferTime()); Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function rujukan_keluar_list_rs($tanggalMulai, $tanggalAkhir)
    {
        // =========================================================
        // 1) Validasi parameter (bukan request()->all())
        // =========================================================
        $payload = [
            'tanggalMulai' => $tanggalMulai,
            'tanggalAkhir' => $tanggalAkhir,
        ];

        $messages = [
            'tanggalMulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggalMulai.date'     => 'Format tanggal mulai tidak valid.',
            'tanggalAkhir.required' => 'Tanggal akhir wajib diisi.',
            'tanggalAkhir.date'     => 'Format tanggal akhir tidak valid.',
            'tanggalAkhir.after_or_equal' => 'Tanggal akhir tidak boleh lebih kecil dari tanggal mulai.',
        ];

        $validator = Validator::make($payload, [
            "tanggalMulai" => "required|date",
            "tanggalAkhir" => "required|date|after_or_equal:tanggalMulai",
        ], $messages);

        if ($validator->fails()) {
            return self::sendError(
                $validator->errors()->first(),
                $validator->errors(),
                400,
                null,
                null
            );
        }

        // =========================================================
        // 2) Call API
        // =========================================================
        $url = env('VCLAIM_URL') . "Rujukan/Keluar/List/tglMulai/{$tanggalMulai}/tglAkhir/{$tanggalAkhir}";

        try {
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);

            return self::response_decrypt(
                $response,
                $signature,
                $url,
                $response->transferStats->getTransferTime()
            );
        } catch (\Throwable $e) {
            return self::sendError(
                $e->getMessage(),
                [],
                408,
                $url,
                null
            );
        }
    }

    public static function rujukan_keluar_detail_by_no_rujukan(string $noRujukan)
    {
        // =========================================================
        // 1) Validasi parameter
        // =========================================================
        $payload = [
            'noRujukan' => $noRujukan,
        ];

        $messages = [
            'noRujukan.required' => 'Nomor rujukan wajib diisi.',
            'noRujukan.string'   => 'Nomor rujukan harus berupa string.',
            'noRujukan.max'      => 'Nomor rujukan maksimal 30 karakter.',
        ];

        $validator = Validator::make($payload, [
            'noRujukan' => 'required|string|max:30',
        ], $messages);

        if ($validator->fails()) {
            return self::sendError(
                $validator->errors()->first(),
                $validator->errors(),
                400,
                null,
                null
            );
        }

        // =========================================================
        // 2) Call API
        // =========================================================
        // Endpoint: /Rujukan/Keluar/{noRujukan}
        $url = env('VCLAIM_URL') . "Rujukan/Keluar/{$noRujukan}";

        try {
            $signature = self::signature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);

            return self::response_decrypt(
                $response,
                $signature,
                $url,
                $response->transferStats->getTransferTime()
            );
        } catch (\Throwable $e) {
            return self::sendError(
                $e->getMessage(),
                [],
                408,
                $url,
                null
            );
        }
    }
}
