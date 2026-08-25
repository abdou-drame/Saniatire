<?php

namespace App\Domain\Dentaire\Support;

/**
 * FDI two-digit tooth numbering: quadrants 1-4 (permanent dentition, 8
 * teeth each = 32) and 5-8 (deciduous dentition, 5 teeth each = 20).
 * Used both as a route constraint (PUT .../teeth/{fdi}) and as a Form
 * Request validation rule wherever a tooth number is optional input.
 */
class FdiNumbering
{
    public static function validCodes(): array
    {
        $codes = [];

        foreach ([1, 2, 3, 4] as $quadrant) {
            for ($tooth = 1; $tooth <= 8; $tooth++) {
                $codes[] = "{$quadrant}{$tooth}";
            }
        }

        foreach ([5, 6, 7, 8] as $quadrant) {
            for ($tooth = 1; $tooth <= 5; $tooth++) {
                $codes[] = "{$quadrant}{$tooth}";
            }
        }

        return $codes;
    }
}
