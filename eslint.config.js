import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
  ...wordpress.configs.recommended,
  {
    files: ['js/**/*.js'],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.jquery,
        wp: 'readonly',
        ajaxurl: 'readonly',
      },
    },
  },
];
