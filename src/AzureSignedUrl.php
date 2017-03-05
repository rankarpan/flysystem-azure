<?php

namespace League\Flysystem\Azure;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class AzureSignedUrl implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'signedUrl';
    }

    public function handle($path, $expiry = NULL, $signedIP = NULL)
    {
        return $this->filesystem->getAdapter()->getSignedUrl($path, $expiry, $signedIP);
    }
}