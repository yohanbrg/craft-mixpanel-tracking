<?php

namespace yohanbrg\craftmixpaneltracking;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use Mixpanel;
use yii\base\Event;
use yohanbrg\craftmixpaneltracking\models\Settings;

/**
 * Mixpanel Tracking plugin
 *
 * @method static MixpanelTracking getInstance()
 */
class MixpanelTracking extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public ?Mixpanel $mixpanel = null;

    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return \Craft::$app->getView()->renderTemplate(
            '_mixpanel-tracking/settings',
            ['settings' => $this->getSettings()]
        );
    }

    public function init(): void
    {
        parent::init();
        $this->initMixpanel();
        Event::on(
            \craft\web\Application::class,
            \craft\web\Application::EVENT_INIT,
            function () {
                $this->trackPageView();
            }
        );

        $this->setSettings(Craft::$app->config->getConfigFromFile('mixpanel-tracking'));
    }

    private function initMixpanel()
    {
        $token = $this->settings->token;
        $this->mixpanel = Mixpanel::getInstance($token, array("host" => "api-eu.mixpanel.com"));
    }

    private function getUTMParameters()
    {
        $request = Craft::$app->getRequest();

        $utmParameters = [
            'utm_source'   => $request->getQueryParam('utm_source'),
            'utm_medium'   => $request->getQueryParam('utm_medium'),
            'utm_campaign' => $request->getQueryParam('utm_campaign'),
            'utm_term'     => $request->getQueryParam('utm_term'),
            'utm_content'  => $request->getQueryParam('utm_content')
        ];

        return array_filter($utmParameters);
    }

    private function trackPageView()
    {
        $utmParameters = $this->getUTMParameters();
        $referrerInfo = $this->getReferrerInfo();
        $request = Craft::$app->getRequest();

        if (!$request->getIsConsoleRequest() && !$request->getIsAjax() && $request->getIsGet()) {
            $currentPageUrl = $request->getAbsoluteUrl();
            // Vérifier si l'URL contient une locale
            if ($this->containsLocale($currentPageUrl)) {
                $currentSiteTitle = Craft::$app->getSites()->getCurrentSite()->name;
                $deviceId = $this->retrieveOrCreateDeviceId();

                $this->mixpanel->track('page_view', array_merge([
                    '$current_url' => $currentPageUrl,
                    'title' => $currentSiteTitle,
                    '$device_id' => $deviceId,
                    '$ip' => $request->getUserIP(),
                ], $utmParameters, $referrerInfo));
            }
        }
    }

    private function containsLocale($url)
    {
        return preg_match('/https?:\/\/www\.youstock\.com\/[a-z]{2}-[a-z]{2}/', $url) === 1;
    }


    private function retrieveOrCreateDeviceId()
    {
        $cookieName = 'YouStockDeviceId';

        $request = Craft::$app->getRequest();
        $deviceId = $request->getCookies()->getValue($cookieName);

        if (!$deviceId) {
            $deviceId = bin2hex(random_bytes(16));
            $cookie = new \yii\web\Cookie([
                'name' => $cookieName,
                'value' => $deviceId,
                'expire' => time() + 86400 * 365,
                'domain' => '.youstock.com',
            ]);

            Craft::$app->getResponse()->getCookies()->add($cookie);
        }

        return $deviceId;
    }

    private function getReferrerInfo()
    {
        $request = Craft::$app->getRequest();
        $referrerUrl = $request->getReferrer();

        if ($referrerUrl) {
            $referrerData = parse_url($referrerUrl);
            return [
                '$referrer' => $referrerUrl,
                '$referring_domain' => $referrerData['host'] ?? null
            ];
        }

        return [];
    }
}
