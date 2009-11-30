<?php

/**
 * Email learn driver
 * @version 0.1
 * @author Philip Weir
 */

function learn_spam($uids) {
	do_emaillearn($uids, true);
}

function learn_ham($uids) {
	do_emaillearn($uids, false);
}

function do_emaillearn($uids, $spam) {
	$rcmail = rcmail::get_instance();

    if ($spam)
   		$mailto = $rcmail->config->get('markasjunk2_email_spam');
    else
    	$mailto = $rcmail->config->get('markasjunk2_email_ham');

    if (!$mailto)
    	return;

    $message_charset = $rcmail->output->get_charset();
	// chose transfer encoding
	$charset_7bit = array('ASCII', 'ISO-2022-JP', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-15');
	$transfer_encoding = in_array(strtoupper($message_charset), $charset_7bit) ? '7bit' : '8bit';

	$temp_dir = realpath($rcmail->config->get('temp_dir'));

    $identity_arr = $rcmail->user->get_identity();
	$from = $identity_arr['email'];

    $subject = $rcmail->config->get('markasjunk2_email_subject');
    $subject = str_replace('%u', $_SESSION['username'], $subject);
    $subject = str_replace('%t', ($spam) ? 'spam' : 'ham', $subject);

    if (strpos($_SESSION['username'], '@') !== false) {
        $parts = explode("@", $_SESSION['username'], 2);

        $subject = str_replace(array('%l', '%d'),
						array($parts[0], $parts[1]),
						$subject);
    }

    foreach (explode(",", $uids) as $uid) {
	    $MESSAGE = new rcube_message($uid);
		$tmpPath = tempnam($temp_dir, 'rcmMarkASJunk2');

		// compose headers array
		$headers = array();
		$headers['Date'] = date('r');
		$headers['From'] = rcube_charset_convert($identity_arr['string'], RCMAIL_CHARSET, $message_charset);
		$headers['To'] = $mailto;
		$headers['Subject'] = $subject;

		$MAIL_MIME = new rcube_mail_mime($rcmail->config->header_delimiter());
		if ($rcmail->config->get('markasjunk2_email_attach', false)) {
			// send mail as attachment
			$MAIL_MIME->setTXTBody(($spam ? 'Spam' : 'Ham'). ' report from RoundCube Webmail', false, true);

 			$message = $rcmail->imap->get_raw_body($uid);
			$subject = $MESSAGE->get_header('subject');

			if(isset($subject) && $subject !="")
				$disp_name = $subject . ".eml";
			else
				$disp_name = "message_rfc822.eml";

			if(file_put_contents($tmpPath, $message)){
				$MAIL_MIME->addAttachment($tmpPath, "message/rfc822", $disp_name, true,
					$ctype == 'message/rfc822' ? $transfer_encoding : 'base64',
					'attachment', $message_charset, '', '',
					$rcmail->config->get('mime_param_folding') ? 'quoted-printable' : NULL,
					$rcmail->config->get('mime_param_folding') == 2 ? 'quoted-printable' : NULL
				);
			}
		}
		else {
			if ($MESSAGE->has_html_part()) {
				$body = $MESSAGE->first_html_part();
				$MAIL_MIME->setHTMLBody($body);

				// add a plain text version of the e-mail as an alternative part.
				$h2t = new html2text($body, false, true, 0);
				$MAIL_MIME->setTXTBody($h2t->get_text());
			}
			else {
				$body = $MESSAGE->first_text_part();
				$MAIL_MIME->setTXTBody($body, false, true);
			}
		}

		// encoding settings for mail composing
		$MAIL_MIME->setParam(array(
			'text_encoding' => $transfer_encoding,
			'html_encoding' => 'quoted-printable',
			'head_encoding' => 'quoted-printable',
			'head_charset'  => $message_charset,
			'html_charset'  => $message_charset,
			'text_charset'  => $message_charset,
		));

		// pass headers to message object
		$MAIL_MIME->headers($headers);

		rcmail_deliver_message($MAIL_MIME, $from, $mailto, $smtp_error);

		if ($rcmail->config->get('markasjunk2_debug')) {
			if ($spam)
				write_log('markasjunk2', $uid . ' SPAM ' . $email_to . ' (' . $subject . ')');
			else
				write_log('markasjunk2', $uid . ' HAM ' . $email_to . ' (' . $subject . ')');

			if ($smtp_error['vars'])
				write_log('markasjunk2', $smtp_error['vars']);
		}
    }
}

?>