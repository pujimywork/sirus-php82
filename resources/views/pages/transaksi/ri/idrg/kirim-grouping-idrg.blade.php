<?php
// resources/views/pages/transaksi/ri/idrg/kirim-grouping-idrg.blade.php
// Step 4: Grouping → Final → Re-edit iDRG + Import ke INACBG
// Kriteria 5-13: iDRG duluan, tombol final conditional dari hasil grouping

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    #[On('idrg-grouping-ri.group')]
    public function group(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->grouperIdrgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'Grouping iDRG gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $groupResult = $res['response'] ?? [];
            // Kriteria 7-8: kode mdc 36 = ungroupable/unrelated → final tidak boleh muncul
            $mdc = $groupResult['mdc_number'] ?? '';
            $isUngroupable = ((string) $mdc === '36');

            $idrg['idrgGroup'] = $groupResult;
            $idrg['idrgUngroupable'] = $isUngroupable;
            $idrg['idrgFinal'] = false;
            $this->saveResult($riHdrNo, $idrg);

            if ($isUngroupable) {
                $this->dispatch('toast', type: 'warning', message: 'Hasil grouping iDRG: ungroupable / unrelated. Tombol Final iDRG tidak aktif.');
            } else {
                $this->dispatch('toast', type: 'success', message: 'Grouping iDRG: ' . ($groupResult['drg_code'] ?? '-'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-ri.final')]
    public function final(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 7-8
            if (!empty($idrg['idrgUngroupable'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak bisa final: grouping iDRG masih ungroupable.');
                return;
            }
            if (empty($idrg['idrgGroup'])) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada grouping iDRG.');
                return;
            }

            $res = $this->finalIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_grouper_final gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgFinal'] = true;
            $idrg['idrgFinalAt'] = now()->toIso8601String();
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-ri.reedit')]
    public function reedit(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->reeditIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_grouper_reedit gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgFinal'] = false;
            $idrg['idrgFinalAt'] = null;
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG dibuka untuk edit ulang.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-ri.import-inacbg')]
    public function importInacbg(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 12: INACBG coding hanya setelah iDRG final
            if (empty($idrg['idrgFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'iDRG harus final terlebih dahulu.');
                return;
            }

            $res = $this->importIdrgToInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_to_inacbg_import gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgImport'] = $res['response'] ?? [];
            $idrg['inacbgImportedAt'] = now()->toIso8601String();
            $this->saveResult($riHdrNo, $idrg);
            // Kriteria 14: tandai kode IM yang tidak valid (validcode=0, error_no E2101) agar UI bisa highlight
            $this->dispatch('toast', type: 'success', message: 'Import iDRG → INACBG selesai. Cek kode "IM tidak berlaku".');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Import INACBG gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $riHdrNo): array
    {
        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) throw new \RuntimeException('Data RI tidak ditemukan.');
        return [$dataRI, $dataRI['idrg'] ?? []];
    }

    private function saveResult(string $riHdrNo, array $idrg): void
    {
        DB::transaction(function () use ($riHdrNo, $idrg) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($riHdrNo, $data);
        });
    }
};
?>
<div></div>
