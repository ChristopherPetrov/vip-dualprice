<?php
/**
 * vipdualprices - a PrestaShop module to display dual prices for BGN and EUR.
 *
 * This module shows product prices in a primary currency with an optional
 * secondary currency in parentheses or separated by a pipe symbol. It uses
 * a fixed conversion rate and allows administrators to toggle where the
 * secondary price is shown from the Back Office. The module does not override
 * core files and relies exclusively on hooks, keeping your PrestaShop and
 * theme intact.
 *
 * Configuration keys defined by this module:
 *  - VIPDP_PRIMARY (string)   Primary currency ISO code ('BGN' or 'EUR')
 *  - VIPDP_SHOW_SECONDARY (int) 1 to enable the secondary price, 0 to hide
 *  - VIPDP_FIXED_RATE (float)  Fixed conversion rate between BGN and EUR
 *  - VIPDP_FORMAT (string)    Display format: 'paren' for parentheses or 'pipe' for bar
 *  - VIPDP_ENABLE_PRODUCT (int) 1/0 whether to show secondary price on product pages and listings
 *  - VIPDP_ENABLE_CART (int)    1/0 whether to show secondary totals on the order confirmation page
 *  - VIPDP_ENABLE_EMAILS (int)  1/0 whether to expose secondary totals for email templates
 *
 * @author ChatGPT
 * @license GPL-3.0-or-later
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Vipdualprices extends Module
{
    /**
     * Constructor sets module metadata and initialises configuration. This
     * constructor follows PrestaShop guidelines and does not execute logic
     * heavy operations.
     */
    public function __construct()
    {
        $this->name = 'vipdualprices';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'ChatGPT';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '1.7.9.9');

        parent::__construct();

        $this->displayName = $this->l('Dual Price Display (BGN/EUR)');
        $this->description = $this->l('Displays product prices in a primary and secondary currency using a fixed rate between Bulgarian lev and euro.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Dual Price module?');
    }

    /**
     * Called when installing the module. Registers hooks and stores default
     * configuration values. Returning false from any of these steps aborts
     * installation.
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('sendMailAlterTemplateVars')
            && $this->registerHook('displayOrderConfirmation')
            && $this->initConfig();
    }

    /**
     * Called when uninstalling the module. Removes configuration values.
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->removeConfig();
        return parent::uninstall();
    }

    /**
     * Creates default configuration values during installation.
     *
     * @return bool
     */
    protected function initConfig()
    {
        Configuration::updateValue('VIPDP_PRIMARY', 'BGN');
        Configuration::updateValue('VIPDP_SHOW_SECONDARY', 1);
        Configuration::updateValue('VIPDP_FIXED_RATE', '1.95583');
        Configuration::updateValue('VIPDP_FORMAT', 'paren');
        Configuration::updateValue('VIPDP_TAG_STYLE', 'symbol');
        Configuration::updateValue('VIPDP_ENABLE_PRODUCT', 1);
        Configuration::updateValue('VIPDP_ENABLE_CART', 1);
        Configuration::updateValue('VIPDP_ENABLE_EMAILS', 1);
        return true;
    }

    /**
     * Removes all module specific configuration values. Called on uninstall.
     *
     * @return void
     */
    protected function removeConfig()
    {
        $keys = array(
            'VIPDP_PRIMARY',
            'VIPDP_SHOW_SECONDARY',
            'VIPDP_FIXED_RATE',
            'VIPDP_FORMAT',
            'VIPDP_TAG_STYLE',
            'VIPDP_ENABLE_PRODUCT',
            'VIPDP_ENABLE_CART',
            'VIPDP_ENABLE_EMAILS',
        );
        foreach ($keys as $k) {
            Configuration::deleteByName($k);
        }
    }

    /**
     * Builds the configuration form and processes form submissions in the
     * module back‑office page. Uses HelperForm to ensure compatibility with
     * PrestaShop 1.7 BO design.
     *
     * @return string Generated HTML for module configuration
     */
    public function getContent()
    {
        $output = '';
        // Save configuration when form is submitted
        if (Tools::isSubmit('submit_'.$this->name)) {
            $primary = Tools::getValue('VIPDP_PRIMARY', 'BGN');
            $showSecondary = (int)Tools::getValue('VIPDP_SHOW_SECONDARY', 0);
            $rate = (float)Tools::getValue('VIPDP_FIXED_RATE', 1.95583);
            $format = Tools::getValue('VIPDP_FORMAT', 'paren');
            $tagStyle = Tools::getValue('VIPDP_TAG_STYLE', 'symbol');
            $enableProduct = (int)Tools::getValue('VIPDP_ENABLE_PRODUCT', 0);
            $enableCart = (int)Tools::getValue('VIPDP_ENABLE_CART', 0);
            $enableEmails = (int)Tools::getValue('VIPDP_ENABLE_EMAILS', 0);

            // Validate rate
            if ($rate <= 0) {
                $output .= $this->displayError($this->l('The conversion rate must be a positive number.'));
            } else {
                Configuration::updateValue('VIPDP_PRIMARY', $primary);
                Configuration::updateValue('VIPDP_SHOW_SECONDARY', $showSecondary);
                Configuration::updateValue('VIPDP_FIXED_RATE', $rate);
                Configuration::updateValue('VIPDP_FORMAT', $format);
                Configuration::updateValue('VIPDP_TAG_STYLE', $tagStyle);
                Configuration::updateValue('VIPDP_ENABLE_PRODUCT', $enableProduct);
                Configuration::updateValue('VIPDP_ENABLE_CART', $enableCart);
                Configuration::updateValue('VIPDP_ENABLE_EMAILS', $enableEmails);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_'.$this->name;
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Dual Price Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Primary currency'),
                        'name' => 'VIPDP_PRIMARY',
                        'options' => array(
                            'query' => array(
                                array('id_option' => 'BGN', 'name' => 'BGN'),
                                array('id_option' => 'EUR', 'name' => 'EUR'),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable secondary price display'),
                        'name' => 'VIPDP_SHOW_SECONDARY',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Fixed conversion rate'),
                        'name' => 'VIPDP_FIXED_RATE',
                        'desc' => $this->l('Defines the conversion rate between BGN and EUR. Default is 1.95583.'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Display format'),
                        'name' => 'VIPDP_FORMAT',
                        'options' => array(
                            'query' => array(
                                array('id_option' => 'paren', 'name' => $this->l('Primary (Secondary)')),
                                array('id_option' => 'pipe', 'name' => $this->l('Primary | Secondary')),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Secondary currency tag'),
                        'name' => 'VIPDP_TAG_STYLE',
                        'options' => array(
                            'query' => array(
                                array('id_option' => 'symbol', 'name' => $this->l('Symbol (€ / лв)')),
                                array('id_option' => 'code', 'name' => $this->l('ISO code (EUR / BGN)')),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show on products and listings'),
                        'name' => 'VIPDP_ENABLE_PRODUCT',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'prod_on', 'value' => 1, 'label' => $this->l('Enabled')),
                            array('id' => 'prod_off', 'value' => 0, 'label' => $this->l('Disabled')),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show on cart, checkout, and order confirmation'),
                        'name' => 'VIPDP_ENABLE_CART',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'cart_on', 'value' => 1, 'label' => $this->l('Enabled')),
                            array('id' => 'cart_off', 'value' => 0, 'label' => $this->l('Disabled')),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Expose variables for emails'),
                        'name' => 'VIPDP_ENABLE_EMAILS',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'email_on', 'value' => 1, 'label' => $this->l('Enabled')),
                            array('id' => 'email_off', 'value' => 0, 'label' => $this->l('Disabled')),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ),
            ),
        );

        return $output.$helper->generateForm(array($fields_form));
    }

    /**
     * Helper method returning current configuration values for the form.
     *
     * @return array
     */
    protected function getConfigFormValues()
    {
        return array(
            'VIPDP_PRIMARY' => Configuration::get('VIPDP_PRIMARY', 'BGN'),
            'VIPDP_SHOW_SECONDARY' => (int)Configuration::get('VIPDP_SHOW_SECONDARY'),
            'VIPDP_FIXED_RATE' => Configuration::get('VIPDP_FIXED_RATE'),
            'VIPDP_FORMAT' => Configuration::get('VIPDP_FORMAT', 'paren'),
            'VIPDP_TAG_STYLE' => Configuration::get('VIPDP_TAG_STYLE', 'symbol'),
            'VIPDP_ENABLE_PRODUCT' => (int)Configuration::get('VIPDP_ENABLE_PRODUCT'),
            'VIPDP_ENABLE_CART' => (int)Configuration::get('VIPDP_ENABLE_CART'),
            'VIPDP_ENABLE_EMAILS' => (int)Configuration::get('VIPDP_ENABLE_EMAILS'),
        );
    }

    /**
     * Registers our front‑office CSS via the appropriate hook. The stylesheet
     * applies a subtle styling to secondary prices and is added with a
     * relatively low priority to allow other modules to override it.
     *
     * @param array $params Hook parameters
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if ($this->context->controller instanceof FrontController) {
            $this->context->controller->registerStylesheet(
                'module-'.$this->name.'-front-css',
                'modules/'.$this->name.'/views/css/front.css',
                array('media' => 'all', 'priority' => 150)
            );
            $this->context->controller->registerJavascript(
                'module-'.$this->name.'-front-js',
                'modules/'.$this->name.'/views/js/front.js',
                array('position' => 'bottom', 'priority' => 150)
            );
            Media::addJsDef(array(
                'vipdpConfig' => array(
                    'rate' => (float)Configuration::get('VIPDP_FIXED_RATE'),
                    'primary' => Configuration::get('VIPDP_PRIMARY', 'BGN'),
                    'format' => Configuration::get('VIPDP_FORMAT', 'paren'),
                    'tagStyle' => Configuration::get('VIPDP_TAG_STYLE', 'symbol'),
                    'showSecondary' => (int)Configuration::get('VIPDP_SHOW_SECONDARY'),
                    'enableProduct' => (int)Configuration::get('VIPDP_ENABLE_PRODUCT'),
                    'enableCart' => (int)Configuration::get('VIPDP_ENABLE_CART'),
                    'currencySymbols' => array(
                        'BGN' => 'лв',
                        'EUR' => '€',
                    ),
                    'currencyCodes' => array(
                        'BGN' => 'BGN',
                        'EUR' => 'EUR',
                    ),
                ),
            ));
        }
    }

    /**
     * Resolves the secondary currency, amount, and currency object.
     *
     * @param float $amount Primary currency amount
     * @return array
     */
    protected function getSecondaryMeta($amount)
    {
        $rate = (float)Configuration::get('VIPDP_FIXED_RATE');
        $primary = Configuration::get('VIPDP_PRIMARY', 'BGN');
        $secondaryIso = ($primary === 'BGN') ? 'EUR' : 'BGN';
        if ($primary === 'BGN') {
            $secondaryAmount = ($rate > 0) ? $amount / $rate : 0;
        } else {
            $secondaryAmount = $amount * $rate;
        }
        $secondaryCurrencyId = (int)Currency::getIdByIsoCode($secondaryIso, (int)$this->context->shop->id);
        $secondaryCurrency = null;
        if ($secondaryCurrencyId > 0) {
            $secondaryCurrency = new Currency($secondaryCurrencyId);
        }

        return array(
            'amount' => $secondaryAmount,
            'iso' => $secondaryIso,
            'currency' => $secondaryCurrency,
        );
    }

    /**
     * Computes and formats the secondary price based on the configured rate and
     * primary currency. Uses Currency::getIdByIsoCode() to obtain the secondary
     * currency object from its ISO code and
     * Tools::displayPrice() for formatting. See PrestaShop core for the
     * implementation of these helpers.
     *
     * @param float $amount Primary currency amount
     * @return string Formatted secondary price
     */
    protected function getSecondaryFormatted($amount)
    {
        $meta = $this->getSecondaryMeta($amount);
        $tagStyle = Configuration::get('VIPDP_TAG_STYLE', 'symbol');
        if ($meta['currency'] instanceof Currency) {
            $formatted = Tools::displayPrice($meta['amount'], $meta['currency']);
            if ($tagStyle === 'code') {
                $clean = trim(str_replace($meta['currency']->sign, '', $formatted));
                return trim($clean).' '.$meta['currency']->iso_code;
            }
            return $formatted;
        }

        $number = sprintf('%0.2f', $meta['amount']);
        if ($tagStyle === 'code') {
            return $number.' '.$meta['iso'];
        }
        $symbol = ($meta['iso'] === 'EUR') ? '€' : 'лв';
        if ($meta['iso'] === 'EUR') {
            return $symbol.$number;
        }
        return $number.' '.$symbol;
    }

    /**
     * Hook into the displayProductPriceBlock hook. This hook is triggered at
     * multiple locations in the front office when product prices are rendered.
     * We append the secondary price only for relevant types (price, unit_price,
     * old_price). The hook passes parameters including the product object and
     * type; we leverage them to determine the base price.
     *
     * @param array $params Parameters provided by PrestaShop, including
     *                      'product' (array|object) and 'type' (string)
     * @return string HTML to append after the price
     */
    public function hookDisplayProductPriceBlock($params)
    {
        // Feature toggles
        if (!Configuration::get('VIPDP_SHOW_SECONDARY')) {
            return '';
        }
        if (!Configuration::get('VIPDP_ENABLE_PRODUCT')) {
            return '';
        }
        $type = isset($params['type']) ? $params['type'] : '';
        if (!in_array($type, array('price', 'unit_price', 'old_price'))) {
            return '';
        }
        // Determine base price: PrestaShop passes it differently depending on context.
        $amount = 0;
        if (isset($params['product']['price_amount'])) {
            $amount = (float)$params['product']['price_amount'];
        } elseif (isset($params['product']['price'])) {
            $amount = (float)$params['product']['price'];
        } elseif (isset($params['product']['price_tax_exc'])) {
            $amount = (float)$params['product']['price_tax_exc'];
        }
        if ($amount <= 0) {
            return '';
        }
        $secondary = $this->getSecondaryFormatted($amount);
        $format = Configuration::get('VIPDP_FORMAT', 'paren');
        $html = '';
        if ($format === 'pipe') {
            $html = ' <span class="vipdp-secondary">| '.$secondary.'</span>';
        } else {
            $html = ' <span class="vipdp-secondary">('.$secondary.')</span>';
        }
        return $html;
    }

    /**
     * Adds a marker for the order confirmation page so the front‑office script
     * can inject secondary totals next to the standard totals table.
     *
     * @param array $params Parameters passed by PrestaShop, including 'order'
     * @return string HTML snippet
     */
    public function hookDisplayOrderConfirmation($params)
    {
        if (!Configuration::get('VIPDP_SHOW_SECONDARY') || !Configuration::get('VIPDP_ENABLE_CART')) {
            return '';
        }
        if (!isset($params['order']) || !($params['order'] instanceof Order)) {
            return '';
        }
        return '<div class="vipdp-confirmation-data" aria-hidden="true"></div>';
    }

    /**
     * Extends email template variables using sendMailAlterTemplateVars hook. This
     * hook is triggered when Mail::send() is called【525733741268901†L623-L650】, providing an opportunity to
     * add or modify template variables before the email is rendered. We add
     * secondary totals for commonly used variables such as {total_paid},
     * {total_products}, and {total_shipping}. Template designers can then
     * incorporate these variables in their mail templates.
     *
     * @param array $params Contains 'template' and 'template_vars' by reference
     */
    public function hookSendMailAlterTemplateVars($params)
    {
        if (!Configuration::get('VIPDP_SHOW_SECONDARY') || !Configuration::get('VIPDP_ENABLE_EMAILS')) {
            return;
        }
        if (!isset($params['template_vars']) || !is_array($params['template_vars'])) {
            return;
        }
        $templateVars = &$params['template_vars'];
        // List of standard order total variables to replicate
        $totals = array(
            'total_paid',
            'total_products',
            'total_shipping',
            'total_tax',
            'total_discounts',
        );
        foreach ($totals as $baseKey) {
            $tplKey = '{'.strtoupper($baseKey).'}';
            if (!isset($templateVars[$tplKey])) {
                continue;
            }
            // Extract numeric value from the formatted price. We strip HTML tags and
            // non‑numeric characters; this is a best‑effort approach because
            // email variables already contain formatted prices.
            $raw = strip_tags($templateVars[$tplKey]);
            // Replace non‑numeric characters except dot and comma
            $clean = preg_replace('/[^0-9,\.]/', '', $raw);
            // Normalise comma to dot for decimal separator
            $clean = str_replace(',', '.', $clean);
            $amount = (float)$clean;
            if ($amount <= 0) {
                continue;
            }
            $secondaryFormatted = $this->getSecondaryFormatted($amount);
            $templateVars['{'.strtoupper($baseKey).'_SECONDARY}'] = $secondaryFormatted;
        }
    }
}
