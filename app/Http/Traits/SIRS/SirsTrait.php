<?php

namespace App\Http\Traits\SIRS;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * SirsTrait
 *
 * Integrasi SIMRS dengan RS Online Kemenkes
 * Base URL  : env('SIRS_URL')  → https://sirs.kemkes.go.id/fo/index.php/
 * Auth      : x-rs-id, x-timestamp, x-pass  (no HMAC)
 *
 * Env yang dibutuhkan:
 *   SIRS_URL     = https://sirs.kemkes.go.id/fo/index.php/
 *   SIRS_RS_ID   = kode RS dari Kemenkes
 *   SIRS_PASS    = password RS Online
 */
trait SirsTrait
{
    // =========================================================
    // HELPERS
    // =========================================================

    private static function sirsHeaders(array $extra = []): array
    {
        date_default_timezone_set('UTC');
        $timestamp = strval(time());

        return array_merge([
            'x-rs-id'      => env('SIRS_RS_ID'),
            'x-timestamp'  => $timestamp,
            'x-pass'       => env('SIRS_PASS'),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], $extra);
    }

    private static function sirsResponse($response, string $url): \Illuminate\Http\JsonResponse
    {
        $status = $response->status();
        $body   = $response->json() ?? $response->body();

        DB::table('web_log_status')->insert([
            'code'                => $status,
            'date_ref'            => Carbon::now(env('APP_TIMEZONE')),
            'response'            => json_encode($body),
            'http_req'            => $url,
            'requestTransferTime' => $response->transferStats?->getTransferTime(),
        ]);

        return response()->json($body, $status);
    }

    private static function sirsError(string $message, int $code, string $url = null): \Illuminate\Http\JsonResponse
    {
        $body = ['message' => $message, 'code' => $code];

        DB::table('web_log_status')->insert([
            'code'                => $code,
            'date_ref'            => Carbon::now(env('APP_TIMEZONE')),
            'response'            => json_encode($body),
            'http_req'            => $url,
            'requestTransferTime' => null,
        ]);

        return response()->json($body, $code);
    }


    // =========================================================
    // B. TEMPAT TIDUR (Fasyankes)
    // =========================================================

