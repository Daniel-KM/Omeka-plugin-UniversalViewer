<?php
/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class UniversalViewer_IiifCreator
{
    protected $_creator;
    protected $_args = array();

    public function __construct()
    {
        $creatorClass = get_option('universalviewer_iiif_creator') ?: 'Auto';
        $this->setCreator('UniversalViewer_IiifCreator_' . $creatorClass);
    }

    public function setCreator($creatorClass)
    {
        try {
            $this->_creator = new $creatorClass();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function setArgs($args)
    {
        $this->_args = $args;
    }

    /**
     * Transform an image into another image according to params.
     *
     * @internal The args are currently already checked in the controller.
     *
     * @param array $args List of arguments for the transformation.
     * @return string|null The filepath to the temp image if success.
     */
    public function transform(array $args = array())
    {
        if (!empty($args)) {
            $this->setArgs($args);
        }
        return $this->_creator->transform($this->_args);
    }
}
