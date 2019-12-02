<?php

    /**
     * Plugin administration
     */

    namespace IdnoPlugins\Mastodon\Pages {

        /**
         * Default class to serve the homepage
         */
        class Auth extends \Idno\Common\Page
        {

            function getContent()
            {
                $this->gatekeeper(); // Logged-in users only
                if ($mastodon = \Idno\Core\Idno::site()->plugins()->get('Mastodon')) {
                    $login_url = $mastodon->getAuthURL();
                    if (!empty($login_url)) {
                        $this->forward($login_url); exit;
                    }
                }
                $this->forward($_SERVER['HTTP_REFERER']);
            }

            function postContent() {
                $this->getContent();
            }

        }

    }
