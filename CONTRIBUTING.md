# Contributing

This SDK follows the KetaPay SDK standards (`documentation/openapi/public/SDK_STANDARDS.md`), shared by every KetaPay SDK. `ketapay-node` is the reference implementation: when in doubt, do what it does, in PHP idiom.

## Layout

```
src/
├── KetaPay.php           the client: one property per resource, config checks, VERSION
├── KetaPayObject.php     base of every API object: read-only fields, ArrayAccess, toArray(), getLastResponse()
├── Page.php              one page of a list; foreach walks every page by next_cursor
├── Collection.php        a list that is not paginated
├── ApiResponse.php       status, headers and request id behind an object
├── CancellationToken.php cancels a request and its retries
├── Webhook.php           signature verification (static, needs no key)
├── Errors/               KetaPayError and its subclasses, ErrorFactory (status and type to class)
├── Http/
│   ├── HttpClient.php    auth, idempotency, retries, timeouts, cancellation, logging, option checks
│   ├── Transport.php     the interface: CurlTransport (default), StreamTransport (no ext-curl), Psr18Transport
│   └── Logger.php        level filter over PSR-3, a callable or error_log
├── Resources/            one class per API resource, nested like the URL (Escrows has ->milestones)
├── Models/               GENERATED from spec/v1.yaml: one class per object the API returns
└── Enums/                GENERATED: constants for each string enum
init.php                  autoloader for projects without Composer (WordPress plugins)
tests/
├── Unit/                 Core/, Resources/, Webhooks/, the catalogue check
├── Contract/             real SDK calls against a Prism mock of the spec
├── E2E/                  the staging API, laid out by domain
└── Support/              MockTransport, TestCase helpers, a local `php -S` server for real HTTP
```

There are no runtime dependencies. PHP 8.1 is the floor: no PHP 8.2+ syntax or functions in `src/` (CI runs every test on 8.1, which catches them, as it caught `CURLOPT_XFERINFOFUNCTION`).

## The rule: the spec comes first

`spec/v1.yaml` is a generated snapshot of the public API spec. The source of truth is `documentation/openapi/public/` (split by domain); its bundled single file is `dist/v1.yaml`. The API, every SDK and the docs are built from it, so they never drift apart.

1. **Change the spec first**, in the documentation repo, in the same piece of work as the API change.
2. **Sync it here:** `npm run public:check` in the documentation repo, then `composer sync-spec` here (reads `../documentation/openapi/public`, or pass a path). Never edit `spec/` by hand: its header records a hash and CI rejects edits.
3. **Regenerate the models:** `composer generate`. Never edit `src/Models/` or `src/Enums/` by hand. An operation that returns a new inline object fails generation until you name its class in `$inlineResponses` in `scripts/generate.php`.
4. **Update the hand-written layer** (`src/Resources/`) for any new or changed endpoint. `tests/Unit/Resources/SpecCoverageTest.php` fails until every operation in the spec has an SDK method sending the spec's method and path: add the operation to its table, plus a call in `tests/Contract/ContractTest.php`.
5. **Run everything:** `composer format:check && composer lint && composer test && composer test:contract`.

CI enforces this on every PR:

| Check | Fails when |
| --- | --- |
| `check:spec` | `spec/` was edited by hand instead of synced |
| spec coverage test | an operation in the spec has no SDK method, or an SDK method has no operation |
| `check:drift` | `src/Models` or `src/Enums` doesn't match `spec/v1.yaml` |
| `test:contract` | the SDK sends a request the spec doesn't allow (Prism mock of the spec) |
| `lint` | PHPStan finds a problem: level max for `src/` and `tests/`, level 5 for `scripts/` |
| `test` | behaviour regressed, on any PHP from 8.1 to the newest |

## Pull requests and tests

Every change goes through a PR from a branch. CI runs on each PR and shows:

- **PR title is a conventional commit**: `feat:`, `fix:`, `chore:` and so on, because release notes are built from it.
- **One check per kind of test**, so a red ❌ tells you what broke: Spec, Format/lint/types/audit, Unit tests (PHP 8.1 to 8.5, with coverage), Contract tests, Package works on PHP 8.1 to 8.5, Test catalogue coverage.
- **A test report** on the run's Summary page: totals, coverage, and every test by name with ✅ or ❌. The HTML coverage report is attached to the run as `coverage-report`.

**Before every push**, a git hook runs formatting, static analysis and unit tests and refuses the push if any fail. `composer install` turns it on. Skip it only in an emergency with `git push --no-verify`; CI still runs everything.

Line coverage must stay at or above `MIN_LINES` in `scripts/check-coverage.php` (90%). Raise it when you add tests; never lower it to get a PR through. Coverage needs pcov or Xdebug.

Locally:

