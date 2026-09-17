import fs from 'node:fs';
import path from 'node:path';

const [inputPath, outputPath, sourceRoot] = process.argv.slice(2);

if (!inputPath || !outputPath || !sourceRoot) {
	throw new Error(
		'Usage: node filter-v0.4.0-plugin-check-drift.mjs <input> <output|-> <source-root>'
	);
}

const root = fs.realpathSync(path.resolve(sourceRoot));
const readmePath = fs.realpathSync(path.join(root, 'readme.txt'));
if (!readmePath.startsWith(`${root}${path.sep}`)) {
	throw new Error('Historical readme resolved outside the release source root.');
}

const readme = fs.readFileSync(readmePath, 'utf8');
if (!/^Tested up to: 7\.0$/m.test(readme)) {
	throw new Error('Historical v0.4.0 readme no longer declares Tested up to: 7.0.');
}
if (!/^Stable tag: 0\.4\.0$/m.test(readme)) {
	throw new Error('Historical v0.4.0 readme no longer declares Stable tag: 0.4.0.');
}

const raw = fs.readFileSync(inputPath, 'utf8');
const lines = raw.split(/\r?\n/);
const output = [];
let currentFile = null;
let acceptedCount = 0;

for (const line of lines) {
	if (line.startsWith('FILE: ')) {
		if (currentFile) {
			throw new Error(`Missing Plugin Check findings for ${currentFile}`);
		}
		currentFile = line.slice('FILE: '.length).trim();
		output.push(line);
		continue;
	}

	if (currentFile && line.trimStart().startsWith('[')) {
		let findings;
		try {
			findings = JSON.parse(line);
		} catch (error) {
			throw new Error(
				`Invalid Plugin Check JSON for ${currentFile}: ${error.message}`
			);
		}

		if (!Array.isArray(findings)) {
			throw new Error(`Expected Plugin Check array for ${currentFile}`);
		}

		if (currentFile === 'readme.txt') {
			if (acceptedCount !== 0 || findings.length !== 1) {
				throw new Error(
					'Expected exactly one historical v0.4.0 readme finding.'
				);
			}

			const [finding] = findings;
			if (
				!finding ||
				finding.line !== 0 ||
				finding.column !== 0 ||
				finding.type !== 'ERROR' ||
				finding.code !== 'outdated_tested_upto_header' ||
				typeof finding.message !== 'string' ||
				!finding.message.startsWith('Tested up to: 7.0 < 7.1.')
			) {
				throw new Error(
					'Unexpected Plugin Check finding for historical v0.4.0 readme.'
				);
			}

			acceptedCount += 1;
			output.push(
				JSON.stringify([
					{
						...finding,
						line: 5,
						column: 1,
						type: 'WARNING',
						message: `[Accepted historical v0.4.0 metadata drift] ${finding.message}`,
						historical_line: finding.line,
						historical_column: finding.column,
					},
				])
			);
		} else {
			output.push(line);
		}

		currentFile = null;
		continue;
	}

	if (line.trimStart().startsWith('[')) {
		throw new Error('Plugin Check findings appeared without a FILE header');
	}

	output.push(line);
}

if (currentFile) {
	throw new Error(`Missing Plugin Check findings for ${currentFile}`);
}
if (acceptedCount !== 1) {
	throw new Error(
		`Expected historical v0.4.0 Tested up to drift exactly once but found ${acceptedCount}.`
	);
}

fs.writeFileSync(outputPath === '-' ? 1 : outputPath, output.join('\n'));
console.error('Accepted exactly one historical v0.4.0 Tested up to metadata finding.');
