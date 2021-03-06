<?php

namespace League\Flysystem\Azure;

use Carbon\Carbon;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\Util;

use MicrosoftAzure\Storage\Blob\Internal\IBlob;
use MicrosoftAzure\Storage\Blob\Models\BlobPrefix;
use MicrosoftAzure\Storage\Blob\Models\BlobProperties;
use MicrosoftAzure\Storage\Blob\Models\CopyBlobResult;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsResult;
use MicrosoftAzure\Storage\Common\ServiceException;

class AzureAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /**
     * @var string
     */
    protected $config;

    /**
     * @var IBlob
     */
    protected $client;

    /**
     * @var API Version
     */
    protected $version = '2015-04-05';

    /**
     * @var string[]
     */
    protected static $metaOptions = [
        'CacheControl',
        'ContentType',
        'Metadata',
        'ContentLanguage',
        'ContentEncoding',
    ];

    /**
     * Constructor.
     *
     * @param IBlob  $azureClient
     * @param string $container
     */
    public function __construct(IBlob $azureClient, $config, $prefix = null)
    {
        $this->client = $azureClient;
        $this->setPathPrefix($prefix);
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function getPutFile($path, $local_path, Config $config)
    {
        return $this->upload($path, fopen($local_path, 'r'), $config->set('mimetype', mime_content_type($local_path)));
    }

    /**
     * {@inheritdoc}
     */
    public function getPutBlobFile($path, $local_path, Config $config)
    {
        return $this->getPutFile($path, $local_path, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        $this->copy($path, $newpath);

        return $this->delete($path);
    }

    public function copy($path, $newpath)
    {
        $path = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        $this->client->copyBlob($this->config['container'], $newpath, $this->config['container'], $path);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $path = $this->applyPathPrefix($path);

        $this->client->deleteBlob($this->config['container'], $path);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        $dirname = $this->applyPathPrefix($dirname);

        $options = new ListBlobsOptions();
        $options->setPrefix($dirname . '/');

        /** @var ListBlobsResult $listResults */
        $listResults = $this->client->listBlobs($this->config['container'], $options);

        foreach ($listResults->getBlobs() as $blob) {
            /** @var \MicrosoftAzure\Storage\Blob\Models\Blob $blob */
            $this->client->deleteBlob($this->config['container'], $blob->getName());
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        $this->write(rtrim($dirname, '/').'/', ' ', $config);

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $this->client->getBlobMetadata($this->config['container'], $path);
        } catch (ServiceException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $path = $this->applyPathPrefix($path);

        /** @var \MicrosoftAzure\Storage\Blob\Models\GetBlobResult $blobResult */
        $blobResult = $this->client->getBlob($this->config['container'], $path);
        $properties = $blobResult->getProperties();
        $content = $this->streamContentsToString($blobResult->getContentStream());

        return $this->normalizeBlobProperties($path, $properties) + ['contents' => $content];
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        /** @var \MicrosoftAzure\Storage\Blob\Models\GetBlobResult $blobResult */
        $blobResult = $this->client->getBlob($this->config['container'], $path);
        $properties = $blobResult->getProperties();

        return $this->normalizeBlobProperties($path, $properties) + ['stream' => $blobResult->getContentStream()];
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->applyPathPrefix($directory);

        // Append trailing slash only for directory other than root (which after normalization is an empty string).
        // Listing for / doesn't work properly otherwise.
        if (strlen($directory)) {
            $directory = rtrim($directory, '/') . '/';
        }

        $options = new ListBlobsOptions();
        $options->setPrefix($directory);

        if (!$recursive) {
            $options->setDelimiter('/');
        }

        /** @var ListBlobsResult $listResults */
        $listResults = $this->client->listBlobs($this->config['container'], $options);

        $contents = [];

        foreach ($listResults->getBlobs() as $blob) {
            $contents[] = $this->normalizeBlobProperties($blob->getName(), $blob->getProperties());
        }

        if (!$recursive) {
            $contents = array_merge($contents, array_map([$this, 'normalizeBlobPrefix'], $listResults->getBlobPrefixes()));
        }

        return Util::emulateDirectories($contents);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        /** @var \MicrosoftAzure\Storage\Blob\Models\GetBlobPropertiesResult $result */
        $result = $this->client->getBlobProperties($this->config['container'], $path);

        return $this->normalizeBlobProperties($path, $result->getProperties());
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Builds the normalized output array.
     *
     * @param string $path
     * @param int    $timestamp
     * @param mixed  $content
     *
     * @return array
     */
    protected function normalize($path, $timestamp, $content = null)
    {
        $data = [
            'path' => $path,
            'timestamp' => (int) $timestamp,
            'dirname' => Util::dirname($path),
            'type' => 'file',
        ];

        if (is_string($content)) {
            $data['contents'] = $content;
        }

        return $data;
    }

    /**
     * Builds the normalized output array from a Blob object.
     *
     * @param string         $path
     * @param BlobProperties $properties
     *
     * @return array
     */
    protected function normalizeBlobProperties($path, BlobProperties $properties)
    {
        if (substr($path, -1) === '/') {
            return ['type' => 'dir', 'path' => $this->removePathPrefix(rtrim($path, '/'))];
        }

        $path = $this->removePathPrefix($path);

        return [
            'path' => $path,
            'timestamp' => (int) $properties->getLastModified()->format('U'),
            'dirname' => Util::dirname($path),
            'mimetype' => $properties->getContentType(),
            'size' => $properties->getContentLength(),
            'type' => 'file',
        ];
    }

    /**
     * Builds the normalized output array from a BlobPrefix object.
     *
     * @param BlobPrefix $blobPrefix
     *
     * @return array
     */
    protected function normalizeBlobPrefix(BlobPrefix $blobPrefix)
    {
        return ['type' => 'dir', 'path' => $this->removePathPrefix(rtrim($blobPrefix->getName(), '/'))];
    }

    /**
     * Retrieves content streamed by Azure into a string.
     *
     * @param resource $resource
     *
     * @return string
     */
    protected function streamContentsToString($resource)
    {
        return stream_get_contents($resource);
    }

    /**
     * Upload a file.
     *
     * @param string           $path     Path
     * @param string|resource  $contents Either a string or a stream.
     * @param Config           $config   Config
     *
     * @return array
     */
    protected function upload($path, $contents, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        /** @var CopyBlobResult $result */
        $result = $this->client->createBlockBlob($this->config['container'], $path, $contents, $this->getOptionsFromConfig($config));

        return $this->normalize($path, $result->getLastModified()->format('U'), $contents);
    }

    /**
     * Retrieve options from a Config instance.
     *
     * @param Config $config
     *
     * @return CreateBlobOptions
     */
    protected function getOptionsFromConfig(Config $config)
    {

        $options = new CreateBlobOptions();

        foreach (static::$metaOptions as $option) {
            if ($config->has($option)) {
                call_user_func([$options, "set$option"], $config->get($option));
            }

            if (isset($this->config['options'][$option])) {
                call_user_func([$options, "set$option"], $this->config['options'][$option]);
            }

        }

        if ($mimetype = $config->get('mimetype')) {
            $options->setContentType($mimetype);
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getDomainUrl()
    {
        if (isset($this->config['domain'])) {
            $domain_url = $this->config['domain'][array_rand($this->config['domain'])];
        } else {
            $domain_url = $this->client->getUri();
        }

        return rtrim($domain_url, '/\\');
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path)
    {
        return $this->getDomainUrl() .'/'. $this->config['container'] . '/' . $this->applyPathPrefix($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getRemoteCopy($remote_path, $local_path)
    {
        return copy($this->getSignedUrl($remote_path, str_replace('+00:00', 'Z', Carbon::now()->addHour()->toIso8601String())), $local_path);
    }

    /**
     * Retrive Signed Url of Private File
     *
     * @param string $path
     * @param string|NULL $expiry
     * @param string $resourceType
     * @param string $permissions
     *
     */
    public function getSignedUrl($path, $expiry = NULL, $signedIP = NULL, $resourceType = 'b', $permissions = 'r')
    {
        $path = $this->applyPathPrefix($path);

        if ($expiry) {
            return $this->getBlobUrl($path, $resourceType, $permissions, $expiry, $signedIP);
        } else {
            return $this->getDomainUrl() .'/'. $this->config['container'] .'/'. $path;
        }
    }

    /**
     * Retrive Temporary Url of Private File
     *
     * @param string $path
     * @param string|NULL $expiry
     * @param string $resourceType
     * @param string $permissions
     *
     */
    public function getTemporaryUrl($path, $expiration, array $options = [])
    {
        return $this->getSignedUrl($path, str_replace('+00:00', 'Z', $expiration->toIso8601String()), '127.0.0.1');
    }

    private function getSASForBlob($blob, $resourceType, $permissions, $expiry, $signedIP)
    {
        $key = $this->config['key'];
        if (is_null($key)) {
            throw new ServiceException("Please Set Storage Key in FileSystems.");
        }

        /* Create the signature */
        $_arraysign = array();
        $_arraysign[] = $permissions;
        $_arraysign[] = '';
        $_arraysign[] = $expiry;
        $_arraysign[] = '/blob/'. $this->config['name'] .'/'. $this->config['container'] .'/'. $blob;
        $_arraysign[] = '';
        $_arraysign[] = $this->signedIP($signedIP);
        $_arraysign[] = '';
        $_arraysign[] = $this->version; //the API version is now required
        $_arraysign[] = '';
        $_arraysign[] = '';
        $_arraysign[] = '';
        $_arraysign[] = '';
        $_arraysign[] = '';

        $_str2sign = implode("\n", $_arraysign);

        // dd($_str2sign);
         
        return base64_encode(hash_hmac('sha256', urldecode(utf8_encode($_str2sign)), base64_decode($key), true));
    }

    public function getBlobUrl($blob, $resourceType, $permissions, $expiry, $signedIP)
    {
        /* Create the signed query part */
        $_parts = array();
        $_parts[] = (!empty($expiry)) ? 'se=' . urlencode($expiry) : '';
        $_parts[] = 'sr=' . $resourceType;
        $_parts[] = (!empty($permissions)) ? 'sp=' . $permissions : '';
        $_parts[] = (!empty($this->signedIP($signedIP))) ? 'sip=' . $this->signedIP($signedIP) : '';
        // $_parts[] = 'spr=' . 'https';
        $_parts[] = 'sig=' . urlencode($this->getSASForBlob($blob, $resourceType, $permissions, $expiry, $signedIP));
        $_parts[] = 'sv=' . $this->version;

        /* Create the signed blob URL */
        return $this->getDomainUrl() .'/'. $this->config['container'] .'/'. $blob .'?'. implode('&', $_parts);
    }

    public function signedIP($signedIP)
    {
        if (is_null($signedIP)) {
            $signedIP = request()->ip();
        }

        if (preg_match('/(^127\.)|(^10\.)|(^172\.1[6-9]\.)|(^172\.2[0-9]\.)|(^172\.3[0-1]\.)|(^192\.168\.)/', $signedIP)) {
            $signedIP = '';
        }

        return $signedIP;
    }
}
