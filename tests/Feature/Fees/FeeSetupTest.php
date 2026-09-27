<?php

use App\Enums\WaiverType;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeWaiver;
use App\Models\Section;
use App\Models\User;

beforeEach(function () {
    feeWorld();
    $this->actingAs($this->accountant);
});

it('manages fee heads', function () {
    $this->post('/fees/heads', ['name_en' => 'Exam fee', 'name_bn' => 'পরীক্ষা ফি', 'type' => 'one_time'])->assertSessionHas('success');
    $head = FeeHead::where('name_en', 'Exam fee')->sole();

    $this->put("/fees/heads/{$head->id}", ['name_en' => 'Exam fee', 'name_bn' => 'পরীক্ষার ফি', 'type' => 'yearly'])->assertSessionHas('success');
    expect($head->fresh()->type->value)->toBe('yearly');

    $this->post('/fees/heads', ['name_en' => 'X', 'name_bn' => 'X', 'type' => 'weekly'])->assertSessionHasErrors('type');

    $this->get('/fees/heads')->assertOk()->assertInertia(fn ($page) => $page->component('Fees/Heads')->has('rows.data', 2));

    $this->delete("/fees/heads/{$head->id}")->assertSessionHas('success');
    // A head used in the fee structure cannot be deleted.
    $this->delete("/fees/heads/{$this->tuition->id}")->assertSessionHas('error');
});

it('saves the fee structure grid for the viewed year', function () {
    $other = Section::factory()->create();
    $session = FeeHead::factory()->yearly()->create();
    $class = $this->section->school_class_id;

    $this->put('/fees/structure', ['amounts' => [
        $class => [$this->tuition->id => '1200', $session->id => '2500.50'],
        $other->school_class_id => [$this->tuition->id => '900', $session->id => null],
        999999 => [$this->tuition->id => '1'], // unknown class: ignored
    ]])->assertSessionHas('success');

    expect(FeeStructure::where('school_class_id', $class)->pluck('amount', 'fee_head_id')->all())->toBe([$this->tuition->id => '1200.00', $session->id => '2500.50'])
        ->and(FeeStructure::where('school_class_id', $other->school_class_id)->count())->toBe(1)
        ->and(FeeStructure::count())->toBe(3);

    // Clearing an amount removes it.
    $this->put('/fees/structure', ['amounts' => [$class => [$session->id => '']]]);
    expect(FeeStructure::where('school_class_id', $class)->count())->toBe(1);

    $this->put('/fees/structure', ['amounts' => [$class => [$session->id => '-5']]])->assertSessionHasErrors();

    $this->get('/fees/structure')->assertOk()->assertInertia(fn ($page) => $page->component('Fees/Structure')
        ->where("amounts.{$class}.{$this->tuition->id}", '1200.00'));
});

it('manages waivers for an enrollment', function () {
    $this->post('/fees/waivers', ['enrollment_id' => $this->enrollment->id, 'fee_head_id' => $this->tuition->id, 'type' => 'percent', 'value' => 50, 'reason' => 'Sibling'])
        ->assertSessionHas('success');
    $waiver = FeeWaiver::sole();

    // One waiver per head per enrollment; percent at most 100.
    $this->post('/fees/waivers', ['enrollment_id' => $this->enrollment->id, 'fee_head_id' => $this->tuition->id, 'type' => 'fixed', 'value' => 100])->assertSessionHasErrors('fee_head_id');
    $this->put("/fees/waivers/{$waiver->id}", ['fee_head_id' => $this->tuition->id, 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');

    $this->put("/fees/waivers/{$waiver->id}", ['fee_head_id' => $this->tuition->id, 'type' => 'fixed', 'value' => 300])->assertSessionHas('success');
    expect($waiver->fresh()->only('type', 'value'))->toBe(['type' => WaiverType::Fixed, 'value' => '300.00']);

    $this->get("/students/{$this->enrollment->student_id}")->assertOk()
        ->assertInertia(fn ($page) => $page->has('waivers', 1)->where('waivers.0.value', '300.00')->has('feeHeads', 1));

    $this->delete("/fees/waivers/{$waiver->id}")->assertSessionHas('success');
    expect(FeeWaiver::count())->toBe(0);
});

it('keeps fee setup to the fee roles and to the school', function () {
    $teacher = User::factory()->for($this->school)->create();
    $teacher->assignRole('teacher');
    $this->actingAs($teacher);
    $this->get('/fees/heads')->assertForbidden();
    $this->get('/fees')->assertForbidden();
    $this->put('/fees/structure', ['amounts' => []])->assertForbidden();

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);

    $this->put("/fees/heads/{$this->tuition->id}", ['name_en' => 'Hacked', 'name_bn' => 'x', 'type' => 'monthly'])->assertNotFound();
    $this->post('/fees/waivers', ['enrollment_id' => $this->enrollment->id, 'fee_head_id' => $this->tuition->id, 'type' => 'percent', 'value' => 100])
        ->assertSessionHasErrors(['enrollment_id', 'fee_head_id']);
    $this->get('/fees/heads')->assertInertia(fn ($page) => $page->has('rows.data', 0));
});
