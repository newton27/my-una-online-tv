# Channel directory — standalone read-only candidate

Independent `NwOnlineTvDirectory` supports bounded pagination, title/category/country text search, status filtering, review-state display, and escaped HTML. It does not embed videos, query the database, render action controls, or permit mutations.

To run the smoke test: `php tests/directory.php`.

Important integration boundary: Filtering in memory is suitable for test fixtures only. A production UNA data layer must enforce SQL-side pagination, permission checks, parameterized queries, count limits and appropriate indexes. Search terms must not be interpolated into SQL.

Studio admin actions will require UNA authentication/authorization, CSRF checks, audit logs, and native UI integration. No live site has been modified.
