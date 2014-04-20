<?php
require('Merge.php');

$usage = 'Shotwell dabatase and thumbnails merging utility.

usage: php run.php -s <source db file> [-t <thumbs directory>]

    As a destination database result/photo.db will be used.
    If result/photo.db does not exist it will be created.

    You can run the script several times, merging a number
    of Shotwell databases.

    The renamed thumbnails will be stored in result/thumbs/ in appropriate
    subdirectories. Please remember to copy the thumbnails corresponding
    to the first database yourself.

The command takes short or long form of the options:
    -s, --source
    -t, --thumbs
';

// Read options.
$options = getopt(
    's:t:',
    array(
        'source:',
        'thumbs:',
    )
);

// Handle errors.
if (!is_array($options) || empty($options['s']) && empty($options['source'])) {
    print($usage);
    exit(1);
}

$source = !empty($options['source']) ? $options['source'] : $options['s'];
$thumbs = !empty($options['thumbs']) ? $options['thumbs'] : (!empty($options['t']) ? $options['t'] : false);

if ($thumbs && !is_dir($thumbs)) {
    print('Warning: "'.$thumbs.'" does not appear to be a directory. Skipiping thumbs merge.');
    $thumbs = false;
}

$merge = new Shotwell\Merge($source);

// Merge tables.
$tables = [
    'PhotoTable',
    'EventTable',
    'TagTable',
    'BackingPhotoTable',
    'TombstoneTable',
];

foreach ($tables as $table) {
    print('Merging '.$table.'...');
    $merge->mergeTable($table);
    print('done.'."\n");
}

// Update tables.
print('Updating PhotoTable...');
$merge->updatePhotoTable();
print('done.'."\n");

print('Updating EventTable...');
$merge->updateEventTable();
print('done.'."\n");

print('Updating TagTable...');
$merge->updateTagTable();
print('done.'."\n");

// Update thumbnails.
if ($thumbs) {
    print('Updating thumbnails...');
    if (!$merge->updateThumbs($thumbs)) {
        print("\n".'Warning: the source thumbs directories do not seem to exist. Skipping thumbs merge.');
    } else {
        print('done.'."\n");
    }
}
