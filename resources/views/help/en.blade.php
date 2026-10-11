{{-- Help docs, English. Keep in step with help/ms.blade.php and with what the app really does. --}}
<h1>Help</h1>
<p class="help-lede">How to use ChessFlow, for students, parents and teachers. Can't find an answer? Email <a href="mailto:{{ $email }}?subject=ChessFlow%20help">{{ $email }}</a>.</p>

<nav class="help-toc" aria-label="Contents">
    <b>Contents</b>
    <ol>
        <li><a href="#mula">Getting started</a></li>
        <li><a href="#murid">For students</a></li>
        <li><a href="#main">Playing Pak Kuda</a></li>
        <li><a href="#ibu-bapa">For parents</a></li>
        <li><a href="#guru">For teachers</a></li>
        <li><a href="#tetapan">Settings</a></li>
        <li><a href="#privasi">Privacy and data</a></li>
        <li><a href="#masalah">Common problems</a></li>
        <li><a href="#pentadbir">For administrators</a></li>
    </ol>
</nav>

<h2 id="mula">1. Getting started</h2>
<ul>
    <li><b>Students</b> need no email. A teacher or parent creates the account and gives them a <b>username</b> and <b>PIN</b>. Sign in at <a href="{{ route('murid.login') }}">Student sign-in</a>.</li>
    <li><b>Parents and teachers</b> <a href="{{ route('register') }}">register</a> with an email address, then <a href="{{ route('login') }}">log in</a>. New accounts are parent accounts; teacher accounts are switched on by an administrator (see <a href="#guru">For teachers</a>).</li>
    <li>ChessFlow runs in the web browser on a computer or tablet. Nothing to install.</li>
</ul>

<h2 id="murid">2. For students</h2>
<h3>The map</h3>
<p>After signing in you see the <b>map</b> with 5 levels. Each lesson unlocks the next one. Press <b>Continue</b> at the top to go straight to your lesson. Levels you have finished or not reached yet can be folded: press a level's title to open or close it. The map remembers your choice.</p>
<h3>Lessons and the board</h3>
<ul>
    <li>Pak Kuda explains every step. Read the <b>Task</b>, then answer on the board or pick an answer.</li>
    <li>Move pieces by <b>tapping</b> (tap the piece, then the square) or by <b>dragging</b> the piece to its new square.</li>
    <li>When a pawn reaches the last rank, choose the promotion piece: Queen, Rook, Bishop or Knight.</li>
    <li>Yellow squares show the last move; a king in check glows red; a ring around a piece means it can be captured.</li>
    <li>Stuck? Press <b>Hint</b>. A hint counts as one mistake.</li>
</ul>
<h3>Stars and XP</h3>
<ul>
    <li>Each lesson gives up to <b>3 stars</b>: 3 stars for at most 1 mistake, 2 stars for at most 4 mistakes, otherwise 1 star.</li>
    <li><b>XP</b> is worked out by the server: full XP the first time you finish a lesson, a small share when you repeat it. The daily puzzle gives 15 XP, a right answer in Practise again 5 XP, and beating Pak Kuda 20, 50 or 100 XP depending on the level.</li>
    <li>If a result comes in too fast you will see "Slow down a little!" and it is not recorded. Just try again calmly.</li>
</ul>
<h3>Daily puzzle and streak</h3>
<p>A new puzzle every day (Malaysia time). Solve it every day to grow your <b>streak</b>; miss a day and the streak starts again.</p>
<h3>Practise again</h3>
<p>Questions you got wrong are saved and asked again: tomorrow, then 3, 7 and 14 days after a right answer. After that the question is "mastered" and not asked again. Look for the <b>Practise again</b> card on the map.</p>
<h3>Badges</h3>
<p>There are 14 badges for lessons, stars, exams, streaks, puzzles and games. See them all, including how many more you need, on the <b>Badges</b> page (card on the map).</p>
<h3>Exams and certificates</h3>
<p>Each level ends with an exam. Get at least <b>70%</b> right to pass and earn a <b>certificate</b>. On the certificate page, write your own name, then print it. Every certificate has a verification code that anyone can check on the home page (Check a certificate).</p>
<h3>Tasks from your teacher and classes</h3>
<p>When a teacher sets a task it appears above the map with its due date. That lesson is unlocked for you straight away. Got a class code from your teacher? Press <b>Join a class</b> on the map.</p>
<h3>Malay and English chess words</h3>
<p>The English names of the pieces and chess terms are on the <a href="{{ route('istilah') }}">Chess glossary</a> page.</p>

<h2 id="main">3. Playing Pak Kuda</h2>
<ul>
    <li>Press <b>Play</b> at the top. Choose a colour (White, Black or Random) and a level (Easy, Medium or Hard), then <b>New game</b>.</li>
    <li><b>Undo</b> takes back your last move. <b>Hint</b> shows a good move with an arrow.</li>
    <li><b>Offer a draw</b>: Pak Kuda only agrees after at least 20 moves each and when he is not ahead on material. If he refuses, wait 10 moves before asking again.</li>
    <li><b>Resign</b> asks for confirmation first, so it can't be pressed by accident.</li>
    <li>When the game is over, press <b>Review the game with Pak Kuda</b>. He shows up to 3 key moves: the red arrow is your move, the green one his suggestion.</li>
    <li>Letters in the move list: K King, Q Queen, R Rook, B Bishop, N Knight, x captures, + check, # checkmate.</li>
