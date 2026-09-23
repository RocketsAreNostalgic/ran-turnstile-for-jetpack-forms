import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const release = readFileSync(
	new URL('../.github/workflows/release-please.yml', import.meta.url),
	'utf8'
);
const observer = readFileSync(
	new URL('../.github/workflows/deploy-wordpress-org.yml', import.meta.url),
	'utf8'
);
const contract = observer
	.split('\n  contract:\n')[1]
	?.split('\n  deploy:\n')[0];
const deploy = observer.split('\n  deploy:\n')[1];

test('release entry point is the pinned Profile B wrapper for main Quality', () => {
	assert.match(
		release,
		/on:\n  workflow_run:\n    workflows: \[Quality\]\n    types: \[completed\]\n    branches: \[main\]/
	);
	assert.doesNotMatch(release, /workflow_dispatch|--clobber|release upload/);
	assert.match(release, /permissions: \{\}/);
	assert.match(
		release,
		/uses: RocketsAreNostalgic\/\.github\/\.github\/workflows\/release-profile-b\.yml@e2fb19244a301a62f8fae2a80536898adf21fe22/
	);
	for (const input of [
		'expected-workflow-path: .github/workflows/quality.yml',
		'release-pr-head: release-please--branches--main--components--ran-turnstile-for-jetpack-forms',
		'artifact-prefix: ran-turnstile-for-jetpack-forms-release',
	]) {
		assert.ok(release.includes(input), `Missing Profile B input: ${input}`);
	}
});

test('WordPress.org observer admits only the exact successful release workflow', () => {
	assert.ok(contract && deploy, 'Expected contract and deploy jobs');
	assert.match(
		observer,
		/on:\n  workflow_run:\n    workflows: \[Release Please\]\n    types: \[completed\]\n    branches: \[main\]/
	);
	assert.doesNotMatch(
		observer,
		/workflow_dispatch|--allow-disabled|--clobber/
	);
	assert.match(observer, /permissions: \{\}/);
	const predicate = contract.match(/if: >-\n\s+\$\{\{([\s\S]*?)\}\}/)?.[1];
	assert.ok(predicate, 'Expected a contract admission predicate');
	assert.deepEqual(
		predicate
			.split('&&')
			.map((term) => term.trim())
			.sort(),
		[
			"github.event.workflow_run.event == 'workflow_run'",
			"github.event.workflow_run.conclusion == 'success'",
			"github.event.workflow_run.head_branch == 'main'",
			'github.event.workflow_run.head_repository.full_name == github.repository',
			'github.event.workflow_run.head_repository.id == github.repository_id',
			"github.event.workflow_run.path == '.github/workflows/release-please.yml'",
		].sort()
	);
	assert.match(contract, /permissions:\n      contents: read/);
	assert.match(
		contract,
		/ref: \$\{\{ github\.event\.workflow_run\.head_sha \}\}/
	);
	assert.match(
		contract,
		/test "\$\(git rev-parse HEAD\)" = "\$RAN_ADMITTED_SHA"/
	);
	assert.match(
		contract,
		/\.target_commitish == \$sha and \.draft == false and \.immutable == true/
	);
	assert.match(
		contract,
		/\.object\.type == "commit" and \.object\.sha == \$sha/
	);
	assert.match(contract, /\.enabled \| type == "boolean"/);
	assert.match(
		contract,
		/if \[\[ "\$count" == 0 \]\]; then echo 'deploy-required=false'/
	);
});

test('SVN execution requires enabled policy and exact immutable two-asset readback', () => {
	assert.ok(deploy, 'Expected the protected SVN deployment job');
	assert.match(
		deploy,
		/if: \$\{\{ needs\.contract\.outputs\.deploy-required == 'true' && needs\.contract\.outputs\.enabled == 'true' \}\}/
	);
	assert.match(deploy, /environment: wordpress-org/);
	assert.match(deploy, /permissions:\n      contents: read/);
	assert.match(deploy, /ref: \$\{\{ env\.TAG_NAME \}\}/);
	assert.match(deploy, /test "\$\(git rev-parse HEAD\)" = "\$ADMITTED_SHA"/);
	assert.match(deploy, /for asset in "\$archive" "\$checksum"; do/);
	assert.match(
		deploy,
		/\.target_commitish == \$commit and \.draft == false and \.immutable == true/
	);
	assert.match(deploy, /\[\.assets\[\]\.name\] \| sort/);
	assert.match(deploy, /test "\$local_digest" = "\$remote_digest"/);
	assert.match(deploy, /sha256sum --check --strict "\$checksum"/);
	assert.doesNotMatch(deploy, /manifest\.json|--allow-disabled/);
});
