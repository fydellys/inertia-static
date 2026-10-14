import js from '@eslint/js'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  { ignores: ['js/dist', 'vendor', 'node_modules'] },
  js.configs.recommended,
  ...tseslint.configs.strict,
  {
    files: ['js/tests/**'],
    rules: { '@typescript-eslint/no-non-null-assertion': 'off' },
  },
)
