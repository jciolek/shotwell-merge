<?php

namespace Shotwell;

class Merge
{
    protected $src_db_file;
    protected $dst_db_file;
    protected $tmp_db_file;

    protected $src_db;
    protected $dst_db;
    protected $tmp_db;


    public function __construct($dst_db_file, $src_db_file, $tmp_db_file)
    {

        $this->src_db_file = $src_db_file;
        $this->dst_db_file = $dst_db_file;
        $this->tmb_db_file = $tmp_db_file;

        // Connect to the destination db and the source db.
        $this->dst_db = new \SQLite3($dst_db_file);
        $this->src_db = new \SQLite3($src_db_file);
        $this->tmp_db = new \SQLite3($tmp_db_file);

        // Create migration tables in the temporary db.
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS PhotoTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS EventTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS TagTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS BackingPhotoTable (old_id INTEGER, new_id INTEGER)');
    }

    public function mergeTable($table)
    {
        // Fetch the fields from the table.
        $result = $this->dst_db->query('PRAGMA table_info('.$table.')');
        $fields = [];
        $values = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get rid of the id - we want to obtain new id when merging.
            if ($row['name'] !== 'id') {
                $fields[] = $row['name'];
                $values[] = ':'.$row['name'];
            }
        }

        // Prepare statements for merging.
        $copy = $this->dst_db->prepare(
            'INSERT INTO '.$table.' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')'
        );
        $translate = $this->tmp_db->prepare(
            'INSERT INTO '.$table.' (new_id, old_id) VALUES (:new_id, :old_id)'
        );

        $result = $this->src_db->query('SELECT * FROM '.$table);

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $old_id = $row['id'];
            // We are going to obtain new id during insert.
            unset($row['id']);
            // Set the parameters in the statement.
            foreach ($row as $column => $value) {
                $copy->bindValue(':'.$column, $value);
            }
            if ($copy->execute()) {
                $new_id = $this->dst_db->lastInsertRowId();
                $translate->bindValue(':new_id', $new_id);
                $translate->bindValue(':old_id', $old_id);
                $translate->execute();
            } else {
                print('Error while inserting: '.$row);
            }
            $copy->reset();
            $translate->reset();
        }
    }

    /**
     * Updates:
     * event_id             - with the new photo id
     */
    public function updatePhotoTable()
    {

    }

    /**
     * Updates:
     * primary_photo_id     - with new photo id
     * primary_source_id    - with the new thumbnail name
     */
    public function updateEventTable()
    {

    }

    /**
     * Updates:
     * photo_id_list        - with new photo ids
     */
    public function updateTagTable()
    {

    }

    /**
     * Updates:
     * thumbnail file names  - the thumbnail file names are based on the photo id.
     */
    public function updateThumbs()
    {

    }
}
