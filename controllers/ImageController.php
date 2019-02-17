<?php
/**
 * The Image controller class.
 *
 * @todo Move all image processing stuff in Image Server.
 *
 * @package UniversalViewer
 */
class UniversalViewer_ImageController extends Omeka_Controller_AbstractActionController
{
    /**
     * Redirect to the 'info' action, required by the feature "baseUriRedirect".
     *
     * @see self::infoAction()
     */
    public function indexAction()
    {
        $id = $this->getParam('id');
        $url = absolute_url(array(
                'id' => $id,
            ), 'universalviewer_image_info');
        $this->redirect($url);
    }

    /**
     * Returns an error 400 or 501 to requests that are invalid or not
     * implemented.
     */
    public function badAction()
    {
        $response = $this->getResponse();

        // TODO Common analysis of the request.

        $response->setHttpResponseCode(400);
        $this->view->message = __('The IIIF server cannot fulfill the request: the arguments are incorrect.');
        $this->renderScript('image/error.php');

        // $response->setHttpResponseCode(501);
        // $this->view->message = __('The IIIF request is valid, but is not implemented by this server.');
        // $this->renderScript('image/error.php');
    }

    /**
     * Send "info.json" for the current file.
     *
     * The info is managed by the ImageControler because it indicates
     * capabilities of the IIIF server for the request of a file.
     */
    public function infoAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        $record = get_record_by_id('File', $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $info = get_view()->iiifInfo($record);

        $this->_helper->viewRenderer->setNoRender();
        $helper = new UniversalViewer_Controller_Action_Helper_JsonLd();
        $helper->jsonLd($info);
    }

