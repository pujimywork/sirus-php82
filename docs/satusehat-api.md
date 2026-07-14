# Dokumentasi API SATUSEHAT — Model Pengiriman & Standarisasi Data

Dokumen ini menjelaskan **cara sistem mengirim data ke SATUSEHAT** (platform interoperabilitas Kemenkes, FHIR R4) dan **standarisasi data** tiap resource. Berbasis implementasi nyata di repo, bukan teori.

- Lapisan trait: `app/Http/Traits/SATUSEHAT/*.php` (20 file, ~3.200 baris)
- Lapisan UI (aktif): `resources/views/pages/transaksi/rj/satu-sehat/*.blade.php` + `daftar-rj/satu-sehat-rj-actions.blade.php`
- Orkestrator batch (referensi): `app/Http/Traits/SATUSEHAT/KirimRawatJalanTrait.php`

> **Ruang lingkup aktif = Rawat Jalan (RJ).** UGD/RI belum punya alur kirim SATUSEHAT.

---

## 1. Arsitektur singkat

```
                    ┌─────────────────────── SatuSehatTrait (core/transport) ───────────────────────┐
                    │  initializeSatuSehat() · getAccessToken() · makeRequest() · logSatuSehat()     │
                    └───────────────────────────────────────────────────────────────────────────────┘
                                   ▲ di-`use` oleh semua resource trait
  Resource traits (bangun payload FHIR + POST/PUT):
   Encounter · Condition · Observation · Procedure · AllergyIntolerance ·
   MedicationRequest · MedicationDispense · ServiceRequest · Specimen · DiagnosticReport ·
   Patient · Practitioner · Organization · Location · (Loinc/Snomed = lookup terminologi)

  UI RJ (Livewire/Volt, satu tombol per-resource):
   satu-sehat-rj-actions  ──buka modal──▶  kirim-encounter │ kirim-condition │ kirim-observation │
                                            kirim-procedure │ kirim-medication-request
```

Dua "jalur" kirim yang perlu dibedakan:
1. **Jalur UI aktif (yang benar-benar dipakai):** 5 komponen Livewire per-langkah, masing-masing tombol "Kirim" sendiri. Menyimpan hasil ke node JSON `satusehat` pada record RJ.
2. **Jalur orkestrator batch `KirimRawatJalanTrait` (11 langkah sekali jalan):** lengkap (termasuk alergi, dispense, lab), tapi **belum di-`use` komponen/route manapun** — anggap sebagai blueprint/cadangan, bukan jalur produksi.

---

## 2. Autentikasi & environment

OAuth2 **client_credentials** — `SatuSehatTrait.php:38-53`.

| Hal | Nilai / Cara |
|---|---|
| Token endpoint | `SATUSEHAT_AUTH_URL . "accesstoken?grant_type=client_credentials"` (POST `asForm`) |
| Kredensial | env `SATUSEHAT_CLIENT_ID`, **`SATUSEHAT_SECRET_ID`** (catat: `_SECRET_ID`, bukan `_CLIENT_SECRET`) |
| Cache token | `Cache::remember('satusehat_access_token', 3500, …)` — TTL hardcoded ~58 mnt, `expires_in` diabaikan |
| Header API | `Authorization: Bearer {token}` + `Organization-Id: {SATUSEHAT_ORGANIZATION_ID}` |
| Base URL FHIR | `SATUSEHAT_BASE_URL` → `https://api-satusehat.kemkes.go.id/fhir-r4/v1/` (**PRODUCTION**) |
| Versi | FHIR **R4**; profil resource `https://fhir.kemkes.go.id/r4/StructureDefinition/*` |

**Environment switch = ganti nilai env** (tak ada toggle di kode). Sandbox Kemkes biasanya `api-satusehat-stg.kemkes.go.id`.

⚠️ **Semua kredensial dibaca `env()` langsung, tanpa wrapper `config/*.php`.** Kalau `php artisan config:cache` dijalankan di production, `env()` runtime → `null` → integrasi mati senyap. (Lihat backlog §8.)

---

## 3. Transport & logging

`makeRequest($method, $endpoint, $data = [])` — `SatuSehatTrait.php:61-104`. Laravel `Http`.

