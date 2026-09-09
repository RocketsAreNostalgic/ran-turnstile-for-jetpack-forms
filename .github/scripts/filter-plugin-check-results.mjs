import fs from 'node:fs';
import path from 'node:path';

const [inputPath, outputPath, sourceRoot] = process.argv.slice(2);

if (!inputPath || !outputPath || !sourceRoot) {
	throw new Error(
		'Usage: node filter-plugin-check-results.mjs <input> <output> <source-root>'
	);
}

const acceptedCodes = new Set([
	'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
	'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
]);

const acceptedUrlsByFile = new Map([
	[
		'includes/Turnstile.php',
		new Set([
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		]),
	],
	[
		'includes/Admin.php',
		new Set([
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'https://developers.cloudflare.com/turnstile/',
			'https://developers.cloudflare.com/turnstile/get-started/server-side-validation/',
			'https://developers.cloudflare.com/turnstile/troubleshooting/testing/',
		]),
	],
]);

const root = path.resolve(sourceRoot);
const raw = fs.readFileSync(inputPath, 'utf8');
const lines = raw.split(/\r?\n/);
const output = [];
let currentFile = null;
let acceptedCount = 0;

function resolveSourcePath(file) {
	const normalized = path.posix.normalize(file);
	if (normalized.startsWith('../') || path.posix.isAbsolute(normalized)) {
		throw new Error(`Unsafe Plugin Check path: ${file}`);
	}

	const sourcePath = path.resolve(root, normalized);
	if (sourcePath !== root && !sourcePath.startsWith(`${root}${path.sep}`)) {
		throw new Error(`Plugin Check path escapes source root: ${file}`);
	}

	return { normalized, sourcePath };
}

function sourceLineFor(file, lineNumber) {
	const { sourcePath } = resolveSourcePath(file);
	const sourceLines = fs.readFileSync(sourcePath, 'utf8').split(/\r?\n/);
	return sourceLines[lineNumber - 1] ?? '';
}

function isAcceptedFinding(file, finding) {
	if (!acceptedCodes.has(finding.code) || !Number.isInteger(finding.line) || finding.line < 1) {
		return false;
	}

	const acceptedUrls = acceptedUrlsByFile.get(file);
	if (!acceptedUrls) {
		return false;
	}

	const sourceLine = sourceLineFor(file, finding.line);
	return [...acceptedUrls].some((url) => sourceLine.includes(url));
}

for (const line of lines) {
	if (line.startsWith('FILE: ')) {
		const reportedFile = line.slice('FILE: '.length).trim();
		const { normalized } = resolveSourcePath(reportedFile);
		currentFile = normalized;
		output.push(`FILE: ${normalized}`);
		continue;
	}

	if (currentFile && line.trimStart().startsWith('[')) {
		let findings;
		try {
			findings = JSON.parse(line);
		} catch (error) {
			throw new Error(`Invalid Plugin Check JSON for ${currentFile}: ${error.message}`);
		}

		if (!Array.isArray(findings)) {
			throw new Error(`Expected Plugin Check array for ${currentFile}`);
		}

		const adjusted = findings.map((finding) => {
			if (!isAcceptedFinding(currentFile, finding)) {
				return finding;
			}

			acceptedCount += 1;
			return {
				...finding,
				type: 'WARNING',
				message: `[Accepted Cloudflare Turnstile dependency] ${finding.message}`,
			};
		});

		output.push(JSON.stringify(adjusted));
		currentFile = null;
		continue;
	}

	output.push(line);
}

fs.writeFileSync(outputPath, output.join('\n'));
console.log(`Accepted ${acceptedCount} allowlisted Cloudflare Turnstile finding(s).`);
