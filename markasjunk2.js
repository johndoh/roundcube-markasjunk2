/**
 * MarkAsJunk2 plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

function rcmail_markasjunk2(prop) {
    if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection(false).length))
        return;

    if (!prop || prop == 'markasjunk2')
        prop = 'junk';

    var prev_sel = null, i;

    // also select children of (collapsed) threads
    if (rcmail.message_list) {
        if (rcmail.env.uid) {
            if (rcmail.message_list.rows[rcmail.env.uid].has_children && !rcmail.message_list.rows[rcmail.env.uid].expanded) {
                if (!rcmail.message_list.in_selection(rcmail.env.uid)) {
                    prev_sel = rcmail.message_list.get_selection(false);
                    rcmail.message_list.select_row(rcmail.env.uid);
                }

                rcmail.message_list.select_children(rcmail.env.uid);
                rcmail.env.uid = null;
            }
            else if (rcmail.message_list.get_single_selection() == rcmail.env.uid) {
                rcmail.env.uid = null;
            }
        }
        else {
            var selection = rcmail.message_list.get_selection(false);
            for (i in selection) {
                if (rcmail.message_list.rows[selection[i]].has_children && !rcmail.message_list.rows[selection[i]].expanded)
                    rcmail.message_list.select_children(selection[i]);
            }
        }
    }

    var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection();

    var lock = rcmail.set_busy(true, 'loading');
    rcmail.http_post('plugin.markasjunk2.' + prop, rcmail.selection_post_data({_uid: uids, _multifolder: rcmail.is_multifolder_listing()}), lock);

    if (prev_sel) {
        rcmail.message_list.clear_selection();

        for (i in prev_sel)
            rcmail.message_list.select_row(prev_sel[i], CONTROL_KEY);
    }
}

function rcmail_markasjunk2_notjunk() {
    rcmail_markasjunk2('not_junk');
}

rcube_webmail.prototype.rcmail_markasjunk2_move = function(mbox, uids) {
    var prev_uid = this.env.uid,
      prev_sel = null,
      a_uids = $.isArray(uids) ? uids : uids.split(","),
      i;

    if (this.message_list) {
        if (a_uids.length == 1 && !this.message_list.rows[a_uids[0]]) {
            this.env.uid = a_uids[0];
        }
        else if (!this.message_list.in_selection(a_uids[0]) || a_uids.length != this.message_list.get_selection(false).length) {
            prev_sel = this.message_list.get_selection(false);
            this.message_list.clear_selection();

            for (i in a_uids)
                this.message_list.highlight_row(a_uids[i], true);
        }
    }

    if (mbox)
        this.move_messages(mbox);
    else
        this.delete_messages();

    this.env.uid = prev_uid;

    if (prev_sel) {
        this.message_list.clear_selection();

        for (i in prev_sel)
            this.message_list.highlight_row(prev_sel[i], true);
    }
}

function rcmail_markasjunk2_update() {
    var spamobj = $('#' + rcmail.buttons['plugin.markasjunk2.junk'][0].id);
    var hamobj = $('#' + rcmail.buttons['plugin.markasjunk2.not_junk'][0].id);

    if (spamobj.parent('li').length > 0) {
        spamobj = spamobj.parent();
        hamobj = hamobj.parent();
    }

    var disp = {'spam': true, 'ham': true};
    if (!rcmail.is_multifolder_listing() && rcmail.env.markasjunk2_spam_mailbox) {
        if (rcmail.env.mailbox != rcmail.env.markasjunk2_spam_mailbox) {
            disp.ham = false;
        }
        else {
            disp.spam = false;
        }
    }

    var evt_rtn = rcmail.triggerEvent('markasjunk2-update', {'objs': {'spamobj': spamobj, 'hamobj': hamobj}, 'disp': disp});
    if (evt_rtn && evt_rtn.abort)
        return;
    disp = evt_rtn ? evt_rtn.disp : disp;

    disp.spam ? spamobj.show() : spamobj.hide();
    disp.ham ? hamobj.show() : hamobj.hide();

    // if only 1 button is visible make sure its the last one (for styling)
    var cur_index = spamobj.index();
    if (disp.spam && !disp.ham) {
        if (cur_index < hamobj.index())
            spamobj.insertAfter(hamobj);
    }
    else if (cur_index > hamobj.index()) {
        hamobj.insertAfter(spamobj);
    }

    // contextmenu integration
    // if the contextmenu exists, remove it to force an update of the buttons
    if (cur_index != spamobj.index() && $('div.contextmenu.rcm-submenu').has('a.markasjunk2').length > 0) {
        var menu_name = $('div.contextmenu.submenu').has('a.markasjunk2').attr('id').replace(/^rcm_/, '');
        rcmail.env.contextmenus['messagelist'].submenus[menu_name].hide();
        rcmail.env.contextmenus['messagelist'].submenus[menu_name].destroy();
        rcmail.env.contextmenus['messagelist'].submenus[menu_name] = null;
    }
}

$(document).ready(function() {
    if (window.rcmail) {
        rcmail.addEventListener('init', function() {
            // register command (directly enable in message view mode)
            rcmail.register_command('plugin.markasjunk2.junk', rcmail_markasjunk2, rcmail.env.uid);
            rcmail.register_command('plugin.markasjunk2.not_junk', rcmail_markasjunk2_notjunk, rcmail.env.uid);

            if (rcmail.message_list) {
                rcmail.message_list.addEventListener('select', function(list) {
                    rcmail.enable_command('plugin.markasjunk2.junk', list.get_selection(false).length > 0);
                    rcmail.enable_command('plugin.markasjunk2.not_junk', list.get_selection(false).length > 0);
                });
            }

            // make sure the correct icon is displayed even when there is no listupdate event
            rcmail_markasjunk2_update();
        });

        rcmail.addEventListener('listupdate', function() { rcmail_markasjunk2_update(); } );

        rcmail.addEventListener('beforemoveto', function(mbox) {
            if (mbox && typeof mbox === 'object')
                mbox = mbox.id;

            // check if destination mbox equals junk box (and we're not already in the junk box)
            if (rcmail.env.markasjunk2_move_spam && mbox && mbox == rcmail.env.markasjunk2_spam_mailbox && mbox != rcmail.env.mailbox) {
                rcmail_markasjunk2();
                return false;

            }
            // or if destination mbox equals ham box and we are in the junk box
            else if (rcmail.env.markasjunk2_move_ham && mbox && mbox == rcmail.env.markasjunk2_ham_mailbox && rcmail.env.mailbox == rcmail.env.markasjunk2_spam_mailbox) {
                rcmail_markasjunk2_notjunk();
                return false;
            }

            return;
        } );
    }
});