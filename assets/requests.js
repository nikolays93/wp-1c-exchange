jQuery(document).ready(function($) {
	var $progress = $('.progress .progress-fill');
	var actions = {
			active: 0,
			arr: [],
			count: 0,
		};

	var $ajax_request,
		error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже',
		ajaxProps = {
			url: ajaxurl,
			type: 'POST',
			data: ajaxdata
		},
		ajaxdata = {
			// nonce : request_settings.nonce,
			counter : 1
		};

	var timer = {
		d : new Date(0, 0, 0, 0, 0, 0, 0, 0),
		timeinterval : 'not init. interval',
		del : ' : ',
		status: 'pause',
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

			if( 'pause' === status )
				this.timeinterval = setInterval(updateClock, 1000);
		},
	}

	function start_products_update()
	{
		// $('#ajax_action').html('Выгрузка началась!');
		// $('#ajax_action').html('Выгрузка завершена!');

		if( ajaxdata.counter == 1 ) {
			$progress.css('width', '1%' );
		}

		ajaxdata.action = arrActions[ activeAction ];
		$.ajax( ajaxProps )
		.done(function(response) {
			ajaxdata.counter++;
			if(ajaxdata.counter - 1 < ajaxdata.update_count) {
				var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1)
					/ arrActions.length - (activeAction + 1);
				$('.progress .progress-fill').css('width',  progrss + '%' );

				if( status == 'pause' ) timer.stop();
				else start_products_update(); // ajaxdata
			}
			else {
				timer.stop();
				$('.progress .progress-fill').css('width', '100%' );
			}
		})
		.fail(function() {
			timer.stop();
			alert( error_msg );
		})
	}

	function set_actions()
	{
		if( ! arrActions.length ) {
			var $tax = $( '#ex-actions #ex_taxes' );
			if( $tax.is(':checked') ) {
				actions.arr.push('exchange_insert_tax');
			}

			var $cat = $( '#ex-actions #ex_categories' );
			if( $cat.is(':checked') ) {
				actions.arr.push('exchange_insert_terms');
			}

			var $product = $( '#ex-actions #ex_products' );
			if( $product.is(':checked') ) {
				actions.arr.push('exchange_insert_posts');
				actions.count = Math.ceil( exchange.products_count / ajaxdata.at_once );
			}

			// var $att = $( '#ex-actions #ex_attributes' );
			// if( $att.is(':checked') ) actions.arr.push('exchange_insert_atts');

			if( arrActions.length >= 1 ) {
				$( '#ex-actions' ).css('border', '1px solid #e5e5e5');

				$tax.attr('disabled', 'true');
				$cat.attr('disabled', 'true');
				// $att.attr('disabled', 'true');
				$product.attr('disabled', 'true');
			}
			else {
				$( '#ex-actions' ).css('border', '1px solid red');
				return false;
			}
		}
	}

	$( '#exchangeit' ).on('click', function(event) {
		event.preventDefault();
		set_actions();

		if( 'start' == $(this).attr('data-action') ) {
			timer.status = 'active';

			// start_products_update();
			$(this).removeClass('button-primary');
			$(this).text( 'Пауза' );
			$(this).attr('data-action', 'pause');
			timer.start();
		}
		else {
			timer.status = 'pause';

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
});
