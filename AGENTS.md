# AGENTS.md

Read `CLAUDE.md` first — it is the full project guide (stack, architecture, deployment, conventions) and applies to any coding agent.

Working rules set by the project owner:
- **Local only until asked.** Do not deploy, push, or upload an APK unless the owner explicitly asks in that turn.
- **Never print, commit or log secrets**: `~/.dascare-deploy/*`, `dascare_api/config/*.json`, `.env*`, Firebase/Mapbox/Ably/TomTom keys. The repo is public — scan before any push.
- Never touch the other sites on the Hostinger account (pestify.site, prorev.site).
- Local PHP is 8.0 (no 8.1+ syntax); live is 8.3. Local DB is MariaDB 10.4.
- New migrations go in `dascare_api/migrations/` and must be run on the live DB by hand when publishing.
- The owner tests features themselves; keep web and mobile (`dascare_mobile/`) in sync.

Current task: see `docs/waves-dispatch-handoff.md`.
