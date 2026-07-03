<?php
// resources/views/pages/transaksi/penunjang/radiologi/⚡upload-radiologi-tambah-actions.blade.php
//
// Sibling action component — TAMBAH PEMERIKSAAN RADIOLOGI.
// Radiologi bisa menambahkan order pemeriksaan untuk pasien yang AKTIF hari ini
// (RJ/UGD: rj_status='A' & rj_date hari ini; RI: ri_status='I' tanpa filter tgl).
//
// Listener event dari halaman utama:
//   - radiologi.tambah.open  (source)
//
// Insert mengikuti pola administrasi per-modul:
//   RJ  → rstxn_rjrads      (PK rad_dtl  = nvl(max+1,1), ref rj_no)
//   UGD → rstxn_ugdrads     (PK rad_dtl  = nvl(max+1,1), ref rj_no)
//   RI  → rstxn_riradiologs (PK rirad_no = nvl(max+1,1), ref rihdr_no)
//
// Setelah insert sukses, dispatch 'radiologi-refresh' ke parent.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithValidationToastTrait;
    // Audit log terpadu — pakai helper appendAdminLog{RJ,UGD,RI} yang sudah ada.
    use EmrRJTrait, EmrUGDTrait, EmrRITrait;

    public string $source = 'RJ';

    // Pasien terpilih
    public string $patientSearch = '';
    public ?int $selectedRefNo = null;
    public array $selectedPatient = []; // reg_no, reg_name, sex, birth_date, umur_format, address

    // Form pemeriksaan
    public array $formEntryRad = ['radId' => '', 'radDesc' => '', 'radPrice' => ''];
    public ?string $drId = null; // dr_id terpilih; nama di-lookup saat insert ke dr_pengirim
    public ?string $klinisDesc = null;

    public int $lovKey = 0; // bump utk re-mount LOV radiologi setelah pilih/tambah

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    #[On('radiologi.tambah.open')]
    public function openTambahModal(string $source): void
    {
        $this->source = in_array($source, ['RJ', 'UGD', 'RI'], true) ? $source : 'RJ';
        $this->resetState();
        $this->dispatch('open-modal', name: 'rad-tambah');
    }

    public function closeTambahModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-tambah');
        $this->resetState();
    }

    private function resetState(): void
    {
        $this->reset(['patientSearch', 'selectedRefNo', 'selectedPatient', 'drId', 'klinisDesc']);
        $this->formEntryRad = ['radId' => '', 'radDesc' => '', 'radPrice' => ''];
        $this->resetValidation();
        $this->lovKey++;
    }

    /* ===============================
     | DAFTAR PASIEN AKTIF HARI INI (per source)
     =============================== */
    #[Computed]
    public function activePatients()
    {
        $keyword = trim($this->patientSearch);

        $pasienCols = [
            'p.reg_no',
            'p.reg_name',
            'p.sex',
            'p.address',
            DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
            DB::raw("CASE WHEN p.birth_date IS NOT NULL THEN
                trunc(months_between(sysdate, p.birth_date) / 12) || ' Thn ' ||
                trunc(mod(months_between(sysdate, p.birth_date), 12)) || ' Bln ' ||
                trunc(sysdate - add_months(p.birth_date, trunc(months_between(sysdate, p.birth_date)))) || ' Hr'
                ELSE NULL END as umur_format"),
        ];

        if ($this->source === 'RJ') {
            $query = DB::table('rstxn_rjhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->whereRaw("NVL(h.rj_status,'A') = 'A'")
                ->whereRaw('TRUNC(h.rj_date) = TRUNC(sysdate)')
                ->select(array_merge(['h.rj_no as ref_no'], $pasienCols));
        } elseif ($this->source === 'UGD') {
            $query = DB::table('rstxn_ugdhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->whereRaw("NVL(h.rj_status,'A') = 'A'")
                ->whereRaw('TRUNC(h.rj_date) = TRUNC(sysdate)')
                ->select(array_merge(['h.rj_no as ref_no'], $pasienCols));
        } else {
            // RI — aktif = ri_status 'I' (tanpa filter tanggal; pasien bisa masuk hari sebelumnya)
            $query = DB::table('rstxn_rihdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->whereRaw("NVL(h.ri_status,'I') = 'I'")
                ->select(array_merge(['h.rihdr_no as ref_no'], $pasienCols));
        }

        if ($keyword !== '') {
            $keywordUpper = '%' . mb_strtoupper($keyword) . '%';
            $query->where(function ($q) use ($keyword, $keywordUpper) {
                $q->whereRaw('UPPER(p.reg_name) LIKE ?', [$keywordUpper])->orWhereRaw('TO_CHAR(p.reg_no) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        return $query->orderBy('p.reg_name')->limit(30)->get();
    }

    public function selectPatient(int $refNo, array $patient): void
    {
        $this->selectedRefNo = $refNo;
        $this->selectedPatient = $patient;

        // Default dokter pengirim = dokter kunjungan (RJ/UGD) / DPJP (RI) — tinggal ganti kalau perlu.
        $default = $this->defaultDoctorId();
        $this->drId = $default !== null ? (string) $default : null;
        unset($this->relatedDoctors);
    }

    public function changePatient(): void
    {
        $this->reset(['selectedRefNo', 'selectedPatient', 'drId', 'klinisDesc']);
        unset($this->relatedDoctors);
    }

    /* Dokter terkait pasien terpilih (untuk dropdown Dokter Pengirim).
       RJ/UGD: dokter kunjungan header. RI: DPJP + dokter visite + dokter jasa (aktif). */
    #[Computed]
    public function relatedDoctors()
    {
        if (!$this->selectedRefNo) {
            return collect();
        }

        if ($this->source === 'RI') {
            $dpjp = DB::table('rstxn_rihdrs')->select('dr_id')->where('rihdr_no', $this->selectedRefNo)->whereNotNull('dr_id');
            $visite = DB::table('rstxn_rivisits')->select('dr_id')->where('rihdr_no', $this->selectedRefNo)->whereNotNull('dr_id');
            $jasa = DB::table('rstxn_riactdocs')->select('dr_id')->where('rihdr_no', $this->selectedRefNo)->whereNotNull('dr_id');
            $unionIds = $dpjp->union($visite)->union($jasa);

            return DB::table('rsmst_doctors as d')
                ->joinSub($unionIds, 'u', 'u.dr_id', '=', 'd.dr_id')
                ->select('d.dr_id', 'd.dr_name')
                ->where('d.active_status', '1')
                ->distinct()
                ->orderBy('d.dr_name')
                ->get();
        }

        // RJ / UGD — dokter kunjungan (ref = rj_no di kedua header)
        $hdrTable = $this->source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        return DB::table('rsmst_doctors as d')
            ->join($hdrTable . ' as h', 'h.dr_id', '=', 'd.dr_id')
            ->where('h.rj_no', $this->selectedRefNo)
            ->select('d.dr_id', 'd.dr_name')
            ->distinct()
            ->orderBy('d.dr_name')
            ->get();
    }

    private function defaultDoctorId(): ?string
    {
        if ($this->source === 'RI') {
            return DB::table('rstxn_rihdrs')->where('rihdr_no', $this->selectedRefNo)->value('dr_id');
        }
        $hdrTable = $this->source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        return DB::table($hdrTable)->where('rj_no', $this->selectedRefNo)->value('dr_id');
    }

    /* ===============================
     | LOV RADIOLOGI
     =============================== */
    #[On('lov.selected.radiologi-tambah')]
    public function onRadiologiSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formEntryRad = ['radId' => '', 'radDesc' => '', 'radPrice' => ''];
            return;
        }

        $this->formEntryRad['radId'] = $payload['rad_id'] ?? '';
        $this->formEntryRad['radDesc'] = $payload['rad_desc'] ?? '';
        $this->formEntryRad['radPrice'] = $payload['rad_price'] ?? '';
    }

    public function clearRad(): void
    {
        $this->formEntryRad = ['radId' => '', 'radDesc' => '', 'radPrice' => ''];
        $this->resetValidation();
        $this->lovKey++;
    }

    /* ===============================
     | INSERT PEMERIKSAAN
     =============================== */
    public function insertRad(): void
    {
        if (!$this->selectedRefNo) {
            $this->dispatch('toast', type: 'error', message: 'Pilih pasien dulu.');
            return;
        }

        $this->validateWithToast(
            [
                'formEntryRad.radId' => 'bail|required|exists:rsmst_radiologis,rad_id',
                'formEntryRad.radDesc' => 'bail|required',
                'formEntryRad.radPrice' => 'bail|required|numeric|min:0',
                'klinisDesc' => 'bail|required|string',
            ],
            [
                'formEntryRad.radId.required' => 'Pemeriksaan radiologi harus dipilih.',
                'formEntryRad.radId.exists' => 'Radiologi tidak valid.',
                'formEntryRad.radDesc.required' => 'Deskripsi radiologi kosong.',
                'formEntryRad.radPrice.required' => 'Tarif harus diisi.',
                'formEntryRad.radPrice.numeric' => 'Tarif harus berupa angka.',
                'klinisDesc.required' => 'Keterangan klinis harus diisi.',
            ],
        );

        // dr_pengirim disimpan sebagai NAMA dokter (selaras pola kirim radiologi).
        $drPengirim = null;
        if (!empty($this->drId)) {
            $name = DB::table('rsmst_doctors')->where('dr_id', $this->drId)->value('dr_name');
            $drPengirim = $name ? trim($name) : null;
        }
        $klinis = trim((string) $this->klinisDesc) ?: null;
        $price = 0 + $this->formEntryRad['radPrice'];

        try {
            DB::transaction(function () use ($drPengirim, $klinis, $price) {
                if ($this->source === 'RJ') {
                    $this->lockHeader('rstxn_rjhdrs', 'rj_no');
                    $last = DB::table('rstxn_rjrads')->select(DB::raw('nvl(max(rad_dtl)+1,1) as rad_dtl_max'))->first();
                    $next = (int) $last->rad_dtl_max;
                    DB::table('rstxn_rjrads')->insert([
                        'rad_dtl' => $next,
                        'rj_no' => $this->selectedRefNo,
                        'rad_id' => $this->formEntryRad['radId'],
                        'rad_price' => $price,
                        'dr_pengirim' => $drPengirim,
                        'klinis_desc' => $klinis,
                        'waktu_entry' => DB::raw('sysdate'),
                    ]);
                } elseif ($this->source === 'UGD') {
                    $this->lockHeader('rstxn_ugdhdrs', 'rj_no');
                    $last = DB::table('rstxn_ugdrads')->select(DB::raw('nvl(max(rad_dtl)+1,1) as rad_dtl_max'))->first();
                    $next = (int) $last->rad_dtl_max;
                    DB::table('rstxn_ugdrads')->insert([
                        'rad_dtl' => $next,
                        'rj_no' => $this->selectedRefNo,
                        'rad_id' => $this->formEntryRad['radId'],
                        'rad_price' => $price,
                        'dr_pengirim' => $drPengirim,
                        'klinis_desc' => $klinis,
                        'waktu_entry' => DB::raw('sysdate'),
                    ]);
                } else {
                    // RI
                    $this->lockHeader('rstxn_rihdrs', 'rihdr_no');
                    $next = (int) DB::scalar('SELECT NVL(MAX(TO_NUMBER(rirad_no)) + 1, 1) FROM rstxn_riradiologs');
                    DB::table('rstxn_riradiologs')->insert([
                        'rirad_no' => $next,
                        'rihdr_no' => $this->selectedRefNo,
                        'rad_id' => $this->formEntryRad['radId'],
                        'rirad_price' => $price,
                        'dr_pengirim' => $drPengirim,
                        'klinis_desc' => $klinis,
                        'waktu_entry' => DB::raw('sysdate'),
                        'rirad_date' => DB::raw('sysdate'),
                    ]);
                }

                // Audit log terpadu (tab "Log Aktivitas") — dari sisi radiologi.
                $this->appendLog('Tambah Order Radiologi (dari radiologi) - ' . $this->formEntryRad['radDesc']);
            });

            $this->dispatch('toast', type: 'success', message: 'Pemeriksaan ' . $this->formEntryRad['radDesc'] . ' ditambahkan.');
            // Pertahankan pasien terpilih → bisa tambah pemeriksaan lagi dengan cepat.
            $this->clearRad();
            $this->dispatch('radiologi-refresh');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah: ' . $e->getMessage());
        }
    }

    // Lock baris header (cegah race PK rad_dtl/rirad_no) + pastikan ref valid.
    private function lockHeader(string $table, string $pkColumn): void
    {
        $exists = DB::table($table)->where($pkColumn, $this->selectedRefNo)->lockForUpdate()->exists();
        if (!$exists) {
            throw new \RuntimeException('Data pasien #' . $this->selectedRefNo . ' tidak ditemukan / tidak aktif.');
        }
    }

    // Arahkan ke appendAdminLog{RJ,UGD,RI} sesuai sumber (dipanggil di dalam transaksi, header sudah di-lock).
    private function appendLog(string $keterangan): void
    {
        $ref = (int) $this->selectedRefNo;
        if ($this->source === 'UGD') {
            $this->appendAdminLogUGD($ref, $keterangan, 'MR');
        } elseif ($this->source === 'RI') {
            $this->appendAdminLogRI($ref, $keterangan, 'MR');
        } else {
            $this->appendAdminLogRJ($ref, $keterangan, 'MR');
        }
    }

    public function updatedPatientSearch(): void
    {
        unset($this->activePatients);
    }
};
?>

<div>
    <x-modal name="rad-tambah" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- HEADER (komposisi & typografi selaras master-poli-actions) --}}
            <div class="px-6 py-5 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    Tambah Pemeriksaan Radiologi
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Pasien aktif hari ini
                                    @if ($source === 'RI')
                                        (sedang dirawat)
                                    @else
                                        (terdaftar &amp; belum selesai)
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge variant="alternative">
                                Sumber: {{ ['RJ' => 'Rawat Jalan', 'UGD' => 'Unit Gawat Darurat', 'RI' => 'Rawat Inap'][$source] ?? $source }}
                            </x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeTambahModal" class="shrink-0">
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
            <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">

                {{-- STEP 1: PILIH PASIEN --}}
                <div>
                    <x-input-label value="1. Pasien" class="mb-1.5" />

                    @if ($selectedRefNo)
                        {{-- Pasien terpilih (typografi identitas standar list) --}}
                        @php
                            $selSex = ($selectedPatient['sex'] ?? null) === 'L' ? 'Laki-Laki' : (($selectedPatient['sex'] ?? null) === 'P' ? 'Perempuan' : '-');
                        @endphp
                        <div class="flex items-start justify-between gap-3 px-4 py-3 border rounded-xl border-brand-green/30 bg-brand-green/5 dark:border-brand-lime/30 dark:bg-brand-lime/5">
                            <div class="space-y-0 leading-tight">
                                <div class="text-base font-medium text-body dark:text-gray-300">
                                    {{ $selectedPatient['reg_no'] ?? '-' }}
                                </div>
                                <div class="text-lg font-semibold text-brand dark:text-white">
                                    {{ $selectedPatient['reg_name'] ?? '-' }} / ({{ $selSex }})
                                </div>
                                <div class="text-sm text-body dark:text-gray-400">
                                    {{ $selectedPatient['birth_date'] ?? '-' }}
                                    @if (!empty($selectedPatient['umur_format']))
                                        <span class="text-muted">({{ $selectedPatient['umur_format'] }})</span>
                                    @endif
                                </div>
                                @if (!empty($selectedPatient['address']))
                                    <div class="text-sm text-muted dark:text-gray-400">{{ $selectedPatient['address'] }}</div>
                                @endif
                            </div>
                            <x-secondary-button type="button" wire:click="changePatient" class="px-3 py-1 text-xs shrink-0">
                                Ganti
                            </x-secondary-button>
                        </div>
                    @else
                        {{-- Cari + daftar pasien aktif --}}
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="patientSearch" class="block w-full pl-10"
                                placeholder="Cari No RM / Nama pasien aktif..." />
                        </div>

                        <div class="mt-2 overflow-y-auto border divide-y border-hairline rounded-xl divide-hairline-soft dark:border-gray-700 dark:divide-gray-800 max-h-64">
                            @forelse ($this->activePatients as $pasien)
                                @php
                                    $sexLabel = $pasien->sex === 'L' ? 'Laki-Laki' : ($pasien->sex === 'P' ? 'Perempuan' : '-');
                                    $identity = [
                                        'reg_no' => $pasien->reg_no,
                                        'reg_name' => $pasien->reg_name,
                                        'sex' => $pasien->sex,
                                        'birth_date' => $pasien->birth_date,
                                        'umur_format' => $pasien->umur_format,
                                        'address' => $pasien->address,
                                    ];
                                @endphp
                                <button type="button" wire:key="ap-{{ $source }}-{{ $pasien->ref_no }}"
                                    wire:click="selectPatient({{ $pasien->ref_no }}, @js($identity))"
                                    class="flex flex-col w-full px-4 py-3 space-y-0 leading-tight text-left transition hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <span class="text-base font-medium text-body dark:text-gray-300">{{ $pasien->reg_no }}</span>
                                    <span class="text-lg font-semibold text-brand dark:text-white">
                                        {{ $pasien->reg_name }} / ({{ $sexLabel }})
                                    </span>
                                    <span class="text-sm text-body dark:text-gray-400">
                                        {{ $pasien->birth_date ?? '-' }}
                                        @if (!empty($pasien->umur_format))
                                            <span class="text-muted">({{ $pasien->umur_format }})</span>
                                        @endif
                                    </span>
                                    @if (!empty($pasien->address))
                                        <span class="text-sm text-muted dark:text-gray-400">{{ $pasien->address }}</span>
                                    @endif
                                </button>
                            @empty
                                <div class="px-4 py-6 text-sm text-center text-muted-soft dark:text-gray-600">
                                    Tidak ada pasien aktif
                                    @if (trim($patientSearch) !== '')
                                        cocok dengan "{{ $patientSearch }}"
                                    @endif
                                    .
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                {{-- STEP 2: PILIH PEMERIKSAAN (aktif setelah pasien dipilih) --}}
                <div @class(['opacity-50 pointer-events-none' => !$selectedRefNo])>
                    @if (empty($formEntryRad['radId']))
                        <div wire:key="lov-rad-tambah-{{ $lovKey }}">
                            <livewire:lov.radiologi.lov-radiologi target="radiologi-tambah"
                                label="2. Pemeriksaan Radiologi" placeholder="Ketik kode/nama radiologi..."
                                wire:key="lov-rad-tambah-inner-{{ $lovKey }}" />
                        </div>
                    @else
                        <x-input-label value="2. Pemeriksaan Radiologi" class="mb-1.5" />
                        <div class="p-4 space-y-3 border border-hairline rounded-xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                            <div class="flex items-end gap-3">
                                <div class="w-28">
                                    <x-input-label value="Kode" class="mb-1 text-xs" />
                                    <x-text-input wire:model="formEntryRad.radId" disabled class="w-full text-sm" />
                                </div>
                                <div class="flex-1">
                                    <x-input-label value="Pemeriksaan" class="mb-1 text-xs" />
                                    <x-text-input wire:model="formEntryRad.radDesc" disabled class="w-full text-sm" />
                                </div>
                                <div class="w-40">
                                    <x-input-label value="Tarif" class="mb-1 text-xs" />
                                    <x-text-input-number wire:model="formEntryRad.radPrice"
                                        :error="$errors->has('formEntryRad.radPrice')" />
                                </div>
                                <x-secondary-button type="button" wire:click="clearRad" class="px-3 py-2 text-xs shrink-0">
                                    Ganti
                                </x-secondary-button>
                            </div>
                            @error('formEntryRad.radPrice')
                                <x-input-error :messages="$message" />
                            @enderror

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Dokter Pengirim" class="mb-1 text-xs" />
                                    <x-select-input wire:model="drId" class="text-sm">
                                        <option value="">— Pilih dokter pengirim —</option>
                                        @foreach ($this->relatedDoctors as $dr)
                                            <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Keterangan Klinis" required class="mb-1 text-xs" />
                                    <x-textarea wire:model="klinisDesc" rows="2"
                                        placeholder="Diagnosis kerja / keterangan klinis pasien..."
                                        :error="$errors->has('klinisDesc')" class="text-sm" />
                                    @error('klinisDesc')
                                        <x-input-error :messages="$message" class="mt-1" />
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeTambahModal">Tutup</x-secondary-button>
                <x-primary-button type="button" wire:click="insertRad" wire:loading.attr="disabled"
                    wire:target="insertRad" :disabled="!$selectedRefNo || empty($formEntryRad['radId'])">
                    <span wire:loading.remove wire:target="insertRad" class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah
                    </span>
                    <span wire:loading wire:target="insertRad"><x-loading class="w-4 h-4" /></span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
