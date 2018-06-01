<?php

/**
 * MarkAsJunk2
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 * or to move messages in the Junk folder to the inbox - moving only the
 * attachment if it is a Spamassassin spam report email
 *
 * @author Philip Weir
 * Based on the Markasjunk plugin by Thomas Bruederli
 *
 * Copyright (C) 2009-2018 Philip Weir
 *
 * This program is a Roundcube (https://roundcube.net) plugin.
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
 * along with Roundcube. If not, see https://www.gnu.org/licenses/.
 */
class markasjunk2 extends rcube_plugin
{
    public $task = 'mail';
    private $spam_mbox = null;
    private $ham_mbox = null;
    private $spam_flag = 'JUNK';
    private $ham_flag = 'NOTJUNK';
    private $toolbar = true;
    private $rcube;

    public function init()
    {
        $this->register_action('plugin.markasjunk2.junk', array($this, 'mark_message'));
        $this->register_action('plugin.markasjunk2.not_junk', array($this, 'mark_message'));

        $this->rcube = rcube::get_instance();
        $this->load_config();
        $this->_load_host_config();

        // Host exceptions
        $hosts = $this->rcube->config->get('markasjunk2_allowed_hosts');
        if (!empty($hosts) && !in_array($_SESSION['storage_host'], (array) $hosts)) {
            return;
        }

        $this->ham_mbox = $this->rcube->config->get('markasjunk2_ham_mbox', 'INBOX');
        $this->spam_mbox = $this->rcube->config->get('markasjunk2_spam_mbox', $this->rcube->config->get('junk_mbox', null));
        $this->toolbar = $this->_set_toolbar_display($this->rcube->config->get('markasjunk2_toolbar', -1), $this->rcube->action);

        // register the ham/spam flags with the core
        $this->add_hook('storage_init', array($this, 'set_flags'));

        // integration with Swipe plugin
        $this->add_hook('swipe_actions_list', array($this, 'swipe_action'));

        if ($this->rcube->action == '' || $this->rcube->action == 'show') {
            $this->include_script('markasjunk2.js');
            $this->add_texts('localization', true);
            $this->include_stylesheet($this->local_skin_path() . '/markasjunk2.css');

            if ($this->toolbar) {
                // add the buttons to the main toolbar
                $this->add_button(array('command' => 'plugin.markasjunk2.junk', 'type' => 'link', 'class' => 'button buttonPas markasjunk2 disabled', 'classact' => 'button markasjunk2', 'classsel' => 'button markasjunk2 pressed', 'title' => 'markasjunk2.buttonjunk', 'innerclass' => 'inner', 'label' => 'junk'), 'toolbar');
                $this->add_button(array('command' => 'plugin.markasjunk2.not_junk', 'type' => 'link', 'class' => 'button buttonPas markasnotjunk2 disabled', 'classact' => 'button markasnotjunk2', 'classsel' => 'button markasnotjunk2 pressed', 'title' => 'markasjunk2.buttonnotjunk', 'innerclass' => 'inner', 'label' => 'markasjunk2.notjunk'), 'toolbar');
            }
            else {
                // add the buttons to the mark message menu
                $this->add_button(array('command' => 'plugin.markasjunk2.junk', 'type' => 'link-menuitem', 'label' => 'markasjunk2.asjunk', 'id' => 'markasjunk2', 'class' => 'icon markasjunk2', 'classact' => 'icon markasjunk2 active', 'innerclass' => 'icon markasjunk2'), 'markmenu');
                $this->add_button(array('command' => 'plugin.markasjunk2.not_junk', 'type' => 'link-menuitem', 'label' => 'markasjunk2.asnotjunk', 'id' => 'markasnotjunk2', 'class' => 'icon markasnotjunk2', 'classact' => 'icon markasnotjunk2 active', 'innerclass' => 'icon markasnotjunk2'), 'markmenu');
            }

            // add markasjunk2 folder settings to the env for JS
            $this->rcube->output->set_env('markasjunk2_ham_mailbox', $this->ham_mbox);
            $this->rcube->output->set_env('markasjunk2_spam_mailbox', $this->spam_mbox);

            $this->rcube->output->set_env('markasjunk2_move_spam', $this->rcube->config->get('markasjunk2_move_spam', false));
            $this->rcube->output->set_env('markasjunk2_move_ham', $this->rcube->config->get('markasjunk2_move_ham', false));
            $this->rcube->output->set_env('markasjunk2_permanently_remove', $this->rcube->config->get('markasjunk2_permanently_remove', false));

            // check for init method from driver
            $this->_call_driver('init');
        }
    }

    public function mark_message()
    {
        $this->add_texts('localization');

        $is_spam = $this->rcube->action == 'plugin.markasjunk2.junk' ? true : false;
        $messageset = rcmail::get_uids(null, null, $multifolder, rcube_utils::INPUT_POST);
        $mbox_name = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $dest_mbox = $is_spam ? $this->spam_mbox : $this->ham_mbox;
        $result = $is_spam ? $this->_spam($messageset, $dest_mbox) : $this->_ham($messageset, $dest_mbox);

        if ($result) {
            if ($dest_mbox && ($mbox_name !== $dest_mbox || $multifolder)) {
                $this->rcube->output->command('rcmail_markasjunk2_move', $dest_mbox, $this->_messageset_to_uids($messageset, $multifolder));
            }
            else {
                $this->rcube->output->command('command', 'list', $mbox_name);
            }

            $this->rcube->output->command('display_message', $is_spam ? $this->gettext('reportedasjunk') : $this->gettext('reportedasnotjunk'), 'confirmation');
        }

        $this->rcube->output->send();
    }

