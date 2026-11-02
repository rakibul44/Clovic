<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MoneyInput
{
    public static function parse(mixed $value, string $field = 'amount'): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Enter an amount in BDT TK with at most two decimal places.']);
        }
        $parts = explode('.', (string) $value);

        return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function validate(Request $request, array $paths, array $rules): array
    {
        $data = $request->all();
        // Explicit unit keeps previously loaded forms and internal minor-unit callers compatible.
        if ($request->input('money_unit') === 'BDT') {
            foreach (Arr::dot($data) as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                foreach ($paths as $path) {
                    if (Str::is($path, $key)) {
                        Arr::set($data, $key, self::parse($value, $key));
                        break;
                    }
                }
            }
        }

        // Validate a copy: failed forms must retain the user's taka input, not converted values.
        $messages = [];
        foreach ($paths as $path) {
            $rule = $rules[$path] ?? '';
            if (! is_string($rule)) {
                continue;
            }
            foreach (['min' => 'at least', 'max' => 'no more than'] as $limit => $wording) {
                if (preg_match('/(?:^|\|)'.$limit.':(\d+)(?:\||$)/', $rule, $match)) {
                    $messages[$path.'.'.$limit] = 'The :attribute must be '.$wording.' '.Settings::money((int) $match[1]).'.';
                }
            }
            $messages[$path.'.lte'] = 'The :attribute must not exceed the regular price (BDT TK).';
        }

        return Validator::make($data, $rules, $messages)->validate();
    }
}