- **Bukan FHIR Bundle.** Tiap resource = satu HTTP call terpisah (`POST Encounter`, `POST Condition`, …).
- `Http::timeout(10)` untuk token & API. **Tanpa `connectTimeout()` / `retry()`** → rawan gagal saat server lambat.
- Sukses (`2xx`) → `$response->json()` (array). Gagal → `throw \Exception('API request failed: '.body)`; caller (blade) tangkap `\Throwable` → toast.
- **Logging:** tiap call di-insert ke tabel **`web_log_status`** via `logSatuSehat()` (`:109-119`): `code, date_ref, response, http_req, http_payload, requestTransferTime`.

---

## 4. Resolusi IHS Code

IHS = identitas resource di SATUSEHAT. Sumbernya kolom master (di-set sekali), bukan dilookup tiap kirim:

| Entitas | IHS disimpan di | Cara isi |
|---|---|---|
| **Pasien** | `rsmst_pasiens.patient_uuid` (+ JSON `pasien.identitas.patientUuid`) | `searchPatient(['nik'=>…])` → `/Patient?identifier=…/nik\|{nik}`; kalau kosong `createPatient()` (Master Pasien) |
| **Dokter** | `rsmst_doctors.dr_uuid` | manual (trait `searchPractitioner` by NIK/IBP/SIPP tersedia tapi tak dipakai runtime) |
| **Poli / Location** | `rsmst_polis.poli_uuid` | manual (trait `searchLocation`/`createLocation` tersedia) |
| **Organization** | env `SATUSEHAT_ORGANIZATION_ID` | tetap (`100027469`) |

⚠️ Kalau `dr_uuid` / `poli_uuid` kosong → kirim Encounter berhenti dengan toast error (`kirim-encounter.blade.php:92-99`). NIK harus 16 digit; kalau tidak, identifier di-skip diam-diam (`PatientTrait.php:47-61`).

---

## 5. Model pengiriman (urutan & aturan)

Urutan kanonik (dari orkestrator `KirimRawatJalanTrait::kirimRawatJalan()`, `:74-118`). Di UI aktif langkah 1-4 + 7 yang tersedia sebagai tombol; sisanya baru ada di trait.

| # | Langkah | Resource FHIR | Sistem kode | Gate |
|---|---|---|---|---|
| 1 | Kunjungan | **Encounter** | class `AMB` (v3-ActCode) | **ROOT — wajib sukses, kalau gagal semua berhenti** (`:76-78`) |
| 2 | Diagnosa | **Condition** (`encounter-diagnosis`) | ICD-10 | fail-soft |
| 3 | Tanda vital | **Observation** (`vital-signs`) | LOINC | fail-soft |
| 4 | Tindakan | **Procedure** | ICD-9-CM | fail-soft |
| 5 | Keluhan utama | **Condition** (`problem-list-item`) | SNOMED | fail-soft |
| 6 | Alergi | **AllergyIntolerance** | SNOMED | fail-soft |
| 7 | Peresepan obat | **MedicationRequest** | KFA | fail-soft |
| 8 | Obat dibawa pulang | **MedicationDispense** | KFA | fail-soft |
| 9-11 | Penunjang lab | **ServiceRequest → Observation(`laboratory`) → DiagnosticReport** | LOINC | fail-soft |

**Aturan penting:**
- **Encounter adalah akar.** Semua resource lain mereferensikan `Encounter/{id}`, `Patient/{id}`, `Practitioner/{id}`. Encounter punya siklus status 3 tahap: `arrived` (POST) → `in-progress` (PUT, `startRoomEncounter`) → `finished` (PUT, hanya bila `txnStatus=CLOSED` atau `rjStatus=2`).
- **Idempotensi** = guard in-memory pada state `$ss` (`if empty($ss['...Ids'])`) + node JSON `satusehat` di record RJ. Setiap `kirim()` cek "sudah pernah?" → toast info & berhenti. Hanya **Encounter** & **ServiceRequest** yang punya `identifier` bisnis (natural key) di sisi server; resource lain andalkan guard lokal → **hati-hati kirim dobel bila state JSON hilang**.
- **Item tanpa kode kunci di-skip diam-diam** (`continue`): diagnosa tanpa `kodeIcdx`, tindakan tanpa `kodeIcd9`, obat tanpa `kfaCode`, lab tanpa `loincCode`. Bisa "berhasil (0 item)" tanpa peringatan.
- **Penyimpanan hasil:** node `satusehat` di JSON RJ → `encounterId`, `conditionIds[]`, `observationIds[]`, `procedureIds[]`, `medicationRequestIds[]`, flag `encounterInProgress`/`encounterFinished`. Ditulis via `DB::transaction` + `lockRJRow` + `updateJsonRJ`.

