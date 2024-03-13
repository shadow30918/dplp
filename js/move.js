$(function(){
	$('.prod-move').hover(function(){
		console.log('ttt');
		$(this).next('img').addClass('move');
	},function(){
		$(this).next('img').removeClass('move');
	})
	
})