(function(){

    var _onSendProcess = false;


    $('.resend-btn').click( goReSendSms );


    function goReSendSms(){

        if( _onSendProcess == true ) return;
        _onSendProcess = true;

        ajax_reSendSms();
    }


    function ajax_reSendSms(){

        var jqxhr = $.ajax({
            url   : '../../gettrial/rest/reSendSms',
            method: 'POST',
            data  : {}
        }).done(function( data, textStatus, jqXHR ) {

            var eventData = JSON.parse(data);

            if( eventData.success == false ){

                if( eventData.message == "NOT_REACH_15MIN" ){
                    alert('還沒有滿15分鐘喔');
                    return;
                }

                alert('發生錯誤');
                return;
            }

            alert('簡訊已重新寄出');
            return;
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            alert( "連線發生問題" );
        })
        .always(function() {
            console.log( "always" );
            _onSendProcess = false;
        });
    }


})();