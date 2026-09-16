import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile('.github/workflows/release-please.yml', 'utf8');
const packageStart = workflow.indexOf('\n  package-release:\n');
const deployStart = workflow.indexOf(
	'\n  deploy-wordpress-org:\n',
	packageStart
);

assert.ok(packageStart > 0, 'package-release job is missing');
assert.ok(deployStart > packageStart, 'deploy-wordpress-org job is missing');

const recovery = workflow.slice(packageStart, deployStart);

test('manual recovery retains its bounded write permission and historical tag checkout', () => {
	assert.match(recovery, /permissions:\n\s+contents: write/);
	assert.match(recovery, /ref: \$\{\{ env\.TAG_NAME \}\}/);
});

test('historical build code runs without GitHub release credentials', () => {
	const buildStart = recovery.indexOf(
		'- name: Rebuild canonical assets without release credentials'
	);
	const publishStart = recovery.indexOf(
		'- name: Replace and read back exact release assets',
		buildStart
	);
	assert.ok(buildStart > 0, 'credential-free build step is missing');
	assert.ok(publishStart > buildStart, 'trusted publication step is missing');
	const buildStep = recovery.slice(buildStart, publishStart);
	assert.match(
		buildStep,
		/env -u GH_TOKEN -u GITHUB_TOKEN bash scripts\/create-release-assets\.sh "\$TAG_NAME"/
	);
	assert.doesNotMatch(buildStep, /GH_TOKEN:\s*\$\{\{/);
});

test('manual recovery proves exact target, asset set, and published digests', () => {
	for (const contract of [
		'git rev-parse "${TAG_NAME}^{commit}"',
		'.target_commitish == $commit',
		'.immutable == false',
		'([.assets[].name] - $expected) | length == 0',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
		'gh release upload "$TAG_NAME" "${assets[@]}" --clobber',
		'upload_status=$?',
		'if [[ "$verified" != true ]]',
	]) {
		assert.ok(
			recovery.includes(contract),
			`missing recovery contract: ${contract}`
		);
	}
});

test('recovery proof stays in the current workflow rather than a tag-only helper', () => {
	assert.doesNotMatch(recovery, /bash scripts\/recover-release-assets\.sh/);
});
