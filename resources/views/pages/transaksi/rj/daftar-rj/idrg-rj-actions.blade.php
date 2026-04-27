<?php
// Komponen Modal Bridging iDRG / INACBG (E-Klaim Kemenkes) RJ.
// Dipisah dari daftar-rj-actions supaya orchestrator daftar-rj-actions tetap ramping.
// Trigger dari parent: dispatch event 'daftar-rj.idrg.open' dengan rjNo.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $dataPasien = [];

    public function mount(?string $initialRjNo = null): void
    {
        if (!empty($initialRjNo)) {
            $this->rjNo = $initialRjNo;
            $this->loadData();
        }
    }

    #[On('daftar-rj.idrg.open')]
    public function handleOpenIdrg(string $rjNo): void
    {
        $this->rjNo = $rjNo;

        if (!$this->loadData()) {
            return;
        }

        $isBpjs = ($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM';
        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Kirim iDRG hanya untuk pasien BPJS.');
            return;
        }

        $this->dispatch('open-modal', name: 'rj-idrg');
    }

    /**
     * Refresh section gating saat ada transisi state kunci (newClaim, finalIdrg,
     * reeditIdrg, finalInacbg, reeditInacbg). Listener TUNGGAL di parent —
     * tidak ikut event broadcast `idrg-state-updated` yang dipakai 16 SFC,
     * jadi tidak ada race condition Livewire batch.
     */
    #[On('idrg-section-changed')]
    public function onIdrgSectionChanged(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->rjNo)) {
            return false;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return false;
        }
        $this->dataDaftarPoliRJ = $data;
        $this->dataPasien = $this->findDataMasterPasien($data['regNo'] ?? '');
        return true;
    }
};
?>

