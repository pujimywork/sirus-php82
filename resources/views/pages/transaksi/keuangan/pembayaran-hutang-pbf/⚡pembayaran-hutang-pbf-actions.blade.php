<?php

/**
 * Pembayaran Hutang PBF — Modal Proses Pembayaran.
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_angsuran (sirus):
 *   1. Validasi: bayar <= total sisa hutang nota terpilih
 *   2. INSERT 1x IMTXN_CASHOUTHDRS (master cashout)
 *   3. Loop nota terpilih (check_boxstatus='1') urut VCOUNT (urutan klik user):
 *        - Kalau bayar >= sisa nota: bayar full → INSERT cashoutdtls + receivepayments,
 *          UPDATE receivehdrs.rcv_status='L', kurangi bayar
 *        - Kalau bayar < sisa nota & bayar > 0: bayar partial (cicilan) → INSERT
 *          cashoutdtls + receivepayments dgn nominal sisa bayar, status tetap 'H'
 *   4. Reset check_boxstatus='0' & vcount=null untuk supplier ini
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $suppId   = null;
    public ?string $suppName = null;
    public float   $sisaChecked = 0.0;
    public int     $jumlah  = 0;

    public ?string $tanggal     = null;
    public ?string $accId       = null; // akun cash-out (acmst_accounts.acc_id)
    public ?string $keterangan  = null;
    public ?int    $bayar       = null;

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open modal ── */
    #[On('hutang-pbf.openProsesBayar')]
    public function openProsesBayar(string $suppId, string $suppName, float $sisaChecked, int $jumlah = 0): void
    {
        $this->resetFormFields();
        $this->suppId      = $suppId;
        $this->suppName    = $suppName;
        $this->sisaChecked = $sisaChecked;
        $this->jumlah      = $jumlah;
        $this->tanggal     = Carbon::now()->format('d/m/Y H:i:s');
        $this->bayar       = (int) $sisaChecked; // default: lunasi semua yg dicentang
        $this->keterangan  = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-hutang-pbf-actions');
        $this->dispatch('focus-bayar-tanggal');
    }

    /* ── LOV Listener (lov-kas) ── */
    #[On('lov.selected.kas-hutang-pbf')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
    }

    /* ── Proses pembayaran ── */
    public function processBayar(): void
    {
        $this->validate(
            [
                'suppId'   => 'required|string',
                'tanggal'  => 'required|date_format:d/m/Y H:i:s',
                'accId'    => 'required|string|exists:acmst_accounts,acc_id',
                'bayar'    => 'required|integer|min:1',
            ],
            [
                'suppId.required'      => 'Supplier tidak terdeteksi.',
                'tanggal.required'     => 'Tanggal wajib diisi.',
                'tanggal.date_format'  => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'accId.required'       => 'Akun pengeluaran wajib dipilih.',
                'accId.exists'         => 'Akun pengeluaran tidak valid.',
                'bayar.required'       => 'Nominal bayar wajib diisi.',
                'bayar.min'            => 'Nominal bayar minimal Rp 1.',
            ],
        );

        $bayar = (int) $this->bayar;

        if ($bayar > (int) $this->sisaChecked) {
            $this->dispatch('toast', type: 'error',
                message: 'Nominal bayar (Rp ' . number_format($bayar)
                    . ') melebihi total sisa hutang nota terpilih (Rp ' . number_format($this->sisaChecked) . ').');
            return;
        }

        // Resolve emp_id dari USERS (sirus IMTXN_CASHOUTHDRS pakai EMP_ID)
        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil pegawai Anda belum di-mapping (emp_id). Hubungi admin.');
            return;
        }

        // Resolve shift dari RSTXN_SHIFTCTLS by jam tanggal pembayaran
        $jam = Carbon::createFromFormat('d/m/Y H:i:s', $this->tanggal)->format('H:i:s');
        $shift = DB::table('rstxn_shiftctls')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift') ?? '1';

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";

        $totalDibayarkan = 0;
        $notaLunas = 0;
        $notaCicilan = 0;

        try {
            DB::transaction(function () use ($empId, $shift, $tanggalDb, &$totalDibayarkan, &$notaLunas, &$notaCicilan, $bayar) {
                // Re-fetch nota terpilih dgn lock, urut VCOUNT (urutan klik user)
                $headers = DB::table('imtxn_receivehdrs')
                    ->where('supp_id', $this->suppId)
                    ->where('rcv_status', 'H')
                    ->where('check_boxstatus', '1')
                    ->orderBy('vcount')
                    ->orderBy('rcv_no')
                    ->lockForUpdate()
                    ->get();

                if ($headers->isEmpty()) {
                    throw new \RuntimeException('Tidak ada nota yang ditandai untuk dibayar (check_boxstatus=1).');
                }

                // Insert master cashout (1x)
                $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;
                $desc = 'Angsuran Atas Nama "' . $this->suppName . '"'
                    . (!empty($this->keterangan) ? ' - ' . $this->keterangan : '');

                DB::table('imtxn_cashouthdrs')->insert([
                    'acc_id'        => $this->accId,
                    'cashout_no'    => $cashoutNo,
                    'cashout_date'  => DB::raw($tanggalDb),
                    'cashout_desc'  => $desc,
                    'cashout_value' => $bayar,
                    'emp_id'        => $empId,
                    'supp_id'       => $this->suppId,
                    'shift'         => $shift,
                ]);

                $sisaBayar = $bayar;

                foreach ($headers as $hdr) {
                    if ($sisaBayar <= 0) break;

                    $rcvNo = $hdr->rcv_no;

                    // Hitung sisa nota ini (re-compute)
                    $totalDetail = (float) DB::table('imtxn_receivedtls')
                        ->where('rcv_no', $rcvNo)
                        ->select(DB::raw("
                            NVL(SUM(
                                (NVL(qty,0)*NVL(cost_price,0))
                                - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                                - NVL(dtl_diskon,0)
                                - (((NVL(qty,0)*NVL(cost_price,0))
                                    - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                                    - NVL(dtl_diskon,0))
                                  * NVL(dtl_persen1,0)/100)
                                - NVL(dtl_diskon1,0)
                            ),0) as t"))
                        ->value('t');

                    $setelahDiskon = $totalDetail - (float) ($hdr->rcv_diskon ?? 0);
                    $ppn = ((string) ($hdr->rcv_ppn_status ?? '1')) === '1'
                        ? ($setelahDiskon * (float) ($hdr->rcv_ppn ?? 0)) / 100
                        : 0;
                    // Cast ke int rupiah sejak awal — selisih sub-1 rupiah dari kaskade
                    // PPN/diskon nggak signifikan & bikin status nyangkut di 'H'.
                    $grandTotal = (int) round($setelahDiskon + $ppn + (float) ($hdr->rcv_materai ?? 0));

                    $titipan = (int) round((float) DB::table('imtxn_receivepayments')
                        ->where('rcv_no', $rcvNo)
                        ->sum('rcvp_value'));

                    $sisaNota = $grandTotal - $titipan;
                    if ($sisaNota <= 0) continue;

                    if ($sisaBayar >= $sisaNota) {
                        // Bayar full → lunas
                        $nominalBayar = $sisaNota;
                        $rcvStatusBaru = 'L';
                        $payDateRaw = DB::raw($tanggalDb);
                        $notaLunas++;
                    } else {
                        // Bayar partial → cicilan, status tetap H
                        $nominalBayar = $sisaBayar;
                        $rcvStatusBaru = 'H';
                        $payDateRaw = null;
                        $notaCicilan++;
                    }

                    DB::table('imtxn_receivepayments')->insert([
                        'rcvp_no'    => DB::raw('rcvp_seq.nextval'),
                        'rcv_no'     => $rcvNo,
                        'rcvp_date'  => DB::raw($tanggalDb),
                        'rcvp_value' => $nominalBayar,
                        'emp_id'     => $empId,
                    ]);

                    DB::table('imtxn_cashoutdtls')->insert([
                        'cashout_no'  => $cashoutNo,
                        'rcv_no'      => $rcvNo,
                        'cashout_dtl' => DB::raw('codtl_seq.nextval'),
                    ]);

                    $updateData = ['rcv_status' => $rcvStatusBaru];
                    if ($payDateRaw !== null) {
                        $updateData['pay_date'] = $payDateRaw;
                    }

                    DB::table('imtxn_receivehdrs')
                        ->where('rcv_no', $rcvNo)
                        ->update($updateData);

                    $totalDibayarkan += $nominalBayar;
                    $sisaBayar -= $nominalBayar;
                }

                // Reset check_boxstatus & vcount untuk supplier (ekuivalen "menetralkan transaksi")
                DB::table('imtxn_receivehdrs')
                    ->where('supp_id', $this->suppId)
                    ->update([
                        'check_boxstatus' => '0',
                        'vcount'          => null,
                    ]);
            });

            $msg = "Pembayaran Rp " . number_format($totalDibayarkan) . " berhasil. ";
            $msg .= "{$notaLunas} nota lunas";
            if ($notaCicilan > 0) $msg .= ", {$notaCicilan} nota cicilan";
            $msg .= '.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->closeModal();
            $this->dispatch('hutang-pbf.paid');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-hutang-pbf-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['suppId', 'suppName', 'sisaChecked', 'jumlah', 'tanggal', 'accId', 'keterangan', 'bayar']);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pembayaran-hutang-pbf-actions" size="2xl" focusable>
        <div class="flex flex-col" wire:key="{{ $this->renderKey('modal', [$suppId]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                            Proses Pembayaran Hutang PBF
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Sistem akan mengalokasikan pembayaran ke <strong>{{ $jumlah }}</strong> nota yang Anda centang (FIFO — nota terlama dulu).
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="px-6 py-5 space-y-5"
                x-data
                x-on:focus-bayar-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))">

                {{-- Info Supplier --}}
                <div class="px-4 py-3 border border-hairline rounded-lg bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted dark:text-gray-400">Supplier</div>
                            <div class="text-base font-semibold text-ink dark:text-gray-100">
                                {{ $suppName ?? '-' }}
                            </div>
                            <div class="font-mono text-xs text-muted">Kode: {{ $suppId ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted dark:text-gray-400">Total Sisa Hutang Terpilih</div>
                            <div class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400">
                                Rp {{ number_format($sisaChecked) }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tanggal --}}
                <div>
                    <x-input-label value="Tanggal Pembayaran" :required="true" />
                    <x-text-input type="text" wire:model="tanggal" placeholder="dd/mm/yyyy hh:mm:ss"
                        x-ref="inputTanggal" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                </div>

                {{-- Akun Kas (sumber dana cashout) --}}
                <div>
                    <livewire:lov.kas.lov-kas
                        target="kas-hutang-pbf"
                        tipe=""
                        label="Akun Kas"
                        placeholder="Ketik kode/nama kas..."
                        :initialAccId="$accId"
                        wire:key="lov-kas-hutang-pbf-{{ $suppId ?? 'empty' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                </div>

                {{-- Nominal Bayar --}}
                <div>
                    <x-input-label value="Nominal Bayar (Rp)" :required="true" />
                    <x-text-input-number wire:model="bayar" />
                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                        Default = total sisa nota terpilih. Bisa kurang dari itu untuk angsuran (sisa nota tetap berstatus Hutang).
                    </p>
                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                </div>

                {{-- Keterangan --}}
                <div>
                    <x-input-label value="Keterangan (opsional)" />
                    <x-text-input type="text" wire:model="keterangan" placeholder="Keterangan tambahan..."
                        class="w-full mt-1" />
                </div>

                {{-- Note --}}
                <div class="px-4 py-3 border rounded-lg border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-xs text-amber-800 dark:text-amber-300">
                            <p class="font-semibold">Cara alokasi pembayaran:</p>
                            <ul class="mt-1 ml-5 space-y-0.5 list-disc">
                                <li>Nota dialokasikan sesuai <strong>urutan klik</strong> (badge angka di tabel) — jadi user yang menentukan nota mana yang dilunasi duluan</li>
                                <li>Nominal bayar dialokasikan penuh sampai nota lunas, sisa diteruskan ke nota berikutnya</li>
                                <li>Kalau nominal kurang dari total sisa, nota terakhir akan menjadi <strong>cicilan</strong> (status tetap Hutang)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="processBayar"
                        wire:loading.attr="disabled" wire:target="processBayar">
                        <span wire:loading.remove wire:target="processBayar">Proses Pembayaran</span>
                        <span wire:loading wire:target="processBayar">Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
