<?php

function returnDemo() {
	include 'e01024_user_notice.php';
	$return = 'returning value of '.__FUNCTION__;
	// will trigger a notice
	include 'e00008_notice.php';
	return $return;
}

$value = returnDemo();
var_dump( $value );
