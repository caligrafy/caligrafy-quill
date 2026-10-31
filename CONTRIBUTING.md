# Contributing to Caligrafy
We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## We Develop with Github
We use github to host code, to track issues and feature requests, as well as accept pull requests.

## We Use [Github Flow](https://guides.github.com/introduction/flow/index.html), So All Code Changes Happen Through Pull Requests
Pull requests are the best way to propose changes to the codebase (we use [Github Flow](https://guides.github.com/introduction/flow/index.html)). We actively welcome your pull requests:

1. Fork the repo and create your branch from `master`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes (see [Testing your changes](#testing-your-changes)).
5. Make sure your code lints.
6. Issue that pull request!

## Testing your changes
The framework has PHPUnit unit tests in `qa/unit` and a QA suite that runs the whole framework in an isolated Docker stack. From a clean checkout of the framework (no `application` folder, no `.env`):

```bash
composer install
vendor/bin/phpunit     # unit tests: no web server, database or .env needed
./qa/run.sh            # the whole suite: Docker, PHP with curl and Node.js
```

Run the QA suite before opening a pull request that touches `framework/`, `caligrafer.php`, `.htaccess` or the client scaffolds, and add a test for your change: a PHPUnit test in `qa/unit` for a core class, a check in `qa/tests` for behavior that needs the database, HTTP or the tooling. Run one QA stack at a time (`docker ps --filter name=quillqa` must be empty). `QA_NO_BUILD=1` reuses an existing image when the Docker image cannot be built on your network, and `QA_WIKI=<wiki clone>` also checks the wiki mirrors of the Claude plugin.

[Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy) explains every phase, the mocks of `qa/fixtures`, how to add a test and why the unit tests are not in `tests/` (every PHP file of `tests/` is loaded on each request).

## Release process
1. After changing a template of `framework/settings/application`, run `php qa/build-template-history.php` and commit `framework/settings/template-history.json` (`php caligrafer.php upgrade` uses it to recognize untouched files).
2. Update the [wiki](https://github.com/caligrafy/caligrafy-quill/wiki) pages that the change affects, and the [release notes](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) when developers need to know about it.
3. Refresh the wiki mirrors of the Claude plugin: `python3 claude-plugin/scripts/sync-wiki.py <wiki clone>` (a new wiki page also needs a line in its `PAGES` table). Update `claude-plugin/skills/caligrafy/reference/api-reference.md` and `gotchas.md` when `framework/` changes.
4. For a framework release, set `Caligrafy\Framework::VERSION` (`framework/core/Framework.php`, [semantic versioning](https://semver.org)) and add its section at the top of `CHANGELOG.md`: the QA suite checks that they agree. Bump `version` in `claude-plugin/.claude-plugin/plugin.json`, add a line to the changelog of `claude-plugin/README.md` and run `claude plugin validate ./claude-plugin --strict`.
5. Run `QA_WIKI=<wiki clone> ./qa/run.sh` and open the pull request.
6. After the merge, tag the release on `master` and push the tag: `git tag -a v<version> -m "Caligrafy Quill <version>"`, then `git push origin v<version>`. Packagist picks up the tag; create the GitHub Release from it with the changelog section as its notes.

The details are in the [release process](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy#release).

## Any contributions you make will be under the MIT Software License
In short, when you submit code changes, your submissions are understood to be under the same [MIT License](https://github.com/caligrafy/caligrafy-quill/blob/master/LICENSE) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using Github's [issues](https://github.com/caligrafy/caligrafy-quill/issues)
We use GitHub issues to track public bugs. Report a bug by [opening a new issue](); it's that easy!

## Write bug reports with detail, background, and sample code

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

People *love* thorough bug reports. I'm not even kidding.

## Use a Consistent Coding Style
I'm again borrowing these from [Facebook's Guidelines](https://github.com/facebook/draft-js/blob/a9316a723f9e918afde44dea68b5f9f39b7d9b00/CONTRIBUTING.md)

* 4-space tab indentations
* Follow the Caligrafy naming conventions

## License
By contributing, you agree that your contributions will be licensed under its MIT License.

## References
This document was inspired by the open-source contribution guidelines for [Facebook's Draft](https://github.com/facebook/draft-js/blob/a9316a723f9e918afde44dea68b5f9f39b7d9b00/CONTRIBUTING.md)
