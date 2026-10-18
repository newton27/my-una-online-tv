# Implementation status — 0.1.0 development candidate

This repository contains original UNA module scaffolding and a standalone M3U parser. The code is **not** certified for native UNA installation.

Implemented:
- Independent `nw_online_tv` namespace and module configuration candidate
- Channel, report and sync-history schema
- Basic bounded M3U parsing, URL validation and deduplication
- PHP parser regression smoke test

Not implemented:
- Native verified Studio installation and uninstallation
- Studio dashboard, channel directory/player, synchronization workers, health probes, reporting UI, permissions model, migration/importer, update packages
- Complete stream URL SSRF protection for network fetching (parser does not fetch URLs)

**Do not install in production or publish a Marketplace release.**
