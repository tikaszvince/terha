<?php
$traceDisplayMode = 'areverseTrace';
$baseUrl = $this->usedProfile->base_url;
?><!doctype html>
<!-- paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither/ -->
<!--[if lt IE 7 ]> <html class="no-js ie6" lang="en"> <![endif]-->
<!--[if IE 7 ]>    <html class="no-js ie7" lang="en"> <![endif]-->
<!--[if IE 8 ]>    <html class="no-js ie8" lang="en"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
  <meta charset="utf-8">

  <!-- Always force latest IE rendering engine (even in intranet) & Chrome Frame
       Remove this if you use the .htaccess -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

  <title>Error</title>
  <meta name="description" content=""/>
  <meta name="author" content=""/>

  <!-- Mobile viewport optimized: j.mp/bplateviewport -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Place favicon.ico & apple-touch-icon.png in the root of your domain and delete these references -->
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <!-- CSS: implied media="all" -->
  <link rel="stylesheet" href="<?php echo $baseUrl ?>/profiles/dev/error.css?v=2">

  <!-- Uncomment if you are specifically targeting less enabled mobile browsers
  <link rel="stylesheet" media="handheld" href="css/handheld.css?v=2">  -->

  <!-- All JavaScript at the bottom, except for Modernizr which enables HTML5 elements & feature detects -->
  <script src="<?php echo $baseUrl ?>/js/modernizr-1.7.min.js"></script>

</head>

<body class="dev">
  <div id="container">
    <header>
		<h1>Hiba történt!</h1>
    </header>
    <div id="main" role="main">
<?php if ( $this->profile == 'prod' ) : ?>
	<div>Nagyon rossz dolog történt</div>
<?php else : ?>
<!-- Display Error-->
<h3>
	<span class="type"><?php echo $e->error_type_name ?></span>
	<?php if ( !($e instanceof ErrorException) ) {
		echo "Uncaught exception '",get_class($e),"' with message ";
	} ?>
	<span class="msg"><?php echo $e->getMessage(); ?></span>
