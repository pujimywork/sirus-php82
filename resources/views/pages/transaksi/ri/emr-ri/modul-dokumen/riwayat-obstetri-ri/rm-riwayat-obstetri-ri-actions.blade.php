<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/riwayat-obstetri-ri/rm-riwayat-obstetri-ri-actions.blade.php
// Dokumen VK/Kebidanan — Riwayat Obstetri (RM 44.b).
// Tabel riwayat kehamilan lalu (repeating rows) + header G-P-A.
// Pola sama dgn Pengkajian Awal Obstetri (modul dokumen RI): multi-entri, simpan ke datadaftarri_json.

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
    protected array $renderAreas = ['modal-riwayat-obstetri-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'riwayatObstetriRI';

    public array $newForm = [
        'gravida' => '',
        'para'    => '',
        'abortus' => '',
        'ttd'     => '',   // nama penanda-tangan (myuser_name)
        'ttdDate' => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode' => '',   // myuser_code penanda-tangan
        'rows'    => [],
    ];

    public array $entriList = [];

    protected function rules(): array
    {
        return [];
    }

    protected function messages(): array
    {
        return [];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-riwayat-obstetri-ri']);

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

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
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

        $this->incrementVersion('modal-riwayat-obstetri-ri');
        $this->dispatch('open-modal', name: 'riwayat-obstetri-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'riwayat-obstetri-ri');
    }

    /** TTD: stamp nama user login + tgl/jam ke entri saat ini. */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (!empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan sudah ada.');
            return;
        }
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /** Batalkan TTD (untuk tanda tangan ulang). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    private function emptyRow(): array
    {
        return [
            'kehamilan'        => '',   // Aterm | Prematur | Imatur | Abortus
            'caraPersalinan'   => '',   // Spontan | Tindakan | Operasi
            'tempat'           => '',   // Rumah | PKM | Klinik | RS
            'penolong'         => '',   // Dukun | Bidan | Perawat | Dokter | Lain
            'komplikasi'       => '',
            'jenisKelaminAnak' => '',   // L | P
            'keadaanAnak'      => '',   // Hidup | Mati
            'umurAnak'         => '',
            'bbl'              => '',    // gram
            'keterangan'       => '',
        ];
    }

    public function addRow(): void
    {
        $this->newForm['rows'][] = $this->emptyRow();
    }

    public function removeRow(int $i): void
    {
        if (isset($this->newForm['rows'][$i])) {
            unset($this->newForm['rows'][$i]);
            $this->newForm['rows'] = array_values($this->newForm['rows']);
        }
    }

    private function resetNewForm(): void
    {
        $this->newForm = [
            'gravida' => '',
            'para'    => '',
            'abortus' => '',
            'ttd'     => '',
            'ttdCode' => '',
            'ttdDate' => '',
            'rows'    => [],
        ];
    }

    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        $entry = $this->newForm;
        $entry['createdAt'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                    $fresh[$this->jsonKey] = [];
                }
                $fresh[$this->jsonKey][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Riwayat Obstetri — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Riwayat obstetri berhasil disimpan.');
            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Riwayat Obstetri — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data riwayat obstetri tidak ditemukan.');
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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.riwayat-obstetri-ri.cetak-riwayat-obstetri-ri-print', ['data' => $data])->setPaper('A4', 'landscape');

            return response()->streamDownload(fn() => print $pdf->output(), 'riwayat-obstetri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $roCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Riwayat Obstetri</h3>
                    @if ($roCount > 0)
                        <x-badge variant="success">{{ $roCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Riwayat kehamilan/persalinan yang lalu (RM 44.b) — tabel per kehamilan: cara persalinan, tempat,
                    penolong, komplikasi, keadaan & keterangan anak. Header G-P-A. Diisi Bidan/Dokter.
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
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="riwayat-obstetri-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-riwayat-obstetri-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Riwayat Obstetri</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 44.b — riwayat kehamilan lalu (VK). Diisi Bidan / Dokter.</p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-6xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="riwayat-obstetri-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- 1. Status Obstetri (G-P-A) --}}
                        <x-border-form title="1. Status Obstetri">
                            <div class="grid grid-cols-3 gap-4 sm:grid-cols-4">
                                <div><x-input-label value="Gravida (G)" /><x-text-input type="number" wire:model="newForm.gravida" class="w-full mt-1" /></div>
                                <div><x-input-label value="Para (P)" /><x-text-input type="number" wire:model="newForm.para" class="w-full mt-1" /></div>
                                <div><x-input-label value="Abortus (A)" /><x-text-input type="number" wire:model="newForm.abortus" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 2. Tabel Riwayat Kehamilan Lalu --}}
                        <x-border-form title="2. Riwayat Kehamilan / Persalinan Yang Lalu">
                            <div class="space-y-3">
                                <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="text-xs text-left tracking-wide uppercase text-muted bg-surface-soft border-b border-hairline dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                                                <th class="px-2 py-1.5 font-semibold text-center">No</th>
                                                <th class="px-2 py-1.5 font-semibold">Kehamilan</th>
                                                <th class="px-2 py-1.5 font-semibold">Cara Persalinan</th>
                                                <th class="px-2 py-1.5 font-semibold">Tempat</th>
                                                <th class="px-2 py-1.5 font-semibold">Penolong</th>
                                                <th class="px-2 py-1.5 font-semibold">Komplikasi</th>
                                                <th class="px-2 py-1.5 font-semibold">JK Anak</th>
                                                <th class="px-2 py-1.5 font-semibold">Keadaan</th>
                                                <th class="px-2 py-1.5 font-semibold">Umur</th>
                                                <th class="px-2 py-1.5 font-semibold">BBL (gr)</th>
                                                <th class="px-2 py-1.5 font-semibold">Keterangan</th>
                                                <th class="px-2 py-1.5 font-semibold"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($newForm['rows'] as $i => $row)
                                                <tr wire:key="ro-row-{{ $i }}" class="border-t border-hairline dark:border-gray-700 align-top">
                                                    <td class="px-2 py-1 text-center">{{ $i + 1 }}</td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.kehamilan" class="w-full min-w-[7rem]">
                                                            <option value="">—</option>
                                                            <option value="Aterm">Aterm</option>
                                                            <option value="Prematur">Prematur</option>
                                                            <option value="Imatur">Imatur</option>
                                                            <option value="Abortus">Abortus</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.caraPersalinan" class="w-full min-w-[7rem]">
                                                            <option value="">—</option>
                                                            <option value="Spontan">Spontan</option>
                                                            <option value="Tindakan">Tindakan</option>
                                                            <option value="Operasi">Operasi</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.tempat" class="w-full min-w-[6rem]">
                                                            <option value="">—</option>
                                                            <option value="Rumah">Rumah</option>
                                                            <option value="PKM">PKM</option>
                                                            <option value="Klinik">Klinik</option>
                                                            <option value="RS">RS</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.penolong" class="w-full min-w-[6rem]">
                                                            <option value="">—</option>
                                                            <option value="Dukun">Dukun</option>
                                                            <option value="Bidan">Bidan</option>
                                                            <option value="Perawat">Perawat</option>
                                                            <option value="Dokter">Dokter</option>
                                                            <option value="Lain">Lain</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1"><x-text-input wire:model="newForm.rows.{{ $i }}.komplikasi" class="w-full min-w-[7rem]" /></td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.jenisKelaminAnak" class="w-full min-w-[4.5rem]">
                                                            <option value="">—</option>
                                                            <option value="L">L</option>
                                                            <option value="P">P</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <x-select-input wire:model="newForm.rows.{{ $i }}.keadaanAnak" class="w-full min-w-[5.5rem]">
                                                            <option value="">—</option>
                                                            <option value="Hidup">Hidup</option>
                                                            <option value="Mati">Mati</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-1 py-1"><x-text-input wire:model="newForm.rows.{{ $i }}.umurAnak" class="w-full min-w-[5rem]" placeholder="th/bl" /></td>
                                                    <td class="px-1 py-1"><x-text-input type="number" wire:model="newForm.rows.{{ $i }}.bbl" class="w-full min-w-[5rem]" /></td>
                                                    <td class="px-1 py-1"><x-text-input wire:model="newForm.rows.{{ $i }}.keterangan" class="w-full min-w-[7rem]" /></td>
                                                    <td class="px-1 py-1 text-center">
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click="removeRow({{ $i }})"
                                                                wire:confirm="Hapus baris ini?" wire:loading.attr="disabled"
                                                                class="!p-2 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus baris">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="12" class="px-2 py-3 text-sm text-center text-muted dark:text-gray-400">Belum ada baris riwayat. Klik "Tambah Baris".</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div>
                                    <x-secondary-button type="button" wire:click="addRow" class="gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Tambah Baris
                                    </x-secondary-button>
                                </div>
                            </div>
                        </x-border-form>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Riwayat</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN ── --}}
                    <x-border-form title="Riwayat Obstetri Tersimpan">
                        @forelse ($entriList as $e)
                            <div wire:key="entri-{{ $e['createdAt'] }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-semibold text-ink dark:text-gray-100">{{ $e['createdAt'] }}</span>
                                    <span class="ml-2 text-muted">· G{{ $e['gravida'] ?? '-' }}P{{ $e['para'] ?? '-' }}A{{ $e['abortus'] ?? '-' }}</span>
                                    <span class="ml-2 text-muted">· {{ count($e['rows'] ?? []) }} kehamilan</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-secondary-button type="button" wire:click="cetak('{{ $e['createdAt'] }}')" class="px-3 py-1.5 text-sm">Cetak</x-secondary-button>
                                    @unless ($isFormLocked)
                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                            wire:confirm="Hapus entri riwayat obstetri ini?" class="px-3 py-1.5 text-sm">Hapus</x-danger-button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada riwayat obstetri tersimpan.</p>
                        @endforelse
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-surface-soft border-t shrink-0 border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
