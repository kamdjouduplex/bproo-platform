# Bproo School (vertical)

Composer: `bproo/school` · Namespace: `School\\` · Host: `apps/school`

Vertical layer that provides School-specific modules and UI on top of shared Bproo packages.

V1 scope (PRD `apps/school/Bproo_School_PRD_v1.1.md`):
- A) Academic year management + classes/subjects/teachers + student enrollment
- B) Student ID cards (single & batch) with QR/Barcode + PDF/print
- C) Payments (bank + on-site) + receipts

Rule: keep shared commerce/inventory POS logic inside `packages/inovcom/*` or other verticals; do not duplicate it here.

