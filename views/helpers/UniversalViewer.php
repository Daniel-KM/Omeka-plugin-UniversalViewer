<?php
/**
 * Helper to display a Universal Viewer
 */
class UniversalViewer_View_Helper_UniversalViewer extends Zend_View_Helper_Abstract
{

    /**
     * Get the specified UniversalViewer.
     *
     * @param array $args Associative array of optional values:
     *   - (string) id: The unique main id.
     *   - (integer|Record) record: The record is the item if it's an integer.
     *   - (string) type: Type of record if record is integer (item by default).
     *   - (integer|Item) item
     *   - (integer|Collection) collection
     *   - (integer|File) file
     *   - (string) class
     *   - (string) width
     *   - (string) height
     *   - (string) locale
     * The only one record is defined according to the priority above.
     * @return string. The html string corresponding to the UniversalViewer.
     */
    public function universalViewer($args = array())
    {
        $record = $this->_getRecord($args);
        if (empty($record)) {
            return '';
        }

        // Some specific checks.
        switch (get_class($record)) {
            case 'Item':
                // Currently, item without files is unprocessable.
                if ($record->fileCount() == 0) {
                    return __('This item has no files and is not displayable.');
                }
                break;
            case 'Collection':
                if ($record->totalItems() == 0) {
                    return __('This collection has no item and is not displayable.');
                }
                break;
        }

        $class = isset($args['class'])
            ? $args['class']
            : get_option('universalviewer_class');
        if (!empty($class)) {
            $class = ' ' . $class;
        }
        $width = isset($args['width'])
            ? $args['width']
            : get_option('universalviewer_width');
        if (!empty($width)) {
            $width = ' width:' . $width . ';';
        }
        $height = isset($args['height'])
            ? $args['height']
            : get_option('universalviewer_height');
        if (!empty($height)) {
            $height = ' height:' . $height . ';';
        }
        $locale = isset($args['locale'])
            ? $args['locale']
            : get_option('universalviewer_locale');
        if (!empty($locale)) {
            $locale = ' data-locale="' . $locale . '"';
        }

        $urlManifest = absolute_url(array(
                'recordtype' => Inflector::tableize(get_class($record)),
                'id' => $record->id,
            ), 'universalviewer_presentation_manifest');

        $config = src('config', 'universal-viewer', 'json');
        $urlJs = src('embed', 'javascripts/uv/lib', 'js');

        $html = sprintf('<div class="uv%s" data-config="%s" data-uri="%s"%s style="background-color: #000;%s%s"></div>',
            $class,
            $config,
            $urlManifest,
            $locale,
            $width,
            $height);
        $html .= sprintf('<script type="text/javascript" id="embedUV" src="%s"></script>', $urlJs);
        $html .= '<script type="text/javascript">/* wordpress fix */</script>';
        return $html;
    }

    protected function _getRecord($args)
    {
        $record = null;
        if (!empty($args['id'])) {
            // Currently only item.
            $record = get_record_by_id('Item', (integer) $args['id']);
        }
        elseif (!empty($args['record'])) {
            if (is_numeric($args['record'])) {
                if (isset($args['record'])
                        && in_array(ucfirst($args['record']), array('Item', 'Collection', 'File'))
                    ) {
                    $record = get_record_by_id(ucfirst($args['type']), (integer) $args['record']);
                }
            }
            elseif (in_array(get_class($args['record']), array('Item', 'Collection', 'File'))) {
                $record = $args['record'];
            }
        }
        elseif (!empty($args['item'])) {
            if (is_numeric($args['item'])) {
                $record = get_record_by_id('Item', (integer) $args['item']);
            }
            else {
                $record = $args['item'];
            }
        }
        elseif (!empty($args['collection'])) {
            if (is_numeric($args['collection'])) {
                $record = get_record_by_id('Collection', (integer) $args['collection']);
            }
            else {
                $record = $args['collection'];
            }
        }
        elseif (!empty($args['file'])) {
            if (is_numeric($args['file'])) {
                $record = get_record_by_id('File', (integer) $args['file']);
            }
            else {
                $record = $args['file'];
            }
        }
        else {
            try {
                $record = get_current_record('item');
            } catch (Exception $e) {
            }
            if (empty($record)) {
                try {
                    $record = get_current_record('collection');
                } catch (Exception $e) {
                }
                if (empty($record)) {
                    try {
                        $record = get_current_record('file');
                    } catch (Exception $e) {
                    }
                }
            }
        }
        return $record;
    }
}
