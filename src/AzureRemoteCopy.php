<?php

namespace League\Flysystem\Azure;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class AzureRemoteCopy implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'remoteCopy';
    }

    public function handle($remote, $local_path)
    {
        return $this->filesystem->getAdapter()->getRemoteCopy($remote, $local_path);
    }
}