
function miniStarsWidget() {

    $(".ekomi-miniStars").each(function () {

        var productId = $(this).data('product-id');
        var storehash = $(this).data('storehash');
        if (storehash !== undefined && productId !== undefined) {
            var item;
            item = this;

            $.ajax({
                type: "GET",
                url: 'https://plugindev.coeus-solutions.de/ekomi-prc-bigcommerce-dev/api/v1/miniStarsWidget?storeHash=' + storehash + '&productId=' + productId,
                data: null,
                cache: false,
                success: function (data) {
                    if (data) {
                        var response = $.parseJSON(data);
                        $(item).html(response.widgetHtml);
                    }
                }
            });
        }
    });
}

function reviewsContainerWidget() {
    var storehash = $('.ekomi-prc').data('storehash');
    var productId = $('.ekomi-prc').data('product-id');

    if (storehash !== undefined && productId !== undefined) {
        $.ajax({
            type: "GET",
            url: 'https://plugindev.coeus-solutions.de/ekomi-prc-bigcommerce-dev/api/v1/reviewsContainerWidget?storeHash=' + storehash + '&productId=' + productId,
            data: null,
            cache: false,
            success: function (data) {
                if (data) {
                    var response = $.parseJSON(data);
                    $('.ekomi-prc').html(response.widgetHtml);
                    var script = response.jsonld;
                    $(".ekomi-prc").append('<script type="application/ld+json">' + script + "</script>");
                }
            }
        });
    }
}

miniStarsWidget();
reviewsContainerWidget();