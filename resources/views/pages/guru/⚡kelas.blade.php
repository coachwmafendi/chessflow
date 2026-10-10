<?php

use App\Actions\CreateStudents;
use App\Actions\DeleteStudent;
use App\Actions\ResetStudentPin;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use App\Support\AssignmentReport;
use App\Support\Chessflow;
use App\Support\Curriculum;
use App\Support\ProgressReport;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::chessflow')] class extends Component {
    public Classroom $classroom;

    public string $names = '';

    /** Credentials of accounts just created / reset, shown once. */
    public array $credentials = [];

    /** New assignment form. */
    public ?int $lessonId = null;

    public string $dueOn = '';

    public string $note = '';

    public ?string $assignedMsg = null;

    public ?string $notice = null;

    public function mount(Classroom $classroom): void
    {
        $this->authorize('manage', $classroom);
        $this->classroom = $classroom;
    }

    public function addStudents(CreateStudents $create): void
    {
        $this->authorize('manage', $this->classroom);
        $this->validate(['names' => 'required|string|max:4000'], [], ['names' => 'senarai nama']);

        $lines = preg_split('/\R/', $this->names) ?: [];
        $this->credentials = $create->handle(auth()->user(), $lines, $this->classroom);
        $this->names = '';
    }

    public function resetPin(int $studentId, ResetStudentPin $reset): void
    {
        $this->authorize('manage', $this->classroom);
        $student = $this->classroom->students()->findOrFail($studentId);

        $this->credentials = [['id' => $student->id, 'name' => $student->name, 'username' => $student->username, 'pin' => $reset->handle($student)]];
    }

    public function remove(int $studentId): void
    {
        $this->authorize('manage', $this->classroom);
        $this->classroom->students()->detach($studentId);
    }

    /** Set (or update) a lesson for the whole class; it opens for every student in the class. */
    public function assign(): void
    {
        $this->authorize('manage', $this->classroom);
        $this->validate([
            'lessonId' => ['required', 'integer', 'exists:lessons,id'],
            'dueOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.Chessflow::today()],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'dueOn.after_or_equal' => 'Tarikh akhir tidak boleh sebelum hari ini.',
        ], ['lessonId' => 'pelajaran', 'dueOn' => 'tarikh akhir', 'note' => 'nota']);

        $lesson = Lesson::where('is_published', true)->findOrFail($this->lessonId);
        $assignment = Assignment::updateOrCreate(
            ['classroom_id' => $this->classroom->id, 'lesson_id' => $lesson->id],
            ['due_on' => $this->dueOn ?: null, 'note' => trim($this->note) ?: null],
        );

        $this->assignedMsg = ($assignment->wasRecentlyCreated ? 'Tugasan diberi: ' : 'Tugasan dikemas kini: ').$lesson->title.'.';
        $this->reset('lessonId', 'dueOn', 'note');
    }

    public function unassign(int $assignmentId): void
    {
        $this->authorize('manage', $this->classroom);
        $this->classroom->assignments()->findOrFail($assignmentId)->delete();
        $this->assignedMsg = null;
    }

    /** Only when the student is looked after by this teacher alone (see DeleteStudent). */
    public function deleteStudent(int $studentId, DeleteStudent $delete): void
    {
        $this->authorize('manage', $this->classroom);
        $student = $this->classroom->students()->findOrFail($studentId);
        if ($reason = $delete->deniedReason(auth()->user(), $student)) {
            $this->notice = $reason;

            return;
        }
        $delete->handle(auth()->user(), $student);
        $this->credentials = [];
        $this->notice = 'Akaun '.$student->name.' dan semua datanya telah dipadam.';
    }

    /** Typed confirmation (wire:confirm.prompt): deleting cannot be undone. */
    private function deleteConfirm(User $student): string
    {
        return e('Padam akaun '.$student->name.' selama-lamanya? Semua kemajuan, sijil, lencana dan permainan akan hilang dan tidak boleh dipulihkan. Taip PADAM untuk sahkan.').'|PADAM';
    }

    public function actionsHtml(User $student): string
    {
        $html = '<button class="ghost" type="button" wire:click="resetPin('.$student->id.')">PIN baru</button> '
            .'<button class="ghost" type="button" wire:click="remove('.$student->id.')" wire:confirm="'
            .e('Keluarkan '.$student->name.' daripada kelas?').'">Keluarkan</button>';

        if (app(DeleteStudent::class)->deniedReason(auth()->user(), $student) === null) {
            $html .= ' <button class="ghost danger" type="button" wire:click="deleteStudent('.$student->id.')" wire:confirm.prompt="'
                .$this->deleteConfirm($student).'">Padam akaun</button>';
        }

        return $html;
    }

    public function render()
    {
        $students = $this->classroom->students()->orderBy('name')->get();

        return $this->view([
            'rows' => app(ProgressReport::class)->forStudents($students),
            'lessons' => app(Curriculum::class)->lessons(),
            'assignments' => app(AssignmentReport::class)->forClassroom($this->classroom),
            'levels' => Level::orderBy('position')->get(),
            'today' => Chessflow::today(),
        ])->title($this->classroom->name);
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('guru') }}">← Kelas saya</a></p>
    <div class="page-head">
        <h1>{{ $classroom->name }}</h1>
        <span>Kod sertai: <span class="code">{{ $classroom->join_code }}</span></span>
    </div>

    @if ($notice)
        <p class="status info" wire:key="notice">{{ $notice }}</p>
    @endif

    @if ($credentials)
        <section class="panel-card" wire:key="creds">
            <h2>Akaun murid</h2>
            <p>Salin atau cetak sekarang. PIN tidak akan dipaparkan lagi.</p>
            <table class="creds">
                <thead><tr><th>Nama</th><th>Nama pengguna</th><th>PIN</th></tr></thead>
                <tbody>
                    @foreach ($credentials as $c)
                        <tr><td>{{ $c['name'] }}</td><td>{{ $c['username'] }}</td><td>{{ $c['pin'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="no-print" style="margin-top: 8px">Murid masuk di <b>{{ route('murid.login') }}</b>
                <button class="ghost" type="button" onclick="window.print()">Cetak</button>
                <button class="ghost" type="button" wire:click="$set('credentials', [])">Tutup</button></p>
        </section>
    @endif

    <section class="panel-card assign-panel">
        <h2>Tugasan</h2>
        @if ($assignments->isEmpty())
            <p class="muted">Beri pelajaran atau ujian kepada seluruh kelas. Pelajaran itu terus terbuka untuk semua murid kelas ini, walaupun mereka belum sampai di peta.</p>
        @else
            <ul class="assign-list">
                @foreach ($assignments as $row)
                    @php
                        $a = $row['assignment'];
                        $complete = $row['total'] > 0 && $row['done'] === $row['total'];
                    @endphp
                    <li wire:key="assign-{{ $a->id }}" class="{{ $complete ? 'complete' : ($a->isOverdue() ? 'overdue' : '') }}">
                        <div class="assign-main">
                            <i class="pc {{ $a->lesson->icon }}" aria-hidden="true"></i>
                            <div>
                                <b>{{ $a->lesson->title }}</b>
                                <small>
                                    {{ $a->dueLabel() ?? 'Tiada tarikh akhir' }}@if ($a->isOverdue() && ! $complete) · <span class="late">Lewat</span>@endif
                                    @if ($a->note) · {{ $a->note }}@endif
                                </small>
                            </div>
                            <span class="assign-count">{{ $row['done'] }}/{{ $row['total'] }}<small>siap</small></span>
                        </div>
                        <div class="meter" aria-hidden="true"><span style="width: {{ $row['total'] ? round($row['done'] / $row['total'] * 100) : 0 }}%"></span></div>
                        <div class="assign-foot">
                            @if ($row['pending']->isNotEmpty() && ! $complete)
                                <details><summary>Belum siap ({{ $row['pending']->count() }})</summary><p>{{ $row['pending']->pluck('name')->implode(', ') }}</p></details>
                            @elseif ($complete)
                                <span class="good-text">Semua murid sudah siap.</span>
                            @endif
                            <button class="ghost no-print" type="button" wire:click="unassign({{ $a->id }})" wire:confirm="{{ 'Padam tugasan '.$a->lesson->title.'? Kemajuan murid tidak dipadam.' }}">Padam</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="assign" class="assign-form no-print">
            <label><span>Pelajaran</span>
                <select wire:model="lessonId" required>
                    <option value="">Pilih pelajaran…</option>
                    @foreach ($levels as $level)
                        <optgroup label="Tahap {{ $level->number }}: {{ $level->name }}">
                            @foreach ($lessons->where('level_id', $level->id) as $l)
                                <option value="{{ $l->id }}">{{ $l->title }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <label><span>Tarikh akhir <small>(pilihan)</small></span>
                <input type="date" wire:model="dueOn" min="{{ $today }}">
            </label>
            <label class="grow"><span>Nota untuk murid <small>(pilihan)</small></span>
                <input type="text" wire:model="note" maxlength="200" placeholder="Contoh: Siapkan sebelum kelab Jumaat">
            </label>
            <button class="cta" type="submit">Beri tugasan</button>
            @error('lessonId') <p class="err">{{ $message }}</p> @enderror
            @error('dueOn') <p class="err">{{ $message }}</p> @enderror
            @error('note') <p class="err">{{ $message }}</p> @enderror
            @if ($assignedMsg) <p class="status good assign-msg">{{ $assignedMsg }}</p> @endif
        </form>
    </section>

    <x-chessflow.progress-table :rows="$rows" :lessons="$lessons" :actions="fn ($s) => $this->actionsHtml($s)" />

    <section class="panel-card no-print">
        <h2>Tambah murid</h2>
        <p>Satu nama setiap baris (maksimum {{ App\Actions\CreateStudents::MAX_PER_BATCH }}). Akaun tanpa e-mel dicipta dengan nama pengguna dan PIN. Murid yang sudah ada akaun boleh sertai dengan kod <b>{{ $classroom->join_code }}</b>.</p>
        <form wire:submit="addStudents">
            <textarea wire:model="names" aria-label="Nama murid" placeholder="Aina&#10;Badrul&#10;Chong Wei"></textarea>
            <button class="cta" type="submit">Cipta akaun</button>
            @error('names') <p class="err">{{ $message }}</p> @enderror
            <p class="consent">Dengan mencipta akaun murid, anda mengesahkan anda berhak memberi kebenaran bagi pihak murid ini (contohnya dengan izin sekolah atau ibu bapa), mengikut <a href="{{ route('privasi') }}">Dasar Privasi</a>.</p>
        </form>
    </section>
</div>
