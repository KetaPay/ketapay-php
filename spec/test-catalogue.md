<!-- GENERATED: do not edit. Copied from documentation/openapi/public by `composer sync-spec`. -->
<!-- sha256: baaee54ae4aead40759d1a3b0090c246af39856f3ffd28d5c4b80d8d44984ac7 -->

# KetaPay SDK test catalogue

Every scenario below must be tested in **every** official KetaPay SDK. This file is strict: it is synced into each SDK with the spec, and each SDK has a catalogue coverage test that **fails CI** when any ID here has no test tagged with it, or a test carries an ID that isn't here.

## Rules

1. **Tag tests with the ID** in the test name, in square brackets: `it('[SEC-002] refuses a plain-http base URL', ...)`. A test may carry several IDs. Package-level checks are tagged in the script that performs them.
2. **One row, one behaviour.** The "Must hold" column is the pass condition. Test exactly that, in the layer given.
3. **Adding a scenario:** add a row with the next free ID in its section, then implement it in every SDK (one PR per SDK). Until each SDK has it, that SDK's CI stays red. That is the point.
4. **Never delete or renumber a row.** If a behaviour is retired, mark it `retired` in the Must hold column and keep its tests passing or remove them in the same change. IDs are never reused.
5. **E2E rows** run against the KetaPay staging API with sandbox keys (`sk_test_`). They are skipped, not deleted, when the environment isn't configured, and must pass before a stable (non-beta) release.
6. Layers: **unit** (fake HTTP, no network), **contract** (real SDK calls against a Prism mock of the spec), **package** (the packed artifact installed and run as a customer would), **e2e** (the real staging API).

## AUTH: keys and environments

| ID | Layer | Must hold |
| --- | --- | --- |
| AUTH-001 | unit | `sk_test_` and `sk_live_` keys are accepted; `livemode` is false and true respectively |
| AUTH-002 | unit | Publishable keys (`pk_`) are refused with a message pointing at the checkout SDK |
| AUTH-003 | unit | Missing, empty or malformed keys are refused before any request |
| AUTH-004 | unit | Every request sends `Authorization: Bearer <key>` |
| AUTH-005 | unit | Options can follow the key (`new KetaPay(key, options)`) or sit in one config object with the key; either way every option is applied. A key given both as the first argument and in the options is refused before any request |

## SEC: security

| ID | Layer | Must hold |
| --- | --- | --- |
| SEC-001 | unit | Default headers and per-call headers can never replace `Authorization`, `Idempotency-Key` handling or the customer token header |
| SEC-002 | unit | A plain-http base URL is refused, except `localhost` and `127.0.0.1` for local mocks |
| SEC-003 | unit | Ids are encoded as single path segments: an id like `../webhook_endpoints` cannot change the route |
| SEC-004 | unit | Logs at every level never contain the secret key, customer tokens, webhook secrets or request bodies |
| SEC-005 | unit | Test helpers refuse a live key before sending anything (`test_mode_only`) |
| SEC-006 | unit | Calls that move money require a customer token in their signature, where the language can enforce it (compile-time check) |
| SEC-007 | unit | Uploaded file bytes are sent as multipart form data, never inlined into JSON |

## WH: webhook signatures

| ID | Layer | Must hold |
| --- | --- | --- |
| WH-001 | unit | A valid signature returns the parsed event, from a string or raw bytes |
| WH-002 | unit | A changed body is rejected (`signature_mismatch`) |
| WH-003 | unit | The wrong secret is rejected (`signature_mismatch`) |
| WH-004 | unit | A timestamp older than 300 seconds is rejected (`timestamp_outside_tolerance`); a custom tolerance is honoured |
| WH-005 | unit | A timestamp more than 300 seconds in the future is rejected |
| WH-006 | unit | A missing or malformed header, or a non-hex signature, is rejected with its own code |
| WH-007 | unit | During secret rotation, any `v1` value in the header and any of several secrets on our side is accepted |
| WH-008 | unit | A correctly signed body that isn't JSON is rejected (`invalid_payload`) |
| WH-009 | unit | The test-signature helper produces headers that verify |

## REQ: building requests

| ID | Layer | Must hold |
| --- | --- | --- |
| REQ-001 | unit | Every `POST` sends an `Idempotency-Key`, generated when none is given; `GET` and `DELETE` send none |
| REQ-002 | unit | A caller-supplied idempotency key is sent unchanged |
| REQ-003 | unit | `User-Agent` names the SDK, its version and the runtime |
| REQ-004 | unit | Query parameters are encoded; empty values are left out |
| REQ-005 | unit | A custom base URL is honoured (trailing slash allowed) |
| REQ-006 | unit | The customer token is sent in `KetaPay-Customer-Token` when given |
| REQ-007 | unit | One-time codes and release codes travel in the JSON body |
| REQ-008 | unit | Creating a webhook endpoint with only a URL is allowed and sends no `enabled_events` (the API defaults it to every event) |

## ERR: errors and responses

| ID | Layer | Must hold |
| --- | --- | --- |
| ERR-001 | unit | Each error type and status maps to its error class: 400 invalid request, 401 authentication, 403 permission, 404 not found, 409 conflict, 409 idempotency, 429 rate limit, 5xx API |
| ERR-002 | unit | Not-found and conflict errors are also catchable as invalid-request errors |
| ERR-003 | unit | Errors carry `code`, `param`, `statusCode` and `requestId` from the response |
| ERR-004 | unit | A successful response that isn't JSON raises an API error, not a crash |
| ERR-005 | unit | The raw response and request id are available on success |

