# serg · Private link studio

Dashboard: **https://gshergd.github.io/serg/**  
Short links: **https://ser.gt.tc/JzQN0** (five case-sensitive letters/numbers)

The deployment is connected and verified. Sign in to the dashboard with your owner password to create, copy, search, pause or resume links. There is no public sign-up.

## How the three services connect

1. GitHub Pages serves the dashboard. Its public `config.js` points to `https://backend-rho-lovat-88.vercel.app`.
2. Vercel runs the API from the separate [Sergent repository](https://github.com/larianakantuka/Sergent). Its root directory is `.`. MongoDB Atlas stores links and hashed sessions in the `serg` database.
3. InfinityFree serves redirects only. PHP makes an outbound HTTPS lookup to Vercel using a private resolver secret. Opening `https://ser.gt.tc/` redirects to GitHub Pages.

No worker or browser request into InfinityFree is needed. The `backend/` directory here is a reference copy; the separate Sergent repo supplies the deployed API.

## Link format

Codes use exactly five characters from `0–9`, `a–z`, `A–Z`: 916,132,832 possible combinations. Uppercase and lowercase are different. MongoDB reserves each code atomically and duplicate reservations retry. Paused codes stay reserved. Existing numeric links keep working. Generated addresses have no extra path or query string.

## Configuration and recovery

- GitHub Pages publishes `main`, `/ (root)`, with `.nojekyll`.
- Vercel Production settings: `MONGODB_URI`, `MONGODB_DB=serg`, `OWNER_PASSWORD_HASH`, `RESOLVER_SECRET`, `FRONTEND_ORIGIN=https://gshergd.github.io`. Redeploy after changing environment variables.
- Atlas uses a dedicated user restricted to read/write on `serg`.
- InfinityFree document root contains `.htaccess`, `index.php`, `resolver.php`, and a private `config.php` with `api_base_url` and the matching `resolver_secret`. `config.example.php` documents its shape.
- The public InfinityFree ZIP has files at its root: extract its contents directly into `/htdocs`, preserving the existing private `config.php`. It contains no secrets.
- Never publish the MongoDB URI, password/hash, resolver secret, owner login file or session tokens. Back up MongoDB separately; source ZIPs do not contain live links.

## Verification

The live dashboard created `https://ser.gt.tc/JzQN0`, which redirected successfully to its destination. Owner login, listing and redirect counts were checked. Unit tests, PHP syntax validation and disposable local MongoDB integration passed, including authentication, CORS, session expiry/logout, pause/resume, literal search, rate limits, old numeric links and concurrent code allocation.

Redirects use HTTP 302 with no-store caching. Counts measure GET requests; HEAD does not increment them. Owner sessions expire after one hour. Short URLs are public and should not be used to protect private documents. InfinityFree controls its browser challenge, which can affect previews or add a temporary query string despite the generated code staying five characters.
