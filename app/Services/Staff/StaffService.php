<?php

namespace App\Services\Staff;

use App\Enums\StaffStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Staff;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffService
{
    public function __construct(private StaffLoginService $logins, private CurrentSchool $currentSchool) {}

    /**
     * @param  array<string, mixed>  $data  validated (StaffRequest)
     */
    public function create(array $data, ?UploadedFile $photo): Staff
    {
        return DB::transaction(function () use ($data, $photo) {
            $staff = new Staff(Arr::only($data, (new Staff)->getFillable()));
            $staff->employee_code = ($data['employee_code'] ?? null) ?: $this->nextCode();
            $staff->photo_path = $photo?->store($this->photoDirectory(), 'local');
            $staff->save();

            return $staff;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated (StaffRequest)
     */
    public function update(Staff $staff, array $data, ?UploadedFile $photo, User $actor): void
    {
        $oldPhoto = $staff->photo_path;

        DB::transaction(function () use ($staff, $data, $photo, $actor) {
            $staff->fill(Arr::only($data, $staff->getFillable()));
            $staff->employee_code = ($data['employee_code'] ?? null) ?: $staff->employee_code;

            if ($photo !== null) {
                $staff->photo_path = $photo->store($this->photoDirectory(), 'local');
            }

            $staff->save();

            // Someone who has left cannot keep signing in.
            if ($staff->status === StaffStatus::Left) {
                $this->logins->remove($staff, $actor);
            } else {
                $this->logins->sync($staff);
            }
        });

        if ($photo !== null && $oldPhoto !== null) {
            Storage::disk('local')->delete($oldPhoto);
        }
    }

    /**
     * Soft-deletes the staff record, deletes their login and their teaching assignments.
     */
    public function delete(Staff $staff, User $actor): void
    {
        if ($staff->user_id === $actor->id) {
            throw new BusinessRuleException(__('messages.own_login'));
        }

        DB::transaction(function () use ($staff, $actor) {
            $this->logins->remove($staff, $actor);
            $staff->classTeacherships()->delete();
            $staff->subjectTeacherships()->delete();
            $staff->delete();
        });
    }

    /**
     * "E-0001", "E-0002", … among codes in that pattern; schools may also type their own codes.
     */
    private function nextCode(): string
    {
        $last = Staff::withTrashed()
            ->where('employee_code', 'like', 'E-%')
            ->orderByRaw('CHAR_LENGTH(employee_code) DESC')
            ->orderByDesc('employee_code')
            ->lockForUpdate()
            ->value('employee_code');

        return sprintf('E-%04d', $last === null ? 1 : (int) substr($last, 2) + 1);
    }

    private function photoDirectory(): string
    {
        return "schools/{$this->currentSchool->id()}/staff";
    }
}
