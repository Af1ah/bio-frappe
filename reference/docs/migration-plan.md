# Bio-Notifier V1 and V2 migration plan

Status: approved direction; implementation plan, not a claim of completed delivery.
Decision date: 2026-09-15. Target: /home/aflah/projects/bio-notifier.

## Product direction

Extend the existing biometric attendance and notification application with leave and payroll. Retain its Filament interface, Laravel application, employee identities, and tenant databases. Replace eBio device transport through a separate Go service based on github.com/s0x90/zkteco-adms. Laravel remains the business API and system of record.

V1 uses direct HTTP device push to the production server because sites cannot currently install a relay. Browser access, application APIs, and downloads remain HTTPS. Initial payroll rules target Indian operations; user-facing navigation and copy remain neutral: Payroll, Salary Components, Currency, Holidays, and Policies. Do not add an “India payroll” label or imply international compliance.

Customer-reported ADMS compatibility includes K30, K30 Pro, K90, Orcus, and Face Mass using push API 1/2. This is evidence for selecting the transport, not proof that every enrollment, template, or access-control command works on every firmware.

## Read together

- [Device API, tenancy and operations](device-api-and-tenancy.md)
- [Attendance, leave and payroll](attendance-leave-and-payroll.md)
- [Mandatory coding rules](../../AGENTS.md)

## Verified starting point

The repository has Laravel 12, Filament 4 (lockfile v4.11.8), Dompdf, Sanctum, Stancl Tenancy, eBio SOAP jobs, tenant device/user resources, schedule resources, reports, and WhatsApp notifications. Tenant configuration uses separate databases, with cache/filesystem/queue bootstrappers.

Existing gaps to address deliberately:
- eBio webhook selects an organisation by the URL token, which is currently its identifier.
- Some webhook failures are acknowledged as success without a recoverable receipt.
- Punch upsert identity uses PIN and time and can overwrite device information.
- Attendance creation sends WhatsApp through a model callback.
- ReportService uses first/last punches and weekday assumptions.
- Livewire tenant fallback uses Referer; tenant selection needs independent membership authorization.
- Local documentation contains stale Filament versions and inconsistent database descriptions.
These are inspection findings, not a complete penetration or production infrastructure audit.

## Release map

| Release | Checkpoint | Deliverable | Exit condition |
| --- | --- | --- | --- |
| V1.1 | 1 | eBio transport migration to Go ADMS | Durable punches, safe routing, verified device actions and rollback |
| V1.2 | 2 | Attendance rules, leave, holidays, basic payroll | Approved calculation fixtures and reconciled payroll pilot |
| V2.1 | 3 | Advanced components, currency policies, deeper payroll | Versioned rules, controlled adjustments and accurate currency handling |
| V2.2 | 4 | Generalization, usability and operational improvements | Measured capacity, recovery rehearsal and reusable tenant onboarding |

“Biometric first” is a delivery gate: checkpoint 2 payroll must not use unverified device ingestion or provisional attendance reports.

## Checkpoint 1: migrate eBio to the Go adapter

### Discovery and compatibility inventory

Record each tenant, branch, device serial, model, firmware, push version, time zone, current eBio endpoint, sync watermark and required commands. Preserve device PINs and employee IDs. Record baseline raw-log counts and pending commands.

Build a parity matrix for sync devices, device status, sync users, push/delete user, enrollment, fingerprint/face template operations, access blocking, unlock, reboot, clear logs, and stamp resets. Classify each as verified, unsupported, or pending for each firmware. Unsupported operations must be disabled with a clear reason.

Pin the Go library to a reviewed commit/release and record Go toolchain requirements and license. Its current master source uses in-memory device and command state; supply durability as described in the architecture document. Do not present its example probe server as a production management API.

### Implement the transport boundary

1. Introduce a Laravel DeviceGateway contract and preserve an eBio adapter during transition.
2. Put Go service code under a clearly owned gateway directory; keep framework and payroll code in PHP.
3. Add authenticated internal management endpoints, a central device ownership registry, durable receipt/command storage, and delivery replay.
4. Refactor existing eBio jobs/resources to call the adapter contract; avoid rewriting working Filament resources.
5. Store raw requests before successful device acknowledgment; normalize from stored receipts.
6. Deliver to Laravel using tenant-bound credentials and idempotent batches.
7. Queue notifications after committed event processing; replay must not send duplicate alerts.
8. Preserve the old configuration until rollback no longer needs it.

### Pilot and cutover

Use captured fixtures first, then one explicitly selected device/tenant. Capture counts, unknown users, parse errors, receipt age, duplicate rate and command results. For hardware with one ADMS address, parallel validation means recorded replay or exports; do not assume it can push to both servers.

