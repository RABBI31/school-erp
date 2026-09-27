<?php

namespace App\Services\Exams;

use App\Models\GradingScheme;

/**
 * A grading scheme's rules: whole percent → letter and point (hundredths,
 * 5.00 = 500). Read from grade_rules, never hardcoded.
 */
final readonly class GradeScale
{
    /**
     * @param  list<array{min: int|float, max: int|float, letter: string, point: int}>  $rules  highest first
     */
    private function __construct(private array $rules) {}

    /**
     * @param  iterable<array{min: int|float|string, max: int|float|string, letter: string, point: int}>  $rules
     */
    public static function fromRules(iterable $rules): self
    {
        $list = [];
        foreach ($rules as $rule) {
            $list[] = ['min' => (float) $rule['min'], 'max' => (float) $rule['max'], 'letter' => $rule['letter'], 'point' => $rule['point']];
        }
        usort($list, fn (array $a, array $b) => $b['min'] <=> $a['min']);

        return new self($list);
    }

    public static function fromScheme(GradingScheme $scheme): self
    {
        return self::fromRules($scheme->rules->map(fn ($rule) => [
            'min' => $rule->min_percent,
            'max' => $rule->max_percent,
            'letter' => $rule->letter,
            'point' => (int) round((float) $rule->point * 100),
        ]));
    }

    /**
     * @return array{letter: string, point: int}
     */
    public function forPercent(int $percent): array
    {
        foreach ($this->rules as $rule) {
            if ($percent >= $rule['min']) {
                return ['letter' => $rule['letter'], 'point' => $rule['point']];
            }
        }

        return $this->failing();
    }

    /**
     * The letter for an overall GPA: the best rule whose point the GPA reaches.
     */
    public function letterForGpa(int $gpa): string
    {
        foreach ($this->rules as $rule) {
            if ($rule['point'] > 0 && $gpa >= $rule['point']) {
                return $rule['letter'];
            }
        }

        return $this->failing()['letter'];
    }

    /**
     * @return array{letter: string, point: int}
     */
    public function failing(): array
    {
        $last = $this->rules === [] ? null : $this->rules[array_key_last($this->rules)];

        return $last && $last['point'] === 0 ? ['letter' => $last['letter'], 'point' => 0] : ['letter' => 'F', 'point' => 0];
    }
}
