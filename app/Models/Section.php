<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['school_class_id', 'shift_id', 'version_id', 'name', 'capacity'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    /**
     * Sections for a filter dropdown, in class order: id, name and class names.
     *
     * @return Collection<int, array{id: int, name: string, school_class_id: int, class_name_en: string, class_name_bn: string}>
     */
    public static function options(): Collection
    {
        return self::query()
            ->with('schoolClass:id,name_en,name_bn,numeric_order')
            ->get()
            ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'school_class_id' => $section->school_class_id,
                'class_name_en' => $section->schoolClass->name_en,
                'class_name_bn' => $section->schoolClass->name_bn,
            ]);
    }
}
