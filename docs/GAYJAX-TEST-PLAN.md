# gayjax.online isolated native test plan

Site: http://gayjax.online/studio/launcher.php
Observed through authorized read-only browser tab: logged-in administrator Studio; Developer and App Store available; independent Online TV not listed.

DO NOT install current 0.1.0 development package yet:
- Module Studio registration and installer contract require a verified disposable environment.
- Never uninstall the existing legacy module for migration.
- Capture UNA version, PHP version, database snapshot and module inventory before first installation.
- Test a staging copy of gayjax.online first if real users or content exist.
- Test native install -> Studio launcher -> dashboard -> disable/enable -> uninstall on disposable data, then assess upgrade paths.
- Do not enable automated catalog jobs until network security and permissions are validated.

Development ZIP creation (in a checkout): python3 tools/build-dev-package.py. It is NOT a production package.
