/* Mark as Junk 2 plugin script */

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

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.markasjunk2', '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), true);
}

function rcmail_markasnotjunk2(prop)
{
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.markasnotjunk2', '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), true);
}

rcmail.rcmail_markasjunk2_move = function(mbox, uid) {
	var prev_uid = rcmail.env.uid;
	if (rcmail.message_list && rcmail.message_list.selection.length <= 1) {
		rcmail.env.uid = uid;
        this.message_list.remove_row(uid, false);
	}

	rcmail.move_messages(mbox);

	rcmail.env.uid = prev_uid;
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

rcmail.add_onload('rcmail_markasjunk2_init()');
rcmail.addEventListener('listupdate', function(props) { rcmail_markasjunk2_update(); } );
