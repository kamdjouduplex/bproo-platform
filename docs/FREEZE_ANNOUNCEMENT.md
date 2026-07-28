# Team announcement — Package freeze (Phase 0)

**Effective immediately**

We have started the Bproo Platform migration (Architecture v1.0).

### What changed today

1. **ERP folder** is now `bproo-erp` (formerly `bproo-erp-pos`). POS stays inside ERP.
2. **Pressing** and **BAT** working trees were committed and are clean.
3. Local baseline tags: `erp-prod-baseline`, `pressing-prod-baseline`, `bat-prod-baseline`.

### Freeze rule (until shared packages exist in M2)

Do **not** copy or diverge `packages/inovcom/*` across apps for shared modules.

- Vertical work OK: `packages/pressing/*`, BAT-only packages (`offres`, `devis`, …)
- Shared package fixes: one place only (coordinate); fingerprint CI will detect ERP↔Pressing drift on the strict set

Details: `docs/FREEZE_POLICY.md` · ADR `docs/adr/0001-adopt-architecture-v1.md`

### Docs hub

`docs/README.md`
