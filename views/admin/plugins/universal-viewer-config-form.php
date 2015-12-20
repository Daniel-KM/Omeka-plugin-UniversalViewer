<fieldset id="fieldset-universalviewer-general"><legend><?php echo __('General parameters'); ?></legend>
    <p class="explanation">
        <?php echo __('If checked, the viewer will be automatically appended to the collections or items show page.'); ?>
        <?php echo __('Else, the viewer can be added via the helper in the theme or the shortcode in any page.'); ?>
    </p>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_append_collections_show',
                __('Append to "Collection show"')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('universalviewer_append_collections_show', true,
                array('checked' => (boolean) get_option('universalviewer_append_collections_show'))); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_append_items_show',
                __('Append to "Item show"')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('universalviewer_append_items_show', true,
                array('checked' => (boolean) get_option('universalviewer_append_items_show'))); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_max_dynamic_size',
                __('Max dynamic size for images')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_max_dynamic_size', get_option('universalviewer_max_dynamic_size'), null); ?>
            <p class="explanation">
                <?php echo __('Set the maximum size in bytes for the dynamic processing of images.'); ?>
                <?php echo __('Beyond this limit, the plugin will require a tiled image, for example made by OpenLayersZoom.'); ?>
                <?php echo __('Let empty to allow processing of any image.'); ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-universalviewer-info"><legend><?php echo __('Common infos'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_licence',
                __('Licence')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_licence', get_option('universalviewer_licence'), null); ?>
            <p class="explanation">
                <?php echo __('If any, this link will be added in all manifests and viewers to indicate the rights.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_attribution',
                __('Attribution')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_attribution', get_option('universalviewer_attribution'), null); ?>
            <p class="explanation">
                <?php echo __('If any, this text will be added in all manifests and viewers.'); ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-universalviewer-embed"><legend><?php echo __('Integration of the viewer'); ?></legend>
    <p>
        <?php echo __('These values allows to parameter the integration of the viewer in Omeka pages.'); ?>
        <?php echo __('The viewer itself can be configured via the file "config.json" and the helper.'); ?>
    </p>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_class',
                __('Class of inline frame')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_class', get_option('universalviewer_class'), null); ?>
            <p class="explanation">
                <?php echo __('Class to add to the inline frame.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_width',
                __('Width of the inline frame')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_width', get_option('universalviewer_width'), null); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_height',
                __('Height of the inline frame')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_height', get_option('universalviewer_height'), null); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_locale',
                __('Locales of the viewer')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('universalviewer_locale', get_option('universalviewer_locale'), null); ?>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-universalviewer-background"><legend><?php echo __('Background parameters'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('universalviewer_iiif_creator',
                __('Image Processor')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php
                $processors['Auto'] = __('Automatic (GD when possible, else ImageMagick)');
                echo $this->formSelect('universalviewer_iiif_creator', get_option('universalviewer_iiif_creator'), array(), $processors);
            ?>
            <p class="explanation">
                <?php echo __('Images may be processed internally before to be sent to browser.'); ?>
                <?php echo __('Select the one to use.'); ?>
                <?php echo __('Generally, GD is quicker than ImageMagick, but ImageMagick manages more formats.'); ?>
                <?php echo __('Nevertheless, the performance depends on your installation and your server.'); ?>
            </p>
        </div>
    </div>
</fieldset>
