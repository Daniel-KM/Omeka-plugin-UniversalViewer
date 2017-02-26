<?php
/**
 * The player controller class.
 *
 * @package UniversalViewer
 */
class UniversalViewer_PlayerController extends Omeka_Controller_AbstractActionController
{
    /**
     * Forward to the 'play' action
     *
     * @see self::playAction()
     */
    public function indexAction()
    {
        $this->forward('play');
    }

    public function playAction()
    {
        $id = $this->getParam('id');
        if (empty($id)) {
            throw new Omeka_Controller_Exception_404;
        }

        // Map iiif resources with Omeka Classic and Omeka S records.
        $matchingRecords = array(
            'item' => 'Item',
            'items' => 'Item',
            'item-set' => 'Collection',
            'item_set' => 'Collection',
            'item-sets' => 'Collection',
            'item_sets' => 'Collection',
            'collection' => 'Collection',
            'collections' => 'Collection',
        );
        $recordType = $this->getParam('recordtype');
        if (!isset($matchingRecords[$recordType])) {
            throw new Omeka_Controller_Exception_404;
        }
        $recordType = $matchingRecords[$recordType];

        $record = get_record_by_id($recordType, $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $this->view->record = $record;
    }
}
