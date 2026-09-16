import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile('.github/workflows/release-please.yml', 'utf8');
const buildStart = workflow.indexOf('\n  package-release-build:\n');
const publishStart = workflow.indexOf('\n  package-release:\n', buildStart + 1);
const deployStart = workflow.indexOf(
	'\n  deploy-wordpress-org:\n',
	publishStart
);

assert.ok(buildStart > 0, 'package-release-build job is missing');
assert.ok(publishStart > buildStart, 'package-release job is missing');
assert.ok(deployStart > publishStart, 'deploy-wordpress-org job is missing');

const build = workflow.slice(buildStart, publishStart);
const publisher = workflow.slice(publishStart, deployStart);

test('historical release code runs in a separate job without repository token permissions', () => {
	assert.match(build, /permissions: \{\}/);
	assert.doesNotMatch(build, /GH_TOKEN|GITHUB_TOKEN|secrets\.GITHUB_TOKEN/);
	assert.match(
		build,
		/git -c protocol\.version=2 fetch --no-tags --depth=1 origin "refs\/tags\/\$\{TAG_NAME\}:refs\/tags\/\$\{TAG_NAME\}"/
	);
	assert.match(build, /git checkout --detach "\$TAG_NAME"/);
	assert.match(build, /git rev-parse "\$\{TAG_NAME\}\^\{commit\}"/);
	assert.match(build, /bash scripts\/create-release-assets\.sh "\$TAG_NAME"/);
	assert.match(build, /actions\/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a/);
});

test('write-capable recovery publisher starts fresh and consumes only the rebuilt artifact', () => {
	assert.match(publisher, /needs: package-release-build/);
	assert.match(publisher, /permissions:\n\s+actions: read\n\s+contents: write/);
	assert.match(
		publisher,
		/actions\/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c/
	);
	assert.doesNotMatch(publisher, /actions\/checkout@/);
	assert.doesNotMatch(
		publisher,
		/composer install|scripts\/create-release-assets\.sh|node scripts\/make-pot\.mjs/
	);
});

test('manual recovery keeps exact guards before mutation and exact readback after it', () => {
	const upload = publisher.indexOf(
		'gh release upload "$TAG_NAME" "${assets[@]}" --clobber'
	);
	assert.ok(upload > 0, 'release asset mutation is missing');

	for (const precondition of [
		'commit="$(jq -er',
		'tag_ref_before="$(gh api',
		'release_before="$(gh api',
		'.tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false',
		'([.assets[].name] - $expected) | length == 0',
		'release_id="$(jq -er',
		'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version',
	]) {
		const position = publisher.indexOf(precondition);
		assert.ok(position >= 0, `missing pre-mutation guard: ${precondition}`);
		assert.ok(
			position < upload,
			`pre-mutation guard moved after upload: ${precondition}`
		);
	}

	for (const postcondition of [
		'release_after="$(gh api',
		'tag_ref_after="$(gh api',
		'.id == $release_id and .tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
		'if [[ "$actual_assets" == "$expected" && "$remote_digests" == "$local_digests" ]]',
		'if [[ "$verified" != true ]]',
	]) {
		const position = publisher.indexOf(postcondition, upload + 1);
		assert.ok(
			position > upload,
			`missing post-mutation readback: ${postcondition}`
		);
	}

	assert.ok(
		publisher.indexOf('upload_status=$?', upload) > upload,
		'upload status must be captured before final readback completes'
	);
});

test('recovery proof stays in the current workflow rather than a tag-only helper', () => {
	assert.doesNotMatch(workflow, /bash scripts\/recover-release-assets\.sh/);
});
