<?php
xdebug_disable();
ini_set('display_errors', 'On');
error_reporting(E_ALL) ;
include '../errorhandler.php';


?>
<a href="?demo=error">Error</a> | 
<a href="?demo=warning">Warning</a> | 
<a href="?demo=parse">Parse</a> | 
<a href="?demo=core_error">Core Error</a> |
<a href="?demo=core_warning">Core Warning</a> | 
<a href="?demo=compile">Compile</a> |
<a href="?demo=compile_warning">Compile</a> |
<a href="?demo=user_error">User error</a> |
<a href="?demo=user_warning">User warning</a> |
<a href="?demo=user_notice">User notice</a> |
<a href="?demo=strict">strict</a> |
<a href="?demo=recoverable_error">Recoverable error</a> |
<a href="?demo=deprecated">Deprecated</a> |
<a href="?demo=user_deprecated">User Deprecated</a> |
<a href="?demo=exception">Exception</a>
<?php

switch ( isset($_GET['demo']) ? $_GET['demo'] : false ) {
	case 'error':
		include 'e00001_error.php'; //1
		break;
	case 'warning':
		include 'e00002_warning.php';
		break;
	case 'parse':
		include 'e00004_parse.php'; //1
		break;
	case 'notice':
		include 'e00008_notice.php';
		break;
	case 'core_error':
		include 'e00016_core_error.php'; //1
		break;
	case 'core_warning':
		include 'e00032_core_warning.php';
		break;
	case 'compile_error':
		include 'e00064_compile_error.php'; //1
		break;
	case 'compile':
		include 'e00128_compile_warning.php';
		break;
	case 'user_error':
		include 'e00256_user_error.php';
		break;
	case 'user_warning':
		include 'e00512_user_warning.php';
		break;
	case 'user_notice':
		include 'e01024_user_notice.php';
		break;
	case 'strict':
		include 'e02048_strict.php';
		break;
	case 'recoverable_error':
		include 'e04096_strict.php';
		break;
	case 'deprecated':
		include 'e08192_deprecated.php';
		break;
	case 'user_deprecated':
		include 'e16384_user_deprecated.php';
		break;
	case 'exception':
		include 'e_exception.php';
		break;
	default:
		break;
}



