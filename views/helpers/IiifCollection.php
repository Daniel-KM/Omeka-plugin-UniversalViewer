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
     * @param Collection $record Collection
     * @return Object|null. The object corresponding to the collection.
     */
    protected function _buildManifestCollection($record)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = array(
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => '',
            '@type' => 'sc:Collection',
            'label' => '',
            'description' => '',
            'thumbnail' => '',
            'license' => '',
            'attribution' => '',
            // A logo to add at the end of the information panel.
            'logo' => '',
            'service' => '',
            // For example the web page of the item.
            'related' => '',
            // Other formats of the same data.
            'seeAlso' => '',
            'within' => '',
            'metadata' => array(),
            'collections' => array(),
            'manifests' => array(),
        );

        $manifest = array_merge($manifest, $this->_buildManifestBase($record, false));

        // Prepare the metadata of the record.
        // TODO Manage filter and escape or use $record->getAllElementTexts()?
        $elementTexts = get_view()->allElementTexts($record, array(
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
                        : reset($values),
                );
            }
        }
        $manifest['metadata'] = $metadata;

        // There is no citation for collections.
        // TODO Use option universalviewer_manifest_description_element?
        $description = strip_formatting(metadata($record, array('Dublin Core', 'Description')));
        $manifest['description'] = $description;

        $licenseElement = get_option('universalviewer_manifest_license_element');
        if ($licenseElement) {
            $license = metadata($record, json_decode($licenseElement, true));
        }
        if (empty($license)) {
            $license = get_option('universalviewer_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionElement = get_option('universalviewer_manifest_attribution_element');
        if ($attributionElement) {
            $attribution = metadata($record, json_decode($attributionElement, true));
        }
        if (empty($attribution)) {
            $attribution = get_option('universalviewer_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = get_option('universalviewer_manifest_logo_default');

        // $manifest['thumbnail'] = $thumbnail;
        // $manifest['service'] = $service;
        // TODO To parameter or to extract from metadata (Dublin Core Relation).
        // $manifest['seeAlso'] = $seeAlso;
        // TODO Use within with collection tree.
        // $manifest['within'] = $within;

        // TODO Finalize display of collections.
        /*
        $collections = array();
        // Hierarchical list of collections.
        if (plugin_is_active('CollectionTree')) {
            $collectionTree = get_db()->getTable('CollectionTree')->getDescendantTree($record->id);
            $collections = $this->_buildManifestCollectionTree($collectionTree);
        }
        // Flat list of collections.
        else {
            $collectionsList = get_records('Collection', array(), 0);
            foreach ($collectionsList as $collectionRecord) {
                if ($collectionRecord->id != $record->id) {
                    $collections[] = $this->_buildManifestBase($collectionRecord);
                }
            }
        }
        $manifest['collections'] = $collections;
        */

        // List of manifests inside the collection.
        $manifests = array();
        $items = get_records('Item', array('collection_id' => $record->id), 0);
        foreach ($items as $item) {
            $manifests[] = $this->_buildManifestBase($item);
        }
        $manifest['manifests'] = $manifests;

        $manifest = apply_filters('uv_manifest', $manifest, array('record' => $record));

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);
        return (object) $manifest;
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
                'id' => $collection['id'],
            ), 'universalviewer_presentation_collection');
            $manifest['@id'] = $this->view->uvForceHttpsIfRequired($manifest['@id']);
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

        if ($recordClass == 'Collection') {
            $url = absolute_url(array(
                'id' => $record->id,
            ), 'universalviewer_presentation_collection');

            $type = 'sc:Collection';
        } else {
            $url = absolute_url(array(
                'id' => $record->id,
            ), 'universalviewer_presentation_item');

            $type = 'sc:Manifest';
        }

        $url = $this->view->uvForceHttpsIfRequired($url);
        $manifest['@id'] = $url;

        $manifest['@type'] = $type;

        $label = strip_formatting(metadata($record, array('Dublin Core', 'Title'), array('no_filter' => true))) ?: __('[Untitled]');
        $manifest['label'] = $label;

        return $asObject ? (object) $manifest : $manifest;
    }
}
