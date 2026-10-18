# Independent database repository milestone

`NwOnlineTvRepository` provides parameterized read-only dashboard statistics, server-side paginated channel search, and a bounded INSERT IGNORE import path. It never reads from or deletes legacy Modzzz tables.

**Integration gates:** It requires a trusted PDO database connection and explicit Studio administrative authorization; there is no HTTP endpoint, scheduler, or unauthenticated import action. Imports accept externally provided rows, do not fetch remote URLs, and do not validate DNS, content type or stream availability. Build an allowlisted catalog retrieval service with DNS rebinding defenses and URL limits before exposing imports.

The SQLite regression fixture is a structural test, not proof of MariaDB/UNA compatibility. Native UNA integration, permissions, CSRF, sync locking, transaction strategy, and production installation are not done.
