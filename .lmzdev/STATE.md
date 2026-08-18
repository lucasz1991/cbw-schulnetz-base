# Current state

## Confirmed

- LMZ Dev workspace initialized.
- The user explicitly limited implementation to the existing Schulnetz `CreateOrUpdateCourse` job and requested no UVS API, scheduler, reconciler, config, migration, or additional job changes.
- All broader implementation changes were removed. The UVS API repository is clean.
- `CreateOrUpdateCourse` no longer soft-deletes a course on a failed UVS response. It clears the cooldown and throws so the existing queue retry mechanism runs.
- The existing successful-response path remains unchanged and still restores a soft-deleted course plus matching enrollments and days.
- Retry attempts were increased from 3 to 6 with delays of 60, 300, and then 900 seconds.

## Verification

- PHP lint passed for `app/Jobs/ApiUpdates/CreateOrUpdateCourse.php`.
- An isolated runtime smoke check injected a 404 response, confirmed the retry exception, and confirmed the cooldown was removed without any database access.
- `git diff --check` passed. No queue job was dispatched and no database row was changed.

## Risks and blockers

- Courses soft-deleted before this fix still need one ordinary successful `CreateOrUpdateCourse` run to invoke the already existing restore path.
- The repository PHPUnit configuration still points at the configured local database unless the caller explicitly overrides it; do not run database tests against the imported data.
