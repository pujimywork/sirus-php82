{{-- pages/transaksi/ugd/emr-ugd/penilaian/tabs/risiko-bunuh-diri-tab.blade.php --}}
{{-- Skrining C-SSRS (Columbia Suicide Severity Rating Scale) — markup murni, state di file induk. --}}
<div class="space-y-4">

    @if (!$isFormLocked)
        <x-border-form :title="__('Tambah Skrining Risiko Bunuh Diri — C-SSRS')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
            <div class="space-y-4">

                {{-- panduan singkat (default tertutup) --}}
                @include('pages.components.rekam-medis.risiko-bunuh-diri-panduan')

                {{-- GATE: ada risiko bunuh diri? --}}
                <div class="sm:max-w-xs">
                    <x-input-label value="Risiko Bunuh Diri" :required="true" />
                    <x-select-input wire:model.live="formEntryResikoBunuhDiri.resikoBunuhDiri" class="w-full mt-1"
                        x-ref="rbdRisiko" x-on:keydown.enter.prevent="$refs.rbdTgl?.focus()">
                        <option value="Tidak">Tidak</option>
                        <option value="Ya">Ya</option>
                    </x-select-input>
                    <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.resikoBunuhDiri')" class="mt-1" />
                </div>

                @if (($formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') === 'Ya')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Tanggal Penilaian" :required="true" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input wire:model="formEntryResikoBunuhDiri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                :error="$errors->has('formEntryResikoBunuhDiri.tglPenilaian')" class="w-full"
                                x-ref="rbdTgl" />
                            <x-now-button wire:click="setTglPenilaianResikoBunuhDiri" />
                        </div>
                        <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.tglPenilaian')" class="mt-1" />
                    </div>

                    {{-- hasil live --}}
                    <div class="flex flex-wrap items-end gap-2 pb-1">
                        <span class="px-2 py-0.5 text-sm font-bold text-white rounded-full bg-brand">
                            Skor: {{ $formEntryResikoBunuhDiri['skorKeparahan'] }}
                        </span>
                        @php $kategoriLive = $formEntryResikoBunuhDiri['kategoriResiko'] ?? 'Tidak Ada'; @endphp
                        <span
                            class="px-2 py-0.5 text-sm font-bold rounded-full
                            {{ $kategoriLive === 'Tinggi'
                                ? 'bg-red-100 text-red-700'
                                : ($kategoriLive === 'Sedang'
                                    ? 'bg-yellow-100 text-yellow-700'
                                    : ($kategoriLive === 'Rendah'
                                        ? 'bg-orange-100 text-orange-700'
                                        : 'bg-green-100 text-green-700')) }}">
                            {{ $kategoriLive }}
                        </span>
                        <span class="text-sm text-muted-soft">Rendah 1–2 | Sedang 3–4 ± persiapan | Tinggi 5 / riwayat percobaan</span>
                    </div>
                </div>

                {{-- A. IDE BUNUH DIRI --}}
                <x-border-form :title="__('A · Ide Bunuh Diri (1 bulan terakhir)')" :align="__('start')" :bgcolor="__('bg-canvas')">
                    <div class="grid grid-cols-1 gap-3">
                        @foreach ($ideBunuhDiriPertanyaan as $key => $pertanyaan)
                            <div class="grid items-center grid-cols-1 gap-2 sm:grid-cols-[1fr_230px]">
                                <x-input-label :value="$loop->iteration . '. ' . $pertanyaan" />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-radio-button label="Ya" value="Ya" name="ideBunuhDiriUgd{{ $key }}"
                                        wire:model.live="formEntryResikoBunuhDiri.ideBunuhDiri.{{ $key }}" />
                                    <x-radio-button label="Tidak" value="Tidak" name="ideBunuhDiriUgd{{ $key }}"
                                        wire:model.live="formEntryResikoBunuhDiri.ideBunuhDiri.{{ $key }}" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-border-form>

                {{-- B. PERILAKU BUNUH DIRI --}}
                <x-border-form :title="__('B · Perilaku Bunuh Diri (sepanjang hidup)')" :align="__('start')" :bgcolor="__('bg-canvas')">
                    <div class="grid grid-cols-1 gap-3">
                        @foreach ($perilakuBunuhDiriPertanyaan as $key => $pertanyaan)
                            <div class="grid items-center grid-cols-1 gap-2 sm:grid-cols-[1fr_230px]">
                                <x-input-label :value="$loop->iteration . '. ' . $pertanyaan" />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-radio-button label="Ya" value="Ya" name="perilakuBunuhDiriUgd{{ $key }}"
                                        wire:model.live="formEntryResikoBunuhDiri.perilakuBunuhDiri.{{ $key }}" />
                                    <x-radio-button label="Tidak" value="Tidak" name="perilakuBunuhDiriUgd{{ $key }}"
                                        wire:model.live="formEntryResikoBunuhDiri.perilakuBunuhDiri.{{ $key }}" />
                                </div>
                            </div>
                        @endforeach

                        @if (collect(['pernahMencoba', 'hampirMencoba', 'memulaiLaluBerhenti', 'persiapanSerius'])->contains(fn($key) => ($formEntryResikoBunuhDiri['perilakuBunuhDiri'][$key] ?? '') === 'Ya'))
                            <div>
                                <x-input-label value="Jika Ya — kapan terakhir?" />
                                <x-text-input wire:model="formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir"
                                    placeholder="mis. 2 bulan yang lalu" class="w-full mt-1"
                                    x-ref="rbdKapan" x-on:keydown.enter.prevent="$refs.rbdCatatan?.focus()" />
                                <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir')" class="mt-1" />
                            </div>
                        @endif
                    </div>
                </x-border-form>

                {{-- C. TINDAK LANJUT --}}
                <x-border-form :title="__('C · Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-canvas')">
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach ($tindakLanjutBunuhDiriOptions as $opsi)
                            <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['tindakLanjut'] ?? [], true) ? '1' : '0'"
                                trueValue="1" falseValue="0"
                                wireClick="toggleTindakLanjutBunuhDiri({{ $loop->index }})">
                                {{ $opsi }}
                            </x-toggle>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <x-input-label value="Catatan klinis singkat" />
                        <x-textarea wire:model="formEntryResikoBunuhDiri.catatanKlinis" class="w-full mt-1" rows="2"
                            x-ref="rbdCatatan" />
                        <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.catatanKlinis')" class="mt-1" />
                    </div>
                </x-border-form>

                {{-- D. Safety Plan — hanya muncul bila tindak lanjut "Safety plan" dipilih di bagian C --}}
                @if (in_array('Safety plan', $formEntryResikoBunuhDiri['tindakLanjut'] ?? [], true))
                    @include('pages.components.rekam-medis.risiko-bunuh-diri-safety-plan')
                @endif
                @endif

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="addAssessmentResikoBunuhDiri" wire:loading.attr="disabled"
                        wire:target="addAssessmentResikoBunuhDiri">
                        <span wire:loading.remove wire:target="addAssessmentResikoBunuhDiri">Simpan Skrining Risiko
                            Bunuh Diri</span>
                        <span wire:loading wire:target="addAssessmentResikoBunuhDiri">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    @if (collect($dataDaftarUGD['penilaian']['resikoBunuhDiri'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'tglPenilaian')))->isNotEmpty())
        <x-border-form :title="__('Riwayat Skrining Risiko Bunuh Diri')" :align="__('start')" :bgcolor="__('bg-canvas')">
            <div class="overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-sm text-left text-muted dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Tgl Penilaian</th>
                            <th class="px-3 py-2 font-medium">Petugas</th>
                            <th class="px-3 py-2 font-medium">Risiko</th>
                            <th class="px-3 py-2 font-medium">Skor</th>
                            <th class="px-3 py-2 font-medium">Kategori</th>
                            <th class="px-3 py-2 font-medium">Tindak Lanjut</th>
                            <th class="px-3 py-2 font-medium">Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse(array_filter($dataDaftarUGD['penilaian']['resikoBunuhDiri'] ?? [], fn($entri) => filled(data_get($entri, 'tglPenilaian'))), true) as $i => $row)
                            @php
                                $kat = $row['kategoriResiko'] ?? '-';
                                $rowBg = match ($kat) {
                                    'Tinggi'
                                        => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    'Sedang'
                                        => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    'Rendah'
                                        => 'bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/10 dark:hover:bg-orange-900/20',
                                    default => 'hover:bg-surface-soft dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-sm font-medium
                                        {{ ($row['resikoBunuhDiri'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['resikoBunuhDiri'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-bold">{{ $row['skorKeparahan'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-sm font-medium
                                        {{ $kat === 'Tinggi'
                                            ? 'bg-red-100 text-red-700'
                                            : ($kat === 'Sedang'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : ($kat === 'Rendah'
                                                    ? 'bg-orange-100 text-orange-700'
                                                    : 'bg-green-100 text-green-700')) }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ implode(', ', $row['tindakLanjut'] ?? []) ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted">{{ $row['catatanKlinis'] ?: '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentResikoBunuhDiri({{ $i }})"
                                            wire:confirm="Hapus data skrining risiko bunuh diri ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="py-6 text-sm text-center text-muted-soft">Belum ada data skrining risiko bunuh diri.</p>
    @endif

</div>
