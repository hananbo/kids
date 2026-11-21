<?php

namespace App\Support\Catalog;

use App\Enums\QuestionFieldType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionMedia
{
    public const DISK = 'quiz_media';

    /** @return array{path: string, mime: string}|null */
    public static function file(QuestionFieldType $type, string $key): ?array
    {
        if (! preg_match('/^[1-9][0-9]*\/[a-zA-Z0-9][a-zA-Z0-9._-]*$/D', $key)) {
            return null;
        }

        $path = Storage::disk(self::DISK)->path($key);
        $mime = is_file($path) ? mime_content_type($path) : false;

        return in_array($mime, $type->mimeTypes(), true) ? ['path' => $path, 'mime' => $mime] : null;
    }

    /** @param array{type: string, path?: string} $field */
    public static function response(array $field): BinaryFileResponse
    {
        $type = QuestionFieldType::tryFrom($field['type']);
        $file = $type && isset($field['path']) ? self::file($type, $field['path']) : null;
        abort_if($file === null, 404);

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline',
        ]);
    }
}
