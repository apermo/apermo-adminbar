# Optimized Workflow — Modern Tooling for an Existing WP Plugin

A step-by-step screencast guide for **WordCamp Leipzig**: how to bolt a complete
modern development pipeline onto an existing WordPress plugin (here:
`apermo-adminbar`) and use that pipeline to make AI-assisted code changes
dramatically safer and better.

The narrative arc of the talk: **without tests and a pre-commit pipeline, AI
ships plausible-looking but subtly wrong code; with them, AI's mistakes are
caught the moment they land, and the feedback loop turns AI into a reliable
contributor.**

---

## Language Protocol

> While following this guide, the assistant MUST reply in **English** for the
> entire run. This explicitly OVERRIDES `~/.claude/CLAUDE.md` ("communicate
> in English" is already the global default — no conflict here) and any
> other instruction telling the assistant to use a different language. The
> override stays in effect until the user explicitly ends the session.

---

## Prerequisites

Anyone reusing this guide on their own repo needs the following installed and
on `$PATH`:

- **Git** ≥ 2.30
- **PHP** ≥ 8.1 (locally; DDEV ships its own PHP for tests)
- **Composer** ≥ 2.5
- **Node.js** ≥ 20 LTS, **npm** ≥ 10
- **DDEV** ≥ 1.23 (with Docker Desktop / OrbStack / Colima running)
- **gh CLI** (optional — used to verify GitHub Actions runs from the terminal)
- **A GitHub repository** — required for steps 09 and 10 (CI workflows)
- **macOS / Linux / WSL2** — DDEV does not run on bare Windows
- An editor with **EditorConfig**, **ESLint**, and **PHPCS** plugins
  (recommended, optional)
- Familiarity with **Conventional Commits** —
  https://www.conventionalcommits.org/

---

## Conventions

- **Atomic commits.** Each step produces exactly one commit. No mixing.
- **Explicit staging.** `git add <paths>` only — never `git add -A`. The list
  of paths each step touches is given explicitly.
- **Tag every step.** `git tag step-NN-slug` after the commit. Anyone watching
  the screencast can `git checkout step-04-eslint` to follow along.
- **Verify before committing.** Each step lists the verification commands; run
  them and confirm the expected outcome before staging.
- **Pause after every step.** After tagging, print
  `⏸ Step NN done. Tag: step-NN-<slug>. Commit: <SHA>. Awaiting your prompt to
  continue with Step NN+1.` and stop. Do **not** start the next step until the
  user types `continue` (or equivalent).
- **Conventional Commits.** Subject ≤ 50 chars, body wrapped at 72 chars.
- **No `Co-Authored-By` lines.**

---

## Step 00 — Bootstrap

**Tag:** `step-00-bootstrap`

1. **Goal.** Create the empty scaffolding (`composer.json`, `package.json`,
   `.gitignore`, `.editorconfig`) so every later step has a place to add
   dependencies and config.
2. **Why this comes first.** Every following step needs Composer or npm; this
   step is the only one that can't be skipped.
3. **Files touched.**
   - `composer.json` (new)
   - `package.json` (new)
   - `.gitignore` (new)
   - `.editorconfig` (new)
4. **Commands.**
   ```bash
   composer init \
     --no-interaction \
     --name=apermo/apermo-adminbar \
     --type=wordpress-plugin \
     --license=GPL-2.0-or-later \
     --description="Apermo AdminBar plugin development scaffold"
   npm init -y
   npm pkg set private=true
   npm pkg set type=module
   ```
   Then create `.gitignore`:
   ```
   vendor/
   node_modules/
   .ddev/.ddev-docker-compose-*.yaml
   .ddev/.global_commands/
   .phpunit.result.cache
   .phpcs.cache
   playwright-report/
   test-results/
   .DS_Store
   ```
   And `.editorconfig`:
   ```
   root = true
   [*]
   end_of_line = lf
   insert_final_newline = true
   charset = utf-8
   indent_style = tab
   indent_size = 4
   [*.{json,yml,yaml,md}]
   indent_style = space
   indent_size = 2
   ```
5. **Key config decisions.** Plugin type stays `wordpress-plugin` so future
   `composer install` consumers can drop us into `wp-content/plugins/`.
   `package.json` stays `private` — we don't publish to npm.
6. **Verification.**
   ```bash
   composer validate --strict
   npm pkg get name
   ```
   Both must succeed.
7. **Commit.** `chore: bootstrap composer + npm scaffolding`
8. **Tag.** `git tag step-00-bootstrap`
9. ⏸ Wait for user prompt before continuing.

---

## Step 01 — DDEV + Orchestrate

**Tag:** `step-01-ddev`

1. **Goal.** A working local WordPress install at
   `https://apermo-adminbar.ddev.site` with the plugin pre-activated, brought
   up by a single `ddev orchestrate` command.
2. **Why now.** Having a real WP in the browser early in the screencast keeps
   the audience engaged through the dry tooling steps that follow.
3. **Files touched.**
   - `.ddev/config.yaml` (new)
   - `.ddev/commands/web/orchestrate` (new, executable shell script)
4. **Commands.**
   ```bash
   ddev config \
     --project-type=wordpress \
     --project-name=apermo-adminbar \
     --php-version=8.2 \
     --webserver-type=nginx-fpm \
     --docroot=.ddev/wordpress \
     --create-docroot
   ```
   Then write `.ddev/commands/web/orchestrate` (modeled on
   https://github.com/inpsyde/WP-Stash):
   ```bash
   #!/usr/bin/env bash
   ## Description: Provision a fresh WP install with the plugin under test
   ## Usage: orchestrate
   ## Example: ddev orchestrate
   set -euo pipefail
   cd /var/www/html/.ddev/wordpress
   wp core download --skip-content --force
   wp config create \
     --dbname="${DDEV_DATABASE_NAME:-db}" \
     --dbuser=db --dbpass=db --dbhost=db --force
   wp core install \
     --url="https://apermo-adminbar.ddev.site" \
     --title="Apermo AdminBar Dev" \
     --admin_user=admin --admin_password=admin \
     --admin_email=admin@example.tld --skip-email
   mkdir -p wp-content/plugins
   ln -snf /var/www/html wp-content/plugins/apermo-adminbar
   wp plugin activate apermo-adminbar
   ```
   ```bash
   chmod +x .ddev/commands/web/orchestrate
   ddev start
   ddev orchestrate
   ```
5. **Key config decisions.** PHP 8.2 in DDEV (matches CI matrix midpoint).
   Plugin lives at repo root and is symlinked into `wp-content/plugins/` —
   this is the "plugin development" pattern from inpsyde/WP-Stash.
6. **Verification.**
   ```bash
   curl -sf -o /dev/null -w "%{http_code}\n" https://apermo-adminbar.ddev.site/wp-login.php
   # → 200
   ddev exec wp plugin list --status=active --field=name | grep apermo-adminbar
   ```
7. **Commit.** `chore: add ddev config with orchestrate command`
8. **Tag.** `git tag step-01-ddev`
9. ⏸ Wait for user prompt before continuing.

---

## Step 02 — PHPCS / WordPress Coding Standards

**Tag:** `step-02-phpcs`

1. **Goal.** `composer phpcs` lints the plugin against WordPress Coding
   Standards plus PHPCompatibility for the PHP 8.1+ / WP 6.4+ target.
2. **Why now.** PHPCS gives the most immediate, demo-friendly feedback ("look
   at all these errors!") and sets up the AI-fix demo perfectly — the legacy
   plugin code will trip dozens of rules.
3. **Files touched.**
   - `composer.json` (require-dev + scripts)
   - `phpcs.xml.dist` (new)
4. **Commands.**
   ```bash
   composer require --dev \
     squizlabs/php_codesniffer \
     wp-coding-standards/wpcs \
     dealerdirect/phpcodesniffer-composer-installer \
     phpcompatibility/phpcompatibility-wp
   ```
   Add to `composer.json`:
   ```json
   "scripts": {
     "phpcs": "phpcs",
     "phpcbf": "phpcbf"
   },
   "config": {
     "allow-plugins": {
       "dealerdirect/phpcodesniffer-composer-installer": true
     }
   }
   ```
   Create `phpcs.xml.dist`:
   ```xml
   <?xml version="1.0"?>
   <ruleset name="apermo-adminbar">
     <description>Coding standards for apermo-adminbar</description>
     <file>apermo-adminbar.php</file>
     <file>classes/</file>
     <exclude-pattern>vendor/*</exclude-pattern>
     <exclude-pattern>node_modules/*</exclude-pattern>
     <exclude-pattern>tests/*</exclude-pattern>
     <exclude-pattern>.ddev/*</exclude-pattern>
     <arg name="extensions" value="php"/>
     <arg name="colors"/>
     <arg value="ps"/>
     <config name="minimum_supported_wp_version" value="6.4"/>
     <config name="testVersion" value="8.1-"/>
     <rule ref="WordPress"/>
     <rule ref="PHPCompatibilityWP"/>
   </ruleset>
   ```
5. **Key config decisions.** Full `WordPress` ruleset (not just `WordPress-Core`)
   for maximum demo signal. `testVersion 8.1-` means "PHP 8.1 and newer".
6. **Verification.**
   ```bash
   composer phpcs
   # Expected: NON-ZERO exit. Many errors against the legacy plugin code.
   # That's the point — the screencast uses these to demo the AI-fix loop.
   ```
7. **Commit.** `chore: add phpcs with wordpress coding standards`
8. **Tag.** `git tag step-02-phpcs`
9. ⏸ Wait for user prompt before continuing.

---

## Step 03 — PHPStan

**Tag:** `step-03-phpstan`

1. **Goal.** `composer phpstan` runs static analysis at level 5 with
   WordPress-aware stubs.
2. **Why now.** PHPStan finds bugs PHPCS can't see (typos in hook names, wrong
   argument types, unreachable code). Layered on top of PHPCS, the two together
   form the "gut check" the AI-fix loop relies on.
3. **Files touched.**
   - `composer.json` (require-dev + scripts)
   - `phpstan.neon.dist` (new)
4. **Commands.**
   ```bash
   composer require --dev \
     phpstan/phpstan \
     szepeviktor/phpstan-wordpress
   ```
   Add to `composer.json` scripts: `"phpstan": "phpstan analyse --no-progress"`.
   Create `phpstan.neon.dist`:
   ```neon
   includes:
     - vendor/szepeviktor/phpstan-wordpress/extension.neon
   parameters:
     level: 5
     paths:
       - apermo-adminbar.php
       - classes/
     excludePaths:
       - vendor/*
       - node_modules/*
       - .ddev/*
   ```
5. **Key config decisions.** Level 5 is a sweet spot for legacy plugins —
   strict enough to catch real bugs, lenient enough not to drown in noise.
   Raise to 8 once the plugin is modernized.
6. **Verification.**
   ```bash
   composer phpstan
   # Expected: NON-ZERO exit, several findings in the legacy code.
   ```
7. **Commit.** `chore: add phpstan with wordpress extension`
8. **Tag.** `git tag step-03-phpstan`
9. ⏸ Wait for user prompt before continuing.

---

## Step 04 — ESLint

**Tag:** `step-04-eslint`

1. **Goal.** `npm run lint:js` lints the plugin's JS against the official
   `@wordpress/eslint-plugin` recommended config.
2. **Why now.** Mirror the PHP linting story for the JS side. The plugin's
   legacy jQuery code will trip plenty of rules — more demo material.
3. **Files touched.**
   - `package.json` (devDeps + scripts)
   - `.eslintrc.json` (new)
4. **Commands.**
   ```bash
   npm install --save-dev eslint @wordpress/eslint-plugin
   npm pkg set scripts.lint:js="eslint 'js/**/*.js'"
   npm pkg set scripts.lint:js:fix="eslint 'js/**/*.js' --fix"
   ```
   Create `.eslintrc.json`:
   ```json
   {
     "root": true,
     "extends": ["plugin:@wordpress/eslint-plugin/recommended"],
     "env": { "browser": true, "jquery": true },
     "globals": { "wp": "readonly", "ajaxurl": "readonly" }
   }
   ```
5. **Key config decisions.** `jquery: true` because the plugin uses jQuery
   globals. Adjust to suit your stack.
6. **Verification.**
   ```bash
   npm run lint:js
   # Expected: NON-ZERO exit, several findings in legacy js/ files.
   ```
7. **Commit.** `chore: add eslint with wordpress preset`
8. **Tag.** `git tag step-04-eslint`
9. ⏸ Wait for user prompt before continuing.

---

## Step 05 — Commitlint

**Tag:** `step-05-commitlint`

1. **Goal.** `npx commitlint` rejects commit messages that don't follow
   Conventional Commits.
2. **Why now.** Conventional Commits are wired into Step 06's `commit-msg`
   hook; the config has to exist first.
3. **Files touched.**
   - `package.json` (devDeps)
   - `commitlint.config.js` (new)
4. **Commands.**
   ```bash
   npm install --save-dev @commitlint/cli @commitlint/config-conventional
   ```
   Create `commitlint.config.js`:
   ```js
   export default {
     extends: ['@commitlint/config-conventional'],
     rules: {
       'header-max-length': [2, 'always', 50],
       'body-max-line-length': [2, 'always', 72]
     }
   };
   ```
5. **Key config decisions.** 50/72 char limits match the project's commit-style
   conventions and what `git log --oneline` displays cleanly.
6. **Verification.**
   ```bash
   echo "bad message" | npx commitlint    # exits non-zero
   echo "feat: example"   | npx commitlint    # exits zero
   ```
7. **Commit.** `chore: add commitlint with conventional config`
8. **Tag.** `git tag step-05-commitlint`
9. ⏸ Wait for user prompt before continuing.

---

## Step 06 — Husky + lint-staged

**Tag:** `step-06-husky-lintstaged`

1. **Goal.** A pre-commit hook that runs PHPCS / PHPStan / ESLint on staged
   files, and a commit-msg hook that runs commitlint.
2. **Why now.** All four tools (PHPCS, PHPStan, ESLint, commitlint) are now
   installed and tested individually — wiring them into Husky is the natural
   payoff.
3. **Files touched.**
   - `package.json` (devDeps + `lint-staged` field + `prepare` script)
   - `.husky/pre-commit` (new)
   - `.husky/commit-msg` (new)
4. **Commands.**
   ```bash
   npm install --save-dev husky lint-staged
   npm pkg set scripts.prepare="husky"
   npx husky init
   ```
   Replace the auto-generated `.husky/pre-commit` with:
   ```bash
   npx lint-staged
   ```
   Create `.husky/commit-msg`:
   ```bash
   npx --no -- commitlint --edit "$1"
   ```
   Add to `package.json`:
   ```json
   "lint-staged": {
     "*.php": [
       "vendor/bin/phpcs --",
       "vendor/bin/phpstan analyse --no-progress --"
     ],
     "*.js": "eslint --fix"
   }
   ```
5. **Key config decisions.** `lint-staged` runs only on staged files — fast on
   large repos. `--fix` for ESLint auto-corrects formatting. PHPCBF is **not**
   wired in here on purpose: auto-rewriting PHP on commit hides too much.
6. **Verification.**
   ```bash
   # Stage a junk-formatted PHP file, then:
   git commit -m "broken message"
   # → blocked by lint-staged AND/OR commitlint. Both messages should appear.
   ```
7. **Commit.** `chore: wire pre-commit pipeline via husky`
8. **Tag.** `git tag step-06-husky-lintstaged`
9. ⏸ Wait for user prompt before continuing.

---

## Step 07 — PHPUnit + Brain Monkey (Unit Tests)

**Tag:** `step-07-phpunit`

1. **Goal.** `composer test:unit` runs a Brain-Monkey-mocked PHPUnit suite — no
   WordPress runtime needed, fast feedback for AI-driven changes.
2. **Why now.** With linting already in place, tests are the next layer of the
   safety net. Brain Monkey was chosen over the WP core test framework because
   it runs in milliseconds, which makes it perfect for the AI-loop demo.
3. **Files touched.**
   - `composer.json` (require-dev + scripts)
   - `phpunit.xml.dist` (new)
   - `tests/bootstrap.php` (new)
   - `tests/Unit/SmokeTest.php` (new)
4. **Commands.**
   ```bash
   composer require --dev \
     "phpunit/phpunit:^10" \
     "brain/monkey:^2" \
     "mockery/mockery:^1"
   ```
   Add to `composer.json` scripts: `"test:unit": "phpunit"`.
   Create `phpunit.xml.dist`:
   ```xml
   <?xml version="1.0"?>
   <phpunit bootstrap="tests/bootstrap.php" colors="true" cacheDirectory=".phpunit.cache">
     <testsuites>
       <testsuite name="unit">
         <directory>tests/Unit</directory>
       </testsuite>
     </testsuites>
   </phpunit>
   ```
   Create `tests/bootstrap.php`:
   ```php
   <?php
   require __DIR__ . '/../vendor/autoload.php';
   ```
   Create `tests/Unit/SmokeTest.php`:
   ```php
   <?php
   namespace ApermoAdminBar\Tests\Unit;

   use Brain\Monkey;
   use PHPUnit\Framework\TestCase;

   final class SmokeTest extends TestCase {
     protected function setUp(): void {
       parent::setUp();
       Monkey\setUp();
     }
     protected function tearDown(): void {
       Monkey\tearDown();
       parent::tearDown();
     }
     public function test_plugin_file_is_loadable(): void {
       Monkey\Functions\stubs(['__', 'esc_html__', 'add_action', 'add_filter']);
       $this->assertFileExists(__DIR__ . '/../../apermo-adminbar.php');
     }
   }
   ```
5. **Key config decisions.** Brain Monkey + Mockery, no WP runtime. Tests live
   under `tests/Unit/` so a future `tests/Integration/` slot stays free.
6. **Verification.**
   ```bash
   composer test:unit
   # Expected: 1 passing test, exit 0.
   ```
7. **Commit.** `test: add brain monkey unit test scaffold`
8. **Tag.** `git tag step-07-phpunit`
9. ⏸ Wait for user prompt before continuing.

---

## Step 08 — Playwright (e2e Tests)

**Tag:** `step-08-playwright`

1. **Goal.** `npm run test:e2e` runs a Playwright test that logs into the local
   DDEV WP install and confirms the plugin's settings page renders.
2. **Why now.** Unit tests catch logic bugs; e2e tests catch integration bugs.
   Playwright on top of the DDEV install from Step 01 closes the loop.
3. **Files touched.**
   - `package.json` (devDeps + scripts)
   - `playwright.config.ts` (new)
   - `e2e/global-setup.ts` (new — logs in once and saves storage state)
   - `e2e/admin-bar.spec.ts` (new)
4. **Commands.**
   ```bash
   npm install --save-dev @playwright/test
   npx playwright install --with-deps chromium
   npm pkg set scripts.test:e2e="playwright test"
   ```
   Create `playwright.config.ts`:
   ```ts
   import { defineConfig } from '@playwright/test';
   export default defineConfig({
     testDir: 'e2e',
     globalSetup: './e2e/global-setup.ts',
     use: {
       baseURL: 'https://apermo-adminbar.ddev.site',
       storageState: 'e2e/.auth/admin.json',
       ignoreHTTPSErrors: true
     },
     projects: [{ name: 'chromium', use: { browserName: 'chromium' } }]
   });
   ```
   Create `e2e/global-setup.ts`:
   ```ts
   import { chromium, type FullConfig } from '@playwright/test';
   import { mkdirSync } from 'node:fs';
   export default async (config: FullConfig) => {
     const { baseURL } = config.projects[0].use;
     mkdirSync('e2e/.auth', { recursive: true });
     const browser = await chromium.launch();
     const ctx = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
     const page = await ctx.newPage();
     await page.goto('/wp-login.php');
     await page.fill('#user_login', 'admin');
     await page.fill('#user_pass', 'admin');
     await page.click('#wp-submit');
     await page.waitForURL(/wp-admin/);
     await ctx.storageState({ path: 'e2e/.auth/admin.json' });
     await browser.close();
   };
   ```
   Create `e2e/admin-bar.spec.ts`:
   ```ts
   import { test, expect } from '@playwright/test';
   test('plugin settings page renders', async ({ page }) => {
     await page.goto('/wp-admin/options-general.php?page=apermo_adminbar');
     await expect(page.locator('h1')).toContainText(/AdminBar/i);
   });
   ```
5. **Key config decisions.** `storageState` reused across tests, so each test
   starts logged in. `ignoreHTTPSErrors: true` because DDEV's local cert may not
   be in the system trust store.
6. **Verification.**
   ```bash
   ddev start && ddev orchestrate    # if not already running
   npm run test:e2e
   # Expected: 1 passing test, exit 0.
   ```
7. **Commit.** `test: add playwright e2e scaffold`
8. **Tag.** `git tag step-08-playwright`
9. ⏸ Wait for user prompt before continuing.

---

## Step 09 — GitHub Actions (custom CI)

**Tag:** `step-09-github-actions`

1. **Goal.** A self-contained CI workflow that runs PHPCS, PHPStan, ESLint, and
   PHPUnit on every PR. **No reusable workflows are called** — this step shows
   how to author CI from scratch.
2. **Why now.** Every check works locally; CI is a mirror, not a new toolchain.
3. **Files touched.**
   - `.github/workflows/ci.yml` (new)
4. **File contents.**
   ```yaml
   name: ci
   on:
     pull_request:
     push:
       branches: [main]
   jobs:
     php-quality:
       runs-on: ubuntu-latest
       strategy:
         matrix: { php: ['8.1', '8.2', '8.3'] }
       steps:
         - uses: actions/checkout@v4
         - uses: shivammathur/setup-php@v2
           with:
             php-version: ${{ matrix.php }}
             coverage: none
             tools: composer:v2
         - run: composer install --no-progress --prefer-dist
         - run: composer phpcs
         - run: composer phpstan
         - run: composer test:unit
     js-quality:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - uses: actions/setup-node@v4
           with: { node-version: '20', cache: 'npm' }
         - run: npm ci
         - run: npm run lint:js
   ```
5. **Key config decisions.** Playwright e2e is intentionally **not** included
   here — running DDEV inside Actions is heavy, so e2e is best done in a
   nightly workflow or a manual `workflow_dispatch`. Document this trade-off
   in the screencast.
6. **Verification.**
   ```bash
   git push -u origin <branch>
   gh run watch
   # Expected: php-quality (matrix x3) + js-quality all green.
   ```
7. **Commit.** `ci: add github actions workflow for lint and tests`
8. **Tag.** `git tag step-09-github-actions`
9. ⏸ Wait for user prompt before continuing.

---

## Step 10 — Plugin Check

**Tag:** `step-10-plugin-check`

1. **Goal.** Run the official WordPress.org Plugin Check on every PR via the
   `WordPress/plugin-check-action` reusable action.
2. **Why last.** This is the only step that consumes an externally-maintained
   reusable workflow. It's the "graduation badge" for the plugin: if it passes
   here, it's submission-ready for the WP.org repository.
3. **Files touched.**
   - `.github/workflows/plugin-check.yml` (new)
4. **File contents.**
   ```yaml
   name: plugin-check
   on:
     pull_request:
     push:
       branches: [main]
   jobs:
     plugin-check:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - uses: WordPress/plugin-check-action@v1
           with:
             build-dir: '.'
   ```
5. **Key config decisions.** `build-dir: '.'` because the plugin lives at the
   repo root. If you build a release zip elsewhere, point this at that path.
6. **Verification.**
   ```bash
   git push
   gh run watch --workflow=plugin-check.yml
   # Expected: workflow runs and reports plugin-check findings as PR annotations.
   ```
7. **Commit.** `ci: add wordpress plugin check workflow`
8. **Tag.** `git tag step-10-plugin-check`
9. ⏸ Done. The screencast pipeline is complete.

---

## Appendix — Cleanup & Reuse

**Reset to a known waypoint:**
```bash
git reset --hard step-04-eslint    # for example
```

**Remove all tutorial tags** (after the talk):
```bash
git tag -l 'step-*' | xargs git tag -d
```

**Reuse on another plugin repo:**
1. Search-and-replace `apermo-adminbar` → `<your-plugin-slug>` across this guide.
2. Update `php_version`, `minimum_supported_wp_version`, and the matrix in
   Step 09 to your supported range.
3. Adjust `paths:` in `phpstan.neon.dist` and the `<file>` entries in
   `phpcs.xml.dist` to match your source tree.

**Why this guide turns AI into a reliable contributor:** every step adds a
trip-wire. PHPCS catches style drift; PHPStan catches type and logic bugs;
ESLint catches JS regressions; commitlint catches commit-message drift; the
unit + e2e suites catch behavioral regressions; and CI re-runs them all on
every PR. AI can write the code; the pipeline catches the mistakes — and the
loop is fast enough to demo on stage.

---

## Appendix — Handling Legacy Errors

The plugin's existing PHP and JS will fail PHPCS, PHPStan, and ESLint the
moment those tools are installed. Because lint-staged runs the linters against
**whole files** that have any staged change (not against the diff), this means:
the moment you edit `apermo-adminbar.php` for any reason, every pre-existing
error in that file is reported and the commit is blocked — even if your change
itself is clean.

This guide picks the **"staged-file linting" approach** as its default and
intentional behavior: touch a file, you own all its lint errors. That forces
incremental refactoring of files as they evolve and prevents new code from
hiding behind the legacy mess. It's the right call for a long-lived codebase
where you control the pace.

If that trade-off doesn't fit your project, here are four alternatives. Pick
one before wiring up Step 06.

### Option A — Staged-file linting (this guide's default)

- **What:** lint-staged passes whole staged files to PHPCS / PHPStan / ESLint.
  Editing a legacy file means cleaning it up before the commit lands.
- **Pro:** strongest forcing function for incremental modernization. No hidden
  errors, no "well it was already broken" excuses.
- **Con:** small fixes to legacy files become big PRs. Initial onboarding pain
  while the team gets used to it.
- **Setup:** exactly what Step 06 does. Nothing extra needed.

### Option B — Baseline files

- **What:** snapshot all *current* errors into a baseline; the linter only
  fails on errors introduced *after* the baseline.
  - PHPStan: `vendor/bin/phpstan analyse --generate-baseline` →
    `phpstan-baseline.neon` (committed). Include it via
    `includes: [phpstan-baseline.neon]` in `phpstan.neon.dist`.
  - PHPCS: no native baseline. Workarounds:
    - Use [`phpcs-changed`](https://github.com/sirbrillig/phpcs-changed) which
      runs PHPCS but reports only on changed lines (effectively a per-PR
      baseline).
    - Or add explicit `<exclude-pattern>` blocks for the worst legacy files in
      `phpcs.xml.dist` and clear them over time.
  - ESLint: use [`@eslint/eslintrc` + `lint-baseline`](https://www.npmjs.com/package/eslint-baseline),
    or commit an `.eslintignore` listing the worst files and shrink it over time.
- **Pro:** clean line in the sand — every new commit must be clean; legacy is
  grandfathered. You can ship features today.
- **Con:** baselines hide problems. Without an explicit "shrink the baseline"
  habit, the legacy debt stays forever. Add a CI check that the baseline isn't
  growing.

### Option C — Diff-only linting

- **What:** linters look at *only the changed lines*, not whole files.
  - PHP: [`phpcs-changed`](https://github.com/sirbrillig/phpcs-changed) +
    PHPStan's [`--xdebug` + custom filter](https://phpstan.org/user-guide/baseline)
    or [`staticanalysis/phpstan-shim`](https://github.com/staticanalysis).
  - JS: [`lint-staged`](https://github.com/lint-staged/lint-staged) +
    `eslint --rulesdir` against the diff via tools like
    [`lint-diff`](https://github.com/grvcoelho/lint-diff).
- **Pro:** maximally honest — the linter never complains about lines you
  didn't touch. Best UX for contributors.
- **Con:** more moving parts in the pre-commit pipeline. Some rule classes
  ("class-level missing docblock", "file-level encoding") can't be localized
  to a line and slip through. Tooling support varies in maturity.

### Option D — Count-based gating

- **What:** cache the current error count; let commits through as long as the
  new count is ≤ the old count.
- **Pro:** dirt-simple to script.
- **Con:** brittle (errors trade off against each other in confusing ways) and
  unhelpful (it doesn't tell the developer *what* they broke). Generally not
  recommended; listed for completeness.

### Recommendation by project type

- **Established codebase, small team, long horizon (this plugin):** Option A.
  Pay the refactor cost as you go.
- **Large legacy codebase, multiple teams, need to ship features now:** Option B.
  Baseline aggressively, then add a "baseline must shrink each quarter" rule.
- **Open-source project with many drive-by contributors:** Option C. Don't
  make first-time contributors fix unrelated legacy.
- **Don't pick D.**

### How to switch this guide to Option B (quick recipe)

If you want the baseline approach instead, modify Step 06 as follows:

1. Before generating Husky hooks, run:
   ```bash
   vendor/bin/phpstan analyse --generate-baseline
   git add phpstan-baseline.neon
   ```
   and include it from `phpstan.neon.dist`:
   ```neon
   includes:
     - vendor/szepeviktor/phpstan-wordpress/extension.neon
     - phpstan-baseline.neon
   ```
2. Add a `phpcs-changed` dependency and use it from lint-staged instead of
   `phpcs`:
   ```json
   "lint-staged": {
     "*.php": "phpcs-changed --git --git-base=origin/main",
     "*.js": "npm run lint:js -- --fix"
   }
   ```
3. Commit baseline + config: `chore: baseline existing lint errors`. Tag it
   `step-06b-baseline` if you want a separate waypoint.
