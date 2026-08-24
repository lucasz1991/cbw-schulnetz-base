# Decisions

Record durable decisions with date, context, decision, and consequences.

## 2026-08-18 | Keep the recovery fix inside the existing course update job

- Decision: Change only `CreateOrUpdateCourse`; remove the proposed reconciler, scheduler, UVS API contract, support classes, config changes, and additional tests.
- Reason: The user explicitly requested the smallest possible local fix and no UVS API changes.
- Consequence: Failed UVS responses preserve the last local state and use existing queue retries. Previously soft-deleted rows require one later successful run of the existing job.

## 2026-08-23 | Bound the course loader to the participant's expected program data

- Decision: Replace the temporary participant-facing 404 only for course identifiers present in the current participant/program scope, poll for at most ten seconds, and retain a real 404 for unknown or foreign identifiers.
- Reason: The delay is caused by the existing asynchronous local course/enrollment creation, while unrestricted retry behavior would weaken authorization and error semantics.
- Consequence: Users receive spinner, progress text, retry, and cancel states without hiding genuine invalid URLs.

## 2026-08-23 | Snapshot external-exam billing data and preserve legacy reads

- Decision: Require the institution explicitly and copy the selected external appointment price into `fee_cents` when the request is created. Read legacy external-exam fields first and current request fields as fallbacks; keep `zert_faild` readable while new failures use `certification_failed`.
- Reason: PDFs must remain historically stable even if appointment prices change, and existing requests must continue rendering.
- Consequence: Newly generated PDFs contain the requested class, institution, exam, date, and fee without breaking older data.

## 2026-08-23 | Use permission-driven atomic report-book takeover

- Decision: Authorize jobs through the existing `jobs.view` ability and update assignment/completion with transactions, task-row locks, the same report-book parent lock in Base and Admin for first-time creation, and expected-assignee checks. Do not hard-code representative names.
- Reason: Representatives can change, and stale Livewire actions must not overwrite a newer takeover or create parallel assignment state.
- Consequence: An active report-book review remains one task that can safely move between authorized employees; parallel Base jobs and Admin transfers are serialized before deciding whether that task must be created. Legacy description matching uses a complete ID boundary and restores the missing morph context.
