<?php
// resources/views/pages/transaksi/rj/idrg/kirim-grouping-inacbg.blade.php
// Step 5: Set diagnosa/prosedur INACBG + Grouping stage 1/2 + Final + Re-edit
// Kriteria 15-19

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    #[On('idrg-inacbg-rj.set-diagnosa')]
    public function setDiagnosa(string $rjNo, ?string $diagnosa = null): void
    {
        try {
            [$dataRJ, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            if ($diagnosa === null || $diagnosa === '') {
                $diagnosa = $this->buildDiagnosaString($dataRJ['diagnosis'] ?? []);
                if (empty($diagnosa)) {
                    $this->dispatch('toast', type: 'error', message: 'Tidak ada diagnosa di EMR.');
                    return;
                }
            }

            $res = $this->setDiagnosaInacbg($nomorSep, $diagnosa)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_diagnosa_set gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgDiagnosa'] = $res['response'] ?? [];
            $idrg['inacbgDiagnosaString'] = $diagnosa;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Diagnosa INACBG tersimpan: ' . $diagnosa);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set diagnosa INACBG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.set-prosedur')]
    public function setProsedur(string $rjNo, ?string $procedure = null): void
    {
        try {
            [$dataRJ, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            if ($procedure === null || $procedure === '') {
                $procedure = $this->buildProsedurString($dataRJ['procedure'] ?? []);
                if (empty($procedure)) $procedure = '#';
            }

            $res = $this->setProsedurInacbg($nomorSep, $procedure)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_procedure_set gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgProsedur'] = $res['response'] ?? [];
            $idrg['inacbgProsedurString'] = $procedure;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Prosedur INACBG tersimpan: ' . $procedure);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set prosedur INACBG gagal: ' . $e->getMessage());
        }
    }

    private function buildDiagnosaString(array $diagnosis): string
    {
        if (empty($diagnosis)) return '';
        $primary = [];
        $secondary = [];
        foreach ($diagnosis as $d) {
            $code = trim((string) ($d['icdX'] ?? $d['diagId'] ?? ''));
            if ($code === '') continue;
            if (($d['kategoriDiagnosa'] ?? '') === 'Primary') $primary[] = $code;
            else $secondary[] = $code;
        }
        return implode('#', array_merge($primary, $secondary));
    }

    private function buildProsedurString(array $procedure): string
    {
        if (empty($procedure)) return '';
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
        foreach ($groups as $items) $parts[] = implode('#', $items);
        return implode('#', $parts);
    }

    #[On('idrg-inacbg-rj.group-stage1')]
    public function groupStage1(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->grouperInacbgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 1 gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $payload = $res['response'] ?? [];
            $cbgCode = (string) ($payload['response_inacbg']['cbg']['code'] ?? '');
            // Kriteria 16-17: ungroupable INACBG diawali "X"
            $isUngroupable = str_starts_with($cbgCode, 'X');

            $idrg['inacbgStage1'] = $payload;
            $idrg['inacbgUngroupable'] = $isUngroupable;
            $idrg['inacbgFinal'] = false;
            $this->saveResult($rjNo, $idrg);

            if ($isUngroupable) {
                $this->dispatch('toast', type: 'warning', message: 'INACBG ungroupable (' . $cbgCode . '). Tombol Final INACBG nonaktif.');
            } else {
                $this->dispatch('toast', type: 'success', message: 'INACBG stage 1: ' . $cbgCode);
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 1 gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.group-stage2')]
    public function groupStage2(string $rjNo, string $specialCmg = ''): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->grouperInacbgStage2($nomorSep, $specialCmg)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 2 gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgStage2'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG stage 2 selesai.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 2 gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.final')]
    public function final(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            if (!empty($idrg['inacbgUngroupable'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak bisa final: INACBG masih ungroupable.');
                return;
            }

            $res = $this->finalInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_grouper_final gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgFinal'] = true;
            $idrg['inacbgFinalAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final INACBG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.reedit')]
    public function reedit(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->reeditInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'inacbg_grouper_reedit gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['inacbgFinal'] = false;
            $idrg['inacbgFinalAt'] = null;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG dibuka untuk edit ulang.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit INACBG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.search-diagnosa')]
    public function searchDiagnosa(string $keyword): void
    {
        try {
            $res = $this->searchDiagnosaInacbg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'search_diagnosis gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $this->dispatch('idrg-inacbg-rj.diagnosa-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search diagnosa INACBG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-inacbg-rj.search-prosedur')]
    public function searchProsedur(string $keyword): void
    {
        try {
            $res = $this->searchProsedurInacbg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'search_procedures gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $this->dispatch('idrg-inacbg-rj.prosedur-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search prosedur INACBG gagal: ' . $e->getMessage());
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
