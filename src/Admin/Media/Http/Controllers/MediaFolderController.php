<?php

declare(strict_types=1);

namespace Baobab\Admin\Media\Http\Controllers;

use Baobab\Media\Actions\CreateMediaFolder;
use Baobab\Media\Actions\DeleteMediaFolder;
use Baobab\Media\Actions\RenameMediaFolder;
use Baobab\Media\Exceptions\FolderNotEmptyException;
use Baobab\Media\Models\MediaFolder;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Adaptateur mince sur les Actions de dossiers (M4 point 1b). Pas de policy
 * dédiée aux dossiers (spec 06 §2 : permissions par dossier prévues en v2
 * seulement) — gardé par `baobab.media.upload`, la permission la plus proche
 * d'« organiser la bibliothèque ».
 */
final class MediaFolderController
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $folder = app(CreateMediaFolder::class)(
            $request->string('name')->toString(),
            $request->integer('parent_id') ?: null,
        );

        return redirect()->route('admin.media.index', ['folder' => $folder->id]);
    }

    public function update(Request $request, MediaFolder $folder): RedirectResponse
    {
        $this->authorizeManage();

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        app(RenameMediaFolder::class)($folder, $request->string('name')->toString());

        return back();
    }

    public function destroy(MediaFolder $folder): RedirectResponse
    {
        $this->authorizeManage();

        $parentId = $folder->parent_id;

        try {
            app(DeleteMediaFolder::class)($folder);
        } catch (FolderNotEmptyException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return back();
        }

        return redirect()->route('admin.media.index', ['folder' => $parentId]);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('baobab.media.upload'), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
