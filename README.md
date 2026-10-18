# My UNA World Online TV

Independent, free UNA CMS Online TV project maintained by My UNA World.

**Status:** pre-release development candidate. Native Studio installation and production readiness are **not** verified.

## Goals
- Public channel directory with categorization, search and playback.
- Administrator-controlled sync from public IPTV catalogs with HTTPS HLS screening.
- Bounded channel health checks and moderation review (no automatic deletion).
- Channel reporting, professional Studio tools and read-only legacy migration.

## Clean-room policy
No legacy Online TV source, artwork or copied SQL definitions are part of this project. The new module has independent identity `nw_online_tv`, directory `modules/newton/my_una_online_tv`, and database prefix `nw_online_tv_`. Existing legacy Online TV installations must remain unchanged.

## Release policy
Free ($0), no activation keys or mandatory subscriptions. Do not distribute as a Marketplace-ready release before native installation, security and migration testing.
