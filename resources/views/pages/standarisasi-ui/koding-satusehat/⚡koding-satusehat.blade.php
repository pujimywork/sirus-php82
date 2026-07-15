<?php

use Livewire\Component;

// Tutorial standarisasi pengiriman data SATUSEHAT (FHIR R4, Kemenkes).
// Gaya sama dgn koding-transaksi/koding-master: sidebar per-submenu,
// snippet = nowdoc (aman compiler Blade). Sumber: docs/satusehat-api.md.
new class extends Component {
    public function snippets(): array
    {
        return [

'auth' => <<<'TXT'
// OAuth2 client_credentials — SatuSehatTrait::getAccessToken()
public function getAccessToken(): string
{
    return Cache::remember('satusehat_access_token', 3500, function () {
        $res = Http::timeout(10)->asForm()->post(
            env('SATUSEHAT_AUTH_URL') . 'accesstoken?grant_type=client_credentials',
            [
                'client_id'     => env('SATUSEHAT_CLIENT_ID'),
                'client_secret' => env('SATUSEHAT_SECRET_ID'),   // catat: _SECRET_ID
            ],
        );
        return $res->json()['access_token'];
    });
}

// TTL cache hardcoded 3500 dtk (~58 mnt) — nilai expires_in dari server DIABAIKAN.
// Header tiap panggilan FHIR:
//   Authorization: Bearer {token}
//   Organization-Id: {SATUSEHAT_ORGANIZATION_ID}   (tetap 100027469)
// Base URL: SATUSEHAT_BASE_URL -> .../fhir-r4/v1/   (PRODUCTION)
//
// Ganti environment = ganti NILAI env (tak ada toggle di kode).
//   Sandbox Kemkes: host api-satusehat-stg.kemkes.go.id
// AWAS: semua kredensial dibaca env() langsung TANPA wrapper config/*.php
//       -> integrasi mati SENYAP bila `php artisan config:cache` dijalankan.
TXT,

'transport' => <<<'TXT'
// makeRequest — SATU resource = SATU HTTP call (BUKAN FHIR Bundle)
public function makeRequest(string $method, string $endpoint, array $data = [])
{
    $res = Http::timeout(10)                          // tanpa connectTimeout/retry (backlog)
        ->withToken($this->getAccessToken())
        ->withHeaders(['Organization-Id' => env('SATUSEHAT_ORGANIZATION_ID')])
        ->{$method}(env('SATUSEHAT_BASE_URL') . $endpoint, $data);

    $this->logSatuSehat($res, $endpoint, $data);      // insert ke web_log_status

    if ($res->successful()) return $res->json();      // 2xx -> array
    throw new \Exception('API request failed: ' . $res->body());   // caller (blade) tangkap Throwable -> toast
}

// Tiap resource = POST/PUT terpisah: POST Encounter, POST Condition, POST Observation, ...
// Tabel audit web_log_status: code, date_ref, response, http_req,
//   http_payload, requestTransferTime -> sumber verifikasi tiap kiriman.
TXT,

'encounter-lifecycle' => <<<'TXT'
// Encounter = AKAR. Semua resource lain mereferensikan Encounter/{id},
//   Patient/{id}, Practitioner/{id}. Siklus status 3 tahap:
//
//   POST  /Encounter                 -> status "arrived"
//   PUT   /Encounter/{id}            -> "in-progress"  (startRoomEncounter)
//   PUT   /Encounter/{id}            -> "finished"     (hanya bila txnStatus=CLOSED / rjStatus=2)
//
// Kalau kirim Encounter GAGAL -> seluruh rangkaian berhenti (ROOT, wajib sukses).
// Prasyarat: rsmst_doctors.dr_uuid & rsmst_polis.poli_uuid TIDAK boleh kosong
//   -> kalau kosong, kirim Encounter berhenti dgn toast error.
// class = AMB (http://terminology.hl7.org/CodeSystem/v3-ActCode) = rawat jalan/ambulatory.
TXT,

'ihs' => <<<'TXT'
// IHS = identitas resource di SATUSEHAT (di-set SEKALI di master, bukan dilookup tiap kirim)

// Pasien — cari dulu, kalau kosong buat:
$ihs = $this->searchPatient(['nik' => $nik]);        // GET /Patient?identifier=.../nik|{nik}
if (! $ihs) $ihs = $this->createPatient($regNo);     // dari Master Pasien
// disimpan: rsmst_pasiens.patient_uuid (+ JSON pasien.identitas.patientUuid)

// Dokter        -> rsmst_doctors.dr_uuid   (di-set manual)
// Poli/Location -> rsmst_polis.poli_uuid   (di-set manual)
// Organization  -> env SATUSEHAT_ORGANIZATION_ID  (tetap)

// NIK wajib 16 digit; kalau tidak, identifier di-skip DIAM-DIAM (PatientTrait).
TXT,

'kirim-component' => <<<'TXT'
// ⚡kirim-<resource>-rj-actions.blade.php — satu tombol per-resource di UI RJ
new class extends Component {
    use SatuSehatTrait, ProcedureTrait, EmrRJTrait;

    public ?int  $rjNo = null;
    public array $ss = [];                 // node JSON 'satusehat' pada record RJ
    public bool  $hasEncounter = false;    // gate: Encounter harus sudah terkirim

    #[On('open-kirim-procedure-rj')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $data = $this->findDataRJ($rjNo);              // baca CLOB (OracleLob)
        $this->ss = $data['satusehat'] ?? [];
        $this->hasEncounter = ! empty($this->ss['encounterId']);
    }

    public function kirimForCurrent(): void
    {
        if (! empty($this->ss['procedureIds'])) {      // idempotensi (guard lokal in-memory)
            $this->dispatch('toast', type: 'info', message: 'Sudah pernah dikirim.');
            return;
        }
        $ids = [];
        foreach ($tindakanList as $t) {
            if (empty($t['kodeIcd9'])) continue;       // item tanpa kode -> skip diam-diam
            $res   = $this->createProcedure($this->ss['encounterId'], $t);
            $ids[] = $res['id'];
        }
        $this->saveResult(['procedureIds' => $ids]);   // tulis balik node JSON satusehat
    }

    private function saveResult(array $patch): void
    {
        DB::transaction(function () use ($patch) {
            $this->lockRJRow($this->rjNo);             // row-lock anti race (pola RMW)
            $data = $this->findDataRJ($this->rjNo);
            $data['satusehat'] = array_replace($data['satusehat'] ?? [], $patch);
            $this->updateJsonRJ($this->rjNo, $data);
        });
    }
};
// Markup: tombol Kirim :disabled="!$hasEncounter" -> semua non-Encounter menunggu Encounter.
TXT,

'add-resource' => <<<'TXT'
// Menambah / mengaktifkan resource FHIR baru

// A) SUDAH ADA trait (Dispense / ServiceRequest / Specimen / DiagnosticReport / Allergy):
//    1. buat komponen kirim-<resource>-rj-actions.blade.php meniru kirim-procedure
//    2. render di satu-sehat-rj-actions.blade.php (deret tombol per-resource, ~baris 105-114)
//    3. gate :disabled="!$hasEncounter" + simpan id hasil ke node JSON 'satusehat'

// B) BELUM ada trait (Composition/Diet · ClinicalImpression · ImagingStudy ·
//                      Immunization · EpisodeOfCare · NutritionOrder):
//    1. buat App\Http\Traits\SATUSEHAT\<Resource>Trait meniru ProcedureTrait
//    2. bangun payload FHIR R4 + POST /<Resource> via makeRequest()
//    3. WAJIB referensi Encounter/{id} + subject Patient/{id}

// UJI DI SANDBOX dulu (ganti env AUTH/BASE URL ke -stg).
// Verifikasi via tabel web_log_status (http_req / http_payload / response).
TXT,

'ss-imaging' => <<<'TXT'
// ImagingStudy — Radiologi.  POST /ImagingStudy
// GAP: butuh DICOM UID (study/series/SOP). Modul radiologi kita upload-based,
//      UID DICOM TIDAK tersimpan -> generate OID sendiri atau kirim minimal.
public function createImagingStudy(array $data): array
{
    $payload = [
        'resourceType' => 'ImagingStudy',
        'identifier'   => [[
            'system' => 'urn:dicom:uid',
            'value'  => 'urn:oid:' . $data['studyUid'],
        ]],
        'status'    => 'available',
        'subject'   => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
        'started'   => $data['started'] ?? now()->toIso8601String(),
        'numberOfSeries'    => count($data['series']),
        'numberOfInstances' => $data['numberOfInstances'] ?? 1,
        'referrer'      => ['reference' => 'Practitioner/' . $data['referrerId']],
        'procedureCode' => [[
            'coding' => [[
                'system'  => 'http://hl7.org/fhir/sid/icd-9-cm',   // atau LOINC
                'code'    => $data['procedureCode'],
                'display' => $data['procedureDisplay'],
            ]],
        ]],
        'series' => array_map(fn ($s) => [
            'uid'      => $s['seriesUid'],
            'number'   => $s['number'] ?? 1,
            'modality' => [
                'system'  => 'http://dicom.nema.org/resources/ontology/DCM',
                'code'    => $s['modality'],           // CR, CT, US, MR, ...
                'display' => $s['modalityDisplay'],
            ],
            'numberOfInstances' => count($s['instances'] ?? [1]),
            'started'  => $s['started'] ?? now()->toIso8601String(),
        ], $data['series']),
    ];
    return $this->makeRequest('post', '/ImagingStudy', $payload);
}
// Sumber SIRUS: modul Radiologi (rstxn_*rads / rsview_rads). UID DICOM = gap.
TXT,

'ss-immunization' => <<<'TXT'
// Immunization — Imunisasi.  POST /Immunization
// GAP: belum ada modul imunisasi -> perlu form capture (vaksin, lot, rute, dosis).
public function createImmunization(array $data): array
{
    $payload = [
        'resourceType' => 'Immunization',
        'status'       => $data['status'] ?? 'completed',
        'vaccineCode'  => [
            'coding' => [[
                'system'  => 'http://sys-ids.kemkes.go.id/kfa',   // KFA vaksin
                'code'    => $data['kfaCode'],
                'display' => $data['kfaDisplay'],
            ]],
        ],
        'patient'            => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'          => ['reference' => 'Encounter/' . $data['encounterId']],
        'occurrenceDateTime' => $data['occurrence'] ?? now()->toIso8601String(),
        'primarySource'      => true,
        'location'           => ['reference' => 'Location/' . $data['locationId']],
        'lotNumber'          => $data['lotNumber'] ?? null,
        'route' => [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-RouteOfAdministration',
                'code'    => $data['routeCode'] ?? 'IM',
                'display' => $data['routeDisplay'] ?? 'Injection, intramuscular',
            ]],
        ],
        'doseQuantity' => [
            'value'  => $data['doseValue'] ?? 0.5,
            'system' => 'http://unitsofmeasure.org',
            'code'   => 'mL',
        ],
        'performer' => [[
            'actor' => ['reference' => 'Practitioner/' . $data['performerId']],
        ]],
    ];
    return $this->makeRequest('post', '/Immunization', $payload);
}
// Sumber SIRUS: perlu modul/riwayat imunisasi baru (KFA vaksin dari master obat).
TXT,

