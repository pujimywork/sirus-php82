<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-awal-ginekologi-ri/rm-pengkajian-awal-ginekologi-ri-actions.blade.php
// Dokumen VK/Kebidanan #2 — Pengkajian Awal Ginekologi (gabungan RM 45 + 45.a).
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json. Kunci entri stabil = createdAt. TTD = stempel nama user login
// (ttdSaya = FINALIZE/kunci), tanpa TTD gambar. [scan] = field form fisik; [akr] = tambahan akreditasi.

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
    protected array $renderAreas = ['modal-pengkajian-awal-ginekologi-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianAwalGinekologiRI';

    public array $newForm = [
        // 1. Data pengkajian
        'jamPengkajian'      => '',
        'caraMasuk'          => '',   // Datang sendiri | Rujukan
        'caraMasukRujukan'   => '',
        // 2. Sosial pasien
        'pekerjaan'          => '',
        'pendidikan'         => '',
        'agama'              => '',
        'suku'               => '',
        'psikososial'        => '',   // [akr]
        'ekonomi'            => '',   // [akr]
        // 3. Suami / Penanggung Jawab
        'namaSuami'          => '',
        'umurSuami'          => '',
        'pekerjaanSuami'     => '',
        'pendidikanSuami'    => '',
        'agamaSuami'         => '',
        'sukuSuami'          => '',
        // 4. Riwayat
        'alergiObat'         => '',
        'riwayatObat'        => '',   // [akr]
        'penyakitPenting'    => [],   // checkbox multi
        'penyakitLain'       => '',
        // 5. Ginekologi
        'hpht'               => '',
        'menarcheUmur'       => '',
        'menopause'          => '',
        'menikahKali'        => '',
        'menikahLama'        => '',
        'anakHidup'          => '',
        'anakMati'           => '',
        'anakTerkecilUmur'   => '',
        'kontrasepsi'        => '',
        'riwayatHaid'        => '',
        'riwayatKeputihan'   => '',
        'riwayatPersalinanLalu' => '',
        // 6. Keluhan
        'keluhanUtama'       => '',
        'riwayatPenyakitSekarang' => '',
        // 7. Status Umum / TTV
        'keadaanUmum'        => '',
        'td'                 => '',
        'nadi'               => '',
        'respirasi'          => '',
        'suhuRectal'         => '',
        'suhuAxiler'         => '',
        'conjungtiva'        => '',
        'edema'              => '',
        'cor'                => '',
        'pulmo'              => '',
        // 8. Pemeriksaan Dalam
        'jenisPemeriksaan'   => '',   // VT | RT | Inspeculo
        'vulvaVagina'        => '',
        'corpusUteri'        => '',
        'portio'             => '',
        'adnexaKanan'        => '',
        'adnexaKiri'         => '',
        'cavumDouglasi'      => '',
        // 9. Skrining
        'skalaNyeri'         => '',
        'risikoJatuh'        => '',
        'skriningGizi'       => '',   // [akr]
        'pengkajianFungsional' => '', // [akr]
        'kebutuhanEdukasi'   => '',   // [akr]
        // 10. Status Lokalis (Dokter)
        'abdomen'            => '',
        'genitalia'          => '',
        // 11. Diagnosa & Rencana
        'diagnosa'           => '',
        'rencanaTindakan'    => '',
        'dischargePlanning'  => '',   // [akr]
        // 12. Penutup
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

    public array $penyakitOptions = [
        'Jantung', 'Diabetes', 'Hypertensi', 'Ginjal', 'Tuberculosis',
        'Asthma Bronchiale', 'Anemia', 'Penyakit Kelamin', 'Tumor Kandungan',
    ];
    public array $pekerjaanOptions = ['Tani', 'PNS', 'Swasta', 'ABRI', 'IRT', 'Lainnya'];
    public array $pendidikanOptions = ['TK', 'SD', 'SLTP', 'SLTA', 'Sarjana', 'Lainnya'];

    protected function rules(): array
    {
        return [
            'newForm.diagnosa' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'newForm.diagnosa.required' => 'Diagnosa harus diisi.',
        ];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-awal-ginekologi-ri']);

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

        $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
        $this->dispatch('open-modal', name: 'pengkajian-awal-ginekologi-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'pengkajian-awal-ginekologi-ri');
    }

    /* ===============================
     | SET JAM / TGL SEKARANG
     =============================== */
    public function setJamSekarang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('H:i');
    }

    public function setTglSekarang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('Y-m-d');
    }

    public function togglePenyakit(string $opt): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $list = $this->newForm['penyakitPenting'] ?? [];
        if (($k = array_search($opt, $list, true)) !== false) {
            unset($list[$k]);
        } else {
            $list[] = $opt;
        }
        $this->newForm['penyakitPenting'] = array_values($list);
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
        $entry['finalized'] = $finalized;
        return $entry;
    }

    // Cek: minimal salah satu isian inti terisi (untuk simpan draft).
    private function adaIsiInti(): bool
    {
        return collect(['jamPengkajian', 'keluhanUtama', 'diagnosa', 'td', 'keadaanUmum'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
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

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Awal Ginekologi — ' . ($entry['jamPengkajian'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Jam Pengkajian, Keluhan Utama, Diagnosa, TD, atau Keadaan Umum.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
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

        // Cek inti: diagnosa wajib sebelum dikunci.
        $this->validateWithToast();

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
            $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
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
        $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
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
        $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Awal Ginekologi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-entri by createdAt)
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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-awal-ginekologi-ri.cetak-pengkajian-awal-ginekologi-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-awal-ginekologi-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Awal Ginekologi</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pengkajian awal pasien ginekologi (RM 45/45.a) — identitas & sosial, riwayat ginekologi & haid,
                    keluhan, TTV, pemeriksaan dalam & status lokalis, skrining (PP 1.2), diagnosa & rencana. Diisi Bidan/Dokter.
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
                            <th class="px-3 py-2 border-b">Tgl / Jam</th>
                            <th class="px-3 py-2 border-b">Diagnosa</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['jamPengkajian'] ?: ($e['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($e['diagnosa'] ?? '', 60) ?: '-' }}</td>
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
    <x-modal name="pengkajian-awal-ginekologi-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-pengkajian-awal-ginekologi-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pengkajian Awal Ginekologi</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 45 / 45.a — kebidanan (VK). Diisi Bidan / Dokter.</p>
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
                        wire:key="pengkajian-ginekologi-display-pasien-{{ $riHdrNo }}" />

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

                        {{-- 1. Data Pengkajian --}}
                        <x-border-form title="1. Data Pengkajian">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Jam Pengkajian" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input type="time" wire:model="newForm.jamPengkajian" class="w-full" />
                                        @if (!$formRO)
                                            <x-now-button wire:click="setJamSekarang('jamPengkajian')" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Cara Masuk" />
                                    <x-select-input wire:model.live="newForm.caraMasuk" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Datang sendiri">Datang sendiri</option>
                                        <option value="Rujukan">Rujukan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Perujuk (bila rujukan)" />
                                    <x-text-input wire:model="newForm.caraMasukRujukan" class="w-full mt-1" placeholder="Faskes / bidan perujuk" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. Data Sosial Pasien --}}
                        <x-border-form title="2. Data Sosial Pasien">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Pekerjaan" />
                                    <x-select-input wire:model="newForm.pekerjaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pekerjaanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Pendidikan" />
                                    <x-select-input wire:model="newForm.pendidikan" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pendidikanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Agama" />
                                    <x-text-input wire:model="newForm.agama" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Suku Bangsa" />
                                    <x-text-input wire:model="newForm.suku" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Psiko-sosio-spiritual" />
                                    <x-text-input wire:model="newForm.psikososial" class="w-full mt-1" placeholder="mis. tenang / cemas; dukungan keluarga; ibadah" />
                                </div>
                                <div>
                                    <x-input-label value="Ekonomi" />
                                    <x-text-input wire:model="newForm.ekonomi" class="w-full mt-1" placeholder="mis. cukup / kurang; penjamin" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 3. Suami / Penanggung Jawab --}}
                        <x-border-form title="3. Suami / Penanggung Jawab">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Nama" /><x-text-input wire:model="newForm.namaSuami" class="w-full mt-1" /></div>
                                <div><x-input-label value="Umur (th)" /><x-text-input type="number" wire:model="newForm.umurSuami" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Pekerjaan" />
                                    <x-select-input wire:model="newForm.pekerjaanSuami" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pekerjaanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Pendidikan" />
                                    <x-select-input wire:model="newForm.pendidikanSuami" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pendidikanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Agama" /><x-text-input wire:model="newForm.agamaSuami" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suku Bangsa" /><x-text-input wire:model="newForm.sukuSuami" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Riwayat --}}
                        <x-border-form title="4. Riwayat">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><x-input-label value="Alergi Obat" /><x-text-input wire:model="newForm.alergiObat" class="w-full mt-1" placeholder="Tidak ada / sebutkan" /></div>
                                    <div><x-input-label value="Riwayat Penggunaan Obat" /><x-text-input wire:model="newForm.riwayatObat" class="w-full mt-1" placeholder="Obat rutin yang dikonsumsi" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Penyakit Penting yang Pernah Diderita" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3">
                                        @foreach ($penyakitOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['penyakitPenting'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="togglePenyakit('{{ $opt }}')"
                                                :disabled="$formRO">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                    <x-text-input wire:model="newForm.penyakitLain" class="w-full mt-2" placeholder="Penyakit lain (bila ada)" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. Riwayat Ginekologi --}}
                        <x-border-form title="5. Riwayat Ginekologi">
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    <div><x-input-label value="HPHT" /><div class="flex gap-1 mt-1"><x-text-input type="date" wire:model="newForm.hpht" class="w-full" />@if (!$formRO)<x-now-button wire:click="setTglSekarang('hpht')" />@endif</div></div>
                                    <div><x-input-label value="Menarche (umur th)" /><x-text-input type="number" wire:model="newForm.menarcheUmur" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Menopause" /><x-text-input wire:model="newForm.menopause" class="w-full mt-1" placeholder="Ya/Tidak; umur" /></div>
                                    <div><x-input-label value="Kontrasepsi" /><x-text-input wire:model="newForm.kontrasepsi" class="w-full mt-1" placeholder="Suntik/Pil/IUD/…" /></div>
                                    <div><x-input-label value="Menikah (kali)" /><x-text-input type="number" wire:model="newForm.menikahKali" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Lama Menikah (th)" /><x-text-input type="number" wire:model="newForm.menikahLama" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Anak Hidup" /><x-text-input type="number" wire:model="newForm.anakHidup" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Anak Mati" /><x-text-input type="number" wire:model="newForm.anakMati" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Umur Anak Terkecil" /><x-text-input wire:model="newForm.anakTerkecilUmur" class="w-full mt-1" /></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><x-input-label value="Riwayat Haid" /><x-text-input wire:model="newForm.riwayatHaid" class="w-full mt-1" placeholder="Siklus/lama/banyak/nyeri" /></div>
                                    <div><x-input-label value="Riwayat Keputihan" /><x-text-input wire:model="newForm.riwayatKeputihan" class="w-full mt-1" placeholder="Warna/bau/gatal" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Riwayat Persalinan yang Lalu" />
                                    <x-textarea wire:model="newForm.riwayatPersalinanLalu" rows="2" class="w-full mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 6. Keluhan --}}
                        <x-border-form title="6. Keluhan">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label value="Keluhan Utama" />
                                    <x-textarea wire:model="newForm.keluhanUtama" rows="2" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Riwayat Penyakit Sekarang" />
                                    <x-textarea wire:model="newForm.riwayatPenyakitSekarang" rows="2" class="w-full mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 7. Status Umum / TTV --}}
                        <x-border-form title="7. Status Umum & Tanda Vital">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <div class="col-span-2 sm:col-span-3 lg:col-span-1"><x-input-label value="Keadaan Umum" /><x-text-input wire:model="newForm.keadaanUmum" class="w-full mt-1" /></div>
                                <div><x-input-label value="TD (mmHg)" /><x-text-input wire:model="newForm.td" class="w-full mt-1" placeholder="120/80" /></div>
                                <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.nadi" class="w-full mt-1" /></div>
                                <div><x-input-label value="RR (x/mnt)" /><x-text-input type="number" wire:model="newForm.respirasi" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu Rectal (°C)" /><x-text-input wire:model="newForm.suhuRectal" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu Axiler (°C)" /><x-text-input wire:model="newForm.suhuAxiler" class="w-full mt-1" /></div>
                                <div><x-input-label value="Conjungtiva" /><x-text-input wire:model="newForm.conjungtiva" class="w-full mt-1" /></div>
                                <div><x-input-label value="Edema" /><x-text-input wire:model="newForm.edema" class="w-full mt-1" /></div>
                                <div><x-input-label value="Cor" /><x-text-input wire:model="newForm.cor" class="w-full mt-1" /></div>
                                <div><x-input-label value="Pulmo" /><x-text-input wire:model="newForm.pulmo" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 8. Pemeriksaan Dalam --}}
                        <x-border-form title="8. Pemeriksaan Dalam">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Jenis Pemeriksaan" />
                                    <x-select-input wire:model="newForm.jenisPemeriksaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="VT">VT</option>
                                        <option value="RT">RT</option>
                                        <option value="Inspeculo">Inspeculo</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Vulva / Vagina" /><x-text-input wire:model="newForm.vulvaVagina" class="w-full mt-1" /></div>
                                <div><x-input-label value="Corpus Uteri" /><x-text-input wire:model="newForm.corpusUteri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Portio" /><x-text-input wire:model="newForm.portio" class="w-full mt-1" /></div>
                                <div><x-input-label value="Adnexa Kanan" /><x-text-input wire:model="newForm.adnexaKanan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Adnexa Kiri" /><x-text-input wire:model="newForm.adnexaKiri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Cavum Douglasi" /><x-text-input wire:model="newForm.cavumDouglasi" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 9. Skrining --}}
                        <x-border-form title="9. Skrining (PP 1.2)">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Skala Nyeri (0–10)" /><x-text-input type="number" min="0" max="10" wire:model="newForm.skalaNyeri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Risiko Jatuh" /><x-text-input wire:model="newForm.risikoJatuh" class="w-full mt-1" placeholder="Rendah/Sedang/Tinggi" /></div>
                                <div><x-input-label value="Skrining Gizi/Nutrisi" /><x-text-input wire:model="newForm.skriningGizi" class="w-full mt-1" placeholder="Risiko / tidak berisiko" /></div>
                                <div><x-input-label value="Pengkajian Fungsional" /><x-text-input wire:model="newForm.pengkajianFungsional" class="w-full mt-1" placeholder="Mandiri / dibantu" /></div>
                                <div class="lg:col-span-2"><x-input-label value="Kebutuhan Edukasi" /><x-text-input wire:model="newForm.kebutuhanEdukasi" class="w-full mt-1" placeholder="mis. perawatan, tindakan, obat" /></div>
                            </div>
                        </x-border-form>

                        {{-- 10. Status Lokalis (Dokter) --}}
                        <x-border-form title="10. Status Lokalis (Dokter)">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label value="Abdomen" /><x-textarea wire:model="newForm.abdomen" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Genitalia" /><x-textarea wire:model="newForm.genitalia" rows="2" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 11. Diagnosa & Rencana --}}
                        <x-border-form title="11. Diagnosa & Rencana">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label value="Diagnosa" />
                                    <x-textarea wire:model="newForm.diagnosa" rows="2" class="w-full mt-1" :error="$errors->has('newForm.diagnosa')" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosa')" class="mt-1" />
                                </div>
                                <div><x-input-label value="Rencana Tindakan / Terapi" /><x-textarea wire:model="newForm.rencanaTindakan" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Discharge Planning" /><x-textarea wire:model="newForm.dischargePlanning" rows="2" class="w-full mt-1" placeholder="Rencana pemulangan / kebutuhan pasca-rawat" /></div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formRO" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Bidan / Dokter)" dateLabel="Waktu TTD"
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
                                            <th class="px-4 py-3 border-b">Tgl / Jam</th>
                                            <th class="px-4 py-3 border-b">Diagnosa</th>
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
                                                    {{ $entry['jamPengkajian'] ?: ($rowKey ?: '-') }}
                                                    <div class="text-xs font-normal text-muted-soft">{{ $rowKey }}</div>
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ \Illuminate\Support\Str::limit($entry['diagnosa'] ?? '', 60) ?: '-' }}
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak PDF">
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
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jam Pengkajian</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jamPengkajian'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cara Masuk</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['caraMasuk'] ?: '-' }}{{ !empty($entry['caraMasukRujukan']) ? ' — ' . $entry['caraMasukRujukan'] : '' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pekerjaan / Pendidikan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pekerjaan'] ?: '-' }} / {{ $entry['pendidikan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Agama / Suku</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['agama'] ?: '-' }} / {{ $entry['suku'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Psiko-sosio-spiritual</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['psikososial'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ekonomi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['ekonomi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Suami / PJ</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaSuami'] ?: '-' }}{{ !empty($entry['umurSuami']) ? ' (' . $entry['umurSuami'] . ' th)' : '' }} — {{ $entry['pekerjaanSuami'] ?: '-' }}, {{ $entry['pendidikanSuami'] ?: '-' }}, {{ $entry['agamaSuami'] ?: '-' }}, {{ $entry['sukuSuami'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alergi Obat</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['alergiObat'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Penggunaan Obat</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['riwayatObat'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penyakit Penting</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['penyakitPenting']) ? implode(', ', (array) $entry['penyakitPenting']) : '-' }}{{ !empty($entry['penyakitLain']) ? '; ' . $entry['penyakitLain'] : '' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">HPHT</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['hpht'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Menarche / Menopause</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['menarcheUmur'] ?: '-' }} / {{ $entry['menopause'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Menikah (kali / lama)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['menikahKali'] ?: '-' }} / {{ $entry['menikahLama'] ?: '-' }} th</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Anak Hidup / Mati / Terkecil</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['anakHidup'] ?: '-' }} / {{ $entry['anakMati'] ?: '-' }} / {{ $entry['anakTerkecilUmur'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kontrasepsi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kontrasepsi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Haid</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['riwayatHaid'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Keputihan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['riwayatKeputihan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Persalinan Lalu</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['riwayatPersalinanLalu'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keluhan Utama</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['keluhanUtama'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Penyakit Sekarang</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['riwayatPenyakitSekarang'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keadaan Umum</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['keadaanUmum'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanda Vital</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">TD {{ $entry['td'] ?: '-' }} · N {{ $entry['nadi'] ?: '-' }} · RR {{ $entry['respirasi'] ?: '-' }} · S(R) {{ $entry['suhuRectal'] ?: '-' }} · S(Ax) {{ $entry['suhuAxiler'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Conjungtiva / Edema</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['conjungtiva'] ?: '-' }} / {{ $entry['edema'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cor / Pulmo</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['cor'] ?: '-' }} / {{ $entry['pulmo'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Pemeriksaan Dalam</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisPemeriksaan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Vulva / Vagina</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['vulvaVagina'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Corpus Uteri / Portio</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['corpusUteri'] ?: '-' }} / {{ $entry['portio'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Adnexa Ka / Ki</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['adnexaKanan'] ?: '-' }} / {{ $entry['adnexaKiri'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cavum Douglasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['cavumDouglasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Skala Nyeri / Risiko Jatuh</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['skalaNyeri'] ?: '-' }} / {{ $entry['risikoJatuh'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Skrining Gizi / Fungsional</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['skriningGizi'] ?: '-' }} / {{ $entry['pengkajianFungsional'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kebutuhan Edukasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kebutuhanEdukasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Abdomen (Dokter)</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['abdomen'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Genitalia (Dokter)</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['genitalia'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Tindakan / Terapi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaTindakan'] ?: '-' }}</dd>
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
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
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
