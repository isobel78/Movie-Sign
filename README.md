# 🚨 MovieSign!
### *We've got movie sign.*

> Inspired by the legendary scramble from Mystery Science Theater 3000 — MovieSign! sounds the alarm when a film on your watchlist is actually playing nearby.

---

## What It Does

Most moviegoers juggle two separate platforms: one to track films they want to see (Letterboxd, a notes app, their memory), and another to check showtimes (Fandango, AMC, the theater's own app). MovieSign! collapses that into a single filtered view — enter your zip code, pick a date, and see only the showtimes for films already on *your* watchlist.

**Core features:**
- User account creation, login, and session management
- Personal watchlist — search, add, and remove titles via TMDB
- Zip-code-based showtime view filtered to watchlist titles only
- "We've got movie sign!" alert when a watchlisted film is playing nearby
- Mobile-first responsive UI

---

## Tech Stack

| Layer | Technology |
|---|---|
| Server-side | PHP 8.2 (MVC-style architecture) |
| Database | MySQL with PDO prepared statements |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Showtime data | MovieGlu API |
| Movie search | TMDB API |
| Geocoding | Zippopotam.us (no key required) |
| Email | PHPMailer |
| Local dev | XAMPP (Apache + PHP 8.2.4) |
| Hosting | GoDaddy shared hosting (cPanel) |
| SSL | ZeroSSL |
| Version control | Git / GitHub |

---

## Project Structure

```
moviesign/
├── app/                            # Above web root
│   ├── Controller/
│   │   ├── auth.php
│   │   ├── movieglu_showtimes.php  # Server-side MovieGlu proxy
│   │   ├── tmdb_search.php         # Server-side TMDB proxy
│   │   └── watchlist.php
│   ├── Model/
│   │   ├── db_config.php           # Live DB credentials (not in git)
│   │   ├── db_config_local.php     # Testing DB credentials (not in git)
│   │   ├── db_session.php
│   │   ├── db_user.php
│   │   ├── db_watchlist.php
│   │   ├── db.php
│   │   ├── mail_config.php         # Mail credentials (not in git)
│   │   ├── movieglu_config.php     # MovieGlu API keys (not in git)
│   │   └── tmdb_config.php         # TMDB API key (not in git)
│   ├── View/
│   │   └──  auth_layout.php
├── public/                         # Web root
│   ├── account.php
│   ├── forgot_password.php
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── reset_password.php 
│   ├── styles/
│   │   ├── auth.css
│   │   └──  main.css
└── vendor/
    └── phpmailer/
```

---

## Local Development Setup

### Prerequisites
- XAMPP (PHP 8.2+, Apache, MySQL)
- Git

### Steps

1. **Clone the repo**
   ```bash
   git clone https://github.com/isobel78/Movie-Sign.git
   cd Movie-Sign
   ```

2. **Set up config files** (not included in repo — create manually)

   `app/Model/db_config_local.php`
   ```php
   <?php
   return [
       'host'   => 'localhost',
       'dbname' => 'moviesign',
       'user'   => 'root',
       'pass'   => '',
   ];
   ```

   `app/Model/tmdb_config.php`
   ```php
   <?php
   return ['bearer_token' => 'YOUR_TMDB_BEARER_TOKEN'];
   ```

   `app/Model/movieglu_config.php`
   ```php
   <?php
   return [
       'sandbox' => [
            'client'        => 'YOUR_MOVIEGLU_CLIENT',
            'x-api-key'     => 'YOUR_MOVIEGLU_KEY',
            'authorization' => 'YOUR_MOVIEGLU_AUTH',
            'territory'     => 'XX',
            'geo'           => '-22.0;14.0',
        ],
        'us' => [
            'client'        => 'YOUR_MOVIEGLU_CLIENT',
            'x-api-key'     => 'YOUR_MOVIEGLU_KEY',
            'authorization' => 'YOUR_MOVIEGLU_AUTH',
            'territory'     => 'US',
            'geo'           => null,
        ],
   ];
   ```

3. **Import the database schema**
   Open phpMyAdmin, create a database named `moviesign`, then import `create.sql`.

4. **Start XAMPP** and navigate to `https://localhost/Movie-Sign/public/`

---

## Environment Modes

MovieSign! uses a `MOVIEGLU_ENV` constant to toggle between sandbox and live API behavior.

| Mode | Behavior |
|---|---|
| `sandbox` | Fixed coordinates (Namibia test data), radius filter bypassed, "Sandbox" badge shown in UI |
| `live` | Real US geolocation via zip code, full radius filtering active |

> ⚠️ The US eval key has a **75-request limit** — only activate live mode on the deployed copy.

---

## Security Practices

- **Passwords** hashed with `password_hash()` (bcrypt) — plaintext never stored
- **SQL injection** prevented via PDO prepared statements throughout
- **XSS** mitigated with `htmlspecialchars()` on all output
- **API keys** kept server-side via proxy endpoints; never exposed to the client
- **Session security** — token-based session management (`db_session.php`), HttpOnly / Secure / SameSite cookies, session ID regenerated on login
- **IDOR protection** on watchlist remove actions
- **Credentials** excluded from Git via `.gitignore`; deployed to production via FTP
- **HTTPS** served via ZeroSSL on GoDaddy hosting
- **GitGuardian** monitoring active on the repository

---

## Deployment

Production is hosted on GoDaddy shared hosting (cPanel). Deployment is manual via FTP:

1. Update `index.php` on the server to set `define('MOVIEGLU_ENV', 'sandbox'); ` → `us`
2. FTP changed files to the server
3. Verify SSL is active and `HTTPS` redirects are in place

---

## Database Schema

```sql
users           -- email, bcrypt pw_hash, zip_code; reset_token for password recovery
watchlist_items -- user_id → film_id + title + poster_url; unique constraint prevents duplicates
sessions        -- token-based auth; expires_at enforces session lifetime
```

Showtime data from MovieGlu is fetched at request time and not persisted — keeps the schema lean and data fresh.

---

## API Reference

| API | Purpose | Auth |
|---|---|---|
| [TMDB](https://developer.themoviedb.org/) | Movie search & metadata | API key (server-side) |
| [MovieGlu](https://www.movieglu.com/) | Theater showtimes | API key + client ID (server-side) |
| [Zippopotam.us](https://www.zippopotam.us/) | Zip → lat/lng geocoding | None required |

---

## Known Behaviors & Notes

- **Sandbox geography:** Fixed coordinates resolve to Namibia — any distance-based filtering is intentionally bypassed in sandbox mode.
- **Date picker:** Defaults to the user's local date via `new Date()` in JS; the `filmsNowShowing` endpoint requires the date param or it always returns today's results.
- **Radius buttons:** Rebuilt after every panel re-render using a `radiusHTML()` + `bindRadiusBtns()` pattern to preserve event listeners.
- **`closestShowing` fallback** *(planned):* When no showtimes exist for the selected date, fall back to `closestShowing` and display *"Not playing today, but showing nearby on [date]."*

---

## Capstone Context

MovieSign! was built as a five-week capstone project for a software development course. The final deliverable includes a recorded video walkthrough demonstrating account creation, watchlist management, and the filtered showtime view on the live hosted site.

**The core competitive differentiator:** no existing platform cross-references a personal watchlist against live theater showtimes in a single filtered view.

---

*🤖 Crow, Tom, Gypsy, Cambot, and Joel would approve.*