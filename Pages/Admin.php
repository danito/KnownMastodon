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

    }
}
