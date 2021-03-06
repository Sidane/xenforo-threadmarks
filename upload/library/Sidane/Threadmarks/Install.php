<?php

class Sidane_Threadmarks_Install
{
  const default_menu_limit = 8;

  public static function install($existingAddOn, $addOnData)
  {
    $required = '5.4.0';
    $phpversion = phpversion();
    if (version_compare($phpversion, $required, '<')) {
      throw new XenForo_Exception("PHP {$required} or newer is required. {$phpversion} does not meet this requirement. Please ask your host to upgrade PHP", true);
    }
    if (XenForo_Application::$versionId < 1030070) {
      throw new XenForo_Exception('XenForo 1.3.0+ is Required!', true); // Make this show nicely.
    }
    if (SV_Utils_AddOn::addOnIsActive('SV_WordCountSearch') &&
      !SV_Utils_AddOn::addOnIsActive('SV_WordCountSearch', 1010500)) {
      throw new XenForo_Exception("Word Count Search support requires v1.1.5 or newer", true);
    }
    $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

    $db = XenForo_Application::get('db');
    $requireIndexing = array();

    if (!$db->fetchRow("SHOW TABLES LIKE 'threadmarks'")) {
      $requireIndexing['threadmark'] = true;
      $db->query("
        CREATE TABLE IF NOT EXISTS threadmarks (
          threadmark_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
          threadmark_category_id INT UNSIGNED NOT NULL,
          thread_id INT UNSIGNED NOT NULL,
          post_id INT UNSIGNED NOT NULL,
          user_id INT NOT NULL DEFAULT 0,
          post_date INT NOT NULL DEFAULT 0,
          position INT NOT NULL DEFAULT 0,
          `parent_threadmark_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
          `depth` INT(10) UNSIGNED NOT NULL DEFAULT '0',
          message_state ENUM('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
          edit_count INT NOT NULL DEFAULT 0,
          last_edit_date INT NOT NULL DEFAULT 0,
          last_edit_user_id INT NOT NULL DEFAULT 0,
          label VARCHAR(255) NOT NULL,
          UNIQUE KEY `thread_post_id` (`thread_id`,`post_id`),
          KEY `thread_position` (`thread_id`,`position`),
          KEY `user_id` (`user_id`),
          UNIQUE KEY `post_id` (`post_id`)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
      ");
    }

    if ($version == 1) {
      SV_Utils_Install::dropColumn('xf_thread', 'has_threadmarks');
      SV_Utils_Install::dropIndex('threadmarks', 'thread_id');
    }

    if ($version < 2) {
      SV_Utils_Install::addColumn('xf_thread', 'threadmark_count', 'INT UNSIGNED DEFAULT 0 NOT NULL');
      SV_Utils_Install::addIndex('threadmarks', 'thread_post_id', array('thread_id', 'post_id'));

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_manage', permission_value, permission_value_int
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND permission_id IN ('warn','editAnyPost','deleteAnyPost')
        ");

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_add', permission_value, permission_value_int
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND permission_id IN ('postReply')
        ");

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_delete', permission_value, permission_value_int
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND permission_id IN ('deleteOwnPost')
        ");

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_edit', permission_value, permission_value_int
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND permission_id IN ('editOwnPost')
        ");

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_menu_limit', 'use_int', " . self::default_menu_limit . "
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND  permission_id IN ('viewContent')
        ");

      $db->query("INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'sidane_tm_view', permission_value, permission_value_int
        FROM xf_permission_entry
        WHERE permission_group_id = 'forum' AND  permission_id IN ('viewContent')
        ");
    }
    if ($version < 3) {
      SV_Utils_Install::modifyColumn('threadmarks', 'label', 'varchar(100)', 'VARCHAR(255) NOT NULL');
    }

    if ($version < 7) {
      SV_Utils_Install::dropIndex('threadmarks', 'post_id');
      SV_Utils_Install::addIndex('threadmarks', 'post_id', array('post_id'));
    }

    $db->query("
      INSERT IGNORE INTO xf_content_type
          (content_type, addon_id, fields)
      VALUES
          ('threadmark', 'sidaneThreadmarks', '')
    ");

    $db->query("
      INSERT IGNORE INTO xf_content_type_field
          (content_type, field_name, field_value)
      VALUES
          ('threadmark', 'edit_history_handler_class', 'Sidane_Threadmarks_EditHistoryHandler_Threadmark')
         ,('threadmark', 'search_handler_class', 'Sidane_Threadmarks_Search_DataHandler_Threadmark')
         ,('threadmark', 'news_feed_handler_class', 'Sidane_Threadmarks_NewsFeedHandler_Threadmark')
    ");
    if ($version <= 9) {
      SV_Utils_Install::addColumn('threadmarks', 'position', 'int not null default 0');
      SV_Utils_Install::addIndex('threadmarks', 'thread_position', array('thread_id', 'position'));

      SV_Utils_Install::addColumn('threadmarks', 'user_id', 'int not null default 0');
      $db->query("UPDATE threadmarks mark
        JOIN xf_post post ON mark.post_id = post.post_id
        SET mark.user_id = post.user_id
        WHERE mark.user_id = 0
        ");
      SV_Utils_Install::addIndex('threadmarks', 'user_id', array('user_id'));
      SV_Utils_Install::addColumn('threadmarks', 'post_date', 'int not null default 0');
      $db->query("UPDATE threadmarks mark
        JOIN xf_post post ON mark.post_id = post.post_id
        SET mark.post_date = post.post_date
        WHERE mark.post_date = 0
        ");
      SV_Utils_Install::addColumn('threadmarks', 'message_state', "enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible'");
      SV_Utils_Install::addColumn('threadmarks', 'edit_count', 'int not null default 0');
      SV_Utils_Install::addColumn('threadmarks', 'last_edit_date', 'int not null default 0');
      SV_Utils_Install::addColumn('threadmarks', 'last_edit_user_id', 'int not null default 0');
    }

    if ($version < 1020002) {
      SV_Utils_Install::renameColumn('threadmarks', 'post_date', 'threadmark_date', 'int not null default 0');
      SV_Utils_Install::addColumn('threadmarks', 'parent_threadmark_id', 'INT UNSIGNED DEFAULT 0 NOT NULL');
      SV_Utils_Install::addColumn('threadmarks', 'depth', 'INT UNSIGNED DEFAULT 0 NOT NULL');
    }

    if (!$db->fetchRow("SHOW TABLES LIKE 'threadmark_category'")) {
      // create threadmark category table
      $db->query(
        "CREATE TABLE IF NOT EXISTS threadmark_category (
          threadmark_category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          display_order INT UNSIGNED NOT NULL DEFAULT 0,
          allowed_user_group_ids VARBINARY(255) NOT NULL DEFAULT '2',
          PRIMARY KEY (threadmark_category_id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci"
      );
    }

    // add column to threadmarks table if it is non-existent
    SV_Utils_Install::addColumn(
      'threadmarks',
      'threadmark_category_id',
      'INT UNSIGNED NOT NULL'
    );

    if ($version < 1050000) {
      // ensure model is loaded before accessing static properties
      XenForo_Model::create('XenForo_Model_User');
      $defaultUserGroupId = XenForo_Model_User::$defaultRegisteredGroupId;

      // create default threadmark category
      $db->query(
        'INSERT IGNORE INTO threadmark_category
          (threadmark_category_id, display_order, allowed_user_group_ids)
          VALUES
            (1, 0, ?)',
        array($defaultUserGroupId)
      );
      // add default threadmark category title to phrases
      $db->query(
        "INSERT IGNORE INTO xf_phrase
          (language_id, title, phrase_text, global_cache, addon_id)
        VALUES
          (0, 'sidane_threadmarks_category_1_title', 'Threadmarks', 0, '')"
      );

      // set all threadmarks to be in the default category
      $db->query(
        'UPDATE threadmarks
          SET threadmark_category_id = 1
          WHERE threadmark_category_id = 0'
      );
    }

    // $version < 1050005
    SV_Utils_Install::addColumn(
      'xf_thread',
      'threadmark_category_data',
      'TEXT'
    );

    SV_Utils_Install::dropColumn('xf_thread', 'firstThreadmarkId');
    SV_Utils_Install::dropColumn('xf_thread', 'lastThreadmarkId');
    SV_Utils_Install::dropColumn('xf_thread', 'threadmark_category_positions');

    if ($version < 1050007) {
      $requireIndexing['threadmark'] = true;
    }

    if ($version && ($version < 1050025 || ($version >= 1060000 && $version < 1060018))) {
      XenForo_Application::defer('Sidane_Threadmarks_Deferred_Cache', array('resync' => false), null, true);
    }

    if ($version && ($version < 1050100 || ($version >= 1060000 && $version < 1060300))) {
      $db->query(
        'UPDATE threadmarks a
        JOIN threadmarks b ON (a.parent_threadmark_id = b.threadmark_id AND a.threadmark_category_id <> b.threadmark_category_id)
        SET a.parent_threadmark_id = 0, a.depth = 0'
      );
    }

    // if Elastic Search is installed, determine if we need to push optimized mappings for the search types
    // requires overriding XenES_Model_Elasticsearch
    SV_Utils_Deferred_Search::SchemaUpdates($requireIndexing);
  }

  public static function uninstall()
  {
    SV_Utils_Install::dropColumn('xf_thread', 'has_threadmarks');
    SV_Utils_Install::dropColumn('xf_thread', 'threadmark_count');
    SV_Utils_Install::dropColumn('xf_thread', 'firstThreadmarkId');
    SV_Utils_Install::dropColumn('xf_thread', 'lastThreadmarkId');
    SV_Utils_Install::dropColumn('xf_thread', 'threadmark_category_data');

    $db = XenForo_Application::get('db');
    $db->query("DROP TABLE IF EXISTS threadmarks");
    $db->query('DROP TABLE IF EXISTS threadmark_category');

    $db->query("DELETE FROM xf_permission_entry
        WHERE permission_id IN ('sidane_tm_manage', 'sidane_tm_add', 'sidane_tm_delete', 'sidane_tm_edit', 'sidane_tm_menu_limit', 'sidane_tm_view')
    ");
    $db->query("DELETE FROM xf_permission_entry_content
        WHERE permission_id IN ('sidane_tm_manage', 'sidane_tm_add', 'sidane_tm_delete', 'sidane_tm_edit', 'sidane_tm_menu_limit', 'sidane_tm_view')
    ");

    $db->query("
      DELETE FROM xf_content_type
      WHERE xf_content_type.addon_id = 'sidaneThreadmarks'
    ");

    $db->query("
      DELETE FROM xf_content_type_field
      WHERE xf_content_type_field.field_value LIKE 'Sidane_Threadmarks_%'
    ");
  }
}
