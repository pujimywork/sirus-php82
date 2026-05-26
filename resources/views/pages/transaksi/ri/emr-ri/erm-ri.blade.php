<?php
// resources/views/pages/transaksi/ri/emr-ri/erm-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\iCareTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, iCareTrait;

    public string $icareUrlResponse = '';
    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-emr-ri']);
    }

    /* ── i-Care ── */
    public function openModalicare(): void
    {
        $this->dispatch('open-modal', name: 'icare-modal-ri');
    }
    public function closeModalicare(): void
    {
        $this->icareUrlResponse = '';
        $this->dispatch('close-modal', name: 'icare-modal-ri');
    }

    public function myiCare(string $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }
        $regNo = $this->dataDaftarRi['regNo'] ?? '';
        $dataMasterPasien = $this->findDataMasterPasien($regNo);
        $nokartuBpjs = $dataMasterPasien['pasien']['identitas']['idbpjs'] ?? '';
        if (!$nokartuBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS tidak ditemukan.');
            return;
        }
        $drId = $this->dataDaftarRi['drId'] ?? '';
        if (!$drId) {
            $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
            return;
        }
        $kodeDokter = DB::table('rsmst_doctors')->select('kd_dr_bpjs')->where('dr_id', $drId)->first();
        if (!$kodeDokter || !$kodeDokter->kd_dr_bpjs) {
            $this->dispatch('toast', type: 'error', message: 'Dokter tidak memiliki hak akses untuk I-Care.');
            return;
        }
        try {
            $response = $this->icare($nokartuBpjs, $kodeDokter->kd_dr_bpjs)->getOriginalContent();
            if (($response['metadata']['code'] ?? 400) == 200) {
                $this->icareUrlResponse = $response['response']['url'] ?? '';
                $this->openModalicare();
            } else {
                $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal mengakses i-Care');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengakses i-Care: ' . $e->getMessage());
        }
    }

    /* ── Open ── */
    #[On('emr-ri.rekam-medis.open')]
    public function openRekamMedis(string $riHdrNo): void
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $data;

        $riStatus = DB::scalar('select ri_status from rstxn_rihdrs where rihdr_no = :r', ['r' => $riHdrNo]);
        $this->isFormLocked = $riStatus !== 'I';

        $this->dispatch('open-modal', name: 'rm-ri-actions');

        /* Broadcast ke semua child */
        $this->dispatch('open-rm-pengkajian-awal-ri', $riHdrNo);
        $this->dispatch('open-rm-pengkajian-dokter-ri', $riHdrNo);
        $this->dispatch('open-rm-pemeriksaan-ri', $riHdrNo);
        $this->dispatch('open-rm-cppt-ri', $riHdrNo);
        $this->dispatch('open-rm-penilaian-ri', $riHdrNo);
        $this->dispatch('open-rm-diagnosa-ri', $riHdrNo);
        $this->dispatch('open-rm-observasi-ri', $riHdrNo);
        $this->dispatch('open-rm-perencanaan-ri', $riHdrNo);
        $this->dispatch('open-rm-asuhan-keperawatan-ri', $riHdrNo);
        $this->dispatch('open-rm-edukasi-pasien-ri', $riHdrNo);
        $this->dispatch('open-rm-inform-consent-ri', $riHdrNo);
        $this->dispatch('open-rm-general-consent-ri', $riHdrNo);
        $this->dispatch('open-rm-case-manager-ri', $riHdrNo);

        // SKDP hanya untuk BPJS
        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $data['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';
        if ($klaimStatus === 'BPJS') {
            $this->dispatch('open-rm-skdp-ri', $riHdrNo);
        }
    }

    /* ── Close ── */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-ri-actions');
    }

    /* ── Shortcuts ── */
    public function openAdministrasiPasien(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $riHdrNo);
    }
    public function openPindahKamar(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.pindah-kamar.open', riHdrNo: $riHdrNo);
    }
    public function openModulDokumen(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.modul-dokumen.open', riHdrNo: $riHdrNo);
    }
    public function openEresep(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.eresep.open', riHdrNo: (int) $riHdrNo);
    }

    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRi']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="rm-ri-actions" size="full" height="full" focusable>
        <x-tabbed-dirty-modal-content
            name="rm-ri-actions"
            savedEvent="refresh-after-ri.saved"
            :wireKey="$this->renderKey('modal-emr-ri', [$riHdrNo ?? 'new'])"
            :tabs="[
                ['key' => 'pengkajian-perawat', 'label' => 'Pengkajian Perawat', 'saveEvent' => 'save-rm-pengkajian-awal-ri'],
                ['key' => 'pengkajian-dokter', 'label' => 'Pengkajian Dokter', 'saveEvent' => 'save-rm-pengkajian-dokter-ri'],
                ['key' => 'diagnosa', 'label' => 'Diagnosis', 'saveEvent' => 'save-rm-diagnosa-ri'],
                ['key' => 'perencanaan', 'label' => 'Perencanaan', 'saveEvent' => 'save-rm-perencanaan-ri'],
                ['key' => 'penilaian', 'label' => 'Penilaian Nyeri', 'saveEvent' => 'save-active-rm-penilaian-ri'],
                ['key' => 'observasi', 'label' => 'Pemberian Obat & Cairan', 'saveEvent' => 'save-active-rm-observasi-ri'],
                ['key' => 'cppt', 'label' => 'CPPT', 'saveEvent' => 'save-rm-cppt-ri'],
            ]">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10] pointer-events-none"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    {{-- Data Pasien RI (kanan + kiri 1 card) menggantikan judul "Rekam Medis Rawat Inap" --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                            wire:key="emr-ri-display-pasien-header-{{ $riHdrNo }}" />
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

                {{-- ── TAB NAVIGATION ── --}}
                <div class="mt-3 border-b border-gray-200 dark:border-gray-700">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400">

                        @php
                            $klaimStatusRi =
                                \Illuminate\Support\Facades\DB::table('rsmst_klaimtypes')
                                    ->where('klaim_id', $dataDaftarRi['klaimId'] ?? '')
                                    ->value('klaim_status') ?? 'UMUM';
                            $isBPJSRi = $klaimStatusRi === 'BPJS';

                            $tabs = [
                                /* 1 */ [
                                    'key' => 'pengkajian-perawat',
                                    'label' => 'Pengkajian Perawat',
                                    'icon' =>
                                        'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                                ],
                                /* 2 */ [
                                    'key' => 'pengkajian-dokter',
                                    'label' => 'Pengkajian Dokter',
                                    'icon' =>
                                        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                                ],
                                /* 3 */ [
                                    'key' => 'pemeriksaan',
                                    'label' => 'Pemeriksaan',
                                    'icon' =>
                                        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                ],
                                /* 4 */ [
                                    'key' => 'penilaian',
                                    'label' => 'Penilaian',
                                    'icon' =>
                                        'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
                                ],
                                /* 5 */ [
                                    'key' => 'observasi',
                                    'label' => 'Observasi',
                                    'icon' =>
                                        'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                ],
                                /* 6 */ [
                                    'key' => 'asuhan',
                                    'label' => 'Asuhan Kep.',
                                    'icon' =>
                                        'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                                ],
                                /* 7 */ [
                                    'key' => 'cppt',
                                    'label' => 'CPPT',
                                    'icon' =>
                                        'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                                ],
                                /* 8 */ [
                                    'key' => 'diagnosa',
                                    'label' => 'Diagnosis (ICD)',
                                    'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                                ],
                                /* 9 */ [
                                    'key' => 'perencanaan',
                                    'label' => 'Perencanaan',
                                    'icon' =>
                                        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                                ],
                                /*10 */ [
                                    'key' => 'riwayat',
                                    'label' => 'Riwayat Kunjungan',
                                    'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                ],
                            ];

                            // Tab Surat Kontrol hanya untuk BPJS
                            if ($isBPJSRi) {
                                array_splice($tabs, 9, 0, [
                                    [
                                        'key' => 'skdp',
                                        'label' => 'Surat Kontrol',
                                        'icon' =>
                                            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                    ],
                                ]);
                            }

                            // Dokter: sembunyikan tab Penilaian & Asuhan Keperawatan (domain Perawat)
                            if (auth()->user()->hasRole('Dokter')) {
                                $tabs = array_values(array_filter($tabs, fn($t) => !in_array($t['key'], ['penilaian', 'asuhan'])));
                            }
                        @endphp

                        @foreach ($tabs as $tab)
                            <li class="mr-0.5">
                                <button type="button"
                                    class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg text-sm transition-colors"
                                    :class="activeTab === '{{ $tab['key'] }}'
                                        ?
                                        'text-brand border-brand bg-brand/5 dark:bg-brand/10 font-semibold' :
                                        'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                                    @click="requestSwitchTab('{{ $tab['key'] }}')">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="{{ $tab['icon'] }}" />
                                    </svg>
                                    {{ $tab['label'] }}
                                    {{-- Dirty indicator dot --}}
                                    <span x-show="tabDirty['{{ $tab['key'] }}']" x-cloak
                                        class="inline-block w-2 h-2 rounded-full bg-amber-500"
                                        title="Belum disimpan"></span>
                                </button>
                            </li>
                        @endforeach

                    </ul>
                </div>

            {{-- ═══════════ BODY — TAB PANELS ═══════════ --}}
            <div class="flex-1 px-4 pb-4 bg-gray-50/70 dark:bg-gray-950/20 overflow-y-auto">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ────────────────────────────────────────────
                        | TAB 1 — PENGKAJIAN PERAWAT
                        | Diisi oleh Perawat — Pengkajian Awal Rawat Inap
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'pengkajian-perawat'" x-transition.opacity.duration.200ms>
                            {{-- Dokter & role lain (termasuk Apoteker) boleh lihat (view-only); edit/simpan di-gate di dalam component
                                 lewat $isReadOnlyByRole. Lihat rm-pengkajian-awal-ri-actions.blade.php --}}
                            @hasanyrole('Perawat|Dokter|Admin|Casemix|Mr|Apoteker')
                                <livewire:pages::transaksi.ri.emr-ri.pengkajian-awal-ri.rm-pengkajian-awal-ri-actions
                                    :riHdrNo="$riHdrNo" wire:key="pengkajian-awal-ri-{{ $riHdrNo }}" />
                            @else
                                <div class="py-12 text-sm text-center text-gray-400 dark:text-gray-600">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Anda tidak memiliki akses ke Pengkajian Perawat.
                                </div>
                            @endhasanyrole
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 2 — PENGKAJIAN DOKTER
                        | Diisi oleh Dokter — Pengkajian Dokter RI
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'pengkajian-dokter'" x-transition.opacity.duration.200ms>
                            {{-- Perawat & role lain (termasuk Apoteker) boleh lihat (view-only); edit/simpan di-gate di dalam component
                                 lewat $isReadOnlyByRole. Lihat rm-pengkajian-dokter-ri-actions.blade.php --}}
                            @hasanyrole('Dokter|Perawat|Admin|Casemix|Mr|Apoteker')
                                <livewire:pages::transaksi.ri.emr-ri.pengkajian-dokter-ri.rm-pengkajian-dokter-ri-actions
                                    :riHdrNo="$riHdrNo" wire:key="pengkajian-dokter-ri-{{ $riHdrNo }}" />
                            @else
                                <div class="py-12 text-sm text-center text-gray-400 dark:text-gray-600">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Anda tidak memiliki akses ke Pengkajian Dokter.
                                </div>
                            @endhasanyrole
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 3 — PEMERIKSAAN
                        | TTV | Nutrisi | Lab | Radiologi | Upload
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'pemeriksaan'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.pemeriksaan-ri.rm-pemeriksaan-ri-actions
                                :riHdrNo="$riHdrNo" wire:key="pemeriksaan-ri-{{ $riHdrNo }}" />
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 4 — PENILAIAN
                        | Penilaian pasien rawat inap
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'penilaian'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.rm-penilaian-ri-actions :riHdrNo="$riHdrNo"
                                wire:key="penilaian-ri-{{ $riHdrNo }}" />
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 5 — OBSERVASI & TERAPI
                        | Observasi | Obat & Cairan
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'observasi'" x-transition.opacity.duration.200ms>
                            <div class="space-y-4">
                                <livewire:pages::transaksi.ri.emr-ri.observasi-ri.rm-observasi-ri-actions
                                    :riHdrNo="$riHdrNo" wire:key="observasi-ri-{{ $riHdrNo }}" />
                            </div>
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 6 — ASUHAN KEPERAWATAN
                        | Asuhan Keperawatan | Diagnosa Keperawatan | Edukasi Pasien
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'asuhan'" x-transition.opacity.duration.200ms>
                            @hasanyrole('Perawat|Admin|Apoteker')
                                <div class="grid grid-cols-1">
                                    <livewire:pages::transaksi.ri.emr-ri.asuhan-keperawatan-ri.rm-asuhan-keperawatan-ri-actions
                                        :riHdrNo="$riHdrNo" wire:key="asuhan-keperawatan-ri-{{ $riHdrNo }}" />
                                </div>
                            @endhasanyrole
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 7 — CPPT
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'cppt'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.cppt-ri.rm-cppt-ri-actions :riHdrNo="$riHdrNo"
                                wire:key="cppt-ri-{{ $riHdrNo }}" />
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 8 — DIAGNOSA (ICD)
                        | Diagnosa ICD-10
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'diagnosa'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.diagnosa-ri.rm-diagnosa-ri-actions :riHdrNo="$riHdrNo"
                                wire:key="diagnosa-ri-{{ $riHdrNo }}" />
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB 9 — PERENCANAAN
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'perencanaan'" x-transition.opacity.duration.200ms>
                            <livewire:pages::transaksi.ri.emr-ri.perencanaan-ri.rm-perencanaan-ri-actions
                                :riHdrNo="$riHdrNo" wire:key="perencanaan-ri-{{ $riHdrNo }}" />
                        </div>

                        {{-- ────────────────────────────────────────────
                        | TAB — SURAT KONTROL (SKDP) — hanya BPJS
                        ──────────────────────────────────────────── --}}
                        @if ($isBPJSRi)
                            <div x-show="activeTab === 'skdp'" x-transition.opacity.duration.200ms>
                                <livewire:pages::transaksi.ri.emr-ri.skdp-ri.rm-skdp-ri-actions :riHdrNo="$riHdrNo"
                                    wire:key="skdp-ri-{{ $riHdrNo }}" />
                            </div>
                        @endif

                        {{-- ────────────────────────────────────────────
                        | TAB — RIWAYAT KUNJUNGAN
                        ──────────────────────────────────────────── --}}
                        <div x-show="activeTab === 'riwayat'" x-transition.opacity.duration.200ms>
                            <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                :regNo="$dataDaftarRi['regNo'] ?? ''" :rjNoRefCopyTo="0" :contextRI="true"
                                wire:key="emr-ri.rekam-medis-display-{{ $dataDaftarRi['regNo'] ?? 'new' }}" />
                        </div>

                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (justify-between: Tutup | aksi | Simpan) ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-2 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: Status + Action buttons --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge variant="brand">Rawat Inap</x-badge>

                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif

                        @role(['Dokter', 'Admin'])
                            @if (!empty($dataDaftarRi['sep']['noSep']))
                                {{-- i-Care (BPJS) — emerald --}}
                                <x-outline-button type="button"
                                    wire:click="myiCare('{{ $dataDaftarRi['sep']['noSep'] }}')"
                                    wire:loading.attr="disabled" wire:target="myiCare"
                                    class="gap-1 !text-emerald-700 !bg-emerald-50 !border-emerald-200 hover:!bg-emerald-100 hover:!text-emerald-800 hover:!border-emerald-300 dark:!text-emerald-300 dark:!bg-emerald-900/20 dark:!border-emerald-800/30 dark:hover:!bg-emerald-900/30">
                                    <span wire:loading.remove wire:target="myiCare" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>i-Care
                                    </span>
                                    <span wire:loading wire:target="myiCare"
                                        class="flex items-center gap-1"><x-loading /> Memuat...</span>
                                </x-outline-button>
                            @endif
                        @endrole

                        @hasanyrole('Mr|Admin|Perawat|Tu')
                            {{-- Pindah Kamar — amber --}}
                            <x-outline-button type="button"
                                wire:click="openPindahKamar('{{ $riHdrNo }}')" wire:loading.attr="disabled"
                                wire:target="openPindahKamar"
                                class="gap-1 !text-amber-700 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-800 hover:!border-amber-300 dark:!text-amber-300 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30">
                                <span wire:loading.remove wire:target="openPindahKamar" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>Pindah Kamar
                                </span>
                                <span wire:loading wire:target="openPindahKamar"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-outline-button>
                        @endhasanyrole

                        @hasanyrole('Admin|Perawat|Casemix')
                            {{-- Dokumen — indigo --}}
                            <x-outline-button type="button"
                                wire:click="openModulDokumen('{{ $riHdrNo }}')" wire:loading.attr="disabled"
                                wire:target="openModulDokumen"
                                class="gap-1 !text-indigo-700 !bg-indigo-50 !border-indigo-200 hover:!bg-indigo-100 hover:!text-indigo-800 hover:!border-indigo-300 dark:!text-indigo-300 dark:!bg-indigo-900/20 dark:!border-indigo-800/30 dark:hover:!bg-indigo-900/30">
                                <span wire:loading.remove wire:target="openModulDokumen"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>Dokumen
                                </span>
                                <span wire:loading wire:target="openModulDokumen"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-outline-button>
                        @endhasanyrole

                        @hasanyrole('Admin|Perawat|Casemix')
                            {{-- Administrasi — teal --}}
                            <x-outline-button type="button"
                                wire:click="openAdministrasiPasien('{{ $riHdrNo }}')" wire:loading.attr="disabled"
                                wire:target="openAdministrasiPasien"
                                class="gap-1 !text-teal-700 !bg-teal-50 !border-teal-200 hover:!bg-teal-100 hover:!text-teal-800 hover:!border-teal-300 dark:!text-teal-300 dark:!bg-teal-900/20 dark:!border-teal-800/30 dark:hover:!bg-teal-900/30">
                                <span wire:loading.remove wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                    </svg>Administrasi
                                </span>
                                <span wire:loading wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-outline-button>
                        @endhasanyrole

                        @hasanyrole('Dokter|Admin|Perawat')
                            {{-- E-Resep — sky/blue --}}
                            <x-outline-button type="button"
                                wire:click="openEresep('{{ $riHdrNo }}')" wire:loading.attr="disabled"
                                wire:target="openEresep"
                                class="gap-1 !text-sky-700 !bg-sky-50 !border-sky-200 hover:!bg-sky-100 hover:!text-sky-800 hover:!border-sky-300 dark:!text-sky-300 dark:!bg-sky-900/20 dark:!border-sky-800/30 dark:hover:!bg-sky-900/30">
                                <span wire:loading.remove wire:target="openEresep" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>E-Resep
                                </span>
                                <span wire:loading wire:target="openEresep"
                                    class="flex items-center gap-1"><x-loading /> Memuat...</span>
                            </x-outline-button>

                            {{-- Resume Medis (RM 41) — rose --}}
                            <x-outline-button type="button"
                                wire:click="$dispatch('resume-medis-ri.open', { riHdrNo: {{ $riHdrNo }} })"
                                class="gap-1 !text-rose-700 !bg-rose-50 !border-rose-200 hover:!bg-rose-100 hover:!text-rose-800 hover:!border-rose-300 dark:!text-rose-300 dark:!bg-rose-900/20 dark:!border-rose-800/30 dark:hover:!bg-rose-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Resume Medis
                            </x-outline-button>
                        @endhasanyrole
                    </div>

                    {{-- KANAN: Tutup + Simpan dinamis per active tab --}}
                    <div class="flex items-center gap-2">
                        <x-secondary-button x-on:click="tryClose()">Tutup</x-secondary-button>

                        @if (!$isFormLocked)
                            <template x-if="saveMap[activeTab]">
                                <x-primary-button type="button" class="min-w-[120px]"
                                    x-on:click="saveActive()">
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    <span>Simpan <span x-text="saveMap[activeTab].label"></span></span>
                                </x-primary-button>
                            </template>
                        @endif
                    </div>
                </div>
            </div>

        </x-tabbed-dirty-modal-content>
    </x-modal>

    {{-- Modal E-Resep RI --}}
    <livewire:pages::transaksi.ri.eresep-ri.eresep-ri wire:key="eresep-ri-modal-{{ $riHdrNo ?? 'new' }}" />

    {{-- Modal Resume Medis RM 41 — editor TinyMCE + generate PDF (listen: resume-medis-ri.open) --}}
    <livewire:pages::components.rekam-medis.r-i.resume-medis-ri.resume-medis-ri-actions
        wire:key="resume-medis-ri-actions" />

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal-ri" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">
            <div
                class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">i-Care BPJS</h3>
                <x-icon-button color="gray" type="button" wire:click="closeModalicare">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>
            <div class="flex-1 min-h-0">
                @if ($icareUrlResponse)
                    <iframe src="{{ $icareUrlResponse }}" class="w-full h-full border-0"
                        title="i-Care BPJS"></iframe>
                @else
                    <p class="py-10 text-sm text-center text-gray-400">Memuat i-Care...</p>
                @endif
            </div>
            <div class="flex justify-end px-6 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
