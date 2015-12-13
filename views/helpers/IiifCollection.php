<?php
/**
 * Helper to get a IIIF manifest for a collection.
 */
class UniversalViewer_View_Helper_IiifCollection extends Zend_View_Helper_Abstract
{
    /**
     * Get the IIIF manifest for the specified collection.
     *
     * @param Record|integer|null $record Collection
     * @param boolean $asJson Return manifest as object or as a json string.
     * @return Object|string|null. The object or the json string corresponding to the
     * manifest.
     */
    public function iiifCollection($record = null, $asJson = true)
    {
        if (is_null($record)) {
            $record = get_current_record('collection');
        }
        elseif (is_numeric($record)) {
            $record = get_record_by_id('Collection', (integer) $record);
        }

        if (empty($record)) {
            return null;
        }

        $recordClass = get_class($record);
        if ($recordClass != 'Collection') {
            return null;
        }
        $result = $this->_buildManifestCollection($record);

        if ($asJson) {
            return version_compare(phpversion(), '5.4.0', '<')
                ? json_encode($result)
                : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        // Return as array
        return $result;
    }

    /**
     * Get the IIIF manifest for the specified collection.
     *
     * @param Collection $collection Collection
     * @return Object|null. The object corresponding to the collection.
     */
    protected function _buildManifestCollection($collection)
    {
        $description = strip_formatting(metadata($collection, array('Dublin Core', 'Description'), array('no_filter' => true)));
        $licence = get_option('universalviewer_licence');
        $attribution = get_option('universalviewer_attribution');

        $elementTexts = get_view()->allElementTexts($collection, array(
            'show_empty_elements' => false,
            // 'show_element_sets' => array('Dublin Core'),
            'return_type' => 'array',
        ));

        $metadata = array();
        foreach ($elementTexts as $elementSetName => $elements) {
            foreach ($elements as $elementName => $values) {
                $metadata[] = (object) array(
                    'label' => $elementName,
                    'value' => count($values) > 1
                       ? $values
                       :  reset($values),
                );
            }
        }

        $collections = array();
        // TODO Finalize display of collections.
        /*
        // Hierarchical list of collections.
        if (plugin_is_active('CollectionTree')) {
            $collectionTree = get_db()->getTable('CollectionTree')->getDescendantTree($collection->id);
            $collections = $this->_buildManifestCollectionTree($collectionTree);
        }
        // Flat list of collections.
        else {
            $collectionsList = get_records('Collection', array(), 0);
            foreach ($collectionsList as $collectionRecord) {
                if ($collectionRecord->id != $collection->id) {
                    $collections[] = $this->_buildManifestBase($collectionRecord);
                }
            }
        }
        */

        // List of manifests inside the collection.
        $manifests = array();
        $items = get_records('Item', array('collection_id' => $collection->id), 0);
        foreach ($items as $item) {
            $manifests[] = $this->_buildManifestBase($item);
        }

        // Prepare manifest.
        $manifest = array();
        $manifest['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        $manifest = array_merge($manifest, $this->_buildManifestBase($collection, false));
        if ($metadata) {
            $manifest['metadata'] = $metadata;
        }
        if ($description) {
           $manifest['description'] = $description;
        }
        if ($licence) {
            $manifest['license'] = $licence;
        }
        if ($attribution) {
            $manifest['attribution'] = $attribution;
        }
        // $manifest['service'] = $service;
        // $manifest['seeAlso'] = $seeAlso;
        // $manifest['within'] = $within;
        if ($collections) {
            $manifest['collections'] = $collections;
        }
        if ($manifests) {
            $manifest['manifests'] = $manifests;
        }
        $manifest = (object) $manifest;

        return $manifest;
    }

    /**
     * Recursive helper to build a hierarchy of collections.
     */
    protected function _buildManifestCollectionTree($collectionTree)
    {
        if (empty($collectionTree)) {
            return;
        }

        $result = array();
        foreach ($collectionTree as $collection) {
            $manifest = array();
            $manifest['@id'] = absolute_url(array(
                'recordtype' => 'collections',
                'id' => $collection['id'],
            ), 'universalviewer_presentation_manifest');
            $manifest['@type'] = 'sc:Collection';
            $manifest['label'] = $collection['name'] ?: __('[Untitled]');
            $children = $this->_buildManifestCollectionTree($collection['children']);
            if ($children) {
                $manifest['collections'] = $children;
            }
            $manifest = (object) $manifest;
            $result[] = $manifest;
        }
        return $result;
    }

    protected function _buildManifestBase($record, $asObject = true)
    {
        $recordClass = get_class($record);
        $manifest = array();
        $manifest['@id'] = absolute_url(array(
            'recordtype' => Inflector::tableize($recordClass),
            'id' => $record->id,
        ), 'universalviewer_presentation_manifest');
        $manifest['@type'] = $recordClass == 'Collection' ? 'sc:Collection' : 'sc:Manifest';
        $manifest['label'] = strip_formatting(metadata($record, array('Dublin Core', 'Title'), array('no_filter' => true))) ?: __('[Untitled]');

        return $asObject
            ? (object) $manifest
            : $manifest;
    }
}
