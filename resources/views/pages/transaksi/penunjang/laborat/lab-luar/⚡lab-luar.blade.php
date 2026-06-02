<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithPagination, WithFileUploads;

    /*
     | Modul: Upload Hasil Lab Luar
     | Khusus upload file PDF hasil pemeriksaan dari laboratorium luar.
     | List HANYA hdr lab luar yang sudah Selesai (checkup_status='H' = lab admin sudah
     | proses & finalisasi). Pembeda di sini: PDF sudah di-upload (pdf_path NOT NULL) vs
     | belum (pdf_path NULL).
     | Post tarif & finalisasi hasil dilakukan di Administrasi Laborat (pemeriksaan-luar-laborat).
     */

    public string $searchKeyword = '';
    public string $filterPdf = 'belum';
    public string $filterSource = '';
    public string $filterBulan = ''; // mm/yyyy, default bulan ini
    public int $itemsPerPage = 15;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public ?int $selectedCheckupNo = null;
    public ?int $selectedDtl = null;
    public string $pdfKeterangan = '';
    public $pdfFile = null;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterPdf(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterSource']);
        $this->filterPdf = 'belum';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price', 'o.labout_result',
                'o.pdf_path', 'o.keterangan',
                'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'h.status_rjri', 'h.ref_no',
                'h.checkup_status',
                'd.dr_name',
                'h.checkup_date',
            )
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl');

        // Hanya pemeriksaan yang sudah Selesai (Hasil siap) — checkup_status='H'.
        // Lab admin selesai entry hasil dulu di pemeriksaan-luar-laborat → status H,
        // baru di sini bisa upload PDF supporting document.
        $q->where('h.checkup_status', 'H');

        if ($this->filterPdf === 'ada') {
            $q->whereNotNull('o.pdf_path');
        } elseif ($this->filterPdf === 'belum') {
            $q->whereNull('o.pdf_path');
        }
        if ($this->filterSource !== '') {
            $q->where('h.status_rjri', $this->filterSource);
        }
        // Filter bulan (mm/yyyy) → EXTRACT month + year dari checkup_date (pola radiologi)
        $bulan = trim($this->filterBulan);
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $bulan, $mm)) {
            $bln = (int) $mm[1];
            $thn = (int) $mm[2];
            if ($bln >= 1 && $bln <= 12) {
                $q->whereRaw('EXTRACT(MONTH FROM h.checkup_date) = ?', [$bln])
                    ->whereRaw('EXTRACT(YEAR FROM h.checkup_date) = ?', [$thn]);
            }
        }
        $kw = trim($this->searchKeyword);
        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                $up = '%' . mb_strtoupper($kw) . '%';
                $w->whereRaw('UPPER(p.reg_name) LIKE ?', [$up])
                    ->orWhereRaw('TO_CHAR(h.reg_no) LIKE ?', ['%' . $kw . '%'])
                    ->orWhereRaw('UPPER(o.labout_desc) LIKE ?', [$up]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    /* ===============================
     | OPEN UPLOAD MODAL — PDF per dtl (1 dtl = 1 PDF)
     =============================== */
    public function openUploadModal(int $checkupNo, int $dtl): void
    {
        $row = DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $checkupNo)
            ->where('labout_dtl', $dtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Detail tidak ditemukan.');
            return;
        }
        $this->selectedCheckupNo = $checkupNo;
        $this->selectedDtl = $dtl;
        $this->pdfFile = null;
        $this->pdfKeterangan = $row->keterangan ?? '';
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'lab-luar-upload');
    }

    public function closeUploadModal(): void
    {
        $this->dispatch('close-modal', name: 'lab-luar-upload');
        $this->selectedCheckupNo = null;
        $this->selectedDtl = null;
        $this->pdfFile = null;
        $this->pdfKeterangan = '';
    }

    public function uploadHasil(): void
    {
        $this->validate(
            [
                'pdfFile' => 'required|file|mimes:pdf,jpg,jpeg|max:5120',
                'pdfKeterangan' => 'nullable|string|max:4000',
            ],
            [
                'pdfFile.required' => 'File harus dipilih.',
                'pdfFile.mimes' => 'Format harus PDF atau JPG.',
                'pdfFile.max' => 'Ukuran maksimal 5 MB.',
            ],
        );

        $row = DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->selectedCheckupNo)
            ->where('labout_dtl', $this->selectedDtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Detail tidak ditemukan.');
            return;
        }

        // Standar: private disk, filename dmYHis, DB simpan filename only.
        $namespace = 'upload/penunjang/lab-luar';

        try {
            // Hapus file lama — backward-compat untuk legacy full-path
            if (!empty($row->pdf_path) && is_string($row->pdf_path)) {
                if (str_contains($row->pdf_path, '/') && Storage::disk('public')->exists($row->pdf_path)) {
                    Storage::disk('public')->delete($row->pdf_path);
                } elseif (Storage::disk('local')->exists($namespace . '/' . $row->pdf_path)) {
                    Storage::disk('local')->delete($namespace . '/' . $row->pdf_path);
                }
            }

            $ext = $this->pdfFile->getClientOriginalExtension();
            $filename = Carbon::now()->format('dmYHis') . '.' . $ext;
            $this->pdfFile->storeAs($namespace, $filename, 'local');

            $keterangan = trim($this->pdfKeterangan);
            DB::transaction(function () use ($row, $filename, $keterangan) {
                DB::table('lbtxn_checkupoutdtls')
                    ->where('checkup_no', $row->checkup_no)
                    ->where('labout_dtl', $row->labout_dtl)
                    ->update([
                        'pdf_path' => $filename,
                        'keterangan' => $keterangan === '' ? null : $keterangan,
                    ]);

                // Update checkup_status hdr → H (Selesai) saat PDF di-upload
                DB::table('lbtxn_checkuphdrs')
                    ->where('checkup_no', $row->checkup_no)
                    ->update(['checkup_status' => 'H']);
            });

            $this->dispatch('toast', type: 'success', message: 'Hasil PDF berhasil di-upload.');
            $this->closeUploadModal();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

};
?>

