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

    public function handle($path, $expiry = NULL, $signedIP = NULL, $resourceType = 'b', $permissions = 'r')
    {
        return $this->filesystem->getAdapter()->getTemporaryUrl($path, $expiry, $signedIP, $resourceType, $permissions);
    }
}