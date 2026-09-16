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

test('manual recovery keeps exact guards before mutation and exact readback after it', () => {
	const publishStart = recovery.indexOf(
		'- name: Replace and read back exact release assets'
	);
	assert.ok(publishStart > 0, 'trusted publication step is missing');
	const publishStep = recovery.slice(publishStart);
	const upload = publishStep.indexOf(
		'gh release upload "$TAG_NAME" "${assets[@]}" --clobber'
	);
	assert.ok(upload > 0, 'release asset mutation is missing');

	for (const precondition of [
		'tag_ref_before="$(gh api',
		'release_before="$(gh api',
		'.id == $release_id and .tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false',
		'([.assets[].name] - $expected) | length == 0',
		'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version',
	]) {
		const position = publishStep.indexOf(precondition);
		assert.ok(position >= 0, `missing pre-mutation guard: ${precondition}`);
		assert.ok(position < upload, `pre-mutation guard moved after upload: ${precondition}`);
	}

	for (const postcondition of [
		'release_after="$(gh api',
		'tag_ref_after="$(gh api',
		'.id == $release_id and .tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
		'if [[ "$verified" != true ]]',
	]) {
		const position = publishStep.indexOf(postcondition, upload + 1);
		assert.ok(position > upload, `missing post-mutation readback: ${postcondition}`);
	}

	assert.ok(
		publishStep.indexOf('upload_status=$?', upload) > upload,
		'upload status must be captured before final readback completes'
	);
});

test('manual recovery proves exact tag identity in both trusted phases', () => {
	assert.ok(
		recovery.indexOf('git rev-parse "${TAG_NAME}^{commit}"') >= 0,
		'exact tag preflight is missing'
	);
	assert.ok(
		recovery.lastIndexOf('git rev-parse "${TAG_NAME}^{commit}"') >
			recovery.indexOf('- name: Replace and read back exact release assets'),
		'exact tag verification is missing from the publication phase'
	);
});

test('recovery proof stays in the current workflow rather than a tag-only helper', () => {
	assert.doesNotMatch(recovery, /bash scripts\/recover-release-assets\.sh/);
});
