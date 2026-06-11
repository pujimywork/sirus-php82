<?php
// resources/views/pages/components/rekam-medis/riwayat-kontrol-pasien/riwayat-kontrol-pasien.blade.php
//
// RIWAYAT JADWAL KONTROL PASIEN — modal per-pasien (pola rekam-medis-display:
// satu pasien, semua riwayat lintas RJ+RI, urut terbaru di atas).
// Dibuka via event 'riwayat-kontrol.open' { regNo, regName } dari halaman
// Jadwal Kontrol / Daftar RJ. Tanggal kontrol bisa diedit inline per kartu
// (logika sama dgn modal Ubah Jadwal: push BPJS dulu, gagal = tak disimpan).
//
// Sumber data sama dengan halaman induk: JSON `kontrol` di rstxn_rjhdrs /
// rstxn_rihdrs (Oracle tanpa JSON_VALUE → filter INSTR; kontrol "betulan" =
// noKontrolRS terisi). Per-pasien difilter h.reg_no (index-friendly) jadi
// tidak perlu pembatas tanggal kunjungan.

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRJTrait, EmrRITrait, WithValidationToastTrait;

    public string $regNo = '';
    public string $regName = '';

    /* ── Edit tanggal inline per kartu ── */
    public string $editKey = ''; // "RJ-12345" yang sedang diedit
    public string $tglBaru = '';

    #[On('riwayat-kontrol.open')]
    public function open(string $regNo, string $regName = ''): void
    {
        if (empty($regNo)) {
            return;
        }

        $this->regNo = $regNo;
        $this->regName = $regName;
        $this->batalEdit();
        unset($this->riwayat);

        $this->dispatch('open-modal', name: 'riwayat-kontrol-pasien');
    }

    /* ═══════════════════════════════════════
     | EDIT TANGGAL KONTROL — inline per kartu.
     | Logika sama dgn modal Ubah Jadwal di halaman Jadwal Kontrol:
     | push BPJS DULU (di luar transaksi), gagal → tidak disimpan lokal.
    ═══════════════════════════════════════ */
    public function mulaiEdit(string $sumber, string $trxNo, string $tglSekarang): void
    {
        $this->editKey = "{$sumber}-{$trxNo}";
        $this->tglBaru = $tglSekarang;
        $this->resetValidation();
    }

    public function batalEdit(): void
    {
        $this->editKey = '';
        $this->tglBaru = '';
        $this->resetValidation();
    }

    public function setTglBaruHariIni(): void
    {
        $this->tglBaru = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    protected function rules(): array
    {
        return [
            // Boleh HARI INI (kasus pasien telat) — sama dgn halaman Jadwal Kontrol.
            'tglBaru' => 'required|date_format:d/m/Y|after_or_equal:today',
        ];
    }

    protected function messages(): array
    {
        return [
            'tglBaru.required' => 'Tanggal kontrol baru wajib diisi.',
            'tglBaru.date_format' => 'Format tanggal harus dd/mm/yyyy.',
            'tglBaru.after_or_equal' => 'Tanggal kontrol baru minimal hari ini.',
        ];
    }

    public function simpanTgl(): void
    {
        if (empty($this->editKey) || !str_contains($this->editKey, '-')) {
            $this->dispatch('toast', type: 'error', message: 'Sesi edit tidak valid.');
            return;
        }
        [$sumber, $trxNo] = explode('-', $this->editKey, 2);

        // Guard whitelist sumber — beda sumber beda tabel; nilai tak terduga
        // harus DITOLAK, jangan jatuh ke cabang default.
        if (!in_array($sumber, ['RJ', 'RI'], true)) {
            $this->dispatch('toast', type: 'error', message: "Sumber tidak dikenal: {$sumber}.");
            return;
        }

        $this->validateWithToast();

        // Re-fetch fresh — kontrol bisa berubah sejak modal dibuka
        $data = [];
        if ($sumber === 'RJ') {
            $data = $this->findDataRJ((int) $trxNo) ?? [];
        }
        if ($sumber === 'RI') {
            $data = $this->findDataRI($trxNo) ?? [];
        }
        $kontrol = $data['kontrol'] ?? [];
        if (empty($kontrol['noKontrolRS'])) {
            $this->dispatch('toast', type: 'error', message: 'Data kontrol tidak ditemukan.');
            return;
        }

        $tglLama = $kontrol['tglKontrol'] ?? '-';
        if ($tglLama === $this->tglBaru) {
            $this->dispatch('toast', type: 'info', message: 'Tanggal tidak berubah.');
            return;
        }

        $kontrol['tglKontrol'] = $this->tglBaru;

        // Push update BPJS DULU — gagal berarti perubahan dibatalkan
        $klaimStatus = DB::table('rsmst_klaimtypes')->where('klaim_id', $data['klaimId'] ?? '')->value('klaim_status') ?? 'UMUM';
        if ($klaimStatus === 'BPJS' && !empty($kontrol['noSKDPBPJS'])) {
            $response = VclaimTrait::suratkontrol_update($kontrol)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 0;
            $message = $response['metadata']['message'] ?? '';

            if ($code != 200) {
                $this->dispatch('toast', type: 'error', message: "UPDATE KONTROL BPJS {$code} {$message} — perubahan TIDAK disimpan.");
                return;
            }
            $this->dispatch('toast', type: 'success', message: "UPDATE KONTROL BPJS {$code} {$message}");
        }

        try {
            DB::transaction(function () use ($sumber, $trxNo, $tglLama) {
                // if eksplisit per sumber (BUKAN if/else) — beda tabel, nilai
                // di luar whitelist tidak boleh diam-diam masuk cabang lain.
                if ($sumber === 'RJ') {
                    $this->lockRJRow((int) $trxNo);
                    $fresh = $this->findDataRJ((int) $trxNo) ?? [];
                    $fresh['kontrol']['tglKontrol'] = $this->tglBaru;
                    $this->updateJsonRJ((int) $trxNo, $fresh);
                    $this->appendAdminLogRJ((int) $trxNo, "Ubah tgl jadwal kontrol {$tglLama} → {$this->tglBaru} (Riwayat Kontrol)", 'MR');
                }

                if ($sumber === 'RI') {
                    $this->lockRIRow($trxNo);
                    $fresh = $this->findDataRI($trxNo) ?? [];
                    $fresh['kontrol']['tglKontrol'] = $this->tglBaru;
                    $this->updateJsonRI((int) $trxNo, $fresh);
                    $this->appendAdminLogRI((int) $trxNo, "Ubah tgl jadwal kontrol {$tglLama} → {$this->tglBaru} (Riwayat Kontrol)", 'MR');
                }
            });

            $this->dispatch('toast', type: 'success', message: "Jadwal kontrol diubah: {$tglLama} → {$this->tglBaru}.");
            $this->batalEdit();
            unset($this->riwayat);
            // Beri tahu list induk (mis. halaman Jadwal Kontrol) supaya re-render
            $this->dispatch('riwayat-kontrol.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'riwayat-kontrol-pasien');
    }

    /** Status jadwal relatif hari ini — utk badge per entri. */
    public function statusJadwal(string $tgl): string
    {
        try {
            $tanggal = Carbon::createFromFormat('d/m/Y', $tgl)->startOfDay();
        } catch (\Throwable) {
            return '-';
        }
        $hariIni = Carbon::now(config('app.timezone'))->startOfDay();

        return $tanggal->lt($hariIni) ? 'Lewat' : ($tanggal->eq($hariIni) ? 'Hari Ini' : 'Akan Datang');
    }

    #[Computed]
    public function riwayat()
    {
        if (empty($this->regNo)) {
            return collect();
        }

        $sumberList = [
            ['tabel' => 'rstxn_rjhdrs', 'kolomNo' => 'rj_no', 'kolomJson' => 'datadaftarpolirj_json', 'kolomTgl' => 'rj_date', 'sumber' => 'RJ'],
            ['tabel' => 'rstxn_rihdrs', 'kolomNo' => 'rihdr_no', 'kolomJson' => 'datadaftarri_json', 'kolomTgl' => 'entry_date', 'sumber' => 'RI'],
        ];

        $riwayatList = collect();

        foreach ($sumberList as $sumber) {
            $kunjunganList = DB::table($sumber['tabel'] . ' as h')
                ->where('h.reg_no', $this->regNo)
                ->whereRaw("INSTR(h.{$sumber['kolomJson']}, '\"noKontrolRS\"') > 0")
                ->whereRaw("INSTR(h.{$sumber['kolomJson']}, '\"noKontrolRS\":\"\"') = 0")
                ->select([
                    "h.{$sumber['kolomNo']} as trx_no", // nomor transaksi kunjungan (rj_no / rihdr_no)
                    DB::raw("to_char(h.{$sumber['kolomTgl']},'dd/mm/yyyy') as tgl_kunjungan"), // tgl kunjungan asal (rj_date / entry_date)
                    "h.{$sumber['kolomJson']} as json_daftar", // JSON pendaftaran — berisi objek `kontrol`
                ])
                ->get();

            foreach ($kunjunganList as $kunjungan) {
                $kontrol = json_decode($kunjungan->json_daftar ?? '{}', true)['kontrol'] ?? [];
                if (empty($kontrol['noKontrolRS'])) {
                    continue;
                }
                $riwayatList->push([
                    'sumber' => $sumber['sumber'],
                    'trx_no' => (string) $kunjungan->trx_no,
                    'tgl_kunjungan' => $kunjungan->tgl_kunjungan,
                    'tglKontrol' => $kontrol['tglKontrol'] ?? '-',
                    'poliKontrolDesc' => $kontrol['poliKontrolDesc'] ?? '-',
                    'drKontrolDesc' => $kontrol['drKontrolDesc'] ?? '-',
                    'noSKDPBPJS' => $kontrol['noSKDPBPJS'] ?? '',
                    'noSEP' => $kontrol['noSEP'] ?? '',
                    'catatan' => $kontrol['catatan'] ?? '',
                ]);
            }
        }

        // Terbaru di atas; tanggal tak terparse paling bawah
        return $riwayatList
            ->sortByDesc(function ($entri) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $entri['tglKontrol'])->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values();
    }
};
?>

<div>
    <x-modal name="riwayat-kontrol-pasien" size="full" height="full" focusable>
        <div class="flex flex-col h-full min-h-0">

            {{-- HEADER --}}
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <div>
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                        Riwayat Jadwal Kontrol
                    </h2>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                        <span class="font-semibold text-brand dark:text-brand-lime">{{ $regName ?: '-' }}</span>
                        <span class="ml-1 font-mono text-xs">{{ $regNo }}</span>
                        — seluruh riwayat surat kontrol (SKDP) dari kunjungan RJ &amp; RI
                    </p>
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY: timeline riwayat --}}
            <div class="flex-1 px-6 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                @if ($this->riwayat->isEmpty())
                    <div class="py-10 text-sm text-center text-muted-soft">
                        Belum ada riwayat jadwal kontrol untuk pasien ini.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($this->riwayat as $item)
                            @php $status = $this->statusJadwal($item['tglKontrol']); @endphp
                            <div wire:key="rk-{{ $item['sumber'] }}-{{ $item['trx_no'] }}"
                                class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                @php $kunci = $item['sumber'] . '-' . $item['trx_no']; @endphp
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold {{ $status === 'Lewat' ? 'text-red-600 dark:text-red-400' : 'text-ink dark:text-gray-100' }}">
                                            {{ $item['tglKontrol'] }}
                                        </span>
                                        @if ($status === 'Lewat')
                                            <x-badge variant="danger">Lewat</x-badge>
                                        @elseif ($status === 'Hari Ini')
                                            <x-badge variant="warning">Hari Ini</x-badge>
                                        @elseif ($status === 'Akan Datang')
                                            <x-badge variant="success">Akan Datang</x-badge>
                                        @endif

                                        @if ($editKey !== $kunci)
                                            <x-outline-button type="button" class="!px-2 !py-1 text-xs whitespace-nowrap"
                                                wire:click="mulaiEdit('{{ $item['sumber'] }}', '{{ $item['trx_no'] }}', '{{ $item['tglKontrol'] }}')">
                                                Ubah Tanggal
                                            </x-outline-button>
                                        @endif
                                    </div>
                                    <x-badge :variant="$item['sumber'] === 'RJ' ? 'success' : 'brand'">
                                        {{ $item['sumber'] === 'RJ' ? 'Rawat Jalan' : 'Rawat Inap' }}
                                    </x-badge>
                                </div>

                                {{-- Form edit tanggal inline — muncul hanya di kartu yang dipilih --}}
                                @if ($editKey === $kunci)
                                    <div class="p-3 mt-2 space-y-2 border rounded-lg border-amber-200 bg-amber-50/60 dark:bg-amber-900/10 dark:border-amber-800/40">
                                        <x-input-label value="Tanggal Kontrol Baru *" class="text-xs" />
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <x-text-input type="text" wire:model="tglBaru" placeholder="dd/mm/yyyy"
                                                :error="$errors->has('tglBaru')" class="w-36 text-sm" />
                                            <x-secondary-button type="button" class="text-xs whitespace-nowrap"
                                                wire:click="setTglBaruHariIni">
                                                Hari Ini
                                            </x-secondary-button>
                                            <x-primary-button type="button" class="text-xs whitespace-nowrap"
                                                wire:click="simpanTgl" wire:loading.attr="disabled" wire:target="simpanTgl">
                                                <span wire:loading.remove wire:target="simpanTgl">Simpan & Update BPJS</span>
                                                <span wire:loading wire:target="simpanTgl"><x-loading /> Menyimpan...</span>
                                            </x-primary-button>
                                            <x-secondary-button type="button" class="text-xs" wire:click="batalEdit">
                                                Batal
                                            </x-secondary-button>
                                        </div>
                                        <x-input-error :messages="$errors->get('tglBaru')" class="mt-1" />
                                        @if ($item['noSKDPBPJS'] !== '')
                                            <p class="text-[11px] text-amber-600 dark:text-amber-400">
                                                ⚡ Pasien BPJS: perubahan langsung di-update ke BPJS — jika ditolak, tidak disimpan.
                                            </p>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-1 text-sm">
                                    <span class="font-semibold text-brand dark:text-emerald-400">{{ $item['poliKontrolDesc'] }}</span>
                                    <span class="text-muted">— {{ $item['drKontrolDesc'] }}</span>
                                </div>

                                <table class="mt-2 text-xs leading-snug">
                                    <tr>
                                        <td class="pr-1 font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">Kunjungan asal</td>
                                        <td class="pr-1.5 text-muted-soft align-top">:</td>
                                        <td class="text-body dark:text-gray-300">{{ $item['tgl_kunjungan'] }} ({{ $item['sumber'] }} #{{ $item['trx_no'] }})</td>
                                    </tr>
                                    <tr>
                                        <td class="pr-1 font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">Surat Kontrol</td>
                                        <td class="pr-1.5 text-muted-soft align-top">:</td>
                                        <td class="font-mono font-medium text-body dark:text-gray-300">{{ $item['noSKDPBPJS'] !== '' ? $item['noSKDPBPJS'] : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="pr-1 font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">SEP</td>
                                        <td class="pr-1.5 text-muted-soft align-top">:</td>
                                        <td class="font-mono font-medium text-body dark:text-gray-300">{{ $item['noSEP'] !== '' ? $item['noSEP'] : '-' }}</td>
                                    </tr>
                                    @if ($item['catatan'] !== '')
                                        <tr>
                                            <td class="pr-1 font-semibold text-muted dark:text-gray-400 whitespace-nowrap align-top">Catatan</td>
                                            <td class="pr-1.5 text-muted-soft align-top">:</td>
                                            <td class="text-body dark:text-gray-300">{{ $item['catatan'] }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-between px-6 py-3 border-t border-hairline dark:border-gray-700 shrink-0">
                <p class="text-xs text-muted-soft">
                    Total {{ $this->riwayat->count() }} jadwal kontrol — terbaru di atas.
                </p>
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
