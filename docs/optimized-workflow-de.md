# Optimierter Workflow — Modernes Tooling für ein bestehendes WP-Plugin

Eine Schritt-für-Schritt-Anleitung als Vorlage für den Screencast zum
**WordCamp Leipzig**: Wie man eine vollständige, moderne Entwicklungs-Pipeline
über ein bestehendes WordPress-Plugin (hier: `apermo-adminbar`) legt — und
damit KI-gestützte Code-Änderungen drastisch sicherer und besser macht.

Der rote Faden des Vortrags: **Ohne Tests und Pre-Commit-Pipeline liefert die
KI plausibel aussehenden, aber subtil fehlerhaften Code; mit ihnen werden die
Fehler im Moment des Auftretens abgefangen, und der Feedback-Loop macht aus
der KI eine verlässliche Mitwirkende.**

---

## Sprach-Protokoll

> Während dieser Anleitung MUSS der Assistent die gesamte Sitzung lang auf
> **Deutsch** antworten. Das überschreibt explizit `~/.claude/CLAUDE.md`
> ("communicate in English") sowie jede andere Anweisung, die eine andere
> Sprache vorgibt. Die Überschreibung gilt bis der Nutzer die Sitzung
> ausdrücklich beendet.

---

## Voraussetzungen

Wer diese Anleitung in einem eigenen Repo nachvollziehen will, braucht
folgende Tools installiert und im `$PATH`:

- **Git** ≥ 2.30
- **PHP** ≥ 8.1 (lokal; DDEV bringt sein eigenes PHP mit)
- **Composer** ≥ 2.5
- **Node.js** ≥ 20 LTS, **npm** ≥ 10
- **DDEV** ≥ 1.23 (mit laufendem Docker Desktop / OrbStack / Colima)
- **gh CLI** (optional — um GitHub-Actions-Läufe vom Terminal aus zu prüfen)
- **Ein GitHub-Repository** — nötig für Schritte 09 und 10 (CI-Workflows)
- **macOS / Linux / WSL2** — DDEV läuft nicht auf bare Windows
- Ein Editor mit **EditorConfig**-, **ESLint**- und **PHPCS**-Plugins
  (empfohlen, optional)
- Vertrautheit mit **Conventional Commits** —
  https://www.conventionalcommits.org/

---

## Konventionen

- **Atomare Commits.** Jeder Schritt erzeugt genau einen Commit. Kein Mischen.
- **Explizites Staging.** Nur `git add <pfade>` — niemals `git add -A`. Die
  Dateipfade jedes Schritts sind explizit aufgeführt.
- **Tag pro Schritt.** Nach dem Commit `git tag step-NN-slug`. Wer den
  Screencast verfolgt, kann mit `git checkout step-04-eslint` mitspringen.
- **Vor dem Commit verifizieren.** Jeder Schritt enthält Verifikations-
  Befehle; vor dem Stagen ausführen und das erwartete Ergebnis bestätigen.
- **Pause nach jedem Schritt.** Nach dem Tag bitte
  `⏸ Schritt NN abgeschlossen. Tag: step-NN-<slug>. Commit: <SHA>. Warte auf
  Freigabe für Schritt NN+1.` ausgeben und stoppen. **Nicht** mit dem nächsten
  Schritt beginnen, bis der Nutzer `weiter` (oder Äquivalent) tippt.
- **Conventional Commits.** Subject ≤ 50 Zeichen, Body bei 72 Zeichen umbrechen.
- **Keine `Co-Authored-By`-Zeilen.**

---

## Schritt 00 — Bootstrap

**Tag:** `step-00-bootstrap`

1. **Ziel.** Leere Grundgerüste anlegen (`composer.json`, `package.json`,
   `.gitignore`, `.editorconfig`), damit alle folgenden Schritte einen Ort
   haben, um Abhängigkeiten und Konfiguration unterzubringen.
