<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

function xmldb_local_recognition_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024122005) {
        // Drop existing tables if they exist
        $tables = array(
            'local_recognition_points',
            'local_recognition_likes',
            'local_recognition_comments',
            'local_recognition_posts',
            'local_recognition_settings'
        );
        
        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        // Posts table
        $table = new xmldb_table('local_recognition_posts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('imagepath', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('likes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('comments', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        $table->add_index('likes', XMLDB_INDEX_NOTUNIQUE, array('likes'));
        $table->add_index('comments', XMLDB_INDEX_NOTUNIQUE, array('comments'));
        
        $dbman->create_table($table);

        // Comments table
        $table = new xmldb_table('local_recognition_comments');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('postid', XMLDB_KEY_FOREIGN, array('postid'), 'local_recognition_posts', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        $table->add_index('postuser', XMLDB_INDEX_NOTUNIQUE, array('postid', 'userid'));
        
        $dbman->create_table($table);

        // Likes table
        $table = new xmldb_table('local_recognition_likes');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('postid', XMLDB_KEY_FOREIGN, array('postid'), 'local_recognition_posts', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        $table->add_index('postuser', XMLDB_INDEX_UNIQUE, array('postid', 'userid'));
        
        $dbman->create_table($table);

        // Points table
        $table = new xmldb_table('local_recognition_points');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('total_points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('post_likes_received', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('post_comments_received', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('comments_made', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('likes_given', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastupdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        $table->add_index('points', XMLDB_INDEX_NOTUNIQUE, array('total_points'));
        
        $dbman->create_table($table);

        // Settings table
        $table = new xmldb_table('local_recognition_settings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('name', XMLDB_INDEX_UNIQUE, array('name'));
        
        $dbman->create_table($table);
        
        // Add default settings
        $settings = array(
            array(
                'name' => 'points_like_received',
                'value' => 30,
                'timemodified' => time()
            ),
            array(
                'name' => 'points_comment_received',
                'value' => 50,
                'timemodified' => time()
            ),
            array(
                'name' => 'points_comment_made',
                'value' => 20,
                'timemodified' => time()
            ),
            array(
                'name' => 'points_like_given',
                'value' => 10,
                'timemodified' => time()
            )
        );

        foreach ($settings as $setting) {
            $DB->insert_record('local_recognition_settings', (object)$setting);
        }

        upgrade_plugin_savepoint(true, 2024122005, 'local', 'recognition');
    }

    return true;
}
