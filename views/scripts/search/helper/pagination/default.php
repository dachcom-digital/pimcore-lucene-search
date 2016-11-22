<div class="<?= $this->class; ?>">

    <?php if( $this->currentSearchPage > 1 ) { ?>
        <a class="previous icon-arrow-left" href="?<?= $this->searchUrlData; ?>&page=<?= $this->currentSearchPage - 1 ?>"></a>
    <?php } ?>
    <?php for($i = $this->searchPageStart; $i <= $this->searchPageEnd; $i++) { ?>
        <a <?php if($this->currentSearchPage === $i) { ?>class="active"<?php } ?> href="?<?= $this->searchUrlData; ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php } ?>
    <?php if($this->searchAllPages > $this->currentSearchPage) { ?>
        <a class="next icon-arrow-right" href="?<?= $this->searchUrlData; ?>&page=<?= $this->currentSearchPage+ 1  ?>"></a>
    <?php } ?>

</div>