---

## 6. Standarisasi data per resource

| Resource | Trait | resourceType / status | Sistem kode (system URI) | Sumber data (JSON EMR / master) |
|---|---|---|---|---|
| Encounter | EncounterTrait | `Encounter` / arrived→in-progress→finished | class `http://terminology.hl7.org/CodeSystem/v3-ActCode` = `AMB` | `rjNo`, `dr_uuid`, `poli_uuid`, `rjDate`, `regName` |
| Condition (diagnosa) | ConditionTrait `createFinalDiagnosis` | `Condition` / active·confirmed, `encounter-diagnosis` | **ICD-10** `http://hl7.org/fhir/sid/icd-10` (SNOMED opsional dual-coding, tak diisi di RJ) | `diagnpinaList[]`/`diagnosaPinaUtama`, `kodeIcdx`/`icdx` |
| Condition (keluhan utama) | ConditionTrait `createChiefComplaint` | `Condition` / `problem-list-item` | **SNOMED** `http://snomed.info/sct` | `keluhanUtama` + `keluhanUtamaSnomedCode` |
| Observation (vital) | ObservationTrait | `Observation` / final, `vital-signs` | **LOINC** `http://loinc.org`, unit UCUM `http://unitsofmeasure.org` | `pemeriksaanFisik`/`tandaVital`: sistole/diastole/nadi/suhu/rr |
| Procedure | ProcedureTrait | `Procedure` / completed | **ICD-9-CM** `http://hl7.org/fhir/sid/icd-9-cm` (category SNOMED `71388002`) | `tindakanList`/`tindakan`, `kodeIcd9`/`icd9` |
| AllergyIntolerance | AllergyIntoleranceTrait | `AllergyIntolerance` / active·confirmed | **SNOMED** | (belum di-wire UI) |
| MedicationRequest | MedicationRequestTrait | `MedicationRequest` + contained `Medication` | **KFA** `http://sys-ids.kemkes.go.id/kfa` | `eresep`/`resepObat`; KFA dari master obat `product_id_satusehat` |
| MedicationDispense | MedicationDispenseTrait | `MedicationDispense` (encounter via `context`) | **KFA** | idem MedicationRequest (belum di-wire UI) |
| ServiceRequest | ServiceRequestTrait | `ServiceRequest` / active·original-order | generik (dimaksudkan LOINC) | (belum di-wire UI) |
| Specimen | SpecimenTrait | `Specimen` / available | generik | (belum di-wire UI) |
| DiagnosticReport | DiagnosticReportTrait | `DiagnosticReport` / final | **LOINC** (category default `MB`/Microbiology) | (belum di-wire UI) |

**Kode LOINC vital di-hardcode di blade** (`kirim-observation.blade.php:86-99`): TD panel `85354-9` (komponen `8480-6` sistole / `8462-4` diastole), Nadi `8867-4`, Suhu `8310-5`, RR `9279-1`. `LoincTrait`/`SnomedTrait` (lookup live ke `tx.fhir.org`) **tidak dipakai** di alur RJ.

**KFA obat** diambil dari master obat kolom `product_id_satusehat` / `product_name_satusehat` (di-set manual di `/master/master-obat`). Kalau kosong → item resep di-skip.

---

## 7. Pemetaan kolom Dashboard SATUSEHAT → status implementasi

Kolom di dashboard platform SATUSEHAT (jumlah resource per bulan) vs kondisi di sistem ini:

