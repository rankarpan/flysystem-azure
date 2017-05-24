<?php

namespace League\Flysystem\Azure;

use League\Flysystem\Config;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class AzurePutBlobFile implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'putBlobFile';
    }

    public function handle($path, $local_path, $config = null)
    {
        if (is_null($config)) {
            $config = new Config;
        }

        return $this->filesystem->getAdapter()->getPutBlobFile($path, $local_path, $config);
    }
}