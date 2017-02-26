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
    echo $this->universalViewer($record, array(
        'style' => 'height: 600px;' . get_option('universalviewer_style'),
    ));
?>
<?php echo foot();
