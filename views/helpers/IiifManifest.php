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
     * @param Omeka_Record_AbstractRecord $record
     * @return object|null Standard object.
     */
    public function iiifManifest(Omeka_Record_AbstractRecord $record)
    {
        $recordClass = get_class($record);
        if ($recordClass == 'Item') {
            return $this->_buildManifestItem($record);
        }

        if ($recordClass == 'Collection') {
            return $this->view->iiifCollection($record);
        }
    }

    /**
     * Get the IIIF manifest for the specified item.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values and a standard json() without ld.
     * @todo Replace all data by standard classes.
     * @todo Replace web root by routes, even if main ones are only urn.
     *
     * @param Item $item
     * @return object|null. The object corresponding to the manifest.
     */
    protected function _buildManifestItem(Item $item)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = array(
            '@context' => '',
            '@id' => '',
            '@type' => 'sc:Manifest',
            'label' => '',
            'description' => '',
            'thumbnail' => '',
            'license' => '',
            'attribution' => '',
            // A logo to add at the end of the information panel.
            'logo' => '',
            'service' => '',
            // For example the web page of the item.
            'related' => '',
            // Other formats of the same data.
            'seeAlso' => '',
            'within' => '',
            'metadata' => array(),
            'mediaSequences' => array(),
            'sequences' => array(),
        );

        $url = absolute_url(array(
            'id' => $item->id,
        ), 'universalviewer_presentation_item');
        $url = $this->view->uvForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        // The base url for some other ids.
        $this->_baseUrl = dirname($url);

        $metadata = $this->_iiifMetadata($item);
        $manifest['metadata'] = $metadata;

        $label = version_compare(OMEKA_VERSION, '2.5', '<')
            ? (metadata($item, array('Dublin Core', 'Title')) ?: __('[Untitled]'))
            : metadata($item, 'display_title');
        $manifest['label'] = $label;

        $description = '';
        $descriptionElement = get_option('universalviewer_manifest_description_element');
        if ($descriptionElement) {
            $description = metadata($item, json_decode($descriptionElement, true));
        }
        if (empty($description) && get_option('universalviewer_manifest_description_default')) {
            // An issue may occur with a contributed item (not fixed upstream yet).
            try {
                $description = metadata($item, 'citation', array('no_escape' => true));
            } catch (Exception $e) {
                $description = metadata($item, 'citation', array('no_escape' => true, 'no_filter' => true));
            }
        }
        $manifest['description'] = $description;

        $licenseElement = get_option('universalviewer_manifest_license_element');
        if ($licenseElement) {
            $license = metadata($item, json_decode($licenseElement, true));
        }
        if (empty($license)) {
            $license = get_option('universalviewer_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionElement = get_option('universalviewer_manifest_attribution_element');
        if ($attributionElement) {
            $attribution = metadata($item, json_decode($attributionElement, true));
        }
        if (empty($attribution)) {
            $attribution = get_option('universalviewer_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = get_option('universalviewer_manifest_logo_default');

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        // Anyway, there is no true service for Omeka Classic.
        $metadata['service'] = array(
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        );
        */

        $manifest['related'] = array(
            '@id' => $this->view->uvForceBaseUrlIfRequired(record_url($item, 'show', true)),
            'format' => 'text/html',
        );

        /*
        // There is no true service for Omeka Classic, and it’s disabled by default.
        $manifest['seeAlso'] = array(
        );
        */

        if ($item->collection_id) {
            $within = absolute_url(array(
                'id' => $item->collection_id,
            ), 'universalviewer_presentation_collection');
            $within = $this->view->uvForceBaseUrlIfRequired($within);
            $metadata['within'] = $within;
        }

        $canvases = array();

        // Get all images and non-images and detect json files (for 3D model).
        $files = $item->getFiles();
        $images = array();
        $nonImages = array();
        $jsonFiles = array();
        foreach ($files as $file) {
            $mediaType = $file->mime_type;
            // Images files.
            // Internal: has_derivative is not only for images.
            if ($mediaType && strpos($mediaType, 'image/') === 0) {
                $images[] = $file;
            }
            // Non-images files.
            else {
                $nonImages[] = $file;
                if ($mediaType == 'application/json') {
                    $jsonFiles[] = $file;
                }
                // Check if this is a json file for old Omeka or old imports.
                elseif ($mediaType == 'text/plain') {
                    switch (strtolower($file->getExtension())) {
                        case 'json':
                            $jsonFiles[] = $file;
                            break;
                    }
                }
            }
        }
        unset($files);
        $totalImages = count($images);
        $totalJsonFiles = count($jsonFiles);

        // Prepare an exception.
        // TODO Check if this is really a 3D model for three.js (see https://threejs.org).
        $isThreejs = $totalJsonFiles == 1;

        // Process images, except if they belong to a 3D model.
        if (!$isThreejs) {
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
        }

        // Process non images.
        $rendering = array();
        $mediaSequences = array();
        $mediaSequencesElements = array();

        // TODO Manage the case where there is a video, a pdf etc, and the image
        // is only a quick view. So a main file should be set, that is not the
        // representative file.

        // When there are images or one json file, other files may be added to
        // download section.
        if ($totalImages || $isThreejs) {
            foreach ($nonImages as $file) {
                $mediaType = $file->mime_type;
                switch ($mediaType) {
                    case '':
                        break;

                    case 'application/pdf':
                        $render = array();
                        $render['@id'] = $file->getWebPath('original');
                        $render['format'] = $mediaType;
                        $render['label'] = __('Download as PDF');
                        $render = (object) $render;
                        $rendering[] = $render;
                        break;
                }
                // TODO Add alto files and search.
                // TODO Add other content.
            }

            // Prepare the media sequence for threejs.
            if ($isThreejs) {
                $mediaSequenceElement = $this->_iiifMediaSequenceThreejs(
                    $file,
                    array('label' => $label, 'metadata' => $metadata, 'files' => $images)
                    );
                $mediaSequencesElements[] = $mediaSequenceElement;
            }
        }

        // Else, check if non-images are managed (special content, as pdf).
        else {
            foreach ($nonImages as $file) {
                $fileElementTexts = $this->view->allElementTexts($file, array(
                    'show_empty_elements' => false,
                    'show_element_sets' => array('Dublin Core'),
                    'return_type' => 'array',
                ));
                $fileMetadata = array();
                foreach ($fileElementTexts as $elements) {
                    foreach ($elements as $elementName => $values) {
                        $fileMetadata[] = (object) array(
                            'label' => "$elementName (file)",
                            'value' => count($values) > 1
                                ? $values
                                : reset($values),
                        );
                    }
                }

                $values = array(
                    'label' => version_compare(OMEKA_VERSION, '2.5', '<')
                        ? (metadata($file, array('Dublin Core', 'Title')) ?: __('[Untitled]'))
                        : metadata($file, 'display_title'),
                    'metadata' => $fileMetadata,
                );

                $mediaType = $file->mime_type;
                switch ($mediaType) {
                    case '':
                        break;

                    case 'application/pdf':
                        $mediaSequenceElement = $this->_iiifMediaSequencePdf($file, $values);
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // TODO Add the file for download (no rendering)? The
                        // file is already available for download in the pdf viewer.
                        break;

                    case strpos($mediaType, 'audio/') === 0:
                    // case 'audio/ogg':
                    // case 'audio/mp3':
                        $mediaSequenceElement = $this->_iiifMediaSequenceAudio($file, $values);
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // Rendering files are automatically added for download.
                        break;

                    // TODO Check/support the media type "application/octet-stream".
                    // case 'application/octet-stream':
                    case strpos($mediaType, 'video/') === 0:
                    // case 'video/webm':
                        $mediaSequenceElement = $this->_iiifMediaSequenceVideo($file, $values);
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // Rendering files are automatically added for download.
                        break;

                    // TODO Add other content.
                    default:
                }

                // TODO Add other files as resources of the current element.
            }
        }

        // Thumbnail of the whole work.
        $manifest['thumbnail'] = $this->_mainThumbnail($item, $isThreejs);

        // Prepare sequences.
        $sequences = array();

        // Manage the exception: the media sequence with threejs 3D model.
        if ($isThreejs && $mediaSequencesElements) {
            $mediaSequence = array();
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
            $mediaSequences[] = $mediaSequence;
        }
        // When there are images.
        elseif ($totalImages) {
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
            $sequence = $this->_iiifSequenceUnsupported($rendering);
            $sequences[] = $sequence;
        }

        // No supported content.
        else {
            // Set a default render if needed.
            /*
            if (empty($rendering)) {
                $placeholder = 'images/placeholder-default.jpg';
                $render = array();
                $render['@id'] = src($placeholder);
                $render['format'] = 'image/jpeg';
                $render['label'] = __('Unsupported content.');
                $render = (object) $render;
                $rendering[] = $render;
            }
            */

            $sequence = $this->_iiifSequenceUnsupported($rendering);
            $sequences[] = $sequence;
        }

        if ($mediaSequences) {
            $manifest['mediaSequences'] = $mediaSequences;
        }

        if ($sequences) {
            $manifest['sequences'] = $sequences;
        }

        if ($isThreejs) {
            $manifest['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                'http://files.universalviewer.io/ld/ixif/0/context.json',
            );
        }
        // For images, the normalized context.
        elseif ($totalImages) {
            $manifest['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        }
        // For other non standard iiif files.
        else {
            $manifest['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            );
        }

        $manifest = apply_filters('uv_manifest', $manifest, array(
            'record' => $item,
            'type' => 'item',
        ));

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        $manifest = (object) $manifest;
        return $manifest;
    }

    /**
     * Prepare the metadata of a resource.
     *
     * @param Omeka_Record_AbstractRecord $record
     * @return array
     */
    protected function _iiifMetadata(Omeka_Record_AbstractRecord $record)
    {
        // Prepare the metadata of the record.
        // TODO Manage filter and escape or use $item->getAllElementTexts()?
        $elementTexts = $this->view->allElementTexts($record, array(
            'show_empty_elements' => false,
            // 'show_element_sets' => array('Dublin Core'),
            'return_type' => 'array',
        ));

        $metadata = array();
        foreach ($elementTexts as $elements) {
            foreach ($elements as $elementName => $values) {
                $metadata[] = (object) array(
                    'label' => __($elementName),
                    'value' => count($values) > 1
                        ? $values
                        : reset($values),
                );
            }
        }
        return $metadata;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param File $file
     * @return object|null Standard object.
     */
    protected function _iiifThumbnail(File $file = null)
    {
        $imageSize = $this->view->imageSize($file, 'thumbnail');
        list($width, $height) = array_values($imageSize);
        if (empty($width) || empty($height)) {
            return;
        }

        $thumbnail = array();

        $imageUrl = absolute_url(array(
                'id' => $file->id,
                'region' => 'full',
                'size' => $width . ',' . $height,
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ), 'universalviewer_image_url');
        $imageUrl = $this->view->uvForceBaseUrlIfRequired($imageUrl);
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = array();
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = absolute_url(array(
                'id' => $file->id,
            ), 'universalviewer_image');
        $thumbnailServiceUrl = $this->view->uvForceBaseUrlIfRequired($thumbnailServiceUrl);
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
     * @todo Use the IiifInfo (short version of info.json of the image).
     *
     * @param File $file
     * @param int $index Used to set the standard name of the image.
     * @param string $canvasUrl Used to set the value for "on".
     * @param int $width If not set, will be calculated.
     * @param int $height If not set, will be calculated.
     * @return object|null Standard object.
     */
    protected function _iiifImage(File $file, $index, $canvasUrl, $width = null, $height = null)
    {
        if (empty($file)) {
            return;
        }

        if (empty($width) || empty($height)) {
            $imageSize = $this->view->imageSize($file, 'original');
            list($width, $height) = $imageSize ? array_values($imageSize) : array(null, null);
        }

        $image = array();
        $image['@id'] = $this->_baseUrl . '/annotation/p' . sprintf('%04d', $index) . '-image';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed currently).
        $imageResource = array();

        // According to https://iiif.io/api/presentation/2.1/#image-resources,
        // "the URL may be the complete URL to a particular size of the image
        // content", so the large one here, and it's always a jpeg.
        $imageSize = $this->view->imageSize($file, 'fullsize');
        list($widthFullsize, $heightFullsize) = array_values($imageSize);
        $imageUrl = absolute_url(array(
            'id' => $file->id,
            'region' => 'full',
            'size' => $widthFullsize . ',' . $heightFullsize,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ), 'universalviewer_image_url');
        $imageUrl = $this->view->uvForceBaseUrlIfRequired($imageUrl);

        $imageResource['@id'] = $imageUrl;
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['format'] = 'image/jpeg';
        $imageResource['width'] = $width;
        $imageResource['height'] = $height;

        $imageUrlService = absolute_url(array(
            'id' => $file->id,
        ), 'universalviewer_image');
        $imageUrlService = $this->view->uvForceBaseUrlIfRequired($imageUrlService);
        $imageResourceService = array();
        $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $imageResourceService['@id'] = $imageUrlService;
        $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';

        $helper = new UniversalViewer_Controller_Action_Helper_TileInfo();
        $tilingData = $helper->tileInfo($file);
        $iiifTileInfo = $tilingData ? $this->_iiifTileInfo($tilingData) : null;
        if ($iiifTileInfo) {
            $tiles = array();
            $tiles[] = $iiifTileInfo;
            $imageResourceService['tiles'] = $tiles;
            $imageResourceService['width'] = $width;
            $imageResourceService['height'] = $height;
        }

        $imageResourceService = (object) $imageResourceService;
        $imageResource['service'] = $imageResourceService;
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        $image = (object) $image;

        return $image;
    }

    /**
     * Create an IIIF canvas object for an image.
     *
     * @param File $file
     * @param int $index Used to set the standard name of the image.
     * @return object|null Standard object.
     */
    protected function _iiifCanvasImage(File $file, $index)
    {
        $canvas = array();

        $titleFile = version_compare(OMEKA_VERSION, '2.5', '<')
            ? (metadata($file, array('Dublin Core', 'Title')) ?: __('[Untitled]'))
            : metadata($file, 'display_title');
        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $titleFile ?: '[' . $index .']';

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($file);

        // Size of canvas should be the double of small images (< 1200 px), but
        // only when more than one image is used by a canvas.
        list($width, $height) = array_values($this->view->imageSize($file, 'original'));
        $canvas['width'] = $width;
        $canvas['height'] = $height;

        $image = $this->_iiifImage($file, $index, $canvasUrl, $width, $height);

        $images = array();
        $images[] = $image;
        $canvas['images'] = $images;

        $mediaMetadata = get_option('universalviewer_manifest_media_metadata');
        if ($mediaMetadata) {
            $metadata = $this->_iiifMetadata($file);
            if ($metadata) {
                $canvas['metadata'] = $metadata;
            }
        }

        $canvas = (object) $canvas;

        return $canvas;
    }

    /**
     * Create an IIIF canvas object for a place holder.
     *
     * @return object Standard object.
     */
    protected function _iiifCanvasPlaceholder()
    {
        $canvas = array();
        $canvas['@id'] = WEB_ROOT . '/iiif/ixif-message/canvas/c1';
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = __('Placeholder image');

        $placeholder = 'images/thumbnails/placeholder-image.png';
        $canvas['thumbnail'] = src($placeholder);

        $imageSize = $this->_getWidthAndHeight(physical_path_to($placeholder));
        $canvas['width'] = $imageSize['width'];
        $canvas['height'] = $imageSize['height'];

        $image = array();
        $image['@id'] = WEB_ROOT . '/iiif/ixif-message/imageanno/placeholder';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed).
        $imageResource = array();
        $imageResource['@id'] = WEB_ROOT . '/iiif/ixif-message-0/res/placeholder';
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['width'] = $imageSize['width'];
        $imageResource['height'] = $imageSize['height'];
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
     * Create an IIIF media sequence object for a pdf.
     *
     * @param File $file
     * @param array $values
     * @return object|null Standard object.
     */
    protected function _iiifMediaSequencePdf(File $file, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $file->getWebPath('original');
        $mediaSequenceElement['@type'] = 'foaf:Document';
        $mediaSequenceElement['format'] = $file->mime_type;
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, a pdf is normally the only one
        // file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        if ($file->hasThumbnail()) {
            $thumbnailUrl = $file->getWebPath('thumbnail');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        $mediaSequencesService = array();
        $mseUrl = absolute_url(array(
            'id' => $file->id,
        ), 'universalviewer_media');
        $mseUrl = $this->view->uvForceBaseUrlIfRequired($mseUrl);
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        $mediaSequenceElement = (object) $mediaSequenceElement;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for an audio.
     *
     * @param File $file
     * @param array $values
     * @return object|null Standard object.
     */
    protected function _iiifMediaSequenceAudio(File $file, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $file->getWebPath('original') . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:Sound';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $file->mime_type;
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, such a file is normally the only
        // one file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        if ($file->hasThumbnail()) {
            $thumbnailUrl = $file->getWebPath('thumbnail');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        // A place holder is recommended for media.
        if (empty($mediaSequenceElement['thumbnail'])) {
            // $placeholder = 'images/placeholder-audio.jpg';
            // $mediaSequenceElement['thumbnail'] = src($placeholder);
            $mediaSequenceElement['thumbnail'] = '';
        }

        // Specific to media files.
        $mseRenderings = array();
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm…
        $mseRendering = array();
        $mseRendering['@id'] = $file->getWebPath('original');
        $mseRendering['format'] = $file->mime_type;
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = array();
        $mseUrl = absolute_url(array(
            'id' => $file->id,
        ), 'universalviewer_media');
        $mseUrl = $this->view->uvForceBaseUrlIfRequired($mseUrl);
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        $mediaSequenceElement = (object) $mediaSequenceElement;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for a video.
     *
     * @param File $file
     * @param array $values
     * @return object|null Standard object.
     */
    protected function _iiifMediaSequenceVideo(File $file, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $file->getWebPath('original') . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:MovingImage';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $file->mime_type;
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, such a file is normally the only
        // one file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        if ($file->hasThumbnail()) {
            $thumbnailUrl = $file->getWebPath('thumbnail');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        // A place holder is recommended for medias.
        if (empty($mediaSequenceElement['thumbnail'])) {
            // $placeholder = 'images/placeholder-video.jpg';
            // $mediaSequenceElement['thumbnail'] = src($placeholder);
            $mediaSequenceElement['thumbnail'] = '';
        }

        // Specific to media files.
        $mseRenderings = array();
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm...
        $mseRendering = array();
        $mseRendering['@id'] = $file->getWebPath('original');
        $mseRendering['format'] = $file->mime_type;
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = array();
        $mseUrl = absolute_url(array(
            'id' => $file->id,
        ), 'universalviewer_media');
        $mseUrl = $this->view->uvForceBaseUrlIfRequired($mseUrl);
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        // TODO Get the true video width and height, even if it
        // is automatically managed.
        $mediaSequenceElement['width'] = 0;
        $mediaSequenceElement['height'] = 0;
        $mediaSequenceElement = (object) $mediaSequenceElement;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for a threejs 3D model.
     *
     * @param File $file
     * @param array $values
     * @return object|null Standard object.
     */
    protected function _iiifMediaSequenceThreejs(File $file, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $file->getWebPath('original');
        $mediaSequenceElement['@type'] = 'dctypes:PhysicalObject';
        $mediaSequenceElement['format'] = 'application/vnd.threejs+json';
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, a 3D model is normally the only one
        // file.
        $mediaSequenceElement['label'] = $values['label'];
        // Metadata are already set at record level.
        // $mediaSequenceElement['metadata'] = $values['metadata'];
        // Check if there is a "thumb.jpg" that can be managed as a thumbnail.
        foreach ($values['files'] as $imageFile) {
            if (basename($imageFile->original_filename) == 'thumb.jpg') {
                // The original is used, because this is already a thumbnail.
                $thumbnailUrl = $imageFile->getWebPath('original');
                if ($thumbnailUrl) {
                    $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
                }
                break;
            }
        }
        // No media sequence service and no sequences.
        $mediaSequenceElement = (object) $mediaSequenceElement;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF sequence object for an unsupported format.
     *
     * @param array $rendering
     * @return object Standard object.
     */
    protected function _iiifSequenceUnsupported($rendering = array())
    {
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

        return $sequence;
    }

    /**
     * Get the representative thumbnail of the whole work.
     *
     * @param Omeka_Record_AbstractRecord $record
     * @param bool $isThreejs Manage an exception.
     * @return object The iiif thumbnail.
     */
    protected function _mainThumbnail($record, $isThreejs)
    {
        $file = null;
        $db = get_db();
        $table = $db->getTable('File');
        // Threejs is an exception, because the thumbnail may be a true file
        // named "thumb.js".
        if ($isThreejs) {
            $select = $table->getSelect()
                ->where('item_id = ?', $record->id)
                ->where('has_derivative_image = 1')
                ->where('original_filename LIKE "%thumb.jpg"')
                ->order('id ASC');
            $file = $table->fetchObject($select);
        }

        // Standard record.
        if (empty($file)) {
            // TODO Use index of the true Omeka representative file.
            $file = $table->findWithImages($record->id, 1);
        }

        if ($file) {
            return $this->_iiifThumbnail($file);
        }
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
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    protected function _getWidthAndHeight($filepath)
    {
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempname = tempnam(sys_get_temp_dir(), 'uv_');
            $result = file_put_contents($tempname, $filepath);
            if ($result !== false) {
                $result = getimagesize($filepath);
                if ($result) {
                    list($width, $height) = $result;
                }
                unlink($tempname);
            }
        } elseif (file_exists($filepath)) {
            $result = getimagesize($filepath);
            if ($result) {
                list($width, $height) = $result;
            }
        }

        if (empty($width) || empty($height)) {
            return null;
        }

        return array(
            'width' => $width,
            'height' => $height,
        );
    }
}
