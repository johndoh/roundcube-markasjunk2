<?php

/**
 * SpamAssassin Blacklist driver
 * @version 2.0
 * @requires SAUserPrefs plugin
 * @author Philip Weir
 */

class markasjunk2_sa_blacklist
{
	public function spam($uids)
	{
		$this->_do_list($uids, true);
	}

	public function ham($uids)
	{
		$this->_do_list($uids, false);
	}

	private function _do_list($uids, $spam)
	{
		$rcmail = rcube::get_instance();
		if (is_file($rcmail->config->get('markasjunk2_sauserprefs_config')) && !$rcmail->config->load_from_file($rcmail->config->get('markasjunk2_sauserprefs_config'))) {
			rcube::raise_error(array('code' => 527, 'type' => 'php',
				'file' => __FILE__, 'line' => __LINE__,
				'message' => "Failed to load config from " . $rcmail->config->get('markasjunk2_sauserprefs_config')), true, false);
			return false;
		}

		$db = rcube_db::factory($rcmail->config->get('sauserprefs_db_dsnw'), $rcmail->config->get('sauserprefs_db_dsnr'), $rcmail->config->get('sauserprefs_db_persistent'));
		$db->db_connect('w');

		// check DB connections and exit on failure
		if ($err_str = $db->is_error()) {
			rcube::raise_error(array(
				'code' => 603,
				'type' => 'db',
				'message' => $err_str), FALSE, TRUE);
		}

		foreach (explode(",", $uids) as $uid) {
			$message = new rcube_message($uid);
			$email = $message->sender['mailto'];

			if ($spam) {
				// delete any whitelisting for this address
				$db->query(
					"DELETE FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = ?;",
					$_SESSION['username'],
					'whitelist_from',
					$email);

				// check address is not already blacklisted
				$sql_result = $db->query(
								"SELECT value FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = ?;",
								$_SESSION['username'],
								'blacklist_from',
								$email);

				if (!$db->fetch_array($sql_result)) {
					$db->query(
						"INSERT INTO ". $rcmail->config->get('sauserprefs_sql_table_name') ." (". $rcmail->config->get('sauserprefs_sql_username_field') .", ". $rcmail->config->get('sauserprefs_sql_preference_field') .", ". $rcmail->config->get('sauserprefs_sql_value_field') .") VALUES (?, ?, ?);",
						$_SESSION['username'],
						'blacklist_from',
						$email);

					if ($rcmail->config->get('markasjunk2_debug'))
						rcube::write_log('markasjunk2', $_SESSION['username'] . ' blacklist ' . $email);
				}
			}
			else {
				// delete any blacklisting for this address
				$db->query(
					"DELETE FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = ?;",
					$_SESSION['username'],
					'blacklist_from',
					$email);

				// check address is not already whitelisted
				$sql_result = $db->query(
								"SELECT value FROM ". $rcmail->config->get('sauserprefs_sql_table_name') ." WHERE ". $rcmail->config->get('sauserprefs_sql_username_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_preference_field') ." = ? AND ". $rcmail->config->get('sauserprefs_sql_value_field') ." = ?;",
								$_SESSION['username'],
								'whitelist_from',
								$email);

				if (!$db->fetch_array($sql_result)) {
					$db->query(
						"INSERT INTO ". $rcmail->config->get('sauserprefs_sql_table_name') ." (". $rcmail->config->get('sauserprefs_sql_username_field') .", ". $rcmail->config->get('sauserprefs_sql_preference_field') .", ". $rcmail->config->get('sauserprefs_sql_value_field') .") VALUES (?, ?, ?);",
						$_SESSION['username'],
						'whitelist_from',
						$email);

					if ($rcmail->config->get('markasjunk2_debug'))
						rcube::write_log('markasjunk2', $_SESSION['username'] . ' whitelist ' . $email);
				}
			}
		}
	}
}

?>