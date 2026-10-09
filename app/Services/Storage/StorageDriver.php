<?php

namespace App\Services\Storage;

interface StorageDriver
{
    /** Store contents; returns the path/identifier to save in storage_files.path */
    public function put(string $path, string $contents, string $mime): string;

    public function get(string $path): string;

    public function delete(string $path): void;

    /** Returns true or an error message. */
    public function test(): true|string;
}
