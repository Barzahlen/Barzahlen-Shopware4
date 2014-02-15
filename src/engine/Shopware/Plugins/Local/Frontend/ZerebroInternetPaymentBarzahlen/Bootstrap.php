<?php
/**
 * Barzahlen Payment Module (Shopware 4)
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; version 3 of the License
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/
 *
 * @copyright   Copyright (c) 2012 Zerebro Internet GmbH (http://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/AGPL-3.0  GNU Affero General Public License, version 3 (GPL-3.0)
 */

class Shopware_Plugins_Frontend_ZerebroInternetPaymentBarzahlen_Bootstrap extends Shopware_Components_Plugin_Bootstrap {

  const CURRENT_VERSION = '1.0.1';

  /**
   * Install methods. Calls sub methods for a successful installation.
   *
   * @return boolean
   */
  public function install() {

    $this->createEvents();
    $this->createPayment();
    $this->createRules();
    $this->createForm();
    $this->createTranslations();

    return true;
  }

  /**
   * Subscribes to events in order to run plugin code.
   */
  protected function createEvents() {

    $event = $this->createEvent('Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentBarzahlen', 'onGetControllerPathFrontend');
    $this->subscribeEvent($event);

    $event = $this->createEvent('Enlight_Controller_Action_Frontend_PaymentBarzahlen_Notify', 'onNotification');
    $this->subscribeEvent($event);

    $event = $this->createEvent('Enlight_Controller_Action_Frontend_Checkout_Finish', 'onCheckoutSuccess');
    $this->subscribeEvent($event);

    $event = $this->createEvent('Enlight_Controller_Action_Frontend_Account_Payment', 'onSelectPaymentMethod');
    $this->subscribeEvent($event);

    $event = $this->createEvent('Enlight_Controller_Action_Frontend_Checkout_Confirm', 'onCheckoutConfirm');
    $this->subscribeEvent($event);
  }

  /**
   * Creates a new or updates the old payment entry for the database.
   */
  public function createPayment() {

    $getOldPayments = $this->Payment();

    if (!empty($getOldPayments['id'])) {
      $where   = array('id = '.(int)$getOldPayments['id']  );
      Shopware()->Payments()->delete($where);
    }

    $settings = array('name' => 'barzahlen',
                      'description' => 'Barzahlen',
                      'action' => 'payment_barzahlen',
                      'active' => 1,
                      'position' => 1,
                      'pluginID' => $this->getId());

    Shopware()->Payments()->createRow($settings)->save();
  }

  /**
   * Sets rules for Barzahlen payment.
   * Country = DE
   * max. Order Amount < 1000 Euros
   */
  public function createRules() {

    $payment = $this->Payment();

    $rules = "INSERT INTO s_core_rulesets
              (paymentID, rule1, value1)
              VALUES
              ('".(int)$payment['id']."', 'ORDERVALUEMORE', '1000'),
              ('".(int)$payment['id']."', 'LANDISNOT', 'DE'),
              ('".(int)$payment['id']."', 'CURRENCIESISOISNOT', 'EUR')";

    Shopware()->Db()->query($rules);

  }

  /**
   * Creates the settings form for the backend.
   */
  protected function createForm() {
    $form = $this->Form();

    $form->setElement('boolean', 'barzahlenSandbox', array(
      'label' => 'Testmodus',
      'value' => true,
      'required' => true,
      'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
    ));

    $form->setElement('number', 'barzahlenShopId', array(
      'label' => 'Shop ID',
      'value' => '',
      'required' => true,
      'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
    ));

    $form->setElement('text', 'barzahlenPaymentKey', array(
      'label' => 'Zahlungsschlüssel',
      'value' => '',
      'required' => true,
      'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
    ));

    $form->setElement('text', 'barzahlenNotificationKey', array(
      'label' => 'Benachrichtigungsschlüssel',
      'value' => '',
      'required' => true,
      'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
    ));

    $form->setElement('boolean', 'barzahlenDebug', array(
      'label' => 'Erweitertes Logging',
      'value' => false,
      'required' => true,
      'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
    ));
  }

