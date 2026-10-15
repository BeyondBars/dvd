# Handoff: Laravel + Vue starter (from beyondBars/invoice)

Same architecture as the original: Laravel API (upgraded from 11 to 13) (`api/`, Sanctum SPA cookie auth, Bouncer roles)
plus a Vue 3 + Pinia + Vite SPA (`frontend/`), run with `docker-compose.yml`.

## Status

### Backend: DONE and verified
- `cd api && php vendor/bin/phpunit` passes 62/62 (34 original tests + 28 new security tests).
- Tests use in-memory SQLite (see phpunit.xml), so they never touch the dev database.
- 2FA is enforced server-side (`2fa` middleware), the account takeover via email change is fixed,
  policies are enforced, sign-up requires a verified email, disabled users can't log in, Socialite
  is safe, rate limits are per user/IP, dead code is removed, and Docker and .env.example work.

### Frontend: DONE and verified
- `cd frontend && npm run typecheck` and `npm run build` both pass with 0 errors (the build still
  prints Sass deprecation and font-path warnings from the template's CSS).
- Account setup after verification validates the signed link on load, creates the account (the API
  logs the user in) and routes to `redirect` or the dashboard.
- Users, roles and blogs stores use the API resource types; pagination is read from `meta`.
  Roles are read-only and unpaginated, matching the API.
- Account page: details, password and 2FA panels each send only their own fields; changing the 2FA
  method calls `PUT /two-factor` and routes to `two-factor.setup.<method>?change=1` for verification.
- `vuex`, `vuex-pathify`, `store`, `sweetalert` and `@types/axios` are removed. `sweetalert2` is now
  a direct dependency, pinned to 11.4.4 to match (and dedupe with) `vue-sweetalert2`.
- `phpunit.xml` now sets a test `APP_KEY`, `APP_URL` and `SANCTUM_STATEFUL_DOMAINS`, so the API
  suite passes without an `api/.env` (it failed 9 tests on a clean checkout before).

### Known leftovers (not wired up)
- `components/forms/tiptap/Menu/MenuBar.vue` and `Interactive/*` are commented out of
  `TiptapEditor.vue`. They are written for Quasar (`q-btn`, `q-dialog`) and import a missing
  `ImageDialog/ImageDialog.vue`, so they only typecheck; re-enabling them needs that work first.

### Laravel 13 upgrade: DONE and verified
- PHP 8.3+ (Dockerfile later bumped to `php:8.4-fpm`, see below), `laravel/framework` ^13.0, `laravel/tinker` ^3.0,
  `phpunit/phpunit` ^12.5, `spatie/laravel-query-builder` ^7.0 (`allowed*()` take variadic
  arguments now, not arrays).
- Removed `codium/laravel-sms-capture` (stuck on Laravel 11) in favour of
  `App\Listeners\CaptureOutgoingSms`, and the unused `spatie/laravel-permission` (roles are Bouncer).
- CSRF middleware is `App\Http\Middleware\PreventRequestForgery` (Laravel 13 rename); Sanctum's
  config key is `validate_csrf_token`. Cross-port SPA requests still pass via the XSRF token.
- Adopted the hardened Laravel 13 defaults: cache `serializable_classes => false` and session
  `serialization => json` (existing sessions are logged out once).
- 62/62 tests pass; also smoke-tested with `artisan serve` + SQLite (CSRF cookie flow, login, user
  filters/sorts, roles include, blogs, SMS capture).

See README.md for setup.

### Starter conversion (branch `starter-tailwind`)
- Removed the demo blog feature (API model, controller, policy, requests, resources, migration, factory,
  seeder, tests; SPA views, store, types, routes) and the `blogger` / `invoicer` roles.
- Frontend moved from Creative Tim Material Dashboard (Bootstrap + Sass) to Tailwind CSS 4
  (`@tailwindcss/vite`). Shared building blocks (`.btn`, `.form-input`, `.card`, ...) and the brand colours
  live in `frontend/src/assets/css/app.css`. Auth screens share `components/auth/AuthCard.vue`; app pages use
  `components/app/PageHeader.vue`. The app shell has a mobile menu.
- Removed Tiptap, the template's JS/SCSS/fonts/demo images, `sass`, `@popperjs/core`, `font-awesome` 4 and
  `@types/bootstrap`. Icons are Font Awesome SVG icons registered in `main.js`.
- `vite.config.js` is now `vite.config.mjs` (`@tailwindcss/vite` is ESM-only).
- Fixed: the 2FA email used a non-existent `mail::code` component, so every 2FA email failed to send. Now
  `mail::panel`, with a test that renders the email (`Notification::fake()` never rendered it).
- Docker image is `php:8.4-fpm`; `composer.json` still allows `^8.3`.
- 51/51 API tests pass; `npm run typecheck` and `npm run build` pass. Sign-in, 2FA setup and verification,
  dashboard, users, account and settings were checked in a browser at desktop and phone widths.

### UI settings (branch `env_changes`)
- App-wide appearance is saved in the `settings` table (`App\Models\Setting`, key/JSON value, defaults in
  `Setting::DEFAULTS`): app name, primary/page/sidebar colours, font and corner radius.
- `GET /settings` is public (the sign-in screens are themed too); `PUT /settings` and `DELETE /settings` (reset)
  need the new `manage settings` ability, which super admins get. Existing databases need
  `php artisan migrate && php artisan abilities:refresh`.
- The SPA applies them as `--app-*` CSS variables (`stores/settings.ts`); `app.css` derives the `brand-*` shades,
  `sidebar`, `page`, `--font-sans` and the radius scale from them. The last settings are cached in localStorage so
  reloads don't flash the default theme. Settings page has an Appearance panel with live preview.

### Per-user permissions (branch `env_changes`)
- Abilities are grouped into modules in `UserAbility::modules()` (Users: view/manage, Settings: view/manage; new
  `read settings` ability, which Staff get by default). Roles set the defaults; a super admin can add or remove
  abilities for one user at Users > user > Permissions (`/users/{id}/permissions`, `App\Services\UserPermissions`,
  stored as Bouncer allow/forbid). Super admins can't edit their own permissions.
- `User::loadAbilities()` now leaves out abilities forbidden to the user, so the SPA's `can()` matches the API.
- Sidebar items for a module only show with its "view" ability; Dashboard and My Account always show.
- Existing databases need `php artisan abilities:refresh` for the new ability.

### Frontend unit tests (branch `env_changes`)
- Vitest + @vue/test-utils + jsdom. Run inside the container: `docker compose exec vue npm run test:unit`
  (`test:unit:watch` to re-run on save). Don't `npm install` on the host: `frontend/` is bind-mounted and the
  container's packages live in a volume at `/home/node/node_modules`. After pulling, rebuild the image (or run
  `npm install` in `/home/node` inside the container) to get the new dev dependencies.
