<?php
/**
 * Error mailer
 * @author Vince Tikász 4image#dev|WSE#dev
 */

/**
 * Requirements
 */
require_once dirname(__FILE__).'/abstract.php';

/**
 * Error mailer
 * @author Vince Tikász 4image#dev|WSE#dev
 */

class ErrorHandler_Handler_Mail
	extends ErrorHandler_Handler_Abstract {

	/**
	 * Path to message stack directory
	 * @var String
	 */
	protected $msgStack;

	/**
	 * Path to lastMailSent file
	 * @var String
	 */
	protected $lastMailFile;
	
	/**
	 * Init handler
	 * @return type 
	 */
	protected function init() {
		if (
			(bool)$this->usedProfile->mail
			&& is_array($this->usedProfile->mail)
		) {
			$this->msgStack = dirname(dirname(__FILE__)).'/logs/_mail';
			$this->lastMailFile = $this->msgStack.'/lastmailsent';

			foreach( $this->usedProfile->mail as $mailSetting => $mailValue ) {
				$this->usedProfile->mail[$mailSetting] = $mailValue;
			}

			if ( is_readable( $this->lastMailFile ) ) {
				$this->usedProfile->mail['lastMailSent'] = 
					intval(file_get_contents($this->lastMailFile));
			}
			else {
				file_put_contents($this->lastMailFile, 0);
				$this->usedProfile->mail['lastMailSent'] = 0;
			}

			$this->usedProfile->mail['sendIntervalSeconds'] =
				intval($this->usedProfile->mail['period']) * 60;

			
			$this->usedProfile->mail['sendOnDestruct'] = 
				!$this->usedProfile->mail['lastMailSent']
				|| 0 >= $this->usedProfile->mail['sendIntervalSeconds']
				|| ( time() >= $this->usedProfile->mail['lastMailSent'] 
						+ $this->usedProfile->mail['sendIntervalSeconds'] );

			return true;
		}
		$this->usedProfile->mail = false;
		return false;
	}

	/**
	 * Error handler
	 * @param Exception $e 
	 */
	protected function onError(Exception $e) {
		$this->onException($e);
	}

	/**
	 * Exception handler
	 * @param Exception $e 
	 */
	protected function onException(Exception $e) {
		$errorHash = md5( $e->getMessage().$e->getFile().$e->getLine() );
		$hashFile = $this->msgStack.'/'.$errorHash;
		$msg = array();
		if ( !is_file( $hashFile ) ) {
			$msg[] = $e->error_type_name.' ';
			$msg[] = $e->getMessage()." in\n";
			$msg[] = $e->getFile().' on line [';
			$msg[] = $e->getLine().']';
			$msg[] = "\nTrace:\n------\n";
			$msg[] = $e->getTraceAsString();
			$msg[] = "\n==============================\n";
			$msg[] = "This error triggered on\n- - - - - - - - - - - - - - -\n";
		}
		$_SERVERPORT = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
		$msg = array_merge( $msg, array(
			date('c'),' @ ',
			$_SERVER['SERVER_PROTOCOL'],' ',$_SERVER['REQUEST_METHOD'],"\n",
			$_SERVER['HTTP_HOST'],':',$_SERVERPORT,$_SERVER['REQUEST_URI'], "\n",
			$_SERVER['REMOTE_ADDR'],' => ',$_SERVER['HTTP_USER_AGENT'],"\n---\n"
		));
		file_put_contents($hashFile, join('',$msg), FILE_APPEND | LOCK_EX);
	}

	/**
	 * Destruction handler
	 * @return void
	 */
	protected function onDestruct() {
		if (
			$this->usedProfile->mail
			&& $this->usedProfile->mail['sendOnDestruct']
		){
			$this->sendMail();
		}
	}

	/**
	 * Do mail sending
	 * @return void
	 */
	private function sendMail() {
		$messages = glob( $this->msgStack.'/*' );
		$msg = array();
		foreach( $messages as $file ) {
			$fileName = basename($file);
			if (
				'.' == substr($fileName, 0, 1)
				|| 'lastmailsent' == $fileName
			) {
				continue;
			}
			$msg[] = file_get_contents($file);
			$msg[] = "\n====================================\n";
		}
		if ( !empty( $msg ) ) {
			$sendSuccess = mail(
				$this->usedProfile->mail['to'],
				'=?UTF-8?B?'.base64_encode($this->usedProfile->mail['subject'].date('Y-m-d H:i:s')).'?=',
				"Theese errors triggered:\n\n".join('',$msg),
				join("\r\n",array(
					// additional headers
					'MIME-Version: 1.0',
					'Content-type: text/plain; charset=UTF-8',
					'Content-Transfer-Encoding: 8bit',
					'X-Priority: 1 (Higuest)',
					'X-MSMail-Priority: High',
					'Importance: High',
					'From: '.$this->usedProfile->mail['from'],
					'Reply-To: '.$this->usedProfile->mail['from'],
					'Return-Path: '.$this->usedProfile->mail['from'],
					'X-Mailer: PHP/'.  phpversion().' - TErHa Error Handler'
				)),
				join("\r\n",array(
					// additional parameters
				))
			);
			if ( $sendSuccess ) {
				foreach( $messages as $file ) {
					unlink($file);
				}
				file_put_contents($this->lastMailFile, time());
			}
		}
	}
}

//end
