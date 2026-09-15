# Offline / Desktop runtime — working notes

**Status:** active epic  
**Branch:** `feature/offline-runtime`  
**ADR:** [0002-offline-desktop-runtime.md](../adr/0002-offline-desktop-runtime.md)

## Goal

Ship Bproo as:

1. **SaaS** (current) for connected customers  
2. **Stand-alone desktop** (offline-first) for the majority of African shops/schools — with **Afroinov-controlled licence + remote updates**

Same monorepo. No second business codebase.

## Current phase

**Phase 2 — Update channel** (signed manifest + download) — see [PHASE-2-UPDATES.md](./PHASE-2-UPDATES.md).  
Phase 1 licence remains documented in [PHASE-1-LICENCE.md](./PHASE-1-LICENCE.md).

Next coding target: **Phase 3 — local event bus + OUT sync** (cloud backup).

## Non-goals (for now)

- Multi-store realtime mesh  
- Rewriting Livewire UI in another stack  
- Blocking `main` School/Pharma delivery  

## Sync sketch

```
Desktop local DB  --OUT events-->  Cloud (backup / licence / updates)
Desktop local DB  <--IN events--   Cloud (phase 2+)
```

Events are append-only and idempotent. Stock/sales are reconciled by replay, not last-write-wins.

## Licence sketch

- Activate online → signed token  
- Heartbeat when online  
- Grace 14–30 days offline → then read-only  

## Update sketch

- Signed manifest + package  
- Apply → healthcheck → rollback on failure  
