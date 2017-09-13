jQuery(document).ready(function($) {
	var $progress = $('.progress .progress-fill');
	var $ajax_request;
	var error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже';
	var status = 'active';
	var arrActions = [];
	var ajaxdata = {
		nonce : request_settings.nonce,
		counter : 1
	};

	var timer = {
		d : new Date(0, 0, 0, 0, 0, 0, 0, 0),
		timeinterval : 'not init. interval',
		del : ' : ',
		addLead : function(num)
		{
			var s = num+"";
			if (s.length < 2)
				s = "0" + s;
			return s;
		},
		stop : function()
		{
			clearInterval( this.timeinterval );
		},
		start : function()
		{
			var self = this;
			function updateClock()
			{
				self.d.setSeconds(self.d.getSeconds() + 1);

				var h = self.d.getHours(),
					m = self.d.getMinutes(),
					s = self.d.getSeconds();


				$('#timer.ex-timer').text( self.addLead(h) + self.del + self.addLead(m) + self.del + self.addLead(s) );
			}
			this.timeinterval = setInterval(updateClock, 1000);
		},
	}

	function start_products_update()
	{
		// arrActions
		return;
		if( ajaxdata.counter == 1 ) {
			timer.start();
			$('#ajax_action').html('Выгрузка началась!');
			$progress.css('width', '1%' );
		}

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

					if( status == 'pause' ) {
						timer.stop();
					}
						start_products_update(ajaxdata);
				}
				else{
					timer.stop();

					$('.progress .progress-fill').css('width', '100%' );
					$('#ajax_action').html('Выгрузка завершена!');
				}
			}
		}).fail(function() {
			timer.stop();
			alert( error_msg );
		});
	}

	$( '#exchangeit' ).on('click', function(event) {
		event.preventDefault();

		if( ! arrActions.length ) {
			var $cat = $( '#ex-actions #ex_categories' );
			if( $cat.is(':checked') ) arrActions.push('exchange_insert_terms');

			var $att = $( '#ex-actions #ex_attributes' );
			if( $att.is(':checked') ) arrActions.push('exchange_insert_atts');

			var $product = $( '#ex-actions #ex_products' );
			if( $product.is(':checked') ) arrActions.push('exchange_insert_posts');

			if( arrActions.length >= 1 ) {
				$( '#ex-actions' ).css('border', '1px solid #e5e5e5');

				$cat.attr('disabled', 'true');
				$att.attr('disabled', 'true');
				$product.attr('disabled', 'true');
			}
			else {
				alert( 'Укажите стадии импорта' );
				$( '#ex-actions' ).css('border', '1px solid red');
				return false;
			}
		}

		var event_action = $(this).attr('data-action');
		if( event_action == 'start' ) {
			status = 'active';
			// ajaxdata.update_count = Math.ceil( request_settings.products_count / ajaxdata.at_once ); // количество запросов

			start_products_update();
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

	$( '#stop-exchange' ).on('click', function(event) {
		event.preventDefault();

		error_msg = 'Импорт товаров прерван!';
		$ajax_request.abort();

		$( this ).attr('disabled', 'true');
		$( '#exchangeit' ).attr('disabled', 'true');
	});

	// $('#load-categories.button-primary').on('click', function(event) {
	// 	event.preventDefault();
	// 	$(this).removeClass('button-primary');

	// 	ajaxdata.update_count = 1; // количество запросов
	// 	ajaxdata.action = 'exchange_insert_terms';

	// 	start_products_update(ajaxdata);
	// });
});
