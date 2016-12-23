<?php

namespace League\Flysystem\Azure;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class AzurePutFile implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'putFile';
    }

    public function handle($path, $local_path)
    {
        return $this->filesystem->getAdapter()->getPutFile($path, $local_path);
    }
}