

<link rel="stylesheet" type="text/css" href="/plugins/LuceneSearch/static/css/frontend.css"/>

<?php if(!$this->omitSearchForm){ ?>

<?php if(!$this->omitJsIncludes){?>

    <script src="/plugins/LuceneSearch/static/js/frontend/jquery-1.3.2.min.js"></script>
    <link rel="stylesheet" href="/plugins/LuceneSearch/static/css/jquery-autocomplete.css" type="text/css" />
    <script type="text/javascript" src="/plugins/LuceneSearch/static/js/frontend/jquery.autocomplete.js"></script>


<?php } ?>

<div class="searchForm">

    <form method="post" action="?search=true" id="searchForm">

        <input type="text" value="<?php echo  $this->query ?>" name="query" id="query" />
        <?php if(is_array($this->availableCategories) and count($this->availableCategories)>0){?>

            <select id="searchCat" name="cat">
                <option value=""><?php echo  $this->translate('search_all_categories')?></option>
                <?php foreach($this->availableCategories as $category){?>
                <option <?php if($this->category==$category){ ?>selected="selected"<?php } ?> value="<?php echo  $category ?>"><?php echo $this->translate('search_category_'.$category)?></option>
                <?php } ?>
            </select>
        <?php } ?>
        <span class="submit_wrapper"><input class="submit" type="submit" value="<?php echo  $this->translate('search_submit')?>"/></span>

             <script type="text/javascript">
                if (jQuery().autocomplete) { // Only use autocompletion if the plugin is loaded
                  $('#query').autocomplete('/plugin/LuceneSearch/frontend/autocomplete/',{
                     minChars:3,
                     cacheLength: 0,
                     extraParams: {
                         cat: function() { return $("#searchCat").val(); }
                     }
                  });
                }
            </script>

    </form>
</div>

<?php } ?>

<div id="search_info">

    <?php if(count($this->searchResults)>=1){
        $start = $this->perPage*($this->page-1);
        $end = $start + $this->perPage;
        if($end>$this->total){
            $end = $this->total;
        }
        ?>
        <?php echo  $this->translate('search_showing_results')?> <?php echo  $start+1 ?> - <?php echo  $end ?> <?php echo  $this->translate('search_results_of')?> <?php echo  $this->total ?><br/>
     <?php } else { echo $this->translate('no_search_results_found'); }?>

</div>

<?php if(!empty($this->suggestions)) { ?>
    <?php echo  $this->translate('search_suggestions') ?>
    <?php for($i=0;$i<5;$i++ ){ ?>
    <?php  $suggestion = $this->suggestions[$i]; ?>
    <a href="?cat=<?php echo  $this->category ?>&query=<?php echo  $suggestion ?>"><?php echo  $suggestion ?></a>&nbsp;
    <?php } ?>
    <?php if(count($this->suggestions)>5) { ?>
        <span id="search_result_additional_suggestions" style="display:none;">
        <?php for($i=5;$i<count($this->suggestions);$i++ ){ ?>
            <?php  $suggestion = $this->suggestions[$i]; ?>
            <a href="?cat=<?php echo  $this->category ?>&query=<?php echo  $suggestion ?>"><?php echo  $suggestion ?></a>&nbsp;
            <?php } ?>
        </span>
        <a style="cursor:pointer;" id="search_result_additional_suggestions_hint" onclick="$('#search_result_additional_suggestions_hint').hide();$('#search_result_additional_suggestions').show()"><?php echo  (count($this->suggestions)-5).' '.$this->translate('more_search_suggestions')?></a>
    <?php } ?>
<?php } ?>

<?php $counter = 1;?>

