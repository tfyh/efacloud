<?php

/**
 * A utility class to wrap the system mail function and store sent mails. Originally build for
 * distribution lists
 */
class Tfyh_mail_handler
{

    /**
     * reference constant for user to many mails
     */
    public static $TYPE_USER = 0;

    /**
     * reference constant for system to many mails
     */
    public static $TYPE_SYSTEM = 1;

    /**
     * reference constant for user to user mails
     */
    public static $TYPE_INDIVIDUAL = 2;

    /**
     * The String that separates the leading html body from a trailing plain text part. Providung a
     * mail in both html and plain text improves its spam rating.
     */
    public $plain_separator = "|>>-plain->>|";

    /**
     * path to store mails sent for user to many mails
     */
    private $userMailPath;

    /**
     * path to store mails sent for system to many mails
     */
    private $systemMailPath;

    /**
     * path to store mails sent for user to user mails
     */
    private $individualMailPath;

    /**
     * mail address for system generated mails, including plain name, e.g.
     * 'No-reply<noreply@domain.com>'
     */
    public $system_mail_sender;

    /**
     * mail address for copy recipient of workflow generated mails
     */
    public $mail_schriftwart;

    /**
     * mail address for getting information from users
     */
    public $mail_webmaster;

    /**
     * mail address for system generated mails on behalf of users
     */
    public $mail_mailer;

    /**
     * Acronym to prefix the subject line, e.g. "[YApp]"
     */
    public $mail_subject_acronym;

    /**
     * mail signature for system generated mails ("Yours sincerely A. Bee")
     */
    public $mail_subscript;

    /**
     * mail footer for system generated mails ("see www.abc.org")
     */
    public $mail_footer;

    /**
     * mail return address for system generated mails
     */
    public $system_mail_address;

    /**
     * public Constructor, reads all users.
     *
     * @param array $cfg
     *            the mailer configuration. Must contain "system_mail_sender", "mail_schriftwart",
     *            "mail_webmaster", "mail_subscript", "mail_footer";
     */
    public function __construct (array $cfg)
    {
        $this->userMailPath = "../mails/user/";
        $this->systemMailPath = "../mails/system/";
        $this->individualMailPath = "../mails/individual/";
        $this->system_mail_sender = $cfg["system_mail_sender"];
        $elements = explode("<", $this->system_mail_sender, 2);
        $this->system_mail_address = substr($elements[1], 0, strlen($elements[1]) - 1);
        $this->mail_schriftwart = $cfg["mail_schriftwart"];
        $this->mail_webmaster = $cfg["mail_webmaster"];
        $this->mail_mailer = $cfg["mail_mailer"];
        $this->mail_subject_acronym = $cfg["mail_subject_acronym"];
        $this->mail_subscript = $cfg["mail_subscript"];
        $this->mail_footer = $cfg["mail_footer"];
    }

    /**
     * encode a mail header line to quoted printable. Will check for real names in address fields
     * "From:", "Reply-To:", "Cc:", "Bcc:" and encode them. It will always trim the fields and add
     * the "\r\n" sequence for appropriate Header encoding according to RFC.
     *
     * @param string $mhLine
     *            line to encode. Note that it must begin with the respective keyword in a case
     *            sensitive manner.
     * @return string encoded line
     */
    private static function mhLineEncode ($mhLine)
    {
        $mhLine = trim($mhLine);
        if (strlen($mhLine) == 0) {
            return "";
        }
        if ((strpos($mhLine, '<') !== false) && ((strpos($mhLine, "To:") == 0) ||
                 (strpos($mhLine, "From:") == 0) || (strpos($mhLine, "Reply-To:") == 0) ||
                 (strpos($mhLine, "Cc:") == 0) || (strpos($mhLine, "Bcc:") == 0))) {
            $mheparts = explode("<", $mhLine, 2);
            $mhepparts = explode(":", $mheparts[0], 2);
            if (strpos($mhepparts[1], '[') !== false) {
                // special support for trailing codes like "John Doe [yahoo-net]" or similar
                $mheppparts = explode("[", $mhepparts[1], 2);
                $mhlNew = trim($mhepparts[0]) . ": \"=?UTF-8?Q?" . str_replace(" ", "_", 
                        quoted_printable_encode(trim($mheppparts[0]))) . "?= [" . trim(
                        $mheppparts[1]) . "\" <" . trim($mheparts[1]) . "\r\n";
            } else {
                $mhlNew = trim($mhepparts[0]) . ": =?UTF-8?Q?" .
                         str_replace(" ", "_", quoted_printable_encode(trim($mhepparts[1]))) . "?= <" .
                         trim($mheparts[1]) . "\r\n";
            }
        } else {
            $mhlNew = trim($mhLine) . "\r\n";
        }
        return $mhlNew;
    }

