# Deploying viralpublisher.com to Coolify

## 1. Generate secrets locally, before touching Coolify

Run these on your own machine (or in a throwaway container) — never paste
a plaintext password anywhere in Coolify's UI or in the repo.

```bash
# Admin panel password hash — pick a real password, replace 'yourpassword'
php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"

# Random salt used to hash visitor IPs before storage (never store raw IPs)
openssl rand -hex 32
```

Keep both outputs handy for step 3.

## 2. Push this code to a Git repo Coolify can reach

Standard Coolify flow: New Resource → your Git provider → this repo →
Coolify detects the `Dockerfile` at the project root automatically.

## 3. Set environment variables in Coolify

Under the app's **Environment Variables** tab, add:

| Variable | Value |
|---|---|
| `OPENROUTER_API_KEY` | Your OpenRouter API key |
| `ADMIN_PASSWORD_HASH` | The hash from step 1 |
| `VP_IP_SALT` | The random string from step 1 |

`VP_DB_PATH` does **not** need to be set — it defaults to
`data/viralpublisher.sqlite` relative to the app root, which is correct
for this container. Only override it if you have a specific reason to.

## 4. Set up the persistent volume — do this before first deploy

This is the step most likely to bite you if skipped: **without a mounted
volume, every redeploy wipes the entire SQLite database** — every lead,
every generated hook/score/script, all admin config.

In Coolify's **Storages** tab for this app, add a persistent volume:
- **Source path** (on the host): whatever Coolify suggests, or a path like `viralpublisher-data`
- **Destination path** (in the container): `/var/www/html/data`

## 5. Set the domain

In the app's **Domains** tab, point `viralpublisher.com` at this app.
Coolify handles the Let's Encrypt certificate automatically — no action
needed beyond DNS pointing at your server.

## 6. Deploy

Trigger the first deploy. Watch the build log for the extension install
step (`docker-php-ext-install pdo_sqlite mbstring curl`) — if this step
is ever removed or fails silently in a future Dockerfile edit, the site
will fatal-error the moment anyone tries to generate a hook, check a
score, or build a script. This bit us once already during development
(mbstring wasn't in the base image) — it's now baked into the Dockerfile,
but worth knowing why that line matters if you ever touch it.

## 7. Post-deploy: seed the AI tool prompts and game data

These do **not** run automatically — they're one-time (or run-when-you-
want-to-update-the-prompt) scripts. Use Coolify's **Terminal** tab for
this app (or `docker exec` if you're on the host directly) to get a shell
inside the running container, then:

```bash
cd /var/www/html
php scripts/seed-hook-generator-prompt.php
php scripts/seed-score-checker-prompt.php
php scripts/seed-script-builder-prompt.php
php scripts/seed-game-matchups.php
```

Each is safe to re-run — see the comment at the top of each script for
its exact idempotency behavior (most just update the prompt text without
touching model/limits; the game seeder skips entirely if matchups already
exist, add `--force` to wipe and reseed).

## 8. Verify the deployment, in this order

```bash
# a) Homepage loads
curl -I https://viralpublisher.com/

# b) The SQLite DB is NOT publicly reachable — this MUST return 403
curl -I https://viralpublisher.com/data/viralpublisher.sqlite

# c) lib/ is NOT publicly reachable — this MUST return 403
curl -I https://viralpublisher.com/lib/db.php

# d) Admin panel is reachable and prompts for login
curl -I https://viralpublisher.com/admin/login.php
```

Then in a real browser:
- Log into `/admin/login.php` with the password you hashed in step 1
- Visit `/admin/tool-config.php?tool=hook_generator` and confirm the
  system prompt shows the real seeded text, not a placeholder
- Actually generate one hook, one score, and one script end-to-end to
  confirm the OpenRouter key works from inside the real container
  (this sandbox could never test that part — it's the one thing that
  can only be verified live)
- Play one round of the game
- Submit a test email through any tool's unlock form, then confirm it
  shows up in `/admin/leads.php`

## 9. Ongoing: watch the free OpenRouter model's behavior

Free-tier models vary in how reliably they follow the JSON-only output
instructions in each tool's system prompt. If you start seeing elevated
`generation_failed` errors in the container logs (`docker logs` or
Coolify's log viewer), check whether the currently-selected free model
has degraded or started wrapping output in commentary more often than
usual — the admin panel's model dropdown makes swapping to a different
free model a one-click fix, no deploy required.
