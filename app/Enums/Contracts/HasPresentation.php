<?php

declare(strict_types=1);

namespace App\Enums\Contracts;

/**
 * Every status/type enum implements this so <x-status-chip :status="$model->status">
 * renders the right label and colour with no extra code.
 */
interface HasPresentation
{
    public function label(): string;

    /** One of: success, warning, danger, info, neutral. */
    public function color(): string;
}
