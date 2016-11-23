<div class="<?= $this->class; ?>">

    <?php if( $this->searchAllPages > 1 ) { ?>

        <?php if($this->searchAllPages > $this->currentSearchPage) { ?>
            <a class="more" href="<?= $this->searchUrl; ?><?= $this->searchUrlData; ?>&page=<?= $this->currentSearchPage + 1 ?>"><?= $this->translate('next page'); ?></a>
        <?php } ?>

    <?php } ?>

</div>