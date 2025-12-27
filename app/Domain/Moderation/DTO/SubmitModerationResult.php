<?php

namespace App\Domain\Moderation\DTO;

use App\Domain\Moderation\Models\ModerationRequest;

class SubmitModerationResult
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        public bool $success,
        public ?ModerationRequest $request = null,
        public array $errors = []
    ) {
    }
}
