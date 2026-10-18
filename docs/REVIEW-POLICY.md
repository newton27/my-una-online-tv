# Review and reporting policy

Independent pure-PHP review decisions:
- First observed failure increments the failure counter.
- Two consecutive failures set a review-required flag.
- Success resets the consecutive-failure counter but does **not** automatically clear an existing review flag.
- No automatic channel deletion, suppression or status overwrite.
- Reports use a fixed set of validated reasons.

A later authenticated worker may apply these state transitions with audit logging, rate limits, request authentication and a carefully designed SSRF-safe network probe. These components do **not** perform remote requests or database writes by themselves.

The PHP fixture `tests/review.php` is provided, but execution in this environment has not been confirmed.
