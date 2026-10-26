---
name: server
description: Start, stop or check the Caligrafy Quill Docker development server (dev-box on 8080, phpMyAdmin on 8077, MySQL on 3306) via .bin/caligrafer / docker-compose, checking for port clashes and .env Docker settings first.
when_to_use: The user asks to start, stop, restart, run or check the Caligrafy server, Docker containers, phpMyAdmin or the local MySQL of a Quill project, or the app is unreachable on localhost:8080, a port is already in use, or PHP cURL in Docker fails with "self-signed certificate in certificate chain" (corporate TLS proxy).
argument-hint: "<start|stop|status>"
---

# Caligrafy dev server

Action: `$ARGUMENTS` (default `status`). Read [gotchas.md](../caligrafy/reference/gotchas.md) G3, G11, G12, G25, the Troubleshooting section below and the Docker section of [api-reference.md](../caligrafy/reference/api-reference.md).

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
   If a port is taken by something that is not this project's container, do NOT stop the other process. Propose remapping the host side (e.g. `8081:80`, `8078:80`, `3307:3306`) in a `docker-compose.override.yml` (see Troubleshooting) rather than in the committed `docker-compose.yaml`, apply it only after the user agrees, and use the new ports in every URL you report.
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

## Troubleshooting

### Port already taken: remap without editing docker-compose.yaml
Put personal port changes in `docker-compose.override.yml` at the project root (compose merges it automatically; it is
not in `.gitignore`: propose adding it so personal settings are not committed). `!override` replaces the port list instead of appending to it (Compose 2.24+):
```yaml
services:
  dev-box:
    ports: !override
      - "8081:80"
  phpmyadmin:
    ports: !override
      - "8078:80"
  mysql:
    ports: !override
      - "3307:3306"
```
Without `!override` compose keeps `8080:80` too and still fails on the taken port. Then `docker-compose up -d dev-box`
and report the new URLs.

### "SSL certificate problem: self-signed certificate in certificate chain" from PHP in Docker
Symptoms: OpenAI/Groq/Stripe calls return empty responses or that cURL error (`api_success` false, `httpRequest()`
returns null) for some hosts, while `curl` on the Mac works. Cause: a corporate TLS inspection proxy (Zscaler,
Netskope, Palo Alto...) re-signs HTTPS traffic with a corporate root CA that macOS trusts (System keychain) but the
Debian-based `php:8.5-apache` image does not. Never "fix" it with `CURLOPT_SSL_VERIFYPEER => false`.

1. Confirm from the container:
   ```bash
   docker-compose exec dev-box curl -sS -o /dev/null https://api.openai.com/v1/models   # fails: self-signed certificate in certificate chain
   docker-compose exec dev-box sh -c 'openssl s_client -connect api.openai.com:443 -showcerts </dev/null 2>/dev/null | grep "i:"'   # issuer = corporate CA
   ```
2. Export the corporate root CA (public certificate, not a secret) from the System keychain; ask the user for its
   name if the issuer above does not make it obvious (Keychain Access > System > Certificates):
   ```bash
   mkdir -p docker/certs
   security find-certificate -c "<Corporate Root CA name>" -p /Library/Keychains/System.keychain > docker/certs/corporate-root-ca.crt
   ```
   The file must contain `-----BEGIN CERTIFICATE-----` and have the `.crt` extension (update-ca-certificates ignores other names).
3. Mount it and refresh the trust store before Apache starts, in `docker-compose.override.yml` (not committed):
   ```yaml
   services:
     dev-box:
       volumes:
         - ./docker/certs/corporate-root-ca.crt:/usr/local/share/ca-certificates/corporate-root-ca.crt:ro
       command: sh -c "update-ca-certificates && exec apache2-foreground"
   ```
4. `docker-compose up -d --force-recreate dev-box` and re-run the curl of step 1 (expect no error, HTTP 401 without a
   key is fine). PHP cURL uses the system store, so no php.ini change is needed. Propose adding `docker/certs/` to `.gitignore`.
