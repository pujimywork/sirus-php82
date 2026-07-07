{{-- Sub-komponen isi (dipakai wrapper ttd-petugas). Prop = pass-through dari wrapper;
     lihat komentar lengkap tiap prop di ttd-petugas.blade.php. --}}
@props([
    // Data TTD (ikut key JSON EMR ttd/ttdCode/ttdDate).
    'ttd' => '',        // nama penanda-tangan; kosong = belum TTD.
    'date' => '',       // waktu/jam TTD (string apa adanya).
    'code' => '',       // kode user; jika diisi tampil "Kode: xxx".
    // Kontrol kemunculan tombol.
    'locked' => false,  // true → form read-only, tombol disembunyikan.
    'canSign' => true,  // false → tombol disembunyikan walau tak terkunci (role tak berwenang); field tetap tampil.
    'allowClear' => true, // false → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan).
    // Method Livewire di induk (wire:click).
    'sign' => 'ttdSaya',  // method stamp TTD.
    'clear' => 'hapusTtd', // method hapus/ganti TTD.
    // Tata letak.
    'framed' => true,   // hanya mempengaruhi alignment (bingkai diurus wrapper).
    'label' => '',      // judul kecil di atas baris field (opsional).
    // Teks label field & tombol (gaya EMR: 2 field readonly).
    'nameLabel' => 'Petugas',   // label field nama.
    'dateLabel' => 'Waktu TTD', // label field waktu.
    'signLabel' => 'TTD Saya',  // teks tombol stamp.
    'clearLabel' => 'Ganti / Hapus TTD', // teks tombol hapus/ganti.
    'emptyText' => 'Belum ditandatangani.', // hint saat terkunci & belum TTD.
])

@php $signed = !empty($ttd); @endphp

<div class="{{ $framed ? 'max-w-xl mx-auto' : '' }}">
    @if ($label)
        <div class="mb-2 text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400 {{ $framed ? 'text-center' : 'text-left' }}">
            {{ $label }}
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        {{-- Nama penanda-tangan (readonly, diisi tombol TTD) --}}
        <div class="flex-1 min-w-[10rem]">
            <x-input-label :value="$nameLabel" />
            <x-text-input value="{{ $signed ? $ttd : '-' }}" class="mt-1" :disabled="true" readonly />
            @if (!empty($code))
                <p class="mt-0.5 text-xs text-muted">Kode: {{ $code }}</p>
            @endif
        </div>

        {{-- Waktu/Jam TTD (readonly) --}}
        <div class="flex-1 min-w-[10rem]">
            <x-input-label :value="$dateLabel" />
            <x-text-input value="{{ $date ?: '-' }}" class="mt-1" :disabled="true" readonly />
        </div>

        {{-- Tombol stamp / ganti (tersembunyi saat terkunci atau tak berwenang) --}}
        @unless ($locked || !$canSign)
            @if (!$signed)
                <div class="pt-1">
                    <x-primary-button type="button" wire:click="{{ $sign }}" wire:loading.attr="disabled" wire:target="{{ $sign }}" class="gap-1.5">
                        <span wire:loading.remove wire:target="{{ $sign }}" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                            </svg>
                            {{ $signLabel }}
                        </span>
                        <span wire:loading wire:target="{{ $sign }}">Menyimpan...</span>
                    </x-primary-button>
                </div>
            @elseif ($allowClear)
                <div class="pt-1">
                    <x-secondary-button type="button" wire:click="{{ $clear }}" wire:loading.attr="disabled" wire:target="{{ $clear }}" class="gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ $clearLabel }}
                    </x-secondary-button>
                </div>
            @endif
        @endunless
    </div>

    @if ($locked && !$signed && $emptyText)
        <p class="mt-2 text-sm italic text-muted-soft">{{ $emptyText }}</p>
    @endif
</div>
