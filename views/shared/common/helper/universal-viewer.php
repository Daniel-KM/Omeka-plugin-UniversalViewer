<?php
/**
 * @var Omeka_View $this
 * @var string $root
 * @var string $urlManifest
 * @var string $class
 * @var string $style
 * @var string $locale
 * @var string $config
 *
 * @link https://github.com/UniversalViewer/universalviewer/wiki/V3
 */

?>
<div id="uv" class="uv <?= $class ?>" style="<?= $style ?>"></div>

<style type="text/css">
    .uv .headerPanel .centerOptions .mode label {
        width: auto;
        min-width: 31px;
    }
</style>
<script type="text/javascript">
    var formattedLocales;
    var locales = <?= json_encode($locale) ?>;
    if (locales) {
        var names = locales.split(',');
        formattedLocales = [];
        for (var i in names) {
            var nameparts = String(names[i]).split(':');
            formattedLocales[i] = {name: nameparts[0], label: nameparts[1]};
        }

    } else {
        formattedLocales = [
            {
                name: 'en-GB'
            }
        ]
    }

    var uvElement;
    window.addEventListener('uvLoaded', function (e) {

        uvElement = createUV('#uv', {
            root: "<?= $root ?>",
            iiifResourceUri: "<?= $urlManifest ?>",
            configUri: "<?= $config ?>",
            locales: formattedLocales,
            embedded: true
        }, new UV.URLDataProvider());

        uvElement.on("created", function(obj) {
            console.log('parsed metadata', uvElement.extension.helper.manifest.getMetadata());
            console.log('raw jsonld', uvElement.extension.helper.manifest.__jsonld);
        });

    }, false);
</script>
