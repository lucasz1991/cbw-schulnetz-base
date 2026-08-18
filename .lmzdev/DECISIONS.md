# Decisions

Record durable decisions with date, context, decision, and consequences.

## 2026-08-18 | Keep the recovery fix inside the existing course update job

- Decision: Change only `CreateOrUpdateCourse`; remove the proposed reconciler, scheduler, UVS API contract, support classes, config changes, and additional tests.
- Reason: The user explicitly requested the smallest possible local fix and no UVS API changes.
- Consequence: Failed UVS responses preserve the last local state and use existing queue retries. Previously soft-deleted rows require one later successful run of the existing job.