    /**
     * path to store and retrieve mails
     *
     * @param int $type
     *            type of mail
     * @return string path to store and retrieve mails
     */
    private function get_mPath (int $type)
    {
        return ($type == self::$TYPE_USER) ? $this->userMailPath : (($type == self::$TYPE_INDIVIDUAL) ? $this->individualMailPath : $this->systemMailPath);
    }

    /**
     * Create a plain text alternative by replacing relevant tags and removing the rest
     *
     * @param String $html_text            
     */
    public function create_plain_text_alternative (String $html_text)
    {
        // "\r\n" = End of line type (RFC)
        $replacer = ["<b>" => "*","</b>" => "*","<br>" => "\r\n",
                "<hr>" => "\r\n----------------\r\n","<p>" => "\r\n\r\n",
                "<h1>" => "\r\n\r\n\r\n\r\n","<h2>" => "\r\n\r\n\r\n","<h3>" => "\r\n\r\n\r\n",
                "<h4>" => "\r\n\r\n","<h5>" => "\r\n\r\n","<h6>" => "\r\n\r\n"
        ];
        $plain_text = $html_text;
        foreach ($replacer as $search => $replace)
            $plain_text = str_replace($search, $replace, $plain_text);
        $plain_text_split = explode("<", $plain_text);
        $plain_text = "";
        foreach ($plain_text_split as $plain_text_part)
            if (strpos($plain_text_part, ">") !== false)
                $plain_text .= substr($plain_text_part, strpos($plain_text_part, ">"));
            else
                $plain_text .= $plain_text_part;
        return $plain_text;
    }

    /**
     * Store a mail. For meaning of fields see send_mail.
     *
     * @param int $type
     *            set to $TYPE_USER for user mails, else system mails will be returned
     * @param string $mailfrom
     *            header field "From:", must be a single line and not contain commas. E.g. "Me
     *            <me@tfyh.org>"
     * @param string $mailto
     *            header field "To:", must be a single line separating all addresses by commas. E.g.
     *            "Me <me@tfyh.org>, You <you@tfyh.org>, them@tfyh.org"
     * @param string $subject
     *            subject text
     * @param string $body
     *            body text
     */
    public function store_mail ($type, $mailfrom, $mailto, $subject, $body)
    {
        // identify path to store mail to
        $mPath = $this->get_mPath($type);
        $mIndex = intval(file_get_contents($mPath . "index.txt"));
        $mIndex ++;
        $mailToStore = strval(time()) . ";" . base64_encode($mailfrom) . ";" . base64_encode(
                $mailto) . ";" . base64_encode($subject) . ";" . base64_encode($body);
        file_put_contents($mPath . strval($mIndex) . ".txt", $mailToStore);
        file_put_contents($mPath . "index.txt", $mIndex);
    }

    /**
     * return a html formatted mail, without the enclosing html tags.
     *
     * @param int $type
     *            set to $TYPE_USER for user mails, else system mails will be returned
     * @param int $mIndex
     *            mail index for mail to be returned.
     * @return string
     */
    public function get_mail_HTML ($type, $mIndex)
    {
        // identify path to store mail to
        $mPath = $this->get_mPath($type);
        // get encoded mail
        $mailStored = file_get_contents($mPath . strval($mIndex) . ".txt");
        if (is_null($mailStored) || (strlen($mailStored) < 2)) {
            return "";
        }
        // decode
        $elements = explode(";", $mailStored, 5);
        $timeSent = trim($elements[0]);
        $timeStamp = date("Y-m-d H:i:s", $timeSent);
        $mailfrom = htmlspecialchars(base64_decode(trim($elements[1])));
        // Empfänger entfernt aus Datenschutzgründen
        // $mailto = htmlspecialchars ( base64_decode ( trim ( $elements [2] ) ) );
        $subject = htmlspecialchars(base64_decode(trim($elements[3])));
        $body = base64_decode(trim($elements[4]));
        // The mail body may contain already html tags. Must then be removed.
        $body = str_replace("<html>", "", $body);
        $body = str_replace("</html>", "", $body);
        $htmlMail = "<p><b>Gesendet:</b> " . $timeStamp . "</p><p><b>Von:</b> " . $mailfrom .
                 "</p><p><p><b>Betreff:</b> " . $subject . "</p>" . $body . "\n";
        // Empfänger entfernt:'<b>An:</b> " . $mailto . "</p>' aus Datenschutzgründen
        return $htmlMail;
    }

