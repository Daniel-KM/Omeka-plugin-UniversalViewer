<?php
/**
 * Helper to get a IIIF Collection manifest for a dynamic list.
 */
class UniversalViewer_View_Helper_IiifCollectionList extends Zend_View_Helper_Abstract
{
    /**
     * Get the IIIF Collection manifest for the specified list of records.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values and a standard json() without ld.
     * @see UniversalViewer_View_Helper_IiifManifest
     *
     * @param array $records Array of records.
     * @return Object|null
     */
    public function iiifCollectionList($records)
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

        $identifier = $this->_buildIdentifierForList($records);
        $route = 'universalviewer_presentation_collection_list';
        $url = absolute_url(array(
            'id' => $identifier,
        ), $route);
        $url = $this->view->uvForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        $label = __('Dynamic List');
        $manifest['label'] = $label;

        // TODO The dynamic list has no metadata. Use the query?

        $license = get_option('universalviewer_manifest_license_default');
        $manifest['license'] = $license;

        $attribution = get_option('universalviewer_manifest_attribution_default');
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = get_option('universalviewer_manifest_logo_default');

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        // Anyway, there is no true service for Omeka Classic.
        $metadata['service'] = array(
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        );
         */

        /*
        $manifest['related'] = array(
            '@id' => $this->view->uvForceBaseUrlIfRequired(record_url($query, 'browse', true)),
            'format' => 'text/html',
        );

        // There is no true service for Omeka Classic, and it’s disabled by default.
        $manifest['seeAlso'] = array(
        );
         */

        // List of the manifest of each record. IIIF v2.0 separates collections
        // and items, so the global order is not kept for them.
        $collections = array();
        $manifests = array();
        foreach ($records as $record) {
            if (get_class($record) == 'Collection') {
                $collections[] = $this->_buildManifestBase($record);
            } else {
                $manifests[] = $this->_buildManifestBase($record);
            }
        }
        $manifest['collections'] = $collections;
        $manifest['manifests'] = $manifests;

        $manifest = apply_filters('uv_manifest', $manifest, array(
            // "records" is kept for compatibility with existing plugins.
            'records' => $records,
            'record' => $records,
            'type' => 'collection_list',
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

    /**
     * Helper to create an identifier from a list of records.
     *
     * The dynamic identifier is a flat list of ids: "c-5i-1,i-2,c-3".
     * If there is only one id, a comma is added to avoid to have the same route
     * than the collection itself.
     * If there are only items, the most common case, the dynamic identifier is
     * simplified: "1,2,6". In all cases the order of records is kept.
     *
     * @todo Merge with UniversalViewer_View_Helper_UniversalViewer::_buildIdentifierForList()
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
}
