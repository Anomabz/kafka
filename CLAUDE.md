# Multi-agent workflow

For heavy tasks or changes touching many files/areas in this repo, delegate to subagents via the Agent tool instead of doing everything inline:

- Split the work into independent subtasks and dispatch them as parallel Agent calls (see `superpowers:dispatching-parallel-agents`).
- For multi-step implementation plans, use `superpowers:subagent-driven-development` to run independent tasks as subagents in-session.
- Use `Explore` for research/investigation subtasks, `general-purpose` for implementation subtasks.
- Act as the orchestrator: brief each agent with full context (it starts cold), collect results, and synthesize.

Skip this for small, single-file, or straightforward changes — no need to spin up agents for a one-line fix.

# Aturan port Docker

Server ini menjalankan belasan project compose sekaligus, banyak yang
`restart: always`. Port host yang bentrok baru ketahuan **saat reboot**: yang
kalah balapan mati dengan exit 255 dan Docker tidak mencoba lagi. Ini pernah
menjatuhkan seluruh stack selama 25 jam (2026-07-26).

- Jangan publish port host kalau tidak benar-benar diakses dari luar. Service
  yang cuma dipakai antar-container (mis. `db`) cukup lewat network compose.
- Sebelum menambah `ports:`, pastikan portnya bebas:
  `ss -ltn | awk '{print $4}' | grep -oE '[0-9]+$' | sort -un`
- Jangan pakai `container_name:` — mematikan namespacing compose dan bentrok
  dengan project lain yang kebetulan punya nama service sama.
- Port milik project ini: **8020** (app), **8086** (phpmyadmin). Db tanpa port.

`docker/watchdog.sh` (cron tiap 5 menit) menyalakan ulang stack kalau `/up`
mati, dan mencatat ke `storage/logs/watchdog.log`.
