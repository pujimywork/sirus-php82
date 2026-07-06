<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/catatan-terapi-neonatal-ri/rm-catatan-terapi-neonatal-ri-actions.blade.php
// Dokumen VK/Kebidanan — Catatan Terapi & Perencanaan Keperawatan Neonatal (RM 08.c).
// Pola sama dgn Pengkajian Awal Obstetri (modul dokumen RI): multi-entri, simpan ke datadaftarri_json.
// Setiap entri = 1 baris catatan (Terapi Dokter atau Perencanaan Keperawatan).

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
    protected array $renderAreas = ['modal-catatan-terapi-neonatal-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'catatanTerapiNeonatalRI';

    public array $newForm = [
        'jenis'      => '',   // Terapi Dokter | Perencanaan Keperawatan
        'tglJam'     => '',
        'keterangan' => '',   // penatalaksanaan/terapi ATAU perencanaan & tindakan keperawatan
        'icd9'       => '',    // hanya relevan bila jenis Terapi Dokter
        'ttd'        => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'    => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'    => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    public array $jenisOptions = ['Terapi Dokter', 'Perencanaan Keperawatan'];

    protected function rules(): array
    {
        return [
            'newForm.jenis'      => 'required',
            'newForm.keterangan' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'newForm.jenis.required'      => 'Jenis catatan (Terapi Dokter / Perencanaan Keperawatan) harus dipilih.',
            'newForm.keterangan.required' => 'Keterangan harus diisi.',
        ];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-catatan-terapi-neonatal-ri']);

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

        $this->incrementVersion('modal-catatan-terapi-neonatal-ri');
        $this->dispatch('open-modal', name: 'catatan-terapi-neonatal-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'catatan-terapi-neonatal-ri');
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

    public function setTglJamSekarang(): void
    {
        $this->newForm['tglJam'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Catatan Terapi Neonatal — ' . ($entry['jenis'] ?? '-') . ' — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-catatan-terapi-neonatal-ri');
            $this->dispatch('toast', type: 'success', message: 'Catatan terapi neonatal berhasil disimpan.');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Catatan Terapi Neonatal — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-catatan-terapi-neonatal-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function cetakSemua()
    {
        if (empty($this->entriList)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada catatan untuk dicetak.');
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

            $entri = collect($this->entriList);
            $terapiDokter = $entri->filter(fn($e) => ($e['jenis'] ?? '') === 'Terapi Dokter')->values()->all();
            $perencanaan = $entri->filter(fn($e) => ($e['jenis'] ?? '') === 'Perencanaan Keperawatan')->values()->all();

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = collect($this->entriList)->pluck('ttdCode')->filter()->last() ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'        => $this->dataDaftarRi,
                'terapiDokter'  => $terapiDokter,
                'perencanaan'   => $perencanaan,
                'identitasRs'   => $identitasRs,
                'tglCetak'      => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.catatan-terapi-neonatal-ri.cetak-catatan-terapi-neonatal-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'catatan-terapi-neonatal-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $ctnCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Catatan Terapi &amp; Perencanaan Keperawatan Neonatal</h3>
                    @if ($ctnCount > 0)
                        <x-badge variant="success">{{ $ctnCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Catatan terapi dokter (penatalaksanaan &amp; ICD 9 CM) dan perencanaan &amp; tindakan keperawatan
                    untuk pasien neonatal (RM 08.c). Diisi Dokter dan Perawat/Bidan.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if ($ctnCount > 0)
                    <x-secondary-button type="button" wire:click="cetakSemua" wire:loading.attr="disabled" wire:target="cetakSemua" class="gap-1.5">
                        <span wire:loading.remove wire:target="cetakSemua">Cetak Catatan</span>
                        <span wire:loading wire:target="cetakSemua">Menyiapkan…</span>
                    </x-secondary-button>
                @endif
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
    <x-modal name="catatan-terapi-neonatal-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-catatan-terapi-neonatal-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Catatan Terapi &amp; Perencanaan Keperawatan Neonatal</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 08.c — neonatal (VK). Diisi Dokter &amp; Perawat/Bidan.</p>
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
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="catatan-neonatal-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        <x-border-form title="Tambah Catatan">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Jenis Catatan" />
                                        <x-select-input wire:model.live="newForm.jenis" class="w-full mt-1" :error="$errors->has('newForm.jenis')">
                                            <option value="">—</option>
                                            @foreach ($jenisOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.jenis')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Tanggal / Jam" />
                                        <div class="flex gap-1 mt-1">
                                            <x-text-input wire:model="newForm.tglJam" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                            <x-now-button wire:click="setTglJamSekarang" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="ICD 9 CM (khusus Terapi Dokter)" />
                                        <x-text-input wire:model="newForm.icd9" class="w-full mt-1" placeholder="Kode / prosedur"
                                            :disabled="($newForm['jenis'] ?? '') !== 'Terapi Dokter'" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Keterangan" />
                                    <x-textarea wire:model="newForm.keterangan" rows="3" class="w-full mt-1" :error="$errors->has('newForm.keterangan')"
                                        placeholder="Penatalaksanaan / terapi (Terapi Dokter) atau perencanaan &amp; tindakan keperawatan (Perencanaan Keperawatan)" />
                                    <x-input-error :messages="$errors->get('newForm.keterangan')" class="mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Catatan</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN ── --}}
                    <x-border-form title="Catatan Tersimpan">
                        @if (count($entriList ?? []) > 0)
                            <div class="flex justify-end mb-3">
                                <x-secondary-button type="button" wire:click="cetakSemua" wire:loading.attr="disabled" wire:target="cetakSemua" class="px-3 py-1.5 text-sm">
                                    <span wire:loading.remove wire:target="cetakSemua">Cetak Catatan</span>
                                    <span wire:loading wire:target="cetakSemua">Menyiapkan…</span>
                                </x-secondary-button>
                            </div>
                        @endif
                        @forelse ($entriList as $e)
                            <div wire:key="entri-{{ $e['createdAt'] }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 mb-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-semibold text-ink dark:text-gray-100">{{ $e['tglJam'] ?: $e['createdAt'] }}</span>
                                    <x-badge :variant="($e['jenis'] ?? '') === 'Terapi Dokter' ? 'info' : 'success'" class="ml-2">{{ $e['jenis'] ?? '-' }}</x-badge>
                                    @if (filled($e['icd9'] ?? null))
                                        <span class="ml-2 text-muted">· ICD9: {{ $e['icd9'] }}</span>
                                    @endif
                                    <div class="text-xs text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($e['keterangan'] ?? '', 100) }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @unless ($isFormLocked)
                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                            wire:confirm="Hapus catatan ini?" class="px-3 py-1.5 text-sm">Hapus</x-danger-button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada catatan tersimpan.</p>
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
