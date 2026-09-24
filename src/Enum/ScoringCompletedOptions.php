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

namespace ILIAS\Plugin\TstManualScoringQuestion\Enum;

enum ScoringCompletedOptions: int
{
    case ALL_USERS = 0;
    case ONLY_FINALIZED = 1;
    case EXCEPT_FINALIZED = 2;

    public function getTranslationKey(): string
    {
        return match ($this) {
            self::ONLY_FINALIZED => "evaluated_users",
            self::EXCEPT_FINALIZED => "not_evaluated_users",
            default => "all_users",
        };
    }
}
