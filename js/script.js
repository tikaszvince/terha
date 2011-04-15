/**
 * Aurora ErrorHandler
 * @author Tikász Vince
 * @since 2011-03-23
 */

jQuery(document).ready(function($){
	var traces = [];
	$('table.trace tbody').each(function(){
		traces.push($(this));
	});
	if ( 1 < traces.length ) {
		$('table.trace thead tr:eq(1) th:first a').live('click',function(){
			$('table.trace tbody').remove();
			traces.reverse();
			for( var i = 0; i < traces.length; i++ ) {
				console.log( $(traces[i]).attr('id'));
				$('table.trace').append( traces[i] );
			}
			$('table.trace thead tr:eq(1) th:first a').toggleClass('hidden');
		});
	}
	$('table.trace tbody .closer').live('click',function(){
		$(this).parents('tbody').find('tr:eq(1)').toggle();
	});
});

// end