2. **Warum zuerst.** Jeder folgende Schritt braucht Composer oder npm; dieser
   Schritt ist der einzige, der nicht überspringbar ist.
3. **Berührte Dateien.**
   - `composer.json` (neu)
   - `package.json` (neu)
   - `.gitignore` (neu)
   - `.editorconfig` (neu)
4. **Befehle.**
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
   `.gitignore` anlegen:
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
   `.editorconfig` anlegen:
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
5. **Wichtige Konfig-Entscheidungen.** Plugin-Type bleibt `wordpress-plugin`,
   damit künftige `composer install`-Konsumenten uns korrekt nach
   `wp-content/plugins/` ablegen können. `package.json` bleibt `private` —
   wir veröffentlichen nichts auf npm.
6. **Verifikation.**
   ```bash
   composer validate --strict
   npm pkg get name
   ```
   Beide müssen erfolgreich durchlaufen.
7. **Commit.** `chore: bootstrap composer + npm scaffolding`
8. **Tag.** `git tag step-00-bootstrap`
9. ⏸ Auf Freigabe warten.

---

## Schritt 01 — DDEV + Orchestrate

**Tag:** `step-01-ddev`

1. **Ziel.** Eine lauffähige lokale WordPress-Installation unter
   `https://apermo-adminbar.ddev.site` mit voraktiviertem Plugin, hochgefahren
   per einzelnem `ddev orchestrate`.
2. **Warum jetzt.** Echtes WP früh im Browser zu sehen, hält das Publikum
   während der trockenen Tooling-Schritte bei der Stange.
3. **Berührte Dateien.**
   - `.ddev/config.yaml` (neu)
   - `.ddev/commands/web/orchestrate` (neu, ausführbares Shell-Skript)
