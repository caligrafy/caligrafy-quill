# Changelog

Every release of Caligrafy Quill is listed here. The project follows [semantic versioning](https://semver.org): a
major version can break existing projects, a minor version adds features, a patch version only fixes bugs. Each
release is a git tag `v<version>` (on [GitHub](https://github.com/caligrafy/caligrafy-quill/tags) and
[Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill)), and `Caligrafy\Framework::VERSION` holds the
same number. Check the version of a project with `php caligrafer.php version`.

## [2.0.0] - 2026-10-30

The first tagged release. Everything before it was only available as `dev-master`. The
[Release notes: October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) explain
every change, what can break and the upgrade checklist for existing projects.

### Breaking changes
- PHP 8.3 or higher, and stable Composer releases instead of `dev-master` branches (stripe-php 22, Phug 2,
  PHPMailer 7, phpdotenv 5.8, GUMP 2.3, SimpleImage 4.4, groq-php 1.4).
- Errors return real HTTP status codes and JSON errors for API requests.
- Bootstrap 5 without jQuery (the Caligrafy look is kept), Vue 3.5, a Vite 8 + Pinia SPA template instead of Vuex.
- OpenAI on current models with images from `gpt-image-2` (base64), `Assistant` on the Responses API, the Stripe
  Payment Element and Checkout URLs, Coinbase Business, Watson on `WATSON_URL`, ML Core 2.0 and
  @vladmandic/face-api.
- The example controllers moved to the `Caligrafy\Controllers` namespace (short route names keep working).

### Added
- `php caligrafer.php upgrade` brings an existing project's `application/` up to date without overwriting
  customized files.
- `php caligrafer.php doctor` checks `.env`, the database and every configured service (read-only).
- `php caligrafer.php version` and `Caligrafy\Framework::VERSION`; `doctor` shows the version on its first line.
- Namespaced controllers in routes (`'Shop\OrderController@show'`).
- Install to home screen (web app manifest, optional service worker).
- Face login with liveness challenges and anti-replay checks, and passkeys (WebAuthn).
- Open model agents on Groq, Together, Fireworks, OpenRouter or Ollama (`AGENT_PROVIDER`).
- The `caligrafy-quill` Claude Code plugin in `claude-plugin/`.
- PHPUnit unit tests (`qa/unit`) and the Docker QA suite (`qa/run.sh`).

### Security
- `.env` and the server files are no longer served, uploads are limited to an allow-list of extensions, and
  scripts never run from `public/uploads`.

[2.0.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.0.0
