<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/instruksi-pasca-bedah-ri/rm-instruksi-pasca-bedah-ri-actions.blade.php
// Instruksi Pasca Bedah (RM 56, PAB 7.3) — pola multi-entri:
// Draft + Lanjut Isi + TTD-Kunci + Lihat(read-only) + tabel expandable, disimpan ke datadaftarri_json.
// Kunci entri stabil = createdAt. TTD Ahli Anestesiologi (setTtd = FINALIZE/kunci) = stempel nama user login.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-instruksi-pasca-bedah-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'instruksiPascaBedahRI';

    // ── Form entri (Instruksi Pasca Bedah — PAB 7.3 / RM 56) ──
    public array $newForm = [
        'tanggal' => '',
        'bilaKesakitan' => '',
        'bilaMualMuntah' => '',
        'antibiotik' => '',
        'obatLain' => '',
        'minum' => '',
        'infus' => '',
        'monitorTensi' => false,
        'monitorNadi' => false,
        'monitorNafas' => false,
        'monitorSetiap' => '',
        'monitorSelama' => '',
        'lainLain' => '',
        // TTD Ahli Anestesiologi / DPJP Anestesi
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $instruksiList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-instruksi-pasca-bedah-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->instruksiList = $data[$this->jsonKey] ?? [];
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
        $this->instruksiList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-instruksi-pasca-bedah-ri');

        $this->dispatch('open-modal', name: "rm-instruksi-pasca-bedah-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-instruksi-pasca-bedah-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.bilaKesakitan' => 'nullable|string|max:500',
            'newForm.bilaMualMuntah' => 'nullable|string|max:500',
            'newForm.antibiotik' => 'nullable|string|max:500',
            'newForm.obatLain' => 'nullable|string|max:1000',
            'newForm.minum' => 'nullable|string|max:500',
            'newForm.infus' => 'nullable|string|max:500',
            'newForm.monitorSetiap' => 'nullable|string|max:100',
            'newForm.monitorSelama' => 'nullable|string|max:100',
            'newForm.lainLain' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 26/06/2026 04:40:00).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam instruksi',
            'newForm.bilaKesakitan' => 'Instruksi bila kesakitan',
            'newForm.bilaMualMuntah' => 'Instruksi bila mual/muntah',
            'newForm.antibiotik' => 'Antibiotik',
            'newForm.obatLain' => 'Obat-obatan lain',
            'newForm.minum' => 'Instruksi minum',
            'newForm.infus' => 'Infus',
            'newForm.monitorSetiap' => 'Monitor setiap',
            'newForm.monitorSelama' => 'Monitor selama',
            'newForm.lainLain' => 'Lain-lain',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
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
        return [
            'tanggal'        => $this->newForm['tanggal'] ?? '',
            'bilaKesakitan'  => $this->newForm['bilaKesakitan'] ?? '',
            'bilaMualMuntah' => $this->newForm['bilaMualMuntah'] ?? '',
            'antibiotik'     => $this->newForm['antibiotik'] ?? '',
            'obatLain'       => $this->newForm['obatLain'] ?? '',
            'minum'          => $this->newForm['minum'] ?? '',
            'infus'          => $this->newForm['infus'] ?? '',
            'monitorTensi'   => (bool) ($this->newForm['monitorTensi'] ?? false),
            'monitorNadi'    => (bool) ($this->newForm['monitorNadi'] ?? false),
            'monitorNafas'   => (bool) ($this->newForm['monitorNafas'] ?? false),
            'monitorSetiap'  => $this->newForm['monitorSetiap'] ?? '',
            'monitorSelama'  => $this->newForm['monitorSelama'] ?? '',
            'lainLain'       => $this->newForm['lainLain'] ?? '',
            'ttd'            => $this->newForm['ttd'] ?? '',
            'ttdCode'        => $this->newForm['ttdCode'] ?? '',
            'ttdDate'        => $this->newForm['ttdDate'] ?? '',
            'createdAt'      => $key,
            'finalized'      => $finalized,
        ];
    }

    // Cek: minimal tanggal atau salah satu instruksi inti terisi.
    private function adaInstruksiInti(): bool
    {
        return collect(['tanggal', 'bilaKesakitan', 'bilaMualMuntah', 'antibiotik', 'obatLain', 'minum', 'infus', 'lainLain'])
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
            $this->instruksiList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Instruksi Pasca Bedah — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaInstruksiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Tanggal/jam atau salah satu instruksi terlebih dahulu.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-instruksi-pasca-bedah-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD AHLI ANESTESIOLOGI = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → kunci entri.
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaInstruksiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Tanggal/jam atau salah satu instruksi sebelum TTD.');
            return;
        }

        // Validasi field (tanggal wajib & format) sebelum kunci.
        $this->validateWithToast();

        // Stempel TTD ahli anestesiologi = user login.
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-instruksi-pasca-bedah-ri');
            $this->dispatch('toast', type: 'success', message: 'Instruksi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function clearTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            if (array_key_exists($k, $entry)) {
                $this->newForm[$k] = $entry[$k];
            } else {
                $this->newForm[$k] = is_array($v) ? [] : (is_bool($v) ? false : '');
            }
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-instruksi-pasca-bedah-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->instruksiList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->instruksiList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-instruksi-pasca-bedah-ri');
    }

    /* ===============================
     | CETAK (inline stream PDF per-entri)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->instruksiList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data instruksi tidak ditemukan.');
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

            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.instruksi-pasca-bedah-ri.cetak-instruksi-pasca-bedah-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak instruksi pasca bedah.');
            return response()->streamDownload(fn() => print $pdf->output(), 'instruksi-pasca-bedah-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
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
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->instruksiList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Instruksi Pasca Bedah — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-instruksi-pasca-bedah-ri');
            $this->dispatch('toast', type: 'success', message: 'Instruksi pasca bedah berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggal' => '',
            'bilaKesakitan' => '',
            'bilaMualMuntah' => '',
            'antibiotik' => '',
            'obatLain' => '',
            'minum' => '',
            'infus' => '',
            'monitorTensi' => false,
            'monitorNadi' => false,
            'monitorNafas' => false,
            'monitorSetiap' => '',
            'monitorSelama' => '',
            'lainLain' => '',
            'ttd' => '',
            'ttdCode' => '',
            'ttdDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->instruksiList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $ipbCount = count($instruksiList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Instruksi Pasca Bedah</h3>
                    @if ($ipbCount > 0)
                        <x-badge variant="success">{{ $ipbCount }} instruksi</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Instruksi pasca-bedah/anestesi (RM 56, PAB 7.3): penanganan nyeri, mual/muntah, antibiotik, obat,
                    minum, infus, monitor tanda vital. Diisi &amp; ditandatangani ahli anestesiologi. Tiap entri = 1 instruksi.
                </p>
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @if ($ipbCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tgl / Jam</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($instruksiList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['tanggal'] ?: ($e['createdAt'] ?? '-') }}</td>
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
    <x-modal name="rm-instruksi-pasca-bedah-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-instruksi-pasca-bedah-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700 shrink-0 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/10">
                                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Instruksi Pasca Bedah</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    RM 56 — PAB 7.3 · tiap entri = 1 instruksi · diisi &amp; ditandatangani ahli anestesiologi
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($instruksiList) > 0)
                                <x-badge variant="info">{{ count($instruksiList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="ipb-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah instruksi lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI (1 instruksi) ── --}}
                    <fieldset @disabled($formRO) class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ══ TANGGAL ══ --}}
                        <section>
                            <x-input-label value="Tanggal / Jam Instruksi *" class="mb-1" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                    :error="$errors->has('newForm.tanggal')" class="w-full max-w-xs" />
                                @if (!$formRO)
                                    <x-now-button wire:click="setTanggalSekarang" />
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                        </section>

                        {{-- ══ INSTRUKSI OBAT/PENANGANAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Bila Kesakitan" class="mb-1" />
                                    <x-textarea wire:model="newForm.bilaKesakitan" :error="$errors->has('newForm.bilaKesakitan')" rows="2" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Bila Mual / Muntah" class="mb-1" />
                                    <x-textarea wire:model="newForm.bilaMualMuntah" :error="$errors->has('newForm.bilaMualMuntah')" rows="2" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Antibiotik" class="mb-1" />
                                    <x-textarea wire:model="newForm.antibiotik" :error="$errors->has('newForm.antibiotik')" rows="2" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Obat-obatan Lain" class="mb-1" />
                                    <x-textarea wire:model="newForm.obatLain" :error="$errors->has('newForm.obatLain')" rows="2" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Minum" class="mb-1" />
                                    <x-text-input wire:model="newForm.minum" :error="$errors->has('newForm.minum')"
                                        placeholder="cth: Boleh minum bertahap bila sadar penuh" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Infus" class="mb-1" />
                                    <x-text-input wire:model="newForm.infus" :error="$errors->has('newForm.infus')" placeholder="cth: RL 20 tpm" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ MONITOR ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Monitor</h3>
                            <div class="flex flex-wrap gap-4">
                                <x-toggle wire:model="newForm.monitorTensi" :trueValue="true" :falseValue="false" label="Tensi" />
                                <x-toggle wire:model="newForm.monitorNadi" :trueValue="true" :falseValue="false" label="Nadi" />
                                <x-toggle wire:model="newForm.monitorNafas" :trueValue="true" :falseValue="false" label="Nafas" />
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Setiap" class="mb-1" />
                                    <x-text-input wire:model="newForm.monitorSetiap" :error="$errors->has('newForm.monitorSetiap')" placeholder="cth: 10 menit" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Selama" class="mb-1" />
                                    <x-text-input wire:model="newForm.monitorSelama" :error="$errors->has('newForm.monitorSelama')" placeholder="cth: 2 jam" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ LAIN-LAIN ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Lain-lain" class="mb-1" />
                            <x-textarea wire:model="newForm.lainLain" :error="$errors->has('newForm.lainLain')" rows="3" class="w-full" />
                        </section>

                        {{-- ══ TTD AHLI ANESTESIOLOGI & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                            :code="$newForm['ttdCode'] ?? ''" :locked="$formRO" sign="setTtd" clear="clearTtd"
                            title="Tanda Tangan Ahli Anestesiologi"
                            nameLabel="Ahli Anestesiologi" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci instruksi ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR INSTRUKSI TERSIMPAN (expandable) ── --}}
                    <x-border-form title="Daftar Instruksi Tersimpan">
                        @if (count($instruksiList ?? []))
                            <div class="mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tgl / Jam</th>
                                            <th class="px-4 py-3 border-b">Ringkasan</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($instruksiList) as $entry)
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
                                                    {{ $entry['tanggal'] ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['bilaKesakitan'] ? Str::limit($entry['bilaKesakitan'], 45) : '-' }}
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak instruksi ini">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus instruksi ini?"
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
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bila Kesakitan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['bilaKesakitan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bila Mual / Muntah</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['bilaMualMuntah'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Antibiotik</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['antibiotik'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat-obatan Lain</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['obatLain'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Minum</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['minum'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Infus</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['infus'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Monitor</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                @php
                                                                    $mon = [];
                                                                    if (!empty($entry['monitorTensi'])) $mon[] = 'Tensi';
                                                                    if (!empty($entry['monitorNadi'])) $mon[] = 'Nadi';
                                                                    if (!empty($entry['monitorNafas'])) $mon[] = 'Nafas';
                                                                @endphp
                                                                {{ $mon ? implode(', ', $mon) : '-' }}
                                                                @if (!empty($entry['monitorSetiap']) || !empty($entry['monitorSelama']))
                                                                    <span class="text-sm text-muted-soft">— setiap {{ $entry['monitorSetiap'] ?: '-' }}, selama {{ $entry['monitorSelama'] ?: '-' }}</span>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lain-lain</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['lainLain'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ahli Anestesiologi (TTD)</dt>
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
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada instruksi tersimpan.</p>
                        @endif
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700 shrink-0">
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
                                    title="Kosongkan form untuk menambah instruksi lain — entri yang sudah tersimpan tidak berubah">
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
