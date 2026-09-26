<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SchoolProfileRequest;
use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The logo is public (UI, receipts). The principal's signature is private:
 * shown to admins through signature() and embedded into PDFs from disk.
 */
class SchoolProfileController extends Controller
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function edit(): Response
    {
        $school = $this->school();

        return Inertia::render('Setup/Profile', [
            'profile' => $school->only(['name_en', 'name_bn', 'eiin', 'address', 'phone', 'email', 'default_locale']),
            'logoUrl' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
            'hasSignature' => $school->principal_signature_path !== null,
        ]);
    }

    public function update(SchoolProfileRequest $request): RedirectResponse
    {
        $school = $this->school();
        $school->fill($request->safe()->except(['logo', 'signature']));

        if ($request->hasFile('logo')) {
            $school->logo_path = $this->replace('public', $school->logo_path, $request->file('logo'), $school);
        }

        if ($request->hasFile('signature')) {
            $school->principal_signature_path = $this->replace('local', $school->principal_signature_path, $request->file('signature'), $school);
        }

        $school->save();

        return back()->with('success', __('messages.saved'));
    }

    public function signature(): StreamedResponse
    {
        $path = $this->school()->principal_signature_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function replace(string $disk, ?string $oldPath, UploadedFile $file, School $school): string
    {
        $path = $file->store("schools/{$school->id}", $disk);

        if ($oldPath !== null) {
            Storage::disk($disk)->delete($oldPath);
        }

        return $path;
    }

    private function school(): School
    {
        return $this->currentSchool->get() ?? abort(404);
    }
}
