<?php

/**
 * Mark as Junk 2
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 * or to move messages in the Junk folder to the inbox - moving only the
 * attachment if it is a Spamassassin spam report email
 *
 * @version 1.1
 * @author Philip Weir
 * Based on the Markasjunk plugin by Thomas Bruederli
 */
class markasjunk2 extends rcube_plugin
{
	public $task = 'mail';
	private $spam_mbox = null;
	private $ham_mbox = 'INBOX';
	private $spam_flag = 'JUNK';
	private $ham_flag = 'NOTJUNK';
	private $toolbar = true;

	function init()
	{
		$this->register_action('plugin.markasjunk2', array($this, 'mark_junk'));
		$this->register_action('plugin.markasnotjunk2', array($this, 'mark_notjunk'));

		$rcmail = rcmail::get_instance();
		$this->load_config();
		$this->spam_mbox = $rcmail->config->get('junk_mbox', null);
		$this->toolbar = $rcmail->config->get('markasjunk2_mb_toolbar', true);

		if ($rcmail->config->get('markasjunk2_spam_flag', false)) {
			if ($flag = array_search($rcmail->config->get('markasjunk2_spam_flag'), $GLOBALS['IMAP_FLAGS']))
				$this->spam_flag = $flag;
			else
				$GLOBALS['IMAP_FLAGS'][$this->spam_flag] = $rcmail->config->get('markasjunk2_spam_flag');
		}

		if ($rcmail->config->get('markasjunk2_ham_flag', false)) {
			if ($flag = array_search($rcmail->config->get('markasjunk2_ham_flag'), $GLOBALS['IMAP_FLAGS']))
				$this->ham_flag = $flag;
			else
				$GLOBALS['IMAP_FLAGS'][$this->ham_flag] = $rcmail->config->get('markasjunk2_ham_flag');
		}

		if (($rcmail->action == '' || $rcmail->action == 'show') && !empty($rcmail->user->ID)) {
			$this->include_script('markasjunk2.js');
			$this->add_texts('localization', true);

			if ($rcmail->action == 'show' && ($this->spam_mbox && $_SESSION['mbox'] != $this->spam_mbox)) {
				$this->add_button(array('command' => 'plugin.markasjunk2', 'imagepas' => $this->local_skin_path() .'/junk_pas.png', 'imageact' => $this->local_skin_path() .'/junk_act.png', 'alt' => 'markasjunk2.buttonjunk', 'title' => 'markasjunk2.buttonjunk'), 'toolbar');
			}
			elseif ($rcmail->action == 'show' && ($this->spam_mbox && $_SESSION['mbox'] == $this->spam_mbox)) {
				$this->add_button(array('command' => 'plugin.markasnotjunk2', 'imagepas' => $this->local_skin_path() .'/notjunk_pas.png', 'imageact' => $this->local_skin_path() .'/notjunk_act.png', 'alt' => 'markasjunk2.buttonnotjunk', 'title' => 'markasjunk2.buttonnotjunk'), 'toolbar');
			}
			elseif ($this->spam_mbox && $this->toolbar) {
				if ($_SESSION['mbox'] == $this->spam_mbox) {
					$this->add_button(array('command' => 'plugin.markasjunk2', 'id' => 'markasjunk2', 'imagepas' => $this->local_skin_path() .'/junk_pas.png', 'imageact' => $this->local_skin_path() .'/junk_act.png', 'alt' => 'markasjunk2.buttonjunk', 'title' => 'markasjunk2.buttonjunk', 'style' => 'display: none;'), 'toolbar');
					$this->add_button(array('command' => 'plugin.markasnotjunk2', 'id' => 'markasnotjunk2', 'imagepas' => $this->local_skin_path() .'/notjunk_pas.png', 'imageact' => $this->local_skin_path() .'/notjunk_act.png', 'alt' => 'markasjunk2.buttonnotjunk', 'title' => 'markasjunk2.buttonnotjunk'), 'toolbar');
				}
				else {
					$this->add_button(array('command' => 'plugin.markasjunk2', 'id' => 'markasjunk2', 'imagepas' => $this->local_skin_path() .'/junk_pas.png', 'imageact' => $this->local_skin_path() .'/junk_act.png', 'alt' => 'markasjunk2.buttonjunk', 'title' => 'markasjunk2.buttonjunk'), 'toolbar');
					$this->add_button(array('command' => 'plugin.markasnotjunk2', 'id' => 'markasnotjunk2', 'imagepas' => $this->local_skin_path() .'/notjunk_pas.png', 'imageact' => $this->local_skin_path() .'/notjunk_act.png', 'alt' => 'markasjunk2.buttonnotjunk', 'title' => 'markasjunk2.buttonnotjunk', 'style' => 'display: none;'), 'toolbar');
				}
			}
			elseif ($this->spam_mbox) {
				$this->include_stylesheet($this->local_skin_path() .'/markasjunk2.css');

				if ($_SESSION['mbox'] == $this->spam_mbox) {
					$markjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2', 'label' => 'markasjunk2.markasjunk', 'id' => 'markasjunk2', 'class' => 'markasjunk2', 'classact' => 'markasjunk2 active', 'style' => 'display: none;'));
					$marknotjunk = $this->api->output->button(array('command' => 'plugin.markasnotjunk2', 'label' => 'markasjunk2.markasnotjunk', 'id' => 'markasnotjunk2', 'class' => 'markasnotjunk2', 'classact' => 'markasnotjunk2 active'));
				}
				else {
					$markjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2', 'label' => 'markasjunk2.markasjunk', 'id' => 'markasjunk2', 'class' => 'markasjunk2', 'classact' => 'markasjunk2 active'));
					$marknotjunk = $this->api->output->button(array('command' => 'plugin.markasnotjunk2', 'label' => 'markasjunk2.markasnotjunk', 'id' => 'markasnotjunk2', 'class' => 'markasnotjunk2', 'classact' => 'markasnotjunk2 active', 'style' => 'display: none;'));
				}

				$this->api->add_content(html::tag('li', null, $markjunk), 'markmenu');
				$this->api->add_content(html::tag('li', null, $marknotjunk), 'markmenu');
			}
		}
	}

