{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/rekonsiliasi-obat-tab.blade.php --}}
@php $daftarObat = $dataDaftarUGD['anamnesa']['rekonsiliasiObat'] ?? []; @endphp

<x-border-form :title="__('Rekonsiliasi Obat')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        <div x-data="{ open: false }"
            class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
            <button type="button" @click="open = !open"
                class="flex items-center justify-between w-full px-4 py-3 text-base font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Cara Pengisian Rekonsiliasi Obat
                </span>
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-collapse class="px-4 pb-4 space-y-3 text-base text-blue-900 dark:text-blue-200">
                <p>
                    Daftar obat yang sedang / terakhir dipakai pasien <strong>sebelum masuk UGD</strong>
                    (rekonsiliasi obat).
                </p>

                <ol class="space-y-2 ml-6 list-decimal">
                    <li>
                        Isi <strong>Nama Obat</strong>, <strong>Dosis</strong>, dan <strong>Rute</strong>
                        (ketiganya wajib).
                    </li>
                    <li>
                        Tentukan <strong>Dibawa Saat Ranap</strong> (obat ikut dibawa ke ruangan) dan
                        <strong>Lanjut Saat Pulang</strong> (obat diteruskan di rumah).
                    </li>
                    <li>
                        Klik <strong>Tambah</strong> &mdash; data langsung tersimpan dan muncul di tabel.
                    </li>
                </ol>

                <p>
                    Isian di tabel bersifat <strong>data saja</strong>. Bila ada yang keliru, hapus
                    barisnya lalu tambahkan ulang dengan keterangan yang benar.
                </p>
            </div>
        </div>

        {{-- Form tambah --}}
        @if (!$isFormLocked)
            <div class="space-y-3">

                {{-- Nama Obat · Dosis · Rute sebaris; label ringkas + nowrap supaya tidak pecah baris --}}
                <div class="grid grid-cols-12 gap-2">
                    <div class="col-span-5">
                        <x-input-label value="Nama Obat" :required="true" class="truncate whitespace-nowrap" />
                        <x-text-input wire:model="rekonNamaObat" wire:keydown.enter.prevent="addRekonsiliasiObat"
                            placeholder="Amlodipin 10 mg" :error="$errors->has('rekonNamaObat')" :disabled="$isFormLocked"
                            class="w-full px-2 mt-1" />
                        <x-input-error :messages="$errors->get('rekonNamaObat')" class="mt-1" />
                    </div>

                    <div class="col-span-3">
                        <x-input-label value="Dosis" :required="true" class="truncate whitespace-nowrap" />
                        <x-text-input wire:model="rekonDosis" wire:keydown.enter.prevent="addRekonsiliasiObat"
                            placeholder="1x1 tab" :error="$errors->has('rekonDosis')" :disabled="$isFormLocked"
                            class="w-full px-2 mt-1" />
                        <x-input-error :messages="$errors->get('rekonDosis')" class="mt-1" />
                    </div>

                    <div class="col-span-4">
                        <x-input-label value="Rute" :required="true" class="truncate whitespace-nowrap" />
                        <x-select-input wire:model="rekonRute" :error="$errors->has('rekonRute')" :disabled="$isFormLocked"
                            class="w-full px-2 mt-1">
                            <option value="">—</option>
                            @foreach (['Oral', 'Sublingual', 'IV', 'IM', 'SC', 'Inhalasi', 'Topikal', 'Rektal', 'Tetes Mata', 'Tetes Telinga', 'Lainnya'] as $rute)
                                <option value="{{ $rute }}">{{ $rute }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('rekonRute')" class="mt-1" />
                    </div>
                </div>

                {{-- Keputusan rekonsiliasi: label kiri, toggle kanan, sejajar --}}
                <div class="pt-1 space-y-2 border-t border-hairline dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3">
                        <x-input-label value="Dibawa Saat Ranap" :required="false" />
                        <x-toggle wire:model.live="rekonDibawaRanap" trueValue="Ya" falseValue="Tidak"
                            :label="$rekonDibawaRanap === 'Ya' ? 'Ya' : 'Tidak'" :disabled="$isFormLocked" />
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <x-input-label value="Lanjut Saat Pulang" :required="false" />
                        <x-toggle wire:model.live="rekonLanjutPulang" trueValue="Ya" falseValue="Tidak"
                            :label="$rekonLanjutPulang === 'Ya' ? 'Ya' : 'Tidak'" :disabled="$isFormLocked" />
                    </div>
                </div>

                <x-primary-button type="button" wire:click="addRekonsiliasiObat" wire:loading.attr="disabled"
                    wire:target="addRekonsiliasiObat" class="justify-center gap-1.5 w-full">
                    <span wire:loading.remove wire:target="addRekonsiliasiObat" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah
                    </span>
                    <span wire:loading wire:target="addRekonsiliasiObat" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Menyimpan...
                    </span>
                </x-primary-button>
            </div>
        @endif

        {{-- Daftar obat --}}
        <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th class="ds-c w-10">No</th>
                        <th>Obat (Dosis &middot; Rute)</th>
                        <th>Keterangan</th>
                        <th class="ds-c w-14">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarObat as $index => $obat)
                        <tr wire:key="rekon-obat-ugd-{{ $rjNo ?? 'new' }}-{{ $index }}">
                            @php
                                $dosisRute = collect([$obat['dosis'] ?? null, $obat['rute'] ?? null])
                                    ->filter(fn($isi) => filled($isi))
                                    ->implode(' · ');
                            @endphp
                            <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                            <td>
                                <div class="ds-td-strong">{{ $obat['namaObat'] ?? '-' }}</div>
                                @if ($dosisRute)
                                    <div class="text-muted dark:text-gray-400">{{ $dosisRute }}</div>
                                @endif
                            </td>

                            {{-- Keputusan rekonsiliasi — tampil sebagai data saja (diisi lewat form Tambah). --}}
                            <td>
                                <div class="space-y-1.5">
                                    @foreach ([['dibawaRanap', 'Dibawa saat ranap'], ['lanjutPulang', 'Lanjut saat pulang']] as [$kolom, $judul])
                                        @php $nilai = ($obat[$kolom] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'; @endphp
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-muted dark:text-gray-400">{{ $judul }}</span>
                                            <span class="font-medium {{ $nilai === 'Ya' ? 'text-success-deep dark:text-success' : 'text-muted-soft' }}">
                                                {{ $nilai }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="ds-c">
                                @if (!$isFormLocked)
                                    <x-confirm-button variant="danger-soft" :action="'removeRekonsiliasiObat(' . $index . ')'"
                                        title="Hapus Obat" :message="'Yakin hapus ' . ($obat['namaObat'] ?? 'obat ini') . ' dari daftar?'"
                                        confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-confirm-button>
                                @else
                                    <span class="text-muted-soft">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="ds-c italic text-muted-soft">
                                Belum ada riwayat pemakaian obat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-border-form>
