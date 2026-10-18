# Native UNA Studio integration gate

Current PHP classes are *independent utilities*, not a registered UNA Studio page. This separation is intentional while installer contracts are being reviewed.

## Required implementation
1. Verify documented UNA module base classes, configuration, Studio menu entries, and installer SQL registration against supported UNA 14 and 15 builds.
2. Implement a native Studio page that derives the webmaster identity from UNA's authenticated session; never use a submitted admin boolean.
3. Use UNA's native CSRF mechanism and parameterized database connection for all write operations.
4. Wire the existing read-only dashboard and channel directory to repository queries with scoped permissions.
5. Add confirmation and audit logging for administrative changes.
6. Test fresh install, enable/disable, and uninstall on an isolated UNA site before deploying to any production host.

## Current state
This repository does not yet include native Studio routing or authenticated mutation controllers. The standalone policy class is a deny-by-default decision helper, not an authentication implementation. Do not claim the module is installable or Marketplace-ready.