	function mark_junk()
	{
		$this->add_texts('localization');

		$uids = get_input_value('_uid', RCUBE_INPUT_POST);
		$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);

		if (($dest_mbox = $this->spam_mbox) && $mbox != $dest_mbox) {
			$this->_spam($uids);

			$this->api->output->command('rcmail_markasjunk2_move', $dest_mbox, $uids);

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
			foreach (explode(",", $uids) as $uid) {
				$saved = FALSE;
				$message = new rcube_message($uid);

				if ($rcmail->config->get('markasjunk2_detach_ham', false) && sizeof($message->attachments)) {
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
					$imap->move_message($uid, $dest_mbox);
					$this->api->output->command('message_list.remove_row', $uid, false);
				}
			}

			$this->api->output->command('set_unread_count', $dest_mbox, $imap->messagecount($dest_mbox, 'UNSEEN'), TRUE);
			$this->api->output->command('display_message', $this->gettext('reportedasnotjunk'), 'confirmation');
			$this->api->output->send();
		}
	}

	private function _spam($uids, $mbox_name=NULL) {
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;

		if ($rcmail->config->get('markasjunk2_read_spam', false))
			$imap->set_flag($uids, 'SEEN', $mbox_name);

		if ($rcmail->config->get('markasjunk2_spam_flag', false))
			$imap->set_flag($uids, $this->spam_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_ham_flag', false))
			$imap->unset_flag($uids, $this->ham_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_learning_driver', false))
			$this->_call_driver($uids, true);
	}

	private function _ham($uids, $mbox_name=NULL) {
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;

		if ($rcmail->config->get('markasjunk2_unread_ham', false))
			$imap->unset_flag($uids, 'SEEN', $mbox_nam);

		if ($rcmail->config->get('markasjunk2_spam_flag', false))
			$imap->unset_flag($uids, $this->spam_flag, $mbox_nam);

		if ($rcmail->config->get('markasjunk2_ham_flag', false))
			$imap->set_flag($uids, $this->ham_flag, $mbox_nam);

		if ($rcmail->config->get('markasjunk2_learning_driver', false))
			$this->_call_driver($uids, false);
	}

	private function _call_driver($uids, $spam) {
	    $driver = $this->home.'/drivers/'.rcmail::get_instance()->config->get('markasjunk2_learning_driver', 'cmd_learn').'.php';

	    if (!is_readable($driver)) {
	      raise_error(array(
	        'code' => 600,
	        'type' => 'php',
	        'file' => __FILE__,
	        'message' => "MarkasJunk2 plugin: Unable to open driver file $driver"
	        ), true, false);
	      return $this->gettext('internalerror');
	    }

	    include_once($driver);

	    if (!function_exists('learn_spam') || !function_exists('learn_ham')) {
	      raise_error(array(
	        'code' => 600,
	        'type' => 'php',
	        'file' => __FILE__,
	        'message' => "MarkasJunk2 plugin: Broken driver: $driver"
	        ), true, false);
	      return $this->gettext('internalerror');
	    }

	    if ($spam)
	    	learn_spam($uids);
	    else
	    	learn_ham($uids);

	}
}

?>
