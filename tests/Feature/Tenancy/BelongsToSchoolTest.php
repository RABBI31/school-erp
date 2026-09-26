<?php

use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Note;

function noteIn(School $school, string $title): Note
{
    app(CurrentSchool::class)->set($school);

    return Note::create(['title' => $title]);
}

test('queries only return the current school\'s rows', function () {
    [$a, $b] = School::factory()->count(2)->create();
    noteIn($a, 'A note');
    noteIn($b, 'B note');

    app(CurrentSchool::class)->set($a);

    expect(Note::pluck('title')->all())->toBe(['A note']);
});

test('creating sets school_id to the current school, even if another was given', function () {
    [$a, $b] = School::factory()->count(2)->create();
    app(CurrentSchool::class)->set($a);

    $note = new Note(['title' => 'x']);
    $note->school_id = $b->id;
    $note->save();

    expect($note->school_id)->toBe($a->id);
});

test('with no current school, queries return nothing and creating throws', function () {
    noteIn(School::factory()->create(), 'A note');

    app(CurrentSchool::class)->set(null);

    expect(Note::count())->toBe(0);
    expect(fn () => Note::create(['title' => 'orphan']))->toThrow(LogicException::class);
});

test('route model binding returns 404 for another school\'s record', function () {
    Route::middleware(['web', 'school', 'auth'])
        ->get('_test/notes/{note}', fn (Note $note) => $note->title);

    [$a, $b] = School::factory()->count(2)->create();
    $own = noteIn($a, 'mine');
    $other = noteIn($b, 'theirs');

    $user = $this->schoolUser($a);

    $this->actingAs($user)->get("/_test/notes/{$own->id}")->assertOk()->assertSee('mine');
    $this->actingAs($user)->get("/_test/notes/{$other->id}")->assertNotFound();
});
