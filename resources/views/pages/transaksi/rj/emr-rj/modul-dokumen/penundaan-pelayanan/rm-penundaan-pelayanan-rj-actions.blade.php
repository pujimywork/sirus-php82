<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/penundaan-pelayanan/rm-penundaan-pelayanan-rj-actions.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penundaan-pelayanan-rj'];

    // ── Form entri baru ──
    public array $newForm = [
        'tglPemberitahuan' => '',
        'jenis' => '',
        'alasan' => '',
        'jadwalUlang' => '',
        'alternatif' => '',
        'respon' => '',
        'namaPenanda' => '',
        'hubunganPasien' => 'pasien',
        'pemberiInfo' => '',
        'pemberiInfoCode' => '',
        'pemberiInfoDate' => '',
    ];

    public string $signature = ''; // TTD pasien/keluarga untuk entri baru

    public array $penundaanList = [];

    public array $responOptions = ['Menerima penundaan', 'Memilih alternatif', 'Menolak'];

    public array $hubunganPasienOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    // Kunci entri yang sedang diedit (signatureDate = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-penundaan-pelayanan-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->penundaanList = $data['penundaanPelayananRJ'] ?? [];
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        if (!isset($this->dataDaftarPoliRJ['penundaanPelayananRJ']) || !is_array($this->dataDaftarPoliRJ['penundaanPelayananRJ'])) {
            $this->dataDaftarPoliRJ['penundaanPelayananRJ'] = [];
        }
        $this->penundaanList = $this->dataDaftarPoliRJ['penundaanPelayananRJ'];
        $this->newForm['namaPenanda'] = $this->dataDaftarPoliRJ['regName'] ?? '';
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-penundaan-pelayanan-rj');

        $this->dispatch('open-modal', name: "rm-penundaan-pelayanan-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-penundaan-pelayanan-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.jenis' => 'required|string|max:500',
            'newForm.alasan' => 'required|string|max:1000',
            'newForm.jadwalUlang' => 'nullable|date_format:d/m/Y H:i:s',
            'newForm.alternatif' => 'nullable|string|max:1000',
            'newForm.respon' => 'required|string',
            'newForm.namaPenanda' => 'required|string|max:200',
            'newForm.hubunganPasien' => 'required|string|max:50',
            'signature' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 25/06/2026 11:11:16).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'Tanggal/jam pemberitahuan',
            'newForm.jenis' => 'Jenis pelayanan yang ditunda',
            'newForm.alasan' => 'Alasan penundaan/kelambatan',
            'newForm.jadwalUlang' => 'Jadwal ulang',
            'newForm.alternatif' => 'Alternatif yang ditawarkan',
            'newForm.respon' => 'Respon pasien/keluarga',
            'newForm.namaPenanda' => 'Nama pasien/keluarga',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'signature' => 'Tanda tangan pasien/keluarga',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTglPemberitahuanSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tglPemberitahuan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJadwalUlangSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['jadwalUlang'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | SIGNATURE (pasien/keluarga)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    /* ===============================
     | TTD PETUGAS (Pemberi Informasi) = FINALIZE
     | Petugas TTD di akhir → validasi lengkap + kunci entri.
     =============================== */
    public function setPemberiInfo(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'TTD pasien/keluarga wajib sebelum TTD petugas.');
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi kolom wajib sebelum TTD petugas.');
            throw $e;
        }

        // Stempel TTD petugas (pemberi informasi) = user login.
        $this->newForm['pemberiInfo'] = auth()->user()->myuser_name ?? '';
        $this->newForm['pemberiInfoCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['pemberiInfoDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewForm();
            $this->newForm['namaPenanda'] = $this->dataDaftarPoliRJ['regName'] ?? '';
            $this->signature = '';
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-penundaan-pelayanan-rj');
            $this->dispatch('toast', type: 'success', message: 'Penundaan ditandatangani petugas dan terkunci.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD pasien dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['signature']);
    }

    // Susun array entri dari state form. $key = signatureDate (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'tglPemberitahuan' => $this->newForm['tglPemberitahuan'] ?? '',
            'jenis' => $this->newForm['jenis'] ?? '',
            'alasan' => $this->newForm['alasan'] ?? '',
            'jadwalUlang' => $this->newForm['jadwalUlang'] ?? '',
            'alternatif' => $this->newForm['alternatif'] ?? '',
            'respon' => $this->newForm['respon'] ?? '',
            'namaPenanda' => $this->newForm['namaPenanda'] ?? '',
            'hubunganPasien' => $this->newForm['hubunganPasien'] ?? 'pasien',
            'signature' => $this->signature,
            'signatureDate' => $key,
            'pemberiInfo' => $this->newForm['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $this->newForm['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $this->newForm['pemberiInfoDate'] ?? '',
            'finalized' => $finalized,
        ];
    }

    // Simpan entri (add/update by $key) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRJRow($this->rjNo);

            $data = $this->findDataRJ($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['penundaanPelayananRJ']) || !is_array($data['penundaanPelayananRJ'])) {
                $data['penundaanPelayananRJ'] = [];
            }

            $list = $data['penundaanPelayananRJ'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['penundaanPelayananRJ'] = array_values($list);

            $this->updateJsonRJ($this->rjNo, $data);
            $this->dataDaftarPoliRJ = $data;
            $this->penundaanList = $data['penundaanPelayananRJ'];

            $this->appendAdminLogRJ((int) $this->rjNo, $logVerb . ' Penundaan Pelayanan RJ — jenis "' . ($entry['jenis'] ?: ($entry['alasan'] ?: '-')) . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['alasan'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Alasan penundaan wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-penundaan-pelayanan-rj');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'tglPemberitahuan' => $entry['tglPemberitahuan'] ?? '',
            'jenis' => $entry['jenis'] ?? '',
            'alasan' => $entry['alasan'] ?? '',
            'jadwalUlang' => $entry['jadwalUlang'] ?? '',
            'alternatif' => $entry['alternatif'] ?? '',
            'respon' => $entry['respon'] ?? '',
            'namaPenanda' => $entry['namaPenanda'] ?? '',
            'hubunganPasien' => $entry['hubunganPasien'] ?? 'pasien',
            'pemberiInfo' => $entry['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $entry['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $entry['pemberiInfoDate'] ?? '',
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->newForm['namaPenanda'] = $this->dataDaftarPoliRJ['regName'] ?? '';
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-penundaan-pelayanan-rj');
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $signatureDate);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-penundaan-pelayanan-rj.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data) || !isset($data['penundaanPelayananRJ'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['penundaanPelayananRJ'] = collect($data['penundaanPelayananRJ'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->penundaanList = $data['penundaanPelayananRJ'];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Pemberitahuan Penundaan/Kelambatan — TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-penundaan-pelayanan-rj');
            $this->dispatch('toast', type: 'success', message: 'Formulir penundaan berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tglPemberitahuan' => '',
            'jenis' => '',
            'alasan' => '',
            'jadwalUlang' => '',
            'alternatif' => '',
            'respon' => '',
            'namaPenanda' => '',
            'hubunganPasien' => 'pasien',
            'pemberiInfo' => '',
            'pemberiInfoCode' => '',
            'pemberiInfoDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->penundaanList = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $ppCount = count($penundaanList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Pemberitahuan Penundaan / Kelambatan Pelayanan
                    </h3>
                    @if ($ppCount > 0)
                        <x-badge variant="success">{{ $ppCount }} catatan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>

                <div class="flex shrink-0">
                    <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                        wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            Buka Formulir
                        </span>
                        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Memuat...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            <p class="text-base text-muted dark:text-gray-400">
                Formulir pemberitahuan kepada pasien/keluarga atas penundaan atau kelambatan pelayanan beserta alasan
                dan alternatif yang ditawarkan. Dapat lebih dari satu catatan.
            </p>

            @if ($ppCount > 0)
                <div class="overflow-x-auto">
                    <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Daftar Pemberitahuan Tersimpan</h4>
                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                        <thead class="bg-surface-soft dark:bg-gray-800">
                            <tr class="text-left text-muted dark:text-gray-300">
                                <th class="px-3 py-2 border-b">Jenis</th>
                                <th class="px-3 py-2 border-b">Tanggal</th>
                                <th class="px-3 py-2 border-b">Pemberi Informasi</th>
                                <th class="px-3 py-2 border-b">Respon</th>
                                <th class="px-3 py-2 border-b text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($penundaanList) as $pp)
                                <tr class="border-b border-hairline dark:border-gray-700">
                                    <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                        {{ Str::limit($pp['jenis'] ?: ($pp['alasan'] ?? '-'), 50) ?: '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $pp['signatureDate'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">
                                        @if (!empty($pp['pemberiInfo'])){{ $pp['pemberiInfo'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                    </td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $pp['respon'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($this->entryIsFinal($pp))
                                            <x-badge variant="info">Terkunci</x-badge>
                                        @else
                                            <x-badge variant="warning">Draft</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-penundaan-pelayanan-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-penundaan-pelayanan-rj', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-500/10">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">
                                    Pemberitahuan Penundaan / Kelambatan Pelayanan
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    Formulir diisi & dijelaskan kepada pasien/keluarga — tampilan dapat diputar ke arah
                                    pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="success">Rawat Jalan</x-badge>
                            @if (count($penundaanList) > 0)
                                <x-badge variant="info">{{ count($penundaanList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="pp-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @php $formRO = $isFormLocked || $viewOnly; @endphp

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if ($viewOnly)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                            </div>
                        @elseif ($editingKey && !$isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                            </div>
                        @endif

                        {{-- ══ TANGGAL/JAM PEMBERITAHUAN ══ --}}
                        <section>
                            <x-input-label value="Tanggal / Jam Pemberitahuan *" class="mb-1" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model.live="newForm.tglPemberitahuan" placeholder="dd/mm/yyyy HH:mm:ss"
                                    :error="$errors->has('newForm.tglPemberitahuan')" :disabled="$formRO"
                                    class="w-full max-w-xs" />
                                @if (!$formRO)
                                    <x-now-button wire:click="setTglPemberitahuanSekarang" />
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('newForm.tglPemberitahuan')" class="mt-1" />
                        </section>

                        {{-- ══ JENIS PELAYANAN YANG DITUNDA / TERLAMBAT ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Pelayanan yang Ditunda / Terlambat *
                            </h3>
                            <x-textarea wire:model.live="newForm.jenis" :error="$errors->has('newForm.jenis')" rows="2"
                                placeholder="cth: Tindakan, Pengobatan, Pemeriksaan Penunjang (Lab), Radiologi, Operasi, Rawat Inap (daftar tunggu)..."
                                :disabled="$formRO" class="w-full" />
                            <x-input-error :messages="$errors->get('newForm.jenis')" class="mt-1" />
                        </section>

                        {{-- ══ ALASAN & ALTERNATIF ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Alasan Penundaan / Kelambatan *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alasan" :error="$errors->has('newForm.alasan')" rows="3"
                                    placeholder="Jelaskan alasan penundaan / kelambatan pelayanan..."
                                    :disabled="$formRO" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.alasan')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Jadwal Ulang" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jadwalUlang" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.jadwalUlang')" :disabled="$formRO"
                                        class="w-full max-w-xs" />
                                    @if (!$formRO)
                                        <x-now-button wire:click="setJadwalUlangSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.jadwalUlang')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Alternatif yang Ditawarkan (sesuai kebutuhan klinis)" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alternatif" :error="$errors->has('newForm.alternatif')" rows="3"
                                    placeholder="Alternatif pelayanan/rujukan yang ditawarkan..."
                                    :disabled="$formRO" class="w-full" />
                            </div>
                        </section>

                        {{-- ══ RESPON PASIEN/KELUARGA ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Respon Pasien / Keluarga *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($responOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="respon"
                                        wire:model.live="newForm.respon" :disabled="$formRO" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.respon')" class="mt-1" />
                        </section>

                        {{-- ══ CATATAN KEBIJAKAN ══ --}}
                        <div
                            class="px-4 py-3 text-sm border rounded-xl bg-surface-soft border-hairline text-muted dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                            Tidak berlaku untuk keterlambatan staf medis di RJ / IGD penuh. Onkologi &amp; transplantasi
                            mengikuti norma nasional. Dicatat di rekam medis (Lihat KE 2).
                        </div>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pasien / Keluarga --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pasien / Keluarga
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$formRO" wireMethod="clearSignature" />
                                    @elseif (!$formRO)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Keluarga *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaPenanda" :error="$errors->has('newForm.namaPenanda')"
                                            placeholder="Nama penanda tangan..." :disabled="$formRO"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.namaPenanda')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.hubunganPasien" :error="$errors->has('newForm.hubunganPasien')"
                                            :disabled="$formRO" class="w-full">
                                            @foreach ($hubunganPasienOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.hubunganPasien')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Pemberi Informasi (DPJP/PPA) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pemberi Informasi (DPJP / PPA)
                                    </div>
                                    @if (empty($newForm['pemberiInfo']))
                                        @if (!$formRO)
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 gap-2 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPemberiInfo"
                                                    wire:loading.attr="disabled" wire:target="setPemberiInfo"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPemberiInfo"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Petugas &amp; Kunci
                                                    </span>
                                                    <span wire:loading wire:target="setPemberiInfo">
                                                        <x-loading class="w-4 h-4" /> Mengunci...
                                                    </span>
                                                </x-primary-button>
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci penundaan ini.</p>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="font-semibold text-center text-ink dark:text-gray-200">
                                                {{ $newForm['pemberiInfo'] }}
                                            </div>
                                            @if (!empty($newForm['pemberiInfoCode']))
                                                <div class="text-sm text-muted mt-0.5">
                                                    Kode: {{ $newForm['pemberiInfoCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-sm text-muted">
                                                {{ $newForm['pemberiInfoDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN (expandable) ══ --}}
                        @if (count($penundaanList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <div class="flex items-center justify-between gap-2 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    <h3 class="text-base font-semibold text-body dark:text-gray-300">
                                        Daftar Pemberitahuan Tersimpan
                                    </h3>
                                    <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                                </div>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Jenis</th>
                                            <th class="px-4 py-3 border-b">Tanggal Dibuat</th>
                                            <th class="px-4 py-3 border-b">Pemberi Informasi</th>
                                            <th class="px-4 py-3 border-b">Respon</th>
                                            <th class="px-4 py-3 border-b text-center">Status</th>
                                            <th class="px-4 py-3 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($penundaanList) as $entry)
                                        @php
                                            // Normalisasi entri lama agar semua key ada (cegah "Undefined array key")
                                            $entry = array_replace([
                                                'tglPemberitahuan' => '', 'jenis' => '', 'alasan' => '', 'jadwalUlang' => '',
                                                'alternatif' => '', 'respon' => '', 'namaPenanda' => '', 'hubunganPasien' => '',
                                                'pemberiInfo' => '', 'pemberiInfoCode' => '', 'pemberiInfoDate' => '',
                                                'signature' => '', 'signatureDate' => '',
                                            ], $entry);
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['signatureDate'] ?? '';
                                            $hubLabel = collect($hubunganPasienOptions)->firstWhere('value', $entry['hubunganPasien'] ?? '')['label'] ?? ($entry['hubunganPasien'] ?? '');
                                        @endphp
                                        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                                    {{ Str::limit(($entry['jenis'] ?: ($entry['alasan'] ?? '')) ?: '(tanpa jenis penundaan)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['pemberiInfo']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['pemberiInfo'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['respon'] ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center" @click.stop>
                                                    <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjut Isi
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal)
                                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat
                                                            </x-secondary-button>
                                                            <x-secondary-button wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                            </x-secondary-button>
                                                        @endif
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus pemberitahuan ini?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Pemberitahuan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tglPemberitahuan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Pelayanan Ditunda</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['jenis'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Penundaan / Kelambatan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alasan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jadwal Ulang</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jadwalUlang'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Respon Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['respon'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alternatif yang Ditawarkan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alternatif'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaPenanda'] ?: '-' }}@if ($hubLabel) <span class="text-muted">({{ $hubLabel }})</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['signature']))
                                                                    <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['signatureDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemberi Informasi (Petugas)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['pemberiInfo']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['pemberiInfo'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['pemberiInfoDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif ($rjNo && !$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Pemberi Informasi.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif ($rjNo && !$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.r-j.penundaan-pelayanan.cetak-penundaan-pelayanan-rj
        wire:key="cetak-penundaan-pelayanan-rj-{{ $rjNo ?? 'init' }}" />
</div>
