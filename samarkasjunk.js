/* Spamassassin Mark-as-Junk plugin script */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		// register command (directly enable in message view mode)
		rcmail.register_command('plugin.samarkasjunk', rcmail_samarkasjunk, rcmail.env.uid);
		rcmail.register_command('plugin.samarkasnotjunk', rcmail_samarkasnotjunk, rcmail.env.uid);

		if (rcmail.message_list && rcmail.env.junk_mailbox) {
			rcmail.message_list.addEventListener('select', function(list){
				rcmail.enable_command('plugin.samarkasjunk', list.get_selection().length > 0);
				rcmail.enable_command('plugin.samarkasnotjunk', list.get_selection().length > 0);
			});
		}
	})
}

function rcmail_samarkasjunk(prop)
{
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.samarkasjunk', '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), true);
}

function rcmail_samarkasnotjunk(prop)
{
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

	rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.samarkasnotjunk', '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox), true);
}

rcmail.rcmail_samarkasjunk_move = function(mbox, uid) {
	var prev_uid = rcmail.env.uid;
	if (rcmail.message_list && rcmail.message_list.selection.length <= 1) {
		rcmail.env.uid = uid;
        this.message_list.remove_row(uid, false);
	}

	rcmail.move_messages(mbox);

	rcmail.env.uid = prev_uid;
}


function rcmail_samarkasjunk_init()
{
	if (window.rcm_contextmenu_register_command) {
		rcm_contextmenu_register_command('samarkasjunk', 'rcmail_samarkasjunk', '&nbsp;&nbsp;' + rcmail.gettext('samarkasjunk.markasjunk'), 'reply', null, true);
		rcm_contextmenu_register_command('samarkasnotjunk', 'rcmail_samarkasnotjunk', '&nbsp;&nbsp;' + rcmail.gettext('samarkasjunk.markasnotjunk'), 'reply', null, true);

		if (rcmail.env.junk_mailbox && rcmail.env.mailbox == rcmail.env.junk_mailbox) {
			$('#rcmContextMenu li.samarkasjunk').hide();
			$('#rcmContextMenu li.samarkasnotjunk').show();
		}
		else {
			$('#rcmContextMenu li.samarkasjunk').show();
			$('#rcmContextMenu li.samarkasnotjunk').hide();
		}

		$('#rcmContextMenu li.unflagged').removeClass('separator_below');
		$('#rcmContextMenu li.reply').addClass('separator_above');
	}
}

function rcmail_samarkasjunk_update()
{
	if (rcmail.env.junk_mailbox && rcmail.env.mailbox != rcmail.env.junk_mailbox) {
		$('#rcmContextMenu li.samarkasjunk').show();
		$('#rcmContextMenu li.samarkasnotjunk').hide();
		$('ul.toolbarmenu li a.samarkasjunk').show();
		$('ul.toolbarmenu li a.samarkasnotjunk').hide();
	}
	else {
		$('#rcmContextMenu li.samarkasjunk').hide();
		$('#rcmContextMenu li.samarkasnotjunk').show();
		$('ul.toolbarmenu li a.samarkasjunk').hide();
		$('ul.toolbarmenu li a.samarkasnotjunk').show();
	}
}

rcmail.add_onload('rcmail_samarkasjunk_init()');
rcmail.addEventListener('listupdate', function(props) { rcmail_samarkasjunk_update(); } );
