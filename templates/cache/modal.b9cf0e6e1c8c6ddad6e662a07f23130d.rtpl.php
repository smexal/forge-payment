<?php if(!class_exists('raintpl')){exit;}?><h4><?php echo $pretitle;?></h4>
<h2><?php echo $title;?></h2>
<div class="adapters">
<?php $counter1=-1; if( isset($adapters) && is_array($adapters) && sizeof($adapters) ) foreach( $adapters as $key1 => $value1 ){ $counter1++; ?>
    <?php if( isset($value1["raw"]) ){ ?>
        <?php echo $value1["raw"];?>
    <?php }else{ ?>
    <a class="adapter<?php if( $value1["desc"] ){ ?> has-desc<?php } ?> clearfix" href="<?php echo $value1["url"];?>">
        <div>
            <h4><?php echo $value1["label"];?></h4>
            <?php if( $value1["desc"] ){ ?>
            <small><?php echo $value1["desc"];?></small>
            <?php } ?>
        </div>
        <?php if( $value1["image"] ){ ?>
        <div class="image">
            <img src="<?php echo $value1["image"];?>" />
        </div>
        <?php } ?>
    </a>
    <?php } ?>
<?php } ?>
</div>
