<?php

/**
 * Topup Supplier PBF (medis).
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_topup:
 *   - Input: supplier (filter medis) + akun kas + tanggal + nominal + keterangan
 *   - INSERT 1x IMTXN_CASHOUTHDRTOPUPS (saldo titipan, tidak link ke nota)
 *   - Saldo bisa dipakai sebagai DP saat penerimaan barang berikutnya
 *
 * Beda dgn pembayaran-hutang-pbf (angsuran):
 *   - Topup tidak link ke rcv_no spesifik (cuma cashouthdrtopups)
 *   - Tidak ada cashoutdtls / receivepayments
 *   - Hanya pencatatan saldo deposit
 *
 * Sumber tabel:
 *   - IMTXN_CASHOUTHDRTOPUPS  (sirus, medis)
 *   - IMMST_SUPPLIERS         (filter medis='Y')
 *   - ACMST_ACCOUNTS          (via lov-kas, mapping per user)
 *   - RSTXN_SHIFTCTLS         (resolve shift dari jam)
 *   - cashout_seq             (Oracle sequence)
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $suppId   = null;
    public ?array  $supplier = null;
    public ?string $accId    = null;

    public ?string $tanggal    = null;
    public ?string $keterangan = null;
    public ?int    $nominal    = null;

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['form']);
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
    }

    /* ── LOV Listeners ── */
    #[On('lov.selected.topup-pbf-supplier')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId   = $payload['supp_id'] ?? null;
        $this->supplier = $payload;
        $this->resetErrorBag('suppId');
    }

    #[On('lov.selected.topup-pbf-kas')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->resetErrorBag('accId');
    }

    public function clearSupplier(): void
    {
        $this->reset(['suppId', 'supplier', 'accId', 'nominal', 'keterangan']);
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('form');
    }

    /* ── Riwayat topup terakhir untuk supplier terpilih ── */
    #[Computed]
    public function history()
    {
        if (!$this->suppId) return collect();

        return DB::table('imtxn_cashouthdrtopups')
            ->select([
                'cashout_no',
                DB::raw("TO_CHAR(cashout_date,'dd/mm/yyyy hh24:mi') as tgl_display"),
                'cashout_desc',
                'cashout_value',
                'acc_id',
                'shift',
            ])
            ->where('supp_id', $this->suppId)
            ->orderByDesc('cashout_date')
            ->orderByDesc('cashout_no')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function totalTopup(): int
    {
        if (!$this->suppId) return 0;
        return (int) DB::table('imtxn_cashouthdrtopups')
            ->where('supp_id', $this->suppId)
            ->sum('cashout_value');
    }

    /* ── Simpan topup ── */
    public function simpan(): void
    {
        $this->validate(
            [
                'suppId'   => 'required|string',
                'tanggal'  => 'required|date_format:d/m/Y H:i:s',
                'accId'    => 'required|string|exists:acmst_accounts,acc_id',
                'nominal'  => 'required|integer|min:1',
            ],
            [
                'suppId.required'      => 'Supplier wajib dipilih.',
                'tanggal.required'     => 'Tanggal wajib diisi.',
                'tanggal.date_format'  => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'accId.required'       => 'Akun kas wajib dipilih.',
                'accId.exists'         => 'Akun kas tidak valid.',
                'nominal.required'     => 'Nominal wajib diisi.',
                'nominal.min'          => 'Nominal minimal Rp 1.',
            ],
        );

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil pegawai Anda belum di-mapping (emp_id). Hubungi admin.');
            return;
        }

        $jam = Carbon::createFromFormat('d/m/Y H:i:s', $this->tanggal)->format('H:i:s');
        $shift = DB::table('rstxn_shiftctls')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift') ?? '1';

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";
        $desc = 'Topup Atas Nama "' . ($this->supplier['supp_name'] ?? '-') . '"'
              . (!empty($this->keterangan) ? ' - ' . $this->keterangan : '');

        try {
            DB::transaction(function () use ($empId, $shift, $tanggalDb, $desc) {
                $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;

                DB::table('imtxn_cashouthdrtopups')->insert([
                    'cashout_no'    => $cashoutNo,
                    'cashout_date'  => DB::raw($tanggalDb),
                    'cashout_desc'  => $desc,
                    'cashout_value' => (int) $this->nominal,
                    'emp_id'        => $empId,
                    'supp_id'       => $this->suppId,
                    'acc_id'        => $this->accId,
                    'shift'         => $shift,
                ]);
            });

            $this->dispatch('toast', type: 'success',
                message: 'Topup Rp ' . number_format($this->nominal) . ' tersimpan untuk ' . ($this->supplier['supp_name'] ?? '-') . '.');

            // reset nominal & keterangan, biarkan supplier+kas tetap (user mungkin mau topup lagi)
            $this->reset(['nominal', 'keterangan']);
            $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
            unset($this->history, $this->totalTopup);
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan topup: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-page-title
        title="Topup Supplier PBF"
        subtitle="Setor DP/uang muka ke supplier obat (saldo titipan, tidak terikat nota tertentu)" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">

            {{-- 1) PILIH SUPPLIER --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-ink dark:text-gray-100">1. Pilih Supplier (PBF)</h3>
                        <p class="mt-1 text-xs text-muted dark:text-gray-400">Cari berdasarkan kode / nama / telp supplier</p>
                    </div>
                    @if ($suppId)
                        <x-secondary-button type="button" wire:click="clearSupplier">Transaksi Baru</x-secondary-button>
                    @endif
                </div>
                <div class="px-5 py-5">
                    <livewire:lov.supplier.lov-supplier
                        target="topup-pbf-supplier"
                        label="Cari Supplier"
                        placeholder="Ketik kode / nama / telp supplier..."
                        jenisSupplier="medis"
                        :initialSuppId="$suppId"
                        wire:key="lov-supplier-topup-pbf-{{ $suppId ?? 'empty' }}" />
                </div>
            </div>

            @if ($suppId)
                {{-- 2) RINGKASAN --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Total Topup Selama Ini</div>
                        <div class="mt-1 font-mono text-2xl font-bold text-success dark:text-success">
                            Rp {{ number_format($this->totalTopup) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Supplier</div>
                        <div class="mt-1 text-base font-semibold text-ink dark:text-gray-100">
                            {{ $supplier['supp_name'] ?? '-' }}
                        </div>
                        <div class="font-mono text-xs text-muted">Kode: {{ $suppId }}</div>
                    </div>
                </div>

                {{-- 3) FORM TOPUP --}}
                <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                    wire:key="{{ $this->renderKey('form', [$suppId]) }}">
                    <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                        <h3 class="text-base font-semibold text-ink dark:text-gray-100">2. Form Topup</h3>
                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                            Isi nominal yang akan disetor ke supplier sebagai saldo titipan/DP.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Tanggal" :required="true" />
                            <x-text-input type="text" wire:model="tanggal" placeholder="dd/mm/yyyy hh:mm:ss" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                        </div>

                        <div>
                            <livewire:lov.kas.lov-kas
                                target="topup-pbf-kas"
                                tipe=""
                                label="Akun Kas"
                                placeholder="Ketik kode/nama kas..."
                                :initialAccId="$accId"
                                wire:key="lov-kas-topup-pbf-{{ $suppId }}-{{ $renderVersions['form'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Nominal Topup (Rp)" :required="true" />
                            <x-text-input-number wire:model="nominal" />
                            <x-input-error :messages="$errors->get('nominal')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Keterangan (opsional)" />
                            <x-text-input type="text" wire:model="keterangan"
                                placeholder="Misal: DP order obat batch 2026-05" class="w-full mt-1" />
                        </div>
                    </div>

                    <div class="px-5 py-4 bg-surface-soft border-t border-hairline dark:border-gray-700 dark:bg-gray-800/50 rounded-b-2xl">
                        <div class="flex items-center justify-end gap-3">
                            <x-primary-button type="button" wire:click="simpan"
                                wire:loading.attr="disabled" wire:target="simpan">
                                <span wire:loading.remove wire:target="simpan">Simpan Topup</span>
                                <span wire:loading wire:target="simpan">Memproses...</span>
                            </x-primary-button>
                        </div>
                    </div>
                </div>

                {{-- 4) RIWAYAT --}}
                <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                        <h3 class="text-base font-semibold text-ink dark:text-gray-100">3. Riwayat Topup (20 terakhir)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="px-3 py-2 font-semibold">No</th>
                                    <th class="px-3 py-2 font-semibold">Tanggal</th>
                                    <th class="px-3 py-2 font-semibold">Keterangan</th>
                                    <th class="px-3 py-2 font-semibold">Akun Kas</th>
                                    <th class="px-3 py-2 font-semibold">Shift</th>
                                    <th class="px-3 py-2 font-semibold text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @forelse($this->history as $row)
                                    <tr wire:key="topup-pbf-{{ $row->cashout_no ?? $loop->index }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->cashout_no }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $row->tgl_display }}</td>
                                        <td class="px-3 py-2">{{ $row->cashout_desc ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono">{{ $row->acc_id }}</td>
                                        <td class="px-3 py-2">{{ $row->shift ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono font-semibold text-right text-emerald-700">
                                            {{ number_format($row->cashout_value) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-muted dark:text-gray-400">
                                            Belum ada riwayat topup untuk supplier ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="px-6 py-16 text-center bg-canvas border border-hairline border-dashed rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-muted dark:text-gray-400">
                        Pilih supplier terlebih dahulu untuk mulai topup.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
