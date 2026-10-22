<?php
// Raw-socket Gmail SMTP sender (no external libraries required)

if (!function_exists('cpmmis_smtp_read')) {
    function cpmmis_smtp_read($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    }
}

if (!function_exists('cpmmis_send_email')) {
    function cpmmis_send_email($to, $subject, $html_body, &$error = null) {
        include(__DIR__ . '/mail_config.php');

        if (!$smtp_username || !$smtp_app_password || strpos($smtp_username, 'yourgmail') !== false) {
            $error = 'SMTP is not configured. Edit backend/mail_config.php with your Gmail App Password.';
            return false;
        }

        $secure = ($smtp_port == 465) ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]
        ]);
        $fp = @stream_socket_client("$secure$smtp_host:$smtp_port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            $error = "SMTP connection failed.";
            return false;
        }
        stream_set_timeout($fp, 12);

        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = "SMTP greeting failed: " . trim($resp); fclose($fp); return false; }

        fwrite($fp, "EHLO cpmmis.local\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = "EHLO rejected: " . trim($resp); fclose($fp); return false; }

        if ($smtp_port != 465) {
            fwrite($fp, "STARTTLS\r\n");
            $resp = cpmmis_smtp_read($fp);
            if ($resp[0] !== '2') { $error = "STARTTLS rejected: " . trim($resp); fclose($fp); return false; }
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                $error = 'TLS negotiation failed.';
                fclose($fp); return false;
            }
            fwrite($fp, "EHLO cpmmis.local\r\n");
            cpmmis_smtp_read($fp);
        }

        fwrite($fp, "AUTH LOGIN\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '3') { $error = 'Server did not accept AUTH LOGIN.'; fclose($fp); return false; }

        fwrite($fp, base64_encode($smtp_username) . "\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '3') { $error = 'SMTP username rejected.'; fclose($fp); return false; }

        fwrite($fp, base64_encode($smtp_app_password) . "\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = 'Gmail authentication failed. Check your email and App Password in mail_config.php.'; fclose($fp); return false; }

        fwrite($fp, "MAIL FROM:<{$smtp_username}>\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = 'MAIL FROM rejected: ' . trim($resp); fclose($fp); return false; }

        fwrite($fp, "RCPT TO:<{$to}>\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = 'Recipient rejected: ' . trim($resp); fclose($fp); return false; }

        fwrite($fp, "DATA\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '3') { $error = 'DATA command rejected.'; fclose($fp); return false; }

        $headers = "From: {$from_name} <{$smtp_username}>\r\n"
                 . "To: <{$to}>\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                 . "Date: " . date('r') . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n";

        $body = preg_replace('/^\./m', '..', $html_body);

        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $resp = cpmmis_smtp_read($fp);
        if ($resp[0] !== '2') { $error = 'Message rejected by server: ' . trim($resp); fclose($fp); return false; }

        fwrite($fp, "QUIT\r\n");
        cpmmis_smtp_read($fp);
        fclose($fp);

        return true;
    }
}
