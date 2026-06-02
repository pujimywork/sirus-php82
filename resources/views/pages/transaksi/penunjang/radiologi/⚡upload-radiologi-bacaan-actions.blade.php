<?php
// resources/views/pages/transaksi/penunjang/radiologi/⚡upload-radiologi-bacaan-actions.blade.php
//
// Sibling action component — HASIL BACAAN saja (2 modal):
//   - rad-upload-pdf  : Upload PDF manual ke kolom rad_upload_pdf
//   - rad-generate    : Generate PDF dari narasi TinyMCE, simpan ke
//                       hasil_bacaan (CLOB) + render PDF ke rad_upload_pdf.
//
// Listener events dari halaman utama:
//   - radiologi.bacaan.upload.open   (source, dtlNo, refNo)
//   - radiologi.bacaan.generate.open (source, dtlNo, refNo)
//
// Setelah save sukses, dispatch 'radiologi-refresh' ke parent.

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public string $selectedSource = '';
    public ?int $selectedDtl = null;
    public ?int $selectedRefNo = null;

    public $pdfFile = null;
    public string $hasilBacaan = '';
    public ?string $drRadiologi = null;

    /* ===============================
     | OPEN UPLOAD PDF (HASIL BACAAN) MODAL
     =============================== */
    #[On('radiologi.bacaan.upload.open')]
    public function openUploadPdfModal(string $source, int $dtlNo, int $refNo): void
    {
        $this->selectedSource = $source;
        $this->selectedDtl = $dtlNo;
        $this->selectedRefNo = $refNo;
        $this->pdfFile = null;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'rad-upload-pdf');
    }

    public function closeUploadPdfModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-upload-pdf');
        $this->reset(['selectedSource', 'selectedDtl', 'selectedRefNo', 'pdfFile']);
    }

    public function uploadPdf(): void
    {
        $this->validate(
            ['pdfFile' => 'required|file|mimes:pdf,jpg,jpeg|max:5120'],
            [
                'pdfFile.required' => 'File harus dipilih.',
                'pdfFile.mimes' => 'Format harus PDF atau JPG.',
                'pdfFile.max' => 'Ukuran maksimal 5 MB.',
            ],
        );

        // Standar private disk: storage/app/private/radiologi-hasil/{dmYHis.pdf}
        $namespace = 'upload/penunjang/radiologi';

        try {
            // 1. Hapus PDF lama
            if ($this->selectedSource === 'RJ') {
                $existing = DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf');
            } elseif ($this->selectedSource === 'UGD') {
                $existing = DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf');
            } else { // RI
                $existing = DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->value('rad_upload_pdf');
            }
            if (!empty($existing) && is_string($existing)) {
                if (str_contains($existing, '/') && Storage::disk('public')->exists($existing)) {
                    Storage::disk('public')->delete($existing);
                } elseif (Storage::disk('local')->exists($namespace . '/' . $existing)) {
                    Storage::disk('local')->delete($namespace . '/' . $existing);
                }
            }

            // 2. Simpan PDF baru ke private disk
            $filename = Carbon::now()->format('dmYHis') . '.pdf';
            $this->pdfFile->storeAs($namespace, $filename, 'local');

            // 3. Update DB — hanya nama file
            if ($this->selectedSource === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            } elseif ($this->selectedSource === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            } else { // RI
                DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            }

            $this->dispatch('toast', type: 'success', message: 'Hasil bacaan PDF berhasil di-upload.');
            $this->closeUploadPdfModal();
            $this->dispatch('radiologi-refresh');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN GENERATE HASIL BACAAN MODAL
     =============================== */
    #[On('radiologi.bacaan.generate.open')]
    public function openGenerateModal(string $source, int $dtlNo, int $refNo): void
    {
        $this->selectedSource = $source;
        $this->selectedDtl = $dtlNo;
        $this->selectedRefNo = $refNo;
        $this->resetValidation();

        $row = $this->getSelectedRowFull();
        $this->hasilBacaan = $row && !empty($row->hasil_bacaan) ? (string) $row->hasil_bacaan : '';
        $this->drRadiologi = $row && !empty($row->dr_radiologi) ? (string) $row->dr_radiologi : null;

        $this->dispatch('open-modal', name: 'rad-generate');
    }

    public function closeGenerateModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-generate');
        $this->reset(['selectedSource', 'selectedDtl', 'selectedRefNo', 'hasilBacaan', 'drRadiologi']);
    }

    #[Computed]
    public function dokterRadiologOptions()
    {
        return DB::table('rsmst_doctors as d')
            ->join('rsmst_polis as p', 'd.poli_id', '=', 'p.poli_id')
            ->whereRaw("UPPER(p.poli_desc) LIKE '%RADIOLOG%'")
            ->where('d.active_status', '1')
            ->orderBy('d.dr_name', 'asc')
            ->get(['d.dr_id', 'd.dr_name']);
    }

    public function generatePdf(): void
    {
        $plain = trim(strip_tags((string) $this->hasilBacaan));
        if (mb_strlen($plain) < 5) {
            $this->addError('hasilBacaan', 'Hasil bacaan harus diisi (minimal 5 karakter teks).');
            return;
        }

        $this->validate(
            [
                'hasilBacaan' => 'required|string|max:65000',
                'drRadiologi' => 'required|string|max:20',
            ],
            [
                'hasilBacaan.required' => 'Hasil bacaan harus diisi.',
                'drRadiologi.required' => 'Dokter radiolog harus dipilih.',
            ],
        );

        $header = $this->buildPrintHeader();
        if (!$header) {
            $this->dispatch('toast', type: 'error', message: 'Data order tidak ditemukan.');
            return;
        }

        try {
            // 1. Simpan teks hasil_bacaan + dr_radiologi ke DB (source of truth)
            $payload = [
                'hasil_bacaan' => $this->hasilBacaan,
                'dr_radiologi' => $this->drRadiologi,
            ];
            if ($this->selectedSource === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update($payload);
            } elseif ($this->selectedSource === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update($payload);
            } else { // RI
                DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->update($payload);
            }

            // 2. Render PDF dari template
            $header->hasil_bacaan = $this->hasilBacaan;
            $header->dr_radiologi = $this->drRadiologi;
            $pdf = Pdf::loadView('pages.components.rekam-medis.penunjang.radiologi-display.radiologi-display-print', [
                'header' => $header,
            ])->setPaper('A4', 'portrait');

            // 3. Hapus PDF lama (kalau ada)
            $namespace = 'upload/penunjang/radiologi';
            if ($this->selectedSource === 'RJ') {
                $existing = DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf');
            } elseif ($this->selectedSource === 'UGD') {
                $existing = DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf');
            } else { // RI
                $existing = DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->value('rad_upload_pdf');
            }
            if (!empty($existing) && is_string($existing)) {
                if (str_contains($existing, '/') && Storage::disk('public')->exists($existing)) {
                    Storage::disk('public')->delete($existing);
                } elseif (Storage::disk('local')->exists($namespace . '/' . $existing)) {
                    Storage::disk('local')->delete($namespace . '/' . $existing);
                }
            }

            // 4. Simpan PDF baru ke private disk
            $filename = Carbon::now()->format('dmYHis') . '.pdf';
            Storage::disk('local')->put($namespace . '/' . $filename, $pdf->output());

            // 5. Update DB — hanya nama file
            if ($this->selectedSource === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            } elseif ($this->selectedSource === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            } else { // RI
                DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->update(['rad_upload_pdf' => $filename]);
            }

            $this->dispatch('toast', type: 'success', message: 'Hasil bacaan ter-generate & PDF tersimpan.');
            $this->closeGenerateModal();
            $this->dispatch('radiologi-refresh');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal generate: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS — query data row utk pre-fill modal & build print header
     =============================== */
    private function getSelectedRowFull(): ?object
    {
        if ($this->selectedSource === 'RJ') {
            $row = DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->select(['rad_upload_pdf', 'rad_upload_pdf_foto', 'dr_radiologi', 'hasil_bacaan'])->first();
        } elseif ($this->selectedSource === 'UGD') {
            $row = DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->select(['rad_upload_pdf', 'rad_upload_pdf_foto', 'dr_radiologi', 'hasil_bacaan'])->first();
        } elseif ($this->selectedSource === 'RI') {
            $row = DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->select(['rad_upload_pdf', 'rad_upload_pdf_foto', 'dr_radiologi', 'hasil_bacaan'])->first();
        } else {
            return null;
        }

        if ($row && is_resource($row->hasil_bacaan ?? null)) {
            $row->hasil_bacaan = stream_get_contents($row->hasil_bacaan);
        }
        return $row;
    }

    private function buildPrintHeader(): ?object
    {
        if ($this->selectedSource === 'RJ') {
            return DB::table('rstxn_rjrads as r')->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')->join('rstxn_rjhdrs as h', 'r.rj_no', '=', 'h.rj_no')->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')->where('r.rad_dtl', $this->selectedDtl)->where('r.rj_no', $this->selectedRefNo)->select('p.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.birth_place', 'p.address', 'm.rad_desc', 'r.dr_pengirim', 'r.dr_radiologi', 'r.keterangan', 'r.waktu_entry')->first();
        }
        if ($this->selectedSource === 'UGD') {
            return DB::table('rstxn_ugdrads as r')->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')->where('r.rad_dtl', $this->selectedDtl)->where('r.rj_no', $this->selectedRefNo)->select('p.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.birth_place', 'p.address', 'm.rad_desc', 'r.dr_pengirim', 'r.dr_radiologi', 'r.keterangan', 'r.waktu_entry')->first();
        }
        if ($this->selectedSource === 'RI') {
            return DB::table('rstxn_riradiologs as r')->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')->join('rstxn_rihdrs as h', 'r.rihdr_no', '=', 'h.rihdr_no')->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')->where('r.rirad_no', $this->selectedDtl)->where('r.rihdr_no', $this->selectedRefNo)->select('p.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.birth_place', 'p.address', 'm.rad_desc', 'r.dr_pengirim', 'r.dr_radiologi', 'r.keterangan', 'r.waktu_entry')->first();
        }
        return null;
    }

};
?>

