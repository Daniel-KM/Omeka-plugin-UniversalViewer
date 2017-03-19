<?php
/**
 * Helper to force absolute urls to be secure (replace "http:"" by "https:").
 */
class UniversalViewer_View_Helper_UvForceHttpsIfRequired extends Zend_View_Helper_Abstract
{
    /**
     * Set the option to force https or not.
     *
     * @var bool
     */
    protected $_forceHttps;

    /**
     * Force absolute urls to be secure (replace "http:" by "https:").
     *
     * @param string $absoluteUrl
     * @return string
     */
    public function uvForceHttpsIfRequired($absoluteUrl)
    {
        if (is_null($this->_forceHttps)) {
            $this->_forceHttps = (boolean) get_option('universalviewer_force_https');
        }

        return $this->_forceHttps && (strpos($absoluteUrl, 'http:') === 0)
            ? substr_replace($absoluteUrl, 'https', 0, 4)
            : $absoluteUrl;
    }
}
