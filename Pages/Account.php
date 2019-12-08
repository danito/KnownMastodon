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
            $oauth_url = \Idno\Core\Idno::site()->config()->getDisplayURL() . 'mastodon/auth';
            $t = \Idno\Core\Idno::site()->template();

            $body = $t->__(array('oauth_url' => $oauth_url))->draw('account/mastodon');
            $t->__(array('title' => 'Mastodon', 'body' => $body))->drawPage();

        }

        function postContent() {
            $this->gatekeeper(); // Logged-in users only
            if (($this->getInput('cancel'))) {
                unset($_SESSION['mastodon_instance']);
                $rm = $this->getInput('remove');
                $user = \Idno\Core\Idno::site()->session()->currentUser();
                unset($user->mastodon[$rm]); // wipes specific credentials
                $user->save();
                $instance = explode('@', $rm);
                \Idno\Core\Idno::site()->session()->addMessage(\Idno\Core\Idno::site()->language()->_('%s instance settings have been removed from your account.', [$instance[1]]));
                $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'account/mastodon/');
            }
            if (($this->getInput('remove'))) {
                $rm = $this->getInput('remove');
                $user = \Idno\Core\Idno::site()->session()->currentUser();
               // $user->mastodon = array(); // wipes all credentials
                unset($user->mastodon[$rm]); // wipes specific credentials
                $user->save();
                $instance = explode('@', $rm);
               // \Idno\Core\site()->config->config['mastodon'] = array();
               // \Idno\Core\site()->config->save();
                \Idno\Core\Idno::site()->session()->addMessage(\Idno\Core\Idno::site()->language()->_('%s instance settings have been removed from your account.', [$instance[1]]));
                $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'account/mastodon/');
            }
            if ($this->getInput('login') && $this->getInput('username')) {
                $user = \Idno\Core\Idno::site()->session()->currentUser();
                $tmp = explode('@', $this->getInput('username'));
                $login = $this->getInput('login');
                $server = $tmp[1];
                $user->mastodon[$this->getInput('username')] = array('server' => $server, 'login' => $login, 'username' => $tmp[0], 'bearer' => '');
                $user->save();

                $_SESSION['mastodon_instance'] = $this->getInput('username');

                if (empty(\Idno\Core\Idno::site()->config()->mastodon)) {
                    \Idno\Core\Idno::site()->config()->mastodon = array('mastodon' => true);
                    \Idno\Core\Idno::site()->config()->save();
                }
                if (empty(\Idno\Core\Idno::site()->config()->mastodon[$server])) {

                    $mastodon = \Idno\Core\Idno::site()->plugins()->get('Mastodon');
                    $mastodonApi = $mastodon->connect($server);
                    $name = \Idno\Core\Idno::site()->config()->getTitle();
                    $website_url = \Idno\Core\Idno::site()->config()->getDisplayURL();
                    $appConfig = $mastodonApi->createApp($name, $website_url);

                    $authUrl = $mastodonApi->getAuthUrl();
                    $clientID = $appConfig['client_id'];
                    $clientSecret = $appConfig['client_secret'];
                    $knownuser = \Idno\Core\Idno::site()->session()->currentUser()->getHandle();
                    $serverConfig = array('name' => $server,
                        'user' => $knownuser,
                        'issued_at' => time(),
                        'client_id' => $clientID,
                        'client_secret' => $clientSecret,
                        'auth_url' => $authUrl);

                    \Idno\Core\Idno::site()->config()->config['mastodon'][$server] = array($serverConfig);
                    \Idno\Core\Idno::site()->config()->save();

                    } else {

                    }
            } else {
                \Idno\Core\Idno::site()->logging()->log("Mastodon debug : account no input : ");
            }
            $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'account/mastodon/');
        }

    }

}
