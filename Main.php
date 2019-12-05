<?php

namespace IdnoPlugins\Mastodon {

    class Main extends \Idno\Common\Plugin {

        //use \Idno\Core

        function registerTranslations()
        {
            \Idno\Core\Idno::site()->language()->register(
               new \Idno\Core\GetTextTranslation(
                   'mastodon', dirname(__FILE__) . '/languages/'
               )
            );
        }

        function registerPages() {
            // Auth URL
            //\Idno\Core\Idno::site()->addPageHandler('mastodon/auth', '\IdnoPlugins\Mastodon\Pages\Auth');
            \Idno\Core\Idno::site()->routes()->addRoute('mastodon/auth', '\IdnoPlugins\Mastodon\Pages\Auth');
            // Deauth URL
            \Idno\Core\Idno::site()->routes()->addRoute('mastodon/deauth', '\IdnoPlugins\Mastodon\Pages\Deauth');
            // Register the callback URL
            \Idno\Core\Idno::site()->routes()->addRoute('mastodon/callback', '\IdnoPlugins\Mastodon\Pages\Callback');
            // Register admin settings
            \Idno\Core\Idno::site()->routes()->addRoute('admin/mastodon', '\IdnoPlugins\Mastodon\Pages\Admin');
            // Register settings page
            \Idno\Core\Idno::site()->routes()->addRoute('account/mastodon', '\IdnoPlugins\Mastodon\Pages\Account');
            /** Template extensions */
            // Add menu items to account & administration screens
            \Idno\Core\Idno::site()->template()->extendTemplate('admin/menu/items', 'admin/mastodon/menu');
            \Idno\Core\Idno::site()->template()->extendTemplate('account/menu/items', 'account/mastodon/menu');
            // \Idno\Core\Idno::site()->template()->extendTemplate('onboarding/connect/networks', 'onboarding/connect/mastodon');
        }

        function registerEventHooks() {
            \Idno\Core\Idno::site()->syndication()->registerService('mastodon', function () {

                return $this->hasMastodon();
            }, array('article', 'note', 'image', 'bookmark'));

            //array('note', 'article', 'image', 'media', 'rsvp', 'bookmark', 'like', 'share'));

            //\Idno\Core\Idno::site()->addEventHook('user/auth/success', function (\Idno\Core\Event $event) {
            \Idno\Core\Idno::site()->events()->addListener('user/auth/success', function (\Idno\Core\Event $event) {
                if ($this->hasMastodon()) {
                    $mastodon = \Idno\Core\Idno::site()->session()->currentUser()->mastodon;
                    if (is_array($mastodon)) {
                        foreach($mastodon as $username => $details) {
                            if (!in_array($username, ['bearer','server','username'])) {
                                \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $username, $username);
                            }
                        }

                        if (array_key_exists('bearer', $mastodon)) {
                            \Idno\Core\Idno::site()->syndication()->registerServiceAccount('mastodon', $mastodon['username'] . "@" . $mastodon['server'], $mastodon['username'] . "@" . $mastodon['server']);
                        }
                    }
                }
            });

