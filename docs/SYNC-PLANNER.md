# Catalog sync planner — development-only

Independent planner: parse bounded M3U text, check HTTPS HLS URLs and deduplicate entries. It never downloads remote content, schedules jobs, probes streams or mutates databases. The private-host filter is only a first validation layer; **before any network fetch**, add DNS resolution, redirect disabling, public-address enforcement and DNS-rebinding protection. A URL passing this parser does not make it safe to fetch.

Studio sync must require administrator authorization, CSRF protection, source allowlists, locking, audit history and fixed resource budgets. In-place updates must preserve existing channels and never automatically delete suspect streams.
