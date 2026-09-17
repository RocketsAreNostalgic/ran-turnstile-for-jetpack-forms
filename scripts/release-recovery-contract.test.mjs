import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile('.github/workflows/release-please.yml', 'utf8');
const reconciliation = await readFile(
	'.github/workflows/reconcile-v0.4.0.yml',
	'utf8'
);
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
const checksumIdentity = `printf '%s  %s\\n' "$archive_sha256" "$(basename "$archive")" | cmp -s - "$checksum"`;

const reconciliationRebuildStart = reconciliation.indexOf('\n  rebuild:\n');
const reconciliationCompatibilityStart = reconciliation.indexOf(
	'\n  compatibility:\n',
	reconciliationRebuildStart + 1
);
const reconciliationPluginCheckStart = reconciliation.indexOf(
	'\n  plugin-check:\n',
	reconciliationCompatibilityStart + 1
);
const reconciliationPublishStart = reconciliation.indexOf(
	'\n  publish:\n',
	reconciliationPluginCheckStart + 1
);

assert.ok(
	reconciliationRebuildStart > 0,
	'v0.4.0 reconciliation rebuild job is missing'
);
assert.ok(
	reconciliationCompatibilityStart > reconciliationRebuildStart,
	'v0.4.0 reconciliation compatibility job is missing'
);
assert.ok(
	reconciliationPluginCheckStart > reconciliationCompatibilityStart,
	'v0.4.0 reconciliation Plugin Check job is missing'
);
assert.ok(
	reconciliationPublishStart > reconciliationPluginCheckStart,
	'v0.4.0 reconciliation publisher job is missing'
);

const reconciliationRebuild = reconciliation.slice(
	reconciliationRebuildStart,
	reconciliationCompatibilityStart
);
const reconciliationCompatibility = reconciliation.slice(
	reconciliationCompatibilityStart,
	reconciliationPluginCheckStart
);
const reconciliationPluginCheck = reconciliation.slice(
	reconciliationPluginCheckStart,
	reconciliationPublishStart
);
const reconciliationPublisher = reconciliation.slice(
	reconciliationPublishStart
);

test('manual recovery is defense-in-depth bound to protected main', () => {
	assert.match(
		build,
		/if: github\.event_name == 'workflow_dispatch' && github\.ref == 'refs\/heads\/main'/
	);
	assert.match(
		publisher,
		/if: github\.event_name == 'workflow_dispatch' && github\.ref == 'refs\/heads\/main' && needs\.package-release-build\.result == 'success'/
	);
});

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
	assert.ok(
		build.includes(checksumIdentity),
		'historical build must bind the checksum file to the exact archive digest and filename'
	);
	assert.match(
		build,
		/actions\/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a/
	);
	assert.match(
		build,
		/name: ran-turnstile-recovery-\$\{\{ github\.run_id \}\}/
	);
	assert.match(build, /overwrite: true/);
	assert.doesNotMatch(build, /ran-turnstile-recovery-.*run_attempt/);
});

test('write-capable recovery publisher starts fresh and consumes only the rebuilt artifact', () => {
	assert.match(publisher, /needs: package-release-build/);
	assert.match(
		publisher,
		/permissions:\n\s+actions: read\n\s+contents: write/
	);
	assert.match(
		publisher,
		/actions\/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c/
	);
	assert.match(
		publisher,
		/name: ran-turnstile-recovery-\$\{\{ github\.run_id \}\}/
	);
	assert.doesNotMatch(publisher, /ran-turnstile-recovery-.*run_attempt/);
	assert.doesNotMatch(publisher, /actions\/checkout@/);
	assert.doesNotMatch(
		publisher,
		/composer install|scripts\/create-release-assets\.sh|node scripts\/make-pot\.mjs/
	);
});

