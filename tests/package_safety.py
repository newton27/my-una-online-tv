#!/usr/bin/env python3
"""Static regression gates for isolated Online TV development packages."""
from pathlib import Path
from zipfile import ZipFile
import re
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[1]
cfg = (root / 'install/config.php').read_text()
installer = (root / 'install/installer.php').read_text()
install = (root / 'install/sql/install.sql').read_text()
uninstall = (root / 'install/sql/uninstall.sql').read_text()
langs = ET.parse(root / 'install/langs/en.xml')
keys = {el.attrib.get('name') for el in langs.iter('string')}
assert {'_sys_module_nw_online_tv', '_nw_online_tv', '_nw_online_tv_description'} <= keys
assert "'name' => 'nw_online_tv'" in cfg
assert "'home_dir' => 'newton/my_una_online_tv/'" in cfg
assert 'extends BxDolStudioInstaller' in installer
assert 'bx_import(' not in installer and 'BxDolModuleInstaller' not in installer
for filename, sql in [('install.sql', install), ('uninstall.sql', uninstall)]:
    for table in re.findall(r'(?:CREATE|DROP)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`?([a-zA-Z0-9_]+)', sql, re.I):
        assert table.startswith('nw_online_tv_'), (filename, table)
    assert 'mz_onlinetv' not in sql.lower(), filename
zip_path = root / 'dist/my-una-online-tv-0.1.0-dev.zip'
assert zip_path.is_file()
with ZipFile(zip_path) as z:
    assert z.testzip() is None
    names = z.namelist()
    assert names and all(n.startswith('my_una_online_tv/') for n in names)
    assert 'my_una_online_tv/install/installer.php' in names
    assert 'my_una_online_tv/install/langs/en.xml' in names
    assert z.read('my_una_online_tv/install/installer.php') == (root / 'install/installer.php').read_bytes()
print('PASS: module identity, language keys, isolated SQL, installer, ZIP path and CRC')