| Kolom Dashboard (Resource FHIR) | Trait ada? | Ter-wire di UI RJ? | Sistem kode |
|---|---|---|---|
| Jumlah Kunjungan (**Encounter**) | ✅ | ✅ tombol | class AMB |
| Jumlah Diagnosis (**Condition**) | ✅ | ✅ tombol | ICD-10 (+SNOMED keluhan) |
| Jumlah Observasi (**Observation**) | ✅ | ✅ tombol | LOINC |
| Jumlah Tindakan (**Procedure**) | ✅ | ✅ tombol | ICD-9-CM |
| Jumlah Peresepan Obat (**MedicationRequest**) | ✅ | ✅ tombol | KFA |
| Jumlah Obat Dibawa Pulang (**MedicationDispense**) | ✅ | ⚠️ trait saja | KFA |
| Jumlah Layanan Penunjang (**ServiceRequest**) | ✅ | ⚠️ trait saja | LOINC |
| Jumlah Laboratorium (**Specimen**) | ✅ | ⚠️ trait saja | — |
| Jumlah Pelaporan Diagnostik (**DiagnosticReport**) | ✅ | ⚠️ trait saja | LOINC |
| Jumlah Intoleransi Alergi (**AllergyIntolerance**) | ✅ | ⚠️ trait saja | SNOMED |
| Jumlah Diet (**Composition**) | ❌ | ❌ | — |
| Jumlah Impresi Klinik (**ClinicalImpression**) | ❌ | ❌ | — |
| Jumlah Radiologi (**ImagingStudy**) | ❌ | ❌ | — |
| Jumlah Imunisasi (**Immunization**) | ❌ | ❌ | — |
| Jumlah Episode Perawatan (**EpisodeOfCare**) | ❌ | ❌ | — |
| Jumlah Instruksi Gizi (**NutritionOrder**) | ❌ | ❌ | — |

**Ringkas coverage:** 5 resource sudah terkirim penuh (Encounter, Condition, Observation, Procedure, MedicationRequest). 5 resource sudah ada trait tapi perlu di-wire ke UI (MedicationDispense, ServiceRequest, Specimen, DiagnosticReport, AllergyIntolerance). 6 resource belum dibuat sama sekali (Composition/Diet, ClinicalImpression, ImagingStudy, Immunization, EpisodeOfCare, NutritionOrder) — **payload lengkap & metode kirim di §9**.

---

## 8. Backlog & gotcha (verifikasi lapangan)

1. **`env()` tanpa config wrapper** → mati senyap bila `config:cache`. **Rekomendasi:** buat `config/satusehat.php` dan baca via `config('satusehat.*')`.
2. **5 resource belum di-wire** (Dispense/ServiceRequest/Specimen/DiagnosticReport/Allergy) → dashboard SATUSEHAT untuk kolom itu akan 0 walau trait tersedia. Orkestrator `KirimRawatJalanTrait` sudah memuat semuanya tapi belum dipanggil UI.
3. **Timeout 10s tanpa retry/connectTimeout** — samakan pola dengan integrasi lain (BPJS `timeout(8)->connectTimeout(3)`), lihat memori "BPJS sync call = freeze".
4. **KFA/kode di-skip diam-diam** bila master belum diisi → tambahkan peringatan "N item tanpa kode dilewati".
5. **`registrationId == medicationCode == kfaCode`** di `kirim-medication-request.blade.php:89-90` — perlu ditinjau apakah field registrasi obat harus beda dari KFA.
6. **DiagnosticReport default kategori `MB`/Microbiology** — set eksplisit `LAB`/`RAD` saat mengaktifkan lab/radiologi.
7. **Diagnosa tidak menandai primer/sekunder** (`Encounter.diagnosis.rank` tidak diisi) — semua Condition setara.
8. **Token TTL hardcoded 3500** mengabaikan `expires_in`, tak ada invalidasi cache saat 401.

---

## 9. Resource belum ada — payload lengkap & metode kirim

Enam kolom dashboard SATUSEHAT **belum punya trait sama sekali** (lihat §7). Bagian ini
adalah referensi kanonik cara mengirimnya: endpoint, contoh payload **FHIR R4**, metode
`createX()` (idiom repo: `resourceType` → `subject`/`encounter` reference → `makeRequest('post', '/X', $payload)`),
pemetaan sumber data SIRUS, dan gap yang harus ditutup dulu.

> ⚠️ **Semua di bagian ini = cetak-biru, BELUM diuji ke sandbox Kemkes.** Uji di `-stg`
> dulu, verifikasi via `web_log_status`, baru arahkan ke production.

### 9.0 Prasyarat & urutan

- **Prasyarat semua resource:** `Encounter` pasien harus sudah terkirim — keenam resource
  ini mereferensikan `Encounter/{id}` **dan** `Patient/{id}` (IHS pasien, §4).
