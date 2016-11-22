<div class="container">

    <div class="row">

        <div class="col-xs-12 search-content">

            <h1><?=$this->input('searchTitle'); ?></h1>

            <div class="info">

                <?php if( $this->searchHasResults) { ?>

                    <?= $this->translate('Results from')?> <?= $this->searchCurrentPageResultStart ?> - <?= $this->searchCurrentPageResultEnd ?> <?= $this->translate('of')?> <?= $this->searchTotalHits ?>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="row">

        <div class="col-xs-12">

            <div class="search-list">

                <?php if( $this->searchHasResults) { ?>

                    <h4><?= sprintf( $this->translate('We found %d entries for "%s".'), $this->searchTotalHits, $this->searchQuery )?></h4>

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

                    <?php if( $this->searchHasResults) { ?>

                        <?= $this->searchHelper()->getPagination(['viewTemplate' => 'default']); ?>

                    <?php } ?>

                <?php } else { ?>

                    <?php if( !empty( $this->searchQuery ) ) { ?>

                        <div class="no-results">

                            <h5><?= $this->translate('no search results found'); ?></h5>

                            <?php if( !empty($this->searchSuggestions) ) { ?>

                                <?= $this->translate('Did you mean') ?>:

                                <?php foreach( $this->searchSuggestions as $i => $suggestion) { ?>
                                    <a href="?<?= $this->searchHelper()->createPaginationUrl($suggestion) ?>"><?= $suggestion ?></a><?= count($this->searchSuggestions)-1 !== $i ? ',' : ''; ?>
                                <?php } ?>

                            <?php } ?>

                        </div>

                    <?php } ?>

                <?php } ?>

            </div>

        </div>

    </div>

</div>