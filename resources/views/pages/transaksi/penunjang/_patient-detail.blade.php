{{--
    Detail pasien per-sumber (RI/RJ/UGD) untuk Tambah Pemeriksaan Lab & Radiologi.
    Dipakai di baris tabel ($p = $identity) & kartu terpilih ($p = $selectedPatient).
    Props: $p (array), $source ('RI'|'RJ'|'UGD').
--}}
@if ($source === 'RI')
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $p['bangsal_name'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">
        {{ $p['room_name'] ?? '-' }}@if (!empty($p['bed_no'])) · Bed {{ $p['bed_no'] }}@endif
    </div>
    @if (!empty($p['leveling_dokter_list']))
        <div class="mt-0.5">
            <span class="text-xs text-muted-soft">DPJP:</span>
            @foreach ($p['leveling_dokter_list'] as $ld)
                <div class="text-sm text-body dark:text-gray-200">{{ $ld['drName'] }}@if (!empty($ld['levelDokter'])) <span class="text-xs text-muted">({{ $ld['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $ld['levelDokter'] }})</span>@endif</div>
            @endforeach
        </div>
    @endif
    <div class="text-xs italic text-muted dark:text-gray-400">Penerima: {{ $p['penerima_name'] ?? '-' }}</div>
@elseif ($source === 'RJ')
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $p['poli_desc'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">Dokter: {{ $p['dokter_name'] ?? '-' }}</div>
    @if (!empty($p['no_antrian']))
        <div class="text-xs text-muted dark:text-gray-400">Antrian: {{ $p['no_antrian'] }}</div>
    @endif
@else
    {{-- UGD --}}
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">Dokter: {{ $p['dokter_name'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">Cara Masuk: {{ $p['entry_desc'] ?? '-' }}</div>
    @if (!empty($p['no_antrian']))
        <div class="text-xs text-muted dark:text-gray-400">Antrian: {{ $p['no_antrian'] }}</div>
    @endif
@endif
<div class="flex flex-wrap items-center gap-2 mt-1">
    <x-badge variant="gray">{{ $p['klaim_desc'] ?? '-' }}</x-badge>
    <x-badge variant="info">{{ $source === 'RI' ? 'Dirawat' : 'Aktif' }}</x-badge>
    @if (!empty($p['masuk_date']))
        <span class="text-xs text-muted-soft">Masuk: {{ $p['masuk_date'] }}</span>
    @endif
</div>
