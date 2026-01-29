<?php

namespace App\Domain\Seo\Services;

use App\Domain\Seo\Models\PageMeta;
use App\Models\User;

class PageMetaService
{
    public function resolve(string $pageType, int $pageId = 0): array
    {
        $meta = PageMeta::query()
            ->where('page_type', $pageType)
            ->where('page_id', $pageId)
            ->first();

        if (!$meta) {
            return [
                'title' => null,
                'description' => null,
                'keywords' => null,
            ];
        }

        return [
            'title' => $meta->title,
            'description' => $meta->description,
            'keywords' => $meta->keywords,
        ];
    }

    public function upsert(array $payload, ?User $user = null): PageMeta
    {
        $pageType = (string) ($payload['page_type'] ?? '');
        $pageId = (int) ($payload['page_id'] ?? 0);

        $meta = PageMeta::query()->firstOrNew([
            'page_type' => $pageType,
            'page_id' => $pageId,
        ]);

        $meta->fill([
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'keywords' => $payload['keywords'] ?? null,
            'updated_by' => $user?->id,
        ]);

        if (!$meta->exists) {
            $meta->created_by = $user?->id;
        }

        $meta->save();

        return $meta;
    }
}
