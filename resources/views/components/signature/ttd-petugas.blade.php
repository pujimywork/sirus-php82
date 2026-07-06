@props([
    // Nilai TTD saat ini (lempar $newForm['ttd'] & $newForm['ttdDate']).
    'ttd' => '',
    'date' => '',
    // Kode penanda-tangan (opsional) — tampil "Kode: xxx" saat diisi.
    'code' => '',
    // Kunci form: sembunyikan tombol TTD/Hapus saat true (lempar $isFormLocked).
    'locked' => false,
    // Nama method Livewire di komponen induk untuk stamp / batalkan TTD.
    'sign' => 'ttdSaya',
    'clear' => 'hapusTtd',
    // allowClear=false → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan).
    'allowClear' => true,
    // framed=true  → dibungkus border-form (kartu bertajuk), kolom sempit di tengah.
    // framed=false → tanpa bingkai, rata kiri; cocok ditaruh dalam grid-cell.
    'framed' => true,
    // Teks yang bisa disesuaikan per pemakaian.
    'title' => 'Tanda Tangan',
    'label' => 'Petugas (Penanda-tangan)',
    'signLabel' => 'TTD Saya',
    'clearLabel' => 'Ganti / Hapus TTD',
    'emptyText' => 'Belum ditandatangani.',
])

{{-- Komponen TTD reusable — gaya general-consent (stamp nama + tgl user login).
     Isi diekstrak ke sub-komponen ttd-petugas-body supaya wrapper border-form
     membungkus utuh (JANGAN split tag komponen antar @if — konten hilang saat framed=false). --}}
@if ($framed)
    <x-border-form :title="$title">
        <x-signature.ttd-petugas-body :ttd="$ttd" :date="$date" :code="$code" :locked="$locked"
            :sign="$sign" :clear="$clear" :allowClear="$allowClear" :framed="true" :label="$label"
            :signLabel="$signLabel" :clearLabel="$clearLabel" :emptyText="$emptyText" />
    </x-border-form>
@else
    <x-signature.ttd-petugas-body :ttd="$ttd" :date="$date" :code="$code" :locked="$locked"
        :sign="$sign" :clear="$clear" :allowClear="$allowClear" :framed="false" :label="$label"
        :signLabel="$signLabel" :clearLabel="$clearLabel" :emptyText="$emptyText" />
@endif
