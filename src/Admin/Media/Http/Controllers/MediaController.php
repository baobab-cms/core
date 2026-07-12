<?php

declare(strict_types=1);

namespace Baobab\Admin\Media\Http\Controllers;

use Baobab\Media\Actions\MoveMedia;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
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
 * Bibliothèque de médias (M4 points 1a/1b) : grille + upload direct ou
 * chunké, tous deux délégués à UploadMedia — même pipeline, même validation.
 */
final class MediaController
{
    public function index(Request $request): View
    {
        $actor = $this->actor();

        abort_unless($actor->can('viewAny', Media::class), 403);

        $folderId = $request->filled('folder') ? $request->integer('folder') : null;

        $query = Media::query()->where('folder_id', $folderId);

        if ($request->filled('q')) {
            $query->where('file_name', 'like', '%'.$request->string('q').'%');
        }

        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->string('type').'/%');
        }

        /** @var LengthAwarePaginator<int, Media> $media */
        $media = $query->orderByDesc('id')->paginate(24)->withQueryString();

        $currentFolder = $folderId !== null ? MediaFolder::find($folderId) : null;

        return view('baobab::admin.media.index', [
            'media' => $media,
            'folders' => MediaFolder::where('parent_id', $folderId)->orderBy('name')->get(),
            'allFolders' => MediaFolder::orderBy('name')->get(),
            'currentFolder' => $currentFolder,
            'breadcrumb' => $this->breadcrumb($currentFolder),
            'canUpload' => $actor->can('create', Media::class),
            'canUploadSvg' => $actor->can('baobab.media.upload_svg'),
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
