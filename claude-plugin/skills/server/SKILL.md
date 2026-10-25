---
name: server
description: Start, stop or check the Caligrafy Quill Docker development server (dev-box on 8080, phpMyAdmin on 8077, MySQL on 3306) via .bin/caligrafer / docker-compose, checking for port clashes and .env Docker settings first.
when_to_use: The user asks to start, stop, restart, run or check the Caligrafy server, Docker containers, phpMyAdmin or the local MySQL of a Quill project, or the app is unreachable on localhost:8080.
argument-hint: "<start|stop|status>"
---

# Caligrafy dev server

Action: `$ARGUMENTS` (default `status`). Read [gotchas.md](../caligrafy/reference/gotchas.md) G3, G11, G12, G25 and the Docker section of [api-reference.md](../caligrafy/reference/api-reference.md).

## status
```bash
docker ps --format '{{.Names}}\t{{.Status}}\t{{.Ports}}'
docker-compose ps            # from the project root (or: docker compose ps)
```
Report which services of this project run, their host ports, and the URLs (app `http://localhost:<dev-box port>`, phpMyAdmin `http://localhost:<pma port>`, root/root).

## start
1. **Docker running?** `docker info >/dev/null 2>&1` - if not, ask the user to start Docker Desktop.
2. **Compose command.** Prefer `docker-compose` (what caligrafer calls); if missing use `docker compose`.
3. **Ports.** Read the host ports from `docker-compose.yaml` (defaults 8080 dev-box/prod-box, 8077 phpmyadmin, 3306 mysql). For each:
   ```bash
   lsof -nP -iTCP:<port> -sTCP:LISTEN
   docker ps --format '{{.Names}} {{.Ports}}' | grep ':<port>->'
   ```
   If a port is taken by something that is not this project's container, do NOT stop the other process. Propose remapping the host side in `docker-compose.yaml` (e.g. `8081:80`, `8078:80`, `3307:3306`), apply it only after the user agrees, and use the new ports in every URL you report.
4. **`.env` for Docker** (G11, `.env` overrides compose env). If `.env` exists check, without printing secret values:
   - `DB_HOST=mysql` when `DB_ACTIVATE=true`;
   - `APP_ROOT=` empty (the dev-box serves the project at the web root);
   - `APP_KEY` / `API_KEY` present.
   Propose fixes; edit only with the user's OK.
5. **Initialization.** If `application/` or `vendor/` is missing, `.bin/caligrafer server start` will run `composer install` and `caligrafer initialize`, which **deletes `.git`** (G25). If the folder is a git repo, warn and ask first; alternatively run `composer install` and `cp -r framework/settings/application ./application` manually and create `.env` from `framework/settings/.env.docker`.
6. **Start** from the project root:
   ```bash
   .bin/caligrafer server start
   ```
   It only starts Docker when no local `httpd` (MAMP/LAMP) process is running; if one is, either stop it or run directly:
   ```bash
   docker-compose up --build -d dev-box
   ```
   (`dev-box` mounts the project, so code edits are live; `prod-box`, used by `.bin/server` when PHP is not installed, copies the code into the image and needs a rebuild for every change.)
7. **Verify**: `curl -s -o /dev/null -w '%{http_code}\n' http://localhost:<port>/` (expect 200) and report the app, phpMyAdmin and MySQL (`host 127.0.0.1`, port, `root`/`root`) endpoints. First MySQL start can take ~20 s; retry before declaring failure.

## stop
```bash
.bin/caligrafer server stop      # = docker-compose stop
```
Use `stop`, never `down`, unless the user explicitly wants containers removed: the MySQL service has no named volume, so after `down` a new container starts with an empty database (the old data stays orphaned in an anonymous volume).
