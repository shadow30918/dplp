(function(){

    var _ticket;

    var url   = window.location.href;
    var regex = /\i=(\d+)\&s=(\d{4,})$/g;

    var event = "dxdsavesensitive";
    var id, security;

    var m = url.match( regex );

    if( m == null){
        alert("系統發生錯誤，請重新由簡訊中點選兌換網址");
        return;
    }

    var params = m[0].split("&");

    if( params.length != 2){
        alert("系統發生錯誤，請重新由簡訊中點選兌換網址");
        return;
    }

    for( var i in params ){
        var param_item = params[i].split("=");

        if( param_item[0] == 'i' ) id = param_item[1];
        if( param_item[0] == 's' ) security = param_item[1];
    }

    if( id == null || security == null ){
        alert("系統發生錯誤，請重新由簡訊中點選兌換網址");
        return;
    }


    // 讀取資訊
    goReceive();


    // 抓取資料
    function goReceive(){

        var jqxhr = $.ajax({
            url   : '../../gettrial/rest/getTicketData?event=dxdsavesensitive',
            method: 'POST',
            data  : {
                id       : id,
                security : security
            }
        }).done(function( data, textStatus, jqXHR ) {

            var ticketData = JSON.parse(data);

            if( ticketData.success == false ){

                if( ticketData.message == "EVENT_DOES_NOT_EXISTS" ){
                    alert('活動代號錯誤');
                    return;
                }

                if( ticketData.message == "EVENT_EXPIRED" ){
                    alert('活動已經結束');
                    return;
                }

                if( ticketData.message == "TICKED_ALREADY_RECEIVED" ){

                    _ticket = ticketData.data.ticket;
                    $('.change-name-title  .val').text( _ticket.name );
                    $('.change-phone-title .val').text( _ticket.phone );
                    $('.change-main-title  .val').text( _ticket.email );
                    $('.change-shop-title  .val').text( _ticket.store_name );

                    alert('您已經領取試用包');
                    $('.change-ok-btn').show();
                    $('.change-btn').hide();
                    return;
                }

                if( ticketData.message == "TICKED_EXPIRED" ){
                    window.location.href = "expired.html";
                    return;
                }

                alert('發生錯誤');
                return;
            }

            _ticket = ticketData.data.ticket;

            $('.change-name-title  .val').text( _ticket.name );
            $('.change-phone-title .val').text( _ticket.phone );
            $('.change-main-title  .val').text( _ticket.email );
            $('.change-shop-title  .val').text( _ticket.store_name );

            $('.change-btn').on('click', doReceive);
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            alert( "連線發生問題" );
        })
        .always(function() {
            console.log( "always" );
        });

    }


    // 點選領取
    function doReceive(){

        $('.change-loading').show();
        $('.change-ok-btn').hide();
        $('.change-btn').hide();

        var jqxhr = $.ajax({
            url   : '../../gettrial/rest/doReceive?event=dxdsavesensitive',
            method: 'POST',
            data  : {
                id       : id,
                security : security
            }
        }).done(function( data, textStatus, jqXHR ) {

            var ticketData = JSON.parse(data);

            if( ticketData.success == false ){

                $('.change-btn').show();

                alert('發生錯誤');
                return;
            }

            $('.change-btn').off('click', doReceive);

            alert('已標記為已兌換');
            $('.change-ok-btn').show();
            $('.change-btn').hide();
            return;
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {

                $('.change-btn').show();

            alert( "連線發生問題" );
        })
        .always(function() {

            $('.change-loading').hide();
            console.log( "always" );
        });

    }


})();