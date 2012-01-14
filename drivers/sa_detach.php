<?php

/**
 * SpamAssassin detach ham driver
 * @version 1.0
 * @author Philip Weir
 */
function learn_spam($uids)
{
	// do nothing
}

function learn_ham(&$uids)
{
	$rcmail = rcmail::get_instance();
	$imap = $rcmail->imap;
	$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);

	$new_uids = array();
	foreach (explode(",", $uids) as $uid) {
		$saved = false;
		$message = new rcube_message($uid);

		if (sizeof($message->attachments) > 0) {
			foreach ($message->attachments as $part) {
				if ($part->ctype_primary == 'message' && $part->ctype_secondary == 'rfc822') {
					$orig_message_raw = $imap->get_message_part($message->uid, $part->mime_id, $part);
					$saved = $imap->save_message($mbox, $orig_message_raw);

					if ($saved !== false) {
						$rcmail->output->command('rcmail_markasjunk2_move', null, $uid);
						array_push($new_uids, $saved);
					}
				}
			}
		}
	}

	if (sizeof($new_uids) > 0)
		$uids = implode(',', $new_uids);
}

?>