<?php
/**
 * Helper to get a IIIF manifest for a record.
 */
class UniversalViewer_View_Helper_IiifManifest extends Zend_View_Helper_Abstract
{
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
            return get_view()->iiifCollection($record, $asJson);
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
                'record' => 'items',
                'id' => $record->id,
            ), 'universalviewer_presentation_manifest');

        // The base url for some other ids.
        $baseUrl = dirname($url);

        $elementTexts = get_view()->allElementTexts($record, array(
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
                    'record' => 'collections',
                    'id' => $record->collection_id,
                ), 'universalviewer_presentation_manifest');
        }

        $canvases = array();

        $files = $record->getFiles();
        $imageNumber = 0;
        $nonImages = array();
        foreach ($files as $file) {
            // Rendering non-image files for download.
            if (strpos($file->mime_type, 'image/') !== 0) {
                $nonImages[] = $file;
                continue;
            }

            ++$imageNumber;
            $titleFile = metadata($file, array('Dublin Core', 'Title'));
            $canvasUrl = $baseUrl . '/canvas/p' . $imageNumber;

            $canvas = array();
            $canvas['@id'] = $canvasUrl;
            $canvas['@type'] = 'sc:Canvas';
            $canvas['label'] = $titleFile ?: $imageNumber;

            $imageType = 'thumbnail';
            $imagePath  = $this->_getImagePath($file, $imageType);
            list($width, $height) = $this->_getWidthAndHeight($imagePath);
            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                    'region' => 'full',
                    'size' => $width . ',' . $height,
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ), 'universalviewer_image');
            $canvas['thumbnail'] = $imageUrl;

            // TODO Manage png and other formats at original size.
            $imageType = 'original';
            $imagePath = $this->_getImagePath($file, $imageType);
            list($width, $height) = $this->_getWidthAndHeight($imagePath);
            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                    'region' => 'full',
                    'size' => 'full',
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ), 'universalviewer_image');
            $canvas['width'] = $width;
            $canvas['height'] = $height;

            // There is only one image (parallel is not managed).
            $imageResource = array();
            $imageResource['@id'] = $file->getWebPath($imageType);
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = $file->mime_type;
            $imageResource['width'] = $width;
            $imageResource['height'] = $height;

            $imageUrl = absolute_url(array(
                    'id' => $file->id,
                ), 'universalviewer_image');

            $imageResourceService = array();
            $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';
            $imageResourceService['@id'] = $imageUrl;
            $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
            $imageResourceService = (object) $imageResourceService;

            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;

            $image = array();
            $image['@id'] = $baseUrl . '/annotation/p' . sprintf('%04d', $imageNumber) . '-image';
            $image['@type'] = 'oa:Annotation';
            $image['motivation'] = "sc:painting";
            $image['resource'] = $imageResource;
            $image['on'] = $canvasUrl;
            $image = (object) $image;

            $images = array($image);
            $canvas['images'] = $images;

            // TODO Add other content.
            /*
            $otherContent = array();
            $otherContent = (object) $otherContent;

            $canvas['otherContent'] = $otherContent;
            */

            $canvas = (object) $canvas;
            $canvases[] = $canvas;
        }

        // Process non images.
        $rendering = array();
        $mediaSequences = array();
        $mediaSequencesElements = array();
        foreach ($nonImages as $file) {
            // When there are images.
            if ($imageNumber) {
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
            // Media sequences when there are no images (special content).
            else {
                if ($file->mime_type == 'application/pdf') {
                    $mediaSequenceElement = array();
                    $mediaSequenceElement['@id'] = $file->getWebPath('original');
                    $mediaSequenceElement['@type'] = 'foaf:Document';
                    $mediaSequenceElement['format'] = 'application/pdf';
                    // TODO If no file metadata, then item ones.
                    // TODO Currently, the main title and metadata are used,
                    // because in Omeka, a pdf is normally the only one file.
                    $mediaSequenceElement['label'] = $title;
                    $mediaSequenceElement['metadata'] = $metadata;
                    $mediaSequenceElement['thumbnail'] = $file->getWebPath('thumbnail');
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
        if ($imageNumber) {
            $sequence = array();
            $sequence['@id'] = $baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = 'Current Page Order';
            $sequence['viewingDirection'] = 'left-to-right';
            $sequence['viewingHint'] = $imageNumber > 1 ? 'paged' : 'non-paged';
            if ($rendering) {
                $sequence['rendering'] = $rendering;
            }
            $sequence['canvases'] = $canvases;
            $sequence = (object) $sequence;

            $sequences[] = $sequence;
        }

        // Sequences when there are no images (special content).
        elseif ($mediaSequencesElements) {
            $mediaSequence = array();
            $mediaSequence['@id'] = $baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
            $mediaSequences[] = $mediaSequence;

            // Add a sequence in case of the media cannot be read.
            $sequence = array();
            $sequence['@id'] = $baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = __('Unsupported extension. This manifest is being used as a wrapper for non-IIIF content (e.g., audio, video) and is unfortunately incompatible with IIIF viewers.');
            $sequence['compatibilityHint'] = 'displayIfContentUnsupported';

            $canvas = array();
            $canvas['@id'] = WEB_ROOT . '/iiif/ixif-message/canvas/c1';
            $canvas['@type'] = 'sc:Canvas';
            $canvas['label'] = __('Placeholder image');
            $placeHolder = 'images/placeholder.jpg';
            $thumbnailPlaceholder = src($placeHolder);
            list($widthPlaceholder, $heightPlaceholder) = $this->_getWidthAndHeight(physical_path_to($placeHolder));
            $canvas['thumbnail'] = $thumbnailPlaceholder;
            $canvas['width'] = $widthPlaceholder;
            $canvas['height'] = $heightPlaceholder;

            // There is only one image (parallel is not managed).
            $imageResource = array();
            $imageResource['@id'] = WEB_ROOT . '/iiif/ixif-message-0/res/placeholder';
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['width'] = $widthPlaceholder;
            $imageResource['height'] = $heightPlaceholder;
            $imageResource = (object) $imageResource;

            $image = array();
            $image['@id'] = WEB_ROOT . '/iiif/ixif-message/imageanno/placeholder';
            $image['@type'] = 'oa:Annotation';
            $image['motivation'] = "sc:painting";
            $image['resource'] = $imageResource;
            $image['on'] = WEB_ROOT . '/iiif/ixif-message/canvas/c1';
            $image = (object) $image;
            $images = array($image);

            $canvas['images'] = $images;
            $canvas = (object) $canvas;

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
            // TODO No files.
        }

        // Prepare manifest.
        $manifest = array();
        $manifest['@context'] = $imageNumber
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
        if ($licence) {
            $manifest['license'] = $licence;
        }
        if ($attribution) {
            $manifest['attribution'] = $attribution;
        }
        // $manifest['service'] = $service;
        // $manifest['seeAlso'] = $seeAlso;
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
     * Get the path to an original or derivative file for an image.
     *
     * @param FIle $file
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
