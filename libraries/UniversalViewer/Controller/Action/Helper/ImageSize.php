<?php

/*
 * Copyright 2015-2019 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

/**
 * @see Zend_Controller_Action_Helper_Abstract
 */
require_once 'Zend/Controller/Action/Helper/Abstract.php';

class UniversalViewer_Controller_Action_Helper_ImageSize extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Get an array of the width and height of the image file.
     *
     * @param File $file
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     */
    public function imageSize(File $file, $imageType = 'original')
    {
        // Check if this is an image.
        if (strtok($file->mime_type, '/') !== 'image') {
            return array(
                'width' => null,
                'height' => null,
            );
        }

        // This is an image, so get the resolution directly, because sometime
        // the resolution is not stored and because the size may be lower than
        // the derivative constraint, in particular when the original image size
        // is lower than the derivative constraint, or when the constraint
        // changed.

        // The storage adapter should be checked for external storage.
        $storageAdapter = $file->getStorage()->getAdapter();
        $filepath = get_class($storageAdapter) == 'Omeka_Storage_Adapter_Filesystem'
            ? FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath($imageType)
            : $file->getWebPath($imageType);
        $result = $this->getWidthAndHeight($filepath);

        // This is an image, but failed to get the resolution.
        if (empty($result)) {
            $msg = __('Failed to get resolution of image #%d ("%s").', $file->id, $filepath);
            throw new Exception($msg);
        }

        return $result;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     */
    protected function getWidthAndHeight($filepath)
    {
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempname = tempnam(sys_get_temp_dir(), 'uv_');
            $result = file_put_contents($tempname, $filepath);
            if ($result !== false) {
                $result = getimagesize($filepath);
                if ($result) {
                    list($width, $height) = $result;
                }
                unlink($tempname);
            }
        } elseif (file_exists($filepath)) {
            $result = getimagesize($filepath);
            if ($result) {
                list($width, $height) = $result;
            }
        }

        if (empty($width) || empty($height)) {
            return null;
        }

        return array(
            'width' => $width,
            'height' => $height,
        );
    }
}
