<?php
/**
 * The Image controller class.
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
        $this->view->message = __('The IIIF server cannot fulfil the request: the arguments are incorrect.');
        $this->renderScript('image/error.php');

        // $response->setHttpResponseCode(501);
        // $this->view->message = __('The IIIF request is valid, but is not implemented by this server.');
        // $this->renderScript('image/error.php');
    }

    /**
     * Send "info.json" for the current file.
     *
     * @internal The info is managed by the ImageControler because it indicates
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

        $info = get_view()->iiifInfo($record, false);

        $this->_sendJson($info);
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

        // Prepare the parameters for the transformation.
        $transform = array();

        $filepath = $this->_getImagePath($file, 'original');
        $transform['source']['filepath'] = $filepath;
        $transform['source']['mime_type'] = $file->mime_type;

        list($sourceWidth, $sourceHeight) = $this->_getWidthAndHeight($filepath);
        $transform['source']['width'] = $sourceWidth;
        $transform['source']['height'] = $sourceHeight;

        // TODO Move the maximum of checks in _transformImage().

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
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the region "%s" is incorrect.', $region);
                $this->renderScript('image/error.php');
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
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the region "%s" is incorrect.', $region);
                $this->renderScript('image/error.php');
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
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the size "%s" is incorrect.', $size);
                $this->renderScript('image/error.php');
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
            $destinationWidth = (integer) substr($size, 1, $pos);
            $destinationHeight = (integer) substr($size, $pos + 1);
            if (empty($destinationWidth) || empty($destinationHeight)) {
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the size "%s" is incorrect.', $size);
                $this->renderScript('image/error.php');
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
            $destinationWidth = (integer) substr($size, 0, $pos);
            $destinationHeight = (integer) substr($size, $pos + 1);
            if (empty($destinationWidth) && empty($destinationHeight)) {
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the size "%s" is incorrect.', $size);
                $this->renderScript('image/error.php');
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
                            list($testWidth, $testHeight) = $this->_getWidthAndHeight($filepath);
                            if ($destinationWidth == $testWidth && $destinationHeight == $testHeight) {
                                $transform['size']['feature'] = 'full';
                                // Change the source file to avoid a transformation.
                                // TODO Check the format?
                                if ($imageType != 'original') {
                                    $transform['source']['filepath'] = $filepath;
                                    $transform['source']['mime_type'] = 'image/jpeg';

                                    list($sourceWidth, $sourceHeight) = $this->_getWidthAndHeight($filepath);
                                    $transform['source']['width'] = $sourceWidth;
                                    $transform['source']['height'] = $sourceHeight;
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
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the size "%s" is not supported.', $size);
                $this->renderScript('image/error.php');
                return;
            }

            // A quick check to avoid a possible transformation.
            if (isset($transform['size']['width']) && empty($transform['size']['width'])
                    || isset($transform['size']['width']) && empty($transform['size']['width'])
                ) {
                $response->setHttpResponseCode(400);
                $this->view->message = __('The IIIF server cannot fulfil the request: the size "%s" is not supported.', $size);
                $this->renderScript('image/error.php');
            }
        }

        // Determine the rotation.

        // No rotation.
        if ($rotation == '0') {
            $transform['rotation']['feature'] = 'noRotation';
        }

        // Simple rotation.
        elseif ($rotation == '90' ||$rotation == '180' || $rotation == '270')  {
            $transform['rotation']['feature'] = 'rotationBy90s';
            $transform['rotation']['degrees'] = (integer) $rotation;
        }

        // Arbitrary rotation.
        // Currently not supported.
        else {
            $transform['rotation']['feature'] = 'rotationArbitrary';
            // if ($rotation > 360) {}
            $response->setHttpResponseCode(400);
            $this->view->message = __('The IIIF server cannot fulfil the request: the rotation "%s" is not supported.', $rotation);
            $this->renderScript('image/error.php');
            return;
        }

        // Determine the quality.
        // The regex in route checks it.
        $transform['quality']['feature'] = $quality;

        // Determine the format.
        // The regex in route checks it.
        $mimeTypes = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'tif' => 'image/tiff',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'jp2' => 'image/jp2',
            'webp' => 'image/webp',
        );
        $transform['format']['feature'] = $mimeTypes[$format];

        // A quick check when there is no transformation and the original or
        // the derivative are used.
        if ($transform['region']['feature'] == 'full'
                && $transform['size']['feature'] == 'full'
                && $transform['rotation']['feature'] == 'noRotation'
                && $transform['quality']['feature'] == 'default'
                && $transform['format']['feature'] == $file->mime_type
            ) {
            $isTempFile = false;
            $imagePath = $transform['source']['filepath'];
        }
        // The image needs to be transformed.
        else {
            $isTempFile = true;
            $imagePath = $this->_transformImage($transform);
            if (empty($imagePath)) {
                $response->setHttpResponseCode(500);
                $this->view->message = __('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found or empty.');
                $this->renderScript('image/error.php');
                return;
            }
        }

        $output = file_get_contents($imagePath);
        if ($isTempFile) {
            unlink($imagePath);
        }

        if (!$output) {
            $response->setHttpResponseCode(500);
            $this->view->message = __('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found or empty.');
            $this->renderScript('image/error.php');
            return;
        }

        $this->_helper->viewRenderer->setNoRender();

        // Header for CORS, required for access of IIIF.
        $response->setHeader('access-control-allow-origin', '*');
        // Recommanded by feature "profileLinkHeader".
        $response->setHeader('Link', '<http://iiif.io/api/image/2/level2.json>;rel="profile"');
        $response->setHeader('Content-Type', $transform['format']['feature']);
        $response->clearBody ();
        $response->setBody($output);
    }

    /**
     * Return Json to client according to request.
     *
     * @param $data
     * @see UniversalViewer_PresentationController::_sendJson()
     */
    protected function _sendJson($data)
    {
        $this->_helper->viewRenderer->setNoRender();
        $request = $this->getRequest();
        $response = $this->getResponse();

        // The helper is not used, because it's not possible to set options.
        // $this->_helper->json($data);

        // According to specification, the response should be json, except if
        // client asks json-ld (feature "jsonldMediaType").
        $accept = $request->getHeader('Accept');
        if (strstr($accept, 'application/ld+json')) {
            $response->setHeader('Content-Type', 'application/ld+json', true);
            $response->setHeader('Content-Type', 'charset=utf-8');
        }
        // Default to json with a link to json-ld.
        else {
            $response->setHeader('Content-Type', 'application/json', true);
            $response->setHeader('Content-Type', 'charset=utf-8');
            $response->setHeader('Link', '<http://iiif.io/api/image/2/context.json>', true);
            $response->setHeader('Link', 'rel="http://www.w3.org/ns/json-ld#context"');
            $response->setHeader('Link', 'type="application/ld+json"');
       }

        // Header for CORS, required for access of IIIF.
        $response->setHeader('access-control-allow-origin', '*');
        $response->clearBody ();
        // $body = json_encode($data);
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->setBody($body);
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

    /**
     * Transform a file according to parameters.
     *
     * @internal GD is used because it's installed by default with php and is
     * quicker for simple processes.
     *
     * @param array $args Contains the filepath and the parameters.
     * @return string|null The filepath to the temp image if success.
     */
    protected function _transformImage($args)
    {
        $sourceGD = $this->_loadImageResource($args['source']['filepath']);
        if (empty($sourceGD)) {
            return;
        }

        // Get width and height if needed.
        if (empty($args['source']['width']) || empty($args['source']['height'])) {
            list($args['source']['width'], $args['source']['height']) = $this->_getWidthAndHeight($args['source']['filepath']);
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
        if (empty($destinationWidth) || empty($destinationHeight)
                || $destinationWidth > $sourceWidth || $destinationHeight > $sourceHeight
            ) {
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
            case 'image/tiff':
            case 'application/pdf':
            case 'image/jp2':
            default:
                $result = false;
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
        if (empty($source) || !is_readable($source)) {
            return false;
        }

        try {
            $result = imagecreatefromstring(file_get_contents($source));
        } catch (Exception $e) {
            _log("GD failed to open the file. Details:\n$e", Zend_Log::ERR);
            return false;
        }

        return $result;
    }
}
