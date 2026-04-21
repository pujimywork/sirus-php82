<?php
// resources/views/pages/transaksi/rj/idrg/kirim-grouping-idrg.blade.php
// Step 4: Grouping → Final → Re-edit iDRG + Import ke INACBG
// Kriteria 5-13: iDRG duluan, tombol final conditional dari hasil grouping

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    #[On('idrg-grouping-rj.group')]
    public function group(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->grouperIdrgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Grouping iDRG'));
                return;
            }

            $groupResult = $res['response'] ?? [];
            // Kriteria 7-8: kode mdc 36 = ungroupable/unrelated → final tidak boleh muncul
            $mdc = $groupResult['mdc_number'] ?? '';
            $isUngroupable = ((string) $mdc === '36');

            $idrg['idrgGroup'] = $groupResult;
            $idrg['idrgUngroupable'] = $isUngroupable;
            $idrg['idrgFinal'] = false;
            $this->saveResult($rjNo, $idrg);

            if ($isUngroupable) {
                $this->dispatch('toast', type: 'warning', message: self::describeUngroupable($groupResult) . ' Tombol Final iDRG tidak aktif.');
            } else {
                $this->dispatch('toast', type: 'success', message: 'Grouping iDRG: ' . ($groupResult['drg_code'] ?? '-'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-rj.final')]
    public function final(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
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
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final iDRG'));
                return;
            }

            $idrg['idrgFinal'] = true;
            $idrg['idrgFinalAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-rj.reedit')]
    public function reedit(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->reeditIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang iDRG'));
                return;
            }

            $idrg['idrgFinal'] = false;
            $idrg['idrgFinalAt'] = null;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG dibuka untuk edit ulang.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-grouping-rj.import-inacbg')]
    public function importInacbg(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 12: INACBG coding hanya setelah iDRG final
            if (empty($idrg['idrgFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'iDRG harus final terlebih dahulu.');
                return;
            }

            $res = $this->importIdrgToInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Import iDRG ke INACBG'));
                return;
            }

            $idrg['inacbgImport'] = $res['response'] ?? [];
            $idrg['inacbgImportedAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            // Kriteria 14: tandai kode IM yang tidak valid (validcode=0, error_no E2101) agar UI bisa highlight
            $this->dispatch('toast', type: 'success', message: 'Import iDRG → INACBG selesai. Cek kode "IM tidak berlaku".');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Import INACBG gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) throw new \RuntimeException('Data RJ tidak ditemukan.');
        return [$dataRJ, $dataRJ['idrg'] ?? []];
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($rjNo, $data);
        });
    }
};
?>
<div></div>
