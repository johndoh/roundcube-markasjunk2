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

	function init()
	{
		$this->register_action('plugin.samarkasjunk', array($this, 'mark_junk'));
		$this->register_action('plugin.samarkasnotjunk', array($this, 'mark_notjunk'));

		$rcmail = rcmail::get_instance();
		if (($rcmail->action == '' || $rcmail->action == 'show') && !empty($rcmail->user->ID)) {
			$this->include_script('samarkasjunk.js');
			$this->add_texts('localization', true);

			if ($rcmail->action == 'show' && ($rcmail->config->get('junk_mbox') && $_SESSION['mbox'] != $rcmail->config->get('junk_mbox'))) {
				$this->add_button(array('command' => 'plugin.samarkasjunk', 'imagepas' => 'skins/'. $this->api->output->config['skin'] .'/junk_pas.png', 'imageact' => 'skins/'. $this->api->output->config['skin'] .'/junk_act.png', 'alt' => 'samarkasjunk.buttonjunk', 'title' => 'samarkasjunk.buttonjunk'), 'toolbar');
			}
			elseif ($rcmail->action == 'show' && ($rcmail->config->get('junk_mbox') && $_SESSION['mbox'] == $rcmail->config->get('junk_mbox'))) {
				$this->add_button(array('command' => 'plugin.samarkasnotjunk', 'imagepas' => 'skins/'. $this->api->output->config['skin'] .'/notjunk_pas.png', 'imageact' => 'skins/'. $this->api->output->config['skin'] .'/notjunk_act.png', 'alt' => 'samarkasjunk.buttonnotjunk', 'title' => 'samarkasjunk.buttonnotjunk'), 'toolbar');
			}
			elseif ($rcmail->config->get('junk_mbox')) {
				$skin_path = 'skins/'. $this->api->output->config['skin'] .'/samarkasjunk.css';
				$skin_path = is_file($this->home .'/'. $skin_path) ? $skin_path : 'skins/default/samarkasjunk.css';
				$this->include_stylesheet($skin_path);

				if ($_SESSION['mbox'] == $rcmail->config->get('junk_mbox')) {
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

		$uids = get_input_value('_uid', RCUBE_INPUT_POST);
		$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);

		if (($junk_mbox = rcmail::get_instance()->config->get('junk_mbox')) && $mbox != $junk_mbox) {
			rcmail::get_instance()->imap->set_flag($uids, 'SEEN');
			$this->api->output->command('rcmail_samarkasjunk_move', $junk_mbox, $uids);

			$this->api->output->command('display_message', $this->gettext('reportedasjunk'), 'confirmation');
			$this->api->output->send();
		}
	}

	function mark_notjunk()
	{
		$this->add_texts('localization');

		$uids = get_input_value('_uid', RCUBE_INPUT_POST);
		$mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
		$move = TRUE;

		if (strpos($uids, ',') === false) {
			$message = new rcube_message($uids);

			if (sizeof($message->attachments)) {
				if ($message->attachments[0]->ctype_primary == 'message' && $message->attachments[0]->ctype_parameters['x-spam-type'] == 'original') {
					$part = $message->mime_parts[$message->attachments[0]->mime_id];
					$move = FALSE;

					$imap = rcmail::get_instance()->imap;
					$saved = $imap->save_message('INBOX', $imap->get_message_part($message->uid, $part->mime_id, $part));

					if ($saved) {
						$imap->delete_message($uids, $mbox);
						$this->api->output->command('message_list.remove_row', $uids, false);
					}
				}
			}
		}

		if (($clean_mbox = 'INBOX') && $mbox != $clean_mbox && $move)
			$this->api->output->command('rcmail_samarkasjunk_move', $clean_mbox, $uids);

		$this->api->output->command('display_message', $this->gettext('reportedasnotjunk'), 'confirmation');
		$this->api->output->send();
	}
}
