<?php

/**
 * Plugin administration
 *
 */
/**
 * TODO :
 * Display all Mastodon servers from different users
 */

namespace IdnoPlugins\Mastodon\Pages {

    /**
     * Default class to serve the homepage
     */
    class Admin extends \Idno\Common\Page {

        function getContent() {
            $this->adminGatekeeper(); // Admins only
            $t = \Idno\Core\Idno::site()->template();
            $body = $t->draw('admin/mastodon');
            $t->__(array('title' => 'Mastodon', 'body' => $body))->drawPage();
        }

        function postContent() {
            $this->adminGatekeeper(); // Admins only
            if (($this->getInput('remove'))) {
                $remove = $this->getInput('remove');
/*
                unset(\Idno\Core\Idno::site()->config()->config['mastodon'][$remove]);
                \Idno\Core\Idno::site()->config()->save();
*/
                \Idno\Core\Idno::site()->session()->addMessage($remove . ' instance settings have been removed from your site.');
                $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'admin/mastodon/');
            }
        }

    }
}