  /**
   * Sets translations for plugin text phrases.
   */
  protected function createTranslations()
  {
    $form = $this->Form();
    $translations = array(
      'de_DE' => array(
        'barzahlenSandbox' => 'Testmodus',
        'barzahlenShopId' => 'Shop ID',
        'barzahlenPaymentKey' => 'Zahlungsschlüssel',
        'barzahlenNotificationKey' =>'Benachrichtigungsschlüssel',
        'barzahlenDebug' => 'Erweitertes Logging'
      ),
      'en_GB' => array(
        'barzahlenSandbox' => 'Sandbox',
        'barzahlenShopId' => 'Shop ID',
        'barzahlenPaymentKey' => 'Payment Key',
        'barzahlenNotificationKey' =>'Notification Key',
        'barzahlenDebug' => 'Extended Logging'
      )
    );

    $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');
    foreach($translations as $locale => $snippets) {
      $localeModel = $shopRepository->findOneBy(array(
        'locale' => $locale
      ));
      foreach($snippets as $element => $snippet) {
        if($localeModel === null){
          continue;
        }
        $elementModel = $form->getElement($element);
        if($elementModel === null) {
          continue;
        }
        $translationModel = new \Shopware\Models\Config\ElementTranslation();
        $translationModel->setLabel($snippet);
        $translationModel->setLocale($localeModel);
        $elementModel->addTranslation($translationModel);
      }
    }
  }

  /**
   * Performs the uninstallation of the payment plugin.
   *
   * @return boolean
   */
  public function uninstall() {

    $payment = $this->Payment();

    Shopware()->Db()->query("DELETE FROM s_core_paymentmeans WHERE id = '".(int)$payment['id']."'");
    Shopware()->Db()->query("DELETE FROM s_core_rulesets WHERE paymentID = '".(int)$payment['id']."'");

    return true;
  }

  /**
   * Enables the payment method.
   *
   * @return parent return
   */
  public function enable() {

    $payment = $this->Payment();
    $payment->active = 1;
    $payment->save();

    return parent::enable();
  }

  /**
   * Disables the payment method.
   *
   * @return parent return
   */
  public function disable() {

    $payment = $this->Payment();
    $payment->active = 0;
    $payment->save();

    return parent::disable();
  }

  /**
   * Gathers all information for the backend overview of the plugin.
   *
   * @return array with all information
   */
  public function getInfo() {
    $img = 'http://cdn.barzahlen.de/images/barzahlen_logo.png';
    return array(
      'version' => $this->getVersion(),
      'autor' => 'Zerebro Internet GmbH',
      'label' => "Barzahlen Payment Module",
      'source' => "Local",
      'description' => '<p><img src="' . $img . '" alt="Barzahlen" /></p> <p>Barzahlen bietet Ihren Kunden die Möglichkeit, online bar zu bezahlen. Sie werden in Echtzeit über die Zahlung benachrichtigt und profitieren von voller Zahlungsgarantie und neuen Kundengruppen. Sehen Sie wie Barzahlen funktioniert: <a href="http://www.barzahlen.de/partner/funktionsweise" target="_blank">http://www.barzahlen.de/partner/funktionsweise</a></p>',
      'license' => 'GNU GPL v3.0',
      'copyright' => 'Copyright © 2012, Zerebro Internet GmbH',
      'support' => 'support@barzahlen.de',
      'link' => 'http://www.barzahlen.de'
    );
  }

  /**
   * Returns the currennt plugin version.
   *
   * @return string with current version
   */
  public function getVersion() {

    return self::CURRENT_VERSION;
  }

  /**
   * Selects all payment method information from the database.
   *
   * @return payment method information
   */
  public function Payment() {

    return Shopware()->Payments()->fetchRow(array('name=?' => 'barzahlen'));
  }

