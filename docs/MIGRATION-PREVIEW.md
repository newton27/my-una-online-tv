# Non-destructive migration foundation

The independent preview adapter accepts arrays of channel title/stream URL pairs and compares SHA-256 URL hashes with channels already present in the new module. It reports eligible, duplicate and rejected records. It **does not** query, modify, delete, uninstall or interact with legacy Modzzz tables.

Before implementing a production migration: document actual installed schema variants, use a read-only SQL connection with least privilege, back up both databases and media, implement explicit user consent, avoid duplicate import, preserve editing metadata where lawful and compatible, and create audited rollback/reconciliation steps.

This preview contains no imported third-party source. `php tests/migration_preview.php` tests the pure PHP logic.
