<?php
    namespace IdnoPlugins\Mastodon {
        class Main extends \Idno\Common\Plugin
        {
            function registerPages()
            {
                // Auth URL
                \Idno\Core\Idno::site()->addPageHandler('mastodon/auth', '\IdnoPlugins\Twitter\Pages\Auth');
                // Deauth URL
                \Idno\Core\Idno::site()->addPageHandler('mastodon/deauth', '\IdnoPlugins\Mastodon\Pages\Deauth');
                // Register the callback URL
                \Idno\Core\Idno::site()->addPageHandler('mastodon/callback', '\IdnoPlugins\Mastodon\Pages\Callback');
                // Register admin settings
                \Idno\Core\Idno::site()->addPageHandler('admin/mastodon', '\IdnoPlugins\Mastodon\Pages\Admin');
                // Register settings page
                \Idno\Core\Idno::site()->addPageHandler('account/mastodon', '\IdnoPlugins\Mastodon\Pages\Account');
                /** Template extensions */
                // Add menu items to account & administration screens
                \Idno\Core\Idno::site()->template()->extendTemplate('admin/menu/items', 'admin/mastodon/menu');
                \Idno\Core\Idno::site()->template()->extendTemplate('account/menu/items', 'account/mastodon/menu');
                \Idno\Core\Idno::site()->template()->extendTemplate('onboarding/connect/networks', 'onboarding/connect/mastodon');
            }
            
             function registerEventHooks()
            {
                \Idno\Core\Idno::site()->syndication()->registerService('mastodon', function () {
                    return $this->hasMastodon();
                }, array('note', 'article', 'image', 'media', 'rsvp', 'bookmark', 'like', 'share'));
                
                \Idno\Core\Idno::site()->addEventHook('user/auth/success', function (\Idno\Core\Event $event) {
                    if ($this->hasMastodon()) {
                        $mastodon = \Idno\Core\Idno::site()->session()->currentUser()->mastodon;
                        if (is_array($mastodon)) {
                            foreach($mastodon as $username => $details) {
                                if (!in_array($username, ['user_token','user_secret','screen_name'])) {
                                    \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $username, $username);
                                }
                            }
                            if (array_key_exists('user_token', $mastodon)) {
                                \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $mastodon['screen_name'], $mastodon['screen_name']);
                            }
                        }
                    }
                });
                
                
                  /**
             * Can the current user use Mastodon?
             * @return bool
             */
            function hasMastodon()
            {
                if (!\Idno\Core\Idno::site()->session()->currentUser()) {
                    return false;
                }
                if (!empty(\Idno\Core\Idno::site()->session()->currentUser()->mastodon)) {
                    if (is_array(\Idno\Core\Idno::site()->session()->currentUser()->mastodon)) {
                        $accounts = 0;
                        foreach(\Idno\Core\Idno::site()->session()->currentUser()->mastodon as $username => $value) {
                            if ($mastodon != 'user_token') {
                                $accounts++;
                            }
                        }
                        if ($accounts > 0) {
                            return true;
                        }
                    }
                    return true;
                }
                return false;
            }
        }
                
            }
            
            
        }
    }
