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

    use Idno\Entities\RemoteUser;
    use Idno\Entities\User;

    /**
     * Default class to serve the homepage
     */
    class Admin extends \Idno\Common\Page {

        function getContent() {
            $this->adminGatekeeper(); // Admins only

            $offset = $this->getInput('offset', 0);
            $limit = $this->getInput('limit', 100);

            $users = User::getFromX(["Idno\\Entities\\User", "Idno\\Entities\\RemoteUser"], [], [], $limit, $offset);
            $count = User::countFromX(["Idno\\Entities\\User", "Idno\\Entities\\RemoteUser"]);

            $t = \Idno\Core\Idno::site()->template();
            $body = $t->__(array('items' => $users, 'count' => $count, 'items_per_page' => $limit))->draw('admin/mastodon');
            $t->__(array('title' => 'Mastodon', 'body' => $body))->drawPage();
        }

        function postContent() {
            $this->adminGatekeeper(); // Admins only
            if (($this->getInput('remove'))) {
                $remove = $this->getInput('remove');

                unset(\Idno\Core\Idno::site()->config()->config['mastodon'][$remove]);
                \Idno\Core\Idno::site()->config()->save();

                \Idno\Core\Idno::site()->session()->addMessage(\Idno\Core\Idno::site()->language()->_('%s instance settings have been removed from your site.', [$remove]));
                $this->forward(\Idno\Core\Idno::site()->config()->getDisplayURL() . 'admin/mastodon/');
            }
        }

    }
}
