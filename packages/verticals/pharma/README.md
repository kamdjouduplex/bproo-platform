# Bproo Pharma (vertical)

Composer: `bproo/pharma` · Namespace: `Pharma\` · Host: `apps/pharma`

## Scope

Pharmacy-specific layer on top of shared retail packages (`sales`, `stock`, `batches`, `prescriptions`, …).

| Phase | In this package |
|---|---|
| V1 (now) | Hub UI, role templates (pharmacien / caissier / magasinier), module bootstrap |
| V2+ | Drug catalogue fields (DCI, forme, dosage), hard expiry rules hooks |
| V3+ | Insurance / mutuelles, loyalty, delivery |

**Rule:** never put POS/stock core here — extend shared `inovcom/*` packages or call their APIs.
