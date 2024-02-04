<?php

namespace App\Values\SongStorageMetadata;

final class LocalMetadata implements StorageMetadata
{
    private function __construct(public string $path)
    {
    }

    public static function make(string $path): self
    {
        return new self($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
