<?php
/**
 * Abstract  to manage strategies used to create an image.
 *
 * @package UniversalViewer
 */
abstract class UniversalViewer_AbstractIiifCreator
{
    // List of managed IIIF media types.
    protected $_supportedFormats = array();

    protected $_args;

    /**
     * Check if a media type is supported.
     *
     * @param string $mediaType
     * @return boolean
     */
    public function checkMediaType($mediaType)
    {
        return !empty($this->_supportedFormats[$mediaType]);
    }

    /**
     * Transform an image into another image according to params.
     *
     * @internal The args are currently already checked in the controller.
     *
     * @param array $args List of arguments for the transformation.
     * @return string|null The filepath to the temp image if success.
     */
    abstract public function transform(array $args = array());

    /**
     * Prepare the extraction from the source and the requested region and size.
     *
     * @return array|null Arguments for the transformation, else null.
     */
    protected function _prepareExtraction()
    {
        $args = &$this->_args;

        switch ($args['region']['feature']) {
            case 'full':
                $sourceX = 0;
                $sourceY = 0;
                $sourceWidth = $args['source']['width'];
                $sourceHeight = $args['source']['height'];
                break;

            case 'regionByPx':
                if ($args['region']['x'] >= $args['source']['width']) {
                    return;
                }
                if ($args['region']['y'] >= $args['source']['height']) {
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
                return;
       }

        // Final generic check for region of the source.
        if ($sourceX < 0 || $sourceX >= $args['source']['width']
                || $sourceY < 0 || $sourceY >= $args['source']['height']
                || $sourceWidth <= 0 || $sourceWidth > $args['source']['width']
                || $sourceHeight <= 0 || $sourceHeight > $args['source']['height']
            ) {
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
                return;
        }

        // Final generic checks for size.
        if (empty($destinationWidth) || empty($destinationHeight)) {
            return;
        }

        return array(
            $sourceX,
            $sourceY,
            $sourceWidth,
            $sourceHeight,
            $destinationWidth,
            $destinationHeight,
        );
    }
}
