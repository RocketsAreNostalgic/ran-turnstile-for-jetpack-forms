import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile('.github/workflows/release-please.yml', 'utf8');
const recovery = await readFile('scripts/recover-release-assets.sh', 'utf8');

test('manual recovery delegates to the fail-closed provenance script', () => {
	assert.match(workflow, /bash scripts\/recover-release-assets\.sh \"\$TAG_NAME\"/);
	assert.match(workflow, /contents: write/);
});

test('recovery proves exact tag, release target, asset set, and digests', () => {
	for (const contract of [
		'git rev-parse "${TAG_NAME}^{commit}"',
		'.target_commitish == $commit',
		'.immutable == false',
		"[.assets[].name] | sort",
		'.assets[] | select(.name == $name) | .digest',
		'gh release upload "${TAG_NAME}" "${assets[@]}" --clobber',
	]) {
		assert.ok(recovery.includes(contract), `missing recovery contract: ${contract}`);
	}
});
