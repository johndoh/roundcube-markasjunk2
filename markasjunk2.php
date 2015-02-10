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
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
 * For more information see README.md.
 * For configuration see config.inc.php.dist.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see http://www.gnu.org/licenses/.
 */
class markasjunk2 extends rcube_plugin
{
	public $task = 'mail';
	private $spam_mbox = null;
	private $ham_mbox = null;
	private $spam_flag = 'JUNK';
	private $ham_flag = 'NOTJUNK';
	private $toolbar = true;

	function init()
	{
		$this->register_action('plugin.markasjunk2.junk', array($this, 'mark_message'));
		$this->register_action('plugin.markasjunk2.not_junk', array($this, 'mark_message'));

		$rcmail = rcube::get_instance();
		$this->load_config();
		$this->ham_mbox = $rcmail->config->get('markasjunk2_ham_mbox', 'INBOX');
		$this->spam_mbox = $rcmail->config->get('markasjunk2_spam_mbox', $rcmail->config->get('junk_mbox', null));
		$this->toolbar = $rcmail->action == 'show' ? $rcmail->config->get('markasjunk2_cp_toolbar', true) : $rcmail->config->get('markasjunk2_mb_toolbar', true);

		// register the ham/spam flags with the core
		$this->add_hook('storage_init', array($this, 'set_flags'));

		if ($rcmail->action == '' || $rcmail->action == 'show') {
			$this->include_script('markasjunk2.js');
			$this->add_texts('localization', true);
			$this->include_stylesheet($this->local_skin_path() .'/markasjunk2.css');

			// check which folder we are currently in to display the correct button
			$mb_override = ($this->spam_mbox) ? false : true;
			$display_junk = $display_not_junk = '';

			if ($_SESSION['mbox'] == $this->spam_mbox) {
				$display_junk = 'display: none;';
			}
			elseif (!$mb_override) {
				$display_not_junk = 'display: none;';
			}

			if ($this->toolbar) {
				// add the buttons to the main toolbar
				$this->add_button(array('command' => 'plugin.markasjunk2.junk', 'type' => 'link', 'class' => 'button buttonPas markasjunk2 disabled', 'classact' => 'button markasjunk2', 'classsel' => 'button markasjunk2Sel', 'title' => 'markasjunk2.buttonjunk', 'label' => 'junk', 'style' => $display_junk), 'toolbar');
				$this->add_button(array('command' => 'plugin.markasjunk2.not_junk', 'type' => 'link', 'class' => 'button buttonPas markasnotjunk2 disabled', 'classact' => 'button markasnotjunk2', 'classsel' => 'button markasnotjunk2Sel', 'title' => 'markasjunk2.buttonnotjunk', 'label' => 'markasjunk2.notjunk', 'style' => $display_not_junk), 'toolbar');
			}
			else {
				// add the buttons to the mark message menu
				$markjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2.junk', 'label' => 'markasjunk2.markasjunk', 'id' => 'markasjunk2', 'class' => 'icon markasjunk2', 'classact' => 'icon markasjunk2 active', 'innerclass' => 'icon markasjunk2'));
				$marknotjunk = $this->api->output->button(array('command' => 'plugin.markasjunk2.not_junk', 'label' => 'markasjunk2.markasnotjunk', 'id' => 'markasnotjunk2', 'class' => 'icon markasnotjunk2', 'classact' => 'icon markasnotjunk2 active', 'innerclass' => 'icon markasnotjunk2'));
				$this->api->add_content(html::tag('li', array('style' => $display_junk, 'role' => 'menuitem'), $markjunk), 'markmenu');
				$this->api->add_content(html::tag('li', array('style' => $display_not_junk, 'role' => 'menuitem'), $marknotjunk), 'markmenu');
			}

			// add markasjunk2 folder settings to the env for JS
			$this->api->output->set_env('markasjunk2_override', $mb_override);
			$this->api->output->set_env('markasjunk2_ham_mailbox', $this->ham_mbox);
			$this->api->output->set_env('markasjunk2_spam_mailbox', $this->spam_mbox);

			$this->api->output->set_env('markasjunk2_move_spam', $rcmail->config->get('markasjunk2_move_spam', false));
			$this->api->output->set_env('markasjunk2_move_ham', $rcmail->config->get('markasjunk2_move_ham', false));
		}
	}

