<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Amenity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AmenityIconService
{
    private const DISK = 'public';

    public function upload(Amenity $amenity, UploadedFile $file, ?User $actor = null): void
    {
        $disk = Storage::disk(self::DISK);

        if ($amenity->icon_path && $disk->exists($amenity->icon_path)) {
            $disk->delete($amenity->icon_path);
        }

        $path = $disk->putFile('amenities/icons', $file);

        $amenity->update([
            'icon_path' => $path,
            'updated_by' => $actor?->id,
        ]);
    }

    public function getUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
