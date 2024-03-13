$(function(){
	//**********************
	//測驗區塊滑動效果
	//**********************
	$('.exper_step1 .exper-btn').click(function(){
		$('.exper-overlay').fadeToggle();
		$('.exper .slideCon:first-child').addClass('slide-out-left');
		$('.exper .slideCon:nth-child(2)').removeClass('slide-out-left').addClass('slide-in');
		
	});
	
	
	$( '.exper_step2 .exper-btn' ).on('click',function(){
		
		$('.overlay').show();
		$('.exper .exper_loading').fadeIn().delay(3000).fadeOut(function(){
			$('.overlay').hide();
			$('.exper .slideCon:nth-child(2)').addClass('slide-out-left');
			$('.exper .slideCon:nth-child(3)').removeClass('slide-out-right').addClass('slide-in');			
		})

	})
	
	$( '.exper_result .exper-retest' ).on('click',function(){
		
		$('.exper .slideCon:nth-child(3)').addClass('slide-out-right');
		$('.exper .slideCon:nth-child(1)').removeClass('slide-out-left').toggleClass('slide-in');
		$('.exper-overlay').fadeToggle();
	})	
	
	
	//**********************
	//立即體驗錨點
	//**********************	
	$('.exper-submit').click(function(){
		$('html,body').animate({
			scrollTop:$('.info').offset().top
		},800);
	});
	
/*
	$("img.exper-retest").hover(function(){

		$(this).attr("src","img/result/exper_retest_hover.png");
	},function(){
		$(this).attr("src","img/result/exper_retest.png");
	});
*/
	
	


//--------------------------------------------------------------

	//**********************
	//調色盤畫布
	//**********************


	$(window).resize(setCanvas);
	
	var color = new Array();	
	
	// var canvas01 = drawRect('canvas01',paletteW,paletteH);
	var type = new Array('br707','br505','be303','rd606','or303');

	color = $("#canvas01").on("click",function(evt){

		var canvas = $(this).get(0);
		var ctx = canvas.getContext('2d');

		//  get mouse coordinates from event parameter
		var mouseX = parseInt(evt.offsetX);
		var mouseY = parseInt(evt.offsetY);
		
		//  get imageData object from canvas
		var imagedata = ctx.getImageData(0, 0, canvas.width, canvas.height);

		//  get pixelArray from imagedata object
		var data = imagedata.data;  

		//  calculate offset into array for pixel at mouseX/mouseY
		var i = ((mouseY * canvas.width) + mouseX) * 4;


		var maxSlash = getSlash( canvas.width,0,canvas.width,canvas.height );

		var mouseSlash = getSlash( mouseX,mouseY,canvas.width,canvas.height );
		var eyeType = Math.ceil(mouseSlash/(maxSlash/5));

		var pick = eyeType-1;
		
		$( '.exper_result .exper-word>img:first-child' ).attr("src","img/result/"+type[pick]+"_word.png");
		$( '.exper_result .exper-word>img:nth-child(2)' ).attr("src","img/result/m_"+type[pick]+"_word.png");
		$( '.exper_result .exper-type>img' ).attr("src","img/result/"+type[pick]+"_type.png");
		$( '.exper_result .exper-product>img' ).attr("src","img/result/"+type[pick]+"_product.png");
		

		
		//  get RGBA values
		color['r'] =  data[i];
		color['g'] =  data[i+1];
		color['b'] =  data[i+2];
		color['a'] =  data[i+3];


		$(".canvas_color").css('background',"rgb( "+color['r']+" , "+color['g']+" , "+color['b']+" )");

	})

	
	

	
	// $(window).load(alignCenter);
	$(window).resize(function(){
		initialColor();
		alignCenter();
	})	
	
	setTimeout(function(){
		initialColor();		
		setCanvas();
		alignCenter();	
		// console.log('done')
	},3000)

})


