<?php

/**
 * Plugin administration
 */

namespace IdnoPlugins\Mastodon\Pages {

    /**
     * Default class to serve the homepage
     */
    class Callback extends \Idno\Common\Page {

        function get($params = array()) {
            $this->gatekeeper(); // Logged-in users only
            if ($token = $this->getInput('code')) {
                $user = \Idno\Core\site()->session()->currentUser();
                $mastodon = $user->mastodon;
                $server = $mastodon['server'];

                if ($mastodon = \Idno\Core\site()->plugins()->get('Mastodon')) {
                    $mastodonAPI = $mastodon->connect($server);
                    $credentials = $mastodon->getCredentials($server);
                    $mastodonAPI->setCredentials($credentials);
                    $testcreds = $mastodonAPI->getCredentials();
                    \Idno\Core\Idno::site()->logging()->log("Mastodon: DEBUG callback credentials " . $server . " " . var_export($testcreds, true) . " /DEBUG");
                    $token_info = $mastodonAPI->getAccessToken($token);
                    $user->mastodon['bearer'] = $token_info;
                    $user->save();

                    if (!empty($_SESSION['onboarding_passthrough'])) {
                        unset($_SESSION['onboarding_passthrough']);
                        $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'begin/connect-forwarder');
                    }
                    $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'account/mastodon');
                } else {
                    \Idno\Core\Idno::site()->logging()->log("Mastodon: DEBUG callback  " . $server . " " . var_export($mastodon, true) . " /DEBUG");
                }
            }
        }

    }

}
