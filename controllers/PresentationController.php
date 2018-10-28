<?php
/**
 * The presentation controller class.
 *
 * @package UniversalViewer
 */
class UniversalViewer_PresentationController extends Omeka_Controller_AbstractActionController
{
    /**
     * Forward to the 'manifest' action.
     *
     * @internal Unlike info.json, the redirect is not required.
     *
     * @see self::manifestAction()
     */
    public function indexAction()
    {
        $this->forward('manifest');
    }

    public function manifestAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        if (strtok($id, '/') == 'collection') {
            $this->setParam('id', strtok('/'));
            $this->collectionAction();
        } else {
            $this->itemAction();
        }
    }

    public function collectionAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        if (!is_numeric($id)) {
            return $this->listAction();
        }

        $record = get_record_by_id('Collection', $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $manifest = get_view()->iiifCollection($record);

        $this->_helper->viewRenderer->setNoRender();
        $helper = new UniversalViewer_Controller_Action_Helper_JsonLd();
        $helper->jsonLd($manifest);
    }

    public function listAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        $identifiers = array_filter(explode(',', $id));

        $map = array(
            'i' => 'Item',
            'c' => 'Collection',
            'm' => 'File',
        );

        // Extract the records from the identifier.
        $records = array();
        foreach ($identifiers as $identifier) {
            $identifier = str_replace('-', '', $identifier);
            if (is_numeric($identifier)) {
                $recordType = 'Item';
                $recordId = (int) $identifier;
            } else {
                $recordType = substr($identifier, 0, 1);
                if (!isset($map[$recordType])) {
                    continue;
                }
                $recordType = $map[$recordType];
                $recordId = (int) substr($identifier, 1);
            }
            if ($recordId) {
                $record = get_record_by_id($recordType, $recordId);
                if ($record) {
                    $records[] = $record;
                }
            }
        }

        if (empty($records)) {
            throw new Omeka_Controller_Exception_404;
        }

        $manifest = get_view()->iiifCollectionList($records);

        $this->_helper->viewRenderer->setNoRender();
        $helper = new UniversalViewer_Controller_Action_Helper_JsonLd();
        $helper->jsonLd($manifest);
    }

    public function itemAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        $record = get_record_by_id('Item', $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $manifest = get_view()->iiifManifest($record);

        $this->_helper->viewRenderer->setNoRender();
        $helper = new UniversalViewer_Controller_Action_Helper_JsonLd();
        $helper->jsonLd($manifest);
    }
}
