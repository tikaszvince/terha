<?php

require_once dirname(__FILE__).'/abstract.php';

class ErrorHandler_Handler_Logger
	extends ErrorHandler_Handler_Abstract {
	
	protected function init() {
		if( (bool)$this->usedProfile->log_errors ) {
			// Setup logger properties
			$logger = explode('@',$this->usedProfile->log_errors);
			// Logging method
			$this->usedProfile->_logger = array_shift($logger);
			// logfilename
			$this->usedProfile->_logfile = join('@',$logger);

		}
		return false;
	}

	public function onException(Exception $e) {
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

	public function onDestruct() {
		if (
			$this->usedProfile->mail
			&& $this->usedProfile->mail['sendOnDestruct']
		){
			$this->sendMail();
		}
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

}

//end