            //\Idno\Core\Idno::site()->addEventHook('post/note/mastodon', function (\Idno\Core\Event $event) {
            \Idno\Core\Idno::site()->events()->addListener('post/note/mastodon', function (\Idno\Core\Event $event) {
                $eventdata = $event->data();
                if ($this->hasMastodon()) {
                    $object = $eventdata['object'];
                    $object_type = $eventdata['object_type'];

                    if (!empty($eventdata['syndication_account'])) {
                        $username = $eventdata['syndication_account'];
                        $screenName = $eventdata['syndication_account'];
                        $mastodonAPI = $this->connect($username);
                    } else {
                        $mastodonAPI = $this->connect();
                        $screenName = isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['screen_name'])
                                    ? \Idno\Core\Idno::site()->session()->currentUser()->mastodon['screen_name']
                                    : false;
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
                    $status = $this->truncate($status,$object_type, $permalink, $permashortlink);

                    $statuses = array('status' => $status,
                        'sensitive' => $nfsw);


                        // Find any Mastodon status IDs in case we need to mark this as a reply to them
                        $inreplytourls = array_merge((array) $object->inreplyto, (array) $object->syndicatedto);
                        //if ($inreplyto = self::findMastoStatus($inreplytourls, $screenName)) {
                        if ($inreplyto = $this->findMastoStatus($inreplytourls, $screenName)) {
                            $statuses['in_reply_to_id'] = $inreplyto['status_id'];

                            \Idno\Core\Idno::site()->logging()->log("Mastodon post to reply to: " . var_export($inreplyto, true));

                            // if inreplytoname is not in the note status, and is not this user's name, then prepend it to the status
                            $replyName = $inreplyto['screen_name'];
                            if ($replyName
                                    && mb_strtolower($screenName) !== mb_strtolower($replyName)
                                    && mb_stristr($status, '@'.$replyName) === false) {
                                $statuses['status'] = '@' . $replyName . ' ' . $status;
                            }
                        }

                    $server = $this->getServer();

                    if (!empty($eventdata['syndication_account'])) {
                         $res = $this->postStatus($statuses, $username);
                    } else {
                        $res = $this->postStatus($statuses);
                    }
                    $response = json_decode($res['content']);
                    $id = $response->id;
                    if (!empty($response)) {
                        if (!empty($id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            if (!empty($eventdata['syndication_account']))
                                $mastodon_user = $eventdata['syndication_account'];
                            $object->setPosseLink('mastodon', $response->url, $mastodon_user, $id, $mastodon_user);
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($response, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                }
            });

            // Push "images" to a Mastodon instance
            //\Idno\Core\Idno::site()->addEventHook('post/image/mastodon', function (\Idno\Core\Event $event) {
            \Idno\Core\Idno::site()->events()->addListener('post/image/mastodon', function (\Idno\Core\Event $event) {
                if ($this->hasMastodon()) {
                    $eventdata = $event->data();
                    $object = $eventdata['object'];
                    $object_type = $eventdata['object_type'];
                    if (!empty($eventdata['syndication_account'])) {
                        $username = $eventdata['syndication_account'];
                        $screenName = $eventdata['syndication_account'];
                        $mastodonAPI = $this->connect($username);
                    } else {
                        $mastodonAPI = $this->connect();
                        $screenName = isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['screen_name'])
                                    ? \Idno\Core\Idno::site()->session()->currentUser()->mastodon['screen_name']
                                    : false;
                    }
                    $server = $this->getServer();
                    $status = $object->getTitle();
                    $status = html_entity_decode($status);
                    // Permalink will be included if the status message is truncated
                    $permalink = $object->getSyndicationURL();
                    // Add link to original post, if IndieWeb references have been requested
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;

                    $status = $this->truncate($status, $object_type, $permalink, $permashortlink);

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

/*
    # Mastodon.media_post(media_file, mime_type=None, description=None, focus=None)
*/

                        foreach ($attachments as $attachment) {
                            if ($bytes = \Idno\Entities\File::getFileDataFromAttachment($attachment)) {
                                $filename = tempnam(sys_get_temp_dir(), 'knownmastodon');
                                file_put_contents($filename, $bytes);
                                $params['description'] = $status;
                                $params['file'] = $filename;
                                $params['filename'] = basename($filename);
                                $params['mime-type'] = $attachment['mime-type'];
                                $response = $this->postMedia($params, $username);
                                $content = json_decode($response['content']);

                                if (!empty($content->id)) {
                                    //$media_ids = $content->id;
                                    $media_ids[] = $content->id;
                                } else {
                                    \Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : we haz no response from mastodon " . $server);
                                }
                            }
                        }
                    }
                    if (!empty($media_ids)) {
                        $statuses = array('status' => $status,
                            'media_ids' => $media_ids,
                            'sensitive' => $nsfw);

                        // Find any Mastodon status IDs in case we need to mark this as a reply to them
                        $inreplytourls = array_merge((array) $object->inreplyto, (array) $object->syndicatedto);
                        //if ($inreplyto = self::findMastoStatus($inreplytourls, $screenName)) {
                        if ($inreplyto = $this->findMastoStatus($inreplytourls, $screenName)) {
                            $statuses['in_reply_to_id'] = $inreplyto['status_id'];

                            \Idno\Core\Idno::site()->logging()->log("Mastodon post to reply to: " . var_export($inreplyto, true));

                            // if inreplytoname is not in the image status, and is not this user's name, then prepend it to the status
                            $replyName = $inreplyto['screen_name'];
                            if ($replyName
                                    && mb_strtolower($screenName) !== mb_strtolower($replyName)
                                    && mb_stristr($status, '@'.$replyName) === false) {
                                $statuses['status'] = '@' . $replyName . ' ' . $status;
                            }
                        }
                        try {
                            $res = $this->postStatus($statuses, $username);
                    \Idno\Core\Idno::site()->logging()->log("Mastodon posting Payload: " . var_export($statuses, true));
                            $response = json_decode($res['content']);
                        } catch (\Exception $e) {
                            \Idno\Core\Idno::site()->logging()->log($e);
                        }
                    }
                    \Idno\Core\Idno::site()->logging()->log("Mastodon posting Response: " . var_export($response, true));

                    @unlink($filename);
                    if (!empty($response)) {
                        if (!empty($response->id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            if (!empty($eventdata['syndication_account']))
                                $mastodon_user = $eventdata['syndication_account'];
                            $object->setPosseLink('mastodon', $response->url, $mastodon_user, $response->id, $mastodon_user);
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
                    if (!empty($eventdata['syndication_account'])) {
                        $username = $eventdata['syndication_account'];
                        \Idno\Core\Idno::site()->logging()->log("Mastodon username: " . $username);
                        $mastodonAPI = $this->connect($username);
                    } else {
                        $mastodonAPI = $this->connect();
                    }
                    $object_type = $eventdata['object_type'];
                    $object = $eventdata['object'];
                    $server = $this->getServer(); // returning empty
                    $status = $object->getTitle();
                    $permalink = $object->getSyndicationURL();
                    $permashortlink = \Idno\Core\Idno::site()->config()->indieweb_reference ? $object->getShortURL() : false;
                    \Idno\Core\Idno::site()->logging()->log("Mastodon PERMASHORT: " . var_export($permashortlink, true));
                    // false

                    $status = html_entity_decode($status);

                    $status = $this->truncate($status, $object_type, $permalink, $permashortlink);
                    $statuses = array('status' => $status);

                        // Find any Mastodon status IDs in case we need to mark this as a reply to them
                        $inreplytourls = array_merge((array) $object->inreplyto, (array) $object->syndicatedto);
                        //if ($inreplyto = self::findMastoStatus($inreplytourls, $screenName)) {
                        if ($inreplyto = $this->findMastoStatus($inreplytourls, $screenName)) {
                            $statuses['in_reply_to_id'] = $inreplyto['status_id'];

                            \Idno\Core\Idno::site()->logging()->log("Mastodon post to reply to: " . var_export($inreplyto, true));

                            // if inreplytoname is not in the status, and is not this user's name, then prepend it to the status
                            $replyName = $inreplyto['screen_name'];
                            if ($replyName
                                    && mb_strtolower($screenName) !== mb_strtolower($replyName)
                                    && mb_stristr($status, '@'.$replyName) === false) {
                                $statuses['status'] = '@' . $replyName . ' ' . $status;
                            }
                        }

                    // $res = $this->postStatus($statuses, $username);
                    // $response = json_decode($res['content']);
                    // $id = $response->id;
                        try {
                            $res = $this->postStatus($statuses, $username);
                            $response = json_decode($res['content']);
                        } catch (\Exception $e) {
                            \Idno\Core\Idno::site()->logging()->log($e);
                        }
                    \Idno\Core\Idno::site()->logging()->log("Mastodon posting Response: " . var_export($response, true));
                    if (!empty($response)) {
                        if (!empty($response->id)) {
                            $mastodon_user = $response->account->username . "@" . $server;
                            //$object->setPosseLink('mastodon', $response['url'], $response['id'], $response['account']['username']);
                            $object->setPosseLink('mastodon', $response->url, $username, $response->id, $username);
                            \Idno\Core\Idno::site()->logging()->log("Posted to Mastodon: " . var_export($response->url, true));
                            $object->save();
                        } else {
                            \Idno\Core\Idno::site()->logging()->log("Nothing was posted to Mastodon: " . var_export($response, true));
                            \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                        }
                    }
                }
            };

            // Push "articles" and "rsvps" to Mastodon
            //\Idno\Core\Idno::site()->addEventHook('post/article/mastodon', $article_handler);
            \Idno\Core\Idno::site()->events()->addListener('post/article/mastodon', $article_handler);
            \Idno\Core\Idno::site()->events()->addListener('post/rsvp/mastodon', $article_handler);
            \Idno\Core\Idno::site()->events()->addListener('post/bookmark/mastodon', $article_handler);
        }

        /**
         *
         * @param array $status
         * @return object
         */
        function postStatus($status, $username='') {
            //$status['visibility'] = "private"; // direct
            $status['visibility'] = "public"; // unlisted
            $mID = "";
            if (!empty($status['media_ids'])) {
                $media_ids = $status['media_ids'];
                unset($status['media_ids']);
                foreach ($media_ids as $id) {
                    $mID = $mID . "&media_ids[]=".$id;
                }
            }
            // split text at || for content warning
            $cwstatus = explode("||", $status['status'], 2);
            if (!empty($cwstatus[1])) {
                $status['status'] = $cwstatus[1];
                $status['spoiler_text'] = $cwstatus[0];
            }
            $status = http_build_query($status).$mID;

            $server = $this->getServer();
            $credentials = $this->getCredentials($username);
            $bearer = $credentials['bearer'];
            $server = $credentials['server'];
            $instance = "https://" . $server . '/api/v1/statuses';
            $headers = array('Accept: application/json',
                'Authorization: Bearer ' . $bearer . "");
            $result = \Idno\Core\Webservice::post($instance, $status, $headers);

            return $result;
        }

/*
    # Mastodon.media_post(media_file, mime_type=None, description=None, focus=None)
*/
        function postMedia($params, $username='') {
            $desc = $params['description'];
            $file = $params['file'];
            $filename = $params['filename'];
            $mime = $params['mime-type'];
            $server = $this->getServer();
            $credentials = $this->getCredentials($username);
            $bearer = $credentials['bearer'];
            $server = $credentials['server'];
            //\Idno\Core\Idno::site()->logging()->log("Mastodon Media Debug : MEDIA  PARAMS " . var_export($params));

            $instance = "https://" . $server . '/api/v1/media';
            $result = \Idno\Core\Webservice::post($instance, [
                        'file' => \Idno\Core\WebserviceFile::createFromCurlString("@" . $file . ";filename=" . $filename . ";type=" . $mime),
                        'description' => $desc
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
        function truncate($status, $format = false, $permalink = false, $shortlink = false, $length = 500) {
            $status = trim($status);
            $truncated = false;
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
            $hellip = mb_convert_encoding('&hellip; ', 'UTF-8', 'HTML-ENTITIES');
            $length = $length - strlen($hellip);

            if (strlen($status) > $length) {
                $status = wordwrap($status);
                $string = explode("\n", $status, 2);
                $status = $string[0] . $hellip;
                $status = $status . $permalink;
                $truncated = true;
            }
            // $status = $status . $permalink . $shortlink;
            //add $permalink to bookmark if not truncated
            if ($format === 'bookmark' && ($truncated == false)) {
                $status = $status . $permalink;
            }
            if ($format === 'article' && ($truncated == false)) {
                $status = $status . $permalink;
            }

            return $status;
        }

            /**
             * Search a list of URLs for one that looks like a Toot
             * permalink and return an array with the Toot's
             * 'status_id' and 'screen_name'.
             * @param array urls
             * @param string username
             * @return array or false
             */
            private function findMastoStatus($urls, $username='')
            {
                $server = $this->getServer();
                if (strlen($server) === 0 && strlen($username) > 1) {
                  $pieces = explode('@', $username);
                  $server = $pieces[1];
                }
                foreach ($urls as $url) {
                  //  if (preg_match('/(www\.|m\.)?twitter.com/i', parse_url($url, PHP_URL_HOST))) {
                    if ( !strstr($url, '@') ) {
                        return false;
                    }

                        $path = explode('/', parse_url($url, PHP_URL_PATH));

                            \Idno\Core\Idno::site()->logging()->log("findMastoStatus: \$path: " . var_export($path, true));

                        if (count($path) >= 3) {
                            $path = array_reverse($path);
                            $status_id = $path[0];
                            // find internal reference for a federated post
                            if ($server !== parse_url($url, PHP_URL_HOST)) {
                                $credentials = $this->getCredentials($username);
                                $bearer = $credentials['bearer'];
                                $server = $credentials['server'];
                                $instance = "https://" . $server . "/api/v2/search?q=";
                                $instance .= $url; // += is not string concatenation
                                $headers = array('Accept: application/json',
                                    'Authorization: Bearer ' . $bearer . "");
                                $result = \Idno\Core\Webservice::get($instance, $status, $headers);

                                $response = json_decode($result['content']);
                                $id = $response->statuses[0]->id;
                                if (!empty($response)) {
                                    if (!empty($id)) {
                                        $status_id = $id;
                                    } else {
                                        \Idno\Core\Idno::site()->logging()->log("Nothing was found on Mastodon: " . var_export($response, true));
                                    //    \Idno\Core\Idno::site()->logging()->log("Mastodon tokens: " . var_export(\Idno\Core\Idno::site()->session()->currentUser()->Mastodon, true));
                                    }
                                }
                            }

                            $responses = [
                                'screen_name' => substr($path[1],1) . '@' . parse_url($url, PHP_URL_HOST),
                                'status_id'   => $status_id,
                            ];
                            \Idno\Core\Idno::site()->logging()->log("findMastoStatus: \$responses: " . var_export($responses, true));
                            return $responses;
                        }
                  //  }
                }
                return false;
            }

        function getCredentials($server = false) {
            if (empty($server)) {
                $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
            }
            $credentials = array(); // the following pair: unnecessary?
            $credentials['client_id'] = \Idno\Core\Idno::site()->config()->mastodon[$server][0]['client_id'];
            $credentials['client_secret'] = \Idno\Core\Idno::site()->config()->mastodon[$server][0]['client_secret'];
            if (empty($server)) {
                $credentials['bearer'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['bearer'];
                $credentials['server'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
            } else {
                $credentials['bearer'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon[$server]['bearer'];
                $credentials['server'] = \Idno\Core\Idno::site()->session()->currentUser()->mastodon[$server]['server'];
            }
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
            // require_once(dirname(__FILE__) . '/external/theCodingCompany/Mastodon.php');
            if (!empty(\Idno\Core\Idno::site()->config()->mastodon)) {
                $callback = \Idno\Core\Idno::site()->config()->getDisplayURL() . "mastodon/callback/";
                                if (!empty($server) && isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon[$server])) {
                    $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon[$server]['server'];
                }
                else if (empty($server) && isset(\Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'])) {
                    $server = \Idno\Core\Idno::site()->session()->currentUser()->mastodon['server'];
                }
                return new \theCodingCompany\Mastodon($callback, $server);
            }
            return false;
        }

        /**
         *  Mastodon app–creation magic
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

