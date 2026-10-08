<?php

use App\Actions\CreateStudents;
use App\Actions\ResetStudentPin;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Curriculum;
use App\Support\ProgressReport;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::chessflow')] class extends Component {
    public Classroom $classroom;

    public string $names = '';

    /** Credentials of accounts just created / reset, shown once. */
    public array $credentials = [];

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

    public function actionsHtml(User $student): string
    {
        return '<button class="ghost" type="button" wire:click="resetPin('.$student->id.')">PIN baru</button> '
            .'<button class="ghost" type="button" wire:click="remove('.$student->id.')" wire:confirm="'
            .e('Keluarkan '.$student->name.' daripada kelas?').'">Keluarkan</button>';
    }

    public function render()
    {
        $students = $this->classroom->students()->orderBy('name')->get();

        return $this->view([
            'rows' => app(ProgressReport::class)->forStudents($students),
            'lessons' => app(Curriculum::class)->lessons(),
        ])->title($this->classroom->name);
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('guru') }}">← Kelas saya</a></p>
    <div class="page-head">
        <h1>{{ $classroom->name }}</h1>
        <span>Kod sertai: <span class="code">{{ $classroom->join_code }}</span></span>
    </div>

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

    <x-chessflow.progress-table :rows="$rows" :lessons="$lessons" :actions="fn ($s) => $this->actionsHtml($s)" />

    <section class="panel-card no-print">
        <h2>Tambah murid</h2>
        <p>Satu nama setiap baris (maksimum {{ App\Actions\CreateStudents::MAX_PER_BATCH }}). Akaun tanpa e-mel dicipta dengan nama pengguna dan PIN. Murid yang sudah ada akaun boleh sertai dengan kod <b>{{ $classroom->join_code }}</b>.</p>
        <form wire:submit="addStudents">
            <textarea wire:model="names" aria-label="Nama murid" placeholder="Aina&#10;Badrul&#10;Chong Wei"></textarea>
            <button class="cta" type="submit">Cipta akaun</button>
            @error('names') <p class="err">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
