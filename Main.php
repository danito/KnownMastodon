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

                return $this->hasMastodon();
            }, array('article','note', 'image', 'bookmark'));
            
            //array('note', 'article', 'image', 'media', 'rsvp', 'bookmark', 'like', 'share'));

            \Idno\Core\Idno::site()->addEventHook('user/auth/success', function (\Idno\Core\Event $event) {
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
                    $tags = $object->getTags();
                    $tags = array_map('strtolower', $tags);
                    $nfsw = false;
                    if (!empty($tags) && in_array("#nsfw", $tags)) {
                        $nfsw = true;
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
                    $status = $this->truncate($status, $permalink, $permashortlink);

                    $statuses = array('status' => $status,
                        'sensitive' => $nfsw);

                    $server = $this->getServer();

                    $res = $this->postStatus($statuses);
                    $response = json_decode($res['content']);
                    $id = $response->id;
                    if (!empty($response)) {
                        if (!empty($id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response->url, $mastodon_user, $mastodon_user);
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
                    $eventdata = $event->data();
                    $object = $eventdata['object'];
                    $mastodonAPI = $this->connect();
                    $server = $this->getServer();
                    $status = $object->getTitle();
                    $status = html_entity_decode($status);
                    // Permalink will be included if the status message is truncated
                    $permalink = $object->getSyndicationURL();
                    // Add link to original post, if IndieWeb references have been requested
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;
                    
                    $status = $this->truncate($status, $permalink, $permashortlink);
                    
                    $media_ids = array();
                    $tags = $object->getTags();
                    $tags = array_map('strtolower', $tags);
                    $nsfw = false;
                    if (!empty($tags) && in_array("#nsfw", $tags)) {
                        $nsfw = true;
                    }

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

                        foreach ($attachments as $attachment) {
                            if ($bytes = \Idno\Entities\File::getFileDataFromAttachment($attachment)) {
                                $filename = tempnam(sys_get_temp_dir(), 'knownmastodon');
                                file_put_contents($filename, $bytes);
                                $params['file'] = $filename;
                                $params['filename'] = basename($filename);
                                $params['mime-type'] = $attachment['mime-type'];
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
                            'media_ids' => $media_ids,
                            'sensititve' => $nsfw);
                        try {
                            $res = $this->postStatus($statuses);
                            $response = json_decode($res['content']);                            //\Idno\Core\Idno::site()->logging()->log($response);
                        } catch (\Exception $e) {
                            \Idno\Core\Idno::site()->logging()->log($e);
                        }
                    }

                    @unlink($filename);
                    if (!empty($response)) {
                        if (!empty($response->id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response->url, $mastodon_user, $mastodon_user);
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($response, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                } else {
                    \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz no Mastodon");
                }
            });


            // Function for articles, RSVPs etc
            $article_handler = function (\Idno\Core\Event $event) {
                if ($this->hasMastodon()) {
                    $eventdata = $event->data();
                    $object = $eventdata['object'];
                    $server = $this->getServer();
                    $status = $object->getTitle();
                    $permalink = $object->getSyndicationURL();
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;
                    \Idno\Core\Idno::site()->logging()->log("Mastodon PERMALINK: " . var_export($permalink, true));

                    $status = html_entity_decode($status);
                    
                    $status = $this->truncate($status, $permalink, $permashortlink);
                    $statuses = array('status' => $status);

                    $res = $this->postStatus($statuses);
                    $response = json_decode($res['content']);
                    $id = $response->id;
                    if (!empty($response)) {
                        if (!empty($id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response->url, $mastodon_user, $mastodon_user);
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($response, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                }
            };

            // Push "articles" and "rsvps" to Twitter
            \Idno\Core\Idno::site()->addEventHook('post/article/mastodon', $article_handler);
            \Idno\Core\Idno::site()->addEventHook('post/rsvp/mastodon', $article_handler);
            \Idno\Core\Idno::site()->addEventHook('post/bookmark/mastodon', $article_handler);
        }

        function postStatus($status) {

            // split text at || for content warning
            $cwstatus = explode("||", $status['status'], 2);
            if (!empty($cwstatus[1])) {
                $status['status'] = $cwstatus[1];
                $status['spoiler_text'] = $cwstatus[0];
            }

            $server = $this->getServer();
            $credentials = $this->getCredentials();
            $bearer = $credentials['bearer'];
            $instance = "https://" . $server . '/api/v1/statuses';
            $headers = array('Accept: application/json',
                'Authorization: Bearer ' . $bearer . "");
            $result = \Idno\Core\Webservice::post($instance, $status, $headers);

            return $result;
        }

        function postMedia($params) {
            $file = $params['file'];
            $filename = $params['filename'];
            $mime = $params['mime-type'];
            $server = $this->getServer();
            $credentials = $this->getCredentials();
            $bearer = $credentials['bearer'];
            $instance = "https://" . $server . '/api/v1/media';
            $result = \Idno\Core\Webservice::post($instance, [
                        'file' => \Idno\Core\WebserviceFile::createFromCurlString("@" . $file . ";filename=" . $filename . ";type=" . $mime)
                            //'file' => $file
                            ], [
                        'Accept: application/json',
                        'Authorization: Bearer ' . $bearer . "",
            ]);

            return $result;
        }

        /**
         * 
         * @param string $string
         * @param string $permalink
         * @param string $shortlink
         * @param int $length
         * @return string
         */
        function truncate($status, $permalink = false, $shortlink = false, $length = 500) {
            $status = trim($status);
            //disabling permashortlink for now
            $shortlink = false;
            if ($permalink) {
                $permalink = ": " . $permalink;
                $length = $length - strlen($permalink);
            }
            if ($shortlink) {
                $shortlink = " " . $shortlink;
                $length = $length - strlen($shortlink);
            }
            $hellip = mb_convert_encoding('&hellip;', 'UTF-8', 'HTML-ENTITIES');
            $length = $length - strlen($hellip);

            if (strlen($status) > $length) {
                $status = wordwrap($status);
                $string = explode("\n", $status, 2);
                $status = $string[0] . $hellip;
                $status = $status . $permalink ;
            }
            //$status = $status . $permalink . $shortlink;

            return $status;
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
    