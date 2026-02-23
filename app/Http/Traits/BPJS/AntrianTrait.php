<?php

namespace App\Http\Traits\BPJS;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


use Exception;


trait AntrianTrait
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


    ///////////////////////////////////////////////////
    // // API FUNCTION
    public static function signature()
    {
        $cons_id =  env('ANTRIAN_CONS_ID');
        $secretKey = env('ANTRIAN_SECRET_KEY');
        $userkey = env('ANTRIAN_USER_KEY');
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);
        $data['user_key'] =  $userkey;
        $data['x-cons-id'] = $cons_id;
        $data['x-timestamp'] = $tStamp;
        $data['x-signature'] = $encodedSignature;
        $data['decrypt_key'] = $cons_id . $secretKey . $tStamp;
        return $data;
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
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($response->reason(),  $response->json('response'), $response->status(), $url, $requestTransferTime);
        } else {

            // Check Response !200          -> metadata d kecil
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
    public static function response_no_decrypt($response, $url, $requestTransferTime)
    {
        // Simpan metadata code untuk menghindari pemanggilan berulang
        $metadataCode = $response->json('metadata.code');

        // Menentukan kode HTTP menggunakan switch-case
        switch ($metadataCode) {
            case 200:
            case 1:
                $code = 200;
                break;
            case 2:
                $code = 400;
                break;
            case 204:
                $code = 404;
                break;
            default:
                $code = 400;
                break;
        }

        // Cek jika respons gagal
        if ($response->failed()) {
            return self::sendError($metadataCode, $response->json('metadata.message'), $code, $url, null);
        }
        // Respons sukses
        return self::sendResponse($response->json('metadata.message'), $response->json('response'), $code, $url, $requestTransferTime);
    }




    public static function dashboard_bulan_index($bulan, $tahun, $rs)
    {


        // Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            // tambahkan rules lain sesuai kebutuhan
        ];

        $attributes = [
            "bulan" => "Bulan",
            "tahun" => "Tahun",
            "rs" => "Rumah Sakit",
        ];

        $r = [
            "bulan" => $bulan,
            "tahun" =>  $tahun,
            "rs" =>  $rs,
        ];

        $rules = [
            "bulan" => "required",
            "tahun" =>  "required",
            "rs" =>  "required",
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {


            $url = env('ANTRIAN_URL') . "dashboard/waktutunggu/bulan/{$bulan}/tahun/{$tahun}/waktu/{$rs}";
            $signature = self::signature();
            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);


            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_no_decrypt($response, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            // error, msgError,Code,url,ReqtrfTime

            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function taskid_antrean($kodebooking)
    {


        // Custom messages spesifik
        $messages = [
            'kodebooking.required' => 'Kode Booking wajib diisi.',
        ];

        $attributes = [
            "kodebooking" => "Kode Booking",
        ];

        $r = ["kodebooking" => $kodebooking];

        $rules = ["kodebooking" => "required"];

        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {

            $url = env('ANTRIAN_URL') . "antrean/getlisttask";
            $signature = self::signature();

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post(
                    $url,
                    [
                        "kodebooking" => $kodebooking,
                    ]
                );

            // dd($response->getBody()->getContents());

            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {

            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function update_antrean($kodebooking, $taskid, $waktu, $jenisresep)
    {

        // Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute harus berupa Tidak ada, Racikan, atau Non racikan.',
        ];

        $attributes = [
            "kodebooking" => "Kode Booking",
            "taskid" => "Task ID",
            "waktu" => "Waktu",
            "jenisresep" => "Jenis Resep",
        ];

        $r = [
            "kodebooking" => $kodebooking,
            "taskid" =>  $taskid,
            "waktu" =>  $waktu,
            "jenisresep" => $jenisresep //  "Tidak ada/Racikan/Non racikan" ---> khusus yang sudah implementasi antrean farmasi
        ];
        // dd(Carbon::createFromTimestamp($waktu / 1000)->toDateTimeString());

        $rules = [
            "kodebooking" => "required",
            "taskid" =>  "required",
            "waktu" =>  "required",
            "jenisresep" => "nullable|in:Tidak ada,Racikan,Non racikan",
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {


            $url = env('ANTRIAN_URL') . "antrean/updatewaktu";
            $signature = self::signature();

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post(
                    $url,
                    [
                        "kodebooking" => $kodebooking,
                        "taskid" => $taskid,
                        "waktu" => $waktu,
                        "jenisresep" => $jenisresep,
                    ]
                );

            // dd($response->getBody()->getContents());

            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            // error, msgError,Code,url,ReqtrfTime

            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }

    public static function tambah_antrean_farmasi($noBooking, $jenisResep, $nomerAntrean)
    {

        // Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute harus berupa Tidak ada, Racikan, atau Non racikan.',
            'string' => ':attribute harus berupa teks.',
        ];

        $attributes = [
            "kodebooking" => "Kode Booking",
            "jenisresep" => "Jenis Resep",
            "nomorantrean" => "Nomor Antrean",
            "keterangan" => "Keterangan",
        ];

        $r = [
            "kodebooking" => $noBooking,
            "jenisresep" =>  $jenisResep, //  "Tidak ada/Racikan/Non racikan" ---> khusus yang sudah implementasi antrean farmasi
            "nomorantrean" =>  $nomerAntrean,
            "keterangan" => "xxxxxxx",
        ];

        $rules = [
            "kodebooking" => "required",
            "jenisresep" =>  "required|in:Tidak ada,Racikan,Non racikan",
            "nomorantrean" =>  "required",
            "keterangan" => "nullable|string",
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }

        try {


            $url = env('ANTRIAN_URL') . "antrean/farmasi/add";
            $signature = self::signature();

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post(
                    $url,
                    [
                        "kodebooking" => $noBooking,
                        "jenisresep" =>  $jenisResep, //  "Tidak ada/Racikan/Non racikan" ---> khusus yang sudah implementasi antrean farmasi
                        "nomorantrean" =>  $nomerAntrean,
                        "keterangan" => "xxxxxxx",
                    ]
                );

            // dd($response->getBody()->getContents());

            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            // error, msgError,Code,url,ReqtrfTime

            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function tambah_antrean($antreanadd)
    {

        // customErrorMessages
        // $messages = customErrorMessagesTrait::messages();
        $messages = [];


        $r = [
            "kodebooking" => $antreanadd['kodebooking'],
            "nomorkartu" =>  $antreanadd['nomorkartu'],
            "nomorreferensi" =>  $antreanadd['nomorreferensi'],
            "nik" =>  $antreanadd['nik'],
            "nohp" => $antreanadd['nohp'],
            "kodepoli" =>  $antreanadd['kodepoli'],
            "norm" =>  $antreanadd['norm'],
            "pasienbaru" =>  $antreanadd['pasienbaru'],
            "tanggalperiksa" =>   $antreanadd['tanggalperiksa'],
            "kodedokter" =>  $antreanadd['kodedokter'],
            "jampraktek" =>  $antreanadd['jampraktek'],
            "jeniskunjungan" => $antreanadd['jeniskunjungan'],
            "jenispasien" =>  $antreanadd['jenispasien'],
            "namapoli" =>  $antreanadd['namapoli'],
            "namadokter" =>  $antreanadd['namadokter'],
            "nomorantrean" =>  $antreanadd['nomorantrean'],
            "angkaantrean" =>  $antreanadd['angkaantrean'],
            "estimasidilayani" =>  $antreanadd['estimasidilayani'],
            "sisakuotajkn" =>  $antreanadd['sisakuotajkn'],
            "kuotajkn" => $antreanadd['kuotajkn'],
            "sisakuotanonjkn" => $antreanadd['sisakuotanonjkn'],
            "kuotanonjkn" => $antreanadd['kuotanonjkn'],
            "keterangan" =>  $antreanadd['keterangan'],
            // "nama" =>  $antreanadd['nama'],
        ];


        $rules = [
            "kodebooking" => "required",
            "nomorkartu" =>  "digits:13|numeric",
            // "nomorreferensi" =>  "required",
            "nik" =>  "required|digits:16|numeric",
            "nohp" => "required|numeric",
            "kodepoli" =>  "required",
            "norm" =>  "required",
            "pasienbaru" =>  "required",
            "tanggalperiksa" =>  "required|date|date_format:Y-m-d",
            "kodedokter" =>  "required",
            "jampraktek" =>  "required",
            "jeniskunjungan" => "required",
            "jenispasien" =>  "required",
            // "namapoli" =>  "required",
            // "namadokter" =>  "required",
            "nomorantrean" =>  "required",
            "angkaantrean" =>  "required",
            "estimasidilayani" =>  "required",
            "sisakuotajkn" =>  "required",
            "kuotajkn" => "required",
            "sisakuotanonjkn" => "required",
            "kuotanonjkn" => "required",
            "keterangan" =>  "required",
            // "nama" =>  "required",
        ];

        // ketika pasien umum nik dan noka boleh kosong
        $rules['nomorkartu'] = ($antreanadd['jenispasien'] == 'JKN') ? 'digits:13|numeric' : '';
        $rules['nik'] = ($antreanadd['jenispasien'] == 'JKN') ? 'required|digits:16|numeric' : '';

        $validator = Validator::make($r, $rules, $messages);
        // dd($validator->errors());

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {

            $antreanadd = $r;

            $url = env('ANTRIAN_URL') . "antrean/add";
            $signature = self::signature();

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->post(
                    $url,
                    [
                        "kodebooking" => $antreanadd['kodebooking'],
                        "jenispasien" => $antreanadd['jenispasien'],
                        "nomorkartu" => $antreanadd['nomorkartu'],
                        "nik" => $antreanadd['nik'],
                        "nohp" => $antreanadd['nohp'],
                        "kodepoli" => $antreanadd['kodepoli'],
                        "namapoli" => $antreanadd['namapoli'],
                        "pasienbaru" => $antreanadd['pasienbaru'],
                        "norm" => $antreanadd['norm'],
                        "tanggalperiksa" => $antreanadd['tanggalperiksa'],
                        "kodedokter" => $antreanadd['kodedokter'],
                        "namadokter" => $antreanadd['namadokter'],
                        "jampraktek" => $antreanadd['jampraktek'],
                        "jeniskunjungan" => $antreanadd['jeniskunjungan'],
                        "nomorreferensi" => $antreanadd['nomorreferensi'],
                        "nomorantrean" => $antreanadd['nomorantrean'],
                        "angkaantrean" => $antreanadd['angkaantrean'],
                        "estimasidilayani" => $antreanadd['estimasidilayani'],
                        "sisakuotajkn" => $antreanadd['sisakuotajkn'],
                        "kuotajkn" => $antreanadd['kuotajkn'],
                        "sisakuotanonjkn" => $antreanadd['sisakuotanonjkn'],
                        "kuotanonjkn" => $antreanadd['kuotanonjkn'],
                        "keterangan" => $antreanadd['keterangan'],
                    ]
                );

            // dd($response->getBody()->getContents());

            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {
            // error, msgError,Code,url,ReqtrfTime

            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }


    public static function ref_jadwal_dokter($kodePoli, $tgl)
    {
        // Custom error messages
        $messages = [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
        ];

        $attributes = [
            "kodePoli" => "Kode Poli",
            "tanggal" => "Tanggal",
        ];

        $r = [
            "kodePoli" => $kodePoli,
            "tanggal" =>  $tgl,
        ];

        $rules = [
            "kodePoli" => "required",
            "tanggal" =>  "required|date",
        ];

        $validator = Validator::make($r, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($validator->errors()->first(), $validator->errors(), 201, null, null);
        }


        // handler when time out and off line mode
        try {

            $url = env('ANTRIAN_URL') . "jadwaldokter/kodepoli/" . $kodePoli . "/tanggal/" . $tgl;
            $signature = self::signature();

            $response = Http::timeout(10)
                ->withHeaders($signature)
                ->get($url);

            // dd($response->getBody()->getContents());

            // dd($response->transferStats->getTransferTime()); //Get Transfertime request
            // semua response error atau sukses dari BPJS di handle pada logic response_decrypt
            return self::response_decrypt($response, $signature, $url, $response->transferStats->getTransferTime());
            /////////////////////////////////////////////////////////////////////////////
        } catch (Exception $e) {

            // error, msgError,Code,url,ReqtrfTime
            return self::sendError($e->getMessage(), $validator->errors(), 408, $url, null);
        }
    }



    ///////////////////////////////////////////////////
}
