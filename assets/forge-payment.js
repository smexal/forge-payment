var forgePayment = {
    running : false,

    init : function() {

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

        $("#payment-overlay.delivery").each(function() {
            $(this).find("h4").unbind('click').on('click', function() {
                $("#payment-overlay.delivery").find("h4").each(function() {
                    $(this).removeClass("open");
                    $(this).next().removeClass('show');
                });
                $(this).addClass("open");
                $(this).next().addClass('show');
            });
        });
    },

    hideOverlay : function() {
        if(! $("body").find("#payment-overlay .pay-panel").hasClass('show')) {
            return;
        }
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
        $(document).trigger("hideOverlays");
        forgePayment.running = true;
        if(!$("body").hasClass("payment-panel-open")) {
            $("body").addClass("payment-panel-open");
        }
        $("body").find("#payment-overlay").remove();
        var deliveryClass = '';
        if(data.delivery == true) {
            deliveryClass = 'delivery';
        }
        var overlay = $(
            "<div class="+deliveryClass+" id='payment-overlay'><div class='pay-panel loading'>" +
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
                $(document).trigger("ajaxReload");
            }, 300);
        });
    }
};

$(document).ready(forgePayment.init);
$(document).on("ajaxReload", forgePayment.init);
$(document).on("hideOverlays", forgePayment.hideOverlay);