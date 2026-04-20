<?php
// resources/views/pages/transaksi/ri/emr-ri/cppt-ri/rm-cppt-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public string $activeProfession = 'Semua';
    public array $professionTabs = ['Semua', 'Dokter', 'Perawat', 'Apoteker', 'Gizi', 'Penunjang', 'MPP'];

    public array $formEntryCPPT = [
        'tglCPPT' => '',
        'petugasCPPT' => '',
        'petugasCPPTCode' => '',
        'profession' => '',
        'soap' => ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
        'instruction' => '',
        'review' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-cppt-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-cppt-ri']);
    }

    #[On('open-rm-cppt-ri')]
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
        $this->dataDaftarRi['cppt'] ??= [];

        if (
            auth()
                ->user()
                ->hasAnyRole(['Dokter', 'Admin'])
        ) {
            $diagnosaAwal = trim((string) data_get($this->dataDaftarRi, 'pengkajianDokter.diagnosaAssesment.diagnosaAwal', ''));
            if ($diagnosaAwal !== '' && empty($this->formEntryCPPT['soap']['assessment'])) {
                $this->formEntryCPPT['soap']['assessment'] = $diagnosaAwal;
            }
        }

        $role = auth()->user()->roles->first()->name ?? '';
        $this->activeProfession = match (true) {
            in_array($role, ['Dokter']) => 'Dokter',
            in_array($role, ['Perawat']) => 'Perawat',
            in_array($role, ['Apoteker']) => 'Apoteker',
            in_array($role, ['Gizi']) => 'Gizi',
            default => 'Semua',
        };

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-cppt-ri');
    }

    public function setTglCPPT(): void
    {
        $this->formEntryCPPT['tglCPPT'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-cppt-ri');
    }

    public function addCPPT(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryCPPT['petugasCPPT'] = auth()->user()->myuser_name;
        $this->formEntryCPPT['petugasCPPTCode'] = auth()->user()->myuser_code;
        $this->formEntryCPPT['profession'] = auth()->user()->roles->first()->name ?? '';

        $this->validate(
            [
                'formEntryCPPT.tglCPPT' => 'required|date_format:d/m/Y H:i:s',
                'formEntryCPPT.soap.subjective' => 'required|string|max:2000',
                'formEntryCPPT.soap.objective' => 'required|string|max:2000',
                'formEntryCPPT.soap.assessment' => 'required|string|max:2000',
                'formEntryCPPT.soap.plan' => 'required|string|max:2000',
            ],
            [
                'formEntryCPPT.tglCPPT.required' => 'Tanggal CPPT wajib diisi.',
                'formEntryCPPT.soap.subjective.required' => 'Subjective (S) wajib diisi.',
                'formEntryCPPT.soap.objective.required' => 'Objective (O) wajib diisi.',
                'formEntryCPPT.soap.assessment.required' => 'Assessment (A) wajib diisi.',
                'formEntryCPPT.soap.plan.required' => 'Plan (P) wajib diisi.',
            ],
        );

        $fingerprint = md5(json_encode([$this->formEntryCPPT['tglCPPT'], $this->formEntryCPPT['soap']], JSON_UNESCAPED_UNICODE));

        try {
            $inserted = false;

            DB::transaction(function () use ($fingerprint, &$inserted) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['cppt'] ??= [];

                if (collect($fresh['cppt'])->first(fn($r) => ($r['fingerprint'] ?? null) === $fingerprint)) {
                    $this->dispatch('toast', type: 'info', message: 'CPPT yang sama sudah tersimpan.');
                    return;
                }

                $fresh['cppt'][] = array_merge($this->formEntryCPPT, [
                    'cpptId' => (string) Str::uuid(),
                    'fingerprint' => $fingerprint,
                ]);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $inserted = true;
            });

            if ($inserted) {
                $this->reset(['formEntryCPPT']);
                $this->afterSave('CPPT berhasil ditambahkan.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeCPPT(string $cpptId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Hanya bisa hapus milik sendiri, kecuali Admin
        $cppt = collect($this->dataDaftarRi['cppt'] ?? [])->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
        if ($cppt && !auth()->user()->hasRole('Admin')) {
            if (($cppt['petugasCPPTCode'] ?? '') !== auth()->user()->myuser_code) {
                $this->dispatch('toast', type: 'error', message: 'Hanya bisa menghapus CPPT yang Anda tulis sendiri.');
                return;
            }
        }

        try {
            DB::transaction(function () use ($cpptId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $cpptId);

                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }

                // Ambil fingerprint CPPT sebelum dihapus — untuk sync ke askep
                $cpptRow = $cppts->get($idx);
                $fingerprint = $cpptRow['fingerprint'] ?? null;

                $cppts->forget($idx);
                $fresh['cppt'] = $cppts->values()->all();

                // Sync bidirectional: hapus juga askep.implementasi dengan fingerprint sama
                if ($fingerprint && !empty($fresh['asuhanKeperawatan'])) {
                    foreach ($fresh['asuhanKeperawatan'] as $aIdx => $askep) {
                        if (empty($askep['implementasi'])) {
                            continue;
                        }
                        $fresh['asuhanKeperawatan'][$aIdx]['implementasi'] = array_values(array_filter(
                            $askep['implementasi'],
                            fn($im) => ($im['fingerprint'] ?? null) !== $fingerprint,
                        ));
                    }
                }

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('CPPT berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Buka E-Resep dari dalam CPPT ── */
    public function openEresep(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RI tidak ditemukan.');
            return;
        }
        $this->dispatch('emr-ri.eresep.open', riHdrNo: (int) $this->riHdrNo);
    }

    /* ── Auto-isi Plan dari Simpan CPPT di E-Resep ── */
    #[On('syncronizeCpptPlan')]
    public function onSyncronizeCpptPlan(string $text): void
    {
        $existing = trim($this->formEntryCPPT['soap']['plan'] ?? '');
        $this->formEntryCPPT['soap']['plan'] = $existing !== '' ? $existing . PHP_EOL . $text : $text;
        $this->incrementVersion('modal-cppt-ri');
    }

    public function copyCPPT(string $cpptId): void
    {
        $cppt = collect($this->dataDaftarRi['cppt'] ?? [])->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);

        if (!$cppt) {
            $this->dispatch('toast', type: 'error', message: 'CPPT tidak ditemukan.');
            return;
        }

        // Copy hanya bisa dilakukan oleh role yang sama, kecuali Admin
        if (!auth()->user()->hasRole('Admin')) {
            $myRole = auth()->user()->roles->first()->name ?? '';
            $cpptRole = $cppt['profession'] ?? '';
            if ($myRole !== $cpptRole) {
                $this->dispatch('toast', type: 'error', message: 'Hanya bisa copy CPPT dari profesi yang sama.');
                return;
            }
        }

        $this->formEntryCPPT = array_merge($this->formEntryCPPT, [
            'tglCPPT' => '',
            'petugasCPPT' => '',
            'petugasCPPTCode' => '',
            'profession' => '',
            'soap' => $cppt['soap'] ?? ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
            'instruction' => $cppt['instruction'] ?? '',
            'review' => $cppt['review'] ?? '',
        ]);

        $this->incrementVersion('modal-cppt-ri');
        $this->dispatch('toast', type: 'success', message: 'CPPT dicopy ke form.');
    }

    public function getCpptCount(string $profession): int
    {
        $list = $this->dataDaftarRi['cppt'] ?? [];
        if ($profession === 'Semua') {
            return count($list);
        }
        return collect($list)->where('profession', $profession)->count();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-cppt-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
        // Beritahu sibling (Askep) untuk reload — sync bidirectional UI
        $this->dispatch('rm-ri.cppt.changed');
    }

    #[On('rm-ri.askep.changed')]
    public function reloadFromAskepChange(): void
    {
        if (!$this->riHdrNo) {
            return;
        }
        $fresh = $this->findDataRI($this->riHdrNo);
        if ($fresh) {
            $this->dataDaftarRi = $fresh;
            $this->incrementVersion('modal-cppt-ri');
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->activeProfession = 'Semua';
        $this->reset(['formEntryCPPT']);
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-cppt-ri', [$riHdrNo ?? 'new']) }}" x-data="{ activeTab: 'cppt' }">

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

    {{-- ── TAB NAV ── --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-gray-500 dark:text-gray-400">
            <li class="mr-2">
                <button type="button" @click="activeTab = 'cppt'"
                    :class="activeTab === 'cppt'
                        ?
                        'text-brand border-brand bg-brand/5 font-semibold' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    CPPT
                    @php $totalCppt = $this->getCpptCount('Semua'); @endphp
                    @if ($totalCppt > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-sm font-bold bg-brand text-white">
                            {{ $totalCppt }}
                        </span>
                    @endif
                </button>
            </li>
            @hasanyrole('Perawat|Admin|MPP')
                <li class="mr-2">
                    <button type="button" @click="activeTab = 'caseManager'"
                        :class="activeTab === 'caseManager'
                            ?
                            'text-brand border-brand bg-brand/5 font-semibold' :
                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Case Manager (MPP)
                    </button>
                </li>
            @endhasanyrole
        </ul>
    </div>

    {{-- ── TAB: CPPT ── --}}
    <div x-show="activeTab === 'cppt'" x-transition.opacity.duration.200ms class="space-y-4">

        {{-- FORM ENTRY --}}
        @if (!$isFormLocked)
            <x-border-form title="Entry CPPT" align="start" bgcolor="bg-gray-50">
                <div class="mt-3 space-y-3">

                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <x-input-label value="Tanggal CPPT *" />
                            <x-text-input wire:model="formEntryCPPT.tglCPPT" class="w-full mt-1 font-mono"
                                placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('formEntryCPPT.tglCPPT')" />
                            <x-input-error :messages="$errors->get('formEntryCPPT.tglCPPT')" class="mt-1" />
                        </div>
                        <x-secondary-button wire:click="setTglCPPT" type="button">Sekarang</x-secondary-button>
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        @foreach ([['subjective', 'S — Subjective *'], ['objective', 'O — Objective *'], ['assessment', 'A — Assessment *'], ['plan', 'P — Plan *']] as [$key, $label])
                            <div>
                                <x-input-label value="{{ $label }}" />
                                <x-textarea wire:model="formEntryCPPT.soap.{{ $key }}" class="w-full mt-1"
                                    rows="3" :error="$errors->has('formEntryCPPT.soap.' . $key)" placeholder="{{ $label }}..." />
                                <x-input-error :messages="$errors->get('formEntryCPPT.soap.' . $key)" class="mt-1" />
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label value="Instruksi" />
                            <x-textarea wire:model="formEntryCPPT.instruction" class="w-full mt-1" rows="2" />
                        </div>
                        <div>
                            <x-input-label value="Review" />
                            <x-textarea wire:model="formEntryCPPT.review" class="w-full mt-1" rows="2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        {{-- Tombol buka E-Resep (plan bisa di-autofill dari E-Resep) --}}
                        @role(['Dokter', 'Admin'])
                            <x-secondary-button wire:click="openEresep" type="button"
                                title="Buka E-Resep — klik Simpan ke CPPT di E-Resep untuk auto-isi field Plan">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                E-Resep
                            </x-secondary-button>
                        @endrole

                        <x-primary-button wire:click="addCPPT" type="button">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah CPPT
                        </x-primary-button>
                    </div>

                </div>
            </x-border-form>
        @endif

        {{-- RIWAYAT CPPT --}}
        <x-border-form title="Riwayat CPPT" align="start" bgcolor="bg-gray-50">
            <div class="mt-2">

                {{-- Tab Profesi --}}
                <div class="border-b border-gray-200 dark:border-gray-700 mb-3">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium">
                        @foreach ($professionTabs as $prof)
                            @php $count = $this->getCpptCount($prof); @endphp
                            <li class="mr-0.5">
                                <button type="button" wire:click="$set('activeProfession', '{{ $prof }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 border-b-2 rounded-t-lg transition-colors
                                        {{ $activeProfession === $prof
                                            ? 'text-brand border-brand bg-brand/5 font-semibold'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    {{ $prof }}
                                    @if ($count > 0)
                                        <span
                                            class="inline-flex items-center justify-center w-4 h-4 text-sm font-bold rounded-full
                                            {{ $activeProfession === $prof ? 'bg-brand text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $count }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- List CPPT --}}
                <div class="space-y-3">
                    @php
                        $allCppt = array_reverse($dataDaftarRi['cppt'] ?? []);
                        $filtered =
                            $activeProfession === 'Semua'
                                ? $allCppt
                                : array_values(
                                    array_filter($allCppt, fn($c) => ($c['profession'] ?? '') === $activeProfession),
                                );
                    @endphp

                    @if ($activeProfession === 'MPP')
                        {{-- ── TAMPILAN KHUSUS TAB MPP ── --}}
                        @hasanyrole('Perawat|Admin|MPP')
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                    <thead class="bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300">
                                        <tr>
                                            <th class="px-3 py-2">Tgl / Petugas</th>
                                            <th class="px-3 py-2">Profesi</th>
                                            <th class="px-3 py-2">Pelaksanaan / Monitoring</th>
                                            <th class="px-3 py-2">Advokasi / Kolaborasi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @forelse ($allCppt as $idx => $cppt)
                                            @php $mpp = $cppt['mpp'] ?? null; @endphp
                                            @if ($mpp)
                                                <tr wire:key="mpp-row-{{ $cppt['cpptId'] ?? $idx }}"
                                                    class="bg-white dark:bg-gray-800 hover:bg-purple-50/50">
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        <div class="font-mono">{{ $cppt['tglCPPT'] ?? '-' }}</div>
                                                        <div class="text-gray-600 dark:text-gray-300">{{ $cppt['petugasCPPT'] ?? '-' }}</div>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        @php
                                                            $profColor = match ($cppt['profession'] ?? '') {
                                                                'Dokter' => 'bg-blue-100 text-blue-700',
                                                                'Perawat' => 'bg-green-100 text-green-700',
                                                                'Apoteker' => 'bg-purple-100 text-purple-700',
                                                                'Gizi' => 'bg-orange-100 text-orange-700',
                                                                default => 'bg-gray-100 text-gray-600',
                                                            };
                                                        @endphp
                                                        <span
                                                            class="px-2 py-0.5 rounded-full text-sm font-bold {{ $profColor }}">
                                                            {{ $cppt['profession'] ?? '-' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 max-w-xs">
                                                        <p class="whitespace-pre-wrap">{{ $mpp['pelaksanaan'] ?? '-' }}
                                                        </p>
                                                    </td>
                                                    <td class="px-3 py-2 max-w-xs">
                                                        <p class="whitespace-pre-wrap">{{ $mpp['advokasi'] ?? '-' }}</p>
                                                    </td>
                                                </tr>
                                            @endif
                                        @empty
                                        @endforelse
                                        @if (collect($allCppt)->filter(fn($c) => isset($c['mpp']))->isEmpty())
                                            <tr>
                                                <td colspan="4" class="px-3 py-6 text-center text-gray-400">
                                                    Belum ada catatan MPP.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-center text-gray-400 py-6">Akses terbatas.</p>
                        @endhasanyrole
                    @else
                        {{-- ── TAMPILAN NORMAL (tab selain MPP) ── --}}
                        @forelse ($filtered as $idx => $cppt)
                            <div wire:key="cppt-{{ $cppt['cpptId'] ?? $idx }}-{{ $this->renderKey('modal-cppt-ri') }}"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">

                                <div
                                    class="flex items-center justify-between px-4 py-2.5
                        bg-gray-50 dark:bg-gray-700/60 border-b border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center gap-2 text-sm">
                                        @php
                                            $profColor = match ($cppt['profession'] ?? '') {
                                                'Dokter' => 'bg-blue-100 text-blue-700',
                                                'Perawat' => 'bg-green-100 text-green-700',
                                                'Apoteker' => 'bg-purple-100 text-purple-700',
                                                'Gizi' => 'bg-orange-100 text-orange-700',
                                                default => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-sm font-bold {{ $profColor }}">
                                            {{ $cppt['profession'] ?? '-' }}
                                        </span>
                                        <span class="font-semibold text-gray-700 dark:text-gray-200">
                                            {{ $cppt['petugasCPPT'] ?? '-' }}
                                        </span>
                                        <span class="font-mono text-gray-600 dark:text-gray-300">{{ $cppt['tglCPPT'] ?? '-' }}</span>
                                    </div>

                                    @if (!$isFormLocked)
                                        @php
                                            $isAdmin = auth()->user()->hasRole('Admin');
                                            $myCode = auth()->user()->myuser_code;
                                            $myRole = auth()->user()->roles->first()->name ?? '';
                                            $cpptOwnerCode = $cppt['petugasCPPTCode'] ?? '';
                                            $cpptRole = $cppt['profession'] ?? '';
                                            $canDelete = $isAdmin || $cpptOwnerCode === $myCode;
                                            $canCopy = $isAdmin || $myRole === $cpptRole;
                                        @endphp
                                        <div class="flex gap-1">
                                            @if ($canCopy)
                                            <x-icon-button color="blue"
                                                wire:click="copyCPPT('{{ $cppt['cpptId'] }}')" title="Copy ke form">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </x-icon-button>
                                            @endif
                                            @if ($canDelete)
                                            <x-icon-button color="red"
                                                wire:click="removeCPPT('{{ $cppt['cpptId'] }}')"
                                                wire:confirm="Yakin hapus CPPT ini?" title="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="px-4 py-3 space-y-2 text-sm">
                                    {{-- Badge askep (auto-sync dari asuhan keperawatan) --}}
                                    @if (!empty($cppt['askepDiagKepId']))
                                        <div
                                            class="flex flex-wrap items-center gap-2 p-2 rounded-lg bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800">
                                            <span
                                                class="px-1.5 py-0.5 font-mono text-sm rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">
                                                {{ $cppt['askepDiagKepId'] }}
                                            </span>
                                            <span
                                                class="font-semibold text-green-800 dark:text-green-300">{{ $cppt['askepDiagKepDesc'] ?? '' }}</span>
                                            @php
                                                $skor = $cppt['skorEvaluasi'] ?? null;
                                                $avgSkor = null;
                                                if (is_array($skor) && count($skor) > 0) {
                                                    $nums = array_filter(array_map('intval', $skor), fn($n) => $n > 0);
                                                    $avgSkor = count($nums) ? round(array_sum($nums) / count($nums), 1) : null;
                                                } elseif (!empty($skor) && !is_array($skor)) {
                                                    $avgSkor = (int) $skor;
                                                }
                                            @endphp
                                            @if ($avgSkor !== null)
                                                <span
                                                    class="ml-auto px-2 py-0.5 rounded-full text-sm font-bold
                                                    {{ $avgSkor >= 4 ? 'bg-green-600 text-white' : ($avgSkor >= 3 ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white') }}">
                                                    {{ is_array($skor) ? 'Avg Skor' : 'Skor' }}: {{ $avgSkor }}/5
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Detail skor per kriteria (data baru) --}}
                                        @if (is_array($cppt['skorEvaluasi'] ?? null) && count($cppt['skorEvaluasi']) > 0)
                                            @php $kriteriaList = $cppt['kriteriaHasilDipilih'] ?? []; @endphp
                                            <div class="mt-1 grid grid-cols-1 gap-0.5 text-xs">
                                                @foreach ($cppt['skorEvaluasi'] as $kIdx => $sk)
                                                    @php
                                                        $kText = $kriteriaList[$kIdx] ?? "Kriteria #" . ($kIdx + 1);
                                                        $skInt = (int) $sk;
                                                        $color = $skInt >= 4 ? 'text-green-600 dark:text-green-400' : ($skInt >= 3 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                                    @endphp
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="flex-1 text-gray-600 dark:text-gray-400">{{ $kText }}</span>
                                                        <span
                                                            class="font-mono font-bold {{ $color }}">{{ $sk }}/5</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif

                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                        @foreach ([['S', 'subjective'], ['O', 'objective'], ['A', 'assessment'], ['P', 'plan']] as [$lbl, $k])
                                            <div>
                                                <span class="font-bold text-brand">{{ $lbl }}</span>
                                                <span class="text-gray-500"> —
                                                    {{ match ($k) {
                                                        'subjective' => 'Subjective',
                                                        'objective' => 'Objective',
                                                        'assessment' => 'Assessment',
                                                        'plan' => 'Plan',
                                                    } }}</span>
                                                <p
                                                    class="mt-0.5 text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
                                                    {{ $cppt['soap'][$k] ?? '-' }}
                                                </p>
                                            </div>
                                        @endforeach

                                        @if (!empty($cppt['instruction']))
                                            <div>
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300">Instruksi:</span>
                                                <p class="mt-0.5 text-gray-700 dark:text-gray-300">
                                                    {{ $cppt['instruction'] }}</p>
                                            </div>
                                        @endif
                                        @if (!empty($cppt['review']))
                                            <div>
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300">Review:</span>
                                                <p class="mt-0.5 text-gray-700 dark:text-gray-300">
                                                    {{ $cppt['review'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Tindakan SIKI (dari askep sync) --}}
                                    @if (!empty($cppt['tindakanDilakukan']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($cppt['tindakanDilakukan'] as $td)
                                                <span
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    {{ $td }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                            </div>
                        @empty
                            <p wire:key="cppt-empty-{{ $activeProfession }}-{{ $this->renderKey('modal-cppt-ri') }}"
                                class="text-sm text-center text-gray-400 py-6">
                                @if ($activeProfession === 'Semua')
                                    Belum ada CPPT.
                                @else
                                    Belum ada CPPT dari <strong>{{ $activeProfession }}</strong>.
                                @endif
                            </p>
                        @endforelse
                    @endif

                </div>

            </div>
        </x-border-form>

    </div>{{-- end tab cppt --}}

    {{-- ── TAB: CASE MANAGER ── --}}
    @hasanyrole('Perawat|Admin|MPP')
        <div x-show="activeTab === 'caseManager'" x-transition.opacity.duration.200ms style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.case-manager-ri.rm-case-manager-ri-actions :riHdrNo="$riHdrNo"
                wire:key="case-manager-cppt-{{ $riHdrNo }}" />
        </div>
    @endhasanyrole

</div>