<div>
    <x-page-title
        title="Upload Hasil Lab Luar"
        subtitle="Upload PDF hasil pemeriksaan dari laboratorium luar (tarif & batal di Administrasi Laborat)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col px-6 pt-4 pb-6 bg-white dark:bg-gray-800">

    <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div>
                <x-input-label value="Cari" />
                <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                    placeholder="reg_no / nama / pemeriksaan" />
            </div>
            <div>
                <x-input-label value="Bulan" />
                <x-text-input wire:model.live.debounce.500ms="filterBulan" class="block w-full mt-1"
                    placeholder="mm/yyyy" maxlength="7" />
            </div>
            <div>
                <x-input-label value="Status PDF" />
                <select wire:model.live="filterPdf"
                    class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                    <option value="">Semua</option>
                    <option value="belum">Belum di-upload</option>
                    <option value="ada">Sudah di-upload</option>
                </select>
            </div>
            <div>
                <x-input-label value="Sumber" />
                <select wire:model.live="filterSource"
                    class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                    <option value="">Semua</option>
                    <option value="RJ">RJ</option>
                    <option value="UGD">UGD</option>
                    <option value="RI">RI</option>
                </select>
            </div>
            <div class="flex items-end">
                <x-secondary-button type="button" wire:click="resetFilters">Reset</x-secondary-button>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
            <table class="w-full text-sm text-left">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                        <th class="px-4 py-3">Tgl Order</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Pasien</th>
                        <th class="px-4 py-3">Pemeriksaan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-center">PDF</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows as $r)
                        <tr wire:key="lab-luar-row-{{ $r->labout_dtl ?? $loop->index }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-badge variant="alternative">{{ $r->status_rjri }}</x-badge>
                                <span class="ml-1 font-mono text-xs text-gray-500">{{ $r->ref_no }}</span>
                            </td>
                            <td class="px-4 py-3 space-y-1 align-top">
                                <div class="text-sm font-mono text-gray-500">{{ $r->reg_no ?? '-' }}</div>
                                <div class="text-base font-semibold text-brand dark:text-white">
                                    {{ $r->reg_name ?? '-' }} /
                                    ({{ $r->sex === 'L' ? 'Laki-Laki' : ($r->sex === 'P' ? 'Perempuan' : '-') }})
                                </div>
                                @if (!empty($r->birth_date))
                                    @php
                                        try {
                                            $tglLahir = \Carbon\Carbon::createFromFormat('d/m/Y', $r->birth_date);
                                            $diff = $tglLahir->diff(now());
                                            $umur = "{$r->birth_date} ({$diff->y} Thn {$diff->m} Bln)";
                                        } catch (\Exception $e) {
                                            $umur = '-';
                                        }
                                    @endphp
                                    <div class="text-xs text-gray-500">{{ $umur }}</div>
                                @endif
                                <div class="text-xs text-gray-500">{{ $r->address ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $r->labout_desc }}
                                @if ($r->labout_result)
                                    <p class="text-xs italic text-gray-500">Catatan klinis: {{ $r->labout_result }}</p>
                                @endif
                                @if ($r->keterangan)
                                    <p class="text-xs italic text-amber-700">Keterangan: {{ $r->keterangan }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($r->labout_price !== null)
                                    Rp {{ number_format($r->labout_price) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($r->pdf_path)
                                    <x-badge variant="success">Sudah</x-badge>
                                @else
                                    <x-badge variant="warning">Belum</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($r->pdf_path)
                                    @php
                                        // Backward-compat: legacy full-path → asset(public); new filename → route(private)
                                        $pdfUrl = str_contains($r->pdf_path, '/')
                                            ? asset('storage/' . $r->pdf_path)
                                            : route('files.show', ['path' => 'mount/penunjang/lab-luar/' . $r->pdf_path]);
                                    @endphp
                                    <a href="{{ $pdfUrl }}" target="_blank"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Lihat PDF
                                    </a>
                                    <x-secondary-button type="button"
                                        wire:click="openUploadModal({{ $r->checkup_no }}, {{ $r->labout_dtl }})"
                                        class="text-xs">Replace</x-secondary-button>
                                @else
                                    <x-primary-button type="button"
                                        wire:click="openUploadModal({{ $r->checkup_no }}, {{ $r->labout_dtl }})"
                                        class="text-xs">Upload Hasil</x-primary-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Tidak ada pemeriksaan lab luar yang sudah Selesai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-100 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
            {{ $this->rows->links() }}
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- MODAL: UPLOAD HASIL                          --}}
    {{-- ============================================ --}}
    <x-modal name="lab-luar-upload" size="lg" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold">Upload Hasil Lab Luar</h2>
                <p class="text-xs text-gray-500">Format PDF atau JPG, maks 5 MB.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <x-file-upload
                    name="pdfFile"
                    label="File"
                    accept="application/pdf,image/jpeg"
                    required
                />

                <div>
                    <x-input-label value="Keterangan (opsional)" />
                    <textarea wire:model.defer="pdfKeterangan" rows="3"
                        placeholder="contoh: PCR Covid-19 PRODIA / BTA — sample 2 dari 3 / Patologi Anatomi"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                    @error('pdfKeterangan')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeUploadModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="uploadHasil" wire:loading.attr="disabled" wire:target="uploadHasil,pdfFile">
                    <span wire:loading.remove wire:target="uploadHasil">Upload</span>
                    <span wire:loading wire:target="uploadHasil"><x-loading /> Uploading...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>

    </div>
</div>
