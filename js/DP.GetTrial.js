(function(){


    var _storeList = {};

    var _onSendProcess = false;

    var $form = {
        name  : $('.input-name'),
        phone : $('.input-phone'),
        email : $('.input-email'),
        city  : $('.input-city'),
        shop  : $('.input-shop')
    };

    $form.name .prop('disabled', true);
    $form.phone.prop('disabled', true);
    $form.email.prop('disabled', true);

    ajax_getEventData();


    // 初始化
    function init(){}


    function initForm(){

        $form.name .prop('disabled', false);
        $form.phone.prop('disabled', false);
        $form.email.prop('disabled', false);
        $form.city.empty();
        $form.shop.empty();

        // 建立城市選單
        var $opt;

        for( var i in _storeList ){

            $opt = $("<option>")
                    .val( i )
                    .text( i );

            $form.city.append( $opt );
        }

        updateShopList();
        $form.city.on('change', updateShopList );

        $('.input-button-next')   .on('click', goPreview);
        $('.info-step1 .back-btn').on('click', goBackForm);
        $('.info-step1 .ok-btn')  .on('click', doSend);
    }


    function updateShopList(){

        // 建立商店選單
        var city = $form.city.val();
        var $opt;

        $form.shop.empty();

        for( var i in _storeList[city] ){

            $opt = $("<option>")
                    .val( _storeList[city][i].code )
                    .text( _storeList[city][i].name );

            $form.shop.append( $opt );
        }

    }


    function goPreview(){

        // check format
        var input_valid       = true;
        var input_invalidList = [];

        if( $form.name.val() =="" ){ input_valid = false; input_invalidList.push("必須填寫姓名"); }
        else if( $form.name.val().length < 2 ){ input_valid = false; input_invalidList.push("姓名格式錯誤"); }

        if( $form.phone.val() == "" ){ input_valid = false; input_invalidList.push("必須填寫電話"); }
        else if( $form.phone.val().search(/^09\d{8,}$/) < 0 ){ input_valid = false; input_invalidList.push("電話格式錯誤"); }

        if( $form.email.val() == "" ){ input_valid = false; input_invalidList.push("必須填寫Email"); }
        else if( $form.email.val().search(/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/) < 0 ){ input_valid = false; input_invalidList.push("EMail格式錯誤"); }

        if( $('.input-confirm:checked').length == 0 ){ input_valid = false; input_invalidList.push("必須同意個資法條款與注意事項"); }

        if( input_valid == false ){

            alert("請確認以下欄位：\n\n - " +
                    input_invalidList.join("\n - ")
                    );

            return;
        }

        $('.info-step1 .done-check .name  .val').text( $form.name.val() );
        $('.info-step1 .done-check .phone .val').text( $form.phone.val() );
        $('.info-step1 .done-check .email .val').text( $form.email.val() );
        $('.info-step1 .done-check .shop  .val').text(
                $form.city.find('option:selected').text() + " " +
                $form.shop.find('option:selected').text() );

        $('.info-step1 .input').hide();
        $('.info-step1 .info-btn').hide();

        $('.info-step1 .done-check').show();
        $('.info-step1 .done-btn').show();

    }


    function goBackForm(){

        $('.info-step1 .done-check').hide();
        $('.info-step1 .done-btn').hide();

        $('.info-step1 .input').show();
        $('.info-step1 .info-btn').show();

    }


    function doSend(){

        if( _onSendProcess == true ) return;
        _onSendProcess = true;

        ajax_doReg();

    }




    // AJAX


    // 取得活動資訊
    function ajax_getEventData(){

        var jqxhr = $.ajax({
            url   : '../../gettrial/rest/getEventData?event=dxdsavesensitive',
            method: 'POST',

        }).done(function( data, textStatus, jqXHR ) {

            var eventData = JSON.parse(data);

            if( eventData.success == false ){

                if( eventData.message == "EVENT_DOES_NOT_EXISTS" ){
                    alert('活動代號錯誤');
                    return;
                }

                if( eventData.message == "EVENT_EXPIRED" ){
                    alert('活動已經結束');
                    return;
                }

                alert('發生錯誤');
                return;
            }

            _storeList = eventData.data.storeList;

            if( _storeList.length == 0 ){
                alert('所有贈品皆已登記兌換完畢');
                return;
            }

            initForm();
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            alert( "連線發生問題" );
        })
        .always(function() {
            console.log( "always" );
        });

    }


    // 登記
    function ajax_doReg(){

        var jqxhr = $.ajax({
            url   : '../../gettrial/rest/reg?event=dxdsavesensitive',
            method: 'POST',
            data  : {
                phone : $form.phone.val(),
                name  : $form.name .val(),
                email : $form.email.val(),
                store : $form.shop .val()
            }
        }).done(function( data, textStatus, jqXHR ) {

            var eventData = JSON.parse(data);

            if( eventData.success == false ){

                if( eventData.message == "EVENT_DOES_NOT_EXISTS" ){
                    alert('活動代號錯誤');
                    goBackForm();
                    return;
                }

                if( eventData.message == "EVENT_EXPIRED" ){
                    alert('活動已經結束');
                    goBackForm();
                    return;
                }

                if( eventData.message == "ALREADY_REG" ){
                    alert('您已申請試用，無法重複申請。');
                    goBackForm();
                    return;
                }

                if( eventData.message == "PHONE_IN_BLACK_LIST" ){
                    alert('您已完成己兌換，無法重複申請。');
                    goBackForm();
                    return;
                }

                if( eventData.message == "STORE_DOES_NOT_EXISTS" ){
                    alert('您選擇的櫃點不存在，請依正常的方式操作網頁。');
                    goBackForm();
                    return;
                }

                if( eventData.message == "OUT_OF_STOCK" ){
                    alert('您選擇的櫃點已經額滿，請嘗試其他櫃點。');
                    init();
                    return;
                }

                alert('發生錯誤');
                return;
            }

            alert('您已成功登記領取試用品。');
            
            //window.location.href = "thank.html";
            window.adGeek.conversion({callback: function () { window.location = "thank.html"; }});
            
        })
        .fail(function( jqXHR, textStatus, errorThrown ) {
            alert( "連線發生問題" );
            goBackForm();
        })
        .always(function() {
            console.log( "always" );

            _onSendProcess = false;
        });
    }


    $(window).on('load', init);


})();