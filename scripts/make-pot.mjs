import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const potPath = resolve(root, 'languages/ran-turnstile-for-jetpack-forms.pot');
const previousPot = existsSync(potPath) ? readFileSync(potPath, 'utf8') : '';
const previousCreationDate = previousPot.match(
	/^"POT-Creation-Date: [^\\n]+\\n"$/m
)?.[0];
const result = spawnSync(
	'wp',
	[
		'i18n',
		'make-pot',
		'.',
		'languages/ran-turnstile-for-jetpack-forms.pot',
		'--domain=ran-turnstile-for-jetpack-forms',
		'--exclude=dist,tests,vendor',
	],
	{ cwd: root, stdio: 'inherit' }
);

if (0 !== result.status) {
	process.exit(result.status ?? 1);
}

let pot = readFileSync(potPath, 'utf8');
const projectId =
	/^"Project-Id-Version: RAN Turnstile for Jetpack Forms [^\\n]+\\n"$/m;

if (!projectId.test(pot)) {
	throw new Error('Unable to find the POT Project-Id-Version header.');
}

if (previousCreationDate) {
	pot = pot.replace(
		/^"POT-Creation-Date: [^\\n]+\\n"$/m,
		previousCreationDate
	);
}

writeFileSync(
	potPath,
	pot.replace(
		projectId,
		'"X-Release-Please-Start: x-release-please-start-version\\n"\n$&\n"X-Release-Please-End: x-release-please-end\\n"'
	)
);