    /**
     * Returns sized image for the current file.
     */
    public function fetchAction()
    {
        $id = $this->getParam('id');
        $file = get_record_by_id('File', $id);
        if (empty($file)) {
            throw new Omeka_Controller_Exception_404;
        }

        $response = $this->getResponse();

        // Check if the original file is an image.
        if (strpos($file->mime_type, 'image/') !== 0) {
            $response->setHttpResponseCode(501);
            $this->view->message = __('The source file is not an image.');
            $this->renderScript('image/error.php');
            return;
        }

        // Check, clean and optimize and fill values according to the request.
        $transform = $this->_cleanRequest($file);
        if (empty($transform)) {
            // The message is set in view.
            $response->setHttpResponseCode(400);
            $this->renderScript('image/error.php');
            return;
        }

        // Now, process the requested transformation if needed.
        $imageUrl = '';
        $imagePath = '';

        // A quick check when there is no transformation.
        if ($transform['region']['feature'] == 'full'
                && $transform['size']['feature'] == 'full'
                && $transform['mirror']['feature'] == 'default'
                && $transform['rotation']['feature'] == 'noRotation'
                && $transform['quality']['feature'] == 'default'
                && $transform['format']['feature'] == $file->mime_type
            ) {
            $imageUrl = $file->getWebPath('original');
        }

        // A transformation is needed.
        else {
            // Quick check if an Omeka derivative is appropriate.
            $pretiled = $this->_useOmekaDerivative($file, $transform);
            if ($pretiled) {
                // Check if a light transformation is needed.
                if ($transform['size']['feature'] != 'full'
                        || $transform['mirror']['feature'] != 'default'
                        || $transform['rotation']['feature'] != 'noRotation'
                        || $transform['quality']['feature'] != 'default'
                        || $transform['format']['feature'] != $pretiled['media_type']
                    ) {
                    $args = $transform;
                    $args['source']['filepath'] = $pretiled['filepath'];
                    $args['source']['media_type'] = $pretiled['media_type'];
                    $args['source']['width'] = $pretiled['width'];
                    $args['source']['height'] = $pretiled['height'];
                    $args['region']['feature'] = 'full';
                    $args['region']['x'] = 0;
                    $args['region']['y'] = 0;
                    $args['region']['width'] = $pretiled['width'];
                    $args['region']['height'] = $pretiled['height'];
                    $imagePath = $this->_transformImage($args);
                }
                // No transformation.
                else {
                    $imageUrl = $file->getWebPath($pretiled['derivativeType']);
                }
            }

            // Check if another image can be used.
            else {
                // Check if the image is pre-tiled.
                $pretiled = $this->_usePreTiled($file, $transform);
                if ($pretiled) {
                    // Warning: Currently, the tile server does not manage
                    // regions or special size, so it is possible to process the
                    // crop of an overlap in one transformation.

                    // Check if a light transformation is needed (all except
                    // extraction of the region).
                    if (($pretiled['overlap'] && !$pretiled['isSingleCell'])
                            || $transform['mirror']['feature'] != 'default'
                            || $transform['rotation']['feature'] != 'noRotation'
                            || $transform['quality']['feature'] != 'default'
                            || $transform['format']['feature'] != $pretiled['media_type']
                        ) {
                        $args = $transform;
                        $args['source']['filepath'] = $pretiled['filepath'];
                        $args['source']['media_type'] = $pretiled['media_type'];
                        $args['source']['width'] = $pretiled['width'];
                        $args['source']['height'] = $pretiled['height'];
                        // The tile server returns always the true tile, so crop
                        // it when there is an overlap.
                        if ($pretiled['overlap']) {
                            $args['region']['feature'] = 'regionByPx';
                            $args['region']['x'] = $pretiled['isFirstColumn'] ? 0 : $pretiled['overlap'];
                            $args['region']['y'] = $pretiled['isFirstRow'] ? 0 : $pretiled['overlap'];
                            $args['region']['width'] = $pretiled['size'];
                            $args['region']['height'] = $pretiled['size'];
                        }
                        // Normal tile.
                        else {
                            $args['region']['feature'] = 'full';
                            $args['region']['x'] = 0;
                            $args['region']['y'] = 0;
                            $args['region']['width'] = $pretiled['width'];
                            $args['region']['height'] = $pretiled['height'];
                        }
                        $args['size']['feature'] = 'full';
                        $imagePath = $this->_transformImage($args);
                    }
                    // No transformation.
                    else {
                        $imageUrl = $pretiled['fileurl'];
                    }
                }

                // The image needs to be transformed dynamically.
                else {
                    $maxFileSize = get_option('universalviewer_max_dynamic_size');
                    if (!empty($maxFileSize) && $file->size > $maxFileSize) {
                        $response->setHttpResponseCode(500);
                        $this->view->message = __('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the file is not tiled for dynamic processing.');
                        $this->renderScript('image/error.php');
                        return;
                    }
                    $imagePath = $this->_transformImage($transform);
                }
            }
        }

        // Redirect to the url when an existing file is available.
        if ($imageUrl) {
            // Header for CORS, required for access of IIIF.
            $response->setHeader('Access-Control-Allow-Origin', '*');
            // Recommanded by feature "profileLinkHeader".
            $response->setHeader('Link', '<http://iiif.io/api/image/2/level2.json>;rel="profile"');
            $response->setHeader('Content-Type', $transform['format']['feature']);

            // Redirect (302/307) to the url of the file.
            // TODO This is a local file (normal server, except iiip server): use 200.
            $this->_helper->redirector->gotoUrlAndExit($imageUrl);
        }

        //This is a transformed file.
        elseif ($imagePath) {
            $output = file_get_contents($imagePath);
            unlink($imagePath);

            if (empty($output)) {
                $response->setHttpResponseCode(500);
                $this->view->message = __('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found or empty.');
                $this->renderScript('image/error.php');
                return;
            }

            $this->_helper->viewRenderer->setNoRender();

            // Header for CORS, required for access of IIIF.
            $response->setHeader('Access-Control-Allow-Origin', '*');
            // Recommanded by feature "profileLinkHeader".
            $response->setHeader('Link', '<http://iiif.io/api/image/2/level2.json>;rel="profile"');
            $response->setHeader('Content-Type', $transform['format']['feature']);

            $response->clearBody();
            $response->setBody($output);
        }

        // No result.
        else {
            $response->setHttpResponseCode(500);
            $this->view->message = __('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is empty or not found.');
            $this->renderScript('image/error.php');
            return;
        }
    }