<div>
    {{-- ============================================ --}}
    {{-- MODAL: GENERATE HASIL BACAAN                 --}}
    {{-- Pola sama dgn resume-medis-ri: TinyMCE + flex h-full + sticky footer.
         Beda: defaultnya kosong (tidak ada pre-fill template — beda dgn resume
         medis yang auto-fill dari pengkajian). Editor support table untuk
         tabel pengukuran (mis. ukuran fraktur, dimensi tumor). --}}
    {{-- ============================================ --}}
    <x-modal name="rad-generate" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        Tulis Hasil Bacaan Radiologi
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Isi narasi hasil bacaan, pilih dokter radiolog yang menandatangani, lalu klik <strong>Generate &amp; Simpan</strong> — PDF otomatis tersusun dan tersimpan ke kolom Hasil Bacaan.
                    </p>
                    @if (!empty($selectedSource) && !empty($selectedRefNo))
                        <p class="mt-1 text-xs font-mono text-gray-400">
                            Sumber: <span class="font-semibold">{{ $selectedSource }}</span>
                            · Ref: <span class="font-semibold">{{ $selectedRefNo }}</span>
                            · Dtl: <span class="font-semibold">{{ $selectedDtl }}</span>
                        </p>
                    @endif
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeGenerateModal" class="shrink-0">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- Body — scrollable --}}
            <div class="flex-1 px-6 py-5 space-y-4 overflow-y-auto">
                <div>
                    <x-input-label value="Dokter Radiolog (TTD)" required />
                    <select wire:model="drRadiologi"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Pilih Dokter —</option>
                        @foreach ($this->dokterRadiologOptions as $d)
                            <option value="{{ $d->dr_id }}">{{ $d->dr_name }}</option>
                        @endforeach
                    </select>
                    @error('drRadiologi')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <x-input-label value="Hasil Bacaan" required />
                    <x-tinymce-editor
                        name="hasilBacaan"
                        placeholder="Tulis hasil bacaan radiologi… (defaultnya kosong, isi manual)"
                        height="500"
                        modal-event="rad-generate"
                        flush-event="rad-generate.flush"
                        class="mt-1"
                    />
                    @error('hasilBacaan')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Toolbar Word-style + <strong>Table</strong> support (untuk tabel pengukuran fraktur/tumor/dimensi). Disimpan sebagai HTML ke kolom <code>hasil_bacaan</code>.</p>
                </div>
            </div>

            {{-- Footer — sticky bottom --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeGenerateModal">Batal</x-secondary-button>
                <x-primary-button type="button"
                    x-on:click="window.dispatchEvent(new Event('rad-generate.flush')); $nextTick(() => $wire.generatePdf())"
                    wire:loading.attr="disabled" wire:target="generatePdf">
                    <span wire:loading.remove wire:target="generatePdf">Generate &amp; Simpan</span>
                    <span wire:loading wire:target="generatePdf"><x-loading /> Generating...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>

    {{-- ============================================ --}}
    {{-- MODAL: UPLOAD PDF HASIL BACAAN               --}}
    {{-- ============================================ --}}
    <x-modal name="rad-upload-pdf" size="lg" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold">Upload Hasil Bacaan Radiologi</h2>
                <p class="text-xs text-gray-500">Format PDF atau JPG, maks 5 MB.</p>
                @if (!empty($selectedSource) && !empty($selectedRefNo))
                    <p class="mt-1 text-xs font-mono text-gray-400">
                        Sumber: <span class="font-semibold">{{ $selectedSource }}</span>
                        · Ref: <span class="font-semibold">{{ $selectedRefNo }}</span>
                        · Dtl: <span class="font-semibold">{{ $selectedDtl }}</span>
                    </p>
                @endif
            </div>
            <div class="px-6 py-5 space-y-4">
                <x-file-upload
                    name="pdfFile"
                    label="File PDF"
                    accept="application/pdf,image/jpeg"
                    required
                />
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeUploadPdfModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="uploadPdf" wire:loading.attr="disabled" wire:target="uploadPdf,pdfFile">
                    <span wire:loading.remove wire:target="uploadPdf">Upload</span>
                    <span wire:loading wire:target="uploadPdf"><x-loading /> Uploading...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
