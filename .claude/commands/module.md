---
description: Build one MVP step from docs/planning.md (usage: /module 6)
argument-hint: <step number>
---

Build step $ARGUMENTS from docs/planning.md.

1. Read CLAUDE.md (including the "Laravel skills" section — it overrides the skills), docs/planning.md (step $ARGUMENTS), and the matching sections of docs/data-model.md and docs/business-rules.md.
2. Check what already exists in the codebase for this step so you don't duplicate work.
3. Propose a short plan: migrations, models, services, routes, pages, tests. Wait for my approval.
4. If business-rules.md lists required tests for this area, write them first.
5. Build in small steps. After each step run `docker compose exec app php artisan test`, `docker compose exec app npm run build` and `docker compose exec app npx vue-tsc --noEmit`; fix failures before continuing. Never run php, composer or npm on the host.
6. Keep it simple — nothing beyond what planning.md lists for this step.
7. When done, tick the checklist and Status table in docs/planning.md and summarize what changed and anything I need to check manually.
