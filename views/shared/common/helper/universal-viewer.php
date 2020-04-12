<?php
/**
 * @var Omeka_View $this
 * @var array $config Require keys "root", "iiifResourceUri" and "configUri".
 *
 * @link https://github.com/UniversalViewer/universalviewer/wiki/V3
 */
$config['id'] = isset($config['id']) ? $config['id'] : 'uv';
$configJson = json_encode($config, 448);

?>

<div id="<?= $config['id'] ?>" class="universal-viewer viewer"></div>
<script type="text/javascript">
var uvElement;
window.addEventListener('uvLoaded', function (e) {

    uvElement = createUV('#<?= $config['id'] ?>', <?= $configJson ?>, new UV.URLDataProvider());
    /*
    uvElement.on("created", function(obj) {
        console.log('parsed metadata', uvElement.extension.helper.manifest.getMetadata());
        console.log('raw jsonld', uvElement.extension.helper.manifest.__jsonld);
    });
    */
}, false);
</script>
