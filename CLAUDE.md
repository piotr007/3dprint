# Claude Code Guide

See `AGENTS.md` for the full project documentation for AI agents
(stack, Docker environment, media handling flow, Filament 5 gotchas, conventions, roadmap).

Quick facts:
- Laravel 13 + Filament 5 + MySQL 8.4, all in Docker (`docker compose up -d`, web on :8080).
- Admin: `/admin`, seeded login `admin@3dprint.local` / `password`.
- Before changing image/file upload code, read "Media handling — critical flow" in `AGENTS.md`.
- Log commands in `commands-log.md`; track decisions/progress in `plan.md`.
