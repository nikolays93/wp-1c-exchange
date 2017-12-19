jQuery(document).ready(function($) {
    var part = 1;
    var progress = 0;
    var $progress = $('.progress .progress-fill');
    var $ajax_request;
    var error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже';
    var status = 'active';
    var arrActions = [];
    var ajaxdata = {
        // nonce : request_settings.nonce,
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

    // ajaxdata.update_count = Math.ceil( request_settings.products_count / ajaxdata.at_once ); // количество запросов

    function doExchangeTerms()
    {
        $ajax_request = $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action' : 'exchange_taxanomies',
            },
            success: function( response ) {
                var response = JSON.parse( response );
                if( response.retry == 1 ) { doExchangeTerms(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop();
                alert( error_msg );
            }
        });
    }

    function doExchangeProducts()
    {
        $ajax_request = $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'part' : part,
                'action' : 'exchange_products',
            },
            success: function( response ) {
                var response = JSON.parse( response );
                if( response.retry == 1 ) { part++; doExchangeProducts(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop();
                alert( error_msg );
            }
        });
    }

    function doExchangeRelationships()
    {
        $ajax_request = $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action' : 'exchange_relationships',
            },
            success: function( response ) {
                var response = JSON.parse( response );
                if( response.retry == 1 ) { doExchangeRelationships(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop();
                alert( error_msg );
            }
        });
    }

    function doExchange() {
        if( progress == 0 ) {
            $progress.css('width', '1%');
            $('#ajax_action').html( 'Обновление категорий.' );
            doExchangeTerms();
        }
        else
        if( progress == 1 ) {
            $progress.css('width', '25%');
            $('#ajax_action').html( 'Обновление товаров.' );
            doExchangeProducts();
        }
        else
        if( progress == 2 ) {
            $progress.css('width', '75%');
            $('#ajax_action').html( 'Обновление отношений.' );
            doExchangeRelationships();
        }
        else
        if( progress == 3 ) {
            $progress.css('width', '100%');
            $('#ajax_action').html( 'Обновление завершено.' );
            $( '#stop-exchange' ).attr('disabled', 'true');
            $( '#exchangeit' ).attr('disabled', 'true');
            timer.stop();
        }

        progress++;
    }

    $( '#exchangeit' ).on('click', function(event) {
        event.preventDefault();

        var event_action = $(this).attr('data-action');
        if( event_action == 'start' ) {
            timer.start();
            status = 'active';

            doExchange();

            $(this).removeClass('button-primary');
            $(this).text( 'Пауза' );
            $(this).attr('data-action', 'pause');
        }
        else if ( event_action == 'pause' ) {
            status = 'pause';
            progress--;

            $(this).addClass('button-primary');
            $(this).text( 'Продолжить' );
            $(this).attr('data-action', 'start');
        }
    });

    $( '#stop-exchange' ).on('click', function(event) {
        event.preventDefault();

        timer.stop();
        error_msg = 'Импорт товаров прерван!';

        $( '#stop-exchange' ).attr('disabled', 'true');
        $( '#exchangeit' ).attr('disabled', 'true');
        $ajax_request.abort();
    });

    // $('#load-categories.button-primary').on('click', function(event) {
    //  event.preventDefault();
    //  $(this).removeClass('button-primary');

    //  ajaxdata.update_count = 1; // количество запросов
    //  ajaxdata.action = 'exchange_insert_terms';

    //  start_products_update(ajaxdata);
    // });

});

// ajaxdata.counter++;
                // if(ajaxdata.counter - 1 < ajaxdata.update_count){
                //     var progrss = (100 / ajaxdata.update_count) * (ajaxdata.counter - 1);
                //     $('.progress .progress-fill').css('width',  progrss + '%' );

                //     if( status == 'pause' ) {
                //         timer.stop();
                //     }

                //     start_products_update( ajaxdata );
                // }
                // else{
                //     timer.stop();

                //     $('.progress .progress-fill').css('width', '100%' );
                //     $('#ajax_action').html('Выгрузка завершена!');
                // }