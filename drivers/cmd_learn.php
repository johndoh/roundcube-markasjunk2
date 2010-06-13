<?php

/**
 * Command line learn driver
 * @version 1.0
 * @author Philip Weir
 */
function learn_spam($uids)
{
	do_salearn($uids, true);
}

function learn_ham($uids)
{
	do_salearn($uids, false);
}

function do_salearn($uids, $spam)
{
    $rcmail = rcmail::get_instance();
    $temp_dir = realpath($rcmail->config->get('temp_dir'));

    if ($spam)
    	$command = $rcmail->config->get('markasjunk2_spam_cmd');
    else
    	$command = $rcmail->config->get('markasjunk2_ham_cmd');

    if (!$command)
    	return;

    $command = str_replace('%u', $_SESSION['username'], $command);
    $command = str_replace('%l', markasjunk2::username_local(), $command);
    $command = str_replace('%d', markasjunk2::username_domain(), $command);

	foreach (explode(",", $uids) as $uid) {
		$tmpfname = tempnam($temp_dir, 'rcmSALearn');
		file_put_contents($tmpfname, $rcmail->imap->get_raw_body($uid));

		$tmp_command = str_replace('%f', $tmpfname, $command);
		exec($tmp_command, $output);

		if ($rcmail->config->get('markasjunk2_debug')) {
			write_log('markasjunk2', $tmp_command);
			write_log('markasjunk2', $output);
		}

		unlink($tmpfname);
		$output = '';
	}
}

?>