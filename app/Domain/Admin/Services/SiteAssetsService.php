<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\AdminSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SiteAssetsService
{
    private const KEY = 'site.assets';
    private const META_KEY = 'site.meta';

    public function get(): array
    {
        $settings = AdminSetting::query()->where('key', self::KEY)->first();
        $value = is_array($settings?->value) ? $settings->value : [];
        $path = $value['favicon_path'] ?? null;
        $url = $path ? $this->toPublicUrl($path) : '';
        $avatarPath = $value['avatar_placeholder_path'] ?? null;
        $avatarUrl = $avatarPath ? $this->toPublicUrl($avatarPath) : '';
        $meta = $this->getMetaSettings();

        return [
            'favicon_path' => $path,
            'favicon_url' => $url,
            'avatar_placeholder_path' => $avatarPath,
            'avatar_placeholder_url' => $avatarUrl,
            'include_site_title' => $meta['include_site_title'] ?? false,
        ];
    }

    public function updateFavicon(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $settings = AdminSetting::query()->where('key', self::KEY)->first();
        $value = is_array($settings?->value) ? $settings->value : [];
        $oldPath = $value['favicon_path'] ?? null;

        $extension = $file->getClientOriginalExtension() ?: 'ico';
        $filename = 'favicon_' . now()->format('Ymd_His') . '.' . $extension;
        $path = $file->storeAs('site', $filename, 'public');

        AdminSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => array_merge($value, ['favicon_path' => $path])]
        );

        if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        return [
            'favicon_path' => $path,
            'favicon_url' => $this->toPublicUrl($path),
        ];
    }

    public function updateAvatarPlaceholder(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $settings = AdminSetting::query()->where('key', self::KEY)->first();
        $value = is_array($settings?->value) ? $settings->value : [];
        $oldPath = $value['avatar_placeholder_path'] ?? null;

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'avatar_placeholder_' . now()->format('Ymd_His') . '.' . $extension;
        $path = $file->storeAs('site', $filename, 'public');

        AdminSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => array_merge($value, ['avatar_placeholder_path' => $path])]
        );

        if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        return [
            'avatar_placeholder_path' => $path,
            'avatar_placeholder_url' => $this->toPublicUrl($path),
        ];
    }

    public function getMetaSettings(): array
    {
        $settings = AdminSetting::query()->where('key', self::META_KEY)->first();
        $value = is_array($settings?->value) ? $settings->value : [];

        return [
            'include_site_title' => (bool) ($value['include_site_title'] ?? false),
        ];
    }

    public function updateMetaSettings(array $data): array
    {
        $payload = [
            'include_site_title' => !empty($data['include_site_title']),
        ];

        AdminSetting::query()->updateOrCreate(
            ['key' => self::META_KEY],
            ['value' => $payload]
        );

        return $payload;
    }

    private function toPublicUrl(string $path): string
    {
        return '/storage/' . ltrim($path, '/');
    }
}
