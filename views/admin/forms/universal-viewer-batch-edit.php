<fieldset id="universalviewer-item-metadata">
    <h2><?php echo __('Universal Viewer'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('orderByFilename',
                __('Order files')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('custom[universalviewer][orderByFilename]', null, array(
                'checked' => false, 'class' => 'order-by-filename-checkbox')); ?>
            <p class="explanation">
                <?php echo __('Order files of each item by their original filename.'); ?></p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('mixImages',
                __('Mix images and other files')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('custom[universalviewer][mixImages]', null, array(
                'checked' => false, 'class' => 'mix-images-checkbox')); ?>
            <p class="explanation">
                <?php echo __('If checked, types will be mixed, else images will be ordered before other files.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('checkImageSize',
                __('Rebuild metadata when missing')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('custom[universalviewer][checkImageSize]', null, array(
                'checked' => false, 'class' => 'check-image-size-checkbox')); ?>
            <p class="explanation">
                <?php echo __('If checked, missing metadata of files will be rebuilt in order to get the size of images instantly.'); ?>
            </p>
        </div>
    </div>
</fieldset>
