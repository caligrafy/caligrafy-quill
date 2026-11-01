# Release notes: 2.6.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.6.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.6.0** starts from Composer, on the server you already have. If PHP and Composer are on your computer, two commands give you a working application on MAMP, XAMPP, LAMP or Docker, whichever is running. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Quickstart with Composer

```bash
composer create-project caligrafy/caligrafy-quill myapp
cd myapp
.bin/caligrafer quickstart --example
```

`composer create-project` downloads the release and its libraries, then says where the project will be served and the command to run next. `quickstart` picks the server like `.bin/caligrafer server` does: your MAMP, XAMPP or LAMP when it is running, Docker otherwise (`--local` or `--docker` chooses; on Windows, `php caligrafer.php quickstart --example`). See [Quickstart with Composer](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#composer).

## On your local server

Create the project in the server's web folder, and `quickstart` does the rest:

+ **The address.** It gives the project its address and `APP_ROOT` from its place in the web folder: `http://localhost:8888/myapp` on a default MAMP, `http://localhost/clients/acme` for a nested folder. With MAMP it reads MAMP's own settings, because many installs serve another folder (`DocumentRoot`) or use ports 80 and 3306.
+ **The database.** It asks for your MySQL settings with the server's defaults (MAMP: `root` / `root`; XAMPP and LAMP: `root` and no password), the password without showing it, then creates the database, the framework tables and the example. If the settings do not work, it switches the database off again so that your pages still open, and says what to fix.
+ **Options.** `--url <address>` for a project in another web folder, `--no-db` to skip the database. Without a terminal to confirm the MySQL settings, quickstart skips the database rather than guess (`--db-defaults` takes the defaults): another MySQL, a Docker project's for example, may answer on the same port.

`.bin/caligrafer` also recognizes the Apache of LAMP on Debian and Ubuntu (`apache2`), not only `httpd`.

## Upgrading from 2.5.0

1. Update the framework (`git pull`, or copy `framework/`, `caligrafer.php`, `.bin/` and `claude-plugin/` from the release).
2. `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply` (no template changed in 2.6.0).

The Claude Code plugin is 1.18.0: it knows the Composer quickstart and the local servers (gotcha G86).

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
