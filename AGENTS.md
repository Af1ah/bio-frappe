# Bio-Notifier coding and agent rules

These rules apply to work in this repository. Follow the user's current task and preserve unrelated changes. Read [the migration plan](reference/docs/migration-plan.md) and the relevant detailed document before migration/payroll work.

## Scope and delivery order

- Deliver biometric ingestion and device management reliability before payroll integration.
- Keep existing employee identities, PIN mappings, tenant databases and useful Filament resources.
- Work checkpoint by checkpoint with concrete acceptance evidence. Make only changes needed for the task.
- Do not perform framework upgrades, rename unrelated code or replace working modules as incidental cleanup.
- The approved new transport is the Go ADMS adapter. Existing eBio remains supported during staged cutover; avoid extending legacy SOAP except for required compatibility.
- Current baseline is Laravel 12 and Filament 4. Verify exact versions from composer.lock; older references to Filament 3 are historical.
- Initial payroll targets Indian requirements internally. Use neutral UI labels and configurable policies; never claim wider country support from currency formatting alone.

## Centralized styling and UI

- Extend the existing Filament theme, shared Blade components and design tokens for color, spacing, typography and states.
- Prefer standard Filament forms, tables, actions, filters and validation. Avoid duplicate CSS, inline styling scattered across pages and custom widgets that recreate existing components.
- Share PDF layout and formatting utilities; use local fonts/assets and private artifact storage.
- Keep labels concise, forms accessible and validation actionable. Use capability-aware device actions.
- Do not expose implementation details or country scope labels in normal product flows unless the user needs them.

## Coding conventions and modular ownership

- Match established PHP/Go conventions and formatter configuration. Use typed inputs/returns and clear domain names.
- Keep controllers, jobs and Filament actions thin. Centralize calculation, authorization and integration logic behind small services.
- Separate device protocol, attendance, leave, payroll, reporting and notification responsibilities.
- Reuse existing interfaces/modules before creating new abstractions. Add shared code only when it resolves actual duplication or a clear boundary.
- Validate external inputs server-side. Use policies for membership/role/record access, not only hidden UI actions.
- Use decimal arithmetic for payroll and integer duration units. Store effective dates and policy versions.
- Keep raw punches immutable. Record corrections and inferred events explicitly.
- Maintain understandable exceptions and structured logs; do not swallow failures and claim success.

## Package reuse and CLI first

- Inspect composer.lock, vendor documentation, installed commands and existing resources first.
- Reuse Dompdf, Laravel sessions/auth, Sanctum, Stancl Tenancy, queues, scheduler, notifications and mail.
- Use Artisan/Filament generators for resources, pages, models, migrations, policies and jobs. Inspect command help for installed versions before choosing flags/panel IDs.
- Review generated files; generators do not implement business rules or tenant authorization automatically.
- Use Laravel Pint for applicable PHP formatting and gofmt for Go. Run focused tests appropriate to behavior.
- Add a dependency only for a documented unmet requirement after checking maintenance, license, compatibility and tenant behavior. Do not hand-build a replacement for a suitable installed feature.
- Keep framework major-version upgrades separate from device/payroll migration.

## Tenant isolation and integration

- Resolve tenant identity from trusted routing/credentials and central ownership records, then authorize membership.
- Do not trust Referer, user-supplied tenant IDs or serial numbers as authentication.
- Jobs carry tenant UUIDs and primitive IDs; initialize tenancy before loading tenant records and end context in finally blocks.
- Scope cache, locks, deduplication, exports, private PDFs and notification routing by tenant.
- Browser sessions belong to Laravel. Go serves device transport and an authenticated internal management API.
- V1 device HTTP is an explicitly chosen transport limitation. Keep the device ingress separate from HTTPS user/API routes.
- Persist accepted device receipts and command state durably. A library callback or HTTP 200 is not proof of downstream completion.
- Never retry uncertain destructive device operations automatically.

## Performance and reliability

- Prefer indexed queries, pagination, bounded batches and precomputed attendance summaries.
- Use database constraints and idempotency keys for duplicate prevention.
- Keep transactions short and do not hold them across external HTTP calls. Use durable outboxes where necessary.
- Move PDF generation, notifications and expensive recomputation to independent queues.
- Use bounded retries, backoff, expiry and visible failed-job recovery. Measure before adding caches or distributed components.
- Do not promise failure-proof operation; state tested failure behavior and recovery limits.

## Data changes and verification

- Use reviewed migrations/import services, dry-run previews, backups and reconciliation.
- Avoid ad hoc database edits that bypass domain logic.
- Freeze approved payroll snapshots; corrections use revisions or adjustments.
- Test tenant boundaries and payroll arithmetic with independent expected values and real failure scenarios.
- Verify PDFs visually and reconcile totals. Do not report a test pass from discovery output or an interrupted command.
- Record what changed, what was tested and any remaining limitation.
- Do not contact devices, change production routing, migrate production data or send employee messages unless the current task authorizes it.

## Agent skills and tools

- Read applicable repository instructions first. Use an explicitly named skill; otherwise select skills only when they materially help the task.
- Read the skill's SKILL.md before applying it, announce its use, and follow required verification.
- Use installed Laravel/Filament/package documentation for version-sensitive work; consult official sources when needed.
- A skill must not expand task scope or override user decisions. Avoid unrelated Frappe, website-building or document workflows.
- Prefer targeted searches and existing CLI tools. Do not open browsers or perform live UI checks unless requested.
- Never put credentials, biometric templates, private payroll data or access tokens in examples, logs, documentation or Git.
