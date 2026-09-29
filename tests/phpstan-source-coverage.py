#!/usr/bin/env python3
"""Require every shipped PHP file to fall within a direct PHPStan path."""

from pathlib import Path
import tempfile


ROOT = Path(__file__).resolve().parents[1]


def direct_paths(config):
    """Read the deliberately simple, direct parameters.paths list; fail closed on changes."""
    lines = config.read_text().splitlines()
    if any(line.lstrip().startswith(("includes:", "excludePaths:")) for line in lines):
        raise ValueError("Imported or excluded PHPStan paths need coverage review")
    if lines.count("    paths:") != 1:
        raise ValueError("Expected one direct PHPStan parameters.paths list")
    start = lines.index("    paths:") + 1
    paths = []
    for line in lines[start:]:
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if not line.startswith("        "):
            break
        if not line.startswith("        - ") or not line[10:].strip():
            raise ValueError("Unexpected PHPStan path syntax; review coverage parser")
        value = line[10:].strip()
        if any(character in value for character in "#*{}[]$'\"") or value.startswith(("/", "../")):
            raise ValueError("Unexpected PHPStan path; review coverage parser")
        paths.append(value.rstrip("/"))
    if not paths:
        raise ValueError("PHPStan has no direct analysis paths")
    return paths


def shipped_php(root, manifest):
    files = set()
    for raw in manifest.read_text().splitlines():
        entry = raw.strip()
        if not entry or entry.startswith("#"):
            continue
        if entry != raw or entry.startswith(("/", ".")) or ".." in Path(entry).parts:
            raise ValueError(f"Unexpected release entry: {raw!r}")
        path = root / entry
        if entry.endswith("/"):
            if not path.is_dir():
                raise ValueError(f"Missing release directory: {entry}")
            files.update(p.relative_to(root).as_posix() for p in path.rglob("*.php") if p.is_file())
        elif path.is_file():
            if path.suffix == ".php":
                files.add(entry)
        else:
            raise ValueError(f"Missing release file: {entry}")
    if not files:
        raise ValueError("Release manifest contains no PHP")
    return files


def uncovered(files, paths):
    return sorted(path for path in files if not any(
        path == selected or path.startswith(selected + "/") for selected in paths
    ))


paths = direct_paths(ROOT / "phpstan.neon.dist")
files = shipped_php(ROOT, ROOT / "release-contents.txt")
missing = uncovered(files, paths)
if missing:
    raise SystemExit(f"Shipped PHP outside direct PHPStan paths: {', '.join(missing)}")

# A future root file added to the archive must make this gate fail until
# PHPStan explicitly selects it. Keep the fixture out of the source tree.
with tempfile.TemporaryDirectory(prefix="turnstile-phpstan-coverage-") as temporary:
    root = Path(temporary)
    (root / "new-entry.php").write_text("<?php\n")
    manifest = root / "release-contents.txt"
    manifest.write_text("new-entry.php\n")
    if uncovered(shipped_php(root, manifest), paths) != ["new-entry.php"]:
        raise SystemExit("Uncovered root PHP negative fixture did not fail")

print(f"Direct PHPStan paths cover {len(files)} shipped PHP files; negative fixture passed.")
