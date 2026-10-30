"""Validate independent Symcon library identity, module discovery and form bindings."""
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
library = json.loads((root / 'library.json').read_text(encoding='utf-8'))
module = json.loads((root / 'PhilipsTV/module.json').read_text(encoding='utf-8'))
form = json.loads((root / 'PhilipsTV/form.json').read_text(encoding='utf-8'))
assert library['id'] != '{2AC4EF61-E417-423C-A0E7-A0A797A0357A}', 'Dedicated library must not use Hausstatus identity'
assert len(library['version'].split('.')) == 3 and library['compatibility']['version'] == '9.0'
assert module['id'] == '{7A9BA592-5E11-4F37-B9D0-740BC1C61C19}' and module['prefix'] == 'SVPTV'
assert module['name'] == 'PhilipsTV' and (root / 'PhilipsTV/module.php').is_file()
for directory in root.iterdir():
    if not directory.is_dir() or directory.name.startswith('.') or directory.name in {'docs', 'tests', 'libs', 'imgs', 'actions'}:
        continue
    assert (directory / 'module.json').is_file(), f'Invalid Symcon module directory: {directory.name}'
code = (root / 'PhilipsTV/module.php').read_text(encoding='utf-8')
for element in form['elements']:
    if 'name' in element:
        assert "'" + element['name'] + "'" in code, element['name']
assert all('Symcon-Hausstatus' not in p.as_posix() for p in root.rglob('*.php'))
print('Independent Philips library: identity, structure, metadata and form bindings passed')
