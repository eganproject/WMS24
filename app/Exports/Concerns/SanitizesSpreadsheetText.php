<?php

namespace App\Exports\Concerns;

trait SanitizesSpreadsheetText
{
    private function spreadsheetText(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
