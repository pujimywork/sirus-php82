<?php

namespace App\Http\Traits\Dokumen;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helper bersama untuk komponen viewer dokumen di display Rekam Medis (RI/RJ/UGD).
 *
 * Menyediakan:
 * - dvPasien()/dvTtdPath()/dvIdentitasRs() — dipakai semua modul (cetak bespoke RJ/UGD).
 * - streamCetakDokumenRi() — pintasan cetak untuk dokumen RI yg payload-nya seragam
 *   (dataRi/form/identitasRs/ttd*), identik dgn aksi cetak di komponen EMR modul-dokumen.
 *
 * Dipakai bersama MasterPasienTrait (findDataMasterPasien). Method streamCetakDokumenRi()
 * juga menuntut EmrRITrait (findDataRI) + properti $riHdrNo pada komponen pemakai.
 */
trait DokumenViewSupportTrait
{
    /**
     * Navigasi Prev/Next antar-record dalam satu modal (tanpa buka-tutup).
     * Komponen list mengeset $navField = nama field id (createdAt/signatureDate/…);
     * navigasi berjalan di atas $this->list + $this->selected, memanggil lihat().
     */
    public string $navField = '';

    /** Daftar id record yg bisa dinavigasi (urut sama dgn baris list). */
    protected function navIdList(): array
    {
        if ($this->navField === '') {
            return [];
        }
        return collect($this->list ?? [])
            ->filter(fn($entri) => filled(data_get($entri, $this->navField)))
            ->pluck($this->navField)
            ->map(fn($id) => (string) $id)
            ->values()->all();
    }

    public function navTotal(): int
    {
        return count($this->navIdList());
    }

    /** Posisi 1-based record aktif (0 bila tak ada). */
    public function navPos(): int
    {
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $this->navIdList(), true);
        return $pos === false ? 0 : $pos + 1;
    }

    public function prevRecord(): void
    {
        $ids = $this->navIdList();
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $ids, true);
        if ($pos !== false && $pos > 0) {
            $this->lihat($ids[$pos - 1]);
        }
    }

    public function nextRecord(): void
    {
        $ids = $this->navIdList();
        $pos = array_search((string) data_get($this->selected ?? [], $this->navField, ''), $ids, true);
        if ($pos !== false && $pos < count($ids) - 1) {
            $this->lihat($ids[$pos + 1]);
        }
    }

    /** Data pasien (findDataMasterPasien) + hitung umur ($pasien['thn']). */
    protected function dvPasien(?string $regNo): array
    {
        $pasien = $this->findDataMasterPasien($regNo ?? '')['pasien'] ?? [];
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }
        return $pasien;
    }

    /** Path TTD dari myuser_code (null bila tak ada / file hilang). */
    protected function dvTtdPath(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }
        $ttdPath = DB::table('users')->where('myuser_code', $code)->value('myuser_ttd_image');
        return (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath)))
            ? public_path('storage/' . $ttdPath)
            : null;
    }

    /** Identitas RS untuk kop cetak. */
    protected function dvIdentitasRs()
    {
        return DB::table('rsmst_identitases')
            ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')
            ->first();
    }

    /**
     * Render blade print jadi HTML self-contained (untuk preview iframe di modal Lihat).
     * $view = nama blade lengkap (mis. 'pages.components.modul-dokumen.r-i.xxx.cetak-xxx-print').
     * Memakai payload yg sama dgn cetak → isi Lihat = persis tampilan Cetak.
     */
    protected function renderDokumenPreview(string $view, array $data): string
    {
        $html = view($view, ['data' => $data])->render();

        // Print blade memakai path filesystem absolut (public_path) untuk gambar
        // logo & TTD — DomPDF butuh itu, tapi di iframe browser jadi URL 404.
        // Untuk preview saja: ubah prefix public_path() → path web-relatif
        // ('/images/...', '/storage/...'). Cetak PDF tetap pakai HTML asli.
        $html = str_replace(public_path(), '', $html);

        // Perbesar tampilan preview (font & layout) TANPA mengubah blade cetak —
        // cukup inject zoom khusus iframe. Cetak PDF tidak terpengaruh.
        return str_replace('</head>', "<style>body{zoom:1.4}</style></head>", $html);
    }

    /**
     * Stream cetak satu entri dokumen RI (payload seragam: dataRi/form/identitasRs/ttd*).
     * Identik dgn aksi cetak asli di komponen EMR modul-dokumen RI.
     *
     * @param  ?array   $entry         entri dokumen (null → toast error)
     * @param  string   $view          path relatif di 'pages.components.modul-dokumen.r-i.'
     * @param  string   $filePrefix    prefix nama file PDF
     * @param  string   $ttdKey        key TTD yg diharapkan print ('ttdPath'/'ttdOperatorPath'/'ttdPemberiPath')
     * @param  ?string  $ttdCodeField  field myuser_code di entri utk resolve TTD (null = tanpa TTD)
     * @param  array    $extra         payload tambahan (mis. aldreteItems/bromageOptions)
     */
    protected function streamCetakDokumenRi(?array $entry, string $view, string $filePrefix, string $ttdKey, ?string $ttdCodeField, array $extra = []): mixed
    {
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data dokumen tidak ditemukan.');
            return null;
        }

        $data = $this->dataDokumenRi($entry, $ttdKey, $ttdCodeField, $extra);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.' . $view, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), $filePrefix . '-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    /** HTML preview dokumen RI (payload seragam) — untuk iframe modal Lihat. */
    protected function previewDokumenRi(?array $entry, string $view, string $ttdKey, ?string $ttdCodeField, array $extra = []): string
    {
        if (empty($entry)) {
            return '';
        }
        return $this->renderDokumenPreview('pages.components.modul-dokumen.r-i.' . $view, $this->dataDokumenRi($entry, $ttdKey, $ttdCodeField, $extra));
    }

    /** Bangun payload cetak dokumen RI (payload seragam dataRi/form/identitasRs/ttd*). */
    protected function dataDokumenRi(array $entry, string $ttdKey, ?string $ttdCodeField, array $extra = []): array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'dataRi' => $dataRi,
            'form' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            $ttdKey => $ttdCodeField ? $this->dvTtdPath($entry[$ttdCodeField] ?? null) : null,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ], $extra);
    }
}
