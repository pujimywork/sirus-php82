<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-diagnosa-idrg.blade.php
// Step 2: Set / Get diagnosa iDRG (ICD-10 2010 IM) — UGD

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    #[On('idrg-diagnosa-ugd.set')]
    public function set(string $rjNo, ?string $diagnosa = null): void
    {
        try {
            [$dataUGD, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // Kalau string diagnosa tidak dikirim, auto-build dari JSON EMR (diagnosis[])
            if ($diagnosa === null || $diagnosa === '') {
                $diagnosa = $this->buildDiagnosaString($dataUGD['diagnosis'] ?? []);
                if (empty($diagnosa)) {
                    $this->dispatch('toast', type: 'error', message: 'Tidak ada diagnosa di EMR untuk dikirim.');
                    return;
                }
            }

            $res = $this->setDiagnosaIdrg($nomorSep, $diagnosa)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Set Diagnosa iDRG'));
                return;
            }

            $idrg['idrgDiagnosa'] = $res['response'] ?? [];
            $idrg['idrgDiagnosaString'] = $diagnosa;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Diagnosa iDRG tersimpan: ' . $diagnosa);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set diagnosa iDRG gagal: ' . $e->getMessage());
        }
    }

    /**
     * Susun string diagnosa dari array EMR:
     *   diagnosis[] item: {diagId, diagDesc, icdX, kategoriDiagnosa}
     * Output: "S71.0#S87.9#E11.9" — Primary di depan, Secondary di belakang.
     */
    private function buildDiagnosaString(array $diagnosis): string
    {
        if (empty($diagnosis)) return '';
        $primary = [];
        $secondary = [];
        foreach ($diagnosis as $d) {
            $code = trim((string) ($d['icdX'] ?? $d['diagId'] ?? ''));
            if ($code === '') continue;
            if (($d['kategoriDiagnosa'] ?? '') === 'Primary') {
                $primary[] = $code;
            } else {
                $secondary[] = $code;
            }
        }
        return implode('#', array_merge($primary, $secondary));
    }

    #[On('idrg-diagnosa-ugd.get')]
    public function get(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getDiagnosaIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Ambil Diagnosa iDRG'));
                return;
            }

            $idrg['idrgDiagnosa'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-diagnosa-ugd.loaded', $idrg['idrgDiagnosa']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get diagnosa iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-diagnosa-ugd.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchDiagnosaIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cari Diagnosa di iDRG'));
                return;
            }
            $this->dispatch('idrg-diagnosa-ugd.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search diagnosa iDRG gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) throw new \RuntimeException('Data UGD tidak ditemukan.');
        return [$dataUGD, $dataUGD['idrg'] ?? []];
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonUGD($rjNo, $data);
        });
    }
};
?>
<div></div>