- **Idempotensi:** resource ini tak punya natural key di server → **wajib guard lokal**
  (cek node JSON `satusehat` sebelum kirim), sama seperti Procedure/Observation (§5).
- **Urutan implementasi disarankan** (dari data paling siap → paling butuh modul baru):

| # | Resource FHIR | Endpoint | Sistem kode | Sumber data SIRUS | Kesiapan |
|---|---|---|---|---|---|
| 1 | **EpisodeOfCare** | `POST /EpisodeOfCare` | episodeofcare-type | `rstxn_rihdrs` (RI) | ✅ data ada |
| 2 | **ClinicalImpression** | `POST /ClinicalImpression` | SNOMED (finding) | asesmen "A" SOAP EMR | ✅ data ada |
| 3 | **NutritionOrder** | `POST /NutritionOrder` | SNOMED (oralDiet) | order diet EMR (role Gizi) | ◑ perlu petakan kode |
| 4 | **Composition** | `POST /Composition` | LOINC (doc type) | narasi EMR → section | ◑ perlu tipe LOINC |
| 5 | **ImagingStudy** | `POST /ImagingStudy` | DICOM DCM + ICD-9-CM | modul Radiologi | ⚠️ gap: UID DICOM |
| 6 | **Immunization** | `POST /Immunization` | KFA (vaksin) | — | ⚠️ gap: belum ada modul |

---

### 9.1 EpisodeOfCare — Episode Perawatan (utamanya Rawat Inap)

Mengelompokkan **banyak Encounter** dalam satu episode perawatan. Setiap Encounter di
episode itu menambahkan `Encounter.episodeOfCare[] = { reference: 'EpisodeOfCare/{id}' }`.
Paling relevan untuk **RI** (satu rawat inap = satu episode); RJ umumnya single-encounter.

**Payload FHIR R4:**

```json
{
  "resourceType": "EpisodeOfCare",
  "identifier": [{
    "system": "http://sys-ids.kemkes.go.id/episodeofcare/{organizationId}",
    "value": "{rihdr_no}"
  }],
  "status": "active",
  "type": [{
    "coding": [{
      "system": "http://terminology.hl7.org/CodeSystem/episodeofcare-type",
      "code": "hacc",
      "display": "Home and Community Care"
    }]
  }],
  "patient": { "reference": "Patient/{ihsPatient}" },
  "managingOrganization": { "reference": "Organization/{organizationId}" },
  "period": { "start": "2026-07-14T08:00:00+07:00", "end": null },
  "careManager": { "reference": "Practitioner/{ihsDpjp}" }
}
```

**Metode:**

```php
public function createEpisodeOfCare(array $data): array
{
    $payload = [
        'resourceType' => 'EpisodeOfCare',
        'identifier'   => [[
            'system' => 'http://sys-ids.kemkes.go.id/episodeofcare/' . $this->organizationId,
            'value'  => $data['episodeNo'],            // rihdr_no
        ]],
        'status' => $data['status'] ?? 'active',       // active | finished | cancelled
        'type'   => [[ 'coding' => [[
            'system'  => 'http://terminology.hl7.org/CodeSystem/episodeofcare-type',
            'code'    => 'hacc',
            'display' => 'Home and Community Care',
        ]]]],
        'patient'              => ['reference' => 'Patient/' . $data['patientId']],
        'managingOrganization' => ['reference' => 'Organization/' . $this->organizationId],
        'period' => array_filter([
            'start' => $data['start'] ?? now()->toIso8601String(),
            'end'   => $data['end'] ?? null,           // diisi saat pasien pulang → PUT status 'finished'
        ]),
        'careManager' => ['reference' => 'Practitioner/' . $data['careManagerId']],
    ];
    return $this->makeRequest('post', '/EpisodeOfCare', $payload);
}
```

- **Pemetaan SIRUS:** `episodeNo` = `rihdr_no`; `start` = tgl masuk RI; `end` = tgl pulang
  (kosong selama dirawat, di-`PUT` `status: finished` + `period.end` saat pulang);
  `careManagerId` = `dr_uuid` DPJP.
- **PR:** karena RI belum punya alur kirim SATUSEHAT sama sekali, EpisodeOfCare mengharuskan
  Encounter RI dikirim lebih dulu — implementasikan jalur RI (Encounter) bersamaan.

