<?php
/**
 * Helper to get a IIIF Collection manifest for a collection.
 */
class UniversalViewer_View_Helper_IiifCollection extends Zend_View_Helper_Abstract
{
    /**
     * Get the IIIF Collection manifest for the specified collection.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values and a standard json() without ld.
     * @see UniversalViewer_View_Helper_IiifManifest
     *
     * @param Collection $collection
     * @return Object|null
     */
    public function iiifCollection(Collection $collection)
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

        $manifest = array_merge($manifest, $this->_buildManifestBase($collection));

        // Prepare the metadata of the record.
        // TODO Manage filter and escape or use $collection->getAllElementTexts()?
        $elementTexts = get_view()->allElementTexts($collection, array(
            'show_empty_elements' => false,
            // 'show_element_sets' => array('Dublin Core'),
            'return_type' => 'array',
        ));

        $metadata = array();
        foreach ($elementTexts as $elements) {
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
        $description = strip_formatting(metadata($collection, array('Dublin Core', 'Description')));
        $manifest['description'] = $description;

        $licenseElement = get_option('universalviewer_manifest_license_element');
        if ($licenseElement) {
            $license = metadata($collection, json_decode($licenseElement, true));
        }
        if (empty($license)) {
            $license = get_option('universalviewer_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionElement = get_option('universalviewer_manifest_attribution_element');
        if ($attributionElement) {
            $attribution = metadata($collection, json_decode($attributionElement, true));
        }
        if (empty($attribution)) {
            $attribution = get_option('universalviewer_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = get_option('universalviewer_manifest_logo_default');

        // $manifest['thumbnail'] = $thumbnail;

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        // Anyway, there is no true service for Omeka Classic.
        $metadata['service'] = array(
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        );
        */

        $manifest['related'] = array(
            '@id' => $this->view->uvForceBaseUrlIfRequired(record_url($collection, 'show', true)),
            'format' => 'text/html',
        );

        /*
        // There is no true service for Omeka Classic, and it’s disabled by default.
        $manifest['seeAlso'] = array(
        );
         */

        // TODO Use within with collection tree.
        // $manifest['within'] = $within;

        // TODO Finalize display of collections.
        /*
        $collections = array();
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
        $manifest['collections'] = $collections;
        */

        // List of manifests inside the collection.
        $manifests = array();
        $items = get_records('Item', array('collection_id' => $collection->id), 0);
        foreach ($items as $item) {
            $manifests[] = $this->_buildManifestBase($item);
        }
        $manifest['manifests'] = $manifests;

        $manifest = apply_filters('uv_manifest', $manifest, array(
            'record' => $collection,
            'type' => 'collection',
        ));

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        // Keep at least "manifests", even if no member.
        if (empty($manifest['collections']) && empty($manifest['manifests'])) {
            $manifest['manifests'] = array();
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
                'id' => $collection['id'],
            ), 'universalviewer_presentation_collection');
            $manifest['@id'] = $this->view->uvForceBaseUrlIfRequired($manifest['@id']);
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

    protected function _buildManifestBase($record)
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

        $url = $this->view->uvForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        $manifest['@type'] = $type;

        $label = strip_formatting(metadata($record, array('Dublin Core', 'Title'), array('no_filter' => true))) ?: __('[Untitled]');
        $manifest['label'] = $label;

        return $manifest;
    }
}