Switch devices in bounded batches. Backfill overlap around the cutover time and reconcile by device and date. Keep unresolved punches visible. Retire eBio only when required operations have verified replacements or an explicitly accepted limitation.

Rollback: stop new command dispatch, inventory commands already sent, restore the recorded device endpoint, replay durable receipts without duplicate attendance or notifications, and reconcile the transition interval. Never replay a destructive command automatically.

### Acceptance

- Known device pushes persist before acknowledgment; malformed/unknown requests have explicit outcomes.
- Repeated pushes, process termination, Laravel downtime and queue retries lose no acknowledged receipts in the tested failure model.
- Devices cannot select another tenant by changing a request field.
- Enrollment and other required commands have firmware-specific evidence.
- Existing employee PIN mappings, attendance history and working UI actions are preserved.
- Recovery from backup and receipt replay is demonstrated.

## Checkpoint 2: reliable attendance and basic payroll

Implement in order: employee work profile and salary history; effective-dated schedules; weekly offs/holidays; leave ledger/approval; attendance reconciliation; OT approval; attendance period lock; payroll draft/review/approval; private PDFs; payment recording.

Support monthly salaries first, fixed/percentage components with explicit bases, LOP, approved OT, manual adjustments with reasons, and configurable pay-period/rounding policies. Do not silently reuse calendar weekdays or first/last punch duration as salary logic.

Provide reusable tenant policy defaults and explicit branch/employee assignments. Salary, shift and holiday changes show their effective dates and affected unlocked periods. Locked results require revision or subsequent adjustments.

Automation creates drafts after attendance readiness checks. Payroll approval and marking paid require authorized users. Generate PDFs and notifications asynchronously from approved snapshots.

Acceptance: accountant/management-reviewed examples; cross-tenant authorization tests; one-page representative PDFs; repeated runs do not duplicate slips; two complete payroll cycles reconciled against approved expected totals. Horilla may supply source records, but its known incorrect historical totals must not become expected test results.

## Checkpoint 3: deeper payroll and currency configuration

Add component eligibility, effective-dated limits, advances/installments, arrears, off-cycle runs, salary revisions and policy simulation according to actual demand. Define country-specific statutory calculations only after applicability and effective dates are confirmed.

Initial currency model: INR default, ISO currency code, formatting locale, precision and rounding policy, one currency per salary agreement/pay run. Store the currency with every amount snapshot from V1. V2 supports configurable tenant currency without implying that changing a symbol converts values.

Exchange conversion, multiple payout currencies and additional jurisdictions remain separate requirements with explicit rate source, rate date, rounding and approval rules. Do not add a general scripting engine or arbitrary PHP expressions for payroll formulas.

Acceptance: historic results remain reproducible after policy changes; every amount explains its basis; caps and rounding pass boundary tests; no mixed-currency summation.

## Checkpoint 4: generalize and improve

Use actual customer requests to add policy templates, better onboarding, capability-aware device screens, streamlined bulk actions, reporting and integration exports. Measure query cost and queue delay before adding caches or service splits.

Add tenant migration orchestration, resumable imports, backup restore tooling, operational dashboards and documented upgrade procedures. Preserve simple workflows for small customers. Recruitment, performance, asset management and other unrelated HR modules are outside this plan.

## Implementation work packages and completion evidence

Each work package records owner, affected files, migration/reversal strategy, fixtures, CLI commands, acceptance results and operational changes. Completion requires behavior evidence, not only a green syntax check or an HTTP 200 response.

Use feature flags per tenant/device for adapter selection and payroll rollout. Separate additive schema rollout, backfill, application switchover and removal. Do not run broad rewrites or remove old columns in the same step as first cutover.

Do not set a fixed delivery estimate before the capability inventory and pilot. Track remaining work by checkpoint exit criteria.

## Sources and verification boundaries

Reviewed local source: composer.json/composer.lock; config/tenancy.php; app/Services/EbioSoapService.php; app/Http/Controllers/EbioWebhookController.php; app/Jobs/ProcessEbioWebhookJob.php; app/Services/Attendance/ReportService.php; app/Models/AttendanceLog.php; tenant middleware and Filament resources.

External references:
- https://github.com/s0x90/zkteco-adms
- https://github.com/s0x90/zkteco-adms/blob/master/adms.go
- https://github.com/s0x90/zkteco-adms/blob/master/handler.go
- https://filamentphp.com/docs/4.x/resources/overview
- https://laravel.com/docs/12.x/sanctum
- https://tenancyforlaravel.com/docs/v3/queues/

Production host capacity, ingress configuration and actual firmware command behavior were not inspected for this planning task.
