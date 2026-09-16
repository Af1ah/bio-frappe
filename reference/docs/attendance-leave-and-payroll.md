# Attendance, leave and payroll specification

Status: implementation specification for checkpoints 2 and 3.
Read [the migration plan](migration-plan.md), [device/tenant architecture](device-api-and-tenancy.md), and [coding rules](../../AGENTS.md).

## Principles

Payroll consumes reconciled biometric attendance. A raw punch is evidence, not a payable day. Keep raw receipts immutable, attendance decisions explainable, and approved payroll reproducible. Extend existing Laravel models/resources where appropriate; separate payroll services from Filament actions and device transport.

Start with Indian operational requirements and INR; keep user-facing terminology neutral. Currency and jurisdiction are independent settings. A configurable symbol does not provide statutory compliance for another country.

## Domain model

| Domain | Proposed records and purpose |
| --- | --- |
| Employee | Existing User plus work profile, employment dates/type and device PIN mappings |
| Salary | Effective-dated salary agreements, currency, components and assignment history |
| Scheduling | Existing Schedule plus validated shift rules, assignments and overrides |
| Calendar | Weekly-off policies, holiday calendars, date/branch applicability |
| Leave | Leave type, policy assignment, ledger entries, requests and approvals |
| Attendance | Immutable events, work sessions, daily results, exceptions and corrections |
| OT | Eligible minutes, approved minutes, rate policy and approval record |
| Payroll | Pay period, run/revision, input snapshot, line items, approvals and payment record |
| Documents | Private payslip artifact, snapshot checksum, generation/delivery state |

Do not require every employee to have a login. Keep immutable employee identity separate from device PIN and user-editable display name. Use additive migrations and preserve existing IDs.

## Shifts and attendance

Shift rules include local start/end time, timezone, overnight boundary, minimum paid working time, unpaid/paid breaks, grace period, rounding and maximum plausible session duration. Assign effective dates with deterministic precedence: employee override, assigned group/branch, then organisation default. Block ambiguous overlapping assignments.

Pair sessions using ordered events and recorded punch semantics. Unknown direction or odd punch count creates an exception; never silently create a multi-day shift. Breaks and multiple sessions must be supported. Attribute overnight work according to the configured workday policy.

Automatic checkout creates a marked inferred event with source and rule version. It must not overwrite a raw device punch. Payroll eligibility for inferred hours is configurable and visible to reviewers.

Daily states distinguish present, absent, paid leave, unpaid leave, partial leave, weekly off, holiday and unresolved. Preserve partial days in exact units. Holiday/leave overlaps need one payable classification; record the precedence decision.

ReportService currently uses weekday assumptions and first/last-punch fallbacks. Replace its calculation dependency with a shared attendance service and keep the reporting UI as a consumer. Version summaries by input digest and policy version.

## Holidays and weekly offs

Provide organisation calendars with branch overrides and effective dates. Support recurring weekly off, dated public/company holiday, optional holiday eligibility and explicitly selected working-day exceptions. Holiday creation previews affected dates and warns on unusually long ranges.

Configure holiday work as separate entitlements: ordinary pay treatment, additional pay/multiplier, minimum eligible work and compensatory leave. Weekly-off work and regular overtime use distinct rules. Prevent the same minutes from entering both normal OT and holiday OT unless an approved policy explicitly allows it.

Sunday off is a calendar rule. A 09:30-18:30 shift spans nine hours; an eight-hour paid day requires an explicit break/payment rule. Do not infer that all elapsed hours are payable.

## Leave workflow

Draft -> submitted -> approved/rejected/cancelled. Validate dates, overlap, eligibility, balances and approver membership. Maintain a ledger for grants, accruals, usage, expiry, carry-forward and corrections. Never directly overwrite a balance without a traceable adjustment.

V1: paid/unpaid types, full/half day, simple allocation and one approval step. Configure whether negative balances are allowed and whether holidays/weekends consume leave. Partial-paid leave and more elaborate accruals follow actual customer demand in V2.

Cancellation of approved leave recomputes unlocked attendance and drafts. For locked payroll, create a controlled revision or later adjustment.

## OT and limits

Store worked, eligible and approved OT separately. Configure threshold basis, grace, increment rounding, multiplier/fixed rate, daily/monthly cap, approval requirement and holiday/week-off treatment. Caps constrain eligibility/payment according to policy; raw time must remain intact.

Payroll previews show any capped minutes and reason. OT is never automatically payable just because an attendance row has a nonzero overtime field. Validate unusual hours and missing checkout before approval.

## Payroll arithmetic

Use decimal money or integer minor units; never binary floating-point for salary calculation. Represent hours/minutes and partial days consistently. Centralize currency precision and rounding, with rules for intermediate versus final rounding.

Recommended presentation:
- Contract salary and earned base for the covered period.
- Positive earning lines, including approved OT.
- Gross earnings before LOP.
- LOP as one explicit deduction line.
- Other deductions.
- Net payable = gross earnings - total deductions.

If a policy needs an adjusted base for component calculation, store that basis separately and explain it. Never subtract LOP from the earning line and then subtract it again from net. Use canonical signed line items and verify totals from those items.