    /**
     * return the index of the last mail send for the respective queue.
     *
     * @param int $type
     *            set to $TYPE_USER for user mails, else system mails will be returned
     * @return int $mIndex mail index for last mail in the queue.
     */
    public function get_last_index ($type)
    {
        // identify path to store mail to
        $mPath = $this->get_mPath($type);
        $mIndex = intval(file_get_contents($mPath . "index.txt"));
        return $mIndex;
    }

    /**
     * Send a mail. Convenience method to wrap the php native mailing method. Will apply proper
     * header and subject encoding as quoted printables and shorten the subject to the maximum
     * allowance of 75 characters.
     *
     * @param string $mailfrom
     *            header field "From:", must be a single line and not contain commas. May contain a
     *            real name. E.g. "Me <me@tfyh.org>"
     * @param string $mailreplyto
     *            header field "Reply-To:", must be a single line, not contain commas neither a real
     *            name. E.g. "You <you@tfyh.org". Set "" to have no specific reply-to path.
     * @param string $mailto
     *            header field "To:", must be a single line separating all addresses by commas. May
     *            contain a real names. E.g. "Me <me@tfyh.org>, You <you@tfyh.org>, them@tfyh.org"
     * @param string $mailcc
     *            header field "Cc:", must be a single line separating all addresses by commas. May
     *            contain a real names. E.g. "Nobody <nobody@tfyh.org>". Set to "" to have no Cc
     *            recipients.
     * @param string $mailbcc
     *            header field "Bcc:", must be a single line separating all addresses by commas. May
     *            contain a real names. E.g. "Nobody <nobody@tfyh.org>". Set to "" to have no Bcc
     *            recipients.
     * @param string $subject
     *            subject text
     * @param string $body
     *            body text
     * @param string $attachment1_location
     *            file path to an optional first attachement, field is optional, default = "" for no
     *            attachment
     * @param string $attachment2_location
     *            file path to an optional second attachement, field is optional, default = "" for
     *            no attachment
     * @return true if mail sent, false if failed.
     */
    public function send_mail ($mailfrom, $mailreplyto, $mailto, $mailcc, $mailbcc, $subject, $body, 
            $attachment1_location = "", $attachment2_location = "")
    {
        // Mail header encoding.
        // =====================
        // To: can have names, but the very first of them must be placed into the "$mailto" field
        // of the send function call rather than into the mail headers.
        $i = 0;
        $mailheaders_encoded = "";
        if (strlen($mailto) > 0) {
            $mhelements = explode(",", $mailto);
            foreach ($mhelements as $mhelement) {
                if ($i == 0) {
                    $mailto_encoded = Tfyh_mail_handler::mhLineEncode("To:" . $mhelement);
                    $mailto_encoded_parts = explode(":", $mailto_encoded);
                    $mailto_encoded = $mailto_encoded_parts[1];
                    // To: is stripped here and added by send method.
                } else {
                    $mailheaders_encoded .= Tfyh_mail_handler::mhLineEncode("To:" . $mhelement);
                }
                $i ++;
            }
        }
        // From may contain a real name, but is a single address
        $mailheaders_encoded .= Tfyh_mail_handler::mhLineEncode("From:" . $mailfrom);
        // Reply-To shall only be a real mail address, no name to be given
        if (strlen($mailreplyto) > 0) {
            $mailheaders_encoded .= Tfyh_mail_handler::mhLineEncode("Reply-To:" . $mailreplyto);
        }
        // To; Cc:, Bcc: Can have names
        if (strlen($mailcc) > 0) {
            $mhelements = explode(",", $mailcc);
            foreach ($mhelements as $mhelement) {
                $mailheaders_encoded .= Tfyh_mail_handler::mhLineEncode("Cc:" . $mhelement);
            }
        }
        if (strlen($mailbcc) > 0) {
            $mhelements = explode(",", $mailbcc);
            foreach ($mhelements as $mhelement) {
                $mailheaders_encoded .= Tfyh_mail_handler::mhLineEncode("Bcc:" . $mhelement);
            }
        }
        
        // Mail subject encoding
        // =====================
        $subject = trim($subject);
        if (strpos($subject, ']') !== false) {
            // special support for preceding codes like "[yahoo-net] John Doe's alive" or similar
            $subjparts = explode("]", $subject, 2);
            $qpSubject = trim($subjparts[0]) . "] =?UTF-8?Q?" .
                     str_replace(" ", "_", quoted_printable_encode(trim($subjparts[1])));
        } else {
            // normal subject lines.
            $qpSubject = "=?UTF-8?Q?" . quoted_printable_encode($subject);
        }
        // limit length to 78 characters, encoding characters are not counted
        if (strlen($qpSubject) > 84) {
            $qpSubject = substr($qpSubject, 0, 81) . "...";
        }
        // add encoding trailer.
        $eol = "\r\n"; // End of line type (RFC)
        $qpSubject = $qpSubject . "?=" . $eol;
        
        // find or create plain text
        if (strpos($body, $this->plain_separator) !== false) {
            $plain = explode($this->plain_separator, $body)[1];
            $body = explode($this->plain_separator, $body)[0];
        } else {
            $plain = $this->create_plain_text_alternative($body);
        }
        
        // a random hash will be necessary to send mixed content
        $separator = "=_Part_" . md5(time());
        $mailheaders_encoded .= "MIME-Version: 1.0" . $eol;
        $mailheaders_encoded .= "Content-Type: multipart/mixed;" . $eol;
        $mailheaders_encoded .= "    boundary=\"" . $separator . "\"" . $eol . $eol;
        
        // plain before html
        $body_mixed = "--" . $separator . $eol;
        $body_mixed .= "Content-Type: multipart/alternative;" . $eol;
        $separator_alternative = "=_Alt_" . md5(time() + 1234567);
        $body_mixed .= "    boundary=\"" . $separator_alternative . "\"" . $eol . $eol;
        $body_mixed .= "--" . $separator_alternative . $eol;
        $body_mixed .= "Content-Type: text/plain; charset=\"utf-8\"" . $eol;
        $body_mixed .= "Content-Transfer-Encoding: quoted-printable" . $eol . $eol;
        $body_mixed .= quoted_printable_encode($plain) . $eol;
        
        // html message
        $body_mixed .= "--" . $separator_alternative . $eol;
        $body_mixed .= "Content-Type: text/html; charset=\"UTF-8\"" . $eol;
        $body_mixed .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body_mixed .= $body . $eol;
        $body_mixed .= "--" . $separator_alternative . "--" . $eol . $eol;
        
        // attachment 1
        if (strlen($attachment1_location) > 0) {
            $body_mixed .= "--" . $separator . $eol;
            $content = file_get_contents($attachment1_location);
            $content = chunk_split(base64_encode($content));
            $attachment1_filename = (strrpos($attachment1_location, "/") == false) ? $attachment1_location : substr($attachment1_location, 
                    strrpos($attachment1_location, "/") + 1);
            $body_mixed .= "Content-Type: application/octet-stream; name=\"" . $attachment1_filename .
                     "\"" . $eol;
            $body_mixed .= "Content-Transfer-Encoding: base64" . $eol;
            $body_mixed .= "Content-Disposition: attachment;   filename=\"" . $attachment1_filename .
                     "\"" . $eol . $eol;
            $body_mixed .= $content . $eol;
        }
        // attachment 2
        if (strlen($attachment2_location) > 0) {
            $body_mixed .= "--" . $separator . $eol;
            $content = file_get_contents($attachment2_location);
            $content = chunk_split(base64_encode($content));
            $attachment2_filename = (strrpos($attachment2_location, "/") == false) ? $attachment2_location : substr($attachment2_location, 
                    strrpos($attachment2_location, "/") + 1);
            $body_mixed .= "Content-Type: application/octet-stream; name=\"" . $attachment2_filename .
                     "\"" . $eol;
            $body_mixed .= "Content-Transfer-Encoding: base64" . $eol;
            $body_mixed .= "Content-Disposition: attachment;   filename=\"" . $attachment2_filename .
                     "\"" . $eol . $eol;
            $body_mixed .= $content . $eol;
        }
        $body_mixed .= "--" . $separator . "--" . $eol;
        
        // Do not send mails, when running on "localhost":
        if (strpos(strtolower($_SERVER["SERVER_NAME"]), "localhost") !== false) {
            $fname = date("Ymd_His") . "mail.txt";
            $mail_text = $mailto_encoded . "\n\n" . $mailheaders_encoded . "\n\n" . $qpSubject .
                     "\n\n" . $body_mixed . "\n\n";
            file_put_contents("../all_mails_localhost/" . $fname, $mail_text) !== false;
            $mailSent = false;
        } else {
            // Send action
            $mailSent = @mail($mailto_encoded, $qpSubject, $body_mixed, $mailheaders_encoded);
        }
        return $mailSent;
    }
}
