# Support and release policy

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Support-and-Release-Policy
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Which versions of Caligrafy Quill get fixes, and for how long. Since 2.26.0.

## Versions

Caligrafy Quill follows [semantic versioning](https://semver.org): a **major** version (3.0) can break projects, a
**minor** version (2.26) adds features, a **patch** version (2.26.1) only fixes. Each release is a git tag `v<version>`
on [GitHub](https://github.com/caligrafy/caligrafy-quill/releases) and
[Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill), and its changes are in the
[changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## What is supported

| Release | Bug fixes | Security fixes |
|---|---|---|
| The latest minor (today 2.26) | yes | yes |
| The minor before it | no | for 12 months after the next minor shipped |
| Older minors | no | no |

- **Security fixes** are released as patch versions of each supported minor (2.25.x while it is supported).
- **Bug fixes** go to the latest minor only: move up with `php caligrafer.php update`, which updates the framework,
  the plugin and your templates and keeps your code (see [updating Caligrafy](Updating-Caligrafy)).
- **There is no long-term support (LTS) release yet.** It will be announced here when there is one.

## Reporting a vulnerability

Do not open a public issue: use GitHub's
[private vulnerability reporting](https://github.com/caligrafy/caligrafy-quill/security/advisories/new) for the
repository. You get an answer, and the fix ships as a patch release of every supported minor.

## Upgrading

Each minor release has its release notes (linked from the sidebar), with what changed and what to check after
`php caligrafer.php update`; `php caligrafer.php doctor` reports what a project should change.
