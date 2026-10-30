"""Build and verify a deterministic source distribution from a clean Git checkout."""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
DIRECTORIES = ('app/', 'config/', 'database/migrations/', 'public/', 'resources/', 'routes/', 'docs/',
               'bridge/paper/src/')
FILES = {'artisan', 'LICENSE', 'NOTICE', 'README.md', 'SECURITY.md', 'composer.json', 'composer.lock', '.env.example',
         'bootstrap/app.php', 'bootstrap/providers.php', 'tools/package_source.py',
         'bridge/paper/LICENSE', 'bridge/paper/NOTICE.md', 'bridge/paper/README.md',
         'bridge/paper/build.sh', 'bridge/paper/dependencies.sha256',
         'bridge/paper/test-lifecycle.sh', 'bridge/paper/integration-dependencies.json',
         'bridge/paper/integration-dependencies.sha256'}
EMPTY_DIRECTORIES = ('bootstrap/cache/', 'storage/app/private/', 'storage/app/public/',
                     'storage/framework/cache/data/', 'storage/framework/sessions/',
                     'storage/framework/views/', 'storage/logs/')
SECRET_PATTERNS = (
    re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----'),
    re.compile(rb'\b(?:AKIA|ASIA)[A-Z0-9]{16}\b'),
    re.compile(rb'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{50,})\b'),
    re.compile(rb'\b(?:sk|rk)_live_[A-Za-z0-9]{20,}\b'),
)


def git(*arguments: str) -> bytes:
    return subprocess.check_output(['git', *arguments], cwd=ROOT)


def selected(name: str) -> bool:
    path = PurePosixPath(name)
    if path.is_absolute() or '..' in path.parts or '\\' in name or ':' in name:
        raise ValueError('Invalid repository path')
    lowered = name.lower()
    if (path.name.lower().startswith('.env') and name != '.env.example') or path.name.lower() == 'auth.json':
        raise ValueError('Private configuration is tracked; distribution refused')
    if lowered.startswith(('public/storage/', 'bootstrap/cache/')):
        return False
    return name in FILES or name.startswith(DIRECTORIES)


def info(name: str, directory: bool = False) -> zipfile.ZipInfo:
    entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
    entry.create_system = 3
    entry.compress_type = zipfile.ZIP_DEFLATED
    entry.external_attr = ((stat.S_IFDIR | 0o755) if directory else (stat.S_IFREG | 0o644)) << 16
    return entry


def verify(archive: Path, expected: dict[str, dict]) -> None:
    with zipfile.ZipFile(archive) as source:
        entries = source.infolist()
        names = [entry.filename for entry in entries]
        if len(names) != len(set(name.casefold() for name in names)):
            raise ValueError('Duplicate or case-colliding archive paths')
        if set(names) != set(expected) | set(EMPTY_DIRECTORIES) | {'distribution-manifest.json'}:
            raise ValueError('Archive differs from the distribution allowlist')
        for name, record in expected.items():
            data = source.read(name)
            if len(data) != record['bytes'] or hashlib.sha256(data).hexdigest() != record['sha256']:
                raise ValueError('Archive file integrity mismatch: '+name)
        manifest = json.loads(source.read('distribution-manifest.json'))
        if {item['path']: item for item in manifest['files']} != expected:
            raise ValueError('Manifest does not match packaged files')
        if source.testzip() is not None:
            raise ValueError('Archive CRC verification failed')


def main() -> None:
    if sys.version_info < (3, 11):
        raise SystemExit('The maintainer packaging tool requires Python3.11or newer.')
    if git('status', '--porcelain').strip():
        raise SystemExit('Commit or resolve source changes before packaging; working tree must be clean.')
    revision = git('rev-parse', 'HEAD').decode('ascii').strip()
    names = sorted(name for name in git('ls-files', '-z').decode('utf8').split('\0') if name and selected(name))
    payloads, records = {}, {}
    total = 0
    for name in names:
        path = ROOT / name
        metadata = path.lstat()
        if not stat.S_ISREG(metadata.st_mode) or getattr(metadata, 'st_file_attributes', 0) & 0x400:
            raise ValueError('Nonregular/reparse source file refused: '+name)
        if ROOT not in path.resolve().parents or metadata.st_size > 8 * 1024 * 1024:
            raise ValueError('Source path/size budget refused: '+name)
        if path.suffix.lower() in ('.pem', '.key', '.pfx', '.p12', '.sqlite', '.sql', '.dump', '.log'):
            raise ValueError('Runtime/private file refused: '+name)
        # Read the pinned Git blob, rather than a concurrently edited working file.
        data = git('cat-file', 'blob', revision+':'+name)
        if len(data) > 8 * 1024 * 1024:
            raise ValueError('Committed file exceeds8MiBbudget: '+name)
        if any(pattern.search(data) for pattern in SECRET_PATTERNS):
            raise ValueError('Credential pattern found; value omitted: '+name)
        total += len(data)
        if total > 64 * 1024 * 1024:
            raise ValueError('Source distribution exceeds64MiBinput budget')
        payloads[name] = data
        records[name] = {'path': name, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
    composer = json.loads(payloads['composer.json'])
    manifest = {'product': 'VibeCraft', 'kind': 'source', 'source_revision': revision,
                'php': composer['require']['php'],
                'licenses': {'website': 'LicenseRef-VibeCraft', 'bridge/paper/': 'LicenseRef-VibeCraft; see LICENSE and NOTICE'},
                'game_bridge': 'source only; JDK25 and separately pinned provided APIs required for its offline build',
                'dependencies': 'vendor excluded; install production dependencies from composer.lock with --no-dev',
                'files': list(records.values())}
    directory = ROOT / '.devenv' / 'artifacts'
    if ROOT not in directory.resolve().parents:
        raise ValueError('Output directory must stay inside the checkout')
    directory.mkdir(parents=True, exist_ok=True)
    destination = directory / ('vibecraft-source-'+revision[:12]+'.zip')
    descriptor, temporary = tempfile.mkstemp(prefix='source-build-', suffix='.zip', dir=directory)
    os.close(descriptor)
    temporary = Path(temporary)
    try:
        with zipfile.ZipFile(temporary, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name in EMPTY_DIRECTORIES:
                archive.writestr(info(name, directory=True), b'')
            for name in names:
                archive.writestr(info(name), payloads[name])
            archive.writestr(info('distribution-manifest.json'), json.dumps(manifest, sort_keys=True, indent=2).encode('utf8'))
        verify(temporary, records)
        if destination.exists():
            existing = destination.lstat()
            if not stat.S_ISREG(existing.st_mode) or getattr(existing, 'st_file_attributes', 0) & 0x400 or hashlib.file_digest(destination.open('rb'), 'sha256').digest() != hashlib.file_digest(temporary.open('rb'), 'sha256').digest():
                raise ValueError('A different artifact already exists; refusing overwrite')
        else:
            os.replace(temporary, destination)
        print(json.dumps({'archive': str(destination), 'kind': 'source', 'files': len(records),
                          'input_bytes': total, 'archive_bytes': destination.stat().st_size,
                          'sha256': hashlib.file_digest(destination.open('rb'), 'sha256').hexdigest(),
                          'source_revision': revision, 'verification': 'allowlist/manifest/digests/CRC passed',
                          'runtime_dependencies_included': False}))
    finally:
        temporary.unlink(missing_ok=True)


if __name__ == '__main__':
    main()
