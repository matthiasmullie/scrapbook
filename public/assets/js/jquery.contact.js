jQuery(document).ready(function(){

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// Comment or uncomment the result you want.
	// Currently, shake on error is enabled.
	// When a field is left blank, jQuery will shake the form

	/* Begin config */

	//	var shake = "Yes";
		var shake = "No";

	/* End config */


//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////// Do not touch below /////////////////////////////////////////

	$('#message').hide();

	// Add validation parts
	$('#contact input[type=text], #contact input[type=email], #contact select, #contact textarea').each(function(){
		$(this).after('<mark class="validate"></mark>');
	});

	// Validate as you type
	$('#comments').focusout(function() {
		if (!$(this).val())
			$(this).addClass('error').parent().find('mark').removeClass('valid').addClass('error');
		else
			$(this).removeClass('error').parent().find('mark').removeClass('error').addClass('valid');
	});

	$('#email').focusout(function() {
		if (!$(this).val() || !isEmail($(this).val()))
			$(this).addClass('error').parent().find('mark').removeClass('valid').addClass('error');
		else
			$(this).removeClass('error').parent().find('mark').removeClass('error').addClass('valid');
	});

	$('#submit').click(function() {
		$("#message").slideUp(200,function() {
			$('#message').hide();

			// Kick in Validation
			$('#email').triggerHandler("focusout");
			$('#comments').triggerHandler("focusout");

		});
	});

	$('#contactform').submit(function(){

		if ($('#contact mark.error').size()>0) {
			if(shake == "Yes") {
			$('#contact').effect('shake', { times:2 }, 75);
			}
			return false;
		}

		var action = $(this).attr('action');

 		$('#submit')
			.after('<img src="images/ajax-loader.gif" class="loader" />')
			.attr('disabled','disabled');

		$.post(action, $('#contactform').serialize(),
			function(data){
				$('#message').html( data );
				$('#message').slideDown();
				$('#contactform img.loader').fadeOut('slow',function(){$(this).remove()});
				$('#contactform #submit').removeAttr('disabled');
				if(data.match('success') != null) $('#contactform #submit').attr("disabled", true);
			}
		);

		return false;

	});

	function isEmail(emailAddress) {

		var pattern = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);

		return pattern.test(emailAddress);
	}

	function isNumeric(input) {
    	return (input - 0) == input && input.length > 0;
	}

});
$('#contactform').submit(function(){
        if ($('#contactform').valid()){
            $.ajax({
                type: "POST",
                url: "php/contact.php",
                data: $(this).serialize(),
                success: function(msg) {
                    if (msg == 'SEND') {
                        response = '<div class="alert success">'+ $success +'</div>';
                    }
                    else {
                        response = '<div class="alert error">'+ msg +'</div>';
                    }
                    $(".error,.success").remove();
                    $('#contactform').prepend(response);
                }
             });
            return false;
        }
        return false;
    });
