<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\BPJS\AntrianTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

/**
 * KOMPONEN AKSI Task ID apotek RJ (TaskId6 Masuk Apotek, TaskId7 Keluar Apotek)
 * — berisi SEMUA fungsi/logika.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * antrian-apotek-rj (bukan per baris). Tombol tiap baris ada di antrian-apotek-rj
 * dan memicu komponen ini via
 * wire:click="$dispatch('task-id-apotek-proses-rj', { rjNo, aksi })" (aksi
 * Livewire, bukan Alpine).
 *
 * Sebelumnya komponen ini di-mount per baris dengan prop #[Reactive]. Saat list
 * re-render pasca 'refresh-after-apotek.saved', SEMUA child reactive ikut di-sync
 * satu batch → TooManyComponents saat baris banyak. Dengan mount sekali, batch
 * pasca-simpan tak lagi skala jumlah baris.
 *
 * ⚠️ Logika tiap aksi (guard, prasyarat, penomoran noAntrianApotek anti-race di
 *    dalam lock, push antrian farmasi + taskId ke BPJS) IDENTIK versi lama.
 */
new class extends Component {
    use EmrRJTrait, AntrianTrait;

    public ?int $rjNo = null;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     | Detail event: { rjNo, aksi } dengan aksi ∈ {'6','7'}.
     =============================== */
    #[On('task-id-apotek-proses-rj')]
    public function proses(int $rjNo, string $aksi): void
    {
        $this->rjNo = $rjNo;

        match ($aksi) {
            '6' => $this->prosesTaskId6(),
            '7' => $this->prosesTaskId7(),
            default => null,
        };
    }

    /* ===============================
     | PROSES TASK ID 6 (Masuk Apotek)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId5 prerequisite
     | 2. Inisialisasi taskIdPelayanan
     | 3. Push antrian apotek ke BPJS jika perlu — DI LUAR transaksi (API call)
     | 4. Push taskId6 ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 5. lockRJRow + hitung noAntrianApotek + update waktu_masuk_apt
     |    + patch taskIdPelayanan & noAntrianApotek — ATOMIK
     |
     | ⚠️  noAntrianApotek dihitung DI DALAM transaksi + lock untuk mencegah
     |     race condition (dua pasien selesai bersamaan → nomor antrian dobel)
    =============================== */
    public function prosesTaskId6(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            // 2. Ambil data RJ — tanpa lock dulu, hanya untuk baca awal
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // 3. Validasi prerequisite: taskId5 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            // 4. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 5. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            // 6. Set taskId6 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId6'])) {
                $data['taskIdPelayanan']['taskId6'] = $waktuSekarang;
            }

            // 7. Push antrian apotek ke BPJS jika poli spesialis — DI LUAR transaksi
            //    jenisResep & noAntrian disiapkan sementara dari data lokal untuk API call,
            //    nilai final akan dihitung ulang secara atomik di dalam transaksi (langkah 9)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $statusApotek = $data['taskIdPelayanan']['tambahAntrianApotek'] ?? '';

                if (empty($statusApotek) || ($statusApotek != 200 && $statusApotek != 208)) {
                    // Hitung sementara untuk kebutuhan API call — akan dikunci ulang di dalam transaksi
                    $eresepRacikanCount = collect($data['eresepRacikan'] ?? [])->count();
                    $jenisResepTemp = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';
                    $noAntrianTemp = $data['noAntrianApotek']['noAntrian'] ?? null;

                    if (!$noAntrianTemp) {
                        $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');
                        $noAntrianTemp = DB::table('rstxn_rjhdrs')->select('datadaftarpolirj_json')->where('rj_status', '!=', 'F')->where('klaim_id', '!=', 'KR')->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)->get()->filter(fn($item) => isset((json_decode($item->datadaftarpolirj_json, true) ?: [])['noAntrianApotek']))->count() + 1;
                    }

                    $this->pushAntreanApotek($data, $noBooking, $jenisResepTemp, $noAntrianTemp);
                }

                // 8. Push taskId6 ke BPJS
                $status = $data['taskIdPelayanan']['taskId6Status'] ?? '';
                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId6'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 6, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId6Status'] = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 6: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                }
            }

            // 9. Simpan ke DB — ATOMIK:
            //    - lock row
            //    - hitung noAntrianApotek di dalam lock (cegah race condition nomor antrian dobel)
            //    - update waktu_masuk_apt
            //    - patch taskIdPelayanan + noAntrianApotek
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Hitung noAntrianApotek di dalam lock — cegah nomor antrian dobel
                if (empty($existingData['noAntrianApotek'])) {
                    $eresepRacikanCount = collect($existingData['eresepRacikan'] ?? [])->count();
                    $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';

                    $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');
                    $noAntrian =
                        ($existingData['klaimId'] ?? '') !== 'KR'
                            ? DB::table('rstxn_rjhdrs')
                                    ->select('datadaftarpolirj_json')
                                    ->where('rj_status', '!=', 'F')
                                    ->where('klaim_id', '!=', 'KR')
                                    ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)
                                    ->lockForUpdate() // lock tabel untuk cegah race condition
                                    ->get()
                                    ->filter(fn($item) => isset((json_decode(OracleLob::toString($item->datadaftarpolirj_json) ?: '{}', true) ?: [])['noAntrianApotek']))
                                    ->count() + 1
                            : 9999;

                    $existingData['noAntrianApotek'] = [
                        'noAntrian' => $noAntrian,
                        'jenisResep' => $jenisResep,
                    ];
                }

                // Patch taskIdPelayanan dari hasil API call di atas
                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];

                // Update waktu_masuk_apt di header — atomik dengan JSON update
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_masuk_apt' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
            $this->dispatch('refresh-after-apotek.saved'); // refresh list apotek ini → nomor antrian langsung tampil
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | PROSES TASK ID 7 (Keluar Apotek)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId6 prerequisite
     | 2. Set taskId7 timestamp jika belum ada
     | 3. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 4. lockRJRow + update waktu_selesai_pelayanan + patch taskIdPelayanan — ATOMIK
    =============================== */
    public function prosesTaskId7(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            // 2. Ambil data RJ — tanpa lock dulu, hanya untuk baca awal
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // 3. Validasi prerequisite: taskId6 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId6 (Masuk Apotek) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            // 4. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 5. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            // 6. Notifikasi jika taskId7 sudah pernah tercatat
            if (!empty($data['taskIdPelayanan']['taskId7'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId7 sudah tercatat: {$data['taskIdPelayanan']['taskId7']}", title: 'Info');
            }

            // 7. Set taskId7 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId7'])) {
                $data['taskIdPelayanan']['taskId7'] = $waktuSekarang;
            }

            // 8. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId7Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId7'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 7, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId7Status'] = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 7: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 7 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // 9. Simpan ke DB — lock + update waktu_selesai_pelayanan + patch taskIdPelayanan atomik
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Update waktu_selesai_pelayanan di header — atomik dengan JSON update
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_selesai_pelayanan' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil keluar apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
            $this->dispatch('refresh-after-apotek.saved'); // refresh list apotek ini → nomor antrian langsung tampil
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | PUSH ANTRIAN APOTEK KE BPJS
     | Dipanggil DI LUAR transaksi — API call tidak boleh di dalam DB::transaction.
     | Hasil code disimpan ke $data (pass by reference) untuk di-patch ke DB di langkah 9.
     =============================== */
    private function pushAntreanApotek(array &$data, string $noBooking, string $jenisResep, int $nomerAntrean): void
    {
        try {
            $response = AntrianTrait::tambah_antrean_farmasi($noBooking, $jenisResep, $nomerAntrean, '')->getOriginalContent();
            $code = $response['metadata']['code'] ?? '';
            $message = $response['metadata']['message'] ?? '';
            $isSuccess = $code == 200 || $code == 208;

            $data['taskIdPelayanan'] ??= [];
            $data['taskIdPelayanan']['tambahAntrianApotek'] = $code;

            $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: 'Antrian Apotek: ' . $message, title: $isSuccess ? 'Berhasil' : 'Gagal');
        } catch (\Exception $e) {
            $data['taskIdPelayanan']['tambahAntrianApotek'] = 500;
            $this->dispatch('toast', type: 'error', message: 'Gagal push antrian apotek: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
    }
};
?>

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris antrian-apotek-rj). --}}
<div wire:key="task-id-apotek-actions-rj-host">
    <div wire:loading wire:target="proses, prosesTaskId6, prosesTaskId7"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID Apotek…
    </div>
</div>
