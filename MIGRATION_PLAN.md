# ChessFlow: Pelan Migrasi ke Laravel + TypeScript

Pelan ini memindahkan prototaip ChessFlow (satu fail HTML) ke aplikasi Laravel sebenar, dan dihos di Coolify dengan Cloudflare di depan.
Setiap fasa boleh diserahkan kepada Claude Code (CC) sebagai satu sesi kerja. Fasa disusun supaya setiap satu menghasilkan sesuatu yang boleh dicuba.

---

## 0. Kandungan pakej ini

| Fail | Guna |
|---|---|
| `MIGRATION_PLAN.md` | Dokumen ini |
| `CLAUDE.md` | Panduan ringkas untuk CC. Salin ke akar projek Laravel |
| `data/lessons.json` | 5 tahap, 32 pelajaran/ujian, 159 langkah. Sumber kebenaran untuk seeder |
| `prototype/chessflow.html` | Prototaip yang berfungsi. Buka dalam browser sebagai rujukan tingkah laku |
| `prototype/src/engine.js` | Enjin prototaip: papan, 7 jenis langkah, bot, Stockfish, mod ujian, teka-teki harian |
| `prototype/src/content.js` | Kandungan asal (sama dengan `lessons.json`) |
| `prototype/src/*.css` | Gaya prototaip: token warna, papan, peta. Ikon buah ada dalam `pieces.css` (set rhosgfx, CC0) |
| `public/stockfish/` | Stockfish 10 (WASM + fallback asm.js). Letak di `public/stockfish/` dalam Laravel |
| `tools/validate-lessons.cjs` | Penyemak kandungan (chess.js v1): FEN sah, langkah sah, jawapan wujud. Sudah diuji: 32 pelajaran, 0 ralat |
| `tools/stockfish-analyse.cjs` | Analisis kedudukan dengan Stockfish di Node (perlu `npm i -D stockfish@16.0.0`) |

---

## 1. Seni bina

```
Browser
 ├─ Halaman Livewire 4 (peta, profil, papan pemuka guru/ibu bapa, log masuk)
 │    └─ "Pulau" TypeScript dalam <div wire:ignore data-chessflow="...">
 │         ├─ Papan + 7 jenis langkah + mod ujian    (resources/ts/chessflow)
 │         ├─ chess.js (peraturan catur)
 │         └─ Web Worker: /stockfish/stockfish.js  (permainan penuh + petunjuk)
 │
 └─ Pulau TS lapor keputusan → Livewire.dispatch('lesson-completed', {...})
                                 └─ Komponen Livewire simpan ke DB (XP, bintang, streak)

Laravel 12 ── MySQL/Postgres ── Scheduler (teka-teki harian 00:05 MYT)
Hos: Coolify (VPS)  ←  Cloudflare (DNS, SSL, CDN, cache aset)
```

**Perubahan kecil daripada perbincangan awal:** dalam Pilihan A, pulau TS **tidak perlu REST API** untuk berfungsi. Data pelajaran dihantar terus dalam halaman Blade (`@js($lesson->steps)`), dan keputusan dihantar balik melalui event Livewire. Ini mengurangkan bahagian yang bergerak: tiada CORS, token atau endpoint tambahan. REST API (`/api/...`) direka dalam Fasa 6, hanya bila Wan perlukan app mobile.

---

## 2. Stack dan versi

| Bahagian | Pilihan |
|---|---|
| Backend | Laravel 12, PHP 8.3+ |
| UI halaman | Livewire 4 SFC (`resources/views/pages/⚡nama.blade.php`, `Route::livewire(...)`) |
| CSS | Tailwind CSS v4, token warna OKLCH (ikut design system Wan) |
| Pulau interaktif | TypeScript melalui Vite (sudah ada dalam Laravel) |
| Peraturan catur | `chess.js` v1.x dari npm |
| Enjin AI | Stockfish 10 WASM sebagai Web Worker (fail dalam `public/stockfish/`) |
| Ujian | Pest (PHP), Vitest (TS), Playwright untuk aliran hujung-ke-hujung |
| Admin kandungan | Filament v5 (Fasa 5) |
| Database | MySQL 8 atau Postgres 16 (resource Coolify) |
| Hos | Coolify di VPS + Cloudflare proxy |

