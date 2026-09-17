import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile(
	'.github/workflows/reconcile-v0-4-0.yml',
	'utf8'
);

const buildStart = workflow.indexOf('\n  build:\n');
const compatibilityStart = workflow.indexOf('\n  compatibility:\n', buildStart);
const pluginCheckStart = workflow.indexOf(
	'\n  plugin-check:\n',
	compatibilityStart
);
const publishStart = workflow.indexOf('\n  publish:\n', pluginCheckStart);

assert.ok(buildStart > 0, 'build job is missing');
assert.ok(compatibilityStart > buildStart, 'compatibility job is missing');
assert.ok(pluginCheckStart > compatibilityStart, 'plugin-check job is missing');
assert.ok(publishStart > pluginCheckStart, 'publish job is missing');

const build = workflow.slice(buildStart, compatibilityStart);
const compatibility = workflow.slice(compatibilityStart, pluginCheckStart);
const pluginCheck = workflow.slice(pluginCheckStart, publishStart);
const publisher = workflow.slice(publishStart);

test('one-time reconciliation is pinned to the exact historical release identity', () => {
	for (const invariant of [
		'TAG_NAME: v0.4.0',
		'VERSION: 0.4.0',
		'SOURCE_COMMIT: 89e9ef33dda3356c644ad094abafb1ac2faf63e0',
		"RELEASE_PR: '8'",
		'RELEASE_PR_HEAD: 061aa2911ac8c610740553be5a4152a59072e12d',
	]) {
		assert.ok(
			workflow.includes(invariant),
			`missing identity: ${invariant}`
		);
	}
	assert.match(
		build,
		/github\.event\.workflow_run\.path == '\.github\/workflows\/quality\.yml'/
	);
	assert.match(build, /github\.event\.workflow_run\.head_branch == 'main'/);
	assert.match(
		build,
		/github\.event\.workflow_run\.head_repository\.full_name == github\.repository/
	);
});

test('historical source qualification is tokenless, exact, and deployment-disabled', () => {
	assert.match(build, /permissions: \{\}/);
	assert.doesNotMatch(build, /GH_TOKEN|GITHUB_TOKEN|secrets\.GITHUB_TOKEN/);
	assert.match(
		build,
		/git -c protocol\.version=2 fetch --no-tags --depth=1 origin "\$SOURCE_COMMIT"/
	);
	assert.match(build, /test "\$\(git rev-parse HEAD\)" = "\$SOURCE_COMMIT"/);
	assert.match(
		build,
		/test "\$\(jq -r '\.enabled' wordpress-org\/deployment\.json\)" = false/
	);
	assert.match(build, /bash scripts\/create-release-assets\.sh "\$TAG_NAME"/);
	assert.match(
		build,
		/\.archive == \$archive and \.commit == \$commit and \.sha256 == \$sha256 and \.tag == \$tag and \.version == \$version/
	);
	assert.match(
		build,
		/actions\/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a/
	);
});

test('publication is gated on fresh compatibility and the current scoped Plugin Check contract', () => {
	assert.match(
		compatibility,
		/matrix:\n\s+include:\n\s+- php: '8\.0'[\s\S]*wordpress: '6\.5'[\s\S]*jetpack: '13\.3\.1'[\s\S]*- php: '8\.5'[\s\S]*wordpress: latest[\s\S]*jetpack: latest/
	);
	assert.match(
		pluginCheck,
		/PLUGIN_CHECK_CORE_REF: WordPress\/WordPress#7\.0\.3/
	);
	assert.match(pluginCheck, /PLUGIN_CHECK_WP_ENV_VERSION: 11\.13\.0/);
	assert.match(
		pluginCheck,
		/\.github\/scripts\/filter-plugin-check-results\.mjs/
	);
	assert.match(pluginCheck, /docker run --rm --network none --read-only/);
	assert.match(
		pluginCheck,
		/node:24-bookworm-slim@sha256:ba849c60be29959425b8734d57b8b4b7d56f98edd9504c9af091d5281095a71e/
	);
	assert.doesNotMatch(pluginCheck, /ignore-codes:/);
	assert.match(publisher, /needs: \[build, compatibility, plugin-check\]/);
	assert.match(publisher, /needs\.build\.result == 'success'/);
	assert.match(publisher, /needs\.compatibility\.result == 'success'/);
	assert.match(publisher, /needs\.plugin-check\.result == 'success'/);
});

test('fresh publisher proves exact Release Please identity with explicit read authority', () => {
	assert.match(
		publisher,
		/permissions:\n\s+actions: read\n\s+contents: write\n\s+issues: write\n\s+pull-requests: read/
	);
	assert.doesNotMatch(publisher, /actions\/checkout@/);

	const mutation = publisher.indexOf('gh release create "$TAG_NAME"');
	assert.ok(mutation > 0, 'release creation is missing');

	for (const guard of [
		'.head.sha == $head and .merge_commit_sha == $merge',
		'.user.login == "github-actions[bot]"',
		'.title == "chore(main): release 0.4.0"',
		'.commit.tree.sha',
		'.merge_base_commit.sha == $source',
		'index("autorelease: pending") != null or index("autorelease: tagged") != null',
		'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version',
	]) {
		const position = publisher.indexOf(guard);
		assert.ok(position >= 0, `missing preflight guard: ${guard}`);
		assert.ok(position < mutation, `guard moved after mutation: ${guard}`);
	}
});

test('partial publication is safely resumable without accepting unrelated state', () => {
	for (const invariant of [
		'tag_exists=false',
		'release_exists=false',
		'.object.type == "commit" and .object.sha == $commit',
		'.tag_name == $tag and .target_commitish == $commit and .prerelease == false',
		'([.assets[].name] - $expected) | length == 0',
		'([.assets[].name] | length) == ([.assets[].name] | unique | length)',
		'if [[ "$RELEASE_EXISTS" != true ]]',
		'assets_exact=false',
		'Existing immutable release does not already contain the exact qualified assets.',
		'releases/assets/${existing_asset_id}',
	]) {
		assert.ok(
			publisher.includes(invariant),
			`missing resumability guard: ${invariant}`
		);
	}
});

test('asset mutation is release-ID-bound and publication has bounded exact readback', () => {
	const upload = publisher.indexOf(
		'https://uploads.github.com/repos/${GITHUB_REPOSITORY}/releases/${release_id}/assets?name=${asset_name}'
	);
	assert.ok(upload > 0, 'release-ID-bound asset upload is missing');
	assert.match(publisher, /for delay in 0 2 2 2 2/);
	assert.match(publisher, /test "\$verified" = true/);

	for (const readback of [
		'.id == $release_id and .tag_name == $tag and .target_commitish == $commit and .draft == false and .prerelease == false and .immutable == false',
		'.object.type == "commit" and .object.sha == $commit',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
		'index("autorelease: tagged") != null and index("autorelease: pending") == null',
	]) {
		const position = publisher.indexOf(readback, upload);
		assert.ok(
			position > upload,
			`missing post-publication readback: ${readback}`
		);
	}
});
