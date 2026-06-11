$(window).on("load", function() {
	"use strict";
	var $skeleton = $("#skeleton");
  	$skeleton.fadeOut(100);	
});

$(document).ready(function() {
	"use strict";
	var $pos = $(".pos-content-wrapper");
	$pos.fadeIn(300);

	var topBar = $(".main-header").outerHeight();

	var adjustLayout = function () {

		var windowHeight 	=  $(window).outerHeight();

		var searchbox 		= $("#searchbox").outerHeight();
		var totalAmountArea = $("#total-amount").outerHeight();

		var peopleArea 	= $("#people-area").outerHeight();
		var itemHead 	= $("#invoice-item-head").outerHeight();
		var totalCalc 	= $("#invoice-calculation").outerHeight();
		var payButton 	= $("#pay-button").outerHeight();
		$("#item-list").css({height:windowHeight-(searchbox+totalAmountArea+topBar)});
		$("#invoice-item-list").css({height: windowHeight-(peopleArea+itemHead +totalCalc+payButton+topBar)+4});
	};

	$(window).resize(function () {
		adjustLayout();
	});

	$("#item-list, #invoice-item-list, #customer-dropdown").perfectScrollbar();

	// Adjust POS Layout
	adjustLayout();

	// Show live clock at topbar
	window.liveDateTime('live_datetime');

	$("#minicart").on("click", function() {
		var rightPanel = $("#right-panel");

		if (rightPanel.hasClass("visible")) {
			rightPanel.removeClass("visible");
		} else {
			rightPanel.addClass("visible");
		}
	});
});