<?php
// resources/views/pages/transaksi/ri/idrg/kirim-prosedur-idrg.blade.php
// Step 3: Set / Get prosedur iDRG (ICD-9-CM 2010 IM, support multiplicity & setting)

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    #[On('idrg-prosedur-ri.set')]
    public function set(string $riHdrNo, ?string $procedure = null): void
    {
        try {
            [$dataRI, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // Kalau string procedure tidak dikirim, auto-build dari JSON EMR (procedure[])
            if ($procedure === null || $procedure === '') {
                $procedure = $this->buildProsedurString($dataRI['procedure'] ?? []);
                if (empty($procedure)) {
                    // Sesuai manual hal. 26: kirim "#" untuk hapus semua, "" malah berarti no-change.
                    $procedure = '#';
                }
            }

            $res = $this->setProsedurIdrg($nomorSep, $procedure)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'idrg_procedure_set gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgProsedur'] = $res['response'] ?? [];
            $idrg['idrgProsedurString'] = $procedure;
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Prosedur iDRG tersimpan: ' . $procedure);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    /**
     * Susun string prosedur dari array EMR:
     *   procedure[] item: {procedureId, procedureDesc, ketProcedure, multiplicity?, settingGroup?}
     * Output format (manual hal. 26):
     *   - kode dipisah "#" antar setting (operasi berbeda)
     *   - multiplicity notasi "+N" (dilakukan N kali di operasi yang sama)
     *   - contoh: "86.22+3#86.22+2" = debridement 3x di operasi 1, 2x di operasi 2
     * Kalau field multiplicity/settingGroup belum di-input di EMR, fallback: join polos "#".
     */
    private function buildProsedurString(array $procedure): string
    {
        if (empty($procedure)) return '';

        // Group by settingGroup (operasi ke-N). Default group 1 kalau tidak ada.
        $groups = [];
        foreach ($procedure as $p) {
            $code = trim((string) ($p['procedureId'] ?? ''));
            if ($code === '') continue;
            $mult = (int) ($p['multiplicity'] ?? 1);
            $groupKey = (int) ($p['settingGroup'] ?? 1);
            $item = $mult > 1 ? "{$code}+{$mult}" : $code;
            $groups[$groupKey][] = $item;
        }
        ksort($groups);

        $parts = [];
        foreach ($groups as $items) {
            $parts[] = implode('#', $items);
        }
        return implode('#', $parts);
    }

    #[On('idrg-prosedur-ri.get')]
    public function get(string $riHdrNo): void
    {
        try {
            [, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getProsedurIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_procedure_get gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgProsedur'] = $res['response'] ?? [];
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('idrg-prosedur-ri.loaded', $idrg['idrgProsedur']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-prosedur-ri.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchProsedurIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'search_procedures_inagrouper gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $this->dispatch('idrg-prosedur-ri.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search prosedur iDRG gagal: ' . $e->getMessage());
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
