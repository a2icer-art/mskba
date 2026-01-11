<?php

namespace App\Presentation\Navigation;

use App\Domain\Filament\Services\FilamentNavigationService;

class FilamentNavigationPresenter extends NavigationPresenter
{
    public function __construct(private readonly FilamentNavigationService $navigation)
    {
    }

    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? 'Разделы';
    }

    protected function buildGroups(array $ctx): array
    {
        $roleLevel = (int) ($ctx['roleLevel'] ?? 0);

        return $this->navigation->getMenuGroups($roleLevel);
    }
}
