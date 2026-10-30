"""Build the ready-to-upload VibeCraft release archive.

The archive contains the website with production Composer dependencies, so it can be uploaded to
shared hosting (for example cPanel) and installed from the browser without a terminal. It is built
from the committed tree only (git archive), never from the working directory, so local files such
as .env, databases, logs or private notes cannot end up in it.

Usage (from a clean checkout):
    python tools/build_release.py --php /path/to/php --composer /path/to/composer.phar [--jar path/to/plugin.jar]

Output in build/release/: vibecraft-<version>.zip, the plugin jar (when given) and SHA256SUMS.
Nothing is published or uploaded by this script.
"""
import argparse
import hashlib
import io
import os
import re
import shutil
import subprocess
import sys
import tarfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
# Development-only material that a hosting upload does not need.
EXCLUDE_PREFIXES = ('tests/', 'tools/', 'bridge/', 'docs/screenshots/', 'docs/adr/')
EXCLUDE_FILES = {'phpunit.xml', 'phpstan.neon', '.editorconfig', '.gitattributes'}
# Anything matching these must never be inside a release, whatever the tree contains.
FORBIDDEN = re.compile(r'(^|/)(\.env(\.(?!example$)[^/]*)?|.*\.(sql|sqlite|sqlite3|dump|bak|log|pem|key|p12|pfx)|installed\.lock|setup-token\.(txt|sha256))$')


def run(command, cwd):
    result = subprocess.run(command, cwd=cwd, capture_output=True, text=True)
    if result.returncode != 0:
        sys.exit('Command failed: ' + ' '.join(command) + '\n' + result.stdout[-2000:] + result.stderr[-2000:])
    return result.stdout


def version():
    match = re.search(r"'version'\s*=>\s*'([^']+)'", (ROOT / 'config' / 'vibecraft.php').read_text(encoding='utf-8'))
    if not match:
        sys.exit('Version not found in config/vibecraft.php')
    return match.group(1)


def sha256(path):
    digest = hashlib.sha256()
    with open(path, 'rb') as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b''):
            digest.update(chunk)
    return digest.hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('--composer', required=True)
    parser.add_argument('--jar')
    args = parser.parse_args()

    if run(['git', 'status', '--porcelain', '--untracked-files=no'], ROOT).strip():
        sys.exit('Commit or discard tracked changes first; releases are built from HEAD only.')
    release = version()
    out = ROOT / 'build' / 'release'
    stage = ROOT / 'build' / 'stage' / 'vibecraft'
    shutil.rmtree(stage.parent, ignore_errors=True)
    out.mkdir(parents=True, exist_ok=True)
    stage.mkdir(parents=True)

    archive = subprocess.run(['git', 'archive', '--format=tar', 'HEAD'], cwd=ROOT, capture_output=True, check=True).stdout
    with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
        for member in tar.getmembers():
            name = member.name
            if not member.isfile() or name.startswith(EXCLUDE_PREFIXES) or name in EXCLUDE_FILES:
                continue
            if FORBIDDEN.search(name):
                sys.exit('Refusing to package a sensitive file: ' + name)
            target = stage / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(tar.extractfile(member).read())

    env = dict(os.environ, COMPOSER_NO_INTERACTION='1')
    run([args.php, args.composer, 'install', '--no-dev', '--prefer-dist', '--classmap-authoritative',
         '--no-plugins', '--no-scripts', '--no-progress'], stage)
    for runtime in ('bootstrap/cache', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions',
                    'storage/framework/views', 'storage/logs'):
        (stage / runtime).mkdir(parents=True, exist_ok=True)

    target = out / f'vibecraft-{release}.zip'
    target.unlink(missing_ok=True)
    count = 0
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for path in sorted(stage.rglob('*')):
            relative = path.relative_to(stage.parent).as_posix()
            if FORBIDDEN.search(relative) and not relative.startswith('vibecraft/vendor/'):
                sys.exit('Refusing to package a sensitive file: ' + relative)
            if path.is_dir():
                if not any(path.iterdir()):
                    bundle.writestr(relative + '/', '')
                continue
            bundle.write(path, relative)
            count += 1

    sums = [(target.name, sha256(target))]
    if args.jar:
        jar = Path(args.jar)
        copied = out / jar.name
        shutil.copyfile(jar, copied)
        sums.append((copied.name, sha256(copied)))
    (out / 'SHA256SUMS').write_text(''.join(f'{digest}  {name}\n' for name, digest in sums), encoding='utf-8', newline='\n')
    shutil.rmtree(stage.parent, ignore_errors=True)
    print(f'{target.name}: {count} files, {target.stat().st_size} bytes')


if __name__ == '__main__':
    main()
