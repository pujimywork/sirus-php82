<?php
// resources/views/pages/components/rekam-medis/r-i/ringkasan-pulang-ri/ringkasan-pulang-ri-actions.blade.php
//
// Ringkasan Pemulangan Pasien — DIISI PERAWAT/BIDAN (beda dgn Resume Medis dokter).
// Pola sama persis Resume Medis: editor TinyMCE (HTML), template auto pre-fill dari
// data EMR, disimpan sebagai HTML string di datadaftarri_json.ringkasanPulang,
// cetak PDF via ringkasan-pulang-ri-print (raw HTML + footer 3 TTD).

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $riHdrNo = null;
    public string $ringkasanPulang = '';

    /** Tidak di-lock (sama alasan dgn Resume Medis: dibuat saat/sesudah pulang). */
    public bool $isFormLocked = false;

    /* ═══════════════ OPEN ═══════════════ */
    #[On('ringkasan-pulang-ri.open')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->isFormLocked = false;

        $existing = (string) data_get($dataRI, 'ringkasanPulang', '');
        $this->ringkasanPulang = $existing !== '' ? $existing : $this->buildPreFilledTemplate($dataRI);

        $this->dispatch('open-modal', name: 'ringkasan-pulang-ri');
    }

    /* ═══════════════ RESET ═══════════════ */
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
        $this->ringkasanPulang = $this->buildPreFilledTemplate($dataRI);
        $this->dispatch('ringkasan-pulang-ri.reload');
        $this->dispatch('toast', type: 'success', message: 'Template di-reset dari data EMR terbaru.');
    }

    /**
     * Build template HTML "Ringkasan Pemulangan Pasien" dgn value pre-filled
     * best-effort dari JSON RI. Perawat tinggal lengkapi sisanya di editor.
     */
    private function buildPreFilledTemplate(array $dataRI): string
    {
        $esc = fn($v) => e(trim((string) $v));

        // Diagnosa utama / komorbid
        $dxList = collect(data_get($dataRI, 'diagnosis', []));
        $byKat = fn(array $kw) => $dxList->first(function ($d) use ($kw) {
            $k = strtolower((string) data_get($d, 'kategoriDiagnosa', ''));
            foreach ($kw as $w) {
                if (str_contains($k, $w)) return true;
            }
            return false;
        });
        $dxUtama = $byKat(['utama', 'primer', 'primary']);
        $dxKomor = $byKat(['komorbid', 'sekunder', 'secondary']);
        $diagMasuk = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', ''));
        $diagnosa = $esc(data_get($dxUtama, 'descDiagnosa', '')) ?: $diagMasuk;
        $komorbid = $esc(data_get($dxKomor, 'descDiagnosa', ''));

        // Tanda Vital ← entri Observasi Lanjutan TERBARU (kondisi terkini saat pulang),
        // bukan TTV saat masuk. Pilih waktuPemeriksaan paling akhir.
        $obsList = collect(data_get($dataRI, 'observasi.observasiLanjutan.tandaVital', []));
        $lastObs = $obsList->sortBy(function ($o) {
            try {
                return Carbon::createFromFormat('d/m/Y H:i:s', (string) data_get($o, 'waktuPemeriksaan', '01/01/2000 00:00:00'))->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        })->last() ?? [];
        $sis = trim((string) data_get($lastObs, 'sistolik', ''));
        $dis = trim((string) data_get($lastObs, 'distolik', ''));
        $td = $sis !== '' || $dis !== '' ? trim($sis . '/' . $dis) : '';
        $nadi = $esc(data_get($lastObs, 'frekuensiNadi', ''));
        $suhu = $esc(data_get($lastObs, 'suhu', ''));
        $nafas = $esc(data_get($lastObs, 'frekuensiNafas', ''));
        // Keadaan umum tidak ada di observasi lanjutan → ambil dari pengkajian awal (jika ada).
        $keadaanUmum = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital.keadaanUmum', ''));

        // Tindakan / prosedur
        $tindakan = collect(data_get($dataRI, 'procedureICDList', []))
            ->map(fn($p) => trim((string) data_get($p, 'descProcedure', '')))
            ->filter()
            ->map(fn($d) => e($d))
            ->implode('<br>');

        // Obat saat pulang ← E-RESEP TERAKHIR (resep pulang), bukan gabungan semua resep
        // selama dirawat. Pilih header dgn resepDate paling akhir.
        $eresepHdrs = (array) data_get($dataRI, 'eresepHdr', []);
        $lastHdr = collect($eresepHdrs)->sortBy(function ($h) {
            try {
                return Carbon::createFromFormat('d/m/Y H:i:s', (string) data_get($h, 'resepDate', '01/01/2000 00:00:00'))->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        })->last() ?? [];

        $obatRows = '';
        foreach ((array) data_get($lastHdr, 'eresep', []) as $eo) {
            $nama = trim((string) data_get($eo, 'productName', ''));
            if ($nama === '') continue;
            $signaX = trim((string) data_get($eo, 'signaX', ''));
            $signaHari = trim((string) data_get($eo, 'signaHari', ''));
            $frek = $signaX !== '' || $signaHari !== '' ? trim($signaX . ' x ' . $signaHari) : '';
            $obatRows .=
                '<tr>' .
                '<td>' . e($nama) . '</td>' .
                '<td>' . e((string) data_get($eo, 'qty', '')) . '</td>' .
                '<td></td>' .
                '<td></td>' .
                '<td>' . ($frek === 'x' ? '' : e($frek)) . '</td>' .
                '<td></td>' .
                '<td>' . e((string) data_get($eo, 'catatanKhusus', '')) . '</td>' .
                '</tr>';
        }
        if ($obatRows === '') {
            $obatRows = '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }

        // Kondisi pulang ← SNOMED tindak lanjut
        $snomed = [
            '371827001' => 'Sembuh',
            '266707007' => 'Pulang Atas Permintaan Sendiri',
            '306206005' => 'Pulang Pindah / Rujuk',
            '371828006' => 'Membaik',
            '419099009' => 'Meninggal',
            '74964007' => 'Lain-lain',
        ];
        $kondisiPulang = e($snomed[trim((string) data_get($dataRI, 'perencanaan.tindakLanjut.tindakLanjut', ''))] ?? '');

        // Kontrol ← surat kontrol (SKDP) di datadaftarri_json.kontrol
        $kontrol = (array) data_get($dataRI, 'kontrol', []);
        $tglKontrolRaw = trim((string) data_get($kontrol, 'tglKontrol', ''));
        $kontrolHariTgl = '';
        if ($tglKontrolRaw !== '') {
            try {
                $kontrolHariTgl = Carbon::createFromFormat('d/m/Y', $tglKontrolRaw)->locale('id')->translatedFormat('l, d/m/Y');
            } catch (\Throwable) {
                $kontrolHariTgl = $tglKontrolRaw;
            }
        }
        $kontrolHariTgl = e($kontrolHariTgl);
        $poliKontrol = trim((string) data_get($kontrol, 'poliKontrolDesc', ''));
        $drKontrol = trim((string) data_get($kontrol, 'drKontrolDesc', ''));
        $tempatKontrol = e(trim($poliKontrol . ($drKontrol !== '' ? ' — ' . $drKontrol : '')));

        // Baris tabel 2-kolom (Label | Value) ber-garis — pola sama Resume Medis.
        $row = fn(string $label, string $value) =>
            "<tr><td class=\"text-muted\" style=\"width: 210px; border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top;\">{$label}</td>" .
            "<td style=\"border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top;\">{$value}</td></tr>";

        $ttv = 'TD: ' . ($td !== '' ? $td : '___') . ' mmHg &nbsp;&nbsp; Nadi: ' . ($nadi !== '' ? $nadi : '___')
            . ' x/mnt &nbsp;&nbsp; Suhu: ' . ($suhu !== '' ? $suhu : '___') . ' °C &nbsp;&nbsp; Pernafasan: '
            . ($nafas !== '' ? $nafas : '___') . ' x/mnt';

        $obatTable = '<table style="border-collapse:collapse;width:100%;"><thead><tr>'
            . '<th>Nama Obat</th><th>Jumlah</th><th>Dosis</th><th>Cara Pemberian</th>'
            . '<th>Frekuensi</th><th>Jam</th><th>Petunjuk Khusus</th>'
            . '</tr></thead><tbody>' . $obatRows . '</tbody></table>';

        $dokumenCell = '1) Hasil Lab: ___ Lbr<br>2) Foto Rontgen / CT Scan / MRI: ___ Lbr<br>3) USG / ECG: ___ Lbr<br>'
            . '4) Surat Asuransi: Ya / Tidak<br>5) Surat Ket. Sakit/Opname/Istirahat: Ya / Tidak<br>'
            . '6) Surat Kematian: Ya / Tidak<br>7) Surat Ket. Kelahiran: Ya / Tidak<br>8) Lain-lain: ';

        $rows = implode("\n", [
            '<tr><th colspan="2" style="border: 1px solid #cbd5e1; padding: 4px 6px; background:#f3f4f6; text-align:center;">OLEH PERAWAT / BIDAN</th></tr>',
            $row('Keadaan Waktu Masuk', ''),
            $row('Diagnosa', $diagnosa),
            $row('Komorbid', $komorbid),
            $row('Keadaan Umum', $keadaanUmum),
            $row('Tanda Vital', $ttv),
            $row('Tindakan Diagnostik &amp; Prosedur Terapi', $tindakan ?: ''),
            $row('Obat Saat Dirawat Inap', ''),
            $row('Efek Terapi Yang Diberikan', ''),
            $row('Obat Yang Diberikan Saat Pulang', $obatTable),
            $row('Dokumen Yang Diserahkan', $dokumenCell),
            $row('Keadaan Pasien Saat Pulang', $kondisiPulang),
            $row('Kontrol Ulang — Hari, Tanggal', $kontrolHariTgl),
            $row('Tempat Kontrol', $tempatKontrol),
            $row('Pendidikan Kesehatan', ''),
            $row('Diit', ''),
            $row('Catatan Khusus Untuk Pasien', ''),
        ]);

        return '<table class="w-full" style="border-collapse: collapse;">' . "\n" . $rows . "\n" . '</table>';
    }

    /* ═══════════════ SAVE ═══════════════ */
    public function save(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }

        $plain = trim(strip_tags((string) $this->ringkasanPulang));
        if (mb_strlen($plain) < 5) {
            $this->addError('ringkasanPulang', 'Ringkasan harus diisi (minimal 5 karakter teks).');
            return;
        }

        $this->validate(
            ['ringkasanPulang' => 'required|string|max:65000'],
            ['ringkasanPulang.required' => 'Ringkasan harus diisi.'],
        );

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use (&$dataRI) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI['ringkasanPulang'] = $this->ringkasanPulang;
                $dataRI['ringkasanPulangSavedBy'] = auth()->user()->myuser_name ?? '';
                $dataRI['ringkasanPulangSavedAt'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Simpan Ringkasan Pemulangan Pasien (perawat)', 'MR');
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Ringkasan pemulangan tersimpan.');
    }

    /* ═══════════════ CETAK PDF ═══════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return null;
        }

        $plain = trim(strip_tags((string) $this->ringkasanPulang));
        if (mb_strlen($plain) < 5) {
            $this->dispatch('toast', type: 'error', message: 'Ringkasan kosong — isi dulu sebelum dicetak.');
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
            'pages.components.rekam-medis.r-i.ringkasan-pulang-ri.ringkasan-pulang-ri-print',
            [
                'dataDaftarRi' => $dataRI,
                'dataPasien' => $pasienData,
                'ringkasanPulang' => $this->ringkasanPulang,
            ],
        )->setPaper('A4', 'portrait');

        $filename = 'ringkasan-pulang-ri-' . ($regNo ?: $this->riHdrNo) . '.pdf';
        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }

    public function closeEditor(): void
    {
        $this->reset(['riHdrNo', 'ringkasanPulang', 'isFormLocked']);
        $this->dispatch('close-modal', name: 'ringkasan-pulang-ri');
    }
};
?>

<div>
    <x-modal name="ringkasan-pulang-ri" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                @if (!empty($riHdrNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="ringkasan-pulang-ri-display-pasien-header-{{ $riHdrNo }}" />
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

            {{-- Body: SATU scroll saja (di dalam editor). Body tidak scroll sendiri;
                 editor di-stretch mengisi tinggi modal via .rp-editor .tox-tinymce { height:100% }. --}}
            <style>
                .rp-editor-wrap .tox-tinymce { height: 100% !important; }
            </style>
            <div class="flex flex-col flex-1 min-h-0 px-6 py-4 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between mb-1 gap-x-2 shrink-0">
                    <x-input-label value="Ringkasan Pemulangan Pasien (oleh Perawat / Bidan)" required class="!mb-0" />
                    <span class="text-xs text-muted dark:text-gray-400">Identitas pasien terisi otomatis saat dicetak. Sebagian field di-isi dari data EMR. Editor mendukung teks ala Word + tabel.</span>
                </div>
                <div class="flex-1 min-h-0 mt-1 rp-editor-wrap">
                    <x-tinymce-editor
                        name="ringkasanPulang"
                        placeholder="Ketik isi ringkasan pemulangan pasien..."
                        height="600"
                        modal-event="ringkasan-pulang-ri"
                        flush-event="ringkasan-pulang-ri.flush"
                        reload-event="ringkasan-pulang-ri.reload"
                        :content-style="'body{font-family:sans-serif;font-size:11px;line-height:1.4;color:#1f2937;} table{border-collapse:collapse;width:100%;} table td,table th{border:1px solid #cbd5e1;padding:3px 6px;vertical-align:top;} .text-muted{color:#6b7280;}'"
                        class="h-full" />
                </div>
                @error('ringkasanPulang')
                    <p class="mt-1 text-xs text-red-500 shrink-0">{{ $message }}</p>
                @enderror
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button"
                    wire:click="resetToDefault"
                    wire:confirm="Reset isi ke template default dari data EMR terbaru? Perubahan yang belum disimpan akan hilang."
                    wire:loading.attr="disabled" wire:target="resetToDefault"
                    class="text-xs">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span wire:loading.remove wire:target="resetToDefault">Reset ke Default</span>
                    <span wire:loading wire:target="resetToDefault"><x-loading /> Reset...</span>
                </x-secondary-button>

                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="closeEditor">Batal</x-secondary-button>

                    <x-secondary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('ringkasan-pulang-ri.flush')); $nextTick(() => $wire.cetakPdf())"
                        wire:loading.attr="disabled" wire:target="cetakPdf,save">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span wire:loading.remove wire:target="cetakPdf">Cetak PDF</span>
                        <span wire:loading wire:target="cetakPdf"><x-loading /> Cetak...</span>
                    </x-secondary-button>

                    <x-primary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('ringkasan-pulang-ri.flush')); $nextTick(() => $wire.save())"
                        wire:loading.attr="disabled" wire:target="save,cetakPdf">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save"><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