- Tests live next to the code in `__tests__/*.spec.ts`. `src/__tests__/setup.ts` mocks the API client and toasts;
  `src/__tests__/helpers.ts` has `signIn(abilities, { role })`, `makeRouter()` and `ok()` for API responses.
- `vitest.config.js` is now `vitest.config.mjs` (it imports the ESM-only Tailwind plugin via vite.config.mjs).
- The Cypress e2e placeholder (`cypress/e2e/example.cy.ts`) is still the template's and would fail.

### DVD admin, phase 1: branding and menu (branch `claude/project-thread-yv8xb5`)
This repo is the admin and backend for Desi Videshi Diner. The customer website is
`BeyondBars/desi-videshi-diner-website` (Lovable, TanStack Start); it will read the menu and send orders here.
- Brought in `env_changes` (the Appearance panel, settings API and per-user permissions).
- Appearance defaults are now the website's colours: primary #f87417 (saffron), sidebar #15110d (ink), sidebar
  text and page #fbf7e9 (cream), font Outfit (new font option). App name "Desi Videshi Diner", DVD logo and favicon.
  Saved settings still win; "Reset to defaults" goes back to these.
- Menu: `menu_categories` and `menu_items` (prices in cents, `is_available` for sold out, `is_visible` to hide a
  category from the website). Admin API under `/menu/...`, abilities `read menu` / `manage menu` (staff get both),
  admin page at `/menu`.
- Public API for the website: `GET /public/menu` (visible categories, sold out items included), rate limited
  60/min per IP. Allowed website origins come from `WEBSITE_URLS` in `api/.env` (comma separated).
- `MenuSeeder` holds the website's menu as of Oct 2026 and only fills an empty menu. On an existing database:
  `php artisan migrate && php artisan abilities:refresh && php artisan db:seed --class=MenuSeeder`.

