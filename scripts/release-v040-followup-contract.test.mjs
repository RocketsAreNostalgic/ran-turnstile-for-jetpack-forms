import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const workflow = await readFile(
	'.github/workflows/reconcile-v0.4.0-followup.yml',
	'utf8'
);
const historicalFilter = await readFile(
	'.github/scripts/filter-v040-historical-plugin-check.mjs',
	'utf8'
);

const pluginCheckStart = workflow.indexOf('\n  plugin-check:\n');
const publishStart = workflow.indexOf('\n  publish:\n', pluginCheckStart + 1);
assert.ok(pluginCheckStart > 0, 'plugin-check job is missing');
assert.ok(publishStart > pluginCheckStart, 'publish job is missing');

const pluginCheck = workflow.slice(pluginCheckStart, publishStart);
const publisher = workflow.slice(publishStart);

test('follow-up is one-time canonical workflow_run reconciliation', () => {
	assert.doesNotMatch(workflow, /workflow_dispatch:/);
	assert.match(workflow, /workflows: \[Quality\]/);
	assert.match(workflow, /branches: \[main\]/);
	assert.match(workflow, /permissions: \{\}/);
	assert.match(workflow, /group: release-please-main/);
	assert.match(workflow, /RAN_SOURCE_RUN: '35239281005'/);
	assert.match(
		workflow,
		/RAN_SOURCE_HEAD: f34baa0da9360f61a20cd1b389187dedb7af2df4/
	);
	assert.match(
		workflow,
		/RAN_HISTORICAL_COMMIT: 89e9ef33dda3356c644ad094abafb1ac2faf63e0/
	);
	assert.match(
		workflow,
		/RAN_RELEASE_HEAD: 061aa2911ac8c610740553be5a4152a59072e12d/
	);
	assert.match(workflow, /RAN_RELEASE_PR: '8'/);
	assert.match(workflow, /RAN_RELEASE_TAG: v0\.4\.0/);
});

test('Plugin Check reuses the exact already compatibility-tested artifact', () => {
	assert.match(
		pluginCheck,
		/permissions:\n\s+actions: read\n\s+contents: read/
	);
	assert.match(
		pluginCheck,
		/repos\/\$\{GITHUB_REPOSITORY\}\/actions\/runs\/\$\{RAN_SOURCE_RUN\}/
	);
	assert.match(pluginCheck, /Rebuild exact historical v0\.4\.0 source/);
	assert.match(pluginCheck, /Historical v0\.4\.0 \/ Plugin Check/);
	assert.match(pluginCheck, /name: \$\{\{ env\.RAN_ARTIFACT_NAME \}\}/);
	assert.match(pluginCheck, /run-id: \$\{\{ env\.RAN_SOURCE_RUN \}\}/);
});

test('historical metadata exception is exact and the normal Turnstile filter still runs', () => {
	assert.match(
		historicalFilter,
		/const normalizedFile = currentFile\.replaceAll\('\\\\', '\/'\)/
	);
	assert.match(historicalFilter, /normalizedFile === 'readme\.txt'/);
	assert.match(historicalFilter, /normalizedFile\.endsWith\('\/readme\.txt'\)/);
	assert.match(
		historicalFilter,
		/finding\?\.code === 'outdated_tested_upto_header'/
	);
	assert.match(historicalFilter, /finding\?\.line === 0/);
	assert.match(historicalFilter, /finding\?\.column === 0/);
	assert.match(
		historicalFilter,
		/finding\.message\.startsWith\('Tested up to: 7\.0 < '\)/
	);
	assert.match(historicalFilter, /if \(accepted !== 1\)/);
	assert.match(
		pluginCheck,
		/node \.github\/scripts\/filter-v040-historical-plugin-check\.mjs/
	);
	assert.match(pluginCheck, /node \/filter\.mjs \/raw\.txt - \/source/);
});

test('publisher is source-free and mutates only the exact historical release identity', () => {
	assert.match(
		publisher,
		/permissions:\n\s+actions: read\n\s+contents: write\n\s+issues: write\n\s+pull-requests: read/
	);
	assert.doesNotMatch(publisher, /actions\/checkout@/);
	assert.doesNotMatch(
		publisher,
		/composer install|create-release-assets|make-pot\.mjs/
	);
	assert.match(publisher, /live_main="\$\(gh api/);
	assert.match(publisher, /pulls\/\$\{RAN_RELEASE_PR\}/);
	assert.match(publisher, /git\/commits\/\$\{RAN_HISTORICAL_COMMIT\}/);
	assert.match(publisher, /git\/commits\/\$\{RAN_RELEASE_HEAD\}/);
	assert.match(
		publisher,
		/v0\.4\.0 has a partial or duplicate tag\/release state/
	);
	assert.match(
		publisher,
		/https:\/\/uploads\.github\.com\/repos\/\$\{GITHUB_REPOSITORY\}\/releases\/\$\{RELEASE_ID\}\/assets\?name=\$\{asset_name\}/
	);
	assert.match(
		publisher,
		/\[\.assets\[\] \| \{name,digest\}\] \| sort_by\(\.name\)/
	);
	assert.match(
		publisher,
		/Reconcile Release Please PR labels only after exact publication readback/
	);
});
