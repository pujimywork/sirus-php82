<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/observasi-persalinan-ri/rm-observasi-persalinan-ri-actions.blade.php
// Dokumen VK/Kebidanan — Observasi Persalinan (lembar pemantauan per titik-waktu).
// Pola sama dgn Pengkajian Awal Obstetri: multi-entri, simpan ke datadaftarri_json.
// Beda: tiap entri = 1 titik-waktu observasi; cetak = SATU lembar tabel monitoring semua baris.
// [akr] = tambahan akreditasi (PP/PAP — Maternal Early Warning Score).

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
    protected array $renderAreas = ['modal-observasi-persalinan-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'observasiPersalinanRI';

    public array $newForm = [
        'jam'            => '',   // titik-waktu observasi (H:i)
        'td'             => '',   // tekanan darah, teks mmHg (mis. 120/80)
        'nadi'           => '',   // x/mnt
        'rr'             => '',   // x/mnt
        'suhu'           => '',   // °C
        'djj'            => '',   // x/mnt
        'his'            => '',   // teks, mis. "3x10'/40\""
        'ewsScore'       => '',   // [akr] Maternal Early Warning Score
        'obatKeterangan' => '',   // drip / infus / obat / keterangan
        'diagnosa'       => '',   // di-set sekali (boleh diisi tiap entri)
        'ttd'            => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'        => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'        => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    protected function rules(): array
    {
        return [];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-observasi-persalinan-ri']);

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

        $this->incrementVersion('modal-observasi-persalinan-ri');
        $this->dispatch('open-modal', name: 'observasi-persalinan-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'observasi-persalinan-ri');
    }

    public function setJamSekarang(): void
    {
        $this->newForm['jam'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
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

        // Validasi minimal: harus ada jam, dan minimal salah satu TTV/observasi terisi.
        if (blank($this->newForm['jam'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Jam observasi harus diisi.');
            return;
        }
        $adaObservasi = collect(['td', 'nadi', 'rr', 'suhu', 'djj', 'his', 'ewsScore', 'obatKeterangan'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
        if (!$adaObservasi) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu tanda vital / observasi.');
            return;
        }

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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Observasi Persalinan — jam ' . ($entry['jam'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-observasi-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Observasi persalinan berhasil disimpan.');
            // Pertahankan diagnosa & TTD agar entri titik-waktu berikutnya lebih cepat.
            $diagnosa = $this->newForm['diagnosa'] ?? '';
            $ttd = $this->newForm['ttd'] ?? '';
            $ttdCode = $this->newForm['ttdCode'] ?? '';
            $ttdDate = $this->newForm['ttdDate'] ?? '';
            $this->resetNewForm();
            $this->newForm['diagnosa'] = $diagnosa;
            $this->newForm['ttd'] = $ttd;
            $this->newForm['ttdCode'] = $ttdCode;
            $this->newForm['ttdDate'] = $ttdDate;
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Observasi Persalinan — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-observasi-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /** Cetak SELURUH baris observasi sebagai 1 lembar tabel monitoring. */
    public function cetakLembar()
    {
        $rows = collect($this->entriList ?? [])
            ->sortBy(function ($e) {
                try {
                    return Carbon::createFromFormat('d/m/Y H:i:s', $e['jam'] ?? '')->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values()
            ->all();

        if (empty($rows)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada baris observasi untuk dicetak.');
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

            // Diagnosa: ambil dari entri terakhir yang terisi.
            $diagnosa = collect($rows)->pluck('diagnosa')->filter()->last() ?? '';
            // TTD: ambil dari entri terakhir yang sudah ditandatangani.
            $ttd = collect($rows)->pluck('ttd')->filter()->last() ?? '';
            $ttdDate = collect($rows)->pluck('ttdDate')->filter()->last() ?? '';

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = collect($rows)->pluck('ttdCode')->filter()->last() ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'      => $this->dataDaftarRi,
                'rows'        => $rows,
                'diagnosa'    => $diagnosa,
                'ttd'         => $ttd,
                'ttdDate'     => $ttdDate,
                'identitasRs' => $identitasRs,
                'tglCetak'    => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.observasi-persalinan-ri.cetak-observasi-persalinan-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'observasi-persalinan-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $opCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Observasi Persalinan</h3>
                    @if ($opCount > 0)
                        <x-badge variant="success">{{ $opCount }} titik-waktu</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Lembar pemantauan persalinan per titik-waktu — TD, nadi, RR, suhu, DJJ, His,
                    Maternal Early Warning Score (PP/PAP) & catatan obat/drip. Tiap Simpan = 1 baris waktu.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if ($opCount > 0)
                    <x-secondary-button type="button" wire:click="cetakLembar" wire:loading.attr="disabled"
                        wire:target="cetakLembar" class="gap-1.5">
                        <span wire:loading.remove wire:target="cetakLembar" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak Lembar Observasi
                        </span>
                        <span wire:loading wire:target="cetakLembar" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Menyiapkan…
                        </span>
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
    <x-modal name="observasi-persalinan-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-observasi-persalinan-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Observasi Persalinan</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Lembar pemantauan persalinan (VK) — tiap Simpan = 1 titik-waktu. Diisi Bidan / Perawat.</p>
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
                        wire:key="observasi-persalinan-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI (1 titik-waktu) ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- Diagnosa (di-set sekali; boleh diisi tiap entri) --}}
                        <x-border-form title="Diagnosa Kerja">
                            <div>
                                <x-input-label value="Diagnosa" />
                                <x-textarea wire:model="newForm.diagnosa" rows="2" class="w-full mt-1"
                                    placeholder="mis. G1P0000 hamil aterm inpartu kala I fase aktif" />
                            </div>
                        </x-border-form>

                        {{-- Titik-waktu observasi --}}
                        <x-border-form title="Observasi (Titik-Waktu)">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Tgl / Jam" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.jam" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        <x-now-button wire:click="setJamSekarang" />
                                    </div>
                                </div>
                                <div><x-input-label value="TD (mmHg)" /><x-text-input wire:model="newForm.td" class="w-full mt-1" placeholder="120/80" /></div>
                                <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.nadi" class="w-full mt-1" /></div>
                                <div><x-input-label value="RR (x/mnt)" /><x-text-input type="number" wire:model="newForm.rr" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu (°C)" /><x-text-input type="number" step="0.1" wire:model="newForm.suhu" class="w-full mt-1" /></div>
                                <div><x-input-label value="DJJ (x/mnt)" /><x-text-input type="number" wire:model="newForm.djj" class="w-full mt-1" /></div>
                                <div><x-input-label value="His" /><x-text-input wire:model="newForm.his" class="w-full mt-1" placeholder="3x10'/40&quot;" /></div>
                                <div><x-input-label value="EWS Score" /><x-text-input type="number" wire:model="newForm.ewsScore" class="w-full mt-1" placeholder="MEWS" /></div>
                                <div class="col-span-2 lg:col-span-4">
                                    <x-input-label value="Obat / Drip / Keterangan" />
                                    <x-text-input wire:model="newForm.obatKeterangan" class="w-full mt-1" placeholder="mis. RL 20 tpm + Oksitosin 5 IU" />
                                </div>
                            </div>
                        </x-border-form>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Titik-Waktu</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR BARIS OBSERVASI TERSIMPAN ── --}}
                    <x-border-form title="Baris Observasi Tersimpan">
                        @if (count($entriList ?? []))
                            <div class="flex justify-end mb-3">
                                <x-secondary-button type="button" wire:click="cetakLembar" wire:loading.attr="disabled"
                                    wire:target="cetakLembar" class="px-3 py-1.5 text-sm">
                                    <span wire:loading.remove wire:target="cetakLembar">Cetak Lembar Observasi</span>
                                    <span wire:loading wire:target="cetakLembar">Menyiapkan…</span>
                                </x-secondary-button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left border-b border-hairline dark:border-gray-700 text-muted">
                                            <th class="px-2 py-1.5 font-medium">Jam</th>
                                            <th class="px-2 py-1.5 font-medium">TD</th>
                                            <th class="px-2 py-1.5 font-medium">N</th>
                                            <th class="px-2 py-1.5 font-medium">RR</th>
                                            <th class="px-2 py-1.5 font-medium">S</th>
                                            <th class="px-2 py-1.5 font-medium">DJJ</th>
                                            <th class="px-2 py-1.5 font-medium">His</th>
                                            <th class="px-2 py-1.5 font-medium">EWS</th>
                                            <th class="px-2 py-1.5 font-medium">Obat/Ket</th>
                                            <th class="px-2 py-1.5 font-medium text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($entriList as $e)
                                            <tr wire:key="obs-{{ $e['createdAt'] }}" class="border-b border-hairline/60 dark:border-gray-800">
                                                <td class="px-2 py-1.5 font-semibold text-ink dark:text-gray-100">{{ $e['jam'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['td'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['nadi'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['rr'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['suhu'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['djj'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['his'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ $e['ewsScore'] ?? '-' }}</td>
                                                <td class="px-2 py-1.5">{{ \Illuminate\Support\Str::limit($e['obatKeterangan'] ?? '', 40) }}</td>
                                                <td class="px-2 py-1.5 text-right">
                                                    @unless ($isFormLocked)
                                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                                            wire:confirm="Hapus baris observasi ini?" class="px-2.5 py-1 text-xs">Hapus</x-danger-button>
                                                    @endunless
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada baris observasi tersimpan.</p>
                        @endif
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
