<?php
/**
 * The Media controller class.
 *
 * @package UniversalViewer
 */
class UniversalViewer_MediaController extends Omeka_Controller_AbstractActionController
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
            ), 'universalviewer_media_info');
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
        $this->view->message = __('The IXIF server cannot fulfil the request: the arguments are incorrect.');
        $this->renderScript('image/error.php');

        // $response->setHttpResponseCode(501);
        // $this->view->message = __('The IIIF request is valid, but is not implemented by this server.');
        // $this->renderScript('image/error.php');
    }

    /**
     * Send "info.json" for the current file.
     *
     * @internal The info is managed by the MediaControler because it indicates
     * capabilities of the IXIF server for the request of a file.
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
     * Return some context files describing media for IxIF.
     *
     * @todo This is not used currently: the Wellcome uris are kept because they
     * are set for main purposes in the UniversalViewer.
     * @link https://gist.github.com/tomcrane/7f86ac08d3b009c8af7c
     */
    public function contextAction()
    {
        $ixif = $this->getParam('ixif');
        $name = '';
        switch ($ixif) {
            case '0/context.json':
                $name = 'ixif/context.json';
                break;
        }

        if ($name) {
            $filepath = physical_path_to($name);
            $src = file_get_contents($filepath);
            if ($src) {
                $src = json_decode($src);
                return $this->_sendJson($src);
            }
        }

        // Silently end without error.
        $this->_helper->viewRenderer->setNoRender();
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

        $filepath = FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath('original');
        if (!file_exists($filepath)) {
            $response->setHttpResponseCode(500);
            $this->view->message = __('The IXIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found.');
            $this->renderScript('image/error.php');
            return;
        }

        $output = file_get_contents($filepath);
        if (!$output) {
            $response->setHttpResponseCode(500);
            $this->view->message = __('The IXIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found or empty.');
            $this->renderScript('image/error.php');
            return;
        }

        $this->_helper->viewRenderer->setNoRender();

        // Header for CORS, required for access of IXIF.
        $response->setHeader('access-control-allow-origin', '*');
        $response->setHeader('Content-Type', $file->mime_type);
        $response->clearBody();
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
            $response->setHeader('Content-Type', 'application/ld+json; charset=utf-8', true);
        }
        // Default to json with a link to json-ld.
        else {
            $response->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $response->setHeader('Link', '<http://iiif.io/api/image/2/context.json>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', true);
       }

        // Header for CORS, required for access of IIIF.
        $response->setHeader('access-control-allow-origin', '*');
        $response->clearBody();
        $body = version_compare(phpversion(), '5.4.0', '<')
            ? json_encode($data)
            : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->setBody($body);
    }
}