test('manual recovery keeps exact guards before mutation and exact readback after it', () => {
	const upload = publisher.indexOf(
		'https://uploads.github.com/repos/${GITHUB_REPOSITORY}/releases/${release_id}/assets?name=${asset_name}'
	);
	assert.ok(upload > 0, 'release-ID-bound asset mutation is missing');
	assert.doesNotMatch(
		publisher,
		/gh release upload "\$TAG_NAME"/,
		'recovery must not re-resolve the release by mutable tag during mutation'
	);

	for (const precondition of [
		'commit="$(jq -er',
		'resolve_tag_commit() {',
		'tag_ref_before="$(gh api',
		'test "$(resolve_tag_commit "$tag_ref_before")" = "$commit"',
		'release_before="$(gh api',
		'.tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false',
		'([.assets[].name] | length) == ([.assets[].name] | unique | length)',
		'([.assets[].name] - $expected) | length == 0',
		'release_id="$(jq -er',
		'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version',
		checksumIdentity,
		'releases/assets/${existing_asset_id}',
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
		'test "$(resolve_tag_commit "$tag_ref_after")" = "$commit"',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
		'if [[ "$actual_assets" == "$expected" && "$remote_digests" == "$local_digests" ]]',
		'if [[ "$verified" != true ]]',
		'if [[ "$mutation_status" -ne 0 ]]',
	]) {
		const position = publisher.indexOf(postcondition, upload + 1);
		assert.ok(
			position > upload,
			`missing post-mutation readback: ${postcondition}`
		);
	}
});

test('recovery proof stays in the current workflow rather than a tag-only helper', () => {
	assert.doesNotMatch(workflow, /bash scripts\/recover-release-assets\.sh/);
});

test('one-time v0.4.0 reconciliation is canonical and hard-coded to the historical Release Please identity', () => {
	assert.doesNotMatch(reconciliation, /workflow_dispatch:/);
	assert.match(
		reconciliation,
		/types: \[completed\]\n\s+branches: \[main\]\n\npermissions: \{\}/
	);
	assert.match(reconciliation, /concurrency:\n\s+group: release-please-main\n\s+cancel-in-progress: false/);
	assert.match(
		reconciliation,
		/RAN_HISTORICAL_COMMIT: 89e9ef33dda3356c644ad094abafb1ac2faf63e0/
	);
	assert.match(
		reconciliation,
		/RAN_RELEASE_HEAD: 061aa2911ac8c610740553be5a4152a59072e12d/
	);
	assert.match(
		reconciliation,
		/RAN_RELEASE_TREE: 53bb06653e0f504e4b4547eb4d939de9a8083eba/
	);
	assert.match(reconciliation, /RAN_RELEASE_PR: '8'/);
	assert.match(reconciliation, /RAN_RELEASE_TAG: v0\.4\.0/);
	assert.match(
		reconciliation,
		/github\.event\.workflow_run\.path == '\.github\/workflows\/quality\.yml'/
	);
	assert.match(
		reconciliation,
		/github\.event\.workflow_run\.head_repository\.full_name == github\.repository/
	);
});

test('one-time v0.4.0 rebuild executes historical source without mutation authority', () => {
	assert.match(reconciliationRebuild, /permissions: \{\}/);
	assert.doesNotMatch(
		reconciliationRebuild,
		/GH_TOKEN|GITHUB_TOKEN|secrets\.GITHUB_TOKEN/
	);
	assert.match(
		reconciliationRebuild,
		/git checkout --detach "\$RAN_HISTORICAL_COMMIT"/
	);
	assert.match(
		reconciliationRebuild,
		/git rev-parse 'HEAD\^\{tree\}'\)" = "\$RAN_RELEASE_TREE"/
	);
	assert.match(
		reconciliationRebuild,
		/bash scripts\/create-release-assets\.sh "\$RAN_RELEASE_TAG"/
	);
	assert.match(
		reconciliationRebuild,
		/name: ran-turnstile-v0\.4\.0-reconciliation-\$\{\{ github\.run_id \}\}/
	);
});

test('one-time v0.4.0 PHP provisioning is bounded and retried once', () => {
	for (const lane of [reconciliationRebuild, reconciliationCompatibility]) {
		assert.match(
			lane,
			/id: php\n\s+continue-on-error: true\n\s+timeout-minutes: 3/
		);
		assert.match(
			lane,
			/name: Retry the bounded PHP setup once\n\s+if: steps\.php\.outcome == 'failure'\n\s+uses: shivammathur\/setup-php@f3e473d116dcccaddc5834248c87452386958240[^\n]*\n\s+timeout-minutes: 3/
		);
	}
});

