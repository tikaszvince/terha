<?php

if ( !defined('E_DEPRECATED') ) {
	define('E_DEPRECATED', 8192 );
}

if ( !defined('E_USER_DEPRECATED') ) {
	define('E_USER_DEPRECATED', 16384 );
}
/**
 * Requirements
 */
require_once dirname(__FILE__).'/handler/abstract.php';

/**
 * Error handler
 *
 * Original ide from Tyrael (https://github.com/Tyrael/php-error-handler)
 * 
 * Error handler which can provide you an easy way to handle errors even non
 * recoverable errors like E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR.
 * 
 * It wraps the error to an ErrorException instance and throws the
 * exception (for catchable errors) or calls the default exception handler (for
 * fatal errors.
 *
 * BEWARE: you cannot continue the execution of your script on such errors, but
 * with this script, you can gracefully terminate.
 * 
 * @author Vince Tikász 4image#dev|WSE#dev
 * @uses PHP >= 5.2
 * @uses PDO_SQLITE
 */
class ErrorHandler {

	// Profile constants
	const DEV = 'dev';
	const TEST = 'test';
	const PROD = 'prod';

	// Display mode constants
	const DM_HTML = 'html';
	const DM_BLANK = 'blank';
	// group mode
	const GDM_COMMENT = 'comment';
	const GDM_DIV = 'div';

	/**
	 * Hold an instance of the class
	 * @var ErrorHandler
	 */
	private static $instance;

	/**
	 * @var mixed
	 */
	protected $oldErrHandler;

	/**
	 * Name of used profile
	 * @var String
	 */
	protected $profile = ErrorHandler::DEV;

	/**
	 * Used profile
	 * @var stdClass
	 */
	protected $usedProfile = array(
		'display_error' => true,
		'error_reporting' => E_ALL,
		'log_errors' =>  'file@{__DIRNAME__}/logs/dev/error-{date}.log',
		'mail' => false,
		'show_trace' => true,
		'group_display' => true,
		'display_mode' => ErrorHandler::DM_HTML,
		'group_mode' => ErrorHandler::GDM_DIV,
		'error_page_template' => 'dev/layout.php',
		'base_url' => '/',
	);

	/**
	 * Catched errors
	 * @var Array
	 */
	protected $_errors = array();

	/**
	 * Inited error handlers
	 * @var Array
	 */
	protected $handlers = array();

	/**
	 * Error page is displayed?
	 * @var Bool
	 */
	protected $errorPageDisplayed = false;

	/**
	 * Used labels for types
	 * @var Array
	 * @todo Move it to a method
	 */
	protected $errorLabels = array(
		E_ERROR => 'Fatal error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parse error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Fatal error',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_ERROR => 'Fatal error',
		E_COMPILE_WARNING => 'Fatal error',
		E_USER_ERROR => 'Fatal user error',
		E_USER_WARNING => 'User warning',
		E_USER_NOTICE => 'User notice',
		E_STRICT => 'Strict error',
		E_RECOVERABLE_ERROR => 'Catchable fatal error',
		E_DEPRECATED => 'Deprecated',
		E_USER_DEPRECATED => 'User deprecated'
	);

	/**
	 * If triggered error is one of theese error types script will terminating
	 * @var Array
	 */
	protected $dieOn = array(
		E_ERROR,
		E_PARSE,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR
	);

	/**
	 * Is this is an AJAX request?
	 * @see sendJSON()
	 * @var Bool
	 */
	protected $isAjaxRequest = false;

