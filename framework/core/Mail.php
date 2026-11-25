<?php

/**
 * Mail.php is the file that sends emails from the framework
 * @copyright 2019
 * @author Dory A.Azar
 * @version 1.0
 */

/**
 * The Mail class handles all emails sent from the framework
 * @author Dory A.Azar
 * @version 1.0
 */

namespace Caligrafy;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use \Exception as Exception;


class Mail extends \stdClass {

    /** @var bool whether the last sendMail() of this request was held back by MAIL_RATE_PER_HOUR (2.30.0) */
    private static $limited = false;

    private $mailHost;
    private $mailPort;
    private $mailUsername;
    private $mailPassword;
    private $mailer;

    public function __construct()
    {
        
        // the settings (.env); a command, a job or a test that did not load them gets an unconfigured mailer, not a crash
        $this->mailHost = defined('MAIL_HOST') ? MAIL_HOST : (string) getenv('MAIL_HOST');
        $this->mailPort = defined('MAIL_PORT') ? MAIL_PORT : (string) getenv('MAIL_PORT');
        $this->mailUsername = defined('MAIL_USERNAME') ? MAIL_USERNAME : (string) getenv('MAIL_USERNAME');
        $this->mailPassword = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : (string) getenv('MAIL_PASSWORD');
        
        $this->mailer = new PHPMailer();
        if (!$this->mailer) {
            throw new Exception(Errors::get('4000'), 4000);  
            exit;
        }
        
        //Server settings
		$this->mailer->isSMTP();
		$this->mailer->Mailer = "smtp";
        // .env values are strings: 'false' must turn the debug output off
        $this->mailer->SMTPDebug = defined('APP_DEBUG') && filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN)? 1 : 0;
        // the SMTP log goes to the server log, never into the page or the JSON answer of an API call
        $this->mailer->Debugoutput = 'error_log';
		$this->mailer->SMTPAuth = true;
		$this->mailer->Port = $this->mailPort;   
		if ($this->mailPort == 465) {
			$this->mailer->SMTPSecure = 'ssl';
		} else {
			$this->mailer->SMTPSecure = 'tls';
		}
        
        $this->mailer->Host = $this->mailHost;
        $this->mailer->Username = $this->mailUsername;
        $this->mailer->Password = $this->mailPassword;
      
                    
        return $this;
    }
    
    
    /**
     * @param array $headers extra headers, name => value (Notify: List-Unsubscribe, List-Unsubscribe-Post)
     */
    public function sendMail($recipients, $emailContent, $from, array $headers = array())
    {
        self::$limited = false;
        if (empty($recipients) || empty($emailContent) || empty($from)) {
            //throw new Exception(Errors::get('4001'), 4001);
			return false;
			exit;
        }
        // the app's hourly allowance (MAIL_RATE_PER_HOUR): over it, nothing is sent, the log says so, false comes back
        $count = is_array($recipients['email'] ?? null) ? max(1, count($recipients['email'])) : 1;
        if (!self::allow($count)) {
            self::$limited = true;
            error_log('Caligrafy mail: MAIL_RATE_PER_HOUR (' . self::rate() . ') reached this hour; not sent: ' . ($emailContent['subject'] ?? ''));
            return false;
        }
        
		$this->recipientEmail = $recipients['email']?? '';
		$this->recipientName = $recipients['name']?? '';
		$this->subject = $emailContent['subject']?? '';
		$this->body = $emailContent['body']?? '';
		$this->fromEmail = $from['email']?? '';
		$this->fromName = $from['name']?? '';
		
		$this->mailer->IsHTML(true);
		if (is_array($this->recipientEmail)) {
			foreach($this->recipientEmail as $recipientEmail) {
				$this->mailer->AddAddress($recipientEmail);
			}
		} else {
			$this->mailer->AddAddress($this->recipientEmail, $this->recipientName);
		}
		$this->mailer->SetFrom($this->fromEmail, $this->fromName);
		//$this->mailer->AddReplyTo("replytoemail", "replyto name");
		//$this->mailer->AddCC("ccemail", "ccname");
		$this->mailer->Subject = $this->subject;
		foreach ($headers as $name => $value) {
			$this->mailer->addCustomHeader($name, $value);
		}
		$content = $this->body;
		
		$this->mailer->MsgHTML($content);
		if(!$this->mailer->Send()) {
			//throw new Exception(Errors::get('4001'), 4001);
			return false;
		} 

        return true;
       
    }

    /**
     * The emails the app may send in an hour (2.30.0): MAIL_RATE_PER_HOUR, null when it is empty or 0 (no limit). A
     * shared mail account (Caligrafy Cloud gives one to every app without its own MAIL_HOST) sets it, so that one app's
     * loop or a bot at a sign-up form cannot stop email for all of them
     */
    public static function rate()
    {
        $rate = (int) trim((string) getenv('MAIL_RATE_PER_HOUR'));
        return $rate > 0 ? $rate : null;
    }

    /**
     * The emails sent this clock hour, as the limit counts them (0 without a limit or a cache to count in)
     */
    public static function sentThisHour()
    {
        try {
            return Cache::store() === 'off' ? 0 : (int) Cache::get(self::rateKey(), 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Whether the last sendMail() of this request was held back by the hourly limit
     */
    public static function limited()
    {
        return self::$limited;
    }

    /**
     * Counts $count emails against the hour's allowance: false (and nothing counted) when they would go over it. The
     * count lives in the cache (shared by the web server, the queue and the scheduler, CACHE_STORE); with the cache off
     * there is nowhere to count, and nothing is held back
     */
    private static function allow($count)
    {
        $rate = self::rate();
        if ($rate === null) return true;
        try {
            if (Cache::store() === 'off') return true;
            $sent = (int) Cache::get(self::rateKey(), 0);
            if ($sent + $count > $rate) return false;
            Cache::put(self::rateKey(), $sent + $count, 3660);
        } catch (\Throwable $e) {
            error_log('Caligrafy mail: the hourly count could not be kept: ' . $e->getMessage());
        }
        return true;
    }

    private static function rateKey()
    {
        return 'caligrafy-mail-rate:' . gmdate('YmdH');
    }
}
