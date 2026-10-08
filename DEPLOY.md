# Deploy ChessFlow (Coolify + Cloudflare)

Senarai semak untuk MIGRATION_PLAN §8. Langkah bertanda **[awak]** dibuat dalam Coolify/Cloudflare.

## 1. Resource Coolify [awak]

- Project `chessflow` → resource baharu daripada repo GitHub `coachwmafendi/chessflow`, branch `main`.
- Build pack **Nixpacks** (Laravel dikesan automatik; `npm ci && npm run build` dijalankan kerana ada `package.json`).
- **PHP 8.4** diperlukan (Symfony 8 dalam `composer.lock`). Nixpacks membaca `"php": "^8.4"` daripada `composer.json`; jika imej memilih versi lain, tetapkan `NIXPACKS_PHP_VERSION=8.4`.
- Database: resource **PostgreSQL** berasingan dalam project yang sama (suite ujian sudah lulus pada Postgres 17). MySQL juga boleh.
- Had sumber permulaan: app 1 vCPU / 768 MB, DB 512 MB.
- Health check: `/up`.

## 2. Pembolehubah env [awak]

```
APP_NAME=ChessFlow
APP_ENV=production
APP_KEY=            # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://<domain>
APP_LOCALE=ms
APP_TIMEZONE=Asia/Kuala_Lumpur

DB_CONNECTION=pgsql
DB_HOST=<host resource DB Coolify>
DB_PORT=5432
DB_DATABASE=chessflow
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# E-mel untuk tetapan semula kata laluan guru/ibu bapa
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=...
```

## 3. Arahan selepas deploy [awak]

```
php artisan migrate --force && php artisan optimize && php artisan chessflow:import-lessons && php artisan chessflow:doctor
```

- `chessflow:import-lessons` menjalankan `tools/validate-lessons.cjs` dengan Node dahulu. Jika Node tiada dalam container, tambah `--skip-validation` (CI sudah mengesahkan setiap push).
- `chessflow:doctor` menyemak env, DB, pelajaran, admin, teka-teki harian, Node, build Vite dan fail Stockfish. Ia gagal (exit 1) jika ada perkara kritikal.

## 4. Scheduler [awak]

Coolify → Scheduled Tasks: `php artisan schedule:run` setiap minit. Ia menjalankan `chessflow:pick-daily` jam 00:05 waktu Malaysia. (Jika scheduler terhenti, `/harian` tetap memilih teka-teki bila dibuka.)

## 5. Admin pertama

1. Daftar di `/register` dengan e-mel awak.
2. Dalam terminal container: `php artisan chessflow:set-role <email> admin`
3. Panel admin: `/admin`. Guru dinaikkan peranan di **Pengguna** atau dengan `chessflow:set-role <email> guru`.

## 6. Sandaran DB [awak]

Coolify → resource DB → Backups: harian (cth. 02:00), simpan 14 hari, destinasi S3 (Cloudflare R2: bucket peribadi + token API R2 yang hanya boleh tulis ke bucket itu). Cuba **restore** sekali ke DB ujian sebelum buka kepada sekolah.

## 7. Cloudflare [awak]

- DNS ke IP server, proxy (awan oren) hidup. SSL/TLS **Full (strict)**.
- Cache Rules: `/build/*` dan `/stockfish/*` → Eligible for cache, Edge TTL 1 bulan.
- **Jangan** cache HTML, `/livewire/*`, `/admin/*`.
- Rate limiting (pilihan): `/login`, `/masuk-murid`, `/admin/login`. Aplikasi juga ada had sendiri (5 cubaan/minit).
- App mempercayai header proxy (`trustProxies`), jadi URL kekal `https`.

## 8. Ujian asap selepas deploy

- [ ] `curl -I https://<domain>/stockfish/stockfish.wasm` → `Content-Type: application/wasm`
- [ ] `/` dipaparkan dalam BM; `/tentang` ada notis lesen.
- [ ] Log masuk guru → `/guru` → cipta kelas → tambah 2 murid.
- [ ] Log masuk murid di `/masuk-murid` → habiskan Papan Catur → Kenal Kuda terbuka, XP bertambah.
- [ ] `/harian` dan `/main` (tahap Sukar) berfungsi di telefon.
- [ ] `/admin` hanya boleh dibuka oleh admin.

## Ralat dan log

Log ke `stderr` → Coolify → Logs. Sentry boleh ditambah kemudian (`sentry/sentry-laravel` + `SENTRY_LARAVEL_DSN`).

## Belum dibuat (MIGRATION_PLAN Fasa 6 / §9)

- **Set buah sendiri** untuk ganti ikon cburnett (GPLv2+) sebelum lancar secara komersial, dan maskot Pak Kuda ilustrasi.
- **REST API `/api/v1`**: hanya jika app mobile diperlukan.
