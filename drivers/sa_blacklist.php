<?php

/**
 * SpamAssassin Blacklist driver
 * @version 1.0
 * @requires SAUserPrefs plugin
 * @author Philip Weir
 */
function learn_spam($uids)
{
	do_list($uids, true);
}

function learn_ham($uids)
{
	do_list($uids, false);
}

function do_list($uids, $spam)
{
	$rcmail = rcmail::get_instance();
	if (is_file($rcmail->config->get('addresssync_sauserprefs_config')) && !$rcmail->config->load_from_file($rcmail->config->get('addresssync_sauserprefs_config'))) {
		raise_error(array('code' => 527, 'type' => 'php',
			'file' => __FILE__, 'line' => __LINE__,
			'message' => "Failed to load config from " . $rcmail->config->get('addresssync_sauserprefs_config')), true, false);

		return false;
	}

	$db = new rcube_mdb2($rcmail->config->get('sauserprefs_db_dsnw'), $rcmail->config->get('sauserprefs_db_dsnr'), $rcmail->config->get('sauserprefs_db_persistent'));
	$db->db_connect('w');

	// check DB connections and exit on failure
	if ($err_str = $db->is_error()) {
		raise_error(array(
			'code' => 603,
			'type' => 'db',
			'message' => $err_str), FALSE, TRUE);
	}

	foreach (explode(",", $uids) as $uid) {
		$message = new rcube_message($uid);
		$email = $message->sender['mailto'];

		if ($spam) {
			// check address is not already blacklisted
			$sql_result = $db->query("SELECT value FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = '". $_SESSION['username'] ."' AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = 'blacklist_from' AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = '". $email ."';");
			if ($db->num_rows($sql_result) == 0) {
				$db->query("INSERT INTO ". $rcmail->config->get('sauserprefs_sql_table_name') ." (". $rcmail->config->get('sauserprefs_sql_username_field') .", ". $rcmail->config->get('sauserprefs_sql_preference_field') .", ". $rcmail->config->get('sauserprefs_sql_value_field') .") VALUES ('". $_SESSION['username'] ."', 'blacklist_from', '". $email ."');");

				if ($rcmail->config->get('markasjunk2_debug'))
					write_log('markasjunk2', $_SESSION['username'] . ' blacklist ' . $email);
			}
		}
		else {
			// check address is not already whitelisted
			$sql_result = $db->query("SELECT value FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = '". $_SESSION['username'] ."' AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = 'whitelist_from' AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = '". $email ."';");
			if ($db->num_rows($sql_result) == 0) {
				$db->query("INSERT INTO ". $rcmail->config->get('sauserprefs_sql_table_name') ." (". $rcmail->config->get('sauserprefs_sql_username_field') .", ". $rcmail->config->get('sauserprefs_sql_preference_field') .", ". $rcmail->config->get('sauserprefs_sql_value_field') .") VALUES ('". $_SESSION['username'] ."', 'whitelist_from', '". $email ."');");

				if ($rcmail->config->get('markasjunk2_debug'))
					write_log('markasjunk2', $_SESSION['username'] . ' whitelist ' . $email);
			}
		}
	}
}

?>