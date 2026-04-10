{{--
    riwayat-asuhan-keperawatan.blade.php
    Partial: Card data diagnosis keperawatan per entry.
    Menampilkan nama diagnosis, kategori SDKI, rumusan, dan tombol Lihat Detail.
    Dipakai oleh rm-asuhan-keperawatan-ri-actions.blade.php via @include.
--}}
@props(['askep', 'idx', 'isFormLocked'])

<x-border-form title="{{ $askep['diagKepDesc'] ?? '-' }}" align="start" bgcolor="bg-white">
    {{-- Header --}}
    <div class="flex items-center justify-between mt-2">
        <div class="text-sm text-gray-700 dark:text-gray-300 space-x-2">
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $askep['petugasAsuhanKeperawatan'] ?? '-' }}</span>
            <span class="font-mono">{{ $askep['tglAsuhanKeperawatan'] ?? '-' }}</span>
        </div>
        @if (!$isFormLocked)
        <x-icon-button color="red" wire:click="removeAsuhanKeperawatan({{ $idx }})"
            wire:confirm="Yakin ingin menghapus Asuhan Keperawatan ini?" title="Hapus">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </x-icon-button>
        @endif
    </div>

    {{-- Kode & Kategori --}}
    <div class="mt-2">
        <p class="text-sm font-semibold text-brand dark:text-emerald-400">
            {{ $askep['diagKepDesc'] ?? '-' }}
            <span class="ml-1 font-mono text-sm text-gray-400">({{ $askep['diagKepId'] ?? '' }})</span>
        </p>
        @if (!empty($askep['diagKepJson']['sdki']['kategori']))
        <p class="text-sm mt-0.5">
            <span class="inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 font-medium">{{ $askep['diagKepJson']['sdki']['kategori'] }}</span>
            <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 font-medium ml-1">{{ $askep['diagKepJson']['sdki']['subkategori'] ?? '' }}</span>
        </p>
        @endif
    </div>

    {{-- Rumusan --}}
    @if (!empty($askep['perumusanDiagnosis']['rumusanDiagnosis']))
    <div class="mt-2 rounded border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/20 px-3 py-2">
        <p class="font-bold text-indigo-600 dark:text-indigo-400 mb-0.5">Rumusan Diagnosis:</p>
        <p class="text-indigo-800 dark:text-indigo-200 leading-relaxed">{{ $askep['perumusanDiagnosis']['rumusanDiagnosis'] }}</p>
    </div>
    @endif

    {{-- Detail (tombol) --}}
    <div x-data="{ open: false }" class="mt-2">
        <x-outline-button type="button" @click="open = !open" class="!py-1.5 !px-3 !text-sm">
            <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span x-text="open ? 'Sembunyikan Detail' : 'Lihat Detail'"></span>
        </x-outline-button>
        <div x-show="open" x-collapse class="mt-3 space-y-2 text-sm">
            @php $rP = $askep['perumusanDiagnosis'] ?? []; @endphp

            @foreach (['penyebabDipilih' => ['Penyebab (b.d)', 'text-red-600'], 'faktorResikoDipilih' => ['Faktor Risiko', 'text-orange-600']] as $fk => $fv)
            @if (!empty($rP[$fk]))
            <div>
                <span class="font-bold {{ $fv[1] }} dark:opacity-80">{{ $fv[0] }}:</span>
                <ul class="ml-4 list-disc text-gray-700 dark:text-gray-300">@foreach ($rP[$fk] as $v) <li>{{ $v }}</li> @endforeach</ul>
            </div>
            @endif
            @endforeach

            @foreach (['tandaMayorSubjDipilih' => 'Tanda Mayor Subjektif', 'tandaMayorObjDipilih' => 'Tanda Mayor Objektif', 'tandaMinorSubjDipilih' => 'Tanda Minor Subjektif', 'tandaMinorObjDipilih' => 'Tanda Minor Objektif'] as $fk => $fl)
            @if (!empty($rP[$fk]))
            <div>
                <span class="font-bold text-emerald-600 dark:opacity-80">{{ $fl }} (d.d):</span>
                <ul class="ml-4 list-disc text-gray-700 dark:text-gray-300">@foreach ($rP[$fk] as $v) <li>{{ $v }}</li> @endforeach</ul>
            </div>
            @endif
            @endforeach

            @if (!empty($askep['perencanaanLuaran']['kriteriaHasilDipilih']))
            <div>
                <span class="font-bold text-green-600 dark:opacity-80">Kriteria Hasil (SLKI):</span>
                <ul class="ml-4 list-disc text-gray-700 dark:text-gray-300">@foreach ($askep['perencanaanLuaran']['kriteriaHasilDipilih'] as $v) <li>{{ $v }}</li> @endforeach</ul>
            </div>
            @endif

            @if (!empty($askep['perencanaanIntervensi']['tindakanDipilih']))
            <div>
                <span class="font-bold text-blue-600 dark:opacity-80">Tindakan (SIKI):</span>
                <ul class="ml-4 list-disc text-gray-700 dark:text-gray-300">@foreach ($askep['perencanaanIntervensi']['tindakanDipilih'] as $v) <li>{{ $v }}</li> @endforeach</ul>
            </div>
            @endif
        </div>
    </div>
</x-border-form>