---

### 9.2 ClinicalImpression — Impresi Klinik

Asesmen klinis dokter (huruf **"A"** di SOAP) — kesimpulan/impresi terhadap kondisi pasien.

**Payload FHIR R4:**

```json
{
  "resourceType": "ClinicalImpression",
  "status": "completed",
  "description": "Asesmen kunjungan rawat jalan",
  "subject": { "reference": "Patient/{ihsPatient}" },
  "encounter": { "reference": "Encounter/{ihsEncounter}" },
  "effectiveDateTime": "2026-07-14T09:15:00+07:00",
  "date": "2026-07-14T09:20:00+07:00",
  "assessor": { "reference": "Practitioner/{ihsDpjp}" },
  "summary": "Suspek ISPA viral, perbaikan klinis, rawat jalan.",
  "finding": [{
    "itemCodeableConcept": {
      "coding": [{ "system": "http://snomed.info/sct", "code": "54150009", "display": "Upper respiratory infection" }]
    }
  }]
}
```

**Metode:**

```php
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
        'summary'      => $data['summary'],
        'finding'      => array_map(fn ($f) => [
            'itemCodeableConcept' => ['coding' => [[
                'system'  => 'http://snomed.info/sct',
                'code'    => $f['code'],
                'display' => $f['display'],
            ]]],
        ], $data['findings'] ?? []),
    ];
    return $this->makeRequest('post', '/ClinicalImpression', $payload);
}
```

- **Pemetaan SIRUS:** `summary` = teks section Penilaian/Assessment EMR; `assessorId` = DPJP;
  `finding` opsional (isi bila asesmen dipetakan ke SNOMED).
- **PR:** SNOMED untuk `finding` opsional — kirim tanpa `finding` (hanya `summary`) sudah valid.

---

### 9.3 NutritionOrder — Instruksi Gizi

Order diet pasien. Role **Gizi** sudah punya akses Daftar RI/EMR (lihat modul terkait).

**Payload FHIR R4:**

```json
{
  "resourceType": "NutritionOrder",
  "status": "active",
  "intent": "order",
  "patient": { "reference": "Patient/{ihsPatient}" },
  "encounter": { "reference": "Encounter/{ihsEncounter}" },
  "dateTime": "2026-07-14T10:00:00+07:00",
  "orderer": { "reference": "Practitioner/{ihsDokter}" },
  "oralDiet": {
    "type": [{
      "coding": [{ "system": "http://snomed.info/sct", "code": "435801000124108", "display": "Low sodium diet" }],
      "text": "Diet rendah garam"
    }]
  }
}
```

**Metode:**

```php
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
        'oralDiet'     => [ 'type' => [[
            'coding' => [[
                'system'  => 'http://snomed.info/sct',
                'code'    => $data['dietCode'],
                'display' => $data['dietDisplay'],
            ]],
            'text' => $data['dietText'],               // "Diet rendah garam", dst.
        ]]],
    ];
    return $this->makeRequest('post', '/NutritionOrder', $payload);
}
```

- **Pemetaan SIRUS:** `dietText` = teks diet dari EMR (role Gizi); `ordererId` = DPJP/dokter gizi.
- **PR:** butuh tabel/mapping teks-diet → **kode SNOMED diet** (`oralDiet.type.coding`).
  Bisa kirim minimal dengan `text` saja bila kode belum tersedia (sebagian server menerima).

---

### 9.4 Composition — Dokumen Klinis Terstruktur (label dashboard "Diet")

Dokumen ber-section (mis. ringkasan pasien pulang / rencana). Dashboard Kemkes melabelinya
"Diet" tetapi resource-nya adalah **Composition** (dokumen FHIR generik).

**Payload FHIR R4:**

```json
{
  "resourceType": "Composition",
  "identifier": {
    "system": "http://sys-ids.kemkes.go.id/composition/{organizationId}",
    "value": "{docNo}"
  },
  "status": "final",
  "type": {
    "coding": [{ "system": "http://loinc.org", "code": "18842-5", "display": "Discharge summary" }]
  },
  "subject": { "reference": "Patient/{ihsPatient}" },
  "encounter": { "reference": "Encounter/{ihsEncounter}" },
  "date": "2026-07-14T12:00:00+07:00",
  "author": [{ "reference": "Practitioner/{ihsDpjp}" }],
  "title": "Ringkasan Pasien Pulang",
  "section": [{
    "title": "Ringkasan",
    "code": { "coding": [{ "system": "http://loinc.org", "code": "8648-8", "display": "Hospital course" }] },
    "text": { "status": "generated", "div": "<div xmlns=\"http://www.w3.org/1999/xhtml\">...</div>" }
  }]
}
```

