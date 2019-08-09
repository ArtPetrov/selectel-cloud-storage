<?php

namespace ArtPetrov\Selectel\CloudStorage\Contracts;

interface CloudStorageContract
{
    /**
     * Available containers.
     *
     * @param int    $limit  = 10000
     * @param string $marker = ''
     *
     * @throws \ArtPetrov\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\Collections\CollectionContract
     */
    public function containers($limit = 10000, $marker = '');

    /**
     * Retrieves single container from cloud storage.
     *
     * @param string $name
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\ContainerContract
     */
    public function getContainer($name);

    /**
     * Creates new container.
     *
     * @param string $name
     * @param string $type
     *
     * @throws \InvalidArgumentException
     * @throws \ArtPetrov\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\ContainerContract
     */
    public function createContainer($name, $type = 'public');
}