</ul>

<h2 id="ibu-bapa">4. For parents</h2>
<ul>
    <li>After logging in, press <b>Children</b> at the top.</li>
    <li><b>Create a child account</b>: type a nickname. We generate a username and PIN. Save or print them straight away; the PIN is not shown again.</li>
    <li>Your child already has an account (made by a teacher, for example)? <b>Link</b> it with the child's username and PIN.</li>
    <li>The progress table shows each child's lessons, stars, XP and streak.</li>
    <li>Forgot the PIN? Press <b>New PIN</b>. <b>Unlink</b> only removes the link; the child's account stays.</li>
    <li><b>Download data</b> gives a copy of the child's data (JSON). <b>Delete account</b> permanently deletes the child's account and all its data (type PADAM to confirm).</li>
</ul>

<h2 id="guru">5. For teachers</h2>
<ul>
    <li><b>Teacher account</b>: register a normal account, then email <a href="mailto:{{ $email }}?subject=ChessFlow%20teacher%20account">{{ $email }}</a> with your school or club name. An administrator will switch on the teacher role.</li>
    <li>Press <b>Classes</b> at the top, then <b>Create a class</b>. Each class has a <b>join code</b>.</li>
    <li><b>Add students</b>: one name per line (up to 40 at a time). Usernames and PINs are shown once only; print them to hand out. Students who already have an account can join with the class code.</li>
    <li><b>Tasks</b>: pick a lesson or exam, an optional due date and note, then <b>Set task</b>. The lesson unlocks for everyone in the class. See how many have finished and who has not.</li>
    <li>The progress table shows every student's stars for every lesson. For each student: <b>New PIN</b>, <b>Remove</b> from the class, or <b>Delete account</b> (only when the student is not linked to a parent or another teacher's class).</li>
</ul>

<h2 id="tetapan">6. Settings</h2>
<ul>
    <li>Buttons at the top right: <b>colour theme</b> (System, Light, Dark), <b>sound</b> (on or off) and <b>language</b> (BM or EN). These are saved on the device.</li>
    <li>Parents and teachers: the gear icon opens <b>Account settings</b> to change your name, email, password, two-factor authentication and passkeys.</li>
</ul>

<h2 id="privasi">7. Privacy and data</h2>
<p>We keep as little data as we can and never ask for a student's email. Read the <a href="{{ route('privasi') }}">Privacy Policy</a> (Personal Data Protection Act 2010) and the <a href="{{ route('terma') }}">Terms of Use</a>. Download your own data in Account settings, or a child's data on the Children page.</p>

<h2 id="masalah">8. Common problems</h2>
<details><summary>I forgot my PIN.</summary><p>Ask the teacher or parent who created your account to press <b>New PIN</b>.</p></details>
<details><summary>"Too many attempts" when signing in.</summary><p>Wait about a minute, then try again with the right username and PIN.</p></details>
<details><summary>A lesson is locked.</summary><p>Finish the lesson before it on the map. A teacher can also unlock a lesson for the class by setting it as a task.</p></details>
<details><summary>The board or Pak Kuda doesn't move.</summary><p>Reload the page. If a message says the engine could not be loaded, Pak Kuda can still play (Easy level) but hints and the review need the engine; try another or an updated browser.</p></details>
<details><summary>No sound.</summary><p>Check that the sound button at the top is not crossed out and that the device is not muted.</p></details>
<details><summary>I want the other language.</summary><p>Press <b>EN</b> or <b>BM</b> at the top. Lessons are written in Bahasa Melayu; the English names of the pieces appear in the lessons and on the Chess glossary page.</p></details>

<h2 id="pentadbir">9. For administrators</h2>
<ul>
    <li>The admin panel is at <b>/admin</b> (the <b>Admin</b> link at the top): change user roles (parent, teacher, admin), <b>Export data</b> and delete accounts, and publish or reorder lessons.</li>
    <li>Lesson content comes from <code>data/lessons.json</code> and is imported with <code>php artisan chessflow:import-lessons</code>. Never edit lessons directly in the database.</li>
    <li>Useful commands: <code>chessflow:set-role &lt;email&gt; guru|admin</code>, <code>chessflow:create-student</code>, <code>chessflow:pick-daily</code>, <code>chessflow:doctor</code>.</li>
</ul>

<h2 id="hubungi">Contact us</h2>
<p>Email <a href="mailto:{{ $email }}?subject=ChessFlow%20help">{{ $email }}</a>. ChessFlow is run by WM AFENDI ENTERPRISE, Ketereh, Kelantan.</p>
