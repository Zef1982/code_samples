<?php
/**
 * AdLdap plugin for Craft CMS 3.x
 *
 * AdLdap settings page.
 *
 * @link      http://estdigital.nl
 * @copyright Copyright (c) 2021 ZEF
 */

namespace estdigital\adldap\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
    public $group;
    public $baseDN;
    public $domainControllers;
    public $ssl;
    public $tls;
    public $referrals;
    public $port;

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     * *
     * @return array
     */
    public function rules()
    {
        return [
            [['group', 'baseDN', 'domainControllers'], 'string'],
            [['ssl', 'tls', 'referrals'], 'boolean'],
            [['port'], 'number'],
            // Set default value for port.
            ['port', 'default', 'value' => 389]
        ];
    }
}