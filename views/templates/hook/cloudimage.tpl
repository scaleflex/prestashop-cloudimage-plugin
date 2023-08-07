{**
 * Copyright since 2022 Scaleflex
 *
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * If you accept the licence agreement.
 *
 * @author    Scaleflex
 * @copyright Since 2022 Scaleflex
 * @license   LICENSE.txt
 *}

{if $ciActivation}
    {if $ciLazyLoading}
        <script>
            window.lazySizesConfig = window.lazySizesConfig || {};
            window.lazySizesConfig.init = false;
        </script>
        <script src="https://cdn.scaleflex.it/filerobot/js-cloudimage-responsive/lazysizes.min.js"></script>
    {/if}
    <script src="https://scaleflex.cloudimg.io/v7/plugins/js-cloudimage-responsive/4.9.1/plain/js-cloudimage-responsive.min.js?func=proxy"></script>
    <script>
        window.ciResponsive = new window.CIResponsive({
            {if $ciLazyLoading}
            lazyLoading: true,
            {/if }
            {if $ciUseOriginalUrl and $ciCustomLibraryOption eq ''}
            doNotReplaceURL: true,
            {else}
            doNotReplaceURL: false,
            {/if}
            {if $ciCName neq '' }
            customDomain: true,
            domain: '{$ciCName}',
            {/if}
            {if $ciMaximumPixelRatio eq 1}
            devicePixelRatioList: [1],
            {/if}
            {if $ciMaximumPixelRatio eq 1.5}
            devicePixelRatioList: [1, 1.5],
            {/if}
            {if $ciMaximumPixelRatio eq 2}
            devicePixelRatioList: [1, 1.5, 2],
            {/if}
            {if $ciRemoveV7}
            apiVersion: null,
            {/if}
            params: '{if $ciOrgIfSml}org_if_sml=1&{/if}{$ciCustomLibraryOption|escape:'htmlall':'UTF-8'}',
            {if $ciCustomJSFunction ne ''}
            processQueryString: function (props) {$ciCustomJSFunction|escape:'htmlall':'UTF-8'},
            {/if}
            token: '{$ciToken|escape:'htmlall':'UTF-8'}'
        })

        {if $ciLazyLoading}
        window.lazySizes.init();
        {/if}
    </script>
    {*Remove this line if unessesary*}
    <style>
        body#checkout .container {
            min-height: inherit;
        }
    </style>
{/if}
