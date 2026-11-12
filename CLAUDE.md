# Xomiton

Les instructions du projet sont dans le skill `xomiton` (`.claude/skills/xomiton/`) : il indique quels documents de `docs/` lire selon la tâche.

## SDLC Vault

Ce projet utilise le **SDLC Kit**. Avant toute tâche qui touche à la planification :

- Lire `.sdlc/CLAUDE.md` — doctrine du vault (schéma du frontmatter, conventions)
- Consulter `.sdlc/_INDEX.md` — index (régénéré par le bibliothécaire)

Commandes principales : `/sdlc-kit:steer`, `/sdlc-kit:prd`, `/sdlc-kit:spec`, `/sdlc-kit:adr`, `/sdlc-kit:dash`

La source de vérité du plan reste `docs/IMPLEMENTATION_PLAN.md` ; les PRD du vault (`.sdlc/01-planning/prd/`) y renvoient.
