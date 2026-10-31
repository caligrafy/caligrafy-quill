# Testing Caligrafy (contributors)

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

This page is for contributors to Caligrafy Quill: how the framework is tested, how to run all or part of the tests, how to add a test, and how a release is prepared. You do not need any of it to build an application with Caligrafy.

Run every command from the root of a **clean checkout of the framework** (`git clone https://github.com/caligrafy/caligrafy-quill.git`), not from one of your projects.

## Two kinds of tests

| | Unit tests | QA suite |
| - | - | - |
| Where | `qa/unit` | `qa/run.sh`, `qa/tests`, `qa/fixtures` |
| What | The core classes, one at a time | The whole framework: database, HTTP requests through Apache, Caligrafer, client apps, the Claude plugin |
| Needs | PHP and `composer install` | Docker, PHP with curl, Node.js and `composer install` |
| Time | Under a second | Several minutes (the first run builds a Docker image) |
| Command | `vendor/bin/phpunit` | `./qa/run.sh` |

Run the unit tests while you work, and the QA suite before you open a pull request. When you change `framework/` or `framework/settings/application`, add a test for the change.

## The QA suite

```bash
composer install
./qa/run.sh            # builds the stack, runs every phase, removes everything
./qa/run.sh --keep     # leaves the stack and the generated files in place, for debugging
```

`qa/run.sh` starts its own Docker stack (`qa/docker-compose.yml`, compose project `quillqa`): the web server built from `docker/Dockerfile` (stage `dev-php`, with the checkout mounted into it) on port `8090`, MySQL 8.4 on port `3316` and Stripe's official mock (`stripe/stripe-mock`). The ports never clash with your development server (`8080`, `3306`); change them with `QA_WEB_PORT` and `QA_DB_PORT`. No API key is needed: OpenAI, Groq and the other Agent providers, Coinbase, Watson and SMTP are replaced by small mock servers from `qa/fixtures`.

Before the phases, it generates what a project needs and removes it at the end: a `.env` from `framework/settings/.env.example` (marked `# QA-GENERATED`), an `application` folder copied from the templates plus the fixtures of `qa/fixtures/application` and `qa/fixtures/facelogin`, the QA routes added to `web.php`, and the client apps `public/qaapp`, `public/qabot`, `public/qafacedetect` and `public/qafacelogin`.

> **The suite refuses to run** when an `application` folder or a `.env` file that it did not create is present: move them aside or use a clean checkout. Note that every Caligrafer command except `doctor` creates `.env` from the template when it is missing: delete that copy before you run the suite.

### Phases

