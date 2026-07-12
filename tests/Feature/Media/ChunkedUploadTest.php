<?php

use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Baobab\Media\Support\ChunkedUploadAssembler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
});

function chunkFile(string $contents): UploadedFile
{
    $path = sys_get_temp_dir().'/baobab-test-chunk-'.bin2hex(random_bytes(6));
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'chunk', null, null, true);
}

it('returns null until every chunk has been received, then assembles them in order', function () {
    $assembler = app(ChunkedUploadAssembler::class);
    $uploadId = (string) Str::uuid();

    expect($assembler->receive($uploadId, 0, 3, chunkFile('AAA')))->toBeNull()
        ->and($assembler->receive($uploadId, 2, 3, chunkFile('CCC')))->toBeNull();

    $assembledPath = $assembler->receive($uploadId, 1, 3, chunkFile('BBB'));

    expect($assembledPath)->not->toBeNull()
        ->and(file_get_contents((string) $assembledPath))->toBe('AAABBBCCC');

    @unlink((string) $assembledPath);
});

it('rejects a malformed upload_id', function () {
    $assembler = app(ChunkedUploadAssembler::class);

    expect(fn () => $assembler->receive('not-a-uuid', 0, 1, chunkFile('AAA')))
        ->toThrow(InvalidMediaUploadException::class);
});