	/**
	 * Get singelton instance
	 * @return ErrorHandler
	 */
	static public function getInst() {
		if ( !isset(self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}

	/**
	 * Prive clone method; prevents object clone
	 */
	private function __clone() {
	}

	/**
	 * Prive wakeup method; prevents object deserialization
	 */
	private function __wakeup() {
	}

	/**
	 * Check if any error triggered
	 * @return Bool
	 */
	public function _hasError() {
		return !empty( $this->_errors );
	}
	
	/**
	 * Has error?
	 * @return Bool
	 */
	static public function hasError() {
		return self::getInst()->_hasError();
	}

// setup

	/**
	 * A private constructor; prevents direct creation of object
	 */
	private function __construct() {
		$this->usedProfile = (object)$this->usedProfile;
		// If ENVIRONMENT is declared, and we have profile with that name
		// reead and use that profile
		if (
			defined('ENVIRONMENT')
			&& is_readable( $ini = dirname(__FILE__).'/profiles/'.strtolower(ENVIRONMENT).'/settings.ini' )
		) {
			$this->loadProfile(ENVIRONMENT);
		}

		// If in root directory You have errorhandler.ini
		// we will try to parse and load settings from that
		$iniPath = dirname($_SERVER['SCRIPT_FILENAME']).'/errorhandler.ini';
		if ( is_readable($iniPath) ) {
			$this->parseConigFile($iniPath);
		}

		// setup internal property
		// On error display this is TRUE we send JSON answer for client
		$this->isAjaxRequest =
			isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH']
		;

		// Switch off PHP error displaing method
		ini_set('display_errors', 0);
		$this->oldErrHandler = set_error_handler(array($this,'errorHandling'));
		if ( $this->oldErrHandler ) {
			// if you already setup any error handler
			restore_error_handler();
			// we are sorry, but "There can be only one!"
			throw new Exception('error handler already defined');
		}
		// setup shutdown
		register_shutdown_function(array($this, 'shutdown'));
		// We must catch all exception too
		set_exception_handler(array($this,'exceptionHandler'));
		$this->initHandlers();
	}

	/**
	 * Init all available handlers
	 * @return ErrorHandler
	 */
	public function initHandlers() {
		foreach( glob( dirname(__FILE__).'/handler/*.php' ) as $file ) {
			$name = pathinfo($file,PATHINFO_FILENAME);
			if ( 'abstract' == $name ) {
				continue;
			}
			include_once $file;
			$class = 'ErrorHandler_Handler_'.ucfirst($name);
			$handler = new $class($this->usedProfile);
			if ( $handler->_init() ) {
				$handler->dieOn = &$this->dieOn;
				$handler->errors = &$this->_errors;
				$handler->errorLabels = &$this->errorLabels;
				$handler->errorPageDisplayed = &$this->errorPageDisplayed;
				$this->handlers[ $handler->order ][] = $handler;
			}
		}
		return $this;
	}
	
	/**
	 * Try to load 
	 * @param String $profile
	 * @return ErrorHandler 
	 */
	public function loadProfile( $profile ) {
		$profile = strtolower($profile);
		if ( is_readable( $ini = dirname(__FILE__).'/profiles/'.$profile.'/settings.ini' ) ) {
			$this->profile = $profile;
			$this->setProfile(parse_ini_file($ini, true));
		}
		return $this;
	}
	
	/**
	 * Parse configiration INI
	 * @param String $path path to INI file
	 * @return ErrorHandler
	 */
	protected function parseConigFile($path) {
		$ini = parse_ini_file($path,true);
		if ( isset($ini['ENVIRONMENT']) ) {
			$this->loadProfile($ini['ENVIRONMENT']);
		}

		if (
			isset( $ini['ERROR_HANDLING'] )
			&& is_array($ini['ERROR_HANDLING'])
		) {
			$this->setProfile($ini['ERROR_HANDLING']);
		}
		return $this;
	}

	/**
	 * Set profile settings from array
	 * @param Array $settings
	 * @return ErrorHandler 
	 */
	protected function setProfile(array $settings) {
		foreach( $settings as $setting => $value ) {
			switch( $setting ) {
				case 'base_url':
					$this->usedProfile->base_url = $value;
					break;

				case 'display_error':
				case 'show_trace':
				case 'group_display':
					$this->usedProfile->{$setting} = (bool)$value;
					break;

				case 'log_errors':
					if ( preg_match('%^(file|sqlite)@%', $value) ) {
						$this->usedProfile->log_errors = $value;
					}
					break;

				case 'display_mode':
					if ( in_array($value, array( self::DM_BLANK, self::DM_HTML )) ) {
						$this->usedProfile->display_mode = $value;
					}
					elseif ( in_array($value, array( 'DM_BLANK', 'DM_HTML' )) ) {
						$this->usedProfile->display_mode = constant(__CLASS__.'::'.$value);
					}
					break;

				case 'group_mode':
					if ( in_array($value, array( self::GDM_COMMENT, self::GDM_DIV )) ) {
						$this->usedProfile->group_mode = $value;
					}
					elseif ( in_array($value, array( 'GDM_COMMENT', 'GDM_DIV' )) ) {
						$this->usedProfile->group_mode = constant(__CLASS__.'::'.$value);
					}
					break;

				case 'mail':
					if ( is_array($value) ) {
						foreach( $value as $mailSetting => $mailValue ) {
							$this->usedProfile->mail[$mailSetting] = $mailValue;
						}
						$this->usedProfile->mail['sendIntervalSeconds'] =
								$this->usedProfile->mail['period'] * 60;
						$lastMailFile = dirname(__FILE__).'/logs/_mail/lastmailsent';
						if ( is_readable( $lastMailFile ) ) {
							$this->usedProfile->mail['lastMailSent'] = 
								intval(file_get_contents($lastMailFile));
						}
						else {
							file_put_contents($lastMailFile, 0);
							$this->usedProfile->mail['lastMailSent'] = 0;
						}
						$this->usedProfile->mail['sendOnDestruct'] = 
							!$this->usedProfile->mail['lastMailSent']
							|| ( time() >= $this->usedProfile->mail['lastMailSent'] 
												+ $this->usedProfile->mail['sendIntervalSeconds'] );
					}
					else {
						$this->usedProfile->mail = false;
					}
					break;
			}
		}
		return $this;
	}

	/**
	 * Enable error display
	 * @return ErrorHandler 
	 */
	public function enableDisplayError() {
		$this->usedProfile->display_error = true;
		return $this;
	}

	/**
	 * Hide ALL error
	 * @param Bool $force
	 * @return ErrorHandler 
	 */
	public function disableDisplayError($force = false) {
		$this->usedProfile->display_error = false;
		$this->usedProfile->force_disable_display = $force;
		return $this;
	}

// handling

	public function trigger($ev, $e = null) {
		foreach( $this->handlers as $order => $handlers ) {
			foreach( $handlers as $handler ) {
				switch( $ev ) {
					case ErrorHandler_Handler_Abstract::EV_INIT:
						$habdler->_init();
						break;
					case ErrorHandler_Handler_Abstract::EV_DESTRUCT:
						$handler->_destruct();
						break;
					case ErrorHandler_Handler_Abstract::EV_SHUTDOWN:
						$handler->_shutdown($e);
						break;
					case ErrorHandler_Handler_Abstract::EV_ONERROR:
						$handler->_errorHandling($e);
						break;
					case ErrorHandler_Handler_Abstract::EV_ONEXCEPTION:
						$handler->_exceptionHandling($e);
						break;
				}
			}
		}
	}
	
	/**
	 * Destructor
	 * @return void
	 */
	public function __destruct() {
		if (
			$this->usedProfile->mail
			&& $this->usedProfile->mail['sendOnDestruct']
		){
			$this->sendMail();
		}
		$this->trigger(ErrorHandler_Handler_Abstract::EV_DESTRUCT);
		restore_error_handler();
		restore_exception_handler();
	}

	/**
	 * Shutdown function to register
	 * @return void
	 */
	public function shutdown() {
		try {
			$error = error_get_last();
			if ( $error ) {
				$e = new ErrorException(
					$error['message'],
					-26,
					$error['type'],
					$error['file'],
					$error['line']
				);
				$this->trigger(ErrorHandler_Handler_Abstract::EV_SHUTDOWN,$e);
			}
		}
		catch(Exception $e) {
			error_log("unexpected exception in register_shutdown_function:\n".
						print_r($e, true), 4);
		}
	}

	/**
	 * Blank handler callback
	 */
	public function blankHandler() {
	}

	/**
	 * Error handler method for set_error_handler
	 * @author Tyrael
	 * @link https://github.com/Tyrael/php-error-handler
	 * @param Int $errno
	 * @param String $errstr
	 * @param String $errfile
	 * @param Int $errline
	 * @throws ErrorException
	 * @return void
	 */
	public function errorHandling($errno, $errstr, $errfile, $errline ) {
		if (!(error_reporting() & $errno)) {
			return;
		}
		$e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
		$e->eh_throwed = true;
		if ( !in_array( $errno, $this->dieOn ) ) {
			$e->eh_throwed = false;
			$this->trigger(ErrorHandler_Handler_Abstract::EV_ONERROR,$e);
		}
		else {
			throw $e;
		}
	}

	/**
	 * Exception handler for set_exception_handler
	 * @param Exception $e
	 * @return void
	 */
	public function exceptionHandler(Exception $e) {
		$this->trigger(ErrorHandler_Handler_Abstract::EV_ONEXCEPTION,$e);
	}
}

ErrorHandler::getInst();

//end