    public function set_flags($p)
    {
        $flags = array(
            $this->spam_flag => $this->rcube->config->get('markasjunk2_spam_flag'),
            $this->ham_flag => $this->rcube->config->get('markasjunk2_ham_flag')
        );

        $p['message_flags'] = array_merge((array) $p['message_flags'], $flags);

        return $p;
    }

    public function swipe_action($p)
    {
        if ($this->spam_mbox && $p['source'] == 'messagelist' && $p['axis'] == 'horizontal') {
            $p['actions']['markasjunk2'] = 'markasjunk2.markasjunk';

            return $p;
        }
    }

    private function _set_toolbar_display($display, $action)
    {
        $ret = true;

        // backwards compatibility for old config options (removed in 1.10)
        if ($display < 0) {
            $mb = $this->rcube->config->get('markasjunk2_mb_toolbar', true);
            $cp = $this->rcube->config->get('markasjunk2_cp_toolbar', true);

            if ($mb && $cp) {
                $display = 1;
            }
            elseif ($mb && !$cp) {
                $display = 2;
            }
            elseif (!$mb && $cp) {
                $display = 3;
            }
            else {
                $display = 0;
            }
        }

        switch ($display) {
            case 0: // always show in mark message menu
                $ret = false;
                break;
            case 1: // always show on toolbar
                $ret = true;
                break;
            case 2: // show in toolbar on mailbox screen, show in mark message menu message on screen
                $ret = ($action != 'show');
                break;
            case 3: // show in mark message menu on mailbox screen, show in toolbar message on screen
                $ret = ($action == 'show');
                break;
        }

        return $ret;
    }

    private function _spam(&$messageset, $dest_mbox = null)
    {
        $storage = $this->rcube->get_storage();
        $result = true;

        foreach ($messageset as $source_mbox => &$uids) {
            $storage->set_folder($source_mbox);

            if ($this->rcube->config->get('markasjunk2_learning_driver', false)) {
                $result = $this->_call_driver('spam', $uids, $source_mbox, $dest_mbox);

                // abort function of the driver says so
                if (!$result) {
                    break;
                }
            }

            if ($this->rcube->config->get('markasjunk2_read_spam', false)) {
                $storage->set_flag($uids, 'SEEN', $source_mbox);
            }

            if ($this->rcube->config->get('markasjunk2_spam_flag', false)) {
                $storage->set_flag($uids, $this->spam_flag, $source_mbox);
            }

            if ($this->rcube->config->get('markasjunk2_ham_flag', false)) {
                $storage->unset_flag($uids, $this->ham_flag, $source_mbox);
            }
        }

        return $result;
    }

    private function _ham(&$messageset, $dest_mbox = null)
    {
        $storage = $this->rcube->get_storage();
        $result = true;

        foreach ($messageset as $source_mbox => &$uids) {
            $storage->set_folder($source_mbox);

            if ($this->rcube->config->get('markasjunk2_learning_driver', false)) {
                $result = $this->_call_driver('ham', $uids, $source_mbox, $dest_mbox);

                // abort function of the driver says so
                if (!$result) {
                    break;
                }
            }

            if ($this->rcube->config->get('markasjunk2_unread_ham', false)) {
                $storage->unset_flag($uids, 'SEEN', $source_mbox);
            }

            if ($this->rcube->config->get('markasjunk2_spam_flag', false)) {
                $storage->unset_flag($uids, $this->spam_flag, $source_mbox);
            }

            if ($this->rcube->config->get('markasjunk2_ham_flag', false)) {
                $storage->set_flag($uids, $this->ham_flag, $source_mbox);
            }
        }

        return $result;
    }

    private function _call_driver($action, &$uids = null, $source_mbox = null, $dest_mbox = null)
    {
        $driver = $this->home . '/drivers/' . $this->rcube->config->get('markasjunk2_learning_driver', 'cmd_learn') . '.php';
        $class = 'markasjunk2_' . $this->rcube->config->get('markasjunk2_learning_driver', 'cmd_learn');

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
        $object = new $class();
        if ($action == 'spam') {
            $object->spam($uids, $source_mbox, $dest_mbox);
        }
        elseif ($action == 'ham') {
            $object->ham($uids, $source_mbox, $dest_mbox);
        }
        elseif ($action == 'init' && method_exists($object, 'init')) { // method_exists check here for backwards compatibility, init method added 20161127
            $object->init();
        }

        return $object->is_error ? false : true;
    }

    private function _messageset_to_uids($messageset, $multifolder)
    {
        $a_uids = array();

        foreach ($messageset as $mbox => $uids) {
            foreach ($uids as $uid) {
                $a_uids[] = $multifolder ? $uid . '-' . $mbox : $uid;
            }
        }

        return $a_uids;
    }

    private function _load_host_config()
    {
        $configs = $this->rcube->config->get('markasjunk2_host_config');
        if (empty($configs) || !array_key_exists($_SESSION['storage_host'], (array) $configs)) {
            return;
        }

        $file = $configs[$_SESSION['storage_host']];
        $this->load_config($file);
    }
}
