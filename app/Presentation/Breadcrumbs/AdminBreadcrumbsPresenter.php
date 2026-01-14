<?php

namespace App\Presentation\Breadcrumbs;

use App\Domain\Admin\Services\AdminNavigationService;
use App\Models\User;
use App\Presentation\BasePresenter;

class AdminBreadcrumbsPresenter extends BasePresenter
{
    public function __construct(private readonly AdminNavigationService $navigation)
    {
    }

    protected function buildData(array $ctx): array
    {
        /** @var User|null $user */
        $user = $ctx['user'] ?? null;
        $currentHref = $ctx['currentHref'] ?? '';
        $childLabel = $ctx['childLabel'] ?? null;

        $items = [
            [
                'label' => 'Admin',
                'href' => $currentHref && $currentHref !== '/admin' ? '/admin' : null,
            ],
        ];

        $currentLabel = $this->resolveLabel($user, $currentHref);

        if ($currentLabel && $currentHref && $currentHref !== '/admin') {
            $items[] = [
                'label' => $currentLabel,
                'href' => $childLabel ? $currentHref : null,
            ];
        }

        if ($childLabel) {
            $items[] = [
                'label' => $childLabel,
                'href' => null,
            ];
        }

        return $items;
    }

    private function resolveLabel(?User $user, string $currentHref): ?string
    {
        if (!$user || !$currentHref || $currentHref === '/admin') {
            return null;
        }

        foreach ($this->navigation->getMenuItems($user) as $item) {
            if (($item['href'] ?? null) === $currentHref) {
                return $item['label'] ?? null;
            }
        }

        return null;
    }
}
