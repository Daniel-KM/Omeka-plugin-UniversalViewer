<?php
/**
 * Helper to get a IIIF info.json for a file.
 */
class UniversalViewer_View_Helper_IiifInfo extends Zend_View_Helper_Abstract
{
    /**
     * Get the IIIF info for the specified record.
     *
     * @todo Replace all data by standard classes.
     *
     * @param Omeka_Record_AbstractRecord|int|null $record
     * @return Object|null
     */
    public function iiifInfo($record = null)
    {
        if (is_null($record)) {
            $record = get_current_record('file', false);
        } elseif (is_numeric($record)) {
            $record = get_record_by_id('File', (int) $record);
        }

        if (empty($record)) {
            return null;
        }

        if (get_class($record) != 'File') {
            return null;
        }

        $file = $record;

        if (strpos($file->mime_type, 'image/') === 0) {
            $sizes = array();
            $availableTypes = array('thumbnail', 'fullsize', 'original');
            foreach ($availableTypes as $imageType) {
                $imageSize = $this->_getImageSize($file, $imageType);
                $size = array();
                $size['width'] = $imageSize['width'];
                $size['height'] = $imageSize['height'];
                $size = (object) $size;
                $sizes[] = $size;
            }

            $imageType = 'original';
            $imageSize = $this->_getImageSize($file, $imageType);
            list($width, $height) = array_values($imageSize);
            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                ), 'universalviewer_image');
            $imageUrl = $this->view->uvForceBaseUrlIfRequired($imageUrl);

            $tiles = array();
            $helper = new UniversalViewer_Controller_Action_Helper_TileInfo();
            $tilingData = $helper->tileInfo($file);
            if ($tilingData) {
                $iiifTileInfo = $this->_iiifTileInfo($tilingData);
                if ($iiifTileInfo) {
                    $tiles[] = $iiifTileInfo;
                }
            }

            $profile = array();
            $profile[] = 'http://iiif.io/api/image/2/level2.json';
            // Temporary fix. See https://github.com/UniversalViewer/universalviewer/issues/438.
            $profile[] = array();
            // According to specifications, the profile details should be omitted,
            // because only default formats, qualities and supports are supported
            // currently.
            /*
            $profileDetails = array();
            $profileDetails['format'] = array('jpg');
            $profileDetails['qualities'] = array('default');
            $profileDetails['supports'] = array('sizeByWhListed');
            $profileDetails = (object) $profileDetails;
            $profile[] = $profileDetails;
            */

            // Exemple of service, useless currently.
            /*
            $service = array();
            $service['@context'] = 'http://iiif.io/api/annex/service/physdim/1/context.json';
            $service['profile'] = 'http://iiif.io/api/annex/service/physdim';
            $service['physicalScale'] = 0.0025;
            $service['physicalUnits'] = 'in';
            $service = (object) $service;
            */

            $info = array();
            $info['@context'] = 'http://iiif.io/api/image/2/context.json';
            $info['@id'] = $imageUrl;
            $info['protocol'] = 'http://iiif.io/api/image';
            $info['width'] = $width;
            $info['height'] = $height;
            $info['sizes'] = $sizes;
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
            $info['profile'] = $profile;
            // Useless currently.
            // $info['service'] = $service;
        }

        // Else non-image file.
        else {
            $info = array();
            $info['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            );
            $fileUrl = absolute_url(array(
                    'id' => $file->id,
                ), 'universalviewer_media');
            $fileUrl = $this->view->uvForceBaseUrlIfRequired($fileUrl);
            $info['@id'] = $fileUrl;
            // See MediaController::contextAction()
            $info['protocol'] = 'http://wellcomelibrary.org/ld/ixif';
        }

        $info = (object) $info;
        return $info;
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function _iiifTileInfo($tileInfo)
    {
        $tile = array();

        $squaleFactors = array();
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
    }

    /**
     * Get an array of the width and height of the image file.
     *
     * @param File $file
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     *
     * @see UniversalViewer_View_Helper_IiifManifest::_getImageSize()
     * @see UniversalViewer_View_Helper_IiifInfo::_getImageSize()
     * @see UniversalViewer_ImageController::_getImageSize()
     * @todo Refactorize.
     */
    protected function _getImageSize(File $file, $imageType = 'original')
    {
        // Check if this is an image.
        if (empty($file) || strpos($file->mime_type, 'image/') === false) {
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
        $result = $this->_getWidthAndHeight($filepath);

        if (empty($result['width']) || empty($result['height'])) {
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
     * @see UniversalViewer_ImageController::_getWidthAndHeight()
     */
    protected function _getWidthAndHeight($filepath)
    {
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempname = tempnam(sys_get_temp_dir(), 'uv_');
            $result = file_put_contents($tempname, $filepath);
            if ($result !== false) {
                list($width, $height) = getimagesize($filepath);
                unlink($tempname);
                return array(
                    'width' => (int) $width,
                    'height' => (int) $height,
                );
            }
        } elseif (file_exists($filepath)) {
            list($width, $height) = getimagesize($filepath);
            return array(
                'width' => (int) $width,
                'height' => (int) $height,
            );
        }

        return array(
            'width' => null,
            'height' => null,
        );
    }
}
