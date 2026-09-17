import fs from 'node:fs';
import path from 'node:path';

const [inputPath, outputPath, sourceRoot] = process.argv.slice(2);

if (!inputPath || !outputPath || !sourceRoot) {
	throw new Error(
		'Usage: node filter-plugin-check-results.mjs <input> <output|-> <source-root>'
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
let acceptedHistoricalV040Metadata = 0;

function isWithinRoot(file, candidateRoot) {
	return (
		file === candidateRoot || file.startsWith(`${candidateRoot}${path.sep}`)
	);
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
	if (
		!normalized ||
		normalized.startsWith('../') ||
		path.posix.isAbsolute(normalized)
	) {
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
	return sourceLine.match(/(?:[a-z][a-z\d+.-]*:)?\/\/[^\s'"`<>]+/gi) ?? [];
}

function isHistoricalV040TestedUpToFinding(file, finding) {
	if (
		file !== 'readme.txt' ||
		!finding ||
		typeof finding !== 'object' ||
		finding.line !== 0 ||
		finding.column !== 0 ||
		finding.type !== 'ERROR' ||
		finding.code !== 'outdated_tested_upto_header' ||
		typeof finding.message !== 'string' ||
		!finding.message.startsWith('Tested up to: 7.0 < 7.1.')
	) {
		return false;
	}

	const { sourcePath } = resolveSourcePath(file);
	const source = fs.readFileSync(sourcePath, 'utf8');
	return (
		/^Tested up to: 7\.0$/m.test(source) &&
		/^Stable tag: 0\.4\.0$/m.test(source)
	);
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
			throw new Error(
				`Invalid Plugin Check JSON for ${currentFile}: ${error.message}`
			);
		}

		if (!Array.isArray(findings)) {
			throw new Error(`Expected Plugin Check array for ${currentFile}`);
		}

		for (const finding of findings) {
			const historicalV040 = isHistoricalV040TestedUpToFinding(
				currentFile,
				finding
			);
			const standardFinding =
				finding &&
				typeof finding === 'object' &&
				Number.isInteger(finding.line) &&
				finding.line >= 1 &&
				typeof finding.code === 'string' &&
				['ERROR', 'WARNING'].includes(finding.type);

			if (!standardFinding && !historicalV040) {
				throw new Error(
					`Invalid Plugin Check finding for ${currentFile}`
				);
			}
		}

		const adjusted = findings.map((finding) => {
			if (isHistoricalV040TestedUpToFinding(currentFile, finding)) {
				acceptedHistoricalV040Metadata += 1;
				return {
					...finding,
					line: 5,
					column: 1,
					type: 'WARNING',
					message: `[Accepted historical v0.4.0 metadata drift] ${finding.message}`,
					historical_line: finding.line,
					historical_column: finding.column,
				};
			}

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

		hasFilteredErrors ||= adjusted.some(
			(finding) => finding?.type === 'ERROR'
		);
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

fs.writeFileSync(outputPath === '-' ? 1 : outputPath, output.join('\n'));

const contractErrors = expectedAcceptedFindings.flatMap((finding) => {
	const count = expectedCounts.get(tupleKey(finding));
	return count === 1
		? []
		: [
				`Expected accepted finding exactly once but found ${count}: ${tupleKey(finding)}`,
			];
});

if (acceptedHistoricalV040Metadata > 1) {
	contractErrors.push(
		`Expected historical v0.4.0 metadata drift at most once but found ${acceptedHistoricalV040Metadata}.`
	);
}

if (hasFilteredErrors) {
	contractErrors.push(
		'Filtered Plugin Check results still contain ERROR findings.'
	);
}

if (contractErrors.length > 0) {
	throw new Error(
		`Plugin Check acceptance contract failed:\n${contractErrors.join('\n')}`
	);
}

console.error(
	`Accepted ${expectedAcceptedFindings.length} expected Cloudflare Turnstile findings.`
);
if (acceptedHistoricalV040Metadata === 1) {
	console.error(
		'Accepted one historical v0.4.0 Tested up to metadata finding.'
	);
}
