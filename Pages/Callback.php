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
                $user = \Idno\Core\Idno::site()->session()->currentUser();
                $_server = $_SESSION['mastodon_instance'];
                unset($_SESSION['mastodon_instance']);
                $mastodon = $user->mastodon[$_server];
                $server = $mastodon['server'];

                if ($mastodon = \Idno\Core\Idno::site()->plugins()->get('Mastodon')) {
                    $mastodonAPI = $mastodon->connect($server);
                    $credentials = $mastodon->getCredentials($server);
                    $mastodonAPI->setCredentials($credentials);
                    $testcreds = $mastodonAPI->getCredentials();
                    \Idno\Core\Idno::site()->logging()->log("Mastodon: DEBUG callback credentials " . $server . " " . var_export($testcreds, true) . " /DEBUG");
                    $token_info = $mastodonAPI->getAccessToken($token);
                    $user->mastodon[$_server]['bearer'] = $token_info;
                    $user->save();

                    if (!empty($_SESSION['onboarding_passthrough'])) {
                        unset($_SESSION['onboarding_passthrough']);
                        $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'begin/connect-forwarder');
                    }
                    $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'account/mastodon');
                } else {
                    \Idno\Core\Idno::site()->logging()->log("Mastodon: DEBUG callback  " . $server . " " . var_export($mastodon, true) . " /DEBUG");
                }
            }
        }

    }

}
