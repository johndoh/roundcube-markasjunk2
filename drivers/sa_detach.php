<?php

/**
 * SpamAssassin detach ham driver
 * @version 2.0
 * @author Philip Weir
 */

class markasjunk2_sa_detach
{
	public function spam($uids, $mbox)
	{
		// do nothing
	}

	public function ham(&$uids, $mbox)
	{
		$rcmail = rcube::get_instance();
		$storage = $rcmail->storage;

		$new_uids = array();
		foreach ($uids as $uid) {
			$saved = false;
			$message = new rcube_message($uid);

			if (sizeof($message->attachments) > 0) {
				foreach ($message->attachments as $part) {
					if ($part->ctype_primary == 'message' && $part->ctype_secondary == 'rfc822' && $part->ctype_parameters['x-spam-type'] == 'original') {
						$orig_message_raw = $message->get_part_body($part->mime_id);
						$saved = $storage->save_message($mbox, $orig_message_raw);

						if ($saved !== false) {
							$rcmail->output->command('rcmail_markasjunk2_move', null, $uid);
							array_push($new_uids, $saved);
						}
					}
				}
			}
		}

		if (sizeof($new_uids) > 0)
			$uids = $new_uids;
	}
}

?>