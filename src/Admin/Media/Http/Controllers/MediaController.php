<?php

declare(strict_types=1);

namespace Baobab\Admin\Media\Http\Controllers;

use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\MoveMedia;
use Baobab\Media\Actions\PurgeMedia;
use Baobab\Media\Actions\RestoreMediaFromTrash;
use Baobab\Media\Actions\RestoreOriginalMedia;
use Baobab\Media\Actions\TransformMedia;
use Baobab\Media\Actions\UpdateMediaFocalPoint;
use Baobab\Media\Actions\UpdateMediaMetadata;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
use Baobab\Media\Exceptions\MediaInUseException;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaFolder;
use Baobab\Media\Support\ChunkedUploadAssembler;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Bibliothèque de médias (M4 points 1a/1b/2b/3) : grille + upload direct ou
 * chunké (délégués à UploadMedia, même pipeline, même validation), écran
 * détail (métadonnées, point focal, édition non destructive), corbeille et
 * suppression protégée (suivi d'usage).
 */
final class MediaController
{
    public function index(Request $request): View
    {
        $actor = $this->actor();

        abort_unless($actor->can('viewAny', Media::class), 403);

        $trashed = $request->boolean('trashed');
        $folderId = $trashed || ! $request->filled('folder') ? null : $request->integer('folder');

        $query = $trashed ? Media::onlyTrashed() : Media::query()->where('folder_id', $folderId);

        if ($request->filled('q')) {
            $query->where('file_name', 'like', '%'.$request->string('q').'%');
        }

        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->string('type').'/%');
        }

        if ($request->boolean('unused')) {
            $query->whereDoesntHave('usages');
        }

        /** @var LengthAwarePaginator<int, Media> $media */
        $media = $query->orderByDesc('id')->paginate(24)->withQueryString();

        $currentFolder = ($trashed || $folderId === null) ? null : MediaFolder::find($folderId);

        return view('baobab::admin.media.index', [
            'media' => $media,
            'folders' => $trashed ? collect() : MediaFolder::where('parent_id', $folderId)->orderBy('name')->get(),
            'allFolders' => MediaFolder::orderBy('name')->get(),
            'currentFolder' => $currentFolder,
            'breadcrumb' => $this->breadcrumb($currentFolder),
            'canUpload' => $actor->can('create', Media::class),
            'canUploadSvg' => $actor->can('baobab.media.upload_svg'),
            'trashed' => $trashed,
            'unused' => $request->boolean('unused'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($actor->can('create', Media::class), 403);

        $request->validate(['file' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return $this->attemptUpload($file, $actor, $request);
    }

    public function storeChunk(Request $request): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($actor->can('create', Media::class), 403);

        $request->validate([
            'upload_id' => ['required', 'string'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:5000'],
            'file_name' => ['required', 'string'],
            'chunk' => ['required', 'file'],
        ]);

        $chunkIndex = $request->integer('chunk_index');
        $totalChunks = $request->integer('total_chunks');

        abort_if($chunkIndex >= $totalChunks, 422, 'chunk_index doit être inférieur à total_chunks.');

        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');

        $assembledPath = app(ChunkedUploadAssembler::class)->receive(
            $request->string('upload_id')->toString(),
            $chunkIndex,
            $totalChunks,
            $chunk,
        );

        if ($assembledPath === null) {
            return response()->json(['status' => 'pending'], 202);
        }

        $file = new UploadedFile($assembledPath, $request->string('file_name')->toString(), null, null, true);

        try {
            return $this->attemptUpload($file, $actor, $request);
        } finally {
            if (is_file($assembledPath)) {
                unlink($assembledPath);
            }
        }
    }

    public function move(Request $request): RedirectResponse
    {
        $actor = $this->actor();

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        /** @var list<int> $ids */
        $ids = $request->input('ids');

        /** @var list<Media> $items */
        $items = Media::query()
            ->findMany($ids)
            ->filter(fn (Media $media): bool => $actor->can('update', $media))
            ->values()
            ->all();

        app(MoveMedia::class)($items, $request->integer('folder_id') ?: null);

        return back();
    }

    public function show(Media $media): View
    {
        abort_unless($this->actor()->can('view', $media), 403);

        return view('baobab::admin.media.show', [
            'media' => $media,
            'canUpdate' => $this->actor()->can('update', $media),
            'canDelete' => $this->actor()->can('delete', $media),
            'blockedUsages' => session('mediaUsages'),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        abort_unless($this->actor()->can('update', $media), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        app(UpdateMediaMetadata::class)($media, $validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.media.show.updated')]);

        return redirect()->route('admin.media.show', ['media' => $media->id]);
    }

    public function updateFocalPoint(Request $request, Media $media): JsonResponse
    {
        abort_unless($this->actor()->can('update', $media), 403);

        $validated = $request->validate([
            'focal_x' => ['required', 'numeric', 'between:0,1'],
            'focal_y' => ['required', 'numeric', 'between:0,1'],
        ]);

        $updated = app(UpdateMediaFocalPoint::class)($media, (float) $validated['focal_x'], (float) $validated['focal_y']);

        return response()->json(['focal_x' => $updated->focal_x, 'focal_y' => $updated->focal_y]);
    }

    public function storeTransform(Request $request, Media $media): JsonResponse
    {
        abort_unless($this->actor()->can('update', $media), 403);

        $request->validate(['file' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $updated = app(TransformMedia::class)($media, $file);

        return response()->json($updated);
    }

    public function destroyTransform(Media $media): JsonResponse
    {
        abort_unless($this->actor()->can('update', $media), 403);

        $updated = app(RestoreOriginalMedia::class)($media);

        return response()->json($updated);
    }

    public function destroy(Request $request, Media $media): RedirectResponse|JsonResponse
    {
        abort_unless($this->actor()->can('delete', $media), 403);

        try {
            app(DeleteMedia::class)($media, $request->boolean('force'));
        } catch (MediaInUseException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'usages' => $e->usages], 409);
            }

            return redirect()->route('admin.media.show', ['media' => $media->id])->with('mediaUsages', $e->usages);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.media.show.deleted')]);

        return redirect()->route('admin.media.index');
    }

    public function restore(Media $media): RedirectResponse
    {
        abort_unless($this->actor()->can('restore', $media), 403);

        app(RestoreMediaFromTrash::class)($media);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.media.show.restored')]);

        return redirect()->route('admin.media.index');
    }

    public function forceDestroy(Media $media): RedirectResponse
    {
        abort_unless($this->actor()->can('forceDelete', $media), 403);

        app(PurgeMedia::class)($media);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.media.show.purged')]);

        return redirect()->route('admin.media.index', ['trashed' => 1]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $actor = $this->actor();

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        $deleted = 0;
        $skipped = 0;

        foreach (Media::query()->findMany($ids) as $media) {
            if (! $actor->can('delete', $media)) {
                continue;
            }

            try {
                app(DeleteMedia::class)($media);
                $deleted++;
            } catch (MediaInUseException) {
                $skipped++;
            }
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.media.show.bulk_deleted', ['deleted' => $deleted, 'skipped' => $skipped])]);

        return back();
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $actor = $this->actor();

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        foreach (Media::onlyTrashed()->findMany($ids) as $media) {
            if ($actor->can('restore', $media)) {
                app(RestoreMediaFromTrash::class)($media);
            }
        }

        return back();
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $actor = $this->actor();

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        foreach (Media::onlyTrashed()->findMany($ids) as $media) {
            if ($actor->can('forceDelete', $media)) {
                app(PurgeMedia::class)($media);
            }
        }

        return back();
    }

    private function attemptUpload(UploadedFile $file, User $actor, Request $request): JsonResponse
    {
        if ($file->getMimeType() === 'image/svg+xml') {
            abort_unless($actor->can('baobab.media.upload_svg'), 403);
        }

        /** @var 'reuse'|'new'|null $duplicateAction */
        $duplicateAction = in_array($request->input('duplicate_action'), ['reuse', 'new'], true)
            ? $request->string('duplicate_action')->toString()
            : null;

        try {
            $media = app(UploadMedia::class)(
                $file,
                $actor,
                $request->only(['title', 'alt', 'caption', 'description', 'folder_id']),
                $duplicateAction,
            );
        } catch (DuplicateMediaDetectedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'existing' => $e->existing,
            ], 409);
        }

        return response()->json($media, 201);
    }

    /**
     * @return list<MediaFolder>
     */
    private function breadcrumb(?MediaFolder $folder): array
    {
        $crumbs = [];

        while ($folder !== null) {
            array_unshift($crumbs, $folder);
            $folder = $folder->parent;
        }

        return $crumbs;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
