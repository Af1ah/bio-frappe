# Device API, tenancy and operational design

Status: implementation specification for checkpoints 1 and 4.
Read [the release plan](migration-plan.md) and [coding rules](../../AGENTS.md).

## Ownership of each layer

| Layer | Owns | Must delegate |
| --- | --- | --- |
| Device | Local punches and firmware command execution | Employee/payroll truth |
| HTTP ingress | Device-only routing, limits, source restrictions | Tenant authorization decisions |
| Go ADMS gateway | Protocol parsing, receipts, device delivery, command acknowledgments | Leave, salary, browser sessions |
| Laravel | Tenant ownership, employee mapping, permissions, business APIs and policies | Firmware protocol details |
| Workers | Attendance recomputation, notifications, payslip generation | Browser request lifecycle |
| Tenant database | Employee, attendance, leave and payroll records | Global device ownership registry |

One deployed PHP application serves centrally managed tenant databases. Keep this existing arrangement; introducing Go does not require replacing Laravel APIs or consolidating all tenant data into one database.

## Direct HTTP push in V1

Approved topology:

Device -> public device-only HTTP listener -> private Go listener -> authenticated HTTPS/private-network Laravel API.

Browser -> HTTPS application domain -> Laravel/Filament.

Provide a dedicated device hostname/port on the current production server. Keep /iclock/cdata, /iclock/getrequest and /iclock/devicecmd paths intact. Accept registry endpoints and .aspx aliases only where fixtures demonstrate that the firmware requires them. Verify hostname, port, proxy body handling, query strings, firmware timeouts and retry behavior during the pilot.

Do not redirect HTTP-only devices to HTTPS. Do not expose the Filament panel, login, employee APIs, Go management API or PDF storage on the device listener. Keep inspection/debug endpoints disabled. Use a default-deny virtual host and only route required device paths. Choose exact DNS and port after inspecting current production routing; do not assume port 80 is free.

HTTP leaves the device-to-server segment unencrypted. TLS on a later hop does not fix that segment. Serial numbers identify devices but are not cryptographic authentication. Register devices administratively, bind ownership, apply source restrictions where stable, and use supported device credentials when verified. Unknown devices must not auto-create tenants or gain command access. Dynamic customer IPs need a documented registration/revalidation procedure, not an assumption of fixed addresses.

Do not log biometric templates, passwords or full token-bearing URLs. Template transfer over HTTP has the same transport limitation; evaluate each feature and retain only the data needed. Later TLS-capable firmware or site VPNs can upgrade the first hop without changing the business API.

## ADMS library boundary

Use the selected Go library for parsing and command encoding. Pin a tested commit. The inspected source uses in-memory maps for devices/commands and asynchronous callbacks. A callback enqueue does not mean the business event is durably stored. Restarts can remove memory-only command state.

Production receipt path:
1. Validate size, method, endpoint and registered device binding.
2. Persist the raw bounded request, device binding version, received time, digest and routing metadata to durable gateway storage.
3. Only then allow a successful device acknowledgment.
4. Parse from the persisted receipt and store event records plus an outbox entry transactionally.
5. Deliver batches to Laravel; mark them delivered only after Laravel confirms durable acceptance.
6. Retry from durable state. Quarantine invalid rows with a reason and replay controls.

A Go wrapper can persist requests before invoking the library, but it must not depend solely on asynchronous callbacks for durable business processing. Verify parser access and response semantics in the pinned version. If safe replay/callback integration requires a small upstream change, document and test that patch. Never use a wrapper that acknowledges before storage or fabricates successful command results.

Return protocol-compatible non-success on storage failure; do not claim data accepted. Once raw storage is committed, a downstream outage can be absorbed locally. Batch parse failures retain original content and line offsets. Alert if durable storage is near capacity and stop accepting beyond bounded limits.

## Data contracts

Central registry: immutable device UUID, tenant UUID, serial, protocol, branch reference, timezone, capability profile, credential reference, ownership version and active state. Enforce unambiguous ownership; serial conflicts are quarantined. A serial alone must not allow claiming or transferring a device.

Proposed normalized event:
- schema_version, event_id, receipt_id, source_sequence/line_index
- trusted tenant_id, device_id, binding_version, original serial and device PIN
- original timestamp text, timezone, UTC timestamp, received_at
- original punch/verification code and normalized classification
- payload digest, parser version, provenance and processing state

Use immutable application employee IDs and an effective-dated device-user mapping. Preserve leading zeros in PINs. The same PIN can exist in different tenants/devices.

Deduplication has two levels: transport receipt identity for retry and business event identity using device, source identifier where available, timestamp, PIN and punch/verification details. Define collision behavior for indistinguishable same-second punches. Do not deduplicate by PIN/time alone across devices. Preserve original receipts even when normalized events are duplicates.

## Proposed API surface

These are new contracts to implement, not existing library endpoints.

| Direction | Endpoint | Behavior |
| --- | --- | --- |
| Laravel -> Go | POST /internal/v1/devices | Register an authorized device binding idempotently |
| Laravel -> Go | POST /internal/v1/devices/{id}/commands | Persist command intent; return 202 and command UUID |
| Laravel -> Go | GET /internal/v1/commands/{id} | Return delivery/execution state |
| Go -> Laravel | POST /api/internal/v1/device-events | Tenant-bound batch acceptance, including dedupe results |
| Go -> Laravel | POST /api/internal/v1/command-results | Persist execution result using immutable command identity |
| Operations | /health/live and /health/ready | Process liveness versus durable storage readiness |

