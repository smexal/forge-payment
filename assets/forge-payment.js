var forgePayment = {
    running : false,

    init : function() {
        /*$(document).on("click", function(e) {
            if($("body").hasClass("payment-panel-open") && ! forgePayment.running) {
                if(! $(e.toElement).hasClass("pay-panel")) {
                    forgePayment.hideOverlay();
                }
            }
        });*/


        $(".payment-trigger").each(function() {
            $(this).on("click", function() {
                var data = $(this).data();
                forgePayment.openOverlay(data);
            });
        });

        // bind "escape" key to close the overlay
        $(document).keyup(function(e) {
            if($("body").hasClass("payment-panel-open") && e.keyCode == 27) {
                forgePayment.hideOverlay();
            }
        });
    },

    hideOverlay : function() {
        forgePayment.running = true;
        $("body").removeClass("payment-panel-open");
        $("body").find("#payment-overlay .pay-panel").removeClass("show");
        setTimeout(function() {
            $("body").find("#payment-overlay").remove();
            forgePayment.running = false;
        }, 800);
    },

    openOverlay : function(data) {
        if(forgePayment.running) {
            return;
        }
        forgePayment.running = true;
        if(!$("body").hasClass("payment-panel-open")) {
            $("body").addClass("payment-panel-open");
        }
        $("body").find("#payment-overlay").remove();
        var overlay = $(
            "<div id='payment-overlay'><div class='pay-panel loading'>" +
                "<div class='content'></div>" +
                "<div class='close'></div>" +
            "</div></div>"
        );
        overlay.appendTo("body");
        overlay.find(".close").on("click", function() {
            forgePayment.hideOverlay();
        });
        setTimeout(function()  {
            overlay.find(".pay-panel").addClass("show");
            forgePayment.load(data, overlay.find(".pay-panel"))
        }, 100);
    }, 

    load : function(data, modal) {
        $.ajax({
            method: 'POST',
            url: data.api + 'forge-payment/modal/',
            data : data
        }).done(function(data) {
            modal.addClass("stopload");
            setTimeout(function() {
                modal.find(".content").html(data.content);
                forgePayment.running = false;
            }, 300);
        });
    }
};

$(document).ready(forgePayment.init);
$(document).on("ajaxReload", forgePayment.init);