> **Amaran untuk CC: chess.js v1 berbeza daripada v0.10 dalam prototaip.**
> Prototaip guna v0.10.3 (`in_checkmate()`, `in_stalemate()`, `game_over()`, `move()` pulangkan `null` jika haram).
> v1 guna `isCheckmate()`, `isStalemate()`, `isGameOver()`, `isDraw()`, `inCheck()`, `isThreefoldRepetition()`, `isInsufficientMaterial()`,
> dan **`move()` melontar ralat jika langkah haram**, jadi balut dengan `try/catch`. `load()` juga melontar ralat untuk FEN tidak sah.
> Semak dokumentasi chess.js versi yang dipasang sebelum port.

---

## 3. Struktur folder sasaran

```
chessflow/
├─ app/
│  ├─ Models/            User, Level, Lesson, LessonProgress, ExamAttempt, Certificate,
│  │                     DailyPuzzle, DailyCompletion, Game, XpEvent, Classroom
│  ├─ Actions/           CompleteLesson, SubmitExam, CompleteDaily, RecordGame, AwardXp
│  ├─ Console/Commands/  PickDailyPuzzle, ImportLessons
│  └─ Policies/          LessonPolicy (semak pelajaran sudah dibuka)
├─ database/
│  ├─ migrations/
│  └─ seeders/LessonSeeder.php    ← baca data/lessons.json
├─ data/lessons.json
├─ resources/
│  ├─ css/app.css                 ← Tailwind v4 + token ChessFlow + papan
│  ├─ ts/chessflow/
│  │  ├─ types.ts                 ← jenis Step (lihat §6)
│  │  ├─ core/squares.ts          ← nm(), sq(), gen(), lpath(), tourOpt()
│  │  ├─ core/board.ts            ← kelas Board (DOM, animasi, anak panah SVG)
│  │  ├─ core/sound.ts
│  │  ├─ steps/{explain,find,collect,tap,quiz,puzzle,play}.ts
│  │  ├─ runner/lesson-runner.ts  ← gelung langkah, kesilapan, mod ujian, bintang
│  │  ├─ game/game.ts             ← permainan penuh
│  │  ├─ engine/stockfish.ts      ← pembalut Worker + barisan permintaan
│  │  ├─ engine/simple-bot.ts     ← bot flee/chase/defend
│  │  └─ mount.ts                 ← cari [data-chessflow] dan pasang
│  ├─ ts/app.ts                   ← import mount.ts
│  └─ views/pages/
│     ├─ ⚡peta.blade.php          ← peta pelajaran
│     ├─ ⚡pelajaran.blade.php     ← satu pelajaran (pulau TS)
│     ├─ ⚡harian.blade.php        ← teka-teki harian
│     ├─ ⚡main.blade.php          ← permainan penuh
│     ├─ ⚡sijil.blade.php         ← sijil (boleh disahkan melalui kod)
│     └─ guru/⚡kelas.blade.php    ← papan pemuka guru
├─ public/stockfish/              ← stockfish.js, .wasm, .asm.js
└─ tests/ (Pest) + resources/ts/**/*.test.ts (Vitest) + e2e/ (Playwright)
```

---

## 4. Skema database

```
users               id, name, username (unik), email (nullable), password,
                    role enum(murid, ibubapa, guru, admin), pin (nullable, untuk murid kecil),
                    xp int default 0, streak_current int, streak_best int, streak_last_date date null,
                    preferences json (bunyi, warna/tahap permainan), timestamps

levels              id, number (1–5), name, note, position

lessons             id, level_id, slug (unik), title, icon (cth 'wN'), kind enum(lesson, exam),
                    position, xp, tip text null, steps json, is_published bool,
                    content_version int, timestamps

lesson_progress     id, user_id, lesson_id, best_stars tinyint, last_stars tinyint,
                    mistakes int, attempts int, completed_at, timestamps
                    unique(user_id, lesson_id)

exam_attempts       id, user_id, lesson_id, score, total, passed bool,
                    failed_steps json, created_at

certificates        id, user_id, level_id, exam_attempt_id, display_name, score, total,
                    code char(10) unik (untuk URL pengesahan /sijil/{code}), issued_at

daily_puzzles       id, date (unik), lesson_id, step_index, created_at
daily_completions   id, user_id, date, solved_at          unique(user_id, date)

games               id, user_id, user_color enum(w,b), level tinyint, result enum(win, loss, draw, resign),
                    pgn text, move_count, finished_at

xp_events           id, user_id, amount, source_type, source_id, created_at   ← lejar XP
classrooms          id, teacher_id, name, join_code (unik), timestamps
classroom_user      classroom_id, user_id
guardian_student    guardian_id, student_id
```