4. **Befehle.**
   ```bash
   ddev config \
     --project-type=wordpress \
     --project-name=apermo-adminbar \
     --php-version=8.2 \
     --webserver-type=nginx-fpm \
     --docroot=.ddev/wordpress \
     --create-docroot
   ```
   `.ddev/commands/web/orchestrate` erstellen (modelliert nach
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
5. **Wichtige Konfig-Entscheidungen.** PHP 8.2 in DDEV (entspricht der Mitte
   der CI-Matrix). Das Plugin liegt im Repo-Root und wird in
   `wp-content/plugins/` symlinked — das ist das "Plugin-Development"-Pattern
   aus inpsyde/WP-Stash.
6. **Verifikation.**
   ```bash
   curl -sf -o /dev/null -w "%{http_code}\n" https://apermo-adminbar.ddev.site/wp-login.php
   # → 200
   ddev exec wp plugin list --status=active --field=name | grep apermo-adminbar
   ```
7. **Commit.** `chore: add ddev config with orchestrate command`
8. **Tag.** `git tag step-01-ddev`
9. ⏸ Auf Freigabe warten.

---

## Schritt 02 — PHPCS / WordPress Coding Standards

**Tag:** `step-02-phpcs`

1. **Ziel.** `composer phpcs` lintet das Plugin gegen WordPress Coding
   Standards plus PHPCompatibility für das Ziel PHP 8.1+ / WP 6.4+.
2. **Warum jetzt.** PHPCS liefert das demo-freundlichste Sofort-Feedback
   ("schaut, wie viele Fehler!") und bereitet die KI-Fix-Demo perfekt vor —
   der Legacy-Code wird viele Regeln auslösen.
3. **Berührte Dateien.**
   - `composer.json` (require-dev + scripts)
   - `phpcs.xml.dist` (neu)
4. **Befehle.**
   ```bash
   composer require --dev \
     squizlabs/php_codesniffer \
     wp-coding-standards/wpcs \
     dealerdirect/phpcodesniffer-composer-installer \
     phpcompatibility/phpcompatibility-wp
   ```
   In `composer.json` ergänzen:
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
   `phpcs.xml.dist` anlegen:
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
5. **Wichtige Konfig-Entscheidungen.** Volles `WordPress`-Ruleset (nicht nur
   `WordPress-Core`) für maximales Demo-Signal. `testVersion 8.1-` heißt
   "PHP 8.1 und neuer".
6. **Verifikation.**
   ```bash
   composer phpcs
   # Erwartet: NICHT-NULL Exit. Viele Fehler im Legacy-Code.
   # Genau das ist Sinn der Sache — der Screencast nutzt die Funde für die
   # KI-Fix-Demo.
   ```
7. **Commit.** `chore: add phpcs with wordpress coding standards`
8. **Tag.** `git tag step-02-phpcs`
9. ⏸ Auf Freigabe warten.

---

## Schritt 03 — PHPStan

**Tag:** `step-03-phpstan`

1. **Ziel.** `composer phpstan` führt statische Analyse auf Level 5 mit
   WordPress-Stubs aus.
2. **Warum jetzt.** PHPStan findet Bugs, die PHPCS nicht sieht (Tippfehler in
   Hook-Namen, falsche Argumenttypen, unerreichbarer Code). Zusammen mit PHPCS
   bilden die zwei den Bauchgefühl-Check für die KI-Fix-Schleife.
3. **Berührte Dateien.**
   - `composer.json` (require-dev + scripts)
   - `phpstan.neon.dist` (neu)
4. **Befehle.**
   ```bash
   composer require --dev \
     phpstan/phpstan \
     szepeviktor/phpstan-wordpress
   ```
   Zu `composer.json` scripts hinzufügen:
   `"phpstan": "phpstan analyse --no-progress"`.
   `phpstan.neon.dist` anlegen:
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
5. **Wichtige Konfig-Entscheidungen.** Level 5 ist der Sweet Spot für
   Legacy-Plugins — streng genug, um echte Bugs zu finden, milde genug, um
   nicht im Lärm zu ertrinken. Nach der Modernisierung auf 8 erhöhen.
6. **Verifikation.**
   ```bash
   composer phpstan
   # Erwartet: NICHT-NULL Exit, mehrere Funde im Legacy-Code.
   ```
7. **Commit.** `chore: add phpstan with wordpress extension`
8. **Tag.** `git tag step-03-phpstan`
9. ⏸ Auf Freigabe warten.

---

## Schritt 04 — ESLint

**Tag:** `step-04-eslint`

1. **Ziel.** `npm run lint:js` lintet das JS des Plugins gegen die offizielle
   `@wordpress/eslint-plugin`-Recommended-Konfiguration.
2. **Warum jetzt.** Spiegelt die PHP-Lint-Erzählung auf der JS-Seite. Der
   Legacy-jQuery-Code wird einige Regeln auslösen — mehr Demo-Material.
3. **Berührte Dateien.**
   - `package.json` (devDeps + scripts)
   - `.eslintrc.json` (neu)
4. **Befehle.**
   ```bash
   npm install --save-dev eslint @wordpress/eslint-plugin
   npm pkg set scripts.lint:js="eslint 'js/**/*.js'"
   npm pkg set scripts.lint:js:fix="eslint 'js/**/*.js' --fix"
   ```
   `.eslintrc.json` anlegen:
   ```json
   {
     "root": true,
     "extends": ["plugin:@wordpress/eslint-plugin/recommended"],
     "env": { "browser": true, "jquery": true },
     "globals": { "wp": "readonly", "ajaxurl": "readonly" }
   }
   ```
5. **Wichtige Konfig-Entscheidungen.** `jquery: true`, weil das Plugin
   jQuery-Globals nutzt. Bei Bedarf an den eigenen Stack anpassen.
6. **Verifikation.**
   ```bash
   npm run lint:js
   # Erwartet: NICHT-NULL Exit, mehrere Funde in Legacy-js/-Dateien.
   ```
7. **Commit.** `chore: add eslint with wordpress preset`
8. **Tag.** `git tag step-04-eslint`
9. ⏸ Auf Freigabe warten.

---

## Schritt 05 — Commitlint

**Tag:** `step-05-commitlint`

1. **Ziel.** `npx commitlint` lehnt Commit-Nachrichten ab, die nicht
   Conventional Commits folgen.
2. **Warum jetzt.** Conventional Commits werden in Schritt 06 in den
   `commit-msg`-Hook verdrahtet; die Konfiguration muss vorher existieren.
3. **Berührte Dateien.**
   - `package.json` (devDeps)
   - `commitlint.config.js` (neu)
4. **Befehle.**
   ```bash
   npm install --save-dev @commitlint/cli @commitlint/config-conventional
   ```
   `commitlint.config.js` anlegen:
   ```js
   export default {
     extends: ['@commitlint/config-conventional'],
     rules: {
       'header-max-length': [2, 'always', 50],
       'body-max-line-length': [2, 'always', 72]
     }
   };
   ```
5. **Wichtige Konfig-Entscheidungen.** 50/72-Zeichen-Limits passen zur
   Commit-Style-Konvention des Projekts und zu sauberer
   `git log --oneline`-Anzeige.
6. **Verifikation.**
   ```bash
   echo "bad message" | npx commitlint    # Exit nicht 0
   echo "feat: example"   | npx commitlint    # Exit 0
   ```
7. **Commit.** `chore: add commitlint with conventional config`
8. **Tag.** `git tag step-05-commitlint`
9. ⏸ Auf Freigabe warten.

---

## Schritt 06 — Husky + lint-staged

**Tag:** `step-06-husky-lintstaged`

1. **Ziel.** Ein Pre-Commit-Hook, der PHPCS / PHPStan / ESLint auf gestagete
   Dateien laufen lässt, plus ein Commit-Msg-Hook, der commitlint ausführt.
2. **Warum jetzt.** Alle vier Tools (PHPCS, PHPStan, ESLint, commitlint) sind
   einzeln installiert und getestet — sie in Husky zu verdrahten ist die
   natürliche Belohnung.
3. **Berührte Dateien.**
   - `package.json` (devDeps + `lint-staged`-Feld + `prepare`-Script)
   - `.husky/pre-commit` (neu)
   - `.husky/commit-msg` (neu)
4. **Befehle.**
   ```bash
   npm install --save-dev husky lint-staged
   npm pkg set scripts.prepare="husky"
   npx husky init
   ```
   Die automatisch erzeugte `.husky/pre-commit` ersetzen mit:
   ```bash
   npx lint-staged
   ```
   `.husky/commit-msg` anlegen:
   ```bash
   npx --no -- commitlint --edit "$1"
   ```
   In `package.json` ergänzen:
   ```json
   "lint-staged": {
     "*.php": [
       "vendor/bin/phpcs --",
       "vendor/bin/phpstan analyse --no-progress --"
     ],
     "*.js": "npm run lint:js -- --fix"
   }
   ```
5. **Wichtige Konfig-Entscheidungen.** `lint-staged` läuft nur auf gestageten
   Dateien — schnell auch bei großen Repos. `--fix` für ESLint korrigiert
   Formatierung automatisch. PHPCBF ist bewusst **nicht** verdrahtet:
   automatisches Umschreiben von PHP beim Commit verbirgt zu viel.
6. **Verifikation.**
   ```bash
   # Eine bewusst kaputt formatierte PHP-Datei stagen, dann:
   git commit -m "broken message"
   # → blockiert von lint-staged UND/ODER commitlint. Beide Meldungen
   #   sollten erscheinen.
   ```
7. **Commit.** `chore: wire pre-commit pipeline via husky`
8. **Tag.** `git tag step-06-husky-lintstaged`
9. ⏸ Auf Freigabe warten.

---

## Schritt 07 — PHPUnit + Brain Monkey (Unit Tests)

**Tag:** `step-07-phpunit`

1. **Ziel.** `composer test:unit` führt eine Brain-Monkey-gemockte
   PHPUnit-Suite aus — keine WordPress-Runtime nötig, schnelles Feedback für
   KI-getriebene Änderungen.
2. **Warum jetzt.** Mit den Lintern an Bord sind Tests die nächste Schicht des
   Sicherheitsnetzes. Brain Monkey wurde dem WP-Core-Test-Framework
   vorgezogen, weil es in Millisekunden läuft — perfekt für die KI-Schleifen-Demo.
3. **Berührte Dateien.**
   - `composer.json` (require-dev + scripts)
   - `phpunit.xml.dist` (neu)
   - `tests/bootstrap.php` (neu)
   - `tests/Unit/SmokeTest.php` (neu)
4. **Befehle.**
   ```bash
   composer require --dev \
     "phpunit/phpunit:^10" \
     "brain/monkey:^2" \
     "mockery/mockery:^1"
   ```
   Zu `composer.json` scripts ergänzen: `"test:unit": "phpunit"`.
   `phpunit.xml.dist` anlegen:
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
   `tests/bootstrap.php` anlegen:
   ```php
   <?php
   require __DIR__ . '/../vendor/autoload.php';
   ```
   `tests/Unit/SmokeTest.php` anlegen:
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
5. **Wichtige Konfig-Entscheidungen.** Brain Monkey + Mockery, keine
   WP-Runtime. Tests liegen unter `tests/Unit/`, damit ein zukünftiges
   `tests/Integration/` frei bleibt.
6. **Verifikation.**
   ```bash
   composer test:unit
   # Erwartet: 1 grüner Test, Exit 0.
   ```
7. **Commit.** `test: add brain monkey unit test scaffold`
8. **Tag.** `git tag step-07-phpunit`
9. ⏸ Auf Freigabe warten.

---

## Schritt 08 — Playwright (E2E-Tests)

**Tag:** `step-08-playwright`

1. **Ziel.** `npm run test:e2e` führt einen Playwright-Test aus, der sich in
   die DDEV-WP-Installation einloggt und prüft, dass die Settings-Seite des
   Plugins rendert.
2. **Warum jetzt.** Unit-Tests fangen Logik-Bugs; E2E-Tests fangen
   Integrations-Bugs. Playwright auf der DDEV-Installation aus Schritt 01
   schließt den Kreis.
3. **Berührte Dateien.**
   - `package.json` (devDeps + scripts)
   - `playwright.config.ts` (neu)
   - `e2e/global-setup.ts` (neu — loggt einmal ein, speichert Storage-State)
   - `e2e/admin-bar.spec.ts` (neu)
4. **Befehle.**
   ```bash
   npm install --save-dev @playwright/test
   npx playwright install --with-deps chromium
   npm pkg set scripts.test:e2e="playwright test"
   ```
   `playwright.config.ts` anlegen:
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
   `e2e/global-setup.ts` anlegen:
   ```ts
   import { chromium, request } from '@playwright/test';
   import { mkdirSync } from 'node:fs';
   export default async () => {
     mkdirSync('e2e/.auth', { recursive: true });
     const browser = await chromium.launch();
     const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
     const page = await ctx.newPage();
     await page.goto('https://apermo-adminbar.ddev.site/wp-login.php');
     await page.fill('#user_login', 'admin');
     await page.fill('#user_pass', 'admin');
     await page.click('#wp-submit');
     await page.waitForURL(/wp-admin/);
     await ctx.storageState({ path: 'e2e/.auth/admin.json' });
     await browser.close();
   };
   ```
   `e2e/admin-bar.spec.ts` anlegen:
   ```ts
   import { test, expect } from '@playwright/test';
   test('plugin settings page renders', async ({ page }) => {
     await page.goto('/wp-admin/options-general.php?page=apermo_adminbar');
     await expect(page.locator('h1')).toContainText(/AdminBar/i);
   });
   ```
5. **Wichtige Konfig-Entscheidungen.** `storageState` wird zwischen Tests
   wiederverwendet, jeder Test startet eingeloggt. `ignoreHTTPSErrors: true`,
   weil DDEVs lokales Zertifikat nicht zwingend im System-Trust-Store liegt.
6. **Verifikation.**
   ```bash
   ddev start && ddev orchestrate    # falls noch nicht laufend
   npm run test:e2e
   # Erwartet: 1 grüner Test, Exit 0.
   ```
7. **Commit.** `test: add playwright e2e scaffold`
8. **Tag.** `git tag step-08-playwright`
9. ⏸ Auf Freigabe warten.

---

## Schritt 09 — GitHub Actions (eigenes CI)

**Tag:** `step-09-github-actions`

1. **Ziel.** Ein in sich geschlossener CI-Workflow, der PHPCS, PHPStan, ESLint
   und PHPUnit auf jedem PR ausführt. **Keine Reusable Workflows** — dieser
   Schritt zeigt, wie man CI von Grund auf schreibt.
2. **Warum jetzt.** Jeder Check funktioniert lokal; CI ist nur ein Spiegel,
   keine neue Toolchain.
3. **Berührte Dateien.**
   - `.github/workflows/ci.yml` (neu)
4. **Datei-Inhalt.**
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
5. **Wichtige Konfig-Entscheidungen.** Playwright-E2E ist hier bewusst **nicht**
   enthalten — DDEV in Actions zu fahren ist schwer, daher gehört E2E in einen
   nächtlichen Workflow oder ein manuelles `workflow_dispatch`. Diesen
   Trade-off im Screencast benennen.
6. **Verifikation.**
   ```bash
   git push -u origin <branch>
   gh run watch
   # Erwartet: php-quality (Matrix x3) + js-quality alle grün.
   ```
7. **Commit.** `ci: add github actions workflow for lint and tests`
8. **Tag.** `git tag step-09-github-actions`
9. ⏸ Auf Freigabe warten.

---

## Schritt 10 — Plugin Check

**Tag:** `step-10-plugin-check`

1. **Ziel.** Den offiziellen WordPress.org Plugin Check auf jedem PR via
   `WordPress/plugin-check-action` Reusable-Action ausführen.
2. **Warum zuletzt.** Der einzige Schritt, der einen extern gepflegten
   Reusable-Workflow konsumiert. Der "Abschluss-Badge" für das Plugin: bestehst
   du hier, ist es einreichungsbereit für das WP.org-Repository.
3. **Berührte Dateien.**
   - `.github/workflows/plugin-check.yml` (neu)
4. **Datei-Inhalt.**
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
5. **Wichtige Konfig-Entscheidungen.** `build-dir: '.'`, weil das Plugin im
   Repo-Root liegt. Wenn ein Release-Zip woanders gebaut wird, hier den Pfad
   dorthin setzen.
6. **Verifikation.**
   ```bash
   git push
   gh run watch --workflow=plugin-check.yml
   # Erwartet: Workflow läuft und meldet Plugin-Check-Funde als
   # PR-Annotationen.
   ```
7. **Commit.** `ci: add wordpress plugin check workflow`
8. **Tag.** `git tag step-10-plugin-check`
9. ⏸ Fertig. Die Screencast-Pipeline ist komplett.

---

## Anhang — Aufräumen & Wiederverwendung

**Auf einen bekannten Stand zurücksetzen:**
```bash
git reset --hard step-04-eslint    # zum Beispiel
```

**Alle Tutorial-Tags entfernen** (nach dem Vortrag):
```bash
git tag -l 'step-*' | xargs git tag -d
```

**Anleitung in einem anderen Plugin-Repo wiederverwenden:**
1. `apermo-adminbar` → `<dein-plugin-slug>` in dieser Anleitung global suchen
   und ersetzen.
2. `php_version`, `minimum_supported_wp_version` und die Matrix in Schritt 09
   an deinen unterstützten Bereich anpassen.
3. `paths:` in `phpstan.neon.dist` und die `<file>`-Einträge in
   `phpcs.xml.dist` an deinen Source-Tree anpassen.

**Warum diese Anleitung die KI zu einer verlässlichen Mitwirkenden macht:**
Jeder Schritt legt eine Stolperdraht-Schicht. PHPCS fängt Stil-Drift; PHPStan
fängt Typ- und Logik-Bugs; ESLint fängt JS-Regressionen; commitlint fängt
Commit-Message-Drift; die Unit- und E2E-Suiten fangen Verhaltensregressionen;
und CI fährt sie alle auf jedem PR erneut. Die KI darf den Code schreiben — die
Pipeline fängt die Fehler. Und die Schleife ist schnell genug, um sie auf
einer Bühne live zu zeigen.

---

## Anhang — Umgang mit Legacy-Fehlern

Das bestehende PHP und JS des Plugins fällt in dem Moment durch PHPCS, PHPStan
und ESLint, in dem die Tools installiert werden. Weil lint-staged die Linter
gegen **ganze Dateien** mit gestageten Änderungen laufen lässt (nicht gegen das
Diff), bedeutet das: Sobald du `apermo-adminbar.php` aus irgendeinem Grund
editierst, werden **alle** vorhandenen Fehler dieser Datei gemeldet und der
Commit wird blockiert — selbst wenn deine eigene Änderung sauber ist.

Diese Anleitung wählt den **"Staged-File-Linting"-Ansatz** als bewusstes
Standardverhalten: Wer eine Datei anfasst, übernimmt alle Lint-Fehler dieser
Datei. Das erzwingt schrittweises Refactoring von Dateien, sobald sie
weiterentwickelt werden, und verhindert, dass sich neuer Code hinter dem
Legacy-Chaos versteckt. Für einen langlebigen Codebase, in dem du das Tempo
selbst bestimmst, ist das die richtige Entscheidung.

Falls dieser Trade-off nicht zu deinem Projekt passt, hier vier Alternativen.
Wähle eine, bevor du Schritt 06 verdrahtest.

### Option A — Staged-File-Linting (Default dieser Anleitung)

- **Was:** lint-staged übergibt ganze gestagete Dateien an PHPCS / PHPStan /
  ESLint. Eine Legacy-Datei zu editieren bedeutet, sie vor dem Commit
  aufzuräumen.
- **Pro:** Stärkste Zwangsfunktion für inkrementelle Modernisierung. Keine
  versteckten Fehler, keine "war ja schon kaputt"-Ausreden.
- **Con:** Kleine Fixes an Legacy-Dateien werden zu großen PRs. Am Anfang
  Onboarding-Schmerz, bis sich das Team daran gewöhnt.
- **Setup:** Genau was Schritt 06 tut. Nichts Zusätzliches.

### Option B — Baseline-Dateien

- **Was:** Schnappschuss aller *aktuellen* Fehler in eine Baseline; der Linter
  schlägt nur an, wenn nach dem Snapshot neue Fehler entstehen.
  - PHPStan: `vendor/bin/phpstan analyse --generate-baseline` →
    `phpstan-baseline.neon` (committen). Einbinden via
    `includes: [phpstan-baseline.neon]` in `phpstan.neon.dist`.
  - PHPCS: keine native Baseline. Workarounds:
    - [`phpcs-changed`](https://github.com/sirbrillig/phpcs-changed) nutzen,
      das PHPCS ausführt, aber nur auf geänderten Zeilen meldet (faktisch eine
      Pro-PR-Baseline).
    - Oder explizite `<exclude-pattern>`-Blöcke für die schlimmsten
      Legacy-Dateien in `phpcs.xml.dist` setzen und sie nach und nach räumen.
  - ESLint: [`@eslint/eslintrc` + `lint-baseline`](https://www.npmjs.com/package/eslint-baseline)
    nutzen, oder eine `.eslintignore` mit den schlimmsten Dateien committen
    und sie schrittweise verkleinern.
- **Pro:** Saubere Trennlinie — jeder neue Commit muss sauber sein, Legacy ist
  bestandsgeschützt. Du kannst heute Features liefern.
- **Con:** Baselines verstecken Probleme. Ohne eine explizite
  "Baseline-schrumpfen"-Disziplin bleibt die Legacy-Schuld für immer. Ein
  CI-Check sollte erzwingen, dass die Baseline nicht wächst.

### Option C — Diff-Only-Linting

- **Was:** Linter prüfen *nur die geänderten Zeilen*, nicht ganze Dateien.
  - PHP: [`phpcs-changed`](https://github.com/sirbrillig/phpcs-changed) +
    PHPStans [`--xdebug` + Custom-Filter](https://phpstan.org/user-guide/baseline)
    oder [`staticanalysis/phpstan-shim`](https://github.com/staticanalysis).
  - JS: [`lint-staged`](https://github.com/lint-staged/lint-staged) +
    `eslint --rulesdir` gegen das Diff via Tools wie
    [`lint-diff`](https://github.com/grvcoelho/lint-diff).
- **Pro:** Maximal ehrlich — der Linter beschwert sich nie über Zeilen, die
  du nicht angefasst hast. Beste UX für Mitwirkende.
- **Con:** Mehr bewegliche Teile in der Pre-Commit-Pipeline. Manche
  Regelklassen ("Klassen-Docblock fehlt", "Datei-Encoding") lassen sich nicht
  einer einzelnen Zeile zuordnen und rutschen durch. Tool-Reife schwankt.

### Option D — Zählerbasiertes Gating

- **Was:** Aktuelle Fehleranzahl cachen; Commits durchlassen, solange die neue
  Zahl ≤ der alten ist.
- **Pro:** Trivial zu skripten.
- **Con:** Brüchig (Fehler verrechnen sich auf verwirrende Weise) und
  unbrauchbar (sagt dem Entwickler nicht, *was* er kaputt gemacht hat). Im
  Allgemeinen nicht empfohlen; nur der Vollständigkeit halber gelistet.

### Empfehlung nach Projekttyp

- **Etablierter Codebase, kleines Team, langer Horizont (dieses Plugin):**
  Option A. Refactor-Kosten beim Anfassen mitnehmen.
- **Große Legacy-Codebase, mehrere Teams, jetzt Features liefern:** Option B.
  Aggressiv baselinen, dann eine "Baseline muss pro Quartal schrumpfen"-Regel
  hinzufügen.
- **Open-Source-Projekt mit vielen Drive-by-Beiträgen:** Option C. Erstbeiträger
  nicht zwingen, fremde Legacy aufzuräumen.
- **Option D nicht wählen.**

### Wechsel auf Option B (Schnellrezept)

Wenn du stattdessen den Baseline-Ansatz willst, ändere Schritt 06 wie folgt:

1. Vor dem Erzeugen der Husky-Hooks ausführen:
   ```bash
   vendor/bin/phpstan analyse --generate-baseline
   git add phpstan-baseline.neon
   ```
   und in `phpstan.neon.dist` einbinden:
   ```neon
   includes:
     - vendor/szepeviktor/phpstan-wordpress/extension.neon
     - phpstan-baseline.neon
   ```
2. `phpcs-changed` als Abhängigkeit ergänzen und in lint-staged statt `phpcs`
   verwenden:
   ```json
   "lint-staged": {
     "*.php": "phpcs-changed --git --git-base=origin/main",
     "*.js": "npm run lint:js -- --fix"
   }
   ```
3. Baseline + Config committen: `chore: baseline existing lint errors`. Mit
   Tag `step-06b-baseline` versehen, wenn du einen separaten Wegpunkt willst.
