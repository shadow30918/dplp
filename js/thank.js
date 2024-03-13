$(document).ready(function(){
  $('.btn').hover(function(){
  	var src = $(this).attr('src');
  	var pic = src.split('.');
  	$(this).attr('src',pic[0]+'_hover.'+pic[1]);
  },function(){
  	var src = $(this).attr('path');
  	$(this).attr('src',src);
  });

  $('.btn').on('mousedown',function(){
  	var src = $(this).attr('path');
  	var pic = src.split('.');
  	$(this).attr('src',pic[0]+'_active.'+pic[1]);
  });
  $('.btn').on('mouseup',function(){
  	var src = $(this).attr('path');
  	$(this).attr('src',src);
  });
});



$(function(){
	var w_width = 500;
	var w_height = 500;
	var x = (screen.width - w_width) / 2;
	var y = (screen.height - w_height) / 2;
	var xy = 'width='+w_width+',height='+w_height+',top='+y+',left='+x;

	$('.fb-share').click(function(){
		window.open("https://www.facebook.com/sharer/sharer.php?u=https://www.dprogram.com.tw/event/2017dprogram/","_blank",config="'+ xy +'");
	});

	$('.resend-btn').click(function(){
      c_no=getUrlParam('c_no');
      s_no=getUrlParam('s_no');
      t_no=getUrlParam('t_no');
      t_sn_no=getUrlParam('t_sn_no');
      token=getUrlParam('token');

      var request_sent = $.ajax({
         url:'func.php',
         type:'POST',
         data:{func:'SentMms', c_no:c_no, s_no:s_no, t_no:t_no, t_sn_no:t_sn_no, token:token},
         dataType:'json'
      });
      request_sent.done(function(data){
         var re_ok = parseInt(data.OK,10);
         var re_status = parseInt(data.status,10);

         if(re_ok == 0){
            if(re_status == 5){
               alert("己經兌換了, 無法再重覆發送簡訊");
            }
				if(re_status == 6){
               alert("距離下一次簡訊發送, 簡訊間隔時間未到");
            }
         }

         if(re_ok == 1){
            alert("簡訊已寄送");
         }
      });
	});
});


function getUrlParam(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)"); //构造一个含有目标参数的正则表达式对象
    var r = window.location.search.substr(1).match(reg); //匹配目标参数
    if (r !== null) return unescape(r[2]);
    return null; //返回参数值
}