<div>
    <x-modal name="rj-idrg" size="full" height="full" focusable>
        @php
            $idrgData = $dataDaftarPoliRJ['idrg'] ?? [];
            $hasClaim = !empty($idrgData['nomorSep']);
            $idrgUngroup = !empty($idrgData['idrgUngroupable']);
            $idrgFinal = !empty($idrgData['idrgFinal']);
            $inacbgUngroup = !empty($idrgData['inacbgUngroupable']);
            $inacbgFinal = !empty($idrgData['inacbgFinal']);
            $klaimFinal = !empty($idrgData['klaimFinal']);
            $nomorSepKlaim = $dataDaftarPoliRJ['sep']['noSep'] ?? '-';

            $guide = [
                ['key' => 'A', 'title' => 'A. Setup Klaim', 'items' => [
                    ['n' => 1, 'head' => 'Generate Nomor Klaim', 'body' => 'Hanya untuk pasien khusus (COVID-19, KIPI, Bayi Baru Lahir, Co-Insidense). Pasien BPJS biasa pakai SEP yang sudah ada.'],
                    ['n' => 2, 'head' => 'Buat Klaim Baru', 'body' => 'Registrasi SEP ke E-Klaim.'],
                    ['n' => 3, 'head' => 'Simpan Data Klaim', 'body' => 'tarif_rs + tanggal masuk/pulang auto dari rincian kasir RJ.'],
                ]],
                ['key' => 'B', 'title' => 'B. Coding iDRG', 'items' => [
                    ['n' => 4, 'head' => 'Set Diagnosa iDRG', 'body' => 'Auto dari EMR (Primary di depan). Coder casemix bisa edit tanpa ubah EMR.'],
                    ['n' => 5, 'head' => 'Set Prosedur iDRG', 'body' => 'Auto dari EMR — support multiplicity +N & setting # antar operasi.'],
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

        <div class="flex flex-col min-h-0">
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
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Kirim iDRG / INACBG
                                (E-Klaim Kemenkes)</h2>
                            @php $sepKosong = empty($nomorSepKlaim) || $nomorSepKlaim === '-'; @endphp
                            <p
                                class="mt-0.5 text-sm {{ $sepKosong ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                <span class="font-semibold">{{ $dataDaftarPoliRJ['regName'] ?? '-' }}</span>
                                &mdash; RM: {{ $dataDaftarPoliRJ['regNo'] ?? '-' }}
                                &mdash; RJ: {{ $rjNo ?? '-' }}
                                &mdash; SEP: <span
                                    class="font-mono font-semibold {{ $sepKosong ? '' : 'text-brand dark:text-brand-lime' }}">{{ $nomorSepKlaim }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Poli: <span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ $dataDaftarPoliRJ['poliDesc'] ?? '-' }}</span>
                                &mdash; Dokter: <span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ $dataDaftarPoliRJ['drDesc'] ?? '-' }}</span>
                                &mdash; Tgl RJ: <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ !empty($dataDaftarPoliRJ['rjDate']) ? substr($dataDaftarPoliRJ['rjDate'], 0, 16) : '-' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'rj-idrg' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-6 py-6 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-7xl mx-auto space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                        {{-- LEFT — Cara Pakai --}}
                        <div class="lg:sticky lg:top-0 lg:self-start">
                            <div class="bg-white border border-brand/30 shadow-sm rounded-xl dark:bg-gray-900 dark:border-brand-lime/30">
                                <div class="px-5 py-3 border-b border-brand/20 dark:border-brand-lime/20">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-brand/10 dark:bg-brand-lime/15">
                                            <svg class="w-4 h-4 text-brand dark:text-brand-lime" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800 dark:text-gray-100">Cara Pakai —
                                                Alur iDRG / INACBG</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">iDRG dikerjakan paling
                                                awal sebelum INACBG. Diagnosa &amp; prosedur ditarik otomatis dari EMR.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div x-data="{ activeSec: {{ $hasClaim ? 'null' : "'A'" }} }" class="px-5 py-4">
                                    @foreach ($guide as $g)
                                        <div x-data="{ sec: '{{ $g['key'] }}' }">
                                            <button type="button"
                                                x-on:click="activeSec = (activeSec === sec) ? null : sec"
                                                class="flex items-center w-full gap-3 py-2 mt-1 text-left group/sec">
                                                <h4 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors text-gray-400 dark:text-gray-500 group-hover/sec:text-gray-600 dark:group-hover/sec:text-gray-300"
                                                    x-bind:class="activeSec === sec ? 'text-brand dark:text-brand-lime' : ''">
                                                    {{ $g['title'] }}</h4>
                                                <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                                    x-bind:class="activeSec === sec ? 'rotate-0' : '-rotate-90'"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <div x-show="activeSec === sec"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-y-2"
                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 translate-y-0"
                                                x-transition:leave-end="opacity-0 -translate-y-2"
                                                class="pb-3 space-y-2" style="display: none;">
                                                @foreach ($g['items'] as $item)
                                                    <div class="flex items-start gap-3 p-3 border border-gray-100 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                                                        <div class="flex items-center justify-center w-7 h-7 rounded-full bg-brand/10 text-brand text-xs font-bold shrink-0 dark:bg-brand-lime/15 dark:text-brand-lime">
                                                            {{ $item['n'] }}</div>
                                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                                                {{ $item['head'] }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $item['body'] }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- RIGHT — SFC per step (self-contained, EMR-style) --}}
                        <div class="space-y-6">
                            {{-- A. Setup Klaim --}}
                            <div class="space-y-3">
                                <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                    A. Setup Klaim
                                </h3>
                                <livewire:pages::transaksi.rj.idrg.kirim-generate-number :rjNo="$rjNo"
                                    wire:key="idrg-generate-number-rj-{{ $rjNo ?? 'none' }}" />
                                <livewire:pages::transaksi.rj.idrg.kirim-new-claim :rjNo="$rjNo"
                                    wire:key="idrg-new-claim-rj-{{ $rjNo ?? 'none' }}" />
                                @if ($hasClaim)
                                    <livewire:pages::transaksi.rj.idrg.kirim-set-data :rjNo="$rjNo"
                                        wire:key="idrg-set-data-rj-{{ $rjNo ?? 'none' }}" />
                                @endif
                            </div>

                            {{-- B. Coding iDRG --}}
                            @if ($hasClaim)
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        B. Coding iDRG
                                    </h3>
                                    <livewire:pages::transaksi.rj.idrg.kirim-diagnosa-idrg :rjNo="$rjNo"
                                        wire:key="idrg-diagnosa-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-prosedur-idrg :rjNo="$rjNo"
                                        wire:key="idrg-prosedur-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-group-idrg :rjNo="$rjNo"
                                        wire:key="idrg-group-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-final-idrg :rjNo="$rjNo"
                                        wire:key="idrg-final-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-import-inacbg :rjNo="$rjNo"
                                        wire:key="idrg-import-inacbg-rj-{{ $rjNo ?? 'none' }}" />
                                </div>
                            @endif

                            {{-- C. Coding INACBG (after iDRG final) --}}
                            @if ($idrgFinal)
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        C. Coding INACBG
                                    </h3>
                                    <livewire:pages::transaksi.rj.idrg.kirim-diagnosa-inacbg :rjNo="$rjNo"
                                        wire:key="idrg-diagnosa-inacbg-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-prosedur-inacbg :rjNo="$rjNo"
                                        wire:key="idrg-prosedur-inacbg-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-group-inacbg-1 :rjNo="$rjNo"
                                        wire:key="idrg-group-inacbg-1-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-group-inacbg-2 :rjNo="$rjNo"
                                        wire:key="idrg-group-inacbg-2-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-final-inacbg :rjNo="$rjNo"
                                        wire:key="idrg-final-inacbg-rj-{{ $rjNo ?? 'none' }}" />
                                </div>
                            @endif

                            {{-- D. Finalisasi Klaim (after INACBG final) --}}
                            @if ($inacbgFinal)
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                        D. Finalisasi Klaim
                                    </h3>
                                    <livewire:pages::transaksi.rj.idrg.kirim-final-klaim :rjNo="$rjNo"
                                        wire:key="idrg-final-klaim-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-send-klaim :rjNo="$rjNo"
                                        wire:key="idrg-send-klaim-rj-{{ $rjNo ?? 'none' }}" />
                                    <livewire:pages::transaksi.rj.idrg.kirim-print-klaim :rjNo="$rjNo"
                                        wire:key="idrg-print-klaim-rj-{{ $rjNo ?? 'none' }}" />
                                </div>
                            @endif
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </x-modal>
</div>
