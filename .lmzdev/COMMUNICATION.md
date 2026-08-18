# Agent communication

Append-only communication log. Source and runtime evidence override stale entries.

## 2026-08-18T17:52:49Z | Codex -> all | status

- Message: Started: Robuste automatische UVS-Kurswiederherstellung nach temporaeren API-Fehlern analysieren

## 2026-08-18T17:59:07Z | Codex -> all | handoff

- Summary: Read-only diagnosis complete. Restore logic exists but is not reliably re-dispatched after a generic 404 soft-delete. Recommended fail-safe API classification, explicit UVS tombstones, and scheduled reconciliation including trashed courses.
- Files changed: `.lmzdev/TASKS.md`, `.lmzdev/STATE.md`, `.lmzdev/COMMUNICATION.md` only.
- Verification: Code, imported database, and history inspected; no application code, data, jobs, or API state changed.
- Risks: Isolate the PHPUnit database before tests; never treat a bare HTTP 404 as authoritative course deletion.
- Next step: Await explicit approval before implementing the Schulnetz and UVS API recovery contract.

## 2026-08-18T18:02:45Z | Codex -> all | status

- Message: Started: Sichere automatische UVS-Kurswiederherstellung lokal implementieren

## 2026-08-18T18:05:17Z | Codex reconciler agent -> all | status

- Message: Started: implement automatic UVS course reconciler command, scheduler, and isolated tests

## 2026-08-18T18:11:38Z | Codex recovery_job agent -> all | handoff

- Task: Make CreateOrUpdateCourse fail-safe for transient and malformed UVS responses.
- Status: completed
- Changed: `app/Jobs/ApiUpdates/CreateOrUpdateCourse.php`, `app/Exceptions/RetryableCourseSyncException.php`, `app/Services/ApiUvs/CourseApiResponseClassifier.php`, `tests/Unit/CourseApiResponseClassifierTest.php`, `tests/Feature/CreateOrUpdateCourseRecoveryTest.php`.
- Verified: PHP lint passed for all five files; classifier tests passed (3 tests, 15 assertions); isolated SQLite recovery tests passed (3 tests, 48 assertions); Pint passed for the four new files; `git diff --check` passed.
- Safety: 404, unsigned/mismatched 410, 5xx, timeout and malformed 2xx responses preserve local Course/Day/Enrollment data, clear the cooldown and throw for queue retry. Only exact HTTP 410 + COURSE_RETIRED + authoritative true + retryable false + matching klassen_id may soft-delete.
- Next: Integrate with the reconciler and UVS 503 error contract, then run the combined focused suite.

## 2026-08-18T18:14:57Z | Codex reconciler agent -> all | handoff

- Task: Add bounded automatic UVS course reconciliation and safe nightly person/program-data refresh.
- Status: completed
- Changed: `app/Console/Commands/ReconcileUvsCourses.php`, `app/Console/Kernel.php`, `app/Jobs/UpdateApiDataJob.php`, `config/api_sync.php`, `tests/Feature/Console/ReconcileUvsCoursesTest.php`.
- Verified: PHP lint passed; focused Pint passed; reconciler suite passed 7 tests/52 assertions; combined recovery and reconciler suites passed 13 tests/115 assertions; `artisan schedule:list` shows 03:00 person sync and 15-minute course reconciliation; imported DB dry-runs selected 141 regular rows (24 trashed) and 51 legacy trashed rows without dispatching jobs; `git diff --check` passed.
- Safety: Tests force SQLite `:memory:` before Laravel boots. No imported data changed, no actual queue/API job dispatched, no commit or push performed. `onOneServer` remains off because shared cache is unverified.
- Next: Parent agent should verify the UVS error-contract change, rerun the combined suite after all edits settle, then decide when to dispatch the one-time `--recover-legacy` run.

## 2026-08-18T18:26:11Z | Codex -> all | handoff

- Task: Reduce the implementation to the user-requested minimal Schulnetz course-update fix.
- Status: completed; earlier broad agent handoffs above are superseded.
- Changed: `app/Jobs/ApiUpdates/CreateOrUpdateCourse.php` only, plus this `.lmzdev` coordination update.
- Removed: UVS API changes, scheduler, reconciler command, config changes, unique nightly job changes, support classes, PHPUnit config changes, documentation, and added test files.
- Verified: UVS API repository clean; PHP lint passed; isolated 404 smoke check confirmed no delete path and released cooldown; `git diff --check` passed.
- Data safety: No database row changed, no real queue job dispatched, no commit or push performed.
