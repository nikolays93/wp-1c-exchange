jQuery(document).ready(function($) {
	if( pagenow != 'woocommerce_page_exchange' )
		return;

	var ajaxdata = {};
	ajaxdata.counter = 1;
	ajaxdata.nonce = AJAX_VAR.nonce;

	function start_products_update(ajaxdata){
		$('#ajax_action').html('Выгрузка началась!');

		$.ajax({
			type: 'POST',
			url: AJAX_VAR.url,
			data: ajaxdata,
			success: function(response){
				ajaxdata.counter++;
				if(ajaxdata.counter <= ajaxdata.update_count){
					start_products_update(ajaxdata);

					var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
					if(progrss >= 100) progrss = 97;
					$('.progress .progress-fill').css('width',  progrss + '%' );
				}
				else{
					$('#ajax_action').html('Исправление кэш карты.');

					var fixmap = { 'nonce' : AJAX_VAR.nonce };
					
					fixmap.action = ( ajaxdata.action == 'insert_terms' ) ? 'fix_term_map' : 'fix_product_map';

					$.ajax({
						type : 'POST',
						url: AJAX_VAR.url,
						data : fixmap,
						success: function(response){
							$('#ajax_action').html('Выгрузка завершена!');

							ajaxdata.counter = 0;
							progrss = 0;
							$('.progress .progress-fill').css('width', '100%' );
						}
					});
					
				}
			}
		}).fail(function() { alert('Случилась непредвиденая ошибка, попробуйте повторить позже'); });
	}
	
	$('#load-products').on('click', function(event) {
		event.preventDefault();
		$(this).removeClass('button-primary');

		ajaxdata.action = 'insert_posts';
		ajaxdata.update_count = Math.floor( $('#p_count').val() / AJAX_VAR.products_at_once ); // количество запросов
		ajaxdata.at_once = AJAX_VAR.products_at_once;
		start_products_update(ajaxdata);
	});

	$('#load-categories').on('click', function(event) {
		event.preventDefault();

		$(this).removeClass('button-primary');
		ajaxdata.action = 'insert_terms';
		ajaxdata.update_count = Math.floor( $('#t_count').val() / AJAX_VAR.products_at_once ); // количество запросов
		ajaxdata.at_once = AJAX_VAR.products_at_once;
		start_products_update(ajaxdata);
	});

	
});