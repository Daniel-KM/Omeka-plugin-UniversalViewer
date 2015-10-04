<?php
/**
 * Helper to get a IIIF manifest for a record.
 */
class UniversalViewer_View_Helper_IiifManifest extends Zend_View_Helper_Abstract
{
    // The base url of the current document.
    protected $_baseUrl;

    /**
     * Get the IIIF manifest for the specified record.
     *
     * @param Record|integer|null $record
     * @param boolean $asJson Return manifest as object or as a json string.
     * @return Object|string|null. The object or the json string corresponding to the
     * manifest.
     */
    public function iiifManifest($record = null, $asJson = true)
    {
        if (is_null($record)) {
            $record = get_current_record('item');
        }
        elseif (is_numeric($record)) {
            $record = get_record_by_id('Item', (integer) $record);
        }

        if (empty($record)) {
            return null;
        }

        $recordClass = get_class($record);
        if ($recordClass == 'Item') {
            $result = $this->_buildManifestItem($record);
        }
        elseif ($recordClass == 'Collection') {
            return $this->view->iiifCollection($record, $asJson);
        }
        else {
            return null;
        }

        return $asJson
            ? json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $result;
    }

    /**
     * Get the IIIF manifest for the specified item.
     *
     * @todo Replace all data by standard classes.
     * @todo Replace web root by routes, even if main ones are only urn.
     *
     * @param Record $record Item
     * @return Object|null. The object corresponding to the manifest.
     */
    protected function _buildManifestItem($record)
    {
        // Prepare all values needed for manifest.
        $url = absolute_url(array(
                'recordtype' => 'items',
                'id' => $record->id,
            ), 'universalviewer_presentation_manifest');

        // The base url for some other ids.
        $this->_baseUrl = dirname($url);

        $elementTexts = $this->view->allElementTexts($record, array(
            'show_empty_elements' => false,
            // 'show_element_sets' => array('Dublin Core'),
            'return_type' => 'array',
        ));

        $metadata = array();
        foreach ($elementTexts as $elementSetName => $elements) {
            foreach ($elements as $elementName => $values) {
                $metadata[] = (object) array(
                    'label' => $elementName,
                    'value' => count($values) > 1
                       ? $values
                       :  reset($values),
                );
            }
        }

        $title = isset($elementTexts['Dublin Core']['Title'][0])
            ? $elementTexts['Dublin Core']['Title'][0]
            : __('[Untitled]');

        $description = metadata($record, 'citation', array('no_escape' => true));

        // Thumbnail of the whole work.
        // TODO Use index of the true representative file.
        $file = get_db()->getTable('File')->findWithImages($record->id, 1);
        $thumbnail = $this->_iiifThumbnail($file);

        $licence = get_option('universalviewer_licence');

        $attribution = get_option('universalviewer_attribution');

        // TODO To parameter or to extract from metadata.
        $service = '';
        /*
        $service = (object) array(
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        );
        */

        // TODO To parameter or to extract from metadata.
        $seeAlso = '';
        /*
        $seeAlso = (object) array(
            '@id' => 'http://www.example.org/library/catalog/book1.marc',
            'format' =>'application/marc',
        );
        */

        $within = '';
        if ($record->collection_id) {
            $within = absolute_url(array(
                    'recordtype' => 'collections',
                    'id' => $record->collection_id,
                ), 'universalviewer_presentation_manifest');
        }

        $canvases = array();

        // Get all images and non-images.
        $files = $record->getFiles();
        $images = array();
        $nonImages = array();
        foreach ($files as $file) {
            // Images files.
            // Internal: has_derivative is not only for images.
            if (strpos($file->mime_type, 'image/') === 0) {
                $images[] = $file;
            }
            // Non-images files.
            else {
                  $nonImages[] = $file;
            }
        }
        unset ($files);
        $totalImages = count($images);

        // Process images.
        $imageNumber = 0;
        foreach ($images as $file) {
            $canvas = $this->_iiifCanvasImage($file, ++$imageNumber);

            // TODO Add other content.
            /*
            $otherContent = array();
            $otherContent = (object) $otherContent;

            $canvas->otherContent = $otherContent;
            */

            $canvases[] = $canvas;
        }

        // Process non images.
        $rendering = array();
        $mediaSequences = array();
        $mediaSequencesElements = array();

        // When there are images, other files are added to download section.
        if ($totalImages > 0) {
            foreach ($nonImages as $file) {
                if ($file->mime_type == 'application/pdf') {
                    $render = array();
                    $render['@id'] = $file->getWebPath('original');
                    $render['format'] = $file->mime_type;
                    $render['label'] = __('Download as PDF');
                    $render = (object) $render;
                    $rendering[] = $render;
                }
                // TODO Add alto files and search.
                // TODO Add other content.
            }
        }

        // Else, check if non-images are managed (special content, as pdf).
        else {
            foreach ($nonImages as $file) {
                if ($file->mime_type == 'application/pdf') {
                    $mediaSequenceElement = array();
                    $mediaSequenceElement['@id'] = $file->getWebPath('original');
                    $mediaSequenceElement['@type'] = 'foaf:Document';
                    $mediaSequenceElement['format'] = 'application/pdf';
                    // TODO If no file metadata, then item ones.
                    // TODO Currently, the main title and metadata are used,
                    // because in Omeka, a pdf is normally the only $thumbnailone file.
                    $mediaSequenceElement['label'] = $title;
                    $mediaSequenceElement['metadata'] = $metadata;
                    $mseThumbnail = $file->getWebPath('thumbnail');
                    if ($mseThumbnail) {
                        $mediaSequenceElement['thumbnail'] = $mseThumbnail;
                    }
                    $mediaSequencesService = array();
                    $mseUrl = absolute_url(array(
                            'id' => $file->id,
                        ), 'universalviewer_media');
                    $mediaSequencesService['@id'] = $mseUrl;
                    // See MediaController::contextAction()
                    $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
                    $mediaSequencesService = (object) $mediaSequencesService;
                    $mediaSequenceElement['service'] = $mediaSequencesService;
                    $mediaSequenceElement = (object) $mediaSequenceElement;
                    $mediaSequencesElements[] = $mediaSequenceElement;

                    // TODO Add the file for download (no rendering).
                }
                // TODO Add other content.
            }
        }

        $sequences = array();

        // When there are images.
        if ($totalImages) {
            $sequence = array();
            $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = 'Current Page Order';
            $sequence['viewingDirection'] = 'left-to-right';
            $sequence['viewingHint'] = $totalImages > 1 ? 'paged' : 'non-paged';
            if ($rendering) {
                $sequence['rendering'] = $rendering;
            }
            $sequence['canvases'] = $canvases;
            $sequence = (object) $sequence;

            $sequences[] = $sequence;
        }

        // Sequences when there is no image (special content).
        elseif ($mediaSequencesElements) {
            $mediaSequence = array();
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
            $mediaSequences[] = $mediaSequence;

            // Add a sequence in case of the media cannot be read.
            $sequence = array();
            $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = __('Unsupported extension. This manifest is being used as a wrapper for non-IIIF content (e.g., audio, video) and is unfortunately incompatible with IIIF viewers.');
            $sequence['compatibilityHint'] = 'displayIfContentUnsupported';

            $canvas = $this->_iiifCanvasPlaceholder();

            $canvases = array();
            $canvases[] = $canvas;

            if ($rendering) {
                $sequence['rendering'] = $rendering;
            }
            $sequence['canvases'] = $canvases;
            $sequence = (object) $sequence;

            $sequences[] = $sequence;
        }

        // No managed content.
        else {
            // TODO No files. Add a warning?
        }

        // Prepare manifest.
        $manifest = array();
        $manifest['@context'] = $totalImages > 0
            ? 'http://iiif.io/api/presentation/2/context.json'
            : array(
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            );
        $manifest['@id'] = $url;
        $manifest['@type'] = 'sc:Manifest';
        $manifest['label'] = $title;
        if ($description) {
            $manifest['description'] = $description;
        }
        if ($thumbnail) {
            $manifest['thumbnail'] = $thumbnail;
        }
        if ($licence) {
            $manifest['license'] = $licence;
        }
        if ($attribution) {
            $manifest['attribution'] = $attribution;
        }
        if ($service) {
            $manifest['service'] = $service;
        }
        if ($seeAlso) {
            $manifest['seeAlso'] = $seeAlso;
        }
        if ($within) {
            $manifest['within'] = $within;
        }
        if ($metadata) {
            $manifest['metadata'] = $metadata;
        }
        if ($mediaSequences) {
            $manifest['mediaSequences'] = $mediaSequences;
        }
        if ($sequences) {
            $manifest['sequences'] = $sequences;
        }
        $manifest = (object) $manifest;

        return $manifest;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param File $file
     * @return Standard object|null
     */
    protected function _iiifThumbnail($file)
    {
        if (empty($file)) {
            return;
        }

        $imagePath  = $this->_getImagePath($file, 'thumbnail');
        if (empty($imagePath)) {
            return;
        }

        $thumbnail = array();

        list($width, $height) = $this->_getWidthAndHeight($imagePath);
        $imageUrl = absolute_url(array(
                'id' => $file->id,
                'region' => 'full',
                'size' => $width . ',' . $height,
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ), 'universalviewer_image_url');
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = array();
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = absolute_url(array(
                'id' => $file->id,
            ), 'universalviewer_image');
        $thumbnailService['@id'] = $thumbnailServiceUrl;
        $thumbnailService['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $thumbnailService = (object) $thumbnailService;

        $thumbnail['service'] = $thumbnailService;
        $thumbnail = (object) $thumbnail;

        return $thumbnail;
    }

    /**
     * Create an IIIF image object from an Omeka file.
     *
     * @param File $file
     * @param integer $index Used to set the standard name of the image.
     * @param string $canvasUrl Used to set the value for "on".
     * @param integer $width If not set, will be calculated.
     * @param integer $height If not set, will be calculated.
     * @return Standard object|null
     */
    protected function _iiifImage($file, $index, $canvasUrl, $width = null, $height = null)
    {
        if (empty($file)) {
            return;
        }

        if (empty($width) || empty($height)) {
            $imagePath = $this->_getImagePath($file, 'original');
            list($width, $height) = $this->_getWidthAndHeight($imagePath);
        }

        $image = array();
        $image['@id'] = $this->_baseUrl . '/annotation/p' . sprintf('%04d', $index) . '-image';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed currently).
        $imageResource = array();
        if (plugin_is_active('OpenLayersZoom')
                && $this->view->openLayersZoom()->isZoomed($file)
            ) {
            $imagePath  = $this->_getImagePath($file, 'fullsize');
            list($widthFullsize, $heightFullsize) = $this->_getWidthAndHeight($imagePath);
            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                    'region' => 'full',
                    'size' => $width . ',' . $height,
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ), 'universalviewer_image_url');
            $imageResource['@id'] = $imageUrl;
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = $file->mime_type;
            $imageResource['width'] = $widthFullsize;
            $imageResource['height'] = $heightFullsize;

            $imageResourceService = array();
            $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';

            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                ), 'universalviewer_image');
            $imageResourceService['@id'] = $imageUrl;
            $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
            $imageResourceService['width'] = $width;
            $imageResourceService['height'] = $height;

            $tile = $this->_iiifTile($file);
            if ($tile) {
                $tiles = array();
                $tiles[] = $tile;
                $imageResourceService['tiles'] = $tiles;
            }
            $imageResourceService = (object) $imageResourceService;

            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;
        }

        // Simple light image.
        else {
            $imageResource['@id'] = $file->getWebPath('original');
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = $file->mime_type;
            $imageResource['width'] = $width;
            $imageResource['height'] = $height;

            $imageResourceService = array();
            $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';

            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                ), 'universalviewer_image');
            $imageResourceService['@id'] = $imageUrl;
            $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
            $imageResourceService = (object) $imageResourceService;

            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;
        }

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        $image = (object) $image;

        return $image;
    }

    /**
     * Create an IIIF canvas object for an image.
     *
     * @param File $file
     * @param integer $index Used to set the standard name of the image.
     * @return Standard object|null
     */
    protected function _iiifCanvasImage($file, $index)
    {
        $canvas = array();

        $titleFile = metadata($file, array('Dublin Core', 'Title'));
        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $titleFile ?: '[' . $index .']';

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($file);

        // Size of canvas should be the double of small images (< 1200 px), but
        // only when more than image is used by a canvas.
        $imagePath = $this->_getImagePath($file, 'original');
        list($width, $height) = $this->_getWidthAndHeight($imagePath);
        $canvas['width'] = $width;
        $canvas['height'] = $height;

        $image = $this->_iiifImage($file, $index, $canvasUrl, $width, $height);

        $images = array();
        $images[] = $image;
        $canvas['images'] = $images;

        $canvas = (object) $canvas;

        return $canvas;
    }

    /**
     * Create an IIIF canvas object for a place holder.
     *
     * @return Standard object
     */
    protected function _iiifCanvasPlaceholder()
    {
        $canvas = array();
        $canvas['@id'] = WEB_ROOT . '/iiif/ixif-message/canvas/c1';
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = __('Placeholder image');

        $placeholder = 'images/placeholder.jpg';
        $canvas['thumbnail'] = src($placeholder);

        list($widthPlaceholder, $heightPlaceholder) = $this->_getWidthAndHeight(physical_path_to($placeholder));
        $canvas['width'] = $widthPlaceholder;
        $canvas['height'] = $heightPlaceholder;

        $image = array();
        $image['@id'] = WEB_ROOT . '/iiif/ixif-message/imageanno/placeholder';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed).
        $imageResource = array();
        $imageResource['@id'] = WEB_ROOT . '/iiif/ixif-message-0/res/placeholder';
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['width'] = $widthPlaceholder;
        $imageResource['height'] = $heightPlaceholder;
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = WEB_ROOT . '/iiif/ixif-message/canvas/c1';
        $image = (object) $image;
        $images = array($image);

        $canvas['images'] = $images;

        $canvas = (object) $canvas;

        return $canvas;
    }

    /**
     * Create an IIIF tile object for a place holder.
     *
     * @internal The method uses the Zoomify format of OpenLayersZoom.
     *
     * @param File $file
     * @return Standard object or null if no tile.
     * @see UniversalViewer_View_Helper_IiifInfo::_iiifTile()
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
     * Get the path to an original or derivative file for an image.
     *
     * @param File $file
     * @param string $derivativeType
     * @return string|null Null if not exists.
     * @see UniversalViewer_View_Helper_IiifInfo::_getImagePath()
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
     * @see UniversalViewer_View_Helper_IiifInfo::_getWidthAndHeight()
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