| Phase | Runs | Covers |
| - | - | - |
| Database scripts | `mysql` in the `mysql` container | `models/Auth/create_db.sql` can be re-run without deleting users |
| Framework tests | `qa/tests/cli_tests.php` in the web container | Models (CRUD with falsy values, SQL injection, relationships and pivots), validation, the database connection, helpers, OpenAI request building, the Assistant on the Responses and Conversations APIs, Stripe against stripe-mock, Coinbase Business with real JWT signatures, the Groq Agent and the other Agent providers, Watson |
| Unit tests | `vendor/bin/phpunit` in the web container | The PHPUnit tests of `qa/unit` (see [below](#unit)) |
| HTTP tests | `qa/tests/http_tests.php` on the host, against `http://localhost:8090` | The front controller, the web app manifest and service worker, server files that must not be served, client apps (`?app=`), CSRF and API access, the REST API example of the wiki, uploads, the authentication module, the AI controller, face login with liveness and passkeys, HTTP status codes |
| Doctor tests | `qa/tests/doctor_tests.sh` | `php caligrafer.php doctor` against the mocks: good and wrong keys, unknown models, leading spaces, DNS errors, Agent providers, masked secrets |
| FileInfo hosts | `qa/run.sh` itself | The `.htaccess` rules on a host that only allows `AllowOverride FileInfo`: pages still load, server files and uploaded scripts answer `403`, `sw.js` keeps `Service-Worker-Allowed` and the manifest its type |
| Tooling tests | `qa/tests/tooling_tests.sh` on the host | `php -l` on the framework, `caligrafer initialize` and `upgrade` on an older project, the client app scaffolds, the liveness logic (`node qa/tests/liveness_tests.js`), front-end libraries (pinned and reachable CDN URLs, no jQuery, no Bootstrap 3), the Bootstrap 5 compatibility layer of `theme.css`, `.env` templates, the web app manifest, the Vue SPA template (build, ESLint, `npm audit`), the Claude plugin |

The suite exits with `0` when every phase passes. Each check prints `PASS` or `FAIL` with the reason.

### Running part of the suite

| To run | Command |
| - | - |
| The unit tests only | `vendor/bin/phpunit` (no Docker) |
| The tooling tests only | `sh qa/tests/tooling_tests.sh` (no Docker; add `QA_SKIP_SPA=1` to skip the Vue SPA build, which needs npm and the network) |
| The liveness and passkey client tests | `node qa/tests/liveness_tests.js` |
| A phase that needs the stack | `./qa/run.sh --keep` once, then for example `docker compose -f qa/docker-compose.yml exec -T web php qa/tests/cli_tests.php` or `php qa/tests/http_tests.php`; clean up with `docker compose -f qa/docker-compose.yml down -v` and remove the generated files |

### Options

| Variable | Effect |
| - | - |
| `QA_WIKI=<wiki clone>` | The tooling phase also checks that the wiki mirrors of the Claude plugin are up to date with that clone (`sync-wiki.py --check`). Without it, the check is skipped |
| `QA_NO_BUILD=1` | Reuses the existing `quillqa-web` image instead of rebuilding it. Use it on networks where the image build fails |
| `QA_SKIP_SPA=1` | Skips the Vue SPA build of the tooling phase |
| `QA_WEB_PORT`, `QA_DB_PORT` | Ports of the stack (`8090`, `3316`) |

For example, before a release:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.wiki.git ../caligrafy-quill.wiki
QA_WIKI=../caligrafy-quill.wiki ./qa/run.sh
```

### One stack at a time

The stack always has the same compose project name (`quillqa`) and ports, and the suite writes `.env`, `application` and the `public/qa*` apps into the checkout. Two runs at the same time, even from two checkouts, share the same containers: one run tears down the stack of the other. Check that no QA stack is running before you start one:
```bash
docker ps --filter name=quillqa
```

### When the image cannot be built

On some slow or proxied networks, `apt-get install` in `docker/Dockerfile` grows to several GB while it retries and the build is killed ("Killed", "cannot allocate memory"). Give Docker more memory, close other containers, or build on another network. Once a `quillqa-web` image exists, run the suite with `QA_NO_BUILD=1 ./qa/run.sh`.

## Unit tests (PHPUnit)

```bash
composer install                               # installs PHPUnit 12 (require-dev)
vendor/bin/phpunit                             # every test of qa/unit
vendor/bin/phpunit qa/unit/RouteTest.php       # one file
vendor/bin/phpunit --filter testBase64Url      # one test
```

The configuration is `phpunit.xml.dist`: test suite `unit` (the folder `qa/unit`), bootstrap `qa/unit/bootstrap.php`, and warnings or risky tests make the run fail.

### What each test file covers

| File | Covers |
| - | - |
| `RouteTest.php` | `Route::resolveController()`: a global class wins over the example namespace, namespaced controllers with or without a leading backslash, short names that fall back to `Caligrafy\Controllers`, unknown controllers |
| `ErrorsTest.php` | `Errors::status()` (404, 401, 403, 500), `Errors::raise()`, the JSON error body for API requests and the plain text for HTML requests |
| `RequestTest.php` | `Request::wantsJson()` and the API key check (`authorizeApiRequest()`) with valid, invalid and missing keys |
| `ModelTest.php` | `prepareInputs()` keeps `0`, `''` and `false` and drops `null` and restricted properties; identifier quoting accepts safe names and rejects unsafe ones |
| `ControllerTest.php` | `validate()` and `filter()` (GUMP) |
| `UpgraderTest.php` | `caligrafer upgrade` in a temporary project: the classification of files, the template history, `--apply` only replaces untouched files and never touches `.env` |
| `DoctorTest.php` | `Doctor::mask()` and the key format checks (leading space, missing dash after the prefix) |
| `DoctorWebAppTest.php` | The install to home screen check of doctor: the framework manifest, missing or invalid manifests, missing icons, required sizes and fields, the service worker check only when `PWA_SERVICE_WORKER` is on |
| `AgentTest.php` | `Agent::settings()`: Groq is the default, the `GROQ_*` and `AGENT_*` settings, key precedence, the defaults of every provider, Ollama without a key, unknown providers |
| `FaceLoginTest.php` | `FaceLogin`: random challenges kept in the session, a nonce accepted once (even when the check fails), expiry, wrong nonce or account, challenge order, identical prints, prints of different faces, print format and count, account matching, print hashes |
| `PasskeyTest.php` | `Passkey` against a software authenticator: registration options, register and authenticate, excluded credentials, replayed assertions, single-use challenges, wrong origin, bad signature, sign counter going back, another user's passkey, user verification, relying party |

### How the bootstrap loads the framework

The unit tests run without a web server, a database or a `.env`. `qa/unit/bootstrap.php` does only what the tested classes need:

1. defines a test `APP_KEY` (constant and environment variable),
2. requires `vendor/autoload.php` and `framework/helpers/corehelpers.php`,
3. requires the core classes under test one by one (`Errors`, `Route`, `Request`, `Model`, `Validator`, `Controller`, `Upgrader`, `Agent`, `Doctor`, `FaceLogin`, `Passkey`),
4. requires the fixtures of `qa/unit/fixtures`: test controllers in the global, `Shop` and `Caligrafy\Controllers` namespaces, and `QaSoftAuthenticator`, a software WebAuthn authenticator (EC P-256, "none" attestation) also used by the HTTP tests.

It never calls `Framework::init()`: no route is dispatched. Classes whose constructor needs the live request or the database are built without their constructor (`ReflectionClass::newInstanceWithoutConstructor()`), and the tests set the properties they need. `Passkey` keeps its credentials in memory by overriding its 4 storage methods. Tests that need files (`UpgraderTest`, `DoctorWebAppTest`) create a temporary project and delete it in `tearDown()`.

### Why `qa/unit` and not `tests/`

The framework requires every PHP file of the `tests/` folder on **every request** (`TEST_PATH`, see `Framework.php` and `Caligrafer.php`). PHPUnit test classes there would be loaded into every page of every application, and would fail without PHPUnit. The unit tests therefore live in `qa/unit`, which the framework never loads (and which `.htaccess` denies to browsers).

### Adding a unit test

1. Create `qa/unit/<Something>Test.php` with a `final class <Something>Test extends TestCase` (`use PHPUnit\Framework\TestCase;`) and `public function test...(): void` methods.
2. If the class under test is not loaded yet, add it to the list of `qa/unit/bootstrap.php`.
3. Keep it independent of the network, the database and `.env`: build objects without their constructor when it reads the request or connects, restore every superglobal (`$_SERVER`, `$_SESSION`) and setting you change in `tearDown()`.
4. Run `vendor/bin/phpunit`.

```php
<?php

use Caligrafy\Errors;
use PHPUnit\Framework\TestCase;

final class ErrorsExampleTest extends TestCase
{
    public function testCsrfErrorIsForbidden(): void
    {
        $this->assertSame(403, Errors::status(3000));
    }
}
```

## Mocks and fixtures (`qa/fixtures`)

| Fixture | Used for |
| - | - |
| `openai_mock.php` | OpenAI Responses, Conversations, Files, Vector Stores and models endpoints (Assistant, doctor). Every request is logged to `qa_openai_mock.log` in the temporary folder, so a test can check what was sent |
| `groq_mock.php` | The OpenAI-compatible endpoints of `Agent` and doctor. The first path segment picks the provider: `/openai/v1` (Groq), `/together/v1`, `/fireworks/inference/v1`, `/openrouter/api/v1`, `/ollama/v1`, `/ollama-empty/v1` (no model pulled), `/custom/v1` |
| `coinbase_mock.php` | Coinbase Business Checkouts API. It logs each request with its `Authorization` header to `qa_coinbase_mock.log`, and `cli_tests.php` verifies the JWT signatures |
| `watson_mock.php` | watsonx Assistant v2 (sessions and messages) |
| `smtp_mock.php` | An SMTP greeter for the doctor `mail` check |
| `echo.php` | Answers with the request it received (method, URI, body, `Authorization` header) to inspect outgoing calls such as `httpRequest()` |
| `application/` | QA controllers and models copied into the generated `application`: `QaController` (errors, uploads, the install module), `BookController` and `Book` (the REST API example of the wiki), a project `AIController` (a name that collides with an example controller) and `Shop/OrderController` (namespaced controller) |
| `books.sql` | Tables of the REST API example |
| `facelogin/` | The face login example of the recipe: `FaceLoginController`, `PasskeyController`, the tables (`create_db.sql`) and the client app |
| `docs/` | The components example of the Vue pages of the wiki |
| `outside/` | A file outside the plugin, for the `php -l` hook test |

The mocks are plain PHP scripts served with `php -S` inside the web container (for example `php -S 127.0.0.1:8766 qa/fixtures/openai_mock.php`), and the tests point the framework to them with the base URL settings (`OPENAI_API_BASE`, `GROQ_API_BASE`, `AGENT_API_BASE`, `STRIPE_API_BASE`, `COINBASE_API_BASE`, `WATSON_URL`).

## Adding a QA check

Choose the phase by what the check needs:

+ **Framework code that needs the database or a mock** (models, payments, AI request building): add a `qa_test()` to `qa/tests/cli_tests.php`, in the right `qa_section()`. The helpers of `qa/tests/lib.php` are `qa_test($name, $fn)`, `qa_assert($condition, $message)`, `qa_equals($expected, $actual)`, `qa_contains($needle, $haystack)` and `qa_not_contains()`.
    ```php
    qa_test('endWith() compares the whole needle', function () {
        qa_assert(endWith('caligrafy.php', '.php'));
        qa_assert(!endWith('caligrafy.php', 'xphp'));
    });
    ```
+ **Behavior seen by a browser or an API client** (status codes, headers, routes): add a `qa_test()` to `qa/tests/http_tests.php`. If it needs a route, add it to the routes that `qa/run.sh` inserts into `web.php`, and its controller to `qa/fixtures/application/controllers`.
+ **Files, Caligrafer commands, scaffolds, front-end libraries, the plugin**: add an `ok`/`ko` check to `qa/tests/tooling_tests.sh`. It runs on the host and works in `qa/tmp`.
+ **doctor**: add a variant `.env` and a check to `qa/tests/doctor_tests.sh`.

Make the check fail first (break the code it protects), then make it pass. Never print a secret in a check message.

## Release process

1. **Templates changed?** When you change a file of `framework/settings/application`, rebuild the template history and commit it:
    ```bash
    php qa/build-template-history.php
    ```
    `framework/settings/template-history.json` holds the sha256 of every version that each template file had in the git history (and the working tree). `php caligrafer.php upgrade` uses it to recognize untouched copies in projects that have no `application/.caligrafy-manifest.json`. The tooling phase fails when a current template version is missing from it.
2. **Wiki.** Update the wiki pages that the change affects, and the [release notes](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) when the change is visible to developers.
3. **Claude plugin.** The `caligrafy` skill bundles the wiki as mirrors in `claude-plugin/skills/caligrafy/reference/`. Refresh them from a clone of the wiki, then check:
    ```bash
    python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki            # rewrite the mirrors
    python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki --check    # exit 1 when a mirror is stale
    ```
    A new wiki page needs a line in the `PAGES` table of `sync-wiki.py` (and in the reference map of `skills/caligrafy/SKILL.md`). `api-reference.md` and `gotchas.md` are written by hand from the source: update them when `framework/` changes.
4. **Framework version.** For a release, set `Caligrafy\Framework::VERSION` in `framework/core/Framework.php` ([semantic versioning](https://semver.org): major when projects can break, minor for features, patch for fixes) and add its section at the top of `CHANGELOG.md`. The tooling phase fails when `php caligrafer.php version`, `CHANGELOG.md` and a release tag on the checked-out commit disagree, and the `VersionTest` unit test checks the format and the changelog.
5. **Plugin version.** Bump `version` in `claude-plugin/.claude-plugin/plugin.json` (minor for new framework features, patch for documentation) and add a line to the changelog of `claude-plugin/README.md`. Validate it:
    ```bash
    claude plugin validate ./claude-plugin --strict
    claude plugin validate . --strict             # the marketplace
    ```
6. **Run the whole suite** with the wiki clone: `QA_WIKI=../caligrafy-quill.wiki ./qa/run.sh`.
7. Open the pull request (see `CONTRIBUTING.md`).
8. **Tag the release** on `master` after the merge, and push the tag. Packagist picks it up; create the GitHub Release from the tag with the `CHANGELOG.md` section as its notes:
    ```bash
    git tag -a v2.0.0 -m "Caligrafy Quill 2.0.0"
    git push origin v2.0.0
    ```

>[Back to the Release notes](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026)
