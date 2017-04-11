<?php

namespace IdnoPlugins\Mastodon {

    class Main extends \Idno\Common\Plugin {

        function registerPages() {
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

        function registerEventHooks() {
            \Idno\Core\Idno::site()->syndication()->registerService('mastodon', function () {
                return $this->hasMastodon();
            }, array('note', 'article', 'image', 'media', 'rsvp', 'bookmark', 'like', 'share'));

            \Idno\Core\Idno::site()->addEventHook('user/auth/success', function (\Idno\Core\Event $event) {
                if ($this->hasMastodon()) {
                    $mastodon = \Idno\Core\Idno::site()->session()->currentUser()->mastodon;
                    if (is_array($mastodon)) {

                        if (array_key_exists('bearer', $mastodon)) {
                            \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $mastodon['server'], $mastodon['server']);
                        }
                    }
                }
            });

            \Idno\Core\Idno::site()->addEventHook('post/note/mastodon', function (\Idno\Core\Event $event) {
                $eventdata = $event->data();
                if ($this->hasMastodon()) {
                    $object = $eventdata['object'];
                    if (!empty($eventdata['syndication_account'])) {
                        $mastodonAPI = $this->connect($eventdata['syndication_account']);
                    } else {
                        $mastodonAPI = $this->connect();
                    }

                    $status_full = trim($object->getDescription());
                    $status = preg_replace('/<[^\>]*>/', '', $status_full); //strip_tags($status_full);
                    $status = str_replace("\r", '', $status);
                    $status = html_entity_decode($status);

                    // TODO:  handle inreply-to
                    // Permalink will be included if the status message is truncated
                    $permalink = $object->getSyndicationURL();
                    // Add link to original post, if IndieWeb references have been requested
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;
                    $lnklen = strlen($permalink);
                    $stlen = strlen($status);
                    if (($lnklen + $stlen) > 500) {
                        $status = $this->truncate($status, (495 - $lnklen));
                    }
                    $params = array('status' => $status);
                    $credentials = $this->getCredentials();
                    $mastodonAPI->setCredentials($credentials);
                    $response = $mastodonAPI->postStatus($params);
                    if (!empty($response)) {
                        if ($json = json_decode($response)) {
                            if (!empty($json->id)) {
                                $object->setPosseLink('mastodon', $json->url, $json->id, $json->account->username);
                                $object->save();
                            } else {
                                \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($json, true));
                                \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                            }
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Bad JSON from Mastodon: " . var_export($json, true));
                        }
                    }
                }
            });

            function truncate($string, $length = 100, $append = "&hellip;") {
                $string = trim($string);

                if (strlen($string) > $length) {
                    $string = wordwrap($string, $length);
                    $string = explode("\n", $string, 2);
                    $string = $string[0] . $append;
                }

                return $string;
            }

            function getCredentials($server = false) {
                if (empty($server)) {
                    $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
                }
                $credentials = array();
                $credentials['client_id'] = \Idno\Core\Idno::site()->config()->mastodon[$server]['client_id'];
                $credentials['client_secret'] = \Idno\Core\Idno::site()->config()->mastodon[$server]['client_secret'];
                $credentials['bearer'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['bearer'];
                return $credentials;
            }

            function connect($server = false) {
                require_once(dirname(__FILE__) . '/autoload.php');
                require_once(dirname(__FILE__) . '/external/PHPMastodon.php');
                if (!empty(\Idno\Core\Idno::site()->config()->mastodon)) {
                    $callback = \Idno\Core\Idno::site()->config()->getDisplayURL() . "mastodon/callback/";
                    if (empty($server) && isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'])) {
                        $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
                    } else {
                        return false;
                    }

                    return new \theCodingCompany\Mastodon($callback, $server);
                }
                return false;
            }

            /**
             * 
             */
            function createApp($server = FALSE) {
                $mastodon = $this;
                $mastodonApi = $mastodon->connect($server);
                $name = \Idno\Core\Idno::site()->config()->getTitle();
                $website_url = \Idno\Core\Idno::site()->config()->getDisplayURL();
                return $mastodonApi->createApp($name, $website_url);
            }

            /**
             * Can the current user use Mastodon?
             * @return bool
             */
            function hasMastodon() {
                if (!\Idno\Core\Idno::site()->session()->currentUser()) {
                    return false;
                }
                if (!empty(\Idno\Core\Idno::site()->session()->currentUser()->mastodon)) {
                    if (is_array(\Idno\Core\Idno::site()->session()->currentUser()->mastodon)) {
                        $accounts = 0;
                        foreach (\Idno\Core\Idno::site()->session()->currentUser()->mastodon as $server => $value) {
                            if (!empty($server['bearer'])) {
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
    
