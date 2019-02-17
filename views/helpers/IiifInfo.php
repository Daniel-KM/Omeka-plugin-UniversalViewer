<?php
/**
 * Helper to get a IIIF info.json for a file.
 */
class UniversalViewer_View_Helper_IiifInfo extends Zend_View_Helper_Abstract
{
    /**
     * Get the IIIF info for the specified record.
     *
     * @todo Replace all data by standard classes.
     *
     * @see https://iiif.io/api/image/2.1
     *
     * @param Omeka_Record_AbstractRecord|int|null $record
     * @return Object|null
     */
    public function iiifInfo($record = null)
    {
        if (is_null($record)) {
            $record = get_current_record('file', false);
        } elseif (is_numeric($record)) {
            $record = get_record_by_id('File', (int) $record);
        }

        if (empty($record)) {
            return null;
        }

        if (get_class($record) != 'File') {
            return null;
        }

        $file = $record;

        if (strpos($file->mime_type, 'image/') === 0) {
            $sizes = array();
            $availableTypes = array('thumbnail', 'fullsize', 'original');
            foreach ($availableTypes as $imageType) {
                $imageSize = $this->view->imageSize($file, $imageType);
                $size = array();
                $size['width'] = $imageSize['width'];
                $size['height'] = $imageSize['height'];
                $size = (object) $size;
                $sizes[] = $size;
            }

            $imageType = 'original';
            $imageSize = $this->view->imageSize($file, $imageType);
            list($width, $height) = array_values($imageSize);
            $imageUrl = absolute_url(array(
                'id' => $file->id,
            ), 'universalviewer_image');
            $imageUrl = $this->view->uvForceBaseUrlIfRequired($imageUrl);

            $tiles = array();
            $helper = new UniversalViewer_Controller_Action_Helper_TileInfo();
            $tilingData = $helper->tileInfo($file);
            if ($tilingData) {
                $iiifTileInfo = $this->_iiifTileInfo($tilingData);
                if ($iiifTileInfo) {
                    $tiles[] = $iiifTileInfo;
                }
            }

            $profile = array();
            $profile[] = 'http://iiif.io/api/image/2/level2.json';
            // Temporary fix. See https://github.com/UniversalViewer/universalviewer/issues/438.
            $profile[] = array();
            // According to specifications, the profile details should be omitted,
            // because only default formats, qualities and supports are supported
            // currently.
            /*
            $profileDetails = array();
            $profileDetails['format'] = array('jpg');
            $profileDetails['qualities'] = array('default');
            $profileDetails['supports'] = array('sizeByWhListed');
            $profileDetails = (object) $profileDetails;
            $profile[] = $profileDetails;
            */

            // Exemple of service, useless currently.
            /*
            $service = array();
            $service['@context'] = 'http://iiif.io/api/annex/service/physdim/1/context.json';
            $service['profile'] = 'http://iiif.io/api/annex/service/physdim';
            $service['physicalScale'] = 0.0025;
            $service['physicalUnits'] = 'in';
            $service = (object) $service;
            */

            $info = array();
            $info['@context'] = 'http://iiif.io/api/image/2/context.json';
            $info['@id'] = $imageUrl;
            $info['protocol'] = 'http://iiif.io/api/image';
            $info['width'] = $width;
            $info['height'] = $height;
            $info['sizes'] = $sizes;
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
            $info['profile'] = $profile;
        }

        // Else non-image file.
        else {
            $info = array();
            $info['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            );
            $fileUrl = absolute_url(array(
                'id' => $file->id,
            ), 'universalviewer_media');
            $fileUrl = $this->view->uvForceBaseUrlIfRequired($fileUrl);
            $info['@id'] = $fileUrl;
            // See MediaController::contextAction()
            $info['protocol'] = 'http://wellcomelibrary.org/ld/ixif';
        }

        $info = apply_filters('uv_manifest', $info, array(
            'record' => $record,
            'type' => 'file',
        ));

        $info = (object) $info;
        return $info;
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function _iiifTileInfo($tileInfo)
    {
        $tile = array();

        $squaleFactors = array();
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
    }
}
