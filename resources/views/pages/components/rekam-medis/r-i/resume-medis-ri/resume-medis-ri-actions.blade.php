<?php

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * Resume Medis Pasien Pulang (Editor & PDF Generator)
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * Modul ini menangani pembuatan **Resume Medis** untuk pasien Rawat Inap
 * saat pulang. Output: dokumen ringkas berisi diagnosa, anamnesis, pemeriksaan,
 * tindakan, kondisi pulang, dst., yang ditandatangani DPJP dan dibawa pasien.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 1. PENYIMPANAN DATA — di mana isi resume medis disimpan?
 * ────────────────────────────────────────────────────────────────────────────────
 *
 * Resume medis disimpan sebagai **HTML string** di kolom JSON di header RI:
 *
 *   Tabel  : `rstxn_rihdrs`
 *   Kolom  : `datadaftarri_json` (CLOB, JSON document)
 *   Path   : `resumeMedis`   ← langsung HTML string, tidak nested object
 *
 * Contoh struktur JSON di `datadaftarri_json`:
 *
 *   {
 *     "regNo": "00012345",
 *     "entryDate": "13/05/2026 00:50:24",
 *     "diagnosis": [ ... ],
 *     "procedureICDList": [ ... ],
 *     "pengkajianDokter": { "anamnesa": {...}, "fisik": "...", ... },
 *     "pengkajianAwalPasienRawatInap": { "bagian1DataUmum": {...}, ... },
 *     "perencanaan": { "tindakLanjut": { "tindakLanjut": "371827001", ... } },
 *     "resumeMedis": "<table>...HTML dari TinyMCE...</table>"
 *   }
 *
 * **Value** = output langsung dari TinyMCE editor — `<table>`, `<p>`, `<strong>`,
 * `<ol>`, dst. Tidak di-parse atau di-normalize; saat render PDF pakai `{!! !!}`
 * raw HTML.
 *
 * Kalau di future butuh metadata audit (savedAt/savedBy), tambahkan sebagai
 * **sibling key root-level** (mis. `resumeMedisSavedAt`, `resumeMedisSavedBy`),
 * **bukan** nested object — supaya path `resumeMedis` tetap konsisten = HTML.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 2. CARA KERJA — alur open → edit → simpan → cetak
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   Step 1 (OPEN)
 *     • Tombol "Resume Medis" di EMR RI dispatch event
 *       `resume-medis-ri.open` dengan `riHdrNo`.
 *     • `open()` load `$dataRI` via `findDataRI()` (decode `datadaftarri_json`).
 *     • Cek `resumeMedis` — kalau sudah ada (sudah pernah disimpan), pakai itu.
 *       Kalau kosong, build template default via
 *       `buildPreFilledTemplate()` — auto-fill dari pengkajian + diagnosis +
 *       prosedur + tindak lanjut.
 *     • Dispatch `open-modal` → TinyMCE bootEditor → editor render dgn pre-fill.
 *
 *   Step 2 (EDIT)
 *     • TinyMCE editor edit `<table>` HTML — sync ke `$this->resumeMedis`
 *       via debounced events (input/change/keyup/blur/SetContent).
 *     • Tombol "Reset ke Default" → `resetToDefault()` → rebuild template dari
 *       data EMR terbaru → dispatch `resume-medis-ri.reload` →
 *       TinyMCE listener panggil `editor.setContent($wire.get('resumeMedis'))`.
 *
 *   Step 3 (SIMPAN)
 *     • Klik tombol "Simpan" → Alpine dispatch `resume-medis-ri.flush` window
 *       event → TinyMCE `flush()` push HTML editor terbaru ke `$this->resumeMedis`
 *       → `$nextTick(() => $wire.save())`.
 *     • Server `save()`: validate min 5 char teks, lock row, update JSON via
 *       `updateJsonRI()` (set key `resumeMedis` ke HTML string), commit, toast
 *       sukses. Modal tetap terbuka — user bisa terus edit.
 *
 *   Step 4 (CETAK PDF)
 *     • Klik "Cetak PDF" → flush event → `cetakPdf()` → render blade
 *       `resume-medis-ri-print.blade.php` via DomPDF dengan
 *       payload `[dataDaftarRi, dataPasien, resumeMedis]` → stream download.
 *     • PDF render = header pasien (auto dari `findDataMasterPasien`) +
 *       body `{!! $resumeMedis !!}` + footer TTD DPJP (digital dari
 *       `users.myuser_ttd_image` lookup via `dr_id` Utama).
 *     • **Penting:** Cetak PDF pakai isi **in-memory** (editor saat ini),
 *       bukan dari JSON DB. Jadi user boleh cetak preview tanpa save dulu.
 *
 *   Step 5 (CLOSE)
 *     • Tombol Batal atau X → `closeEditor()` → reset property → dispatch
 *       `close-modal` → TinyMCE `cleanupEditor()` (remove instance,
 *       filter null entries dari `tinymce.editors` global).
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 3. EMR STATUS LOCK — sengaja DI-SKIP untuk Resume Medis
 * ────────────────────────────────────────────────────────────────────────────────
 *
 * Modul EMR RI lain (mis. pengkajian dokter, perencanaan, form pindah ruang)
 * di-lock saat `ri_status != 'I'` (pasien sudah pulang/'P'). Polanya:
 *
 *   $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
 *
 * **Resume Medis TIDAK mengikut pola ini.** Alasannya:
 *
 *   • Resume Medis biasanya dibuat **saat atau sesudah** pasien pulang.
 *     Pulang = `ri_status = 'P'`. Kalau lock pas 'P', justru momen Resume
 *     Medis paling dibutuhkan dia tidak bisa dibuat — counter-productive.
 *
 *   • DPJP juga sering perlu **edit/koreksi** resume medis pasca-pulang
 *     (typo, tambahan diagnosis komplikasi, revisi obat pulang) — terutama
 *     untuk klaim BPJS yang ditolak verifikator.
 *
 *   • Untuk audit, kita pakai `savedAt` + `savedBy` di JSON sebagai trail.
 *
 * Property `$isFormLocked` dipertahankan di state component supaya UI binding
 * (badge, disabled state) tidak error — tapi **selalu false**. Kalau di
 * future ada policy lain (mis. lock setelah klaim BPJS lolos verifikasi),
 * tinggal aktifkan lagi `checkEmrRIStatus()` atau ganti dengan policy custom.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 4. EVENTS YANG DIDISPATCH/LISTEN
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   LISTEN:
 *     • `resume-medis-ri.open` (Livewire, dari EMR RI button)
 *
 *   DISPATCH:
 *     • `open-modal` { name: 'resume-medis-ri' }   — buka modal
 *     • `close-modal` { name: 'resume-medis-ri' }  — tutup modal
 *     • `resume-medis-ri.reload`                   — trigger TinyMCE reload
 *     • `toast` { type, message }                         — global toast
 *
 *   WINDOW EVENT (dari Alpine, ditangkap TinyMCE factory):
 *     • `resume-medis-ri.flush` — paksa flush isi editor ke $wire sebelum action
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 5. FILE TERKAIT
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   • `resume-medis-ri-print.blade.php` — template PDF DomPDF
 *   • `resources/views/components/tinymce-editor.blade.php` — komponen editor
 *   • `resources/js/app.js` (Alpine factory `tinymceEditor`) — TinyMCE bootstrap
 *   • `App\Http\Traits\Txn\Ri\EmrRITrait`                — findDataRI, updateJsonRI, lockRIRow
 *   • `App\Http\Traits\Master\MasterPasien\MasterPasienTrait` — findDataMasterPasien
 *
 *   Dokumentasi tambahan:
 *   • `docs/tinymce-editor-pattern.md`   — pola pemakaian TinyMCE
 *   • `docs/ttd-pattern-pdf-print.md`    — pola TTD di blade print
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $riHdrNo = null;
    public string $resumeMedis = '';

    /**
     * Lock state — saat ini SELALU false untuk Resume Medis (lihat doc block §3).
     * Property dipertahankan untuk binding UI (badge, :disabled) supaya tidak
     * error kalau di future kita aktifkan lock-nya kembali.
     */
    public bool $isFormLocked = false;

    /* ═══════════════════════════════════════
     | OPEN — buka modal editor Resume Medis
     |
     | Flow:
     |   1. Load $dataRI dari JSON `datadaftarri_json` via findDataRI()
     |   2. Cek existing `resumeMedis` di JSON
     |      - Ada  → pakai HTML tersimpan (edit ulang)
     |      - Tidak → build template default dari data EMR (auto pre-fill)
     |   3. Dispatch open-modal → TinyMCE boot dgn pre-fill
    ═══════════════════════════════════════ */
    #[On('resume-medis-ri.open')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        // Resume Medis sengaja tidak di-lock — DPJP boleh edit pasca-pulang
        // (revisi klaim BPJS, tambahan diagnosis). Lihat doc block §3.
        $this->isFormLocked = false;

        // Load existing dari path `resumeMedis` (HTML string) di datadaftarri_json.
        // Kalau kosong (belum pernah disimpan) → auto-build template default dari
        // data EMR terbaru via buildPreFilledTemplate().
        $existing = (string) data_get($dataRI, 'resumeMedis', '');
        $this->resumeMedis = $existing !== '' ? $existing : $this->buildPreFilledTemplate($dataRI);

        $this->dispatch('open-modal', name: 'resume-medis-ri');
    }

    /* ═══════════════════════════════════════
     | RESET — rebuild template default dari data EMR latest
     |
     | User klik tombol "Reset ke Default" di header modal. Akan:
     |  - Re-fetch $dataRI (kalau ada perubahan diagnosis/prosedur di EMR
     |    setelah modal dibuka)
     |  - Generate ulang template via buildPreFilledTemplate()
     |  - Push isi baru ke TinyMCE via event 'resume-medis-ri.reload'
     |
     | TIDAK menyentuh JSON DB. User harus klik Simpan untuk persist.
    ═══════════════════════════════════════ */
    public function resetToDefault(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->resumeMedis = $this->buildPreFilledTemplate($dataRI);
        $this->dispatch('resume-medis-ri.reload');
        $this->dispatch('toast', type: 'success', message: 'Template di-reset dari data EMR terbaru.');
    }

    /**
     * Build template Resume Medis dgn value pre-filled dari JSON RI:
     *  - Diagnosa Masuk         ← pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk
     *  - Indikasi Rawat         ← pengkajianDokter.anamnesa.keluhanTambahan
     *  - Anamnesis              ← pengkajianDokter.anamnesa.keluhanUtama + riwayatPenyakit
     *  - Pemeriksaan Fisik      ← pengkajianAwalPasienRawatInap...tandaVital + pengkajianDokter.fisik
     *  - Pemeriksaan Penunjang  ← pengkajianDokter.hasilPemeriksaanPenunjang.{laboratorium, radiologi, penunjangLain}
     *  - Diagnosa Akhir         ← diagnosis[] filter kategoriDiagnosa = utama/primer
     *  - Komplikasi             ← diagnosis[] filter kategoriDiagnosa = komplikasi
     *  - Komorbid               ← diagnosis[] filter kategoriDiagnosa = komorbid/sekunder
     *  - Tindakan/Operasi       ← procedureICDList[]
     *  - Riwayat Alergi         ← pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.*
     *
     * Dokter tinggal isi sisa field manual (Obat Selama Rawat, Obat Pulang, Kondisi Pulang, Pengobatan Lanjutan, dst).
     */
    private function buildPreFilledTemplate(array $dataRI): string
    {
        $esc = fn($v) => e(trim((string) $v));

        // ── 1) Diagnosa Masuk ────────────────────────────────
        $diagnosaMasuk = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', ''));

        // ── 2) Indikasi Rawat ────────────────────────────────
        $indikasi = $esc(data_get($dataRI, 'pengkajianDokter.anamnesa.keluhanTambahan', ''));

        // ── 3) Anamnesis (keluhan utama + riwayat penyakit) ─
        $kuLine = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.keluhanUtama', ''));
        $rpSekarang = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.sekarang', ''));
        $rpDahulu = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.dahulu', ''));
        $rpKeluarga = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.keluarga', ''));
        $anamnesisParts = [];
        if ($kuLine !== '') $anamnesisParts[] = 'Keluhan utama: ' . e($kuLine);
        if ($rpSekarang !== '') $anamnesisParts[] = 'Riwayat penyakit sekarang: ' . e($rpSekarang);
        if ($rpDahulu !== '') $anamnesisParts[] = 'Riwayat penyakit dahulu: ' . e($rpDahulu);
        if ($rpKeluarga !== '') $anamnesisParts[] = 'Riwayat penyakit keluarga: ' . e($rpKeluarga);
        $anamnesis = implode('<br>', $anamnesisParts);

        // ── 4) Pemeriksaan Fisik (TTV + narasi fisik) ───────
        $tv = (array) data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital', []);
        $td = trim((string) data_get($tv, 'sistolik', '') . '/' . (string) data_get($tv, 'distolik', ''));
        $ttvParts = [];
        if ($td !== '/' && $td !== '') $ttvParts[] = 'TD ' . e($td) . ' mmHg';
        if ($n = data_get($tv, 'frekuensiNadi'))  $ttvParts[] = 'N ' . e($n) . '/mnt';
        if ($r = data_get($tv, 'frekuensiNafas')) $ttvParts[] = 'RR ' . e($r) . '/mnt';
        if ($s = data_get($tv, 'suhu'))           $ttvParts[] = 'T ' . e($s) . '°C';
        $ttvLine = implode('; ', $ttvParts);
        $fisikNarasi = trim((string) data_get($dataRI, 'pengkajianDokter.fisik', ''));
        $pemFisik = trim($ttvLine . ($ttvLine && $fisikNarasi ? '<br>' : '') . e($fisikNarasi));

        // ── 5) Pemeriksaan Penunjang ────────────────────────
        $lab = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium', ''));
        $rad = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.radiologi', ''));
        $lain = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain', ''));
        $penunjangParts = [];
        if ($lab !== '')  $penunjangParts[] = 'Lab: ' . e($lab);
        if ($rad !== '')  $penunjangParts[] = 'Radiologi: ' . e($rad);
        if ($lain !== '') $penunjangParts[] = 'Lain: ' . e($lain);
        $penunjang = implode('<br>', $penunjangParts);

        // ── 6) Diagnosis (filter by kategori) ───────────────
        $dxList = collect(data_get($dataRI, 'diagnosis', []));
        $cari = function (array $keywords) use ($dxList) {
            return $dxList->first(function ($d) use ($keywords) {
                $k = strtolower((string) data_get($d, 'kategoriDiagnosa', ''));
                foreach ($keywords as $kw) {
                    if (str_contains($k, $kw)) return true;
                }
                return false;
            });
        };
        $dxFree = trim((string) data_get($dataRI, 'diagnosisFreeText', ''));
        $dxUtama = $cari(['utama', 'primer', 'primary']);
        $dxKompl = $cari(['komplikasi']);
        $dxKomor = $cari(['komorbid', 'sekunder', 'secondary']);

        $diagAkhirText = $esc(data_get($dxUtama, 'descDiagnosa', '')) ?: e($dxFree);
        $diagAkhirIcd = $esc(data_get($dxUtama, 'kdDiagnosa', ''));
        $komplikasiText = $esc(data_get($dxKompl, 'descDiagnosa', ''));
        $komplikasiIcd = $esc(data_get($dxKompl, 'kdDiagnosa', ''));
        $komorbidText = $esc(data_get($dxKomor, 'descDiagnosa', ''));
        $komorbidIcd = $esc(data_get($dxKomor, 'kdDiagnosa', ''));

        // ── 7) Tindakan / Operasi (procedureICDList) ────────
        $procList = collect(data_get($dataRI, 'procedureICDList', []))
            ->map(fn($p) => [
                'desc' => trim((string) data_get($p, 'descProcedure', '')),
                'icd' => trim((string) data_get($p, 'kdProcedure', '')),
            ])
            ->filter(fn($p) => $p['desc'] !== '')
            ->values();

        $tindakanHtml = '';
        if ($procList->isNotEmpty()) {
            foreach ($procList as $p) {
                $tindakanHtml .= '<li>' . e($p['desc']);
                if ($p['icd'] !== '') $tindakanHtml .= ' &nbsp;&nbsp;<em>ICD-9CM:</em> ' . e($p['icd']);
                $tindakanHtml .= '</li>';
            }
        } else {
            $tindakanHtml = '<li>&nbsp;&nbsp;<em>ICD-9CM:</em> </li><li>&nbsp;&nbsp;<em>ICD-9CM:</em> </li>';
        }

        // ── 8) Riwayat Alergi ───────────────────────────────
        $alergiObat = trim((string) data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.alergiObat.desc', ''));
        $alergiMakanan = trim((string) data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.alergiMakanan.desc', ''));
        $alergiLain = trim((string) data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.alergiLain.desc', ''));
        $alergiParts = [];
        if ($alergiObat !== '')    $alergiParts[] = 'Obat: ' . e($alergiObat);
        if ($alergiMakanan !== '') $alergiParts[] = 'Makanan: ' . e($alergiMakanan);
        if ($alergiLain !== '')    $alergiParts[] = 'Lain: ' . e($alergiLain);
        $alergi = implode('; ', $alergiParts);

        // ── 9) Kondisi Saat Pulang ← perencanaan.tindakLanjut.tindakLanjut (SNOMED code!) ─
        // Field tindakLanjut.tindakLanjut menyimpan SNOMED code (mis. '371828006'),
        // bukan label. Mapping SNOMED → Resume Medis label:
        $snomedToLabel = [
            '371827001' => 'Sembuh',                          // Pulang Sehat
            '266707007' => 'Pulang Atas Permintaan Sendiri',  // Pulang dgn Permintaan Sendiri
            '306206005' => 'Pulang Pindah / Rujuk',           // Pulang Pindah / Rujuk
            '371828006' => 'Perbaikan',                       // Pulang Tanpa Perbaikan
            '419099009' => 'Meninggal',                       // Meninggal
            '74964007'  => 'Lain-lain',                       // Lain-lain
        ];
        $tindakLanjutCode = trim((string) data_get($dataRI, 'perencanaan.tindakLanjut.tindakLanjut', ''));
        $tglPulang = (string) data_get($dataRI, 'perencanaan.tindakLanjut.tglPulang', '');
        $tglMeninggal = (string) data_get($dataRI, 'perencanaan.tindakLanjut.tglMeninggal', '');
        $ketTindakLanjut = trim((string) data_get($dataRI, 'perencanaan.tindakLanjut.keterangan', ''));

        $kondisiPulang = $snomedToLabel[$tindakLanjutCode] ?? '';
        if ($kondisiPulang === 'Meninggal' && $tglMeninggal !== '') {
            $kondisiPulang .= ' (' . $tglMeninggal . ')';
        }
        if ($kondisiPulang === 'Lain-lain' && $ketTindakLanjut !== '') {
            $kondisiPulang .= ' (' . $ketTindakLanjut . ')';
        }

        $isRujuk = $tindakLanjutCode === '306206005';
        $dirujukKe = $isRujuk ? e($ketTindakLanjut) : '';
        $alasanRujuk = $isRujuk ? e($ketTindakLanjut) : '';
        $kondisiPulang = e($kondisiPulang);

        // ── Build final HTML — pakai <table> 2-kolom (Label | Value) ────
        // Tema diselaraskan dgn header identitas pasien (Tailwind): label
        // pakai `text-muted` (abu, normal — bukan <strong>), ukuran font
        // ikut wrapper `.resume-medis-content` (11px) di template cetak.
        // Border tipis #cbd5e1 inline (border-gray Tailwind rawan purge di PDF).
        $row = fn(string $label, string $value) =>
            "<tr><td class=\"text-muted align-top\" style=\"width: 200px; border: 1px solid #cbd5e1; padding: 3px 6px;\">{$label}</td>" .
            "<td class=\"align-top\" style=\"border: 1px solid #cbd5e1; padding: 3px 6px;\">{$value}</td></tr>";

        $tindakanCell = $procList->isNotEmpty()
            ? '<ol style="margin:0; padding-left: 18px;">' . $tindakanHtml . '</ol>'
            : '<ol style="margin:0; padding-left: 18px;"><li>ICD-9CM: </li><li>ICD-9CM: </li></ol>';

        $diagAkhirCell = $diagAkhirText . ($diagAkhirIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $diagAkhirIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $komplikasiCell = $komplikasiText . ($komplikasiIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $komplikasiIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $komorbidCell = $komorbidText . ($komorbidIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $komorbidIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $dirujukCell = $dirujukKe . ' &nbsp;&nbsp;<strong>Alasan:</strong> ' . $alasanRujuk;

        $rows = implode("\n", [
            $row('Diagnosa Masuk', $diagnosaMasuk),
            $row('Indikasi Rawat', $indikasi),
            $row('Anamnesis', $anamnesis),
            $row('Pemeriksaan Fisik', $pemFisik),
            $row('Pemeriksaan Penunjang', $penunjang),
            $row('Obat Selama Rawat', ''),
            $row('Diagnosa Akhir', $diagAkhirCell),
            $row('Komplikasi', $komplikasiCell),
            $row('Komorbid', $komorbidCell),
            $row('Tindakan / Operasi', $tindakanCell),
            $row('Riwayat Alergi', $alergi),
            $row('Obat / Terapi Pulang', ''),
            $row('Kondisi Saat Pulang', $kondisiPulang),
            $row('Dirujuk ke', $dirujukCell),
            $row('Pengobatan Lanjutan', 'Poliklinik:  &nbsp;&nbsp;<strong>Tanggal Kontrol:</strong> '),
            $row('Segera Bawa ke RS Bila', ''),
        ]);

        return '<table class="w-full" style="border-collapse: collapse;">' . "\n" . $rows . "\n" . '</table>';
    }

    public function closeEditor(): void
    {
        $this->reset(['riHdrNo', 'resumeMedis', 'isFormLocked']);
        $this->dispatch('close-modal', name: 'resume-medis-ri');
    }

    /* ═══════════════════════════════════════
     | SAVE — simpan ke JSON RI, modal tetap terbuka
     |
     | Tulis HTML editor langsung ke path `resumeMedis` (string) di
     | `rstxn_rihdrs.datadaftarri_json`. Pakai lockRIRow() untuk
     | concurrency safety (FOR UPDATE).
     |
     | Catatan: Resume Medis tidak mengikut EMR lock (lihat doc §3) —
     | DPJP boleh simpan sekalipun ri_status sudah 'P'.
    ═══════════════════════════════════════ */
    public function save(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }

        $plain = trim(strip_tags((string) $this->resumeMedis));
        if (mb_strlen($plain) < 5) {
            $this->addError('resumeMedis', 'Resume medis harus diisi (minimal 5 karakter teks).');
            return;
        }

        $this->validate(
            ['resumeMedis' => 'required|string|max:65000'],
            ['resumeMedis.required' => 'Resume medis harus diisi.'],
        );

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use (&$dataRI) {
                $this->lockRIRow($this->riHdrNo);
                // Simpan langsung sebagai HTML string di key `resumeMedis` —
                // tidak pakai nested object/metadata, supaya path JSON konsisten.
                $dataRI['resumeMedis'] = $this->resumeMedis;
                $this->updateJsonRI($this->riHdrNo, $dataRI);
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Resume medis tersimpan.');
    }

    /* ═══════════════════════════════════════
     | CETAK PDF — generate PDF dari isi editor saat ini (in-memory)
    ═══════════════════════════════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return null;
        }

        $plain = trim(strip_tags((string) $this->resumeMedis));
        if (mb_strlen($plain) < 5) {
            $this->addError('resumeMedis', 'Resume medis kosong — isi dulu sebelum dicetak.');
            $this->dispatch('toast', type: 'error', message: 'Resume medis kosong.');
            return null;
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $regNo = (string) ($dataRI['regNo'] ?? '');
        $pasienData = $regNo ? $this->findDataMasterPasien($regNo) : null;
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.r-i.resume-medis-ri.resume-medis-ri-print',
            [
                'dataDaftarRi' => $dataRI,
                'dataPasien' => $pasienData,
                'resumeMedis' => $this->resumeMedis,
            ],
        )->setPaper('A4', 'portrait');

        $filename = 'resume-medis-ri-' . ($regNo ?: $this->riHdrNo) . '.pdf';
        $this->dispatch('toast', type: 'success', message: 'PDF di-generate.');
        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>

<div>
    <x-modal name="resume-medis-ri" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                {{-- Judul & subjudul dihapus — header = identitas pasien sebelahan dengan tombol X. --}}
                @if (!empty($riHdrNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="resume-medis-ri-display-pasien-header-{{ $riHdrNo }}" />
                            @if ($isFormLocked)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-2 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    Terkunci (Pasien Pulang)
                                </span>
                            @endif
                        </div>
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @else
                    <div class="flex items-center justify-end">
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
            </div>

            <div class="flex-1 px-6 py-5 overflow-y-auto">
                <div class="flex flex-wrap items-center justify-between mb-1 gap-x-2">
                    <x-input-label value="Isi Resume Medis" required class="!mb-0" />
                    <span class="text-xs text-muted dark:text-gray-400">Ringkasan diagnosa, tindakan, terapi &amp; kondisi pulang. Identitas pasien &amp; TTD DPJP terisi otomatis saat dicetak.</span>
                </div>
                <x-tinymce-editor
                    name="resumeMedis"
                    placeholder="Ketik isi resume medis (Diagnosa Masuk, Anamnesis, Pemeriksaan, Diagnosa Akhir, Tindakan, Obat Pulang, Kondisi Pulang, dll)..."
                    height="600"
                    modal-event="resume-medis-ri"
                    flush-event="resume-medis-ri.flush"
                    reload-event="resume-medis-ri.reload"
                    :content-style="'body{font-family:sans-serif;font-size:11px;line-height:1.4;color:#1f2937;} table{border-collapse:collapse;width:100%;} table td,table th{border:1px solid #cbd5e1;padding:3px 6px;vertical-align:top;} .text-muted{color:#6b7280;}'"
                    class="mt-1" />
                @error('resumeMedis')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">
                    Editor mendukung format teks ala Word + <strong>tabel</strong> (tambah baris/kolom, gabung sel).
                    Isi tersimpan ke berkas EMR pasien setelah klik <strong>Simpan</strong>.
                </p>
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                {{-- Reset ke Default — pojok kiri sendiri --}}
                <x-secondary-button type="button"
                    wire:click="resetToDefault"
                    wire:confirm="Reset isi Resume Medis ke template default dari data EMR terbaru? Perubahan yang belum disimpan akan hilang."
                    :disabled="$isFormLocked"
                    wire:loading.attr="disabled" wire:target="resetToDefault"
                    class="text-xs">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span wire:loading.remove wire:target="resetToDefault">Reset ke Default</span>
                    <span wire:loading wire:target="resetToDefault"><x-loading /> Reset...</span>
                </x-secondary-button>

                {{-- Aksi kanan: Batal · Cetak · Simpan --}}
                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="closeEditor">Batal</x-secondary-button>

                    {{-- Cetak PDF (in-memory: tidak save dulu, langsung render). --}}
                    <x-secondary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('resume-medis-ri.flush')); $nextTick(() => $wire.cetakPdf())"
                        wire:loading.attr="disabled" wire:target="cetakPdf,save">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span wire:loading.remove wire:target="cetakPdf">Cetak PDF</span>
                        <span wire:loading wire:target="cetakPdf"><x-loading /> Cetak...</span>
                    </x-secondary-button>

                    {{-- Simpan saja (modal tetap terbuka, toast sukses). --}}
                    <x-primary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('resume-medis-ri.flush')); $nextTick(() => $wire.save())"
                        wire:loading.attr="disabled" wire:target="save,cetakPdf"
                        :disabled="$isFormLocked">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save"><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
