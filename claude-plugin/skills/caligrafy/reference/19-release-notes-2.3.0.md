# Release notes: 2.3.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.3.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.3.0** comes from real use: upgrading a live site (caligrafy.com, a September 2025 project) and building the Caligrafy course on 2.2.0. It adds one command, makes `upgrade` and `doctor` catch what made that upgrade hard, and fixes the templates that broke. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Try a capability in one line: `call`

```bash
php caligrafer.php call get_book id=3
php caligrafer.php call create_book '{"title": "Kindred", "published_year": 1979, "author_id": 2}'
php caligrafer.php call create_book title=Kindred --guest       # what a visitor gets
```
It prints the same JSON as the REST API, so you can try a capability as soon as you write it, before any route or page. See [Caligrafer: call](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#call).

## Upgrades that tell you what matters

`php caligrafer.php upgrade` now also reports, without changing anything:
+ the **settings** of the new `.env.example` that your `.env` lacks, and obsolete ones (`CRYPTO_PAY_KEY`: Coinbase Commerce closed on March 31, 2026);
+ the **views and apps that still use Bootstrap 3 classes** (`col-xs-`, `pull-left`, `btn-default`...), which break under Bootstrap 5;
+ the untouched default pages it replaces, with **`--keep`** to keep one that became a page of your site (the home page, for example). Kept pages are remembered.

`doctor` warns about `APP_DEBUG=true` on a site served over HTTPS and about a leftover `CRYPTO_PAY_KEY`. See [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrade-command).

## Fixes

| Area | Before | Now |
| --- | --- | --- |
| Payment page template | the Stripe Payment Element overflowed its box | built with the page components |
| Page components | several `.cq-card` in a page sat side by side | they stack |
| Vue app template (`caligrafer app`) | axios with the shared API key | the generated JS client, no key in the page |
| `caligrafer create` in Docker | "packages installed" after `npm: not found` | says the packages are not installed and how to install them |
| Times | the audit log used PHP's time zone, `CURRENT_TIMESTAMP` the database's | one clock: MySQL follows `SERVER_TIMEZONE` (`DB_TIMEZONE=server` keeps the old behavior) |
| Bot template | silent when the assistant cannot be reached | says so, or that the address needs `?botSkillId=` |
| Face detection template | buttons only after the models and the camera | buttons at once, and a message when the camera is blocked |

## Upgrading from 2.2.0

1. Update the framework (`git pull`, or copy `framework/`, `caligrafer.php`, `.bin/` and `claude-plugin/` from the release).
2. Copy `public/css/tokens.css` and `public/js/services/detect.js` from the release (they are not templates).
3. `php caligrafer.php upgrade`, read the report (keep a page with `--keep`), then `php caligrafer.php upgrade --apply`.
4. `php caligrafer.php doctor`.

Existing rows keep their times. New rows use `SERVER_TIMEZONE`, unless you set `DB_TIMEZONE=server`.

The Claude Code plugin is 1.14.0: it knows `call`, the upgrade reports and `--keep`.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
