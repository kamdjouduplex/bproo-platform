# Shared Composer packages

Populated in **Phase M2** (ERP ↔ Pressing identical packages).

Target layout (Architecture v1.0):

```
packages/
  platform/    # tenancy, auth, modules, billing, …
  shared/      # crm, catalogue, sales, inventory, …
  verticals/   # pressing, bat-*, …
  ui/          # design system
```

Until M2, each app keeps its own `apps/*/packages/inovcom/` tree.
