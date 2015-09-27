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
     * @param Record|integer|null $record
     * @param boolean $asJson Return manifest as object or as a json string.
     * @return Object|string|null. The object or the json string corresponding
     * to the manifest.
     */
    public function iiifInfo($record = null, $asJson = true)
    {
        if (is_null($record)) {
            $record = get_current_record('file');
        }
        elseif (is_numeric($record)) {
            $record = get_record_by_id('File', (integer) $record);
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
                $imagePath = $this->_getImagePath($file, $imageType);
                list($width, $height) = $this->_getWidthAndHeight($imagePath);
                $size = array();
                $size['width'] = $width;
                $size['height'] = $height;
                $size = (object) $size;
                $sizes[] = $size;
            }

            $imageType = 'original';
            $imagePath = $this->_getImagePath($file, $imageType);
            list($width, $height) = $this->_getWidthAndHeight($imagePath);
            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                    // TODO Useless?
                    'region' => 'full',
                    'size' => 'full',
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ), 'universalviewer_image');

            // TODO Use tiles of OpenLayersZoom if any (required for big files).
            $tile = array();
            $tile['width'] = 256;
            $tile['height'] = 256;
            $tile['scaleFactors'] = array(1, 2, 4, 8, 16, 32);
            $tile = (object) $tile;
            $tiles = array();
            $tiles[] = $tile;

            $profile = array();
            $profile[] = 'http://iiif.io/api/image/2/level2.json';
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
            // Unavailable currently.
            // $info['tiles'] = $tiles;
            $info['profile'] = $profile;
            // Useless currently.
            // $info['service'] = $service;
            $info = (object) $info;
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
            $info['@id'] = $fileUrl;
            // See MediaController::contextAction()
            $info['protocol'] = 'http://wellcomelibrary.org/ld/ixif';
            $info = (object) $info;
        }

        return $asJson
            ? json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $info;
    }

    /**
     * Get the path to an original or derivative file for an image.
     *
     * @param FIle $file
     * @param string $derivativeType
     * @return string|null Null if not exists.
     * @see UniversalViewer_View_Helper_IiifManifest::_getImagePath()
     */
    protected function _getImagePath($file, $derivativeType = 'original')
    {
        // Check if the file is an image.
        if (strpos($file->mime_type, 'image/') === 0) {
            // Don't use the webpath to avoid the transfer through server.
            $filepath = FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath($derivativeType);
            if (file_exists($filepath)) {
                return $filepath;
            }
        }
    }

    /**
     * Helper to get width and height of a file.
     *
     * @param string $filepath
     * @return array of width and height.
     * @see UniversalViewer_View_Helper_IiifManifest::_getWidthAndHeight()
     */
    protected function _getWidthAndHeight($filepath)
    {
        if (file_exists($filepath)) {
            list($width, $height, $type, $attr) = getimagesize($filepath);
            return array($width, $height);
        }
        return array(0, 0);
    }
}
