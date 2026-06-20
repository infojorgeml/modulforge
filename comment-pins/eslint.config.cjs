/**
 * ESLint (flat config) for the WP Comment Pins module.
 *
 * Extends the @wordpress/scripts defaults and relaxes a few rules that don't
 * fit this code:
 * - jsdoc/* param rules: noise on React function components with destructured
 *   props (the prop names already document themselves).
 * - import/no-extraneous-dependencies: @wordpress/* packages are provided by
 *   WordPress at runtime (externalized in the build), not bundled deps.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [ 'build/**', 'node_modules/**' ],
	},
	...defaultConfig,
	{
		rules: {
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-returns': 'off',
			'import/no-extraneous-dependencies': 'off',
			// Native confirm()/alert() are an intentional, minimal UX choice here.
			'no-alert': 'off',
		},
	},
];
