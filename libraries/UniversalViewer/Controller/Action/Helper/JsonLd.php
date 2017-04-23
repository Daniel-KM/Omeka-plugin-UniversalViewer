<?php
/**
 * @see Zend_Controller_Action_Helper_Abstract
 */
require_once 'Zend/Controller/Action/Helper/Abstract.php';

class UniversalViewer_Controller_Action_Helper_JsonLd extends Zend_Controller_Action_Helper_Abstract
{
    public function jsonLd($data)
    {
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
        $body = version_compare(phpversion(), '5.4.0', '<') || get_option('universalviewer_force_strict_json')
            ? json_encode($data)
            : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->setBody($body);
    }
}
