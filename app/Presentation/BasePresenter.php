<?php

namespace App\Presentation;

abstract class BasePresenter
{
    public function present(array $ctx = []): array
    {
        return [
            'title' => $this->resolveTitle($ctx),
            'data' => $this->buildData($ctx),
        ];
    }

    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? '';
    }

    abstract protected function buildData(array $ctx): array;
}
