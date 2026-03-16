<?php
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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cloudimage extends Module
{
    public function __construct()
    {
        $this->name = 'cloudimage';
        $this->version = '2.0.0';
        $this->tab = 'front_office_features';
        $this->author = 'Scaleflex';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Cloudimage by Scaleflex', [], 'Modules.Cloudimage.Admin');
        $this->description = $this->trans(
            'The easiest way to resize, compress, optimise and deliver lightning fast images to your users on any device via CDN.',
            [],
            'Modules.Cloudimage.Admin'
        );
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Cloudimage.Admin');
    }

    /**
     * Install the Module
     *
     * @return bool
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->initConfigs() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('actionDispatcher') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    /**
     * Uninstall the Module
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() && $this->deleteConfigs();
    }

    /**
     * Add JS to Front Page
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia()
    {
        if (!$this->getConfigs('ciPrerender') && $this->getConfigs('ciActivation')) {
            $this->context->controller->addJS($this->_path . 'views/js/cloudimage.js');
            $this->context->controller->addJS($this->_path . 'views/js/product.js');
        }
    }

    /**
     * Override smarty template Hook
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionDispatcher($params)
    {
        $this->context->smarty->registerFilter('output', [$this, 'changeImageSrc']);
    }

    /**
     * Check if file extension is an image
     *
     * @param string $extension
     *
     * @return bool
     */
    public function checkExtensionIsImage($extension)
    {
        $imageExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'tiff', 'webp', 'svg', 'ico', 'jfif',
            'pjpeg', 'pjp', 'avif', 'apng',
        ];

        return in_array($extension, $imageExtensions);
    }

    /**
     * Override smarty template output to rewrite image URLs
     *
     * @param string $html
     * @param Smarty_Internal_Template $template
     *
     * @return string
     */
    public function changeImageSrc($html, Smarty_Internal_Template $template)
    {
        $replaceHtml = false;

        $ciActivation = $this->getConfigs('ciActivation');
        $ignoreSvg = $this->getConfigs('ciIgnoreSvgImage');
        $ciPrerender = $this->getConfigs('ciPrerender');
        $ciToken = $this->getConfigs('ciToken');
        $ciImageQuality = $this->getConfigs('ciImageQuality');

        if (!$this->context->employee && $ciActivation) {
            $dom = new DOMDocument();
            $useErrors = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_use_internal_errors($useErrors);
            $dom->preserveWhiteSpace = false;

            $xpath = new DOMXPath($dom);

            // Update <img> tags
            $imgElements = $xpath->query('//img');
            $dataAttributes = ['data-image-medium-src', 'data-image-large-src', 'data-full-size-image-url'];
            foreach ($imgElements as $element) {
                $src = $element->getAttribute('src');

                if ($src == '') {
                    foreach ($dataAttributes as $attribute) {
                        if ($element->hasAttribute($attribute)) {
                            $src = $element->getAttribute($attribute);
                            break;
                        }
                    }
                }

                // Skip filerobot.com URLs
                if (stripos($src, 'filerobot.com') !== false) {
                    continue;
                }

                $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                if ($ignoreSvg && $extension === 'svg') {
                    continue;
                }

                if ($ciPrerender) {
                    if (stripos($src, $ciToken) === false && $src != '') {
                        $ciSrc = $this->buildUrl($src);
                        $element->setAttribute('src', $ciSrc);

                        foreach ($dataAttributes as $attribute) {
                            if ($element->hasAttribute($attribute)) {
                                $imageSrc = $element->getAttribute($attribute);
                                $ciSrc = $this->buildUrl($imageSrc);
                                $element->setAttribute($attribute, $ciSrc);
                            }
                        }
                    }
                } else {
                    if ($src != '') {
                        $quality = $ciImageQuality < 100 ? '?q=' . $ciImageQuality : '';
                        $element->setAttribute('ci-src', $src . $quality);
                        $element->removeAttribute('src');
                    }
                }

                $replaceHtml = true;
            }

            // Update <a> tags — Only for Prerender
            $anchorTags = $xpath->query('//a');
            foreach ($anchorTags as $element) {
                $src = $element->getAttribute('href');

                // Skip filerobot.com URLs
                if (stripos($src, 'filerobot.com') !== false) {
                    continue;
                }

                $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));

                if ($this->checkExtensionIsImage($extension)) {
                    if ($ignoreSvg && $extension === 'svg') {
                        continue;
                    }

                    if ($ciPrerender) {
                        if (stripos($src, $ciToken) === false) {
                            $ciSrc = $this->buildUrl($src);
                            $element->setAttribute('href', $ciSrc);
                        }
                    }
                }

                $replaceHtml = true;
            }

            // Update <source> tags
            $sourceElements = $xpath->query('//source');
            foreach ($sourceElements as $element) {
                $srcset = $element->getAttribute('srcset');

                // Skip filerobot.com URLs
                if (stripos($srcset, 'filerobot.com') !== false) {
                    continue;
                }

                $extension = strtolower(pathinfo($srcset, PATHINFO_EXTENSION));
                if ($ignoreSvg && $extension === 'svg') {
                    continue;
                }

                if ($ciPrerender && stripos($srcset, $ciToken) === false) {
                    $ciSrc = $this->buildUrl($srcset);
                    $element->setAttribute('srcset', $ciSrc);
                    $replaceHtml = true;
                }
            }

            if ($replaceHtml) {
                $html = $dom->saveHTML($dom->documentElement);
                $html = str_ireplace(['<html><body>', '</body></html>'], '', $html);

                if (!$ciPrerender) {
                    $html = preg_replace('/<img([^>]*)\ssrc=[\'"]?([^\'"\\s>]+)[\'"]?([^>]*)>/', '<img$1$3>', $html);
                }
            }
        }

        return $html;
    }

    /**
     * Add scripts to Header via displayHeader hook
     *
     * @param array $params
     *
     * @return string|void
     */
    public function hookDisplayHeader($params)
    {
        if (!$this->getConfigs('ciPrerender') && $this->getConfigs('ciActivation')) {
            $this->context->smarty->assign($this->getConfigs());

            return $this->display(__FILE__, 'views/templates/hook/cloudimage.tpl');
        }
    }

    /**
     * Get Configuration values
     *
     * @param string|null $configName
     *
     * @return mixed
     */
    public function getConfigs($configName = null)
    {
        $configs = [
            'ciActivation' => (bool) Configuration::get('CLOUDIMAGE_ACTIVATION'),
            'ciToken' => (string) Configuration::get('CLOUDIMAGE_TOKEN'),
            'ciCName' => (string) Configuration::get('CLOUDIMAGE_CNAME'),
            'ciUseOriginalUrl' => (bool) Configuration::get('CLOUDIMAGE_USE_ORIGINAL_URL'),
            'ciPrerender' => (bool) Configuration::get('CLOUDIMAGE_PRERENDER'),
            'ciAutoBaseUrl' => (bool) Configuration::get('CLOUDIMAGE_AUTO_BASE_URL'),
            'ciLazyLoading' => (bool) Configuration::get('CLOUDIMAGE_LAZY_LOADING'),
            'ciIgnoreSvgImage' => (bool) Configuration::get('CLOUDIMAGE_IGNORE_SVG_IMAGE'),
            'ciOrgIfSml' => (bool) Configuration::get('CLOUDIMAGE_ORG_IF_SML'),
            'ciImageQuality' => (int) Configuration::get('CLOUDIMAGE_IMAGE_QUALITY'),
            'ciMaximumPixelRatio' => (int) Configuration::get('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'),
            'ciCustomJSFunction' => (string) Configuration::get('CLOUDIMAGE_CUSTOM_JS_FUNCTION'),
            'ciCustomLibraryOption' => (string) Configuration::get('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'),
            'ciRemoveV7' => (bool) Configuration::get('CLOUDIMAGE_REMOVE_V7'),
        ];

        if ($configName) {
            return $configs[$configName];
        }

        return $configs;
    }

    /**
     * Admin Config Page
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitcloudimage')) {
            $submitStatus = true;
            $ciToken = Tools::getValue('CLOUDIMAGE_TOKEN');

            if (empty($ciToken)) {
                Configuration::updateValue('CLOUDIMAGE_ACTIVATION', false);
                if (Tools::getValue('CLOUDIMAGE_ACTIVATION')) {
                    $submitStatus = false;
                }
            } else {
                Configuration::updateValue('CLOUDIMAGE_ACTIVATION', Tools::getValue('CLOUDIMAGE_ACTIVATION'));
            }

            Configuration::updateValue('CLOUDIMAGE_TOKEN', $ciToken);
            Configuration::updateValue('CLOUDIMAGE_CNAME', Tools::getValue('CLOUDIMAGE_CNAME'));
            Configuration::updateValue('CLOUDIMAGE_USE_ORIGINAL_URL', Tools::getValue('CLOUDIMAGE_USE_ORIGINAL_URL'));
            Configuration::updateValue('CLOUDIMAGE_PRERENDER', Tools::getValue('CLOUDIMAGE_PRERENDER'));
            Configuration::updateValue('CLOUDIMAGE_AUTO_BASE_URL', Tools::getValue('CLOUDIMAGE_AUTO_BASE_URL'));
            Configuration::updateValue('CLOUDIMAGE_LAZY_LOADING', Tools::getValue('CLOUDIMAGE_LAZY_LOADING'));
            Configuration::updateValue('CLOUDIMAGE_IGNORE_SVG_IMAGE', Tools::getValue('CLOUDIMAGE_IGNORE_SVG_IMAGE'));
            Configuration::updateValue('CLOUDIMAGE_ORG_IF_SML', Tools::getValue('CLOUDIMAGE_ORG_IF_SML'));
            Configuration::updateValue('CLOUDIMAGE_IMAGE_QUALITY', Tools::getValue('CLOUDIMAGE_IMAGE_QUALITY'));
            Configuration::updateValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO', Tools::getValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'));
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION', Tools::getValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION'));
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION', Tools::getValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'));
            Configuration::updateValue('CLOUDIMAGE_REMOVE_V7', Tools::getValue('CLOUDIMAGE_REMOVE_V7'));

            $output .= $submitStatus
                ? $this->displayConfirmation($this->trans('Form submitted successfully', [], 'Modules.Cloudimage.Admin'))
                : $this->displayError($this->trans('You can not activate Cloudimage because your Token is empty', [], 'Modules.Cloudimage.Admin'));
        }

        return $output . $this->renderForm();
    }

    /**
     * Render the admin configuration form
     *
     * @return string
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitcloudimage';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Get the configuration form definition
     *
     * @return array
     */
    protected function getConfigForm()
    {
        $qualityOptions = [];
        for ($i = 1; $i <= 100; $i++) {
            $qualityOptions[] = [
                'id_option' => $i,
                'name' => (string) $i,
            ];
        }

        $pixelRatios = [
            ['id_option' => 1, 'name' => '1'],
            ['id_option' => 1.5, 'name' => '1.5'],
            ['id_option' => 2, 'name' => '2'],
        ];

        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Cloudimage Integration', [], 'Modules.Cloudimage.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Activation', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('Enable/Disable the Module', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_ACTIVATION',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->trans('Token', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('Cloudimage token', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_TOKEN',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->trans('CNAME', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('CNAME', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_CNAME',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Use Original URL', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('If enabled, the plugin will only add query parameters to the image source URL, avoiding double CDNization in some cases, like if you have aliases configured.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_USE_ORIGINAL_URL',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'use_original_url_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'use_original_url_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Auto Base URL Image', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('If enabled, Production with SSL only, the plugin auto adds base URL to some missing Base URL images.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_AUTO_BASE_URL',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'auto_base_url_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'auto_base_url_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Prerender', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('If enabled, the plugin will disable JS Responsive and change URL to {token}.cloudimg.io/{origin_url}', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_PRERENDER',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'prerender_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'prerender_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Lazy Loading', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('If enabled, only images close to the current viewpoint will be loaded.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_LAZY_LOADING',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'lazy_loading_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'lazy_loading_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Ignore SVG Image', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('By default, No.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_IGNORE_SVG_IMAGE',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'ignore_svg_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'ignore_svg_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Prevent Image Resize', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('If you set Maximum "Pixel ratio" equal to 2, but some of your assets does not have min retina size (at least 2560x960), please enable this to prevent image resized. By default, yes.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_ORG_IF_SML',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'org_if_sml_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'org_if_sml_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Image Quality', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('The smaller the value, the more your image will be compressed. Careful — the quality of the image will decrease as well. By default, 90.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_IMAGE_QUALITY',
                        'required' => false,
                        'options' => [
                            'query' => $qualityOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Maximum "Pixel ratio"', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('List of supported device pixel ratios, eg 2 for Retina devices.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_MAXIMUM_PIXEL_RATIO',
                        'required' => false,
                        'options' => [
                            'query' => $pixelRatios,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Remove V7', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('Removes the "/v7" part in URL format. Activate for token created after October 20th 2021.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_REMOVE_V7',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'remove_v7_on', 'value' => true, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'remove_v7_off', 'value' => false, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Custom JS Function', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('The valid JS function starting with { and finishing with }', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_CUSTOM_JS_FUNCTION',
                    ],
                    [
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->trans('Custom Library Options', [], 'Modules.Cloudimage.Admin'),
                        'desc' => $this->trans('Modifies the library URL and must begin with the symbol &. Please read documentation before use.', [], 'Modules.Cloudimage.Admin'),
                        'name' => 'CLOUDIMAGE_CUSTOM_LIBRARY_OPTION',
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];
    }

    /**
     * Get form values from configuration
     *
     * @return array
     */
    protected function getConfigFormValues()
    {
        return [
            'CLOUDIMAGE_ACTIVATION' => Configuration::get('CLOUDIMAGE_ACTIVATION'),
            'CLOUDIMAGE_TOKEN' => Configuration::get('CLOUDIMAGE_TOKEN'),
            'CLOUDIMAGE_CNAME' => Configuration::get('CLOUDIMAGE_CNAME'),
            'CLOUDIMAGE_USE_ORIGINAL_URL' => Configuration::get('CLOUDIMAGE_USE_ORIGINAL_URL'),
            'CLOUDIMAGE_PRERENDER' => Configuration::get('CLOUDIMAGE_PRERENDER'),
            'CLOUDIMAGE_AUTO_BASE_URL' => Configuration::get('CLOUDIMAGE_AUTO_BASE_URL'),
            'CLOUDIMAGE_LAZY_LOADING' => Configuration::get('CLOUDIMAGE_LAZY_LOADING'),
            'CLOUDIMAGE_IGNORE_SVG_IMAGE' => Configuration::get('CLOUDIMAGE_IGNORE_SVG_IMAGE'),
            'CLOUDIMAGE_ORG_IF_SML' => Configuration::get('CLOUDIMAGE_ORG_IF_SML'),
            'CLOUDIMAGE_IMAGE_QUALITY' => Configuration::get('CLOUDIMAGE_IMAGE_QUALITY'),
            'CLOUDIMAGE_MAXIMUM_PIXEL_RATIO' => Configuration::get('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'),
            'CLOUDIMAGE_CUSTOM_JS_FUNCTION' => Configuration::get('CLOUDIMAGE_CUSTOM_JS_FUNCTION'),
            'CLOUDIMAGE_CUSTOM_LIBRARY_OPTION' => Configuration::get('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'),
            'CLOUDIMAGE_REMOVE_V7' => Configuration::get('CLOUDIMAGE_REMOVE_V7'),
        ];
    }

    /**
     * Using new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Init configs on install
     *
     * @return bool
     */
    private function initConfigs()
    {
        return Configuration::updateValue('CLOUDIMAGE_ACTIVATION', '0')
            && Configuration::updateValue('CLOUDIMAGE_TOKEN', '')
            && Configuration::updateValue('CLOUDIMAGE_CNAME', '')
            && Configuration::updateValue('CLOUDIMAGE_USE_ORIGINAL_URL', '1')
            && Configuration::updateValue('CLOUDIMAGE_PRERENDER', '0')
            && Configuration::updateValue('CLOUDIMAGE_AUTO_BASE_URL', '0')
            && Configuration::updateValue('CLOUDIMAGE_LAZY_LOADING', '1')
            && Configuration::updateValue('CLOUDIMAGE_IGNORE_SVG_IMAGE', '0')
            && Configuration::updateValue('CLOUDIMAGE_ORG_IF_SML', '1')
            && Configuration::updateValue('CLOUDIMAGE_IMAGE_QUALITY', '90')
            && Configuration::updateValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO', '2')
            && Configuration::updateValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION', '')
            && Configuration::updateValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION', '')
            && Configuration::updateValue('CLOUDIMAGE_REMOVE_V7', '1');
    }

    /**
     * Delete configs on uninstall
     *
     * @return bool
     */
    private function deleteConfigs()
    {
        return Configuration::deleteByName('CLOUDIMAGE_ACTIVATION')
            && Configuration::deleteByName('CLOUDIMAGE_TOKEN')
            && Configuration::deleteByName('CLOUDIMAGE_CNAME')
            && Configuration::deleteByName('CLOUDIMAGE_USE_ORIGINAL_URL')
            && Configuration::deleteByName('CLOUDIMAGE_PRERENDER')
            && Configuration::deleteByName('CLOUDIMAGE_AUTO_BASE_URL')
            && Configuration::deleteByName('CLOUDIMAGE_LAZY_LOADING')
            && Configuration::deleteByName('CLOUDIMAGE_IGNORE_SVG_IMAGE')
            && Configuration::deleteByName('CLOUDIMAGE_ORG_IF_SML')
            && Configuration::deleteByName('CLOUDIMAGE_IMAGE_QUALITY')
            && Configuration::deleteByName('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO')
            && Configuration::deleteByName('CLOUDIMAGE_CUSTOM_JS_FUNCTION')
            && Configuration::deleteByName('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION')
            && Configuration::deleteByName('CLOUDIMAGE_REMOVE_V7');
    }

    /**
     * Build Cloudimage URL
     *
     * @param string $inputUrl
     *
     * @return string
     */
    public function buildUrl($inputUrl)
    {
        $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];

        if (stripos($inputUrl, 'http') === false) {
            $inputUrl = $this->removeFirstSlash($inputUrl);
            if ($this->getConfigs('ciAutoBaseUrl')) {
                $inputUrl = $this->addSlashIfMissing($baseUrl) . $inputUrl;
            }
        } else {
            $inputUrl = $this->removeFirstSlash($inputUrl);
        }

        $config = $this->getConfigs();

        $signedUrl = '//' . $config['ciToken'] . '.cloudimg.io/';

        if (!empty($config['ciCName'])) {
            $signedUrl = $this->addSlashIfMissing('//' . $config['ciCName']);
        }

        if (!$config['ciRemoveV7']) {
            $signedUrl .= 'v7/';
        }

        $flagCheck = false;

        if (!empty($config['ciCName'])) {
            $inputUrl = str_replace('/' . $config['ciCName'], '', $inputUrl);
        }

        $ciUrl = $signedUrl . $inputUrl . '?';

        if (!empty($config['ciCName'])) {
            $ciUrl = str_replace($config['ciCName'] . '//', $config['ciCName'] . '/', $ciUrl);
        }

        if (!$config['ciRemoveV7']) {
            $ciUrl = str_replace('//v7', '', $ciUrl);
        }

        if ($config['ciImageQuality'] < 100) {
            if (!strpos($ciUrl, '?q=' . $config['ciImageQuality'])) {
                $ciUrl .= 'q=' . $config['ciImageQuality'];
            }
            $flagCheck = true;
        }
        $ciUrl = $this->removeLastQuestionMark($ciUrl);

        if ($config['ciOrgIfSml']) {
            $configParam = 'org_if_sml=1';
            if (!strpos($ciUrl, $configParam)) {
                if ($config['ciImageQuality'] === 100) {
                    $ciUrl .= '?' . $configParam;
                } else {
                    $ciUrl .= $flagCheck ? '&' . $configParam : $configParam;
                }
            }
            $flagCheck = true;
        }

        return $flagCheck ? $ciUrl : $this->removeLastQuestionMark($baseUrl . $inputUrl);
    }

    /**
     * Remove trailing question mark from URL
     *
     * @param string $ciUrl
     *
     * @return string
     */
    private function removeLastQuestionMark($ciUrl)
    {
        if (substr($ciUrl, -1) === '?') {
            $ciUrl = substr($ciUrl, 0, -1);
        }

        return $ciUrl;
    }

    /**
     * Remove leading slash from URL
     *
     * @param string $inputUrl
     *
     * @return string
     */
    private function removeFirstSlash($inputUrl)
    {
        if (substr($inputUrl, 0, 1) === '/') {
            $inputUrl = substr($inputUrl, 1);
        }

        return $inputUrl;
    }

    /**
     * Add trailing slash if missing
     *
     * @param string $baseUrl
     *
     * @return string
     */
    private function addSlashIfMissing($baseUrl)
    {
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }

        return $baseUrl;
    }
}
