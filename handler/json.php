<?php

require_once dirname(__FILE__).'/abstract.php';

class ErrorHandler_Handler_Json
	extends ErrorHandler_Handler_Abstract {
	
	protected function init() {
		return $this->isAjaxRequest;
	}
	
	public function onException(Exception $e) {
		if ( !$this->isAjaxRequest ) {
			return false;
		}
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
		return true;
	}
}

//end
