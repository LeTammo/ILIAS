<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

class ilCSVSanitizingWriter extends ilCSVWriter
{
    public function addColumn(string $a_col): void
    {
        $a_col = strip_tags($a_col);

        // Check if the string starts with a formula-triggering character (=, +, -, @) or a control character (tab, carriage return)
        if (preg_match('/^([=+\-@]|[\x09\x0D])/', $a_col)) {
            $a_col = $this->sanitizeCol($a_col);
        }

        parent::addColumn($a_col);
    }

    private function sanitizeCol(string $value): string
    {
        // Escape double quotes for valid CSV formatting
        $value = str_replace('"', '""', $value);

        // Prevent formula injection in spreadsheet applications by prefixing with a single quote
        $value = "'" . $value;

        // Enclose in double quotes so the entire value is treated as a single CSV cell
        $value = '"' . $value . '"';

        return $value;
    }
}