**Metode:**

```php
public function createComposition(array $data): array
{
    $payload = [
        'resourceType' => 'Composition',
        'identifier'   => [
            'system' => 'http://sys-ids.kemkes.go.id/composition/' . $this->organizationId,
            'value'  => $data['docNo'],
        ],
        'status' => $data['status'] ?? 'final',
        'type'   => [ 'coding' => [[
            'system'  => 'http://loinc.org',
            'code'    => $data['loincDocType'],        // jenis dokumen (LOINC)
            'display' => $data['loincDisplay'],
        ]]],
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
```

- **Pemetaan SIRUS:** `section[].text.div` = narasi EMR (ringkasan/rencana) dibungkus XHTML valid;
  `authorId` = DPJP; `docNo` = nomor dokumen unik.
- **PR:** tentukan `type` LOINC dokumen (mis. `18842-5` discharge summary); `div` **wajib**
  XHTML valid ber-namespace (`xmlns="http://www.w3.org/1999/xhtml"`).

---

### 9.5 ImagingStudy — Radiologi

Modul radiologi kita **upload-based** (tanpa PACS/DICOM), jadi UID DICOM
(`studyUid`/`seriesUid`/`sopUid`) tidak tersimpan.

**Payload FHIR R4 (minimal, tanpa instance):**

```json
{
  "resourceType": "ImagingStudy",
  "identifier": [{ "system": "urn:dicom:uid", "value": "urn:oid:{studyUid}" }],
  "status": "available",
  "subject": { "reference": "Patient/{ihsPatient}" },
  "encounter": { "reference": "Encounter/{ihsEncounter}" },
  "started": "2026-07-14T11:00:00+07:00",
  "numberOfSeries": 1,
  "numberOfInstances": 1,
  "referrer": { "reference": "Practitioner/{ihsDpjp}" },
  "procedureCode": [{
    "coding": [{ "system": "http://hl7.org/fhir/sid/icd-9-cm", "code": "87.44", "display": "Routine chest x-ray" }]
  }],
  "series": [{
    "uid": "{seriesUid}",
    "number": 1,
    "modality": { "system": "http://dicom.nema.org/resources/ontology/DCM", "code": "CR", "display": "Computed Radiography" },
    "numberOfInstances": 1,
    "started": "2026-07-14T11:00:00+07:00"
  }]
}
```

**Metode:**

```php
public function createImagingStudy(array $data): array
{
    $payload = [
        'resourceType' => 'ImagingStudy',
        'identifier'   => [['system' => 'urn:dicom:uid', 'value' => 'urn:oid:' . $data['studyUid']]],
        'status'    => 'available',
        'subject'   => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
        'started'   => $data['started'] ?? now()->toIso8601String(),
        'numberOfSeries'    => count($data['series']),
        'numberOfInstances' => $data['numberOfInstances'] ?? 1,
        'referrer'      => ['reference' => 'Practitioner/' . $data['referrerId']],
        'procedureCode' => [['coding' => [[
            'system'  => 'http://hl7.org/fhir/sid/icd-9-cm',   // atau LOINC
            'code'    => $data['procedureCode'],
            'display' => $data['procedureDisplay'],
        ]]]],
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
```

- **Pemetaan SIRUS:** sumber `rstxn_*rads` / `rsview_rads`; `procedureCode` dari master
  pemeriksaan radiologi (ICD-9-CM); `modality` per jenis alat (CR/CT/US/MR).
- **Gap yang harus ditutup:** UID DICOM tak ada. Opsi: (a) **generate OID sendiri** yang stabil
  per pemeriksaan (mis. prefix OID RS + id pemeriksaan) dan kirim minimal (tanpa `instance[]`);
  (b) integrasi PACS bila tersedia. Tanpa UID valid, server menolak.

---

### 9.6 Immunization — Imunisasi

Belum ada modul imunisasi di sistem — **perlu form capture dulu**.

**Payload FHIR R4:**

