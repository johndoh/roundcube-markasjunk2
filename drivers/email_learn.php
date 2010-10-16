<?php

/**
 * Email learn driver
 * @version 1.0
 * @author Philip Weir
 */
include('mimeDecode.php');

function learn_spam($uids)
{
	do_emaillearn($uids, true);
}

function learn_ham($uids)
{
	do_emaillearn($uids, false);
}

function do_emaillearn($uids, $spam)
{
	$rcmail = rcmail::get_instance();
	$identity_arr = $rcmail->user->get_identity();
	$from = $identity_arr['email'];

	if ($spam)
		$mailto = $rcmail->config->get('markasjunk2_email_spam');
	else
		$mailto = $rcmail->config->get('markasjunk2_email_ham');

	$mailto = str_replace('%u', $_SESSION['username'], $mailto);
	$mailto = str_replace('%l', $rcmail->user->get_username('local'), $mailto);
	$mailto = str_replace('%d', $rcmail->user->get_username('domain'), $mailto);
	$mailto = str_replace('%i', $from, $mailto);

	if (!$mailto)
		return;

	$message_charset = $rcmail->output->get_charset();
	// chose transfer encoding
	$charset_7bit = array('ASCII', 'ISO-2022-JP', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-15');
	$transfer_encoding = in_array(strtoupper($message_charset), $charset_7bit) ? '7bit' : '8bit';

	$temp_dir = realpath($rcmail->config->get('temp_dir'));

	$subject = $rcmail->config->get('markasjunk2_email_subject');
	$subject = str_replace('%u', $_SESSION['username'], $subject);
	$subject = str_replace('%t', ($spam) ? 'spam' : 'ham', $subject);
	$subject = str_replace('%l', $rcmail->user->get_username('local'), $subject);
	$subject = str_replace('%d', $rcmail->user->get_username('domain'), $subject);

	// compose headers array
	$headers = array();
	$headers['Date'] = date('r');
	$headers['From'] = format_email_recipient($identity_arr['email'], $identity_arr['name']);
	$headers['To'] = $mailto;
	$headers['Subject'] = $subject;

	foreach (explode(",", $uids) as $uid) {
		$MESSAGE = new rcube_message($uid);
		$MAIL_MIME = new Mail_mime($rcmail->config->header_delimiter());

		if ($rcmail->config->get('markasjunk2_email_attach', false)) {
			$tmpPath = tempnam($temp_dir, 'rcmMarkASJunk2');

			// send mail as attachment
			$MAIL_MIME->setTXTBody(($spam ? 'Spam' : 'Ham'). ' report from ' . $rcmail->config->get('product_name'), false, true);

			$raw_message = $rcmail->imap->get_raw_body($uid);
			$subject = $MESSAGE->get_header('subject');

			if(isset($subject) && $subject !="")
				$disp_name = $subject . ".eml";
			else
				$disp_name = "message_rfc822.eml";

			if(file_put_contents($tmpPath, $raw_message)){
				$MAIL_MIME->addAttachment($tmpPath, "message/rfc822", $disp_name, true,
					$transfer_encoding, 'attachment', $message_charset, '', '',
					$rcmail->config->get('mime_param_folding') ? 'quoted-printable' : NULL,
					$rcmail->config->get('mime_param_folding') == 2 ? 'quoted-printable' : NULL
				);
			}

			// encoding settings for mail composing
			$MAIL_MIME->setParam('text_encoding', $transfer_encoding);
			$MAIL_MIME->setParam('html_encoding', 'quoted-printable');
			$MAIL_MIME->setParam('head_encoding', 'quoted-printable');
			$MAIL_MIME->setParam('head_charset', $message_charset);
			$MAIL_MIME->setParam('html_charset', $message_charset);
			$MAIL_MIME->setParam('text_charset', $message_charset);

			// pass headers to message object
			$MAIL_MIME->headers($headers);
		}
		else {
			$params['include_bodies'] = true;
			$params['decode_bodies'] = true;
			$params['decode_headers'] = true;
			$params['input'] = $rcmail->imap->get_raw_body($uid);

			$MIME_DECODE = Mail_mimeDecode::decode($params);

			$headers['Resent-From'] = $headers['From'];
			$headers['Resent-Date'] = $headers['Date'];
			$headers['Date'] = $MESSAGE->headers->date;
			$headers['From'] = $MESSAGE->headers->from;
			$headers['Subject'] = $MESSAGE->headers->subject;

			$MAIL_MIME->headers($headers);

			markasjunk2_email_learn_build_parts($MAIL_MIME, $MIME_DECODE);
		}

		rcmail_deliver_message($MAIL_MIME, $from, $mailto, $smtp_error, $body_file);

		// clean up
		if (file_exists($tmpPath))
			unlink($tmpPath);

		if ($rcmail->config->get('markasjunk2_debug')) {
			if ($spam)
				write_log('markasjunk2', $uid . ' SPAM ' . $mailto . ' (' . $subject . ')');
			else
				write_log('markasjunk2', $uid . ' HAM ' . $mailto . ' (' . $subject . ')');

			if ($smtp_error['vars'])
				write_log('markasjunk2', $smtp_error['vars']);
		}
	}
}

function markasjunk2_email_learn_build_parts(&$MAIL_MIME, $MIME_DECODE)
{
	foreach ($MIME_DECODE->parts as $part) {
		if ($part->ctype_primary == 'multipart') {
			markasjunk2_email_learn_build_parts($MAIL_MIME, $part);
		}
		elseif ($part->ctype_primary == 'text' && $part->ctype_secondary == 'html') {
			$MAIL_MIME->setHTMLBody($part->body);
		}
		elseif ($part->ctype_primary == 'text' && $part->ctype_secondary == 'plain') {
			$MAIL_MIME->setTXTBody($part->body);
		}
		elseif (!empty($part->headers['content-id'])) {
			// covert CID to Mail_MIME format
			$part->headers['content-id'] = str_replace('<', '', $part->headers['content-id']);
			$part->headers['content-id'] = str_replace('>', '', $part->headers['content-id']);

			if (empty($part->ctype_parameters['name']))
				$part->ctype_parameters['name'] = $part->headers['content-id'];

			$message_body = $MAIL_MIME->getHTMLBody();
			$dispurl = 'cid:' . $part->headers['content-id'];
			$message_body = str_replace($dispurl, $part->ctype_parameters['name'], $message_body);
			$MAIL_MIME->setHTMLBody($message_body);

			$MAIL_MIME->addHTMLImage($part->body,
				$part->ctype_primary .'/'. $part->ctype_secondary,
				$part->ctype_parameters['name'],
				false,
				$part->headers['content-id']
			);
		}
		else {
			$MAIL_MIME->addAttachment($part->body,
				$part->ctype_primary .'/'. $part->ctype_secondary,
				$part->ctype_parameters['name'],
				false,
				$part->headers['content-transfer-encoding'],
				$part->disposition
			);
		}
	}
}

?>