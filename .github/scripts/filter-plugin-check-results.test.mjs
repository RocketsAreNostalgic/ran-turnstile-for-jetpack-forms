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

function runFilter({ file, sourceLine, findings }) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-plugin-check-'));
	const sourcePath = path.join(root, file);
	fs.mkdirSync(path.dirname(sourcePath), { recursive: true });
	fs.writeFileSync(sourcePath, `${sourceLine}\n`);

	const input = path.join(root, 'raw.txt');
	const output = path.join(root, 'filtered.txt');
	fs.writeFileSync(input, `FILE: ${file}\n${JSON.stringify(findings)}\n`);

	const result = spawnSync(process.execPath, [scriptPath, input, output, root], {
		encoding: 'utf8',
	});

	return {
		...result,
		output: fs.existsSync(output) ? fs.readFileSync(output, 'utf8') : '',
	};
}

function finding(overrides = {}) {
	return {
		line: 1,
		column: 1,
		type: 'ERROR',
		code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
		message: 'Remote resource detected.',
		docs: '',
		...overrides,
	};
}

test('downgrades a known Turnstile URL finding to a warning', () => {
	const result = runFilter({
		file: 'includes/Turnstile.php',
		sourceLine:
			"const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';",
		findings: [finding()],
	});

	assert.equal(result.status, 0, result.stderr);
	assert.match(result.output, /"type":"WARNING"/);
	assert.match(result.output, /Accepted Cloudflare Turnstile dependency/);
});

test('keeps the same generic code as an error for an unapproved URL', () => {
	const result = runFilter({
		file: 'includes/Turnstile.php',
		sourceLine: "const URL = 'https://example.invalid/unapproved.js';",
		findings: [finding()],
	});

	assert.equal(result.status, 0, result.stderr);
	assert.match(result.output, /"type":"ERROR"/);
	assert.doesNotMatch(result.output, /Accepted Cloudflare Turnstile dependency/);
});

test('does not accept a URL that only prefixes an allowlisted URL', () => {
	const result = runFilter({
		file: 'includes/Turnstile.php',
		sourceLine:
			"const URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js.example';",
		findings: [finding()],
	});

	assert.equal(result.status, 0, result.stderr);
	assert.match(result.output, /"type":"ERROR"/);
	assert.doesNotMatch(result.output, /Accepted Cloudflare Turnstile dependency/);
});

test('keeps non-offloading errors unchanged', () => {
	const result = runFilter({
		file: 'includes/Turnstile.php',
		sourceLine:
			"const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';",
		findings: [finding({ code: 'PluginCheck.SomeOther.Error' })],
	});

	assert.equal(result.status, 0, result.stderr);
	assert.match(result.output, /"type":"ERROR"/);
	assert.match(result.output, /PluginCheck\.SomeOther\.Error/);
});

test('accepts an absolute reported path when it remains under the source root', () => {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-plugin-check-'));
	const sourcePath = path.join(root, 'includes', 'Turnstile.php');
	fs.mkdirSync(path.dirname(sourcePath), { recursive: true });
	fs.writeFileSync(
		sourcePath,
		"const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';\n"
	);

	const input = path.join(root, 'raw.txt');
	const output = path.join(root, 'filtered.txt');
	fs.writeFileSync(input, `FILE: ${sourcePath}\n${JSON.stringify([finding()])}\n`);

	const result = spawnSync(process.execPath, [scriptPath, input, output, root], {
		encoding: 'utf8',
	});
	const filtered = fs.existsSync(output) ? fs.readFileSync(output, 'utf8') : '';

	assert.equal(result.status, 0, result.stderr);
	assert.match(filtered, /^FILE: includes\/Turnstile\.php/m);
	assert.match(filtered, /"type":"WARNING"/);
});

test('rejects an unsafe source path before accepting a finding', () => {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ran-plugin-check-'));
	const input = path.join(root, 'raw.txt');
	const output = path.join(root, 'filtered.txt');
	fs.writeFileSync(input, `FILE: ../secret.php\n${JSON.stringify([finding()])}\n`);

	const result = spawnSync(process.execPath, [scriptPath, input, output, root], {
		encoding: 'utf8',
	});

	assert.notEqual(result.status, 0);
	assert.match(result.stderr, /Unsafe Plugin Check path/);
});
