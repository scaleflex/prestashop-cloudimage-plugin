/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Scaleflex
 * @copyright Since 2022 Scaleflex
 * @license   LICENSE.txt
 */

document.addEventListener('DOMContentLoaded', function () {
    // Handle thumbnail clicks on product page
    document.querySelectorAll('.js-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var imageSrc = this.getAttribute('data-image-large-src');
            var coverImage = document.querySelector('.js-qv-product-cover');
            if (coverImage) {
                coverImage.classList.remove('ci-image', 'ci-image-loaded', 'lazyloaded');
                coverImage.setAttribute('ci-src', imageSrc);
                reloadCiImage();
            }
        });
    });

    // Handle modal thumbnail clicks
    document.querySelectorAll('.js-modal-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var imageSrc = this.getAttribute('data-image-large-src');
            var coverModalImage = document.querySelector('.js-modal-product-cover');
            if (coverModalImage) {
                coverModalImage.classList.remove('ci-image', 'ci-image-loaded', 'lazyloaded');
                coverModalImage.setAttribute('ci-src', imageSrc);
                reloadCiImage();
            }
        });
    });

    // Reload after Ajax call
    function reloadCiImage() {
        if (window.ciResponsive !== undefined) {
            window.ciResponsive.process();
        }
    }

    function triggerCheckUpdatedAfterAjax(selector) {
        var isAjaxFinished = false;
        var countChecked = 0;

        function checkDOMRendered() {
            countChecked++;
            if (document.querySelector(selector)) {
                isAjaxFinished = true;
                reloadCiImage();
            } else {
                if (countChecked < 1000) {
                    setTimeout(checkDOMRendered, 100);
                }
            }
        }

        checkDOMRendered();
    }

    // PrestaShop JS Events
    if (typeof prestashop !== 'undefined') {
        prestashop.on('updatedProduct', function (event) {
            reloadCiImage();
        });

        prestashop.on('updateProductList', function (event) {
            reloadCiImage();
        });

        prestashop.on('updateCart', function (event) {
            triggerCheckUpdatedAfterAjax('#blockcart-modal');
        });

        prestashop.on('clickQuickView', function (event) {
            triggerCheckUpdatedAfterAjax('.quickview');
        });
    }
});
