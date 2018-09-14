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
                $user->mastodon = array(); // wipes all credentials
                $user->save();
               // \Idno\Core\site()->config->config['mastodon'] = array();
               // \Idno\Core\site()->config->save();
                \Idno\Core\site()->session()->addMessage('Your Mastodon settings have been removed from your account.');
                $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'account/mastodon/');
            }
            if ($this->getInput('login') && $this->getInput('username')) {
                $user = \Idno\Core\site()->session()->currentUser();
                $tmp = explode('@', $this->getInput('username'));
                $login = $this->getInput('login');
                $server = $tmp[1];
                $user->mastodon[$this->getInput('username')] = array('server' => $server, 'login' => $login, 'username' => $tmp[0], 'bearer' => '');
                $user->save();

                $_SESSION['mastodon_instance'] = $this->getInput('username');

                if (empty(\Idno\Core\Idno::site()->config()->mastodon)) {
                    \Idno\Core\Idno::site()->config()->mastodon = array('mastodon' => true);
                    \Idno\Core\site()->config->save();
                }
                if (empty(\Idno\Core\Idno::site()->config()->mastodon[$server])) {

                    $mastodon = \Idno\Core\site()->plugins()->get('Mastodon');
                    $mastodonApi = $mastodon->connect($server);
                    $name = \Idno\Core\Idno::site()->config()->getTitle();
                    $website_url = \Idno\Core\Idno::site()->config()->getDisplayURL();
                    $appConfig = $mastodonApi->createApp($name, $website_url);

                    $authUrl = $mastodonApi->getAuthUrl();
                    $clientID = $appConfig['client_id'];
                    $clientSecret = $appConfig['client_secret'];
                    $knownuser = \Idno\Core\site()->session()->currentUser()->getHandle();
                    $serverConfig = array('name' => $server,
                        'user' => $knownuser,
                        'issued_at' => time(),
                        'client_id' => $clientID,
                        'client_secret' => $clientSecret,
                        'auth_url' => $authUrl);

                    \Idno\Core\site()->config->config['mastodon'][$server] = array($serverConfig);
                    \Idno\Core\site()->config->save();

                    } else {

                    }
            } else {
                \Idno\Core\Idno::site()->logging()->log("Mastodon debug : account no input : ");
            }
            $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'account/mastodon/');
        }

    }

}
