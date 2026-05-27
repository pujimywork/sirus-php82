<?php
// Komponen Modal Bridging iDRG / INACBG (E-Klaim Kemenkes) RI.
// Dipisah dari daftar-ri-actions supaya orchestrator daftar-ri-actions tetap ramping.
// Trigger dari parent: dispatch event 'daftar-ri.idrg.open' dengan rjNo.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait;

    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];
    public array $dataPasien = [];
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(?string $initialRiHdrNo = null): void
    {
        $this->registerAreas(['modal']);
        if (!empty($initialRiHdrNo)) {
            $this->riHdrNo = $initialRiHdrNo;
            $this->loadData();
        }
    }

    #[On('daftar-ri.idrg.open')]
    public function handleOpenIdrg(string $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;

        if (!$this->loadData()) {
            return;
        }

        $isBpjs = ($this->dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarRi['klaimId'] ?? '') === 'JM';
        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Kirim iDRG hanya untuk pasien BPJS.');
            return;
        }

        $this->dispatch('open-modal', name: 'ri-idrg');
    }

    /**
     * Single listener untuk SEMUA perubahan state iDRG (pola mirror administrasi-rj):
     * SFC dispatch `idrg-section-changed-ri` setiap kali saveResult / state berubah.
     * Parent re-load data + incrementVersion('modal') → wire:key versioned berubah
     * → seluruh subtree SFC REMOUNT → fresh state via mount(). Tidak butuh
     * cross-sibling broadcast (`idrg-state-updated-ri`), jadi tidak ada race
     * "A request already contains one of the messages" di Livewire bundle interceptor.
     */
    #[On('idrg-section-changed-ri')]
    public function onIdrgSectionChanged(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->loadData();
        $this->incrementVersion('modal');
    }

    private function loadData(): bool
    {
        if (empty($this->riHdrNo)) {
            return false;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return false;
        }
        $this->dataDaftarRi = $data;
        $this->dataPasien = $this->findDataMasterPasien($data['regNo'] ?? '');
        return true;
    }
};
?>

