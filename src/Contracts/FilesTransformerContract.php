<?php

namespace ArtPetrov\Selectel\CloudStorage\Contracts;

interface FilesTransformerContract
{
    /**
     * Transforms file array to instance of File object.
     *
     * @param array $file File array.
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\FileContract
     */
    public function getFileFromArray(array $file);

    /**
     * Transforms Collection of file arrays (or plain array) to Collection of File objects.
     * Warning: converting a lot of files to `File` instances may result in performance loss.
     *
     * @param array|\ArtPetrov\Selectel\CloudStorage\Collections\Collection $files
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Collections\Collection
     */
    public function getFilesCollectionFromArrays($files);
}
