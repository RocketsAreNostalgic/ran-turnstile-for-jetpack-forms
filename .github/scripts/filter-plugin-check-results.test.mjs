import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const scriptPath = path.join(
	path.dirname(fileURLToPath(import.meta.url)),
	'filter-plugin-check-results.mjs'
);

const offloadingCode = 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent';
const enqueuedCode =
	'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent';

function finding(code = offloadingCode, overrides = {}) {
	return {
		line: 1,
		column: 1,
		type: 'ERROR',
		code,
		message: 'Remote resource detected.',
		docs: '',
		...overrides,
	};
}

function expectedFixture() {
	return [
		{
			file: 'includes/Turnstile.php',
			sourceLines: [
				"const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';",
				"wp_register_script('https://challenges.cloudflare.com/turnstile/v0/api.js');",
			],
			findings: [
				finding(offloadingCode, { line: 1 }),
				finding(enqueuedCode, { line: 2 }),
			],
		},
		{
			file: 'includes/Admin.php',
			sourceLines: [
				"wp_enqueue_script('https://challenges.cloudflare.com/turnstile/v0/api.js');",
				'<a href="https://developers.cloudflare.com/turnstile/">Docs</a>',
				'<a href="https://developers.cloudflare.com/turnstile/get-started/server-side-validation/">Validation</a>',
				'<a href="https://developers.cloudflare.com/turnstile/troubleshooting/testing/">Testing</a>',
			],
			findings: [
				finding(enqueuedCode, { line: 1 }),
				finding(offloadingCode, { line: 2 }),
				finding(offloadingCode, { line: 3 }),
				finding(offloadingCode, { line: 4 }),
			],
		},
	];
}

function runFilter({ sections = expectedFixture(), absoluteFiles = false, setup }) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-plugin-check-'));
	const input = path.join(root, 'raw.txt');
	const output = path.join(root, 'filtered.txt');
	const records = [];

	for (const section of sections) {
		const sourcePath = path.join(root, section.file);
		fs.mkdirSync(path.dirname(sourcePath), { recursive: true });
		fs.writeFileSync(sourcePath, `${section.sourceLines.join('\n')}\n`);
		records.push(
			`FILE: ${absoluteFiles ? sourcePath : section.file}`,
			JSON.stringify(section.findings)
		);
	}

	setup?.({ root, records });
	fs.writeFileSync(input, `${records.join('\n')}\n`);

	const result = spawnSync(process.execPath, [scriptPath, input, output, root], {
		encoding: 'utf8',
	});

	return {
		...result,
		output: fs.existsSync(output) ? fs.readFileSync(output, 'utf8') : '',
	};
}

function assertGateFailsWithEvidence(result) {
	assert.notEqual(result.status, 0);
	assert.notEqual(result.output, '', 'filtered evidence should be written before failure');
}

test('accepts each of the six expected findings exactly once', () => {
	const result = runFilter({});

	assert.equal(result.status, 0, result.stderr);
	assert.equal(result.output.match(/"type":"WARNING"/g)?.length, 6);
	assert.equal(
		result.output.match(/Accepted Cloudflare Turnstile dependency/g)?.length,
		6
	);
});

test('keeps an allowlisted URL plus an evil URL as an error and fails', () => {
	const sections = expectedFixture();
	sections[0].sourceLines[0] += " 'https://evil.example/tracker.js'";
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.output, /"type":"ERROR"/);
	assert.match(result.stderr, /Filtered Plugin Check results still contain ERROR/);
});

test('keeps an extra unapproved offloading finding as an error and fails', () => {
	const sections = expectedFixture();
	sections.push({
		file: 'includes/Other.php',
		sourceLines: ["const URL = 'https://example.invalid/unapproved.js';"],
		findings: [finding()],
	});
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.output, /"type":"ERROR"/);
});

test('keeps a non-offloading error unchanged and fails', () => {
	const sections = expectedFixture();
	sections[0].findings.push(finding('PluginCheck.SomeOther.Error'));
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.output, /PluginCheck\.SomeOther\.Error/);
	assert.match(result.output, /"type":"ERROR"/);
});

test('fails when an expected accepted finding is missing', () => {
	const sections = expectedFixture();
	sections[1].findings.pop();
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.stderr, /found 0/);
});

test('fails when an expected accepted finding is duplicated', () => {
	const sections = expectedFixture();
	sections[0].findings.push({ ...sections[0].findings[0] });
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.stderr, /found 2/);
});

test('does not accept an allowlisted URL with a parenthesized suffix', () => {
	const sections = expectedFixture();
	sections[1].sourceLines[1] =
		'<a href="https://developers.cloudflare.com/turnstile/(unapproved)">Docs</a>';
	const result = runFilter({ sections });

	assertGateFailsWithEvidence(result);
	assert.match(result.output, /"type":"ERROR"/);
});

test('accepts absolute reported paths that remain under the source root', () => {
	const result = runFilter({ absoluteFiles: true });

	assert.equal(result.status, 0, result.stderr);
	assert.match(result.output, /^FILE: includes\/Turnstile\.php/m);
	assert.match(result.output, /^FILE: includes\/Admin\.php/m);
});

test('rejects a traversing source path', () => {
	const result = runFilter({
		sections: [],
		setup: ({ records }) => {
			records.push(`FILE: ../secret.php`, JSON.stringify([finding()]));
		},
	});

	assert.notEqual(result.status, 0);
	assert.equal(result.output, '');
	assert.match(result.stderr, /Unsafe Plugin Check path/);
});

test('rejects an absolute source path outside the source root', () => {
	const outsideRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-plugin-check-outside-'));
	const outsideFile = path.join(outsideRoot, 'secret.php');
	fs.writeFileSync(outsideFile, "const URL = 'https://example.invalid';\n");

	const result = runFilter({
		sections: [],
		setup: ({ records }) => {
			records.push(`FILE: ${outsideFile}`, JSON.stringify([finding()]));
		},
	});

	assert.notEqual(result.status, 0);
	assert.equal(result.output, '');
	assert.match(result.stderr, /Unsafe Plugin Check path/);
});
