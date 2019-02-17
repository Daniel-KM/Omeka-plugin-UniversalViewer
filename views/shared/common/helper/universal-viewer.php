<?php
/**
 * @var Omeka_View $this
 * @var string $urlManifest
 * @var string $class
 * @var string $style
 * @var string $locale
 * @var string $config
 */

if (empty($style)) $style = 'height: 600px;';
if (!empty($style)) $style = ' style="' . $style . '"';
if (!empty($locale)) $locale = ' data-locale="' . $locale . '"';

?>
<div class="uv <?= $class ?>" data-config="<?= $config ?>" data-uri="<?= $urlManifest ?>"<?= $locale ?><?= $style ?>></div>
