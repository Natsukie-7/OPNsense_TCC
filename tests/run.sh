#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
find src scripts tests -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
php tests/run.php "$@"
php tests/controller.php
if command -v node >/dev/null 2>&1; then
    node --check src/opnsense/www/js/userregister.js
fi
python3 - <<'PY'
from pathlib import Path
import xml.etree.ElementTree as ET
for path in Path('src').rglob('*.xml'):
    if 'vendor' not in path.parts:
        ET.parse(path)
print('OK: XMLs de menu e ACL válidos.')
PY
bash -n build_plugin.sh
sh -n scripts/install_remote.sh
