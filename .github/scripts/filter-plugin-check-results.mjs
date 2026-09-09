import fs from 'node:fs';
import path from 'node:path';

const [inputPath, outputPath, sourceRoot] = process.argv.slice(2);

if (!inputPath || !outputPath || !sourceRoot) {
	throw new Error(
		'Usage: node filter-plugin-check-results.mjs <input> <output> <source-root>'
	);
}

const expectedAcceptedFindings = [
	{
		file: 'includes/Turnstile.php',
		code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
		url: 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
	},
	{
		file: 'includes/Turnstile.php',
		code: 'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
		url: 'https://challenges.cloudflare.com/turnstile/v0/api.js',
	},
	{
		file: 'includes/Admin.php',
		code: 'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
		url: 'https://challenges.cloudflare.com/turnstile/v0/api.js',
	},
	{
		file: 'includes/Admin.php',
		code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
		url: 'https://developers.cloudflare.com/turnstile/',
	},
	{
		file: 'includes/Admin.php',
		code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
		url: 'https://developers.cloudflare.com/turnstile/get-started/server-side-validation/',
	},
	{
		file: 'includes/Admin.php',
		code: 'PluginCheck.CodeAnalysis.Offloading.OffloadedContent',
		url: 'https://developers.cloudflare.com/turnstile/troubleshooting/testing/',
	},
];

function tupleKey({ file, code, url }) {
	return JSON.stringify([file, code, url]);
}

const expectedCounts = new Map(
	expectedAcceptedFindings.map((finding) => [tupleKey(finding), 0])
);
const requestedRoot = path.resolve(sourceRoot);
const root = fs.realpathSync(requestedRoot);
const raw = fs.readFileSync(inputPath, 'utf8');
const lines = raw.split(/\r?\n/);
const output = [];
let currentFile = null;
let hasFilteredErrors = false;

function isWithinRoot(file, candidateRoot) {
	return file === candidateRoot || file.startsWith(`${candidateRoot}${path.sep}`);
}

function resolveSourcePath(file) {
	if (file.split(/[\\/]/).includes('..')) {
		throw new Error(`Unsafe Plugin Check path: ${file}`);
	}

	const requestedPath = path.isAbsolute(file)
		? path.resolve(file)
		: path.resolve(requestedRoot, file);

	if (!isWithinRoot(requestedPath, requestedRoot)) {
		throw new Error(`Unsafe Plugin Check path: ${file}`);
	}

	const sourcePath = fs.realpathSync(requestedPath);
	if (!isWithinRoot(sourcePath, root)) {
		throw new Error(`Unsafe Plugin Check path: ${file}`);
	}

	const relative = path.relative(root, sourcePath);
	const normalized = relative.split(path.sep).join('/');
	if (!normalized || normalized.startsWith('../') || path.posix.isAbsolute(normalized)) {
		throw new Error(`Unsafe Plugin Check path: ${file}`);
	}

	return { normalized, sourcePath };
}

function sourceLineFor(file, lineNumber) {
	const { sourcePath } = resolveSourcePath(file);
	const sourceLines = fs.readFileSync(sourcePath, 'utf8').split(/\r?\n/);
	return sourceLines[lineNumber - 1] ?? '';
}

function urlsFromSourceLine(sourceLine) {
	return sourceLine.match(/https:\/\/[^\s'"`<>]+/g) ?? [];
}

function acceptedTupleKey(file, finding) {
	if (
		!finding ||
		typeof finding !== 'object' ||
		!Number.isInteger(finding.line) ||
		finding.line < 1
	) {
		return null;
	}

	const sourceUrls = urlsFromSourceLine(sourceLineFor(file, finding.line));
	if (sourceUrls.length !== 1) {
		return null;
	}

	const key = tupleKey({ file, code: finding.code, url: sourceUrls[0] });
	return expectedCounts.has(key) ? key : null;
}

for (const line of lines) {
	if (line.startsWith('FILE: ')) {
		if (currentFile) {
			throw new Error(`Missing Plugin Check findings for ${currentFile}`);
		}

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

		for (const finding of findings) {
			if (
				!finding ||
				typeof finding !== 'object' ||
				!Number.isInteger(finding.line) ||
				finding.line < 1 ||
				typeof finding.code !== 'string' ||
				!['ERROR', 'WARNING'].includes(finding.type)
			) {
				throw new Error(`Invalid Plugin Check finding for ${currentFile}`);
			}
		}

		const adjusted = findings.map((finding) => {
			const key = acceptedTupleKey(currentFile, finding);
			if (!key) {
				return finding;
			}

			expectedCounts.set(key, expectedCounts.get(key) + 1);
			return {
				...finding,
				type: 'WARNING',
				message: `[Accepted Cloudflare Turnstile dependency] ${finding.message}`,
			};
		});

		hasFilteredErrors ||= adjusted.some((finding) => finding?.type === 'ERROR');
		output.push(JSON.stringify(adjusted));
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

fs.writeFileSync(outputPath, output.join('\n'));

const contractErrors = expectedAcceptedFindings.flatMap((finding) => {
	const count = expectedCounts.get(tupleKey(finding));
	return count === 1
		? []
		: [`Expected accepted finding exactly once but found ${count}: ${tupleKey(finding)}`];
});

if (hasFilteredErrors) {
	contractErrors.push('Filtered Plugin Check results still contain ERROR findings.');
}

if (contractErrors.length > 0) {
	throw new Error(`Plugin Check acceptance contract failed:\n${contractErrors.join('\n')}`);
}

console.log(
	`Accepted ${expectedAcceptedFindings.length} expected Cloudflare Turnstile findings.`
);