</h3>
<p class="triggered">
<?php echo $e->getMessage(),' in ',$e->getFile(),' on line <i>',$e->getLine(),'</i>'; ?>
</p>
<?php
if ( $this->usedProfile->show_trace ) {
	if ( $e->getPrevious() ) {
		echo '<p>',$e->getPrevious()->getMessage(),'</p>';
	}
	if ( isset($e->queryLog) ) {
		echo '<h2>Query Log</h2>';
		foreach( array_reverse($e->queryLog, true) as $i => $log ) {
			echo '<div class="query"><h3>Query ',$i,'</h3><pre lang="sql">',$log['query'],'</pre>';
			if (
				-1 < $log['countAffected']
				|| $log['error']['errno']
			) {
				echo '<dl>';
				if ( -1 < $log['countAffected'] ) {
					echo '<dt>Affected rows</dt><dd>',$log['countAffected'],'</dd>';
				}
				if ( $log['error']['errno'] ) {
					echo '<dt>ErrNo</dt><dd>',$log['error']['errno'],'</dd>',
						'<dt>Error message</dt><dd>',$log['error']['message'],'</dd>';
				}
				echo '</dl>';
			}
				

			echo '</div>';
		}
	}
	$reverse = 'reverseTrace' == $traceDisplayMode;
	function traceTableRow($i, $func, $tr, $show = false) {
		$count = $i+1;
		$file = isset($tr['file']) ? $tr['file'] : '';
		$line = isset($tr['line']) ? $tr['line'] : 0;
		$_closer = $line ? '&nbsp;<a class="closer">+</a>' : '';

		echo '<tbody id="trace-',($i),'"><tr><td>',$count,$_closer,'</td>',
			'<td>',$func,'</td>',
			'<td title="',$file,'">',basename($file),'<b>:</b>',$line,
			"</td></tr>\n";
		if ( $line ) {
			echo '<tr style="display:',($show ? 'table-row':'none'),'"><td colspan="3" class="code">',"\n<!-- !!! -->\n";
			$fileContent = preg_split( '/\r?\n/', file_get_contents($file) );
			$_start = $line-10;
			if ( 0 >= $_start ) {
				$_start = 0;
			}
			echo '<ol start="',($_start+1),'">';
			foreach( array_slice($fileContent, $_start, 20, true) as $l => $codeLine ) {
				//$codeLine = preg_replace("/\t/", '    ', $codeLine);
				//$codeLine = highlight_string('<?php '.$codeLine, true);
				//$codeLine = preg_replace('/>&lt;\?php&nbsp;/', '>', $codeLine);
				$codeLine = htmlentities($codeLine, ENT_QUOTES, 'UTF-8');
				echo '<li class="l',($l+1 == $line ? ' actline' :'' ),'">',$codeLine,"<!-- !! --></li>\n";
			}
			echo "</ol></td></tr>\n";
		}
		echo '</tbody>';
	}
	echo '<table class="trace"><thead><tr><th colspan="3">Call Stack</th></tr>',
		'<tr><th>#&nbsp;<a class="switch up hidden">&DoubleUpArrow;</a><a class="switch down">&DoubleDownArrow;</a></th><th>Function</th>',
			'<th>Location</th></tr></thead>',"\n";
	if ( -26 === $e->getCode() ) {
		traceTableRow(0, $e->getMessage(), array('file'=> $e->getFile(),'line'=>$e->getLine()),true);
	}
	else {
		$trace = $e->getTrace();
		if ( $reverse ) {
			echo $main;
			$trace = array_reverse($trace);
			echo '<tbody id="trace-0"><tr><td>1</td><td>{main}(  )</td>',
				'<td title="',$_SERVER['PHP_SELF'],'">',basename($_SERVER['PHP_SELF']),"<b>:</b>0</td></tr></tbody>\n";
		}
		else {
			traceTableRow(0, $e->getMessage(), array('file'=> $e->getFile(),'line'=>$e->getLine()),true);
		}

		foreach( $trace as $i => $tr ) {
			$count = $i+1;
			$func = $this->getFunctionString($tr);
			if ( 'ErrorHandler::errorHandler' == $func ) {
				continue;
			}
			traceTableRow($count, $func, $tr);
		}
		if ($reverse) {
			traceTableRow($i+2, $e->getMessage(), array('file'=> $e->getFile(),'line'=>$e->getLine()),true);
		}
		else {
			echo '<tbody id="trace-',($i+2),'"><tr><td>',($i+3),'</td><td>{main}(  )</td>',
				'<td title="',$_SERVER['PHP_SELF'],'">',basename($_SERVER['PHP_SELF']),"<b>:</b>0</td></tr></tbody>\n";
		}
	}	
	echo "\n</table>\n";
}
if ( isset($e->xdebug_message) ) {
	//echo $e->xdebug_message;
}
?>
<!-- /DisplayError -->
<?php endif; ?>
</div>
    <footer>
		
		
    </footer>
  </div> <!--! end of #container -->


  <!-- JavaScript at the bottom for fast page loading -->

  <!-- Grab Google CDN's jQuery, with a protocol relative URL; fall back to local if necessary -->
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.js"></script>
  <script>window.jQuery || document.write('<script src="<?php echo $baseUrl ?>/js/libs/jquery-1.5.1.min.js">\x3C/script>')</script>


  <!-- scripts concatenated and minified via ant build script-->
  <script src="<?php echo $baseUrl ?>/js/plugins.js"></script>
  <script src="<?php echo $baseUrl ?>/js/script.js"></script>
  <!-- end scripts-->

  <!-- mathiasbynens.be/notes/async-analytics-snippet Change UA-XXXXX-X to be your site's ID -->
  <script>
    var _gaq=[['_setAccount','UA-XXXXX-X'],['_trackPageview']];
    (function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];g.async=1;
    g.src=('https:'==location.protocol?'//ssl':'//www')+'.google-analytics.com/ga.js';
    s.parentNode.insertBefore(g,s)}(document,'script'));
  </script>

</body>
</html>
