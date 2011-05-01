<?php
/**
 * Error display handler
 * @author Vince Tikász 4image#dev|WSE#dev
 */

/**
 * Requirements
 */
require_once dirname(__FILE__).'/abstract.php';

/**
 * Error display handler
 * @author Vince Tikász 4image#dev|WSE#dev
 */
class ErrorHandler_Handler_Display
	extends ErrorHandler_Handler_Abstract {

	/**
	 * Init display handler
	 * @return Bool
	 */
	protected function init() {
		return (bool)$this->usedProfile->display_error;
	}

	/**
	 * Shutdown handler
	 * @return void
	 */
	protected function onShutdown(Exception $e) {
		$this->onException($e);
	}

	/**
	 * Error handler
	 * @return void
	 */
	protected function onError($e) {
		$this->display($e);
	}

	/**
	 * Exception handler
	 * @param Exception $e 
	 */
	protected function onException(Exception $e) {
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
				include dirname(dirname(__FILE__)).'/profiles/_debug/layout.php';
				exit;
			}
			$templates = dirname(dirname(__FILE__)).'/profiles/';
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
		if ( $this->usedProfile->display_error && !$displayed ) {
			$this->display($e, $die);
		}

	}

	/**
	 * Destruction handler
	 * @return void
	 */
	protected function onDestruct() {
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
	}

	/**
	 * Display error
	 * @param Exception $e
	 * @param Bool $die
	 * @return void
	 */
	private function display(Exception $e, $die = false) {
		ob_start();
		// Display Mode
		if (
			ErrorHandler::DM_HTML == $this->usedProfile->display_mode
			&& ErrorHandler::GDM_COMMENT !== $this->usedProfile->group_mode
		) {
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
	 * Display an error with trace
	 * @param Exception $e
	 * @return void
	 */
	private function displayHtml(Exception $e) {
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
						'<td title="',$_SERVER['SCRIPT_FILENAME'],'" bgcolor="#eeeeec">',basename($_SERVER['SCRIPT_FILENAME']),
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
	private function displayComment(Exception $e) {
		echo 
			$e->error_type_name,
			$e->getMessage(),' in ',
			$e->getFile(),':[',$e->getLine(),']',"\n\t"
		;
	}

}

//end
