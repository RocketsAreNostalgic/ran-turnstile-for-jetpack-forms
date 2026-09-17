import fs from 'node:fs';

const [inputPath, outputPath] = process.argv.slice(2);

if (!inputPath || !outputPath) {
	throw new Error(
		'Usage: node filter-v040-historical-plugin-check.mjs <input> <output>'
	);
}

const raw = fs.readFileSync(inputPath, 'utf8');
const lines = raw.split(/\r?\n/);
const output = [];
let currentFile = null;
let accepted = 0;

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
		const findings = JSON.parse(line);
		if (!Array.isArray(findings)) {
			throw new Error(`Expected Plugin Check array for ${currentFile}`);
		}

		const remaining = findings.filter((finding) => {
			const historicalMetadataFinding =
				currentFile === 'readme.txt' &&
				finding?.type === 'ERROR' &&
				finding?.code === 'outdated_tested_upto_header' &&
				finding?.line === 0 &&
				finding?.column === 0 &&
				typeof finding?.message === 'string' &&
				finding.message.startsWith('Tested up to: 7.0 < ');

			if (historicalMetadataFinding) {
				accepted += 1;
				return false;
			}

			return true;
		});

		if (remaining.length > 0) {
			output.push(JSON.stringify(remaining));
		} else {
			output.pop();
		}
		currentFile = null;
		continue;
	}

	output.push(line);
}

if (currentFile) {
	throw new Error(`Missing Plugin Check findings for ${currentFile}`);
}

if (accepted !== 1) {
	throw new Error(
		`Expected exactly one historical v0.4.0 Tested up to finding, found ${accepted}`
	);
}

fs.writeFileSync(outputPath, output.join('\n'));
console.error('Accepted exactly one historical v0.4.0 Tested up to metadata finding.');
