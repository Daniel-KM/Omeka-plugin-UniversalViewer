<?php
/**
 * Abstract  to manage strategies used to create an image.
 *
 * @package UniversalViewer
 */
abstract class UniversalViewer_AbstractIiifCreator
{
    /**
     * Transform an image into another image according to params.
     *
     * @internal The args are currently already checked in the controller.
     *
     * @param array $args List of arguments for the transformation.
     * @return string|null The filepath to the temp image if success.
     */
    abstract public function transform(array $args = array());
}
