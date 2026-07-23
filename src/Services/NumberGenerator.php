<?php

namespace Qnox\Workflows\Services;

use DateTimeInterface;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Qnox\Workflows\Models\NumberSequence;
use RuntimeException;

class NumberGenerator
{
    public function __construct(
        protected DB $db,
        protected NumberFormatter $formatter,
    ) {}

    public function next(
        string $key,
        array $context = [],
        ?Model $scope = null
    ): string {
        return $this->db->transaction(function () use ($key, $context, $scope) {
            $sequence = $this->query($key, $scope)->lockForUpdate()->firstOrFail();

            if (!$sequence->is_active) {
                throw new RuntimeException("Number sequence [{$key}] is inactive.");
            }

            $marker = $this->resetMarker($sequence, $context['date'] ?? null);
            if ($marker !== null && $sequence->reset_marker !== $marker) {
                $sequence->next_value = 1;
                $sequence->reset_marker = $marker;
            }

            $number = $this->formatter->format($sequence, $sequence->next_value, $context);
            $sequence->next_value++;
            $sequence->last_generated_at = now();
            $sequence->save();

            return $number;
        });
    }

    public function preview(
        string $key,
        array $context = [],
        ?Model $scope = null
    ): string {
        $sequence = $this->query($key, $scope)->firstOrFail();
        $value = $sequence->next_value;
        $marker = $this->resetMarker($sequence, $context['date'] ?? null);

        if ($marker !== null && $sequence->reset_marker !== $marker) {
            $value = 1;
        }

        return $this->formatter->format($sequence, $value, $context);
    }

    protected function query(string $key, ?Model $scope)
    {
        return NumberSequence::query()
            ->where('key', $key)
            ->when(
                $scope,
                fn ($query) => $query
                    ->where('scope_type', $scope->getMorphClass())
                    ->where('scope_id', $scope->getKey()),
                fn ($query) => $query->whereNull('scope_type')->whereNull('scope_id')
            );
    }

    protected function resetMarker(NumberSequence $sequence, DateTimeInterface|string|null $date): ?string
    {
        $date = $date instanceof DateTimeInterface ? Carbon::instance($date) : ($date ? Carbon::parse($date) : now());

        return match ($sequence->reset_period) {
            'daily' => $date->format('Y-m-d'),
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => null,
        };
    }
}