Use versioned envelopes, correlation IDs, bounded batches, validation errors and explicit retryable responses. Success means durable acceptance, not attendance approval. Authenticate internal requests with rotated scoped service credentials, TLS when crossing hosts, and optional request signatures with timestamp/replay checks. Reuse Sanctum for Laravel service API authentication where appropriate; Go needs its own management-endpoint verification.

## Command lifecycle and capability parity

States: requested -> authorized -> queued -> delivered -> acknowledged/succeeded/failed; also expired, cancelled and outcome_unknown. An HTTP 200 or empty poll is not execution success.

Keep durable command UUID, protocol command ID, device binding, operation, initiator, request digest, timestamps, expiry and result. Protocol IDs must not be accidentally reused across gateway restarts; confirm the library's allocation behavior and provide a persistent mapping/allocator if required.

A device may execute a command while its acknowledgment is lost. Read back state for idempotent configuration changes; quarantine uncertain destructive commands. Do not automatically retry clear logs, delete users, enrollments or door operations without an operation-specific rule. Serial command delivery per device and bounded queues prevent competing commands.

Expose capability checks in existing Filament actions. Device firmware testing is required for enrollment, biometric templates, access control and command parity even when attendance push already works.

## Tenant isolation through the entire path

Retain Stancl database tenancy. Central storage holds tenant registry and minimal routing metadata. Go uses its dedicated ingress store and cannot freely query payroll databases.

Resolve device ownership through the central registry and authorized credentials. Reject request tenant IDs that disagree with registry/credential binding. Bind receipts to ownership version at receipt time; later device reassignment must not reroute old data into a new tenant.

For browser/Livewire requests, initialize tenant context from trusted routing and verify authenticated membership/roles. Referer is not an authorization boundary. Reject missing or conflicting context. Keep central administrator identity distinct from tenant employee identity.

Jobs carry explicit tenant UUID and primitive record identifiers. Initialize tenancy before loading tenant models and end tenancy in finally blocks. Do not reuse tenant connections, tokens or cached models across jobs. Verify Stancl queue integration against existing queue settings.

Scope cache keys, locks, idempotency keys, notification routing, PDF paths and exports by tenant. Authorize private downloads against membership and record ownership. Signed URLs need expiry and access policy; a guessed filename must never expose salary records.

Separate database users/grants per tenant where operationally feasible. Database-per-tenant improves separation but does not prevent application routing mistakes or overly privileged shared credentials. Test tenant A/B using identical PINs and local IDs, failed jobs, alternating Livewire requests, exports and guessed URLs.

## Capacity and failure recovery

Start with one gateway owner for device command state and independent Laravel worker queues. The library's memory state makes naive round-robin gateway replicas unsafe. Scale command ownership by stable device partition with a fenced leader/lease, or implement shared persistent command coordination before adding active replicas.

Use indexes on device/time, employee/date, pending receipt age and processing state. Process bounded batches; paginate UI tables and precompute daily attendance summaries. Avoid repeatedly loading all raw punches for every employee/day report. Query salary periods from approved snapshots.

Separate ingestion, attendance, notification and PDF queues. Retry with exponential backoff and jitter, capped attempts and a visible dead-letter workflow. A slow WhatsApp provider must not delay punch commits. Publish jobs after commit using an outbox where delivery must survive process failure.

| Failure | Required outcome |
| --- | --- |
| Go terminates after device acknowledgment | Durable raw receipt replays; no acknowledged data lost on surviving storage |
| Laravel is unavailable | Outbox accumulates within limits and resumes |
| Duplicate push or delivery retry | One logical event, with receipt provenance |
| Tenant unknown or conflicting | Quarantine/reject; never choose a default tenant |
| Device offline after command dispatch | Expiry/outcome_unknown and operator visibility |
| PDF/mail provider fails | Salary snapshot retained; job retry without duplicate payment |
| Host/disk loss | Restore backups and reconcile devices; local persistence alone is insufficient |
| Holiday/salary policy edited | Recompute unlocked periods only; locked results retain policy versions |

No system is failure-proof. Define availability and latency objectives from measured pilot capacity. Baseline metrics: receipt acknowledgment p95/p99, oldest outbox item, parse failures, missing device heartbeat, duplicate/conflict count, unknown command outcomes, queue age, tenant migration failures and backup age. Alert on sustained thresholds.

Back up central registry, gateway receipts/commands, tenant databases, encrypted private files and necessary encryption keys. Test full restore plus one-tenant restore, preserving event identities. Document recovery time and possible data-loss windows for host failure; do not claim zero loss without replicated durable storage and tested recovery.

## Deployment and verification

Run Go under a supervised service/container with graceful drain, bounded shutdown and durable volumes. Build PHP assets once during release and migrate through controlled jobs. Avoid destructive static rebuilding at each ordinary restart. Inspect existing production server before allocating ports, workers or memory.

Required fixtures: firmware request variants, retransmission, malformed body, wrong clock/timezone, unknown serial/PIN, duplicate serial claim, API outage, store full, worker crash, tenant switching and uncertain command acknowledgment. Replay must suppress historical notifications by default.

References: selected Go repository and adms.go/handler.go; Laravel 12 queues and Sanctum documentation; Stancl v3 queue documentation. Pin dependency versions and verify commands against installed help during implementation.
