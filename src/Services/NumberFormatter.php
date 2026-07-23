<?php

namespace Qnox\Workflows\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Qnox\Workflows\Models\NumberSequence;

class NumberFormatter
{
    public function format(
        NumberSequence $sequence,
        int $value,
        array $context = []
    ): string {
        $date = $this->date($context['date'] ?? null);
        $tokens = array_merge($context, [
            'prefix' => $sequence->prefix ?? '',
            'number' => str_pad((string) $value, $sequence->padding, '0', STR_PAD_LEFT),
            'year' => $date->format('Y'),
            'month' => $date->format('m'),
            'day' => $date->format('d'),
        ]);

        return preg_replace_callback('/\{([a-z_]+)(?::(\d+))?\}/i', function (array $match) use ($tokens) {
            $key = $match[1];

            if (!array_key_exists($key, $tokens)) {
                throw new InvalidArgumentException("No value was supplied for number token [{$key}].");
            }

            $value = $tokens[$key];
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Number token [{$key}] must be a scalar value.");
            }

            $rendered = (string) $value;

            return isset($match[2]) ? substr($rendered, -((int) $match[2])) : $rendered;
        }, $sequence->format);
    }

    protected function date(mixed $date): Carbon
    {
        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date);
        }

        return $date ? Carbon::parse($date) : now();
    }
}
