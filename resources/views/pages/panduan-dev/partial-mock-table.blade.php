{{-- Tabel STANDAR (.ds-table) untuk preview Responsive di /panduan-dev.
     Sama persis dengan "Model tabel" — hanya ditampilkan di frame Desktop/Tablet/Mobile.
     Props: $rows = [[id, nama, kode, status(1/0)], ...], $minWidth (opsional, mis. '520px'
     untuk memicu scroll horizontal di frame sempit). --}}
@php $minWidth = $minWidth ?? null; @endphp
<table class="ds-table" @if ($minWidth) style="min-width:{{ $minWidth }}" @endif>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Kode</th>
            <th>Status</th>
            <th class="ds-c">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as [$id, $nama, $kode, $status])
            <tr>
                <td class="ds-td-token">{{ $id }}</td>
                <td class="ds-td-strong">{{ $nama }}</td>
                <td class="ds-td-token">{{ $kode }}</td>
                <td>
                    <x-badge :variant="$status ? 'success' : 'gray'">{{ $status ? 'Aktif' : 'Nonaktif' }}</x-badge>
                </td>
                <td class="ds-c">
                    <div class="flex justify-center gap-2">
                        <x-secondary-button type="button" class="px-2.5 py-1.5 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            Edit
                        </x-secondary-button>
                        <x-confirm-button variant="danger" action="$refresh" title="Hapus data?"
                            message="Yakin hapus {{ $nama }}?" confirmText="Ya, hapus" class="px-2.5 py-1.5 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            Hapus
                        </x-confirm-button>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
