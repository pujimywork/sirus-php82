{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/dekubitus-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Dekubitus (Skala Braden)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
            <div class="mt-4 space-y-4">

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryDekubitus.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryDekubitus.tglPenilaian')" class="w-full" />
                            <x-outline-button wire:click="setTglPenilaianDekubitus" class="whitespace-nowrap">
                                Sekarang
                            </x-outline-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryDekubitus.tglPenilaian')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Status Dekubitus" :required="true" />
                        <x-select-input wire:model.live="formEntryDekubitus.dekubitus.dekubitus" class="w-full mt-1">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('formEntryDekubitus.dekubitus.dekubitus')" class="mt-1" />
                    </div>
                </div>

                <x-border-form :title="__('Penilaian Skala Braden')" :align="__('start')" :bgcolor="__('bg-white')">
                    <div class="mt-4 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                Skor: {{ $formEntryDekubitus['dekubitus']['bradenScore'] ?? 0 }}
                            </span>
                            @if ($formEntryDekubitus['dekubitus']['kategoriResiko'] ?? '')
                                @php $katForm = $formEntryDekubitus['dekubitus']['kategoriResiko']; @endphp
                                <span
                                    class="px-2 py-0.5 text-xs font-bold rounded-full
                                    {{ in_array($katForm, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($katForm === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                    {{ $katForm }}
                                </span>
                            @endif
                            <span class="text-xs text-gray-400">Interpretasi: ≤12 Sangat Tinggi | 13–14 Tinggi | 15–18
                                Sedang | ≥19 Rendah</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($bradenScaleOptions as $key => $options)
                                <div>
                                    <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                    <x-select-input
                                        wire:model.live="formEntryDekubitus.dekubitus.dataBraden.{{ $key }}"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach ($options as $opt)
                                            <option value="{{ $opt['score'] }}">{{ $opt['description'] }} (Skor:
                                                {{ $opt['score'] }})</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-border-form>

                <div>
                    <x-input-label value="Rekomendasi" />
                    <x-textarea wire:model="formEntryDekubitus.dekubitus.rekomendasi" class="w-full mt-1"
                        rows="2" />
                </div>

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="addAssessmentDekubitus" wire:loading.attr="disabled"
                        wire:target="addAssessmentDekubitus">
                        <span wire:loading.remove wire:target="addAssessmentDekubitus">Simpan Penilaian Dekubitus</span>
                        <span wire:loading wire:target="addAssessmentDekubitus">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    @if (!empty($dataDaftarUGD['penilaian']['dekubitus']))
        <x-border-form :title="__('Riwayat Penilaian Dekubitus')" :align="__('start')" :bgcolor="__('bg-white')">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Tgl Penilaian</th>
                            <th class="px-3 py-2 font-medium">Petugas</th>
                            <th class="px-3 py-2 font-medium">Dekubitus</th>
                            <th class="px-3 py-2 font-medium">Skor Braden</th>
                            <th class="px-3 py-2 font-medium">Kategori Risiko</th>
                            <th class="px-3 py-2 font-medium">Rekomendasi</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarUGD['penilaian']['dekubitus'] ?? [], true) as $i => $row)
                            @php
                                $kat = $row['dekubitus']['kategoriResiko'] ?? '-';
                                $rowBg = match (true) {
                                    in_array($kat, ['Sangat Tinggi', 'Tinggi'])
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    $kat === 'Sedang'
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    $kat === 'Rendah'
                                        => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                    default => 'hover:bg-gray-50 dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ ($row['dekubitus']['dekubitus'] ?? '') === 'Ya' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['dekubitus']['dekubitus'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-bold">{{ $row['dekubitus']['bradenScore'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ in_array($kat, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['dekubitus']['rekomendasi'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-icon-button variant="danger"
                                            wire:click="removeAssessmentDekubitus({{ $i }})"
                                            wire:confirm="Hapus data dekubitus ini?" tooltip="Hapus">
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
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian dekubitus.</p>
    @endif

</div>
