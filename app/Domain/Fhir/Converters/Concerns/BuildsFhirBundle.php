<?php

namespace App\Domain\Fhir\Converters\Concerns;

trait BuildsFhirBundle
{
    /**
     * @param  array<int,array<string,mixed>>  $resources
     */
    private static function bundle(array $resources): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => count($resources),
            'entry' => array_map(fn (array $resource) => ['resource' => $resource], $resources),
        ];
    }
}
