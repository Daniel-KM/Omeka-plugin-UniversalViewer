<?php
/**
 * Helper to display a Universal Viewer
 */
class UniversalViewer_View_Helper_UniversalViewer extends Zend_View_Helper_Abstract
{
    /**
     * Get the Universal Viewer for the provided record.
     *
     * @param Omeka_Record_AbstractRecord $record
     * @param array $options
     * @return string Html string corresponding to the viewer.
     */
    public function universalViewer($record, $options = array())
    {
        if (empty($record)) {
            return '';
        }

        // Prepare the url of the manifest for a dynamic collection.
        if (is_array($record)) {
            $identifier = $this->_buildIdentifierForList($record);
            $route = 'universalviewer_presentation_collection_list';
            $urlManifest = absolute_url(array(
                 'id' => $identifier,
            ), $route);
            $urlManifest = $this->view->uvForceBaseUrlIfRequired($urlManifest);
            return $this->_display($urlManifest, $options, 'multiple');
        }

        // Prepare the url for the manifest of a record after additional checks.
        $recordClass = get_class($record);
        if (!in_array($recordClass, array('Item', 'Collection'))) {
            return '';
        }

        // Determine if we should get the manifest from a field in the metadata.
        $urlManifest = '';
        $manifestElement = get_option('universalviewer_alternative_manifest_element');
        if ($manifestElement) {
            $urlManifest = metadata($record, json_decode($manifestElement, true));
            if ($urlManifest) {
                return $this->_display($urlManifest, $options, $recordClass);
            }
            // If manifest not provided in metadata, point to manifest created
            // from Omeka files.
        }

        // Some specific checks.
        switch ($recordClass) {
            case 'Item':
                // Currently, an item without files is unprocessable.
                if ($record->fileCount() == 0) {
                    // return __('This item has no files and is not displayable.');
                    return '';
                }
                $route = 'universalviewer_presentation_item';
                break;
            case 'Collection':
                if ($record->totalItems() == 0) {
                    // return __('This collection has no item and is not displayable.');
                    return '';
                }
                $route = 'universalviewer_presentation_collection';
                break;
        }

        $urlManifest = absolute_url(array(
            'id' => $record->id,
        ), $route);
        $urlManifest = $this->view->uvForceBaseUrlIfRequired($urlManifest);

        return $this->_display($urlManifest, $options, $recordClass);
    }

    /**
     * Helper to create an identifier from a list of records.
     *
     * The dynamic identifier is a flat list of ids: "c-5i-1,i-2,c-3".
     * If there is only one id, a comma is added to avoid to have the same route
     * than the collection itself.
     * If there are only items, the most common case, the dynamic identifier is
     * simplified: "1,2,6". In all cases the order of records is kept.
     *
     * @todo Merge with UniversalViewer_View_Helper_IiifCollectionList::_buildIdentifierForList()
     *
     * @param array $records
     * @return string
     */
    protected function _buildIdentifierForList($records)
    {
        $map = array(
            'Item' => 'i-',
            'Collection' => 'c-',
            'File' => 'm-',
        );

        $identifiers = array();
        foreach ($records as $record) {
            $identifiers[] = $map[get_class($record)] . $record->id;
        }

        $identifier = implode(',', $identifiers);

        if (count($identifiers) == 1) {
            $identifier .= ',';
        }

        // Simplify the identifier: remove the "i-" if there are only items.
        if (strpos($identifier, 'c') === false && strpos($identifier, 'm') === false) {
            $identifier = str_replace('i-', '', $identifier);
        }

        return $identifier;
    }

    /**
     * Render a universal viewer for a url, according to options.
     *
     * @param string $urlManifest
     * @param array $options
     * @param string $recordClass
     * @return string
     */
    protected function _display($urlManifest, $options = array(), $recordClass = null)
    {
        static $id = 0;

        $html = '';

        // Unlike Omeka S, the head() is already executed.
        $urlCss = src('uv', 'javascripts/uv', 'css');
        $html .= sprintf('<link rel="stylesheet" property="stylesheet" href="%s">', $urlCss);
        $urlCss = css_src('universal-viewer');
        $html .= sprintf('<link rel="stylesheet" property="stylesheet" href="%s">', $urlCss);
        $urlJs = src('lib/offline', 'javascripts/uv', 'js');
        $html .= sprintf('<script type="text/javascript" src="%s"></script>', $urlJs);
        $urlJs = src('helpers', 'javascripts/uv', 'js');
        $html .= sprintf('<script type="text/javascript" src="%s"></script>', $urlJs);
        $urlJs = src('uv', 'javascripts/uv', 'js');
        $html .= sprintf('<script type="text/javascript" src="%s"></script>', $urlJs);
        // $this->view->headLink()
        //     ->appendStylesheet(src('uv.css', 'javascripts/uv', 'css'))
        //     ->appendStylesheet(css_src('universal-viewer'));
        // $this->view->headScript()
        //     ->appendFile(src('lib/offline', 'javascripts/uv', 'js'), 'application/javascript')
        //     ->appendFile(src('helpers', 'javascripts/uv', 'js'), 'application/javascript')
        //     ->appendFile(src('uv', 'javascripts/uv', 'js'), 'application/javascript');

        // Default configuration file.
        $configUri = empty($options['config'])
            ? src('config', 'universal-viewer', 'json')
            : $options['config'];

        $config = array(
            'id' => 'uv-' . ++$id,
            'root' => substr(src('uv', 'javascripts/uv', 'js'), 0, -5),
            'iiifResourceUri' => $urlManifest,
            'configUri' => $configUri,
            'embedded' => true,
        );

        $config['locales'] = array(
            array('name' => 'en-GB', 'label' => 'English'),
        );

        $config += $options;

        $html .= common('helper/universal-viewer', array(
            'config' => $config,
        ));

        return $html;
    }
}
