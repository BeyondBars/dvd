# Laravel + Vue Starter

A starter for apps that need a solid sign-in system: Laravel 13 API (PHP 8.4, `api/`, Sanctum SPA cookie
auth, Bouncer roles) plus a Vue 3 + Pinia + Vite + Tailwind CSS 4 SPA (`frontend/`), run together with
`docker-compose.yml`.

Included: sign-up with email verification, sign-in, Google/Facebook login, 2-step verification by email or
SMS, password reset, terms acceptance, user management, roles and an account page. Nothing app-specific.

## Building an app on this starter

Create the app from this repo and keep it linked, so auth fixes made here can be pulled in later:

```sh
git clone git@github.com:BeyondBars/<this-repo>.git my-app
cd my-app
git remote rename origin upstream
git remote add origin git@github.com:BeyondBars/my-app.git
git push -u origin main
```

Pull starter changes when you want them:

```sh
git fetch upstream
git merge upstream/main          # everything
git cherry-pick <commit-hash>    # one specific fix
```

To keep merges painless:

- Fix auth and shared-shell problems **here**, then pull them into the app. Keep starter commits focused
  (one fix per commit) so they can be cherry-picked.
- In the app, extend rather than edit starter files where you can. App-specific user data goes in its own
  table rather than new columns on `users`; app pages go in new route files under `frontend/src/router/routes/`.
- Rebrand by changing the `--color-brand-*` values in `frontend/src/assets/css/app.css` and replacing
  `frontend/src/assets/img/logo/logo.png` and `white-logo.png`. Everything else uses those.

## Email templates

Admins can see every email the app sends under **Email Templates**, preview each one with sample data
and send a test copy (subject prefixed with `[TEST]`). Access needs the `manage email templates` ability,
which super admins get; on an existing database run `php artisan abilities:refresh` to add it.

When the app adds an email, register it in `api/app/Providers/EmailTemplateServiceProvider.php`:

```php
$templates->register(new EmailTemplate(
    key: 'order_shipped',
    name: 'Order Shipped',
    type: EmailTemplateType::Transactional,   // or Marketing
    triggeredBy: 'order.shipped',
    description: 'Tells the customer their order is on its way.',
    sample: fn (User $recipient) => new OrderShippedNotification(Order::factory()->make()),
));
```

`sample` returns the real notification built with sample data, so previews match what users get. Test
copies go to real inboxes, so never put real tokens or signed links in a sample.
`tests/Feature/EmailTemplateTest.php` renders and test-sends every registered template.

## Getting started

Requirements: Docker with the Compose plugin.

```sh
cp api/.env.example api/.env
docker compose build
docker compose run --rm fpm composer install
docker compose run --rm fpm php artisan key:generate
docker compose up -d
docker compose exec fpm php artisan migrate --seed
```

| Service             | URL                   |
| ------------------- | --------------------- |
| SPA                 | http://localhost:8080 |
| API                 | http://localhost:8000 |
| Emails (MailDev)    | http://localhost:8025 |
| MySQL (from host)   | `localhost:33061` (see `DB_*` in `api/.env`) |

Seeded logins (password `Secret*12345` for both):

- `admin@example.com`: super admin
- `user@example.com`: standard user

Notes:

- Sign-up, email verification and password reset emails are caught by MailDev.
- With `APP_ENV=local`, 2FA SMS codes are captured instead of sent and show up in
  `api/storage/logs/laravel.log`. Set `TWO_FACTOR_SKIP=true` in `api/.env` to accept any code locally.
- The SPA's `node_modules` live in a Docker volume. After changing `frontend/package.json`, rebuild
  the image and recreate that volume: `docker compose build vue`, then
  `docker compose rm -sf vue && docker volume rm <project>_node_modules && docker compose up -d vue`
  (`docker volume ls` shows the exact name).
- Social login (Google / Facebook) is optional: fill in the `GOOGLE_*` / `FACEBOOK_*` keys in
  `api/.env`. Callback URLs point at the API, e.g. `http://localhost:8000/auth/google/callback`.

## Checks

```sh
# API tests (in-memory SQLite, no .env or database needed)
cd api && php vendor/bin/phpunit

# Frontend type check and production build
cd frontend && npm ci && npm run typecheck && npm run build
```

Inside Docker: `docker compose exec fpm php vendor/bin/phpunit` and
`docker compose exec vue npm run typecheck`.

## Security

The original repository committed `ec2-pem.pem` (an RSA private key). That EC2 key pair must be
rotated and the file purged from the original repository's git history (git filter-repo / BFG).
It is not included here, and `*.pem`, `*.key` and `.env` files are gitignored.
