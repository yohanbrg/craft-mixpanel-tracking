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

    private function getUTMAndAdClickParameters($request)
    {
        $request = Craft::$app->getRequest();

        $utmParameters = [
            'utm_source'   => $request->getQueryParam('utm_source'),
            'utm_medium'   => $request->getQueryParam('utm_medium'),
            'utm_campaign' => $request->getQueryParam('utm_campaign'),
            'utm_term'     => $request->getQueryParam('utm_term'),
            'utm_content'  => $request->getQueryParam('utm_content'),
            'dclid'        => $request->getQueryParam('dclid'),
            'fbclid'       => $request->getQueryParam('fbclid'),
            'gclid'        => $request->getQueryParam('gclid'),
            'ko_click_id'  => $request->getQueryParam('ko_click_id'),
            'li_fat_id'    => $request->getQueryParam('li_fat_id'),
            'msclkid'      => $request->getQueryParam('msclkid'),
            'ttclid'       => $request->getQueryParam('ttclid'),
            'twclid'       => $request->getQueryParam('twclid'),
            'wbraid'       => $request->getQueryParam('wbraid')
        ];

        $utmAndAdClickCookie = 'ysuad';
        if (!$request->getCookies()->has($utmAndAdClickCookie)) {
            $cookie = new \yii\web\Cookie([
                'name' => $utmAndAdClickCookie,
                'value' => json_encode($utmParameters),
                'expire' => time() + 86400 * 365,
                'domain' => '.youstock.com',
            ]);
            Craft::$app->getResponse()->getCookies()->add($cookie);
        }
    
        return array_filter($utmParameters);
    }

    private function trackPageView()
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        if ($response->statusCode != 200) {
            return;
        }

        // Obtenir l'URL actuelle et analyser ses composants
        $currentUrl = $request->getAbsoluteUrl();
        $urlComponents = parse_url($currentUrl);

        // Exclure les sitemap.xml, robots.txt et les URLs avec un paramètre token
        if (
            in_array($urlComponents['path'], ['/sitemap.xml', '/robots.txt']) ||
            isset($urlComponents['query']) && strpos($urlComponents['query'], 'token=') !== false
        ) {
            return;
        }
        
        $utmAndAdClickParameters = $this->getUTMAndAdClickParameters($request);
        $referrerInfo = $this->getReferrerInfo();

        // Vérifier si l'URL contient une locale
        if ($this->containsLocale($currentUrl)) {
            $deviceId = $this->retrieveOrCreateDeviceId();

            $this->mixpanel->track('page_view', array_merge([
                '$current_url' => $currentUrl,
                '$device_id' => $deviceId,
                '$ip' => $request->getUserIP(),
            ], $utmAndAdClickParameters, $referrerInfo));
        }
    }

    private function containsLocale($url)
    {
        return preg_match('/http[s]?:\/\/.*\/[a-z]{2}-[a-z]{2}/', $url) === 1;
    }

    private function retrieveOrCreateDeviceId()
    {
        $cookieName = 'ysdid';

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
