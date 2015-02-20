/*-----------------------------------------------------------------------------------
/* Styles Switcher
-----------------------------------------------------------------------------------*/

window.console = window.console || (function(){
	var c = {}; c.log = c.warn = c.debug = c.info = c.error = c.time = c.dir = c.profile = c.clear = c.exception = c.trace = c.assert = function(){};
	return c;
})();


jQuery(document).ready(function($) {
	
		// Color Changer
		$(".grayblue" ).click(function(){
			$("#colors" ).attr("href", "css/colors/grayblue.css" );
			return false;
		});
		$(".orange" ).click(function(){
			$("#colors" ).attr("href", "css/colors/orange.css" );
			return false;
		});
		$(".green" ).click(function(){
			$("#colors" ).attr("href", "css/colors/green.css" );
			return false;
		});
		$(".blue" ).click(function(){
			$("#colors" ).attr("href", "css/colors/blue.css" );
			return false;
		});	
		$(".yellow" ).click(function(){
			$("#colors" ).attr("href", "css/colors/yellow.css" );
			return false;
		});
		$(".navy" ).click(function(){
			$("#colors" ).attr("href", "css/colors/navy.css" );
			return false;
		});
		$(".beige" ).click(function(){
			$("#colors" ).attr("href", "css/colors/beige.css" );
			return false;
		});
		$(".red" ).click(function(){
			$("#colors" ).attr("href", "css/colors/red.css" );
			return false;
		});
		$(".cyan" ).click(function(){
			$("#colors" ).attr("href", "css/colors/cyan.css" );
			return false;
		});	
		$(".pink" ).click(function(){
			$("#colors" ).attr("href", "css/colors/pink.css" );
			return false;
		});
		$(".brown" ).click(function(){
			$("#colors" ).attr("href", "css/colors/brown.css" );
			return false;
		});
		$(".olive" ).click(function(){
			$("#colors" ).attr("href", "css/colors/olive.css" );
			return false;
		});
		$(".purple" ).click(function(){
			$("#colors" ).attr("href", "css/colors/purple.css" );
			return false;
		});
		$(".gray" ).click(function(){
			$("#colors" ).attr("href", "css/colors/gray.css" );
			return false;
		});
		
		

		$("#style-switcher h2 a").click(function(e){
			e.preventDefault();
			var div = $("#style-switcher");
			console.log(div.css("left"));
			if (div.css("left") === "-206px") {
				$("#style-switcher").animate({
					left: "0px"
				}); 
			} else {
				$("#style-switcher").animate({
					left: "-206px"
				});
			}
		});

		// Header Style Switcher
	   $("#header-style").change(function(e){
			if( $(this).val() == 1){
				$(".topbar").addClass("white"); 
				$(".topbar").removeClass("light");
				$(".topbar").removeClass("color");
				
			} else if( $(this).val() == 2){
				$(".topbar").addClass("light");
				$(".topbar").removeClass("color");
				$(".topbar").removeClass("white"); 
				
			} else if( $(this).val() == 3){
				$(".topbar").addClass("color");
				$(".topbar").removeClass("light");
				$(".topbar").removeClass("white"); 
				
			} else{
				
				$(".topbar").removeClass("light"); 
				$(".topbar").removeClass("color");
				$(".topbar").removeClass("white"); 
				
			}
		});

		//Layout Switcher
	   $("#layout-style").change(function(e){
			if( $(this).val() == 1){
				$("body").removeClass("boxed"), 
				$(window).resize();
				stickyheader = !stickyheader;
			} else{
				$("body").addClass("boxed"),
				$(window).resize();
				stickyheader = !stickyheader;
			}
		});

		$("#layout-switcher").on('change', function() {
			$('#layout').attr('href', $(this).val() + '.css');
		});

		$(".colors li a").click(function(e){
			e.preventDefault();
			$(this).parent().parent().find("a").removeClass("active");
			$(this).addClass("active");
		});
		
		$('.bg li a').click(function() {
			var current = $('#style-switcher select[id=layout-style]').find('option:selected').val();
			if(current == '2') {
				var bg = $(this).css("backgroundImage");
				$("body").css("backgroundImage",bg);
			} else {
				alert('Please select boxed layout');
			}
		});	

		$("#reset a").click(function(e){
			var bg = $(this).css("backgroundImage");
			$("body").css("backgroundImage","url(images/bg/noise.png)");
			$("#navigation" ).removeClass("style-2")
		});
			

	});