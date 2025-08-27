# Repository Guidelines

## Project Structure & Module Organization
- PHP source: `src/MemberPressCoursesCopilot/*` (Controllers, Services, Models, Admin, Utilities, Security, Database).
- Built assets: `assets/css`, `assets/js`; templates: `templates/`; docs: `docs/`.
- Tests: PHP in `tests/Unit` and `tests/Integration`; browser/integration scripts in `tests/*.js`.
- Entry point: `memberpress-courses-copilot.php`; Composer autoload maps `MemberPressCoursesCopilot\` to `src/MemberPressCoursesCopilot/`.

## Build, Test, and Development Commands
- `npm run dev`: Watch SCSS/JS and recompile for local development.
- `npm run build`: Production build for CSS/JS (Sass + Webpack).
- `npm run lint` | `npm run lint:fix`: Lint (and fix) JS/SCSS.
- `npm run clean` | `npm run format`: Clean built assets and format sources.
- `composer run test`: Run PHPUnit; `composer run test-coverage` for HTML coverage.
- `composer run cs-check` | `composer run cs-fix`: Check/fix PHP coding standards.
- `npm run test:ai-chat`: Puppeteer test for the WP admin AI chat UI.

## Coding Style & Naming Conventions
- PHP: WordPress + Caseproof-WP standards; PSR-4 autoload. Use tabs for indent, escape output, sanitize input. Classes: PascalCase; methods: camelCase; files match class names.
- JavaScript: ESLint Standard config; 2-space indent; camelCase; prefix DOM classes with `mpcc-`.
- Styles: SCSS with Stylelint standard; BEM-style with `mpcc-` prefix; use CSS variables where available.
- Run `npm run lint` and `composer run cs-check` before pushing.

## Testing Guidelines
- PHP: Place tests under `tests/Unit` or `tests/Integration` with `*Test.php` suffix. Run with `composer run test`.
- Coverage: `composer run test-coverage` (includes `src/`, excludes utilities per `phpunit.xml`).
- Browser: Ensure local WP site is running, then `npm run test:ai-chat` to validate the metabox and AJAX flow.

## Commit & Pull Request Guidelines
- Commits: small, imperative, and scoped (e.g., `fix(ajax): handle nonce failure`).
- PRs: include summary, linked issue, screenshots/GIFs for UI, testing steps, and notes on performance/security. Ensure tests pass and linters are clean.

## Security & Configuration Tips
- Always use nonces, capability checks, sanitization, and escaping for admin/AJAX.
- Keep secrets out of the repo; configure via `wp-config.php` (`MPCC_LITELLM_PROXY_URL`, `MPCC_LITELLM_MASTER_KEY`).
