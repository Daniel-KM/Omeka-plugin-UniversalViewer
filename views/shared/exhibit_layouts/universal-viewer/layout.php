<?php
$records = array();
foreach ($attachments as $attachment) {
    $item = $attachment->getItem();
    if ($item) {
        $records[] = $item;
    }
}
// Display the viewer with the specified item.
echo $this->universalViewer($records);