test('one-time v0.4.0 qualification reruns the fixed Plugin Check environment', () => {
	assert.match(
		reconciliationPluginCheck,
		/PLUGIN_CHECK_CORE_REF: WordPress\/WordPress#7\.0\.3/
	);
	assert.match(
		reconciliationPluginCheck,
		/PLUGIN_CHECK_WP_ENV_VERSION: 11\.13\.0/
	);
	assert.match(
		reconciliationPluginCheck,
		/ref: \$\{\{ github\.event\.workflow_run\.head_sha \}\}/
	);
	assert.match(
		reconciliationPluginCheck,
		/node \/filter\.mjs \/raw\.txt - \/source/
	);
});

test('one-time v0.4.0 publisher is source-free and performs exact release-ID-bound readback', () => {
	assert.match(
		reconciliationPublisher,
		/permissions:\n\s+actions: read\n\s+contents: write\n\s+issues: write\n\s+pull-requests: read/
	);
	assert.doesNotMatch(reconciliationPublisher, /actions\/checkout@/);
	assert.doesNotMatch(
		reconciliationPublisher,
		/composer install|scripts\/create-release-assets\.sh|node scripts\/make-pot\.mjs/
	);

	const upload = reconciliationPublisher.indexOf(
		'https://uploads.github.com/repos/${GITHUB_REPOSITORY}/releases/${RELEASE_ID}/assets?name=${asset_name}'
	);
	assert.ok(upload > 0, 'v0.4.0 release-ID-bound upload is missing');

	for (const precondition of [
		'live_main="$(gh api',
		'pulls/${RAN_RELEASE_PR}',
		'.merge_commit_sha == $merge',
		'git/commits/${RAN_HISTORICAL_COMMIT}',
		'git/commits/${RAN_RELEASE_HEAD}',
		'manifest_version="$(gh api',
		'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version',
		'v0.4.0 has a partial tag/release state',
	]) {
		const position = reconciliationPublisher.indexOf(precondition);
		assert.ok(
			position >= 0,
			`missing v0.4.0 precondition: ${precondition}`
		);
		assert.ok(
			position < upload,
			`v0.4.0 precondition moved after upload: ${precondition}`
		);
	}

	for (const postcondition of [
		'releases/tags/${RAN_RELEASE_TAG}',
		'git/ref/tags/${RAN_RELEASE_TAG}',
		'[.assets[].name] | sort',
		'[.assets[] | {name, digest}] | sort_by(.name)',
	]) {
		const position = reconciliationPublisher.indexOf(
			postcondition,
			upload + 1
		);
		assert.ok(
			position > upload,
			`missing v0.4.0 post-publication readback: ${postcondition}`
		);
	}

	const labels = reconciliationPublisher.indexOf(
		'Reconcile Release Please PR labels only after exact publication readback'
	);
	assert.ok(labels > upload, 'Release Please labels must be reconciled last');
});

test('one-time v0.4.0 publisher resumes exact drafts and partial label cleanup safely', () => {
	assert.match(
		reconciliationPublisher,
		/if \[\[ "\$\(jq -er '\.draft' <<< "\$release_json"\)" == true \]\]; then\n\s+state=draft\n\s+else\n\s+state=published/
	);
	assert.match(
		reconciliationPublisher,
		/echo "release-id=\$release_id" >> "\$GITHUB_OUTPUT"/
	);
	assert.match(
		reconciliationPublisher,
		/if: steps\.identity\.outputs\.state == 'absent' \|\| steps\.identity\.outputs\.state == 'draft'/
	);
	assert.match(
		reconciliationPublisher,
		/remote_digest="\$\(jq -er '\.\[0\]\.digest' <<< "\$matches"\)"/
	);
	assert.match(
		reconciliationPublisher,
		/releases\/assets\/\$\{existing_asset_id\}/
	);
	assert.match(
		reconciliationPublisher,
		/name: Verify the complete exact draft before publication/
	);
	assert.match(
		reconciliationPublisher,
		/if \[\[ "\$pending" != true && "\$tagged" != true \]\]; then/
	);
});
