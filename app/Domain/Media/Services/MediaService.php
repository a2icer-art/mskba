<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MediaService
{
    public function upload(UploadedFile $file, Model $mediable, string $collection = 'default', ?User $actor = null): Media
    {
        $disk = config('filesystems.media_disk', 'public');
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s_%s.%s', Str::slug($mediable::class), now()->format('Ymd_His'), $extension);
        $path = $file->storeAs('media', $filename, $disk);

        $isFirstMedia = Media::query()
            ->where('mediable_type', $mediable::class)
            ->where('mediable_id', $mediable->getKey())
            ->count() === 0;

        $collection = $collection ?: 'gallery';

        $media = Media::create([
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->getKey(),
            'is_avatar' => $isFirstMedia,
            'is_featured' => false,
            'collection' => $collection,
            'type' => $file->getClientMimeType() ?: 'image',
            'disk' => $disk,
            'path' => $path,
            'meta' => [],
            'size' => $file->getSize() ?: 0,
            'mime' => $file->getClientMimeType() ?? '',
            'created_by' => $actor?->getKey() ?? null,
            'status' => 'uploaded',
        ]);

        if ($media->is_avatar) {
            Media::query()
                ->where('mediable_type', $mediable::class)
                ->where('mediable_id', $mediable->getKey())
                ->whereKeyNot($media->getKey())
            ->update(['is_avatar' => false]);
        }

        return $media;
    }

    public function delete(Media $media): bool
    {
        // Soft delete: keep file on disk to allow restore
        return (bool) $media->delete();
    }

    public function update(Media $media, array $data): Media
    {
        $allowed = array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'collection' => $data['collection'] ?? null,
            'is_avatar' => array_key_exists('is_avatar', $data) ? (bool) $data['is_avatar'] : null,
            'is_featured' => array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : null,
        ], function ($v) { return !is_null($v); });

        if (array_key_exists('is_avatar', $allowed) && $allowed['is_avatar'] === false) {
            $hasOtherAvatar = Media::query()
                ->where('mediable_type', $media->mediable_type)
                ->where('mediable_id', $media->mediable_id)
                ->where('is_avatar', true)
                ->whereKeyNot($media->getKey())
                ->exists();

            if (!$hasOtherAvatar) {
                throw new \RuntimeException('Нельзя снять последний аватар');
            }
        }

        $media->fill($allowed);
        $media->save();

        if (array_key_exists('is_avatar', $allowed) && $media->is_avatar) {
            Media::query()
                ->where('mediable_type', $media->mediable_type)
                ->where('mediable_id', $media->mediable_id)
                ->whereKeyNot($media->getKey())
                ->update(['is_avatar' => false]);
        }

        return $media->refresh();
    }

    public function forceDelete(Media $media): bool
    {
        $disk = Storage::disk($media->disk ?? 'public');
        if ($media->path && $disk->exists($media->path)) {
            $disk->delete($media->path);
        }

        return (bool) $media->forceDelete();
    }

    public function restore(Media $media): Media
    {
        $disk = Storage::disk($media->disk ?? 'public');

        if (!$media->path || !$disk->exists($media->path)) {
            throw new \RuntimeException('Associated media file not found on disk');
        }

        $media->restore();
        return $media->refresh();
    }

    public function toPublicUrl(Media $media): string
    {
        $disk = $media->disk ?? config('filesystems.media_disk', 'public');
        if ($disk === 'public') {
            return '/storage/' . ltrim($media->path, '/');
        }
        return Storage::disk($disk)->url($media->path);
    }
}
