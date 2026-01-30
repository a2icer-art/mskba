<?php

namespace App\Http\Controllers;

use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaService;
use App\Domain\Venues\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function store(Request $request, string $type, Venue $venue, MediaService $service)
    {
        $this->authorize('manageMedia', $venue);

        $request->validate([
            'file' => ['required', 'file'],
            'collection' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $collection = $request->input('collection', 'gallery');

        $media = $service->upload($file, $venue, $collection, $request->user());

        return response()->json([
            'media' => $this->formatMedia($media, $service),
        ], 201);
    }

    public function update(Request $request, string $type, Venue $venue, Media $media, MediaService $service)
    {
        $mediable = $media->mediable;

        if (!($mediable instanceof Venue) || $mediable->getKey() !== $venue->getKey()) {
            abort(404);
        }

        $this->authorize('manageMedia', $venue);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'collection' => ['nullable', 'string', 'max:64'],
            'is_avatar' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        try {
            $media = $service->update($media, $data);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'last_avatar',
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'media' => $this->formatMedia($media, $service),
        ]);
    }

    public function destroy(string $type, Venue $venue, Media $media, MediaService $service)
    {
        $mediable = $media->mediable;

        if (!($mediable instanceof Venue) || $mediable->getKey() !== $venue->getKey()) {
            abort(404);
        }

        $this->authorize('manageMedia', $venue);
        $service->delete($media);

        return response()->json(['ok' => true]);
    }

    public function restore(string $type, Venue $venue, int $media, MediaService $service)
    {
        $media = Media::withTrashed()->findOrFail($media);
        $mediable = $media->mediable;

        if (!($mediable instanceof Venue) || $mediable->getKey() !== $venue->getKey()) {
            abort(404);
        }

        $this->authorize('manageMedia', $venue);

        try {
            $media = $service->restore($media);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'file_missing',
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'media' => $this->formatMedia($media, $service),
        ]);
    }

    public function forceDestroy(string $type, Venue $venue, int $media, MediaService $service)
    {
        $media = Media::withTrashed()->findOrFail($media);
        $mediable = $media->mediable;

        if (!($mediable instanceof Venue) || $mediable->getKey() !== $venue->getKey()) {
            abort(404);
        }

        $this->authorize('manageMedia', $venue);

        $service->forceDelete($media);

        return response()->json(['ok' => true]);
    }

    private function formatMedia(Media $media, MediaService $service): array
    {
        return [
            'id' => $media->getKey(),
            'title' => $media->title,
            'description' => $media->description,
            'is_avatar' => (bool) $media->is_avatar,
            'is_featured' => (bool) $media->is_featured,
            'collection' => $media->collection,
            'path' => $media->path,
            'size' => $media->size,
            'mime' => $media->mime,
            'url' => $media->path ? $service->toPublicUrl($media) : null,
            'created_by' => $media->created_by,
            'created_at' => $media->created_at?->toDateTimeString(),
            'deleted_at' => $media->deleted_at?->toDateTimeString(),
        ];
    }
}
