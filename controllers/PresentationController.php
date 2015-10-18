<?php
/**
 * The presentation controller class.
 *
 * @package UniversalViewer
 */
class UniversalViewer_PresentationController extends Omeka_Controller_AbstractActionController
{
    /**
     * Forward to the 'manifest' action.
     *
     * @internal Unlike info.json, the redirect is not required.
     *
     * @see self::manifestAction()
     */
    public function indexAction()
    {
        $this->forward('manifest');
    }

    public function manifestAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        $recordType = $this->getParam('recordtype');
        $record = get_record_by_id(Inflector::classify($recordType), $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $manifest = get_view()->iiifManifest($record, false);

        $this->_sendJson($manifest);
    }

    /**
     * Return Json to client according to request.
     *
     * @param $data
     * @see UniversalViewer_ImageController::_sendJson()
     */
    protected function _sendJson($data)
    {
        $this->_helper->viewRenderer->setNoRender();
        $request = $this->getRequest();
        $response = $this->getResponse();

        // The helper is not used, because it's not possible to set options.
        // $this->_helper->json($data);

        // According to specification, the response should be json, except if
        // client asks json-ld.
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
