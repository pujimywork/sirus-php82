{{--
    implementasi-asuhan-keperawatan.blade.php
    Partial: Form entry + riwayat implementasi SOAP per diagnosis.
    Berisi checklist tindakan SIKI, skor evaluasi SLKI (1-5), dan SOAP.
    Data auto-sync ke array cppt[] saat disimpan.
    Dipakai oleh rm-asuhan-keperawatan-ri-actions.blade.php via @include.
--}}
@props(['askep', 'idx', 'isFormLocked', 'activeImplIndex', 'formImpl', 'errors'])

<x-border-form title="Implementasi & Evaluasi" align="start" bgcolor="bg-white">
    <div class="mt-2">
        {{-- Tombol tambah --}}
        @if (!$isFormLocked)
            <div class="flex justify-end mb-3">
                @if ($activeImplIndex !== $idx)
                    <x-outline-button type="button" wire:click="openFormImpl({{ $idx }})">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah SOAP
                    </x-outline-button>
                @else
                    <x-secondary-button type="button" wire:click="closeFormImpl">
                        Batal
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Form entry implementasi --}}
        @if (!$isFormLocked && $activeImplIndex === $idx)
            @php $tindakanRencana = $askep['perencanaanIntervensi']['tindakanDipilih'] ?? []; @endphp
            <div class="p-3 mb-3 border border-brand/20 rounded-xl bg-brand/5 dark:bg-brand/10 dark:border-brand/30 space-y-3">
                {{-- Tanggal --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-input-label value="Tanggal *" />
                        <x-text-input wire:model="formImpl.tglImpl" class="w-full mt-1 font-mono" placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('formImpl.tglImpl')" />
                        <x-input-error :messages="$errors->get('formImpl.tglImpl')" class="mt-1" />
                    </div>
                    <x-secondary-button wire:click="setTglImpl" type="button" class="shrink-0">Sekarang</x-secondary-button>
                </div>

                {{-- Checklist tindakan SIKI --}}
                @if (count($tindakanRencana) > 0)
                    <div>
                        <p class="mb-1.5 text-sm font-semibold text-brand dark:text-brand-lime">Tindakan SIKI yang dilakukan:</p>
                        <div class="grid grid-cols-1 gap-1">
                            @foreach ($tindakanRencana as $tindakan)
                                @php $isOn = in_array($tindakan, $formImpl['tindakanDilakukan'] ?? [], true); @endphp
                                <div class="flex items-start gap-2 py-0.5 cursor-pointer hover:bg-brand/5 dark:hover:bg-brand/10 rounded px-1.5 -mx-1"
                                    wire:click="toggleTindakanImpl('{{ addslashes($tindakan) }}')">
                                    <div class="shrink-0 w-8 h-[18px] mt-0.5 rounded-full transition-colors {{ $isOn ? 'bg-brand' : 'bg-gray-300 dark:bg-gray-600' }}">
                                        <div class="w-3.5 h-3.5 mt-[1px] bg-white rounded-full shadow transition-transform {{ $isOn ? 'translate-x-[17px]' : 'translate-x-[1px]' }}"></div>
                                    </div>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $tindakan }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Skor evaluasi SLKI — per kriteria hasil --}}
                @php $kriteriaRencana = $askep['perencanaanLuaran']['kriteriaHasilDipilih'] ?? []; @endphp
                @if (count($kriteriaRencana) > 0)
                    <div>
                        <p class="mb-1 text-sm font-semibold text-brand dark:text-brand-lime">Skor Evaluasi SLKI
                            (per kriteria):</p>
                        <div class="space-y-1.5">
                            @foreach ($kriteriaRencana as $kIdx => $kh)
                                <div
                                    class="flex items-center gap-3 p-2 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
                                    <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $kh }}</span>
                                    <div class="flex gap-1 shrink-0">
                                        @foreach (range(1, 5) as $skor)
                                            <button type="button"
                                                wire:click="$set('formImpl.skorEvaluasi.{{ $kIdx }}', '{{ $skor }}')"
                                                class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-lg border transition-colors
                                                    {{ ($formImpl['skorEvaluasi'][$kIdx] ?? '') == (string) $skor
                                                        ? 'bg-brand text-white border-brand'
                                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-brand/5 dark:bg-gray-800 dark:border-gray-600' }}">
                                                {{ $skor }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-0.5 text-xs text-gray-400">Pilih skor 1-5 per kriteria sesuai interpretasi
                            masing-masing (teks kriteria sudah memuat arah skala).</p>
                    </div>
                @endif

                {{-- SOAP --}}
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([['subjective', 'S — Subjective *'], ['objective', 'O — Objective *'], ['assessment', 'A — Assessment *'], ['plan', 'P — Plan *']] as [$key, $label])
                        <div>
                            <x-input-label value="{{ $label }}" />
                            <x-textarea wire:model="formImpl.soap.{{ $key }}" class="w-full mt-1" rows="2"
                                :error="$errors->has('formImpl.soap.' . $key)" placeholder="{{ $label }}..." />
                            <x-input-error :messages="$errors->get('formImpl.soap.' . $key)" class="mt-1" />
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <x-primary-button wire:click="addImplementasi" type="button">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Simpan Implementasi
                    </x-primary-button>
                </div>
            </div>
        @endif

        {{-- Riwayat implementasi --}}
        @php $implList = array_reverse($askep['implementasi'] ?? []); @endphp
        @forelse ($implList as $iIdx => $impl)
            @php $realIdx = count($askep['implementasi'] ?? []) - 1 - $iIdx; @endphp
            <div wire:key="impl-{{ $idx }}-{{ $iIdx }}" class="p-3 mb-2 border border-gray-200 rounded-lg bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="px-1.5 py-0.5 rounded-full text-xs font-bold bg-brand/10 text-brand dark:bg-brand/20 dark:text-brand-lime">SOAP</span>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $impl['petugasImpl'] ?? '-' }}</span>
                        <span class="font-mono text-gray-600 dark:text-gray-300">{{ $impl['tglImpl'] ?? '-' }}</span>
                        @php
                            $skor = $impl['skorEvaluasi'] ?? null;
                            $avgSkor = null;
                            if (is_array($skor) && count($skor) > 0) {
                                $nums = array_filter(array_map('intval', $skor), fn($n) => $n > 0);
                                $avgSkor = count($nums) ? round(array_sum($nums) / count($nums), 1) : null;
                            } elseif (!empty($skor) && !is_array($skor)) {
                                $avgSkor = (int) $skor;
                            }
                        @endphp
                        @if ($avgSkor !== null)
                            <span class="px-1.5 py-0.5 rounded-full text-xs font-bold
                                {{ $avgSkor >= 4 ? 'bg-green-600 text-white' : ($avgSkor >= 3 ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white') }}">
                                {{ is_array($skor) ? 'Avg Skor' : 'Skor' }}: {{ $avgSkor }}/5
                            </span>
                        @endif
                    </div>
                    @if (!$isFormLocked)
                        <x-icon-button color="red" wire:click="removeImplementasi({{ $idx }}, {{ $realIdx }})"
                            wire:confirm="Yakin hapus implementasi ini?" title="Hapus">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </x-icon-button>
                    @endif
                </div>

                {{-- SOAP --}}
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    @foreach ([['S', 'subjective'], ['O', 'objective'], ['A', 'assessment'], ['P', 'plan']] as [$lbl, $k])
                        <div>
                            <span class="font-bold text-brand dark:text-brand-lime">{{ $lbl }}</span>
                            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $impl['soap'][$k] ?? '-' }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Tindakan --}}
                @if (!empty($impl['tindakanDilakukan']))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($impl['tindakanDilakukan'] as $td)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs bg-brand/10 text-brand dark:bg-brand/20 dark:text-brand-lime">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $td }}
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Detail skor per kriteria (data baru) --}}
                @if (is_array($impl['skorEvaluasi'] ?? null) && count($impl['skorEvaluasi']) > 0)
                    @php $kriteriaList = $askep['perencanaanLuaran']['kriteriaHasilDipilih'] ?? []; @endphp
                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                        <p class="mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Skor per Kriteria SLKI:
                        </p>
                        <div class="grid grid-cols-1 gap-0.5 text-xs">
                            @foreach ($impl['skorEvaluasi'] as $kIdx => $sk)
                                @php
                                    $kText = $kriteriaList[$kIdx] ?? "Kriteria #{$kIdx}";
                                    $skInt = (int) $sk;
                                    $color = $skInt >= 4 ? 'text-green-600 dark:text-green-400' : ($skInt >= 3 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="flex-1 text-gray-700 dark:text-gray-300">{{ $kText }}</span>
                                    <span class="font-mono font-bold {{ $color }}">{{ $sk }}/5</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-3">Belum ada implementasi.</p>
        @endforelse
    </div>
</x-border-form>
