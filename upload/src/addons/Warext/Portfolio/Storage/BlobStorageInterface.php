<?php

namespace Warext\Portfolio\Storage;

interface BlobStorageInterface
{
    public function ensureFromAbstractedPath(string $sourcePath, string $sha256, string $extension): string;

    public function readStream(string $storageName);

    public function delete(string $storageName): void;
}