    /**
     * Check, clean and optimize the request for quicker transformation.
     *
     * @todo Move the maximum of checks in the Image Server.
     *
     * @param File $file
     * @return array|null Array of cleaned requested image, else null.
     */
    protected function _cleanRequest($file)
    {
        $transform = array();

        $transform['source']['filepath'] = $this->_getImagePath($file, 'original');
        $transform['source']['media_type'] = $file->mime_type;

        $helper = new UniversalViewer_Controller_Action_Helper_ImageSize();
        list($sourceWidth, $sourceHeight) = array_values($helper->imageSize($file, 'original'));
        $transform['source']['width'] = $sourceWidth;
        $transform['source']['height'] = $sourceHeight;

        // The regex in the route implies that all requests are valid (no 501),
        // but may be bad formatted (400).

        $region = $this->getParam('region');
        $size = $this->getParam('size');
        $rotation = $this->getParam('rotation');
        $quality = $this->getParam('quality');
        $format = $this->getParam('format');

        // Determine the region.

        // Full image.
        if ($region == 'full') {
            $transform['region']['feature'] = 'full';
            // Next values may be needed for next parameters.
            $transform['region']['x'] = 0;
            $transform['region']['y'] = 0;
            $transform['region']['width'] = $sourceWidth;
            $transform['region']['height'] = $sourceHeight;
        }

        // "pct:x,y,w,h": regionByPct
        elseif (strpos($region, 'pct:') === 0) {
            $regionValues = explode(',', substr($region, 4));
            if (count($regionValues) != 4) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the region "%s" is incorrect.', $region);
                return;
            }
            $regionValues = array_map('floatval', $regionValues);
            // A quick check to avoid a possible transformation.
            if ($regionValues[0] == 0
                    && $regionValues[1] == 0
                    && $regionValues[2] == 100
                    && $regionValues[3] == 100
                ) {
                $transform['region']['feature'] = 'full';
                // Next values may be needed for next parameters.
                $transform['region']['x'] = 0;
                $transform['region']['y'] = 0;
                $transform['region']['width'] = $sourceWidth;
                $transform['region']['height'] = $sourceHeight;
            }
            // Normal region.
            else {
                $transform['region']['feature'] = 'regionByPct';
                $transform['region']['x'] = $regionValues[0];
                $transform['region']['y'] = $regionValues[1];
                $transform['region']['width'] = $regionValues[2];
                $transform['region']['height'] = $regionValues[3];
            }
        }

