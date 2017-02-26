<?php
if (!empty($attachments)):
    $attachment = reset($attachments);
    $item = $attachment->getItem();
    // Display the viewer with the specified item and specified config.
    echo $this->universalViewer($item);
endif;
?>