<div>
    <x-modal name="ri-idrg" size="full" height="full" focusable>
        @php
            $idrgData = $dataDaftarRi['idrg'] ?? [];
            $hasClaim = !empty($idrgData['nomorSep']);
            $idrgUngroup = !empty($idrgData['idrgUngroupable']);
            $idrgFinal = !empty($idrgData['idrgFinal']);
            $inacbgUngroup = !empty($idrgData['inacbgUngroupable']);
            $inacbgFinal = !empty($idrgData['inacbgFinal']);
            $klaimFinal = !empty($idrgData['klaimFinal']);
            $nomorSepKlaim = $dataDaftarRi['sep']['noSep'] ?? '-';

            $guide = [
                ['key' => 'A', 'title' => 'A. Setup Klaim', 'items' => [
                    ['n' => 1, 'head' => 'Generate Nomor Klaim', 'body' => 'Hanya untuk pasien khusus (COVID-19, KIPI, Bayi Baru Lahir, Co-Insidense). Pasien BPJS biasa pakai SEP yang sudah ada.'],
                    ['n' => 2, 'head' => 'Buat Klaim Baru', 'body' => 'Registrasi SEP ke E-Klaim.'],
                    ['n' => 3, 'head' => 'Simpan Data Klaim', 'body' => 'tarif_rs + tanggal masuk/pulang auto dari rincian kasir RJ.'],
                ]],
                ['key' => 'B', 'title' => 'B. Coding iDRG', 'items' => [
                    ['n' => 4, 'head' => 'Set Diagnosa iDRG', 'body' => 'Auto dari EMR (Primary di depan). Coder casemix bisa edit tanpa ubah EMR.'],
                    ['n' => 5, 'head' => 'Set Prosedur iDRG', 'body' => 'Auto dari EMR — support multiplicity (+N) untuk kode yang diulang.'],
                    ['n' => 6, 'head' => 'Grouping iDRG', 'body' => 'Jalankan grouper untuk dapat kode DRG.'],
                    ['n' => 7, 'head' => 'Final iDRG', 'body' => 'Disabled jika ungroupable (MDC 36). Edit Ulang iDRG kalau perlu revisi.'],
                    ['n' => 8, 'head' => 'Import → INACBG', 'body' => 'Import seluruh kode sekaligus.'],
                ]],
                ['key' => 'C', 'title' => 'C. Coding INACBG (setelah iDRG final)', 'items' => [
                    ['n' => 9, 'head' => 'Set Diagnosa / Prosedur INACBG', 'body' => 'Kalau ada kode "IM tidak berlaku", ganti dengan kode non-IM.'],
                    ['n' => 11, 'head' => 'Grouping INACBG Stage 1', 'body' => 'Hasil kode CBG.'],
                    ['n' => 12, 'head' => 'Grouping Stage 2', 'body' => 'Hanya jika muncul special_cmg_option (implant/prosthesis).'],
                    ['n' => 13, 'head' => 'Final INACBG', 'body' => 'Disabled jika kode diawali X (ungroupable).'],
                ]],
                ['key' => 'D', 'title' => 'D. Finalisasi Klaim (setelah INACBG final)', 'items' => [
                    ['n' => 14, 'head' => 'Final Klaim', 'body' => 'coder_nik otomatis dari emp_id user login (Karyawan).'],
                    ['n' => 15, 'head' => 'Kirim Klaim', 'body' => 'send_claim_individual ke data center.'],
                    ['n' => 16, 'head' => 'Cetak Klaim', 'body' => 'PDF tampil di SFC step 16, bisa didownload.'],
                ]],
            ];
        @endphp

        <div class="flex flex-col min-h-0" wire:key="{{ $this->renderKey('modal', [$riHdrNo ?? 'none']) }}">
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand dark:text-brand-lime" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Kirim iDRG / INACBG (E-Klaim Kemenkes)
                        </h2>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ri-idrg' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>

                {{-- Identitas pasien — pakai komponen standar display-pasien-ri --}}
                <div class="mt-4">
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="{{ $this->renderKey('modal', ['idrg-display-pasien-ri', $riHdrNo ?? 'none']) }}" />
                </div>
            </div>

            <div class="flex-1 px-6 py-6 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="w-full space-y-6">
                    {{-- Cara Pakai — Alur iDRG / INACBG (collapsible, native <details>) --}}
                    <details class="bg-white border border-brand/30 shadow-sm rounded-xl dark:bg-gray-900 dark:border-brand-lime/30 group">
                        <summary class="flex items-center gap-3 px-5 py-3 cursor-pointer select-none">
                            <svg class="w-4 h-4 transition-transform text-brand dark:text-brand-lime shrink-0 group-open:rotate-90"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <div class="flex items-baseline flex-wrap gap-x-2">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Cara Pakai — Alur iDRG / INACBG</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">— iDRG dikerjakan paling awal sebelum INACBG. Diagnosa &amp; prosedur ditarik otomatis dari EMR.</span>
                            </div>
                        </summary>
                        <div class="px-5 pt-1 pb-4 space-y-4 border-t border-gray-100 dark:border-gray-800">
                            @foreach ($guide as $g)
                                <div>
                                    <h4 class="pt-3 mb-2 text-sm font-bold tracking-wider uppercase text-brand dark:text-brand-lime">
                                        {{ $g['title'] }}
                                    </h4>
                                    <div class="space-y-2">
                                        @foreach ($g['items'] as $item)
                                            <div class="flex items-start gap-3 p-3 border border-gray-100 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                                                <div class="flex items-center justify-center w-7 h-7 rounded-full bg-brand/10 text-brand text-sm font-bold shrink-0 dark:bg-brand-lime/15 dark:text-brand-lime">
                                                    {{ $item['n'] }}
                                                </div>
                                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                                    <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $item['head'] }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $item['body'] }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>

                    {{-- SFC per step (self-contained, EMR-style) --}}
                    <div class="space-y-6">
                            {{-- A. Setup Klaim --}}
                            <div class="space-y-3">
                                <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                    A. Setup Klaim
                                </h3>
                                <livewire:pages::transaksi.ri.idrg.kirim-generate-number :riHdrNo="$riHdrNo"
                                    wire:key="{{ $this->renderKey('modal', ['idrg-generate-number-ri', $riHdrNo ?? 'none']) }}" />
                                <livewire:pages::transaksi.ri.idrg.kirim-new-claim :riHdrNo="$riHdrNo"
                                    wire:key="{{ $this->renderKey('modal', ['idrg-new-claim-ri', $riHdrNo ?? 'none']) }}" />
                                @if ($hasClaim)
                                    <livewire:pages::transaksi.ri.idrg.kirim-set-data :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-set-data-ri', $riHdrNo ?? 'none']) }}" />
                                @endif
                            </div>

                            {{-- B. Coding iDRG --}}
                            @if ($hasClaim)
                                @php
                                    $idrgDiagSaved = !empty($idrgData['idrgDiagnosaString']);
                                    $idrgProcSaved = !empty($idrgData['idrgProsedurString']);
                                    $idrgBothSaved = $idrgDiagSaved && $idrgProcSaved;
                                @endphp
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        B. Coding iDRG
                                    </h3>
                                    <livewire:pages::transaksi.ri.idrg.kirim-diagnosa-idrg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-diagnosa-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-prosedur-idrg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-prosedur-ri', $riHdrNo ?? 'none']) }}" />

                                    {{-- Shortcut: Set Diagnosa + Prosedur iDRG sekaligus (dispatch ke 2 SFC) --}}
                                    <div class="flex justify-end px-4 py-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <x-primary-button type="button" :disabled="$idrgFinal"
                                            x-on:click="
                                                Livewire.dispatch('idrg-diagnosa-ri.set', { riHdrNo: '{{ $riHdrNo }}' });
                                                Livewire.dispatch('idrg-prosedur-ri.set', { riHdrNo: '{{ $riHdrNo }}' });
                                            "
                                            class="!bg-brand hover:!bg-brand/90 min-w-[260px] {{ $idrgBothSaved ? '!bg-emerald-600' : '' }}">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                            </svg>
                                            {{ $idrgBothSaved ? 'Set Ulang Diagnosa & Prosedur iDRG' : 'Set Diagnosa & Prosedur iDRG' }}
                                        </x-primary-button>
                                    </div>

                                    <livewire:pages::transaksi.ri.idrg.kirim-group-idrg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-group-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-group-idrg-2 :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-group-2-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-final-idrg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-final-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-import-inacbg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-import-inacbg-ri', $riHdrNo ?? 'none']) }}" />
                                </div>
                            @endif

                            {{-- C. Coding INACBG (after iDRG final) --}}
                            @if ($idrgFinal)
                                @php
                                    $inacbgDiagSaved = !empty($idrgData['inacbgDiagnosaString']);
                                    $inacbgProcSaved = !empty($idrgData['inacbgProsedurString']);
                                    $inacbgBothSaved = $inacbgDiagSaved && $inacbgProcSaved;
                                @endphp
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        C. Coding INACBG
                                    </h3>
                                    <livewire:pages::transaksi.ri.idrg.kirim-diagnosa-inacbg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-diagnosa-inacbg-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-prosedur-inacbg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-prosedur-inacbg-ri', $riHdrNo ?? 'none']) }}" />

                                    {{-- Shortcut: Set Diagnosa + Prosedur INACBG sekaligus --}}
                                    <div class="flex justify-end px-4 py-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <x-primary-button type="button" :disabled="$inacbgFinal"
                                            x-on:click="
                                                Livewire.dispatch('idrg-diagnosa-inacbg-ri.set', { riHdrNo: '{{ $riHdrNo }}' });
                                                Livewire.dispatch('idrg-prosedur-inacbg-ri.set', { riHdrNo: '{{ $riHdrNo }}' });
                                            "
                                            class="!bg-brand hover:!bg-brand/90 min-w-[260px] {{ $inacbgBothSaved ? '!bg-emerald-600' : '' }}">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                            </svg>
                                            {{ $inacbgBothSaved ? 'Set Ulang Diagnosa & Prosedur INACBG' : 'Set Diagnosa & Prosedur INACBG' }}
                                        </x-primary-button>
                                    </div>

                                    <livewire:pages::transaksi.ri.idrg.kirim-group-inacbg-1 :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-group-inacbg-1-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-group-inacbg-2 :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-group-inacbg-2-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-final-inacbg :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-final-inacbg-ri', $riHdrNo ?? 'none']) }}" />
                                </div>
                            @endif

                            {{-- D. Finalisasi Klaim (after INACBG final) --}}
                            @if ($inacbgFinal)
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        D. Finalisasi Klaim
                                    </h3>
                                    <livewire:pages::transaksi.ri.idrg.kirim-final-klaim :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-final-klaim-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-send-klaim :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-send-klaim-ri', $riHdrNo ?? 'none']) }}" />
                                    <livewire:pages::transaksi.ri.idrg.kirim-print-klaim :riHdrNo="$riHdrNo"
                                        wire:key="{{ $this->renderKey('modal', ['idrg-print-klaim-ri', $riHdrNo ?? 'none']) }}" />
                                </div>
                            @endif
                        </div>

                </div>
            </div>
        </div>
    </x-modal>
</div>