'ss-nutrition' => <<<'TXT'
// NutritionOrder — Instruksi Gizi.  POST /NutritionOrder
public function createNutritionOrder(array $data): array
{
    $payload = [
        'resourceType' => 'NutritionOrder',
        'status'       => $data['status'] ?? 'active',
        'intent'       => 'order',
        'patient'      => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'    => ['reference' => 'Encounter/' . $data['encounterId']],
        'dateTime'     => $data['dateTime'] ?? now()->toIso8601String(),
        'orderer'      => ['reference' => 'Practitioner/' . $data['ordererId']],
        'oralDiet'     => [
            'type' => [[
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['dietCode'],       // mis. 435801000124108
                    'display' => $data['dietDisplay'],
                ]],
                'text' => $data['dietText'],              // "Diet rendah garam", dst.
            ]],
        ],
    ];
    return $this->makeRequest('post', '/NutritionOrder', $payload);
}
// Sumber SIRUS: order diet EMR (role Gizi / diet RI).
TXT,

'ss-episode' => <<<'TXT'
// EpisodeOfCare — Episode Perawatan (utamanya RAWAT INAP).  POST /EpisodeOfCare
// Mengelompokkan banyak Encounter dlm satu episode -> Encounter.episodeOfCare[].
public function createEpisodeOfCare(array $data): array
{
    $payload = [
        'resourceType' => 'EpisodeOfCare',
        'identifier'   => [[
            'system' => 'http://sys-ids.kemkes.go.id/episodeofcare/' . $this->organizationId,
            'value'  => $data['episodeNo'],               // mis. rihdr_no
        ]],
        'status' => $data['status'] ?? 'active',          // active | finished | cancelled
        'type'   => [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/episodeofcare-type',
                'code'    => 'hacc',
                'display' => 'Home and Community Care',
            ]],
        ]],
        'patient'              => ['reference' => 'Patient/' . $data['patientId']],
        'managingOrganization' => ['reference' => 'Organization/' . $this->organizationId],
        'period' => array_filter([
            'start' => $data['start'] ?? now()->toIso8601String(),
            'end'   => $data['end'] ?? null,              // diisi saat pasien pulang
        ]),
        'careManager' => ['reference' => 'Practitioner/' . $data['careManagerId']],
    ];
    return $this->makeRequest('post', '/EpisodeOfCare', $payload);
}
// Sumber SIRUS: rstxn_rihdrs (satu episode per rawat inap; DPJP = careManager).
TXT,

'ss-clinical' => <<<'TXT'
// ClinicalImpression — Impresi Klinik (asesmen "A" di SOAP).  POST /ClinicalImpression
public function createClinicalImpression(array $data): array
{
    $payload = [
        'resourceType' => 'ClinicalImpression',
        'status'       => $data['status'] ?? 'completed',
        'description'  => $data['description'] ?? null,
        'subject'      => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'    => ['reference' => 'Encounter/' . $data['encounterId']],
        'effectiveDateTime' => $data['effective'] ?? now()->toIso8601String(),
        'date'         => now()->toIso8601String(),
        'assessor'     => ['reference' => 'Practitioner/' . $data['assessorId']],
        'summary'      => $data['summary'],               // teks asesmen klinis
        'finding'      => array_map(fn ($f) => [
            'itemCodeableConcept' => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $f['code'],
                    'display' => $f['display'],
                ]],
            ],
        ], $data['findings'] ?? []),
    ];
    return $this->makeRequest('post', '/ClinicalImpression', $payload);
}
// Sumber SIRUS: section Penilaian/Assessment EMR (narasi asesmen dokter).
TXT,

'ss-composition' => <<<'TXT'
// Composition — dokumen klinis terstruktur (dashboard label: "Diet").  POST /Composition
public function createComposition(array $data): array
{
    $payload = [
        'resourceType' => 'Composition',
        'identifier'   => [
            'system' => 'http://sys-ids.kemkes.go.id/composition/' . $this->organizationId,
            'value'  => $data['docNo'],
        ],
        'status' => $data['status'] ?? 'final',
        'type'   => [
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => $data['loincDocType'],       // jenis dokumen (LOINC)
                'display' => $data['loincDisplay'],
            ]],
        ],
        'subject'   => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
        'date'      => $data['date'] ?? now()->toIso8601String(),
        'author'    => [['reference' => 'Practitioner/' . $data['authorId']]],
        'title'     => $data['title'],
        'section'   => array_map(fn ($s) => [
            'title' => $s['title'],
            'code'  => ['coding' => [$s['code']]],
            'text'  => ['status' => 'generated', 'div' => $s['html']],
        ], $data['sections'] ?? []),
    ];
    return $this->makeRequest('post', '/Composition', $payload);
}
// Sumber SIRUS: narasi EMR (ringkasan/rencana). Catatan: label "Diet" dari Kemkes.
TXT,

        ];
    }
};

