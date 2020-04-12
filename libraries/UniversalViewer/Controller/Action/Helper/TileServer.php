<?php

/*
 * Copyright 2015-2018 Daniel Berthereau
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

class UniversalViewer_Controller_Action_Helper_TileServer extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Retrieve tiles for an image, if any, according to the required transform.
     *
     * @note This tile server was tested only with images tiled from the
     * integrated tiler.
     *
     * @internal Because the position of the requested region may be anything
     * (it depends of the client), until four images may be needed to build the
     * resulting image. It's always quicker to reassemble them rather than
     * extracting the part from the full image, specially with big ones.
     * Nevertheless, OpenSeadragon tries to ask 0-based tiles, so only this case
     * is managed currently.
     * @todo For non standard requests, the tiled images may be used to rebuild
     * a fullsize image that is larger the Omeka derivatives. In that case,
     * multiple tiles should be joined.
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    public function tileServer(array $tileInfo, array $transform)
    {
        if (empty($tileInfo)) {
            return;
        }

        // Quick check of supported transformation of tiles.
        if (!in_array($transform['region']['feature'], array('regionByPx', 'full'))
            || !in_array($transform['size']['feature'], array('sizeByW', 'sizeByH', 'sizeByWh', 'sizeByWhListed', 'full'))
        ) {
            return;
        }

        switch ($tileInfo['tile_type']) {
            case 'deepzoom':
                $tile = $this->serveTilesDeepzoom($tileInfo, $transform);
                return $tile;

            case 'zoomify':
                $tile = $this->serveTilesZoomify($tileInfo, $transform);
                return $tile;
        }
    }

    /**
     * Retrieve the data for a transformation.
     *
     * @internal See a client implementation of the converter in OpenSeadragon.
     * @see https://github.com/openseadragon/openseadragon/blob/master/src/iiiftilesource.js
     * @see https://gist.github.com/jpstroop/4624253#file-dzi_to_iiif_2-py
     * @see /src/libraries/Deepzoom/Deepzoom.php
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    protected function serveTilesDeepzoom($tileInfo, $transform)
    {
        $data = $this->getLevelAndPosition(
            $tileInfo,
            $transform['source'],
            $transform['region'],
            $transform['size'],
            true
        );
        if (is_null($data)) {
            return;
        }

        // To manage Windows, the same path cannot be used for url and local.
        $relativeUrl = sprintf(
            '%d/%d_%d.jpg',
            $data['level'],
            $data['column'],
            $data['row']
        );
        $relativePath = sprintf(
            '%d%s%d_%d.jpg',
            $data['level'],
            DIRECTORY_SEPARATOR,
            $data['column'],
            $data['row']
        );

        return $this->serveTiles($tileInfo, $data, $relativeUrl, $relativePath);
    }

    /**
     * Retrieve the data for a transformation.
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    protected function serveTilesZoomify($tileInfo, $transform)
    {
        $data = $this->getLevelAndPosition(
            $tileInfo,
            $transform['source'],
            $transform['region'],
            $transform['size'],
            false
        );
        if (is_null($data)) {
            return;
        }

        $imageSize = array(
            'width' => $transform['source']['width'],
            'height' => $transform['source']['height'],
        );
        $tileGroup = $this->getTileGroup($imageSize, $data);
        if (is_null($tileGroup)) {
            return;
        }

        // To manage Windows, the same path cannot be used for url and local.
        $relativeUrl = sprintf(
            'TileGroup%d/%d-%d-%d.jpg',
            $tileGroup,
            $data['level'],
            $data['column'],
            $data['row']
        );
        $relativePath = sprintf(
            'TileGroup%d%s%d-%d-%d.jpg',
            $tileGroup,
            DIRECTORY_SEPARATOR,
            $data['level'],
            $data['column'],
            $data['row']
        );

        return $this->serveTiles($tileInfo, $data, $relativeUrl, $relativePath);
    }

    /**
     * Retrieve the data for a transformation.
     *
     * @param array $tileInfo
     * @param array $cellData
     * @param string $relativeUrl
     * @param string $relativePath
     * @return array
     */
    protected function serveTiles($tileInfo, $cellData, $relativeUrl, $relativePath)
    {
        // The image url is used when there is no transformation.
        $imageUrl = $tileInfo['url_base']
            . '/' . $tileInfo['media_path']
            . '/' . $relativeUrl;
        $imagePath = $tileInfo['path_base']
            . DIRECTORY_SEPARATOR . $tileInfo['media_path']
            . DIRECTORY_SEPARATOR . $relativePath;

        list($tileWidth, $tileHeight) = array_values($this->getWidthAndHeight($imagePath));

        $result = array(
            'fileurl' => $imageUrl,
            'filepath' => $imagePath,
            'derivativeType' => 'tile',
            'media_type' => 'image/jpeg',
            'width' => $tileWidth,
            'height' => $tileHeight,
            'overlap' => $tileInfo['overlap'],
        );
        return $result + $cellData;
    }

    /**
     * Get the level and the position of the cell from the source and region.
     *
     * @param array $tileInfo
     * @param array $source
     * @param array $region
     * @param array $size
     * @param bool $isOneBased True if the pyramid starts at 1x1, or false if
     * it starts at the tile size.
     * @return array|null
     */
    protected function getLevelAndPosition($tileInfo, $source, $region, $size, $isOneBased)
    {
        // Initialize with default values.
        $level = 0;
        $cellX = 0;
        $cellY = 0;
        // TODO A bigger size can be requested directly, and, in that case,
        // multiple tiles should be joined. Currently managed via the dynamic
        // processor.
        $cellSize = $tileInfo['size'];

        // Check if the tile may be cropped.
        $isFirstColumn = $region['x'] == 0;
        $isFirstRow = $region['y'] == 0;
        $isFirstCell = $isFirstColumn && $isFirstRow;
        $isLastColumn = $source['width'] == ($region['x'] + $region['width']);
        $isLastRow = $source['height'] == ($region['y'] + $region['height']);
        $isLastCell = $isLastColumn && $isLastRow;
        $isSingleCell = $isFirstCell && $isLastCell;

        // No process is needed when the requested cell is single.
        if (!$isSingleCell) {
            // Determine the position of the cell from the source and the
            // region.
            switch ($size['feature']) {
                case 'sizeByW':
                    if ($isLastColumn) {
                        // Normal row. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use row, because Deepzoom and Zoomify tiles are
                            // square by default.
                            // TODO Manage the case where tiles are not square.
                            $count = (int) ceil(max($source['width'], $source['height']) / $region['height']);
                            $cellX = $region['x'] / $region['height'];
                            $cellY = $region['y'] / $region['height'];
                        }
                    }
                    // Normal column and normal region.
                    else {
                        $count = (int) ceil(max($source['width'], $source['height']) / $region['width']);
                        $cellX = $region['x'] / $region['width'];
                        $cellY = $region['y'] / $region['width'];
                    }
                    break;

                case 'sizeByH':
                    if ($isLastRow) {
                        // Normal column. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use column, because tiles are square.
                            $count = (int) ceil(max($source['width'], $source['height']) / $region['width']);
                            $cellX = $region['x'] / $region['width'];
                            $cellY = $region['y'] / $region['width'];
                        }
                    }
                    // Normal row and normal region.
                    else {
                        $count = (int) ceil(max($source['width'], $source['height']) / $region['height']);
                        $cellX = $region['x'] / $region['height'];
                        $cellY = $region['y'] / $region['height'];
                    }
                    break;

                case 'sizeByWh':
                case 'sizeByWhListed':
                    // TODO To improve.
                    if ($isLastColumn) {
                        // Normal row. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use row, because tiles are square.
                            $count = (int) ceil(max($source['width'], $source['height']) / $region['height']);
                            $cellX = $region['x'] / $region['width'];
                            $cellY = $region['y'] / $region['height'];
                        }
                    }
                    // Normal column and normal region.
                    else {
                        $count = (int) ceil(max($source['width'], $source['height']) / $region['width']);
                        $cellX = $region['x'] / $region['width'];
                        $cellY = $region['y'] / $region['height'];
                    }
                    break;

                case 'full':
                    // TODO To be checked.
                    // Normalize the size, but they can be cropped.
                    $size['width'] = $region['width'];
                    $size['height'] = $region['height'];
                    $count = (int) ceil(max($source['width'], $source['height']) / $region['width']);
                    $cellX = $region['x'] / $region['width'];
                    $cellY = $region['y'] / $region['height'];
                    break;

                default:
                    return;
            }

            // Get the list of squale factors.
            $maxDimension = max(array($source['width'], $source['height']));
            $numLevels = $this->getNumLevels($maxDimension);
            // In IIIF, levels start at the tile size.
            $numLevels -= (int) log($cellSize, 2);
            $squaleFactors = $this->getScaleFactors($numLevels);
            // TODO Find why maxSize and total were needed.
            // $maxSize = max($source['width'], $source['height']);
            // $total = (int) ceil($maxSize / $tileInfo['size']);
            // If level is set, count is not set and useless.
            $level = isset($level) ? $level : 0;
            $count = isset($count) ? $count : 0;
            foreach ($squaleFactors as $squaleFactor) {
                if ($squaleFactor >= $count) {
                    break;
                }
                ++$level;
            }

            // Process the last cell, an exception because it may be cropped.
            if ($isLastCell) {
                // TODO Quick check if the last cell is a standard one (not cropped)?
                // Because the default size of the region lacks, it is simpler
                // to check if an image of the zoomed file is the same using the
                // tile size from properties, for each possible factor.
                $reversedSqualeFactors = array_reverse($squaleFactors);
                $isLevelFound = false;
                foreach ($reversedSqualeFactors as $level => $reversedFactor) {
                    $tileFactor = $reversedFactor * $tileInfo['size'];
                    $countX = (int) ceil($source['width'] / $tileFactor);
                    $countY = (int) ceil($source['height'] / $tileFactor);
                    $lastRegionWidth = $source['width'] - (($countX - 1) * $tileFactor);
                    $lastRegionHeight = $source['height'] - (($countY - 1) * $tileFactor);
                    $lastRegionX = $source['width'] - $lastRegionWidth;
                    $lastRegionY = $source['height'] - $lastRegionHeight;
                    if ($region['x'] == $lastRegionX
                        && $region['y'] == $lastRegionY
                        && $region['width'] == $lastRegionWidth
                        && $region['height'] == $lastRegionHeight
                    ) {
                        // Level is found.
                        $isLevelFound = true;
                        // Cells are 0-based.
                        $cellX = $countX - 1;
                        $cellY = $countY - 1;
                        break;
                    }
                }
                if (!$isLevelFound) {
                    return;
                }
            }
        }

        // TODO Check if the cell size is the required one (always true for image tiled here).

        if ($isOneBased) {
            $level += (int) log($cellSize, 2);
        }

        return array(
            'level' => $level,
            'column' => $cellX,
            'row' => $cellY,
            'size' => $cellSize,
            'isFirstColumn' => $isFirstColumn,
            'isFirstRow' => $isFirstRow,
            'isFirstCell' => $isFirstCell,
            'isLastColumn' => $isLastColumn,
            'isLastRow' => $isLastRow,
            'isLastCell' => $isLastCell,
            'isSingleCell' => $isSingleCell,
        );
    }

    /**
     * Get the number of levels in the pyramid (first level has a size of 1x1).
     *
     * @param int $maxDimension
     * @return int
     */
    protected function getNumLevels($maxDimension)
    {
        $result = (int) ceil(log($maxDimension, 2)) + 1;
        return $result;
    }

    /**
     * Get the scale factors.
     *
     * @internal Check the number of levels (1-based or tile based) before.
     *
     * @param int $numLevels
     * @return array
     */
    protected function getScaleFactors($numLevels)
    {
        $result = array();
        foreach (range(0, $numLevels - 1) as $level) {
            $result[] = pow(2, $level);
        }
        return $result;
    }

    /**
     * Return the tile group of a tile from level, position and size.
     *
     * @link https://github.com/openlayers/openlayers/blob/v4.0.0/src/ol/source/zoomify.js
     *
     * @param array $image
     * @param array $tile
     * @return int|null
     */
    protected function getTileGroup($image, $tile)
    {
        if (empty($image) || empty($tile)) {
            return;
        }

        $tierSizeCalculation = 'default';
        // $tierSizeCalculation = 'truncated';

        $tierSizeInTiles = array();

        switch ($tierSizeCalculation) {
            case 'default':
                $tileSize = $tile['size'];
                while ($image['width'] > $tileSize || $image['height'] > $tileSize) {
                    $tierSizeInTiles[] = array(
                        ceil($image['width'] / $tileSize),
                        ceil($image['height'] / $tileSize),
                    );
                    $tileSize += $tileSize;
                }
                break;

            case 'truncated':
                $width = $image['width'];
                $height = $image['height'];
                while ($width > $tile['size'] || $height > $tile['size']) {
                    $tierSizeInTiles[] = array(
                        ceil($width / $tile['size']),
                        ceil($height / $tile['size']),
                    );
                    $width >>= 1;
                    $height >>= 1;
                }
                break;

            default:
                return;
        }

        $tierSizeInTiles[] = array(1, 1);
        $tierSizeInTiles = array_reverse($tierSizeInTiles);

        $tileCountUpToTier = array(0);
        for ($i = 1, $ii = count($tierSizeInTiles); $i < $ii; $i++) {
            $tileCountUpToTier[] =
                $tierSizeInTiles[$i - 1][0] * $tierSizeInTiles[$i - 1][1]
                + $tileCountUpToTier[$i - 1];
        }

        $tileIndex = $tile['column']
            + $tile['row'] * $tierSizeInTiles[$tile['level']][0]
            + $tileCountUpToTier[$tile['level']];
        $tileGroup = ($tileIndex / $tile['size']) ?: 0;
        return (int) $tileGroup;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     */
    protected function getWidthAndHeight($filepath)
    {
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempname = tempnam(sys_get_temp_dir(), 'uv_');
            $result = file_put_contents($tempname, $filepath);
            if ($result !== false) {
                $result = getimagesize($filepath);
                if ($result) {
                    list($width, $height) = $result;
                }
                unlink($tempname);
            }
        } elseif (file_exists($filepath)) {
            list($width, $height) = getimagesize($filepath);
            if ($result) {
                list($width, $height) = $result;
            }
        }

        if (empty($width) || empty($height)) {
            return array(
                'width' => null,
                'height' => null,
            );
        }

        return array(
            'width' => $width,
            'height' => $height,
        );
    }
}
