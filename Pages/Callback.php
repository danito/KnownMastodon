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
                if ($token = $this->getInput('access_token')) {
                    $user = \Idno\Core\site()->session()->currentUser();
                    $server = $user->mastodon['server'];
                    if ($mastodon = \Idno\Core\site()->plugins()->get('Mastodon')) {
                        $mastodonAPI = $mastodon->connect($server);
                        $token_info = $mastodonAPI->getAccessToken($token);
                        $bearer = $token_info['bearer'];
                        $user->mastodon['bearer'] = $bearer;
                        $user->save();

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
