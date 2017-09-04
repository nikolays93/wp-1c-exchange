jQuery(document).ready(function($) {
	var ajaxdata = {};
	ajaxdata.counter = 1;
	ajaxdata.nonce = request_settings.nonce;

	function start_products_update(ajaxdata){
		$('#ajax_action').html('Выгрузка началась!');

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: ajaxdata,
			success: function(response){
				ajaxdata.counter++;
				if(ajaxdata.counter <= ajaxdata.update_count){
					start_products_update(ajaxdata);

					var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
					$('.progress .progress-fill').css('width',  progrss + '%' );
				}
				else{
					$('#ajax_action').html('Выгрузка завершена!');
				}
			}
		}).fail(function() { alert('Случилась непредвиденая ошибка, попробуйте повторить позже'); });
	}

	$('#load-products').on('click', function(event) {
		event.preventDefault();
		$(this).removeClass('button-primary');

		ajaxdata.action = 'exchange_insert_posts';
		ajaxdata.at_once = request_settings.products_at_once;
		ajaxdata.update_count = Math.ceil( request_settings.products_count / ajaxdata.at_once ); // количество запросов

		start_products_update(ajaxdata);
	});

	$('#load-categories').on('click', function(event) {
		event.preventDefault();

		$(this).removeClass('button-primary');
		ajaxdata.action = 'exchange_insert_terms';
		ajaxdata.at_once = request_settings.products_at_once;
		ajaxdata.update_count = Math.ceil( request_settings.cats_count / ajaxdata.at_once ); // количество запросов

		start_products_update(ajaxdata);
	});

	$('#product_count').html( request_settings.products_count );
	$('#cat_count').html( request_settings.cats_count );
});
