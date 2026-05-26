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

declare(strict_types=1);

namespace ILIAS\Authentication\Login;

final readonly class ClientIpMatcher
{
    public function matches(ClientIpPattern $pattern, IpAddress $remote): bool
    {
        if ($pattern->isEmpty()) {
            return true;
        }

        $regex = $this->patternToRegex($pattern->pattern());

        return (bool) preg_match('/^' . $regex . '$/', $remote->value());
    }

    private function patternToRegex(string $client_ip): string
    {
        $sanitized = preg_replace('/[^0-9.?*,:]+/', '', $client_ip) ?? '';

        return str_replace(
            ['.', '?', '*', ','],
            ['\\.', '[0-9]', '[0-9]*', '|'],
            $sanitized,
        );
    }
}
