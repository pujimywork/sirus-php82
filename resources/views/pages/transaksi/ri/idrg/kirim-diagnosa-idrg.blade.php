<?php
// resources/views/pages/transaksi/ri/idrg/kirim-diagnosa-idrg.blade.php
// Step 2: Set / Get diagnosa iDRG (ICD-10 2010 IM)

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    #[On('idrg-diagnosa-ri.set')]
    public function set(string $riHdrNo, ?string $diagnosa = null): void
    {
        try {
            [$dataRI, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // Kalau string diagnosa tidak dikirim, auto-build dari JSON EMR (diagnosis[])
            if ($diagnosa === null || $diagnosa === '') {
                $diagnosa = $this->buildDiagnosaString($dataRI['diagnosis'] ?? []);
                if (empty($diagnosa)) {
                    $this->dispatch('toast', type: 'error', message: 'Tidak ada diagnosa di EMR untuk dikirim.');
                    return;
                }
            }

            $res = $this->setDiagnosaIdrg($nomorSep, $diagnosa)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_diagnosa_set gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgDiagnosa'] = $res['response'] ?? [];
            $idrg['idrgDiagnosaString'] = $diagnosa;
            $this->saveResult($riHdrNo, $idrg);
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

    #[On('idrg-diagnosa-ri.get')]
    public function get(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getDiagnosaIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_diagnosa_get gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgDiagnosa'] = $res['response'] ?? [];
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('idrg-diagnosa-ri.loaded', $idrg['idrgDiagnosa']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get diagnosa iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-diagnosa-ri.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchDiagnosaIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'search_diagnosis_inagrouper gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $this->dispatch('idrg-diagnosa-ri.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search diagnosa iDRG gagal: ' . $e->getMessage());
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
