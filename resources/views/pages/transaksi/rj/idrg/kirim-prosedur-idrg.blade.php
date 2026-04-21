<?php
// resources/views/pages/transaksi/rj/idrg/kirim-prosedur-idrg.blade.php
// Step 3: Set / Get prosedur iDRG (ICD-9-CM 2010 IM, support multiplicity & setting)

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    #[On('idrg-prosedur-rj.set')]
    public function set(string $rjNo, ?string $procedure = null): void
    {
        try {
            [$dataRJ, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // Kalau string procedure tidak dikirim, auto-build dari JSON EMR (procedure[])
            if ($procedure === null || $procedure === '') {
                $procedure = $this->buildProsedurString($dataRJ['procedure'] ?? []);
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
            $this->saveResult($rjNo, $idrg);
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

    #[On('idrg-prosedur-rj.get')]
    public function get(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getProsedurIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_procedure_get gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['idrgProsedur'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-prosedur-rj.loaded', $idrg['idrgProsedur']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-prosedur-rj.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchProsedurIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'search_procedures_inagrouper gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $this->dispatch('idrg-prosedur-rj.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search prosedur iDRG gagal: ' . $e->getMessage());
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
