<?php

declare(strict_types=1);

namespace Baobab\Admin\Media\Http\Controllers;

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Adaptateur mince sur UploadMedia (M4 point 1a) — pas encore d'écran
 * (grille, drag & drop) : ce endpoint prouve l'Action sur une vraie requête
 * HTTP, l'UI de bibliothèque arrive au point 1b.
 */
final class MediaController
{
    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($actor->can('create', Media::class), 403);

        $request->validate(['file' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        if ($file->getMimeType() === 'image/svg+xml') {
            abort_unless($actor->can('baobab.media.upload_svg'), 403);
        }

        $media = app(UploadMedia::class)(
            $file,
            $actor,
            $request->only(['title', 'alt', 'caption', 'description']),
        );

        return response()->json($media, 201);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