    /**
     * Referensi master tempat tidur
     * GET /Referensi/tempat_tidur
     */
    public function sirsRefTempaTidur()
    {
        $url = env('SIRS_URL') . 'Referensi/tempat_tidur';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * GET data tempat tidur yang sudah diinputkan
     * GET /Fasyankes
     */
    public function sirsGetTempaTidur()
    {
        $url = env('SIRS_URL') . 'Fasyankes';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim data tempat tidur baru
     * POST /Fasyankes
     *
     * @param array $data {id_tt, ruang, jumlah_ruang, jumlah, terpakai,
     *                     terpakai_suspek, terpakai_konfirmasi,
     *                     antrian, prepare, prepare_plan, covid}
     */
    public function sirsKirimTempaTidur(array $data)
    {
        $rules = [
            'id_tt'                => 'required',
            'ruang'                => 'required|string|max:100',
            'jumlah_ruang'         => 'required|numeric|min:0',
            'jumlah'               => 'required|numeric|min:0',
            'terpakai'             => 'required|numeric|min:0',
            'terpakai_suspek'      => 'nullable|numeric|min:0',
            'terpakai_konfirmasi'  => 'nullable|numeric|min:0',
            'antrian'              => 'nullable|numeric|min:0',
            'prepare'              => 'nullable|numeric|min:0',
            'prepare_plan'         => 'nullable|numeric|min:0',
            'covid'                => 'required|in:0,1',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update data tempat tidur
     * PUT /Fasyankes
     *
     * @param array $data {id_t_tt (dari GET), ruang, jumlah_ruang, jumlah, terpakai,
     *                     terpakai_suspek, terpakai_konfirmasi,
     *                     antrian, prepare, prepare_plan, covid}
     */
    public function sirsUpdateTempaTidur(array $data)
    {
        $rules = [
            'id_t_tt'              => 'required',
            'ruang'                => 'required|string|max:100',
            'jumlah_ruang'         => 'required|numeric|min:0',
            'jumlah'               => 'required|numeric|min:0',
            'terpakai'             => 'required|numeric|min:0',
            'terpakai_suspek'      => 'nullable|numeric|min:0',
            'terpakai_konfirmasi'  => 'nullable|numeric|min:0',
            'antrian'              => 'nullable|numeric|min:0',
            'prepare'              => 'nullable|numeric|min:0',
            'prepare_plan'         => 'nullable|numeric|min:0',
            'covid'                => 'required|in:0,1',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus data tempat tidur
     * DELETE /Fasyankes
     *
     * @param string|int $idTTt  id_t_tt dari GET data tempat tidur
     */
    public function sirsHapusTempaTidur($idTTt)
    {
        $validator = Validator::make(
            ['id_t_tt' => $idTTt],
            ['id_t_tt' => 'required'],
            ['id_t_tt.required' => 'id_t_tt wajib diisi.']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->delete($url, ['id_t_tt' => $idTTt]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // C. SDM
    // =========================================================

    /**
     * Referensi master SDM
     * GET /Referensi/kebutuhan_sdm
     */
    public function sirsRefSDM()
    {
        $url = env('SIRS_URL') . 'Referensi/kebutuhan_sdm';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * GET data SDM yang sudah dikirim
     * GET /Fasyankes/sdm
     */
    public function sirsGetSDM()
    {
        $url = env('SIRS_URL') . 'Fasyankes/sdm';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim data SDM
     * POST /Fasyankes/sdm
     *
     * @param array $data {id_kebutuhan, jumlah_eksisting, jumlah, jumlah_diterima}
     */
    public function sirsKirimSDM(array $data)
    {
        $rules = [
            'id_kebutuhan'    => 'required',
            'jumlah_eksisting' => 'required|numeric|min:0',
            'jumlah'          => 'required|numeric|min:0',
            'jumlah_diterima' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/sdm';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update data SDM
     * PUT /Fasyankes/sdm
     *
     * @param array $data {id_kebutuhan, jumlah_eksisting, jumlah, jumlah_diterima}
     */
    public function sirsUpdateSDM(array $data)
    {
        $rules = [
            'id_kebutuhan'    => 'required',
            'jumlah_eksisting' => 'required|numeric|min:0',
            'jumlah'          => 'required|numeric|min:0',
            'jumlah_diterima' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/sdm';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus data SDM
     * DELETE /Fasyankes/sdm  (id_kebutuhan dikirim via header)
     *
     * @param string|int $idKebutuhan
     */
    public function sirsHapusSDM($idKebutuhan)
    {
        $validator = Validator::make(
            ['id_kebutuhan' => $idKebutuhan],
            ['id_kebutuhan' => 'required'],
            ['id_kebutuhan.required' => 'id_kebutuhan wajib diisi.']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/sdm';
        try {
            $response = Http::timeout(10)
                ->withHeaders(self::sirsHeaders(['Id_kebutuhan' => $idKebutuhan]))
                ->delete($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // D. APD / ALKES
    // =========================================================

    /**
     * Referensi master APD
     * GET /Referensi/kebutuhan_apd
     */
    public function sirsRefAPD()
    {
        $url = env('SIRS_URL') . 'Referensi/kebutuhan_apd';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * GET data APD yang sudah dikirim
     * GET /Fasyankes/apd
     */
    public function sirsGetAPD()
    {
        $url = env('SIRS_URL') . 'Fasyankes/apd';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim data APD
     * POST /Fasyankes/apd
     *
     * @param array $data {id_kebutuhan, jumlah_eksisting, jumlah, jumlah_diterima}
     */
    public function sirsKirimAPD(array $data)
    {
        $rules = [
            'id_kebutuhan'    => 'required',
            'jumlah_eksisting' => 'required|numeric|min:0',
            'jumlah'          => 'required|numeric|min:0',
            'jumlah_diterima' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/apd';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update data APD
     * PUT /Fasyankes/apd
     *
     * @param array $data {id_kebutuhan, jumlah_eksisting, jumlah, jumlah_diterima}
     */
    public function sirsUpdateAPD(array $data)
    {
        $rules = [
            'id_kebutuhan'    => 'required',
            'jumlah_eksisting' => 'required|numeric|min:0',
            'jumlah'          => 'required|numeric|min:0',
            'jumlah_diterima' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/apd';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus data APD
     * DELETE /Fasyankes/apd  (id_kebutuhan dikirim via header)
     *
     * @param string|int $idKebutuhan
     */
    public function sirsHapusAPD($idKebutuhan)
    {
        $validator = Validator::make(
            ['id_kebutuhan' => $idKebutuhan],
            ['id_kebutuhan' => 'required'],
            ['id_kebutuhan.required' => 'id_kebutuhan wajib diisi.']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Fasyankes/apd';
        try {
            $response = Http::timeout(10)
                ->withHeaders(self::sirsHeaders(['Id_kebutuhan' => $idKebutuhan]))
                ->delete($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // E. PCR NAKES
    // =========================================================

    /**
     * GET data pemeriksaan PCR Nakes
     * GET /Pasien/pcr_nakes
     *
     * @param string|null $tanggal  format yyyy-mm-dd, null = 10 hari terakhir
     */
    public function sirsGetPcrNakes(?string $tanggal = null)
    {
        $url = env('SIRS_URL') . 'Pasien/pcr_nakes';
        $extra = $tanggal ? ['x-tanggal' => $tanggal] : [];
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders($extra))->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim / update data pemeriksaan PCR Nakes
     * POST /Pasien/pcr_nakes
     * (insert jika tanggal belum ada, update jika sudah ada)
     *
     * @param array $data  sesuai body dokumentasi PCR Nakes
     */
    public function sirsKirimPcrNakes(array $data)
    {
        $rules = [
            'tanggal'  => 'required|date_format:Y-m-d',
            'tgllapor' => 'required|date',
        ];
        $messages = [
            'tanggal.required'         => 'Tanggal wajib diisi.',
            'tanggal.date_format'      => 'Format tanggal harus yyyy-mm-dd.',
            'tgllapor.required'        => 'Tanggal lapor wajib diisi.',
        ];

        $validator = Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/pcr_nakes';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // F. REKAP HARIAN NAKES TERINFEKSI
    // =========================================================

    /**
     * GET data rekap harian nakes terinfeksi
     * GET /Pasien/harian_nakes_terinfeksi
     *
     * @param string|null $tanggal  format yyyy-mm-dd, null = 10 hari terakhir
     */
    public function sirsGetHarianNakesTerminfeksi(?string $tanggal = null)
    {
        $url   = env('SIRS_URL') . 'Pasien/harian_nakes_terinfeksi';
        $extra = $tanggal ? ['x-tanggal' => $tanggal] : [];
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders($extra))->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim / update data rekap harian nakes terinfeksi
     * POST /Pasien/harian_nakes_terinfeksi
     * (insert jika tanggal belum ada, update jika sudah ada)
     *
     * @param array $data  sesuai body dokumentasi
     */
    public function sirsKirimHarianNakesTerminfeksi(array $data)
    {
        $validator = Validator::make($data, [
            'tanggal' => 'required|date_format:Y-m-d',
        ], [
            'tanggal.required'    => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal harus yyyy-mm-dd.',
        ]);

        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/harian_nakes_terinfeksi';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // G. OKSIGENASI
    // =========================================================

    /**
     * GET data oksigenasi
     * GET /Logistik/oksigen
     *
     * @param string|null $tanggal  format yyyy-mm-dd, null = 10 hari terakhir
     */
    public function sirsGetOksigen(?string $tanggal = null)
    {
        $url   = env('SIRS_URL') . 'Logistik/oksigen';
        $extra = $tanggal ? ['x-tanggal' => $tanggal] : [];
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders($extra))->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Kirim / update data oksigenasi (dalam m3)
     * POST /Logistik/oksigen
     *
     * @param array $data {tanggal, p_cair, p_tabung_kecil, p_tabung_sedang, p_tabung_besar,
     *                     k_isi_cair, k_isi_tabung_kecil, k_isi_tabung_sedang, k_isi_tabung_besar}
     */
    public function sirsKirimOksigen(array $data)
    {
        $rules = [
            'tanggal'           => 'required|date_format:Y-m-d',
            'p_cair'            => 'required|numeric|min:0',
            'p_tabung_kecil'    => 'required|numeric|min:0',
            'p_tabung_sedang'   => 'required|numeric|min:0',
            'p_tabung_besar'    => 'required|numeric|min:0',
            'k_isi_cair'        => 'required|numeric|min:0',
            'k_isi_tabung_kecil'  => 'required|numeric|min:0',
            'k_isi_tabung_sedang' => 'required|numeric|min:0',
            'k_isi_tabung_besar'  => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules, [
            'tanggal.required'    => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal harus yyyy-mm-dd.',
        ]);

        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Logistik/oksigen';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // IV. PASIEN SHK (Skrining Hipotiroid Kongenital)
    // =========================================================

    /**
     * GET data pasien SHK
     * GET /Pasien/shk
     *
     * @param array $body {tgl_ambil_sampel?, id_shk?}
     */
    public function sirsGetPasienSHK(array $body = [])
    {
        $url = env('SIRS_URL') . 'Pasien/shk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->withBody(json_encode($body), 'application/json')
                ->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Insert pasien SHK
     * POST /Pasien/shk
     *
     * @param array $data  sesuai body dokumentasi SHK
     */
    public function sirsInsertPasienSHK(array $data)
    {
        $rules = [
            'koders'               => 'required|string',
            'nama_ibu'             => 'required|string|max:100',
            'nama_anak'            => 'required|string|max:100',
            'tgllahir'             => 'required|date_format:Y-m-d',
            'gender'               => 'required|in:Laki-Laki,Perempuan',
            'tgl_ambil_sampel'     => 'required|date_format:Y-m-d',
            'tgl_kirim_sampel'     => 'required|date_format:Y-m-d',
            'tgl_lapor'            => 'required|date_format:Y-m-d',
            'jenis_fasyankes'      => 'required',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/shk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update pasien SHK
     * PUT /Pasien/shk
     *
     * @param array $data  wajib sertakan id_shk
     */
    public function sirsUpdatePasienSHK(array $data)
    {
        $rules = [
            'id_shk'               => 'required',
            'koders'               => 'required|string',
            'nama_ibu'             => 'required|string|max:100',
            'nama_anak'            => 'required|string|max:100',
            'tgllahir'             => 'required|date_format:Y-m-d',
            'gender'               => 'required|in:Laki-Laki,Perempuan',
            'tgl_ambil_sampel'     => 'required|date_format:Y-m-d',
            'tgl_kirim_sampel'     => 'required|date_format:Y-m-d',
            'tgl_lapor'            => 'required|date_format:Y-m-d',
            'jenis_fasyankes'      => 'required',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/shk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus pasien SHK
     * DELETE /Pasien/shk
     *
     * @param string|int $idShk
     * @param string     $koders
     */
    public function sirsHapusPasienSHK($idShk, string $koders)
    {
        $validator = Validator::make(
            ['id_shk' => $idShk, 'koders' => $koders],
            ['id_shk' => 'required', 'koders' => 'required|string']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/shk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->delete($url, ['id_shk' => $idShk, 'koders' => $koders]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // IV. HASIL LABORATORIUM SHK
    // =========================================================

    /**
     * GET hasil lab SHK
     * GET /Pasien/hasilShk
     *
     * @param string|int $idShk
     */
    public function sirsGetHasilSHK($idShk)
    {
        $validator = Validator::make(
            ['id_shk' => $idShk],
            ['id_shk' => 'required'],
            ['id_shk.required' => 'id_shk wajib diisi.']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/hasilShk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->withBody(json_encode(['id_shk' => $idShk]), 'application/json')
                ->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Insert hasil lab SHK
     * POST /Pasien/hasilShk
     *
     * @param array $data {id_shk, jenis_pemeriksaan, hasil_pemeriksaan,
     *                     tgl_periksa, tgl_hasil, layak_sampel, id_layak,
     *                     tgl_terima, tgllapor}
     */
    public function sirsInsertHasilSHK(array $data)
    {
        $rules = [
            'id_shk'              => 'required',
            'jenis_pemeriksaan'   => 'required',
            'hasil_pemeriksaan'   => 'required',
            'tgl_periksa'         => 'required|date_format:Y-m-d',
            'tgl_hasil'           => 'required|date_format:Y-m-d',
            'layak_sampel'        => 'required',
            'id_layak'            => 'required',
            'tgl_terima'          => 'required|date_format:Y-m-d',
            'tgllapor'            => 'required|date',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/hasilShk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update hasil lab SHK
     * PUT /Pasien/hasilShk
     *
     * @param array $data  wajib sertakan id_hasil dan id_shk
     */
    public function sirsUpdateHasilSHK(array $data)
    {
        $rules = [
            'id_hasil'            => 'required',
            'id_shk'              => 'required',
            'jenis_pemeriksaan'   => 'required',
            'hasil_pemeriksaan'   => 'required',
            'tgl_periksa'         => 'required|date_format:Y-m-d',
            'tgl_hasil'           => 'required|date_format:Y-m-d',
            'layak_sampel'        => 'required',
            'id_layak'            => 'required',
            'tgl_terima'          => 'required|date_format:Y-m-d',
            'tgllapor'            => 'required|date',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/hasilShk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus hasil lab SHK
     * DELETE /Pasien/hasilShk
     *
     * @param string|int $idHasil
     * @param string|int $idShk
     */
    public function sirsHapusHasilSHK($idHasil, $idShk)
    {
        $validator = Validator::make(
            ['id_hasil' => $idHasil, 'id_shk' => $idShk],
            ['id_hasil' => 'required', 'id_shk' => 'required']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Pasien/hasilShk';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->delete($url, ['id_hasil' => $idHasil, 'id_shk' => $idShk]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }


    // =========================================================
    // V. KPI 1 — WAKTU PELAYANAN RAWAT JALAN (Antrian)
    // =========================================================

    /**
     * GET data booking antrian
     * GET /Antrian
     *
     * @param string|null $tanggal  format yyyy-mm-dd, null = hari ini
     */
    public function sirsGetAntrian(?string $tanggal = null)
    {
        $url  = env('SIRS_URL') . 'Antrian';
        $body = $tanggal ? ['tanggal' => $tanggal] : [];
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->withBody(json_encode($body), 'application/json')
                ->get($url);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Buat kode booking antrian
     * POST /Antrian
     *
     * @param array $data  sesuai body dokumentasi Antrian
     */
    public function sirsInsertAntrian(array $data)
    {
        $rules = [
            'kodebooking'       => 'required|string',
            'jenispasien'       => 'required|in:JKN,NON JKN',
            'nik'               => 'required|digits:16',
            'kodepoli'          => 'required|string',
            'namapoli'          => 'required|string',
            'pasienbaru'        => 'required|in:0,1',
            'norm'              => 'required|string',
            'tanggalperiksa'    => 'required|date_format:Y-m-d',
            'kodedokter'        => 'required',
            'namadokter'        => 'required|string',
            'jampraktek'        => 'required|string',
            'jeniskunjungan'    => 'required|in:1,2,3,4',
            'nomorantrean'      => 'required|string',
            'angkaantrean'      => 'required|integer|min:1',
            'estimasidilayani'  => 'required|integer|min:0',
        ];

        $messages = [
            'jenispasien.in'     => 'Jenis pasien harus JKN atau NON JKN.',
            'nik.digits'         => 'NIK harus 16 digit.',
            'pasienbaru.in'      => 'Pasien baru harus 0 atau 1.',
            'jeniskunjungan.in'  => 'Jenis kunjungan harus 1-4.',
        ];

        $validator = Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Antrian';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update data booking antrian
     * PUT /Antrian
     *
     * @param array $data  sama seperti insert, wajib sertakan kodebooking
     */
    public function sirsUpdateAntrian(array $data)
    {
        $rules = [
            'kodebooking'       => 'required|string',
            'jenispasien'       => 'required|in:JKN,NON JKN',
            'nik'               => 'required|digits:16',
            'kodepoli'          => 'required|string',
            'namapoli'          => 'required|string',
            'pasienbaru'        => 'required|in:0,1',
            'norm'              => 'required|string',
            'tanggalperiksa'    => 'required|date_format:Y-m-d',
            'kodedokter'        => 'required',
            'namadokter'        => 'required|string',
            'jampraktek'        => 'required|string',
            'jeniskunjungan'    => 'required|in:1,2,3,4',
            'nomorantrean'      => 'required|string',
            'angkaantrean'      => 'required|integer|min:1',
            'estimasidilayani'  => 'required|integer|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Antrian';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, $data);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Hapus data booking antrian
     * DELETE /Antrian
     *
     * @param string $kodebooking
     */
    public function sirsHapusAntrian(string $kodebooking)
    {
        $validator = Validator::make(
            ['kodebooking' => $kodebooking],
            ['kodebooking' => 'required|string'],
            ['kodebooking.required' => 'Kode booking wajib diisi.']
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Antrian';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())
                ->delete($url, ['kodebooking' => $kodebooking]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Catat waktu pelayanan pasien (per task/titik layanan)
     * POST /Antrian/Task
     *
     * Task ID:
     *  1  = mulai waktu tunggu admisi
     *  2  = akhir tunggu admisi / mulai layan admisi
     *  3  = akhir layan admisi / mulai tunggu poli
     *  4  = akhir tunggu poli / mulai layan poli
     *  5  = akhir layan poli / mulai tunggu farmasi
     *  6  = akhir tunggu farmasi / mulai layan farmasi
     *  7  = akhir obat selesai dibuat
     *  99 = tidak hadir / batal
     *
     * @param string $kodebooking
     * @param int    $taskid       salah satu dari task ID di atas
     * @param int    $waktu        timestamp milisecond
     */
    public function sirsInsertAntrianTask(string $kodebooking, int $taskid, int $waktu)
    {
        $validTaskIds = [1, 2, 3, 4, 5, 6, 7, 99];

        $validator = Validator::make(
            ['kodebooking' => $kodebooking, 'taskid' => $taskid, 'waktu' => $waktu],
            [
                'kodebooking' => 'required|string',
                'taskid'      => 'required|integer|in:' . implode(',', $validTaskIds),
                'waktu'       => 'required|integer|min:0',
            ],
            [
                'kodebooking.required' => 'Kode booking wajib diisi.',
                'taskid.required'      => 'Task ID wajib diisi.',
                'taskid.in'            => 'Task ID tidak valid. Harus salah satu dari: ' . implode(', ', $validTaskIds),
                'waktu.required'       => 'Waktu wajib diisi.',
                'waktu.integer'        => 'Waktu harus berupa timestamp milisecond.',
            ]
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Antrian/Task';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->post($url, [
                'kodebooking' => $kodebooking,
                'taskid'      => $taskid,
                'waktu'       => $waktu,
            ]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }

    /**
     * Update waktu pelayanan
     * PUT /Antrian/Task
     *
     * @param string $kodebooking
     * @param int    $taskid
     * @param int    $waktu  timestamp milisecond
     */
    public function sirsUpdateAntrianTask(string $kodebooking, int $taskid, int $waktu)
    {
        $validTaskIds = [1, 2, 3, 4, 5, 6, 7, 99];

        $validator = Validator::make(
            ['kodebooking' => $kodebooking, 'taskid' => $taskid, 'waktu' => $waktu],
            [
                'kodebooking' => 'required|string',
                'taskid'      => 'required|integer|in:' . implode(',', $validTaskIds),
                'waktu'       => 'required|integer|min:0',
            ]
        );
        if ($validator->fails()) {
            return self::sirsError($validator->errors()->first(), 400);
        }

        $url = env('SIRS_URL') . 'Antrian/Task';
        try {
            $response = Http::timeout(10)->withHeaders(self::sirsHeaders())->put($url, [
                'kodebooking' => $kodebooking,
                'taskid'      => $taskid,
                'waktu'       => $waktu,
            ]);
            return self::sirsResponse($response, $url);
        } catch (Exception $e) {
            return self::sirsError($e->getMessage(), 408, $url);
        }
    }
}