```json
{
  "resourceType": "Immunization",
  "status": "completed",
  "vaccineCode": {
    "coding": [{ "system": "http://sys-ids.kemkes.go.id/kfa", "code": "{kfaVaksin}", "display": "Vaksin ..." }]
  },
  "patient": { "reference": "Patient/{ihsPatient}" },
  "encounter": { "reference": "Encounter/{ihsEncounter}" },
  "occurrenceDateTime": "2026-07-14T10:30:00+07:00",
  "primarySource": true,
  "location": { "reference": "Location/{ihsPoli}" },
  "lotNumber": "L123",
  "route": {
    "coding": [{ "system": "http://terminology.hl7.org/CodeSystem/v3-RouteOfAdministration", "code": "IM", "display": "Injection, intramuscular" }]
  },
  "doseQuantity": { "value": 0.5, "system": "http://unitsofmeasure.org", "code": "mL" },
  "performer": [{ "actor": { "reference": "Practitioner/{ihsPetugas}" } }]
}
```

**Metode:**

```php
public function createImmunization(array $data): array
{
    $payload = [
        'resourceType' => 'Immunization',
        'status'       => $data['status'] ?? 'completed',
        'vaccineCode'  => ['coding' => [[
            'system'  => 'http://sys-ids.kemkes.go.id/kfa',    // KFA vaksin
            'code'    => $data['kfaCode'],
            'display' => $data['kfaDisplay'],
        ]]],
        'patient'            => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'          => ['reference' => 'Encounter/' . $data['encounterId']],
        'occurrenceDateTime' => $data['occurrence'] ?? now()->toIso8601String(),
        'primarySource'      => true,
        'location'           => ['reference' => 'Location/' . $data['locationId']],
        'lotNumber'          => $data['lotNumber'] ?? null,
        'route'              => ['coding' => [[
            'system'  => 'http://terminology.hl7.org/CodeSystem/v3-RouteOfAdministration',
            'code'    => $data['routeCode'] ?? 'IM',
            'display' => $data['routeDisplay'] ?? 'Injection, intramuscular',
        ]]],
        'doseQuantity' => [
            'value'  => $data['doseValue'] ?? 0.5,
            'system' => 'http://unitsofmeasure.org',
            'code'   => 'mL',
        ],
        'performer' => [['actor' => ['reference' => 'Practitioner/' . $data['performerId']]]],
    ];
    return $this->makeRequest('post', '/Immunization', $payload);
}
```

- **Pemetaan SIRUS:** butuh **modul/riwayat imunisasi baru** yang menangkap jenis vaksin
  (ber-KFA, ambil dari master obat `product_id_satusehat`), lot, rute, dosis, petugas.
- **Gap yang harus ditutup:** data belum ada sama sekali → prioritas paling akhir; dahului
  dengan form capture (paling relevan imunisasi anak / vaksin di RJ).

---

## 10. Cara menambah / mengaktifkan resource baru

1. **Sudah ada trait, tinggal wire ke UI:** buat komponen Livewire `kirim-<resource>.blade.php` meniru `kirim-procedure.blade.php` (state, tombol `kirimForCurrent`, `saveResult()` ke node `satusehat`, gate `:disabled="!$hasEncounter"`), lalu render di `satu-sehat-rj-actions.blade.php` (baris ~105-114).
2. **Belum ada trait (EpisodeOfCare/ClinicalImpression/NutritionOrder/Composition/ImagingStudy/Immunization):** buat `App\Http\Traits\SATUSEHAT\<Resource>Trait` meniru pola `ProcedureTrait` (bangun payload FHIR R4 + `POST /<Resource>` via `makeRequest`), pastikan referensi `Encounter/{id}` + `subject Patient/{id}`. **Payload lengkap & metode `createX()` untuk keenam resource ini sudah disiapkan di §9** — tinggal salin.
3. Simpan id hasil ke node JSON `satusehat`. Uji di **sandbox** dulu (ganti env AUTH/BASE URL ke `-stg`).
4. Verifikasi via tabel `web_log_status` (http_req/http_payload/response).

> Lihat juga: `docs/trait-template-api-eksternal.md` (pola trait API eksternal), memori "BPJS sync call = freeze" (timeout), `docs/diagnosa-architecture.md` (kode ICD-10/diagnosa).
