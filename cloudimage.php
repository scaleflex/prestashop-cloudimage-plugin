<?php
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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cloudimage extends Module
{
    public function __construct()
    {
        $this->name = 'cloudimage';
        $this->version = '1.0.1';
        $this->tab = 'front_office_features';
        $this->author = 'Scaleflex';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];

        if (_PS_VERSION_ > '1.7') {
            $this->bootstrap = true;
        }

        $this->displayName = $this->l('Cloudimage by Scaleflex');
        $this->description = $this->l('The easiest way to resize, compress, optimise and deliver lightning fast images to your users on any device via CDN.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        parent::__construct();
    }

    /**
     * Install the Module
     * @return bool
     */
    public function install()
    {
        return parent::install() &&
            $this->initConfigs() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('actionDispatcher') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    /**
     * Uninstall the application
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() && $this->deleteConfigs();
    }

    /**
     * Add JS to Front Page
     * @return void
     */
    public function hookActionFrontControllerSetMedia()
    {
        if ($this->getConfigs('ciActivation')) {
            $this->context->controller->addJS($this->_path . 'views/js/cloudimage.js');
            $this->context->controller->addJS($this->_path . 'views/js/product.js');
        }
    }

    /**
     * Override smarty template Hook
     * @param $params
     * @return void
     * @throws SmartyException
     */
    public function hookActionDispatcher($params)
    {
        $this->context->smarty->registerFilter(Smarty::FILTER_OUTPUT, [$this, 'changeImageSrc']);
    }

    /**
     * Override smarty template
     * @param $html
     * @param Smarty_Internal_Template $template
     * @return array|mixed|string|string[]
     */
    public function changeImageSrc($html, Smarty_Internal_Template $template)
    {
        if (!$this->context->employee && $this->getConfigs('ciActivation')) {
            if (stripos($html, '<img') !== false) {
                $dom = new domDocument();
                $useErrors = libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                libxml_use_internal_errors($useErrors);
                $dom->preserveWhiteSpace = false;
                $replaceHtml = false;

                $quality = '';
                if ($this->getConfigs('ciImageQuality') < 100) {
                    $quality = '?q=' . $this->getConfigs('ciImageQuality');
                }
                $ignoreSvg = $this->getConfigs('ciIgnoreSvgImage');

                foreach ($dom->getElementsByTagName('img') as $element) {
                    /** @var DOMElement $element */
                    if ($element->hasAttribute('src')) {
                        if ($ignoreSvg && strtolower(pathinfo($element->getAttribute('src'), PATHINFO_EXTENSION)) === 'svg') {
                            continue;
                        }

                        $element->setAttribute('ci-src', $element->getAttribute('src') . $quality);
                        $element->removeAttribute('src');
                        $replaceHtml = true;
                    }

                }

                if ($replaceHtml) {
                    $html = $dom->saveHTML($dom->documentElement);
                    $html = str_ireplace(['<html><body>', '</body></html>'], '', $html);
                }
            }
        }
        return $html;
    }

    /**
     * Add to Header
     * @param $params
     * @return false|string
     */
    public function hookDisplayHeader($params)
    {
        $this->context->smarty->assign($this->getConfigs());
        return $this->display(__FILE__, 'views/templates/hook/cloudimage.tpl');
    }

    /** Get Configuration */
    public function getConfigs($configName = null)
    {
        $configs = [
            'ciActivation' => (bool)Configuration::get('CLOUDIMAGE_ACTIVATION'),
            'ciToken' => (string)Configuration::get('CLOUDIMAGE_TOKEN'),
            'ciUseOriginalUrl' => (bool)Configuration::get('CLOUDIMAGE_USE_ORIGINAL_URL'),
            'ciLazyLoading' => (bool)Configuration::get('CLOUDIMAGE_LAZY_LOADING'),
            'ciIgnoreSvgImage' => (bool)Configuration::get('CLOUDIMAGE_IGNORE_SVG_IMAGE'),
            'ciOrgIfSml' => (bool)Configuration::get('CLOUDIMAGE_ORG_IF_SML'),
            'ciImageQuality' => (int)Configuration::get('CLOUDIMAGE_IMAGE_QUALITY'),
            'ciMaximumPixelRatio' => (int)Configuration::get('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'),
            'ciCustomJSFunction' => (string)Configuration::get('CLOUDIMAGE_CUSTOM_JS_FUNCTION'),
            'ciCustomLibraryOption' => (string)Configuration::get('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'),
            'ciRemoveV7' => (string)Configuration::get('CLOUDIMAGE_REMOVE_V7'),

        ];

        if ($configName) {
            return $configs[$configName];
        }

        return $configs;
    }


    /**
     * Admin Config Page
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
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
            Configuration::updateValue('CLOUDIMAGE_USE_ORIGINAL_URL', Tools::getValue('CLOUDIMAGE_USE_ORIGINAL_URL'));
            Configuration::updateValue('CLOUDIMAGE_LAZY_LOADING', Tools::getValue('CLOUDIMAGE_LAZY_LOADING'));
            Configuration::updateValue('CLOUDIMAGE_IGNORE_SVG_IMAGE', Tools::getValue('CLOUDIMAGE_IGNORE_SVG_IMAGE'));
            Configuration::updateValue('CLOUDIMAGE_ORG_IF_SML', Tools::getValue('CLOUDIMAGE_ORG_IF_SML'));
            Configuration::updateValue('CLOUDIMAGE_IMAGE_QUALITY', Tools::getValue('CLOUDIMAGE_IMAGE_QUALITY'));
            Configuration::updateValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO', Tools::getValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'));
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION', Tools::getValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION'));
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION', Tools::getValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'));
            Configuration::updateValue('CLOUDIMAGE_REMOVE_V7', Tools::getValue('CLOUDIMAGE_REMOVE_V7'));

            $output .= $submitStatus ? $this->displayConfirmation($this->trans('Form submitted successfully')) :
                $this->displayError($this->trans('You can not active Cloudimage because your Token is empty'));
        }

        return $output . $this->buildForm();
    }

    /**
     * Build Admin Form
     * @return string
     */
    public function buildForm()
    {
        $ciActivation = (bool)Configuration::get('CLOUDIMAGE_ACTIVATION');
        $ciToken = (string)Configuration::get('CLOUDIMAGE_TOKEN');
        $ciUseOriginalUrl = (bool)Configuration::get('CLOUDIMAGE_USE_ORIGINAL_URL');
        $ciLazyLoading = (bool)Configuration::get('CLOUDIMAGE_LAZY_LOADING');
        $ciIgnoreSvgImage = (bool)Configuration::get('CLOUDIMAGE_IGNORE_SVG_IMAGE');
        $ciOrgIfSml = (bool)Configuration::get('CLOUDIMAGE_ORG_IF_SML');
        $ciImageQuality = (int)Configuration::get('CLOUDIMAGE_IMAGE_QUALITY');
        $ciMaximumPixelRatio = (int)Configuration::get('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO');
        $ciCustomJSFunction = (string)Configuration::get('CLOUDIMAGE_CUSTOM_JS_FUNCTION');
        $ciCustomLibraryOption = (string)Configuration::get('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION');
        $ciRemoveV7 = (bool)Configuration::get('CLOUDIMAGE_REMOVE_V7');

        $qualityOptions = [];
        $pixelRatios = [
            [
                'id_option' => 1,
                'name' => (string)1
            ],
            [
                'id_option' => 1.5,
                'name' => (string)1.5
            ],
            [
                'id_option' => 2,
                'name' => (string)2
            ]
        ];

        for ($i = 1; $i <= 100; $i++) {
            $qualityOptions[] = [
                'id_option' => $i,
                'name' => (string)$i
            ];
        }

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cloudimage Integration'),
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activation'),
                        'desc' => $this->l('Enable/Disable the Module'),
                        'name' => 'CLOUDIMAGE_ACTIVATION',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Token'),
                        'desc' => $this->l('Cloudimage token'),
                        'name' => 'CLOUDIMAGE_TOKEN',
                        'size' => 20
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Use Original URL'),
                        'desc' => $this->l('If enabled, the plugin will only add query parameters to the image source URL, avoiding double CDNization in some cases, like if you have aliases configured.'),
                        'name' => 'CLOUDIMAGE_USE_ORIGINAL_URL',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Lazy Loading'),
                        'desc' => $this->l('If enabled, only images close to the current viewpoint will be loaded.'),
                        'name' => 'CLOUDIMAGE_LAZY_LOADING',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Ignore SVG Image'),
                        'desc' => $this->l('By default, No.'),
                        'name' => 'CLOUDIMAGE_IGNORE_SVG_IMAGE',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Prevent Image Resize'),
                        'desc' => $this->l('If you set Maximum "Pixel ratio" equal to 2, but some of your assets does not have min retina size(at least 2560x960), please enable this to prevent image resized. By default, yes.'),
                        'name' => 'CLOUDIMAGE_ORG_IF_SML',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Image Quality'),
                        'desc' => $this->l('The smaller the value, the more your image will be compressed. Careful ??? the quality of the image will decrease as well. By default, 90.'),
                        'name' => 'CLOUDIMAGE_IMAGE_QUALITY',
                        'required' => false,
                        'options' => [
                            'query' => $qualityOptions,
                            'id' => 'id_option',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Maximum " Pixel ratio"'),
                        'desc' => $this->l('List of supported device pixel ratios, eg 2 for Retina devices'),
                        'name' => 'CLOUDIMAGE_MAXIMUM_PIXEL_RATIO',
                        'required' => false,
                        'options' => [
                            'query' => $pixelRatios,
                            'id' => 'id_option',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Remove V7'),
                        'desc' => $this->l('Removes the "/v7" part in URL format. Activate for token created after October 20th 2021.'),
                        'name' => 'CLOUDIMAGE_REMOVE_V7',
                        'size' => 20,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            ]
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Custom js function'),
                        'desc' => $this->l('The valid js function starting with { and finishing with }'),
                        'name' => 'CLOUDIMAGE_CUSTOM_JS_FUNCTION',
                        'size' => 20
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Custom Library Options'),
                        'desc' => $this->l('Modifies the library URL and must begin with the symbol &. Please read document before use.'),
                        'name' => 'CLOUDIMAGE_CUSTOM_LIBRARY_OPTION',
                        'size' => 20
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        // Load current value into the form
        $helper->fields_value['CLOUDIMAGE_ACTIVATION'] = $ciActivation;
        $helper->fields_value['CLOUDIMAGE_TOKEN'] = $ciToken;
        $helper->fields_value['CLOUDIMAGE_USE_ORIGINAL_URL'] = $ciUseOriginalUrl;
        $helper->fields_value['CLOUDIMAGE_LAZY_LOADING'] = $ciLazyLoading;
        $helper->fields_value['CLOUDIMAGE_IGNORE_SVG_IMAGE'] = $ciIgnoreSvgImage;
        $helper->fields_value['CLOUDIMAGE_ORG_IF_SML'] = $ciOrgIfSml;
        $helper->fields_value['CLOUDIMAGE_IMAGE_QUALITY'] = $ciImageQuality;
        $helper->fields_value['CLOUDIMAGE_MAXIMUM_PIXEL_RATIO'] = $ciMaximumPixelRatio;
        $helper->fields_value['CLOUDIMAGE_CUSTOM_JS_FUNCTION'] = $ciCustomJSFunction;
        $helper->fields_value['CLOUDIMAGE_CUSTOM_LIBRARY_OPTION'] = $ciCustomLibraryOption;
        $helper->fields_value['CLOUDIMAGE_REMOVE_V7'] = $ciRemoveV7;

        return $helper->generateForm([$form]);
    }

    /**
     * Using new translation system
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Init configs if install
     * @return bool
     */
    private function initConfigs()
    {
        return Configuration::updateValue('CLOUDIMAGE_ACTIVATION', "0") &&
            Configuration::updateValue('CLOUDIMAGE_TOKEN', "") &&
            Configuration::updateValue('CLOUDIMAGE_USE_ORIGINAL_URL', "1") &&
            Configuration::updateValue('CLOUDIMAGE_LAZY_LOADING', "1") &&
            Configuration::updateValue('CLOUDIMAGE_IGNORE_SVG_IMAGE', "0") &&
            Configuration::updateValue('CLOUDIMAGE_ORG_IF_SML', "1") &&
            Configuration::updateValue('CLOUDIMAGE_IMAGE_QUALITY', "90") &&
            Configuration::updateValue('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO', "2") &&
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_JS_FUNCTION', "") &&
            Configuration::updateValue('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION', "") &&
            Configuration::updateValue('CLOUDIMAGE_REMOVE_V7', "1");
    }

    /**
     * Delete config if uninstalled
     * @return bool
     */
    private function deleteConfigs()
    {
        return Configuration::deleteByName('CLOUDIMAGE_ACTIVATION') &&
            Configuration::deleteByName('CLOUDIMAGE_TOKEN') &&
            Configuration::deleteByName('CLOUDIMAGE_USE_ORIGINAL_URL') &&
            Configuration::deleteByName('CLOUDIMAGE_LAZY_LOADING') &&
            Configuration::deleteByName('CLOUDIMAGE_IGNORE_SVG_IMAGE') &&
            Configuration::deleteByName('CLOUDIMAGE_ORG_IF_SML') &&
            Configuration::deleteByName('CLOUDIMAGE_IMAGE_QUALITY') &&
            Configuration::deleteByName('CLOUDIMAGE_MAXIMUM_PIXEL_RATIO') &&
            Configuration::deleteByName('CLOUDIMAGE_CUSTOM_JS_FUNCTION') &&
            Configuration::deleteByName('CLOUDIMAGE_CUSTOM_LIBRARY_OPTION') &&
            Configuration::deleteByName('CLOUDIMAGE_REMOVE_V7');
    }
}
