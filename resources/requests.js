jQuery(document).ready(function($) {
	function start_products_update(ajaxdata){
		$('#ajax_action').html('Выгрузка началась!');
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: ajaxdata,
			success: function(response){
				ajaxdata.counter++;
				if(ajaxdata.counter - 1 <= ajaxdata.update_count){
					start_products_update(ajaxdata);

					var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
					$('.progress .progress-fill').css('width',  progrss + '%' );
				}
				else{
					$('.progress .progress-fill').css('width', '100%' );
					$('#ajax_action').html('Выгрузка завершена!');
				}
			}
		}).fail(function() { alert('Случилась непредвиденая ошибка, попробуйте повторить позже'); });
	}

	var ajaxdata = {};
	ajaxdata.nonce = request_settings.nonce;
	ajaxdata.at_once = request_settings.products_at_once;

	$('#load-products.button-primary').on('click', function(event) {
		event.preventDefault();
		$(this).removeClass('button-primary');

		ajaxdata.counter = 1;
		ajaxdata.action = 'exchange_insert_posts';
		ajaxdata.update_count = Math.ceil( request_settings.products_count / ajaxdata.at_once ); // количество запросов

		start_products_update(ajaxdata);
	});

	$('#load-categories.button-primary').on('click', function(event) {
		event.preventDefault();
		$(this).removeClass('button-primary');

		ajaxdata.counter = 1;
		ajaxdata.action = 'exchange_insert_terms';
		ajaxdata.update_count = Math.ceil( request_settings.cats_count / ajaxdata.at_once ); // количество запросов

		start_products_update(ajaxdata);
	});
});