```sh
composer test                 # unit tests (about 15 seconds: some tests wait on real timeouts and backoff)
composer test:coverage        # with coverage (needs pcov or xdebug); open coverage/index.html
composer test:contract        # SDK requests against a Prism mock (needs Node for npx)
composer smoke:package        # the archive Packagist users get, installed and used in an empty project
```

Testing on PHP 8.1 without installing it: `docker run --rm -v "$PWD":/app -w /app php:8.1-cli vendor/bin/phpunit --testsuite unit`.

## The test catalogue

`spec/test-catalogue.md` is synced from `documentation/openapi/public/SDK_TEST_CATALOGUE.md` and lists every scenario every KetaPay SDK must test. Tag each test with its ID in its TestDox title, like `#[TestDox('[SEC-002] refuses a plain-http base URL except localhost')]`; package checks are tagged in the script that runs them. `tests/Unit/CatalogueCoverageTest.php` fails when a catalogue ID has no tagged test or a test carries an unknown ID.

What some rows mean in PHP:

- **SEC-006** (customer token required "at compile time"): the token methods take a required `$options` array whose PHPStan type requires `customerToken`, and they refuse at runtime before sending. The test asserts the runtime refusal and marks each call `@phpstan-ignore argument.type`, which fails `composer lint` if the type ever stops requiring the token.
- **RES-006** (cancel): PHP is synchronous, so cancellation is a `CancellationToken` checked before each attempt, during backoff, and mid-transfer by cURL's progress callback.
- **PKG-001**: Composer autoloading and `init.php`. **PKG-002**: PHPStan level max on a customer project. **PKG-005**: the stream transport (hosts without ext-curl) and one client reused for many requests (Octane, RoadRunner, FrankenPHP workers).

New scenario: add the row to the catalogue in the documentation repo first, `composer sync-spec` here, then write the tagged test.

## End-to-end tests

The real SDK against the staging API with sandbox keys, in `tests/E2E/` by domain (`Checkout/`, `Customers/`, `Escrows/`, `Disputes/`, `Payouts/`, `Webhooks/`, `Platform/`). `Support/E2ETestCase.php` has the shared setup: fresh people per test, a funded escrow, customer tokens, events for an escrow, and `eventually()` for effects that land a moment later (never a fixed sleep). Everything created is stamped with the run id (`metadata.e2e_run`).

Settings: `KETAPAY_E2E_BASE_URL` and `KETAPAY_E2E_SECRET_KEY`, plus optional ones listed in `Support/Env.php`. Run locally with `composer test:e2e`; without settings every test is skipped. The **E2E** workflow runs on every merge to `main`, nightly and by hand, reading the settings from Infisical (`ketapay-sdks`, staging: one project shared by every KetaPay SDK). **Publish to Packagist** runs it on the release tag and refuses a stable release unless it ran and passed.

## No breaking changes in v1

Adding endpoints, fields, enum values and optional parameters is fine. Removing or renaming anything, or making an optional field required, is not: mark it `deprecated` in the spec, keep it working for at least 6 months, and remove it only in v2.

## Versioning and publishing

Semantic versioning, automated by Release Please. Write commit messages and PR titles as conventional commits. Never edit `KetaPay::VERSION`, `version.txt` or `CHANGELOG.md` by hand.

1. **Release PR (automatic).** On every push to `main`, Release Please updates a PR with the next version (in `src/KetaPay.php`, marked `x-release-please-version`) and the changelog.
2. **Tag (when you merge that PR).** Merging tags the version (`v0.1.0-beta.1`) and creates a GitHub release.
3. **Publish (manual, dudox only).** Actions tab > **Publish to Packagist** > Run workflow. It checks the tag is on `main`, matches `KetaPay::VERSION` and isn't on Packagist yet; runs the whole CI suite and E2E on that exact tag; smoke-tests the archive; then asks Packagist to read the tag and waits until the version is listed.

Packagist reads versions straight from the repo's tags, so the package on Packagist must **not** have GitHub's auto-update hook: the publish workflow is the only thing that tells Packagist about a new tag. Packagist also needs the repo to be public (until then, use the repo as a Composer `vcs` repository).

The first release is pinned to `0.1.0-beta.1` by `release-as` in `release-please-config.json`: remove that line after it ships. When the API is stable, drop `prerelease` and `versioning` from the same file to cut `0.1.0`.

Secrets come from the Infisical project `ketapay-sdks`, shared by every KetaPay SDK: E2E settings in `staging`, `PACKAGIST_TOKEN` in `prod`. Switches (repo variables): `RELEASES_ENABLED` (Release Please) and `PACKAGIST_PUBLISH_ENABLED` (publishing). Check the token without publishing with the **Check release secrets** workflow.

## Style

PER-CS 2.0 via php-cs-fixer (`composer format`). Generated code is excluded. No runtime dependencies.
