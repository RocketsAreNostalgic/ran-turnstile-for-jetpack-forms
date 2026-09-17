import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const scriptPath = path.join(
	path.dirname(fileURLToPath(import.meta.url)),
	'filter-v0.4.0-plugin-check-drift.mjs'
);

function historicalFinding(overrides = {}) {
	return {
		line: 0,
		column: 0,
		type: 'ERROR',
		code: 'outdated_tested_upto_header',
		message:
			'Tested up to: 7.0 < 7.1. The "Tested up to" value in your plugin is not set to the current version of WordPress.',
		docs: 'https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/#readme-header-information',
		...overrides,
	};
}

function runFilter({ finding = historicalFinding(), readme, includeReadme = true } = {}) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-v040-drift-'));
	const input = path.join(root, 'raw.txt');
	const output = path.join(root, 'filtered.txt');
	fs.writeFileSync(
		path.join(root, 'readme.txt'),
		readme ??
			'=== RAN Turnstile for Jetpack Forms ===\nTested up to: 7.0\nStable tag: 0.4.0\n'
	);

	const records = [];
	if (includeReadme) {
		records.push('FILE: readme.txt', JSON.stringify([finding]));
	}
	records.push(
		'FILE: includes/Turnstile.php',
		JSON.stringify([
			{
				line: 19,
				column: 25,
				type: 'ERROR',
				code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
				message: 'Remote resource detected.',
				docs: '',
			},
		])
	);
	fs.writeFileSync(input, `${records.join('\n')}\n`);

	const result = spawnSync(
		process.execPath,
		[scriptPath, input, output, root],
		{ encoding: 'utf8' }
	);
	return {
		...result,
		output: fs.existsSync(output) ? fs.readFileSync(output, 'utf8') : '',
	};
}

test('accepts exactly the historical v0.4.0 Tested up to drift as visible warning evidence', () => {
	const result = runFilter();
	assert.equal(result.status, 0, result.stderr);
	assert.match(result.stderr, /Accepted exactly one historical v0\.4\.0/);
	assert.match(result.output, /^FILE: readme\.txt$/m);
	assert.match(result.output, /"line":5/);
	assert.match(result.output, /"column":1/);
	assert.match(result.output, /"type":"WARNING"/);
	assert.match(result.output, /Accepted historical v0\.4\.0 metadata drift/);
	assert.match(result.output, /"historical_line":0/);
	assert.match(result.output, /^FILE: includes\/Turnstile\.php$/m);
	assert.match(
		result.output,
		/PluginCheck\.CodeAnalysis\.Offloading\.OffloadedContent/
	);
});

test('rejects a different historical readme finding', () => {
	const result = runFilter({
		finding: historicalFinding({ code: 'different_code' }),
	});
	assert.notEqual(result.status, 0);
	assert.equal(result.output, '');
	assert.match(result.stderr, /Unexpected Plugin Check finding/);
});

test('rejects a different WordPress freshness comparison', () => {
	const result = runFilter({
		finding: historicalFinding({
			message: 'Tested up to: 7.0 < 7.2. Future drift.',
		}),
	});
	assert.notEqual(result.status, 0);
	assert.match(result.stderr, /Unexpected Plugin Check finding/);
});

test('rejects if the historical readme no longer declares Tested up to 7.0', () => {
	const result = runFilter({
		readme:
			'=== RAN Turnstile for Jetpack Forms ===\nTested up to: 7.1\nStable tag: 0.4.0\n',
	});
	assert.notEqual(result.status, 0);
	assert.match(result.stderr, /no longer declares Tested up to: 7\.0/);
});

test('rejects if the historical readme no longer declares Stable tag 0.4.0', () => {
	const result = runFilter({
		readme:
			'=== RAN Turnstile for Jetpack Forms ===\nTested up to: 7.0\nStable tag: 0.4.1\n',
	});
	assert.notEqual(result.status, 0);
	assert.match(result.stderr, /no longer declares Stable tag: 0\.4\.0/);
});

test('rejects if the historical freshness finding disappears', () => {
	const result = runFilter({ includeReadme: false });
	assert.notEqual(result.status, 0);
	assert.match(result.stderr, /exactly once but found 0/);
});