## RES: resilience

| ID | Layer | Must hold |
| --- | --- | --- |
| RES-001 | unit | Network errors, 429 and 5xx are retried up to the limit, then raise the matching error |
| RES-002 | unit | Retries reuse the same idempotency key |
| RES-003 | unit | Other 4xx responses are never retried |
| RES-004 | unit | On 429 the wait honours `X-RateLimit-Reset` |
| RES-005 | unit | A request that exceeds its timeout raises a connection error with code `timeout` |
| RES-006 | unit | Aborting a request (or one already aborted) raises an aborted error and stops all retries, including during the backoff wait |
| RES-007 | unit | Each retry is logged at warn level |

## PAG: lists

| ID | Layer | Must hold |
| --- | --- | --- |
| PAG-001 | unit | Awaiting a list returns the first page |
| PAG-002 | unit | Iterating a list walks every page by `next_cursor`, keeping the caller's filters |
| PAG-003 | unit | The first page is available with its raw response |

## SPEC: the SDK matches the spec

| ID | Layer | Must hold |
| --- | --- | --- |
| SPEC-001 | unit | Every operation in the spec has an SDK method, and every SDK method has an operation |
| SPEC-002 | unit | Each SDK method sends the HTTP method and path the spec defines |
| SPEC-003 | contract | SDK requests for every resource are accepted by a mock that validates against the spec |
| SPEC-004 | package | The spec snapshot has not been edited by hand (hash check) |
| SPEC-005 | package | The generated models match the spec snapshot (drift check) |
| SPEC-006 | unit | Every ID in this catalogue has a tagged test, and no test carries an unknown ID |

## PKG: the published package

| ID | Layer | Must hold |
| --- | --- | --- |
| PKG-001 | package | The packed artifact installs into an empty project and works through every supported module system (Node: ESM and CommonJS) |
| PKG-002 | package | The public types compile in a customer project (Node: TypeScript 5.0 and current, `nodenext` and `bundler` resolution) |
| PKG-003 | package | The artifact contains only built code, licence and readme |
| PKG-004 | package | It works on the oldest supported runtime (Node 18, PHP 8.1) and the newest |
| PKG-005 | package | It works on edge and alternative runtimes where the language has them (Node: Deno) |
| PKG-006 | package | The version reported by the SDK equals the published version |
| PKG-007 | package | No required runtime dependencies (or only the ones the standard allows) |

## OPS: operations and observability

| ID | Layer | Must hold |
| --- | --- | --- |
| OPS-001 | unit | Debug logging records method, path, status, request id and timing for every request |
| OPS-002 | unit | The default log level logs nothing on success |
| OPS-003 | unit | Defaults: 2 retries, 30-second timeout, production base URL |

## E2E: end to end against staging (sandbox keys)

| ID | Layer | Must hold |
| --- | --- | --- |
| E2E-001 | e2e | A checkout session is created with milestones, images and both contacts; it has a hosted `url` and correct `fees` |
| E2E-002 | e2e | A customer session sends a code; verifying it (sandbox code `123456`) returns a customer token |
| E2E-003 | e2e | Bank transfer details are returned for the buyer's token, and refused for someone else's |
| E2E-004 | e2e | Paying with the test helper funds the escrow, completes the session and records `escrow.funded` |
| E2E-005 | e2e | Seller delivers, buyer releases: the milestone is `released` with a payout, and `milestone.delivered` and `milestone.released` events exist |
| E2E-006 | e2e | In-person release with the buyer's release code works; a wrong code is refused |
| E2E-007 | e2e | Auto-release (simulated) releases a delivered milestone |
| E2E-008 | e2e | The buyer cancels before delivery: the escrow is `refunded` and `escrow.refunded` exists |
| E2E-009 | e2e | Dispute opened, evidence uploaded with a file, seller concedes: dispute `resolved`, money refunded |
| E2E-010 | e2e | A dispute KetaPay resolves (simulated partial refund) splits the money and records `dispute.resolved` |
| E2E-011 | e2e | A lost chargeback (simulated) closes with `lost` and records `chargeback.closed` |
| E2E-012 | e2e | A failed payout (simulated) records `payout.failed` and the money stays in the seller's balance |
| E2E-013 | e2e | A failed card refund (simulated) moves the refund to the buyer's balance and records `refund.failed` |
| E2E-014 | e2e | A payout account is added and confirmed with its code (sandbox code `123456`) |
| E2E-015 | e2e | Same idempotency key and body returns the same object; same key with a different body returns `idempotency_key_reused` |
| E2E-016 | e2e | Responses carry `KetaPay-Request-Id` and `X-RateLimit-*` headers |
| E2E-017 | e2e | A webhook endpoint is created (secret shown once), a test event is delivered, and after rotation both signatures verify |
| E2E-018 | e2e | The events list contains the events produced by the flows above |
| E2E-019 | e2e | The wrong party is refused: the seller can't release, the buyer can't deliver (`customer_not_allowed`) |
| E2E-020 | e2e | One organisation's key cannot read another organisation's escrow (404) |
| E2E-021 | e2e | An open dispute blocks the normal release (`dispute_open`) |
| E2E-022 | e2e | Invalid input is refused with the right `param` (party without contact, milestones not adding up, description too short) |
| E2E-023 | e2e | Lists paginate across more than one page |
| E2E-024 | e2e | A live key cannot reach test helpers (`test_mode_only`) |
