<?php
// resources/views/pages/transaksi/ri/emr-ri/asuhan-keperawatan/rm-asuhan-keperawatan-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    /* ── LOVDiagKepTrait dihapus — komunikasi via dispatch event ── */

    public bool    $isFormLocked = false;
    public ?string $riHdrNo      = null;
    public array   $dataDaftarRi = [];

    public array $formEntryAsuhanKeperawatan = [
        'tglAsuhanKeperawatan'         => '',
        'petugasAsuhanKeperawatan'     => '',
        'petugasAsuhanKeperawatanCode' => '',
        'diagKepId'                    => '',
        'diagKepDesc'                  => '',
        'diagKepJson'                  => [],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-asuhan-keperawatan-ri'];

    /* ================================================================
     | MOUNT
     ================================================================ */
    public function mount(): void
    {
        $this->registerAreas(['modal-asuhan-keperawatan-ri']);
    }

    /* ================================================================
     | OPEN — dipanggil dari tab EMR RI
     ================================================================ */
    #[On('open-rm-asuhan-keperawatan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;

        $this->riHdrNo = $riHdrNo;
        $this->resetFormEntry();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['asuhanKeperawatan'] ??= [];

        $this->incrementVersion('modal-asuhan-keperawatan-ri');

        $riStatus = DB::scalar(
            "select ri_status from rstxn_rihdrs where rihdr_no = :r",
            ['r' => $riHdrNo]
        );
        $this->isFormLocked = ($riStatus !== 'I');
    }

    /* ================================================================
     | TERIMA PAYLOAD DARI lov-diag-kep (standalone component)
     | target = 'riFormAsuhanKeperawatan'
     ================================================================ */
    #[On('lov.selected.riFormAsuhanKeperawatan')]
    public function onDiagKepSelected(string $target, ?array $payload): void
    {
        if (empty($payload)) {
            // User klik "Ubah" / clear
            $this->formEntryAsuhanKeperawatan['diagKepId']   = '';
            $this->formEntryAsuhanKeperawatan['diagKepDesc'] = '';
            $this->formEntryAsuhanKeperawatan['diagKepJson'] = [];
            return;
        }

        $this->formEntryAsuhanKeperawatan['diagKepId']   = $payload['diagkep_id']   ?? '';
        $this->formEntryAsuhanKeperawatan['diagKepDesc'] = $payload['diagkep_desc'] ?? '';
        $this->formEntryAsuhanKeperawatan['diagKepJson'] = $payload['diagkep_json'] ?? [];
    }

    /* ================================================================
     | SET TANGGAL
     ================================================================ */
    public function setTglAsuhanKeperawatan(): void
    {
        $this->formEntryAsuhanKeperawatan['tglAsuhanKeperawatan'] =
            Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ================================================================
     | ADD
     ================================================================ */
    public function addAsuhanKeperawatan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $this->formEntryAsuhanKeperawatan['petugasAsuhanKeperawatan']     = auth()->user()->myuser_name;
        $this->formEntryAsuhanKeperawatan['petugasAsuhanKeperawatanCode'] = auth()->user()->myuser_code;

        $this->validate([
            'formEntryAsuhanKeperawatan.tglAsuhanKeperawatan'         => 'required|date_format:d/m/Y H:i:s',
            'formEntryAsuhanKeperawatan.petugasAsuhanKeperawatan'     => 'required|string|max:200',
            'formEntryAsuhanKeperawatan.petugasAsuhanKeperawatanCode' => 'required|string|max:50',
            'formEntryAsuhanKeperawatan.diagKepId'                    => 'required|string|exists:rsmst_diagkeperawatans,diagkep_id',
            'formEntryAsuhanKeperawatan.diagKepDesc'                  => 'required|string|max:500',
            'formEntryAsuhanKeperawatan.diagKepJson'                  => 'required|array|min:1',
        ], [
            'formEntryAsuhanKeperawatan.tglAsuhanKeperawatan.required'    => 'Tanggal Asuhan Keperawatan wajib diisi.',
            'formEntryAsuhanKeperawatan.tglAsuhanKeperawatan.date_format' => 'Format tanggal harus d/m/Y H:i:s.',
            'formEntryAsuhanKeperawatan.petugasAsuhanKeperawatan.required' => 'Nama petugas wajib diisi.',
            'formEntryAsuhanKeperawatan.petugasAsuhanKeperawatanCode.required' => 'Kode petugas wajib diisi.',
            'formEntryAsuhanKeperawatan.diagKepId.required'               => 'Diagnosis Keperawatan wajib dipilih.',
            'formEntryAsuhanKeperawatan.diagKepId.exists'                 => 'Diagnosis Keperawatan tidak ditemukan di master.',
            'formEntryAsuhanKeperawatan.diagKepDesc.required'             => 'Deskripsi Diagnosis Keperawatan wajib diisi.',
            'formEntryAsuhanKeperawatan.diagKepJson.required'             => 'Detail Diagnosis Keperawatan wajib diisi.',
            'formEntryAsuhanKeperawatan.diagKepJson.min'                  => 'Minimal satu item Diagnosis Keperawatan.',
        ]);

        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['asuhanKeperawatan'] ??= [];
                $fresh['asuhanKeperawatan'][] = $this->formEntryAsuhanKeperawatan;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->resetFormEntry();
            $this->afterSave('Asuhan Keperawatan berhasil ditambahkan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ================================================================
     | REMOVE
     ================================================================ */
    public function removeAsuhanKeperawatan(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                if (!isset($fresh['asuhanKeperawatan'][$index])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }
                array_splice($fresh['asuhanKeperawatan'], $index, 1);
                $fresh['asuhanKeperawatan'] = array_values($fresh['asuhanKeperawatan']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('Asuhan Keperawatan berhasil dihapus.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ================================================================
     | RESET FORM — tidak perlu reset collectingMyDiagKep lagi
     ================================================================ */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryAsuhanKeperawatan']);
        $this->resetValidation();

        /* Dispatch ke lov-diag-kep agar state LOV ikut di-reset */
        $this->dispatch('lov-diag-kep.reset', target: 'riFormAsuhanKeperawatan');
    }

    /* ================================================================
     | HELPERS
     ================================================================ */
    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-asuhan-keperawatan-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                $fn();
            }, 5);
        });
    }

    /* render: tidak ada syncLOV() / syncDataFormEntry() lagi */
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-asuhan-keperawatan-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ============================================================
    | FORM ENTRY
    ============================================================= --}}
    @if (!$isFormLocked)
    <x-border-form title="Entry Asuhan Keperawatan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-4">

            {{-- Tanggal --}}
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal Asuhan Keperawatan *" />
                    <x-text-input
                        wire:model="formEntryAsuhanKeperawatan.tglAsuhanKeperawatan"
                        class="w-full mt-1 font-mono"
                        readonly
                        placeholder="dd/mm/yyyy hh:mm:ss"
                        :error="$errors->has('formEntryAsuhanKeperawatan.tglAsuhanKeperawatan')" />
                    <x-input-error
                        :messages="$errors->get('formEntryAsuhanKeperawatan.tglAsuhanKeperawatan')"
                        class="mt-1" />
                </div>
                <x-secondary-button wire:click="setTglAsuhanKeperawatan" type="button">
                    Sekarang
                </x-secondary-button>
            </div>

            {{-- LOV Diagnosis Keperawatan (standalone) --}}
            {{--
                target  : harus cocok dengan nama event yang di-listen di #[On]
                          yaitu 'lov.selected.riFormAsuhanKeperawatan'
                disabled: ikuti isFormLocked
            --}}
            <div>
                <livewire:lov.diag-kep.lov-diag-kep
                    label="Diagnosis Keperawatan *"
                    target="riFormAsuhanKeperawatan"
                    :disabled="$isFormLocked"
                    wire:key="lov-diagkep-{{ $this->renderKey('modal-asuhan-keperawatan-ri') }}" />

                <x-input-error :messages="$errors->get('formEntryAsuhanKeperawatan.diagKepId')"   class="mt-1" />
                <x-input-error :messages="$errors->get('formEntryAsuhanKeperawatan.diagKepJson')" class="mt-1" />
            </div>

            {{-- Preview diagKep terpilih
                 (LOV baru sudah render preview di dalam dirinya sendiri
                  saat mode selected, tapi kita tetap tampilkan di sini
                  sebagai konfirmasi nilai yang akan disimpan) --}}
            @if (!empty($formEntryAsuhanKeperawatan['diagKepDesc']))
            <div class="rounded-lg border border-brand/30 bg-brand/5 px-4 py-3">
                <p class="text-xs font-semibold text-brand uppercase tracking-wide mb-1">
                    Diagnosis Keperawatan Terpilih
                </p>
                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                    {{ $formEntryAsuhanKeperawatan['diagKepDesc'] }}
                    <span class="ml-1 font-mono text-xs text-gray-400">
                        ({{ $formEntryAsuhanKeperawatan['diagKepId'] }})
                    </span>
                </p>

                {{-- Detail JSON: SDKI / SIKI / SLKI --}}
                @if (!empty($formEntryAsuhanKeperawatan['diagKepJson']))
                <div class="mt-2 space-y-1.5">
                    @foreach ($formEntryAsuhanKeperawatan['diagKepJson'] as $kunci => $nilai)
                    @if (!empty($nilai))
                    <div class="text-xs">
                        <span class="font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                            {{ $kunci }}:
                        </span>
                        @if (is_array($nilai))
                        <ul class="mt-0.5 ml-4 list-disc text-gray-700 dark:text-gray-300 space-y-0.5">
                            @foreach ($nilai as $item)
                            <li>
                                @if (is_array($item))
                                    @foreach ($item as $sk => $sv)
                                        <span class="font-medium">{{ $sk }}:</span>
                                        {{ is_array($sv) ? implode(', ', $sv) : $sv }}
                                    @endforeach
                                @else
                                    {{ $item }}
                                @endif
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $nilai }}</span>
                        @endif
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Tombol reset form + LOV --}}
            <x-ghost-button wire:click="resetFormEntry" type="button" class="text-xs">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Reset Pilihan
            </x-ghost-button>
            @endif

            {{-- Tombol simpan --}}
            <div class="flex justify-end pt-1">
                <x-primary-button wire:click="addAsuhanKeperawatan" type="button">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Asuhan Keperawatan
                </x-primary-button>
            </div>

        </div>
    </x-border-form>
    @endif

    {{-- ============================================================
    | LIST ASUHAN KEPERAWATAN
    ============================================================= --}}
    <x-border-form title="Riwayat Asuhan Keperawatan" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">

            @forelse ($dataDaftarRi['asuhanKeperawatan'] ?? [] as $idx => $askep)
            <div
                wire:key="askep-{{ $idx }}-{{ $this->renderKey('modal-asuhan-keperawatan-ri') }}"
                class="border border-gray-200 dark:border-gray-700 rounded-lg
                       bg-white dark:bg-gray-800 overflow-hidden">

                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-2.5
                            bg-gray-50 dark:bg-gray-700/60
                            border-b border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 space-x-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">
                            {{ $askep['petugasAsuhanKeperawatan'] ?? '-' }}
                        </span>
                        <span class="font-mono">{{ $askep['tglAsuhanKeperawatan'] ?? '-' }}</span>
                    </div>

                    @if (!$isFormLocked)
                    <x-icon-button
                        variant="danger"
                        wire:click="removeAsuhanKeperawatan({{ $idx }})"
                        wire:confirm="Yakin ingin menghapus Asuhan Keperawatan ini?"
                        tooltip="Hapus">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                     L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </x-icon-button>
                    @endif
                </div>

                {{-- Body --}}
                <div class="px-4 py-3 space-y-2">

                    {{-- Nama diagnosis --}}
                    <p class="text-sm font-semibold text-brand dark:text-emerald-400">
                        {{ $askep['diagKepDesc'] ?? '-' }}
                        <span class="ml-1 font-mono text-xs text-gray-400">
                            ({{ $askep['diagKepId'] ?? '' }})
                        </span>
                    </p>

                    {{-- Detail JSON: SDKI/SIKI/SLKI --}}
                    @if (!empty($askep['diagKepJson']) && is_array($askep['diagKepJson']))
                    <div class="space-y-2 text-xs">
                        @foreach ($askep['diagKepJson'] as $kunci => $nilai)
                        @if (!empty($nilai))
                        <div>
                            <span class="font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                {{ $kunci }}:
                            </span>
                            @if (is_array($nilai))
                            <ul class="mt-1 ml-4 list-disc text-gray-700 dark:text-gray-300 space-y-0.5">
                                @foreach ($nilai as $item)
                                <li>
                                    @if (is_array($item))
                                        @foreach ($item as $sk => $sv)
                                            <span class="font-medium">{{ $sk }}:</span>
                                            {{ is_array($sv) ? implode(', ', $sv) : $sv }}
                                        @endforeach
                                    @else
                                        {{ $item }}
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                            @else
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $nilai }}</span>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @endif

                </div>
            </div>
            @empty
            <p
                wire:key="askep-empty-{{ $this->renderKey('modal-asuhan-keperawatan-ri') }}"
                class="text-xs text-center text-gray-400 py-6">
                Belum ada Asuhan Keperawatan.
            </p>
            @endforelse

        </div>
    </x-border-form>

</div>
