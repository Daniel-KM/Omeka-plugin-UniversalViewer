<fieldset id="universalviewer-item-metadata">
    <h2><?php echo __('Universal Viewer'); ?></h2>
    <div class="field">
        <label class="two columns alpha">
            <?php echo __('Order files by filename'); ?>
        </label>
        <div class="inputs five columns omega">
            <label class="order-by-filename">
                <?php
                    echo $this->formCheckbox('custom[universalviewer][orderByFilename]', null, array(
                        'checked' => false, 'class' => 'order-by-filename-checkbox'));
                ?>
            </label>
            <p class="explanation"><?php
                echo __('Order files of each item by their original filename.');
            ?></p>
        </div>
    </div>
    <div class="field">
        <label class="two columns alpha">
            <?php echo __('Mix images and other files'); ?>
        </label>
        <div class="inputs five columns omega">
            <label class="mix-images">
                <?php
                    echo $this->formCheckbox('custom[universalviewer][mixImages]', null, array(
                        'checked' => false, 'class' => 'mix-images-checkbox'));
                ?>
            </label>
            <p class="explanation"><?php
                echo ' ' . __('If checked, types will be mixed, else images will be ordered before other files.');
            ?></p>
        </div>
    </div>
</fieldset>
