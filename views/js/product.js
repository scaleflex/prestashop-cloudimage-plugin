/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 * You must not modify, adapt or create derivative works of this source code
 *  @author    Scaleflex
 *  @copyright Since 2022 Scaleflex
 *  @license   LICENSE.txt
 */

$(document).ready(function () {
    const body = $('body');

    $('.js-thumb').on('click', function (){
        const imageSrc = $(this).data("image-large-src");
        const coverImage = $('.js-qv-product-cover');
        coverImage.removeClass('ci-image ci-image-loaded lazyloaded')
        coverImage.attr('ci-src', imageSrc);
        reloadCiImage();
    })

    $('.js-modal-thumb').on('click', function (){
        const imageSrc = $(this).data("image-large-src");
        const coverModalImage = $('.js-modal-product-cover');
        coverModalImage.removeClass('ci-image ci-image-loaded lazyloaded')
        coverModalImage.attr('ci-src', imageSrc);
        reloadCiImage();
    })

    // Reload After Ajax Call
    function reloadCiImage() {
        if (window.ciResponsive !== undefined) {
            window.ciResponsive.process();
        }
    }

    function triggerCheckUpdatedAfterAjax(element) {
        let isAjaxFinished = false;
        let countChecked = 0; // Prevent loop infinity
        function checkDOMRendered() {
            countChecked++;
            if (body.find(element).length) {
                isAjaxFinished = true;
                reloadCiImage();
            } else {
                if (countChecked < 1000) { // Prevent loop infinity
                    setTimeout(checkDOMRendered, 100);
                }
            }
        }

        checkDOMRendered();
    }

    // Prestashop JS Event
    if (typeof prestashop !== 'undefined') {
        prestashop.on(
            'updatedProduct',
            function (event) {
                reloadCiImage();
            }
        );

        prestashop.on(
            'updateProductList',
            function (event) {
                reloadCiImage();
            }
        );

        prestashop.on(
            'updateCart',
            function (event) {
                triggerCheckUpdatedAfterAjax('#blockcart-modal')
            }
        );

        prestashop.on(
            'clickQuickView',
            function (event) {
                triggerCheckUpdatedAfterAjax('.quickview');
            }
        );
    }
})
