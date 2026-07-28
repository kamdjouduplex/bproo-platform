# BPROO ERP - Documentation

Systeme ERP/CRM multi-tenant et modulaire, centre client, pour la gestion d'entreprise (cible : construction, renovation et maintenance du batiment).

## Langues

- **Francais** (par defaut)
- **English**

## Documents

| Document | Description |
|----------|-------------|
| [SPECIFICATION.md](SPECIFICATION.md) | Objectifs metier, processus (projets et maintenance), modules. |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Multi-tenant, modules par tenant, noyau, API Clients. |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Installation, commandes, ajout de modules. |
| [user_manual/README.md](user_manual/README.md) | **Manuel utilisateur — 20 processus testables de bout en bout.** |

## Principes

- **Centre client** : le client est au centre ; offres, projets, maintenances et facturation sont rattaches au client.
- **Multi-tenant** : chaque entreprise (tenant) a sa propre base et parametres.
- **Modules** : activables/desactivables par tenant (sauf core).
- **Portails** : Admin (plateforme) et Portail tenant (espace entreprise), menu lateral.

## Guides de phase

| Guide | Statut | Contenu |
|-------|--------|---------|
| [PHASE_0_GUIDE.md](PHASE_0_GUIDE.md) | ✅ Complété | Architecture core, multi-tenant, kernel |
| [PHASE_1_GUIDE.md](PHASE_1_GUIDE.md) | ✅ Complété | Logique métier : devis, facturation, projets, workflows, PDFs |
| [PHASE_2_GUIDE.md](PHASE_2_GUIDE.md) | ✅ Complété | Maintenance, DMS, Dashboard, Reporting, Kanban, notifications |
| [PHASE_3_GUIDE.md](PHASE_3_GUIDE.md) | ✅ Complété | Planning, Suivi terrain, Audit trail |
| [PHASE_4_GUIDE.md](PHASE_4_GUIDE.md) | ✅ Complété | Rapports avancés, Maturité plateforme, Analytique |

## Roadmap par semaine

**Phase 2 (Semaines 1–6) ✅**

- Week 1-2  → Maintenance module (contracts + orders + interventions)
- Week 3    → DMS module (file upload + attachments)
- Week 4    → Dashboard module (role-based KPI panels)
- Week 5    → Reporting module (AR Aging + Revenue + export)
- Week 6    → Offer Kanban + preventive scheduler + email notifications

**Phase 3 (Semaines 7–12) 🔄**

- Week 7-8  → Planning module (calendar, appointments, scheduling)
- Week 9-10 → Suivi terrain module (site reports, photos, PV client)
- Week 11-12 → Audit trail (change history, audits table)

**Phase 4 (Semaines 13–18) ✅**

- Week 13-14 → Advanced reporting (rentabilité projet, rapports techniciens) ✅
- Week 15-16 → PWA / mobile-first for field technicians _(skipped)_
- Week 17-18 → Platform maturity (admin analytics, subscription tracking) ✅
