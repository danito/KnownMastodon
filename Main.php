<?php

namespace IdnoPlugins\Mastodon {

    class Main extends \Idno\Common\Plugin {

        //use \Idno\Core

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
                \Idno\Core\Idno::site()->logging()->log("Mastodon: registerService");

                return $this->hasMastodon();
            }, array('note', 'image'));

            \Idno\Core\Idno::site()->addEventHook('user/auth/success', function (\Idno\Core\Event $event) {
                \Idno\Core\Idno::site()->logging()->log("Mastodon: RegisterEventHook");
                if ($this->hasMastodon()) {
                    $mastodon = \Idno\Core\Idno::site()->session()->currentUser()->mastodon;
                    if (is_array($mastodon)) {

                        if (array_key_exists('bearer', $mastodon)) {
                            \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $mastodon['username'] . "@" . $mastodon['server'], $mastodon['username'] . "@" . $mastodon['server']);
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


                    // TODO:  handle inreply-to
                    // Permalink will be included if the status message is truncated
                    $permalink = $object->getSyndicationURL();
                    // Add link to original post, if IndieWeb references have been requested
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;
                    $lnklen = strlen($permalink);
                    $stlen = strlen($status);
                    if (($stlen) >= 500) {
                        $status = $this->truncate($status, (495 - $lnklen))." ".$permalink;
                    }
                    $params = array('status' => $status);
                    $credentials = $this->getCredentials();
                    $mastodonAPI->setCredentials($credentials);

                    //$userinf = $mastodonAPI->getUser();
                    //\Idno\Core\Idno::site()->logging()->log("Mastodon (getUser): " . var_export($userinf, true));
                    $server = $this->getServer();
                    $response = $mastodonAPI->postStatus($params);
                    if (!empty($response)) {
                        if (!empty($response['id'])) {
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response['url'], $response['account']['username'] . "@" . $server, $response['account']['username'] . "@" . $server);
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($response, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                }
            });

            // Push "images" to Twitter
            \Idno\Core\Idno::site()->addEventHook('post/image/mastodon', function (\Idno\Core\Event $event) {
                if ($this->hasMastodon()) {
                    \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz Mastodon");
                    $eventdata = $event->data();
                    $object = $eventdata['object'];
                    $mastodonAPI = $this->connect();
                    $credentials = $this->getCredentials();
                    $mastodonAPI->setCredentials($credentials);
                    $userinf = $mastodonAPI->getUser();
                    \Idno\Core\Idno::site()->logging()->log("Mastodon (getUser): " . var_export($userinf, true));

                    $server = $this->getServer();
                    $status = $object->getTitle();
                    if ($status == 'Untitled') {
                        //$status = '';
                    }
                    $status = html_entity_decode($status);
                    $media_ids = array();

                    // Let's first try getting the thumbnail
                    if (!empty($object->thumbnail_id)) {
                        if ($thumb = (array) \Idno\Entities\File::getByID($object->thumbnail_id)) {
                            $attachments = array($thumb['file']);
                        }
                    }
                    // No? Then we'll use the main event
                    if (empty($attachments)) {
                        $attachments = $object->getAttachments();
                    }
                    if (!empty($attachments)) {
                        \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz attachments" . var_export($attachments, true));

                        foreach ($attachments as $attachment) {
                            if ($bytes = \Idno\Entities\File::getFileDataFromAttachment($attachment)) {
                                $filename = tempnam(sys_get_temp_dir(), 'knownmastodon');
                                file_put_contents($filename, $bytes);
                                $params['file'] = $filename;
                                $params['bearer'] = $credentials['bearer'];
                                $params['filename'] = basename($filename);
                                $params['mime-type'] = $attachment['mime-type'];
                                $params['server'] = $server;

                                $response = $this->postMedia($params);
                                $content = json_decode($response['content']);
                                if (!empty($content->id)) {
                                    $media_ids[] = $content->id;
                                } else {
                                    \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz no response from mastodon " . $server);
                                }
                            }
                        }
                    }
                    if (!empty($media_ids)) {
                        $params = array('status' => $status,
                            'media_ids' => $media_ids);
                        try {
                            $response = $mastodonAPI->postStatus($params);
                            \Idno\Core\Idno::site()->logging()->log($response);
                        } catch (\Exception $e) {
                            \Idno\Core\Idno::site()->logging()->log($e);
                        }
                    }

                    @unlink($filename);
                    if (!empty($response)) {
                        if (!empty($response['id'])) {
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response['url'], $response['account']['username'] . "@" . $server, $response['account']['username'] . "@" . $server);
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($json, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                } else {
                    \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz no Mastodon");
                }
            });
        }

        function postMedia($params) {
            $file = $params['file'];
            $filename = $params['filename'];
            $mime = $params['mime-type'];
            $instance = "https://" . $params['server'] . '/api/v1/media';
            $bearer = $params['bearer'];
            \Idno\Core\Idno::site()->logging()->log("Mastodon FILE TEST: " . var_export($bearer, true));
            $result = \Idno\Core\Webservice::post($instance, [
                        'file' => \Idno\Core\WebserviceFile::createFromCurlString("@" . $file . ";filename=" . $filename . ";type=" . $mime)
                            //'file' => $file
                            ], [
                        'Accept: application/json',
                        'Authorization: Bearer ' . $bearer . "",
            ]);
            $pp = \Idno\Core\Webservice::getLastRequest();
            \Idno\Core\Idno::site()->logging()->log("Mastodon FILE PPPPPPPPPPPP: " . var_export($pp, true));

            return $result;
        }

        /**
         * @param type $string
         * @param type $length
         * @param type $append
         * @return string

         * */
        function truncate($string, $length = 100, $append = "&hellip;") {
            $string = trim($string);
            $append = html_entity_decode($append);
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
            $credentials['client_id'] = \Idno\Core\Idno::site()->config()->mastodon[$server][0]['client_id'];
            $credentials['client_secret'] = \Idno\Core\Idno::site()->config()->mastodon[$server][0]['client_secret'];
            $credentials['bearer'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['bearer'];
            return $credentials;
        }

        function getServer() {
            if (!empty(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'])) {
                return \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
            }
            return false;
        }

        function connect($server = false) {
            require_once(dirname(__FILE__) . '/autoload.php');
            // require_once(dirname(__FILE__) . '/external/theCodingCompa  ny/Mastodon.php');
            if (!empty(\Idno\Core\Idno::site()->config()->mastodon)) {
                $callback = \Idno\Core\Idno::site()->config()->getDisplayURL() . "mastodon/callback/";
                if (empty($server) && isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'])) {
                    $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
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
    