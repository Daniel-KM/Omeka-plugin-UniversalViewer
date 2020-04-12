<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

/**
 * @see Zend_Controller_Action_Helper_Abstract
 */
require_once 'Zend/Controller/Action/Helper/Abstract.php';

class UniversalViewer_Controller_Action_Helper_TileInfo extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Extension added to a folder name to store data and tiles for DeepZoom.
     *
     * @var string
     */
    const FOLDER_EXTENSION_DEEPZOOM = '_files';

    /**
     * Extension added to a folder name to store data and tiles for Zoomify.
     *
     * @var string
     */
    const FOLDER_EXTENSION_ZOOMIFY = '_zdata';

    /**
     * Base dir of tiles.
     *
     * @var string
     */
    protected $tileBaseDir;

    /**
     * Base url of tiles.
     *
     * @var string
     */
    protected $tileBaseUrl;

    /**
     * Retrieve info about the tiling of an image.
     *
     * @param File $file
     * @return array|null
     */
    public function tileInfo(File $file)
    {
        // Quick check for possible issue when used outside of the Iiif Server.
        if (strpos($file->mime_type, 'image/') !== 0) {
            return;
        }

        // Check the default dir to avoid the dependency to OpenLayers Zoom.
        $dir = get_option('openlayerszoom_tiles_dir') ?: FILES_DIR . DIRECTORY_SEPARATOR . 'zoom_tiles';
        if (!file_exists($dir) || !is_dir($dir)) {
            return;
        }
        $this->tileBaseDir = $dir;

        $url = get_option('openlayerszoom_tiles_web') ?: WEB_FILES . '/zoom_tiles';
        $this->tileBaseUrl = $url;

        $tilingData = $this->getTilingData($this->baseFilename($file->filename));
        return $tilingData;
    }

    /**
     * Get a filepath without extension, i.e. "/path/file.ext" to "/path/file".
     *
     * @return string
     */
    protected function baseFilename($filepath)
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        return strlen($extension) ? substr($filepath, 0, strrpos($filepath, '.')) : $filepath;
    }

    /**
     * Check if an image is zoomed and return its main data.
     *
     * @internal Path to the storage of tiles:
     * - For Omeka Semantic (DeepZoom): files/tile/storagehash_files
     *   with metadata "storagehash.js" or "storagehash.dzi" and no subdir.
     * - For Omeka Classic (Zoomify): files/zoom_tiles/storagehash_zdata
     *   and, inside it, metadata "ImageProperties.xml" and subdirs "TileGroup{x}".
     *
     * @internal This implementation is compatible with ArchiveRepertory (use of
     * a basename that may be a partial path) and possible alternate adapters.
     *
     * @param string $basename Filename without the extension (storage id).
     * @return array|null
     */
    protected function getTilingData($basename)
    {
        $basepath = $this->tileBaseDir . DIRECTORY_SEPARATOR . $basename . '.dzi';
        if (file_exists($basepath)) {
            $tilingData = $this->getTilingDataDeepzoomDzi($basepath);
            $tilingData['media_path'] = $basename . self::FOLDER_EXTENSION_DEEPZOOM;
            $tilingData['metadata_path'] = $basename . '.dzi';
            return $tilingData;
        }

        $basepath = $this->tileBaseDir . DIRECTORY_SEPARATOR . $basename . '.js';
        if (file_exists($basepath)) {
            $tilingData = $this->getTilingDataDeepzoomJsonp($basepath);
            $tilingData['media_path'] = $basename . self::FOLDER_EXTENSION_DEEPZOOM;
            $tilingData['metadata_path'] = $basename . '.js';
            return $tilingData;
        }

        $basepath = $this->tileBaseDir
            . DIRECTORY_SEPARATOR . $basename . self::FOLDER_EXTENSION_ZOOMIFY
            . DIRECTORY_SEPARATOR . 'ImageProperties.xml';
        if (file_exists($basepath)) {
            $tilingData = $this->getTilingDataZoomify($basepath);
            $tilingData['media_path'] = $basename . self::FOLDER_EXTENSION_ZOOMIFY;
            $tilingData['metadata_path'] = $basename . self::FOLDER_EXTENSION_ZOOMIFY
                . DIRECTORY_SEPARATOR . 'ImageProperties.xml';
            return $tilingData;
        }
    }

    /**
     * Get rendering data from a dzi format.
     *
     * @param string path
     * @return array|null
     */
    protected function getTilingDataDeepzoomDzi($path)
    {
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_PARSEHUGE);
        if ($xml === false) {
            return;
        }
        $data = json_encode($xml);
        $data = json_decode($data, true);

        $tilingData = array();
        $tilingData['tile_type'] = 'deepzoom';
        $tilingData['metadata_path'] = $path;
        $tilingData['media_path'] = '';
        $tilingData['url_base'] = $this->tileBaseUrl;
        $tilingData['path_base'] = $this->tileBaseDir;
        $tilingData['size'] = (int) $data['@attributes']['TileSize'];
        $tilingData['overlap'] = (int) $data['@attributes']['Overlap'];
        $tilingData['total'] = null;
        $tilingData['source']['width'] = (int) $data['Size']['@attributes']['Width'];
        $tilingData['source']['height'] = (int) $data['Size']['@attributes']['Height'];
        $tilingData['format'] = $data['@attributes']['Format'];
        return $tilingData;
    }

    /**
     * Get rendering data from a jsonp format.
     *
     * @param string path
     * @return array|null
     */
    protected function getTilingDataDeepzoomJsonp($path)
    {
        $data = file_get_contents($path);
        // Keep only the json object.
        $data = substr($data, strpos($data, '{'), strrpos($data, '}') - strpos($data, '{') + 1);
        $data = json_decode($data, true);
        $data = $data['Image'];

        $tilingData = array();
        $tilingData['tile_type'] = 'deepzoom';
        $tilingData['metadata_path'] = '';
        $tilingData['media_path'] = '';
        $tilingData['url_base'] = $this->tileBaseUrl;
        $tilingData['path_base'] = $this->tileBaseDir;
        $tilingData['size'] = (int) $data['TileSize'];
        $tilingData['overlap'] = (int) $data['Overlap'];
        $tilingData['total'] = null;
        $tilingData['source']['width'] = (int) $data['Size']['Width'];
        $tilingData['source']['height'] = (int) $data['Size']['Height'];
        $tilingData['format'] = $data['Format'];
        return $tilingData;
    }

    /**
     * Get rendering data from a zoomify format
     *
     * @param string path
     * @return array|null
     */
    protected function getTilingDataZoomify($path)
    {
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_PARSEHUGE);
        if ($xml === false) {
            return;
        }
        $properties = $xml->attributes();
        $properties = reset($properties);

        $tilingData = array();
        $tilingData['tile_type'] = 'zoomify';
        $tilingData['metadata_path'] = '';
        $tilingData['media_path'] = '';
        $tilingData['url_base'] = $this->tileBaseUrl;
        $tilingData['path_base'] = $this->tileBaseDir;
        $tilingData['size'] = (int) $properties['TILESIZE'];
        $tilingData['overlap'] = 0;
        $tilingData['total'] = (int) $properties['NUMTILES'];
        $tilingData['source']['width'] = (int) $properties['WIDTH'];
        $tilingData['source']['height'] = (int) $properties['HEIGHT'];
        $tilingData['format'] = isset($properties['FORMAT'])
            ? $properties['FORMAT']
            : 'jpg';
        return $tilingData;
    }
}
