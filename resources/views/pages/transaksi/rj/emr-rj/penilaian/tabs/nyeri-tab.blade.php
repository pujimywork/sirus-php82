{{-- pages/transaksi/rj/emr-rj/penilaian/tabs/nyeri-tab.blade.php --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Penilaian Nyeri')" :align="__('start')" :bgcolor="__('bg-gray-50')">
            <div class="mt-4 space-y-4">

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryNyeri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryNyeri.tglPenilaian')" class="w-full" />
                            <x-outline-button wire:click="setTglPenilaianNyeri" class="whitespace-nowrap">
                                Sekarang
                            </x-outline-button>
                        </div>
                        <x-input-error :messages="$errors->get('formEntryNyeri.tglPenilaian')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Status Nyeri" :required="true" />
                        <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeri" class="w-full mt-1">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeri')" class="mt-1" />
                    </div>
                </div>

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label value="Metode Penilaian" :required="true" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetode"
                                class="w-full mt-1">
                                <option value="">-- Pilih Metode --</option>
                                @foreach ($nyeriMetodeOptions as $opt)
                                    <option value="{{ $opt['nyeriMetode'] }}">{{ $opt['nyeriMetode'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetode')" class="mt-1" />
                        </div>

                        @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'])
                            <div>
                                <x-input-label value="Skor" />
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-3 py-2 text-xs font-bold text-white rounded-lg bg-brand">
                                        {{ $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] }}
                                    </span>
                                    @if ($formEntryNyeri['nyeri']['nyeriKet'])
                                        <span
                                            class="px-2 py-0.5 text-xs font-bold rounded-full
                                            {{ str_contains(strtolower($formEntryNyeri['nyeri']['nyeriKet']), 'berat')
                                                ? 'bg-red-100 text-red-700'
                                                : (str_contains(strtolower($formEntryNyeri['nyeri']['nyeriKet']), 'sedang')
                                                    ? 'bg-yellow-100 text-yellow-700'
                                                    : (str_contains(strtolower($formEntryNyeri['nyeri']['nyeriKet']), 'ringan')
                                                        ? 'bg-orange-100 text-orange-700'
                                                        : 'bg-green-100 text-green-700')) }}">
                                            {{ $formEntryNyeri['nyeri']['nyeriKet'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ===== NRS ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya' && $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'NRS')
                    <x-border-form :title="__('Numeric Rating Scale (NRS)')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <p class="text-xs text-gray-400">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6 Sedang |
                                7–10 Berat</p>
                            <div>
                                <x-input-label value="Skor NRS (0–10)" :required="true" />
                                <x-text-input type="number" min="0" max="10"
                                    wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                    class="w-32 mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

                {{-- ===== VAS ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya' && $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'VAS')
                    <x-border-form :title="__('Visual Analog Scale (VAS)')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <p class="text-xs text-gray-400">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6 Sedang |
                                7–10 Berat</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $opt)
                                    <button type="button" wire:click="updateVasNyeriScore({{ $opt['vas'] }})"
                                        class="w-10 h-10 text-xs font-bold rounded-lg border-2 transition
                                            {{ $opt['active']
                                                ? 'border-primary bg-brand text-white'
                                                : 'border-gray-300 bg-white text-gray-600 hover:border-primary hover:text-primary dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300' }}">
                                        {{ $opt['vas'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </x-border-form>
                @endif

                {{-- ===== FLACC ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya' && $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'FLACC')
                    <x-border-form :title="__('FLACC Scale')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-4">
                            <p class="text-xs text-gray-400">Interpretasi: 0 Santai | 1–3 Ketidaknyamanan ringan | 4–6
                                Nyeri sedang | 7–10 Nyeri berat</p>
                            @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $category => $items)
                                <div>
                                    <x-input-label :value="ucwords($category)" />
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        @foreach ($items as $item)
                                            <button type="button"
                                                wire:click="updateFlaccScore('{{ $category }}', {{ $item['score'] }})"
                                                class="px-3 py-1.5 text-xs rounded-lg border-2 transition text-left
                                                    {{ $item['active']
                                                        ? 'border-primary bg-brand text-white'
                                                        : 'border-gray-300 bg-white text-gray-600 hover:border-primary hover:text-primary dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300' }}">
                                                <span class="font-bold">{{ $item['score'] }}</span> —
                                                {{ $item['description'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-border-form>
                @endif

                {{-- ===== BPS / NIPS ===== --}}
                @if (
                    $formEntryNyeri['nyeri']['nyeri'] === 'Ya' &&
                        in_array($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'], ['BPS', 'NIPS']))
                    <x-border-form :title="__($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'])" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'BPS')
                                <p class="text-xs text-gray-400">Interpretasi: 3 Tidak Nyeri | 4–6 Nyeri Ringan | 7–9
                                    Nyeri Sedang | 10–12 Nyeri Berat</p>
                            @else
                                <p class="text-xs text-gray-400">Interpretasi: 0–2 Tidak Nyeri | 3–4 Nyeri Ringan | 5–6
                                    Nyeri Sedang | 7 Nyeri Berat</p>
                            @endif
                            <div>
                                <x-input-label value="Skor" :required="true" />
                                <x-text-input type="number" min="0"
                                    wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                    class="w-32 mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

                {{-- ===== DETAIL NYERI ===== --}}
                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    <x-border-form :title="__('Detail Nyeri')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <x-input-label value="Pencetus" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.pencetus" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Durasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.durasi"
                                        placeholder="Contoh: 30 menit" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Lokasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Waktu Nyeri" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.waktuNyeri"
                                        placeholder="Contoh: Malam hari" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Kesadaran" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatKesadaran"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        <option value="Composmentis">Composmentis</option>
                                        <option value="Apatis">Apatis</option>
                                        <option value="Somnolen">Somnolen</option>
                                        <option value="Stupor">Stupor</option>
                                        <option value="Koma">Koma</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Aktivitas" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatAktivitas"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="Dibantu Sebagian">Dibantu Sebagian</option>
                                        <option value="Dibantu Penuh">Dibantu Penuh</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                @endif

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    {{-- ===== INTERVENSI & CATATAN ===== --}}
                    <x-border-form :title="__('Intervensi & Catatan')" :align="__('start')" :bgcolor="__('bg-white')">
                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label value="Intervensi Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi"
                                    class="w-full mt-1" rows="2" placeholder="Nama obat, dosis, rute..." />
                            </div>
                            <div>
                                <x-input-label value="Intervensi Non-Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiNonFarmakologi"
                                    class="w-full mt-1" rows="2"
                                    placeholder="Kompres, relaksasi, distraksi..." />
                            </div>
                            <div>
                                <x-input-label value="Catatan Tambahan" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.catatanTambahan" class="w-full mt-1"
                                    rows="2" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="addAssessmentNyeri" wire:loading.attr="disabled"
                        wire:target="addAssessmentNyeri">
                        <span wire:loading.remove wire:target="addAssessmentNyeri">Simpan Penilaian Nyeri</span>
                        <span wire:loading wire:target="addAssessmentNyeri">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    {{-- ===== TABEL RIWAYAT ===== --}}
    @if (!empty($dataDaftarPoliRJ['penilaian']['nyeri']))
        <x-border-form :title="__('Riwayat Penilaian Nyeri')" :align="__('start')" :bgcolor="__('bg-white')">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Tgl Penilaian</th>
                            <th class="px-3 py-2 font-medium">Petugas</th>
                            <th class="px-3 py-2 font-medium">Nyeri</th>
                            <th class="px-3 py-2 font-medium">Metode</th>
                            <th class="px-3 py-2 font-medium">Skor</th>
                            <th class="px-3 py-2 font-medium">Keterangan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (array_reverse($dataDaftarPoliRJ['penilaian']['nyeri'] ?? [], true) as $i => $row)
                            @php
                                $ket = $row['nyeri']['nyeriKet'] ?? '-';
                                $rowBg = match (true) {
                                    str_contains(strtolower($ket), 'berat')
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    str_contains(strtolower($ket), 'sedang')
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    str_contains(strtolower($ket), 'ringan')
                                        => 'bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/10 dark:hover:bg-orange-900/20',
                                    default
                                        => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ ($row['nyeri']['nyeri'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['nyeri']['nyeri'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ $row['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-bold">
                                    {{ $row['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    @if ($ket !== '-')
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ str_contains(strtolower($ket), 'berat')
                                                ? 'bg-red-100 text-red-700'
                                                : (str_contains(strtolower($ket), 'sedang')
                                                    ? 'bg-yellow-100 text-yellow-700'
                                                    : (str_contains(strtolower($ket), 'ringan')
                                                        ? 'bg-orange-100 text-orange-700'
                                                        : 'bg-green-100 text-green-700')) }}">
                                            {{ $ket }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-icon-button variant="danger"
                                            wire:click="removeAssessmentNyeri({{ $i }})"
                                            wire:confirm="Hapus data nyeri ini?" tooltip="Hapus">
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
        <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian nyeri.</p>
    @endif

</div>
