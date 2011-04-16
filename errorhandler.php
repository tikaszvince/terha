<?php

if ( !defined('E_DEPRECATED') ) {
	define('E_DEPRECATED', 8192 );
}

if ( !defined('E_USER_DEPRECATED') ) {
	define('E_USER_DEPRECATED', 16384 );
}

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
final class ErrorHandler {

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

		// Setup logger properties
		$logger = explode('@',$this->usedProfile->log_errors);
		// Logging method
		$this->usedProfile->_logger = array_shift($logger);
		// logfilename
		$this->usedProfile->_logfile = join('@',$logger);

		// Switch off PHP error displaing method
		ini_set('display_errors', 0);
		// setup shutdown
		register_shutdown_function(array($this, 'shutdown'));
		$this->oldErrHandler = set_error_handler(array($this,'errorHandling'));
		if ( $this->oldErrHandler ) {
			// if you already setup any error handler
			restore_error_handler();
			// we are sorry, but "There can be only one!"
			throw new Exception('error handler already defined');
		}
		// We must catch all exception too
		set_exception_handler(array($this,'exceptionHandler'));
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
						$this->usedProfile->group_display = $value;
					}
					elseif ( in_array($value, array( 'DM_BLANK', 'DM_HTML' )) ) {
						$this->usedProfile->group_display = constant(__CLASS__.'::'.$value);
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
							$this->usedProfile->mail['lastMailSent']
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

	/**
	 * Destructor
	 * @return void
	 */
	public function __destruct() {
		if (
			$this->errorPageDisplayed
			|| (
				isset($this->usedProfile->force_disable_display)
				&& $this->usedProfile->force_disable_display
			)
		) {
			// do not display
		}
		elseif(
			$this->usedProfile->display_error
			&& count( $this->_errors )
		){
			if ( ErrorHandler::GDM_DIV == $this->usedProfile->group_mode ) {
				echo "<div id=\"php-errors\"><div class=\"c\">Triggered errors:<br/>\n",
					join("\n", $this->_errors),"\n</div></div>\n";
			}
			else {
				echo "\n<!-- Triggered errors:\n",
					join("\n", $this->_errors),"\n-->\n";				
			}
		}
		if (
			$this->usedProfile->mail
			&& $this->usedProfile->mail['sendOnDestruct']
		){
			$this->sendMail();
		}
		restore_error_handler();
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
				$this->exceptionHandler($e);
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
			$this->exceptionHandler($e);
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
		$typeLabel = 'Error:';
		$displayed = false;
		if ( $e instanceof ErrorException ) {
			$severity = $e->getSeverity();
			$die = in_array( $severity, $this->dieOn );
			if ( isset( $this->errorLabels[$severity] ) ) {
				$typeLabel = $this->errorLabels[$severity].':';
			}
		}
		else {
			$typeLabel = '';
			$die = false;
		}
		$e->error_type_name = $typeLabel;
		if ( $die || !($e instanceof ErrorException) ) {
			$this->errorPageDisplayed = true;
			// For debug You can throw an exception with code -16
			// to display any data, without display any error page
			if ( -16 == $e->getCode() ) {
				if ( $this->isAjaxRequest ) {
					$this->sendJSON($e);
					exit;
				}
				include dirname(__FILE__).'/profiles/_debug/layout.php';
				exit;
			}
			$templates = dirname(__FILE__).'/profiles/';
			$pageTemplate = 'prod/layout.php';
			if (
				$this->usedProfile->error_page_template
				&& is_file( $templates.$this->usedProfile->error_page_template )
			) {
				$pageTemplate = $this->usedProfile->error_page_template;
			}
			include $templates.$pageTemplate;
			$displayed = true;
		}

		// do error handling
		if ( $this->isAjaxRequest ) {
			$this->sendJSON($e);
			$displayed = true;
		}
		if ( $this->usedProfile->display_error && !$displayed ) {
			$this->display($e, $die);
		}
		if ( $this->usedProfile->log_errors ) {
			$this->log($e);
		}
		if ( $this->usedProfile->mail ) {
			$this->mail($e);
		}
	}

// backend

	/**
	 * Send JSON answer to client
	 * @param Exception $e 
	 * @return void
	 */
	protected function sendJSON(Exception $e) {
		header('HTTP/1.1 500 Internal Server Error');
		header('Content-type: application/json; charset=utf8');
		header('Access-Control-Max-Age: 3628800');
		echo json_encode(array(
			'error' => true,
			'code' => 500,
			'status' => 'error',
			'exception' => array(
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'message' => $e->getMessage(),
				'trace' => $e->getTrace()
			)
		));
	}

	/**
	 * Display error
	 * @param Exception $e
	 * @param Bool $die
	 * @return void
	 */
	protected function display(Exception $e, $die = false) {
		ob_start();
		// Display Mode
		if ( ErrorHandler::DM_HTML == $this->usedProfile->display_mode ) {
			$this->displayHtml($e);
		}
		else {
			$this->displayComment($e);
		}
		
		if ( $this->usedProfile->display_error ) {
			// group display?
			if ( $this->usedProfile->group_display && !$die ) {
				$this->_errors[] = ob_get_clean();
			}
			else {
				ob_end_flush();
			}
		}
	}

	/**
	 * Log error
	 * @param Exception $e
	 * @return void
	 */
	protected function log(Exception $e) {
		if ( !$this->usedProfile->log_errors ) {
			return;
		}
		switch($this->usedProfile->_logger) {
			case 'file':
				$this->logLog($e);
				break;
			case 'sqlite':
				$this->logSqlite($e);
				break;
			default:
				break;
		}
	}

	/**
	 * Mail errors
	 * @param Exception $e
	 * @return void
	 */
	protected function mail(Exception $e) {
		$errorHash = md5( $e->getMessage().$e->getFile().$e->getLine() );
		$hashFile = dirname(__FILE__).'/logs/_mail/'.$errorHash;
		$msg = array();
		if ( !is_file( $hashFile ) ) {
			$msg[] = $e->error_type_name.': ';
			$msg[] = $e->getMessage().' in ';
			$msg[] = $e->getFile().':[';
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

//helpers

	/**
	 * Get function string for trace display
	 * @param Array $tr trace
	 * @return String
	 */
	protected function getFunctionString(array $tr) {
		$func = array();
		if ( isset($tr['class']) ) {
			if( 'ErrorHandler' == $tr['class'] && 'errorHandler' == $tr['function'] ) {
				return 'ErrorHandler::errorHandler';
			}
			$func[] = $tr['class'];
		}
		if ( isset($tr['type']) ) {
			$func[] = $tr['type'];
		}
		if ( isset($tr['function']) ) {
			$func[] = $tr['function'].'(';
			if ( isset($tr['args']) && $tr['args'] ) {
				$args = array();
				foreach( $tr['args'] as $arg ) {
					if ( is_object($arg) ) {
						$args[] = 'Object['.get_class($arg).']';
					}
					elseif( is_array( $arg ) ) {
						$args[] = 'Array('.count($arg).')';
					}
					else {
						$args[] = $arg;
					}
				}
				$func[] = join(', ',$args);
			}
			$func[] = ')';
		}
		return join('',$func);
	}

	/**
	 * Get log file name
	 * @return String
	 */
	protected function getLogFileName() {
		if ( !isset( $this->usedProfile->logfilename ) ) {
			$filename = '/logs/error-{w}.log';
			$filename = preg_replace(
				array(
					'%{__DIRNAME__}%','%{__APPDIR__}%',
					'%{[wW]}%', '%{date}%', '%{m}%', '%{Ym}%', '%{Y[wW]}%'
				),
				array(
					dirname(__FILE__),dirname($_SERVER['SCRIPT_FILENAME']),
					date('W'), date('Y.m.d'), date('m'), date('Y.m'), date('Y.W')
				),
				$this->usedProfile->_logfile
			);
			if ( !is_dir( dirname($filename) ) ) {
				mkdir( dirname($filename), 0777, true );
				chmod( dirname($filename), 0777 );
			}
			$this->usedProfile->logfilename = dirname($filename).'/'.basename($filename);
		}
		return $this->usedProfile->logfilename;
	}

// Helpers: file log

	/**
	 * Write error into TXT log
	 * @param Exception $e
	 * @return void
	 */
	protected function logLog(Exception $e) {
		$msg = array(
			date('c'),' @ ',
			$_SERVER['SERVER_PROTOCOL'],' ',$_SERVER['REQUEST_METHOD'],' ',
			$_SERVER['HTTP_HOST'],':',$_SERVER['SERVER_PORT'],$_SERVER['REQUEST_URI'], ' - ',
			$e->error_type_name,$e->getMessage(),' in ',
			$e->getFile(),':[',$e->getLine(),']',"\n\t",
			$_SERVER['REMOTE_ADDR'],' => ',$_SERVER['HTTP_USER_AGENT'],"\n"
		);
		$fileName = $this->getLogFileName();
		if (!is_file($fileName)) {
			touch($fileName);
			chmod($fileName,0666);
		}
		file_put_contents($fileName, implode('',$msg), FILE_APPEND | LOCK_EX );
	}

// Helpers: SQLite

	/**
	 * Get SQLite PDO object
	 * @return PDO
	 */
	protected function getSqliteDb() {
		$createTable = !is_file($this->getLogFileName());
		if ( !isset($this->sqliteLogDb) ) {
			$this->sqliteLogDb = new PDO('sqlite:'.$this->getLogFileName());
		}
		if ( !$createTable ) {
			$createTable = 0 >= count($this->sqliteLogDb->query('PRAGMA table_info(errors)')->fetchAll());
		}
		if ( $createTable ) {
			$this->sqliteLogDb->exec('CREATE TABLE errors (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				hash char(32),
				msg text,
				file text,
				line INTEGER default 0
			)');
			$this->sqliteLogDb->exec('CREATE INDEX hashIndex on hash');
			$this->sqliteLogDb->exec('CREATE TABLE log (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				hash char(32),
				timestamp timestamp NOT NULL default CURRENT_TIMESTAMP,
				method cahr(10),
				hostname varchar(100),
				port integer,
				uri text,
				ip char(14),
				params text
			)');
			
		}
		return $this->sqliteLogDb;
	}

	/**
	 * Write into SQLite log
	 * @param Exception $e
	 * @return void
	 */
	protected function logSqlite(Exception $e) {
		if ( !extension_loaded('pdo_sqlite') ) {
			$this->logLog($e);
			return;
		}
		$error = array(
			'id' => null,
			'hash' => md5( $e->getMessage().$e->getFile().$e->getLine() ),
			'msg' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine()
		);
		$sth = $this->getSqliteDb()->prepare('SELECT id FROM errors WHERE hash = :hash LIMIT 1');
		$sth->execute(array(':hash' => $error['hash'] ));
		$error['id'] = $sth->fetchColumn();
		if ( !$error['id'] ) {
			$sth = $this->getSqliteDb()
				->prepare('INSERT INTO errors (hash, msg,file,line) VALUES (:hash, :msg, :file, :line)');
			$sth->bindValue(':hash', $error['hash'], PDO::PARAM_STR);
			$sth->bindValue(':msg', $error['msg'], PDO::PARAM_STR);
			$sth->bindValue(':file', $error['file'], PDO::PARAM_STR);
			$sth->bindValue(':line', $error['line'], PDO::PARAM_INT);
			$sth->execute();
			$error['id'] = $this->getSqliteDb()->lastInsertId();
		}
		$sth = $this->getSqliteDb()->prepare('INSERT INTO log (hash,method,hostname,port,uri,ip,params)
			VALUES (:hash,:method,:hostname,:port,:uri,:ip,:params)');
		$sth->bindValue(':hash', $error['hash'], PDO::PARAM_STR);
		$sth->bindValue(':method', $_SERVER['REQUEST_METHOD'], PDO::PARAM_STR);
		$sth->bindValue(':hostname', $_SERVER['HTTP_HOST'], PDO::PARAM_STR);
		$sth->bindValue(':port', $_SERVER['SERVER_PORT'], PDO::PARAM_STR);
		$sth->bindValue(':uri', $_SERVER['REQUEST_URI'], PDO::PARAM_STR);
		$sth->bindValue(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
		$params = array(
			'get' => $_GET,
			'post' => $_POST,
			'cookie' => $_COOKIE,
			'server' => $_SERVER,
			'env' => $_ENV,
		);
		foreach( array('password','password2','password_again','pwd','pwd2','passwd','passwrd2') as $pwd ) {
			$params['post'][$pwd] = '[masked-password]';
		}
		$sth->bindValue(':params', serialize($params), PDO::PARAM_STR);
		$sth->execute();
	}

// Helpers: display

	/**
	 * Display an error with trace
	 * @param Exception $e
	 * @return void
	 */
	protected function displayHtml(Exception $e) {
		echo "\n<!-- Display Error-->\n<br/>",
			'<table class="xdebug-error" dir="ltr" cellspacing="0" cellpadding="1" ',
				'border="1" style="text-align:left !important;">';
		if ( isset($e->xdebug_message) ) {
			echo $e->xdebug_message;
		}
		else {
			$typeName = isset($e->error_type_name)
				? $e->error_type_name
				: ''
			;
			echo '<tr><th align=""left"" bgcolor="#f57900" colspan="3">',
				'<span style="background-color: #cc0000; color: #fce94f; font-size: x-large;">( ! )</span> ',
				$typeName
			;
			if ( !($e instanceof ErrorException) ) {
				echo "Uncaught exception '",get_class($e),"' with message ";
			}
			echo " '",$e->getMessage(),"' in ",
					$e->getFile(),' on line <i>',$e->getLine(),"</i></th></tr>\n";
			
			if ( $this->usedProfile->show_trace ) {
				echo '<tr><th align="left" bgcolor="#e9b96e" colspan="3">Call Stack</th></tr>',
					'<tr><th align="center" bgcolor="#eeeeec">#</th><th align="left" bgcolor="#eeeeec">Function</th>',
						'<th align="left" bgcolor="#eeeeec">Location</th></tr>',"\n",
					'<tr><td bgcolor="#eeeeec" align="center">1</td><td bgcolor="#eeeeec">{main}(  )</td>',
						'<td title="',$_SERVER['PHP_SELF'],'" bgcolor="#eeeeec">',basename($_SERVER['PHP_SELF']),
							"<b>:</b>0</td></tr>\n"
				;
				$trace = array_reverse($e->getTrace());
				foreach( $trace as $i => $tr ) {
					$count = $i+2;
					$func = $this->getFunctionString($tr);
					if ( 'ErrorHandler::errorHandler' == $func ) {
						continue;
					}
					$file = isset($tr['file']) ? $tr['file'] : '';
					$line = isset($tr['line']) ? $tr['line'] : 0;
					
					echo '<tr><td bgcolor="#eeeeec" align="center">',$count,'</td>',
						'<td bgcolor="#eeeeec">',$func,'</td>',
						'<td title="',$file,'" bgcolor="#eeeeec">',basename($file),'<b>:</b>',$line,
						"</td></tr>\n";
				}
				echo '<tr><td bgcolor="#eeeeec" align="center">',($count+1),'</td>',
					'<td bgcolor="#eeeeec">',$e->getMessage(),'</td>',
					'<td title="',$e->getFile(),'" bgcolor="#eeeeec">',basename($e->getFile()),'<b>:</b>',$e->getLine(),
					"</td></tr>\n";
			}
		}
		echo "</table>\n<!-- /DisplayError-->\n";
	}

	/**
	 * Display error
	 * @param Exception $e
	 * @return void
	 */
	protected function displayComment(Exception $e) {
		echo 
			$e->error_type_name,
			$e->getMessage(),' in ',
			$e->getFile(),':[',$e->getLine(),']',"\n\t"
		;
	}

// Helpers: mail

	/**
	 * Do mail sending
	 * @return void
	 */
	protected function sendMail() {
		$messages = glob( dirname(__FILE__).'/logs/_mail/*' );
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
				$this->usedProfile->mail['subject'].date('Y-m-d H:i:s'),
				'=?UTF-8?B?'.base64_encode('Theese errors triggered: '.join('',$msg)).'?=',
				join("\r\n",array(
					// additional headers
					'MIME-Version: 1.0',
					'Content-type: text/plain; charset=UTF-8',
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
				file_put_contents(dirname(__FILE__).'/logs/_mail/lastmailsent', time());
			}
		}
	}
}

ErrorHandler::getInst();

//end
