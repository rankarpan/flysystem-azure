<?php

namespace League\Flysystem\Azure;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class AzureTemporaryUrl implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'temporaryUrl';
    }

    public function handle($path, $expiration, array $options = [])
    {
        return $this->filesystem->getAdapter()->getTemporaryUrl($path, $expiration, $options);
    }
}