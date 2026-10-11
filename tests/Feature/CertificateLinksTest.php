<?php

use App\Actions\SubmitExam;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\LessonSeeder;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create(['name' => 'Aina']);
    $exam = Lesson::where('slug', 'ujian1')->firstOrFail();
    $code = app(SubmitExam::class)->handle($this->student, $exam, [])['certificateCode'];
    $this->certUrl = route('sijil', $code);
});

it('links the certificate from the map and from "Sijil saya"', function () {
    $this->actingAs($this->student)->get(route('peta'))->assertSee('href="'.$this->certUrl.'"', false);

    $this->actingAs($this->student)->get(route('lencana'))
        ->assertSee('Sijil saya')
        ->assertSee('Tahap 1: Kenal Catur')
        ->assertSee('href="'.$this->certUrl.'"', false);
});

it('tells a student without certificates how to earn one', function () {
    $this->actingAs(User::factory()->student()->create())->get(route('lencana'))
        ->assertSee('Belum ada sijil.', false);
});

it('lets parents and teachers open a student\'s certificate from the progress table', function () {
    $parent = User::factory()->create();
    $parent->students()->attach($this->student->id);
    $this->actingAs($parent)->get(route('anak'))->assertSee('href="'.$this->certUrl.'"', false);

    $teacher = User::factory()->teacher()->create();
    $class = Classroom::create(['teacher_id' => $teacher->id, 'name' => '6 Arif', 'join_code' => Classroom::newJoinCode()]);
    $class->students()->attach($this->student->id);
    $this->actingAs($teacher)->get(route('guru.kelas', $class))->assertSee('href="'.$this->certUrl.'"', false);
});

it('explains how to save the certificate as a PDF', function () {
    $this->get($this->certUrl)
        ->assertOk()
        ->assertSee('Cetak / Simpan PDF')
        ->assertSee('Simpan sebagai PDF', false);
});
