<?php

namespace ArtPetrov\Selectel\CloudStorage\Traits;

use ArtPetrov\Selectel\CloudStorage\File;
use ArtPetrov\Selectel\CloudStorage\Collections\Collection;

trait FilesTransformer
{
    /**
     * Container name. This name will be used in transformation process.
     *
     * @return string
     */
    abstract public function containerName();

    /**
     * API Client.
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    abstract public function apiClient();

    /**
     * Transforms file array to instance of File object.
     *
     * @param array $file File array.
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\FileContract
     */
    public function getFileFromArray(array $file)
    {
        return new File($this->apiClient(), $this->containerName(), $file);
    }

    /**
     * Transforms Collection of file arrays (or plain array) to Collection of File objects.
     * Warning: converting a lot of files to `File` instances may result in performance loss.
     *
     * @param array|\ArtPetrov\Selectel\CloudStorage\Collections\Collection $files
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Collections\Collection
     */
    public function getFilesCollectionFromArrays($files)
    {
        $collection = new Collection();

        foreach ($files as $file) {
            $collection[] = $this->getFileFromArray($file);
        }

        return $collection;
    }
}
