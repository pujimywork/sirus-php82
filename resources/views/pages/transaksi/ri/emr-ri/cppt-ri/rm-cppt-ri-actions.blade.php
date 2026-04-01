<?php
// resources/views/pages/transaksi/ri/emr-ri/cppt-ri/rm-cppt-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    /* Tab profesi aktif — default 'Semua' */
    public string $activeProfession = 'Semua';

    public array $professionTabs = ['Semua', 'Dokter', 'Perawat', 'Apoteker', 'Gizi', 'Penunjang'];

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

        /* Auto-fill assessment dari diagnosa awal (Dokter/Admin) */
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

        /* Set tab awal sesuai role user */
        $role = auth()->user()->roles->first()->name ?? '';
        $this->activeProfession = match (true) {
            in_array($role, ['Dokter']) => 'Dokter',
            in_array($role, ['Perawat']) => 'Perawat',
            in_array($role, ['Apoteker']) => 'Apoteker',
            in_array($role, ['Gizi']) => 'Gizi',
            default => 'Semua',
        };

        $this->incrementVersion('modal-cppt-ri');
        $riStatus = DB::scalar('select ri_status from rstxn_rihdrs where rihdr_no=:r', ['r' => $riHdrNo]);
        $this->isFormLocked = $riStatus !== 'I';
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
            $this->withRiLock(function () use ($fingerprint, &$inserted) {
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
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
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
        try {
            $this->withRiLock(function () use ($cpptId) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }
                $cppts->forget($idx);
                $fresh['cppt'] = $cppts->values()->all();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('CPPT berhasil dihapus.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function copyCPPT(string $cpptId): void
    {
        $cppt = collect($this->dataDaftarRi['cppt'] ?? [])->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
        if (!$cppt) {
            $this->dispatch('toast', type: 'error', message: 'CPPT tidak ditemukan.');
            return;
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

    /* Hitung jumlah CPPT per profesi — untuk badge di tab */
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
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->activeProfession = 'Semua';
        $this->reset(['formEntryCPPT']);
    }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo);
                $fn();
            }, 5);
        });
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-cppt-ri', [$riHdrNo ?? 'new']) }}">
    {{ $this->renderKey('modal-cppt-ri', [$riHdrNo ?? 'new']) }}

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

    {{-- ============================================================
    | FORM ENTRY
    ============================================================= --}}
    @if (!$isFormLocked)
        <x-border-form title="Entry CPPT" align="start" bgcolor="bg-gray-50">
            <div class="mt-3 space-y-3">

                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label value="Tanggal CPPT *" />

                        {{ $formEntryCPPT['tglCPPT'] ?? 'x' }}
                        <x-text-input wire:model="formEntryCPPT.tglCPPT" class="w-full mt-1 font-mono"
                            placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('formEntryCPPT.tglCPPT')" />
                        <x-input-error :messages="$errors->get('formEntryCPPT.tglCPPT')" class="mt-1" />
                    </div>
                    <x-secondary-button wire:click="setTglCPPT" type="button">Sekarang</x-secondary-button>
                </div>

                <div class="grid grid-cols-2 gap-3">
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

                <div class="flex justify-end">
                    <x-primary-button wire:click="addCPPT" type="button">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah CPPT
                    </x-primary-button>
                </div>

            </div>
        </x-border-form>
    @endif

    {{-- ============================================================
    | RIWAYAT CPPT — dengan tab profesi
    ============================================================= --}}
    <x-border-form title="Riwayat CPPT" align="start" bgcolor="bg-gray-50">
        <div class="mt-2">

            {{-- Tab Profesi --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-3">
                <ul class="flex flex-wrap -mb-px text-xs font-medium">
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
                                        class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full
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

                @forelse ($filtered as $idx => $cppt)
                    <div wire:key="cppt-{{ $cppt['cpptId'] ?? $idx }}-{{ $this->renderKey('modal-cppt-ri') }}"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">

                        {{-- Header --}}
                        <div
                            class="flex items-center justify-between px-4 py-2.5
                                bg-gray-50 dark:bg-gray-700/60
                                border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center gap-2 text-xs">
                                {{-- Badge profesi --}}
                                @php
                                    $profColor = match ($cppt['profession'] ?? '') {
                                        'Dokter' => 'bg-blue-100 text-blue-700',
                                        'Perawat' => 'bg-green-100 text-green-700',
                                        'Apoteker' => 'bg-purple-100 text-purple-700',
                                        'Gizi' => 'bg-orange-100 text-orange-700',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $profColor }}">
                                    {{ $cppt['profession'] ?? '-' }}
                                </span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $cppt['petugasCPPT'] ?? '-' }}
                                </span>
                                <span class="font-mono text-gray-400">
                                    {{ $cppt['tglCPPT'] ?? '-' }}
                                </span>
                            </div>

                            @if (!$isFormLocked)
                                <div class="flex gap-1">
                                    <x-icon-button variant="info" wire:click="copyCPPT('{{ $cppt['cpptId'] }}')"
                                        tooltip="Copy ke form">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </x-icon-button>
                                    <x-icon-button variant="danger" wire:click="removeCPPT('{{ $cppt['cpptId'] }}')"
                                        wire:confirm="Yakin hapus CPPT ini?" tooltip="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                             L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-icon-button>
                                </div>
                            @endif
                        </div>

                        {{-- Body SOAP --}}
                        <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                            @foreach ([['S', 'subjective'], ['O', 'objective'], ['A', 'assessment'], ['P', 'plan']] as [$lbl, $k])
                                <div>
                                    <span class="font-bold text-brand">{{ $lbl }}</span>
                                    <span class="text-gray-500"> —
                                        {{ match ($k) {'subjective' => 'Subjective','objective' => 'Objective','assessment' => 'Assessment','plan' => 'Plan'} }}</span>
                                    <p
                                        class="mt-0.5 text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
                                        {{ $cppt['soap'][$k] ?? '-' }}
                                    </p>
                                </div>
                            @endforeach

                            {{-- Instruksi & Review jika ada --}}
                            @if (!empty($cppt['instruction']))
                                <div>
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Instruksi:</span>
                                    <p class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $cppt['instruction'] }}</p>
                                </div>
                            @endif
                            @if (!empty($cppt['review']))
                                <div>
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Review:</span>
                                    <p class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $cppt['review'] }}</p>
                                </div>
                            @endif
                        </div>

                    </div>
                @empty
                    <p wire:key="cppt-empty-{{ $activeProfession }}-{{ $this->renderKey('modal-cppt-ri') }}"
                        class="text-xs text-center text-gray-400 py-6">
                        @if ($activeProfession === 'Semua')
                            Belum ada CPPT.
                        @else
                            Belum ada CPPT dari <strong>{{ $activeProfession }}</strong>.
                        @endif
                    </p>
                @endforelse
            </div>

        </div>
    </x-border-form>

</div>
