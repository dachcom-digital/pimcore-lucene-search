<ul>
    <?php if(is_array($this->suggestions)){ ?>
        <?php foreach($this->suggestions as $suggestion) { ?>
            <li><?= $suggestion['q'] ?></li>
        <?php } ?>
    <?php } ?>
</ul>