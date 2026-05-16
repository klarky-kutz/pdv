$(document).ready(function() {
	"use strict";
	window.toastr.options = {
	  "closeButton": true,
	  "debug": false,
	  "newestOnTop": false,
	  "progressBar": false,
	  "positionClass": "toast-top-center",
	  "preventDuplicates": true,
	  "onclick": null,
	  "showDuration": "300",
	  "hideDuration": "1000",
	  "timeOut": "5000",
	  "extendedTimeOut": "1000",
	  "showEasing": "swing",
	  "hideEasing": "linear",
	  "showMethod": "fadeIn",
	  "hideMethod": "fadeOut"
	};

	$("select").select2({
	  tags: false,
	  "width": "100%",
	  "height": "50px",
	});

	$(".ajaxcall").on("click", function(e) {
		e.stopImmediatePropagation();
		e.stopPropagation();
        e.preventDefault();

        var $funcName;
        $(".field-error").remove();
        $(".loader-status").hide();
		var $btn = $(this);
		var $formID = $btn.data("form");
		var $form = $("#"+$formID);
		var $data = $form.serialize();
		var $actionUrl = $form.data("action");

		$.ajax({
			url: $actionUrl,
			type: "POST",
			dataType: 'json',
			data:  $data,
			beforeSend: function() {
				$("body").addClass("overlay-loader");
				$(".btn").attr("disabled", "disabled");
				$(".form-control").attr("disabled", "disabled");
				$btn.button("loading");
				$funcName = $formID+"BeforesendCallback";
				if (eval("typeof "+$funcName) == "function") {
					eval($formID+"BeforeSendCallbackCallback()");
				}
			},
			complete: function() {
				$funcName = $formID+"CompleteCallback";
				if (eval("typeof "+$funcName) == "function") {
					eval($formID+"CompleteCallback()");
				}
			},
			success: function(res) {
				console.log(res);
				if (res.redirect) {
					window.location = res.redirect;
				} else {
					if (!res["next"]) {
						$("body").removeClass("overlay-loader");
						$(".btn").removeAttr("disabled");
						$(".form-control").removeAttr("disabled", "disabled");
						$btn.button("reset");
						$.each(res, function (index, value) {
							$("#"+index).after("<span class='text-red field-error'><i>"+value+"<i></span>");
							toastr.error(value);
						});
					} else {
						$funcName = $formID+"SuccessCallback";
						if (eval("typeof "+$funcName) == "function") {
							eval($formID+"SuccessCallback(res)");
						}
					}
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				$funcName = $formID+"ErrorCallback";
				if (eval("typeof "+$funcName) == "function") {
					eval($formID+"ErrorCallback()");
				}
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
				$("body").removeClass("overlay-loader");
				$(".btn").removeAttr("disabled");
				$(".form-control").removeAttr("disabled", "disabled");
				$btn.button("reset");
			}
		});

	});
});