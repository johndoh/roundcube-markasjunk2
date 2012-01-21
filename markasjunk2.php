<?php

/**
 * MarkAsJunk2
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 * or to move messages in the Junk folder to the inbox - moving only the
 * attachment if it is a Spamassassin spam report email
 *
 * @version @package_version@
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

		if ($rcmail->action == '' || $rcmail->action == 'show') {
			$this->include_script('markasjunk2.js');
			$this->add_texts('localization', true);
			$this->include_stylesheet($this->local_skin_path() .'/markasjunk2.css');
			if ($rcmail->output->browser->ie && $rcmail->output->browser->ver == 6)
				$this->include_stylesheet($this->local_skin_path() . '/ie6hacks.css');

			$mb_override = ($this->spam_mbox) ? false : true;
			$display_junk = $display_not_junk = '';
			if ($_SESSION['mbox'] == $this->spam_mbox)
				$display_junk = 'display: none;';
			elseif (!$mb_override)
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

			$this->api->output->set_env('markasjunk2_override', $mb_override);
		}
	}

	function mark_junk()
	{
		$this->add_texts('localization');
		$this->_set_flags();

		$uids = rcube_ui::get_input_value('_uid', rcube_ui::INPUT_POST);
		$mbox = rcube_ui::get_input_value('_mbox', rcube_ui::INPUT_POST);

		$this->_spam($uids, $mbox, $this->spam_mbox);
		$this->api->output->command('display_message', $this->gettext('reportedasjunk'), 'confirmation');
		$this->api->output->send();
	}

	function mark_notjunk()
	{
		$this->add_texts('localization');
		$this->_set_flags();

		$uids = rcube_ui::get_input_value('_uid', rcube_ui::INPUT_POST);
		$mbox = rcube_ui::get_input_value('_mbox', rcube_ui::INPUT_POST);

		$this->_ham($uids, $mbox, $this->ham_mbox);
		$this->api->output->command('display_message', $this->gettext('reportedasnotjunk'), 'confirmation');
		$this->api->output->send();
	}

	private function _spam($uids, $mbox_name = NULL, $dest_mbox = NULL)
	{
		$rcmail = rcmail::get_instance();
		$storage = $rcmail->storage;

		if ($rcmail->config->get('markasjunk2_learning_driver', false))
			$this->_call_driver($uids, true);

		if ($rcmail->config->get('markasjunk2_read_spam', false))
			$storage->set_flag($uids, 'SEEN', $mbox_name);

		if ($rcmail->config->get('markasjunk2_spam_flag', false))
			$storage->set_flag($uids, $this->spam_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_ham_flag', false))
			$storage->unset_flag($uids, $this->ham_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_move_spam', true) && $dest_mbox && $mbox_name != $dest_mbox)
			$this->api->output->command('rcmail_markasjunk2_move', $dest_mbox, $uids);
		else
			$this->api->output->command('command', 'list', $mbox_name);
	}

	private function _ham($uids, $mbox_name = NULL, $dest_mbox = NULL)
	{
		$rcmail = rcmail::get_instance();
		$storage = $rcmail->storage;

		if ($rcmail->config->get('markasjunk2_learning_driver', false))
			$this->_call_driver($uids, false);

		if ($rcmail->config->get('markasjunk2_unread_ham', false))
			$storage->unset_flag($uids, 'SEEN', $mbox_name);

		if ($rcmail->config->get('markasjunk2_spam_flag', false))
			$storage->unset_flag($uids, $this->spam_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_ham_flag', false))
			$storage->set_flag($uids, $this->ham_flag, $mbox_name);

		if ($rcmail->config->get('markasjunk2_move_ham', true) && $dest_mbox && $mbox_name != $dest_mbox)
			$this->api->output->command('rcmail_markasjunk2_move', $dest_mbox, $uids);
		else
			$this->api->output->command('command', 'list', $mbox_name);
	}

	private function _call_driver(&$uids, $spam)
	{
		$driver = $this->home.'/drivers/'.rcmail::get_instance()->config->get('markasjunk2_learning_driver', 'cmd_learn').'.php';

		if (!is_readable($driver)) {
			rcube_ui::raise_error(array(
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
			rcube_ui::raise_error(array(
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
			if ($flag = array_search($rcmail->config->get('markasjunk2_spam_flag'), $rcmail->storage->conn->flags))
				$this->spam_flag = $flag;
			else
				$rcmail->storage->conn->flags[$this->spam_flag] = $rcmail->config->get('markasjunk2_spam_flag');
		}

		if ($rcmail->config->get('markasjunk2_ham_flag', false)) {
			if ($flag = array_search($rcmail->config->get('markasjunk2_ham_flag'), $rcmail->storage->conn->flags))
				$this->ham_flag = $flag;
			else
				$rcmail->storage->conn->flags[$this->ham_flag] = $rcmail->config->get('markasjunk2_ham_flag');
		}
	}
}

?>