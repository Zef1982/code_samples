<?php
/**
 * AdLdap plugin for Craft CMS 3.x
 *
 * AdLdap settings page.
 *
 * @link      http://estdigital.nl
 * @copyright Copyright (c) 2021 ZEF
 */
namespace estdigital\adldap;

use Craft;
use craft\base\Plugin;
use estdigital\adldap\models\Settings as SettingsModel;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

class Adldap extends Plugin
{
  // Static Properties
  // =========================================================================
  /**
   * @var static
   */
  public static $plugin;

	public $hasCpSettings       = true;

  // Public Properties
  // =========================================================================
  /**
   * @var string
   */
  public $schemaVersion = '1.0.0';

  // Public Methods
  // =========================================================================
  public function init()
  {

    parent::init();
    self::$plugin = $this;
    
    // Register variable
    Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
      /** @var CraftVariable $variable */
      $variable = $event->sender;
      $variable->set('entryCount', EntryCountVariable::class);
    });
    
    Craft::info(
      Craft::t(
          'adldap',
          '{name} plugin loaded',
          ['name' => $this->name]
      ),
      __METHOD__
    );
  }

  // Remove CP nav item.
  public function getCpNavItem ()
	{
    return;
  }

  // Protected Methods
  // =========================================================================

  /**
   * @inheritdoc
   */
  protected function createSettingsModel() : SettingsModel
  {
    return new SettingsModel();
  }

  /**
	 * @param Event $event
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	public function onRegisterVariable (Event $event)
	{
		/** @var CraftVariable $variable */
		$variable = $event->sender;
		$variable->set('adldap', Variable::class);
	}

  /**
   * @inheritdoc
   */
  protected function settingsHtml()
  {
    return Craft::$app->getView()->renderTemplate('adldap/settings', [
      'settings' => $this->getSettings()
    ]);
  }
}