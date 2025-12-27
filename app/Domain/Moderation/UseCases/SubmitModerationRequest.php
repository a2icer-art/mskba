<?php

namespace App\Domain\Moderation\UseCases;

use App\Domain\Moderation\DTO\SubmitModerationResult;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Moderation\Services\ModerationRulesFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SubmitModerationRequest
{
    public function __construct(private readonly ModerationRulesFactory $rulesFactory)
    {
    }

    public function execute(User $actor, ModerationEntityType $entityType, Model $entity): SubmitModerationResult
    {
        $hasPending = ModerationRequest::query()
            ->where('entity_type', $entityType->value)
            ->where('entity_id', $entity->getKey())
            ->where('status', ModerationStatus::Pending->value)
            ->exists();

        if ($hasPending) {
            return new SubmitModerationResult(false, null, ['Заявка уже отправлена на модерацию.']);
        }

        $rules = $this->rulesFactory->make($entityType);
        $missing = $rules->getMissingRequirements($actor, $entity);

        if ($missing !== []) {
            return new SubmitModerationResult(false, null, $missing);
        }

        $request = ModerationRequest::query()->create([
            'entity_type' => $entityType->value,
            'entity_id' => $entity->getKey(),
            'status' => ModerationStatus::Pending->value,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        return new SubmitModerationResult(true, $request);
    }
}
