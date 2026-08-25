<?php

namespace App\Domain\Fhir\Converters\Concerns;

/**
 * FHIR JSON must not carry null values or empty arrays for absent optional
 * elements — every converter builds its payload with PHP null/[] as
 * placeholders for "not applicable to this record" and calls clean() once
 * at the end, rather than repeating array_filter() logic per field.
 */
trait StripsEmptyFhirFields
{
    private static function clean(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = array_is_list($value)
                    ? array_values(array_filter(array_map(
                        fn ($item) => is_array($item) ? self::clean($item) : $item,
                        $value
                    ), fn ($item) => $item !== null && $item !== []))
                    : self::clean($value);

                if ($value === []) {
                    continue;
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
