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

            $tiles = array();
            if (plugin_is_active('OpenLayersZoom')
                    && $this->view->openLayersZoom()->isZoomed($file)
                ) {
                $tile = $this->_iiifTile($file);
                if ($tile) {
                    $tiles[] = $tile;
                }
            }

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
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
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

        if ($asJson) {
            return version_compare(phpversion(), '5.4.0', '<')
                ? json_encode($info)
                : json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        // Return as array
        return $info;
    }

    /**
     * Create an IIIF tile object for a place holder.
     *
     * @internal The method uses the Zoomify format of OpenLayersZoom.
     *
     * @param File $file
     * @return Standard object or null if no tile.
     * @see UniversalViewer_View_Helper_IiifManifest::_iiifTile()
     */
    protected function _iiifTile($file)
    {
        $tile = array();

        $tileProperties = $this->_getTileProperties($file);
        if (empty($tileProperties)) {
            return;
        }

        $squaleFactors = array();
        $maxSize = max($tileProperties['source']['width'], $tileProperties['source']['height']);
        $tileSize = $tileProperties['size'];
        $total = (integer) ceil($maxSize / $tileSize);
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
        $tile = (object) $tile;
        return $tile;
    }

    /**
     * Return the properties of a tiled file.
     *
     * @return array|null
     * @see UniversalViewer_ImageController::_getTileProperties()
     */
    protected function _getTileProperties($file)
    {
        $olz = new OpenLayersZoom_Creator();
        $dirpath = $olz->useIIPImageServer()
            ? $olz->getZDataWeb($file)
            : $olz->getZDataDir($file);
        $properties = simplexml_load_file($dirpath . '/ImageProperties.xml');
        if ($properties === false) {
            return;
        }
        $properties = $properties->attributes();
        $properties = reset($properties);

        // Standardize the properties.
        $result = array();
        $result['size'] = (integer) $properties['TILESIZE'];
        $result['total'] = (integer) $properties['NUMTILES'];
        $result['source']['width'] = (integer) $properties['WIDTH'];
        $result['source']['height'] = (integer) $properties['HEIGHT'];
        return $result;
    }

    /**
     * Get an array of the width and height of the image file.
     *
     * @internal The process uses the saved constraints. It they are changed but
     * the derivative haven't been rebuilt, the return will be wrong (but
     * generally without consequences for BookReader).
     *
     * @param File $file
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     * @see UniversalViewer_View_Helper_IiifManifest::_getImageSize()
     */
    protected function _getImageSize($file, $imageType = 'original')
    {
        static $sizeConstraints = array();

        if (!isset($sizeConstraints[$imageType])) {
            $sizeConstraints[$imageType] = get_option($imageType . '_constraint');
        }
        $sizeConstraint = $sizeConstraints[$imageType];

        // Check if this is an image.
        if (empty($file) || strpos($file->mime_type, 'image/') !== 0) {
            $width = null;
            $height = null;
        }

        // This is an image.
        else {
            $metadata = json_decode($file->metadata, true);
            if (empty($metadata['video']['resolution_x']) || empty($metadata['video']['resolution_y'])) {
                $msg = __('The image #%d ("%s") is not stored correctly.', $file->id, $file->original_filename);
                _log($msg, Zend_Log::NOTICE);

                if (isset($metadata['video']['resolution_x']) || isset($metadata['video']['resolution_y'])) {
                    throw new Exception($msg);
                }

                // Get the resolution directly.
                // The storage adapter should be checked for external storage.
                $storageAdapter = $file->getStorage()->getAdapter();
                $filepath = get_class($storageAdapter) == 'Omeka_Storage_Adapter_Filesystem'
                    ? FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath($imageType)
                    : $file->getWebPath($imageType);
                list($width, $height, $type, $attr) = getimagesize($filepath);
                if (empty($width) || empty($height)) {
                    throw new Exception($msg);
                }
            }

            // Calculate the size.
            else {
                $sourceWidth = $metadata['video']['resolution_x'];
                $sourceHeight = $metadata['video']['resolution_y'];

                // Use the original size when possible.
                if ($imageType == 'original') {
                    $width = $sourceWidth;
                    $height = $sourceHeight;
                }
                // This supposes that the option has not changed before.
                else {
                    // Source is landscape.
                    if ($sourceWidth > $sourceHeight) {
                        $width = $sizeConstraint;
                        $height = round($sourceHeight * $sizeConstraint / $sourceWidth);
                    }
                    // Source is portrait.
                    elseif ($sourceWidth < $sourceHeight) {
                        $width = round($sourceWidth * $sizeConstraint / $sourceHeight);
                        $height = $sizeConstraint;
                    }
                    // Source is square.
                    else {
                        $width = $sizeConstraint;
                        $height = $sizeConstraint;
                    }
                }
            }
        }

        return array(
            'width' => $width,
            'height' => $height,
        );
    }
}
