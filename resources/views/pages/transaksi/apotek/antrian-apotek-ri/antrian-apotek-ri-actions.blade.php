<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Stock\StockBalanceTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, StockBalanceTrait;

    /** Sumber obat resep RI = Apotek. */
    private const SL_CODE_APOTEK = '02';

    public ?int $slsNo = null;
    public ?int $riHdrNo = null;
    public ?int $eresepIndex = null;   // index ke eresepHdr (dokter — untuk display obat)
    public ?int $apotekIndex = null;   // index ke apotekHdr (apoteker — untuk telaah)
    public bool $isFormLocked = false;
    public array $dataDaftarRI = [];

    /**
     * Map productId → saldo apotek, dihitung sekali saat modal dibuka.
     * Tujuan: apoteker melihat ketersediaan per obat sebelum ttd telaah —
     * apoteker tidak bisa mengubah qty (qty dari dokter), jadi UI ini review-only.
     */
    public array $saldoPerObat = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-telaah-apotek-ri'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN TELAAH
     =============================== */
    #[On('antrian-apotek-ri.telaah.open')]
    public function open(int $slsNo): void
    {
        $this->resetForm();
        $this->slsNo = $slsNo;

        $sls = DB::table('imtxn_slshdrs')->where('sls_no', $slsNo)->first(['rihdr_no']);
        if (!$sls || !$sls->rihdr_no) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            return;
        }

        $this->riHdrNo = (int) $sls->rihdr_no;
        $this->dataDaftarRI = $this->findDataRI($this->riHdrNo);

        // Index ke eresepHdr (dokter) — untuk display obat di modal
        $this->eresepIndex = $this->findIndexInArr($this->dataDaftarRI['eresepHdr'] ?? [], $slsNo);
        if ($this->eresepIndex === null) {
            $this->dispatch('toast', type: 'error', message: 'Header resep dokter tidak ditemukan untuk SLS ini.');
            return;
        }

        // Pastikan apotekHdr[apotekIndex] ada untuk slsNo (auto-create)
        $this->dataDaftarRI['apotekHdr'] ??= [];
        $existing = $this->findIndexInArr($this->dataDaftarRI['apotekHdr'], $slsNo);
        if ($existing === null) {
            $this->dataDaftarRI['apotekHdr'][] = ['slsNo' => $slsNo];
            $existing = count($this->dataDaftarRI['apotekHdr']) - 1;
        }
        $this->apotekIndex = $existing;

        // Init defaults telaah resep & obat (di apotekHdr)
        $apotek = &$this->dataDaftarRI['apotekHdr'][$this->apotekIndex];

        if (!isset($apotek['telaahResep'])) {
            $apotek['telaahResep'] = $this->defaultTelaahResep();
        } else {
            foreach ($this->defaultTelaahResep() as $key => $default) {
                $apotek['telaahResep'][$key] ??= $default;
            }
        }

        if (!isset($apotek['telaahObat'])) {
            $apotek['telaahObat'] = $this->defaultTelaahObat();
        } else {
            foreach ($this->defaultTelaahObat() as $key => $default) {
                $apotek['telaahObat'][$key] ??= $default;
            }
        }

        // Hitung saldo apotek untuk semua obat unik di resep (non-racikan + racikan).
        $this->saldoPerObat = $this->hitungSaldoPerObat($this->dataDaftarRI['eresepHdr'][$this->eresepIndex] ?? []);

        $this->incrementVersion('modal-telaah-apotek-ri');
        $this->dispatch('open-modal', name: 'telaah-apotek-ri');
    }

    /**
     * Kumpulkan productId unik dari eresep + eresepRacikan, lalu query saldo apotek per productId.
     * Dipanggil sekali saat modal dibuka — hasil di-cache di {@see $saldoPerObat}.
     *
     * @param array $eresepHdr  Header eresep (single) — punya 'eresep' (non-racikan) dan 'eresepRacikan'.
     * @return array<string, float>  Map productId → saldo akhir.
     */
    private function hitungSaldoPerObat(array $eresepHdr): array
    {
        $ids = [];
        foreach ($eresepHdr['eresep'] ?? [] as $obat) {
            if (!empty($obat['productId'])) {
                $ids[(string) $obat['productId']] = true;
            }
        }
        foreach ($eresepHdr['eresepRacikan'] ?? [] as $rac) {
            if (!empty($rac['productId'])) {
                $ids[(string) $rac['productId']] = true;
            }
        }

        $saldo = [];
        foreach (array_keys($ids) as $pid) {
            $saldo[$pid] = $this->saldoStok(self::SL_CODE_APOTEK, $pid);
        }
        return $saldo;
    }

    /* ===============================
     | SAVE TELAAH RESEP
     =============================== */
    public function saveTelaahResep(): void
    {
        if ($this->isFormLocked || $this->apotekIndex === null) {
            $this->dispatch('toast', type: 'error', message: 'Form tidak aktif.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                $idx = $this->ensureApotekHdrIndex($data, $this->slsNo);

                $data['apotekHdr'][$idx]['telaahResep'] =
                    $this->dataDaftarRI['apotekHdr'][$this->apotekIndex]['telaahResep'] ?? [];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->apotekIndex = $idx;
            });

            $this->incrementVersion('modal-telaah-apotek-ri');
            $this->dispatch('toast', type: 'success', message: 'Telaah Resep berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD TELAAH RESEP
     =============================== */
    public function ttdTelaahResep(): void
    {
        if (!auth()->user()->hasRole('Apoteker')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Apoteker yang dapat melakukan TTD-E Telaah Resep.');
            return;
        }

        $apotek = $this->dataDaftarRI['apotekHdr'][$this->apotekIndex] ?? null;
        if ($apotek && isset($apotek['telaahResep']['penanggungJawab'])) {
            $this->dispatch('toast', type: 'info', message: 'TTD-E Telaah Resep sudah dilakukan oleh ' . $apotek['telaahResep']['penanggungJawab']['userLog']);
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                $idx = $this->ensureApotekHdrIndex($data, $this->slsNo);

                $data['apotekHdr'][$idx]['telaahResep'] =
                    $this->dataDaftarRI['apotekHdr'][$this->apotekIndex]['telaahResep'] ?? [];
                $data['apotekHdr'][$idx]['telaahResep']['penanggungJawab'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogCode' => auth()->user()->myuser_code,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->apotekIndex = $idx;
            });

            $this->incrementVersion('modal-telaah-apotek-ri');
            $this->dispatch('toast', type: 'success', message: 'TTD-E Telaah Resep berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE TELAAH OBAT
     =============================== */
    public function saveTelaahObat(): void
    {
        if ($this->isFormLocked || $this->apotekIndex === null) {
            $this->dispatch('toast', type: 'error', message: 'Form tidak aktif.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                $idx = $this->ensureApotekHdrIndex($data, $this->slsNo);

                $data['apotekHdr'][$idx]['telaahObat'] =
                    $this->dataDaftarRI['apotekHdr'][$this->apotekIndex]['telaahObat'] ?? [];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->apotekIndex = $idx;
            });

            $this->incrementVersion('modal-telaah-apotek-ri');
            $this->dispatch('toast', type: 'success', message: 'Telaah Obat berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD TELAAH OBAT
     =============================== */
    public function ttdTelaahObat(): void
    {
        if (!auth()->user()->hasRole('Apoteker')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Apoteker yang dapat melakukan TTD-E Telaah Obat.');
            return;
        }

        $apotek = $this->dataDaftarRI['apotekHdr'][$this->apotekIndex] ?? null;
        if ($apotek && isset($apotek['telaahObat']['penanggungJawab'])) {
            $this->dispatch('toast', type: 'info', message: 'TTD-E Telaah Obat sudah dilakukan oleh ' . $apotek['telaahObat']['penanggungJawab']['userLog']);
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                $idx = $this->ensureApotekHdrIndex($data, $this->slsNo);

                $data['apotekHdr'][$idx]['telaahObat'] =
                    $this->dataDaftarRI['apotekHdr'][$this->apotekIndex]['telaahObat'] ?? [];
                $data['apotekHdr'][$idx]['telaahObat']['penanggungJawab'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogCode' => auth()->user()->myuser_code,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;
                $this->apotekIndex = $idx;
            });

            $this->incrementVersion('modal-telaah-apotek-ri');
            $this->dispatch('toast', type: 'success', message: 'TTD-E Telaah Obat berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeTelaah(): void
    {
        $this->dispatch('close-modal', name: 'telaah-apotek-ri');
        $this->resetForm();
    }

    /* ===============================
     | DEFAULTS
     =============================== */
    private function defaultTelaahResep(): array
    {
        return [
            'kejelasanTulisanResep' => ['kejelasanTulisanResep' => 'Ya', 'desc' => ''],
            'tepatObat' => ['tepatObat' => 'Ya', 'desc' => ''],
            'tepatDosis' => ['tepatDosis' => 'Ya', 'desc' => ''],
            'tepatRute' => ['tepatRute' => 'Ya', 'desc' => ''],
            'tepatWaktu' => ['tepatWaktu' => 'Ya', 'desc' => ''],
            'duplikasi' => ['duplikasi' => 'Tidak', 'desc' => ''],
            'alergi' => ['alergi' => 'Tidak', 'desc' => ''],
            'interaksiObat' => ['interaksiObat' => 'Tidak', 'desc' => ''],
            'bbPasienAnak' => ['bbPasienAnak' => 'Ya', 'desc' => ''],
            'kontraIndikasiLain' => ['kontraIndikasiLain' => 'Tidak', 'desc' => ''],
        ];
    }

    private function defaultTelaahObat(): array
    {
        return [
            'obatdgnResep' => ['obatdgnResep' => 'Ya', 'desc' => ''],
            'jmlDosisdgnResep' => ['jmlDosisdgnResep' => 'Ya', 'desc' => ''],
            'rutedgnResep' => ['rutedgnResep' => 'Ya', 'desc' => ''],
            'waktuFrekPemberiandgnResep' => ['waktuFrekPemberiandgnResep' => 'Ya', 'desc' => ''],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function findIndexInArr(array $arr, int $slsNo): ?int
    {
        foreach ($arr as $idx => $hdr) {
            if ((int) ($hdr['slsNo'] ?? 0) === $slsNo) {
                return $idx;
            }
        }
        return null;
    }

    private function ensureApotekHdrIndex(array &$data, int $slsNo): int
    {
        $data['apotekHdr'] ??= [];
        foreach ($data['apotekHdr'] as $idx => $h) {
            if ((int) ($h['slsNo'] ?? 0) === $slsNo) {
                return $idx;
            }
        }
        $data['apotekHdr'][] = ['slsNo' => $slsNo];
        return count($data['apotekHdr']) - 1;
    }

    private function afterSave(): void
    {
        $this->dispatch('refresh-after-antrian-apotek-ri.saved');
    }

    private function resetForm(): void
    {
        $this->resetVersion();
        $this->slsNo = null;
        $this->riHdrNo = null;
        $this->eresepIndex = null;
        $this->apotekIndex = null;
        $this->isFormLocked = false;
        $this->dataDaftarRI = [];
        $this->saldoPerObat = [];
    }
};
?>

<div>
    <x-modal name="telaah-apotek-ri" size="full" height="full" focusable>
        <div wire:key="{{ $this->renderKey('modal-telaah-apotek-ri', [$slsNo ?? 'new']) }}">

            @php
                $eresep = $eresepIndex !== null ? ($dataDaftarRI['eresepHdr'][$eresepIndex] ?? null) : null;
                $apotek = $apotekIndex !== null ? ($dataDaftarRI['apotekHdr'][$apotekIndex] ?? null) : null;
            @endphp

            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Telaah Resep &amp; Obat — Rawat Inap
                    </h3>
                    @if ($eresep)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $dataDaftarRI['regName'] ?? '' }}
                            &bull; No SLS: {{ $slsNo }}
                            @if (!empty($eresep['resepNo']))
                                &bull; Resep #{{ $eresep['resepNo'] }}
                            @endif
                        </p>
                    @endif
                </div>
                <button wire:click="closeTelaah"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if (!$eresep || !$apotek)
                <div class="px-6 py-12 text-center text-gray-400">Memuat data...</div>
            @else
                {{-- GRID --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-gray-200 dark:divide-gray-700">

                    {{-- ══════════════ KIRI: TELAAH RESEP (2/3) ══════════════ --}}
                    <div class="flex flex-col lg:col-span-2">

                        <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                            {{-- Daftar obat (dari eresepHdr — dokter) --}}
                            @if (!empty($eresep['eresep']) || !empty($eresep['eresepRacikan']))
                                <div class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">
                                        Daftar Obat dalam Resep (Dokter)
                                    </p>
                                    <div class="space-y-1">
                                        @foreach ($eresep['eresep'] ?? [] as $obat)
                                            @php
                                                $pid = (string) ($obat['productId'] ?? '');
                                                $qtyObat = (float) ($obat['qty'] ?? 0);
                                                $saldoObat = $pid !== '' ? ($saldoPerObat[$pid] ?? null) : null;
                                                $stokKurang = $saldoObat !== null && $qtyObat > $saldoObat;
                                                $saldoDisplay = $saldoObat !== null
                                                    ? rtrim(rtrim(number_format((float) $saldoObat, 2, ',', '.'), '0'), ',')
                                                    : null;
                                            @endphp
                                            <div class="flex justify-between text-xs text-blue-800 dark:text-blue-200">
                                                <span class="font-medium uppercase">
                                                    {{ $obat['productName'] ?? '-' }}
                                                    @if ($saldoDisplay !== null)
                                                        @if ($stokKurang)
                                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                                                title="Stok Apotek kurang dari qty diminta">
                                                                Stok: {{ $saldoDisplay }} (kurang)
                                                            </span>
                                                        @else
                                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                                title="Saldo Apotek">
                                                                Stok: {{ $saldoDisplay }}
                                                            </span>
                                                        @endif
                                                    @endif
                                                </span>
                                                <span class="text-blue-600 dark:text-blue-400 shrink-0">
                                                    No.{{ $obat['qty'] ?? '-' }} &mdash;
                                                    S{{ $obat['signaX'] ?? '-' }}dd{{ $obat['signaHari'] ?? '-' }}
                                                    @if (!empty($obat['catatanKhusus']))
                                                        ({{ $obat['catatanKhusus'] }})
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                        @if (!empty($eresep['eresepRacikan']))
                                            @php $prevNo = null; @endphp
                                            @foreach ($eresep['eresepRacikan'] as $racikan)
                                                @isset($racikan['jenisKeterangan'])
                                                    @php
                                                        $pidR = (string) ($racikan['productId'] ?? '');
                                                        $qtyR = (float) ($racikan['qty'] ?? 0);
                                                        $saldoR = $pidR !== '' ? ($saldoPerObat[$pidR] ?? null) : null;
                                                        $kurangR = $saldoR !== null && $qtyR > $saldoR;
                                                        $saldoRDisplay = $saldoR !== null
                                                            ? rtrim(rtrim(number_format((float) $saldoR, 2, ',', '.'), '0'), ',')
                                                            : null;
                                                    @endphp
                                                    <div class="flex justify-between text-xs text-amber-800 dark:text-amber-200
                                                        {{ $prevNo !== ($racikan['noRacikan'] ?? null) ? 'mt-1 pt-1 border-t border-amber-200 dark:border-amber-700' : '' }}">
                                                        <span class="font-medium uppercase">
                                                            {{ $racikan['noRacikan'] ?? '-' }}/
                                                            {{ $racikan['productName'] ?? '-' }}
                                                            @if (!empty($racikan['dosis']))
                                                                &mdash; {{ $racikan['dosis'] }}
                                                            @endif
                                                            @if ($saldoRDisplay !== null)
                                                                @if ($kurangR)
                                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                                                        title="Stok Apotek kurang dari qty diminta">
                                                                        Stok: {{ $saldoRDisplay }} (kurang)
                                                                    </span>
                                                                @else
                                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                                        title="Saldo Apotek">
                                                                        Stok: {{ $saldoRDisplay }}
                                                                    </span>
                                                                @endif
                                                            @endif
                                                        </span>
                                                        @if (!empty($racikan['qty']))
                                                            <span class="text-amber-600 dark:text-amber-400 shrink-0">
                                                                Jml {{ $racikan['qty'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @php $prevNo = $racikan['noRacikan'] ?? null; @endphp
                                                @endisset
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Form telaah resep (data di apotekHdr) --}}
                            @php
                                $telaahResepLabels = [
                                    'kejelasanTulisanResep' => 'Kejelasan Tulisan Resep',
                                    'tepatObat' => 'Tepat Obat',
                                    'tepatDosis' => 'Tepat Dosis',
                                    'tepatRute' => 'Tepat Rute',
                                    'tepatWaktu' => 'Tepat Waktu',
                                    'duplikasi' => 'Duplikasi Obat',
                                    'alergi' => 'Riwayat Alergi',
                                    'interaksiObat' => 'Interaksi Obat',
                                    'bbPasienAnak' => 'BB Pasien Anak',
                                    'kontraIndikasiLain' => 'Kontra Indikasi Lain',
                                ];
                            @endphp

                            @if (isset($apotek['telaahResep']))
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                                    @foreach ($apotek['telaahResep'] as $key => $field)
                                        @if ($key === 'penanggungJawab')
                                            @continue
                                        @endif
                                        @if (!is_array($field) || !isset($field[$key]))
                                            @continue
                                        @endif
                                        <div class="p-3 bg-gray-50 rounded-xl dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $telaahResepLabels[$key] ?? $key }}
                                                    </p>
                                                </div>
                                                <div class="shrink-0">
                                                    <x-toggle
                                                        wire:model.live="dataDaftarRI.apotekHdr.{{ $apotekIndex }}.telaahResep.{{ $key }}.{{ $key }}"
                                                        trueValue="Ya" falseValue="Tidak"
                                                        :disabled="isset($apotek['telaahResep']['penanggungJawab'])">
                                                        {{ ($field[$key] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}
                                                    </x-toggle>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <x-text-input
                                                    wire:model="dataDaftarRI.apotekHdr.{{ $apotekIndex }}.telaahResep.{{ $key }}.desc"
                                                    class="w-full text-xs py-1.5"
                                                    placeholder="Catatan (opsional)..."
                                                    :disabled="isset($apotek['telaahResep']['penanggungJawab'])" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if (isset($apotek['telaahResep']['penanggungJawab']))
                                    <div class="mt-4 p-3 bg-emerald-50 rounded-xl border border-emerald-100 dark:bg-emerald-900/10 dark:border-emerald-800">
                                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-2">
                                            Ringkasan Telaah Resep
                                        </p>
                                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-1.5">
                                            @foreach ($apotek['telaahResep'] as $key => $field)
                                                @if ($key === 'penanggungJawab')
                                                    @continue
                                                @endif
                                                @if (!is_array($field) || !isset($field[$key]))
                                                    @continue
                                                @endif
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    @if (($field[$key] ?? '') === 'Ya')
                                                        <span class="text-emerald-500">✓</span>
                                                        <span class="text-emerald-700 dark:text-emerald-300">{{ $telaahResepLabels[$key] ?? $key }}</span>
                                                    @else
                                                        <span class="text-rose-500">✗</span>
                                                        <span class="text-rose-700 dark:text-rose-400">{{ $telaahResepLabels[$key] ?? $key }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- FOOTER kiri --}}
                        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                            <x-secondary-button wire:click="closeTelaah">Tutup</x-secondary-button>

                            <div class="flex gap-2">
                                @if (!isset($apotek['telaahResep']['penanggungJawab']))
                                    <x-outline-button wire:click="saveTelaahResep" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="saveTelaahResep" class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                            </svg>
                                            Simpan
                                        </span>
                                        <span wire:loading wire:target="saveTelaahResep" class="flex items-center gap-1.5">
                                            <x-loading /> Menyimpan...
                                        </span>
                                    </x-outline-button>

                                    @if (auth()->user()->hasRole('Apoteker'))
                                        <x-success-button wire:click="ttdTelaahResep" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="ttdTelaahResep" class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                </svg>
                                                TTD-E &amp; Selesai
                                            </span>
                                            <span wire:loading wire:target="ttdTelaahResep" class="flex items-center gap-1.5">
                                                <x-loading /> Proses TTD...
                                            </span>
                                        </x-success-button>
                                    @else
                                        <div class="px-3 py-2 text-xs text-amber-700 bg-amber-50 rounded-lg border border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-700">
                                            TTD-E hanya untuk Apoteker
                                        </div>
                                    @endif
                                @else
                                    <div class="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <span>
                                            <strong>TTD-E</strong> oleh
                                            {{ $apotek['telaahResep']['penanggungJawab']['userLog'] }}
                                            pada
                                            {{ $apotek['telaahResep']['penanggungJawab']['userLogDate'] }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ══════════════ KANAN: TELAAH OBAT ══════════════ --}}
                    <div class="flex flex-col">

                        <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                            @if (!empty($eresep['eresep']) || !empty($eresep['eresepRacikan']))
                                <div class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">
                                        Daftar Obat dalam Resep (Dokter)
                                    </p>
                                    <div class="space-y-1">
                                        @foreach ($eresep['eresep'] ?? [] as $idx => $obat)
                                            @php
                                                $pidTo = (string) ($obat['productId'] ?? '');
                                                $qtyTo = (float) ($obat['qty'] ?? 0);
                                                $saldoTo = $pidTo !== '' ? ($saldoPerObat[$pidTo] ?? null) : null;
                                                $kurangTo = $saldoTo !== null && $qtyTo > $saldoTo;
                                                $saldoToDisplay = $saldoTo !== null
                                                    ? rtrim(rtrim(number_format((float) $saldoTo, 2, ',', '.'), '0'), ',')
                                                    : null;
                                            @endphp
                                            <div class="flex justify-between text-xs text-blue-800 dark:text-blue-200">
                                                <span>
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200 text-[9px] font-bold mr-1">{{ $idx + 1 }}</span>
                                                    <span class="font-medium uppercase">{{ $obat['productName'] ?? '-' }}</span>
                                                    @if ($saldoToDisplay !== null)
                                                        @if ($kurangTo)
                                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                                                title="Stok Apotek kurang dari qty diminta">
                                                                Stok: {{ $saldoToDisplay }} (kurang)
                                                            </span>
                                                        @else
                                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                                title="Saldo Apotek">
                                                                Stok: {{ $saldoToDisplay }}
                                                            </span>
                                                        @endif
                                                    @endif
                                                </span>
                                                <span class="text-blue-600 dark:text-blue-400 shrink-0">
                                                    No.{{ $obat['qty'] ?? '-' }} &mdash;
                                                    S{{ $obat['signaX'] ?? '-' }}dd{{ $obat['signaHari'] ?? '-' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @php
                                $telaahObatLabels = [
                                    'obatdgnResep' => 'Obat Sesuai Resep',
                                    'jmlDosisdgnResep' => 'Jumlah & Dosis Sesuai Resep',
                                    'rutedgnResep' => 'Rute Sesuai Resep',
                                    'waktuFrekPemberiandgnResep' => 'Waktu & Frekuensi Pemberian',
                                ];
                            @endphp

                            @if (isset($apotek['telaahObat']))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($apotek['telaahObat'] as $key => $field)
                                        @if ($key === 'penanggungJawab')
                                            @continue
                                        @endif
                                        @if (!is_array($field) || !isset($field[$key]))
                                            @continue
                                        @endif
                                        <div class="p-3 bg-gray-50 rounded-xl dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $telaahObatLabels[$key] ?? $key }}
                                                    </p>
                                                </div>
                                                <div class="shrink-0">
                                                    <x-toggle
                                                        wire:model.live="dataDaftarRI.apotekHdr.{{ $apotekIndex }}.telaahObat.{{ $key }}.{{ $key }}"
                                                        trueValue="Ya" falseValue="Tidak"
                                                        :disabled="isset($apotek['telaahObat']['penanggungJawab'])">
                                                        {{ ($field[$key] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}
                                                    </x-toggle>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <x-text-input
                                                    wire:model="dataDaftarRI.apotekHdr.{{ $apotekIndex }}.telaahObat.{{ $key }}.desc"
                                                    class="w-full text-xs py-1.5"
                                                    placeholder="Catatan (opsional)..."
                                                    :disabled="isset($apotek['telaahObat']['penanggungJawab'])" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if (isset($apotek['telaahObat']['penanggungJawab']))
                                    <div class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100 dark:bg-blue-900/10 dark:border-blue-800">
                                        <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-2">
                                            Ringkasan Telaah Obat
                                        </p>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            @foreach ($apotek['telaahObat'] as $key => $field)
                                                @if ($key === 'penanggungJawab')
                                                    @continue
                                                @endif
                                                @if (!is_array($field) || !isset($field[$key]))
                                                    @continue
                                                @endif
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    @if (($field[$key] ?? '') === 'Ya')
                                                        <span class="text-emerald-500">✓</span>
                                                        <span class="text-emerald-700 dark:text-emerald-300">{{ $telaahObatLabels[$key] ?? $key }}</span>
                                                    @else
                                                        <span class="text-rose-500">✗</span>
                                                        <span class="text-rose-700 dark:text-rose-400">{{ $telaahObatLabels[$key] ?? $key }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- FOOTER kanan --}}
                        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                            <x-secondary-button wire:click="closeTelaah">Tutup</x-secondary-button>

                            <div class="flex gap-2">
                                @if (!isset($apotek['telaahObat']['penanggungJawab']))
                                    <x-outline-button wire:click="saveTelaahObat" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="saveTelaahObat" class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                            </svg>
                                            Simpan
                                        </span>
                                        <span wire:loading wire:target="saveTelaahObat" class="flex items-center gap-1.5">
                                            <x-loading /> Menyimpan...
                                        </span>
                                    </x-outline-button>

                                    @if (auth()->user()->hasRole('Apoteker'))
                                        <x-info-button wire:click="ttdTelaahObat" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="ttdTelaahObat" class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                </svg>
                                                TTD-E &amp; Selesai
                                            </span>
                                            <span wire:loading wire:target="ttdTelaahObat" class="flex items-center gap-1.5">
                                                <x-loading /> Proses TTD...
                                            </span>
                                        </x-info-button>
                                    @else
                                        <div class="px-3 py-2 text-xs text-amber-700 bg-amber-50 rounded-lg border border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-700">
                                            TTD-E hanya untuk Apoteker
                                        </div>
                                    @endif
                                @else
                                    <div class="flex items-center gap-1.5 text-xs text-blue-700 dark:text-blue-300">
                                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <span>
                                            <strong>TTD-E</strong> oleh
                                            {{ $apotek['telaahObat']['penanggungJawab']['userLog'] }}
                                            pada
                                            {{ $apotek['telaahObat']['penanggungJawab']['userLogDate'] }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            @endif

        </div>
    </x-modal>
</div>
