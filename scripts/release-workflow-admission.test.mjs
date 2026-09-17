import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const workflow = readFileSync(
	new URL('../.github/workflows/release-please.yml', import.meta.url),
	'utf8'
);

test('release job preserves authenticated canonical Quality admission', () => {
	const jobStart = workflow.indexOf('jobs:\n  release-please:');
	const ifMarker = '    if: >-\n';
	const ifStart = workflow.indexOf(ifMarker, jobStart);
	const runsOn = workflow.indexOf('\n    runs-on:', ifStart);

	assert.ok(jobStart >= 0 && ifStart > jobStart && runsOn > ifStart);
	const conditionLines = workflow
		.slice(ifStart + ifMarker.length, runsOn)
		.trimEnd()
		.split('\n');
	const allLinesActive = conditionLines.every((line) => {
		return line.startsWith('      ') && !line.trimStart().startsWith('#');
	});
	assert.ok(allLinesActive);

	const condition = conditionLines.map((line) => line.trim()).join(' ');
	const expression = condition.replace('${{', '').replace('}}', '').trim();
	assert.doesNotMatch(expression, /\|\|/);

	const terms = expression.split('&&').map((term) => term.trim());
	const requiredTerms = [
		"github.event_name == 'workflow_run'",
		"github.event.workflow_run.event == 'push'",
		"github.event.workflow_run.conclusion == 'success'",
		"github.event.workflow_run.head_branch == 'main'",
		'github.event.workflow_run.head_repository.full_name == github.repository',
		"github.event.workflow_run.path == '.github/workflows/quality.yml'",
	];

	assert.deepEqual([...terms].sort(), [...requiredTerms].sort());
});
