<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kasir-ugd'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    // ── Ringkasan Biaya ──
    public int $rjTotal = 0;
    public ?int $rjDiskon = 0;
    public int $dspTotalAll = 0;
    public int $sudahBayar = 0;
    public int $rjSisa = 0;

    // ── Input Kasir ──
    public ?string $accId = null;
    public ?string $accName = null;
    public ?int $bayar = null;
    public int $kembalian = 0;

    // ── Status Transaksi ──
    public ?string $txnStatus = null;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->loadKasirUGD($this->rjNo);
        } else {
            $this->isFormLocked = false;
        }
    }

    /* ===============================
     | LISTENER
     =============================== */
    #[On('administrasi-kasir-ugd.updated')]
    public function onAdministrasiKasirUpdated(): void
    {
        if ($this->rjNo) {
            $this->loadKasirUGD($this->rjNo);
        }
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-kasir-ugd')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        $this->dispatch('focus-input-bayar');
    }

    /* ===============================
     | LOAD KASIR
     =============================== */
    public function loadKasirUGD($rjNo): void
    {
        $this->resetKasir();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $this->dataDaftarUGD = $this->findDataUGD($rjNo) ?? [];

        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $hdr = DB::table('rstxn_ugdhdrs')->select('rj_status', 'txn_status', 'rj_diskon', 'acc_id')->where('rj_no', $rjNo)->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->txnStatus = $hdr->txn_status;
        $this->rjDiskon = (int) ($hdr->rj_diskon ?? 0);

        if ($hdr->acc_id) {
            $this->accId = $hdr->acc_id;
            $this->accName = DB::table('acmst_accounts')->where('acc_id', $hdr->acc_id)->value('acc_name') ?? $hdr->acc_id;
        }

        $this->hitungTotal();
        $this->incrementVersion('kasir-ugd');
    }

    private function resetKasir(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD', 'bayar', 'accId', 'accName', 'txnStatus']);
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->rjTotal = 0;
        $this->rjDiskon = 0;
        $this->dspTotalAll = 0;
        $this->sudahBayar = 0;
        $this->rjSisa = 0;
        $this->kembalian = 0;
    }

    /* ===============================
     | HITUNG TOTAL
     =============================== */
    public function hitungTotal(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $rjNo = $this->rjNo;
        $hdr = DB::table('rstxn_ugdhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        $this->rjTotal = (int) ($hdr->rs_admin ?? 0) + (int) ($hdr->rj_admin ?? 0) + (int) ($hdr->poli_price ?? 0) + (int) DB::table('rstxn_ugdactemps')->where('rj_no', $rjNo)->sum('acte_price') + (int) DB::table('rstxn_ugdaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price') + (int) DB::table('rstxn_ugdactparams')->where('rj_no', $rjNo)->sum('pact_price') + (int) DB::table('rstxn_ugdobats')->where('rj_no', $rjNo)->selectRaw('nvl(sum(qty * price), 0) as total')->value('total') + (int) DB::table('rstxn_ugdlabs')->where('rj_no', $rjNo)->sum('lab_price') + (int) DB::table('rstxn_ugdrads')->where('rj_no', $rjNo)->sum('rad_price') + (int) DB::table('rstxn_ugdothers')->where('rj_no', $rjNo)->sum('other_price');

        $this->recalcSisa();
    }

    private function recalcSisa(): void
    {
        $this->dspTotalAll = max(0, $this->rjTotal - $this->rjDiskon);
        $this->sudahBayar = (int) DB::table('rstxn_ugdcashins')->where('rj_no', $this->rjNo)->sum('rjc_nominal');
        $this->rjSisa = max(0, $this->dspTotalAll - $this->sudahBayar);
        $this->hitungKembalian();
    }

    /* ===============================
     | REAKTIF
     =============================== */
    public function updatedRjDiskon(): void
    {
        $this->rjDiskon = max(0, (int) $this->rjDiskon);
        $this->recalcSisa();
    }

    public function updatedBayar(): void
    {
        $this->hitungKembalian();
    }

    private function hitungKembalian(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $this->kembalian = $bayar >= $this->rjSisa ? $bayar - $this->rjSisa : 0;
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'accId' => ['required', 'string'],
            'bayar' => ['required', 'integer', 'min:1'],
            'rjDiskon' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'accId.required' => 'Akun kas belum dipilih.',
            'bayar.required' => 'Kolom Bayar masih kosong.',
            'bayar.min' => 'Nominal bayar harus lebih dari 0.',
        ];
    }

    /* ===============================
     | POST TRANSAKSI
     =============================== */
    public function postTransaksi(): void
    {
        // 1. Read-only guard
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // 2. Cek akun kas user — pakai user_kas (bukan smmst_kases)
        $cekAkunKas = DB::table('user_kas')
            ->where('user_id', auth()->id())
            ->count();

        if ($cekAkunKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        // 3. Guard header
        if (!DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // 4. Cek status UGD sebelum lock
        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'info', message: 'Data sudah diproses.');
            return;
        }

        // 5. Validasi form
        $this->validate();

        // 6. Cek lab pending
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab belum selesai, pembayaran tidak bisa diproses.');
            return;
        }

        // 7. Ambil emp_id dari users — tidak perlu query smmst_users lagi
        $empId = auth()->user()->emp_id;

        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $bayar = (int) $this->bayar;
        $dspTotalAll = $this->rjSisa;
        $newTxnStatus = null;

        try {
            DB::transaction(function () use ($bayar, $dspTotalAll, $empId, &$newTxnStatus) {
                $this->lockUGDRow($this->rjNo);

                if ($this->checkUGDStatus($this->rjNo)) {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $rjHdr = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->first();

                $cashRow = [
                    'acc_id' => $this->accId,
                    'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
                    'rjc_date' => $rjHdr->rj_date,
                    'rjc_desc' => $rjHdr->reg_no . ' / ' . $rjHdr->rj_no,
                    'emp_id' => $empId,
                    'rj_no' => $this->rjNo,
                    'shift' => $rjHdr->shift,
                ];

                if ($bayar < $dspTotalAll) {
                    // CICILAN
                    DB::table('rstxn_ugdcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $bayar]));
                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'H',
                            'pay_date' => null,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'H';
                } else {
                    // LUNAS
                    if ($this->rjTotal > 0) {
                        DB::table('rstxn_ugdcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $dspTotalAll]));
                    }
                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'L',
                            'pay_date' => $rjHdr->rj_date,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'L';

                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $rjHdr->reg_no)
                        ->update(['lockstatus' => null]);
                }
            });

            $this->txnStatus = $newTxnStatus;
            $this->hitungTotal();
            $this->isFormLocked = true;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->incrementVersion('kasir-ugd');

            $msg = $newTxnStatus === 'L' ? 'Pembayaran lunas berhasil disimpan.' : 'Pembayaran sebagian (cicilan) berhasil disimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('administrasi-ugd.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // Cek lab pending sebelum batal
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab belum selesai, transaksi tidak bisa dibatalkan.');
            return;
        }

        $hdr = DB::table('rstxn_ugdhdrs')->select('rj_status', 'txn_status', 'reg_no')->where('rj_no', $this->rjNo)->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($hdr) {
                DB::table('rstxn_ugdcashins')->where('rj_no', $this->rjNo)->delete();

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'txn_status' => 'A',
                        'pay_date' => null,
                        'acc_id' => null,
                        'rj_diskon' => 0,
                        'rj_status' => 'A',
                        'emp_id' => null,
                    ]);

                if ($hdr->reg_no) {
                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $hdr->reg_no)
                        ->update(['lockstatus' => null]);
                }
            });

            $this->txnStatus = null;
            $this->rjDiskon = 0;
            $this->accId = null;
            $this->accName = null;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->isFormLocked = false;

            $this->hitungTotal();
            $this->incrementVersion('kasir-ugd');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('administrasi-ugd.updated');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('kasir-ugd', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Transaksi sudah lunas — data terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- RINGKASAN BIAYA --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
        <div class="flex items-stretch gap-3">

            <div
                class="flex-1 px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Tagihan</p>
                <p class="text-base font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format($rjTotal) }}</p>
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div
                class="flex-1 px-4 py-3 border border-amber-200 rounded-xl dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                <p class="mb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    Diskon @if (!$isFormLocked)
                        <span class="opacity-60">(dapat diubah)</span>
                    @endif
                </p>
                @if (!$isFormLocked)
                    <x-text-input wire:model.live="rjDiskon" type="number" min="0"
                        class="w-full px-0 py-0 text-base font-bold text-amber-700 bg-transparent border-0
                            dark:text-amber-300 focus:ring-0 focus:outline-none
                            [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none
                            [&::-webkit-inner-spin-button]:appearance-none"
                        placeholder="0" x-on:keyup.enter="$dispatch('focus-lov-kas-kasir-ugd')" />
                    <x-input-error :messages="$errors->get('rjDiskon')" class="mt-1" />
                @else
                    <p class="text-base font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($rjDiskon) }}
                    </p>
                @endif
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div
                class="flex-1 px-4 py-3 border border-blue-200 rounded-xl dark:border-blue-800/40 bg-blue-50 dark:bg-blue-900/10">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-0.5">Setelah Diskon</p>
                <p class="text-base font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($dspTotalAll) }}</p>
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            @if ($sudahBayar > 0)
                <div
                    class="flex-1 px-4 py-3 border border-violet-200 rounded-xl dark:border-violet-800/40 bg-violet-50 dark:bg-violet-900/10">
                    <p class="text-xs text-violet-600 dark:text-violet-400 mb-0.5">Sudah Dibayar</p>
                    <p class="text-base font-bold text-violet-700 dark:text-violet-300">Rp
                        {{ number_format($sudahBayar) }}</p>
                </div>
                <div class="flex items-center text-gray-300 dark:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            @endif

            <div
                class="flex-1 px-4 py-3 border rounded-xl
                {{ $rjSisa > 0
                    ? 'border-rose-200 dark:border-rose-800/40 bg-rose-50 dark:bg-rose-900/10'
                    : 'border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10' }}">
                <p
                    class="text-xs mb-0.5 {{ $rjSisa > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    Sisa Tagihan
                </p>
                <p
                    class="text-base font-bold {{ $rjSisa > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    Rp {{ number_format($rjSisa) }}
                </p>
            </div>

        </div>
    </div>

    {{-- FORM INPUT PEMBAYARAN --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-input-bayar.window="$nextTick(() => $refs.inputBayar?.focus())">

        @if ($isFormLocked)
            <div class="flex items-center justify-between">
                <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
                <x-confirm-button variant="danger" :action="'batalTransaksi()'" title="Batal Transaksi"
                    message="Yakin ingin membatalkan transaksi? Semua data pembayaran akan dihapus."
                    confirmText="Ya, batalkan" cancelText="Batal">
                    Batal Transaksi
                </x-confirm-button>
            </div>
        @else
            <div class="flex items-end gap-3">

                {{-- LOV Akun Kas — tipe="ugd" agar hanya tampil kas aktif untuk UGD --}}
                <div class="w-80"
                    x-on:focus-lov-kas-kasir-ugd.window="$nextTick(() => $el.querySelector('input')?.focus())">
                    <livewire:lov.kas.lov-kas target="kas-kasir-ugd" tipe="ugd" label="Akun Kas" :initialAccId="$accId"
                        wire:key="lov-kas-kasir-ugd-{{ $rjNo }}-{{ $renderVersions['kasir-ugd'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                </div>

                {{-- Input Bayar --}}
                <div class="w-52">
                    <x-input-label value="Nominal Bayar (Rp)" class="mb-1" />
                    <x-text-input type="number" wire:model.live="bayar" placeholder="0"
                        class="w-full font-mono text-right" min="1" x-ref="inputBayar"
                        x-on:keyup.enter="$wire.postTransaksi()" />
                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                </div>

                {{-- Kembalian / Kurang Bayar --}}
                @if ((int) ($bayar ?? 0) >= $rjSisa && $rjSisa > 0)
                    <div
                        class="flex-1 px-4 py-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10">
                        <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kembalian</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">Rp
                            {{ number_format($kembalian) }}</p>
                    </div>
                @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $rjSisa)
                    <div
                        class="flex-1 px-4 py-2.5 rounded-xl border border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                        <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Kurang Bayar</p>
                        <p class="text-lg font-bold text-amber-700 dark:text-amber-300">Rp
                            {{ number_format($rjSisa - (int) ($bayar ?? 0)) }}</p>
                    </div>
                @else
                    <div class="flex-1"></div>
                @endif

                {{-- Tombol Post --}}
                <div class="flex gap-2 pb-0.5">
                    <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled"
                        wire:target="postTransaksi">
                        <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                        <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                    </x-primary-button>
                </div>

            </div>

            {{-- Badge status pembayaran --}}
            @if ((int) ($bayar ?? 0) >= $rjSisa && $rjSisa > 0)
                <div class="flex items-center gap-1.5 mt-3">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        Pembayaran akan diproses sebagai LUNAS
                    </span>
                </div>
            @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $rjSisa)
                <div class="flex items-center gap-1.5 mt-3">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                        Pembayaran akan diproses sebagai CICILAN — sisa Rp
                        {{ number_format($rjSisa - (int) ($bayar ?? 0)) }}
                    </span>
                </div>
            @endif
        @endif

    </div>

    {{-- TABEL RIWAYAT PEMBAYARAN --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Pembayaran</h3>
            @php $cashins = DB::table('rstxn_ugdcashins')->where('rj_no', $rjNo)->orderBy('rjc_date')->get(); @endphp
            <x-badge variant="gray">{{ $cashins->count() }} transaksi</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Akun Kas</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($cashins as $cash)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ Carbon::parse($cash->rjc_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $cash->acc_id }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $cash->rjc_desc }}</td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($cash->rjc_nominal) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada riwayat pembayaran
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($cashins->isNotEmpty())
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">
                                Total Dibayar
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($cashins->sum('rjc_nominal')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
