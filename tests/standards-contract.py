#!/usr/bin/env python3
"""Exercise config-driven PHPCS/PHPCBF scope on a disposable source tree."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

def require(condition, message):
    if not condition:
        raise RuntimeError(message)


ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix="turnstile-standards-") as directory:
    root = Path(directory)
    for name in ("composer.json", "phpcs.xml.dist", "ran-turnstile-for-jetpack-forms.php"):
        shutil.copy2(ROOT / name, root / name)
    for name in ("includes", "tests"):
        shutil.copytree(ROOT / name, root / name)

    def run(tool="phpcs", *args):
        return subprocess.run(["php", str(ROOT / "vendor/bin" / tool),
            "--standard=" + str(root / "phpcs.xml.dist"), *args],
            cwd=root, capture_output=True, text=True, timeout=120)

    def snapshot():
        return {str(p.relative_to(root)): hashlib.sha256(p.read_bytes()).hexdigest()
                for p in root.rglob("*") if p.is_file() and (p.suffix == ".php" or p.name.endswith(".php.template"))}

    result = run()
    require(result.returncode == 0, result.stdout + result.stderr)
    clean = snapshot()
    for _ in range(2):
        result = run("phpcbf")
        require(result.returncode == 0, result.stdout + result.stderr)
        require(snapshot() == clean, "Quality contract failed")

    # All three selected roots must reject and fix ordinary formatting errors.
    for relative in ("ran-turnstile-for-jetpack-forms.php", "includes/Settings.php", "tests/wp-tests-config.php.template", "tests/phpstan/bootstrap.php"):
        path = root / relative
        original = path.read_bytes()
        try:
            path.write_bytes(original + b"\n$quality_probe = array(1,2);\n")
            result = run()
            require(result.returncode != 0, relative)
            result = run("phpcbf")
            require(result.returncode in (0, 1), result.stdout + result.stderr)
            fixed = snapshot()
            result = run()
            require(result.returncode == 0, result.stdout + result.stderr)
            result = run("phpcbf")
            require(result.returncode == 0, result.stdout + result.stderr)
            require(snapshot() == fixed, "Quality contract failed")
        finally:
            path.write_bytes(original)
print("Config-driven scope and repeatable fixes passed.")
