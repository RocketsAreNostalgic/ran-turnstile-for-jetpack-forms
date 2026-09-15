import ranWordPress from '@rocketsarenostalgic/quality-config/eslint/wordpress';

export default [
	...ranWordPress,
	{
		files: [ 'assets/**/*.js' ],
		languageOptions: {
			globals: {
				document: 'readonly',
				MutationObserver: 'readonly',
				window: 'readonly',
			},
		},
	},
	{
		files: [ 'scripts/**/*.mjs', '.github/scripts/**/*.mjs' ],
		languageOptions: {
			globals: {
				console: 'readonly',
				process: 'readonly',
			},
		},
	},
];
