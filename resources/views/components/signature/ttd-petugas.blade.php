@props([
    // ══ Data TTD ══ (nama prop mengikuti key JSON EMR: $newForm['ttd'] / 'ttdCode' / 'ttdDate')
    // Nama penanda-tangan. Kosong = belum TTD → tombol stamp muncul; terisi = kartu TTD tampil.
    'ttd' => '',
    // Waktu/jam TTD; string ditampilkan apa adanya (mis. "07/07/2026 09:00:00").
    'date' => '',
    // Kode user penanda-tangan (myuser_code). Jika diisi: tampil "Kode: xxx" & dipakai
    // me-resolve gambar myuser_ttd_image saat cetak.
    'code' => '',

    // ══ Kontrol kemunculan tombol ══
    // true → form terkunci / read-only: tombol TTD & Ganti-Hapus disembunyikan (lempar $isFormLocked).
    'locked' => false,
    // false → tombol disembunyikan walau form TIDAK terkunci (mis. role tak berwenang);
    //         field readonly tetap terlihat. Pasangkan cek role:
    //         :canSign="auth()->user()?->hasAnyRole(['Perawat','Admin'])".
    'canSign' => true,
    // false → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan); mis. serah-terima / pengkajian.
    'allowClear' => true,

    // ══ Method Livewire di komponen induk (dipanggil via wire:click) ══
    // Nama method untuk men-stamp TTD (nama+kode+tgl user login).
    'sign' => 'ttdSaya',
    // Nama method untuk menghapus/mengganti TTD (hanya relevan saat allowClear=true).
    'clear' => 'hapusTtd',

    // ══ Tata letak ══
    // true  → dibungkus border-form (komponen bingkai) bertajuk $title, kolom di tengah.
    // false → tanpa bingkai, rata kiri; cocok di grid-cell / kolom serah-terima.
    'framed' => true,
    // Judul border-form — hanya dipakai saat framed=true.
    'title' => 'Tanda Tangan',
    // Judul kecil di atas baris field; kosongkan bila judul kolom sudah ada di luar komponen.
    'label' => '',

    // ══ Teks label field & tombol (gaya EMR: 2 field readonly bersanding) ══
    // Label field nama (mis. "Petugas Pengkaji", "Dokter Pengkaji").
    'nameLabel' => 'Petugas',
    // Label field waktu (mis. "Jam Pengkajian", "Jam TTD").
    'dateLabel' => 'Waktu TTD',
    // Teks tombol stamp.
    'signLabel' => 'TTD Saya',
    // Teks tombol hapus/ganti.
    'clearLabel' => 'Ganti / Hapus TTD',
    // Hint saat terkunci & belum TTD (mis. "Menunggu TTD Pengirim.").
    'emptyText' => 'Belum ditandatangani.',
])

{{-- Komponen TTD reusable — gaya EMR (field Petugas + Waktu/Jam readonly + tombol
     "TTD Saya" yang men-stamp nama user login + kode + tgl). Isi diekstrak ke
     sub-komponen ttd-petugas-body supaya wrapper border-form membungkus utuh
     (JANGAN split tag komponen antar @if — konten hilang saat framed=false). --}}
@if ($framed)
    <x-border-form :title="$title">
        <x-signature.ttd-petugas-body :ttd="$ttd" :date="$date" :code="$code" :locked="$locked"
            :canSign="$canSign" :sign="$sign" :clear="$clear" :allowClear="$allowClear" :framed="true"
            :label="$label" :nameLabel="$nameLabel" :dateLabel="$dateLabel" :signLabel="$signLabel"
            :clearLabel="$clearLabel" :emptyText="$emptyText" />
    </x-border-form>
@else
    <x-signature.ttd-petugas-body :ttd="$ttd" :date="$date" :code="$code" :locked="$locked"
        :canSign="$canSign" :sign="$sign" :clear="$clear" :allowClear="$allowClear" :framed="false"
        :label="$label" :nameLabel="$nameLabel" :dateLabel="$dateLabel" :signLabel="$signLabel"
        :clearLabel="$clearLabel" :emptyText="$emptyText" />
@endif
