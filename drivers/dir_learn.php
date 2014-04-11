<?php

/**
 * Copy spam/ham messages to a direcotry for learning later
 * @version 2.0
 * @author Philip Weir
 */

class markasjunk2_dir_learn
{
	public function spam($uids, $mbox)
	{
		$this->_do_messagemove($uids, true);
	}

	public function ham($uids, $mbox)
	{
		$this->_do_messagemove($uids, false);
	}

	private function _do_messagemove($uids, $spam)
	{
	    $rcmail = rcube::get_instance();

		if ($spam)
			$dest_dir = unslashify($rcmail->config->get('markasjunk2_spam_dir'));
		else
			$dest_dir = unslashify($rcmail->config->get('markasjunk2_ham_dir'));

		if (!$dest_dir)
			return;

		$filename = $rcmail->config->get('markasjunk2_filename');
		$filename = str_replace('%u', $_SESSION['username'], $filename);
		$filename = str_replace('%t', ($spam) ? 'spam' : 'ham', $filename);
		$filename = str_replace('%l', $rcmail->user->get_username('local'), $filename);
		$filename = str_replace('%d', $rcmail->user->get_username('domain'), $filename);

		foreach ($uids as $uid) {
			$tmpfname = tempnam($dest_dir, $filename);
			file_put_contents($tmpfname, $rcmail->storage->get_raw_body($uid));

			if ($rcmail->config->get('markasjunk2_debug'))
				rcube::write_log('markasjunk2', $tmpfname);
		}
	}
}

?>