  /**
   * Calls the payment constructor when frontend event fires.
   *
   * @param Enlight_Event_EventArgs $args
   * @return string with path to payment controller
   */
  public function onGetControllerPathFrontend(Enlight_Event_EventArgs $args) {
    return dirname(__FILE__) . '/Controllers/Frontend/PaymentBarzahlen.php';
  }

  /**
   * Sets empty template file to avoid errors.
   *
   * @param Enlight_Event_EventArgs $args
   */
  public function onNotification(Enlight_Event_EventArgs $args) {

    $view = $args->getSubject()->View();
    $view->addTemplateDir(dirname(__FILE__) . '/Views/');
    $view->extendsTemplate('frontend/payment_barzahlen/notify.tpl');
  }

  /**
   * Prepares checkout success page with received payment slip information.
   *
   * @param Enlight_Event_EventArgs $args
   */
  public function onCheckoutSuccess(Enlight_Event_EventArgs $args) {

    if(isset(Shopware()->Session()->BarzahlenResponse)) {
      $view = $args->getSubject()->View();
      $view->addTemplateDir(dirname(__FILE__) . '/Views/');
      $view->assign('infotext1', Shopware()->Session()->BarzahlenResponse['infotext-1']);
      $view->extendsTemplate('frontend/payment_barzahlen/finish.tpl');
      unset(Shopware()->Session()->BarzahlenResponse);
    }
  }

  /**
   * Setting payment method selection payment description depending on sandbox
   * settings in payment config.
   *
   * @param Enlight_Event_EventArgs $args
   */
  public function onSelectPaymentMethod(Enlight_Event_EventArgs $args) {

    $payment = $this->Payment();
    $config = Shopware()->Plugins()->Frontend()->ZerebroInternetPaymentBarzahlen()->Config();

    $description = '<img src="http://cdn.barzahlen.de/images/barzahlen_logo.png" style="height: 45px;"/><br/>';
    $description .= '<p id="payment_desc">Mit Abschluss der Bestellung bekommen Sie einen Zahlschein angezeigt, den Sie sich ausdrucken oder auf Ihr Handy schicken lassen können. Bezahlen Sie den Online-Einkauf mit Hilfe des Zahlscheins an der Kasse einer Barzahlen-Partnerfiliale.';

    if($config->barzahlenSandbox) {
      $description .= '<br/><br/>Der <strong>Sandbox Modus</strong> ist aktiv. Allen getätigten Zahlungen wird ein Test-Zahlschein zugewiesen. Dieser kann nicht von unseren Einzelhandelspartnern verarbeitet werden.';
    }

    $description .= '</p>';
    $description .= '<b>Bezahlen Sie bei:</b>&nbsp;';

    for($i = 1; $i <= 10; $i++) {
      $count = str_pad($i,2,"0",STR_PAD_LEFT);
      $description .= '<img src="http://cdn.barzahlen.de/images/barzahlen_partner_'.$count.'.png" alt="" style="vertical-align: middle; height: 25px;" />';
    }

    $newData = array('additionaldescription' => $description);
    $where   = array('id = '.(int)$payment['id']);

    Shopware()->Payments()->update($newData, $where);
  }

  /**
   * Extending checkout/confirm template to show Barzahlen Payment Error, if
   * necessary.
   *
   * @param Enlight_Event_EventArgs $args
   */
  public function onCheckoutConfirm(Enlight_Event_EventArgs $args) {

    if(isset(Shopware()->Session()->BarzahlenPaymentError)) {
      $view = $args->getSubject()->View();
      $view->addTemplateDir(dirname(__FILE__) . '/Views/');
      $view->assign('BarzahlenPaymentError', Shopware()->Session()->BarzahlenPaymentError);
      $view->extendsTemplate('frontend/payment_barzahlen/error.tpl');
      unset(Shopware()->Session()->BarzahlenPaymentError);
    }
  }
}