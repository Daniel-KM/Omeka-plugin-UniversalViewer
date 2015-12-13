<?php
    $title = metadata($record, array('Dublin Core', 'Title'));
    $creator = metadata($record, array('Dublin Core', 'Creator'));
    if ($creator) {
        $title .= ' - ' . $creator;
    }
    echo head(array(
        'title' => $title,
        'bodyclass' => 'universal-viewer play',
    ));
?>
<?php
    echo $this->universalViewer(array(
        'record' => $record,
        'width' => '100%',
        'height' => '700px',
    ));
?>
<?php echo foot();
