<?php
// resources/views/pages/transaksi/ri/emr-ri/sbar-ri/rm-sbar-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public string $activeProfession = 'Semua';
    public array $professionTabs = ['Semua', 'Dokter', 'Perawat', 'Apoteker', 'Gizi', 'Penunjang'];

    public array $formEntrySBAR = [
        'tglSBAR' => '',
        'petugasSBAR' => '',
        'petugasSBARCode' => '',
        'profession' => '',
        'sbar' => ['situation' => '', 'background' => '', 'assessment' => '', 'recommendation' => ''],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-sbar-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-sbar-ri']);
    }

    #[On('open-rm-sbar-ri')]
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
        $this->dataDaftarRi['sbar'] ??= [];

        $role = auth()->user()->roles->first()->name ?? '';
        $this->activeProfession = match (true) {
            in_array($role, ['Dokter']) => 'Dokter',
            in_array($role, ['Perawat']) => 'Perawat',
            in_array($role, ['Apoteker']) => 'Apoteker',
            in_array($role, ['Gizi']) => 'Gizi',
            default => 'Semua',
        };

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-sbar-ri');
    }

    public function setTglSBAR(): void
    {
        $this->formEntrySBAR['tglSBAR'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-sbar-ri');
    }

    #[On('save-rm-sbar-ri')]
    public function addSBAR(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntrySBAR['petugasSBAR'] = auth()->user()->myuser_name;
        $this->formEntrySBAR['petugasSBARCode'] = auth()->user()->myuser_code;
        $this->formEntrySBAR['profession'] = auth()->user()->roles->first()->name ?? '';

        $this->validateWithToast(
            [
                'formEntrySBAR.tglSBAR' => 'required|date_format:d/m/Y H:i:s',
                'formEntrySBAR.sbar.situation' => 'required|string|max:2000',
                'formEntrySBAR.sbar.background' => 'required|string|max:2000',
                'formEntrySBAR.sbar.assessment' => 'required|string|max:2000',
                'formEntrySBAR.sbar.recommendation' => 'required|string|max:2000',
            ],
            [
                'formEntrySBAR.tglSBAR.required' => 'Tanggal SBAR wajib diisi.',
                'formEntrySBAR.sbar.situation.required' => 'Situation (S) wajib diisi.',
                'formEntrySBAR.sbar.background.required' => 'Background (B) wajib diisi.',
                'formEntrySBAR.sbar.assessment.required' => 'Assessment (A) wajib diisi.',
                'formEntrySBAR.sbar.recommendation.required' => 'Recommendation (R) wajib diisi.',
            ],
        );

        $fingerprint = md5(json_encode([$this->formEntrySBAR['tglSBAR'], $this->formEntrySBAR['sbar']], JSON_UNESCAPED_UNICODE));

        try {
            $inserted = false;

            DB::transaction(function () use ($fingerprint, &$inserted) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['sbar'] ??= [];

                if (collect($fresh['sbar'])->first(fn($r) => ($r['fingerprint'] ?? null) === $fingerprint)) {
                    $this->dispatch('toast', type: 'info', message: 'SBAR yang sama sudah tersimpan.');
                    return;
                }

                $fresh['sbar'][] = array_merge($this->formEntrySBAR, [
                    'sbarId' => (string) Str::uuid(),
                    'fingerprint' => $fingerprint,
                ]);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $inserted = true;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah SBAR — entri ' . $this->formEntrySBAR['tglSBAR'] . ' (' . ($this->formEntrySBAR['profession'] ?: '-') . ')', 'MR');
            });

            if ($inserted) {
                $this->reset(['formEntrySBAR']);
                $this->afterSave('SBAR berhasil ditambahkan.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeSBAR(string $sbarId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Hapus SBAR: hanya level Supervisor (2) ke atas — fungsional (Dokter/Perawat dll) tidak bisa walau pemilik entri
        if (!auth()->user()->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis', 'Supervisor Penunjang', 'Supervisor Tu', 'Mr', 'Casemix'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Supervisor ke atas yang dapat menghapus SBAR.');
            return;
        }

        try {
            DB::transaction(function () use ($sbarId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list = collect($fresh['sbar'] ?? []);
                $idx = $list->search(fn($r) => ($r['sbarId'] ?? null) === $sbarId);

                if ($idx === false) {
                    throw new \RuntimeException('SBAR tidak ditemukan.');
                }

                $row = $list->get($idx);
                $list->forget($idx);
                $fresh['sbar'] = $list->values()->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus SBAR — entri ' . ($row['tglSBAR'] ?? '-') . ' oleh ' . ($row['petugasSBAR'] ?? '-'), 'MR');
            });

            $this->afterSave('SBAR berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── DPJP Utama dari leveling (Pengkajian Awal). drId === User.myuser_code ── */
    private function dpjpUtamaRow(array $data): ?array
    {
        return collect($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])
            ->first(fn($r) => strcasecmp((string) ($r['levelDokter'] ?? ''), 'Utama') === 0);
    }

    /* ── Review/TTD SBAR — HANYA DPJP Utama ── */
    public function reviewSbarDpjp(string $sbarId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $dpjp = $this->dpjpUtamaRow($this->dataDaftarRi);
        $dpjpId = (string) ($dpjp['drId'] ?? '');
        $isAdmin = auth()->user()->hasRole('Admin');
        if (!$isAdmin && $dpjpId !== auth()->user()->myuser_code) {
            $this->dispatch('toast', type: 'error', message: 'Hanya DPJP Utama / Admin yang dapat me-review SBAR.');
            return;
        }
        if ($dpjpId === '') {
            $this->dispatch('toast', type: 'error', message: 'DPJP Utama belum ditentukan di leveling Pengkajian Awal.');
            return;
        }

        try {
            DB::transaction(function () use ($sbarId, $dpjp, $dpjpId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list = collect($fresh['sbar'] ?? []);
                $idx = $list->search(fn($r) => ($r['sbarId'] ?? null) === $sbarId);
                if ($idx === false) {
                    throw new \RuntimeException('SBAR tidak ditemukan.');
                }

                $row = $list->get($idx);
                $row['reviewDpjp'] = [
                    'drId' => $dpjpId,
                    'drName' => $dpjp['drName'] ?? auth()->user()->myuser_name,
                    'tglReview' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];
                $list->put($idx, $row);
                $fresh['sbar'] = $list->values()->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Review SBAR — entri ' . ($row['tglSBAR'] ?? '-') . ' oleh DPJP ' . ($dpjp['drName'] ?? '-'), 'MR');
            });

            $this->afterSave('SBAR sudah direview DPJP Utama.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Batal review — HANYA DPJP Utama ── */
    public function batalReviewSbarDpjp(string $sbarId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $dpjp = $this->dpjpUtamaRow($this->dataDaftarRi);
        $dpjpId = (string) ($dpjp['drId'] ?? '');
        $isAdmin = auth()->user()->hasRole('Admin');
        if (!$isAdmin && $dpjpId !== auth()->user()->myuser_code) {
            $this->dispatch('toast', type: 'error', message: 'Hanya DPJP Utama / Admin yang dapat membatalkan review.');
            return;
        }

        try {
            DB::transaction(function () use ($sbarId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list = collect($fresh['sbar'] ?? []);
                $idx = $list->search(fn($r) => ($r['sbarId'] ?? null) === $sbarId);
                if ($idx === false) {
                    throw new \RuntimeException('SBAR tidak ditemukan.');
                }

                $row = $list->get($idx);
                unset($row['reviewDpjp']);
                $list->put($idx, $row);
                $fresh['sbar'] = $list->values()->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Batal review SBAR — entri ' . ($row['tglSBAR'] ?? '-'), 'MR');
            });

            $this->afterSave('Review DPJP dibatalkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Cetak PDF satu entri SBAR ── */
    public function printSbar(string $sbarId): mixed
    {
        $sbar = collect($this->dataDaftarRi['sbar'] ?? [])->first(fn($r) => ($r['sbarId'] ?? null) === $sbarId);
        if (empty($sbar)) {
            $this->dispatch('toast', type: 'error', message: 'SBAR tidak ditemukan.');
            return null;
        }

        $regNo = (string) ($this->dataDaftarRi['regNo'] ?? '');
        $pasienData = $regNo !== '' ? $this->findDataMasterPasien($regNo) : [];
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pdf = Pdf::loadView('pages.components.rekam-medis.r-i.cetak-sbar.cetak-sbar-ri-print', [
            'sbar' => $sbar,
            'dataPasien' => $pasienData,
            'dataDaftarRi' => $this->dataDaftarRi,
        ])->setPaper('A4');

        $filename = 'sbar-ri-' . ($regNo !== '' ? $regNo : $this->riHdrNo) . '-' . substr($sbarId, 0, 8) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }

    public function copySBAR(string $sbarId): void
    {
        $sbar = collect($this->dataDaftarRi['sbar'] ?? [])->first(fn($r) => ($r['sbarId'] ?? null) === $sbarId);

        if (!$sbar) {
            $this->dispatch('toast', type: 'error', message: 'SBAR tidak ditemukan.');
            return;
        }

        // Copy hanya bisa dilakukan oleh role yang sama, kecuali Admin
        if (!auth()->user()->hasRole('Admin')) {
            $myRole = auth()->user()->roles->first()->name ?? '';
            $sbarRole = $sbar['profession'] ?? '';
            if ($myRole !== $sbarRole) {
                $this->dispatch('toast', type: 'error', message: 'Hanya bisa copy SBAR dari profesi yang sama.');
                return;
            }
        }

        $this->formEntrySBAR = array_merge($this->formEntrySBAR, [
            'tglSBAR' => '',
            'petugasSBAR' => '',
            'petugasSBARCode' => '',
            'profession' => '',
            'sbar' => $sbar['sbar'] ?? ['situation' => '', 'background' => '', 'assessment' => '', 'recommendation' => ''],
        ]);

        $this->incrementVersion('modal-sbar-ri');
        $this->dispatch('toast', type: 'success', message: 'SBAR dicopy ke form.');
    }

    public function getSbarCount(string $profession): int
    {
        $list = $this->dataDaftarRi['sbar'] ?? [];
        if ($profession === 'Semua') {
            return count($list);
        }
        return collect($list)->where('profession', $profession)->count();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-sbar-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
        // Modal-level dirty tracker reset + saveAndClose counter
        $this->dispatch('refresh-after-ri.saved', tab: 'sbar');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->activeProfession = 'Semua';
        $this->reset(['formEntrySBAR']);
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-sbar-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{
        sectionDirty: false,
        openedAt: 0,
        markDirty() {
            if (!this.sectionDirty && Date.now() - this.openedAt > 300) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: 'sbar' });
            }
        },
    }"
    x-init="
        openedAt = Date.now();
        window.addEventListener('refresh-after-ri.saved', (e) => {
            const tab = e.detail?.tab;
            if (tab && tab !== 'sbar') return;
            sectionDirty = false;
            openedAt = Date.now();
            $dispatch('section-clean', { tab: 'sbar' });
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

    {{-- ── SBAR CONTENT ── --}}
    <div class="space-y-4">

        {{-- FORM ENTRY --}}
        @if (!$isFormLocked)
            <div class="space-y-3">

                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label value="Tanggal SBAR *" />
                        <x-text-input wire:model="formEntrySBAR.tglSBAR" class="w-full mt-1 font-mono"
                            placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('formEntrySBAR.tglSBAR')" />
                        <x-input-error :messages="$errors->get('formEntrySBAR.tglSBAR')" class="mt-1" />
                    </div>
                    <x-now-button wire:click="setTglSBAR" />
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @foreach ([
                        ['situation', 'S — Situation *', "Pasien & masalah utama + TTV. Cth: 'Tn ... keluhan ..., TD .../..., SpO2 ...%'"],
                        ['background', 'B — Background *', "Diagnosis/alasan masuk, riwayat, alergi. Cth: 'Diagnosis ..., riwayat ..., alergi ...'"],
                        ['assessment', 'A — Assessment *', "Penilaian klinis & keparahan. Cth: 'Khawatir terjadi ...'"],
                        ['recommendation', 'R — Recommendation *', "Tindakan diminta + batas waktu. Cth: 'Mohon visit/order ... cito'"],
                    ] as [$key, $label, $hint])
                        <div>
                            <x-input-label value="{{ $label }}" />
                            <x-textarea wire:model="formEntrySBAR.sbar.{{ $key }}" class="w-full mt-1"
                                rows="4" :error="$errors->has('formEntrySBAR.sbar.' . $key)" placeholder="{{ $hint }}" />
                            <x-input-error :messages="$errors->get('formEntrySBAR.sbar.' . $key)" class="mt-1" />
                        </div>
                    @endforeach
                </div>

            </div>
        @endif

        {{-- RIWAYAT SBAR --}}
        <x-border-form title="Riwayat SBAR" align="start" bgcolor="bg-gray-50">
            <div class="mt-2">

                {{-- Tab Profesi (sticky atas agar mudah ganti tab saat data banyak) --}}
                <div class="sticky top-0 z-20 -mx-4 -mt-2 px-4 pt-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 mb-3">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium">
                        @foreach ($professionTabs as $prof)
                            @php
                                $count = $this->getSbarCount($prof);
                                $isActive = $activeProfession === $prof;
                                // Warna per profesi (selaras badge kartu). Class ditulis literal agar tidak ke-purge Tailwind.
                                $tab = match ($prof) {
                                    'Dokter' => [
                                        'active' => 'text-blue-700 border-blue-500 bg-blue-50 dark:text-blue-300 dark:border-blue-400 dark:bg-blue-900/20',
                                        'inactive' => 'text-blue-500/80 border-transparent hover:text-blue-700 hover:bg-blue-50/60 hover:border-blue-300 dark:text-blue-400/70',
                                        'badgeOn' => 'bg-blue-600 text-white',
                                        'badgeOff' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                    ],
                                    'Perawat' => [
                                        'active' => 'text-green-700 border-green-500 bg-green-50 dark:text-green-300 dark:border-green-400 dark:bg-green-900/20',
                                        'inactive' => 'text-green-600/80 border-transparent hover:text-green-700 hover:bg-green-50/60 hover:border-green-300 dark:text-green-400/70',
                                        'badgeOn' => 'bg-green-600 text-white',
                                        'badgeOff' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                    ],
                                    'Apoteker' => [
                                        'active' => 'text-rose-700 border-rose-500 bg-rose-50 dark:text-rose-300 dark:border-rose-400 dark:bg-rose-900/20',
                                        'inactive' => 'text-rose-500/80 border-transparent hover:text-rose-700 hover:bg-rose-50/60 hover:border-rose-300 dark:text-rose-400/70',
                                        'badgeOn' => 'bg-rose-600 text-white',
                                        'badgeOff' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                                    ],
                                    'Gizi' => [
                                        'active' => 'text-orange-700 border-orange-500 bg-orange-50 dark:text-orange-300 dark:border-orange-400 dark:bg-orange-900/20',
                                        'inactive' => 'text-orange-500/80 border-transparent hover:text-orange-700 hover:bg-orange-50/60 hover:border-orange-300 dark:text-orange-400/70',
                                        'badgeOn' => 'bg-orange-600 text-white',
                                        'badgeOff' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                                    ],
                                    'Penunjang' => [
                                        'active' => 'text-cyan-700 border-cyan-500 bg-cyan-50 dark:text-cyan-300 dark:border-cyan-400 dark:bg-cyan-900/20',
                                        'inactive' => 'text-cyan-600/80 border-transparent hover:text-cyan-700 hover:bg-cyan-50/60 hover:border-cyan-300 dark:text-cyan-400/70',
                                        'badgeOn' => 'bg-cyan-600 text-white',
                                        'badgeOff' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
                                    ],
                                    default => [
                                        'active' => 'text-slate-700 border-slate-500 bg-slate-100 dark:text-slate-200 dark:border-slate-400 dark:bg-slate-700/40',
                                        'inactive' => 'text-slate-500 border-transparent hover:text-slate-700 hover:bg-slate-50 hover:border-slate-300 dark:text-slate-400',
                                        'badgeOn' => 'bg-slate-600 text-white',
                                        'badgeOff' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                                    ],
                                };
                            @endphp
                            <li class="mr-0.5">
                                <button type="button" wire:click="$set('activeProfession', '{{ $prof }}')"
                                    class="inline-flex items-center gap-1.5 px-4 py-3 border-b-2 rounded-t-lg transition-colors
                                        {{ $isActive ? $tab['active'] : $tab['inactive'] }}">
                                    {{ $prof }}
                                    @if ($count > 0)
                                        <span
                                            class="inline-flex items-center justify-center w-4 h-4 text-sm font-bold rounded-full
                                            {{ $isActive ? $tab['badgeOn'] : $tab['badgeOff'] }}">
                                            {{ $count }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- List SBAR --}}
                <div class="space-y-3">
                    @php
                        // Urut tanggal SBAR desc (terbaru di atas) untuk semua tab profesi.
                        $allSbar = collect($dataDaftarRi['sbar'] ?? [])
                            ->sortByDesc(fn($c) => Carbon::createFromFormat('d/m/Y H:i:s', ($c['tglSBAR'] ?? '') ?: '01/01/2000 00:00:00')->timestamp)
                            ->values()
                            ->all();
                        $filtered =
                            $activeProfession === 'Semua'
                                ? $allSbar
                                : array_values(
                                    array_filter($allSbar, fn($c) => ($c['profession'] ?? '') === $activeProfession),
                                );

                        // DPJP Utama (leveling Pengkajian Awal). Hanya dia yang boleh review/TTD SBAR.
                        $dpjpUtamaRow = collect($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])
                            ->first(fn($r) => strcasecmp((string) ($r['levelDokter'] ?? ''), 'Utama') === 0);
                        $dpjpUtamaId = (string) ($dpjpUtamaRow['drId'] ?? '');
                        $isDpjpUtama = $dpjpUtamaId !== '' && $dpjpUtamaId === auth()->user()->myuser_code;
                        // DPJP Utama atau Admin boleh review; tetap perlu DPJP Utama terdefinisi (atribusi atas nama DPJP).
                        $canReviewDpjp = $dpjpUtamaId !== '' && ($isDpjpUtama || auth()->user()->hasRole('Admin'));
                    @endphp

                    @forelse ($filtered as $idx => $sbar)
                        <div wire:key="sbar-{{ $sbar['sbarId'] ?? $idx }}-{{ $this->renderKey('modal-sbar-ri') }}"
                            class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">

                            <div
                                class="flex items-center justify-between px-4 py-2.5
                    bg-gray-50 dark:bg-gray-700/60 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2 text-sm">
                                    @php
                                        $profColor = match ($sbar['profession'] ?? '') {
                                            'Dokter' => 'bg-blue-100 text-blue-700',
                                            'Perawat' => 'bg-green-100 text-green-700',
                                            'Apoteker' => 'bg-rose-100 text-rose-700',
                                            'Gizi' => 'bg-orange-100 text-orange-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-sm font-bold {{ $profColor }}">
                                        {{ $sbar['profession'] ?? '-' }}
                                    </span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">
                                        {{ $sbar['petugasSBAR'] ?? '-' }}
                                    </span>
                                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ $sbar['tglSBAR'] ?? '-' }}</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if (!empty($sbar['reviewDpjp']['drName']))
                                        <div class="flex flex-col items-end leading-tight">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                title="Direview DPJP Utama: {{ $sbar['reviewDpjp']['drName'] }} — {{ $sbar['reviewDpjp']['tglReview'] ?? '' }}">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Sudah direview oleh {{ $sbar['reviewDpjp']['drName'] }}
                                            </span>
                                            @if (!empty($sbar['reviewDpjp']['tglReview']))
                                                <span class="text-sm font-mono text-gray-400 dark:text-gray-500 mt-0.5">{{ $sbar['reviewDpjp']['tglReview'] }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    @unless (auth()->user()->hasRole('Dokter'))
                                    <x-outline-button type="button"
                                        wire:click="printSbar('{{ $sbar['sbarId'] }}')"
                                        wire:loading.attr="disabled"
                                        class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300"
                                        title="Cetak SBAR">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </x-outline-button>
                                    @endunless

                                @if (!$isFormLocked)
                                    @php
                                        $isAdmin = auth()->user()->hasRole('Admin');
                                        $myRole = auth()->user()->roles->first()->name ?? '';
                                        $sbarRole = $sbar['profession'] ?? '';
                                        // Hapus SBAR: hanya level Supervisor (2) ke atas — fungsional (Dokter/Perawat dll) tidak bisa, walau pemilik entri
                                        $canDelete = auth()->user()->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis', 'Supervisor Penunjang', 'Supervisor Tu', 'Mr', 'Casemix']);
                                        $canCopy = $isAdmin || $myRole === $sbarRole;
                                    @endphp
                                    <div class="flex gap-1.5">
                                        @if ($canReviewDpjp)
                                            @if (empty($sbar['reviewDpjp']['drName']))
                                            <x-outline-button type="button"
                                                wire:click="reviewSbarDpjp('{{ $sbar['sbarId'] }}')"
                                                wire:confirm="Review & TTD SBAR ini sebagai DPJP Utama?"
                                                wire:loading.attr="disabled"
                                                class="!text-emerald-600 !bg-emerald-50 !border-emerald-200 hover:!bg-emerald-100 hover:!text-emerald-700 hover:!border-emerald-300 dark:!text-emerald-400 dark:!bg-emerald-900/20 dark:!border-emerald-800/30 dark:hover:!bg-emerald-900/30 dark:hover:!text-emerald-300"
                                                title="Review / TTD DPJP Utama">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span class="text-sm font-semibold">Review DPJP</span>
                                                </span>
                                            </x-outline-button>
                                            @else
                                            <x-outline-button type="button"
                                                wire:click="batalReviewSbarDpjp('{{ $sbar['sbarId'] }}')"
                                                wire:confirm="Batalkan review DPJP pada SBAR ini?"
                                                wire:loading.attr="disabled"
                                                class="!text-gray-500 !bg-gray-50 !border-gray-200 hover:!bg-gray-100 hover:!text-gray-700 hover:!border-gray-300 dark:!text-gray-400 dark:!bg-gray-800/40 dark:!border-gray-700 dark:hover:!bg-gray-800/60"
                                                title="Batal review DPJP">
                                                <span class="inline-flex items-center gap-1">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                    <span class="text-sm font-semibold">Batal Review</span>
                                                </span>
                                            </x-outline-button>
                                            @endif
                                        @endif
                                        @if ($canCopy)
                                        <x-outline-button type="button"
                                            wire:click="copySBAR('{{ $sbar['sbarId'] }}')"
                                            wire:loading.attr="disabled"
                                            class="!text-blue-600 !bg-blue-50 !border-blue-200 hover:!bg-blue-100 hover:!text-blue-700 hover:!border-blue-300 dark:!text-blue-400 dark:!bg-blue-900/20 dark:!border-blue-800/30 dark:hover:!bg-blue-900/30 dark:hover:!text-blue-300"
                                            title="Copy ke form">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </x-outline-button>
                                        @endif
                                        @if ($canDelete)
                                        <x-outline-button type="button"
                                            wire:click="removeSBAR('{{ $sbar['sbarId'] }}')"
                                            wire:confirm="Yakin hapus SBAR ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                        @endif
                                    </div>
                                @endif
                                </div>
                            </div>

                            <div class="px-4 py-3 space-y-2 text-sm">
                                <div class="grid grid-cols-2 gap-3">
                                    @php
                                        $sbarStyles = [
                                            'situation'      => ['lbl' => 'S', 'name' => 'Situation',      'wrap' => 'border-l-4 border-blue-500 bg-blue-50/40 dark:bg-blue-900/10', 'text' => 'text-blue-700 dark:text-blue-400'],
                                            'background'     => ['lbl' => 'B', 'name' => 'Background',     'wrap' => 'border-l-4 border-emerald-500 bg-emerald-50/40 dark:bg-emerald-900/10', 'text' => 'text-emerald-700 dark:text-emerald-400'],
                                            'assessment'     => ['lbl' => 'A', 'name' => 'Assessment',     'wrap' => 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-900/10', 'text' => 'text-amber-700 dark:text-amber-400'],
                                            'recommendation' => ['lbl' => 'R', 'name' => 'Recommendation', 'wrap' => 'border-l-4 border-rose-500 bg-rose-50/40 dark:bg-rose-900/10', 'text' => 'text-rose-700 dark:text-rose-400'],
                                        ];
                                    @endphp
                                    @foreach ($sbarStyles as $k => $s)
                                        <div class="{{ $s['wrap'] }} pl-3 py-1 rounded-r-md">
                                            <span class="font-bold {{ $s['text'] }}">{{ $s['lbl'] }}</span>
                                            <span class="text-gray-500"> — {{ $s['name'] }}</span>
                                            <p class="mt-0.5 text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ trim($sbar['sbar'][$k] ?? '') ?: '-' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    @empty
                        <p wire:key="sbar-empty-{{ $activeProfession }}-{{ $this->renderKey('modal-sbar-ri') }}"
                            class="text-sm text-center text-gray-400 py-6">
                            @if ($activeProfession === 'Semua')
                                Belum ada SBAR.
                            @else
                                Belum ada SBAR dari <strong>{{ $activeProfession }}</strong>.
                            @endif
                        </p>
                    @endforelse

                </div>

            </div>
        </x-border-form>

    </div>{{-- end sbar content --}}

</div>
