
function miniStarsWidget(){

$(".ekomi-miniStars").each(function() { 

        var productId = $(this).data('product-id');
        var storehash = $(this).data('storehash');
         
        var item;
        item =this;

        $.ajax({
            type: "GET",
            url: 'https://plugindev.coeus-solutions.de/ekomi-prc-bigcommerce-dev/api/v1/miniStarsWidget?storeHash='+storehash+'&productId='+productId,
            data: null,
            dataType : 'json',
            cache: false,
            success: function (data) {
              $(item).html(data);
            }
        });
});
}

function reviewsContainerWidget(){
        var storehash = $('.ekomi-prc').data('storehash');
        var productId = $('.ekomi-prc').data('product-id');

        $.ajax({
            type: "GET",
             url: 'https://plugindev.coeus-solutions.de/ekomi-prc-bigcommerce-dev/api/v1/reviewsContainerWidget?storeHash='+storehash+'&productId='+productId,
            data: null,
            dataType : 'json',
            cache: false,
            success: function (data) {
              $('.ekomi-prc').html(data);
            }
        });
}

miniStarsWidget();
reviewsContainerWidget();