<?php

/**
 * Abstract Error handler
 * @author Tikász Vince WSE#dev
 */
abstract class ErrorHandler_Handler_Abstract {

	/**#@+
	 * Event constansts
	 */
	const EV_INIT = 1;
	const EV_DESTRUCT = 2;
	const EV_SHUTDOWN = 3;
	const EV_ONERROR = 4;
	const EV_ONEXCEPTION = 5;
	/**#@-*/

	/**
	 * @var stdClass
	 */
	protected $usedProfile;
	
	/**
	 * Is this is an AJAX request?
	 * @see sendJSON()
	 * @var Bool
	 */
	protected $isAjaxRequest = false;

	/**
	 * @var Int
	 */
	public $order = 10;

	/**
	 * Constructor
	 * @param stdClass $_settings ErrorHandler->usedProfile
	 */
	public function __construct( &$_settings) {
		$this->usedProfile = $_settings;

		// setup internal property
		// On error display this is TRUE we send JSON answer for client
		$this->isAjaxRequest =
			isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH']
		;
	}

	/**
	 * Handler init
	 * @return Bool
	 */
	final public function _init() {
		//var_dump( get_class($this).'::'.__FUNCTION__.'()' );
		return $this->init();
	}

	/**
	 * Destruction handler
	 * @return void
	 */
	final public function _destruct() {
		//var_dump( get_class($this).'::'.__FUNCTION__.'()' );
		$this->onDestruct();
	}

	/**
	 * Shutdown handler
	 * @return void
	 */
	final public function _shutdown(Exception $e) {
		//var_dump( get_class($this).'::'.__FUNCTION__.'()' );
		$this->onShutdown($e);
	}

	/**
	 * Error handler
	 * @param Exception $e 
	 */
	final public function _errorHandling(Exception $e) {
		//var_dump( get_class($this).'::'.__FUNCTION__.'()' );
		$this->onError($e);
	}

	/**
	 * Exception handler
	 * @param Exception $e 
	 */
	final public function _exceptionHandling(Exception $e) {
		//var_dump( get_class($this).'::'.__FUNCTION__.'()' );
		$this->onException($e);
	}

	/**
	 * Blank init event
	 * @return Bool
	 */
	protected function init() {
		return false;
	}

	/**
	 * Blank Destruction handler
	 * @return void
	 */
	protected function onDestruct() {}

	/**
	 * Blank Shutdown handler
	 * @return void
	 */
	protected function onShutdown(Exception $e) {}

	/**
	 * Blank error handler
	 * @param Exception $e 
	 */
	protected function onError(Exception $e) {}

	/**
	 * Blank Excpetion handler
	 * @param Exception $e
	 */
	protected function onException(Exception $e) {}

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

}

//end
