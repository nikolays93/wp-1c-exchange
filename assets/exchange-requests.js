jQuery(document).ready(function($) {
    var progress_fill = 0;
    var error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже';
    var $progress = $('.progress .progress-fill');
    var part = 0,
        progress = 0;
    var $ajax_request;

    var pParts = Math.ceil( resourses.import_size / resourses.offset );

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

    function update_progress() {
        progress_fill++;
        $progress.css('width', progress_fill * 100 / (1 + pParts + pParts) + '%' );
    }

    function doExchangeTerms()
    {
        $ajax_request = $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action' : 'exchange_taxanomies',
            },
            success: function( response ) {
                update_progress();

                var response = JSON.parse( response );
                if( response.retry == 1 ) { doExchangeTerms(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop(); $('#ajax_action').html( '<span style="color: red;">' + error_msg + '</span>' );
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
                if( response.retry == 1 ) { part++; update_progress(); doExchangeProducts(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop(); $('#ajax_action').html( '<span style="color: red;">' + error_msg + '</span>' );
            }
        });
    }

    function doExchangeRelationships()
    {
        $ajax_request = $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'part' : part,
                'action' : 'exchange_relationships',
            },
            success: function( response ) {
                var response = JSON.parse( response );
                if( response.retry == 1 ) { part++; update_progress(); doExchangeRelationships(); }
                else { doExchange(); }
            },
            error: function() {
                timer.stop(); $('#ajax_action').html( '<span style="color: red;">' + error_msg + '</span>' );
            }
        });
    }

    function doExchange() {
        part = 1;

        if( progress == 0 ) {
            $('#ajax_action').html( 'Обновление категорий.' );
            doExchangeTerms();
        }
        else
        if( progress == 1 ) {
            $('#ajax_action').html( 'Обновление товаров.' );
            doExchangeProducts();
        }
        else
        if( progress == 2 ) {
            $('#ajax_action').html( 'Обновление отношений.' );
            doExchangeRelationships();
        }
        else
        if( progress == 3 ) {
            $('#ajax_action').html( 'Обновление завершено.' );
            $( '#stop-exchange' ).attr('disabled', 'true');
            $( '#exchangeit' ).attr('disabled', 'true');
            timer.stop();
        }

        progress++;
    }

    $( '#exchangeit' ).on('click', function(event) {
        event.preventDefault();
        $progress.css('width', '1%');

        timer.start();
        doExchange();
        $(this).removeClass('button-primary');
        $( '#exchangeit' ).attr('disabled', 'true');
    });

    $( '#stop-exchange' ).on('click', function(event) {
        event.preventDefault();

        timer.stop();
        error_msg = 'Импорт товаров прерван!';

        $ajax_request.abort();
        $( '#stop-exchange' ).attr('disabled', 'true');
        $( '#exchangeit' ).attr('disabled', 'true');
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