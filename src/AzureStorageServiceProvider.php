<?php

namespace League\Flysystem\Azure;

use Storage;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Azure\AzureAdapter;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use League\Flysystem\Azure\AzureSignedUrl;
use League\Flysystem\Azure\AzurePutFile;
use League\Flysystem\Azure\AzureRemoteCopy;

class AzureStorageServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('azure', function($app, $config) {
            
            if ($config['emulator'] === false) {
                $endpoint = sprintf('DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
                    $config['protocol'],
                    $config['name'],
                    $config['key']);
            } else {
                $endpoint = sprintf('UseDevelopmentStorage=true;DevelopmentStorageProxyUri=%s',
                    $config['proxy_uri']);
            }
            $blobRestProxy = ServicesBuilder::getInstance()->createBlobService($endpoint);
            $filesystem = new Filesystem(new AzureAdapter($blobRestProxy, $config, null));
            return $filesystem->addPlugin(new AzureSignedUrl)->addPlugin(new AzurePutFile)->addPlugin(new AzureRemoteCopy);
        });
    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}