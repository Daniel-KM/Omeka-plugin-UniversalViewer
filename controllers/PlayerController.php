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

        $recordType = $this->getParam('recordtype');
        $record = get_record_by_id(Inflector::classify($recordType), $id);
        if (empty($record)) {
            throw new Omeka_Controller_Exception_404;
        }

        $this->view->record = $record;
    }
}
