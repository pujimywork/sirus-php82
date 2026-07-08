<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-awal-bayi-ri/rm-pengkajian-awal-bayi-ri-actions.blade.php
// Dokumen VK/Kebidanan — Pengkajian Awal Bayi (RM 14 e.3), diisi Dokter.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json. Kunci entri stabil = createdAt.
// TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-awal-bayi-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianAwalBayiRI';

    public array $newForm = [
        // 1. Identitas Bayi
        'tglLahir'          => '',
        'jamLahir'          => '',
        'caraPersalinan'    => '',
        'namaAyah'          => '',
        'namaIbu'           => '',
        'ruanganIbu'        => '',
        'noRmIbu'           => '',
        // 2. APGAR — 5 komponen x 3 menit (0-2)
        'warnaKulit1'       => '', 'warnaKulit5'   => '', 'warnaKulit10'   => '',
        'reflek1'           => '', 'reflek5'       => '', 'reflek10'       => '',
        'denyutJantung1'    => '', 'denyutJantung5'=> '', 'denyutJantung10'=> '',
        'tonus1'            => '', 'tonus5'        => '', 'tonus10'        => '',
        'usahaNafas1'       => '', 'usahaNafas5'   => '', 'usahaNafas10'   => '',
        // 3. Pemeriksaan Fisik
        'keadaanTaliPusat'  => '',
        'jantung'           => '',
        'paru'              => '',
        'abdomenHati'       => '',
        'limpa'             => '',
        'anus'              => '',
        'ekstremitas'       => '',
        'imunisasi'         => '',
        // 4. Antropometri
        'lingkarKepala'     => '',
        'beratBadan'        => '',
        'tinggiBadan'       => '',
        'lingkarDada'       => '',
        'jenisKelamin'      => '',
        // 5. Keadaan Bayi Waktu Lahir
        'sianosis'          => '', 'sianosisKet'    => '',
        'asphyxia'          => '', 'asphyxiaKet'    => '',
        'traumaLahir'       => '', 'traumaLahirKet' => '',
        // 6. Diagnosa
        'diagnosaUtama'     => '',
        // 7. Rencana
        'rencanaDiagnosa'   => '',
        'terapi'            => '',
        'diet'              => '',
        'edukasi'           => '',
        'monitoring'        => '',
        'dischargePlanning' => '',
        // 8. Tanda Tangan
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    protected function rules(): array
    {
        return [];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-awal-bayi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->entriList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi[$this->jsonKey]) || !is_array($this->dataDaftarRi[$this->jsonKey])) {
            $this->dataDaftarRi[$this->jsonKey] = [];
        }
        $this->entriList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;

        $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
        $this->dispatch('open-modal', name: 'pengkajian-awal-bayi-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'pengkajian-awal-bayi-ri');
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setTglJamSekarang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = $this->newForm;
        $entry['createdAt'] = $key;
        $entry['finalized']  = $finalized;
        return $entry;
    }

    // Cek: field inti (Diagnosa Utama) terisi.
    private function adaIntiTerisi(): bool
    {
        return filled($this->newForm['diagnosaUtama'] ?? null);
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                $fresh[$this->jsonKey] = [];
            }

            $list = $fresh[$this->jsonKey];
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->entriList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Awal Bayi — ' . ($entry['tglLahir'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi Diagnosa Utama sebelum menyimpan.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → kunci entri.
     =============================== */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi Diagnosa Utama sebelum TTD.');
            return;
        }

        // Stempel TTD petugas = user login.
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). TANPA TTD gambar.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : '');
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
    }

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->entriList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    /* ===============================
     | HAPUS entri (final atau draft)
     =============================== */
    public function hapus(string $createdAt): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($e) => ($e['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Awal Bayi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-ENTRI, by createdAt)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'       => $this->dataDaftarRi,
                'form'         => $entry,
                'identitasRs'  => $identitasRs,
                'tglCetak'     => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-awal-bayi-ri.cetak-pengkajian-awal-bayi-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-awal-bayi-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $paCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Awal Bayi</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pengkajian awal bayi baru lahir (RM 14 e.3) — identitas bayi, nilai APGAR (1'/5'/10'),
                    pemeriksaan fisik, antropometri, keadaan waktu lahir, diagnosa &amp; rencana. Diisi Dokter.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @if ($paCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tgl / Jam Lahir</th>
                            <th class="px-3 py-2 border-b">Jenis Kelamin</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['tglLahir'] ?: ($e['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['jenisKelamin'] ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($e['ttd'])){{ $e['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($e))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="pengkajian-awal-bayi-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-pengkajian-awal-bayi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pengkajian Awal Bayi</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 14 e.3 — kebidanan (VK). Tiap entri = 1 bayi. Diisi Dokter.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (count($entriList) > 0)
                            <x-badge variant="info">{{ count($entriList) }} tersimpan</x-badge>
                        @endif
                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif
                        <x-icon-button color="gray" type="button" wire:click="closeModal">
                            <span class="sr-only">Tutup</span>
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pengkajian-bayi-display-pasien-{{ $riHdrNo }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($formRO) class="space-y-4">

                        {{-- 1. Identitas Bayi --}}
                        <x-border-form title="1. Identitas Bayi">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Tgl / Jam Lahir" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.tglLahir" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        @if (!$formRO)
                                            <x-now-button wire:click="setTglJamSekarang('tglLahir')" />
                                        @endif
                                    </div>
                                </div>
                                <div><x-input-label value="Cara Persalinan" /><x-text-input wire:model="newForm.caraPersalinan" class="w-full mt-1" placeholder="Spontan/SC/…" /></div>
                                <div><x-input-label value="Nama Ayah" /><x-text-input wire:model="newForm.namaAyah" class="w-full mt-1" /></div>
                                <div><x-input-label value="Nama Ibu" /><x-text-input wire:model="newForm.namaIbu" class="w-full mt-1" /></div>
                                <div><x-input-label value="Ruangan Ibu" /><x-text-input wire:model="newForm.ruanganIbu" class="w-full mt-1" /></div>
                                <div><x-input-label value="No. RM Ibu" /><x-text-input wire:model="newForm.noRmIbu" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 2. Nilai APGAR --}}
                        <x-border-form title="2. Nilai APGAR (0–2 per komponen)">
                            @php
                                $apgarRows = [
                                    ['Warna Kulit', 'warnaKulit'],
                                    ['Reflek', 'reflek'],
                                    ['Denyut Jantung', 'denyutJantung'],
                                    ['Tonus', 'tonus'],
                                    ['Usaha Bernafas', 'usahaNafas'],
                                ];
                                $apgarMenit = ['1' => "1'", '5' => "5'", '10' => "10'"];
                                $sumMenit = fn($m) => collect($apgarRows)->sum(fn($r) => (int) ($newForm[$r[1] . $m] ?? 0));
                            @endphp
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="text-left border-b border-hairline dark:border-gray-700">
                                            <th class="py-2 pr-3 font-medium text-muted">Komponen</th>
                                            @foreach ($apgarMenit as $mk => $ml)
                                                <th class="px-2 py-2 font-medium text-center text-muted">Menit {{ $ml }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($apgarRows as $row)
                                            <tr class="border-b border-hairline/60 dark:border-gray-800">
                                                <td class="py-1.5 pr-3 text-ink dark:text-gray-200">{{ $row[0] }}</td>
                                                @foreach ($apgarMenit as $mk => $ml)
                                                    <td class="px-2 py-1.5 text-center">
                                                        <x-text-input type="number" min="0" max="2" wire:model="newForm.{{ $row[1] . $mk }}" class="w-16 mx-auto text-center" />
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        <tr class="font-semibold border-t-2 border-hairline dark:border-gray-600">
                                            <td class="py-2 pr-3 text-ink dark:text-gray-100">Jumlah</td>
                                            @foreach ($apgarMenit as $mk => $ml)
                                                <td class="px-2 py-2 text-center text-brand-green dark:text-brand-lime">{{ $sumMenit($mk) }}</td>
                                            @endforeach
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </x-border-form>

                        {{-- 3. Pemeriksaan Fisik --}}
                        <x-border-form title="3. Pemeriksaan Fisik">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div><x-input-label value="Keadaan Tali Pusat" /><x-text-input wire:model="newForm.keadaanTaliPusat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Jantung" /><x-text-input wire:model="newForm.jantung" class="w-full mt-1" /></div>
                                <div><x-input-label value="Paru" /><x-text-input wire:model="newForm.paru" class="w-full mt-1" /></div>
                                <div><x-input-label value="Abdomen / Hati" /><x-text-input wire:model="newForm.abdomenHati" class="w-full mt-1" /></div>
                                <div><x-input-label value="Limpa" /><x-text-input wire:model="newForm.limpa" class="w-full mt-1" /></div>
                                <div><x-input-label value="Anus" /><x-text-input wire:model="newForm.anus" class="w-full mt-1" /></div>
                                <div><x-input-label value="Ekstremitas" /><x-text-input wire:model="newForm.ekstremitas" class="w-full mt-1" /></div>
                                <div><x-input-label value="Imunisasi" /><x-text-input wire:model="newForm.imunisasi" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Antropometri --}}
                        <x-border-form title="4. Antropometri">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <div><x-input-label value="Lingkar Kepala (cm)" /><x-text-input type="number" wire:model="newForm.lingkarKepala" class="w-full mt-1" /></div>
                                <div><x-input-label value="Berat Badan (gr)" /><x-text-input type="number" wire:model="newForm.beratBadan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Tinggi Badan (cm)" /><x-text-input type="number" wire:model="newForm.tinggiBadan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Lingkar Dada (cm)" /><x-text-input type="number" wire:model="newForm.lingkarDada" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Jenis Kelamin" />
                                    <x-select-input wire:model="newForm.jenisKelamin" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. Keadaan Bayi Waktu Lahir --}}
                        <x-border-form title="5. Keadaan Bayi Waktu Lahir">
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Sianosis" />
                                        <x-select-input wire:model="newForm.sianosis" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Sianosis" /><x-text-input wire:model="newForm.sianosisKet" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Asphyxia" />
                                        <x-select-input wire:model="newForm.asphyxia" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Asphyxia" /><x-text-input wire:model="newForm.asphyxiaKet" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Trauma Lahir" />
                                        <x-select-input wire:model="newForm.traumaLahir" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Trauma Lahir" /><x-text-input wire:model="newForm.traumaLahirKet" class="w-full mt-1" /></div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 6. Diagnosa --}}
                        <x-border-form title="6. Diagnosa">
                            <div>
                                <x-input-label value="Diagnosa Utama" />
                                <x-textarea wire:model="newForm.diagnosaUtama" rows="2" class="w-full mt-1" />
                            </div>
                        </x-border-form>

                        {{-- 7. Rencana --}}
                        <x-border-form title="7. Rencana">
                            <div class="space-y-4">
                                <div><x-input-label value="Rencana Diagnosa" /><x-textarea wire:model="newForm.rencanaDiagnosa" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Terapi" /><x-textarea wire:model="newForm.terapi" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Diet" /><x-textarea wire:model="newForm.diet" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Edukasi" /><x-textarea wire:model="newForm.edukasi" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Monitoring" /><x-textarea wire:model="newForm.monitoring" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Discharge Planning" /><x-textarea wire:model="newForm.dischargePlanning" rows="2" class="w-full mt-1" placeholder="Rencana pemulangan / kebutuhan pasca-rawat" /></div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formRO" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Dokter Pengkaji" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci pengkajian ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN (expandable) ── --}}
                    <x-border-form title="Riwayat Pengkajian Tersimpan">
                        @if (count($entriList ?? []))
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tgl / Jam Lahir</th>
                                            <th class="px-4 py-3 border-b">Jenis Kelamin</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($entriList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                        @endphp
                                        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                    {{ $entry['tglLahir'] ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['jenisKelamin'] ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['ttd']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle" @click.stop>
                                                    <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjut Isi
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal)
                                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat
                                                            </x-secondary-button>
                                                        @endif
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak entri ini">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </x-secondary-button>
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus entri pengkajian ini?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Lahir</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tglLahir'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cara Persalinan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['caraPersalinan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Ayah</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaAyah'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Ibu</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaIbu'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ruangan Ibu</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['ruanganIbu'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">No. RM Ibu</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['noRmIbu'] ?: '-' }}</dd>
                                                        </div>

                                                        {{-- APGAR (semua komponen) --}}
                                                        <div class="md:col-span-2">
                                                            <dt class="mb-1 text-xs font-semibold tracking-wide uppercase text-muted-soft">Nilai APGAR</dt>
                                                            <dd class="overflow-x-auto">
                                                                @php
                                                                    $apgarD = [
                                                                        ['Warna Kulit', 'warnaKulit'],
                                                                        ['Reflek', 'reflek'],
                                                                        ['Denyut Jantung', 'denyutJantung'],
                                                                        ['Tonus', 'tonus'],
                                                                        ['Usaha Bernafas', 'usahaNafas'],
                                                                    ];
                                                                    $menitD = ['1' => "1'", '5' => "5'", '10' => "10'"];
                                                                @endphp
                                                                <table class="min-w-full text-sm border-collapse">
                                                                    <thead>
                                                                        <tr class="text-left border-b border-hairline dark:border-gray-700">
                                                                            <th class="py-1 pr-3 font-medium text-muted-soft">Komponen</th>
                                                                            @foreach ($menitD as $mk => $ml)
                                                                                <th class="px-2 py-1 font-medium text-center text-muted-soft">Menit {{ $ml }}</th>
                                                                            @endforeach
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($apgarD as $r)
                                                                            <tr class="border-b border-hairline/60 dark:border-gray-800">
                                                                                <td class="py-1 pr-3 text-ink dark:text-gray-200">{{ $r[0] }}</td>
                                                                                @foreach ($menitD as $mk => $ml)
                                                                                    <td class="px-2 py-1 text-center text-ink dark:text-gray-200">{{ ($entry[$r[1] . $mk] ?? '') === '' ? '-' : $entry[$r[1] . $mk] }}</td>
                                                                                @endforeach
                                                                            </tr>
                                                                        @endforeach
                                                                        <tr class="font-semibold border-t border-hairline dark:border-gray-600">
                                                                            <td class="py-1 pr-3 text-ink dark:text-gray-100">Jumlah</td>
                                                                            @foreach ($menitD as $mk => $ml)
                                                                                <td class="px-2 py-1 text-center text-brand-green dark:text-brand-lime">{{ collect($apgarD)->sum(fn($r) => (int) ($entry[$r[1] . $mk] ?? 0)) }}</td>
                                                                            @endforeach
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keadaan Tali Pusat</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['keadaanTaliPusat'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jantung</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jantung'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Paru</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['paru'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Abdomen / Hati</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['abdomenHati'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Limpa</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['limpa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Anus</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['anus'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ekstremitas</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['ekstremitas'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Imunisasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['imunisasi'] ?: '-' }}</dd>
                                                        </div>

                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lingkar Kepala (cm)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lingkarKepala'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Berat Badan (gr)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['beratBadan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tinggi Badan (cm)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tinggiBadan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lingkar Dada (cm)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lingkarDada'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Kelamin</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisKelamin'] ?: '-' }}</dd>
                                                        </div>

                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sianosis</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['sianosis'] ?: '-' }}@if (!empty($entry['sianosisKet'])) <span class="text-sm text-muted-soft">— {{ $entry['sianosisKet'] }}</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Asphyxia</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['asphyxia'] ?: '-' }}@if (!empty($entry['asphyxiaKet'])) <span class="text-sm text-muted-soft">— {{ $entry['asphyxiaKet'] }}</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Trauma Lahir</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['traumaLahir'] ?: '-' }}@if (!empty($entry['traumaLahirKet'])) <span class="text-sm text-muted-soft">— {{ $entry['traumaLahirKet'] }}</span>@endif</dd>
                                                        </div>

                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa Utama</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosaUtama'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Diagnosa</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaDiagnosa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Terapi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['terapi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diet</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diet'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Edukasi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['edukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Monitoring</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['monitoring'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Discharge Planning</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['dischargePlanning'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['ttd']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['ttdDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada pengkajian tersimpan.</p>
                        @endif
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 border-t shrink-0 bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah entri lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
