<?php
/**
 * Helper to force the base of absolute urls (replace "http:" by "https:", etc.).
 */
class UniversalViewer_View_Helper_UvForceBaseUrlIfRequired extends Zend_View_Helper_Abstract
{
    /**
     * Set the base of the url to change from, for example "http:".
     *
     * @var string
     */
    protected $forceFrom;

    /**
     * Set the base of the url to change to, for example "https:".
     *
     * @var string
     */
    protected $forceTo;

    /**
     * Force the base of absolute urls.
     *
     * @param string $absoluteUrl
     * @return string
     */
    public function uvForceBaseUrlIfRequired($absoluteUrl)
    {
        if (is_null($this->forceFrom)) {
            $this->forceFrom = (string) get_option('universalviewer_manifest_force_url_from');
            $this->forceTo = (string) get_option('universalviewer_manifest_force_url_to');
        }

        return $this->forceFrom && (strpos($absoluteUrl, $this->forceFrom) === 0)
            ? substr_replace($absoluteUrl, $this->forceTo, 0, strlen($this->forceFrom))
            : $absoluteUrl;
    }
}
