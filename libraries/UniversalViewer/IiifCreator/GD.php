<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_IiifCreator_GD extends UniversalViewer_AbstractIiifCreator
{
    // List of managed IIIF media types.
    protected $_supportedFormats = array(
        'image/jpeg' => true,
        'image/png' => true,
        'image/tiff' => false,
        'image/gif' => true,
        'application/pdf' => false,
        'image/jp2' => false,
        'image/webp' => true,
    );

    /**
     * Check for the php extension.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new Exception(__('The transformation of images via GD requires the PHP extension "gd".'));
        }

        $gdInfo = gd_info();
        if (empty($gdInfo['GIF Read Support']) || empty($gdInfo['GIF Create Support'])) {
            $this->_supportedFormats['image/gif'] = false;
        }
        if (empty($gdInfo['WebP Support'])) {
            $this->_supportedFormats['image/webp'] = false;
        }
    }

    /**
     * Transform an image into another image according to params.
     *
     * @internal The args are currently already checked in the controller.
     *
     * @param array $args List of arguments for the transformation.
     * @return string|null The filepath to the temp image if success.
     */
    public function transform(array $args = array())
    {
        if (empty($args)) {
            return;
        }

        $this->_args = $args;
        $args = &$this->_args;

        if (!$this->checkMediaType($args['source']['mime_type'])
                || !$this->checkMediaType($args['format']['feature'])
            ) {
            return;
        }

        $sourceGD = $this->_loadImageResource($args['source']['filepath']);
        if (empty($sourceGD)) {
            return;
        }

        // Get width and height if missing.
        if (empty($args['source']['width']) || empty($args['source']['height'])) {
            $args['source']['width'] = imagesx($sourceGD);
            $args['source']['height'] = imagesy($sourceGD);
        }

        $extraction = $this->_prepareExtraction();
        if (!$extraction) {
            imagedestroy($sourceGD);
            return;
        }

        list(
            $sourceX,
            $sourceY,
            $sourceWidth,
            $sourceHeight,
            $destinationWidth,
            $destinationHeight) = $extraction;

        $destinationGD = imagecreatetruecolor($destinationWidth, $destinationHeight);
        // The background is normally useless, but it's costless.
        $black = imagecolorallocate($destinationGD, 0, 0, 0);
        imagefill($destinationGD, 0, 0, $black);
        $result = imagecopyresampled($destinationGD, $sourceGD, 0, 0, $sourceX, $sourceY, $destinationWidth, $destinationHeight, $sourceWidth, $sourceHeight);

        if ($result === false) {
            imagedestroy($sourceGD);
            imagedestroy($destinationGD);
            return;
        }

        // Rotation.
        switch ($args['rotation']['feature']) {
            case 'noRotation':
                break;

            case 'rotationBy90s':
                switch ($args['rotation']['degrees']) {
                    case 90:
                    case 270:
                        $i = $destinationWidth;
                        $destinationWidth = $destinationHeight;
                        $destinationHeight = $i;
                        // GD uses anticlockwise rotation.
                        $degrees = $args['rotation']['degrees'] == 90 ? 270 : 90;
                        // Continues below.
                    case 180:
                        $degrees = isset($degrees) ? $degrees : 180;

                        // imagerotate() returns a resource, not a boolean.
                        $destinationGDrotated = imagerotate($destinationGD, $degrees, 0);
                        imagedestroy($destinationGD);
                        if ($destinationGDrotated === false) {
                            imagedestroy($sourceGD);
                            return;
                        }
                        $destinationGD = &$destinationGDrotated;
                        break;
                }
                break;

            case 'rotationArbitrary':
                // Currently not managed.

            default:
                imagedestroy($sourceGD);
                imagedestroy($destinationGD);
                return;
        }

        // Quality.
        switch ($args['quality']['feature']) {
            case 'default':
                break;

            case 'color':
                // No change, because only one image is managed.
                break;

            case 'gray':
                $result = imagefilter($destinationGD, IMG_FILTER_GRAYSCALE);
                if ($result === false) {
                    imagedestroy($sourceGD);
                    imagedestroy($destinationGD);
                    return;
                }
                break;

            case 'bitonal':
                $result = imagefilter($destinationGD, IMG_FILTER_GRAYSCALE);
                $result = imagefilter($destinationGD, IMG_FILTER_CONTRAST, -65535);
                if ($result === false) {
                    imagedestroy($sourceGD);
                    imagedestroy($destinationGD);
                    return;
                }
                break;

            default:
                imagedestroy($sourceGD);
                imagedestroy($destinationGD);
                return;
        }

        // Save resulted resource into the specified format.
        // TODO Use a true name to allow cache, or is it managed somewhere else?
        $destination = tempnam(sys_get_temp_dir(), 'uv_');

        switch ($args['format']['feature']) {
            case 'image/jpeg':
                $result = imagejpeg($destinationGD, $destination);
                break;
            case 'image/png':
                $result = imagepng($destinationGD, $destination);
                break;
            case 'image/gif':
                $result = imagegif($destinationGD, $destination);
                break;
            case 'image/webp':
                $result = imagewebp($destinationGD, $destination);
                break;
        }

        imagedestroy($sourceGD);
        imagedestroy($destinationGD);

        return $result ? $destination : null;
    }

    /**
     * GD uses multiple functions to load an image, so this one manages all.
     *
     * @param string $source Path of the managed image file
     * @return false|GD image ressource
     */
    protected function _loadImageResource($source)
    {
        if (empty($source)) {
            return false;
        }

        try {
            // The source can be a local file or an external one.
            $storageAdapter = Zend_Registry::get('storage')->getAdapter();
            if (get_class($storageAdapter) == 'Omeka_Storage_Adapter_Filesystem') {
                if (!is_readable($source)) {
                    return false;
                }
                $image = imagecreatefromstring(file_get_contents($source));
            }
            // When the storage is external, the file should be fetched before.
            else {
                $tempPath = tempnam(sys_get_temp_dir(), 'uv_');
                $result = copy($source, $tempPath);
                if (!$result) {
                    return false;
                }
                $image = imagecreatefromstring(file_get_contents($tempPath));
                unlink($tempPath);
            }
        } catch (Exception $e) {
            _log(__("GD failed to open the file \"%s\". Details:\n%s", $source, $e->getMessage()), Zend_Log::ERR);
            return false;
        }

        return $image;
    }
}
