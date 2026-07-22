<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/permintaan-darah-ri/rm-permintaan-darah-ri-actions.blade.php
//
// FORMULIR PERMINTAAN DARAH (RI) — permintaan komponen darah untuk transfusi.
// Digital = Bagian 1 (diisi petugas RS) + TTD Dokter peminta → kunci → cetak.
// Bagian 2 (diisi Petugas PMI: labu, IMLTD, crossmatch, TTD pengirim/penerima)
// SENGAJA dicetak KOSONG untuk diisi manual — PMI pihak eksternal, bukan user sistem.
// Pola modul-dokumen RI multi-entri: draft → TTD Dokter (kunci) → Lihat/Cetak; buka-kunci Admin/Manager.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\PermintaanDarahOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $form = [];
    public ?string $editingKey = null;   // id entri yang sedang diedit; null = entri baru
    public bool $viewOnly = false;        // entri terkunci ditampilkan read-only

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-permintaan-darah-ri'];

    // Opsi (label & pilihan) — sumber tunggal di App\Support\PermintaanDarahOptions.
    public array $jenisOptions = PermintaanDarahOptions::JENIS;
    public array $golonganOptions = PermintaanDarahOptions::GOLONGAN;
    public array $rhesusOptions = PermintaanDarahOptions::RHESUS;
    public array $satuanOptions = PermintaanDarahOptions::SATUAN;
    public array $transfusiOptions = PermintaanDarahOptions::TRANSFUSI;

    /* ===============================
     | MOUNT / OPEN / CLOSE
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-permintaan-darah-ri']);

        $this->form = $this->defaultForm();

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarRi['permintaanDarahRI'] ??= [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $data = $this->findDataRI($this->riHdrNo);
        if ($data) {
            $this->dataDaftarRi = $data;
            $this->regNo = $data['regNo'] ?? $this->regNo;
            $this->dataDaftarRi['permintaanDarahRI'] ??= [];
            $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        }

        $this->resetFormDarah();
        $this->dispatch('open-modal', name: "rm-permintaan-darah-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: "rm-permintaan-darah-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | DEFAULT FORM
     =============================== */
    private function defaultForm(): array
    {
        return [
            'tglPermintaan' => '',
            'transfusiSebelumnya' => 'belum',   // belum | pernah — default off (toggle)
            'diagnosaSementara' => '',
            'indikasiTransfusi' => '',
            'jenisDarah' => [
                'wb' => $this->defaultBaris(),
                'prc' => $this->defaultBaris(),
                'ffp' => $this->defaultBaris(),
                'lainnya' => array_merge($this->defaultBaris(), ['ket1' => '', 'ket2' => '']),
            ],
            'ttd' => [
                'dokterNama' => '',
                'dokterCode' => '',
                'dokterDate' => '',
            ],
        ];
    }

    private function defaultBaris(): array
    {
        // 'diperlukan' = tanggal + jam jadi satu string dd/mm/yyyy HH:MM:SS.
        return ['pilih' => false, 'golongan' => '', 'rhesus' => '', 'jumlah' => '', 'satuan' => 'Unit', 'diperlukan' => ''];
    }

    private function prefillDariEmr(): void
    {
        if (empty($this->form['tglPermintaan'])) {
            $this->form['tglPermintaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
        // Diagnosa masuk sebagai bantuan awal — tetap bisa dikoreksi.
        $diagnosa = data_get($this->dataDaftarRi, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', '');
        if (filled($diagnosa) && empty($this->form['diagnosaSementara'])) {
            $this->form['diagnosaSementara'] = (string) $diagnosa;
        }
    }

    /* ===============================
     | VALIDATION
     =============================== */
    private function darahRules(): array
    {
        $rules = [
            'form.tglPermintaan' => 'required|date_format:d/m/Y H:i:s',
            'form.transfusiSebelumnya' => 'required|in:belum,pernah',
            'form.diagnosaSementara' => 'required|string|max:500',
            'form.indikasiTransfusi' => 'required|string|max:500',
        ];

        $messages = [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute belum dipilih.',
            'form.tglPermintaan.date_format' => 'Tanggal permintaan — format: dd/mm/yyyy hh:mm:ss.',
        ];

        $attributes = [
            'form.tglPermintaan' => 'Tanggal permintaan',
            'form.transfusiSebelumnya' => 'Transfusi sebelumnya',
            'form.diagnosaSementara' => 'Diagnosa sementara',
            'form.indikasiTransfusi' => 'Indikasi transfusi',
        ];

        return [$rules, $messages, $attributes];
    }

    /**
     * Validasi tabel jenis darah: minimal satu jenis dipilih, tiap yang dipilih
     * wajib golongan + jumlah. Mengembalikan true bila ADA error (sudah di-addError).
     */
    private function validasiJenisDarah(): bool
    {
        $adaError = false;
        $adaTerpilih = false;

        foreach (array_keys($this->jenisOptions) as $kode) {
            if (empty($this->form['jenisDarah'][$kode]['pilih'])) {
                continue;
            }
            $adaTerpilih = true;

            if (blank($this->form['jenisDarah'][$kode]['golongan'] ?? '')) {
                $this->addError("form.jenisDarah.$kode.golongan", 'Golongan darah wajib diisi.');
                $adaError = true;
            }
            if (blank($this->form['jenisDarah'][$kode]['jumlah'] ?? '')) {
                $this->addError("form.jenisDarah.$kode.jumlah", 'Jumlah wajib diisi.');
                $adaError = true;
            }
            if (blank($this->form['jenisDarah'][$kode]['diperlukan'] ?? '')) {
                $this->addError("form.jenisDarah.$kode.diperlukan", 'Diperlukan tgl wajib diisi.');
                $adaError = true;
            }
        }

        if (!$adaTerpilih) {
            $this->addError('form.jenisDarah', 'Pilih minimal satu jenis darah yang diperlukan.');
            $adaError = true;
        }

        return $adaError;
    }

    /* ===============================
     | SIMPAN DRAFT (tanpa validasi penuh)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->form['tglPermintaan'])) {
            $this->form['tglPermintaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        $id = $this->editingKey ?: (string) Str::uuid();
        try {
            $this->persistEntry($id, false, 'Simpan draft');
            $this->resetFormDarah();
            $this->dispatch('toast', type: 'success', message: 'Draft permintaan darah disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD DOKTER = validasi penuh + stempel + KUNCI entri (pola baku).
     =============================== */
    public function ttdDokter(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!auth()->user()?->hasAnyRole(['Dokter', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya dokter yang berwenang menandatangani permintaan darah.');
            return;
        }

        if (empty($this->form['tglPermintaan'])) {
            $this->form['tglPermintaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // Field skalar wajib memerah dulu (jangan short-circuit sebelum validate).
        [$rules, $messages, $attributes] = $this->darahRules();
        $this->validateWithToast($rules, $messages, $attributes);

        // Tabel jenis darah divalidasi manual (min. satu + golongan/jumlah).
        if ($this->validasiJenisDarah()) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi jenis darah yang diminta.');
            return;
        }

        $this->form['ttd']['dokterNama'] = auth()->user()->myuser_name ?? '';
        $this->form['ttd']['dokterCode'] = auth()->user()->myuser_code ?? '';
        $this->form['ttd']['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $id = $this->editingKey ?: (string) Str::uuid();
        try {
            $this->persistEntry($id, true, 'TTD Dokter (Kunci)');
            $this->resetFormDarah();
            $this->dispatch('toast', type: 'success', message: 'Permintaan darah ditandatangani dokter dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PERSIST
     =============================== */
    private function persistEntry(string $id, bool $finalize, string $logVerb): void
    {
        DB::transaction(function () use ($id, $finalize, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            $list = $fresh['permintaanDarahRI'] ?? [];
            if (!is_array($list)) {
                $list = [];
            }

            $index = collect($list)->search(fn($it) => ($it['id'] ?? null) === $id);

            // Entri final tak boleh ditimpa lewat draft/edit.
            if ($index !== false && $this->entryIsFinal($list[$index]) && !$finalize) {
                throw new \RuntimeException('Entri sudah dikunci — tidak dapat diubah.');
            }

            $entry = $index !== false ? $list[$index] : [
                'id' => $id,
                'created_at' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                'created_by' => ['name' => auth()->user()->myuser_name ?? '', 'code' => auth()->user()->myuser_code ?? ''],
            ];

            $entry['form'] = $this->form;
            $entry['finalized'] = $finalize ? true : ($entry['finalized'] ?? false);
            $entry['updated_at'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            if ($index !== false) {
                $list[$index] = $entry;
            } else {
                $list[] = $entry;
            }

            $fresh['permintaanDarahRI'] = array_values($list);
            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI(
                (int) $this->riHdrNo,
                $logVerb . ' Permintaan Darah — ' . ($this->form['tglPermintaan'] ?? '-')
                    . ' (' . ($this->ringkasJenis() ?: '-') . ')',
                'MR',
            );
        });

        $this->incrementVersion('modal-permintaan-darah-ri');
    }

    /** Ringkasan jenis darah terpilih untuk log/daftar. */
    private function ringkasJenis(): string
    {
        return collect($this->jenisOptions)
            ->filter(fn($label, $kode) => !empty($this->form['jenisDarah'][$kode]['pilih']))
            ->values()->implode(', ');
    }

    /* ===============================
     | ENTRI (edit / view / hapus)
     =============================== */
    private function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : filled(data_get($e, 'form.ttd.dokterNama'));
    }

    public function editEntri(string $id): void
    {
        $entri = collect($this->dataDaftarRi['permintaanDarahRI'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->editingKey = $id;
        $this->form = array_replace_recursive($this->defaultForm(), $entri['form'] ?? []);
        $this->viewOnly = $this->entryIsFinal($entri);
        $this->resetValidation();
        $this->incrementVersion('modal-permintaan-darah-ri');
    }

    public function hapusEntri(string $id): void
    {
        if (!auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis', 'Supervisor', 'Mr'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['permintaanDarahRI'] ?? [];
                $newList = collect($list)->reject(fn($it) => ($it['id'] ?? null) === $id)->values()->all();

                $fresh['permintaanDarahRI'] = $newList;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Permintaan Darah — entri ' . $id, 'MR');
            });

            if ($this->editingKey === $id) {
                $this->resetFormDarah();
            }
            $this->incrementVersion('modal-permintaan-darah-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri permintaan darah dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI — Admin / Manager; cabut TTD dokter → entri kembali draft.
     =============================== */
    private function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis']);
    }

    public function bukaKunci(string $id): void
    {
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['permintaanDarahRI'] ?? [];
                $index = collect($list)->search(fn($it) => ($it['id'] ?? null) === $id);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$index]['finalized'] = false;
                $list[$index]['form']['ttd']['dokterNama'] = '';
                $list[$index]['form']['ttd']['dokterCode'] = '';
                $list[$index]['form']['ttd']['dokterDate'] = '';

                $fresh['permintaanDarahRI'] = array_values($list);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI(
                    (int) $this->riHdrNo,
                    'Buka kunci Permintaan Darah — entri ' . ($list[$index]['form']['tglPermintaan'] ?? $id)
                        . ' (oleh ' . (auth()->user()->myuser_name ?? '-') . ')',
                    'MR',
                );
            });

            $this->incrementVersion('modal-permintaan-darah-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka, entri kembali menjadi draft.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $id)
    {
        $entry = collect($this->dataDaftarRi['permintaanDarahRI'] ?? [])->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data permintaan darah tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            $ttdDokterPath = null;
            $dokterCode = data_get($entry, 'form.ttd.dokterCode');
            if ($dokterCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $dokterCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdDokterPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdDokterPath' => $ttdDokterPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
                'opsiLabel' => PermintaanDarahOptions::labels(),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.permintaan-darah-ri.cetak-permintaan-darah-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Permintaan Darah.');
            return response()->streamDownload(fn() => print $pdf->output(), 'permintaan-darah-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    public function resetFormDarah(): void
    {
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->form = $this->defaultForm();
        $this->prefillDariEmr();
        $this->resetValidation();
        $this->incrementVersion('modal-permintaan-darah-ri');
    }

    /** Set "Diperlukan" (tanggal + jam) baris jenis darah ke waktu sekarang. */
    public function setDiperlukanSekarang(string $kode): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!array_key_exists($kode, $this->form['jenisDarah'] ?? [])) {
            return;
        }
        $this->form['jenisDarah'][$kode]['diperlukan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }
}; ?>

<div>
    {{-- KARTU RINGKAS + TOMBOL BUKA --}}
    @php $darahCount = count($dataDaftarRi['permintaanDarahRI'] ?? []); @endphp
    <div class="flex items-center justify-between gap-3 p-4 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-ink dark:text-white">Formulir Permintaan Darah</p>
            <p class="mt-0.5 text-xs text-muted dark:text-gray-400">
                Permintaan komponen darah untuk transfusi — ditandatangani dokter peminta lalu dicetak untuk PMI.
                @if ($darahCount > 0)
                    <span class="font-medium">· {{ $darahCount }} permintaan</span>
                @endif
            </p>
        </div>
        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
            :disabled="!$riHdrNo" class="gap-2 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v16m8-8H4" />
            </svg>
            Buka Formulir
        </x-primary-button>
    </div>

    <x-modal name="rm-permintaan-darah-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-permintaan-darah-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER: identitas pasien --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                            wire:key="permintaan-darah-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
                        @if ($isFormLocked)
                            <div class="mt-2"><x-badge variant="danger">Pasien Pulang — Read Only</x-badge></div>
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                @php $formRO = $isFormLocked || $viewOnly; @endphp

                @if ($viewOnly)
                    <div class="px-3 py-2 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                        Entri ini sudah ditandatangani dokter &amp; terkunci — hanya bisa dilihat / dicetak.
                    </div>
                @endif

                {{-- ── BAGIAN 1: DATA KLINIS ── --}}
                <x-border-form title="Data Klinis Pasien" align="start" bgcolor="bg-surface-soft">
                    <fieldset @disabled($formRO)>
                        <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-2 xl:grid-cols-4 items-start">
                            <div>
                                <x-input-label value="Tanggal Permintaan *" />
                                <x-text-input wire:model.blur="form.tglPermintaan" class="w-full mt-1"
                                    placeholder="dd/mm/yyyy hh:mm:ss"
                                    :error="$errors->has('form.tglPermintaan')" :disabled="$formRO" />
                                <x-input-error :messages="$errors->get('form.tglPermintaan')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Transfusi Sebelumnya *" />
                                <div class="mt-2">
                                    <x-toggle wire:model.live="form.transfusiSebelumnya" trueValue="pernah" falseValue="belum"
                                        label="Pernah ditransfusi sebelumnya" :disabled="$formRO" />
                                </div>
                                <x-input-error :messages="$errors->get('form.transfusiSebelumnya')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Diagnosa Sementara *" />
                                <x-textarea wire:model.blur="form.diagnosaSementara" class="w-full mt-1" rows="2"
                                    placeholder="Diagnosa kerja" :error="$errors->has('form.diagnosaSementara')" :disabled="$formRO" />
                                <x-input-error :messages="$errors->get('form.diagnosaSementara')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Indikasi Transfusi *" />
                                <x-textarea wire:model.blur="form.indikasiTransfusi" class="w-full mt-1" rows="2"
                                    placeholder="mis. anemia, perdarahan, dll." :error="$errors->has('form.indikasiTransfusi')" :disabled="$formRO" />
                                <x-input-error :messages="$errors->get('form.indikasiTransfusi')" class="mt-1" />
                            </div>
                        </div>
                    </fieldset>
                </x-border-form>

                {{-- ── BAGIAN 2: JENIS DARAH YANG DIPERLUKAN ── --}}
                <x-border-form title="Jenis Darah yang Diperlukan" align="start" bgcolor="bg-surface-soft">
                    <fieldset @disabled($formRO)>
                        <x-input-error :messages="$errors->get('form.jenisDarah')" class="mt-2" />
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-sm border-collapse">
                                <thead>
                                    <tr class="text-left text-muted dark:text-gray-400">
                                        <th class="px-2 py-2 border-b border-hairline dark:border-gray-700">Jenis Permintaan</th>
                                        <th class="px-2 py-2 border-b border-hairline dark:border-gray-700 w-40">Golongan</th>
                                        <th class="px-2 py-2 border-b border-hairline dark:border-gray-700 w-32">Jumlah</th>
                                        <th class="px-2 py-2 border-b border-hairline dark:border-gray-700 w-28">Satuan</th>
                                        <th class="px-2 py-2 border-b border-hairline dark:border-gray-700 w-64">Diperlukan (Tgl &amp; Jam)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (['wb', 'prc', 'ffp'] as $kode)
                                        @php $baris = $form['jenisDarah'][$kode] ?? []; $aktif = !empty($baris['pilih']); @endphp
                                        <tr wire:key="darah-{{ $kode }}" class="align-top">
                                            <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                                <x-toggle wire:model.live="form.jenisDarah.{{ $kode }}.pilih"
                                                    :trueValue="true" :falseValue="false"
                                                    :label="$jenisOptions[$kode]" :disabled="$formRO" />
                                            </td>
                                            <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                                <div class="flex gap-1">
                                                    <x-select-input wire:model.blur="form.jenisDarah.{{ $kode }}.golongan" class="text-sm"
                                                        :error="$errors->has('form.jenisDarah.' . $kode . '.golongan')" :disabled="$formRO || !$aktif">
                                                        <option value="">—</option>
                                                        @foreach ($golonganOptions as $g => $gl)
                                                            <option value="{{ $g }}">{{ $gl }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                    <x-select-input wire:model.blur="form.jenisDarah.{{ $kode }}.rhesus" class="text-sm w-16"
                                                        :disabled="$formRO || !$aktif">
                                                        <option value="">Rh</option>
                                                        @foreach ($rhesusOptions as $r => $rl)
                                                            <option value="{{ $r }}">{{ $r }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                </div>
                                            </td>
                                            <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                                <x-text-input type="number" step="any" wire:model.blur="form.jenisDarah.{{ $kode }}.jumlah"
                                                    class="w-full text-sm" :error="$errors->has('form.jenisDarah.' . $kode . '.jumlah')" :disabled="$formRO || !$aktif" />
                                            </td>
                                            <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                                <x-select-input wire:model.blur="form.jenisDarah.{{ $kode }}.satuan" class="w-full text-sm" :disabled="$formRO || !$aktif">
                                                    @foreach ($satuanOptions as $s => $sl)
                                                        <option value="{{ $s }}">{{ $sl }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </td>
                                            <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                                <div class="flex items-center gap-1">
                                                    <x-text-input type="text" wire:model.blur="form.jenisDarah.{{ $kode }}.diperlukan"
                                                        placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                                                        :error="$errors->has('form.jenisDarah.' . $kode . '.diperlukan')" :disabled="$formRO || !$aktif" />
                                                    <x-now-button wire:click="setDiperlukanSekarang('{{ $kode }}')"
                                                        title="Set ke waktu sekarang" :disabled="$formRO || !$aktif" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- Lainnya --}}
                                    @php $lain = $form['jenisDarah']['lainnya'] ?? []; $aktifLain = !empty($lain['pilih']); @endphp
                                    <tr wire:key="darah-lainnya" class="align-top">
                                        <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                            <x-toggle wire:model.live="form.jenisDarah.lainnya.pilih"
                                                :trueValue="true" :falseValue="false" label="Lainnya" :disabled="$formRO" />
                                            <div class="mt-1.5 space-y-1">
                                                <x-text-input wire:model.blur="form.jenisDarah.lainnya.ket1" class="w-full text-sm"
                                                    placeholder="1. Sebutkan…" :disabled="$formRO || !$aktifLain" />
                                                <x-text-input wire:model.blur="form.jenisDarah.lainnya.ket2" class="w-full text-sm"
                                                    placeholder="2. Sebutkan…" :disabled="$formRO || !$aktifLain" />
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                            <div class="flex gap-1">
                                                <x-select-input wire:model.blur="form.jenisDarah.lainnya.golongan" class="text-sm"
                                                    :error="$errors->has('form.jenisDarah.lainnya.golongan')" :disabled="$formRO || !$aktifLain">
                                                    <option value="">—</option>
                                                    @foreach ($golonganOptions as $g => $gl)
                                                        <option value="{{ $g }}">{{ $gl }}</option>
                                                    @endforeach
                                                </x-select-input>
                                                <x-select-input wire:model.blur="form.jenisDarah.lainnya.rhesus" class="text-sm w-16" :disabled="$formRO || !$aktifLain">
                                                    <option value="">Rh</option>
                                                    @foreach ($rhesusOptions as $r => $rl)
                                                        <option value="{{ $r }}">{{ $r }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                            <x-text-input type="number" step="any" wire:model.blur="form.jenisDarah.lainnya.jumlah"
                                                class="w-full text-sm" :error="$errors->has('form.jenisDarah.lainnya.jumlah')" :disabled="$formRO || !$aktifLain" />
                                        </td>
                                        <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                            <x-select-input wire:model.blur="form.jenisDarah.lainnya.satuan" class="w-full text-sm" :disabled="$formRO || !$aktifLain">
                                                @foreach ($satuanOptions as $s => $sl)
                                                    <option value="{{ $s }}">{{ $sl }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </td>
                                        <td class="px-2 py-2 border-b border-hairline dark:border-gray-700">
                                            <div class="flex items-center gap-1">
                                                <x-text-input type="text" wire:model.blur="form.jenisDarah.lainnya.diperlukan"
                                                    placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                                                    :error="$errors->has('form.jenisDarah.lainnya.diperlukan')" :disabled="$formRO || !$aktifLain" />
                                                <x-now-button wire:click="setDiperlukanSekarang('lainnya')"
                                                    title="Set ke waktu sekarang" :disabled="$formRO || !$aktifLain" />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-muted dark:text-gray-400">
                            Bagian PMI (nomor labu, IMLTD, crossmatch, TTD pengirim/penerima) diisi manual pada lembar cetak.
                        </p>
                    </fieldset>
                </x-border-form>

                {{-- ── TTD DOKTER PEMINTA ── --}}
                <x-border-form title="Dokter yang Meminta" align="start" bgcolor="bg-surface-soft">
                    <div class="mt-2">
                        <x-signature.ttd-petugas :framed="false" :allowClear="false"
                            :ttd="$form['ttd']['dokterNama'] ?? ''"
                            :date="$form['ttd']['dokterDate'] ?? ''"
                            :code="$form['ttd']['dokterCode'] ?? ''"
                            :locked="$formRO"
                            :canSign="auth()->user()?->hasAnyRole(['Dokter', 'Admin'])"
                            sign="ttdDokter" nameLabel="Dokter Peminta" dateLabel="Jam TTD" signLabel="TTD & Kunci" />
                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                            TTD dokter = memvalidasi &amp; <strong>mengunci</strong> permintaan ini.
                        </p>
                    </div>
                </x-border-form>

                {{-- ── DAFTAR PERMINTAAN ── --}}
                @php $list = $dataDaftarRi['permintaanDarahRI'] ?? []; @endphp
                <x-border-form title="Riwayat Permintaan Darah" align="start" bgcolor="bg-surface-soft">
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-muted dark:text-gray-400">
                                    <th class="px-3 py-2 border-b border-hairline dark:border-gray-700">Tanggal</th>
                                    <th class="px-3 py-2 border-b border-hairline dark:border-gray-700">Jenis</th>
                                    <th class="px-3 py-2 border-b border-hairline dark:border-gray-700">Status</th>
                                    <th class="px-3 py-2 border-b border-hairline dark:border-gray-700">Dokter</th>
                                    <th class="px-3 py-2 text-center border-b border-hairline dark:border-gray-700 w-72">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($list as $row)
                                    @php
                                        $rid = $row['id'] ?? '';
                                        $rf = $row['form'] ?? [];
                                        $final = array_key_exists('finalized', $row) ? (bool) $row['finalized'] : filled(data_get($rf, 'ttd.dokterNama'));
                                        $jenisRingkas = collect($jenisOptions)->filter(fn($l, $k) => !empty(data_get($rf, "jenisDarah.$k.pilih")))->values()->implode(', ');
                                    @endphp
                                    <tr wire:key="row-darah-{{ $rid }}">
                                        <td class="px-3 py-2 align-middle border-b border-hairline dark:border-gray-700 text-ink dark:text-gray-200">{{ data_get($rf, 'tglPermintaan', '-') ?: '-' }}</td>
                                        <td class="px-3 py-2 align-middle border-b border-hairline dark:border-gray-700 text-muted dark:text-gray-300">{{ $jenisRingkas ?: '-' }}</td>
                                        <td class="px-3 py-2 align-middle border-b border-hairline dark:border-gray-700">
                                            @if ($final)
                                                <x-badge variant="success">Terkunci</x-badge>
                                            @else
                                                <x-badge variant="warning">Draft</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-middle border-b border-hairline dark:border-gray-700 text-muted dark:text-gray-300">{{ data_get($rf, 'ttd.dokterNama') ?: '-' }}</td>
                                        <td class="px-3 py-2 text-center align-middle border-b border-hairline dark:border-gray-700">
                                            <div class="flex items-center justify-center gap-2">
                                                @if (!$final && !$isFormLocked && $rid)
                                                    <x-primary-button type="button" wire:click="editEntri('{{ $rid }}')"
                                                        wire:loading.attr="disabled" wire:target="editEntri('{{ $rid }}')"
                                                        class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Lanjut Isi
                                                    </x-primary-button>
                                                @endif
                                                @if ($final && $rid)
                                                    <x-secondary-button type="button" wire:click="editEntri('{{ $rid }}')"
                                                        wire:loading.attr="disabled" wire:target="editEntri('{{ $rid }}')"
                                                        class="gap-1.5" title="Lihat entri terkunci">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        Lihat
                                                    </x-secondary-button>
                                                @endif
                                                @if ($rid)
                                                    <x-info-button type="button" wire:click="cetak('{{ $rid }}')"
                                                        wire:loading.attr="disabled" wire:target="cetak('{{ $rid }}')"
                                                        class="gap-1.5" title="Cetak permintaan darah">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                        </svg>
                                                        Cetak
                                                    </x-info-button>
                                                @endif
                                                @if ($final && $rid && !$isFormLocked)
                                                    @hasanyrole('Admin|Manager Umum|Manager Medis')
                                                        <x-confirm-button action="bukaKunci('{{ $rid }}')"
                                                            title="Buka Kunci Permintaan Darah"
                                                            message="TTD dokter akan dicabut & entri kembali menjadi draft untuk dikoreksi. Lanjutkan?"
                                                            confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                            </svg>
                                                            Buka Kunci
                                                        </x-confirm-button>
                                                    @endhasanyrole
                                                @endif
                                                @if (!$isFormLocked && $rid)
                                                    @hasanyrole('Admin|Manager Umum|Manager Medis|Supervisor|Mr')
                                                        <x-outline-button type="button" wire:click.prevent="hapusEntri('{{ $rid }}')"
                                                            wire:confirm="Hapus permintaan darah ini?" wire:loading.attr="disabled"
                                                            class="!px-2 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                            title="Hapus permintaan darah">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endhasanyrole
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-muted-soft">Belum ada permintaan darah.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-border-form>

            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    @if (!$formRO)
                        @if ($editingKey)
                            <x-secondary-button wire:click="resetFormDarah">Batal Edit</x-secondary-button>
                        @endif
                        <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                            <span wire:loading.remove wire:target="saveDraft">{{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}</span>
                            <span wire:loading wire:target="saveDraft"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
