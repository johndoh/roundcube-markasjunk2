<?php

/**
 * Copy spam/ham messages to a direcotry for learning later
 * @version 0.1
 * @author Philip Weir
 */

function learn_spam($uids) {
	do_messagemove($uids, true);
}

function learn_ham($uids) {
	do_messagemove($uids, false);
}

function do_messagemove($uids, $spam) {
    $rcmail = rcmail::get_instance();

    if ($spam)
   		$dest_dir = unslashify($rcmail->config->get('markasjunk2_spam_dir'));
    else
    	$dest_dir = unslashify($rcmail->config->get('markasjunk2_ham_dir'));

    if (!$dest_dir)
    	return;

    $filename = $rcmail->config->get('markasjunk2_filename');
    $filename = str_replace('%u', $_SESSION['username'], $filename);
    $filename = str_replace('%t', ($spam) ? 'spam' : 'ham', $filename);

    if (strpos($_SESSION['username'], '@') !== false) {
        $parts = explode("@", $_SESSION['username'], 2);

        $filename = str_replace(array('%l', '%d'),
						array($parts[0], $parts[1]),
						$filename);
    }

	foreach (explode(",", $uids) as $uid) {
		$tmpfname = tempnam($dest_dir, $filename);
		file_put_contents($tmpfname, $rcmail->imap->get_raw_body($uid));

		if ($rcmail->config->get('markasjunk2_debug')) {
			write_log('markasjunk2', $tmpfname);
		}
	}
}

?>