<?php

    /**
     * Plugin administration
     */

    namespace IdnoPlugins\Mastodon\Pages {

        /**
         * Default class to serve the homepage
         */
        class Admin extends \Idno\Common\Page
        {

            function getContent()
            {
                $this->adminGatekeeper(); // Admins only
                $t = \Idno\Core\site()->template();
                $body = $t->draw('admin/mastodon');
                $t->__(array('title' => 'Mastodon', 'body' => $body))->drawPage();
            }

           /**
           function postContent() {
                $this->adminGatekeeper(); // Admins only
                $client_id = trim($this->getInput('client_id'));
                $client_secret = trim($this->getInput('client_secret'));
                \Idno\Core\site()->config->config['mastodon'] = array(
                    'client_id' => $consumer_key,
                    'client_secret' => $consumer_secret
                );
            **/
                \Idno\Core\site()->config()->save();
                \Idno\Core\site()->session()->addMessage('Mastodon is installed. Check your account settings to connect');
                $this->forward(\Idno\Core\site()->config()->getDisplayURL() . 'admin/mastodon/');
            }

        }

    }
