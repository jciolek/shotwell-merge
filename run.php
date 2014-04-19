<?php
require('Merge.php');

function initDatabases()
{
    // Create a copy of the destination db.
    copy($this->src_db_file, $dst_db_file);

    if (file_exists($tmp_db_file)) {
        unlink($tmp_db_file);
    }
    touch($tmp_db_file);
}

$merge = new Shotwell\Merge('photo.db', 'photo-samsung.db', 'tmp.db');

$merge->mergeTable('PhotoTable');
$merge->mergeTable('EventTable');
$merge->mergeTable('TagTable');
$merge->mergeTable('BackingPhotoTable');
