<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_ImageServer_Auto extends UniversalViewer_AbstractImageServer
{
    protected $_gdMediaTypes = array();
    protected $_imagickMediaTypes = array();

    /**
     * Check for the imagick extension at creation.
     *
     * @throws Exception
     */
    public function __construct()
    {
        // For simplicity, the check is prepared here, without load of classes.

        // If available, use GD when source and destination formats are managed.
        if (extension_loaded('gd')) {
            $this->_gdMediaTypes = array(
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
                $this->_gdMediaTypes['image/gif'] = false;
            }
            if (empty($gdInfo['WebP Support'])) {
                $this->_gdMediaTypes['image/webp'] = false;
            }
        }

        if (extension_loaded('imagick')) {
            $iiifMediaTypes = array(
                'image/jpeg' => 'JPG',
                'image/png' => 'PNG',
                'image/tiff' => 'TIFF',
                'image/gif' => 'GIF',
                'application/pdf' => 'PDF',
                'image/jp2' => 'JP2',
                'image/webp' => 'WEBP',
            );
            $this->_imagickMediaTypes = array_intersect($iiifMediaTypes, Imagick::queryFormats());
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
        // GD seems to be 15% speeder, so it is used first if available.
        if (!empty($this->_gdMediaTypes[$args['source']['media_type']])
                && !empty($this->_gdMediaTypes[$args['format']['feature']])
                // The arbitrary rotation is not managed currently.
                && $args['rotation']['feature'] != 'rotationArbitrary'
            ) {
            $processor = new UniversalViewer_ImageServer_GD();
            return $processor->transform($args);
        }

        // Else use the extension Imagick, that manages more formats.
        if (!empty($this->_imagickMediaTypes[$args['source']['media_type']])
                && !empty($this->_imagickMediaTypes[$args['format']['feature']])
            ) {
            $processor = new UniversalViewer_ImageServer_Imagick();
            return $processor->transform($args);
        }

        // Else use the command line convert, if available.
        $processor = new UniversalViewer_ImageServer_ImageMagick();
        return $processor->transform($args);
    }
}
