#!/usr/bin/env python3
"""Build deterministic development-only UNA module archive from a checked-out tree."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo
import hashlib
import sys

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "dist" / "my-una-online-tv-0.1.0-dev.zip"
INCLUDE = ("classes/", "install/", "template/")
paths = sorted(p for p in ROOT.rglob("*") if p.is_file() and p.relative_to(ROOT).as_posix().startswith(INCLUDE))
if not paths:
    raise SystemExit("No module files found")
required = ["install/config.php", "install/installer.php", "install/sql/install.sql",
            "install/sql/uninstall.sql", "install/langs/en.xml", "classes/NwOnlineTvModule.php"]
missing = [p for p in required if not (ROOT / p).is_file()]
if missing:
    raise SystemExit("Missing files: " + ", ".join(missing))
OUT.parent.mkdir(exist_ok=True)
with ZipFile(OUT, "w", ZIP_DEFLATED, compresslevel=9) as z:
    for p in paths:
        name = "my_una_online_tv/" + p.relative_to(ROOT).as_posix()
        entry = ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
        entry.compress_type = ZIP_DEFLATED
        entry.external_attr = 0o644 << 16
        z.writestr(entry, p.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
with ZipFile(OUT) as z:
    if z.testzip() is not None:
        raise SystemExit("ZIP CRC verification failed")
digest = hashlib.sha256(OUT.read_bytes()).hexdigest()
print(f"DEVELOPMENT ONLY: {OUT.name}: {len(paths)} files sha256={digest}")
print("This build is NOT certified for UNA Studio installation or Marketplace publication.")
