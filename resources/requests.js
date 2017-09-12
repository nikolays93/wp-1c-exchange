jQuery(document).ready(function($) {
	var $progress = $('.progress .progress-fill');
	var $ajax_request;
	var status = 'active';

	function start_products_update(ajaxdata, action){
		return;
		$('#ajax_action').html('Выгрузка началась!');
		$progress.css('width', '1%' );

		ajaxdata.action = action;
		$ajax_request = $.ajax({
			type: 'POST',
			url: ajaxurl,
			data: ajaxdata,
			success: function(response){
				ajaxdata.counter++;
				if(ajaxdata.counter - 1 < ajaxdata.update_count){
					var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
					$('.progress .progress-fill').css('width',  progrss + '%' );

					start_products_update(ajaxdata);
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
	ajaxdata.counter = 1;

	$( '#exchangeit' ).on('click', function(event) {
		event.preventDefault();

		var event_action = $(this).attr('data-action');
		if( event_action == 'start' ) {
			status = 'active';
			ajaxdata.update_count = Math.ceil( request_settings.products_count / ajaxdata.at_once ); // количество запросов

			start_products_update( ajaxdata, 'exchange_insert_posts' );
			$(this).removeClass('button-primary');
			$(this).text( 'Пауза' );
			$(this).attr('data-action', 'pause');
		}
		else if ( event_action == 'pause' ) {
			status = 'pause';

			$(this).addClass('button-primary');
			$(this).text( 'Продолжить' );
			$(this).attr('data-action', 'start');
		}

	});

	// $('#load-categories.button-primary').on('click', function(event) {
	// 	event.preventDefault();
	// 	$(this).removeClass('button-primary');

	// 	ajaxdata.update_count = 1; // количество запросов
	// 	ajaxdata.action = 'exchange_insert_terms';

	// 	start_products_update(ajaxdata);
	// });
});
