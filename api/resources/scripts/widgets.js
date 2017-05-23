
function miniStarsWidget() {

    $(".ekomi-miniStars").each(function () {

        var productId = $(this).data('product-id');
        var storehash = $(this).data('storehash');

        var item;
        item = this;

        $.ajax({
            type: "GET",
            url: 'http://localhost/ekomi-prc-bigcommerce/api/v1/miniStarsWidget?storeHash=' + storehash + '&productId=' + productId,
            data: null,
            cache: false,
            success: function (data) {
                if (data) {
                    var response = $.parseJSON(data);
                    $(item).html(response.widgetHtml);
                }
            }
        });
    });
}

function reviewsContainerWidget() {
    var storehash = $('.ekomi-prc').data('storehash');
    var productId = $('.ekomi-prc').data('product-id');

    $.ajax({
        type: "GET",
        url: 'http://localhost/ekomi-prc-bigcommerce/api/v1/reviewsContainerWidget?storeHash=' + storehash + '&productId=' + productId,
        data: null,
        cache: false,
        success: function (data) {
            if (data) {
                var response = $.parseJSON(data);
                $('.ekomi-prc').html(response.widgetHtml);
            }
        }
    });
}

miniStarsWidget();
reviewsContainerWidget();