**Peraturan penting (dikuatkuasakan di server, bukan di browser):**
- Pelajaran `n` hanya dibuka jika pelajaran `n-1` (ikut `position`) ada dalam `lesson_progress`, atau pengguna ialah guru/admin (pengganti "Mod guru").
- XP diambil dari `lessons.xp` dalam DB, bukan dari nilai yang dihantar browser. Ulang pelajaran = 20% XP.
- Ujian lulus jika `score >= ceil(total * 0.7)`. Sijil hanya dibuat bila lulus.
- Streak: jika `streak_last_date` = semalam → +1; jika hari ini → tiada perubahan; selain itu → 1. Zon masa **Asia/Kuala_Lumpur**.
- Bintang: `mistakes <= 1` → 3, `<= 4` → 2, selain itu → 1 (sama macam prototaip).

**Privasi murid (PDPA):** murid kecil tidak perlu e-mel. Guna `username` + PIN atau kata laluan gambar, dan akaun dicipta oleh guru atau ibu bapa. Simpan data peribadi seminimum mungkin, dan nama pada sijil diisi oleh murid sendiri.

---

## 5. Jambatan pulau TS ↔ Livewire

```blade
{{-- resources/views/pages/⚡pelajaran.blade.php --}}
<div wire:ignore
     data-chessflow="lesson"
     data-lesson='@json($lessonPayload)'>   {{-- slug, title, kind, steps, tip --}}
</div>
```

```ts
// mount.ts (ringkas)
document.querySelectorAll<HTMLElement>('[data-chessflow]').forEach(el => {
  const payload = JSON.parse(el.dataset.lesson!);
  const runner = new LessonRunner(el, payload);
  runner.on('complete', (r) => {
    // r = { slug, mistakes, failedSteps, durationMs }
    window.Livewire.dispatch('lesson-completed', r);
  });
});
```

```php
// dalam komponen ⚡pelajaran
#[On('lesson-completed')]
public function complete(string $slug, int $mistakes, array $failedSteps = []): void
{
    $result = app(CompleteLesson::class)->handle(auth()->user(), $this->lesson, $mistakes, $failedSteps);
    $this->dispatch('show-result', $result);   // TS paparkan modal Tahniah / Sijil
}
```

Untuk navigasi Livewire (`wire:navigate`), `mount.ts` mesti dijalankan semula pada event `livewire:navigated`, dan menyahpasang pulau lama untuk elak kebocoran timer dan Worker.

---

## 6. Skema JSON langkah (`steps`)

Semua langkah ada medan biasa: `type`, `title`, `say` (HTML ringkas), `task`, serta kedudukan **sama ada** `fen` (guna chess.js) **atau** `pcs` (senarai ringkas seperti `"wNd4 bPe5"`, tanpa peraturan penuh). Medan pilihan: `orient` (`"b"` untuk papan terbalik), `hl`, `ring`, `dots` (petak; boleh guna `"file:e"` atau `"rank:4"`), `arrows` (`[["e2","e4"]]`), `counter`.

| type | Medan khusus | Lulus bila |
|---|---|---|
| `explain` | `showMoves`, `initPath`, `demo` (UCI), `demoNotes` {indeks: {status, arrows, ring}}, `demoEnd` | Terus |
| `find` | `piece` (petak buah), `done` | Semua petak sasaran ditekan |
| `collect` | `piece`, `stars[]` | Semua bintang dikutip. Lebih langkah daripada optimum = kesilapan |
| `tap` | `seq[]` {ask, sq[], all?, ok?, arrows?, dotsOf?}, `wrong`, `wrongMap` | Semua item `seq` dijawab |
| `quiz` | `options[]` {t, ok?, why?} | Pilihan `ok` dipilih |
| `puzzle` | `line[]` (UCI, giliran pengguna/lawan berselang), `alts` {ply: [SAN]}, `mids[]`, `mid`, `after1`, `acceptSan[]`, `acceptMate`, `accept` {piece, notPiece, to[], toFile[], capture, noMateInOne}, `hintRing`, `wrongMsg`, `win` | Langkah diterima mengikut peraturan di kiri |
| `play` | `goal` (mate, promote, captureQ), `bot` (flee, chase, defend), `maxMoves`, `win` | Matlamat dicapai sebelum had |

