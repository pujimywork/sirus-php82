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

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $formEntryAsuhanKeperawatan = [
        'tglAsuhanKeperawatan' => '',
        'petugasAsuhanKeperawatan' => '',
        'petugasAsuhanKeperawatanCode' => '',
        'diagKepId' => '',
        'diagKepDesc' => '',
        'diagKepJson' => [],
        'perumusanDiagnosis' => [
            'penyebabDipilih' => [],
            'faktorResikoDipilih' => [],
            'tandaMayorSubjDipilih' => [],
            'tandaMayorObjDipilih' => [],
            'tandaMinorSubjDipilih' => [],
            'tandaMinorObjDipilih' => [],
            'rumusanDiagnosis' => '',
        ],
        'perencanaanLuaran' => [
            'kriteriaHasilDipilih' => [],
        ],
        'perencanaanIntervensi' => [
            'tindakanDipilih' => [],
        ],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-asuhan-keperawatan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-asuhan-keperawatan-ri']);
    }

    #[On('open-rm-asuhan-keperawatan-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
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
        $riStatus = DB::scalar('select ri_status from rstxn_rihdrs where rihdr_no = :r', ['r' => $riHdrNo]);
        $this->isFormLocked = $riStatus !== 'I';
    }

    #[On('lov.selected.riFormAsuhanKeperawatan')]
    public function onDiagKepSelected(string $target, ?array $payload): void
    {
        if (empty($payload)) {
            $this->formEntryAsuhanKeperawatan['diagKepId'] = '';
            $this->formEntryAsuhanKeperawatan['diagKepDesc'] = '';
            $this->formEntryAsuhanKeperawatan['diagKepJson'] = [];
            $this->resetPerumusan();
            return;
        }
        $this->formEntryAsuhanKeperawatan['diagKepId'] = $payload['diagkep_id'] ?? '';
        $this->formEntryAsuhanKeperawatan['diagKepDesc'] = $payload['diagkep_desc'] ?? '';
        $this->formEntryAsuhanKeperawatan['diagKepJson'] = $payload['diagkep_json'] ?? [];
        $this->resetPerumusan();
    }

    public function setTglAsuhanKeperawatan(): void
    {
        $this->formEntryAsuhanKeperawatan['tglAsuhanKeperawatan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ── Toggle helpers ── */
    public function togglePerumusan(string $field, string $value): void
    {
        $arr = $this->formEntryAsuhanKeperawatan['perumusanDiagnosis'][$field] ?? [];
        if (in_array($value, $arr, true)) {
            $arr = array_values(array_filter($arr, fn($v) => $v !== $value));
        } else {
            $arr[] = $value;
        }
        $this->formEntryAsuhanKeperawatan['perumusanDiagnosis'][$field] = $arr;
        $this->buildRumusanDiagnosis();
    }

    public function toggleKriteriaHasil(string $value): void
    {
        $arr = $this->formEntryAsuhanKeperawatan['perencanaanLuaran']['kriteriaHasilDipilih'] ?? [];
        if (in_array($value, $arr, true)) {
            $arr = array_values(array_filter($arr, fn($v) => $v !== $value));
        } else {
            $arr[] = $value;
        }
        $this->formEntryAsuhanKeperawatan['perencanaanLuaran']['kriteriaHasilDipilih'] = $arr;
    }

    public function toggleTindakan(string $value): void
    {
        $arr = $this->formEntryAsuhanKeperawatan['perencanaanIntervensi']['tindakanDipilih'] ?? [];
        if (in_array($value, $arr, true)) {
            $arr = array_values(array_filter($arr, fn($v) => $v !== $value));
        } else {
            $arr[] = $value;
        }
        $this->formEntryAsuhanKeperawatan['perencanaanIntervensi']['tindakanDipilih'] = $arr;
    }

    protected function buildRumusanDiagnosis(): void
    {
        $form = $this->formEntryAsuhanKeperawatan;
        $p = $form['perumusanDiagnosis'];
        $desc = $form['diagKepDesc'];
        $sdki = $form['diagKepJson']['sdki'] ?? [];

        $isRisiko = !empty($sdki['faktor_risiko']);
        $hasPenyebab = !empty($sdki['penyebab']);

        $parts = [$desc];

        if ($isRisiko) {
            $fr = $p['faktorResikoDipilih'] ?? [];
            if (!empty($fr)) {
                $parts[] = 'dibuktikan dengan ' . implode('; ', $fr);
            }
        } elseif ($hasPenyebab) {
            $penyebab = $p['penyebabDipilih'] ?? [];
            if (!empty($penyebab)) {
                $parts[] = 'berhubungan dengan ' . implode('; ', $penyebab);
            }
            $tandaGejala = array_merge($p['tandaMayorSubjDipilih'] ?? [], $p['tandaMayorObjDipilih'] ?? [], $p['tandaMinorSubjDipilih'] ?? [], $p['tandaMinorObjDipilih'] ?? []);
            if (!empty($tandaGejala)) {
                $parts[] = 'dibuktikan dengan ' . implode('; ', $tandaGejala);
            }
        } else {
            $tandaGejala = array_merge($p['tandaMayorSubjDipilih'] ?? [], $p['tandaMayorObjDipilih'] ?? []);
            if (!empty($tandaGejala)) {
                $parts[] = 'dibuktikan dengan ' . implode('; ', $tandaGejala);
            }
        }

        $this->formEntryAsuhanKeperawatan['perumusanDiagnosis']['rumusanDiagnosis'] = implode(' ', $parts);
    }

    public function addAsuhanKeperawatan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $this->formEntryAsuhanKeperawatan['petugasAsuhanKeperawatan'] = auth()->user()->myuser_name;
        $this->formEntryAsuhanKeperawatan['petugasAsuhanKeperawatanCode'] = auth()->user()->myuser_code;
        $this->buildRumusanDiagnosis();

        $this->validate(
            [
                'formEntryAsuhanKeperawatan.tglAsuhanKeperawatan' => 'required|date_format:d/m/Y H:i:s',
                'formEntryAsuhanKeperawatan.diagKepId' => 'required|string|exists:rsmst_diagkeperawatans,diagkep_id',
                'formEntryAsuhanKeperawatan.perumusanDiagnosis.rumusanDiagnosis' => 'required|string|min:5',
            ],
            [
                'formEntryAsuhanKeperawatan.tglAsuhanKeperawatan.required' => 'Tanggal wajib diisi.',
                'formEntryAsuhanKeperawatan.diagKepId.required' => 'Diagnosis Keperawatan wajib dipilih.',
                'formEntryAsuhanKeperawatan.perumusanDiagnosis.rumusanDiagnosis.required' => 'Pilih minimal satu penyebab/faktor risiko atau tanda gejala.',
            ],
        );

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

    /* ── IMPLEMENTASI (SOAP per diagnosis, disimpan di dalam askep) ── */

    public array $formImpl = [
        'tglImpl' => '',
        'soap' => ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
        'tindakanDilakukan' => [],
        'skorEvaluasi' => [],
    ];

    public ?int $activeImplIndex = null; // index askep yang sedang dibuka form implementasinya

    public function openFormImpl(int $askepIndex): void
    {
        $this->activeImplIndex = $askepIndex;
        $this->reset(['formImpl']);

        // Auto-fill assessment dengan nama diagnosis
        $askep = $this->dataDaftarRi['asuhanKeperawatan'][$askepIndex] ?? null;
        if ($askep) {
            $this->formImpl['soap']['assessment'] = $askep['diagKepDesc'] ?? '';
        }

        $this->incrementVersion('modal-asuhan-keperawatan-ri');
    }

    public function closeFormImpl(): void
    {
        $this->activeImplIndex = null;
        $this->reset(['formImpl']);
        $this->incrementVersion('modal-asuhan-keperawatan-ri');
    }

    public function setTglImpl(): void
    {
        $this->formImpl['tglImpl'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function toggleTindakanImpl(string $value): void
    {
        $arr = $this->formImpl['tindakanDilakukan'] ?? [];
        if (in_array($value, $arr, true)) {
            $arr = array_values(array_filter($arr, fn($v) => $v !== $value));
        } else {
            $arr[] = $value;
        }
        $this->formImpl['tindakanDilakukan'] = $arr;
    }

    public function addImplementasi(): void
    {
        if ($this->isFormLocked || $this->activeImplIndex === null) {
            return;
        }

        $this->validate(
            [
                'formImpl.tglImpl' => 'required|date_format:d/m/Y H:i:s',
                'formImpl.soap.subjective' => 'required|string|max:2000',
                'formImpl.soap.objective' => 'required|string|max:2000',
                'formImpl.soap.assessment' => 'required|string|max:2000',
                'formImpl.soap.plan' => 'required|string|max:2000',
            ],
            [
                'formImpl.tglImpl.required' => 'Tanggal wajib diisi.',
                'formImpl.soap.subjective.required' => 'Subjective (S) wajib diisi.',
                'formImpl.soap.objective.required' => 'Objective (O) wajib diisi.',
                'formImpl.soap.assessment.required' => 'Assessment (A) wajib diisi.',
                'formImpl.soap.plan.required' => 'Plan (P) wajib diisi.',
            ],
        );

        try {
            $idx = $this->activeImplIndex;
            $this->withRiLock(function () use ($idx) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                if (!isset($fresh['asuhanKeperawatan'][$idx])) {
                    throw new \RuntimeException('Asuhan Keperawatan tidak ditemukan.');
                }

                // Fingerprint stabil — dipakai untuk link bidirectional askep ↔ cppt
                $fingerprint = md5(json_encode([$this->formImpl['tglImpl'], $this->formImpl['soap']], JSON_UNESCAPED_UNICODE));

                $implEntry = array_merge($this->formImpl, [
                    'petugasImpl' => auth()->user()->myuser_name,
                    'petugasImplCode' => auth()->user()->myuser_code,
                    'fingerprint' => $fingerprint,
                ]);

                // 1. Simpan di dalam askep
                $fresh['asuhanKeperawatan'][$idx]['implementasi'] ??= [];
                $fresh['asuhanKeperawatan'][$idx]['implementasi'][] = $implEntry;

                // 2. Auto-sync ke CPPT supaya muncul di timeline semua profesi
                $askep = $fresh['asuhanKeperawatan'][$idx];
                $fresh['cppt'] ??= [];
                $fresh['cppt'][] = [
                    'cpptId' => (string) \Illuminate\Support\Str::uuid(),
                    'fingerprint' => $fingerprint,
                    'tglCPPT' => $implEntry['tglImpl'],
                    'petugasCPPT' => $implEntry['petugasImpl'],
                    'petugasCPPTCode' => $implEntry['petugasImplCode'],
                    'profession' => 'Perawat',
                    'soap' => $implEntry['soap'],
                    'instruction' => '',
                    'review' => '',
                    // Referensi askep untuk traceability
                    'askepDiagKepId' => $askep['diagKepId'] ?? '',
                    'askepDiagKepDesc' => $askep['diagKepDesc'] ?? '',
                    'tindakanDilakukan' => $implEntry['tindakanDilakukan'] ?? [],
                    'skorEvaluasi' => $implEntry['skorEvaluasi'] ?? [],
                    'kriteriaHasilDipilih' => $askep['perencanaanLuaran']['kriteriaHasilDipilih'] ?? [],
                ];

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formImpl']);
            $this->activeImplIndex = null;
            $this->afterSave('Implementasi & evaluasi berhasil ditambahkan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeImplementasi(int $askepIndex, int $implIndex): void
    {
        if ($this->isFormLocked) {
            return;
        }
        try {
            $this->withRiLock(function () use ($askepIndex, $implIndex) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                if (!isset($fresh['asuhanKeperawatan'][$askepIndex]['implementasi'][$implIndex])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }

                // Ambil fingerprint (atau fallback: fingerprint lama dari tglImpl+soap)
                $impl = $fresh['asuhanKeperawatan'][$askepIndex]['implementasi'][$implIndex];
                $fingerprint = $impl['fingerprint'] ?? md5(json_encode([$impl['tglImpl'] ?? '', $impl['soap'] ?? []], JSON_UNESCAPED_UNICODE));

                // 1. Hapus dari askep
                array_splice($fresh['asuhanKeperawatan'][$askepIndex]['implementasi'], $implIndex, 1);
                $fresh['asuhanKeperawatan'][$askepIndex]['implementasi'] = array_values($fresh['asuhanKeperawatan'][$askepIndex]['implementasi']);

                // 2. Hapus CPPT yang match fingerprint (sync bidirectional)
                if (!empty($fresh['cppt']) && $fingerprint) {
                    $fresh['cppt'] = array_values(array_filter(
                        $fresh['cppt'],
                        fn($c) => ($c['fingerprint'] ?? null) !== $fingerprint,
                    ));
                }

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Implementasi & CPPT terkait berhasil dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntryAsuhanKeperawatan']);
        $this->resetValidation();
        $this->dispatch('lov-asuhan-keperawatan.reset', target: 'riFormAsuhanKeperawatan');
    }

    protected function resetPerumusan(): void
    {
        $this->formEntryAsuhanKeperawatan['perumusanDiagnosis'] = [
            'penyebabDipilih' => [],
            'faktorResikoDipilih' => [],
            'tandaMayorSubjDipilih' => [],
            'tandaMayorObjDipilih' => [],
            'tandaMinorSubjDipilih' => [],
            'tandaMinorObjDipilih' => [],
            'rumusanDiagnosis' => '',
        ];
        $this->formEntryAsuhanKeperawatan['perencanaanLuaran'] = ['kriteriaHasilDipilih' => []];
        $this->formEntryAsuhanKeperawatan['perencanaanIntervensi'] = ['tindakanDipilih' => []];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-asuhan-keperawatan-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
        // Beritahu sibling (CPPT) untuk reload — sync bidirectional UI
        $this->dispatch('rm-ri.askep.changed');
    }

    #[On('rm-ri.cppt.changed')]
    public function reloadFromCpptChange(): void
    {
        if (!$this->riHdrNo) {
            return;
        }
        $fresh = $this->findDataRI($this->riHdrNo);
        if ($fresh) {
            $this->dataDaftarRi = $fresh;
            $this->incrementVersion('modal-asuhan-keperawatan-ri');
        }
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-asuhan-keperawatan-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ============================================================
    | PANDUAN PENGISIAN
    ============================================================= --}}
    <div class="p-3 border rounded-xl border-blue-200 bg-blue-50/50 dark:bg-blue-950/20 dark:border-blue-800"
        x-data="{ showGuide: false }">
        <button type="button" @click="showGuide = !showGuide"
            class="flex items-center justify-between w-full text-left">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-semibold text-blue-800 dark:text-blue-300">Panduan Pengisian Asuhan
                    Keperawatan</span>
            </div>
            <svg class="w-4 h-4 transition-transform text-blue-600 dark:text-blue-400"
                :class="showGuide ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="showGuide" x-transition class="mt-3 text-sm text-blue-900 dark:text-blue-200 space-y-3">
            <div>
                <p class="font-bold mb-1">Langkah-langkah:</p>
                <ol class="ml-4 space-y-1.5 list-decimal">
                    <li>Klik <strong>"Sekarang"</strong> untuk mengisi tanggal asuhan keperawatan.</li>
                    <li><strong>Cari diagnosis</strong> di kolom "Pilih Diagnosis Keperawatan" — ketik kode (D.0001)
                        atau nama diagnosis. Data akan muncul dari master SDKI.</li>
                    <li>Setelah diagnosis terpilih, akan muncul <strong>3 kolom</strong>:
                        <ul class="ml-4 mt-1 space-y-1 list-disc">
                            <li><span class="font-bold text-red-700 dark:text-red-400">SDKI (Diagnosis)</span> — Centang
                                penyebab, faktor risiko, dan tanda/gejala yang sesuai kondisi pasien. Rumusan diagnosis
                                akan terbentuk otomatis di bawah.</li>
                            <li><span class="font-bold text-green-700 dark:text-green-400">SLKI (Luaran)</span> —
                                Centang kriteria hasil yang akan dipantau untuk menilai keberhasilan intervensi.</li>
                            <li><span class="font-bold text-blue-700 dark:text-blue-400">SIKI (Intervensi)</span> —
                                Centang tindakan yang direncanakan: Observasi, Terapeutik, Edukasi, dan/atau Kolaborasi.
                            </li>
                        </ul>
                    </li>
                    <li>Klik <strong>"Simpan Asuhan Keperawatan"</strong>. Satu pasien bisa memiliki lebih dari satu
                        diagnosis (sesuai prioritas masalah).</li>
                </ol>
            </div>

            <div class="p-2.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 space-y-1">
                <p class="font-bold">Setelah disimpan:</p>
                <ul class="ml-4 list-disc space-y-0.5">
                    <li>Data muncul di daftar <strong>"Riwayat Asuhan Keperawatan"</strong> di bawah form.</li>
                    <li>Diagnosis yang sudah dibuat akan muncul di <strong>tab CPPT</strong> saat perawat mengisi
                        catatan perkembangan pasien.</li>
                    <li>Di CPPT, perawat bisa memilih diagnosis, <strong>centang tindakan SIKI</strong> yang sudah
                        dilakukan, dan <strong>beri skor evaluasi SLKI (1-5)</strong> untuk menilai progress luaran.
                    </li>
                </ul>
            </div>

            <p class="text-sm text-blue-600 dark:text-blue-400">Referensi: SDKI, SLKI, SIKI — Persatuan Perawat Nasional
                Indonesia (PPNI)</p>
        </div>
    </div>

    {{-- ============================================================
    | FORM ENTRY
    ============================================================= --}}
    @if (!$isFormLocked)
        <x-border-form title="Perencanaan Asuhan Keperawatan" align="start" bgcolor="bg-gray-50">
            <div class="mt-3 space-y-4">

                {{-- Row: Tanggal + LOV --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {{-- Tanggal --}}
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-input-label value="Tanggal *" />
                            <x-text-input wire:model="formEntryAsuhanKeperawatan.tglAsuhanKeperawatan"
                                class="w-full mt-1 font-mono text-sm" readonly placeholder="dd/mm/yyyy hh:mm:ss"
                                :error="$errors->has('formEntryAsuhanKeperawatan.tglAsuhanKeperawatan')" />
                            <x-input-error :messages="$errors->get('formEntryAsuhanKeperawatan.tglAsuhanKeperawatan')" class="mt-1" />
                        </div>
                        <x-secondary-button wire:click="setTglAsuhanKeperawatan" type="button"
                            class="shrink-0">Sekarang</x-secondary-button>
                    </div>
                    {{-- LOV --}}
                    <div>
                        <livewire:lov.asuhan-keperawatan.lov-asuhan-keperawatan label="Pilih Diagnosis Keperawatan *"
                            target="riFormAsuhanKeperawatan" :disabled="$isFormLocked"
                            wire:key="lov-asuhan-keperawatan-{{ $this->renderKey('modal-asuhan-keperawatan-ri') }}" />
                        <x-input-error :messages="$errors->get('formEntryAsuhanKeperawatan.diagKepId')" class="mt-1" />
                    </div>
                </div>

                {{-- ============================================================
            | 3-COLUMN LAYOUT: SDKI | SLKI | SIKI (mirip Desnet)
            ============================================================= --}}
                @if (!empty($formEntryAsuhanKeperawatan['diagKepJson']['sdki']))
                    @php
                        $sdki = $formEntryAsuhanKeperawatan['diagKepJson']['sdki'];
                        $slkiList = $formEntryAsuhanKeperawatan['diagKepJson']['slki'] ?? [];
                        $sikiList = $formEntryAsuhanKeperawatan['diagKepJson']['siki'] ?? [];
                        $perumusan = $formEntryAsuhanKeperawatan['perumusanDiagnosis'];
                        $luaranDipilih = $formEntryAsuhanKeperawatan['perencanaanLuaran']['kriteriaHasilDipilih'] ?? [];
                        $tindakanDipilih =
                            $formEntryAsuhanKeperawatan['perencanaanIntervensi']['tindakanDipilih'] ?? [];
                        $isRisiko = !empty($sdki['faktor_risiko']);
                        $hasPenyebab = !empty($sdki['penyebab']);
                    @endphp

                    <div
                        class="grid grid-cols-1 lg:grid-cols-3 gap-0 border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden text-sm">

                        {{-- ══════════════════════════════════════
                        | KOLOM 1: DIAGNOSIS KEPERAWATAN (SDKI)
                        ══════════════════════════════════════ --}}
                        <div class="border-b lg:border-b-0 lg:border-r border-gray-300 dark:border-gray-600">
                            {{-- Header --}}
                            <div class="px-3 py-2 bg-red-600 text-white font-bold text-center uppercase tracking-wide">
                                Diagnosis Keperawatan
                            </div>

                            <div class="p-3 space-y-3 max-h-[32rem] overflow-y-auto">
                                {{-- Kode & Nama --}}
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">
                                        {{ $formEntryAsuhanKeperawatan['diagKepId'] }} —
                                        {{ $formEntryAsuhanKeperawatan['diagKepDesc'] }}
                                    </p>
                                    <p class="text-sm mt-0.5">
                                        <span
                                            class="inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 font-medium">{{ $sdki['kategori'] ?? '-' }}</span>
                                        <span
                                            class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 font-medium ml-1">{{ $sdki['subkategori'] ?? '-' }}</span>
                                    </p>
                                </div>

                                {{-- Definisi --}}
                                @if (!empty($sdki['definisi']))
                                    <div>
                                        <p class="font-bold text-gray-600 dark:text-gray-400 mb-0.5">Definisi</p>
                                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                            {{ $sdki['definisi'] }}</p>
                                    </div>
                                @endif

                                {{-- Penyebab (Aktual) --}}
                                @if ($hasPenyebab && !$isRisiko)
                                    <div>
                                        <p class="font-bold text-gray-600 dark:text-gray-400 mb-1">Penyebab</p>
                                        @foreach ($sdki['penyebab'] as $jenis => $items)
                                            @if (is_array($items) && count($items))
                                                <p class="font-semibold text-gray-500 italic mt-1 mb-0.5">
                                                    {{ ucfirst($jenis) }}:</p>
                                                @foreach ($items as $i => $item)
                                                    @php $isOn = in_array($item, $perumusan['penyebabDipilih'] ?? []); @endphp
                                                    <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/10 rounded px-1 -mx-1"
                                                        wire:click="togglePerumusan('penyebabDipilih', '{{ addslashes($item) }}')">
                                                        <div
                                                            class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-red-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                            <div
                                                                class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                            {{ $item }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Faktor Risiko --}}
                                @if ($isRisiko)
                                    <div>
                                        <p class="font-bold text-gray-600 dark:text-gray-400 mb-1">Faktor Risiko</p>
                                        @foreach ($sdki['faktor_risiko'] as $i => $fr)
                                            @php $isOn = in_array($fr, $perumusan['faktorResikoDipilih'] ?? []); @endphp
                                            <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-orange-50 dark:hover:bg-orange-900/10 rounded px-1 -mx-1"
                                                wire:click="togglePerumusan('faktorResikoDipilih', '{{ addslashes($fr) }}')">
                                                <div
                                                    class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-orange-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                    <div
                                                        class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                    </div>
                                                </div>
                                                <span
                                                    class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                    {{ $fr }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Gejala Tanda Mayor --}}
                                @if (!empty($sdki['gejala_tanda_mayor']))
                                    <div>
                                        <p class="font-bold text-gray-600 dark:text-gray-400 mb-1">Gejala dan Tanda
                                            Mayor</p>
                                        @foreach (['subjektif' => 'tandaMayorSubjDipilih', 'objektif' => 'tandaMayorObjDipilih'] as $tipe => $field)
                                            @if (!empty($sdki['gejala_tanda_mayor'][$tipe]) && !in_array('Tidak tersedia', $sdki['gejala_tanda_mayor'][$tipe]))
                                                <p class="font-semibold text-gray-500 italic mt-1 mb-0.5">
                                                    {{ ucfirst($tipe) }}:</p>
                                                @foreach ($sdki['gejala_tanda_mayor'][$tipe] as $i => $item)
                                                    @php $isOn = in_array($item, $perumusan[$field] ?? []); @endphp
                                                    <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-emerald-50 dark:hover:bg-emerald-900/10 rounded px-1 -mx-1"
                                                        wire:click="togglePerumusan('{{ $field }}', '{{ addslashes($item) }}')">
                                                        <div
                                                            class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                            <div
                                                                class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                            {{ $item }}</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <p class="font-semibold text-gray-500 italic mt-1 mb-0.5">
                                                    {{ ucfirst($tipe) }}:</p>
                                                <p class="text-gray-400 ml-1">(Tidak tersedia)</p>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Gejala Tanda Minor --}}
                                @if (!empty($sdki['gejala_tanda_minor']))
                                    @php
                                        $hasMinor = false;
                                        foreach ($sdki['gejala_tanda_minor'] as $items) {
                                            if (
                                                is_array($items) &&
                                                count($items) &&
                                                !in_array('Tidak tersedia', $items)
                                            ) {
                                                $hasMinor = true;
                                                break;
                                            }
                                        }
                                    @endphp
                                    @if ($hasMinor)
                                        <div>
                                            <p class="font-bold text-gray-600 dark:text-gray-400 mb-1">Gejala dan Tanda
                                                Minor</p>
                                            @foreach (['subjektif' => 'tandaMinorSubjDipilih', 'objektif' => 'tandaMinorObjDipilih'] as $tipe => $field)
                                                @if (!empty($sdki['gejala_tanda_minor'][$tipe]) && !in_array('Tidak tersedia', $sdki['gejala_tanda_minor'][$tipe]))
                                                    <p class="font-semibold text-gray-500 italic mt-1 mb-0.5">
                                                        {{ ucfirst($tipe) }}:</p>
                                                    @foreach ($sdki['gejala_tanda_minor'][$tipe] as $i => $item)
                                                        @php $isOn = in_array($item, $perumusan[$field] ?? []); @endphp
                                                        <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-teal-50 dark:hover:bg-teal-900/10 rounded px-1 -mx-1"
                                                            wire:click="togglePerumusan('{{ $field }}', '{{ addslashes($item) }}')">
                                                            <div
                                                                class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-teal-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                                <div
                                                                    class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                                </div>
                                                            </div>
                                                            <span
                                                                class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                                {{ $item }}</span>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                {{-- Kondisi Klinis Terkait --}}
                                @if (!empty($sdki['kondisi_klinis_terkait']))
                                    <div>
                                        <p class="font-bold text-gray-600 dark:text-gray-400 mb-0.5">Kondisi Klinis
                                            Terkait</p>
                                        <p class="text-gray-600 dark:text-gray-400">
                                            {{ implode(', ', $sdki['kondisi_klinis_terkait']) }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- ══════════════════════════════════════
                        | KOLOM 2: LUARAN KEPERAWATAN (SLKI)
                        ══════════════════════════════════════ --}}
                        <div class="border-b lg:border-b-0 lg:border-r border-gray-300 dark:border-gray-600">
                            <div
                                class="px-3 py-2 bg-green-600 text-white font-bold text-center uppercase tracking-wide">
                                Luaran Keperawatan
                            </div>

                            <div class="p-3 space-y-3 max-h-[32rem] overflow-y-auto">
                                @forelse ($slkiList as $luaran)
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-gray-100 mb-1">
                                            {{ $luaran['kode'] ?? '' }} — {{ $luaran['nama'] ?? '' }}
                                        </p>
                                        @if (!empty($luaran['kriteria_hasil']))
                                            <p class="font-semibold text-gray-500 mb-1">Kriteria Hasil:</p>
                                            @foreach ($luaran['kriteria_hasil'] as $i => $kh)
                                                @php $isOn = in_array($kh, $luaranDipilih); @endphp
                                                <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/10 rounded px-1 -mx-1"
                                                    wire:click="toggleKriteriaHasil('{{ addslashes($kh) }}')">
                                                    <div
                                                        class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                        <div
                                                            class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                        </div>
                                                    </div>
                                                    <span
                                                        class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                        {{ $kh }}</span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    @if (!$loop->last)
                                        <hr class="border-gray-200 dark:border-gray-700">
                                    @endif
                                @empty
                                    <p class="text-gray-400 text-center py-4">Tidak ada data luaran.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- ══════════════════════════════════════
                        | KOLOM 3: INTERVENSI KEPERAWATAN (SIKI)
                        ══════════════════════════════════════ --}}
                        <div>
                            <div
                                class="px-3 py-2 bg-blue-600 text-white font-bold text-center uppercase tracking-wide">
                                Intervensi Keperawatan
                            </div>

                            <div class="p-3 space-y-3 max-h-[32rem] overflow-y-auto">
                                @forelse ($sikiList as $intervensi)
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">
                                            {{ $intervensi['kode'] ?? '' }} — {{ $intervensi['nama'] ?? '' }}
                                        </p>
                                        @if (!empty($intervensi['definisi']))
                                            <p class="text-gray-500 dark:text-gray-400 italic mt-0.5 mb-1">
                                                {{ $intervensi['definisi'] }}</p>
                                        @endif

                                        @if (!empty($intervensi['tindakan']))
                                            @foreach ($intervensi['tindakan'] as $kategori => $tindakanList)
                                                @if (is_array($tindakanList) && count($tindakanList))
                                                    <p class="font-semibold text-gray-500 mt-1.5 mb-0.5">
                                                        {{ ucfirst($kategori) }}:</p>
                                                    @foreach ($tindakanList as $i => $t)
                                                        @php $isOn = in_array($t, $tindakanDipilih); @endphp
                                                        <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/10 rounded px-1 -mx-1"
                                                            wire:click="toggleTindakan('{{ addslashes($t) }}')">
                                                            <div
                                                                class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                                <div
                                                                    class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}">
                                                                </div>
                                                            </div>
                                                            <span
                                                                class="{{ $isOn ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $i + 1 }}.
                                                                {{ $t }}</span>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                    @if (!$loop->last)
                                        <hr class="border-gray-200 dark:border-gray-700 my-2">
                                    @endif
                                @empty
                                    <p class="text-gray-400 text-center py-4">Tidak ada data intervensi.</p>
                                @endforelse
                            </div>
                        </div>

                    </div>

                    {{-- Rumusan Diagnosis --}}
                    @if (
                        !empty($perumusan['rumusanDiagnosis']) &&
                            $perumusan['rumusanDiagnosis'] !== $formEntryAsuhanKeperawatan['diagKepDesc']
                    )
                        <div
                            class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 px-4 py-3">
                            <p
                                class="text-sm font-bold text-indigo-700 dark:text-indigo-400 uppercase tracking-wide mb-1">
                                Rumusan Diagnosis Keperawatan</p>
                            <p class="text-sm text-indigo-900 dark:text-indigo-200 font-medium leading-relaxed">
                                {{ $perumusan['rumusanDiagnosis'] }}</p>
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('formEntryAsuhanKeperawatan.perumusanDiagnosis.rumusanDiagnosis')" class="mt-1" />

                    {{-- Buttons --}}
                    <div class="flex items-center justify-between pt-1">
                        <x-ghost-button wire:click="resetFormEntry" type="button" class="text-sm">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-ghost-button>
                        <x-primary-button wire:click="addAsuhanKeperawatan" type="button">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Simpan Asuhan Keperawatan
                        </x-primary-button>
                    </div>
                @endif

            </div>
        </x-border-form>
    @endif

    {{-- ============================================================
    | RIWAYAT ASUHAN KEPERAWATAN
    ============================================================= --}}
    @forelse ($dataDaftarRi['asuhanKeperawatan'] ?? [] as $idx => $askep)
        <div wire:key="askep-{{ $idx }}-{{ $this->renderKey('modal-asuhan-keperawatan-ri') }}"
            class="grid grid-cols-1 lg:grid-cols-2 gap-2">

            {{-- Kolom Kiri: Data Diagnosis (riwayat-asuhan-keperawatan.blade.php) --}}
            <div>
                @include('pages::transaksi.ri.emr-ri.asuhan-keperawatan-ri.riwayat-asuhan-keperawatan', [
                    'askep' => $askep,
                    'idx' => $idx,
                    'isFormLocked' => $isFormLocked,
                ])
            </div>

            {{-- Kolom Kanan: Implementasi & Evaluasi (implementasi-asuhan-keperawatan.blade.php) --}}
            <div>
                @include(
                    'pages::transaksi.ri.emr-ri.asuhan-keperawatan-ri.implementasi-asuhan-keperawatan',
                    [
                        'askep' => $askep,
                        'idx' => $idx,
                        'isFormLocked' => $isFormLocked,
                        'activeImplIndex' => $activeImplIndex,
                        'formImpl' => $formImpl,
                        'errors' => $errors,
                    ]
                )
            </div>

        </div>
    @empty
        <x-border-form title="Riwayat Asuhan Keperawatan" align="start" bgcolor="bg-gray-50">
            <p class="text-sm text-center text-gray-400 py-6 mt-2">
                Belum ada Asuhan Keperawatan.
            </p>
        </x-border-form>
    @endforelse

</div>
