<?php

namespace App\Presentation\Venues;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use App\Presentation\BasePresenter;

class VenueSidebarPresenter extends BasePresenter
{
    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? 'Площадки';
    }

    protected function buildData(array $ctx): array
    {
        $typeSlug = $ctx['typeSlug'] ?? '';
        /** @var Venue|null $venue */
        $venue = $ctx['venue'] ?? null;
        /** @var User|null $user */
        $user = $ctx['user'] ?? null;

        if (!$typeSlug || !$venue) {
            return [];
        }

        $groups = [
            [
                'title' => 'Общее',
                'items' => [
                    [
                        'label' => 'Общая информация',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}",
                    ],
                    [
                        'label' => 'Расписание',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}/schedule",
                    ],
                ],
            ],
        ];

        if ($this->canViewContracts($user, $venue)) {
            $groups[] = [
                'title' => 'Администрация',
                'items' => [
                    [
                        'label' => 'Контракты',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}/contracts",
                    ],
                ],
            ];
        }

        return $groups;
    }

    private function canViewContracts(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()
            ->where('alias', 'admin')
            ->exists();

        if ($isAdmin) {
            return true;
        }

        $now = now();

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->exists();
    }
}