Define monthly divisor policy explicitly: calendar days, fixed divisor, or scheduled payable days. Define joining/leaving proration, half-day rules and which days are paid. Do not copy Horilla's current results blindly.

Example fixture (calendar-day divisor, August with 31 days, no allowances/other deductions):
- Contract salary INR 6,000; one unpaid day.
- LOP = round(6000 / 31, 2) = INR 193.55.
- Gross INR 6,000; deductions INR 193.55; net INR 5,806.45.
The example is a test scenario, not an instruction to assign every tenant that divisor.

## Pay-period lifecycle

Attendance open -> reviewed -> locked.
Payroll draft -> reviewed -> approved -> payment recorded.

Before draft generation validate employment coverage, salary assignment, currency, holiday policy, unresolved punches and approved leave/OT. Show blockers with employee/date links. Do not silently treat missing configuration as zero salary or zero working hours.

Snapshot the salary agreement, policy versions, holiday/calendar decisions, attendance/leave/OT aggregates, rounding rules, line-item calculations and approval metadata. Link back to raw evidence without duplicating unnecessary biometric content.

Re-running the same tenant/period/revision must update the intended draft or return the existing result, never duplicate payslips. Approvals protect against stale previews using version/checksum validation. An approved or paid run cannot change silently. Corrections create a revision with supersession history or a subsequent-period adjustment.

Payment recording is a separate authorized action. No automatic bank transfer or marking paid merely because a PDF/email was generated.

## Payslip automation and reports

Use existing barryvdh/laravel-dompdf and Blade layouts with local fonts supporting INR. Generate from saved payroll snapshots, store privately by tenant and revision, and reuse artifacts only when the snapshot/template version matches.

A scheduled tenant-aware job may prepare drafts when the period closes and attendance is ready. Send reviewers a summary of blockers/totals. Generate/distribute approved slips according to tenant settings. Retrying PDF/email/WhatsApp jobs must not duplicate payments or resend successful deliveries without an explicit resend action.

Use Laravel scheduler locks, queue jobs, notifications and mail. Scope locks by tenant/period and enforce database uniqueness in addition to scheduler guards. Provide manual “retry failed” actions and delivery history.

PDF content: company identity/logo, period, employee, paid/LOP days, contract salary, earnings, deductions, net and document status. One A4 page for normal component counts; deliberate continuation pages for unusually long slips rather than unreadable shrinking. Do not assert that signatures are unnecessary unless the organisation selects that wording.

Reports: daily exceptions, monthly attendance, leave balance/ledger, OT approval, payroll register, component totals, payment status and restricted exports. Use aggregate queries and approved snapshots; avoid recalculating payroll inside table cells.

## V2 currency and advanced policies

INR is the V1 default. Store ISO code with agreements, runs and artifacts from the beginning. V2 can configure organisation currency, decimal precision, symbol placement and display locale. Changing display settings must not relabel historic amounts as another currency.

Treat currency conversion as separate optional scope: source/target currency, explicit rate/source/date, effective period, rounding and approval. Mixed currencies must remain separate in reports unless converted using a recorded rate.

Add safe component definitions with validated bases, fixed/percentage values, eligibility and caps. Avoid arbitrary executable formulas. Version tax/statutory policies with effective dates and applicability; require verified requirements for PF, ESI, professional tax, TDS and other obligations before implementing them. Currency alone cannot select these rules.

Arrears, advances, installment recovery and off-cycle runs need dedicated ledger records and links to prior results, with limits and approval reasons.

## Reuse and CLI workflow

Keep Dompdf, Sanctum and Stancl. Use Laravel auth/session middleware, policies, validation requests, queues, scheduler and notifications. Evaluate extra packages only for a specific missing requirement, checking installed-version compatibility and tenant behavior. Do not install a general payroll package merely for CRUD scaffolding.

Before generating files inspect current routes, panel IDs, existing resources and installed command help. Typical commands:
- php artisan help make:filament-resource
- php artisan help make:filament-page
- php artisan make:filament-resource LeaveRequest
- php artisan make:filament-resource PayrollRun
- php artisan make:model SalaryAgreement -m
- php artisan make:policy PayrollRunPolicy --model=PayrollRun
- php artisan make:job GeneratePayslip

Specify the existing tenant panel using flags supported by installed help. Review generated schemas, policies and tenant connection behavior. CLI scaffolding is the starting point; business rules belong in tested application services.

## Verification gates

Meaningful fixtures: full month, half-day LOP, joining/leaving mid-month, overnight shift, break, missing checkout, inferred checkout, holiday/week-off overlap, holiday work, OT caps, late punches, leave cancellation, salary revision, duplicate generation, locked period, negative/zero net and decimal boundaries.

Cross-tenant fixtures use the same employee PIN and local IDs. Test queue context cleanup, role rejection, PDF/export ownership and private file URLs. Test reconciliation between line-item sums, stored totals, payroll register and rendered PDFs.

Run isolated automated tests and measured sample workloads. Compare two complete payroll periods to independently approved expected calculations. Record actual output and exit codes; test discovery alone does not prove tests passed.
