<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_ImageServer_Imagick extends UniversalViewer_AbstractImageServer
{
    // List of managed IIIF media types.
    protected $_supportedFormats = array(
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/tiff' => 'TIFF',
        'image/gif' => 'GIF',
        'application/pdf' => 'PDF',
        'image/jp2' => 'JP2',
        'image/webp' => 'WEBP',
    );

    /**
     * Check for the php extension.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!extension_loaded('imagick')) {
            throw new Exception(__('The transformation of images via ImageMagick requires the PHP extension "imagick".'));
        }

        $this->_supportedFormats = array_intersect($this->_supportedFormats, Imagick::queryFormats());
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

        if (!$this->checkMediaType($args['source']['media_type'])
                || !$this->checkMediaType($args['format']['feature'])
            ) {
            return;
        }

        $imagick = $this->_loadImageResource($args['source']['filepath']);
        if (empty($imagick)) {
            return;
        }

        // Get width and height if missing.
        if (empty($args['source']['width']) || empty($args['source']['height'])) {
            $args['source']['width'] = $imagick->getImageWidth();
            $args['source']['height'] = $imagick->getImageHeight();
        }

        // Region + Size.
        $extraction = $this->_prepareExtraction();
        if (!$extraction) {
            $imagick->clear();
            return;
        }

        list(
            $sourceX,
            $sourceY,
            $sourceWidth,
            $sourceHeight,
            $destinationWidth,
            $destinationHeight) = $extraction;

        // The background is normally useless, but it's costless.
        $imagick->setBackgroundColor('black');
        $imagick->setImageBackgroundColor('black');
        $imagick->setImagePage($sourceWidth, $sourceHeight, 0, 0);
        $imagick->cropImage($sourceWidth, $sourceHeight, $sourceX, $sourceY);
        $imagick->thumbnailImage($destinationWidth, $destinationHeight);
        $imagick->setImagePage($destinationWidth, $destinationHeight, 0, 0);

        // Mirror.
        switch ($args['mirror']['feature']) {
            case 'mirror':
            case 'horizontal':
                $imagick->flopImage();
                break;

            case 'vertical':
                $imagick->flipImage();
                break;

            case 'both':
                $imagick->flopImage();
                $imagick->flipImage();
                break;

            case 'default':
                // Nothing to do.
                break;

            default:
                $imagick->clear();
                return;
        }

        // Rotation.
        switch ($args['rotation']['feature']) {
            case 'noRotation':
                break;

            case 'rotationBy90s':
            case 'rotationArbitrary':
                $imagick->rotateimage('black', $args['rotation']['degrees']);
                break;

            default:
                $imagick->clear();
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
                $imagick->transformImageColorspace(Imagick::COLORSPACE_GRAY);
                break;

            case 'bitonal':
                $imagick->thresholdImage(0.77 * $imagick->getQuantum());
                break;

            default:
                $imagick->clear();
                return;
        }

        // Save resulted resource into the specified format.
        // TODO Use a true name to allow cache, or is it managed somewhere else?
        $destination = tempnam(sys_get_temp_dir(), 'uv_');

        $imagick->setImageFormat($this->_supportedFormats[$args['format']['feature']]);
        $result = $imagick->writeImage($this->_supportedFormats[$args['format']['feature']] . ':' . $destination);

        $imagick->clear();

        return $result ? $destination : null;
    }

    /**
     * Load an image from anywhere.
     *
     * @param string $source Path of the managed image file
     * @return Imagick|false
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
                $imagick = new Imagick($source);
            }
            // When the storage is external, the file should be fetched before.
            else {
                $tempPath = tempnam(sys_get_temp_dir(), 'uv_');
                $result = copy($source, $tempPath);
                if (!$result) {
                    return false;
                }
                $imagick = new Imagick($tempPath);
                unlink($tempPath);
            }
        } catch (Exception $e) {
            _log(__("Imagick failed to open the file \"%s\". Details:\n%s", $source, $e->getMessage()), Zend_Log::ERR);
            return false;
        }

        return $imagick;
    }
}