$('.exper').imagesLoaded()
	// .progress( function( instance, image ) {
		// var result = image.isLoaded ? 'loaded' : 'broken';
		// console.log( 'image is ' + result + ' for ' + image.img.src );
	// })
	.done( function( instance ) {
		
		initialColor();	
		setCanvas();		
		alignCenter();

  });
    

	//**********************
	//瞳色墊底符合圖片尺寸
	//**********************	  
	function initialColor(){
		var picW = $( '.exper_step2 img.eye_face' ).width();
		var picH = $( '.exper_step2 img.eye_face' ).height();
		$(".exper_step2 .canvas_color").css({
			height:picH,
			width:picW
		});

		var picW = $( '.exper_result img.eye_face' ).width();
		var picH = $( '.exper_result img.eye_face' ).height();


		$(".exper_result .canvas_color").css({
			height:picH,
			width:picW
		});
		// console.log(picW);
		
	}
  
  
	//**********************
	//解決調色盤尺寸問題
	//**********************	  
  	function setCanvas(){
		if( $(window).width() <= 450 ){
	
			paletteW = 140;
			paletteH = 140;
			source = 'img/exper_palette_140.png';
			$( '.exper_step2 img.eye_face' ).css("width","140");
			
		}else if( $(window).width() <= 706 ){

			paletteW = 200;
			paletteH = 200;		
			source = 'img/exper_palette_200.png';		
			$( '.exper_step2 img.eye_face' ).css("width","200");


			
		}else if( $(window).width() <= 1000 ){

			paletteW = 300;
			paletteH = 300;		
			source = 'img/exper_palette_300.png';
			$( '.exper_step2 img.eye_face' ).css("width","300");

			
		}else if( $(window).width() <= 1054 ){
			
			paletteW = 400;
			paletteH = 400;		
			source = 'img/exper_palette_400.png';
			$( '.exper_step2 img.eye_face' ).css("width","400");
			
		}else{			
			
			paletteW = 430;
			paletteH = 430;		
			source = 'img/exper_palette.png';
			$( '.exper_step2 img.eye_face' ).css("width","430");
		}
		
		$('#canvas01').attr('width',paletteW);
		$('#canvas01').attr('height',paletteH);
		var canvas01 = draw('canvas01',source,paletteW,paletteH);
		// console.log('canvas');
	}

	
	
	
	//**********************
	//需要強制置中的東西
	//**********************
	function alignCenter(){
		var width = $('.exper-color').width();
		var palette = $('#canvas01').width();
		var eye = $('img.eye_face').width();
		var stuff = ( width - palette - eye )/2;
		$('.exper-color').css({
			paddingLeft: stuff
		})
		
		var winW = $(window).width();
		var winH = $(window).height();
		var loadingW = $('.exper_loading').width();
		var loadingH = $('.exper_loading').height();

		var x = ( winW - loadingW )/2 ;
		var y = ( winH - loadingH )/2;
		
		$( '.exper_loading' ).css({
			'left':x,
			'top':y
			
		})

	}

	//**********************
	//漸層調色盤繪製(未使用)
	//**********************	
	function drawRect(id,width,height) {
		var canvas = document.getElementById(id);
		var ctx = canvas.getContext('2d');

		// Create gradients
		var lingrad = ctx.createLinearGradient(0,height,width,0);
		lingrad.addColorStop(0, '#200f04');
		lingrad.addColorStop(1, '#976200');

		// assign gradients to fill and stroke styles
		ctx.fillStyle = lingrad;

		// draw shapes
		ctx.fillRect(0, 0, canvas.width, canvas.height)

	}
	
	//**********************
	//調色盤繪製
	//**********************	
	function draw( id,source,width,height,color ){
		var canvas = document.getElementById(id);
		var ctx = canvas.getContext('2d');
		var img = new Image();   // Create new img element
		img.addEventListener("load", function() {
			ctx.drawImage( img,0,0,width,height );

			if( typeof(color) == 'object' ){
			
				var imgData = ctx.getImageData(0, 0, width, height);
				var length = width * height; 
				
				   for (var i = 0; i < length * 4; i += 4) {

						imgData.data[i] = color['r'];
						imgData.data[i+1] = color['g'];
						imgData.data[i+2] = color['b'];

					}
				
				ctx.putImageData(imgData, 0, 0);
				
			}
		 
		}, false);
		img.src = source; // Set source path	
		return canvas;
	}

	//**********************
	//原點距離計算
	//**********************
	function getSlash( x,y,width,height ){

		var y = height-y;
		
		return Math.sqrt(x*x + y*y);
	}	
	