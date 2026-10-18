# Studio dashboard candidate

The new `classes/NwOnlineTvDashboard.php` and `template/css/dashboard.css` contain an independent, read-only dashboard presenter.

Current cards: channel count, channels needing review, open reports, and last successful sync.

## Security and integration boundaries
- No SQL queries, write actions, imported Modzzz code, or calls to UNA internals.
- The caller must supply authorized, filtered channel/report/sync data.
- All dynamic values are escaped for HTML; styles have responsive fallback.
- **Not yet wired to UNA Studio.** Native Studio routing, permissions, asset loading, theme testing, and installation integration remain pending.
- The presenter is safe to exercise via `php tests/dashboard.php` and is not a production dashboard.

Next: implement and verify native module class/config and an authenticated Studio page using UNA's supported extension points on an isolated test installation.
