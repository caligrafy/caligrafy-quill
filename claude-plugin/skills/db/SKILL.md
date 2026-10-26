---
name: db
description: Run Caligrafy Quill SQL scripts (application/models/**/create_db.sql or any .sql file) against the project's MySQL - inside the docker-compose mysql container, or a local MySQL using the .env DB_* settings - and inspect tables.
when_to_use: The user asks to create or update database tables, run a create_db.sql script, import SQL, check which tables exist, or fix [ERROR 2003] / "table doesn't exist" errors in a Caligrafy Quill project.
argument-hint: "<path/to/script.sql | tables | query \"SQL\">"
---

# Caligrafy database tasks

Arguments: `$ARGUMENTS`. Read [gotchas.md](../caligrafy/reference/gotchas.md) G3, G10, G11, G13 first.

## 1. Find the database settings (never print passwords)
Read `.env`: `DB_ACTIVATE`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`; use `DB_PASSWORD` only inside commands. If `DB_ACTIVATE` is not `true`, the app will not connect (G20 - tell the user). For connection errors, `php caligrafer.php doctor --only db` reports the exact cause without printing the password.

## 2. Pick the target
- **Docker** (default when `DB_HOST=mysql` or this project's `mysql` service is running): find it with `docker-compose ps mysql` (or `docker compose ps mysql`). If it is not running, offer `/caligrafy-quill:server start`. Use
  ```bash
  docker-compose exec -T mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`<DB_DATABASE>\` DEFAULT CHARACTER SET utf8mb4;"
  docker-compose exec -T mysql mysql -uroot -proot <DB_DATABASE> < <script.sql>
  ```
  (credentials: `root`/`root` from docker-compose.yaml unless the user changed them; prefer `.env` values when they differ.)
- **Local MySQL / MAMP**: `mysql -h <DB_HOST> -u <DB_USERNAME> -p<DB_PASSWORD> <DB_DATABASE> < <script.sql>` (`DB_PORT` is ignored by the framework, G3; pass `-P` to the CLI if needed).

## 3. Before running a script
1. Read it. If it contains `DROP TABLE`, `DROP DATABASE`, `TRUNCATE` or `DELETE` (for example `application/models/Auth/create_db.sql` of projects created before the security release drops `users`, G13), list the tables it would drop, check whether they contain rows (`SELECT COUNT(*) FROM <table>`), and get explicit confirmation before running it.
2. If the script has its own `CREATE DATABASE` / `USE <db>`, that database wins over `DB_DATABASE` - point this out when they differ.
3. Check FK constraint names are unique and FK column types match the parent id signedness (G10).
4. Parent tables must be created before child tables.

## 4. Run and verify
- Run the script (commands above). On error, show the MySQL message and fix the SQL, not the data.
- Verify: `SHOW TABLES;` and `DESCRIBE <table>;` for each created table.
- `tables`: list tables with row counts (`SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = '<db>';`).
- `query "SQL"`: run read-only queries freely; ask before any statement that writes.
- Remind the user that each table needs a matching Model class with one public property per column (`/caligrafy-quill:new-resource`).
