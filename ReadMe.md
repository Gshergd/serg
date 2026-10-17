# ser.gt.tc

An owner-only PHP + MySQL link studio, made for InfinityFree. Every generated link is exactly `https://ser.gt.tc/12345`: five digits, no suffix, no extra path, no query parameters. HTTPS is the protocol, not extra characters in the code.

Source is public; administration and credentials are private. GitHub stores the code. InfinityFree serves the entire app. This is not a GitHub Pages application and uses no workers, proxy, external API, Composer, npm, or cron.

## InfinityFree installation

1. Add `ser.gt.tc` to your hosting account and activate its SSL certificate. Wait until HTTPS works. This app requires HTTPS for owner access.
2. Create a MySQL database in the hosting control panel. Note its **actual MySQL hostname**, database name, username and password. The hostname is generally not `localhost`.
3. Open phpMyAdmin, select that database, and import `schema.sql` from this package. It creates three tables without deleting existing data. Use a dedicated database.
4. Extract `ser-gt-tc-infinityfree.zip` locally. Upload the **contents** of `htdocs/` to the document root assigned to `ser.gt.tc`. Do not create an extra nested `htdocs` folder. Ensure the hidden `.htaccess` file is uploaded. InfinityFree's file manager may require extracting locally and uploading files instead of extracting ZIPs on the host.
5. On the host, copy `config.example.php` to `config.php`. Enter the database credentials. Replace `setup_key` with your own unique random secret of at least 32 characters (a password manager can generate it). Keep this file and key private. The ZIP intentionally contains no real credentials or default owner password.
6. Visit `https://ser.gt.tc/setup`. Enter that setup key and choose your owner password (14–72 bytes; ASCII characters make this simple). The first successful setup creates the **only** owner account and permanently closes registration. Save the owner password in your password manager.
7. Create a link, copy it, and test it in a separate browser. Confirm that its address has exactly five digits. Open `/admin` in a private window and confirm it asks for your password. Test Pause and Resume too.

Never upload the whole Git repo, `.git`, `.vs`, tests, or development tools. The supplied ZIP already separates the upload files from documentation and schema. Do not publish `config.php`, the setup key or your password in GitHub issues, screenshots or commits.

## Everyday use

- Sign in at `/` or `/admin`. There is no public link creation or sign-up.
- Enter a full HTTP(S) destination and optionally a label. Codes are cryptographically randomly selected five-digit strings, including leading zeros (`00000`–`99999`). They are stored as text and cannot exceed five digits.
- Copy, search, pause or resume your links. Paused links return HTTP 410; unknown codes return 404. Codes stay reserved so old shared URLs never silently become someone else's destination.
- Redirects use HTTP 302 and no-store caching, so pausing works without permanent redirect caching. Counts represent GET redirect requests, not unique humans. Bots may increment them.
- Sessions expire after 30 minutes without owner activity. Sign out on shared computers.
- The finite namespace supports **100,000 lifetime codes**. It fails safely at capacity instead of generating longer links.
- Five digits are guessable. Do not use shortened URLs as access control for private documents.

## InfinityFree limitations

The host's mandatory browser verification can add `?i=1` or another challenge parameter before your PHP code executes. The application never generates those parameters and cannot guarantee that the host will keep the address bare during verification. If absolutely no added parameter at any point is mandatory, InfinityFree free hosting cannot guarantee that requirement. Bots, preview crawlers and cross-domain API calls may be blocked. No bypass is included.

InfinityFree's administrator has previously warned that infrastructure provider iFastNet may suspend URL shorteners. Owner-only creation reduces abuse but does not guarantee permission. Confirm your intended use with the host before relying on these links long term.

References: [browser security restrictions](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353), [shortener policy discussion](https://forum.infinityfree.com/t/can-i-use-infinityfree-as-url-shortener/31273).

## Security and recovery

PDO prepared statements, hashed passwords, secure HttpOnly SameSite cookies, CSRF tokens, DB-backed per-IP attempt limits (10 attempts / 15 minutes), session regeneration, protected configuration files and output escaping are included. URL validation allows only HTTP(S), rejects credentials/control characters, and blocks this domain to avoid direct self-redirect loops. Destinations are redirected to, never fetched by the server.

If you forget your owner password, use phpMyAdmin to delete only the row with `id=1` from `settings`, rotate `setup_key` in your private configuration, then immediately repeat `/setup`. Existing links remain. Anyone with the setup key could claim ownership while that row is absent. Protect your hosting account.

Back up the MySQL database and your private config separately. The public repo and deployment ZIP do not contain your live link data. For updates, preserve config.php and the database; do not rerun first-time owner setup.

## Local development

Requires PHP 8.1+ with PDO MySQL and MySQL/MariaDB. Import schema.sql, copy the config example privately and fill local database settings. Run `php -S 127.0.0.1:8080 dev-router.php`. The router is for local development only; the ZIP uses Apache rewrite rules. Loopback requests allow HTTP for development. Production requires HTTPS.

Run `php tests/validation.php` for destination validation, and `php -l filename.php` for syntax checks. `tests/capacity.php` exercises all 100,000 slots against a disposable local MariaDB database only; read its guard before running it. Local HTTP testing does not reproduce InfinityFree's browser challenge or verify its live Apache configuration.
