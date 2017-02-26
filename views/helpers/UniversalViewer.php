<?php
/**
 * Helper to display a Universal Viewer
 */
class UniversalViewer_View_Helper_UniversalViewer extends Zend_View_Helper_Abstract
{

    /**
     * Get the specified UniversalViewer.
     *
     * @param Record $record
     * @param array $options Associative array of optional values:
     *   - (string) class
     *   - (string) locale
     *   - (string) style
     *   - (string) config
     * @return string. The html string corresponding to the UniversalViewer.
     */
    public function universalViewer($record, $options = array())
    {
        if (empty($record)) {
            return '';
        }

        $recordClass = get_class($record);
        if (!in_array($recordClass, array('Item', 'Collection'))) {
            return '';
        }

        // Determine if we should get the manifest from a field in the metadata.
        $urlManifest = '';
        $manifestElement = get_option('universalviewer_alternative_manifest_element');
        if ($manifestElement) {
            $urlManifest = metadata($record, json_decode($manifestElement, true));
        }

        // Some specific checks.
        switch ($recordClass) {
            case 'Item':
                // Currently, item without files is unprocessable.
                if ($record->fileCount() == 0 and $urlManifest == '') {
                    // return __('This item has no files and is not displayable.');
                    return '';
                }
                break;
            case 'Collection':
                if ($record->totalItems() == 0 and $urlManifest == '') {
                    // return __('This collection has no item and is not displayable.');
                    return '';
                }
                break;
        }

        // If manifest not provided in metadata, point to manifest created from
        // Omeka files.
        if (empty($urlManifest)) {
            $route = 'universalviewer_presentation_' . strtolower($recordClass);
            $urlManifest = absolute_url(array(
                'id' => $record->id,
            ), $route);
            $urlManifest = $this->view->uvForceHttpsIfRequired($urlManifest);
        }

        return $this->_display($urlManifest, $options);
    }

    /**
     * Render a universal viewer for a url, according to options.
     *
     * @param string $urlManifest
     * @param array $options
     * @return string
     */
    protected function _display($urlManifest, $options = array())
    {
        $class = isset($options['class'])
            ? $options['class']
            : get_option('universalviewer_class');
        if (!empty($class)) {
            $class = ' ' . $class;
        }
        $locale = isset($options['locale'])
            ? $options['locale']
            : get_option('universalviewer_locale');
        if (!empty($locale)) {
            $locale = ' data-locale="' . $locale . '"';
        }
        $style = isset($options['style'])
            ? $options['style']
            : get_option('universalviewer_style');
        if (!empty($style)) {
            $style = ' style="' . $style . '"';
        }

        // Default configuration file.
        $config = empty($options['config'])
            ? src('config', 'universal-viewer', 'json')
            : $options['config'];
        $urlJs = src('embed', 'javascripts/uv/lib', 'js');

        $html = sprintf('<div class="uv%s" data-config="%s" data-uri="%s"%s%s></div>',
            $class,
            $config,
            $urlManifest,
            $locale,
            $style);
        $html .= sprintf('<script type="text/javascript" id="embedUV" src="%s"></script>', $urlJs);
        $html .= '<script type="text/javascript">/* wordpress fix */</script>';
        return $html;
    }
}
