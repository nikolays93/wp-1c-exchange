jQuery(document).ready(function($) {
	if( pagenow != 'woocommerce_page_exchange' )
		return;

	var ajaxdata = {};
	ajaxdata.counter = 1;
	ajaxdata.nonce = AJAX_VAR.nonce;

	function start_products_update(ajaxdata){
		$.ajax({
			type: 'POST',
			url: AJAX_VAR.url,
			data: ajaxdata,
			success: function(response){
				ajaxdata.counter++;
				if(ajaxdata.counter <= ajaxdata.update_count)
					start_products_update(ajaxdata);
				else
					alert('Выгрузка завершена');

				var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
				// console.log( 100 / ajaxdata.update_count +', '+ ajaxdata.counter +', '+ progrss );
				$('.progress .progress-fill').css('width',  progrss + '%' );
			}
		}).fail(function() { alert('AJAX Error'); });
	}
	
	$('#load-products').on('click', function(event) {
		event.preventDefault();

		ajaxdata.action = 'insert_posts';
		ajaxdata.update_count = Math.floor( $('#p_count').val() / AJAX_VAR.products_at_once ); // количество запросов
		ajaxdata.at_once = AJAX_VAR.products_at_once;
		start_products_update(ajaxdata);
	});

	$('#load-categories').on('click', function(event) {
		console.log('clack!');
		event.preventDefault();

		ajaxdata.action = 'insert_terms';
		ajaxdata.update_count = Math.floor( $('#t_count').val() / AJAX_VAR.products_at_once ); // количество запросов
		ajaxdata.at_once = AJAX_VAR.products_at_once;
		start_products_update(ajaxdata);
	});

	
});