### DVD admin, phase 2: online orders
- Item choices: `menu_option_groups` / `menu_options` (min/max choices, extra price in cents). Sent with an item
  as `option_groups` and replaced as a whole on save; edited in the Menu page's item dialog. `MenuSeeder` adds the
  patty filling, combo drink and Large Combo choices (on an existing menu, add them in the admin).
- Orders: `orders` + `order_items` (names, prices and choices are copied, so old orders survive menu changes).
  Numbers start at DVD-1001. Statuses new > accepted > preparing > ready > completed (steps can be skipped),
  or cancelled with an optional reason. Pay at pickup; collecting an order marks it paid.
- Public API for the website: `GET /public/store` (open now, today's hours, pickup times),
  `POST /public/orders` (5/min, 30/hour per IP; prices always come from the menu) and
  `GET /public/orders/{token}` for the customer's status page (no contact details).
- Ordering settings (`App\Services\Ordering`, in the settings table but not in the public `/settings`):
  on/off, hours per day, prep minutes, alert email and SMS. Times are `ORDERING_TIMEZONE` (Australia/Adelaide);
  pickup times are 15 minute steps from now + prep time (or opening time) to closing.
- Admin: Orders page (board refreshing every 10 seconds with an optional chime, plus History) behind
  `read orders` / `manage orders` (staff get both), and an Online ordering card in Settings.
- New order alerts use the existing mail and Vonage setup; locally SMS goes to the log and mail to MailDev.
- Customer accounts: `customers` table and a separate `customer` session guard (staff stay on `web`, which is
  all `auth:sanctum` checks, so a customer login never opens the admin). `/public/customer/...`: register,
  login, logout, password forgot/reset (own token table; the emailed link goes to `WEBSITE_URL/reset-password`),
  and, signed in, `GET/PUT /public/customer` and `GET /public/customer/orders`. Orders placed while signed in
  get `customer_id` and show an "Account" tag on the board. No email verification yet.
- The website's origin must be in `SANCTUM_STATEFUL_DOMAINS` (localhost:3000 locally) for customer logins, so
  it calls `GET /sanctum/csrf-cookie` once and sends `X-XSRF-TOKEN` with every POST/PUT (guest orders too).
  In production the API needs to share the site, e.g. api.desivideshidiner.com.au with
  `SESSION_DOMAIN=.desivideshidiner.com.au`.
- After pulling: `php artisan migrate && php artisan abilities:refresh`.
- Not done yet: the website side (menu from the API, cart, checkout, status page, account pages).

### Live kitchen numbers
- `GET /public/kitchen` (cached 30s): orders cooking, ready for pickup, served today, `wait_minutes`,
  `busy_level` (quiet / steady / slammed) and the earliest pickup. Counts only, no customer details.
- Wait = prep time + 3 min per order still to cook (ASAP, or due before a new order could be ready) + rush time,
  rounded up to 5. Prep time is learned from the last 30 orders' accepted-to-ready time (14 days, at least 5
  orders, kept within 5 to 60 min), else the Prep time setting. Tunables in `config/ordering.php` under `wait`.
- The same wait sets the earliest pickup time, and `/public/store` returns it too. `GET /public/orders/{token}`
  has `orders_ahead` while the order is still cooking.
- Rush time (`busy_minutes`, 0 to 60) is on the Online ordering card in Settings.
- Test data: `php artisan db:seed --class=DemoOrdersSeeder` adds today's sample orders (refuses on production).
- The website (`desi-videshi-diner-website`) shows it once `VITE_DVD_API_URL` is set; until then sample numbers.

## Getting started (dev)
```
cp api/.env.example api/.env
docker compose build
docker compose run --rm fpm composer install
docker compose run --rm fpm php artisan key:generate
docker compose up -d
docker compose exec fpm php artisan migrate --seed
```
- SPA: http://localhost:8080
- API: http://localhost:8000
- Emails (MailDev): http://localhost:8025
- Seeded logins: admin@example.com / Secret*12345 (super admin), user@example.com / Secret*12345
- With APP_ENV=local, SMS codes are captured instead of sent and show up in api/storage/logs/laravel.log
  (`App\Listeners\CaptureOutgoingSms`).

## SECURITY
The original repo committed `ec2-pem.pem` (an RSA private key). Rotate that EC2 key pair and purge
it from git history (git filter-repo / BFG). It is removed here and `*.pem` is gitignored.