        // "x,y,w,h": regionByPx.
        else {
            $regionValues = explode(',', $region);
            if (count($regionValues) != 4) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the region "%s" is incorrect.', $region);
                return;
            }
            $regionValues = array_map('intval', $regionValues);
            // A quick check to avoid a possible transformation.
            if ($regionValues[0] == 0
                    && $regionValues[1] == 0
                    && $regionValues[2] == $sourceWidth
                    && $regionValues[3] == $sourceHeight
                ) {
                $transform['region']['feature'] = 'full';
                // Next values may be needed for next parameters.
                $transform['region']['x'] = 0;
                $transform['region']['y'] = 0;
                $transform['region']['width'] = $sourceWidth;
                $transform['region']['height'] = $sourceHeight;
            }
            // Normal region.
            else {
                $transform['region']['feature'] = 'regionByPx';
                $transform['region']['x'] = $regionValues[0];
                $transform['region']['y'] = $regionValues[1];
                $transform['region']['width'] = $regionValues[2];
                $transform['region']['height'] = $regionValues[3];
            }
        }

        // Determine the size.

        // Full image.
        if ($size == 'full') {
            $transform['size']['feature'] = 'full';
        }

        // "pct:x": sizeByPct
        elseif (strpos($size, 'pct:') === 0) {
            $sizePercentage = floatval(substr($size, 4));
            if (empty($sizePercentage) || $sizePercentage > 100) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the size "%s" is incorrect.', $size);
                return;
            }
            // A quick check to avoid a possible transformation.
            if ($sizePercentage == 100) {
                $transform['size']['feature'] = 'full';
            }
            // Normal size.
            else {
                $transform['size']['feature'] = 'sizeByPct';
                $transform['size']['percentage'] = $sizePercentage;
            }
        }

        // "!w,h": sizeByWh
        elseif (strpos($size, '!') === 0) {
            $pos = strpos($size, ',');
            $destinationWidth = (int) substr($size, 1, $pos);
            $destinationHeight = (int) substr($size, $pos + 1);
            if (empty($destinationWidth) || empty($destinationHeight)) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the size "%s" is incorrect.', $size);
                return;
            }
            // A quick check to avoid a possible transformation.
            if ($destinationWidth == $transform['region']['width']
                    && $destinationHeight == $transform['region']['width']
                ) {
                $transform['size']['feature'] = 'full';
            }
            // Normal size.
            else {
                $transform['size']['feature'] = 'sizeByWh';
                $transform['size']['width'] = $destinationWidth;
                $transform['size']['height'] = $destinationHeight;
            }
        }

        // "w,h", "w," or ",h".
        else {
            $pos = strpos($size, ',');
            $destinationWidth = (int) substr($size, 0, $pos);
            $destinationHeight = (int) substr($size, $pos + 1);
            if (empty($destinationWidth) && empty($destinationHeight)) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the size "%s" is incorrect.', $size);
                return;
            }

            // "w,h": sizeByWhListed or sizeByForcedWh.
            if ($destinationWidth && $destinationHeight) {
                // Check the size only if the region is full, else it's forced.
                if ($transform['region']['feature'] == 'full') {
                    $availableTypes = array('thumbnail', 'fullsize', 'original');
                    foreach ($availableTypes as $imageType) {
                        $filepath = $this->_getImagePath($file, $imageType);
                        if ($filepath) {
                            $helper = new UniversalViewer_Controller_Action_Helper_ImageSize();
                            list($testWidth, $testHeight) = array_values($helper->imageSize($file, $imageType));
                            if ($destinationWidth == $testWidth && $destinationHeight == $testHeight) {
                                $transform['size']['feature'] = 'full';
                                // Change the source file to avoid a transformation.
                                // TODO Check the format?
                                if ($imageType != 'original') {
                                    $transform['source']['filepath'] = $filepath;
                                    $transform['source']['media_type'] = 'image/jpeg';
                                    $transform['source']['width'] = $testWidth;
                                    $transform['source']['height'] = $testHeight;
                                }
                                break;
                            }
                        }
                    }
                }
                if (empty($transform['size']['feature'])) {
                    $transform['size']['feature'] = 'sizeByForcedWh';
                    $transform['size']['width'] = $destinationWidth;
                    $transform['size']['height'] = $destinationHeight;
                }
            }

            // "w,": sizeByW.
            elseif ($destinationWidth && empty($destinationHeight)) {
                $transform['size']['feature'] = 'sizeByW';
                $transform['size']['width'] = $destinationWidth;
            }

            // ",h": sizeByH.
            elseif (empty($destinationWidth) && $destinationHeight) {
                $transform['size']['feature'] = 'sizeByH';
                $transform['size']['height'] = $destinationHeight;
            }

            // Not supported.
            else {
                $this->view->message = __('The IIIF server cannot fulfill the request: the size "%s" is not supported.', $size);
                return;
            }

            // A quick check to avoid a possible transformation.
            if (isset($transform['size']['width']) && empty($transform['size']['width'])
                    || isset($transform['size']['height']) && empty($transform['size']['height'])
                ) {
                $this->view->message = __('The IIIF server cannot fulfill the request: the size "%s" is not supported.', $size);
                return;
            }
        }

        // Determine the mirroring and the rotation.

        $transform['mirror']['feature'] = substr($rotation, 0, 1) === '!' ? 'mirror' : 'default';
        if ($transform['mirror']['feature'] != 'default') {
            $rotation = substr($rotation, 1);
        }

        // Strip leading and ending zeros.
        if (strpos($rotation, '.') === false) {
            $rotation += 0;
        }
        // This may be a float, so keep all digits, because they can be managed
        // by the image server.
        else {
            $rotation = trim($rotation, '0');
            $rotationDotPos = strpos($rotation, '.');
            if ($rotationDotPos === strlen($rotation)) {
                $rotation = (int) trim($rotation, '.');
            } elseif ($rotationDotPos === 0) {
                $rotation = '0' . $rotation;
            }
        }

        // No rotation.
        if (empty($rotation)) {
            $transform['rotation']['feature'] = 'noRotation';
        }

        // Simple rotation.
        elseif ($rotation == 90 || $rotation == 180 || $rotation == 270) {
            $transform['rotation']['feature'] = 'rotationBy90s';
            $transform['rotation']['degrees'] = $rotation;
        }

        // Arbitrary rotation.
        else {
            $transform['rotation']['feature'] = 'rotationArbitrary';
            $transform['rotation']['degrees'] = $rotation;
        }

        // Determine the quality.
        // The regex in route checks it.
        $transform['quality']['feature'] = $quality;

        // Determine the format.
        // The regex in route checks it.
        $mediaTypes = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'tif' => 'image/tiff',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'jp2' => 'image/jp2',
            'webp' => 'image/webp',
        );
        $transform['format']['feature'] = $mediaTypes[$format];

        return $transform;
    }

    /**
     * Get a pre tiled image from Omeka derivatives.
     *
     * Omeka derivative are light and basic pretiled files, that can be used for
     * a request of a full region as a fullsize.
     * @todo To be improved. Currently, thumbnails are not used.
     *
     * @param File $file
     * @param array $transform
     * @return array|null Associative array with the file path, the derivative
     * type, the width and the height. Null if none.
     */
    protected function _useOmekaDerivative($file, $transform)
    {
        // Some requirements to get tiles.
        if ($transform['region']['feature'] != 'full') {
            return;
        }

        // Check size. Here, the "full" is already checked.
        $useDerivativePath = false;

        // Currently, the check is done only on fullsize.
        $derivativeType = 'fullsize';
        $helper = new UniversalViewer_Controller_Action_Helper_ImageSize();
        list($derivativeWidth, $derivativeHeight) = array_values($helper->imageSize($file, $derivativeType));
        switch ($transform['size']['feature']) {
            case 'sizeByW':
            case 'sizeByH':
                $constraint = $transform['size']['feature'] == 'sizeByW'
                    ? $transform['size']['width']
                    : $transform['size']['height'];

                // Check if width is lower than fulllsize or thumbnail.
                // Omeka and IIIF doesn't use the same type of constraint, so
                // a double check is done.
                // TODO To be improved.
                if ($constraint <= $derivativeWidth || $constraint <= $derivativeHeight) {
                    $useDerivativePath = true;
                }
                break;

            case 'sizeByWh':
            case 'sizeByWhListed':
            case 'sizeByForcedWh':
                $constraintW = $transform['size']['width'];
                $constraintH = $transform['size']['height'];

                // Check if width is lower than fulllsize or thumbnail.
                if ($constraintW <= $derivativeWidth || $constraintH <= $derivativeHeight) {
                    $useDerivativePath = true;
                }
                break;

            case 'sizeByPct':
                if ($transform['size']['percentage'] <= ($derivativeWidth * 100 / $transform['source']['width'])) {
                    $useDerivativePath = true;
                }
                break;

            case 'full':
                // Not possible to use a derivative, because the region is full.
            default:
                return;
        }

        if ($useDerivativePath) {
            $derivativePath = $this->_getImagePath($file, $derivativeType);

            return array(
                'filepath' => $derivativePath,
                'derivativeType' => $derivativeType,
                'media_type' => 'image/jpeg',
                'width' => $derivativeWidth,
                'height' => $derivativeHeight,
            );
        }
    }

    /**
     * Get a pre tiled image.
     *
     * @todo Prebuild tiles directly with the IIIF standard (same type of url).
     *
     * @param File $file
     * @param array $transform
     * @return array|null Associative array with the file path, the derivative
     * type, the width and the height. Null if none.
     */
    protected function _usePreTiled($file, $transform)
    {
        $helper = new UniversalViewer_Controller_Action_Helper_TileInfo();
        $tileInfo = $helper->tileInfo($file);
        if ($tileInfo) {
            $helper = new UniversalViewer_Controller_Action_Helper_TileServer();
            $tile = $helper->tileServer($tileInfo, $transform);
            return $tile;
        }
    }

    /**
     * Transform a file according to parameters.
     *
     * @param array $args Contains the filepath and the parameters.
     * @return string|null The filepath to the temp image if success.
     */
    protected function _transformImage($args)
    {
        $imageServer = new UniversalViewer_ImageServer();
        return $imageServer->transform($args);
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
            // Use the web url when an external storage is used. No check can be
            // done.
            // TODO Load locally the external path? It will be done later.
            else {
                $filepath = $file->getWebPath($derivativeType);
                return $filepath;
            }
        }
    }
}
