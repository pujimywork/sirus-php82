{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/gizi-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Gizi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
            <div class="mt-4 space-y-4">

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryGizi.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryGizi.tglPenilaian')" class="w-full" />
                            <x-outline-button wire:click="setTglPenilaianGizi" class="whitespace-nowrap">
                                Sekarang
                            </x-outline-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryGizi.tglPenilaian')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Berat Badan (kg)" :required="true" />
                        <x-text-input type="number" step="0.1" wire:model.live="formEntryGizi.gizi.beratBadan"
                            :error="$errors->has('formEntryGizi.gizi.beratBadan')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formEntryGizi.gizi.beratBadan')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Tinggi Badan (cm)" :required="true" />
                        <x-text-input type="number" step="0.1" wire:model.live="formEntryGizi.gizi.tinggiBadan"
                            :error="$errors->has('formEntryGizi.gizi.tinggiBadan')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('formEntryGizi.gizi.tinggiBadan')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="IMT" />
                        <x-text-input wire:model="formEntryGizi.gizi.imt" readonly
                            class="w-full mt-1 bg-gray-100 cursor-not-allowed" />
                        <p class="mt-1 text-xs text-gray-400">Auto-hitung</p>
                    </div>

                    <div>
                        <x-input-label value="Kebutuhan Gizi" />
                        <x-text-input wire:model="formEntryGizi.gizi.kebutuhanGizi" placeholder="Contoh: 1800 kkal/hari"
                            class="w-full mt-1" />
                    </div>
                </div>

                <x-border-form :title="__('Skrining Gizi Awal')" :align="__('start')" :bgcolor="__('bg-white')">
                    <div class="mt-4 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                Skor: {{ $formEntryGizi['gizi']['skorSkrining'] ?? 0 }}
                            </span>
                            @if ($formEntryGizi['gizi']['kategoriGizi'] ?? '')
                                <span
                                    class="px-2 py-0.5 text-xs font-bold rounded-full
                                    {{ ($formEntryGizi['gizi']['kategoriGizi'] ?? '') === 'Berisiko Malnutrisi'
                                        ? 'bg-orange-100 text-orange-700'
                                        : 'bg-green-100 text-green-700' }}">
                                    {{ $formEntryGizi['gizi']['kategoriGizi'] }}
                                </span>
                            @endif
                            <span class="text-xs text-gray-400">Interpretasi: Skor ≥2 = Berisiko Malnutrisi</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($skriningGiziAwalOptions as $key => $options)
                                @php
                                    $fieldKey = match ($key) {
                                        'perubahanBeratBadan' => 'perubahan',
                                        'asupanMakanan' => 'asupan',
                                        'penyakit' => 'penyakit',
                                        default => $key,
                                    };
                                    $label = match ($key) {
                                        'perubahanBeratBadan' => 'Perubahan Berat Badan',
                                        'asupanMakanan' => 'Asupan Makanan',
                                        'penyakit' => 'Kondisi Penyakit',
                                        default => ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key)),
                                    };
                                @endphp
                                <div>
                                    <x-input-label :value="$label" />
                                    <x-select-input
                                        wire:model.live="formEntryGizi.gizi.skriningGizi.{{ $key }}"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach ($options as $opt)
                                            <option value="{{ $opt[$fieldKey] }}">
                                                {{ $opt[$fieldKey] }} (Skor: {{ $opt['score'] }})
                                            </option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-border-form>

                <div>
                    <x-input-label value="Catatan" />
                    <x-textarea wire:model="formEntryGizi.gizi.catatan" class="w-full mt-1" rows="2" />
                </div>

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="addAssessmentGizi" wire:loading.attr="disabled"
                        wire:target="addAssessmentGizi">
                        <span wire:loading.remove wire:target="addAssessmentGizi">Simpan Penilaian Gizi</span>
                        <span wire:loading wire:target="addAssessmentGizi">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarUGD['penilaian']['gizi']))
        <x-border-form :title="__('Riwayat Penilaian Gizi')" :align="__('start')" :bgcolor="__('bg-white')">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Tgl Penilaian</th>
                            <th class="px-3 py-2 font-medium">Petugas</th>
                            <th class="px-3 py-2 font-medium">BB (kg)</th>
                            <th class="px-3 py-2 font-medium">TB (cm)</th>
                            <th class="px-3 py-2 font-medium">IMT</th>
                            <th class="px-3 py-2 font-medium">Skor Skrining</th>
                            <th class="px-3 py-2 font-medium">Kategori</th>
                            <th class="px-3 py-2 font-medium">Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarUGD['penilaian']['gizi'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['gizi']['kategoriGizi'] ?? '-';
                                $rowBg = match ($kat) {
                                    'Berisiko Malnutrisi'
                                        => 'bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/10 dark:hover:bg-orange-900/20',
                                    'Normal'
                                        => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                    default => 'hover:bg-gray-50 dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['gizi']['beratBadan'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['gizi']['tinggiBadan'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">{{ $row['gizi']['imt'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">{{ $row['gizi']['skorSkrining'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $kat === 'Berisiko Malnutrisi' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500 max-w-xs truncate">
                                    {{ $row['gizi']['catatan'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-icon-button variant="danger"
                                            wire:click="removeAssessmentGizi({{ $i }})"
                                            wire:confirm="Hapus data gizi ini?" tooltip="Hapus">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-icon-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian gizi.</p>
    @endif

</div>
