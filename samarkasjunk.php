<?php

/**
 * Spamassassin Mark as Junk
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 * or to move messages in the Junk folder to the inbox - moving only the
 * attachment if it is a Spamassassin spam report email
 *
 * @version 1.0
 * @author Philip Weir
 * Base on the Markasjunk plugin by Thomas Bruederli
 */
class samarkasjunk extends rcube_plugin
{
	public $task = 'mail';
	private $spam_mbox = null;
	private $ham_mbox = 'INBOX';
	private $spam_flag = 'SAJUNK';
	private $ham_flag = 'SANOTJUNK';

	function init()
	{
		$this->register_action('plugin.samarkasjunk', array($this, 'mark_junk'));
		$this->register_action('plugin.samarkasnotjunk', array($this, 'mark_notjunk'));

		$rcmail = rcmail::get_instance();
		$this->load_config();
		$this->spam_mbox = $rcmail->config->get('junk_mbox', null);

		if ($rcmail->config->get('samarkasjunk_spam_flag', false)) {
			if ($flag = array_search($rcmail->config->get('samarkasjunk_spam_flag'), $GLOBALS['IMAP_FLAGS']))
				$this->spam_flag = $flag;
			else
				$GLOBALS['IMAP_FLAGS'][$this->spam_flag] = $rcmail->config->get('samarkasjunk_spam_flag');
		}

		if ($rcmail->config->get('samarkasjunk_ham_flag', false)) {
			if ($flag = array_search($rcmail->config->get('samarkasjunk_ham_flag'), $GLOBALS['IMAP_FLAGS']))
				$this->ham_flag = $flag;
			else
				$GLOBALS['IMAP_FLAGS'][$this->ham_flag] = $rcmail->config->get('samarkasjunk_ham_flag');
		}

		if (($rcmail->action == '' || $rcmail->action == 'show') && !empty($rcmail->user->ID)) {
			$this->include_script('samarkasjunk.js');
			$this->add_texts('localization', true);

			if ($rcmail->action == 'show' && ($this->spam_mbox && $_SESSION['mbox'] != $this->spam_mbox)) {
				$this->add_button(array('command' => 'plugin.samarkasjunk', 'imagepas' => 'skins/'. $this->api->output->config['skin'] .'/junk_pas.png', 'imageact' => 'skins/'. $this->api->output->config['skin'] .'/junk_act.png', 'alt' => 'samarkasjunk.buttonjunk', 'title' => 'samarkasjunk.buttonjunk'), 'toolbar');
			}
			elseif ($rcmail->action == 'show' && ($this->spam_mbox && $_SESSION['mbox'] == $this->spam_mbox)) {
				$this->add_button(array('command' => 'plugin.samarkasnotjunk', 'imagepas' => 'skins/'. $this->api->output->config['skin'] .'/notjunk_pas.png', 'imageact' => 'skins/'. $this->api->output->config['skin'] .'/notjunk_act.png', 'alt' => 'samarkasjunk.buttonnotjunk', 'title' => 'samarkasjunk.buttonnotjunk'), 'toolbar');
			}
			elseif ($this->spam_mbox) {
				$skin_path = 'skins/'. $this->api->output->config['skin'] .'/samarkasjunk.css';
				$skin_path = is_file($this->home .'/'. $skin_path) ? $skin_path : 'skins/default/samarkasjunk.css';
				$this->include_stylesheet($skin_path);

				if ($_SESSION['mbox'] == $this->spam_mbox) {
					$markjunk = $this->api->output->button(array('command' => 'plugin.samarkasjunk', 'label' => 'samarkasjunk.markasjunk', 'class' => 'samarkasjunk', 'classact' => 'samarkasjunk active', 'style' => 'display: none;'));
					$marknotjunk = $this->api->output->button(array('command' => 'plugin.samarkasnotjunk', 'label' => 'samarkasjunk.markasnotjunk', 'class' => 'samarkasnotjunk', 'classact' => 'samarkasnotjunk active'));
				}
				else {
					$markjunk = $this->api->output->button(array('command' => 'plugin.samarkasjunk', 'label' => 'samarkasjunk.markasjunk', 'class' => 'samarkasjunk', 'classact' => 'samarkasjunk active'));
					$marknotjunk = $this->api->output->button(array('command' => 'plugin.samarkasnotjunk', 'label' => 'samarkasjunk.markasnotjunk', 'class' => 'samarkasnotjunk', 'classact' => 'samarkasnotjunk active', 'style' => 'display: none;'));
				}

				$this->api->add_content(html::tag('li', null, $markjunk), 'markmenu');
				$this->api->add_content(html::tag('li', null, $marknotjunk), 'markmenu');
			}
		}
	}

	function mark_junk()
	{
		$this->add_texts('localization');
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;

		$uids = get_input_value('_uid', RCUBE_INPUT_POST);
		$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);