	function mark_message()
	{
		$this->add_texts('localization');

		$is_spam = rcube::get_instance()->action == 'plugin.markasjunk2.junk' ? true : false;
		$multi_folder = $_POST['_multifolder'] == 'true' ? true : false;
		$messageset = rcmail::get_uids();
		$mbox_name = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
		$dest_mbox = $is_spam ? $this->spam_mbox : $this->ham_mbox;
		$result = $is_spam ? $this->_spam($messageset, $dest_mbox) : $this->_ham($messageset, $dest_mbox);

		if ($result) {
			if ($dest_mbox && ($mbox_name !== $dest_mbox || $multi_folder)) {
				$this->api->output->command('rcmail_markasjunk2_move', $dest_mbox, $this->_messageset_to_uids($messageset, $multi_folder));
			}
			else {
				$this->api->output->command('command', 'list', $mbox_name);
			}

			$this->api->output->command('display_message', $is_spam ? $this->gettext('reportedasjunk') : $this->gettext('reportedasnotjunk'), 'confirmation');
		}

		$this->api->output->send();
	}

	function set_flags($p)
	{
		$rcmail = rcube::get_instance();

		$flags = array(
			$this->spam_flag => $rcmail->config->get('markasjunk2_spam_flag'),
			$this->ham_flag => $rcmail->config->get('markasjunk2_ham_flag')
		);

		$p['message_flags'] = array_merge((array)$p['message_flags'], $flags);

		return $p;
	}

	private function _spam(&$messageset, $dest_mbox = NULL)
	{
		$rcmail = rcube::get_instance();
		$storage = $rcmail->get_storage();
		$result = true;

		foreach ($messageset as $mbox => &$uids) {
			$storage->set_folder($mbox);

			if ($rcmail->config->get('markasjunk2_learning_driver', false)) {
				$result = $this->_call_driver($uids, $mbox, true);

				// abort function of the driver says so
				if (!$result)
					break;
			}

			if ($rcmail->config->get('markasjunk2_read_spam', false))
				$storage->set_flag($uids, 'SEEN', $mbox);

			if ($rcmail->config->get('markasjunk2_spam_flag', false))
				$storage->set_flag($uids, $this->spam_flag, $mbox);

			if ($rcmail->config->get('markasjunk2_ham_flag', false))
				$storage->unset_flag($uids, $this->ham_flag, $mbox);
		}

		return $result;
	}

	private function _ham(&$messageset, $dest_mbox = NULL)
	{
		$rcmail = rcube::get_instance();
		$storage = $rcmail->get_storage();
		$result = true;

		foreach ($messageset as $mbox => &$uids) {
			$storage->set_folder($mbox);

			if ($rcmail->config->get('markasjunk2_learning_driver', false)) {
				$result = $this->_call_driver($uids, $mbox, false);

				// abort function of the driver says so
				if (!$result)
					break;
			}

			if ($rcmail->config->get('markasjunk2_unread_ham', false))
				$storage->unset_flag($uids, 'SEEN', $mbox);

			if ($rcmail->config->get('markasjunk2_spam_flag', false))
				$storage->unset_flag($uids, $this->spam_flag, $mbox);

			if ($rcmail->config->get('markasjunk2_ham_flag', false))
				$storage->set_flag($uids, $this->ham_flag, $mbox);
		}

		return $result;
	}

	private function _call_driver(&$uids, $mbox, $spam)
	{
		$driver = $this->home.'/drivers/'. rcube::get_instance()->config->get('markasjunk2_learning_driver', 'cmd_learn') .'.php';
		$class = 'markasjunk2_' . rcube::get_instance()->config->get('markasjunk2_learning_driver', 'cmd_learn');

		if (!is_readable($driver)) {
			rcube::raise_error(array(
				'code' => 600,
				'type' => 'php',
				'file' => __FILE__,
				'line' => __LINE__,
				'message' => "MarkasJunk2 plugin: Unable to open driver file $driver"
				), true, false);
		}

		include_once $driver;

		if (!class_exists($class, false) || !method_exists($class, 'spam') || !method_exists($class, 'ham')) {
			rcube::raise_error(array(
				'code' => 600,
				'type' => 'php',
				'file' => __FILE__,
				'line' => __LINE__,
				'message' => "MarkasJunk2 plugin: Broken driver: $driver"
				), true, false);
		}

		// call the relevant function from the driver
		$object = new $class;
		if ($spam)
			$object->spam($uids, $mbox);
		else
			$object->ham($uids, $mbox);

		return $object->is_error ? false : true;
	}

	private function _messageset_to_uids($messageset, $multi_folder)
	{
		$a_uids = array();

		foreach ($messageset as $mbox => $uids) {
			foreach ($uids as $uid) {
				$a_uids[] = $multi_folder ? $uid . '-' . $mbox : $uid;
			}
		}

		return $a_uids;
	}
}

?>