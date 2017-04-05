<?php

    /**
     * Plugin administration
     */

    namespace IdnoPlugins\Mastodon\Pages {

        /**
         * Default class to serve the homepage
         */
        class Callback extends \Idno\Common\Page
        {

            function get($params = array())
            {
                $this->gatekeeper(); // Logged-in users only
                if ($token = $this->getInput('oauth_token')) {
                    if ($mastodon = \Idno\Core\site()->plugins()->get('Mastodon')) {
                        $mastodonAPI = $mastodon->connect();
                        $mastodonAPI->config['user_token'] = \idno\Core\site()->session()->get('oauth')['oauth_token'];
                        $mastodonAPI->config['user_secret'] = \idno\Core\site()->session()->get('oauth')['oauth_token_secret'];

                        $decoded = urldecode($this->getInput('oauth_verifier'));

                        if (!mb_check_encoding($decoded, 'UTF-8')) {
                            $decoded = utf8_encode($decoded);
                        }

                        $code = $mastodonAPI->request('POST', $mastodonAPI->url('oauth/token', ''), array(
                            'oauth_verifier' => urldecode($decoded)
                        ));
                        if ($code == 200) {
                            $access_token = $mastodonAPI->extract_params($mastodonAPI->response['response']);
                            \Idno\Core\site()->session()->remove('oauth');
                            $user = \Idno\Core\site()->session()->currentUser();
                            \Idno\Core\site()->syndication()->registerServiceAccount('mastodon', $access_token['email'], $access_token['email']);
                            $user->mastodon[$access_token['email']] = array('user_token' => $access_token['oauth_token'], 'user_secret' => $access_token['oauth_token_secret'], 'email' => $access_token['email']);
                            $user->save();
                            \Idno\Core\site()->session()->addMessage('Your Mastodon credentials were saved.');
                        }
                        else {
                            \Idno\Core\site()->session()->addErrorMessage('Your Mastodon credentials could not be saved.');
                        }

                        if (!empty($_SESSION['onboarding_passthrough'])) {
                            unset($_SESSION['onboarding_passthrough']);
                            $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'begin/connect-forwarder');
                        }
                        $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'account/mastodon');
                    }
                }
            }

        }

    }
