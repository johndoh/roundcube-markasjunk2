/**
 * MarkAsJunk2 plugin script
 */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		// register command (directly enable in message view mode)
		rcmail.register_command('plugin.markasjunk2.junk', rcmail_markasjunk2, rcmail.env.uid);
		rcmail.register_command('plugin.markasjunk2.not_junk', rcmail_markasjunk2_notjunk, rcmail.env.uid);

		if (rcmail.message_list && rcmail.env.junk_mailbox) {
			rcmail.message_list.addEventListener('select', function(list) {
				rcmail.enable_command('plugin.markasjunk2.junk', list.get_selection().length > 0);
				rcmail.enable_command('plugin.markasjunk2.not_junk', list.get_selection().length > 0);
			});
		}
	})
}

function rcmail_markasjunk2(prop) {
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	if (!prop || prop == 'markasjunk2')
		prop = 'junk';

	var prev_sel = null;

	// also select childs of (collapsed) threads
	if (rcmail.message_list) {
		if (rcmail.env.uid) {
			if (rcmail.message_list.rows[rcmail.env.uid].has_children && !rcmail.message_list.rows[rcmail.env.uid].expanded) {
				if (!rcmail.message_list.in_selection(rcmail.env.uid)) {
					prev_sel = rcmail.message_list.get_selection();
					rcmail.message_list.select_row(rcmail.env.uid);
				}

				rcmail.message_list.select_childs(rcmail.env.uid);
				rcmail.env.uid = null;
			}
			else if (rcmail.message_list.get_single_selection() == rcmail.env.uid) {
				rcmail.env.uid = null;
			}
		}
		else {
			selection = rcmail.message_list.get_selection();
			for (var i in selection) {
				if (rcmail.message_list.rows[selection[i]].has_children && !rcmail.message_list.rows[selection[i]].expanded)
					rcmail.message_list.select_childs(selection[i]);
			}
		}
	}

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	var lock = rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.markasjunk2.' + prop, '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), lock);

	if (prev_sel) {
		rcmail.message_list.clear_selection();

		for (var i in prev_sel)
			rcmail.message_list.select_row(prev_sel[i], CONTROL_KEY);
	}
}

function rcmail_markasjunk2_notjunk(prop) {
	rcmail_markasjunk2('not_junk');
}

rcmail.rcmail_markasjunk2_move = function(mbox, uid) {
	var prev_uid = rcmail.env.uid;
	var prev_sel = null;
	var a_uids = uid.split(",");

	if (rcmail.message_list && a_uids.length == 1 && !rcmail.message_list.in_selection(a_uids[0]) && !rcmail.env.threading) {
		rcmail.env.uid = a_uids[0];
		rcmail.message_list.remove_row(rcmail.env.uid, false);
	}
	else if (rcmail.message_list && (!rcmail.message_list.in_selection(a_uids[0]) || a_uids.length != rcmail.message_list.selection.length)) {
		prev_sel = rcmail.message_list.get_selection();
		rcmail.message_list.clear_selection();

		for (var i in a_uids)
			rcmail.message_list.select_row(a_uids[i], CONTROL_KEY);
	}

	if (mbox)
		rcmail.move_messages(mbox);
	else
		rcmail.delete_messages();

	rcmail.env.uid = prev_uid;

	if (prev_sel) {
		rcmail.message_list.clear_selection();

		for (var i in prev_sel) {
			if (prev_sel[i] != uid)
				rcmail.message_list.select_row(prev_sel[i], CONTROL_KEY);
		}
	}
}

function rcmail_markasjunk2_init() {
	if (window.rcm_contextmenu_register_command) {
		rcm_contextmenu_register_command('markasjunk2', 'rcmail_markasjunk2', '&nbsp;&nbsp;' + rcmail.gettext('markasjunk2.markasjunk'), 'reply', null, true);
		rcm_contextmenu_register_command('markasnotjunk2', 'rcmail_markasjunk2_notjunk', '&nbsp;&nbsp;' + rcmail.gettext('markasjunk2.markasnotjunk'), 'reply', null, true);

		if (rcmail.env.junk_mailbox && rcmail.env.mailbox == rcmail.env.junk_mailbox) {
			$('#rcmContextMenu li.markasjunk2').hide();
			$('#rcmContextMenu li.markasnotjunk2').show();
		}
		else {
			$('#rcmContextMenu li.markasjunk2').show();
			$('#rcmContextMenu li.markasnotjunk2').hide();
		}

		$('#rcmContextMenu li.unflagged').removeClass('separator_below');
		$('#rcmContextMenu li.reply').addClass('separator_above');
	}
}

function rcmail_markasjunk2_update() {
	var spamobj = $('#' + rcmail.buttons['plugin.markasjunk2.junk'][0].id);
	var hamobj = $('#' + rcmail.buttons['plugin.markasjunk2.not_junk'][0].id);

	if (spamobj.parent('li').length > 0) {
		spamobj = spamobj.parent();
		hamobj = hamobj.parent();
	}

	if (rcmail.env.junk_mailbox && rcmail.env.mailbox != rcmail.env.junk_mailbox) {
		$('#rcmContextMenu li.markasjunk2').show();
		$('#rcmContextMenu li.markasnotjunk2').hide();
		spamobj.show();
		hamobj.hide();
	}
	else {
		$('#rcmContextMenu li.markasjunk2').hide();
		$('#rcmContextMenu li.markasnotjunk2').show();
		spamobj.hide();
		hamobj.show();
	}
}

function rcmail_markasjunk2_status(command) {
	switch (command) {
		case 'beforedelete':
			if (!rcmail.env.flag_for_deletion && rcmail.env.trash_mailbox &&
				rcmail.env.mailbox != rcmail.env.trash_mailbox &&
				(rcmail.message_list && !rcmail.message_list.shiftkey))
				rcmail.enable_command('plugin.markasjunk2.junk', 'plugin.markasjunk2.not_junk', false);

			break;
		case 'beforemove':
		case 'beforemoveto':
			rcmail.enable_command('plugin.markasjunk2.junk', 'plugin.markasjunk2.not_junk', false);
			break;
		case 'aftermove':
		case 'aftermoveto':
			if (rcmail.env.action == 'show')
				rcmail.enable_command('plugin.markasjunk2.junk', 'plugin.markasjunk2.not_junk', true);

			break;
		case 'afterpurge':
		case 'afterexpunge':
			if (!rcmail.env.messagecount && rcmail.task == 'mail')
				rcmail.enable_command('plugin.markasjunk2.junk', 'plugin.markasjunk2.not_junk', false);

			break;
	}
}

rcmail.add_onload('rcmail_markasjunk2_init()');
rcmail.addEventListener('listupdate', function(props) { rcmail_markasjunk2_update(); } );

// update button activation after external events
rcmail.addEventListener('beforedelete', function(props) { rcmail_markasjunk2_status('beforedelete'); } );
rcmail.addEventListener('beforemove', function(props) { rcmail_markasjunk2_status('beforemove'); } );
rcmail.addEventListener('beforemoveto', function(props) { rcmail_markasjunk2_status('beforemoveto'); } );
rcmail.addEventListener('aftermove', function(props) { rcmail_markasjunk2_status('aftermove'); } );
rcmail.addEventListener('aftermoveto', function(props) { rcmail_markasjunk2_status('aftermoveto'); } );
rcmail.addEventListener('afterpurge', function(props) { rcmail_markasjunk2_status('afterpurge'); } );
rcmail.addEventListener('afterexpunge', function(props) { rcmail_markasjunk2_status('afterexpunge'); } );