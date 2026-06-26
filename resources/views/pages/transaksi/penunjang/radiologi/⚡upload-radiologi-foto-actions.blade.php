<?php
// resources/views/pages/transaksi/penunjang/radiologi/⚡upload-radiologi-foto-actions.blade.php
//
// Sibling action component — UPLOAD FOTO RADIOLOGI saja.
// Listen #[On('radiologi.foto.open')] → buka modal upload foto.
// Setelah upload sukses, dispatch 'radiologi-refresh' ke parent.

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use WithFileUploads, WithValidationToastTrait;

    public string $selectedSource = '';
    public ?int $selectedDtl = null;
    public ?int $selectedRefNo = null;
    public $fotoFile = null;

    #[On('radiologi.foto.open')]
    public function openUploadFotoModal(string $source, int $dtlNo, int $refNo): void
    {
        $this->selectedSource = $source;
        $this->selectedDtl = $dtlNo;
        $this->selectedRefNo = $refNo;
        $this->fotoFile = null;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'rad-upload-foto');
    }

    public function closeUploadFotoModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-upload-foto');
        $this->reset(['selectedSource', 'selectedDtl', 'selectedRefNo', 'fotoFile']);
    }

    public function uploadFoto(): void
    {
        $this->validateWithToast(
            ['fotoFile' => 'required|file|mimes:pdf,jpg,jpeg|max:5120'],
            [
                'fotoFile.required' => 'File foto harus dipilih.',
                'fotoFile.mimes' => 'Format harus PDF atau JPG.',
                'fotoFile.max' => 'Ukuran maksimal 5 MB.',
            ],
        );

        // Standar private disk: storage/app/private/upload/penunjang/radiologi/{dmYHis.ext}
        // (foto + hasil bacaan share folder yang sama — terhubung ke 1 share rad_path)
        // DB simpan nama file saja (path & namespace di-prepend saat render Lihat).
        $namespace = 'upload/penunjang/radiologi';

        try {
            // 1. Hapus file foto lama
            if ($this->selectedSource === 'RJ') {
                $existing = DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf_foto');
            } elseif ($this->selectedSource === 'UGD') {
                $existing = DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->value('rad_upload_pdf_foto');
            } else { // RI
                $existing = DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->value('rad_upload_pdf_foto');
            }
            if (!empty($existing) && is_string($existing)) {
                // Backward-compat: row lama bisa berisi 'Radiologi/Foto/x.pdf' (public legacy)
                // atau cuma 'x.pdf' (new). Coba hapus dari kedua disk untuk safety.
                if (str_contains($existing, '/') && Storage::disk('public')->exists($existing)) {
                    Storage::disk('public')->delete($existing);
                } elseif (Storage::disk('local')->exists($namespace . '/' . $existing)) {
                    Storage::disk('local')->delete($namespace . '/' . $existing);
                }
            }

            // 2. Simpan file baru ke private disk
            $ext = $this->fotoFile->getClientOriginalExtension();
            $filename = Carbon::now()->format('dmYHis') . '.' . $ext;
            $this->fotoFile->storeAs($namespace, $filename, 'local');

            // 3. Update DB — hanya nama file (tanpa path)
            if ($this->selectedSource === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf_foto' => $filename]);
            } elseif ($this->selectedSource === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $this->selectedDtl)->where('rj_no', $this->selectedRefNo)->update(['rad_upload_pdf_foto' => $filename]);
            } else { // RI
                DB::table('rstxn_riradiologs')->where('rirad_no', $this->selectedDtl)->where('rihdr_no', $this->selectedRefNo)->update(['rad_upload_pdf_foto' => $filename]);
            }

            $this->dispatch('toast', type: 'success', message: 'Foto radiologi berhasil di-upload.');
            $this->closeUploadFotoModal();
            $this->dispatch('radiologi-refresh');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ============================================ --}}
    {{-- MODAL: UPLOAD FOTO RADIOLOGI                 --}}
    {{-- ============================================ --}}
    <x-modal name="rad-upload-foto" size="lg" focusable>
        <div>
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold">Upload Foto Radiologi</h2>
                <p class="text-xs text-muted">Format PDF atau JPG, maks 5 MB.</p>
                @if (!empty($selectedSource) && !empty($selectedRefNo))
                    <p class="mt-1 text-xs font-mono text-muted-soft">
                        Sumber: <span class="font-semibold">{{ $selectedSource }}</span>
                        · Ref: <span class="font-semibold">{{ $selectedRefNo }}</span>
                        · Dtl: <span class="font-semibold">{{ $selectedDtl }}</span>
                    </p>
                @endif
            </div>
            <div class="px-6 py-5 space-y-4">
                <x-file-upload
                    name="fotoFile"
                    label="File Foto"
                    accept="application/pdf,image/jpeg"
                    required
                />
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeUploadFotoModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="uploadFoto" wire:loading.attr="disabled" wire:target="uploadFoto,fotoFile">
                    <span wire:loading.remove wire:target="uploadFoto">Upload</span>
                    <span wire:loading wire:target="uploadFoto"><x-loading /> Uploading...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
