<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public string $activeTab = 'NonRacikan'; // tab aktif, default Non Racikan

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | OPEN ERESEP RJ
     =============================== */
    #[On('emr-rj.eresep.open')]
    public function openEresep(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;

        // Cek status lock kunjungan
        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        // Initialize struktur data resep jika belum ada
        $this->dataDaftarPoliRJ['eresep'] ??= [];
        $this->dataDaftarPoliRJ['eresepRacikan'] ??= [];

        // Buka modal
        $this->dispatch('open-modal', name: 'emr-rj.eresep-rj');

        // 🔥 INCREMENT: Refresh komponen anak jika perlu
        $this->incrementVersion('modal');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-rj.eresep-rj');
    }

    /* ===============================
     | TOGGLE STATUS PRB
     | Toggle flag statusPRB di JSON.
     | ON  → set perencanaan.tindakLanjut = 'PRB'
     | OFF → reset tindakLanjut hanya jika sebelumnya 'PRB' (jangan timpa pilihan user lain)
     =============================== */
    public function setStatusPRB(): void
    {
        if ($this->isFormLocked || empty($this->rjNo)) {
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $statusPRB = isset($data['statusPRB']['penanggungJawab']['statusPRB'])
                    ? !$data['statusPRB']['penanggungJawab']['statusPRB']
                    : 1;

                $data['statusPRB']['penanggungJawab'] = [
                    'statusPRB' => $statusPRB,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => now()->format('d/m/Y H:i:s'),
                    'userLogCode' => auth()->user()->myuser_code,
                ];

                if ($statusPRB) {
                    $data['perencanaan']['tindakLanjut']['tindakLanjut'] = 'PRB';
                } else {
                    if (($data['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') === 'PRB') {
                        $data['perencanaan']['tindakLanjut']['tindakLanjut'] = null;
                    }
                }

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Status PRB berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui status PRB: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TOGGLE STATUS ITER (dokter — intent)
     | Toggle flag statusIter di JSON + langsung update rstxn_rjhdrs.status_iter.
     | Apoteker nanti detail per-obat (qty iter) di obat-rj; sync header
     | otomatis dari obat-rj override flag ini saat ada perubahan obat.
     =============================== */
    public function setStatusIter(): void
    {
        if ($this->isFormLocked || empty($this->rjNo)) {
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $statusIter = isset($data['statusIter']['penanggungJawab']['statusIter'])
                    ? !$data['statusIter']['penanggungJawab']['statusIter']
                    : 1;

                $data['statusIter']['penanggungJawab'] = [
                    'statusIter' => $statusIter,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => now()->format('d/m/Y H:i:s'),
                    'userLogCode' => auth()->user()->myuser_code,
                ];

                $this->updateJsonRJ($this->rjNo, $data);

                // Sinkron langsung ke rstxn_rjhdrs.status_iter
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['status_iter' => $statusIter ? 'Y' : 'N']);

                $this->dataDaftarPoliRJ = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Status Iter berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui status Iter: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE ALL ERESEP → TERAPI
     | Dipanggil dari tombol Simpan di footer modal.
     | Membangun teks terapi dari eresep + eresepRacikan,
     | lalu menyimpannya ke key perencanaan.terapi.
     =============================== */
    public function saveAllEreseptoTerapi(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tujuan belum ditentukan.');
            return;
        }

        // 2. Guard: pasien sudah pulang
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // 3. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 5. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 6. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // ── BUILD TEKS TERAPI ─────────────────────────────────────────
                $eresepText = collect($data['eresep'] ?? [])
                    ->map(function ($item) {
                        $catatan = $item['catatanKhusus'] ? " ({$item['catatanKhusus']})" : '';
                        return "R/ {$item['productName']} | No. {$item['qty']} | S {$item['signaX']}dd{$item['signaHari']}{$catatan}";
                    })
                    ->implode(PHP_EOL);

                $eresepRacikanText = collect($data['eresepRacikan'] ?? [])
                    ->filter(fn($item) => isset($item['jenisKeterangan']))
                    ->map(function ($item) {
                        // Catatan & signa (catatanKhusus) tetap tampil walau qty kosong —
                        // sebelumnya seluruh baris di-skip kalau qty falsy sehingga
                        // catatan/signa hilang dari terapi EMR.
                        $parts = [];
                        if (!empty($item['qty'])) {
                            $parts[] = "Jml Racikan {$item['qty']}";
                        }
                        if (!empty($item['catatan'])) {
                            $parts[] = $item['catatan'];
                        }
                        if (!empty($item['catatanKhusus'])) {
                            $parts[] = "S {$item['catatanKhusus']}";
                        }
                        $jmlRacikan = $parts ? implode(' | ', $parts) . PHP_EOL : '';
                        // Satuan (takar) ikut di baris produk, setelah dosis.
                        $satuan = !empty($item['takar']) ? ' ' . $item['takar'] : '';
                        return "{$item['noRacikan']}/ {$item['productName']} - " . ($item['dosis'] ?? '') . $satuan . PHP_EOL . $jmlRacikan;
                    })
                    ->implode('');

                // 7. Merge ke perencanaan yang sudah ada — tidak overwrite seluruh perencanaan
                $data['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;

                // 8. Auto-isi waktu pemeriksaan jika belum diisi
                if (empty($data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'])) {
                    $data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
                }

                // 9. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Eresep berhasil disimpan.');
            $this->dispatch('emr-rj.rekam-medis.open', $this->rjNo);
            $this->closeModal();
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan eresep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ', 'activeTab']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="emr-rj.eresep-rj" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-3.5 border-b border-hairline dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    {{-- Title kecil + Data Pasien (mengikuti pola EMR RJ) --}}
                    <div class="flex-1 min-w-0 space-y-2">
                        {{-- Title kecil --}}
                        <div class="text-xs font-medium tracking-wide text-muted uppercase dark:text-gray-400">
                            E-Resep Rawat Jalan — Penulisan resep obat racikan dan non racikan
                        </div>

                        {{-- Data Pasien (dipindah dari body ke header, kayak EMR RJ) --}}
                        <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                            wire:key="eresep-rj-display-pasien-rj-header-{{ $rjNo }}" />

                        {{-- Info status --}}
                        <div class="flex flex-wrap items-center gap-4 mt-3">
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif

                            {{-- Toggle Status PRB --}}
                            <x-toggle :current="$dataDaftarPoliRJ['statusPRB']['penanggungJawab']['statusPRB'] ?? 0"
                                :trueValue="1" :falseValue="0"
                                wireClick="setStatusPRB"
                                :disabled="$isFormLocked">
                                Status PRB
                                @if (!empty($dataDaftarPoliRJ['statusPRB']['penanggungJawab']['userLog'] ?? null))
                                    <span class="ml-1 text-xs text-muted dark:text-gray-400">
                                        — {{ $dataDaftarPoliRJ['statusPRB']['penanggungJawab']['userLog'] }}
                                        @if (!empty($dataDaftarPoliRJ['statusPRB']['penanggungJawab']['userLogDate'] ?? null))
                                            · {{ $dataDaftarPoliRJ['statusPRB']['penanggungJawab']['userLogDate'] }}
                                        @endif
                                    </span>
                                @endif
                            </x-toggle>

                            {{-- Toggle Status Iter --}}
                            <x-toggle :current="$dataDaftarPoliRJ['statusIter']['penanggungJawab']['statusIter'] ?? 0"
                                :trueValue="1" :falseValue="0"
                                wireClick="setStatusIter"
                                :disabled="$isFormLocked">
                                Status Iter
                                @if (!empty($dataDaftarPoliRJ['statusIter']['penanggungJawab']['userLog'] ?? null))
                                    <span class="ml-1 text-xs text-muted dark:text-gray-400">
                                        — {{ $dataDaftarPoliRJ['statusIter']['penanggungJawab']['userLog'] }}
                                        @if (!empty($dataDaftarPoliRJ['statusIter']['penanggungJawab']['userLogDate'] ?? null))
                                            · {{ $dataDaftarPoliRJ['statusIter']['penanggungJawab']['userLogDate'] }}
                                        @endif
                                    </span>
                                @endif
                            </x-toggle>
                        </div>
                    </div>

                    {{-- Tombol close --}}
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
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
            <div class="flex-1 px-4 pt-3 pb-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="grid max-w-full grid-cols-3 gap-4 mx-auto">
                    <div
                        class="col-span-2 p-4 space-y-2.5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Tab Navigasi Racikan / Non Racikan --}}
                        <div x-data="{ activeTab: @entangle('activeTab') }" class="w-full">
                            <div class="px-2 mb-0 overflow-auto border-b border-hairline">
                                <ul
                                    class="flex flex-row flex-wrap justify-center -mb-px text-sm font-medium text-muted text-start">

                                    {{-- Non Racikan Tab --}}
                                    <li class="mx-1 mr-0 rounded-t-lg"
                                        :class="activeTab === 'NonRacikan' ? 'text-brand border-brand dark:text-emerald-300 dark:border-emerald-400 bg-surface-soft' :
                                            'border border-hairline'">
                                        <label
                                            class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-muted hover:border-gray-300"
                                            x-on:click="activeTab = 'NonRacikan'"
                                            wire:click="$set('activeTab', 'NonRacikan')">
                                            Non Racikan
                                        </label>
                                    </li>

                                    {{-- Racikan Tab --}}
                                    <li class="mx-1 mr-0 rounded-t-lg"
                                        :class="activeTab === 'Racikan' ? 'text-brand border-brand dark:text-emerald-300 dark:border-emerald-400 bg-surface-soft' :
                                            'border border-hairline'">
                                        <label
                                            class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-muted hover:border-gray-300"
                                            x-on:click="activeTab = 'Racikan'"
                                            wire:click="$set('activeTab', 'Racikan')">
                                            Racikan
                                        </label>
                                    </li>

                                </ul>
                            </div>

                            {{-- Konten Tab Non Racikan --}}
                            <div class="w-full mt-2 rounded-lg bg-surface-soft" x-show="activeTab === 'NonRacikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.rj.eresep-rj.eresep-rj-non-racikan
                                    wire:key="{{ $this->renderKey('modal', ['non-racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>

                            {{-- Konten Tab Racikan --}}
                            <div class="w-full mt-2 rounded-lg bg-surface-soft" x-show="activeTab === 'Racikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.rj.eresep-rj.eresep-rj-racikan
                                    wire:key="{{ $this->renderKey('modal', ['racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>
                        </div>
                    </div>

                    {{-- REKAM MEDIS --}}
                    <div>
                        <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" :rjNoRefCopyTo="$rjNo ?? 0"
                            wire:key="eresep-rj-rekam-medis-display-rj-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}-{{ $rjNo ?? 'none' }}" />
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>

                    @if (!$isFormLocked)
                        <x-primary-button wire:click="saveAllEreseptoTerapi" class="min-w-[120px]"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan
                            </span>
                            <span wire:loading>
                                <x-loading />
                                Menyimpan...
                            </span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