		if (($dest_mbox = $this->spam_mbox) && $mbox != $dest_mbox) {
			$this->_spam($uids);

			$this->api->output->command('rcmail_samarkasjunk_move', $dest_mbox, $uids);

			$this->api->output->command('display_message', $this->gettext('reportedasjunk'), 'confirmation');
			$this->api->output->send();
		}
	}

	function mark_notjunk()
	{
		$this->add_texts('localization');
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;

		$uids = get_input_value('_uid', RCUBE_INPUT_POST);
		$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);

		if (($dest_mbox = $this->ham_mbox) && $mbox != $dest_mbox) {
			foreach (split(",", $uids) as $uid) {
				$saved = FALSE;
				$message = new rcube_message($uid);

				if ($rcmail->config->get('samarkasjunk_detach_ham', false) && sizeof($message->attachments)) {
					foreach ($message->attachments as $part) {
						if ($part->ctype_primary == 'message' && $part->ctype_parameters['x-spam-type'] == 'original') {
							$orig_message_raw = $imap->get_message_part($message->uid, $part->mime_id, $part);
							$saved = $imap->save_message($dest_mbox, $orig_message_raw);

							if ($saved) {
								$imap->delete_message($uid, $mbox);
								$this->api->output->command('message_list.remove_row', $uid, false);

								$orig_message = new Mail_mimeDecode($orig_message_raw);
								$orig_headers = $orig_message->decode(array('decode_headers' => true));
								$a_messageid = $imap->search($dest_mbox, 'HEADER Message-ID ' . $orig_headers->headers['message-id']);
								$orig_uid = $imap->get_uid($a_messageid[0], $dest_mbox);

								$this->_ham($orig_uid, $dest_mbox);
							}
						}
					}
				}

				// if not SA report with attachment then move the whole message
				if (!$saved) {
					$this->_ham($uid);
					$this->api->output->command('rcmail_samarkasjunk_move', $dest_mbox, $uid);
				}
			}

			$this->api->output->command('set_unread_count', $dest_mbox, $imap->messagecount($dest_mbox, 'UNSEEN'), TRUE);
			$this->api->output->command('display_message', $this->gettext('reportedasnotjunk'), 'confirmation');
			$this->api->output->send();
		}
	}

	private function _spam($uids, $mbox_name=NULL) {
		$rcmail = rcmail::get_instance();

		if ($rcmail->config->get('samarkasjunk_read_spam', false))
			$imap->set_flag($uids, 'SEEN', $mbox_name);

		if ($rcmail->config->get('samarkasjunk_spam_flag', false))
			$imap->set_flag($uids, $this->spam_flag, $mbox_name);

		if ($rcmail->config->get('samarkasjunk_ham_flag', false))
			$imap->unset_flag($uids, $this->ham_flag, $mbox_name);

		if ($rcmail->config->get('samarkasjunk_spam_cmd', false))
			$this->_salearn($uids, true);
	}

	private function _ham($uids, $mbox_name=NULL) {
		$rcmail = rcmail::get_instance();

		if ($rcmail->config->get('samarkasjunk_unread_ham', false))
			$imap->unset_flag($uids, 'SEEN', $mbox_nam);

		if ($rcmail->config->get('samarkasjunk_spam_flag', false))
			$imap->unset_flag($uids, $this->spam_flag, $mbox_nam);

		if ($rcmail->config->get('samarkasjunk_ham_flag', false))
			$imap->set_flag($uids, $this->ham_flag, $mbox_nam);

		if ($rcmail->config->get('samarkasjunk_ham_cmd', false))
			$this->_salearn($uids, false);
	}

	private function _salearn($uids, $spam) {
        $rcmail = rcmail::get_instance();
        $temp_dir = realpath($rcmail->config->get('temp_dir'));

        $config_spam = str_replace('%u', $_SESSION['username'], $rcmail->config->get('samarkasjunk_spam_cmd'));
        $config_ham = str_replace('%u', $_SESSION['username'], $rcmail->config->get('samarkasjunk_ham_cmd'));

        if (strpos($_SESSION['username'], '@') !== false) {
	        $parts = split("@", $_SESSION['username'], 2);

	        $config_spam = str_replace(array('%l', '%d'),
							array($parts[0], $parts[1]),
							$config_spam);

	        $config_ham = str_replace(array('%l', '%d'),
							array($parts[0], $parts[1]),
							$config_ham);

        }

		foreach (split(",", $uids) as $uid) {
			$tmpfname = tempnam($temp_dir, 'rcmSALearn');
			file_put_contents($tmpfname, $rcmail->imap->get_raw_body($uid));

			$spam_command = str_replace('%f', $tmpfname, $config_spam);
			$ham_command = str_replace('%f', $tmpfname, $config_ham);

  			if ($spam)
  				exec($spam_command, $output);
  			else
				exec($ham_command, $output);

			if ($rcmail->config->get('samarkasjunk_debug'))
				write_log('samarkasjunk', $output);

			unlink($tmpfname);
		}
	}
}

?>