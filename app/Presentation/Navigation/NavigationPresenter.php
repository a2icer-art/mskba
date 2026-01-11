<?php

namespace App\Presentation\Navigation;

use App\Presentation\BasePresenter;

class NavigationPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        $groups = $this->buildGroups($ctx);

        if ($groups !== []) {
            return $groups;
        }

        return $this->buildItems($ctx);
    }

    protected function buildItems(array $ctx): array
    {
        return [];
    }

    protected function buildGroups(array $ctx): array
    {
        return [];
    }
}
