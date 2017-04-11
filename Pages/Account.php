<?php

/**
 * Plugin administration
 */

namespace IdnoPlugins\Mastodon\Pages {

    /**
     * Default class to serve the homepage
     */
    class Account extends \Idno\Common\Page {

        function getContent() {
            $this->gatekeeper(); // Logged-in users only
            /* if ($mastodon = \Idno\Core\site()->plugins()->get('Mastodon')) {
              $oauth_url = $mastodon->getAuthURL();
              } */
            $oauth_url = \Idno\Core\site()->config()->getDisplayURL() . 'mastodon/auth';
            $t = \Idno\Core\site()->template();
            $body = $t->__(array('oauth_url' => $oauth_url))->draw('account/mastodon');
            $t->__(array('title' => 'Mastodon', 'body' => $body))->drawPage();
        }

        function postContent() {
            $this->gatekeeper(); // Logged-in users only
            if (($this->getInput('remove'))) {
                $user = \Idno\Core\site()->session()->currentUser();
                $user->mastodon = array();
                $user->save();
                \Idno\Core\site()->session()->addMessage('Your Mastodon settings have been removed from your account.');
            }
            if ($this->getInput('login') && $this->getInput('username')) {
                $user = \Idno\Core\site()->session()->currentUser();
                $tmp = explode('@', $this->getInput('username'));
                $login = $this->getInput('login');
                $server = $tmp[1];
                $user->mastodon = array('server' => $server, 'login' => $login, 'username' => $tmp[0], 'bearer' => '');
                $user->save();
                \Idno\Core\Idno::site()->logging()->log("Mastodon debug : Account server: ".$server);
                if (empty(\Idno\Core\Idno::site()->config()->mastodon[$server])) {
                    $mastodon = \Idno\Core\site()->plugins()->get('Mastodon');
                    $mastodonApi = $mastodon->connect($server);
                    $appConfig = $mastodonApi->createApp($server);
                    $authUrl = $mastodonApi->getAuthUrl();
                    $clientID = $appConfig['client_id'];
                    $clientSecret = $appConfig['client_secret'];
                    $knownuser = \Idno\Core\site()->session()->currentUser()->getHandle();
                    $serverConfig = array('name' => $server,
                                            'user' => $knownuser,
                                            'issued_at' => now,
                                            'client_id' => $clientID,
                                            'client_secret' => $clientSecret,
                                            'auth_url' => $authUrl);
                    
                    \Idno\Core\site()->config->config['mastodon'][$server] = array($serverConfig);
                    \Idno\Core\site()->config->save();
                }
            }
            $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'account/mastodon/');
        }

    }

}
