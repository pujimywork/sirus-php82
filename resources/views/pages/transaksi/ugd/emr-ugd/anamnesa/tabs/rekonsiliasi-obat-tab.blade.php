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
                    (rekonsiliasi obat). Pengisiannya bertahap mengikuti perjalanan pasien:
                </p>

                <ol class="space-y-2 ml-6 list-decimal">
                    <li>
                        <strong>Saat pasien masuk:</strong> isi Nama Obat, Dosis, dan Rute
                        (ketiganya wajib), lalu klik <strong>Tambah</strong>. Data langsung tersimpan.
                    </li>
                    <li>
                        <strong>Saat pasien dirawat inapkan:</strong> nyalakan toggle
                        <strong>Dibawa Saat Rawat Inap</strong> pada obat yang ikut dibawa ke ruangan.
                    </li>
                    <li>
                        <strong>Saat pasien pulang:</strong> nyalakan toggle
                        <strong>Dilanjutkan Saat Pulang</strong> pada obat yang diteruskan di rumah.
                    </li>
                </ol>

                <p>
                    Kedua toggle bisa diubah kapan saja langsung di tabel &mdash; setiap perubahan
                    langsung tersimpan tanpa perlu klik Simpan.
                </p>
            </div>
        </div>

        {{-- Form tambah --}}
        @if (!$isFormLocked)
            <div class="space-y-3">

                {{-- Baris atas: identitas obat --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-6">
                        <x-input-label value="Nama Obat" :required="true" />
                        <x-text-input wire:model="rekonNamaObat" wire:keydown.enter.prevent="addRekonsiliasiObat"
                            placeholder="Contoh: Amlodipin 10 mg" :error="$errors->has('rekonNamaObat')" :disabled="$isFormLocked"
                            class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('rekonNamaObat')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-3">
                        <x-input-label value="Dosis" :required="true" />
                        <x-text-input wire:model="rekonDosis" wire:keydown.enter.prevent="addRekonsiliasiObat"
                            placeholder="Contoh: 1x1 tab" :error="$errors->has('rekonDosis')" :disabled="$isFormLocked"
                            class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('rekonDosis')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-3">
                        <x-input-label value="Rute" :required="true" />
                        <x-select-input wire:model="rekonRute" :error="$errors->has('rekonRute')" :disabled="$isFormLocked"
                            class="w-full mt-1">
                            <option value="">—</option>
                            @foreach (['Oral', 'Sublingual', 'IV', 'IM', 'SC', 'Inhalasi', 'Topikal', 'Rektal', 'Tetes Mata', 'Tetes Telinga', 'Lainnya'] as $rute)
                                <option value="{{ $rute }}">{{ $rute }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('rekonRute')" class="mt-1" />
                    </div>
                </div>

                {{-- Baris bawah: keputusan rekonsiliasi + tombol tambah --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                        <div class="flex items-center gap-3">
                            <x-input-label value="Dibawa Saat Ranap" :required="false" />
                            <x-toggle wire:model.live="rekonDibawaRanap" trueValue="Ya" falseValue="Tidak"
                                :label="$rekonDibawaRanap === 'Ya' ? 'Ya' : 'Tidak'" :disabled="$isFormLocked" />
                        </div>

                        <div class="flex items-center gap-3">
                            <x-input-label value="Lanjut Saat Pulang" :required="false" />
                            <x-toggle wire:model.live="rekonLanjutPulang" trueValue="Ya" falseValue="Tidak"
                                :label="$rekonLanjutPulang === 'Ya' ? 'Ya' : 'Tidak'" :disabled="$isFormLocked" />
                        </div>
                    </div>

                    <x-primary-button type="button" wire:click="addRekonsiliasiObat" wire:loading.attr="disabled"
                        wire:target="addRekonsiliasiObat" class="justify-center gap-1.5 w-full sm:w-auto">
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
            </div>
        @endif

        {{-- Daftar obat --}}
        <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th class="ds-c w-12">No</th>
                        <th>Obat (Dosis &middot; Rute)</th>
                        <th>Keterangan</th>
                        <th class="ds-c w-24">Aksi</th>
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
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $dosisRute }}</div>
                                @endif
                            </td>

                            {{-- Keputusan rekonsiliasi — bisa diisi/diubah belakangan (saat transfer ke ranap
                                 maupun saat pasien pulang), tiap perubahan langsung tersimpan. --}}
                            <td>
                                <div class="space-y-1.5">
                                    @foreach ([['dibawaRanap', 'Dibawa Saat Rawat Inap'], ['lanjutPulang', 'Dilanjutkan Saat Pulang']] as [$kolom, $judul])
                                        @php $nilai = ($obat[$kolom] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'; @endphp
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-xs text-muted dark:text-gray-400 w-40 shrink-0">{{ $judul }}</span>
                                            <x-toggle :current="$nilai" trueValue="Ya" falseValue="Tidak" :label="$nilai"
                                                :disabled="$isFormLocked"
                                                wireClick="toggleRekonsiliasiObat({{ $index }}, '{{ $kolom }}')"
                                                title="{{ $judul }}" class="text-xs" />
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
