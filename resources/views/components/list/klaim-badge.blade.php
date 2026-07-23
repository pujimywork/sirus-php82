@props([
    'status' => null,   // klaim_status: BPJS | UMUM | KRONIS | DOKEL (kategori → warna)
    'desc' => null,     // klaim_desc: nama asli DB (mis. "JKN MANDIRI") → label badge
    'id' => null,       // klaim_id: fallback label bila desc kosong
    'prefix' => '',     // opsional teks di depan (mis. "Klaim: ")
])

{{--
    Badge cara bayar / klaim untuk list transaksi. Data tetap dari MODEL KLAIM
    (rsmst_klaimtypes): label = klaim_desc asli (bukan disederhanakan jadi UMUM/BPJS),
    warna = kategori klaim_status (robust untuk SEMUA jenis klaim, tak seperti match
    klaim_id yang menjatuhkan jenis lain ke default).
      BPJS → success · UMUM → alternative · KRONIS → warning · DOKEL → purple · lainnya → gray
    Pakai di kolom Klaim/cara bayar semua list (pelayanan/daftar/kasir/apotek/bulanan).
--}}
@php
    $kategori = strtoupper(trim((string) $status));
    $variant = match ($kategori) {
        'BPJS' => 'success',
        'UMUM' => 'alternative',
        'KRONIS' => 'warning',
        'DOKEL' => 'purple',
        default => 'gray',
    };
    $descVal = trim((string) $desc);
    // Label: "KATEGORI · desc" sejajar (mis. "BPJS · JKN MANDIRI"); dedupe bila desc = kategori.
    $namaKlaim = filled($descVal) ? $descVal : (filled($id) ? $id : '-');
    $label = ($kategori !== '' && strtoupper($namaKlaim) !== $kategori)
        ? $kategori . ' · ' . $namaKlaim
        : ($kategori !== '' ? $kategori : $namaKlaim);
@endphp
<x-badge :variant="$variant" {{ $attributes }}>{{ $prefix }}{{ $label }}</x-badge>
