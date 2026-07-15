<?php
// resources/views/pages/transaksi/ri/emr-ri/perencanaan/rm-perencanaan-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use App\Support\DischargePlanningOptions;
use App\Support\DischargeDisposition;
use App\Support\NomorSuratKematian;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public bool $isBPJS = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    // Form entri Discharge Planning (multi-entri). Ditambahkan ke array in-memory,
    // dipersist saat store() — sama pola dengan field perencanaan lain di form ini.
    public array $formPelayanan = [];
    public array $formAlat = [];

    // Opsi status pulang = SUMBER TUNGGAL di App\Support\DischargeDisposition::OPTIONS
    // (dipakai bareng cetak ringkasan pulang). Diisi saat mount().
    public array $tindakLanjutOptions = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-perencanaan-ri']);
        $this->tindakLanjutOptions = DischargeDisposition::OPTIONS;
    }

    #[On('open-rm-perencanaan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['perencanaan'] ??= [
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'tindakLanjutKode' => '',
                'statusPulang' => '',
                'noSuratMeninggal' => '',
                'tglMeninggal' => '',
                'tglPulang' => '',
                'noLPManual' => '',
                'noSep' => '',
            ],
            'dischargePlanning' => [
                'pelayananBerkelanjutan' => ['pelayananBerkelanjutan' => 'Tidak Ada', 'ketPelayananBerkelanjutan' => '', 'pelayananBerkelanjutanData' => []],
                'penggunaanAlatBantu' => ['penggunaanAlatBantu' => 'Tidak Ada', 'ketPenggunaanAlatBantu' => '', 'penggunaanAlatBantuData' => []],
            ],
        ];

        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $this->dataDaftarRi['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $this->isBPJS = $klaimStatus === 'BPJS';

        if ($this->isBPJS && empty($this->dataDaftarRi['perencanaan']['tindakLanjut']['noSep'])) {
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['noSep'] = $this->dataDaftarRi['sep']['noSep'] ?? '';
        }

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-perencanaan-ri');
    }

    public function updatedDataDaftarRiPerencanaanTindakLanjutTindakLanjut(string $val): void
    {
        $opt = collect($this->tindakLanjutOptions)->firstWhere('tindakLanjutKode', $val);
        if ($opt) {
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjutKode'] = $opt['tindakLanjutKode'];
            $this->dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] = $opt['tindakLanjutKodeBpjs'];

            // Meninggal (statusPulang BPJS 4): No. Surat Meninggal WAJIB saat update pulang
            // SEP (VclaimTrait: required_if statusPulang=4|min:5), tapi kenyataannya sering
            // dibiarkan kosong. Isi otomatis dgn stempel waktu — pola yyyymmddhh24miss yang
            // sama dgn Surat Kematian UGD, tak butuh sequence/DDL & tak mungkin bentrok.
            //
            // JANGAN timpa yang sudah terisi: bisa nomor manual RS yang sudah terlanjur
            // dilaporkan/dicetak. Field tetap bisa diedit petugas — ini default, bukan kunci.
            if ((string) $opt['tindakLanjutKodeBpjs'] === '4' && trim((string) ($this->dataDaftarRi['perencanaan']['tindakLanjut']['noSuratMeninggal'] ?? '')) === '') {
                $this->dataDaftarRi['perencanaan']['tindakLanjut']['noSuratMeninggal'] = NomorSuratKematian::generate();
            }
        }
        $this->store();
    }

    /** @return array<string, string> */
    private function defaultFormPelayanan(): array
    {
        return ['jenisPelayanan' => '', 'ketJenis' => '', 'tempatFasyankes' => '', 'tglRencana' => ''];
    }

    /** @return array<string, string> */
    private function defaultFormAlat(): array
    {
        return ['jenisAlat' => '', 'ketAlat' => '', 'durasi' => '', 'sumberAlat' => ''];
    }

    /**
     * Tambah entri pelayanan berkelanjutan.
     * snomedCode disimpan PER ENTRI (bukan dicari saat kirim) supaya record lama tetap
     * memakai kode saat dicatat, walau daftar opsi berubah kemudian.
     */
    public function tambahPelayanan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        // validate() DULU, sebelum guard lain — kalau di-guard duluan, field wajib
        // tak pernah dapat border merah.
        $this->validateWithToast([
            'formPelayanan.jenisPelayanan' => 'required',
            'formPelayanan.tglRencana' => 'nullable|date_format:d/m/Y',
            'formPelayanan.ketJenis' => 'required_if:formPelayanan.jenisPelayanan,Lainnya',
        ], [
            'formPelayanan.jenisPelayanan.required' => 'Jenis pelayanan wajib dipilih.',
            'formPelayanan.tglRencana.date_format' => 'Tanggal rencana harus dd/mm/yyyy.',
            'formPelayanan.ketJenis.required_if' => 'Keterangan wajib diisi bila memilih Lainnya.',
        ]);

        $jenis = trim((string) $this->formPelayanan['jenisPelayanan']);
        $snomed = DischargePlanningOptions::pelayanan($jenis);

        $this->dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanData'][] = [
            'id'              => (string) Str::uuid(),
            'jenisPelayanan'  => $jenis,
            'ketJenis'        => trim((string) ($this->formPelayanan['ketJenis'] ?? '')),
            'tempatFasyankes' => trim((string) ($this->formPelayanan['tempatFasyankes'] ?? '')),
            'tglRencana'      => trim((string) ($this->formPelayanan['tglRencana'] ?? '')),
            'snomedCode'      => $snomed['code'] ?? '',
            'snomedDisplay'   => $snomed['display'] ?? '',
            'petugas'         => auth()->user()->myuser_name ?? '',
            'petugasCode'     => auth()->user()->myuser_code ?? '',
            'tglPencatatan'   => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
        $this->formPelayanan = $this->defaultFormPelayanan();
        $this->store('Tambah pelayanan berkelanjutan — ' . $jenis);
    }

    public function hapusPelayanan(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $rows = $this->dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanData'] ?? [];
        if (!isset($rows[$index])) {
            return;
        }
        $jenis = $rows[$index]['jenisPelayanan'] ?? '-';
        unset($rows[$index]);
        $this->dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanData'] = array_values($rows);
        $this->store('Hapus pelayanan berkelanjutan — ' . $jenis);
    }

    /** Tambah entri alat bantu. snomedCode disimpan per entri (alasan sama seperti pelayanan). */
    public function tambahAlat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->validateWithToast([
            'formAlat.jenisAlat' => 'required',
            'formAlat.ketAlat' => 'required_if:formAlat.jenisAlat,Lainnya',
        ], [
            'formAlat.jenisAlat.required' => 'Jenis alat bantu wajib dipilih.',
            'formAlat.ketAlat.required_if' => 'Keterangan wajib diisi bila memilih Lainnya.',
        ]);

        $jenis = trim((string) $this->formAlat['jenisAlat']);
        $snomed = DischargePlanningOptions::alatBantu($jenis);

        $this->dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuData'][] = [
            'id'            => (string) Str::uuid(),
            'jenisAlat'     => $jenis,
            'ketAlat'       => trim((string) ($this->formAlat['ketAlat'] ?? '')),
            'durasi'        => trim((string) ($this->formAlat['durasi'] ?? '')),
            'sumberAlat'    => trim((string) ($this->formAlat['sumberAlat'] ?? '')),
            'snomedCode'    => $snomed['code'] ?? '',
            'snomedDisplay' => $snomed['display'] ?? '',
            'petugas'       => auth()->user()->myuser_name ?? '',
            'petugasCode'   => auth()->user()->myuser_code ?? '',
            'tglPencatatan' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
        $this->formAlat = $this->defaultFormAlat();
        $this->store('Tambah alat bantu — ' . $jenis);
    }

    public function hapusAlat(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $rows = $this->dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuData'] ?? [];
        if (!isset($rows[$index])) {
            return;
        }
        $jenis = $rows[$index]['jenisAlat'] ?? '-';
        unset($rows[$index]);
        $this->dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuData'] = array_values($rows);
        $this->store('Hapus alat bantu — ' . $jenis);
    }

    #[On('save-rm-perencanaan-ri')]
    public function store(?string $logKeterangan = null): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($logKeterangan) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $isBaru = empty($fresh['perencanaan']);
                $fresh['perencanaan'] = $this->dataDaftarRi['perencanaan'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $tl = $fresh['perencanaan']['tindakLanjut'] ?? [];
                $tlDesc = collect($this->tindakLanjutOptions)->firstWhere('tindakLanjutKode', $tl['tindakLanjutKode'] ?? '')['tindakLanjut'] ?? '-';
                $tglPulang = $tl['tglPulang'] ?? '';
                $defaultKet = ($isBaru ? 'Buat' : 'Update') . ' Perencanaan RI — ' . $tlDesc . ($tglPulang !== '' ? ' (pulang ' . $tglPulang . ')' : '');
                $this->appendAdminLogRI((int) $this->riHdrNo, $logKeterangan ?? $defaultKet, 'MR');
            });

            $this->afterSave('Perencanaan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function setTglPulang(): void
    {
        $this->dataDaftarRi['perencanaan']['tindakLanjut']['tglPulang'] = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setTglMeninggal(): void
    {
        $this->dataDaftarRi['perencanaan']['tindakLanjut']['tglMeninggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setTglRencana(): void
    {
        $this->formPelayanan['tglRencana'] = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function updateTglPulangBPJS(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Simpan JSON dulu sebelum kirim ke BPJS
        $this->store('Update Tgl Pulang BPJS — pulang ' . ($this->dataDaftarRi['perencanaan']['tindakLanjut']['tglPulang'] ?? '-'));

        $tindak = $this->dataDaftarRi['perencanaan']['tindakLanjut'] ?? [];

        // Cek klaim BPJS
        $klaimStatus = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $this->dataDaftarRi['klaimId'] ?? '')
            ->value('klaim_status') ?? 'UMUM';

        if ($klaimStatus !== 'BPJS') {
            $this->dispatch('toast', type: 'error', message: 'Gagal: Klaim ini bukan BPJS.');
            return;
        }

        $noSep = $tindak['noSep'] ?? '';
        if (empty($noSep)) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: Nomor SEP belum tersedia.');
            return;
        }

        $tglSepRaw = $this->dataDaftarRi['sep']['reqSep']['request']['t_sep']['tglSep'] ?? null;
        if (empty($tglSepRaw)) {
            $this->dispatch('toast', type: 'error', message: 'Pembuatan SEP bukan melalui siRUS, Tgl SEP belum tersedia.');
            return;
        }

        // Konversi format tanggal dd/mm/yyyy → Y-m-d
        try {
            $tglPulang = !empty($tindak['tglPulang'])
                ? Carbon::createFromFormat('d/m/Y', $tindak['tglPulang'])->format('Y-m-d')
                : null;
            $tglMeninggal = !empty($tindak['tglMeninggal'])
                ? Carbon::createFromFormat('d/m/Y', $tindak['tglMeninggal'])->format('Y-m-d')
                : null;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Format tanggal tidak valid: ' . $e->getMessage());
            return;
        }

        $SEPJsonReq = [
            'request' => [
                't_sep' => [
                    'noSep'             => $noSep,
                    'statusPulang'      => $tindak['statusPulang'] ?? '',
                    'noSuratMeninggal'  => $tindak['noSuratMeninggal'] ?? '',
                    'tglMeninggal'      => $tglMeninggal,
                    'tglPulang'         => $tglPulang,
                    'noLPManual'        => $tindak['noLPManual'] ?? '',
                    'user'              => 'siRUS',
                    'isKLL'             => $tindak['isKLL'] ?? 0,
                    'tglSep'            => $tglSepRaw,
                    'isAlreadyReferred' => false,
                ],
            ],
        ];

        $HttpGetBpjs = VclaimTrait::sep_updtglplg($SEPJsonReq)->getOriginalContent();

        if (($HttpGetBpjs['metadata']['code'] ?? 0) == 200) {
            $this->dispatch('toast', type: 'success', message: 'Update Tgl Pulang BPJS: ' . $HttpGetBpjs['metadata']['message']);
        } else {
            $this->dispatch('toast', type: 'error', message: 'Update Tgl Pulang BPJS: ' . ($HttpGetBpjs['metadata']['message'] ?? 'Gagal'));
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-perencanaan-ri');
        $this->dispatch('refresh-after-ri.saved');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->formPelayanan = $this->defaultFormPelayanan();
        $this->formAlat = $this->defaultFormAlat();
    }

};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-perencanaan-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{
        sectionDirty: false,
        openedAt: 0,
        tab: 'perencanaan',
        markDirty() {
            if (!this.sectionDirty && Date.now() - this.openedAt > 300) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.tab });
            }
        },
    }"
    x-init="
        openedAt = Date.now();
        window.addEventListener('refresh-after-ri.saved', () => {
            sectionDirty = false;
            openedAt = Date.now();
            $dispatch('section-clean', { tab: tab });
        });
    "
    x-on:input="markDirty()"
    x-on:change="markDirty()">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── TINDAK LANJUT ── --}}
    <x-border-form title="Tindak Lanjut / Rencana Pulang" align="start" bgcolor="bg-surface-soft">
        <div class="mt-3 space-y-4">

            {{-- Radio tindak lanjut --}}
            <div>
                <x-input-label value="Tindak Lanjut *" />
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($tindakLanjutOptions as $opt)
                        <x-radio-button :label="$opt['tindakLanjut']" :value="$opt['tindakLanjutKode']" name="tindakLanjut"
                            wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tindakLanjut" :disabled="$isFormLocked" />
                        {{-- x-radio-button sudah include label --}}
                    @endforeach
                </div>
                @if (!empty($dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjut']))
                    <p class="mt-1 text-xs text-muted">
                        Kode SNOMED: <span
                            class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['tindakLanjutKode'] ?? '-' }}</span>
                        | Status BPJS: <span
                            class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '-' }}</span>
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- Tanggal Pulang --}}
                <div>
                    <x-input-label value="Tanggal Pulang" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tglPulang" class="flex-1"
                            placeholder="dd/mm/yyyy" :disabled="$isFormLocked" />
                        @if (!$isFormLocked)
                            <x-secondary-button wire:click="setTglPulang" type="button" class="text-xs">Hari
                                Ini</x-secondary-button>
                        @endif
                    </div>
                </div>

                {{-- Keterangan --}}
                <div>
                    <x-input-label value="Keterangan Tindak Lanjut" />
                    <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.keteranganTindakLanjut"
                        class="w-full mt-1" :disabled="$isFormLocked" />
                </div>
            </div>

            {{-- Jika Meninggal (statusPulang: 4) --}}
            @if (($dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '') == 4)
                <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div>
                        <x-input-label value="No. Surat Keterangan Meninggal *" />
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noSuratMeninggal"
                            class="w-full mt-1" :disabled="$isFormLocked" placeholder="Wajib diisi jika meninggal" />
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Terisi otomatis (stempel waktu) saat status Meninggal dipilih. Boleh diganti bila RS
                            memakai nomor sendiri. Dikirim ke BPJS saat update pulang SEP.
                        </p>
                    </div>
                    <div>
                        <x-input-label value="Tanggal Meninggal *" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.tglMeninggal"
                                class="flex-1" placeholder="dd/mm/yyyy" :disabled="$isFormLocked" />
                            @if (!$isFormLocked)
                                <x-now-button wire:click="setTglMeninggal" />
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Jika KLL (Kecelakaan Lalu Lintas) --}}
            @if ($dataDaftarRi['kPolisi'] ?? false)
                <div class="p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                    <div>
                        <x-input-label value="No. Laporan Polisi (KLL) *" />
                        <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noLPManual"
                            class="w-full mt-1" :disabled="$isFormLocked" placeholder="Wajib diisi untuk kasus KLL" />
                    </div>
                </div>
            @endif

            {{-- BPJS: No SEP + Tombol Update Pulang — hide untuk Dokter (domain Perawat) --}}
            @if ($isBPJS && !auth()->user()->hasRole('Dokter'))
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div>
                        <x-input-label value="No. SEP" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model.live="dataDaftarRi.perencanaan.tindakLanjut.noSep"
                                class="flex-1 font-mono" :disabled="$isFormLocked" />
                            @if (!$isFormLocked)
                                <x-success-button wire:click="updateTglPulangBPJS" type="button"
                                    wire:confirm="Kirim update tanggal pulang ke BPJS?"
                                    wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="updateTglPulangBPJS" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                        </svg>
                                        Update Pasien Pulang BPJS
                                    </span>
                                    <span wire:loading wire:target="updateTglPulangBPJS" class="inline-flex items-center gap-2">
                                        <x-loading /> Mengirim ke BPJS...
                                    </span>
                                </x-success-button>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-muted">
                            Status Pulang: <span class="font-mono">{{ $dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '-' }}</span>
                            (1: Atas Persetujuan Dokter, 3: Atas Permintaan Sendiri, 4: Meninggal, 5: Lain-lain)
                        </p>
                    </div>
                </div>
            @endif

        </div>
    </x-border-form>

    {{-- ── DISCHARGE PLANNING ── --}}
    <x-border-form title="Discharge Planning" align="start" bgcolor="bg-surface-soft">
        <div class="mt-3 grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Pelayanan Berkelanjutan" />
                <div class="mt-2 flex gap-4">
                    @foreach (['Ada', 'Tidak Ada'] as $opt)
                        <x-radio-button :label="$opt" :value="$opt" name="pelayananBerkelanjutan"
                            wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan"
                            :disabled="$isFormLocked" />
                    @endforeach
                </div>
                @if (
                    ($dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ?? '') ===
                        'Ada')
                    {{-- Entri terstruktur: tiap baris membawa kode SNOMED-nya sendiri (untuk CarePlan). --}}
                    <div class="mt-3 space-y-2 rounded-lg border border-hairline p-3 dark:border-gray-700">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <x-input-label value="Jenis Pelayanan *" />
                                <x-select-input wire:model="formPelayanan.jenisPelayanan" class="mt-1 w-full"
                                    :error="$errors->has('formPelayanan.jenisPelayanan')" :disabled="$isFormLocked">
                                    <option value="">-- pilih --</option>
                                    @foreach (\App\Support\DischargePlanningOptions::PELAYANAN as $opt)
                                        <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formPelayanan.jenisPelayanan')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Rencana" />
                                <div class="mt-1 flex gap-2">
                                    <x-text-input wire:model="formPelayanan.tglRencana" class="flex-1"
                                        :error="$errors->has('formPelayanan.tglRencana')" placeholder="dd/mm/yyyy"
                                        :disabled="$isFormLocked" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setTglRencana" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('formPelayanan.tglRencana')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tempat / Fasyankes" />
                                <x-text-input wire:model="formPelayanan.tempatFasyankes" class="mt-1 w-full"
                                    placeholder="mis. Puskesmas Ngunut" :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan" />
                                <x-text-input wire:model="formPelayanan.ketJenis" class="mt-1 w-full"
                                    :error="$errors->has('formPelayanan.ketJenis')" placeholder="wajib diisi bila Lainnya"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('formPelayanan.ketJenis')" class="mt-1" />
                            </div>
                        </div>
                        <x-ghost-button type="button" wire:click="tambahPelayanan" wire:loading.attr="disabled"
                            :disabled="$isFormLocked" class="text-xs">+ Tambah Pelayanan</x-ghost-button>
                    </div>

                    @php
                        $rowsPelayanan =
                            $dataDaftarRi['perencanaan']['dischargePlanning']['pelayananBerkelanjutan'][
                                'pelayananBerkelanjutanData'
                            ] ?? [];
                    @endphp
                    @if (count($rowsPelayanan) > 0)
                        <div class="mt-2 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                            <table class="ds-table">
                                <thead>
                                    <tr class="text-left">
                                        <th class="min-w-[10rem]">Jenis Pelayanan</th>
                                        <th class="min-w-[11rem]">Tempat / Tgl Rencana</th>
                                        <th class="min-w-[8rem]">Keterangan</th>
                                        <th class="ds-c">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rowsPelayanan as $i => $row)
                                        <tr>
                                            <td>
                                                <div class="ds-td-strong">{{ $row['jenisPelayanan'] ?? '-' }}</div>
                                                @if (!empty($row['snomedCode']))
                                                    <div class="ds-td-meta">{{ $row['snomedCode'] }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $row['tempatFasyankes'] ?: '-' }}</div>
                                                @if (!empty($row['tglRencana']))
                                                    <div class="ds-td-meta">{{ $row['tglRencana'] }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $row['ketJenis'] ?: '-' }}</td>
                                            <td class="ds-c">
                                                @if (!$isFormLocked)
                                                    <x-confirm-button variant="danger-soft" :action="'hapusPelayanan(' . $i . ')'"
                                                        title="Hapus Pelayanan"
                                                        :message="'Yakin hapus ' . ($row['jenisPelayanan'] ?? 'entri ini') . '?'"
                                                        confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-confirm-button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
            <div>
                <x-input-label value="Penggunaan Alat Bantu" />
                <div class="mt-2 flex gap-4">
                    @foreach (['Ada', 'Tidak Ada'] as $opt)
                        <x-radio-button :label="$opt" :value="$opt" name="penggunaanAlatBantu"
                            wire:model.live="dataDaftarRi.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu"
                            :disabled="$isFormLocked" />
                    @endforeach
                </div>
                @if (($dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantu'] ?? '') === 'Ada')
                    {{-- Entri terstruktur: tiap baris membawa kode SNOMED-nya sendiri (untuk CarePlan). --}}
                    <div class="mt-3 space-y-2 rounded-lg border border-hairline p-3 dark:border-gray-700">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <x-input-label value="Jenis Alat Bantu *" />
                                <x-select-input wire:model="formAlat.jenisAlat" class="mt-1 w-full"
                                    :error="$errors->has('formAlat.jenisAlat')" :disabled="$isFormLocked">
                                    <option value="">-- pilih --</option>
                                    @foreach (\App\Support\DischargePlanningOptions::ALAT_BANTU as $opt)
                                        <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formAlat.jenisAlat')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Sumber Alat" />
                                <x-select-input wire:model="formAlat.sumberAlat" class="mt-1 w-full"
                                    :disabled="$isFormLocked">
                                    <option value="">-- pilih --</option>
                                    @foreach (\App\Support\DischargePlanningOptions::SUMBER_ALAT as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label value="Durasi Penggunaan" />
                                <x-text-input wire:model="formAlat.durasi" class="mt-1 w-full"
                                    placeholder="mis. 2 minggu / seterusnya" :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan" />
                                <x-text-input wire:model="formAlat.ketAlat" class="mt-1 w-full"
                                    :error="$errors->has('formAlat.ketAlat')" placeholder="wajib diisi bila Lainnya"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('formAlat.ketAlat')" class="mt-1" />
                            </div>
                        </div>
                        <x-ghost-button type="button" wire:click="tambahAlat" wire:loading.attr="disabled"
                            :disabled="$isFormLocked" class="text-xs">+ Tambah Alat Bantu</x-ghost-button>
                    </div>

                    @php
                        $rowsAlat =
                            $dataDaftarRi['perencanaan']['dischargePlanning']['penggunaanAlatBantu'][
                                'penggunaanAlatBantuData'
                            ] ?? [];
                    @endphp
                    @if (count($rowsAlat) > 0)
                        <div class="mt-2 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                            <table class="ds-table">
                                <thead>
                                    <tr class="text-left">
                                        <th class="min-w-[10rem]">Jenis Alat Bantu</th>
                                        <th class="min-w-[8rem]">Sumber Alat</th>
                                        <th class="min-w-[7rem]">Durasi</th>
                                        <th class="min-w-[8rem]">Keterangan</th>
                                        <th class="ds-c">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rowsAlat as $i => $row)
                                        <tr>
                                            <td>
                                                <div class="ds-td-strong">{{ $row['jenisAlat'] ?? '-' }}</div>
                                                @if (!empty($row['snomedCode']))
                                                    <div class="ds-td-meta">{{ $row['snomedCode'] }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $row['sumberAlat'] ?: '-' }}</td>
                                            <td>{{ $row['durasi'] ?: '-' }}</td>
                                            <td>{{ $row['ketAlat'] ?: '-' }}</td>
                                            <td class="ds-c">
                                                @if (!$isFormLocked)
                                                    <x-confirm-button variant="danger-soft" :action="'hapusAlat(' . $i . ')'"
                                                        title="Hapus Alat Bantu"
                                                        :message="'Yakin hapus ' . ($row['jenisAlat'] ?? 'entri ini') . '?'"
                                                        confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-confirm-button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </x-border-form>

</div>
