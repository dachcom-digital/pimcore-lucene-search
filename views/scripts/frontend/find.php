<div class="container">

    <div class="row">

        <div class="col-xs-12 search-content">

            <h1><?=$this->input('searchTitle'); ?></h1>

            <div class="info">

                <?php if( $this->hasSearchResults) { ?>

                    <?= $this->translate('Results from')?> <?= $this->currentPageResultStart ?> - <?= $this->currentPageResultEnd ?> <?= $this->translate('of')?> <?= $this->total ?>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="row">

        <div class="col-xs-12">

            <div class="search-list">

                <?php if( $this->hasSearchResults) { ?>

                    <h4><?= sprintf( $this->translate('We found %d entries for "%s".'), $this->total, $this->query )?></h4>

                    <ul class="search-results list-unstyled">

                        <?php foreach($this->searchResults as $i => $searchResult) { ?>

                            <li class="search-result">

                                <?php if($searchResult['title']) { ?>
                                    <h5><?= $searchResult['title'] ?></h5>
                                <?php } ?>

                                <span class="result-summary-<?= $i ?>">

                                    <?php if( !empty($searchResult['description']) ) { ?>
                                        <p><?= $searchResult['description'] ?></p>
                                    <?php } else if( !empty($searchResult['summary']) ) { ?>
                                        <p><?= $searchResult['summary'] ?> ...</p>
                                    <?php } ?>

                                </span>

                                <a href="<?= $searchResult['url']?>" class="more"><?=$this->translate('read more')?></a>

                            </li>

                        <?php } ?>

                    </ul>

                    <?php if( $this->hasSearchResults) { ?>

                        <div class="paginator">

                            <?php

                            if($this->page > 3 ) {
                                $pageStart = $this->page-2;
                            } else {
                                $pageStart = 1;
                            }

                            $pageEnd = $pageStart + 5;
                            if($pageEnd > $this->pages) {
                                $pageEnd = $this->pages;
                            }

                            ?>

                            <?php if ($this->pages > 1) { ?>

                                <?php if( $this->page > 1 ) { ?>
                                    <a class="previous icon-arrow_left" href="?q=<?= $this->query?>&language=<?= $this->language; ?>&page=<?= $this->page-1 ?>"></a>
                                <?php } ?>
                                <?php for($i=$pageStart; $i<=$pageEnd; $i++) { ?>
                                    <a <?php if($this->page == $i) { ?>class="active"<?php } ?> href="?q=<?= $this->query?>&language=<?= $this->language; ?>&page=<?= $i ?>"><?= $i ?></a>
                                <?php } ?>
                                <?php if($this->pages > $this->page) { ?>
                                    <a class="next icon-arrow_right" href="?q=<?= $this->query ?>&language=<?= $this->language; ?>&page=<?= $this->page+1 ?>"></a>
                                <?php } ?>

                            <?php } ?>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <?php if( !empty( $this->query ) ) { ?>

                        <div class="no-results">

                            <h5><?= $this->translate('no search results found'); ?></h5>

                            <?php if(!empty($this->suggestions)) { ?>

                                <br>
                                <?= $this->translate('Did you mean') ?>

                                <?php foreach( $this->suggestions as $i => $suggestion) { ?>
                                    <a href="?q=<?= $suggestion ?>&language=<?= $this->language; ?>"><?= $suggestion ?></a><?= count($this->suggestions)-1 !== $i ? ',' : ''; ?>
                                <?php } ?>

                            <?php } ?>

                        </div>

                    <?php } ?>

                <?php } ?>

            </div>

        </div>

    </div>

</div>