Pelajaran: `id`→`slug`, `tahap`→`level`, `title`, `icon`, `exam` (bool), `tip`, `steps[]`. Dalam mod ujian, kesilapan pertama menandakan soalan itu salah dan butang Teruskan dibuka.

**Terjemahan (pilihan).** Teks sumber ialah Bahasa Melayu. Terjemahan diletak bersebelahan di bawah kod bahasa:
- Tahap: `"en": {"name", "note"}`. Pelajaran: `"en": {"title", "tip"}`.
- Langkah: `"en": {...}` dengan medan teks sahaja: `title`, `say`, `task`, `wrong`, `wrongMsg`, `win`, `done`, `mid`, `mids[]`, `ok`, `counter`, `wrongMap`, `options[] {t, why}`, `seq[] {ask, ok, wrong}`, `demoNotes {status}`.
- Medan yang tiada terjemahan kekal BM; senarai digabung ikut kedudukan. `validate-lessons` menolak medan logik catur (fen, line, accept, …) dalam blok terjemahan. Pemegang tempat seperti `{sq}` mesti dikekalkan.

---

## 7. Fasa kerja

Setiap fasa ada **matlamat**, **siap bila** dan **arahan untuk CC**. Mulakan setiap sesi CC dengan: *"Baca CLAUDE.md dan MIGRATION_PLAN.md §7 Fasa N."*

### Fasa 0: Rangka projek dan deploy awal (½ hari)
- `laravel new chessflow` (starter kit Livewire), Livewire 4, Tailwind v4, Pest.
- Tambah TypeScript ke Vite (`resources/ts/app.ts`, `tsconfig.json`), `npm i chess.js`, `npm i -D vitest @playwright/test`.
- Salin `data/`, `public/stockfish/` dan `tools/` dari pakej ini.
- Deploy "hello world" ke Coolify **sekarang**, supaya masalah hos dijumpai awal (lihat §8).

**Siap bila:** URL produksi memaparkan halaman Laravel, dan `/stockfish/stockfish.wasm` dihantar dengan `Content-Type: application/wasm`.

> CC: *"Sediakan projek Laravel 12 + Livewire 4 + Tailwind v4 + TypeScript (Vite) + Pest + Vitest ikut MIGRATION_PLAN §2–3. Jangan bina ciri lagi. Tambah skrip npm `typecheck`, `test:ts` dan `validate:lessons` (jalankan tools/validate-lessons.cjs ke atas data/lessons.json)."*

### Fasa 1: Port enjin ke TypeScript tanpa backend (2–3 hari)
- Pecahkan `prototype/src/engine.js` kepada modul dalam §3. Tukar ke chess.js v1.
- Tulis `types.ts` daripada §6 (union type mengikut `type`).
- Halaman `/dev/playground` (hanya dalam `local`) yang memuatkan `data/lessons.json` dan membolehkan semua 32 pelajaran dimainkan, sama macam prototaip.
- Ujian Vitest untuk `gen()`, `lpath()`, `tourOpt()`, dan penerimaan jawapan puzzle (line, alts, acceptMate, accept.noMateInOne).
- Ujian Playwright: port `prototype` click-through (klik semua pelajaran, tiada ralat konsol).

**Siap bila:** Semua pelajaran boleh dimainkan di playground, dan tingkah lakunya sama dengan `prototype/chessflow.html`. `npm run typecheck` dan `npm run test:ts` lulus.

> CC: *"Port prototype/src/engine.js ke resources/ts/chessflow mengikut struktur MIGRATION_PLAN §3 dan skema §6. Guna chess.js v1 (lihat amaran §2). Kekalkan tingkah laku yang sama. Mulakan dengan types.ts dan core/, tulis ujian Vitest, kemudian steps/ satu demi satu. Buat /dev/playground untuk saya cuba."*

### Fasa 2: Database dan kandungan (1 hari)
- Migration ikut §4, model dan hubungan.
- `LessonSeeder` / `php artisan chessflow:import-lessons` membaca `data/lessons.json` (idempotent, ikut `slug`).
- `npm run validate:lessons` dijalankan dalam CI dan sebelum import.

