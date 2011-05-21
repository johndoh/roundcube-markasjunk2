<?php

/**
 * MarkAsJunk2
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 * or to move messages in the Junk folder to the inbox - moving only the
 * attachment if it is a Spamassassin spam report email
 *
 * @version 1.2
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
		$this->register_action('plugin.markasjunk2.junk', array($this, 'mark_junk'));
		$this->register_action('plugin.markasjunk2.not_junk', array($this, 'mark_notjunk'));

		$rcmail = rcmail::get_instance();
		$this->load_config();
		$this->spam_mbox = $rcmail->config->get('junk_mbox', null);
		$this->toolbar = $rcmail->config->get('markasjunk2_mb_toolbar', true);

		if ($this->spam_mbox && ($rcmail->action == '' || $rcmail->action == 'show')) {
			$this->include_script('markasjunk2.js');
			$this->add_texts('localization', true);
			$this->include_stylesheet($this->local_skin_path() .'/markasjunk2.css');
			if ($rcmail->output->browser->ie && $rcmail->output->browser->ver == 6)
				$this->include_stylesheet($this->local_skin_path() . '/ie6hacks.css');

			$display_junk = $display_not_junk = '';
			if ($_SESSION['mbox'] == $this->spam_mbox)
				$display_junk = 'display: none;';
			else
				$display_not_junk = 'display: none;';

			if ($rcmail->action == 'show') {
				$this->add_button(array('command' => 'plugin.markasjunk2.junk', 'type' => 'link', 'class' => 'buttonPas markasjunk2', 'classact' => 'button markasjunk2', 'classsel' => 'button markasjunk2Sel', 'title' => 'markasjunk2.buttonjunk', 'content' => ' ', 'style' => $display_junk), 'toolbar');
				$this->add_button(array('command' => 'plugin.markasjunk2.not_junk', 'type' => 'link', 'class' => 'buttonPas markasnotjunk2', 'classact' => 'button markasnotjunk2', 'classsel' => 'button markasnotjunk2Sel', 'title' => 'markasjunk2.buttonnotjunk', 'content' => ' ', 'style' => $display_not_junk), 'toolbar');
			}
			elseif ($this->toolbar) {
				$this->add_button(array('command' => 'plugin.markasjunk2.junk', 'type' => 'link', 'class' => 'buttonPas markasjunk2', 'classact' => 'button markasjunk2', 'classsel' => 'button markasjunk2Sel', 'title' => 'markasjunk2.buttonjunk', 'content' => ' ', 'style' => $display_junk), 'toolbar');
				$this->add_button(array('command' => 'plugin.markasjunk2.not_junk', 'type' => 'link', 'class' => 'buttonPas markasnotjunk2', 'classact' => 'button markasnotjunk2', 'classsel' => 'button markasnotjunk2Sel', 'title' => 'markasjunk2.buttonnotjunk', 'content' => ' ', 'style' => $display_not_junk), 'toolbar');
			}
			else {
				$markjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2.junk', 'label' => 'markasjunk2.markasjunk', 'id' => 'markasjunk2', 'class' => 'markasjunk2', 'classact' => 'markasjunk2 active'));
				$marknotjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2.not_junk', 'label' => 'markasjunk2.markasnotjunk', 'id' => 'markasnotjunk2', 'class' => 'markasnotjunk2', 'classact' => 'markasnotjunk2 active'));
				$this->api->add_content(html::tag('li', array('style' => $display_junk), $markjunk), 'markmenu');
				$this->api->add_content(html::tag('li', array('style' => $display_not_junk), $marknotjunk), 'markmenu');
			}
		}
	}

	function mark_junk()
	{
		$this->add_texts('localization');
		$this->_set_flags();

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
		$this->_set_flags();
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
						if ($part->ctype_primary == 'message' && $part->ctype_secondary == 'rfc822') {
							$orig_message_raw = $imap->get_message_part($message->uid, $part->mime_id, $part);
							$saved = $imap->save_message($dest_mbox, $orig_message_raw);

							if ($saved) {
								$this->api->output->command('rcmail_markasjunk2_move', null, $uid);

								// Assume the one we just added has the highest UID
								$uids = $imap->conn->fetchUIDs($dest_mbox);
								$orig_uid = end($uids);

								$this->_ham($orig_uid, $dest_mbox);
							}
						}
					}
				}

				// if not SA report with attachment then move the whole message
				if (!$saved) {
					$this->_ham($uid);
					$this->api->output->command('rcmail_markasjunk2_move', $dest_mbox, $uid);
				}
			}

			$this->api->output->command('display_message', $this->gettext('reportedasnotjunk'), 'confirmation');
			$this->api->output->send();
		}
	}

	private function _spam($uids, $mbox_name = NULL)
	{
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

	private function _ham($uids, $mbox_name = NULL)
	{
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;

		if ($rcmail->config->get('markasjunk2_unread_ham', false))
			$imap->unset_flag($uids, 'SEEN', $mbox_name);

		if ($rcmail->config->get('markasjunk2_spam_flag', false))
			$imap->unset_flag($uids, $this->spam_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_ham_flag', false))
			$imap->set_flag($uids, $this->ham_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_learning_driver', false))
			$this->_call_driver($uids, false);
	}

	private function _call_driver($uids, $spam)
	{
		$driver = $this->home.'/drivers/'.rcmail::get_instance()->config->get('markasjunk2_learning_driver', 'cmd_learn').'.php';

		if (!is_readable($driver)) {
			raise_error(array(
				'code' => 600,
				'type' => 'php',
				'file' => __FILE__,
				'line' => __LINE__,
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
				'line' => __LINE__,
				'message' => "MarkasJunk2 plugin: Broken driver: $driver"
				), true, false);
			return $this->gettext('internalerror');
		}

		if ($spam)
			learn_spam($uids);
		else
			learn_ham($uids);
	}

	private function _set_flags()
	{
		$rcmail = rcmail::get_instance();

		if ($rcmail->config->get('markasjunk2_spam_flag', false)) {
			if ($flag = array_search($rcmail->config->get('markasjunk2_spam_flag'), $rcmail->imap->conn->flags))
				$this->spam_flag = $flag;
			else
				$rcmail->imap->conn->flags[$this->spam_flag] = $rcmail->config->get('markasjunk2_spam_flag');
		}

		if ($rcmail->config->get('markasjunk2_ham_flag', false)) {
			if ($flag = array_search($rcmail->config->get('markasjunk2_ham_flag'), $rcmail->imap->conn->flags))
				$this->ham_flag = $flag;
			else
				$rcmail->imap->conn->flags[$this->ham_flag] = $rcmail->config->get('markasjunk2_ham_flag');
		}
	}
}

?>