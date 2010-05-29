/**
 * MarkAsJunk2 plugin script
 */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		// register command (directly enable in message view mode)
		rcmail.register_command('plugin.markasjunk2', rcmail_markasjunk2, rcmail.env.uid);
		rcmail.register_command('plugin.markasnotjunk2', rcmail_markasnotjunk2, rcmail.env.uid);

		if (rcmail.message_list && rcmail.env.junk_mailbox) {
			rcmail.message_list.addEventListener('select', function(list){
				rcmail.enable_command('plugin.markasjunk2', list.get_selection().length > 0);
				rcmail.enable_command('plugin.markasnotjunk2', list.get_selection().length > 0);
			});
		}
	})
}

function rcmail_markasjunk2(prop)
{
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	if (!prop)
		prop = 'markasjunk2';

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
			for (var i in selection)
				rcmail.message_list.select_childs(selection[i]);
		}
	}

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.' + prop, '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), true);

	if (prev_sel) {
		rcmail.message_list.clear_selection();

		for (var i in prev_sel)
			rcmail.message_list.select_row(prev_sel[i], CONTROL_KEY);
	}
}

function rcmail_markasnotjunk2(prop)
{
	rcmail_markasjunk2('markasnotjunk2');
}

rcmail.rcmail_markasjunk2_move = function(mbox, uid) {
	var prev_uid = rcmail.env.uid;
	var prev_sel = null;
	var a_uids = uid.split(",");

	if (rcmail.message_list && a_uids.length == 1 && !rcmail.message_list.in_selection(a_uids[0])) {
		rcmail.env.uid = a_uids[0];
		rcmail.message_list.remove_row(rcmail.env.uid, false);
	}
	else if (rcmail.message_list && !rcmail.message_list.in_selection(a_uids[0])) {
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

		for (var i in prev_sel)
			rcmail.message_list.select_row(prev_sel[i], CONTROL_KEY);
	}
}

function rcmail_markasjunk2_init()
{
	if (window.rcm_contextmenu_register_command) {
		rcm_contextmenu_register_command('markasjunk2', 'rcmail_markasjunk2', '&nbsp;&nbsp;' + rcmail.gettext('markasjunk2.markasjunk'), 'reply', null, true);
		rcm_contextmenu_register_command('markasnotjunk2', 'rcmail_markasnotjunk2', '&nbsp;&nbsp;' + rcmail.gettext('markasjunk2.markasnotjunk'), 'reply', null, true);

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

function rcmail_markasjunk2_update()
{
	if (rcmail.env.junk_mailbox && rcmail.env.mailbox != rcmail.env.junk_mailbox) {
		$('#rcmContextMenu li.markasjunk2').show();
		$('#rcmContextMenu li.markasnotjunk2').hide();
		$('#' + rcmail.buttons['plugin.markasjunk2'][0].id).show();
		$('#' + rcmail.buttons['plugin.markasnotjunk2'][0].id).hide();
	}
	else {
		$('#rcmContextMenu li.markasjunk2').hide();
		$('#rcmContextMenu li.markasnotjunk2').show();
		$('#' + rcmail.buttons['plugin.markasjunk2'][0].id).hide();
		$('#' + rcmail.buttons['plugin.markasnotjunk2'][0].id).show();
	}
}

function rcmail_markasjunk2_status(command){
	switch (command) {
		case 'beforedelete':
			if (!rcmail.env.flag_for_deletion && rcmail.env.trash_mailbox &&
				rcmail.env.mailbox != rcmail.env.trash_mailbox &&
				(rcmail.message_list && !rcmail.message_list.shiftkey))
				rcmail.enable_command('plugin.markasjunk2', 'plugin.markasnotjunk2', false);

			break;
		case 'beforemove':
		case 'beforemoveto':
			rcmail.enable_command('plugin.markasjunk2', 'plugin.markasnotjunk2', false);
			break;
		case 'aftermove':
		case 'aftermoveto':
			if (rcmail.env.action == 'show')
				rcmail.enable_command('plugin.markasjunk2', 'plugin.markasnotjunk2', true);

			break;
		case 'afterpurge':
		case 'afterexpunge':
			 if (!rcmail.env.messagecount && rcmail.task == 'mail')
			 	rcmail.enable_command('plugin.markasjunk2', 'plugin.markasnotjunk2', false);

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