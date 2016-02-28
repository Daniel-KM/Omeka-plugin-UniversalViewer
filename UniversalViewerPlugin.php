<?php
/**
 * Universal Viewer
 *
 * This plugin integrates the Universal Viewer, the open sourced viewer taht is
 * the successor of the Wellcome Viewer of Digirati, into Omeka.
 *
 * @copyright Daniel Berthereau, 2015
 * @license https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
 * @license https://github.com/UniversalViewer/universalviewer/blob/master/LICENSE.txt (viewer)
 *  */

/**
 * The Universal Viewer plugin.
 * @package Omeka\Plugins\UniversalViewer
 */
class UniversalViewerPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'initialize',
        'config_form',
        'config',
        'define_routes',
        'admin_items_batch_edit_form',
        'items_batch_edit_custom',
        'public_collections_show',
        'public_items_show',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        // It's a checkbox, so no error can be done.
        // 'items_batch_edit_error',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        'universalviewer_append_collections_show' => true,
        'universalviewer_append_items_show' => true,
        'universalviewer_max_dynamic_size' => 50000000,
        'universalviewer_licence' => 'http://www.example.org/license.html',
        'universalviewer_attribution' => 'Provided by Example Organization',
        'universalviewer_class' => '',
        'universalviewer_width' => '95%',
        'universalviewer_height' => '600px',
        'universalviewer_locale' => 'en-GB:English (GB),fr-FR:French',
        'universalviewer_iiif_creator' => 'Auto',
    );

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $processors = $this->_getProcessors();
        if (count($processors) == 1) {
            throw new Omeka_Plugin_Exception(__('At least one graphic processor (GD or ImageMagick) is required to use the UniversalViewer.'));
        }

        $js = dirname(__FILE__)
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'shared'
            . DIRECTORY_SEPARATOR . 'javascripts'
            . DIRECTORY_SEPARATOR . 'uv'
            . DIRECTORY_SEPARATOR . 'lib'
            . DIRECTORY_SEPARATOR . 'embed.js';
        if (!file_exists($js)) {
            throw new Omeka_Plugin_Exception(__('UniversalViewer library should be installed. See %sReadme%s.',
                '<a href="https://github.com/Daniel-KM/UniversalViewer4Omeka#installation">', '</a>'));
        }

        $this->_installOptions();
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    /**
     * Initialize the plugin.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
        add_shortcode('uv', array($this, 'shortcodeUniversalViewer'));
    }

    /**
     * Shows plugin configuration page.
     *
     * @return void
     */
    public function hookConfigForm($args)
    {
        $view = get_view();

        $processors = $this->_getProcessors();

        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        if (count($processors) == 1) {
            $flash->addMessage(__("Warning: No graphic library is installed: Universaliewer can't work.",
                '<strong>', '</strong>'), 'error');
            echo flash();
        }

        if (!isset($processors['Imagick'])) {
            $flash->addMessage(__('Warning: Imagick is not installed: Only standard images (jpg, png, gif and webp) will be processed.',
                '<strong>', '</strong>'), 'info');
            echo flash();
        }

        echo $view->partial(
            'plugins/universal-viewer-config-form.php',
            array(
                'processors' => $processors,
            )
        );
    }

    /**
     * Processes the configuration form.
     *
     * @param array Options set in the config form.
     * @return void
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
        }
    }

    /**
     * Defines public routes.
     *
     * @return void
     */
    public function hookDefineRoutes($args)
    {
        if (is_admin_theme()) {
            return;
        }

        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
    }

    /**
     * Add a partial batch edit form.
     *
     * @return void
     */
    public function hookAdminItemsBatchEditForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'forms/universal-viewer-batch-edit.php'
        );
    }

    /**
     * Process the partial batch edit form.
     *
     * @return void
     */
    public function hookItemsBatchEditCustom($args)
    {
        $item = $args['item'];
        $orderByFilename = $args['custom']['universalviewer']['orderByFilename'];
        $mixImages = $args['custom']['universalviewer']['mixImages'];
        $checkImageSize = $args['custom']['universalviewer']['checkImageSize'];

        if ($orderByFilename) {
            $this->_sortFiles($item, (boolean) $mixImages);
        }

        if ($checkImageSize) {
            $this->_checkImageSize($item);
        }
    }

    /**
     * Sort all files of an item by name and eventually sort images first.
     *
     * @param Item $item
     * @param boolean $mixImages
     * @return void
     */
    protected function _sortFiles($item, $mixImages = false)
    {
        if ($item->fileCount() < 2) {
            return;
        }

        $list = $item->Files;
        // Make a sort by name before sort by type.
        usort($list, function($fileA, $fileB) {
            return strcmp($fileA->original_filename, $fileB->original_filename);
        });
        // The sort by type doesn't remix all filenames.
        if (!$mixImages) {
            $images = array();
            $nonImages = array();
            foreach ($list as $file) {
                // Image.
                if (strpos($file->mime_type, 'image/') === 0) {
                    $images[] = $file;
                }
                // Non image.
                else {
                    $nonImages[] = $file;
                }
            }
            $list = array_merge($images, $nonImages);
        }

        // To avoid issues with unique index when updating (order should be
        // unique for each file of an item), all orders are reset to null before
        // true process.
        $db = $this->_db;
        $bind = array(
            $item->id,
        );
        $sql = "
            UPDATE `$db->File` files
            SET files.order = NULL
            WHERE files.item_id = ?
        ";
        $db->query($sql, $bind);

        // To avoid multiple updates, a single query is used.
        foreach ($list as &$file) {
            $file = $file->id;
        }
        // The array is made unique, because a file can be repeated.
        $list = implode(',', array_unique($list));
        $sql = "
            UPDATE `$db->File` files
            SET files.order = FIND_IN_SET(files.id, '$list')
            WHERE files.id in ($list)
        ";
        $db->query($sql);
    }

   /**
     * Rebuild missing metadata of files.
     *
     * @param Item $item
     * @return void
     */
    protected function _checkImageSize($item)
    {
        foreach ($item->Files as $file) {
            if (!$file->hasThumbnail() || strpos($file->mime_type, 'image/') !== 0) {
                continue;
            }
            $metadata = json_decode($file->metadata, true);
            if (empty($metadata)) {
                $metadata = array();
            }
            // Check if resolution is set.
            elseif (!empty($metadata['video']['resolution_x']) && !empty($metadata['video']['resolution_y'])) {
                continue;
            }

            // Set the resolution directly.
            $imageType = 'original';
            // The storage adapter should be checked for external storage.
            $storageAdapter = $file->getStorage()->getAdapter();
            $filepath = get_class($storageAdapter) == 'Omeka_Storage_Adapter_Filesystem'
                ? FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath($imageType)
                : $file->getWebPath($imageType);
            list($width, $height, $type, $attr) = getimagesize($filepath);
            $metadata['video']['resolution_x'] = $width;
            $metadata['video']['resolution_y'] = $height;
            $file->metadata = version_compare(phpversion(), '5.4.0', '<')
                ? json_encode($metadata)
                : json_encode($metadata, JSON_UNESCAPED_SLASHES);
            $file->save();
        }
    }

    /**
     * Hook to display viewer.
     *
     * @param array $args
     *
     * @return void
     */
    public function hookPublicCollectionsShow($args)
    {
        if (!get_option('universalviewer_append_collections_show')) {
            return;
        }
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        echo $args['view']->universalViewer($args);
    }

    /**
     * Hook to display viewer.
     *
     * @param array $args
     *
     * @return void
     */
    public function hookPublicItemsShow($args)
    {
        if (!get_option('universalviewer_append_items_show')) {
            return;
        }
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        echo $args['view']->universalViewer($args);
    }

    /**
     * Shortcode to display viewer.
     *
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public static function shortcodeUniversalViewer($args, $view)
    {
        $args['view'] = $view;
        return $view->universalViewer($args);
    }

    /**
     * Check and return the list of available processors.
     *
     * @return array Associative array of available processors.
     */
    protected function _getProcessors()
    {
        $processors = array(
            'Auto' => __('Automatic'),
        );
        if (extension_loaded('gd')) {
            $processors['GD'] = 'GD';
        }
        if (extension_loaded('imagick')) {
            $processors['Imagick'] = 'ImageMagick';
        }

        return $processors;
    }
}
