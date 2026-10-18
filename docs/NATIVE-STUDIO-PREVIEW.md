# UNA Studio extension-point candidate

Based on UNA developer documentation, `NwOnlineTvModule` extends `BxDolModule` and `NwOnlineTvStudioPage` extends `BxTemplStudioModule`. The initial Studio page intentionally renders placeholders. The exact runtime Studio method signature, permissions enforcement, asset loading and module registration must be confirmed on supported UNA installations.

No registration SQL or production-facing endpoint has been added. `getPageCode()` is intentionally a candidate entrypoint, **not proof of working Studio integration**. The module must not be installed on a live site.

Next: use a disposable UNA environment to inspect the module Studio page lifecycle, confirm the precise override method signatures, then register the native launcher, connect read-only PDO metrics, and add validated dashboard/directory links.
