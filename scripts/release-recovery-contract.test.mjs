import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile('.github/workflows/release-please.yml', 'utf8');

test('manual recovery proves exact tag, release target, asset set, and digests inline', () => {
	for (const contract of [
		'git rev-parse "${TAG_NAME}^{commit}"',
		'.target_commitish == $commit',
		'.immutable == false',
		'([.assets[].name] - $expected) | length == 0',
		"[.assets[].name] | sort",
		"[.assets[] | {name, digest}] | sort_by(.name)",
		'gh release upload "$TAG_NAME" "${assets[@]}" --clobber',
	]) {
		assert.ok(workflow.includes(contract), `missing recovery contract: ${contract}`);
	}
});

test('manual recovery stays in the workflow that survives historical tag checkout', () => {
	assert.doesNotMatch(workflow, /bash scripts\/recover-release-assets\.sh/);
	assert.match(workflow, /ref: \$\{\{ env\.TAG_NAME \}\}/);
});