?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'arsitektur'  => 'Arsitektur & 2 Jalur',
                'autentikasi' => 'Autentikasi & Environment',
            ],
            'Pengiriman' => [
                'transport' => 'Transport & Logging',
                'ihs'       => 'Resolusi IHS Code',
                'urutan'    => 'Model & Urutan Kirim',
                'checklist' => 'Checklist & Langkah Kirim',
                'standar'   => 'Standarisasi per Resource',
                'ri-ugd'    => 'Status per Modul (RJ/UGD/RI)',
            ],
            'Adopsi' => [
                'dashboard'  => 'Peta Dashboard SATUSEHAT',
                'belum-ada'  => 'Resource Belum Ada — Kirim',
                'backlog'    => 'Backlog & Gotcha',
                'tambah'     => 'Menambah Resource Baru',
                'glosarium'  => 'Glosarium FHIR',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('standarisasi-ui') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding SATUSEHAT</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('standarisasi-ui.koding-transaksi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Transaksi</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Prasyarat: <a href="{{ route('standarisasi-ui.koding-transaksi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Transaksi</a><br>
                            Ruang lingkup aktif: <span class="ds-code">transaksi/rj</span> (RJ)<br>
                            Sumber: <span class="ds-code">docs/satusehat-api.md</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Pengiriman Data ke SATUSEHAT</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>SATUSEHAT</strong> adalah platform interoperabilitas data kesehatan
                            Kemenkes berbasis <strong>FHIR R4</strong>. Setiap kunjungan pasien yang kita
                            layani harus dikirim ulang ke SATUSEHAT sebagai sekumpulan
                            <em>resource</em> FHIR (Encounter, Condition, Observation, Procedure, dst.).
                            Tutorial ini merangkum <strong>cara sistem mengirim</strong> dan
                            <strong>standarisasi data</strong> tiap resource — berbasis implementasi nyata
                            di repo, bukan teori.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Lapisan trait: <span class="ds-code">app/Http/Traits/SATUSEHAT/*.php</span>
                            (20 file, ±3.200 baris). Lapisan UI aktif:
                            <span class="ds-code">transaksi/rj/satu-sehat/*.blade.php</span> +
                            <span class="ds-code">daftar-rj/satu-sehat-rj-actions.blade.php</span>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">FHIR R4, per-resource</div>
                                <div class="ds-body-sm">Tiap resource = satu HTTP call terpisah (<span class="ds-code">POST Encounter</span>, <span class="ds-code">POST Condition</span>…), <strong>bukan</strong> FHIR Bundle.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Encounter = akar</div>
                                <div class="ds-body-sm">Semua resource mereferensikan <span class="ds-code">Encounter/{id}</span>. Encounter wajib sukses dulu; kalau gagal semua berhenti.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Kode terstandar</div>
                                <div class="ds-body-sm">ICD-10 (diagnosa), ICD-9-CM (tindakan), LOINC (observasi), SNOMED (keluhan/alergi), KFA (obat).</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Ruang lingkup aktif = Rawat Jalan (RJ).</strong>
                                UGD &amp; RI <em>belum</em> punya alur kirim SATUSEHAT — pola di bab-bab
                                ini adalah cetak-biru saat kita memperluasnya ke jalur lain.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dapat tugas mengaktifkan resource?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah')">Menambah Resource Baru</button> —
                                bedakan dulu apakah trait-nya sudah ada
                                (<button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('dashboard')">lihat peta dashboard</button>) atau harus dibuat dari nol.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ARSITEKTUR ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur &amp; 2 Jalur Kirim</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Satu trait inti (<span class="ds-code">SatuSehatTrait</span>) memegang
                            <em>transport</em> &amp; autentikasi; di atasnya sederet
                            <strong>resource trait</strong> membangun payload FHIR dan menembak endpoint
                            masing-masing.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Lapisan trait</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">SatuSehatTrait  (core/transport)
  · initializeSatuSehat()  · getAccessToken()  · makeRequest()  · logSatuSehat()
        ▲ di-`use` oleh SEMUA resource trait di bawah

Resource traits (bangun payload FHIR + POST/PUT):
  Encounter · Condition · Observation · Procedure · AllergyIntolerance ·
  MedicationRequest · MedicationDispense · ServiceRequest · Specimen ·
  DiagnosticReport · Patient · Practitioner · Organization · Location
  (Loinc / Snomed = lookup terminologi)

UI RJ (Livewire, satu tombol per-resource):
  satu-sehat-rj-actions ──buka modal──▶ kirim-encounter │ kirim-condition │
  kirim-observation │ kirim-procedure │ kirim-medication-request</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Dua jalur kirim yang WAJIB dibedakan</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-2">1 · Jalur UI aktif (produksi)</div>
                                <div class="ds-body-sm">
                                    5 komponen Livewire per-langkah, masing-masing tombol
                                    <strong>Kirim</strong> sendiri. Menyimpan hasil ke node JSON
                                    <span class="ds-code">satusehat</span> pada record RJ.
                                    <strong>Ini yang benar-benar dipakai.</strong>
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">2 · Orkestrator batch (blueprint)</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">KirimRawatJalanTrait::kirimRawatJalan()</span> —
                                    11 langkah sekali jalan, lengkap (alergi, dispense, lab). Tapi
                                    <strong>belum di-<span class="ds-code">use</span> komponen/route manapun</strong>
                                    — anggap cadangan, bukan jalur produksi.
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 03 AUTENTIKASI ====== --}}
                    <section x-show="section === 'autentikasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Autentikasi &amp; Environment</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            OAuth2 <strong>client_credentials</strong> — token di-cache lalu dipasang
                            sebagai <span class="ds-code">Bearer</span> di tiap panggilan FHIR bersama
                            header <span class="ds-code">Organization-Id</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">getAccessToken() + header</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['auth'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Hal</th><th>Nilai / Cara</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Token endpoint</td><td class="ds-body-sm"><span class="ds-code">SATUSEHAT_AUTH_URL . "accesstoken?grant_type=client_credentials"</span> (POST <span class="ds-code">asForm</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Kredensial</td><td class="ds-body-sm">env <span class="ds-code">SATUSEHAT_CLIENT_ID</span>, <strong><span class="ds-code">SATUSEHAT_SECRET_ID</span></strong> (catat: <span class="ds-code">_SECRET_ID</span>, bukan <span class="ds-code">_CLIENT_SECRET</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Cache token</td><td class="ds-body-sm"><span class="ds-code">Cache::remember('satusehat_access_token', 3500, …)</span> — TTL hardcoded ~58 mnt, <span class="ds-code">expires_in</span> diabaikan</td></tr>
                                    <tr><td class="ds-td-strong">Header API</td><td class="ds-body-sm"><span class="ds-code">Authorization: Bearer {token}</span> + <span class="ds-code">Organization-Id: {SATUSEHAT_ORGANIZATION_ID}</span></td></tr>
                                    <tr><td class="ds-td-strong">Base URL FHIR</td><td class="ds-body-sm"><span class="ds-code">SATUSEHAT_BASE_URL</span> → <span class="ds-code">.../fhir-r4/v1/</span> (<strong>PRODUCTION</strong>)</td></tr>
                                    <tr><td class="ds-td-strong">Versi</td><td class="ds-body-sm">FHIR <strong>R4</strong>; profil <span class="ds-code">https://fhir.kemkes.go.id/r4/StructureDefinition/*</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Switch environment = ganti nilai env</strong> (tak ada toggle di kode).
                                Sandbox Kemkes umumnya <span class="ds-code">api-satusehat-stg.kemkes.go.id</span>.
                                <br><strong>Bahaya:</strong> semua kredensial dibaca <span class="ds-code">env()</span> langsung
                                tanpa wrapper <span class="ds-code">config/*.php</span> — kalau
                                <span class="ds-code">php artisan config:cache</span> dijalankan di production,
                                <span class="ds-code">env()</span> runtime → <span class="ds-code">null</span> →
                                integrasi mati senyap.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 TRANSPORT ====== --}}
                    <section x-show="section === 'transport'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Transport &amp; Logging</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Semua kiriman lewat satu pintu: <span class="ds-code">makeRequest($method, $endpoint, $data)</span>.
                            <strong>Bukan FHIR Bundle</strong> — tiap resource satu HTTP call terpisah.
                            Setiap call di-audit ke tabel <span class="ds-code">web_log_status</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">makeRequest() + logSatuSehat()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transport'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Sukses vs gagal</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>2xx → <span class="ds-code">$response->json()</span> (array)</li>
                                    <li>Gagal → <span class="ds-code">throw \Exception('API request failed: '.body)</span></li>
                                    <li>Caller (blade) tangkap <span class="ds-code">\Throwable</span> → toast error</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Audit — <span class="ds-code">web_log_status</span></div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Kolom: <span class="ds-code">code, date_ref, response, http_req, http_payload, requestTransferTime</span></li>
                                    <li>Sumber verifikasi tiap kiriman (payload &amp; balasan server)</li>
                                    <li><span class="ds-code">Http::timeout(10)</span> tanpa <span class="ds-code">connectTimeout/retry</span> → rawan freeze (backlog)</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 05 IHS ====== --}}
                    <section x-show="section === 'ihs'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Resolusi IHS Code</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            IHS = identitas resource di SATUSEHAT. Sumbernya kolom master
                            (di-set sekali), <strong>bukan</strong> dilookup tiap kirim.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Entitas</th><th>IHS disimpan di</th><th>Cara isi</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pasien</td><td class="ds-td-class">rsmst_pasiens.patient_uuid</td><td class="ds-body-sm"><span class="ds-code">searchPatient(['nik'=>…])</span> → kalau kosong <span class="ds-code">createPatient()</span></td></tr>
                                    <tr><td class="ds-td-strong">Dokter</td><td class="ds-td-class">rsmst_doctors.dr_uuid</td><td class="ds-body-sm">manual (<span class="ds-code">searchPractitioner</span> tersedia, tak dipakai runtime)</td></tr>
                                    <tr><td class="ds-td-strong">Poli / Location</td><td class="ds-td-class">rsmst_polis.poli_uuid</td><td class="ds-body-sm">manual (<span class="ds-code">searchLocation/createLocation</span> tersedia)</td></tr>
                                    <tr><td class="ds-td-strong">Organization</td><td class="ds-td-class">env SATUSEHAT_ORGANIZATION_ID</td><td class="ds-body-sm">tetap (<span class="ds-code">100027469</span>)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Resolusi IHS pasien</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ihs'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kalau <span class="ds-code">dr_uuid</span> / <span class="ds-code">poli_uuid</span>
                                kosong → kirim Encounter berhenti dengan toast error. NIK harus 16 digit;
                                kalau tidak, identifier di-skip <strong>diam-diam</strong>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 06 URUTAN ====== --}}
                    <section x-show="section === 'urutan'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Model &amp; Urutan Kirim</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Urutan kanonik (dari orkestrator <span class="ds-code">KirimRawatJalanTrait</span>).
                            Di UI aktif langkah 1–4 + 7 tersedia sebagai tombol; sisanya baru ada di trait.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Langkah</th><th>Resource FHIR</th><th>Sistem kode</th><th>Gate</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">1</td><td class="ds-body-sm">Kunjungan</td><td class="ds-td-class">Encounter</td><td class="ds-body-sm">class AMB (v3-ActCode)</td><td class="ds-body-sm" style="color:var(--primary)"><strong>ROOT — wajib sukses</strong></td></tr>
                                    <tr><td class="ds-td-strong">2</td><td class="ds-body-sm">Diagnosa</td><td class="ds-td-class">Condition (encounter-diagnosis)</td><td class="ds-body-sm">ICD-10</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">3</td><td class="ds-body-sm">Tanda vital</td><td class="ds-td-class">Observation (vital-signs)</td><td class="ds-body-sm">LOINC</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">4</td><td class="ds-body-sm">Tindakan</td><td class="ds-td-class">Procedure</td><td class="ds-body-sm">ICD-9-CM</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">5</td><td class="ds-body-sm">Keluhan utama</td><td class="ds-td-class">Condition (problem-list-item)</td><td class="ds-body-sm">SNOMED</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">6</td><td class="ds-body-sm">Alergi</td><td class="ds-td-class">AllergyIntolerance</td><td class="ds-body-sm">SNOMED</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">7</td><td class="ds-body-sm">Peresepan obat</td><td class="ds-td-class">MedicationRequest</td><td class="ds-body-sm">KFA</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">8</td><td class="ds-body-sm">Obat dibawa pulang</td><td class="ds-td-class">MedicationDispense</td><td class="ds-body-sm">KFA</td><td class="ds-body-sm">fail-soft</td></tr>
                                    <tr><td class="ds-td-strong">9–11</td><td class="ds-body-sm">Penunjang lab</td><td class="ds-td-class">ServiceRequest → Observation → DiagnosticReport</td><td class="ds-body-sm">LOINC</td><td class="ds-body-sm">fail-soft</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Siklus status Encounter (akar)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['encounter-lifecycle'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Encounter = akar</div>
                                <div class="ds-body-sm">Semua resource lain mereferensikan <span class="ds-code">Encounter/{id}</span>, <span class="ds-code">Patient/{id}</span>, <span class="ds-code">Practitioner/{id}</span>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Idempotensi rapuh</div>
                                <div class="ds-body-sm">Guard in-memory <span class="ds-code">$ss</span> + node JSON <span class="ds-code">satusehat</span>. Hanya Encounter &amp; ServiceRequest punya natural key di server — sisanya <strong>hati-hati kirim dobel</strong> bila state JSON hilang.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Skip diam-diam</div>
                                <div class="ds-body-sm">Item tanpa kode kunci di-<span class="ds-code">continue</span>: diagnosa tanpa <span class="ds-code">kodeIcdx</span>, tindakan tanpa <span class="ds-code">kodeIcd9</span>, obat tanpa <span class="ds-code">kfaCode</span>. Bisa "berhasil (0 item)".</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Penyimpanan hasil:</strong> node <span class="ds-code">satusehat</span> di JSON RJ →
                                <span class="ds-code">encounterId</span>, <span class="ds-code">conditionIds[]</span>,
                                <span class="ds-code">observationIds[]</span>, <span class="ds-code">procedureIds[]</span>,
                                <span class="ds-code">medicationRequestIds[]</span>, flag
                                <span class="ds-code">encounterInProgress</span>/<span class="ds-code">encounterFinished</span>.
                                Ditulis via <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockRJRow</span> + <span class="ds-code">updateJsonRJ</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 07 CHECKLIST & LANGKAH KIRIM ====== --}}
                    <section x-show="section === 'checklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Checklist Kolom Wajib &amp; Langkah Kirim</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Panduan <strong>petugas</strong>: kolom apa saja yang <strong>wajib terisi</strong>
                            agar tiap tombol "Kirim" di modal <em>Satu Sehat</em> (Daftar RJ) berhasil,
                            dan urutan langkahnya. Prinsip: <strong>tanpa kode standar (IHS / SNOMED / LOINC /
                            KFA / ICD), item di-skip diam-diam</strong> — isi master dulu supaya tidak "berhasil (0 item)".
                        </p>

                        @php
                            $stepBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:9999px;background:var(--primary);color:#fff;font-size:13px;font-weight:700;line-height:1;flex:none';
                            $numBadge  = 'display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:9999px;background:var(--surface-dark-soft,#1f2937);color:#fff;font-size:11px;font-weight:700;flex:none';
                        @endphp

                        {{-- ===== BAGIAN 1: DUA LAPIS PRASYARAT ===== --}}
                        <h2 class="ds-title-lg mb-3">1 · Dua lapis prasyarat</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-1">A · Setup Master <span class="ds-caption" style="color:var(--muted)">(sekali per entitas)</span></div>
                                <div class="ds-body-sm mb-3" style="color:var(--muted)">Tanpa ini, kirim langsung gagal.</div>
                                <ul class="ds-body-sm space-y-2" style="list-style:none; padding:0">
                                    <li>🆔 <strong>IHS Pasien</strong> — <span class="ds-code">rsmst_pasiens.patient_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Pasien (otomatis via NIK 16 digit) · WAJIB semua resource</span></li>
                                    <li>🩺 <strong>IHS Dokter</strong> — <span class="ds-code">rsmst_doctors.dr_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Dokter · WAJIB Encounter, Alergi, Lab, Radiologi, Dispense, Impresi</span></li>
                                    <li>🏥 <strong>IHS Poli</strong> — <span class="ds-code">rsmst_polis.poli_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Poli · WAJIB Encounter</span></li>
                                    <li>💊 <strong>KFA Obat</strong> — <span class="ds-code">product_id_satusehat</span><br><span class="ds-caption" style="color:var(--muted)">Master Obat · WAJIB Resep &amp; Obat Pulang</span></li>
                                    <li>🧪 <strong>LOINC Lab</strong> — <span class="ds-code">lbmst_clabitems.loinc_code</span><br><span class="ds-caption" style="color:var(--muted)">Master Lab (per item) · WAJIB Observasi Lab</span></li>
                                    <li>🏢 <strong>Organization Id</strong> — <span class="ds-code">env SATUSEHAT_ORGANIZATION_ID</span> <span class="ds-caption" style="color:var(--muted)">(tetap)</span></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-1">B · Isi per Kunjungan <span class="ds-caption" style="color:var(--muted)">(EMR RJ)</span></div>
                                <div class="ds-body-sm mb-3" style="color:var(--muted)">Diisi petugas saat pelayanan; yang kosong → resource-nya tak terkirim.</div>
                                <ul class="ds-body-sm space-y-2" style="list-style:none; padding:0">
                                    <li>📝 <strong>Keluhan Utama</strong> + <strong>Kode SNOMED</strong> <span class="ds-caption" style="color:var(--muted)">(Anamnesa)</span></li>
                                    <li>⚠️ <strong>Alergi</strong> + <strong>Kode SNOMED</strong> <span class="ds-caption" style="color:var(--muted)">(Anamnesa)</span></li>
                                    <li>❤️ <strong>Tanda Vital</strong> (TD/nadi/suhu/RR) <span class="ds-caption" style="color:var(--muted)">(Pemeriksaan)</span></li>
                                    <li>🩹 <strong>Diagnosa ICD-10</strong> <span class="ds-caption" style="color:var(--muted)">(Diagnosa)</span></li>
                                    <li>✂️ <strong>Tindakan ICD-9</strong> <span class="ds-caption" style="color:var(--muted)">(Perencanaan)</span></li>
                                    <li>💊 <strong>E-Resep</strong> (obat ber-KFA)</li>
                                    <li>🧪 <strong>Hasil Lab selesai</strong> (status ≠ Pending)</li>
                                    <li>🩻 <strong>Order Radiologi</strong></li>
                                </ul>
                            </div>
                        </div>

                        {{-- ===== BAGIAN 2: TABEL KOLOM WAJIB PER KARTU ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">2 · Kolom wajib per tombol Kirim</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Kartu (Resource)</th><th>Kolom / field WAJIB</th><th>Di mana diisi</th><th>Kalau kosong</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">1</td><td class="ds-body-sm">Encounter</td><td class="ds-body-sm">IHS pasien + <span class="ds-code">dr_uuid</span> + <span class="ds-code">poli_uuid</span></td><td class="ds-body-sm">Master Pasien/Dokter/Poli</td><td class="ds-body-sm" style="color:var(--primary)"><strong>Gagal → semua terkunci</strong></td></tr>
                                    <tr><td class="ds-td-strong">2</td><td class="ds-body-sm">Condition (diagnosa)</td><td class="ds-body-sm">Diagnosa ICD-10 (<span class="ds-code">kodeIcdx</span>)</td><td class="ds-body-sm">EMR › Diagnosa</td><td class="ds-body-sm">Item tanpa kode di-skip</td></tr>
                                    <tr><td class="ds-td-strong">3</td><td class="ds-body-sm">Observation</td><td class="ds-body-sm">Tanda vital (TD/nadi/suhu/RR)</td><td class="ds-body-sm">EMR › Pemeriksaan</td><td class="ds-body-sm">Tak ada vital → gagal</td></tr>
                                    <tr><td class="ds-td-strong">4</td><td class="ds-body-sm">Procedure</td><td class="ds-body-sm">Tindakan ICD-9 (<span class="ds-code">kodeIcd9</span>)</td><td class="ds-body-sm">EMR › Perencanaan</td><td class="ds-body-sm">Item tanpa kode di-skip</td></tr>
                                    <tr><td class="ds-td-strong">5</td><td class="ds-body-sm">MedicationRequest</td><td class="ds-body-sm">E-Resep + KFA (<span class="ds-code">product_id_satusehat</span>)</td><td class="ds-body-sm">E-Resep + Master Obat</td><td class="ds-body-sm">Obat tanpa KFA di-skip</td></tr>
                                    <tr><td class="ds-td-strong">6</td><td class="ds-body-sm">Chief Complaint</td><td class="ds-body-sm">Keluhan utama + <strong>Kode SNOMED</strong></td><td class="ds-body-sm">EMR › Anamnesa (LOV SNOMED)</td><td class="ds-body-sm">Tanpa SNOMED → ditolak (toast)</td></tr>
                                    <tr><td class="ds-td-strong">7</td><td class="ds-body-sm">Allergy Intolerance</td><td class="ds-body-sm">Alergi + <strong>Kode SNOMED</strong> + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › Anamnesa + Master Dokter</td><td class="ds-body-sm">Tanpa SNOMED/IHS dokter → ditolak</td></tr>
                                    <tr><td class="ds-td-strong">8</td><td class="ds-body-sm">Medication Dispense</td><td class="ds-body-sm"><strong>Resep (kartu 5) harus dikirim dulu</strong> + KFA</td><td class="ds-body-sm">idem Resep</td><td class="ds-body-sm">Resep belum dikirim → tombol nonaktif</td></tr>
                                    <tr><td class="ds-td-strong">9</td><td class="ds-body-sm">Penunjang Lab</td><td class="ds-body-sm">Hasil lab selesai + <strong>LOINC per item</strong></td><td class="ds-body-sm">Master Lab (LOINC) + input hasil</td><td class="ds-body-sm">Item tanpa LOINC di-skip; tak ada hasil → gagal</td></tr>
                                    <tr><td class="ds-td-strong">10</td><td class="ds-body-sm">Penunjang Radiologi</td><td class="ds-body-sm">Order radiologi + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › order radiologi</td><td class="ds-body-sm">ImagingStudy dilewati (no DICOM)</td></tr>
                                    <tr><td class="ds-td-strong">11</td><td class="ds-body-sm">Clinical Impression</td><td class="ds-body-sm">Diagnosa (jadi ringkasan) + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › Diagnosa</td><td class="ds-body-sm">Tak ada diagnosa → ditolak</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mt-3" style="color:var(--muted)">
                            IHS pasien (<span class="ds-code">patient_uuid</span>) &amp; Encounter terkirim = prasyarat mutlak SEMUA kartu di atas.
                        </p>

                        {{-- ===== BAGIAN 3: STEP BY STEP ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-4">3 · Langkah demi langkah</h2>
                        <div class="space-y-3">
                            @foreach ([
                                ['Lengkapi IHS di Master (sekali)', 'Master Pasien → <span class="ds-code">patient_uuid</span> (otomatis dari NIK 16 digit), Master Dokter → <span class="ds-code">dr_uuid</span>, Master Poli → <span class="ds-code">poli_uuid</span>. Tanpa ini Encounter gagal & semua terkunci.'],
                                ['Isi kode standar di Master (sekali)', 'KFA obat di Master Obat (<span class="ds-code">product_id_satusehat</span>), LOINC lab per item di Master Lab (<span class="ds-code">loinc_code</span>). Item tanpa kode akan dilewati saat kirim.'],
                                ['Petugas isi EMR lengkap saat pelayanan', 'Keluhan+SNOMED, Alergi+SNOMED, tanda vital, diagnosa ICD-10, tindakan ICD-9, e-resep; selesaikan hasil lab & order radiologi.'],
                                ['Buka modal Satu Sehat', 'Daftar RJ → klik ikon <em>Satu Sehat</em> pada baris pasien → muncul modal berisi 11 kartu.'],
                                ['Kirim kartu 1 Encounter DULU', 'Encounter = akar (wajib). Semua tombol kartu lain <strong>nonaktif</strong> sampai Encounter sukses.'],
                                ['Kirim kartu 2–11 sesuai data terisi', 'Urutan bebas. Kartu 8 Obat Pulang: kirim kartu 5 Resep lebih dulu. Item tanpa kode standar dilewati dengan notifikasi jumlah.'],
                                ['Cek badge & verifikasi', 'Kartu yang sukses jadi hijau "Terkirim". Verifikasi payload &amp; respons server di tabel <span class="ds-code">web_log_status</span>, lalu cek angka di dashboard SATUSEHAT.'],
                                ['Saat pasien pulang', 'Ketika kunjungan CLOSED, Encounter otomatis di-<span class="ds-code">PUT</span> ke status <span class="ds-code">finished</span>.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="{{ $stepBadge }}">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{!! $isi !!}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== BAGIAN 4: GATE / KUNCI ===== --}}
                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Aturan gate yang mengunci kirim:</strong>
                                (1) <strong>Encounter akar</strong> — semua kartu lain nonaktif sampai Encounter terkirim;
                                (2) <strong>Obat Pulang</strong> butuh Resep dikirim dulu;
                                (3) <strong>item tanpa kode standar</strong> (SNOMED/LOINC/KFA/ICD) di-skip diam-diam —
                                kalau hasil "0 item", cek pengisian master; ulangi kirim setelah master dilengkapi.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 STANDAR ====== --}}
                    <section x-show="section === 'standar'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Data per Resource</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tiap resource punya <span class="ds-code">resourceType</span>/status,
                            sistem kode (system URI), dan sumber data (JSON EMR / master) sendiri.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Trait</th><th>Sistem kode</th><th>Sumber data</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Encounter</td><td class="ds-td-class">EncounterTrait</td><td class="ds-body-sm">class AMB (v3-ActCode)</td><td class="ds-body-sm">rjNo, dr_uuid, poli_uuid, rjDate, regName</td></tr>
                                    <tr><td class="ds-td-strong">Condition (diagnosa)</td><td class="ds-td-class">createFinalDiagnosis</td><td class="ds-body-sm"><strong>ICD-10</strong> hl7 sid/icd-10</td><td class="ds-body-sm">diagnpinaList[], kodeIcdx/icdx</td></tr>
                                    <tr><td class="ds-td-strong">Condition (keluhan)</td><td class="ds-td-class">createChiefComplaint</td><td class="ds-body-sm"><strong>SNOMED</strong> snomed.info/sct</td><td class="ds-body-sm">keluhanUtama + keluhanUtamaSnomedCode</td></tr>
                                    <tr><td class="ds-td-strong">Observation (vital)</td><td class="ds-td-class">ObservationTrait</td><td class="ds-body-sm"><strong>LOINC</strong> + UCUM</td><td class="ds-body-sm">tandaVital: sistole/diastole/nadi/suhu/rr</td></tr>
                                    <tr><td class="ds-td-strong">Procedure</td><td class="ds-td-class">ProcedureTrait</td><td class="ds-body-sm"><strong>ICD-9-CM</strong></td><td class="ds-body-sm">tindakanList, kodeIcd9/icd9</td></tr>
                                    <tr><td class="ds-td-strong">AllergyIntolerance</td><td class="ds-td-class">AllergyIntoleranceTrait</td><td class="ds-body-sm"><strong>SNOMED</strong></td><td class="ds-body-sm">riwayat alergi (anamnesa) + SNOMED; dr_uuid — <strong>wired (kartu 7)</strong></td></tr>
                                    <tr><td class="ds-td-strong">MedicationRequest</td><td class="ds-td-class">MedicationRequestTrait</td><td class="ds-body-sm"><strong>KFA</strong> sys-ids.kemkes/kfa</td><td class="ds-body-sm">eresep; KFA dari master product_id_satusehat</td></tr>
                                    <tr><td class="ds-td-strong">MedicationDispense</td><td class="ds-td-class">MedicationDispenseTrait</td><td class="ds-body-sm"><strong>KFA</strong></td><td class="ds-body-sm">eresep + KFA; butuh Resep terkirim dulu — <strong>wired (kartu 8)</strong></td></tr>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-td-class">ServiceRequestTrait</td><td class="ds-body-sm"><strong>LOINC</strong> 26436-6 (panel)</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs/dtls</span> + <span class="ds-code">lbmst_clabitems.loinc_code</span> — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">Specimen</td><td class="ds-td-class">SpecimenTrait</td><td class="ds-body-sm">SNOMED (darah/venipuncture)</td><td class="ds-body-sm">1 per paket checkup — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-td-class">DiagnosticReportTrait</td><td class="ds-body-sm"><strong>LOINC</strong> (kategori LAB)</td><td class="ds-body-sm">merangkum paket lab (<span class="ds-code">lbtxn_checkup*</span>) — <strong>wired (kartu 9 Lab)</strong></td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport (radiologi)</td><td class="ds-td-class">DiagnosticReportTrait</td><td class="ds-body-sm">LOINC (kategori RAD)</td><td class="ds-body-sm">order radiologi + dr_uuid; ImagingStudy dilewati (no DICOM) — <strong>wired (kartu 10)</strong></td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-td-class">ClinicalImpressionTrait</td><td class="ds-body-sm">— (ringkasan diagnosa)</td><td class="ds-body-sm">diagnosa (Condition) + dr_uuid — <strong>wired (kartu 11)</strong></td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ===== DETAIL PENGIRIMAN LAB (kartu 9) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Pengiriman Penunjang Lab (kartu 9)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Sumber <strong>dari DB lab internal</strong> (bukan JSON EMR). Tiap <strong>paket checkup</strong> yang
                            sudah selesai menghasilkan rantai 4 resource. Sama untuk RJ &amp; UGD — beda hanya
                            <span class="ds-code">status_rjri</span> ('RJ' vs 'UGD').
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai per paket checkup</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC panel 26436-6)
                                → <span class="ds-code">Specimen</span> (darah/venipuncture)
                                → <span class="ds-code">Observation</span> <strong>× per item ber-LOINC</strong> (kategori laboratory)
                                → <span class="ds-code">DiagnosticReport</span> (merangkum paket).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Sumber (tabel · kolom)</th><th>Aturan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pilih paket</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs</span>: <span class="ds-code">ref_no</span>=rj_no · <span class="ds-code">status_rjri</span>='RJ'/'UGD' · <span class="ds-code">checkup_status &lt;&gt; 'P'</span></td><td class="ds-body-sm">Hanya paket <strong>selesai</strong> (bukan Pending). Tak ada → gagal (toast).</td></tr>
                                    <tr><td class="ds-td-strong">Ambil item</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkupdtls</span> ⋈ <span class="ds-code">lbmst_clabitems</span>: <span class="ds-code">loinc_code</span>, <span class="ds-code">loinc_display</span>, <span class="ds-code">unit_desc</span>, <span class="ds-code">lab_result</span></td><td class="ds-body-sm">Buang <span class="ds-code">hidden_status≠'N'</span> &amp; header grup (<span class="ds-code">is_group='Y'</span>).</td></tr>
                                    <tr><td class="ds-td-strong">Observation</td><td class="ds-body-sm"><span class="ds-code">loinc_code</span> → code · <span class="ds-code">lab_result</span> → nilai · <span class="ds-code">unit_desc</span> → UCUM</td><td class="ds-body-sm">Item <strong>tanpa LOINC di-skip</strong>; hasil numerik → <span class="ds-code">valueQuantity</span>, selain itu <span class="ds-code">valueString</span>; hasil kosong dilewati.</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">identifier <span class="ds-code">{rjNo}-{checkup_no}</span>, category LAB, code 26436-6</td><td class="ds-body-sm">result = semua Observation paket; basedOn = ServiceRequest; performer = <span class="ds-code">dr_uuid</span> DPJP.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ID yang disimpan</strong> (<span class="ds-code">satusehat.labServiceRequestIds / labSpecimenIds / labObservationIds / labDiagnosticReportIds</span>)
                                = <strong>UUID balikan SATUSEHAT</strong> dari respons POST tiap resource — bukan dari DB. Dipakai untuk badge hijau &amp; guard "sudah pernah dikirim".
                                <br><strong>⚠️ Asumsi MVP (perlu validasi sandbox):</strong> panel SR/DR pakai LOINC generik <span class="ds-code">26436-6</span> &amp; Specimen default <strong>darah/venipuncture</strong> untuk semua paket — belum tepat untuk lab non-darah (urin/feses).
                            </span>
                        </div>

                        {{-- ===== DETAIL PENGIRIMAN RADIOLOGI (kartu 10) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Pengiriman Penunjang Radiologi (kartu 10)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Lebih ringkas dari lab: tiap <strong>order radiologi</strong> hanya menghasilkan 2 resource.
                            <strong>ImagingStudy dilewati</strong> (master tanpa kode standar, hasil = PDF, tak ada DICOM).
                            Sama untuk RJ &amp; UGD — beda hanya tabel order.
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai per order radiologi</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC generik 18748-4 · SNOMED 363679005 Imaging)
                                → <span class="ds-code">DiagnosticReport</span> (laporan minimal, kategori RAD, <strong>tanpa Observation/ImagingStudy</strong>).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Sumber (tabel · kolom)</th><th>Aturan</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Ambil order</td><td class="ds-body-sm"><span class="ds-code">rstxn_rjrads</span>/<span class="ds-code">rstxn_ugdrads</span> ⋈ <span class="ds-code">rsmst_radiologis</span>: <span class="ds-code">rad_dtl</span>, <span class="ds-code">rad_id</span>, <span class="ds-code">rad_desc</span> · where <span class="ds-code">rj_no</span></td><td class="ds-body-sm"><strong>Semua order</strong> dikirim (tak difilter status/hasil — beda dari lab). Tak ada order → gagal.</td></tr>
                                    <tr><td class="ds-td-strong">ServiceRequest</td><td class="ds-body-sm">identifier <span class="ds-code">rad-{rjNo}-{rad_dtl}</span> · display = <span class="ds-code">rad_desc</span></td><td class="ds-body-sm">code generik LOINC 18748-4; requester = <span class="ds-code">dr_uuid</span> DPJP.</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">category RAD, code 18748-4, basedOn = SR</td><td class="ds-body-sm"><strong>Minimal</strong>: tanpa Observation &amp; tanpa lampiran PDF; ImagingStudy dilewati (no DICOM).</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ID disimpan</strong> (<span class="ds-code">satusehat.radServiceRequestIds / radDiagnosticReportIds</span>) = UUID balikan SATUSEHAT (guard "sudah pernah dikirim").
                                <br><strong>⚠️ GAP (perlu perbaikan):</strong> alur kirim masih pakai kode generik <span class="ds-code">18748-4</span> untuk semua order. Master <span class="ds-code">rsmst_radiologis</span> kini sudah punya kolom <span class="ds-code">loinc_code</span>/<span class="ds-code">loinc_display</span> (isi via <span class="ds-code">/master/radiologis</span>) — tinggal dipakai menggantikan kode generik. Hasil bacaan/PDF (<span class="ds-code">rsview_rads.rad_upload_pdf</span>) &amp; nilai terstruktur <strong>belum dikirim</strong>; DR masih laporan kosong.
                            </span>
                        </div>

                        {{-- ===== DETAIL JALUR DICOM / ImagingStudy (ideal, belum aktif) ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">Detail — Jalur DICOM / ImagingStudy (ideal, belum aktif)</h2>
                        <p class="ds-body-md mb-3" style="max-width:64ch">
                            Ini jalur <strong>lengkap versi SATUSEHAT</strong> kalau RS punya <strong>PACS/modality</strong> ber-DICOM.
                            Saat ini <strong>dilewati</strong> karena modul radiologi kita <em>upload-based</em> (hasil = PDF, tak ada
                            <span class="ds-code">studyUid/seriesUid/sopUid</span>). Diagram di bawah = <strong>target</strong>, bukan yang berjalan sekarang.
                        </p>

                        <div class="ds-card-outline mb-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-2">Rantai ideal per order radiologi (DICOM)</div>
                            <div class="ds-body-sm" style="line-height:1.9">
                                <span class="ds-code">ServiceRequest</span> (order, LOINC/ICD spesifik)
                                → <span class="ds-code">ImagingStudy</span> (UID DICOM + modality DCM: CR/CT/MR/US)
                                → <span class="ds-code">Observation</span> <em>(opsional — temuan terstruktur)</em>
                                → <span class="ds-code">DiagnosticReport</span> (basedOn SR, <span class="ds-code">imagingStudy</span> ref, conclusion bacaan + <span class="ds-code">presentedForm</span> PDF).
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Langkah</th><th>Butuh (sumber · field)</th><th>Aturan</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Ambil order + kode</td><td class="ds-body-sm"><span class="ds-code">rstxn_rjrads/ugdrads</span> ⋈ <span class="ds-code">rsmst_radiologis</span>: <span class="ds-code">loinc_code</span>/<span class="ds-code">loinc_display</span>, ICD-9</td><td class="ds-body-sm">Pakai kode spesifik per tindakan (bukan generik 18748-4).</td><td class="ds-body-sm">🟡 kolom LOINC <strong>ada</strong>, alur kirim belum pakai</td></tr>
                                    <tr><td class="ds-td-strong">Dapatkan UID DICOM</td><td class="ds-body-sm"><span class="ds-code">studyUid</span> · <span class="ds-code">seriesUid</span> · <span class="ds-code">sopUid</span> dari PACS / modality worklist</td><td class="ds-body-sm">Format <span class="ds-code">urn:oid:{OID}</span>. Tanpa PACS → generate OID stabil sendiri.</td><td class="ds-body-sm">🔴 GAP — UID tak tersimpan</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-body-sm"><span class="ds-code">POST /ImagingStudy</span>: identifier <span class="ds-code">urn:dicom:uid</span>, modality DCM, numberOfSeries/Instances, procedureCode</td><td class="ds-body-sm">Referensi ke <span class="ds-code">Encounter</span> + <span class="ds-code">Patient</span>; started = tgl periksa.</td><td class="ds-body-sm">🟡 trait <span class="ds-code">createImagingStudy()</span> siap, belum di-wire</td></tr>
                                    <tr><td class="ds-td-strong">Observation <em>(opsional)</em></td><td class="ds-body-sm">temuan terstruktur ber-LOINC</td><td class="ds-body-sm">Boleh dilewati — banyak radiologi cuma narasi.</td><td class="ds-body-sm">🔴 belum ada capture terstruktur</td></tr>
                                    <tr><td class="ds-td-strong">DiagnosticReport</td><td class="ds-body-sm">basedOn = SR, <span class="ds-code">imagingStudy</span> = [ref], conclusion = bacaan, <span class="ds-code">presentedForm</span> = PDF base64 (<span class="ds-code">rsview_rads.rad_upload_pdf</span>)</td><td class="ds-body-sm">Lengkap (beda dari DR minimal sekarang).</td><td class="ds-body-sm">🔴 sekarang DR tanpa bacaan/PDF</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jalur SEKARANG (aktif)</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">ServiceRequest</span> + <span class="ds-code">DiagnosticReport</span> minimal</li>
                                    <li>Kode generik LOINC <span class="ds-code">18748-4</span></li>
                                    <li>Tanpa ImagingStudy · tanpa PDF · tanpa Observation</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jalur IDEAL (DICOM)</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>SR + <strong>ImagingStudy</strong> + (Observation) + DR lengkap</li>
                                    <li>Kode LOINC/ICD spesifik per modalitas</li>
                                    <li>Bacaan (conclusion) + PDF (<span class="ds-code">presentedForm</span>)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Cara menutup gap (3 langkah, bisa bertahap):</strong>
                                <br><strong>1)</strong> Isi <span class="ds-code">loinc_code</span> tindakan di <span class="ds-code">/master/radiologis</span> → ganti kode generik 18748-4.
                                <br><strong>2)</strong> <strong>Tanpa PACS:</strong> <em>generate OID stabil</em> (mis. <span class="ds-code">urn:oid:{root}.{rjNo}.{rad_dtl}</span>) + kirim ImagingStudy minimal (started + modality + procedureCode), lampirkan PDF di <span class="ds-code">DiagnosticReport.presentedForm</span>.
                                <br><strong>3)</strong> <strong>Dengan PACS:</strong> ambil UID DICOM asli dari modality worklist → ImagingStudy penuh (series/instances). Paling akurat, butuh integrasi.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">LOINC vital di-hardcode di blade</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>TD panel <span class="ds-code">85354-9</span> (sistole <span class="ds-code">8480-6</span> / diastole <span class="ds-code">8462-4</span>)</li>
                                    <li>Nadi <span class="ds-code">8867-4</span> · Suhu <span class="ds-code">8310-5</span> · RR <span class="ds-code">9279-1</span></li>
                                    <li><span class="ds-code">LoincTrait</span>/<span class="ds-code">SnomedTrait</span> (lookup live tx.fhir.org) <strong>tidak dipakai</strong> di alur RJ</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">KFA obat</div>
                                <div class="ds-body-sm">
                                    Diambil dari master obat kolom
                                    <span class="ds-code">product_id_satusehat</span> /
                                    <span class="ds-code">product_name_satusehat</span> (di-set manual di
                                    <span class="ds-code">/master/master-obat</span>). Kalau kosong → item
                                    resep di-<strong>skip</strong>.
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 08b STATUS PER MODUL (RJ/UGD/RI) ====== --}}
                    <section x-show="section === 'ri-ugd'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Status Pengiriman per Modul — RJ / UGD / RI</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Alur kirim SATUSEHAT sudah tersedia di <strong>tiga modul</strong>. Tiap resource = 1 komponen
                            <span class="ds-code">kirim-*.blade.php</span> di <span class="ds-code">transaksi/{modul}/satu-sehat/</span>,
                            digabung di 1 modal <span class="ds-code">satu-sehat-{modul}-actions</span>, dipanggil dari Daftar {modul}.
                            ID balikan SATUSEHAT disimpan di JSON record (<span class="ds-code">satusehat.*</span>) sebagai guard "sudah pernah dikirim".
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RJ — Rawat Jalan</div>
                                <div class="ds-body-sm">Lengkap (11 resource). Ruang lingkup awal &amp; acuan pola.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">UGD</div>
                                <div class="ds-body-sm">Lengkap + <strong>ChiefComplaint &amp; Allergy</strong> (LOV SNOMED ditambahkan di anamnesa keluhan utama &amp; alergi).</div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <div class="ds-title-sm mb-1">RI — Rawat Inap</div>
                                <div class="ds-body-sm"><strong>13 resource aktif</strong> + 2 digating (SNOMED). Encounter class <span class="ds-code">IMP</span>.</div>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-6 mb-3">Detail Resource RI (Rawat Inap)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Sumber data RI</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Encounter (IMP)</td><td class="ds-body-sm"><span class="ds-code">rstxn_rihdrs</span> + lokasi <span class="ds-code">rsmst_rooms.room_uuid</span></td><td class="ds-body-sm">✅ aktif — dukung <strong>pindah kamar</strong> (location[] bertambah)</td></tr>
                                    <tr><td class="ds-td-strong">EpisodeOfCare</td><td class="ds-body-sm"><span class="ds-code">rstxn_rihdrs</span> (episodeNo=rihdr_no, careManager=DPJP)</td><td class="ds-body-sm">✅ aktif — 1 RI = 1 episode; link Encounter; Finish saat pulang</td></tr>
                                    <tr><td class="ds-td-strong">Condition</td><td class="ds-body-sm"><span class="ds-code">rstxn_ridtls</span> ⋈ <span class="ds-code">rsmst_mstdiags</span> <strong>by diag_id</strong></td><td class="ds-body-sm">✅ aktif — aman 288 icdx kembar</td></tr>
                                    <tr><td class="ds-td-strong">Procedure</td><td class="ds-body-sm">JSON <span class="ds-code">procedure[]</span> (procedureId=ICD-9)</td><td class="ds-body-sm">✅ aktif</td></tr>
                                    <tr><td class="ds-td-strong">Observation (vital)</td><td class="ds-body-sm">JSON <span class="ds-code">observasi.observasiLanjutan.tandaVital[]</span></td><td class="ds-body-sm">✅ aktif — <strong>multi-entri</strong> (per waktu ukur)</td></tr>
                                    <tr><td class="ds-td-strong">MedicationRequest</td><td class="ds-body-sm">JSON <span class="ds-code">eresepHdr[].eresep[]</span> → join <span class="ds-code">immst_products</span> (KFA)</td><td class="ds-body-sm">✅ aktif — <strong>racikan belum</strong>; item tanpa KFA di-skip</td></tr>
                                    <tr><td class="ds-td-strong">MedicationDispense</td><td class="ds-body-sm">sda (obatList identik) — pairing 1:1 dgn medicationRequestIds</td><td class="ds-body-sm">✅ aktif — butuh MedicationRequest dulu; authorizingPrescription</td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-body-sm">JSON <span class="ds-code">cppt[]</span> (SOAP)</td><td class="ds-body-sm">✅ aktif — 1 entri = 1 CI; <strong>assessor = DPJP</strong> (fallback MVP); guard per <span class="ds-code">cpptId</span></td></tr>
                                    <tr><td class="ds-td-strong">Penunjang Lab</td><td class="ds-body-sm"><span class="ds-code">lbtxn_checkuphdrs</span> <span class="ds-code">status_rjri='RI'</span> ⋈ <span class="ds-code">lbmst_clabitems.loinc_code</span></td><td class="ds-body-sm">✅ aktif — chain SR→Specimen→Obs→DR</td></tr>
                                    <tr><td class="ds-td-strong">Penunjang Radiologi</td><td class="ds-body-sm"><span class="ds-code">rstxn_riradiologs</span> ⋈ <span class="ds-code">rsmst_radiologis.loinc_code</span></td><td class="ds-body-sm">✅ aktif — <strong>LOINC spesifik</strong> bila master terisi, else generik 18748-4; ImagingStudy dilewati</td></tr>
                                    <tr><td class="ds-td-strong">NutritionOrder (Diet)</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.rencana.diet</span> (free-text)</td><td class="ds-body-sm">✅ aktif — <strong>text-only</strong> (tanpa coding SNOMED); trait baru <span class="ds-code">NutritionOrderTrait</span></td></tr>
                                    <tr><td class="ds-td-strong">Penilaian (Observation)</td><td class="ds-body-sm">JSON <span class="ds-code">penilaian.resikoJatuh[]</span> &amp; <span class="ds-code">penilaian.gizi[]</span> (bersarang ganda) → <span class="ds-code">App\Support\PenilaianObservationMap</span></td><td class="ds-body-sm">✅ aktif — Morse <span class="ds-code">59460-6</span> skor + <span class="ds-code">59461-4</span> level (<span class="ds-code">survey</span>); BB/TB/IMT <span class="ds-code">29463-7</span>/<span class="ds-code">8302-2</span>/<span class="ds-code">39156-5</span> (<span class="ds-code">vital-signs</span>). Humpty <strong>tanpa LOINC</strong> → generik <span class="ds-code">73830-2</span></td></tr>
                                    <tr><td class="ds-td-strong">Observasi Lanjutan</td><td class="ds-body-sm">JSON <span class="ds-code">observasi.obatDanCairan.pemberianObatDanCairan[]</span>, <span class="ds-code">pemakaianOksigen.pemakaianOksigenData[]</span>, <span class="ds-code">pengeluaranCairan.pengeluaranCairan[]</span> &rarr; <span class="ds-code">App\Support\ObservasiLanjutanMap</span></td><td class="ds-body-sm">&#9989; aktif &mdash; <strong>MedicationAdministration</strong> (KFA, route SNOMED) + Observation oksigen <span class="ds-code">107117-4</span>/<span class="ds-code">3151-8</span> (valueRange) + urine <span class="ds-code">9187-6</span>. Hanya ~31% baris obat ber-productId &rarr; sisanya dilewati &amp; <strong>dilaporkan</strong></td></tr>
                                    <tr><td class="ds-td-strong">ChiefComplaint</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.anamnesa.keluhanUtama</span> + SNOMED</td><td class="ds-body-sm">⏸️ <strong>digating</strong> <span class="ds-code">@@if(false)</span> — aktifkan bareng LOV SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">AllergyIntolerance</td><td class="ds-body-sm">JSON <span class="ds-code">pengkajianDokter.anamnesa.jenisAlergi</span> + SNOMED</td><td class="ds-body-sm">⏸️ <strong>digating</strong> <span class="ds-code">@@if(false)</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Keputusan desain RI:</strong>
                                <br>• <strong>Location kamar</strong>: kolom <span class="ds-code">rsmst_rooms.room_uuid</span> baru + tombol "Daftarkan Location" di <span class="ds-code">/master/kamar</span> (pola <span class="ds-code">poli_uuid</span>). Pindah kamar → entri <span class="ds-code">location[]</span> baru.
                                <br>• <strong>SNOMED RI digating</strong> (<span class="ds-code">@@if(false)</span>) di <span class="ds-code">rm-pengkajian-dokter-ri-actions</span> (LOV) &amp; <span class="ds-code">satu-sehat-ri-actions</span> (sender). Backend utuh — aktifkan = ubah <span class="ds-code">false→true</span> di 3 titik.
                                <br>• <strong>CPPT → ClinicalImpression</strong>: assessor = DPJP karena PPA non-dokter belum punya IHS.
                                <br>• <strong>NutritionOrder</strong>: text-only — cek sandbox apakah profile wajib coding; kalau ya, perlu master diet ber-SNOMED.
                                <br>• <strong>Penilaian</strong>: kode LOINC diverifikasi lewat <span class="ds-code">tx.fhir.org</span> (<span class="ds-code">LoincTrait</span>), <strong>bukan hafalan</strong> — tebakan awal <span class="ds-code">59460-2</span>/<span class="ds-code">59461-0</span> ternyata SALAH. Pemetaan dipakai bareng RJ/UGD lewat helper statis (bukan trait, hindari tabrakan nama method EMR).
                                <br>• <strong>Guard skor 0</strong>: entri tanpa metode DAN tanpa kategori tidak memancarkan apa-apa. Default form (<span class="ds-code">resikoJatuh='Tidak'</span>, metode='', <strong>skor=0</strong>) = tak ada skala dipakai. <strong>Jangan pakai skor sebagai guard</strong> — <span class="ds-code">0 !== null</span> lolos, dan skor 0 juga nilai Morse yang sah; tanpa guard ini ~1000 record RJ terkirim sebagai "Fall risk = Tidak diketahui".
                                <br>• <strong>Skor/kategori gizi tak dikirim</strong>: skrining custom 3-item (bukan MST/MUST/Strong-Kids) → tanpa padanan LOINC. Hanya antropometri. Nilai di luar batas wajar (BB 0.3–500, TB 20–260, IMT 5–200) dilewati — data nyata sempat berisi <span class="ds-code">bb=1 tb=1 imt=10000</span>.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <div class="ds-title-sm mb-1">Belum dikerjakan (pengayaan, non-wajib)</div>
                            <div class="ds-body-sm">
                                <strong>RI</strong>: SBAR (butuh Communication trait), Composition (ringkasan pulang), ImagingStudy (gap DICOM). •
                                <strong>UGD</strong>: MedicationAdministration cairan.
                                <br><br><strong>Racikan obat (RJ/UGD/RI) — buntu ganda, bukan sekadar "belum dikerjakan":</strong>
                                <br>• <em>Spek</em>: kode <span class="ds-code">medicationType</span> racikan belum terverifikasi — sender menulis <span class="ds-code">'SD' => 'Compound'</span> tapi 'SD' tak ada di dokumentasi mana pun, dan ternary <span class="ds-code">isCompound</span> <strong>tak pernah aktif</strong> (selalu 'NC'). Juga: racikan tak punya KFA tunggal → <span class="ds-code">Medication.code</span> harus diisi apa? <span class="ds-code">ingredient[]</span> sudah ditulis tapi <strong>di-comment out</strong> di kedua trait.
                                <br>• <em>Data</em>: <strong>~97% baris racikan RJ/UGD tanpa <span class="ds-code">productId</span></strong> (hanya nama teks) → tak bisa dipetakan ke KFA. Volume: RJ 17.428 record / 19.404 grup; UGD 2.417 / 2.958; RI cuma 142 / 294. Kolom <span class="ds-code">product_id</span> di <span class="ds-code">rstxn_rjobatracikans</span>/<span class="ds-code">rstxn_ugdobatracikans</span> <strong>ada tapi 0% terisi</strong> (INSERT tak menulisnya padahal nilainya tersedia) → backfill lewat join mustahil.
                                <br>• Kartu MedicationRequest kini menampilkan <strong>"N racikan belum didukung"</strong> di 3 modul via <span class="ds-code">App\Support\EresepJson::jumlahRacikan()</span> — sebelumnya RJ/UGD membuangnya <strong>diam-diam</strong> (17rb+ record tampil "berhasil").
                            </div>
                        </div>
                    </section>

                    {{-- ====== 09 DASHBOARD ====== --}}
                    <section x-show="section === 'dashboard'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Peta Dashboard SATUSEHAT → Status</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kolom di dashboard platform SATUSEHAT (jumlah resource per bulan) vs kondisi
                            implementasi di sistem ini. Pakai peta ini untuk tahu apa yang tinggal
                            di-wire dan apa yang harus dibuat dari nol.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Kolom Dashboard (Resource)</th><th>Trait ada?</th><th>Ter-wire di UI RJ?</th><th>Sistem kode</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Kunjungan (Encounter)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">class AMB</td></tr>
                                    <tr><td class="ds-td-strong">Diagnosis (Condition)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">ICD-10 (+SNOMED keluhan)</td></tr>
                                    <tr><td class="ds-td-strong">Observasi (Observation)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Tindakan (Procedure)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">ICD-9-CM</td></tr>
                                    <tr><td class="ds-td-strong">Peresepan Obat (MedicationRequest)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol</td><td class="ds-body-sm">KFA</td></tr>
                                    <tr><td class="ds-td-strong">Obat Dibawa Pulang (MedicationDispense)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 8)</td><td class="ds-body-sm">KFA</td></tr>
                                    <tr><td class="ds-td-strong">Layanan Penunjang (ServiceRequest)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 9 Lab)</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Laboratorium (Specimen)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 9 Lab)</td><td class="ds-body-sm">SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">Pelaporan Diagnostik (DiagnosticReport)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (Lab kartu 9 + Radiologi kartu 10)</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Intoleransi Alergi (AllergyIntolerance)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 7)</td><td class="ds-body-sm">SNOMED</td></tr>
                                    <tr><td class="ds-td-strong">Impresi Klinik (ClinicalImpression)</td><td class="ds-body-sm">✅</td><td class="ds-body-sm" style="color:var(--primary)">✅ tombol (kartu 11)</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Diet (Composition)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Radiologi (ImagingStudy)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">➖ ImagingStudy dilewati (no DICOM); radiologi dikirim via SR+DR (kartu 10)</td><td class="ds-body-sm">LOINC</td></tr>
                                    <tr><td class="ds-td-strong">Imunisasi (Immunization)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Episode Perawatan (EpisodeOfCare)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                    <tr><td class="ds-td-strong">Instruksi Gizi (NutritionOrder)</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">❌</td><td class="ds-body-sm">—</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1" style="color:var(--primary)">11</div>
                                <div class="ds-body-sm">kartu <strong>ter-wire tombol Kirim</strong> di RJ (Encounter, Condition, Observation, Procedure, MedicationRequest, Chief Complaint, Allergy, Medication Dispense, Lab, Radiologi, Clinical Impression). <strong>UGD = 9</strong> (tanpa Chief Complaint &amp; Allergy).</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1">4</div>
                                <div class="ds-body-sm"><strong>belum dibuat</strong> (Composition/Diet, Immunization, EpisodeOfCare, NutritionOrder)</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-display-sm mb-1">1</div>
                                <div class="ds-body-sm"><strong>sengaja dilewati</strong>: ImagingStudy (no DICOM) — radiologi tetap terkirim via ServiceRequest + DiagnosticReport.</div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 10 RESOURCE BELUM ADA ====== --}}
                    <section x-show="section === 'belum-ada'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Resource Belum Ada — Cara &amp; Metode Kirim</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Enam kolom dashboard ini <strong>belum punya trait sama sekali</strong>.
                            Bab ini merangkum <em>cara kirim</em> (endpoint + payload FHIR R4) dan
                            <em>metode</em> (<span class="ds-code">createX()</span> mengikuti idiom repo:
                            <span class="ds-code">resourceType</span> → <span class="ds-code">subject/encounter</span>
                            reference → <span class="ds-code">makeRequest('post', '/X', $payload)</span>).
                            Semua di bawah = <strong>cetak-biru, belum diuji sandbox</strong>.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Resource</th><th>Endpoint</th><th>Kode system</th><th>Kesiapan data SIRUS</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">EpisodeOfCare</td><td class="ds-td-class">POST /EpisodeOfCare</td><td class="ds-body-sm">episodeofcare-type</td><td class="ds-body-sm" style="color:var(--primary)">✅ ada — rstxn_rihdrs (RI)</td></tr>
                                    <tr><td class="ds-td-strong">ClinicalImpression</td><td class="ds-td-class">POST /ClinicalImpression</td><td class="ds-body-sm">SNOMED (finding)</td><td class="ds-body-sm" style="color:var(--primary)">✅ ada — asesmen "A" EMR</td></tr>
                                    <tr><td class="ds-td-strong">NutritionOrder</td><td class="ds-td-class">POST /NutritionOrder</td><td class="ds-body-sm">SNOMED (diet)</td><td class="ds-body-sm">◑ sebagian — order diet EMR (role Gizi)</td></tr>
                                    <tr><td class="ds-td-strong">Composition</td><td class="ds-td-class">POST /Composition</td><td class="ds-body-sm">LOINC (doc type)</td><td class="ds-body-sm">◑ sebagian — narasi EMR jadi section</td></tr>
                                    <tr><td class="ds-td-strong">ImagingStudy</td><td class="ds-td-class">POST /ImagingStudy</td><td class="ds-body-sm">DICOM DCM + ICD-9</td><td class="ds-body-sm">⚠️ gap — UID DICOM tak tersimpan</td></tr>
                                    <tr><td class="ds-td-strong">Immunization</td><td class="ds-td-class">POST /Immunization</td><td class="ds-body-sm">KFA (vaksin)</td><td class="ds-body-sm">⚠️ gap — belum ada modul imunisasi</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prasyarat sama utk semuanya:</strong> Encounter pasien harus sudah
                                terkirim (semua resource ini mereferensikan <span class="ds-code">Encounter/{id}</span>
                                &amp; <span class="ds-code">Patient/{id}</span>). Urutan implementasi disarankan
                                dari yang <strong>datanya sudah ada</strong> (EpisodeOfCare, ClinicalImpression)
                                ke yang butuh modul baru (Immunization, ImagingStudy).
                            </span>
                        </div>

                        {{-- Grup 1: data sudah ada --}}
                        <div class="ds-caption-up mb-3">Data sudah ada — tinggal buat trait &amp; wire</div>

                        <div class="ds-title-md mb-1">EpisodeOfCare — Episode Perawatan</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Mengelompokkan seluruh kunjungan satu rawat inap jadi satu episode. Sumber:
                            <span class="ds-code">rstxn_rihdrs</span> (mulai = tgl masuk, selesai = tgl pulang, DPJP = careManager).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">EpisodeOfCareTrait::createEpisodeOfCare()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-episode'] }}</pre>
                        </div>

                        <div class="ds-title-md mb-1">ClinicalImpression — Impresi Klinik</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Asesmen dokter (huruf "A" di SOAP). Sumber: section Penilaian/Assessment EMR;
                            <span class="ds-code">finding</span> bisa dipetakan ke SNOMED bila tersedia.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ClinicalImpressionTrait::createClinicalImpression()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-clinical'] }}</pre>
                        </div>

                        {{-- Grup 2: data sebagian --}}
                        <div class="ds-caption-up mb-3">Data sebagian — perlu pemetaan kode</div>

                        <div class="ds-title-md mb-1">NutritionOrder — Instruksi Gizi</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Order diet dari EMR (role Gizi punya akses Daftar RI/EMR). PR: petakan teks diet
                            ke kode SNOMED diet (<span class="ds-code">oralDiet.type</span>).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">NutritionOrderTrait::createNutritionOrder()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-nutrition'] }}</pre>
                        </div>

                        <div class="ds-title-md mb-1">Composition — Dokumen Klinis (label dashboard "Diet")</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Dokumen terstruktur ber-section (mis. ringkasan/rencana). PR: tentukan
                            <span class="ds-code">type</span> LOINC dokumen &amp; bungkus narasi jadi
                            <span class="ds-code">section[].text.div</span> (XHTML valid).
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">CompositionTrait::createComposition()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-composition'] }}</pre>
                        </div>

                        {{-- Grup 3: gap data --}}
                        <div class="ds-caption-up mb-3">Gap data — butuh modul / field baru dulu</div>

                        <div class="ds-title-md mb-1">ImagingStudy — Radiologi</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Modul radiologi kita <strong>upload-based</strong> (tak ada PACS/DICOM), jadi
                            <span class="ds-code">studyUid/seriesUid/sopUid</span> tidak ada. Opsi: generate OID
                            sendiri &amp; kirim minimal (started + modality + procedureCode), atau integrasi PACS.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ImagingStudyTrait::createImagingStudy()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-imaging'] }}</pre>
                        </div>

                        <div class="ds-title-md mb-1">Immunization — Imunisasi</div>
                        <p class="ds-body-sm mb-3" style="max-width:62ch; color:var(--muted)">
                            Belum ada modul imunisasi. Perlu form capture (jenis vaksin ber-KFA, lot, rute,
                            dosis, petugas) sebelum resource ini bisa dikirim.
                        </p>
                        <div class="ds-card-dark mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">ImmunizationTrait::createImmunization()</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['ss-immunization'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Langkah adopsi (tiap resource):</strong> (1) buat
                                <span class="ds-code">App\Http\Traits\SATUSEHAT\&lt;Resource&gt;Trait</span> berisi
                                <span class="ds-code">createX()</span> di atas; (2) buat komponen
                                <span class="ds-code">kirim-&lt;resource&gt;-rj-actions</span> meniru
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah')">bab Menambah Resource</button>; (3) gate
                                <span class="ds-code">:disabled="!$hasEncounter"</span>; (4) simpan id ke node JSON
                                <span class="ds-code">satusehat</span>; (5) uji sandbox, verifikasi
                                <span class="ds-code">web_log_status</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 BACKLOG ====== --}}
                    <section x-show="section === 'backlog'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Backlog &amp; Gotcha</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Temuan verifikasi lapangan — perbaiki saat menyentuh area terkait.
                        </p>

                        <div class="space-y-3">
                            @foreach ([
                                ['env() tanpa config wrapper', 'Mati senyap bila config:cache. Rekomendasi: buat config/satusehat.php dan baca via config(\'satusehat.*\').'],
                                ['5 resource belum di-wire', 'Dispense/ServiceRequest/Specimen/DiagnosticReport/Allergy → kolom dashboard itu akan 0 walau trait tersedia.'],
                                ['Timeout 10s tanpa retry/connectTimeout', 'Samakan pola dgn BPJS timeout(8)->connectTimeout(3) supaya tak membekukan worker.'],
                                ['KFA/kode di-skip diam-diam', 'Bila master belum diisi → tambahkan peringatan "N item tanpa kode dilewati".'],
                                ['registrationId == medicationCode == kfaCode', 'Di kirim-medication-request:89-90 — perlu ditinjau apakah field registrasi obat harus beda dari KFA.'],
                                ['DiagnosticReport default kategori MB (Microbiology)', 'Set eksplisit LAB/RAD saat mengaktifkan lab/radiologi.'],
                                ['Diagnosa tak tandai primer/sekunder', 'Encounter.diagnosis.rank tidak diisi → semua Condition setara.'],
                                ['Token TTL hardcoded 3500', 'Mengabaikan expires_in, tak ada invalidasi cache saat 401.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- ====== 12 TAMBAH ====== --}}
                    <section x-show="section === 'tambah'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Menambah / Mengaktifkan Resource Baru</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua skenario. Cek dulu di <button type="button" class="hover:underline font-semibold"
                                style="color:var(--primary)" x-on:click="go('dashboard')">Peta Dashboard</button>
                            apakah trait-nya sudah ada.
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-2">A · Trait sudah ada</div>
                                <div class="ds-body-sm">Dispense · ServiceRequest · Specimen · DiagnosticReport · Allergy → tinggal <strong>wire ke UI</strong>.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">B · Trait belum ada</div>
                                <div class="ds-body-sm">Composition · ClinicalImpression · ImagingStudy · Immunization · EpisodeOfCare · NutritionOrder → <strong>buat trait dulu</strong>.</div>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka komponen kirim per-resource (meniru kirim-procedure)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kirim-component'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah adopsi — A (wire) vs B (trait baru)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['add-resource'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Selalu uji di sandbox dulu</strong> (ganti env AUTH/BASE URL ke <span class="ds-code">-stg</span>),
                                lalu verifikasi payload &amp; balasan lewat tabel <span class="ds-code">web_log_status</span>
                                sebelum diarahkan ke production.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Glosarium FHIR &amp; SATUSEHAT</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Istilah yang sering muncul saat menyentuh integrasi ini.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['SATUSEHAT', 'Platform interoperabilitas data kesehatan Kemenkes (standar FHIR)'],
                                        ['FHIR R4', 'Fast Healthcare Interoperability Resources versi R4 — standar pertukaran data kesehatan'],
                                        ['Resource', 'Satuan data FHIR (Encounter, Condition, Observation, dst.) — dikirim per HTTP call'],
                                        ['Encounter', 'Resource kunjungan pasien — AKAR yang direferensikan semua resource lain'],
                                        ['Condition', 'Resource diagnosa (encounter-diagnosis) atau keluhan (problem-list-item)'],
                                        ['Observation', 'Resource pengukuran/observasi klinis (mis. tanda vital)'],
                                        ['Procedure', 'Resource tindakan medis'],
                                        ['MedicationRequest', 'Resource peresepan obat'],
                                        ['IHS Code', 'Identitas unik resource/pasien/dokter di SATUSEHAT (patient_uuid, dr_uuid, poli_uuid)'],
                                        ['Organization-Id', 'Identitas fasilitas kesehatan pengirim (env SATUSEHAT_ORGANIZATION_ID)'],
                                        ['ICD-10', 'Sistem kode diagnosa penyakit internasional'],
                                        ['ICD-9-CM', 'Sistem kode tindakan/prosedur medis'],
                                        ['LOINC', 'Sistem kode observasi & pemeriksaan laboratorium'],
                                        ['SNOMED CT', 'Terminologi klinis (keluhan, alergi, kategori tindakan)'],
                                        ['KFA', 'Kamus Farmasi & Alat kesehatan Kemenkes — kode standar obat'],
                                        ['UCUM', 'Unified Code for Units of Measure — satuan pengukuran standar'],
                                        ['client_credentials', 'Alur OAuth2 mesin-ke-mesin (client_id + secret → access_token)'],
                                        ['fail-soft', 'Kegagalan langkah non-akar tidak menghentikan langkah lain'],
                                        ['Idempotensi', 'Jaminan kirim ulang tak menggandakan resource (guard state + natural key)'],
                                        ['Bundle', 'Kumpulan resource FHIR dalam satu transaksi — TIDAK dipakai di sini (per-resource)'],
                                        ['web_log_status', 'Tabel audit tiap panggilan API SATUSEHAT (payload & response)'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Detail lengkap &amp; nomor baris kode: <span class="ds-code">docs/satusehat-api.md</span>.
                                Lihat juga <span class="ds-code">docs/trait-template-api-eksternal.md</span> &amp;
                                <span class="ds-code">docs/diagnosa-architecture.md</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
