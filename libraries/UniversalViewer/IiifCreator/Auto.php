<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_IiifCreator_Auto extends UniversalViewer_AbstractIiifCreator
{
    // List of managed IIIF mime types.
    protected $_gdMimeTypes = array();

    /**
     * Check for the imagick extension at creation.
     *
     * @throws Exception
     */
    public function __construct()
    {
        // If available, use GD when source and destination formats are managed.
        if (extension_loaded('gd')) {
            $this->_gdMimeTypes = array(
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
                $this->_gdMimeTypes['image/gif'] = false;
            }
            if (empty($gdInfo['WebP Support'])) {
                $this->_gdMimeTypes['image/webp'] = false;
            }
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
        // If available, use GD when source and destination formats are managed.
        if (!empty($this->_gdMimeTypes[$args['source']['mime_type']])
                && !empty($this->_gdMimeTypes[$args['format']['feature']])
                && $args['rotation']['feature'] != 'rotationArbitrary'
            ) {
            $processor = new UniversalViewer_IiifCreator_GD();
            return $processor->transform($args);
        }
    }
}
