<?php
if (!empty($attachments)):
    $attachment = reset($attachments);
    // Display the viewer with the specified item and specified config.
    echo $this->universalViewer(array(
        'type' => 'Item',
        'id' => $attachment->item_id,
    ));
endif;
?>