**Siap bila:** `migrate:fresh --seed` menghasilkan 5 tahap, 32 pelajaran dan 159 langkah. Ada ujian Pest untuk import.

### Fasa 3: Akaun, peta dan kemajuan (3–4 hari)
- Log masuk: guru/ibu bapa guna e-mel; murid guna username + PIN.
- `⚡peta`: peta zigzag mengikut tahap, status (selesai/buka/terkunci), bintang, meter kemajuan, kad Harian dan kad Main.
- `⚡pelajaran`: pulau TS + jambatan §5. Tindakan `CompleteLesson` (XP, bintang, buka kunci) dan `SubmitExam` (markah, sijil).
- `⚡sijil/{code}`: halaman sijil awam (boleh dicetak), dengan pengesahan kod.
- Policy: tak boleh buka pelajaran yang masih terkunci (uji dengan Pest).

**Siap bila:** Murid baharu boleh log masuk, habiskan Papan Catur, nampak Kenal Kuda terbuka, dan XP bertambah. Ujian Tahap 1 menghasilkan sijil.

### Fasa 4: Teka-teki harian dan permainan penuh (2 hari)
- Arahan `chessflow:pick-daily` dijadualkan setiap hari 00:05 Asia/Kuala_Lumpur. Ia memilih puzzle daripada pelajaran yang ditanda sesuai (seperti `DAILY_IDS` dalam prototaip), tanpa ulangan dalam 30 hari.
- `⚡harian`: streak dan XP mengikut peraturan §4.
- `⚡main`: permainan penuh (warna, 3 tahap, undur, petunjuk, mengaku kalah). Simpan ke `games` bersama PGN. XP bila menang.
- `engine/stockfish.ts`: satu Worker dikongsi, barisan permintaan, timeout, fallback ke simple-bot jika Worker gagal.

**Siap bila:** Streak bertambah merentas dua hari (uji dengan `Carbon::setTestNow`), dan permainan Sukar berjalan di telefon tanpa membeku.

### Fasa 5: Guru, ibu bapa dan admin (3 hari)
- Guru cipta kelas → kod sertai → tambah murid (cipta akaun murid secara pukal).
- `guru/⚡kelas`: jadual murid × pelajaran (bintang), XP, streak, ujian lulus, aktiviti terakhir.
- Ibu bapa: pautkan anak, lihat kemajuan.
- Filament v5: urus pelajaran (lihat JSON langkah baca sahaja + pratonton pulau TS), terbitkan atau sembunyikan, susun semula. *Keputusan: kandungan kekal dalam `data/lessons.json`; import tidak menimpa `position` dan `is_published`.*

### Fasa 6: Produksi dan pilihan masa depan
- Sandaran DB harian (Coolify → S3/R2), Sentry atau log ralat, had kadar (rate limit) pada tindakan kemajuan.
- REST API `/api/v1` (Sanctum) **hanya jika** app mobile diperlukan: `GET levels`, `GET lessons/{slug}`, `POST lessons/{slug}/complete`, `POST exams/{slug}/submit`, `GET daily`, `POST daily/complete`, `POST games`.
- Set buah sendiri (lihat §9) dan maskot Pak Kuda versi ilustrasi.

---

## 8. Deploy: Coolify + Cloudflare

**Strategi hos (keputusan):**
- **Fasa 0–4 (bina dan beta):** deploy ke server Hetzner sedia ada (bersama Ihsan dan Mailr), dalam *Project* Coolify berasingan bernama `chessflow`.
  - Tetapkan **had sumber** pada container app ChessFlow dan database-nya (Coolify → Resource → *Advanced → Resource Limits*). Contoh permulaan: 1 vCPU, 768 MB RAM untuk app, 512 MB untuk DB. Laraskan ikut metrik.
  - Database ChessFlow **sendiri** (container berasingan), bukan dikongsi dengan Ihsan.
  - Pantau *Metrics* Coolify. Jika RAM server melebihi ~70% secara berterusan, terus pindah.
