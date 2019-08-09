<?php

namespace ArtPetrov\Selectel\CloudStorage\Contracts;

use ArtPetrov\Selectel\CloudStorage\Contracts\Api\ApiClientContract;

interface FileUploaderContract
{
    /**
     * Upload file from string or stream resource.
     *
     * @param \ArtPetrov\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param string                                                               $path           Remote path.
     * @param string|resource                                                      $body           File contents.
     * @param array                                                                $params         = [] Upload params.
     * @param bool                                                                 $verifyChecksum = true
     *
     * @throws \ArtPetrov\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function upload(ApiClientContract $api, $path, $body, array $params = [], $verifyChecksum = true);
}
