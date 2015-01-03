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


    public function __construct($src_db_file, $dst_db_file = 'result/photo.db', $tmp_db_file = 'tmp.db')
    {
        $this->src_db_file = $src_db_file;
        $this->dst_db_file = $dst_db_file;
        $this->tmp_db_file = $tmp_db_file;

        // Remove temp database - it is to be used once per db merge only.
        if (file_exists($tmp_db_file)) {
            unlink($tmp_db_file);
        }

        // Connect to the destination db and the source db.
        $this->dst_db = new \SQLite3($dst_db_file);
        $this->src_db = new \SQLite3($src_db_file);
        $this->tmp_db = new \SQLite3($tmp_db_file);

        // Speed up the INSERTS
        $this->dst_db->exec('PRAGMA synchronous=OFF');
        $this->tmp_db->exec('PRAGMA synchronous=OFF');

        // Create migration tables in the temporary db.
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS VideoTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS PhotoTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS EventTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS TagTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS BackingPhotoTable (old_id INTEGER, new_id INTEGER)');
        $this->tmp_db->exec('CREATE TABLE IF NOT EXISTS TombstoneTable (old_id INTEGER, new_id INTEGER)');
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
        if ($result === false) {
            // This table doesn't exist in source, skip it.
            // E.g. the BackingPhotoTable doesn't always exist
            print('Skipping merge of table: '.$table.'\n');
            return;
        }

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
     * editable_id          - with the new backing photo id
     */
    public function updatePhotoTable()
    {
        $photo_statement_select = $this->dst_db->prepare('SELECT * FROM PhotoTable WHERE id=:id');
        $photo_statement_update = $this->dst_db->prepare('UPDATE PhotoTable SET event_id=:event_id, editable_id=:editable_id WHERE id=:id');
        $event_statement_select = $this->tmp_db->prepare('SELECT * FROM EventTable WHERE old_id=:old_id');
        $backing_statement_select = $this->tmp_db->prepare('SELECT * FROM BackingPhotoTable WHERE old_id=:old_id');

        $result = $this->tmp_db->query('SELECT * FROM PhotoTable');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get the photo row in order to read the event_id.
            $photo_statement_select->bindValue(':id', $row['new_id']);
            $photo_row = $photo_statement_select->execute()->fetchArray(SQLITE3_ASSOC);
            
            // Get the new event_id
            $event_statement_select->bindValue(':old_id', $photo_row['event_id']);
            $event_row = $event_statement_select->execute()->fetchArray(SQLITE3_ASSOC);

            $editable_id = $photo_row['editable_id'];
            if ($editable_id !== -1) {
                $backing_statement_select->bindValue(':old_id', $editable_id);
                $backing_row = $backing_statement_select->execute()->fetchArray(SQLITE3_ASSOC);
                $editable_id = $backing_row['new_id'];
                $backing_statement_select->reset();
            }

            // Update event_id
            $photo_statement_update->bindValue(':id', $photo_row['id']);
            $photo_statement_update->bindValue(':event_id', $event_row['new_id']);
            $photo_statement_update->bindValue(':editable_id', $editable_id);
            $photo_statement_update->execute();

            $photo_statement_select->reset();
            $photo_statement_update->reset();
            $event_statement_select->reset();
        }
    }

    /**
     * Updates:
     * event_id             - with the new photo id
     */
    public function updateVideoTable()
    {
        $video_statement_select = $this->dst_db->prepare('SELECT * FROM VideoTable WHERE id=:id');
        $video_statement_update = $this->dst_db->prepare('UPDATE VideoTable SET event_id=:event_id WHERE id=:id');
        $event_statement_select = $this->tmp_db->prepare('SELECT * FROM EventTable WHERE old_id=:old_id');

        $result = $this->tmp_db->query('SELECT * FROM VideoTable');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get the video row in order to read the event_id.
            $video_statement_select->bindValue(':id', $row['new_id']);
            $video_row = $video_statement_select->execute()->fetchArray(SQLITE3_ASSOC);

            // Get the new event_id
            $event_statement_select->bindValue(':old_id', $video_row['event_id']);
            $event_row = $event_statement_select->execute()->fetchArray(SQLITE3_ASSOC);

            // Update event_id
            $video_statement_update->bindValue(':id', $video_row['id']);
            $video_statement_update->bindValue(':event_id', $event_row['new_id']);
            $video_statement_update->execute();

            $video_statement_select->reset();
            $video_statement_update->reset();
            $event_statement_select->reset();
        }
    }

    /**
     * Updates:
     * primary_photo_id     - with new photo id
     * primary_source_id    - with the new thumbnail name
     */
    public function updateEventTable()
    {
        $event_statement_select = $this->dst_db->prepare('SELECT * FROM EventTable WHERE id=:id');
        $event_statement_update = $this->dst_db->prepare('UPDATE EventTable SET primary_photo_id=:primary_photo_id, primary_source_id=:primary_source_id WHERE id=:id');
        $photo_statement_select = $this->tmp_db->prepare('SELECT * FROM PhotoTable WHERE old_id=:old_id');

        $result = $this->tmp_db->query('SELECT * FROM EventTable');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get the event row in order to read the photo id.
            $event_statement_select->bindValue(':id', $row['new_id']);
            $event_row = $event_statement_select->execute()->fetchArray(SQLITE3_ASSOC);
            
            // Get old photo id.
            if (!empty($event_row['primary_photo_id'])) {
                $old_photo_id = $event_row['primary_photo_id'];
            } elseif (!empty($event_row['primary_source_id'])) {
                $old_photo_id = $this->getIdFromThumbName($event_row['primary_source_id']);
            } else {
                continue;
            }

            // Get new photo id
            $photo_statement_select->bindValue(':old_id', $old_photo_id);
            $photo_row = $photo_statement_select->execute()->fetchArray(SQLITE3_ASSOC);
            $photo_id = $photo_row['new_id'];

            // Update event with photo id or thumbnail name.
            $event_statement_update->bindValue(':id', $event_row['id']);
            $event_statement_update->bindValue(
                ':primary_photo_id',
                empty($event_row['primary_photo_id']) ? $event_row['primary_photo_id'] : $photo_id
            );
            $event_statement_update->bindValue(
                ':primary_source_id',
                empty($event_row['primary_source_id']) ? $event_row['primary_source_id'] : $this->getThumbNameFromId($photo_id)
            );
            $event_statement_update->execute();

            $event_statement_select->reset();
            $event_statement_update->reset();
            $event_statement_select->reset();
        }
    }

    /**
     * Updates:
     * photo_id_list        - with new photo ids
     */
    public function updateTagTable()
    {
        $tag_statement_select = $this->dst_db->prepare('SELECT * FROM TagTable WHERE id=:id');
        $tag_statement_update = $this->dst_db->prepare('UPDATE TagTable SET photo_id_list=:photo_id_list WHERE id=:id');
        $photo_statement_select = $this->tmp_db->prepare('SELECT * FROM PhotoTable WHERE old_id=:old_id');

        $result = $this->tmp_db->query('SELECT * FROM TagTable');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // The the tag row and begin the fun.
            $tag_statement_select->bindValue(':id', $row['new_id']);
            $tag_row = $tag_statement_select->execute()->fetchArray(SQLITE3_ASSOC);

            if (empty($tag_row['photo_id_list'])) {
                continue;
            }

            // Update photo ids
            $old_thumb_names = explode(',', $tag_row['photo_id_list']);
            $thumb_names = [];
            foreach ($old_thumb_names as $old_thumb_name) {
                $old_photo_id = $this->getIdFromThumbName($old_thumb_name);
                $photo_statement_select->bindValue(':old_id', $old_photo_id);
                $photo_row = $photo_statement_select->execute()->fetchArray(SQLITE3_ASSOC);
                $thumb_names[] = $this->getThumbNameFromId($photo_row['new_id']);
                $photo_statement_select->reset();
            }
            $tag_statement_update->bindValue(':id', $tag_row['id']);
            $tag_statement_update->bindValue(':photo_id_list', implode(',', $thumb_names));
            $tag_statement_update->execute();

            $tag_statement_select->reset();
            $tag_statement_update->reset();
        }
    }

    /**
     * Updates:
     * thumbnail file names  - the thumbnail file names are based on the photo id.
     */
    public function updateThumbs($thumbs_dir)
    {
        // Check if the source directories exist.
        if (!is_dir($thumbs_dir.'/thumbs128') || !is_dir($thumbs_dir.'/thumbs360')) {
            return false;
        }

        // Created destination directories, if they do not exist.
        if (!is_dir('result/thumbs/thumbs128')) {
            mkdir('result/thumbs/thumbs128', 0755, true);
        }
        if (!is_dir('result/thumbs/thumbs360')) {
            mkdir('result/thumbs/thumbs360', 0755, true);
        }

        $result = $this->tmp_db->query('SELECT * FROM PhotoTable');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $old_thumb_name = $this->getThumbNameFromId($row['old_id']);
            $thumb_name = $this->getThumbNameFromId($row['new_id']);

            copy($thumbs_dir.'/thumbs128/'.$old_thumb_name.'.jpg', 'result/thumbs/thumbs128/'.$thumb_name.'.jpg');
            copy($thumbs_dir.'/thumbs360/'.$old_thumb_name.'.jpg', 'result/thumbs/thumbs360/'.$thumb_name.'.jpg');
        }

        return true;
    }

    protected function getThumbNameFromId($id)
    {
        return sprintf('thumb%016x', $id);
    }

    protected function getIdFromThumbName($filename)
    {
        return intval(str_replace('thumb', '', $filename), 16);
    }
}
