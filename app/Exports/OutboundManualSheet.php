<?php

namespace App\Exports;

use App\Support\OutboundManualReport;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

abstract class OutboundManualSheet extends DefaultValueBinder
{
    public function __construct(protected OutboundManualReport $report)
    {
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
