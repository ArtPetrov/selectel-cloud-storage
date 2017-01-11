<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use ArgentCrusade\Selectel\CloudStorage\Collections\Collection;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException;
use Countable;
use JsonSerializable;
use LogicException;

class Container implements ContainerContract, Countable, JsonSerializable
{
    /**
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     */
    protected $api;

    /**
     * Container name.
     *
     * @var string
     */
    protected $name;

    /**
     * Container data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Determines if container data was already loaded.
     *
     * @var bool
     */
    protected $dataLoaded = false;

    /**
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param array                                                                $data
     */
    public function __construct(ApiClientContract $api, $name, array $data = [])
    {
        $this->api = $api;
        $this->name = $name;
        $this->data = $data;
        $this->dataLoaded = !empty($data);
    }

    /**
     * Returns specific container data.
     *
     * @param string $key
     * @param mixed  $default = null
     *
     * @return mixed
     */
    protected function containerData($key, $default = null)
    {
        if (!$this->dataLoaded) {
            $this->loadContainerData();
        }

        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Lazy loading for container data.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    protected function loadContainerData()
    {
        // CloudStorage::containers and CloudStorage::getContainer methods did not
        // produce any requests to Selectel API, since it may be unnecessary if
        // user only wants to upload/manage files or delete container via API.

        // If user really wants some container info, we will load
        // it here on demand. This speeds up application a bit.

        $response = $this->api->request('HEAD', '/'.$this->name());

        if ($response->getStatusCode() !== 204) {
            throw new ApiRequestFailedException('Container "'.$this->name().'" was not found.');
        }

        $this->dataLoaded = true;
        $this->data = [
            'type' => $response->getHeaderLine('X-Container-Meta-Type'),
            'count' => intval($response->getHeaderLine('X-Container-Object-Count')),
            'bytes' => intval($response->getHeaderLine('X-Container-Bytes-Used')),
            'rx_bytes' => intval($response->getHeaderLine('X-Received-Bytes')),
            'tx_bytes' => intval($response->getHeaderLine('X-Transfered-Bytes')),
        ];
    }

    /**
     * Container name.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Container visibility type.
     *
     * @return string
     */
    public function type()
    {
        return $this->containerData('type', 'public');
    }

    /**
     * Container files count.
     *
     * @return int
     */
    public function filesCount()
    {
        return intval($this->containerData('count', 0));
    }

    /**
     * Container files count.
     *
     * @return int
     */
    public function count()
    {
        return $this->filesCount();
    }

    /**
     * Container size in bytes.
     *
     * @return int
     */
    public function size()
    {
        return intval($this->containerData('bytes', 0));
    }

    /**
     * Total uploaded (received) bytes.
     *
     * @return int
     */
    public function uploadedBytes()
    {
        return intval($this->containerData('rx_bytes', 0));
    }

    /**
     * Total downloaded (transmitted) bytes.
     *
     * @return int
     */
    public function downloadedBytes()
    {
        return intval($this->containerData('tx_bytes', 0));
    }

    /**
     * Returns JSON representation of container.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->name(),
            'type' => $this->type(),
            'files_count' => $this->filesCount(),
            'size' => $this->size(),
            'uploaded_bytes' => $this->uploadedBytes(),
            'downloaded_bytes' => $this->downloadedBytes(),
        ];
    }

    /**
     * Determines if container is public.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->type() == 'public';
    }

    /**
     * Determines if container is private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return !$this->isPublic();
    }

    /**
     * Retrieves files from current container.
     *
     * @param string $directory        = null
     * @param string $prefixOrFullPath = null
     * @param string $delimiter        = null
     * @param int    $limit            = 10000
     * @param string $marker           = ''
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Collections\CollectionContract
     */
    public function files($directory = null, $prefixOrFullPath = null, $delimiter = null, $limit = 10000, $marker = '')
    {
        $response = $this->api->request('GET', '/'.$this->name(), [
            'query' => [
                'limit' => intval($limit),
                'marker' => $marker,
                'path' => !is_null($directory) ? ltrim($directory, '/') : '',
                'prefix' => !is_null($prefixOrFullPath) ? ltrim($prefixOrFullPath, '/') : '',
                'delimiter' => !is_null($delimiter) ? $delimiter : '',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new ApiRequestFailedException('Unable to list container files.', $response->getStatusCode());
        }

        return new Collection(json_decode($response->getBody(), true));
    }

    /**
     * Retrieves file object container. This method does not actually download file, see File::download.
     *
     * @param string $path
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException
     * @throws \LogicException
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FileContract
     */
    public function getFile($path)
    {
        $files = $this->files(null, $path);

        if (!count($files)) {
            throw new FileNotFoundException('File "'.$path.'" was not found in container "'.$this->name().'".');
        }

        if (count($files) > 1) {
            throw new LogicException('There is more than one file that satisfies given path "'.$path.'".');
        }

        return new File($this->api, $this->name(), $files->get(0));
    }

    /**
     * Uploads file contents from string. Returns ETag header value if upload was successful.
     *
     * @param string $path           Remote path.
     * @param string $contents       File contents.
     * @param array  $params         = [] Upload params.
     * @param bool   $verifyChecksum = true
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function uploadFromString($path, $contents, array $params = [], $verifyChecksum = true)
    {
        $headers = $this->convertUploadParamsToHeaders($contents, $params, $verifyChecksum);
        $url = $this->normalizeUploadPath($path);

        $response = $this->api->request('PUT', $url, [
            'headers' => $headers,
            'body' => $contents,
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new UploadFailedException('Unable to upload file from string.', $response->getStatusCode());
        }

        return $response->getHeaderLine('ETag');
    }

    /**
     * Uploads file from stream. Returns ETag header value if upload was successful.
     *
     * @param string   $path     Remote path.
     * @param resource $resource Stream resource.
     * @param array    $params   = [] Upload params.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function uploadFromStream($path, $resource, array $params = [])
    {
        $headers = $this->convertUploadParamsToHeaders(null, $params, false);
        $url = $this->normalizeUploadPath($path);

        $response = $this->api->request('PUT', $url, [
            'headers' => $headers,
            'body' => $resource,
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new UploadFailedException('Unable to upload file from stream.', $response->getStatusCode());
        }

        return $response->getHeaderLine('ETag');
    }

    /**
     * Deletes container. Container must be empty in order to perform this operation.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    public function delete()
    {
        $response = $this->api->request('DELETE', '/'.$this->name());

        switch ($response->getStatusCode()) {
            case 204:
                // Container removed.
                return;
            case 404:
                throw new ApiRequestFailedException('Container "'.$this->name().'" was not found.');
            case 409:
                throw new ApiRequestFailedException('Container must be empty.');
        }
    }

    /**
     * Normalizes upload path.
     *
     * @param string $path Remote path (without container name).
     *
     * @return string
     */
    protected function normalizeUploadPath($path)
    {
        return '/'.$this->name().'/'.ltrim($path, '/');
    }

    /**
     * Parses upload parameters and assigns them to appropriate HTTP headers.
     *
     * @param mixed $contents       = null
     * @param array $params         = []
     * @param bool  $verifyChecksum = true
     *
     * @return array
     */
    protected function convertUploadParamsToHeaders($contents = null, array $params = [], $verifyChecksum = true)
    {
        $headers = [];

        if ($verifyChecksum) {
            $headers['ETag'] = md5($contents);
        }

        $availableParams = [
            'contentType' => 'Content-Type',
            'contentDisposition' => 'Content-Disposition',
            'deleteAfter' => 'X-Delete-After',
            'deleteAt' => 'X-Delete-At',
        ];

        foreach ($availableParams as $key => $header) {
            if (isset($params[$key])) {
                $headers[$header] = $params[$key];
            }
        }

        return $headers;
    }
}