<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_IiifCreator_GD extends UniversalViewer_AbstractIiifCreator
{
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

        $sourceGD = $this->_loadImageResource($args['source']['filepath']);
        if (empty($sourceGD)) {
            return;
        }

        // Get width and height if missing.
        if (empty($args['source']['width']) || empty($args['source']['height'])) {
            $args['source']['width'] = imagesx($sourceGD);
            $args['source']['height'] = imagesy($sourceGD);
        }

        switch ($args['region']['feature']) {
            case 'full':
                $sourceX = 0;
                $sourceY = 0;
                $sourceWidth = $args['source']['width'];
                $sourceHeight = $args['source']['height'];
                break;

            case 'regionByPx':
                if ($args['region']['x'] >= $args['source']['width']) {
                    imagedestroy($sourceGD);
                    return;
                }
                if ($args['region']['y'] >= $args['source']['height']) {
                    imagedestroy($sourceGD);
                    return;
                }
                $sourceX = $args['region']['x'];
                $sourceY = $args['region']['y'];
                $sourceWidth = ($sourceX + $args['region']['width']) <= $args['source']['width']
                    ? $args['region']['width']
                    : $args['source']['width'] - $sourceX;
                $sourceHeight = ($sourceY + $args['region']['height']) <= $args['source']['height']
                    ? $args['region']['height']
                    : $args['source']['height'] - $sourceY;
                break;

            case 'regionByPct':
                // Percent > 100 has already been checked.
                $sourceX = $args['source']['width'] * $args['region']['x'] / 100;
                $sourceY = $args['source']['height'] * $args['region']['y'] / 100;
                $sourceWidth = ($args['region']['x'] + $args['region']['width']) <= 100
                    ? $args['source']['width'] * $args['region']['width'] / 100
                    : $args['source']['width'] - $sourceX;
                $sourceHeight = ($args['region']['y'] + $args['region']['height']) <= 100
                    ? $args['source']['height'] * $args['region']['height'] / 100
                    : $args['source']['height'] - $sourceY;
                break;

            default:
                imagedestroy($sourceGD);
                return;
       }

        // Final generic check for region of the source.
        if ($sourceX < 0 || $sourceX >= $args['source']['width']
                || $sourceY < 0 || $sourceY >= $args['source']['height']
                || $sourceWidth <= 0 || $sourceWidth > $args['source']['width']
                || $sourceHeight <= 0 || $sourceHeight > $args['source']['height']
            ) {
            imagedestroy($sourceGD);
            return;
        }

        // The size is checked against the region, not the source.
        switch ($args['size']['feature']) {
            case 'full':
                $destinationWidth = $sourceWidth;
                $destinationHeight = $sourceHeight;
                break;

            case 'sizeByPct':
                $destinationWidth = $sourceWidth * $args['size']['percentage'] / 100;
                $destinationHeight = $sourceHeight * $args['size']['percentage'] / 100;
                break;

            case 'sizeByWhListed':
            case 'sizeByForcedWh':
                $destinationWidth = $args['size']['width'];
                $destinationHeight = $args['size']['height'];
                break;

            case 'sizeByW':
                $destinationWidth = $args['size']['width'];
                $destinationHeight = $destinationWidth * $sourceHeight / $sourceWidth;
                break;

            case 'sizeByH':
                $destinationHeight = $args['size']['height'];
                $destinationWidth = $destinationHeight * $sourceWidth / $sourceHeight;
                break;

            case 'sizeByWh':
                // Check sizes before testing.
                if ($args['size']['width'] > $sourceWidth) {
                    $args['size']['width'] = $sourceWidth;
                }
                if ($args['size']['height'] > $sourceHeight) {
                    $args['size']['height'] = $sourceHeight;
                }
                // Check ratio to find best fit.
                $destinationHeight = $args['size']['width'] * $sourceHeight / $sourceWidth;
                if ($destinationHeight > $args['size']['height']) {
                    $destinationWidth = $args['size']['height'] * $sourceWidth / $sourceHeight;
                    $destinationHeight = $args['size']['height'];
                }
                // Ratio of height is better, so keep it.
                else {
                    $destinationWidth = $args['size']['width'];
                }
                break;

            default:
                imagedestroy($sourceGD);
                return;
        }

        // Final generic checks for size.
        if (empty($destinationWidth) || empty($destinationHeight)) {
            imagedestroy($sourceGD);
            return;
        }

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

        $supporteds = array(
            'image/jpeg' => true,
            'image/png' => true,
            'image/tiff' => false,
            'image/gif' => true,
            'application/pdf' => false,
            'image/jp2' => false,
            'image/webp' => true,
        );
        $gdInfo = gd_info();
        if (empty($gdInfo['GIF Read Support']) || empty($gdInfo['GIF Create Support'])) {
            $supporteds['image/gif'] = false;
        }
        if (empty($gdInfo['WebP Support'])) {
            $supporteds['image/webp'] = false;
        }

        if (empty($supporteds[$args['format']['feature']])) {
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