- **Sebelum buka kepada sekolah atau ramai pengguna:** tambah server Hetzner kecil baharu dalam **Coolify yang sama** (*Servers → Add Server*), kemudian pindahkan resource ChessFlow ke server itu. Asingkan data kanak-kanak daripada sistem bayaran Ihsan.
  - Pindah = deploy semula app ke server baharu, `pg_dump`/`mysqldump` → restore, kemudian tukar DNS di Cloudflare. Tiada perubahan kod.

**Coolify (aplikasi Laravel):**
1. Resource baharu → Git repo → Build pack **Nixpacks** (kesan Laravel automatik) atau Dockerfile sendiri.
2. Pembolehubah env: `APP_ENV=production`, `APP_KEY`, `APP_URL`, `APP_TIMEZONE=Asia/Kuala_Lumpur`, `DB_*`, `SESSION_DRIVER=database`, `CACHE_STORE=database` (atau Redis).
3. Pastikan fasa build menjalankan `npm ci && npm run build`.
4. Arahan selepas deploy: `php artisan migrate --force && php artisan optimize && php artisan chessflow:import-lessons`.
5. **Scheduler:** tambah *Scheduled Task* di Coolify `php artisan schedule:run` setiap minit, atau proses `schedule:work`. Ia wajib untuk teka-teki harian.
6. Database: resource MySQL/Postgres dalam Coolify, dengan sandaran berjadual dihidupkan.
7. Semak `curl -I https://domain/stockfish/stockfish.wasm` → `Content-Type: application/wasm`. Jika salah, tambah jenis MIME dalam konfigurasi Nginx atau Caddy.

**Cloudflare (di depan Coolify):**
1. DNS domain ke IP VPS, dengan proxy dihidupkan (awan oren).
2. SSL/TLS mod **Full (strict)**. Coolify keluarkan sijil Let's Encrypt atau guna Cloudflare Origin Certificate.
3. Cache Rule: `/build/*` dan `/stockfish/*` → cache tepi 1 bulan (fail Vite sudah ada hash; namakan semula folder stockfish jika versi berubah).
4. **Jangan** cache halaman HTML atau `/livewire/*`.
5. Pilihan: WAF asas dan had kadar pada `/login`.

**Kenapa bukan Vercel/Cloudflare Pages sahaja:** Laravel perlukan PHP, scheduler dan sesi yang kekal. Vercel hanya ada runtime PHP komuniti (serverless, tiada scheduler atau queue), dan Cloudflare Workers tidak menjalankan PHP. Coolify + Cloudflare memberi kelebihan kedua-duanya.

---

## 9. Lesen dan risiko

| Perkara | Status | Tindakan |
|---|---|---|
| Ikon buah "rhosgfx" oleh RhosGFX (dalam `pieces.css`, dari repo lichess-org/lila) | CC0 1.0 (domain awam) | Selesai: menggantikan set cburnett (GPLv2+). Tiada syarat atribusi, tetapi dikreditkan di halaman "Tentang" |
| Stockfish | GPLv3 | Dihantar sebagai fail berasingan. Paparkan notis lesen dan pautan kod sumber di halaman "Tentang" |
| chess.js | BSD-2 | Selamat. Kekalkan notis lesen |
| Kandungan pelajaran | Asli, ditulis untuk ChessFlow | Buku Levy Rozman hanya rujukan struktur. Jangan salin teks atau diagram buku |
| Teka-teki tambahan | Pangkalan data puzzle Lichess (CC0) | Boleh diimport kemudian untuk bank teka-teki harian yang lebih besar |
| Langkah dari browser boleh dipalsukan | Risiko rendah (laman pendidikan) | XP dikira di server, had kadar, semak pelajaran sudah dibuka |

---

## 10. Cara tambah pelajaran baharu (selepas migrasi)

1. Tambah objek pelajaran dalam `data/lessons.json` (sumber kandungan tunggal; Filament hanya untuk pratonton, terbit/sembunyi dan susun semula).
2. `npm run validate:lessons`: menyemak FEN, langkah dan jawapan.
3. Untuk teka-teki taktik atau endgame, sahkan dengan `node tools/stockfish-analyse.cjs "<FEN>" 20 6`.
4. `php artisan chessflow:import-lessons` → cuba di `/dev/playground` → terbitkan.
5. Terjemahan English (pilihan): tambah blok `"en"` seperti §6, jalankan semula langkah 2 dan 4, kemudian semak dengan butang **EN** di header.