<?php /* --------- Display grouped by category --------------*/ ?>
<?php if($this->groupByCategory) { ?>
	<?php 
	$categories = array('nocat');
	foreach($this->searchResults as $searchResult) {
		if(is_array($searchResult['categories'])) {
			foreach($searchResult['categories'] as $cat){
				if(!in_array($cat,$categories)){
				$categories[] = $cat;
				}
				$categorizedSearchResults[$cat][]=$searchResult;
			}
		} else {
				$categorizedSearchResults['nocat'][]=$searchResult;
		}	
	}
	if(is_array($categorizedSearchResults)){
		if(is_array($this->categoryOrder) and count($this->categoryOrder)>0){
			$tmp=array();
			foreach($this->categoryOrder as $cat){
				if(!empty($categorizedSearchResults[$cat])){
					$tmp[$cat]=$categorizedSearchResults[$cat];
					unset($categorizedSearchResults[$cat]);
				}	
			}
			$categorizedSearchResults=array_merge($tmp,$categorizedSearchResults);
		} else {
			natsort($categorizedSearchResults);
		}
	}

	?>
	<?php if(is_array($categorizedSearchResults)){ ?>
		<?php foreach($categorizedSearchResults as $key=>$categoryResults){ ?>
		<div class="search_result_category category_<?php echo  $key ?>">
				<div class="search_category_headline">
					<h2><?php echo $this->translate('search_category_'.$key) ?></h2>
				</div>
				<?php foreach($categoryResults as $searchResult){ ?>
				
				<div class="search_result <?php if(is_array($searchResult['categories'])) { echo implode(' ',$searchResult['categories']);} ?>">

				<a href="<?php echo  $searchResult['url']?>"><?php if(!empty($searchResult['title']) and trim($searchResult['title'])!='') { echo trim($searchResult['title']); } else { echo $searchResult['url']; }?></a><br/>
				<?php if($searchResult['h1']){?><strong><?php echo  $searchResult['h1'] ?></strong>  <?php } ?>
                <div id="resultSumary_<?php echo  $counter ?>">
					... <?php echo  $searchResult['sumary']?> ...
				</div>

				<?php $counter++;?>
				</div>
				
				<?php } ?>
		
		</div>
		<?php } ?>
	<?php } ?>
<?php /* --------- /Display grouped by category --------------*/ ?>	


<?php /* --------- Display not grouped --------------*/ ?>	
<?php } else { ?>

<?php foreach($this->searchResults as $searchResult) { ?>

<div class="search_result <?php if(is_array($searchResult['categories'])) { echo implode(' ',$searchResult['categories']);} ?>">

<a href="<?php echo $searchResult['url']?>"><?php if(!empty($searchResult['title']) and trim($searchResult['title'])!='') { echo trim($searchResult['title']); } else { echo $searchResult['url']; }?></a><br/>
<?php if($searchResult['h1']){?><strong><?php echo  $searchResult['h1'] ?></strong>  <?php } ?>
<div id="resultSumary_<?php echo  $counter ?>">
    ... <?php echo  $searchResult['sumary']?> ...
</div>
<?php $counter++;?>
</div>
<?php } ?>
<?php /* --------- /Display not grouped --------------*/ ?>		

<?php } ?>

<?php if(count($this->searchResults)>0) { ?>
<div id="search_paging">
    <?php
    if($this->page>3){
        $pageStart = $this->page-2;
    } else $pageStart=1;
    $pageEnd = $pageStart+5;
    if($pageEnd>$this->pages){
        $pageEnd = $this->pages;
    }
    ?>
    <?php if($this->pages>0) { ?>
    <?php echo  $this->translate('page') ?>
    <?php } ?>
    <?php if($this->page>1) {?>
    <a href="?query=<?php echo  $this->query?>&cat=<?php echo  $this->category ?>&page=<?php echo  $this->page-1 ?>">&lt;</a>
    <?php } ?>
    <?php for($i=$pageStart;$i<=$pageEnd;$i++) { ?>
    <a <?php if($this->page == $i) { ?>class="active"<?php } ?> href="?query=<?php echo  $this->query?>&cat=<?php echo  $this->category ?>&page=<?php echo  $i ?>"><?php echo  $i ?></a>
    <?php } ?>
    <?php if($this->pages > $this->page) { ?>
    <a href="?query=<?php echo  $this->query?>&cat=<?php echo  $this->category ?>&page=<?php echo  $this->page+1 ?>">&gt;</a>
    <?php } ?>

</div>
<?php } ?>
